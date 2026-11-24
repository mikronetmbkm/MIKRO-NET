<?php
// Tindak lanjut hasil belajar: soal remidi (perbaikan) dan pengayaan per modul per siswa.
// Soal dari Konten (jenis 'remidi' / 'pengayaan', format sama dengan LKPD); jawaban siswa di tabel tindak_lanjut.
final class Tindak
{
    public const JENIS = ['remidi' => 'Remidi', 'pengayaan' => 'Pengayaan'];

    /** Sudah ada siswa yang mengumpulkan soal jenis ini (null = jenis apa pun) untuk modul tersebut? */
    public static function adaJawaban(int $modulId, ?string $jenis): bool
    {
        $sql = "SELECT COUNT(*) FROM tindak_lanjut WHERE modul_id = ? AND status = 'dikumpulkan'" . ($jenis ? ' AND jenis = ?' : '');
        return (int) DB::nilai($sql, $jenis ? [$modulId, $jenis] : [$modulId]) > 0;
    }

    public static function definisi(int $nomor, string $jenis): ?array
    {
        return isset(self::JENIS[$jenis]) ? Lembar::definisiJenis($nomor, $jenis) : null;
    }

    private static function buka(?array $r): ?array
    {
        if ($r) {
            $r['jawaban_arr'] = json_decode((string) $r['jawaban'], true) ?: [];
            $r['skor_uraian_arr'] = $r['skor_uraian'] ? (json_decode((string) $r['skor_uraian'], true) ?: []) : [];
        }
        return $r;
    }

    public static function ambil(int $modulId, int $siswaId, string $jenis): ?array
    {
        return self::buka(DB::satu('SELECT * FROM tindak_lanjut WHERE modul_id = ? AND siswa_id = ? AND jenis = ?', [$modulId, $siswaId, $jenis]));
    }

    public static function byId(int $id): ?array
    {
        return self::buka(DB::satu('SELECT * FROM tindak_lanjut WHERE id = ?', [$id]));
    }

    public static function pastikan(int $modulId, int $siswaId, string $jenis): array
    {
        $r = self::ambil($modulId, $siswaId, $jenis);
        if (!$r) {
            DB::jalankan(
                'INSERT INTO tindak_lanjut (modul_id, siswa_id, jenis, jawaban, status, dibuat_pada, diperbarui_pada) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$modulId, $siswaId, $jenis, '{}', 'draf', sekarang(), sekarang()]
            );
            $r = self::ambil($modulId, $siswaId, $jenis);
        }
        return $r;
    }

    public static function simpanJawaban(int $id, array $def, array $baru): void
    {
        $lama = self::byId($id)['jawaban_arr'] ?? [];
        DB::jalankan('UPDATE tindak_lanjut SET jawaban = ?, diperbarui_pada = ? WHERE id = ?', [json_encode(Lembar::saring($def, $lama, $baru), JSON_UNESCAPED_UNICODE), sekarang(), $id]);
    }

    public static function kumpulkan(array $t, array $def): void
    {
        $skor = Lembar::jumlahBenar($def, $t['jawaban_arr']) * Lembar::poinPg($def);
        $nilai = empty($def['uraian']) ? (int) round($skor / max(1, Lembar::maksUntuk($def, $t['jawaban_arr'])) * 100) : null;
        DB::jalankan(
            "UPDATE tindak_lanjut SET status = 'dikumpulkan', skor_pg = ?, nilai = ?, dikumpulkan_pada = ?, diperbarui_pada = ? WHERE id = ?",
            [$skor, $nilai, sekarang(), sekarang(), $t['id']]
        );
    }

    public static function nilaiUraian(array $t, array $def, array $skor, string $catatan): int
    {
        $nilai = (int) round(((int) $t['skor_pg'] + array_sum($skor)) / max(1, Lembar::maksUntuk($def, $t['jawaban_arr'])) * 100);
        DB::jalankan(
            'UPDATE tindak_lanjut SET skor_uraian = ?, nilai = ?, catatan = ?, dinilai_pada = ?, diperbarui_pada = ? WHERE id = ?',
            [json_encode(array_values($skor)), $nilai, $catatan, sekarang(), sekarang(), $t['id']]
        );
        return $nilai;
    }

    public static function kembalikan(int $id): void
    {
        DB::jalankan("UPDATE tindak_lanjut SET status = 'draf', skor_pg = NULL, skor_uraian = NULL, nilai = NULL, dikumpulkan_pada = NULL, dinilai_pada = NULL, diperbarui_pada = ? WHERE id = ?", [sekarang(), $id]);
    }

    /** Remidi/pengayaan yang sudah dikumpulkan tetapi uraiannya belum dinilai, untuk kelas tertentu. */
    public static function menungguKelas(int $kelasId, ?int $modulId = null): array
    {
        return DB::semua(
            "SELECT t.id, t.jenis, t.modul_id, t.dikumpulkan_pada, u.nama, u.nis, m.nomor FROM tindak_lanjut t
             JOIN kelas_anggota a ON a.siswa_id = t.siswa_id JOIN users u ON u.id = t.siswa_id JOIN modul m ON m.id = t.modul_id
             WHERE a.kelas_id = ? AND t.status = 'dikumpulkan' AND t.nilai IS NULL" . ($modulId ? ' AND t.modul_id = ?' : '') . ' ORDER BY t.dikumpulkan_pada',
            $modulId ? [$kelasId, $modulId] : [$kelasId]
        );
    }
}
