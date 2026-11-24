<?php
// Halaman guru: kelas & kode kelas, penilaian LKPD. Admin juga boleh membuka (melihat semua kelas).
final class GuruController extends Kontroler
{
    /** Apakah pengguna ini berhak melihat data seorang siswa? (pemilik, guru pengampu kelasnya, atau admin) */
    public static function bolehLihatSiswa(?array $u, int $siswaId): bool
    {
        if (!$u) {
            return false;
        }
        if ($u['peran'] === 'admin' || (int) $u['id'] === $siswaId) {
            return true;
        }
        if ($u['peran'] !== 'guru') {
            return false;
        }
        $kelas = DB::nilai('SELECT kelas_id FROM kelas_anggota WHERE siswa_id = ?', [$siswaId]);
        return $kelas && Kelas::diajar((int) $kelas, (int) $u['id']);
    }

    private function kelasBoleh(int $id): array
    {
        $k = Kelas::byId($id);
        $u = Auth::pengguna();
        if (!$k || ($u['peran'] !== 'admin' && !Kelas::diajar((int) $k['id'], (int) $u['id']))) {
            Respon::galat(404, 'Kelas tidak ditemukan.');
        }
        return $k;
    }

    /** Kelas + modul yang sedang dilihat guru (dari ?k= & ?m=, diingat di sesi supaya pindah halaman tetap sama). */
    private function pilihan(): array
    {
        $daftar = $this->kelasSaya();
        $modul = Modul::terbit();
        $k = (int) ($_GET['k'] ?? ($_SESSION['guru_k'] ?? 0));
        $kelas = null;
        foreach ($daftar as $x) {
            if ((int) $x['id'] === $k) {
                $kelas = $x;
            }
        }
        $kelas ??= $daftar[0] ?? null;
        $mId = (int) ($_GET['m'] ?? ($_SESSION['guru_m'] ?? 0));
        $m = null;
        foreach ($modul as $x) {
            if ((int) $x['id'] === $mId) {
                $m = $x;
            }
        }
        $m ??= Modul::aktif($modul);
        $_SESSION['guru_k'] = $kelas['id'] ?? 0;
        $_SESSION['guru_m'] = $m['id'] ?? 0;
        return [$daftar, $modul, $kelas, $m];
    }

    /** Status 6 langkah tiap siswa di kelas untuk satu modul. */
    private function kemajuanKelas(array $kelas, array $m): array
    {
        $baris = [];
        foreach (Kelas::anggota((int) $kelas['id']) as $s) {
            $langkah = [];
            foreach (Modul::langkah($m, (int) $s['id']) as $l) {
                $langkah[$l['kunci']] = $l['status'];
            }
            $pr = Modul::progres((int) $m['id'], (int) $s['id']);
            $baris[] = [
                'siswa' => $s, 'langkah' => $langkah,
                'persen' => (int) round(count(array_filter($langkah, fn($x) => $x === 'selesai')) / max(1, count(array_filter($langkah, fn($x) => $x !== 'segera'))) * 100),
                'progres' => $pr, 'lembar' => Lembar::ambil((int) $m['id'], (int) $s['id']), 'lkpd' => Lkpd::ambil((int) $m['id'], (int) $s['id']),
            ];
        }
        return $baris;
    }

    // ---------- Beranda guru ----------
    public function beranda(array $p): void
    {
        $daftar = $this->kelasSaya();
        $terbit = Modul::terbit();
        $aktif = Modul::aktif($terbit);
        $kartu = [];
        foreach ($daftar as $k) {
            $kid = (int) $k['id'];
            $siswa = (int) DB::nilai('SELECT COUNT(*) FROM kelas_anggota WHERE kelas_id = ?', [$kid]);
            $perluPraktik = (int) DB::nilai(
                "SELECT COUNT(*) FROM lkpd_pengumpulan p JOIN kelas_anggota a ON a.siswa_id = p.siswa_id WHERE a.kelas_id = ? AND p.status = 'dikumpulkan' AND p.nilai IS NULL", [$kid]);
            $perluLkpd = (int) DB::nilai(
                "SELECT COUNT(*) FROM lembar_kerja l JOIN kelas_anggota a ON a.siswa_id = l.siswa_id WHERE a.kelas_id = ? AND l.status = 'dikumpulkan' AND l.nilai IS NULL", [$kid]);
            $perluTindak = count(Tindak::menungguKelas($kid));
            $rata = 0;
            $selesaiModul = 0;
            if ($aktif && $siswa) {
                $kem = $this->kemajuanKelas($k, $aktif);
                $rata = (int) round(array_sum(array_column($kem, 'persen')) / count($kem));
                $selesaiModul = count(array_filter($kem, fn($r) => $r['persen'] === 100));
            }
            $kartu[] = ['k' => $k, 'siswa' => $siswa, 'perluPraktik' => $perluPraktik, 'perluLkpd' => $perluLkpd, 'perluTindak' => $perluTindak, 'rata' => $rata, 'selesai' => $selesaiModul,
                'lkpdBuka' => $aktif ? Lembar::terbuka((int) $aktif['id'], $kid) : true];
        }
        $this->tampil('guru/beranda', ['kartu' => $kartu, 'aktif' => $aktif, 'kuis' => array_filter([Kuis::byJenis('pretest'), Kuis::byJenis('posttest')]), 'adaLembar' => $aktif && Lembar::definisi((int) $aktif['nomor']) !== null, 'diskusiBelum' => Diskusi::belumDijawab(Auth::pengguna())]);
    }

