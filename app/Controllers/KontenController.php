<?php
// Editor isi modul untuk guru dan admin: praktikum, pengumpulan, LKPD, refleksi, soal remidi, dan soal pengayaan.
// Juga unggah gambar praktikum, hapus modul, dan kelola folder simulasi (unggah ZIP khusus admin).
final class KontenController extends Kontroler
{
    private function modul(array $p): array
    {
        $m = Modul::byId($this->id($p));
        if (!$m) {
            Respon::galat(404, 'Modul tidak ditemukan.');
        }
        return $m;
    }

    private function jenis(array $p): string
    {
        $j = (string) ($p['jenis'] ?? '');
        if (!isset(Konten::JENIS[$j])) {
            Respon::galat(404, 'Jenis isi tidak dikenal.');
        }
        return $j;
    }

    /** Status kunci isi: apakah sudah ada jawaban siswa yang membuat sebagian isi tidak boleh diubah. */
    public static function kunci(array $m, string $jenis): array
    {
        $id = (int) $m['id'];
        return match ($jenis) {
            'lkpd' => [
                'terkumpul' => (int) DB::nilai("SELECT COUNT(*) FROM lkpd_pengumpulan WHERE modul_id = ? AND (status = 'dikumpulkan' OR jawaban <> '{}')", [$id]) > 0,
                'dinilai' => (int) DB::nilai('SELECT COUNT(*) FROM lkpd_pengumpulan WHERE modul_id = ? AND rubrik_skor IS NOT NULL', [$id]) > 0,
            ],
            'lembar' => ['terkumpul' => (int) DB::nilai("SELECT COUNT(*) FROM lembar_kerja WHERE modul_id = ? AND status = 'dikumpulkan'", [$id]) > 0],
            'remidi', 'pengayaan' => ['terkumpul' => Tindak::adaJawaban($id, $jenis)],
            default => [],
        };
    }

    public function editor(array $p): void
    {
        $m = $this->modul($p);
        $jenis = $this->jenis($p);
        $this->tampil('kelola/konten', [
            'm' => $m, 'jenis' => $jenis, 'dataIsi' => Konten::ambil((int) $m['nomor'], $jenis), 'info' => Konten::info($m, $jenis),
            'kunci' => self::kunci($m, $jenis), 'riwayat' => Konten::riwayat((int) $m['id'], $jenis),
            'gambar' => self::daftarGambar((int) $m['nomor']),
        ]);
    }

    public function simpan(array $p): void
    {
        $m = $this->modul($p);
        $jenis = $this->jenis($p);
        $j = input_json();
        if (!is_array($j['isi'] ?? null)) {
            Respon::json(['ok' => false, 'galat' => ['Data tidak terbaca. Muat ulang halaman lalu coba lagi.']], 422);
        }
        $lama = Konten::ambil((int) $m['nomor'], $jenis);
        [$isi, $galat] = KontenValidasi::periksa($jenis, $j['isi'], (int) $m['nomor'], $lama, self::kunci($m, $jenis));
        if ($galat) {
            Respon::json(['ok' => false, 'galat' => array_values(array_unique($galat))], 422);
        }
        Konten::simpan((int) $m['id'], $jenis, $isi, Auth::id());
        Respon::json(['ok' => true, 'pesan' => Konten::JENIS[$jenis]['nama'] . ' Modul ' . $m['nomor'] . ' disimpan.', 'isi' => $isi, 'waktu' => date('H:i')]);
    }

    public function kosongkan(array $p): void
    {
        $m = $this->modul($p);
        $jenis = $this->jenis($p);
        $k = self::kunci($m, $jenis);
        if (!empty($k['terkumpul'])) {
            flash('galat', 'Isi ini sudah dipakai siswa, jadi tidak bisa dikosongkan.');
        } else {
            Konten::simpan((int) $m['id'], $jenis, null, Auth::id());
            flash('sukses', Konten::JENIS[$jenis]['nama'] . ' Modul ' . $m['nomor'] . ' dikosongkan. Langkah ini tidak tampil untuk siswa sampai diisi lagi.');
        }
        Respon::alihkan('/kelola/modul/' . $m['id'] . '/isi/' . $jenis);
    }

