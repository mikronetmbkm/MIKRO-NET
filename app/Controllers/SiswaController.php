<?php
// Semua halaman siswa: beranda, daftar modul, isi modul (materi, simulasi, praktikum, pengumpulan), nilai, alat bantu.
final class SiswaController extends Kontroler
{
    public function beranda(array $p): void
    {
        $u = Auth::pengguna();
        $uid = (int) $u['id'];
        $kelas = Kelas::dariSiswa($uid);
        $semua = Modul::semua();

        $dimulai = 0;
        $lkpdKumpul = 0;
        $lkpdTotal = 0;
        $lanjut = null;
        $tugas = [];
        foreach ($semua as $m) {
            if ($m['status'] !== 'terbit') {
                continue;
            }
            $langkah = Modul::langkah($m, $uid);
            $selesai = count(array_filter($langkah, fn($l) => $l['status'] === 'selesai'));
            $mulai = count(array_filter($langkah, fn($l) => in_array($l['status'], ['selesai', 'berjalan'], true)));
            $lkpdTotal++;
            $l = array_values(array_filter($langkah, fn($x) => $x['kunci'] === 'lkpd'))[0];
            if ($l['status'] === 'selesai') {
                $lkpdKumpul++;
            } elseif ($l['status'] !== 'segera') {
                $tugas[] = ['judul' => 'Pengumpulan Pertemuan ' . $m['nomor'], 'ket' => 'Belum dikumpulkan', 'href' => '/modul/' . $m['id'] . '/lkpd', 'modul' => $m['nomor']];
            }
            if ($mulai > 0) {
                $dimulai++;
            }
            $aturan ??= Nilai::aturanSiswa($uid);
            $nm = Nilai::modul($m, $uid, $aturan);
            if ($nm['status'] === 'remidi' && $nm['adaRemidi'] && !($nm['remidi'] && $nm['remidi']['status'] === 'dikumpulkan')) {
                $tugas[] = ['judul' => 'Remidi Modul ' . $m['nomor'], 'ket' => 'Nilai modul ' . $nm['awal'] . ', KKM ' . $nm['kkm'] . '. Kerjakan soal perbaikan.', 'href' => '/modul/' . $m['id'] . '/remidi', 'modul' => $m['nomor'], 'aksi' => $nm['remidi'] ? 'Lanjutkan' : 'Kerjakan'];
            }
            if (!$lanjut && $selesai < count($langkah)) {
                $lanjut = ['modul' => $m, 'langkah' => $langkah, 'selesai' => $selesai];
            }
        }
        $kuis = Kuis::ringkasanSiswa($uid);
        foreach ($kuis as $jenis => $x) {
            if (in_array($x['status'], ['siap', 'berjalan'], true)) {
                $tugas[] = ['judul' => $x['kuis']['judul'], 'ket' => $x['status'] === 'berjalan' ? 'Sedang dikerjakan, waktunya berjalan' : 'Sudah dibuka gurumu', 'href' => '/kuis/' . $jenis, 'aksi' => $x['status'] === 'berjalan' ? 'Lanjutkan' : 'Mulai'];
            }
        }
        $depan = null;
        foreach ($semua as $m) {
            if ($m['tanggal'] && $m['tanggal'] >= date('Y-m-d')) {
                $depan = $m;
                break;
            }
        }
        $this->tampil('siswa/beranda', [
            'kelas' => $kelas, 'semua' => $semua, 'dimulai' => $dimulai, 'lkpdKumpul' => $lkpdKumpul, 'lkpdTotal' => $lkpdTotal,
            'lanjut' => $lanjut, 'tugas' => $tugas, 'depan' => $depan, 'kuis' => $kuis,
        ]);
    }

    public function daftarModul(array $p): void
    {
        $uid = Auth::id();
        $baris = [];
        foreach (Modul::semua() as $m) {
            $persen = null;
            if ($m['status'] === 'terbit') {
                $persen = Modul::persen(Modul::langkah($m, $uid));
            }
            $baris[] = ['m' => $m, 'persen' => $persen];
        }
        $this->tampil('siswa/modul', ['baris' => $baris]);
    }