    // ---------- Kemajuan siswa ----------
    public function kemajuan(array $p): void
    {
        [$daftar, $modul, $kelas, $m] = $this->pilihan();
        $baris = $kelas && $m ? $this->kemajuanKelas($kelas, $m) : [];
        $this->tampil('guru/kemajuan', [
            'daftar' => $daftar, 'modul' => $modul, 'kelas' => $kelas, 'm' => $m, 'baris' => $baris,
            'judulLangkah' => $m ? array_column(Modul::langkah($m, 0), 'judul', 'kunci') : [],
            'lkpdBuka' => $kelas && $m ? Lembar::terbuka((int) $m['id'], (int) $kelas['id']) : true,
            'adaLembar' => $m && Lembar::definisi((int) $m['nomor']) !== null,
        ]);
    }

    public function refleksi(array $p): void
    {
        [$daftar, $modul, $kelas, $m] = $this->pilihan();
        $baris = [];
        if ($kelas && $m) {
            foreach (Kelas::anggota((int) $kelas['id']) as $s) {
                $baris[] = ['siswa' => $s, 'refleksi' => Modul::refleksi(Modul::progres((int) $m['id'], (int) $s['id']))];
            }
        }
        $this->tampil('guru/refleksi', ['daftar' => $daftar, 'modul' => $modul, 'kelas' => $kelas, 'm' => $m, 'baris' => $baris,
            'tujuan' => $m ? Modul::tujuanDaftar($m) : [], 'khusus' => $m ? Modul::refleksiKhusus((int) $m['nomor']) : []]);
    }

    /** Buka/tutup LKPD satu modul untuk satu kelas. */
    public function aturLembar(array $p): void
    {
        $k = $this->kelasBoleh((int) ($_POST['kelas_id'] ?? 0));
        $m = Modul::byId((int) ($_POST['modul_id'] ?? 0));
        $kembali = (string) ($_POST['kembali'] ?? '/guru');
        if (!preg_match('#^/guru(/[a-z]+)?(\?[a-z0-9=&]*)?$#', $kembali)) {
            $kembali = '/guru';
        }
        if (!$m) {
            Respon::galat(404, 'Modul tidak ditemukan.');
        }
        $buka = ($_POST['buka'] ?? '') === '1';
        Lembar::aturAkses((int) $k['id'], (int) $m['id'], $buka, Auth::id());
        flash('sukses', 'LKPD Modul ' . $m['nomor'] . ' untuk ' . $k['nama'] . ($buka ? ' dibuka. Siswa sekarang bisa mengerjakan.' : ' ditutup. Siswa belum bisa mengerjakan atau mengumpulkan.'));
        Respon::alihkan($kembali);
    }

    // ---------- Kelas ----------
    public function kelas(array $p): void
    {
        $daftar = $this->kelasSaya();
        $pilih = null;
        foreach ($daftar as $k) {
            if ((int) ($_GET['k'] ?? 0) === (int) $k['id'] || (!$pilih && !isset($_GET['k']))) {
                $pilih = $k;
            }
        }
        $jumlah = [];
        foreach ($daftar as $k) {
            $jumlah[$k['id']] = (int) DB::nilai('SELECT COUNT(*) FROM kelas_anggota WHERE kelas_id = ?', [$k['id']]);
        }
        $anggota = $pilih ? Kelas::anggota((int) $pilih['id']) : [];
        $this->tampil('guru/kelas', ['daftar' => $daftar, 'pilih' => $pilih, 'anggota' => $anggota, 'jumlah' => $jumlah,
            'guruKelas' => $pilih ? Kelas::guru((int) $pilih['id']) : [], 'guruSemua' => Pengguna::guruAktif()]);
    }