    public function pulihkan(array $p): void
    {
        $m = $this->modul($p);
        $jenis = $this->jenis($p);
        $v = Konten::versi($this->id($p, 'rid'), (int) $m['id'], $jenis);
        $balik = '/kelola/modul/' . $m['id'] . '/isi/' . $jenis;
        if (!$v) {
            flash('galat', 'Versi tidak ditemukan.');
            Respon::alihkan($balik);
        }
        if ($v['isi'] === null) {
            if (!empty(self::kunci($m, $jenis)['terkumpul'])) {
                flash('galat', 'Versi itu kosong, sedangkan isi ini sudah dipakai siswa.');
                Respon::alihkan($balik);
            }
            Konten::simpan((int) $m['id'], $jenis, null, Auth::id());
        } else {
            [$isi, $galat] = KontenValidasi::periksa($jenis, $v['isi'], (int) $m['nomor'], Konten::ambil((int) $m['nomor'], $jenis), self::kunci($m, $jenis));
            if ($galat) {
                flash('galat', "Versi itu tidak bisa dipulihkan:\n• " . implode("\n• ", array_slice($galat, 0, 5)));
                Respon::alihkan($balik);
            }
            Konten::simpan((int) $m['id'], $jenis, $isi, Auth::id());
        }
        flash('sukses', 'Versi lama dipulihkan.');
        Respon::alihkan($balik);
    }

    // ---------- gambar praktikum ----------
    public static function daftarGambar(int $nomor): array
    {
        $dir = ROOT . '/public/assets/praktikum/p' . $nomor;
        $g = is_dir($dir) ? array_values(array_filter(scandir($dir), fn($f) => preg_match('/\.(png|jpe?g|webp|gif)$/i', $f))) : [];
        sort($g);
        return $g;
    }