    public function detailModul(array $p): void
    {
        $m = $this->modulTerbuka($p);
        $langkah = Modul::langkah($m, Auth::id());
        $this->tampil('siswa/modul-detail', ['m' => $m, 'langkah' => $langkah, 'pre' => Kuis::ringkasanSiswa(Auth::id())['pretest'] ?? null, 'persen' => Modul::persen($langkah), 'tujuan' => Modul::tujuanDaftar($m),
            'nilai' => Nilai::modul($m, Auth::id(), Nilai::aturanSiswa(Auth::id())), 'jumlahDiskusi' => Diskusi::jumlahModul(Auth::pengguna(), (int) $m['id'])]);
    }

    // ---------- Materi ----------
    public function materi(array $p): void
    {
        $m = $this->modulTerbuka($p);
        $b = Modul::berkasMateri($m);
        $langkah = Modul::langkah($m, Auth::id());
        $this->tampil('siswa/materi', ['m' => $m, 'langkah' => $langkah, 'berikut' => Modul::berikut($langkah, 'materi'), 'ada' => $b['jalan'] !== '', 'contoh' => $b['contoh'], 'p' => Modul::progres((int) $m['id'], Auth::id()), 'video' => Modul::video($m), 'bacaan' => Modul::dokumen($m, 'bacaan')]);
    }

    public function materiPdf(array $p): void
    {
        $m = $this->modulTerbuka($p);
        $b = Modul::berkasMateri($m);
        if ($b['jalan'] === '') {
            Respon::galat(404, 'Materi belum diunggah.');
        }
        Respon::berkas($b['jalan'], 'materi-' . $m['nomor'] . '.pdf', 'application/pdf');
    }

    /** Bahan bacaan PDF untuk siswa (bukan modul ajar guru). */
    public function bacaan(array $p): void
    {
        $m = $this->modulTerbuka($p);
        $d = Modul::dokumen($m, 'bacaan');
        if ($d['jalan'] === '') {
            Respon::galat(404, 'Bahan bacaan untuk modul ini belum tersedia.');
        }
        Respon::berkas($d['jalan'], 'Bahan-Bacaan-Pertemuan-' . $m['nomor'] . '.pdf', 'application/pdf', isset($_GET['unduh']));
    }

    public function materiProgres(array $p): void
    {
        $m = $this->modulTerbuka($p);
        $j = input_json();
        $total = max(0, min(2000, (int) ($j['total'] ?? 0)));
        $hal = max(0, min($total, (int) ($j['halaman'] ?? 0)));
        $lama = Modul::progres((int) $m['id'], Auth::id());
        if (!empty($j['tuntas'])) {
            // Cadangan bila penampil PDF tidak bisa dimuat: tandai selesai tanpa hitung halaman.
            $total = max((int) $lama['total_halaman'], 1);
            $hal = $total;
        }
        // Kemajuan hanya maju, tidak mundur (halaman terjauh yang pernah dibaca).
        Modul::catat((int) $m['id'], Auth::id(), ['halaman_terakhir' => max($hal, (int) $lama['halaman_terakhir']), 'total_halaman' => $total]);
        Respon::json(['ok' => true]);
    }

    // ---------- Simulasi ----------
    public function simulasi(array $p): void
    {
        $m = $this->modulTerbuka($p);
        $this->langkahBuka($m, 'simulasi');
        $langkah = Modul::langkah($m, Auth::id());
        $folder = (string) $m['game_folder'];
        $ada = $folder !== '' && preg_match('/^[a-z0-9_-]+$/i', $folder) && is_file(ROOT . '/public/games/' . $folder . '/index.html');
        $this->tampil('siswa/simulasi', ['m' => $m, 'langkah' => $langkah, 'berikut' => Modul::berikut($langkah, 'simulasi'), 'folder' => $ada ? $folder : '', 'p' => Modul::progres((int) $m['id'], Auth::id())]);
    }

