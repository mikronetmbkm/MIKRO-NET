<?php
// Model Kelas: kelas, kode kelas, dan anggota kelas.
final class Kelas
{
    public static function byKode(string $kode): ?array
    {
        return DB::satu(
            'SELECT k.*, u.nama AS guru_nama FROM kelas k JOIN users u ON u.id = k.guru_id WHERE k.kode = ? AND k.aktif = 1',
            [strtoupper(trim($kode))]
        );
    }

    public static function byId(int $id): ?array
    {
        return DB::satu('SELECT * FROM kelas WHERE id = ?', [$id]);
    }

    /** Syarat SQL "guru ? mengajar kelas k": pembuat kelas atau guru pendamping. Butuh dua parameter guru_id. */
    private const AKSES = '(k.guru_id = ? OR EXISTS (SELECT 1 FROM kelas_guru kg WHERE kg.kelas_id = k.id AND kg.guru_id = ?))';

    /** Kelas yang diajar guru ini (sebagai pembuat atau guru pendamping). */
    public static function milikGuru(int $guruId): array
    {
        return self::denganGuru(DB::semua('SELECT k.* FROM kelas k WHERE k.aktif = 1 AND ' . self::AKSES . ' ORDER BY k.nama', [$guruId, $guruId]));
    }

    public static function semua(): array
    {
        return self::denganGuru(DB::semua(
            'SELECT k.*, u.nama AS guru_nama, (SELECT COUNT(*) FROM kelas_anggota a WHERE a.kelas_id = k.id) AS jumlah_siswa
             FROM kelas k JOIN users u ON u.id = k.guru_id ORDER BY k.nama'
        ));
    }

    /** Apakah guru ini mengajar kelas tersebut? */
    public static function diajar(int $kelasId, int $guruId): bool
    {
        return (bool) DB::nilai('SELECT k.id FROM kelas k WHERE k.id = ? AND ' . self::AKSES, [$kelasId, $guruId, $guruId]);
    }

    /** Semua guru pengajar sebuah kelas; pembuat kelas paling atas (pembuat = 1). */
    public static function guru(int $kelasId): array
    {
        return DB::semua(
            'SELECT u.id, u.nama, u.email, 1 AS pembuat FROM kelas k JOIN users u ON u.id = k.guru_id WHERE k.id = ?
             UNION ALL
             SELECT u.id, u.nama, u.email, 0 AS pembuat FROM kelas_guru kg JOIN users u ON u.id = kg.guru_id WHERE kg.kelas_id = ?
             ORDER BY pembuat DESC, nama',
            [$kelasId, $kelasId]
        );
    }

    /** Tambahkan $daftar['guru'] (daftar nama guru pengajar) ke setiap kelas. */
    private static function denganGuru(array $daftar): array
    {
        foreach ($daftar as &$k) {
            $k['guru'] = self::guru((int) $k['id']);
            $k['guru_nama_semua'] = implode(', ', array_column($k['guru'], 'nama'));
        }
        unset($k);
        return $daftar;
    }

    public static function tambahGuru(int $kelasId, int $guruId): void
    {
        if (!self::diajar($kelasId, $guruId)) {
            DB::jalankan('INSERT INTO kelas_guru (kelas_id, guru_id, ditambahkan_pada) VALUES (?, ?, ?)', [$kelasId, $guruId, sekarang()]);
        }
    }

    public static function hapusGuru(int $kelasId, int $guruId): void
    {
        DB::jalankan('DELETE FROM kelas_guru WHERE kelas_id = ? AND guru_id = ?', [$kelasId, $guruId]);
    }

    /** Apakah NIS ini sudah dipakai siswa lain di kelas yang sama? (NIS boleh sama di kelas berbeda.) */
    public static function nisDipakai(int $kelasId, string $nis, int $kecualiSiswa = 0): bool
    {
        return (bool) DB::nilai(
            'SELECT u.id FROM kelas_anggota a JOIN users u ON u.id = a.siswa_id WHERE a.kelas_id = ? AND u.nis = ? AND u.id <> ?',
            [$kelasId, $nis, $kecualiSiswa]
        );
    }

    public static function dariSiswa(int $siswaId): ?array
    {
        return DB::satu(
            'SELECT k.* FROM kelas k JOIN kelas_anggota a ON a.kelas_id = k.id WHERE a.siswa_id = ?',
            [$siswaId]
        );
    }

    public static function anggota(int $kelasId): array
    {
        return DB::semua(
            'SELECT u.id, u.nama, u.nis, u.email, a.bergabung_pada
             FROM kelas_anggota a JOIN users u ON u.id = a.siswa_id
             WHERE a.kelas_id = ? ORDER BY u.nama',
            [$kelasId]
        );
    }

    public static function buat(string $nama, int $guruId): array
    {
        // Kode harus unik; coba lagi kalau kebetulan bentrok.
        for ($i = 0; $i < 10; $i++) {
            $kode = buat_kode_kelas($nama);
            if (!DB::nilai('SELECT id FROM kelas WHERE kode = ?', [$kode])) {
                $id = DB::sisip('INSERT INTO kelas (nama, kode, guru_id, aktif, dibuat_pada) VALUES (?, ?, ?, 1, ?)', [$nama, $kode, $guruId, sekarang()]);
                return ['id' => $id, 'kode' => $kode];
            }
        }
        throw new RuntimeException('Gagal membuat kode kelas unik');
    }

    public static function kodeBaru(int $kelasId): string
    {
        $k = self::byId($kelasId);
        for ($i = 0; $i < 10; $i++) {
            $kode = buat_kode_kelas($k['nama']);
            if (!DB::nilai('SELECT id FROM kelas WHERE kode = ?', [$kode])) {
                DB::jalankan('UPDATE kelas SET kode = ? WHERE id = ?', [$kode, $kelasId]);
                return $kode;
            }
        }
        throw new RuntimeException('Gagal membuat kode kelas unik');
    }

    public static function keluarkan(int $kelasId, int $siswaId): void
    {
        DB::jalankan('DELETE FROM kelas_anggota WHERE kelas_id = ? AND siswa_id = ?', [$kelasId, $siswaId]);
    }
}