    public function unggahGambar(array $p): void
    {
        $m = $this->modul($p);
        $f = $_FILES['gambar'] ?? null;
        if (!$f || $f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
            Respon::json(['ok' => false, 'pesan' => 'Unggahan gagal (maks. 5 MB).'], 422);
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
        $ext = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/gif' => 'gif'][$mime] ?? null;
        if (!$ext || $f['size'] > 5 * 1024 * 1024 || @getimagesize($f['tmp_name']) === false) {
            Respon::json(['ok' => false, 'pesan' => 'Berkas harus gambar PNG, JPG, WEBP, atau GIF, maksimal 5 MB.'], 422);
        }
        $dir = ROOT . '/public/assets/praktikum/p' . (int) $m['nomor'];
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            Respon::json(['ok' => false, 'pesan' => 'Folder gambar tidak bisa dibuat di server.'], 500);
        }
        $nama = 'u-' . date('ymd') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
        if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $nama)) {
            Respon::json(['ok' => false, 'pesan' => 'Gambar tidak bisa disimpan di server.'], 500);
        }
        @chmod($dir . '/' . $nama, 0644);
        Respon::json(['ok' => true, 'nama' => $nama, 'url' => asset('praktikum/p' . (int) $m['nomor'] . '/' . $nama)]);
    }

    // ---------- hapus modul ----------
    public static function adaDataSiswa(int $modulId): bool
    {
        foreach (['modul_siswa', 'lkpd_pengumpulan', 'lembar_kerja'] as $t) {
            if ((int) DB::nilai("SELECT COUNT(*) FROM $t WHERE modul_id = ?", [$modulId]) > 0) {
                return true;
            }
        }
        return Tindak::adaJawaban($modulId, null);
    }

    public function hapusModul(array $p): void
    {
        $m = $this->modul($p);
        if (self::adaDataSiswa((int) $m['id'])) {
            flash('galat', 'Modul ' . $m['nomor'] . ' sudah punya data siswa, jadi tidak bisa dihapus. Ubah statusnya menjadi Terkunci bila tidak dipakai lagi.');
            Respon::alihkan('/kelola/modul/' . $m['id']);
        }
        if ((string) ($_POST['konfirmasi'] ?? '') !== (string) $m['nomor']) {
            flash('galat', 'Ketik nomor modul (' . $m['nomor'] . ') untuk mengonfirmasi penghapusan.');
            Respon::alihkan('/kelola/modul/' . $m['id']);
        }
        if ($m['materi_berkas']) {
            @unlink(ROOT . '/storage/uploads/materi/' . basename($m['materi_berkas']));
        }
        foreach (Modul::DOKUMEN as $d) {
            if (!empty($m[$d['kolom']])) {
                @unlink(ROOT . '/storage/uploads/dokumen/' . basename($m[$d['kolom']]));
            }
        }
        DB::jalankan('DELETE FROM lembar_akses WHERE modul_id = ?', [$m['id']]);
        DB::jalankan('DELETE FROM modul WHERE id = ?', [$m['id']]);
        flash('sukses', 'Modul ' . $m['nomor'] . ' (' . $m['judul'] . ') dihapus.');
        Respon::alihkan('/kelola/modul');
    }

    // ---------- dokumen PDF: modul ajar (guru) dan bahan bacaan (siswa) ----------
    private function jenisDokumen(array $p): string
    {
        $j = (string) ($p['jenis'] ?? '');
        if (!isset(Modul::DOKUMEN[$j])) {
            Respon::galat(404, 'Dokumen tidak dikenal.');
        }
        return $j;
    }

    public function dokumen(array $p): void
    {
        $m = $this->modul($p);
        $j = $this->jenisDokumen($p);
        $d = Modul::dokumen($m, $j);
        if ($d['jalan'] === '') {
            Respon::galat(404, Modul::DOKUMEN[$j]['nama'] . ' untuk modul ini belum ada.');
        }
        Respon::berkas($d['jalan'], Modul::DOKUMEN[$j]['unduh'] . $m['nomor'] . '.pdf', 'application/pdf', isset($_GET['unduh']));
    }

    public function unggahDokumen(array $p): void
    {
        $m = $this->modul($p);
        $j = $this->jenisDokumen($p);
        $nama = Modul::DOKUMEN[$j]['nama'];
        $balik = '/kelola/modul/' . $m['id'];
        $f = $_FILES['berkas'] ?? null;
        if (!$f || $f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
            flash('galat', 'Unggahan gagal. Pastikan ukuran PDF tidak melebihi batas server (maks. 25 MB).');
            Respon::alihkan($balik);
        }
        if ($f['size'] > 25 * 1024 * 1024 || strtolower(pathinfo($f['name'], PATHINFO_EXTENSION)) !== 'pdf'
            || (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']) !== 'application/pdf') {
            flash('galat', 'Berkas harus PDF dan maksimal 25 MB. Dokumen Word disimpan sebagai PDF dulu.');
            Respon::alihkan($balik);
        }
        $dir = ROOT . '/storage/uploads/dokumen';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $baru = $j . '-p' . $m['nomor'] . '-' . bin2hex(random_bytes(6)) . '.pdf';
        move_uploaded_file($f['tmp_name'], $dir . '/' . $baru);
        $lama = (string) ($m[Modul::DOKUMEN[$j]['kolom']] ?? '');
        if ($lama !== '') {
            @unlink($dir . '/' . basename($lama));
        }
        Modul::setDokumen((int) $m['id'], $j, $baru);
        flash('sukses', $nama . ' Modul ' . $m['nomor'] . ' diunggah.');
        Respon::alihkan($balik);
    }

    public function hapusDokumen(array $p): void
    {
        $m = $this->modul($p);
        $j = $this->jenisDokumen($p);
        $lama = (string) ($m[Modul::DOKUMEN[$j]['kolom']] ?? '');
        if ($lama !== '') {
            @unlink(ROOT . '/storage/uploads/dokumen/' . basename($lama));
        }
        Modul::setDokumen((int) $m['id'], $j, null);
        $sisa = Modul::dokumen(Modul::byId((int) $m['id']), $j);
        flash('sukses', 'Unggahan dihapus. ' . ($sisa['sumber'] === 'bawaan' ? 'Sekarang memakai ' . mb_strtolower(Modul::DOKUMEN[$j]['nama']) . ' bawaan.' : 'Modul ini belum punya ' . mb_strtolower(Modul::DOKUMEN[$j]['nama']) . '.'));
        Respon::alihkan('/kelola/modul/' . $m['id']);
    }

    // ---------- simulasi ----------
    private const EKS_SIMULASI = ['html', 'htm', 'css', 'js', 'mjs', 'json', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'ico', 'mp3', 'wav', 'ogg', 'mp4', 'webm', 'woff', 'woff2', 'ttf', 'otf', 'txt', 'md', 'wasm', 'pck', 'csv'];

    public static function daftarSimulasi(): array
    {
        $hasil = [];
        foreach (glob(ROOT . '/public/games/*', GLOB_ONLYDIR) ?: [] as $d) {
            if (is_file($d . '/index.html')) {
                $nama = basename($d);
                $hasil[] = ['folder' => $nama, 'dipakai' => DB::semua('SELECT nomor, judul FROM modul WHERE game_folder = ? ORDER BY nomor', [$nama]), 'diubah' => date('Y-m-d H:i', (int) filemtime($d . '/index.html'))];
            }
        }
        return $hasil;
    }

    public function simulasi(array $p): void
    {
        $this->tampil('kelola/simulasi', ['daftar' => self::daftarSimulasi(), 'admin' => Auth::pengguna()['peran'] === 'admin']);
    }

    public function unggahSimulasi(array $p): void
    {
        $balik = '/kelola/simulasi';
        $folder = strtolower($this->teks('folder', 60));
        $timpa = !empty($_POST['timpa']);
        if (!preg_match('/^[a-z0-9][a-z0-9-]{1,40}$/', $folder)) {
            flash('galat', 'Nama folder hanya huruf kecil, angka, dan tanda minus (contoh: simulasi-p5).');
            Respon::alihkan($balik);
        }
        $tujuan = ROOT . '/public/games/' . $folder;
        if (is_dir($tujuan) && !$timpa) {
            flash('galat', 'Folder ' . $folder . ' sudah ada. Centang "Timpa" untuk memperbarui isinya.');
            Respon::alihkan($balik);
        }
        $f = $_FILES['zip'] ?? null;
        if (!$f || $f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name']) || $f['size'] > 40 * 1024 * 1024) {
            flash('galat', 'Unggahan gagal. ZIP maksimal 40 MB (dan tidak melebihi batas upload server).');
            Respon::alihkan($balik);
        }
        if (!class_exists('ZipArchive')) {
            flash('galat', 'Server belum mendukung ZIP (ekstensi PHP zip tidak aktif).');
            Respon::alihkan($balik);
        }
        $zip = new ZipArchive();
        if ($zip->open($f['tmp_name']) !== true) {
            flash('galat', 'Berkas bukan ZIP yang valid.');
            Respon::alihkan($balik);
        }
        // Periksa semua isi dulu: jalur aman, ekstensi diizinkan, ada index.html.
        $nama = [];
        $total = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $st = $zip->statIndex($i);
            $n = str_replace('\\', '/', (string) $st['name']);
            if (str_ends_with($n, '/') || str_starts_with($n, '__MACOSX/') || basename($n) === '.DS_Store') {
                continue;
            }
            $total += (int) $st['size'];
            if (str_contains($n, '../') || str_starts_with($n, '/') || preg_match('/^[a-z]:/i', $n) || !preg_match('~^[A-Za-z0-9 ._()/-]+$~', $n)) {
                $zip->close();
                flash('galat', 'Nama berkas tidak aman di dalam ZIP: ' . $n);
                Respon::alihkan($balik);
            }
            $ext = strtolower(pathinfo($n, PATHINFO_EXTENSION));
            if (!in_array($ext, self::EKS_SIMULASI, true) || str_starts_with(basename($n), '.')) {
                $zip->close();
                flash('galat', 'Jenis berkas tidak diizinkan di dalam simulasi: ' . $n . '. Yang boleh: ' . implode(', ', self::EKS_SIMULASI) . '.');
                Respon::alihkan($balik);
            }
            $nama[$i] = $n;
        }
        if ($total > 150 * 1024 * 1024 || count($nama) > 2000) {
            $zip->close();
            flash('galat', 'Isi ZIP terlalu besar (maks. 150 MB setelah diekstrak, 2000 berkas).');
            Respon::alihkan($balik);
        }
        // index.html boleh di akar ZIP atau di dalam satu folder teratas.
        $awalan = '';
        if (!in_array('index.html', $nama, true)) {
            $calon = array_values(array_filter($nama, fn($n) => preg_match('~^[^/]+/index\.html$~', $n)));
            if (count($calon) !== 1) {
                $zip->close();
                flash('galat', 'ZIP harus berisi index.html (di akar ZIP atau di dalam satu folder).');
                Respon::alihkan($balik);
            }
            $awalan = dirname($calon[0]) . '/';
        }
        $sementara = ROOT . '/storage/cache/sim-' . bin2hex(random_bytes(5));
        @mkdir($sementara, 0755, true);
        foreach ($nama as $i => $n) {
            if ($awalan !== '' && !str_starts_with($n, $awalan)) {
                continue;
            }
            $rel = substr($n, strlen($awalan));
            $jalan = $sementara . '/' . $rel;
            @mkdir(dirname($jalan), 0755, true);
            file_put_contents($jalan, $zip->getFromIndex($i));
        }
        $zip->close();
        if (is_dir($tujuan)) {
            self::hapusFolder($tujuan);
        }
        if (!@rename($sementara, $tujuan)) {
            self::hapusFolder($sementara);
            flash('galat', 'Folder simulasi tidak bisa dipasang di server (izin tulis public/games).');
            Respon::alihkan($balik);
        }
        flash('sukses', 'Simulasi ' . $folder . ' terpasang. Pilih folder ini di halaman Edit modul.');
        Respon::alihkan($balik);
    }

    public function hapusSimulasi(array $p): void
    {
        $folder = (string) ($p['folder'] ?? '');
        if (!preg_match('/^[a-z0-9][a-z0-9_-]{1,40}$/i', $folder) || !is_dir(ROOT . '/public/games/' . $folder)) {
            Respon::galat(404);
        }
        if (DB::nilai('SELECT COUNT(*) FROM modul WHERE game_folder = ?', [$folder])) {
            flash('galat', 'Simulasi ' . $folder . ' masih dipakai modul. Lepaskan dulu dari modulnya.');
        } else {
            self::hapusFolder(ROOT . '/public/games/' . $folder);
            flash('sukses', 'Simulasi ' . $folder . ' dihapus.');
        }
        Respon::alihkan('/kelola/simulasi');
    }

    private static function hapusFolder(string $d): void
    {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($d, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($d);
    }
}
