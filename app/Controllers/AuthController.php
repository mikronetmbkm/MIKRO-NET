<?php
// Landing, masuk, daftar dengan kode kelas, keluar, dan ganti password.
final class AuthController extends Kontroler
{
    private const BATAS_GAGAL = 5;      // percobaan gagal
    private const JENDELA_MENIT = 15;   // dalam rentang waktu ini

    public function landing(array $p): void
    {
        if ($u = Auth::pengguna()) {
            Respon::alihkan(Auth::beranda($u));
        }
        $this->tampil('landing', ['modul' => array_slice(Modul::semua(), 0, 4)], 'kosong');
    }

    public function health(array $p): void
    {
        // Cek cepat untuk memastikan database tersambung dan tabel sudah diimpor.
        try {
            $modul = (int) DB::nilai('SELECT COUNT(*) FROM modul');
            $users = (int) DB::nilai('SELECT COUNT(*) FROM users');
            Respon::json(['ok' => true, 'modul' => $modul, 'akun_ada' => $users > 0, 'storage_bisa_ditulis' => is_writable(ROOT . '/storage/uploads')]);
        } catch (Throwable $e) {
            Respon::json(['ok' => false, 'pesan' => 'Database belum siap. Cek .env dan impor schema.sql.'], 500);
        }
    }

    // ---------- Masuk ----------
    public function formMasuk(array $p): void
    {
        $this->tampil('auth/masuk', [], 'auth');
    }