    public function simulasiHasil(array $p): void
    {
        $m = $this->modulTerbuka($p);
        $this->langkahBuka($m, 'simulasi', true);
        $j = input_json();
        $uid = Auth::id();
        if (!empty($j['selesai']) && !isset($j['skor'])) {
            Modul::catat((int) $m['id'], $uid, ['simulasi_selesai' => 1]);
            Respon::json(['ok' => true]);
        }
        $skor = (int) ($j['skor'] ?? -1);
        $maks = (int) ($j['skorMaks'] ?? 0);
        if ($maks < 1 || $maks > 100000 || $skor < 0 || $skor > $maks) {
            Respon::json(['ok' => false, 'pesan' => 'Skor tidak valid.'], 422);
        }
        $lama = Modul::progres((int) $m['id'], $uid);
        // Simpan skor terbaik.
        $lebihBaik = $lama['game_skor'] === null || $lama['game_skor_maks'] === null
            || ($skor / $maks) > ((int) $lama['game_skor'] / max(1, (int) $lama['game_skor_maks']));
        $kolom = ['simulasi_selesai' => 1];
        if ($lebihBaik) {
            $kolom += ['game_skor' => $skor, 'game_skor_maks' => $maks];
        }
        Modul::catat((int) $m['id'], $uid, $kolom);
        Respon::json(['ok' => true]);
    }

    /** Simpan progres game (teks JSON dari window.name game) supaya siswa bisa melanjutkan, bukan mengulang. */
    public function simulasiProgres(array $p): void
    {
        $m = $this->modulTerbuka($p);
        $this->langkahBuka($m, 'simulasi', true);
        $data = input_json()['data'] ?? null;
        if ($data === '' || $data === null) {
            Modul::catat((int) $m['id'], Auth::id(), ['game_data' => null]);
            Respon::json(['ok' => true]);
        }
        if (!is_string($data) || strlen($data) > 60000 || !is_array(json_decode($data, true))) {
            Respon::json(['ok' => false, 'pesan' => 'Data progres tidak valid.'], 422);
        }
        Modul::catat((int) $m['id'], Auth::id(), ['game_data' => $data]);
        Respon::json(['ok' => true]);
    }

    // ---------- Praktikum ----------
    public function praktikum(array $p): void
    {
        $m = $this->modulTerbuka($p);
        $this->langkahBuka($m, 'praktikum');
        $def = Modul::praktikum((int) $m['nomor']);
        if (!$def) {
            flash('info', 'Panduan praktikum untuk modul ini belum tersedia.');
            Respon::alihkan('/modul/' . $m['id']);
        }
        $langkah = Modul::langkah($m, Auth::id());
        $this->tampil('siswa/praktikum', [
            'm' => $m, 'def' => $def, 'langkah' => $langkah, 'berikut' => Modul::berikut($langkah, 'praktikum'),
            'p' => Modul::progres((int) $m['id'], Auth::id()), 'total' => Modul::jumlahLangkahPraktikum($def),
        ]);
    }

    /** Catat kemajuan praktikum: langkah terjauh yang dibuka (hanya maju) dan penanda selesai (hanya bila sudah sampai langkah terakhir). */
    public function praktikumProgres(array $p): void
    {
        $m = $this->modulTerbuka($p);
        $this->langkahBuka($m, 'praktikum', true);
        $def = Modul::praktikum((int) $m['nomor']);
        if (!$def) {
            Respon::json(['ok' => false, 'pesan' => 'Panduan belum tersedia.'], 404);
        }
        $total = Modul::jumlahLangkahPraktikum($def);
        $j = input_json();
        $lama = Modul::progres((int) $m['id'], Auth::id());
        $maju = max(0, min($total, (int) ($j['langkah'] ?? 0)));
        $maju = max($maju, (int) $lama['praktikum_langkah']);
        $kolom = ['praktikum_langkah' => $maju];
        if (!empty($j['selesai'])) {
            if ($maju < $total) {
                Respon::json(['ok' => false, 'pesan' => 'Baca dulu sampai langkah terakhir.'], 422);
            }
            $kolom['praktikum_selesai'] = 1;
        }
        Modul::catat((int) $m['id'], Auth::id(), $kolom);
        Respon::json(['ok' => true, 'langkah' => $maju, 'selesai' => !empty($kolom['praktikum_selesai']) || (int) $lama['praktikum_selesai'] === 1]);
    }