    public function buatKelas(array $p): void
    {
        $nama = $this->teks('nama', 60);
        if (mb_strlen($nama) < 2) {
            flash('galat', 'Nama kelas minimal 2 karakter.');
            Respon::alihkan('/guru/kelas');
        }
        $k = Kelas::buat($nama, Auth::id());
        flash('sukses', 'Kelas dibuat. Kode kelas: ' . $k['kode'] . '. Bagikan ke siswa.');
        Respon::alihkan('/guru/kelas?k=' . $k['id']);
    }

    public function kodeBaru(array $p): void
    {
        $k = $this->kelasBoleh($this->id($p));
        $kode = Kelas::kodeBaru((int) $k['id']);
        flash('sukses', 'Kode kelas baru: ' . $kode . '. Kode lama tidak berlaku lagi.');
        Respon::alihkan('/guru/kelas?k=' . $k['id']);
    }

    public function tambahGuru(array $p): void
    {
        $k = $this->kelasBoleh($this->id($p));
        $g = Pengguna::byId((int) ($_POST['guru_id'] ?? 0));
        if (!$g || $g['peran'] !== 'guru' || (int) $g['aktif'] !== 1) {
            flash('galat', 'Pilih guru yang akan ditambahkan.');
            Respon::alihkan('/guru/kelas?k=' . $k['id']);
        }
        Kelas::tambahGuru((int) $k['id'], (int) $g['id']);
        flash('sukses', $g['nama'] . ' sekarang ikut mengajar ' . $k['nama'] . '. Kelas ini muncul di akunnya.');
        Respon::alihkan('/guru/kelas?k=' . $k['id']);
    }

    public function hapusGuru(array $p): void
    {
        $k = $this->kelasBoleh($this->id($p));
        $u = Auth::pengguna();
        $gid = (int) ($_POST['guru_id'] ?? 0);
        $pembuat = (int) $k['guru_id'] === (int) $u['id'];
        $diriSendiri = $gid === (int) $u['id'];
        if ($gid === (int) $k['guru_id'] || !($u['peran'] === 'admin' || $pembuat || $diriSendiri)) {
            flash('galat', $gid === (int) $k['guru_id'] ? 'Guru pembuat kelas tidak bisa dihapus dari kelasnya.' : 'Hanya pembuat kelas yang bisa menghapus guru lain.');
            Respon::alihkan('/guru/kelas?k=' . $k['id']);
        }
        Kelas::hapusGuru((int) $k['id'], $gid);
        flash('sukses', $diriSendiri ? 'Kamu tidak lagi mengajar ' . $k['nama'] . '.' : 'Guru dihapus dari ' . $k['nama'] . '.');
        Respon::alihkan($diriSendiri ? '/guru/kelas' : '/guru/kelas?k=' . $k['id']);
    }

    public function keluarkan(array $p): void
    {
        $k = $this->kelasBoleh($this->id($p));
        Kelas::keluarkan((int) $k['id'], (int) ($_POST['siswa_id'] ?? 0));
        flash('sukses', 'Siswa dikeluarkan dari kelas.');
        Respon::alihkan('/guru/kelas?k=' . $k['id']);
    }

    // ---------- Penilaian ----------
    public function penilaian(array $p): void
    {
        [$daftar, $modul, $kelasPilih, $mPilih] = $this->pilihan();
        $kelasId = (int) ($kelasPilih['id'] ?? 0);
        $modulId = (int) ($mPilih['id'] ?? 0);
        $rekap = $kelasPilih && $modulId ? Lkpd::rekapKelas($kelasId, $modulId) : [];
        $this->tampil('guru/penilaian', ['daftar' => $daftar, 'modul' => $modul, 'kelasId' => $kelasId, 'modulId' => $modulId, 'rekap' => $rekap, 'kelasPilih' => $kelasPilih,
            'mPilih' => $mPilih, 'lkpdBuka' => $kelasPilih && $mPilih ? Lembar::terbuka($modulId, $kelasId) : true, 'adaLembar' => $mPilih && Lembar::definisi((int) $mPilih['nomor']) !== null]);
    }