    public function masuk(array $p): void
    {
        $email = strtolower($this->teks('email', 190));
        $sandi = (string) ($_POST['password'] ?? '');
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0';
        simpan_lama(['email' => $email]);

        $batas = date('Y-m-d H:i:s', time() - self::JENDELA_MENIT * 60);
        $gagal = (int) DB::nilai('SELECT COUNT(*) FROM login_percobaan WHERE (email = ? OR ip = ?) AND dibuat_pada > ?', [$email, $ip, $batas]);
        if ($gagal >= self::BATAS_GAGAL * 3 || (int) DB::nilai('SELECT COUNT(*) FROM login_percobaan WHERE email = ? AND dibuat_pada > ?', [$email, $batas]) >= self::BATAS_GAGAL) {
            flash('galat', 'Terlalu banyak percobaan gagal. Coba lagi dalam ' . self::JENDELA_MENIT . ' menit.');
            Respon::alihkan('/masuk');
        }

        $u = Pengguna::byEmail($email);
        // password_verify selalu dijalankan supaya waktu respons tidak membocorkan apakah email terdaftar.
        $hash = $u['password_hash'] ?? password_hash('tidak-dipakai', PASSWORD_DEFAULT);
        $benar = password_verify($sandi, $hash) && $u && (int) $u['aktif'] === 1;
        if (!$benar) {
            DB::jalankan('INSERT INTO login_percobaan (email, ip, dibuat_pada) VALUES (?, ?, ?)', [$email, $ip, sekarang()]);
            flash('galat', 'Email atau password salah.');
            Respon::alihkan('/masuk');
        }
        DB::jalankan('DELETE FROM login_percobaan WHERE email = ?', [$email]);
        DB::jalankan('DELETE FROM login_percobaan WHERE dibuat_pada < ?', [$batas]);
        if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
            Pengguna::setSandi((int) $u['id'], $sandi, (int) $u['harus_ganti_password'] === 1);
        }
        hapus_lama();
        Auth::masuk((int) $u['id']);
        Respon::alihkan(Auth::beranda($u));
    }

    public function keluar(array $p): void
    {
        Auth::keluar();
        Auth::mulaiSesi();
        Respon::alihkan('/');
    }

    // ---------- Daftar ----------
    public function formDaftar(array $p): void
    {
        $this->tampil('auth/daftar', ['kodeAwal' => strtoupper(trim((string) ($_GET['kode'] ?? '')))], 'auth');
    }

    public function cekKode(array $p): void
    {
        $k = Kelas::byKode((string) ($_GET['kode'] ?? ''));
        if (!$k) {
            Respon::json(['ok' => false, 'pesan' => 'Kode kelas tidak ditemukan.']);
        }
        Respon::json(['ok' => true, 'kelas' => $k['nama'], 'guru' => $k['guru_nama']]);
    }

    public function daftar(array $p): void
    {
        $d = [
            'nama' => preg_replace('/\s+/', ' ', $this->teks('nama', 100)),
            'nis' => $this->teks('nis', 30),
            'email' => strtolower($this->teks('email', 190)),
            'kode' => strtoupper($this->teks('kode', 20)),
        ];
        $sandi = (string) ($_POST['password'] ?? '');
        $ulang = (string) ($_POST['password2'] ?? '');
        simpan_lama($d);

        $galat = [];
        if (mb_strlen($d['nama']) < 3) {
            $galat[] = 'Nama lengkap minimal 3 huruf.';
        }
        if (!Pengguna::nisValid($d['nis'])) {
            $galat[] = 'Isi NIS atau nomor absen (huruf/angka, maksimal 30 karakter).';
        }
        if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
            $galat[] = 'Format email belum benar.';
        }
        if (strlen($sandi) < 8) {
            $galat[] = 'Password minimal 8 karakter.';
        }
        if ($sandi !== $ulang) {
            $galat[] = 'Konfirmasi password tidak sama.';
        }
        $kelas = Kelas::byKode($d['kode']);
        if (!$kelas) {
            $galat[] = 'Kode kelas tidak ditemukan. Tanyakan ke gurumu.';
        }
        if (!$galat && Pengguna::emailAda($d['email'])) {
            $galat[] = 'Email ini sudah terdaftar. Coba masuk.';
        }
        if (!$galat && Kelas::nisDipakai((int) $kelas['id'], $d['nis'])) {
            $galat[] = 'NIS/nomor absen ini sudah dipakai temanmu di kelas ' . $kelas['nama'] . '. Periksa lagi, atau hubungi gurumu.';
        }
        if ($galat) {
            flash('galat', implode("\n", $galat));
            Respon::alihkan('/daftar');
        }

        try {
            $id = Pengguna::buatSiswa($d['nama'], $d['nis'], $d['email'], $sandi, (int) $kelas['id']);
        } catch (PDOException $e) {
            // Balapan: dua orang mendaftar bersamaan dengan email yang sama.
            flash('galat', 'Email ini sudah terdaftar. Coba masuk.');
            Respon::alihkan('/daftar');
        }
        hapus_lama();
        Auth::masuk($id);
        flash('sukses', 'Akun dibuat. Selamat datang di kelas ' . $kelas['nama'] . '!');
        Respon::alihkan('/siswa');
    }

    // ---------- Ganti password ----------
    public function formPassword(array $p): void
    {
        $this->tampil('akun/password', ['wajib' => (int) Auth::pengguna()['harus_ganti_password'] === 1]);
    }

    public function ubahPassword(array $p): void
    {
        $u = Auth::pengguna();
        $lama = (string) ($_POST['lama'] ?? '');
        $baru = (string) ($_POST['baru'] ?? '');
        $ulang = (string) ($_POST['baru2'] ?? '');
        $baris = Pengguna::byId((int) $u['id']);
        $galat = [];
        if (!password_verify($lama, $baris['password_hash'])) {
            $galat[] = 'Password saat ini salah.';
        }
        if (strlen($baru) < 8) {
            $galat[] = 'Password baru minimal 8 karakter.';
        }
        if ($baru !== $ulang) {
            $galat[] = 'Konfirmasi password baru tidak sama.';
        }
        if ($baru !== '' && $baru === $lama) {
            $galat[] = 'Password baru harus berbeda dari yang lama.';
        }
        if ($galat) {
            flash('galat', implode("\n", $galat));
            Respon::alihkan('/akun/password');
        }
        Pengguna::setSandi((int) $u['id'], $baru, false);
        session_regenerate_id(true);
        flash('sukses', 'Password berhasil diganti.');
        Respon::alihkan(Auth::beranda($u));
    }
}
