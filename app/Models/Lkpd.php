<?php
// Model LKPD: definisi soal (dari berkas JSON), jawaban siswa, unggahan, dan penilaian.
final class Lkpd
{
    /** Definisi LKPD dibaca dari database/content/lkpd-p{nomor}.json. Null = belum ada. */
    public static function definisi(int $nomor): ?array
    {
        $d = Konten::ambil($nomor, 'lkpd');
        return is_array($d) && !empty($d['bagian']) ? $d : null;
    }

    /** Semua butir soal dalam satu daftar datar: id => butir. */
    public static function butir(array $def): array
    {
        $hasil = [];
        foreach ($def['bagian'] ?? [] as $b) {
            foreach ($b['butir'] ?? [] as $x) {
                $hasil[$x['id']] = $x;
            }
        }
        return $hasil;
    }

    public static function ambil(int $modulId, int $siswaId): ?array
    {
        $r = DB::satu('SELECT * FROM lkpd_pengumpulan WHERE modul_id = ? AND siswa_id = ?', [$modulId, $siswaId]);
        if ($r) {
            $r['jawaban_arr'] = json_decode((string) $r['jawaban'], true) ?: [];
        }
        return $r;
    }

    public static function byId(int $id): ?array
    {
        $r = DB::satu('SELECT * FROM lkpd_pengumpulan WHERE id = ?', [$id]);
        if ($r) {
            $r['jawaban_arr'] = json_decode((string) $r['jawaban'], true) ?: [];
        }
        return $r;
    }

    public static function pastikan(int $modulId, int $siswaId): array
    {
        $r = self::ambil($modulId, $siswaId);
        if (!$r) {
            DB::jalankan(
                'INSERT INTO lkpd_pengumpulan (modul_id, siswa_id, jawaban, status, dibuat_pada, diperbarui_pada) VALUES (?, ?, ?, ?, ?, ?)',
                [$modulId, $siswaId, '{}', 'draf', sekarang(), sekarang()]
            );
            $r = self::ambil($modulId, $siswaId);
        }
        return $r;
    }

    /** Simpan jawaban. Hanya id butir yang ada di definisi yang diterima (daftar putih). */
    public static function simpanJawaban(int $pengumpulanId, array $def, array $jawaban): void
    {
        $butir = self::butir($def);
        $lama = self::byId($pengumpulanId)['jawaban_arr'] ?? [];
        foreach ($jawaban as $id => $nilai) {
            if (!isset($butir[$id]) || $butir[$id]['jenis'] === 'berkas' || !is_string($nilai)) {
                continue;
            }
            $nilai = mb_substr($nilai, 0, 5000);
            if ($butir[$id]['jenis'] === 'pilihan' && $nilai !== '' && !in_array($nilai, $butir[$id]['opsi'] ?? [], true)) {
                continue;
            }
            $lama[$id] = $nilai;
        }
        DB::jalankan(
            'UPDATE lkpd_pengumpulan SET jawaban = ?, diperbarui_pada = ? WHERE id = ?',
            [json_encode($lama, JSON_UNESCAPED_UNICODE), sekarang(), $pengumpulanId]
        );
    }

    public static function kumpulkan(int $pengumpulanId): void
    {
        DB::jalankan(
            "UPDATE lkpd_pengumpulan SET status = 'dikumpulkan', dikumpulkan_pada = ?, diperbarui_pada = ? WHERE id = ?",
            [sekarang(), sekarang(), $pengumpulanId]
        );
    }

    public static function kembalikan(int $pengumpulanId): void
    {
        DB::jalankan(
            "UPDATE lkpd_pengumpulan SET status = 'draf', dikumpulkan_pada = NULL, rubrik_skor = NULL, skor = NULL, skor_maks = NULL, nilai = NULL, dinilai_pada = NULL, diperbarui_pada = ? WHERE id = ?",
            [sekarang(), $pengumpulanId]
        );
    }

    public static function berkas(int $pengumpulanId): array
    {
        return DB::semua('SELECT * FROM lkpd_berkas WHERE pengumpulan_id = ? ORDER BY id', [$pengumpulanId]);
    }

    public static function berkasById(int $id): ?array
    {
        return DB::satu('SELECT b.*, p.siswa_id, p.modul_id FROM lkpd_berkas b JOIN lkpd_pengumpulan p ON p.id = b.pengumpulan_id WHERE b.id = ?', [$id]);
    }

    public static function nilai(int $pengumpulanId, array $skorRubrik, int $skorMaks, string $catatan): int
    {
        $skor = array_sum($skorRubrik);
        $nilai = $skorMaks > 0 ? (int) round($skor / $skorMaks * 100) : 0;
        DB::jalankan(
            'UPDATE lkpd_pengumpulan SET rubrik_skor = ?, skor = ?, skor_maks = ?, nilai = ?, catatan = ?, dinilai_pada = ?, diperbarui_pada = ? WHERE id = ?',
            [json_encode(array_values($skorRubrik)), $skor, $skorMaks, $nilai, $catatan, sekarang(), sekarang(), $pengumpulanId]
        );
        return $nilai;
    }

    /** Status pengumpulan semua siswa dalam satu kelas untuk satu modul. */
    public static function rekapKelas(int $kelasId, int $modulId): array
    {
        return DB::semua(
            'SELECT u.id AS siswa_id, u.nama, u.nis, p.id AS pengumpulan_id, p.status, p.dikumpulkan_pada, p.nilai,
                    l.id AS lembar_id, l.status AS lembar_status, l.skor_pg AS lembar_skor_pg, l.nilai AS lembar_nilai
             FROM kelas_anggota a
             JOIN users u ON u.id = a.siswa_id
             LEFT JOIN lkpd_pengumpulan p ON p.siswa_id = u.id AND p.modul_id = ?
             LEFT JOIN lembar_kerja l ON l.siswa_id = u.id AND l.modul_id = ?
             WHERE a.kelas_id = ? ORDER BY u.nama',
            [$modulId, $modulId, $kelasId]
        );
    }
}
