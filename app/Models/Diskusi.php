<?php
// Ruang diskusi siswa dan guru.
// Ruang: kelas_id NULL = semua kelas (siswa dari kelas mana pun bisa membaca dan ikut bertanya), selain itu hanya kelas tersebut.
// Topik: modul_id NULL = diskusi umum, selain itu diskusi tentang modul tertentu.
final class Diskusi
{
    public const PER_HALAMAN = 30;
    public const BATAS_KIRIM = 12;      // maksimal pesan per pengguna dalam 10 menit (cegah spam)

    private const KOLOM = "t.*, u.nama AS penulis_nama, u.peran AS penulis_peran, k.nama AS kelas_nama, m.nomor AS modul_nomor, m.judul AS modul_judul,
        (SELECT COUNT(*) FROM diskusi_balasan b WHERE b.topik_id = t.id) AS jumlah_balasan,
        (SELECT COUNT(*) FROM diskusi_ikut i WHERE i.topik_id = t.id) AS jumlah_ikut,
        (SELECT COUNT(*) FROM diskusi_balasan b JOIN users ub ON ub.id = b.penulis_id WHERE b.topik_id = t.id AND ub.peran IN ('guru','admin')) AS balasan_guru,
        (SELECT COUNT(*) FROM diskusi_ikut i WHERE i.topik_id = t.id AND i.pengguna_id = ?) AS saya_ikut";
    private const DARI = ' FROM diskusi_topik t JOIN users u ON u.id = t.penulis_id LEFT JOIN kelas k ON k.id = t.kelas_id LEFT JOIN modul m ON m.id = t.modul_id';

    /** Id kelas yang ruangnya boleh dibuka pengguna. Null = semua (admin). */
    public static function kelasBoleh(array $u): ?array
    {
        if ($u['peran'] === 'admin') {
            return null;
        }
        if ($u['peran'] === 'guru') {
            return array_map('intval', array_column(Kelas::milikGuru((int) $u['id']), 'id'));
        }
        $k = DB::nilai('SELECT kelas_id FROM kelas_anggota WHERE siswa_id = ?', [$u['id']]);
        return $k ? [(int) $k] : [];
    }

    public static function bolehLihat(array $t, array $u): bool
    {
        if ($t['kelas_id'] === null) {
            return true;
        }
        $boleh = self::kelasBoleh($u);
        return $boleh === null || in_array((int) $t['kelas_id'], $boleh, true);
    }

    /** Guru pengajar ruang itu (atau guru mana pun untuk ruang semua kelas) dan admin boleh memoderasi. */
    public static function bolehModerasi(array $t, array $u): bool
    {
        return $u['peran'] === 'admin' || ($u['peran'] === 'guru' && self::bolehLihat($t, $u));
    }

    public static function guru(array $u): bool
    {
        return in_array($u['peran'], ['guru', 'admin'], true);
    }

