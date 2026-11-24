<?php
// Fungsi bantu kecil yang dipakai di banyak tempat (terutama di tampilan).

/** Escape teks sebelum dicetak ke HTML. WAJIB untuk semua data dari pengguna/database. */
function e(mixed $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    return Kernel::base() . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/')) . '?v=' . ASSET_VERSION;
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(Csrf::token()) . '">';
}

/** Pesan sekali-tampil (mis. "Berhasil disimpan") yang bertahan satu kali pindah halaman. */
function flash(string $jenis, string $pesan): void
{
    $_SESSION['flash'][] = ['jenis' => $jenis, 'pesan' => $pesan];
}

function ambil_flash(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/** Isian form sebelumnya, supaya pengguna tidak mengetik ulang saat ada kesalahan. */
function lama(string $kunci, string $bawaan = ''): string
{
    return e($_SESSION['lama'][$kunci] ?? $bawaan);
}

function simpan_lama(array $data, array $kecuali = ['password', '_csrf']): void
{
    $_SESSION['lama'] = array_diff_key($data, array_flip($kecuali));
}

function hapus_lama(): void
{
    unset($_SESSION['lama']);
}

function icon(string $nama, int $ukuran = 20, string $kelas = ''): string
{
    static $d = null;
    $d ??= require ROOT . '/app/Core/icons.php';
    $isi = $d['ikon'][$nama] ?? '';
    return '<svg class="ic ' . e($kelas) . '" width="' . $ukuran . '" height="' . $ukuran . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $isi . '</svg>';
}

function hari_id(string $tanggal, bool $panjang = false): string
{
    $t = strtotime($tanggal);
    if (!$t) {
        return '';
    }
    $hari = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'][(int) date('w', $t)];
    $bulan = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'][(int) date('n', $t)];
    return $hari . ', ' . (int) date('j', $t) . ' ' . $bulan . ($panjang ? ' ' . date('Y', $t) : '');
}

function tgl_pendek(string $tanggal): string
{
    $t = strtotime($tanggal);
    $bulan = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'][(int) date('n', $t)];
    return (int) date('j', $t) . ' ' . $bulan;
}

function kategori_nilai(int $nilai): string
{
    return match (true) {
        $nilai >= 86 => 'Sangat Baik',
        $nilai >= 71 => 'Baik',
        $nilai >= 56 => 'Cukup',
        default => 'Perlu Bimbingan',
    };
}

/** Kode kelas acak yang mudah dibaca (tanpa huruf/angka yang mirip: 0 O 1 I). */
function buat_kode_kelas(string $nama): string
{
    $awal = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $nama));
    $awal = substr($awal ?: 'KLS', 0, 6);
    $abjad = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $acak = '';
    for ($i = 0; $i < 4; $i++) {
        $acak .= $abjad[random_int(0, strlen($abjad) - 1)];
    }
    return $awal . '-' . $acak;
}

function sandi_acak(int $panjang = 10): string
{
    $abjad = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $s = '';
    for ($i = 0; $i < $panjang; $i++) {
        $s .= $abjad[random_int(0, strlen($abjad) - 1)];
    }
    return $s;
}

function sekarang(): string
{
    return date('Y-m-d H:i:s');
}

function input_json(): array
{
    $j = json_decode(file_get_contents('php://input') ?: '', true);
    return is_array($j) ? $j : [];
}

/** Lencana status kecil (Selesai / Berjalan / Belum / Segera). */
/** Label modul untuk soal kuis: 0 = materi umum. */
function label_modul(int $n, bool $pendek = false): string
{
    return $n === 0 ? 'Umum' : ($pendek ? 'M' : 'Modul ') . $n;
}

function lencana(string $status): string
{
    $peta = [
        'selesai' => ['ok', 'Selesai'], 'berjalan' => ['warn', 'Berjalan'], 'belum' => ['grey', 'Belum'],
        'segera' => ['grey', 'Segera'], 'terbit' => ['ok', 'Terbuka'], 'terkunci' => ['grey', 'Terkunci'],
        'draf' => ['warn', 'Draf'], 'dikumpulkan' => ['ok', 'Dikumpulkan'], 'dinilai' => ['ok', 'Dinilai'],
        'berlangsung' => ['blue', 'Berlangsung'], 'dibuka' => ['ok', 'Dibuka'], 'ditutup' => ['grey', 'Ditutup'], 'siap' => ['blue', 'Siap dikerjakan'],
    ];
    [$warna, $teks] = $peta[$status] ?? ['grey', ucfirst($status)];
    return '<span class="lencana lencana--' . $warna . '">' . e($teks) . '</span>';
}

/**
 * Motif jaringan dari logo: titik + garis. Titik kuning = titik aktif (bercahaya pelan),
 * beberapa garis membawa "paket" kecil. Gerak dimatikan CSS bila pengguna memilih gerak dikurangi.
 */
