<?php
// Halaman admin: ringkasan, pengguna, modul (termasuk unggah PDF materi), dan daftar kelas.
final class AdminController extends Kontroler
{
    public function ringkasan(array $p): void
    {
        $this->tampil('admin/ringkasan', [
            'jumlah' => [
                'siswa' => Pengguna::hitung('siswa'), 'guru' => Pengguna::hitung('guru'),
                'kelas' => (int) DB::nilai('SELECT COUNT(*) FROM kelas'),
                'lkpd' => (int) DB::nilai("SELECT COUNT(*) FROM lkpd_pengumpulan WHERE status = 'dikumpulkan'"),
            ],
            'modul' => Modul::semua(),
            'terbaru' => DB::semua("SELECT u.nama, u.peran, u.dibuat_pada FROM users u ORDER BY u.id DESC LIMIT 6"),
        ]);
    }

    public function pengguna(array $p): void
    {
        $peran = (string) ($_GET['peran'] ?? '');
        $cari = trim((string) ($_GET['q'] ?? ''));
        $this->tampil('admin/pengguna', ['daftar' => Pengguna::daftar($peran, $cari), 'peran' => $peran, 'cari' => $cari]);
    }

    public function tambahGuru(array $p): void
    {
        $nama = $this->teks('nama', 100);
        $email = strtolower($this->teks('email', 190));
        if (mb_strlen($nama) < 3 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('galat', 'Isi nama (min. 3 huruf) dan email yang valid.');
            Respon::alihkan('/admin/pengguna');
        }
        if (Pengguna::emailAda($email)) {
            flash('galat', 'Email itu sudah terdaftar.');
            Respon::alihkan('/admin/pengguna');
        }
        $sandi = sandi_acak(10);
        Pengguna::buatGuru($nama, $email, $sandi);
        flash('sukses', "Akun guru dibuat.\nEmail: $email\nPassword sementara: $sandi\n(Catat sekarang, karena tidak akan ditampilkan lagi. Guru wajib menggantinya saat pertama masuk.)");
        Respon::alihkan('/admin/pengguna');
    }

    public function resetSandi(array $p): void
    {
        $u = Pengguna::byId($this->id($p));
        if (!$u) {
            Respon::galat(404);
        }
        $sandi = sandi_acak(10);
        Pengguna::setSandi((int) $u['id'], $sandi, true);
        flash('sukses', "Password {$u['nama']} direset.\nPassword sementara: $sandi\n(Catat sekarang, karena tidak akan ditampilkan lagi.)");
        Respon::alihkan('/admin/pengguna');
    }

    /** Ubah nama dan NIS/nomor absen siswa. NIS cukup unik di dalam kelasnya. */
    public function ubahSiswa(array $p): void
    {
        $u = Pengguna::byId($this->id($p));
        if (!$u || $u['peran'] !== 'siswa') {
            Respon::galat(404);
        }
        $nama = preg_replace('/\s+/', ' ', $this->teks('nama', 100));
        $nis = $this->teks('nis', 30);
        $kembali = '/admin/pengguna?peran=siswa&q=' . rawurlencode((string) ($_POST['q'] ?? ''));
        $kelas = Kelas::dariSiswa((int) $u['id']);
        if (mb_strlen($nama) < 3) {
            flash('galat', 'Nama minimal 3 huruf.');
        } elseif (!Pengguna::nisValid($nis)) {
            flash('galat', 'NIS/nomor absen harus huruf/angka, maksimal 30 karakter.');
        } elseif ($kelas && Kelas::nisDipakai((int) $kelas['id'], $nis, (int) $u['id'])) {
            flash('galat', 'NIS ' . $nis . ' sudah dipakai siswa lain di ' . $kelas['nama'] . '.');
        } else {
            Pengguna::ubahDataSiswa((int) $u['id'], $nama, $nis);
            flash('sukses', 'Data ' . $nama . ' disimpan (NIS ' . $nis . ').');
        }
        Respon::alihkan($kembali);
    }

    public function ubahAktif(array $p): void
    {
        $u = Pengguna::byId($this->id($p));
        if (!$u) {
            Respon::galat(404);
        }
        if ((int) $u['id'] === Auth::id()) {
            flash('galat', 'Kamu tidak bisa menonaktifkan akunmu sendiri.');
            Respon::alihkan('/admin/pengguna');
        }
        $aktif = (int) $u['aktif'] !== 1;
        Pengguna::setAktif((int) $u['id'], $aktif);
        flash('sukses', $u['nama'] . ($aktif ? ' diaktifkan.' : ' dinonaktifkan.'));
        Respon::alihkan('/admin/pengguna');
    }