    private function pengumpulan(array $p): array
    {
        $pg = Lkpd::byId($this->id($p));
        if (!$pg || !self::bolehLihatSiswa(Auth::pengguna(), (int) $pg['siswa_id'])) {
            Respon::galat(404, 'Pengumpulan tidak ditemukan.');
        }
        return $pg;
    }

    public function detailPenilaian(array $p): void
    {
        $pg = $this->pengumpulan($p);
        $m = Modul::byId((int) $pg['modul_id']);
        $def = Lkpd::definisi((int) $m['nomor']) ?? ['bagian' => []];
        $siswa = Pengguna::byId((int) $pg['siswa_id']);
        $berkas = [];
        foreach (Lkpd::berkas((int) $pg['id']) as $b) {
            $berkas[$b['butir_id']][] = $b;
        }
        $this->tampil('guru/penilaian-detail', [
            'pg' => $pg, 'm' => $m, 'def' => $def, 'siswa' => $siswa, 'berkas' => $berkas,
            'rubrikSkor' => $pg['rubrik_skor'] ? json_decode($pg['rubrik_skor'], true) : [],
            'kelas' => Kelas::dariSiswa((int) $siswa['id']),
            'refleksi' => Modul::refleksi(Modul::progres((int) $m['id'], (int) $siswa['id'])), 'tujuan' => Modul::tujuanDaftar($m),
        ]);
    }

    public function simpanNilai(array $p): void
    {
        $pg = $this->pengumpulan($p);
        $m = Modul::byId((int) $pg['modul_id']);
        $def = Lkpd::definisi((int) $m['nomor']);
        $rubrik = $def['rubrik'] ?? [];
        if ($pg['status'] !== 'dikumpulkan') {
            flash('galat', 'LKPD ini belum dikumpulkan siswa.');
            Respon::alihkan('/guru/penilaian/' . $pg['id']);
        }
        // Rubrik skala 1-4 (default) atau berbobot: 'maks' per kriteria, skor 0..maks.
        $skor = [];
        $maksTotal = 0;
        foreach ($rubrik as $i => $r) {
            $maks = isset($r['maks']) ? max(1, (int) $r['maks']) : 4;
            $mentah = $_POST['r'][$i] ?? '';
            $v = is_string($mentah) && preg_match('/^\d{1,3}$/', $mentah) ? (int) $mentah : -1;
            $min = isset($r['maks']) ? 0 : 1;
            if ($v < $min || $v > $maks) {
                flash('galat', isset($r['maks']) ? 'Isi skor tiap kriteria sesuai skor maksimumnya (0 sampai maksimum).' : 'Pilih skor 1–4 untuk semua kriteria rubrik.');
                Respon::alihkan('/guru/penilaian/' . $pg['id']);
            }
            $skor[] = $v;
            $maksTotal += $maks;
        }
        if (!$skor) {
            flash('galat', 'Rubrik untuk modul ini belum ada.');
            Respon::alihkan('/guru/penilaian/' . $pg['id']);
        }
        $nilai = Lkpd::nilai((int) $pg['id'], $skor, $maksTotal, $this->teks('catatan', 1000));
        flash('sukses', 'Nilai tersimpan: ' . $nilai . ' (' . kategori_nilai($nilai) . ').');
        Respon::alihkan('/guru/penilaian/' . $pg['id']);
    }

    public function kembalikan(array $p): void
    {
        $pg = $this->pengumpulan($p);
        Lkpd::kembalikan((int) $pg['id']);
        flash('sukses', 'LKPD dikembalikan ke siswa untuk diperbaiki.');
        Respon::alihkan('/guru/penilaian/' . $pg['id']);
    }

    public function praktikum(array $p): void
    {
        $this->tampil('guru/praktikum');
    }

    // ---------- LKPD (lembar kerja) ----------
    private function lembarKonteks(array $p): array
    {
        $lb = Lembar::byId($this->id($p));
        if (!$lb || !self::bolehLihatSiswa(Auth::pengguna(), (int) $lb['siswa_id'])) {
            Respon::galat(404, 'LKPD tidak ditemukan.');
        }
        $m = Modul::byId((int) $lb['modul_id']);
        $def = Lembar::definisi((int) $m['nomor']);
        if (!$def) {
            Respon::galat(404, 'Soal LKPD untuk modul ini tidak ada.');
        }
        return [$lb, $m, $def];
    }