    // ---------- Refleksi ----------
    public function refleksi(array $p): void
    {
        $m = $this->modulTerbuka($p);
        $this->langkahBuka($m, 'refleksi');
        $lama = $_SESSION['lama'] ?? [];
        hapus_lama();
        $prog = Modul::progres((int) $m['id'], Auth::id());
        $this->tampil('siswa/refleksi', [
            'm' => $m, 'langkah' => Modul::langkah($m, Auth::id()), 'tujuan' => Modul::tujuanDaftar($m), 'khusus' => Modul::refleksiKhusus((int) $m['nomor']),
            'jawab' => is_array($lama) && $lama ? $lama : (Modul::refleksi($prog) ?? []), 'sudah' => Modul::refleksi($prog) !== null,
        ]);
    }

    public function simpanRefleksi(array $p): void
    {
        $m = $this->modulTerbuka($p);
        $this->langkahBuka($m, 'refleksi');
        $tujuan = Modul::tujuanDaftar($m);
        $paham = (int) ($_POST['paham'] ?? 0);
        $bisa = [];
        foreach ((array) ($_POST['bisa'] ?? []) as $i) {
            if (is_numeric($i) && isset($tujuan[(int) $i])) {
                $bisa[] = (int) $i;
            }
        }
        $d = [
            'paham' => $paham, 'bisa' => array_values(array_unique($bisa)),
            'penting' => $this->teks('penting', 2000), 'bingung' => $this->teks('bingung', 2000), 'tanya' => $this->teks('tanya', 1000),
        ];
        $khusus = Modul::refleksiKhusus((int) $m['nomor']);
        $galat = [];
        if ($khusus) {
            $d['khusus'] = [];
            $d['khusus_q'] = $khusus;
            foreach ($khusus as $i => $_) {
                $d['khusus'][$i] = mb_substr(trim((string) ($_POST['khusus'][$i] ?? '')), 0, 2000);
            }
            if (count(array_filter($d['khusus'], fn($t) => mb_strlen($t) >= 2)) < count($khusus)) {
                $galat[] = 'Jawab semua pertanyaan tentang materi hari ini.';
            }
        }
        if (!isset(Modul::SKALA_PAHAM[$paham])) {
            $galat[] = 'Pilih seberapa paham kamu sekarang.';
        }
        if (mb_strlen($d['penting']) < 5) {
            $galat[] = 'Tulis hal penting yang kamu pelajari (minimal satu kalimat pendek).';
        }
        if (mb_strlen($d['bingung']) < 2) {
            $galat[] = 'Tulis bagian yang masih membingungkan. Kalau tidak ada, tulis "tidak ada".';
        }
        if ($galat) {
            $_SESSION['lama'] = $d;
            flash('galat', implode("\n", $galat));
            Respon::alihkan('/modul/' . $m['id'] . '/refleksi');
        }
        hapus_lama();
        $d['pada'] = sekarang();
        Modul::catat((int) $m['id'], Auth::id(), ['refleksi' => json_encode($d, JSON_UNESCAPED_UNICODE)]);
        flash('sukses', 'Refleksi tersimpan. Modul ' . $m['nomor'] . ' selesai, kerja bagus!');
        Respon::alihkan('/modul/' . $m['id']);
    }

    // ---------- Nilai & alat ----------
    public function nilai(array $p): void
    {
        $uid = Auth::id();
        $baris = [];
        foreach (Modul::semua() as $m) {
            if ($m['status'] !== 'terbit') {
                continue;
            }
            $pr = Modul::progres((int) $m['id'], $uid);
            $lk = Lkpd::ambil((int) $m['id'], $uid);
            $aturan ??= Nilai::aturanSiswa($uid);
            $baris[] = ['m' => $m, 'progres' => $pr, 'lkpd' => $lk, 'lembar' => Lembar::ambil((int) $m['id'], $uid), 'nilai' => Nilai::modul($m, $uid, $aturan)];
        }
        $hasil = Kuis::hasilSiswa($uid);
        $pre = $hasil['pretest']['skor'] ?? null;
        $post = $hasil['posttest']['skor'] ?? null;
        $this->tampil('siswa/nilai', [
            'baris' => $baris, 'kuis' => Kuis::ringkasanSiswa($uid),
            'ngain' => Kuis::ngain($pre !== null ? (int) $pre : null, $post !== null ? (int) $post : null),
        ]);
    }

    public function alatSubnet(array $p): void
    {
        $this->tampil('siswa/subnet');
    }
}