    public function tambahModul(array $p): void
    {
        $judul = $this->teks('judul', 200);
        if (mb_strlen($judul) < 3) {
            flash('galat', 'Judul modul minimal 3 huruf.');
            Respon::alihkan('/kelola/modul');
        }
        $id = Modul::tambah($judul);
        flash('sukses', 'Modul baru dibuat (status Terkunci). Lengkapi isinya lalu ubah status menjadi Terbit.');
        Respon::alihkan('/kelola/modul/' . $id);
    }

    public function modul(array $p): void
    {
        $this->tampil('admin/modul', ['daftar' => Modul::semua()]);
    }

    public function formModul(array $p): void
    {
        $m = Modul::byId($this->id($p));
        if (!$m) {
            Respon::galat(404);
        }
        $games = [];
        foreach (glob(ROOT . '/public/games/*', GLOB_ONLYDIR) ?: [] as $d) {
            if (is_file($d . '/index.html')) {
                $games[] = basename($d);
            }
        }
        $this->tampil('admin/modul-form', ['m' => $m, 'games' => $games, 'materi' => Modul::berkasMateri($m), 'lkpd' => Lkpd::definisi((int) $m['nomor'])]);
    }

    public function simpanModul(array $p): void
    {
        $m = Modul::byId($this->id($p));
        if (!$m) {
            Respon::galat(404);
        }
        $tgl = $this->teks('tanggal', 10);
        $status = $this->teks('status', 10);
        $game = $this->teks('game_folder', 100);
        $video = trim((string) ($_POST['video_url'] ?? ''));
        if (!Modul::videoUrlValid($video)) {
            flash('galat', 'Link video harus diawali https:// (misalnya link YouTube).');
            Respon::alihkan('/kelola/modul/' . $m['id']);
        }
        if (!in_array($status, ['terbit', 'segera', 'terkunci'], true)) {
            $status = 'segera';
        }
        if ($game !== '' && !preg_match('/^[a-z0-9_-]+$/i', $game)) {
            $game = '';
        }
        if ($judul = $this->teks('judul', 200)) {
            Modul::simpan((int) $m['id'], [
                'judul' => $judul, 'ringkasan' => $this->teks('ringkasan', 300) ?: null,
                'tujuan' => mb_substr(trim((string) ($_POST['tujuan'] ?? '')), 0, 2000) ?: null,
                'tanggal' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl) ? $tgl : null,
                'status' => $status, 'game_folder' => $game ?: null, 'video_url' => $video ?: null,
            ]);
            flash('sukses', 'Modul ' . $m['nomor'] . ' disimpan.');
        } else {
            flash('galat', 'Judul modul tidak boleh kosong.');
        }
        Respon::alihkan('/kelola/modul/' . $m['id']);
    }

    public function unggahMateri(array $p): void
    {
        $m = Modul::byId($this->id($p));
        if (!$m) {
            Respon::galat(404);
        }
        $f = $_FILES['materi'] ?? null;
        $balik = '/kelola/modul/' . $m['id'];
        if (!$f || $f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
            flash('galat', 'Unggahan gagal. Pastikan ukuran PDF tidak melebihi batas server (maks. 25 MB).');
            Respon::alihkan($balik);
        }
        if ($f['size'] > 25 * 1024 * 1024 || strtolower(pathinfo($f['name'], PATHINFO_EXTENSION)) !== 'pdf'
            || (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']) !== 'application/pdf') {
            flash('galat', 'Berkas harus PDF dan maksimal 25 MB.');
            Respon::alihkan($balik);
        }
        $dir = ROOT . '/storage/uploads/materi';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $nama = 'modul' . $m['nomor'] . '-' . bin2hex(random_bytes(6)) . '.pdf';
        move_uploaded_file($f['tmp_name'], $dir . '/' . $nama);
        if ($m['materi_berkas']) {
            @unlink($dir . '/' . basename($m['materi_berkas']));
        }
        Modul::setMateri((int) $m['id'], $nama);
        // Jumlah halaman dihitung ulang otomatis saat siswa membuka materi.
        flash('sukses', 'PDF materi Modul ' . $m['nomor'] . ' diunggah.');
        Respon::alihkan($balik);
    }

    public function kelas(array $p): void
    {
        $this->tampil('admin/kelas', ['daftar' => Kelas::semua()]);
    }
}