    public function lembar(array $p): void
    {
        [$lb, $m, $def] = $this->lembarKonteks($p);
        $siswa = Pengguna::byId((int) $lb['siswa_id']);
        $this->tampil('guru/lembar', ['lb' => $lb, 'm' => $m, 'def' => $def, 'siswa' => $siswa, 'kelas' => Kelas::dariSiswa((int) $siswa['id'])]);
    }

    public function nilaiLembar(array $p): void
    {
        [$lb, $m, $def] = $this->lembarKonteks($p);
        $ke = '/guru/lkpd/' . $lb['id'];
        if ($lb['status'] !== 'dikumpulkan') {
            flash('galat', 'LKPD ini belum dikumpulkan siswa.');
            Respon::alihkan($ke);
        }
        $skor = Lembar::skorUraian($def, $lb['jawaban_arr'], is_array($_POST['u'] ?? null) ? $_POST['u'] : []);
        if ($skor === null) {
            flash('galat', 'Isi skor setiap soal uraian (0 sampai skor maksimalnya).');
            Respon::alihkan($ke);
        }
        $nilai = Lembar::nilaiUraian($lb, $def, $skor, $this->teks('catatan', 1000));
        flash('sukses', 'Nilai LKPD tersimpan: ' . $nilai . ' (' . kategori_nilai($nilai) . ').');
        Respon::alihkan($ke);
    }

    public function kembalikanLembar(array $p): void
    {
        [$lb] = $this->lembarKonteks($p);
        Lembar::kembalikan((int) $lb['id']);
        flash('sukses', 'LKPD dibuka lagi. Siswa bisa memperbaiki lalu mengumpulkan ulang.');
        Respon::alihkan('/guru/lkpd/' . $lb['id']);
    }

    // ---------- Analisis nilai, ketuntasan, remidi & pengayaan ----------
    public function analisis(array $p): void
    {
        [$daftar, $modul, $kelas, $m] = $this->pilihan();
        $this->tampil('guru/analisis', [
            'daftar' => $daftar, 'modul' => $modul, 'kelas' => $kelas, 'm' => $m,
            'a' => $kelas && $m ? Nilai::analisisKelas($kelas, $m) : null,
            'menunggu' => $kelas && $m ? Tindak::menungguKelas((int) $kelas['id'], (int) $m['id']) : [],
        ]);
    }

    public function analisisCsv(array $p): void
    {
        [, , $kelas, $m] = $this->pilihan();
        if (!$kelas || !$m) {
            Respon::galat(404, 'Pilih kelas dan modul dulu.');
        }
        $a = Nilai::analisisKelas($kelas, $m);
        $aman = fn($v) => is_string($v) && $v !== '' && strpbrk($v[0], '=+-@') !== false ? "'" . $v : $v;
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="nilai-modul-' . (int) $m['nomor'] . '-' . preg_replace('/[^a-z0-9]+/i', '-', $kelas['nama']) . '.csv"');
        header('X-Content-Type-Options: nosniff');
        $o = fopen('php://output', 'w');
        fwrite($o, "\xEF\xBB\xBF");
        fputcsv($o, ['Nama', 'NIS', 'Praktik', 'LKPD', 'Simulasi', 'Nilai awal', 'Nilai remidi', 'Nilai akhir', 'KKM', 'Status', 'Tindak lanjut', 'Nilai pengayaan', 'Perlu diperkuat']);
        foreach ($a['baris'] as $b) {
            $n = $b['nilai'];
            $k = $n['komponen'];
            fputcsv($o, [$aman($b['siswa']['nama']), $aman((string) $b['siswa']['nis']),
                $k['praktik']['nilai'] ?? '', $k['lkpd']['nilai'] ?? '', $k['simulasi']['nilai'] ?? '',
                $n['awal'] ?? '', $n['remidi']['nilai'] ?? '', $n['akhir'] ?? '', $n['kkm'], Nilai::STATUS[$n['status']][0],
                $n['rekomendasi'] === 'remidi' ? 'Perbaikan (remidi)' : ($n['rekomendasi'] === 'pengayaan' ? 'Pengayaan' : ''),
                $n['pengayaan']['nilai'] ?? '', $aman(implode('; ', array_map(fn($x) => $x['label'] . ' (' . $x['persen'] . '%)', $b['kelemahan']['lemah'])))]);
        }
        fclose($o);
        exit;
    }