    /**
     * Daftar topik yang boleh dilihat, dengan saringan:
     * ruang '' (semua yang boleh) | 'umum' (semua kelas) | id kelas; modul '' | 'umum' | id modul; status '' | 'belum' (belum dijawab guru) | 'selesai' | 'saya'; cari teks.
     */
    public static function daftar(array $u, array $f, int $hal = 1): array
    {
        $w = [];
        $p = [(int) $u['id']];
        $boleh = self::kelasBoleh($u);
        if ($boleh !== null) {
            $w[] = $boleh ? '(t.kelas_id IS NULL OR t.kelas_id IN (' . implode(',', array_fill(0, count($boleh), '?')) . '))' : 't.kelas_id IS NULL';
            array_push($p, ...$boleh);
        }
        if (($f['ruang'] ?? '') === 'umum') {
            $w[] = 't.kelas_id IS NULL';
        } elseif (ctype_digit((string) ($f['ruang'] ?? ''))) {
            $w[] = 't.kelas_id = ?';
            $p[] = (int) $f['ruang'];
        }
        if (($f['modul'] ?? '') === 'umum') {
            $w[] = 't.modul_id IS NULL';
        } elseif (ctype_digit((string) ($f['modul'] ?? ''))) {
            $w[] = 't.modul_id = ?';
            $p[] = (int) $f['modul'];
        }
        $w[] = match ($f['status'] ?? '') {
            'belum' => "t.selesai = 0 AND NOT EXISTS (SELECT 1 FROM diskusi_balasan b JOIN users ub ON ub.id = b.penulis_id WHERE b.topik_id = t.id AND ub.peran IN ('guru','admin'))",
            'selesai' => 't.selesai = 1',
            'saya' => '(t.penulis_id = ? OR EXISTS (SELECT 1 FROM diskusi_ikut i WHERE i.topik_id = t.id AND i.pengguna_id = ?))',
            default => '1 = 1',
        };
        if (($f['status'] ?? '') === 'saya') {
            array_push($p, (int) $u['id'], (int) $u['id']);
        }
        $q = trim((string) ($f['q'] ?? ''));
        if ($q !== '') {
            $w[] = "(t.judul LIKE ? ESCAPE '!' OR t.isi LIKE ? ESCAPE '!')";
            $pola = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_substr($q, 0, 60)) . '%';
            array_push($p, $pola, $pola);
        }
        $where = $w ? ' WHERE ' . implode(' AND ', $w) : '';
        $total = (int) DB::nilai('SELECT COUNT(*)' . self::DARI . $where, array_slice($p, 1));
        $mulai = (max(1, $hal) - 1) * self::PER_HALAMAN;
        $baris = DB::semua('SELECT ' . self::KOLOM . self::DARI . $where . ' ORDER BY t.disematkan DESC, t.aktivitas_pada DESC, t.id DESC LIMIT ' . self::PER_HALAMAN . ' OFFSET ' . $mulai, $p);
        return ['baris' => $baris, 'total' => $total];
    }

    public static function topik(int $id, int $penggunaId): ?array
    {
        return DB::satu('SELECT ' . self::KOLOM . self::DARI . ' WHERE t.id = ?', [$penggunaId, $id]);
    }

    public static function balasan(int $topikId): array
    {
        return DB::semua(
            'SELECT b.*, u.nama AS penulis_nama, u.peran AS penulis_peran, k.nama AS kelas_nama FROM diskusi_balasan b JOIN users u ON u.id = b.penulis_id
             LEFT JOIN kelas_anggota a ON a.siswa_id = u.id LEFT JOIN kelas k ON k.id = a.kelas_id WHERE b.topik_id = ? ORDER BY b.terpilih DESC, b.id',
            [$topikId]
        );
    }

    public static function balasanById(int $id): ?array
    {
        return DB::satu('SELECT * FROM diskusi_balasan WHERE id = ?', [$id]);
    }

    /** Sudah terlalu banyak mengirim dalam 10 menit terakhir? */
    public static function terlaluCepat(int $uid): bool
    {
        $sejak = date('Y-m-d H:i:s', time() - 600);
        $n = (int) DB::nilai('SELECT COUNT(*) FROM diskusi_topik WHERE penulis_id = ? AND dibuat_pada >= ?', [$uid, $sejak])
           + (int) DB::nilai('SELECT COUNT(*) FROM diskusi_balasan WHERE penulis_id = ? AND dibuat_pada >= ?', [$uid, $sejak]);
        return $n >= self::BATAS_KIRIM;
    }

    public static function buat(?int $kelasId, ?int $modulId, int $uid, string $judul, string $isi): int
    {
        return DB::sisip(
            'INSERT INTO diskusi_topik (kelas_id, modul_id, penulis_id, judul, isi, disematkan, selesai, dibuat_pada, aktivitas_pada) VALUES (?, ?, ?, ?, ?, 0, 0, ?, ?)',
            [$kelasId, $modulId, $uid, $judul, $isi, sekarang(), sekarang()]
        );
    }

    public static function balas(int $topikId, int $uid, string $isi): int
    {
        $id = DB::sisip('INSERT INTO diskusi_balasan (topik_id, penulis_id, isi, terpilih, dibuat_pada) VALUES (?, ?, ?, 0, ?)', [$topikId, $uid, $isi, sekarang()]);
        DB::jalankan('UPDATE diskusi_topik SET aktivitas_pada = ? WHERE id = ?', [sekarang(), $topikId]);
        return $id;
    }

    /** Tambah/hapus "Saya juga ingin tahu". Mengembalikan status baru. */
    public static function ikut(int $topikId, int $uid): bool
    {
        if (DB::nilai('SELECT id FROM diskusi_ikut WHERE topik_id = ? AND pengguna_id = ?', [$topikId, $uid])) {
            DB::jalankan('DELETE FROM diskusi_ikut WHERE topik_id = ? AND pengguna_id = ?', [$topikId, $uid]);
            return false;
        }
        DB::jalankan('INSERT INTO diskusi_ikut (topik_id, pengguna_id, dibuat_pada) VALUES (?, ?, ?)', [$topikId, $uid, sekarang()]);
        return true;
    }

    public static function atur(int $topikId, string $kolom, bool $nilai): void
    {
        if (!in_array($kolom, ['selesai', 'disematkan'], true)) {
            throw new InvalidArgumentException('Kolom tidak dikenal');
        }
        DB::jalankan("UPDATE diskusi_topik SET $kolom = ? WHERE id = ?", [$nilai ? 1 : 0, $topikId]);
    }

    /** Tandai satu balasan sebagai jawaban (hanya satu per topik); topik ikut ditandai selesai. */
    public static function pilihJawaban(array $b, bool $pilih): void
    {
        DB::transaksi(function () use ($b, $pilih) {
            DB::jalankan('UPDATE diskusi_balasan SET terpilih = 0 WHERE topik_id = ?', [$b['topik_id']]);
            if ($pilih) {
                DB::jalankan('UPDATE diskusi_balasan SET terpilih = 1 WHERE id = ?', [$b['id']]);
                DB::jalankan('UPDATE diskusi_topik SET selesai = 1 WHERE id = ?', [$b['topik_id']]);
            }
        });
    }

    public static function hapusTopik(int $id): void
    {
        DB::jalankan('DELETE FROM diskusi_topik WHERE id = ?', [$id]);
    }

    public static function hapusBalasan(int $id): void
    {
        DB::jalankan('DELETE FROM diskusi_balasan WHERE id = ?', [$id]);
    }

    /** Jumlah pertanyaan yang belum dibalas guru di ruang yang bisa dilihat guru ini. */
    public static function belumDijawab(array $u): int
    {
        return self::daftar($u, ['status' => 'belum'])['total'];
    }

    /** Jumlah topik untuk satu modul yang boleh dilihat pengguna. */
    public static function jumlahModul(array $u, int $modulId): int
    {
        return self::daftar($u, ['modul' => (string) $modulId])['total'];
    }
}