function jaringan(): string
{
    $titik = [[.06, .78, 5, 0], [.2, .42, 7, 1], [.36, .72, 5, 0], [.5, .26, 9, 1], [.66, .62, 6, 0], [.8, .22, 7, 0], [.93, .55, 6, 1], [.58, .92, 4, 0], [.3, .1, 4, 0], [.84, .86, 4, 0]];
    $garis = [[0, 1], [1, 2], [1, 3], [2, 4], [3, 4], [3, 5], [4, 6], [5, 6], [2, 7], [4, 7], [8, 3], [8, 1], [6, 9], [4, 9]];
    $paket = [1 => 0, 4 => 1.8, 6 => 3.4, 10 => 5.1];
    [$w, $h] = [600, 400];
    $s = '<svg viewBox="0 0 600 400" preserveAspectRatio="xMidYMid slice" aria-hidden="true" focusable="false">';
    foreach ($garis as $i => [$a, $b]) {
        $k = sprintf('x1="%d" y1="%d" x2="%d" y2="%d"', $titik[$a][0] * $w, $titik[$a][1] * $h, $titik[$b][0] * $w, $titik[$b][1] * $h);
        $s .= '<line class="garis" ' . $k . '/>';
        if (isset($paket[$i])) {
            $s .= '<line class="paket" pathLength="400" style="animation-delay:' . $paket[$i] . 's" ' . $k . '/>';
        }
    }
    foreach ($titik as $i => [$x, $y, $r, $aktif]) {
        $cx = (int) ($x * $w);
        $cy = (int) ($y * $h);
        if ($aktif) {
            $s .= sprintf('<circle class="halo" style="animation-delay:%.1fs" cx="%d" cy="%d" r="%d"/><circle class="titik-aktif" cx="%d" cy="%d" r="%d"/>', $i * .7, $cx, $cy, $r + 7, $cx, $cy, $r);
        } else {
            $s .= sprintf('<circle class="titik" cx="%d" cy="%d" r="%d"/>', $cx, $cy, $r);
        }
    }
    return $s . '</svg>';
}

/** Cincin kemajuan (angka di tengah). Warnanya dari CSS supaya ikut tema; terisi dari 0 saat tampil. */
function cincin(int $selesai, int $total, int $ukuran = 104): string
{
    $r = 42;
    $keliling = round(2 * M_PI * $r, 1);
    $frac = $total > 0 ? min(1, $selesai / $total) : 0;
    $sisa = round($keliling * (1 - $frac), 1);
    return '<div class="cincin" style="width:' . $ukuran . 'px;height:' . $ukuran . 'px" role="img" aria-label="' . $selesai . ' dari ' . $total . ' langkah selesai">'
        . '<svg viewBox="0 0 100 100" width="' . $ukuran . '" height="' . $ukuran . '" aria-hidden="true"><circle class="jalur" cx="50" cy="50" r="' . $r . '" fill="none" stroke-width="10"/>'
        . '<circle class="isi-c" cx="50" cy="50" r="' . $r . '" fill="none" stroke-width="10" stroke-dasharray="' . $keliling . '" stroke-dashoffset="' . $sisa . '" style="--k:' . $keliling . '"/></svg>'
        . '<span aria-hidden="true">' . $selesai . '/' . $total . '</span></div>';
}

/** Tombol ganti tema terang/gelap. Pilihan disimpan di localStorage oleh app.js. */
function tombol_tema(): string
{
    return '<button type="button" class="tombol-ikon tombol-tema" data-tema aria-pressed="false">'
        . '<span class="ic-bulan">' . icon('moon', 20) . '</span><span class="ic-matahari">' . icon('sun', 20) . '</span>'
        . '<span class="sr">Tema gelap</span></button>';
}

function inisial(string $nama): string
{
    return mb_strtoupper(mb_substr(trim($nama), 0, 1)) ?: '?';
}

function ukuran_berkas(int $b): string
{
    return $b >= 1048576 ? round($b / 1048576, 1) . ' MB' : max(1, (int) round($b / 1024)) . ' KB';
}

/** Data kecil yang diisi tampilan tapi dibaca tata letak (mis. judul halaman, skrip tambahan). */
function meta(string $kunci, mixed $nilai = null): mixed
{
    static $d = [];
    if (func_num_args() > 1) {
        $d[$kunci] = $nilai;
    }
    return $d[$kunci] ?? null;
}

/** Waktu relatif singkat: "baru saja", "5 menit lalu", "3 jam lalu", "kemarin 14.05", atau tanggal. */
function waktu_lalu(string $waktu): string
{
    $t = strtotime($waktu);
    $d = time() - $t;
    return match (true) {
        $d < 60 => 'baru saja',
        $d < 3600 => intdiv($d, 60) . ' menit lalu',
        date('Y-m-d', $t) === date('Y-m-d') => intdiv($d, 3600) . ' jam lalu',
        date('Y-m-d', $t) === date('Y-m-d', time() - 86400) => 'kemarin ' . date('H.i', $t),
        default => tgl_pendek(date('Y-m-d', $t)),
    };
}
