<?php
// Kelas induk semua controller: bantuan menampilkan halaman dengan menu sesuai peran.
abstract class Kontroler
{
    protected function tampil(string $view, array $data = [], string $layout = 'app'): void
    {
        $u = Auth::pengguna();
        View::tampil($view, $data + ['pengguna' => $u, 'menu' => $u ? self::menu($u['peran']) : []], $layout);
    }

    protected static function menu(string $peran): array
    {
        return match ($peran) {
            'siswa' => [['/siswa', 'Beranda', 'home'], ['/modul', 'Modul', 'grid'], ['/diskusi', 'Diskusi', 'chat'], ['/kuis', 'Kuis', 'edit'], ['/nilai', 'Nilai', 'award'], ['/alat/subnet', 'Alat bantu', 'wrench']],
            'guru' => [['/guru', 'Beranda', 'home'], ['/guru/kelas', 'Kelas', 'users'], ['/guru/kemajuan', 'Kemajuan', 'award'], ['/guru/penilaian', 'Penilaian', 'clip'], ['/guru/analisis', 'Analisis', 'chart'], ['/diskusi', 'Diskusi', 'chat'], ['/kelola/modul', 'Modul', 'layers'], ['/kelola/kuis', 'Kuis', 'edit']],
            'admin' => [['/admin', 'Ringkasan', 'home'], ['/admin/pengguna', 'Pengguna', 'users'], ['/kelola/modul', 'Modul', 'layers'], ['/kelola/kuis', 'Kuis', 'edit'], ['/admin/kelas', 'Kelas', 'grid'], ['/guru/penilaian', 'Penilaian', 'clip'], ['/guru/analisis', 'Analisis', 'chart'], ['/diskusi', 'Diskusi', 'chat']],
            default => [],
        };
    }

    /** Kelas yang boleh dilihat pengguna: admin semua, guru hanya kelas miliknya. */
    protected function kelasSaya(): array
    {
        $u = Auth::pengguna();
        return $u['peran'] === 'admin' ? Kelas::semua() : Kelas::milikGuru((int) $u['id']);
    }

    protected function teks(string $kunci, int $maks = 200): string
    {
        return mb_substr(trim((string) ($_POST[$kunci] ?? '')), 0, $maks);
    }

    protected function id(array $p, string $k = 'id'): int
    {
        return (int) ($p[$k] ?? 0);
    }

    /** Ambil modul yang boleh dibuka siswa. Modul "segera"/"terkunci" ditolak dengan pesan ramah. */
    protected function modulTerbuka(array $p): array
    {
        $m = Modul::byId($this->id($p));
        if (!$m) {
            Respon::galat(404, 'Modul tidak ditemukan.');
        }
        if ($m['status'] !== 'terbit') {
            flash('info', 'Modul ' . $m['nomor'] . ' belum dibuka.');
            Respon::alihkan('/modul');
        }
        return $m;
    }

    /**
     * Pastikan langkah $kunci sudah boleh dibuka siswa (langkah sebelumnya selesai). Bila belum, kembalikan ke daftar langkah.
     * Dengan $json = true (permintaan AJAX) jawabannya JSON 403, bukan pengalihan.
     */
    protected function langkahBuka(array $m, string $kunci, bool $json = false): void
    {
        if (Modul::langkahTerbuka($m, Auth::id(), $kunci)) {
            return;
        }
        if ($json) {
            Respon::json(['ok' => false, 'pesan' => 'Selesaikan langkah sebelumnya dulu.'], 403);
        }
        flash('info', 'Selesaikan langkah sebelumnya dulu, baru langkah ini terbuka.');
        Respon::alihkan('/modul/' . $m['id']);
    }
}
