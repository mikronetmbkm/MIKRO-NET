<?php
// Isi modul yang bisa diedit guru: praktikum, pengumpulan (lkpd), LKPD (lembar), refleksi, remidi, pengayaan.
// Disimpan di tabel modul_konten (format JSON yang sama dengan berkas database/content/*-p{nomor}.json).
// Bila belum pernah diedit, isi dibaca dari berkas bawaan. Isi JSON "null" berarti sengaja dikosongkan guru.
final class Konten
{
    public const JENIS = [
        'praktikum' => ['berkas' => 'praktikum', 'nama' => 'Praktikum', 'ket' => 'Langkah praktik bergambar, perintah, tabel, dan checkpoint'],
        'lkpd' => ['berkas' => 'lkpd', 'nama' => 'Pengumpulan', 'ket' => 'Isian hasil praktik kelompok, unggah berkas, dan rubrik penilaian'],
        'lembar' => ['berkas' => 'lembar', 'nama' => 'LKPD', 'ket' => 'Soal pilihan ganda (dinilai otomatis) dan uraian (dinilai guru)'],
        'refleksi' => ['berkas' => 'refleksi', 'nama' => 'Refleksi', 'ket' => 'Pertanyaan refleksi khusus modul (pertanyaan umum selalu ada)'],
        'remidi' => ['berkas' => 'remidi', 'nama' => 'Soal remidi', 'ket' => 'Soal perbaikan untuk siswa yang belum tuntas'],
        'pengayaan' => ['berkas' => 'pengayaan', 'nama' => 'Soal pengayaan', 'ket' => 'Soal tantangan untuk siswa yang sudah tuntas'],
    ];

    private static array $cache = [];

    /** Isi untuk modul bernomor $nomor. Null = tidak ada (langkah dianggap belum tersedia). */
    public static function ambil(int $nomor, string $jenis): ?array
    {
        $k = $nomor . ':' . $jenis;
        if (array_key_exists($k, self::$cache)) {
            return self::$cache[$k];
        }
        $r = DB::satu('SELECT k.isi FROM modul_konten k JOIN modul m ON m.id = k.modul_id WHERE m.nomor = ? AND k.jenis = ?', [$nomor, $jenis]);
        if ($r) {
            $d = json_decode((string) $r['isi'], true);
            return self::$cache[$k] = is_array($d) ? $d : null;
        }
        $f = ROOT . '/database/content/' . self::JENIS[$jenis]['berkas'] . '-p' . $nomor . '.json';
        $d = is_file($f) ? json_decode((string) file_get_contents($f), true) : null;
        return self::$cache[$k] = is_array($d) ? $d : null;
    }

    /** Asal isi: 'db' (diedit guru), 'berkas' (bawaan), atau null (belum ada). Beserta siapa/kapan mengubah. */
    public static function info(array $modul, string $jenis): array
    {
        $r = DB::satu('SELECT k.isi, k.diubah_pada, u.nama FROM modul_konten k LEFT JOIN users u ON u.id = k.diubah_oleh WHERE k.modul_id = ? AND k.jenis = ?', [$modul['id'], $jenis]);
        if ($r) {
            return ['sumber' => 'db', 'kosong' => json_decode((string) $r['isi'], true) === null, 'oleh' => $r['nama'], 'pada' => $r['diubah_pada']];
        }
        $ada = is_file(ROOT . '/database/content/' . self::JENIS[$jenis]['berkas'] . '-p' . (int) $modul['nomor'] . '.json');
        return ['sumber' => $ada ? 'berkas' : null, 'kosong' => !$ada, 'oleh' => null, 'pada' => null];
    }

    /** Simpan isi baru (null = kosongkan). Versi lama disimpan di riwayat agar bisa dipulihkan. */
    public static function simpan(int $modulId, string $jenis, ?array $isi, int $olehId): void
    {
        $lama = DB::satu('SELECT isi, diubah_oleh, diubah_pada FROM modul_konten WHERE modul_id = ? AND jenis = ?', [$modulId, $jenis]);
        $json = json_encode($isi, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        DB::transaksi(function () use ($lama, $modulId, $jenis, $json, $olehId) {
            if ($lama) {
                DB::jalankan('INSERT INTO modul_konten_riwayat (modul_id, jenis, isi, diubah_oleh, diubah_pada) VALUES (?, ?, ?, ?, ?)', [$modulId, $jenis, $lama['isi'], $lama['diubah_oleh'], $lama['diubah_pada']]);
                DB::jalankan('UPDATE modul_konten SET isi = ?, diubah_oleh = ?, diubah_pada = ? WHERE modul_id = ? AND jenis = ?', [$json, $olehId, sekarang(), $modulId, $jenis]);
            } else {
                // Edit pertama atas isi bawaan: simpan isi bawaan ke riwayat agar selalu bisa dikembalikan.
                $f = ROOT . '/database/content/' . self::JENIS[$jenis]['berkas'] . '-p' . (int) DB::nilai('SELECT nomor FROM modul WHERE id = ?', [$modulId]) . '.json';
                if (is_file($f) && is_array(json_decode((string) file_get_contents($f), true))) {
                    DB::jalankan('INSERT INTO modul_konten_riwayat (modul_id, jenis, isi, diubah_oleh, diubah_pada) VALUES (?, ?, ?, NULL, ?)', [$modulId, $jenis, (string) file_get_contents($f), date('Y-m-d H:i:s', (int) filemtime($f))]);
                }
                DB::jalankan('INSERT INTO modul_konten (modul_id, jenis, isi, diubah_oleh, diubah_pada) VALUES (?, ?, ?, ?, ?)', [$modulId, $jenis, $json, $olehId, sekarang()]);
            }
            // Simpan paling banyak 20 versi lama per isi.
            $id = DB::nilai('SELECT id FROM modul_konten_riwayat WHERE modul_id = ? AND jenis = ? ORDER BY id DESC LIMIT 1 OFFSET 19', [$modulId, $jenis]);
            if ($id) {
                DB::jalankan('DELETE FROM modul_konten_riwayat WHERE modul_id = ? AND jenis = ? AND id < ?', [$modulId, $jenis, $id]);
            }
        });
        self::$cache = [];
    }

    public static function riwayat(int $modulId, string $jenis): array
    {
        return DB::semua('SELECT r.id, r.diubah_pada, u.nama FROM modul_konten_riwayat r LEFT JOIN users u ON u.id = r.diubah_oleh WHERE r.modul_id = ? AND r.jenis = ? ORDER BY r.id DESC', [$modulId, $jenis]);
    }

    public static function versi(int $riwayatId, int $modulId, string $jenis): ?array
    {
        $r = DB::satu('SELECT isi FROM modul_konten_riwayat WHERE id = ? AND modul_id = ? AND jenis = ?', [$riwayatId, $modulId, $jenis]);
        return $r ? ['isi' => json_decode((string) $r['isi'], true)] : null;
    }

    /** Apakah modul bernomor $n punya isi bawaan (berkas konten, slide, gambar, atau video)? Dipakai agar nomor modul tidak dipakai ulang. */
    public static function adaBawaan(int $n): bool
    {
        return (bool) glob(ROOT . '/database/content/*-p' . $n . '.{json,pdf}', GLOB_BRACE)
            || is_dir(ROOT . '/public/assets/praktikum/p' . $n) || is_file(ROOT . '/public/assets/video/materi-p' . $n . '.mp4');
    }
}
