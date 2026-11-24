<?php
// Model Pengguna: semua urusan tabel `users`.
final class Pengguna
{
    public static function byId(int $id): ?array
    {
        return DB::satu('SELECT * FROM users WHERE id = ?', [$id]);
    }

    public static function byEmail(string $email): ?array
    {
        return DB::satu('SELECT * FROM users WHERE email = ?', [strtolower(trim($email))]);
    }

    public static function emailAda(string $email): bool
    {
        return (bool) DB::nilai('SELECT id FROM users WHERE email = ?', [strtolower(trim($email))]);
    }

    /** Format NIS yang diterima: 1-30 karakter huruf/angka/titik/strip (nomor absen seperti "7" juga boleh). */
    public static function nisValid(string $nis): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9.\-]{1,30}$/', $nis);
    }

    public static function ubahDataSiswa(int $id, string $nama, string $nis): void
    {
        DB::jalankan('UPDATE users SET nama = ?, nis = ?, diperbarui_pada = ? WHERE id = ? AND peran = ?', [$nama, $nis, sekarang(), $id, 'siswa']);
    }

    /** Guru aktif (untuk pilihan guru pendamping kelas). */
    public static function guruAktif(): array
    {
        return DB::semua("SELECT id, nama, email FROM users WHERE peran = 'guru' AND aktif = 1 ORDER BY nama");
    }

    /** Buat akun siswa sekaligus memasukkannya ke kelas (satu transaksi: berhasil semua atau batal semua). */
    public static function buatSiswa(string $nama, string $nis, string $email, string $sandi, int $kelasId): int
    {
        $pdo = DB::pdo();
        $pdo->beginTransaction();
        try {
            $id = DB::sisip(
                'INSERT INTO users (nama, email, password_hash, peran, nis, aktif, harus_ganti_password, dibuat_pada, diperbarui_pada) VALUES (?, ?, ?, ?, ?, 1, 0, ?, ?)',
                [$nama, strtolower(trim($email)), password_hash($sandi, PASSWORD_DEFAULT), 'siswa', $nis, sekarang(), sekarang()]
            );
            DB::jalankan('INSERT INTO kelas_anggota (kelas_id, siswa_id, bergabung_pada) VALUES (?, ?, ?)', [$kelasId, $id, sekarang()]);
            $pdo->commit();
            return $id;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function buatGuru(string $nama, string $email, string $sandiSementara): int
    {
        return DB::sisip(
            'INSERT INTO users (nama, email, password_hash, peran, nis, aktif, harus_ganti_password, dibuat_pada, diperbarui_pada) VALUES (?, ?, ?, ?, NULL, 1, 1, ?, ?)',
            [$nama, strtolower(trim($email)), password_hash($sandiSementara, PASSWORD_DEFAULT), 'guru', sekarang(), sekarang()]
        );
    }

    public static function setSandi(int $id, string $sandi, bool $wajibGanti): void
    {
        DB::jalankan(
            'UPDATE users SET password_hash = ?, harus_ganti_password = ?, diperbarui_pada = ? WHERE id = ?',
            [password_hash($sandi, PASSWORD_DEFAULT), $wajibGanti ? 1 : 0, sekarang(), $id]
        );
    }

    public static function setAktif(int $id, bool $aktif): void
    {
        DB::jalankan('UPDATE users SET aktif = ?, diperbarui_pada = ? WHERE id = ?', [$aktif ? 1 : 0, sekarang(), $id]);
    }

    /** Daftar pengguna untuk halaman admin, dengan nama kelas (untuk siswa dan guru). */
    public static function daftar(string $peran = '', string $cari = ''): array
    {
        $sql = 'SELECT u.id, u.nama, u.email, u.peran, u.nis, u.aktif,
                       COALESCE(ks.nama, (SELECT GROUP_CONCAT(k2.nama) FROM kelas k2 WHERE k2.guru_id = u.id OR k2.id IN (SELECT kg.kelas_id FROM kelas_guru kg WHERE kg.guru_id = u.id))) AS kelas_nama, a.kelas_id
                FROM users u
                LEFT JOIN kelas_anggota a ON a.siswa_id = u.id
                LEFT JOIN kelas ks ON ks.id = a.kelas_id
                WHERE 1 = 1';
        $p = [];
        if (in_array($peran, ['admin', 'guru', 'siswa'], true)) {
            $sql .= ' AND u.peran = ?';
            $p[] = $peran;
        }
        if ($cari !== '') {
            $sql .= ' AND (u.nama LIKE ? OR u.email LIKE ? OR u.nis = ?)';
            $p[] = '%' . $cari . '%';
            $p[] = '%' . $cari . '%';
            $p[] = $cari;
        }
        $sql .= ' ORDER BY u.peran, u.nama LIMIT 200';
        return DB::semua($sql, $p);
    }

    public static function hitung(string $peran): int
    {
        return (int) DB::nilai('SELECT COUNT(*) FROM users WHERE peran = ?', [$peran]);
    }
}
