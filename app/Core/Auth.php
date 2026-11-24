<?php
// Sesi login. Data pengguna diambil ulang dari database di setiap permintaan,
// jadi akun yang dinonaktifkan langsung tidak bisa dipakai lagi.
final class Auth
{
    private static ?array $pengguna = null;
    private static bool $sudahCek = false;

    public static function mulaiSesi(): void
    {
        // Cookie "secure" hanya bila halaman memang dibuka lewat HTTPS. Kalau dipaksa di HTTP biasa
        // (mis. sertifikat SSL hosting belum terpasang), browser menolak cookie sesi -> login gagal "sesi kedaluwarsa".
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https'
            || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443';
        $aman = Env::get('APP_ENV') === 'production' && $https;
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_name('mikronet');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $aman,      // cookie hanya lewat HTTPS di produksi
            'httponly' => true,     // tidak bisa dibaca JavaScript
            'samesite' => 'Lax',    // membantu melawan CSRF
        ]);
        session_start();
    }

    public static function pengguna(): ?array
    {
        if (self::$sudahCek) {
            return self::$pengguna;
        }
        self::$sudahCek = true;
        $id = $_SESSION['uid'] ?? null;
        if ($id) {
            $u = DB::satu('SELECT id, nama, email, peran, nis, aktif, harus_ganti_password FROM users WHERE id = ?', [$id]);
            self::$pengguna = ($u && (int) $u['aktif'] === 1) ? $u : null;
        }
        return self::$pengguna;
    }

    public static function id(): int
    {
        return (int) (self::pengguna()['id'] ?? 0);
    }

    public static function masuk(int $userId): void
    {
        session_regenerate_id(true); // cegah session fixation
        $_SESSION['uid'] = $userId;
        self::$sudahCek = false;
    }

    public static function keluar(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $c = session_get_cookie_params();
            setcookie(session_name(), '', time() - 3600, $c['path'], $c['domain'], $c['secure'], $c['httponly']);
        }
        session_destroy();
        self::$pengguna = null;
        self::$sudahCek = false;
    }

    /** Alamat beranda sesuai peran. */
    public static function beranda(?array $u = null): string
    {
        $u = $u ?? self::pengguna();
        return match ($u['peran'] ?? '') {
            'admin' => '/admin',
            'guru' => '/guru',
            'siswa' => '/siswa',
            default => '/masuk',
        };
    }
}