    /** Simpan KKM dan bobot nilai satu kelas. Bobot harus berjumlah 100. */
    public function aturNilai(array $p): void
    {
        $k = $this->kelasBoleh($this->id($p));
        $ke = '/guru/analisis?k=' . $k['id'];
        $ambil = function (string $n, int $min, int $maks): ?int {
            $v = (string) ($_POST[$n] ?? '');
            return preg_match('/^\d{1,3}$/', $v) && (int) $v >= $min && (int) $v <= $maks ? (int) $v : null;
        };
        $a = ['kkm' => $ambil('kkm', 1, 100), 'praktik' => $ambil('bobot_praktik', 0, 100), 'lkpd' => $ambil('bobot_lkpd', 0, 100), 'simulasi' => $ambil('bobot_simulasi', 0, 100)];
        if (in_array(null, $a, true)) {
            flash('galat', 'Isi KKM 1–100 dan setiap bobot 0–100.');
            Respon::alihkan($ke);
        }
        if ($a['praktik'] + $a['lkpd'] + $a['simulasi'] !== 100) {
            flash('galat', 'Jumlah bobot harus 100% (sekarang ' . ($a['praktik'] + $a['lkpd'] + $a['simulasi']) . '%).');
            Respon::alihkan($ke);
        }
        Nilai::simpanAturan((int) $k['id'], $a);
        flash('sukses', 'Aturan nilai ' . $k['nama'] . ' disimpan: KKM ' . $a['kkm'] . ', praktik ' . $a['praktik'] . '%, LKPD ' . $a['lkpd'] . '%, simulasi ' . $a['simulasi'] . '%.');
        Respon::alihkan($ke);
    }

    private function tindakKonteks(array $p): array
    {
        $t = Tindak::byId($this->id($p));
        if (!$t || !self::bolehLihatSiswa(Auth::pengguna(), (int) $t['siswa_id'])) {
            Respon::galat(404, 'Jawaban tidak ditemukan.');
        }
        $m = Modul::byId((int) $t['modul_id']);
        $def = Tindak::definisi((int) $m['nomor'], (string) $t['jenis']);
        if (!$def) {
            Respon::galat(404, 'Soal untuk jawaban ini tidak ada.');
        }
        return [$t, $m, $def];
    }

    public function tindak(array $p): void
    {
        [$t, $m, $def] = $this->tindakKonteks($p);
        $siswa = Pengguna::byId((int) $t['siswa_id']);
        $kelas = Kelas::dariSiswa((int) $siswa['id']);
        $this->tampil('guru/lembar', ['lb' => $t, 'm' => $m, 'def' => $def, 'siswa' => $siswa, 'kelas' => $kelas,
            'label' => Tindak::JENIS[$t['jenis']], 'dasar' => '/guru/tindak/' . $t['id'],
            'kembali' => ['/guru/analisis?m=' . $m['id'] . ($kelas ? '&k=' . $kelas['id'] : ''), 'Analisis nilai']]);
    }

    public function nilaiTindak(array $p): void
    {
        [$t, $m, $def] = $this->tindakKonteks($p);
        $ke = '/guru/tindak/' . $t['id'];
        if ($t['status'] !== 'dikumpulkan') {
            flash('galat', 'Belum dikumpulkan siswa.');
            Respon::alihkan($ke);
        }
        $skor = Lembar::skorUraian($def, $t['jawaban_arr'], is_array($_POST['u'] ?? null) ? $_POST['u'] : []);
        if ($skor === null) {
            flash('galat', 'Isi skor setiap soal uraian (0 sampai skor maksimalnya).');
            Respon::alihkan($ke);
        }
        $nilai = Tindak::nilaiUraian($t, $def, $skor, $this->teks('catatan', 1000));
        $ket = '';
        if ($t['jenis'] === 'remidi') {
            $aturan = Nilai::aturanSiswa((int) $t['siswa_id']);
            $n = Nilai::modul($m, (int) $t['siswa_id'], $aturan);
            $ket = ' Nilai akhir modul: ' . $n['akhir'] . ' (' . Nilai::STATUS[$n['status']][0] . ', nilai remidi dihitung maks. KKM ' . $aturan['kkm'] . ').';
        }
        flash('sukses', Tindak::JENIS[$t['jenis']] . ' dinilai: ' . $nilai . '.' . $ket);
        Respon::alihkan($ke);
    }

    public function kembalikanTindak(array $p): void
    {
        [$t] = $this->tindakKonteks($p);
        Tindak::kembalikan((int) $t['id']);
        flash('sukses', Tindak::JENIS[$t['jenis']] . ' dibuka lagi. Siswa bisa memperbaiki lalu mengumpulkan ulang.');
        Respon::alihkan('/guru/tindak/' . $t['id']);
    }
}
