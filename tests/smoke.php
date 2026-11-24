<?php
date_default_timezone_set("Asia/Jakarta"); // sama dengan zona waktu aplikasi (APP_TZ)
// Uji asap (smoke test) dari luar: menjalankan alur nyata lewat HTTP terhadap server yang sedang jalan.
// Pemakaian:  php tests/smoke.php http://localhost:8080
// Database HARUS baru dengan data uji (schema.sql + seed-modul.sql + seed-dev.sql).
// Jalankan dengan LANGKAH_URUT=1 di .env: sebagian uji memeriksa penguncian langkah modul berurutan.
$base = rtrim($argv[1] ?? 'http://localhost:8080', '/');
$lulus = 0; $gagal = 0;

function cek(string $nama, bool $ok, string $info = ''): void
{
    global $lulus, $gagal;
    if ($ok) { $lulus++; echo "  [ok]   $nama\n"; } else { $gagal++; echo "  [GAGAL] $nama" . ($info ? " -> $info" : '') . "\n"; }
}

final class Klien
{
    public string $jar;
    public function __construct(public string $base) { $this->jar = tempnam(sys_get_temp_dir(), 'jar'); }
    public function req(string $method, string $path, array $opsi = []): array
    {
        $ch = curl_init($this->base . $path);
        $h = $opsi['header'] ?? [];
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar,
            CURLOPT_CUSTOMREQUEST => $method, CURLOPT_FOLLOWLOCATION => $opsi['ikuti'] ?? false, CURLOPT_TIMEOUT => 30]);
        if (isset($opsi['post'])) { curl_setopt($ch, CURLOPT_POSTFIELDS, $opsi['post']); }
        if (isset($opsi['json'])) { curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($opsi['json'])); $h[] = 'Content-Type: application/json'; $h[] = 'Accept: application/json'; $h[] = 'X-Requested-With: fetch'; }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $h);
        $r = curl_exec($ch);
        $kode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $hs = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        $head = substr((string) $r, 0, $hs);
        return ['kode' => $kode, 'head' => $head, 'isi' => substr((string) $r, $hs),
            'lokasi' => preg_match('/^Location:\s*(\S+)/mi', $head, $m) ? $m[1] : '',
            'tipe' => preg_match('/^Content-Type:\s*([^\r\n;]+)/mi', $head, $m2) ? trim($m2[1]) : ''];
    }
    public function get(string $p, array $o = []): array { return $this->req('GET', $p, $o); }
    public function token(string $halaman = '/masuk'): string
    {
        $r = $this->get($halaman, ['ikuti' => true]);
        return preg_match('/name="csrf-token" content="([a-f0-9]+)"/', $r['isi'], $m) ? $m[1] : '';
    }
    public function kirim(string $p, array $data = [], string $tokenDari = '/masuk', array $o = []): array
    {
        $data['_csrf'] = $this->tokenTerakhir ?: $this->token($tokenDari);
        return $this->req('POST', $p, $o + ['post' => http_build_query($data)]);
    }
    public string $tokenTerakhir = '';
    public function masuk(string $email, string $sandi): array
    {
        $this->tokenTerakhir = $this->token('/masuk');
        $r = $this->req('POST', '/masuk', ['post' => http_build_query(['_csrf' => $this->tokenTerakhir, 'email' => $email, 'password' => $sandi])]);
        return $r;
    }
    /** Ambil token dari halaman aplikasi yang sedang dibuka (untuk POST setelah login). */
    public function segarkan(string $halaman): string { return $this->tokenTerakhir = $this->token($halaman); }
    public function postJson(string $p, array $data, bool $denganToken = true): array
    {
        $h = $denganToken ? ['X-CSRF-TOKEN: ' . $this->tokenTerakhir] : [];
        return $this->req('POST', $p, ['json' => $data, 'header' => $h]);
    }
}

echo "== Halaman umum ==\n";
$tamu = new Klien($base);
$r = $tamu->get('/');
cek('landing 200', $r['kode'] === 200 && str_contains($r['isi'], 'MIKRO-NET'));
cek('landing memuat tautan masuk dan daftar', str_contains($r['isi'], '/masuk') && str_contains($r['isi'], '/daftar'));
$r = $tamu->get('/health'); $j = json_decode($r['isi'], true);
cek('health ok + 10 modul', ($j['ok'] ?? false) && $j['modul'] === 10, $r['isi']);
cek('halaman tidak ada -> 404', $tamu->get('/tidak-ada')['kode'] === 404);
cek('/siswa tanpa login -> alih ke /masuk', str_ends_with($tamu->get('/siswa')['lokasi'], '/masuk'));
cek('/admin tanpa login -> alih ke /masuk', str_ends_with($tamu->get('/admin')['lokasi'], '/masuk'));
cek('POST tanpa token CSRF ditolak (419)', $tamu->req('POST', '/masuk', ['post' => 'email=a&password=b'])['kode'] === 419);
$r = $tamu->get('/daftar/cek-kode?kode=TKJ1-7K2Q'); $j = json_decode($r['isi'], true);
cek('cek kode kelas benar', ($j['ok'] ?? false) && $j['kelas'] === 'XI TKJ 1');
cek('cek kode kelas salah', !(json_decode($tamu->get('/daftar/cek-kode?kode=SALAH-0000')['isi'], true)['ok'] ?? true));

echo "== Daftar siswa ==\n";
$s1 = new Klien($base);
$s1->tokenTerakhir = $s1->token('/daftar');
$post = fn(array $d) => $s1->req('POST', '/daftar', ['post' => http_build_query($d + ['_csrf' => $s1->tokenTerakhir])], );
$dasar = ['nama' => 'Budi Santoso', 'nis' => '2025101', 'email' => 'budi@contoh.id', 'password' => 'rahasia123', 'password2' => 'rahasia123', 'kode' => 'TKJ1-7K2Q'];
$r = $post(['kode' => 'SALAH-1234'] + $dasar);
cek('daftar dengan kode salah ditolak', str_ends_with($r['lokasi'], '/daftar'));
$r = $post(['password2' => 'beda'] + $dasar);
cek('daftar dengan konfirmasi password beda ditolak', str_ends_with($r['lokasi'], '/daftar'));
$r = $post(['password' => 'pendek', 'password2' => 'pendek'] + $dasar);
cek('daftar dengan password pendek ditolak', str_ends_with($r['lokasi'], '/daftar'));
$r = $post(['email' => 'siswa1@mikronet.test'] + $dasar);
cek('daftar dengan email yang sudah ada ditolak', str_ends_with($r['lokasi'], '/daftar'));
$r = $post($dasar);
cek('daftar berhasil -> alih ke /siswa', str_ends_with($r['lokasi'], '/siswa'), $r['lokasi']);
$r = $s1->get('/siswa');
cek('beranda siswa tampil untuk akun baru', $r['kode'] === 200 && str_contains($r['isi'], 'Halo, Budi'));
cek('daftar lagi saat sudah login -> dialihkan', $s1->get('/daftar')['kode'] === 302);

echo "== Login, throttling, keluar ==\n";
$x = new Klien($base);
$r = $x->masuk('siswa1@mikronet.test', 'salah'); cek('login salah kembali ke /masuk', str_ends_with($r['lokasi'], '/masuk'));
$r = $x->get('/masuk', ['ikuti' => false]); cek('login form 200', $r['kode'] === 200 && str_contains($r['isi'], 'Email atau password salah'));
$x2 = new Klien($base);
for ($i = 0; $i < 6; $i++) { $x2->masuk('korban@contoh.id', 'salah' . $i); }
$r = $x2->get('/masuk'); 
cek('setelah 5x gagal, akun dibatasi sementara', str_contains($r['isi'], 'Terlalu banyak') || str_contains($x2->get('/masuk')['isi'], 'Terlalu banyak'));
$a = new Klien($base);
$r = $a->masuk('SISWA1@mikronet.test', 'Siswa12345');
cek('login siswa (email tak peka huruf besar)', str_ends_with($r['lokasi'], '/siswa'), $r['lokasi']);
$r = $a->get('/guru/kelas'); cek('siswa tidak bisa buka /guru/kelas (403)', $r['kode'] === 403);
$r = $a->get('/admin'); cek('siswa tidak bisa buka /admin (403)', $r['kode'] === 403);
$a->segarkan('/siswa');
$r = $a->req('POST', '/keluar', ['post' => '']); cek('keluar tanpa CSRF ditolak', $r['kode'] === 419);

echo "== Modul & materi (siswa1) ==\n";
$r = $a->get('/modul'); cek('daftar modul 200, ada 10 kartu', $r['kode'] === 200 && substr_count($r['isi'], 'class="mcard') === 10, (string) substr_count($r['isi'], 'class="mcard'));
$r = $a->get('/modul/4'); cek('Modul 4 (wajanbolic) terbit dan bisa dibuka', $r['kode'] === 200 && str_contains($r['isi'], 'Wajanbolic'));
$r = $a->get('/modul/7'); cek('modul "terkunci" tidak bisa dibuka', str_ends_with($r['lokasi'], '/modul'));
$r = $a->get('/modul/1'); cek('detail modul 1 tampil 6 langkah', $r['kode'] === 200 && substr_count($r['isi'], 'class="lk ') === 6);
cek('langkah 2-6 terkunci sebelum materi selesai', substr_count($r['isi'], 'lk--terkunci') === 5 && str_contains($r['isi'], 'Selesaikan langkah sebelumnya'), (string) substr_count($r['isi'], 'lk--terkunci'));
$r = $a->get('/modul/1/materi'); cek('halaman materi 200', $r['kode'] === 200 && str_contains($r['isi'], 'id="pv"') && str_contains($r['isi'], 'Materi 1 dari 6'));
cek('tombol Lanjut materi tersembunyi sebelum selesai baca', str_contains($r['isi'], 'id="lanjut" hidden'));
$r = $a->get('/modul/1/materi/pdf'); cek('PDF materi dilayani (application/pdf)', $r['kode'] === 200 && $r['tipe'] === 'application/pdf' && str_starts_with($r['isi'], '%PDF'));
cek('PDF materi = slide ajar Pertemuan 1 (bukan PDF contoh)', md5($r['isi']) === md5_file(__DIR__ . '/../database/content/materi-p1.pdf'));
$r = $tamu->get('/modul/1/materi/pdf'); cek('PDF materi tanpa login ditolak', $r['kode'] === 302);
$r = $a->postJson('/modul/1/materi/progres', ['halaman' => 3, 'total' => 20], false); cek('progres tanpa token CSRF -> 419', $r['kode'] === 419);
$r = $a->postJson('/modul/1/materi/progres', ['halaman' => 3, 'total' => 20]); cek('simpan progres materi', ($r['kode'] === 200) && (json_decode($r['isi'], true)['ok'] ?? false), $r['isi']);
$a->postJson('/modul/1/materi/progres', ['halaman' => 2, 'total' => 20]);
$r = $a->get('/modul/1'); cek('progres tidak mundur (tetap 3 halaman)', str_contains($r['isi'], '3 sudah dibaca'));
$r = $a->get('/modul/1/pretest'); cek('pretest per modul (Google Form) sudah dihapus -> 404', $r['kode'] === 404);

echo "== Kunci langkah berurutan ==\n";
$r = $a->get('/modul/1/simulasi'); cek('simulasi terkunci sebelum materi selesai (dialihkan ke modul)', $r['kode'] === 302 && str_ends_with($r['lokasi'], '/modul/1'), $r['lokasi'] . ' ' . $r['kode']);
$r = $a->get('/modul/1/praktikum'); cek('praktikum terkunci', $r['kode'] === 302 && str_ends_with($r['lokasi'], '/modul/1'));
$r = $a->get('/modul/1/lkpd'); cek('pengumpulan terkunci', $r['kode'] === 302 && str_ends_with($r['lokasi'], '/modul/1'));
$r = $a->postJson('/modul/1/simulasi/hasil', ['skor' => 80, 'skorMaks' => 100]); cek('POST skor game ditolak saat terkunci (403)', $r['kode'] === 403, $r['isi']);
$r = $a->postJson('/modul/1/praktikum/progres', ['langkah' => 3]); cek('POST progres praktikum ditolak saat terkunci (403)', $r['kode'] === 403);
$r = $a->postJson('/modul/1/lkpd/simpan', ['jawaban' => ['kelompok' => 'x']]); cek('simpan pengumpulan ditolak saat terkunci (403)', $r['kode'] === 403);
$a->postJson('/modul/1/materi/progres', ['halaman' => 20, 'total' => 20]);
$r = $a->get('/modul/1'); cek('materi selesai -> simulasi terbuka, praktikum/pengumpulan/LKPD/refleksi masih terkunci', substr_count($r['isi'], 'lk--terkunci') === 4 && str_contains($r['isi'], 'lk--selesai'));
$r = $a->get('/modul/1/materi'); cek('tombol Lanjut ke Simulasi tampil setelah materi selesai', (bool) preg_match('/id="lanjut"(?! hidden)/', $r['isi']) && str_contains($r['isi'], 'Lanjut ke Simulasi'));
$r = $a->get('/modul/1/praktikum'); cek('praktikum masih terkunci (simulasi belum)', $r['kode'] === 302);

echo "== Simulasi (game DMI) ==\n";
$r = $a->get('/modul/1/simulasi'); cek('simulasi memuat iframe bersandbox ke game simulasi-p1', str_contains($r['isi'], 'sandbox="allow-scripts') && str_contains($r['isi'], '/games/simulasi-p1/index.html?v=') && !str_contains($r['isi'], 'Konten contoh'));
cek('iframe simulasi boleh memuat CSS dari situs sendiri (allow-same-origin)', str_contains($r['isi'], 'allow-same-origin'));
cek('tombol Lanjut ke Praktikum tersembunyi sebelum ada skor', str_contains($r['isi'], 'id="lanjut" hidden') && str_contains($r['isi'], 'Lanjut ke Praktikum'));
foreach (['index.html', 'ip-alamat.html', 'subnetmask.html', 'topologi.html', 'hasil.html', 'style.css', 'script.js'] as $fg) {
    $rg = $a->get('/games/simulasi-p1/' . $fg); cek('berkas game ' . $fg . ' dilayani', $rg['kode'] === 200, (string) $rg['kode']);
}
$rg = $a->get('/games/simulasi-p1/script.js'); cek('game melapor skor lewat mikronet:hasil', str_contains($rg['isi'], 'mikronet:hasil'));
$r = $a->postJson('/modul/1/simulasi/hasil', ['skor' => 70, 'skorMaks' => 100]); cek('skor game tercatat', json_decode($r['isi'], true)['ok'] ?? false, $r['isi']);
$r = $a->postJson('/modul/1/simulasi/progres', ['data' => json_encode(['mikronet_p1_v2' => ['prog' => ['m1' => ['selesai' => true, 'benar' => 4]], 'kuis' => ['m2' => ['urut' => [0, 1, 2, 3, 4], 'jawab' => ['0' => 2]]]]])]);
cek('progres game tersimpan di server', $r['kode'] === 200 && str_contains($r['isi'], '"ok":true'), $r['isi']);
$r = $a->postJson('/modul/1/simulasi/progres', ['data' => 'bukan json']); cek('progres game bukan JSON ditolak (422)', $r['kode'] === 422);
$r = $a->postJson('/modul/1/simulasi/progres', ['data' => str_repeat('x', 70000)]); cek('progres game terlalu besar ditolak (422)', $r['kode'] === 422);
$r = $a->get('/modul/1/simulasi'); cek('progres game dipasang lagi di iframe (atribut name)', str_contains($r['isi'], 'name="{&quot;mikronet_p1_v2&quot;') && str_contains($r['isi'], 'data-progres='));
$r = $a->postJson('/modul/1/simulasi/hasil', ['skor' => 9, 'skorMaks' => 5]); cek('skor tidak valid ditolak (422)', $r['kode'] === 422);
$a->postJson('/modul/1/simulasi/hasil', ['skor' => 30, 'skorMaks' => 100]);
$r = $a->get('/modul/1/simulasi'); cek('skor terbaik dipertahankan (70/100)', str_contains($r['isi'], '70 / 100'));
cek('tombol Lanjut ke Praktikum tampil setelah ada skor', (bool) preg_match('/id="lanjut"(?! hidden)/', $r['isi']));

echo "== Praktikum ==\n";
$r = $a->get('/modul/1/praktikum'); cek('praktikum terbuka setelah simulasi', $r['kode'] === 200 && substr_count($r['isi'], 'class="pk-langkah') === 18 && str_contains($r['isi'], 'data-total="18"'), (string) $r['kode']);
cek('praktikum memuat gambar langkah dan tabel IP', str_contains($r['isi'], '/assets/praktikum/p1/g02.png') && str_contains($r['isi'], '192.168.1.2') && str_contains($r['isi'], 'Troubleshooting'));
$rg = $a->get('/assets/praktikum/p1/g19.png'); cek('gambar praktikum dilayani', $rg['kode'] === 200);
cek('tombol Lanjut ke Pengumpulan tersembunyi sebelum selesai', str_contains($r['isi'], 'id="lanjut" hidden') && str_contains($r['isi'], 'Lanjut ke Pengumpulan'));
$r = $a->get('/modul/1/lkpd'); cek('pengumpulan masih terkunci sebelum praktikum selesai', $r['kode'] === 302);
$r = $a->postJson('/modul/1/praktikum/progres', ['langkah' => 5], false); cek('progres praktikum tanpa CSRF -> 419', $r['kode'] === 419);
$r = $a->postJson('/modul/1/praktikum/progres', ['langkah' => 5]); $j = json_decode($r['isi'], true); cek('progres praktikum tersimpan (5)', ($j['ok'] ?? false) && $j['langkah'] === 5, $r['isi']);
$r = $a->postJson('/modul/1/praktikum/progres', ['langkah' => 2]); $j = json_decode($r['isi'], true); cek('progres praktikum tidak mundur', $j['langkah'] === 5);
$r = $a->postJson('/modul/1/praktikum/progres', ['langkah' => 99]); $j = json_decode($r['isi'], true); cek('progres praktikum dibatasi total langkah (18)', $j['langkah'] === 18);
$a->postJson('/modul/1/praktikum/progres', ['langkah' => 0]);
$kx = new Klien($base); $kx->masuk('budi@contoh.id', 'rahasia123'); $kx->postJson('/modul/1/materi/progres', ['halaman' => 20, 'total' => 20]); $kx->postJson('/modul/1/simulasi/hasil', ['skor' => 50, 'skorMaks' => 100]);
$r = $kx->postJson('/modul/1/praktikum/progres', ['langkah' => 7, 'selesai' => true]); cek('tandai selesai sebelum langkah terakhir ditolak (422)', $r['kode'] === 422, $r['isi']);
$r = $kx->get('/modul/1/lkpd'); cek('pengumpulan tetap terkunci untuk siswa lain yang belum selesai praktikum', $r['kode'] === 302);
$r = $a->postJson('/modul/1/praktikum/progres', ['langkah' => 18, 'selesai' => true]); $j = json_decode($r['isi'], true); cek('selesai praktikum diterima di langkah terakhir', ($j['ok'] ?? false) && $j['selesai'] === true, $r['isi']);
$r = $a->get('/modul/1/praktikum'); cek('tombol Lanjut ke Pengumpulan tampil setelah selesai', (bool) preg_match('/id="lanjut"(?! hidden)/', $r['isi']));
$r = $a->get('/modul/1'); cek('3 langkah selesai, pengumpulan berikutnya, LKPD dan refleksi masih terkunci', substr_count($r['isi'], 'lk--selesai') === 3 && substr_count($r['isi'], 'lk--terkunci') === 2);
$r = $a->get('/modul/1/lembar'); cek('LKPD terkunci sebelum pengumpulan', $r['kode'] === 302 && str_ends_with($r['lokasi'], '/modul/1'));
$r = $a->get('/modul/1/refleksi'); cek('refleksi terkunci sebelum pengumpulan', $r['kode'] === 302 && str_ends_with($r['lokasi'], '/modul/1'));
$r = $a->get('/alat/subnet'); cek('alat bantu tampil "Segera hadir"', $r['kode'] === 200 && str_contains($r['isi'], 'Segera hadir') && !str_contains($r['isi'], 'sn-ip'));
$r = $a->get('/nilai'); cek('halaman nilai 200', $r['kode'] === 200);

echo "== Pengumpulan (siswa1) ==\n";
$r = $a->get('/modul/1/lkpd'); cek('Pengumpulan tampil dengan semua butir', $r['kode'] === 200 && str_contains($r['isi'], 'name="j[kelompok]"') && str_contains($r['isi'], 'data-unggah-butir="f_pkt"') && str_contains($r['isi'], 'Pengumpulan 4 dari 6') && !str_contains($r['isi'], 'Konten contoh'));
$a->segarkan('/modul/1/lkpd');
$r = $a->postJson('/modul/1/lkpd/simpan', ['jawaban' => ['kelompok' => 'Kelompok 3', 'anggota' => 'Ahmad, Budi', 'paket12' => '4', 'ping12' => 'Berhasil', 'hack' => 'x', 'a1x' => 'y']]);
cek('simpan otomatis OK', json_decode($r['isi'], true)['ok'] ?? false, $r['isi']);
$r = $a->get('/modul/1/lkpd');
cek('jawaban tersimpan dan tampil lagi', str_contains($r['isi'], 'Kelompok 3') && str_contains($r['isi'], 'value="4"'));
cek('pilihan tersimpan (radio checked)', (bool) preg_match('/value="Berhasil" checked/', $r['isi']));
cek('butir yang tidak ada di definisi diabaikan', !str_contains($r['isi'], 'hack'));
$r = $a->postJson('/modul/1/lkpd/simpan', ['jawaban' => ['ping12' => 'Pilihan ngawur']]);
$r = $a->get('/modul/1/lkpd'); cek('opsi pilihan yang tidak sah ditolak', preg_match('/value="Berhasil" checked/', $r['isi']) === 1);

// unggahan
function unggah(Klien $k, string $butir, string $nama, string $isi, string $mime, int $modul = 1): array
{
    $tmp = tempnam(sys_get_temp_dir(), 'up'); file_put_contents($tmp, $isi);
    $ch = curl_init($k->base . '/modul/' . $modul . '/lkpd/berkas');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $k->jar, CURLOPT_COOKIEFILE => $k->jar,
        CURLOPT_POSTFIELDS => ['butir_id' => $butir, 'berkas' => new CURLFile($tmp, $mime, $nama)],
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'X-Requested-With: fetch', 'X-CSRF-TOKEN: ' . $k->tokenTerakhir]]);
    $r = curl_exec($ch); $kode = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch); unlink($tmp);
    return ['kode' => $kode, 'j' => json_decode((string) $r, true) ?? [], 'mentah' => $r];
}
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
$u = unggah($a, 'f_topologi', 'topologi.png', $png, 'image/png');
cek('unggah PNG valid diterima', $u['j']['ok'] ?? false, (string) $u['mentah']);
$idBerkas = $u['j']['berkas']['id'] ?? 0;
$u2 = unggah($a, 'f_topologi', 'shell.php', '<?php echo 1;', 'text/plain'); cek('unggah .php ditolak', ($u2['j']['ok'] ?? true) === false && $u2['kode'] === 422);
$u3 = unggah($a, 'f_topologi', 'palsu.png', 'ini bukan gambar', 'image/png'); cek('unggah "png" palsu ditolak (isi tidak cocok)', ($u3['j']['ok'] ?? true) === false);
$u4 = unggah($a, 'anggota', 'x.png', $png, 'image/png'); cek('unggah ke butir non-berkas ditolak', ($u4['j']['ok'] ?? true) === false);
$r = $a->get('/modul/1/lkpd'); cek('berkas yang sudah diunggah tampil lagi setelah halaman dimuat ulang', str_contains($r['isi'], 'topologi.png') && str_contains($r['isi'], 'data-berkas="' . $idBerkas . '"'));
$r = $a->get('/lkpd/berkas/' . $idBerkas); cek('pemilik bisa melihat berkasnya (image/png)', $r['kode'] === 200 && $r['tipe'] === 'image/png');
$asing = new Klien($base); $asing->masuk('budi@contoh.id', 'rahasia123');
$r = $asing->get('/lkpd/berkas/' . $idBerkas); cek('siswa lain TIDAK bisa melihat berkas (404)', $r['kode'] === 404);
$r = $tamu->get('/lkpd/berkas/' . $idBerkas); cek('tanpa login tidak bisa melihat berkas', $r['kode'] === 302);
$up = unggah($a, 'f_pkt', 'Kelompok_3_Lab1_Pertemuan1.pkt', "PKT\x00\x01binary-contoh", 'application/octet-stream'); cek('unggah file .pkt diterima', $up['j']['ok'] ?? false, (string) $up['mentah']);
// kumpul tidak lengkap
$r = $a->kirim('/modul/1/lkpd/kumpul', ['j' => ['kelompok' => 'Kelompok 3']], '/modul/1/lkpd');
$r = $a->get('/modul/1/lkpd', ['ikuti' => false]); cek('kumpul tidak lengkap ditolak (tetap draf)', str_contains($r['isi'], 'id="status-simpan"') && str_contains($r['isi'], 'Masih ada yang belum diisi'));
// kumpul lengkap
$up2 = unggah($a, 'f_ping', 'ping.png', $png, 'image/png');
$a->kirim('/modul/1/lkpd/kumpul', ['j' => ['kelompok' => 'Kelompok 3', 'anggota' => 'Ahmad, Budi', 'ping12' => 'Berhasil', 'paket12' => '4', 'ping21' => 'Berhasil', 'paket21' => '4',
    'r1' => 'Karena satu network.', 'r2' => 'Menghubungkan perangkat dalam satu subnet.', 'r3' => 'Terjadi IP conflict.']], '/modul/1/lkpd');
$r = $a->get('/modul/1/lkpd'); cek('Pengumpulan lengkap -> dikumpulkan dan terkunci', str_contains($r['isi'], 'sudah dikumpulkan') && !str_contains($r['isi'], 'id="status-simpan"'));
cek('setelah kumpul ada tombol Lanjut ke LKPD (tampil)', (bool) preg_match('/id="lanjut"(?! hidden)/', $r['isi']));
$r = $a->postJson('/modul/1/lkpd/simpan', ['jawaban' => ['kelompok' => 'diubah']]); cek('simpan setelah dikumpulkan ditolak (409)', $r['kode'] === 409);
$r = $a->postJson('/lkpd/berkas/' . $idBerkas . '/hapus', []); cek('hapus berkas setelah dikumpulkan ditolak (409)', $r['kode'] === 409);
$r = $a->get('/modul/1'); cek('pengumpulan selesai, LKPD terbuka (4 dari 6 selesai)', substr_count($r['isi'], 'lk--selesai') === 4 && str_contains($r['isi'], '67%'));
$r = $a->get('/modul/1/lkpd'); cek('setelah kumpul ada tombol Lanjut ke LKPD', str_contains($r['isi'], 'Lanjut ke LKPD'));
$r = $a->get('/modul/1/refleksi'); cek('refleksi terkunci sebelum LKPD dikumpulkan', $r['kode'] === 302);

echo "== LKPD (pilihan ganda + uraian) ==\n";
$defLb = json_decode(file_get_contents(__DIR__ . '/../database/content/lembar-p1.json'), true);
$r = $a->get('/modul/1/lembar'); cek('halaman LKPD 200 dengan 10 PG dan 5 uraian', $r['kode'] === 200 && str_contains($r['isi'], 'LKPD 5 dari 6') && str_contains($r['isi'], 'name="j[pg10]"') && str_contains($r['isi'], 'name="j[u5]"'));
cek('halaman LKPD tidak membocorkan kunci/pembahasan sebelum dikumpulkan', !str_contains($r['isi'], $defLb['pg'][0]['bahas']) && !str_contains($r['isi'], 'Lihat contoh jawaban'));
$a->segarkan('/modul/1/lembar');
$r = $a->postJson('/modul/1/lembar/simpan', ['jawaban' => ['pg1' => '1', 'pg2' => '9', 'u1' => 'IP adalah alamat perangkat.', 'asing' => 'x']]);
cek('simpan otomatis LKPD OK', json_decode($r['isi'], true)['ok'] ?? false, $r['isi']);
$r = $a->get('/modul/1/lembar'); cek('jawaban LKPD tersimpan; pilihan di luar rentang diabaikan', (bool) preg_match('/name="j\[pg1\]" value="1" checked/', $r['isi']) && !preg_match('/name="j\[pg2\]" value="\d" checked/', $r['isi']) && str_contains($r['isi'], 'IP adalah alamat perangkat.'));
$a->kirim('/modul/1/lembar/kumpul', ['j' => ['pg3' => '1']], '/modul/1/lembar');
$r = $a->get('/modul/1/lembar'); cek('kumpul LKPD tidak lengkap ditolak', str_contains($r['isi'], 'id="status-simpan"') && str_contains($r['isi'], 'Masih ada yang belum dijawab'));
$jLb = [];
foreach ($defLb['pg'] as $i => $sLb) { $jLb[$sLb['id']] = (string) ($i < 8 ? $sLb['kunci'] : ($sLb['kunci'] + 1) % 5); }
foreach ($defLb['uraian'] as $sLb) { $jLb[$sLb['id']] = 'Jawaban uraian siswa untuk ' . $sLb['id']; }
$a->kirim('/modul/1/lembar/kumpul', ['j' => $jLb], '/modul/1/lembar');
$r = $a->get('/modul/1/lembar'); cek('LKPD terkumpul: PG 8/10 benar = 40/50, uraian menunggu guru, pembahasan tampil', str_contains($r['isi'], '40 / 50') && str_contains($r['isi'], '8 dari 10 benar') && str_contains($r['isi'], 'Menunggu') && str_contains($r['isi'], $defLb['pg'][0]['bahas']) && str_contains($r['isi'], 'Lihat contoh jawaban'));
cek('setelah LKPD terkumpul ada tombol Lanjut ke Refleksi', str_contains($r['isi'], 'Lanjut ke Refleksi'));
$r = $a->postJson('/modul/1/lembar/simpan', ['jawaban' => ['u1' => 'ubah']]); cek('simpan LKPD setelah dikumpulkan ditolak (409)', $r['kode'] === 409);
$r = $a->get('/nilai'); cek('halaman nilai: LKPD menunggu uraian', str_contains($r['isi'], 'PG 40') && str_contains($r['isi'], 'uraian menunggu guru'));
$r = $a->get('/modul/1'); cek('LKPD selesai (5 dari 6), refleksi terbuka', substr_count($r['isi'], 'lk--selesai') === 5);
$r = $a->get('/modul/1/refleksi'); cek('halaman refleksi 200 dengan skala dan tujuan', $r['kode'] === 200 && str_contains($r['isi'], 'name="paham"') && str_contains($r['isi'], 'name="bisa[]"') && str_contains($r['isi'], 'Refleksi 6 dari 6'));
$a->kirim('/modul/1/refleksi', ['paham' => '9', 'penting' => '', 'bingung' => ''], '/modul/1/refleksi');
$r = $a->get('/modul/1'); cek('refleksi tidak lengkap ditolak', substr_count($r['isi'], 'lk--selesai') === 5);
$a->kirim('/modul/1/refleksi', ['paham' => '4', 'bisa' => ['0', '2', '99'], 'penting' => 'PC satu network bisa saling ping.', 'bingung' => 'Menghitung broadcast.', 'tanya' => ''], '/modul/1/refleksi');
$r = $a->get('/modul/1'); cek('refleksi tersimpan -> semua 6 langkah selesai (100%)', substr_count($r['isi'], 'lk--selesai') === 6 && str_contains($r['isi'], '100%'));
$r = $a->get('/modul/1/refleksi'); cek('refleksi tersimpan tampil lagi dan bisa diubah', str_contains($r['isi'], 'Menghitung broadcast.') && str_contains($r['isi'], 'Simpan perubahan'));

echo "== Guru ==\n";
$g = new Klien($base);
$r = $g->masuk('guru1@mikronet.test', 'Guru12345'); cek('login guru -> beranda guru (/guru)', str_ends_with($r['lokasi'], '/guru'), $r['lokasi']);
$r = $g->get('/guru/kelas'); cek('guru melihat kode kelas dan anggota', str_contains($r['isi'], 'TKJ1-7K2Q') && str_contains($r['isi'], 'Ahmad Fauzi') && str_contains($r['isi'], 'Budi Santoso'));
$r = $g->get('/admin'); cek('guru tidak bisa buka /admin (403)', $r['kode'] === 403);
$r = $g->get('/guru/penilaian?k=1&m=1'); cek('rekap penilaian menampilkan siswa', $r['kode'] === 200 && str_contains($r['isi'], 'Ahmad Fauzi'));
preg_match('#/guru/penilaian/(\d+)#', $r['isi'], $mm); $idPg = $mm[1] ?? 0;
cek('ada tautan ke pengumpulan yang dikumpulkan', (int) $idPg > 0);
$r = $g->get('/guru/penilaian?k=1&m=1'); preg_match('#/guru/lkpd/(\d+)#', $r['isi'], $mLb); $idLb = (int) ($mLb[1] ?? 0);
cek('rekap penilaian punya kolom LKPD dengan tombol Nilai LKPD dan filter', $idLb > 0 && str_contains($r['isi'], 'Nilai LKPD') && str_contains($r['isi'], 'Menunggu uraian') && str_contains($r['isi'], 'data-saring="perlu"') && str_contains($r['isi'], 'Pretest &amp; post-test'));
$r = $g->get('/guru/lkpd/' . $idLb); cek('guru membuka LKPD siswa: jawaban PG ringkas, uraian dan kunci', $r['kode'] === 200 && str_contains($r['isi'], '8 dari 10 benar') && str_contains($r['isi'], 'Jawaban uraian siswa untuk u3') && str_contains($r['isi'], 'name="u[4]"'));
$g->segarkan('/guru/lkpd/' . $idLb);
$g->req('POST', '/guru/lkpd/' . $idLb . '/nilai', ['post' => http_build_query(['_csrf' => $g->tokenTerakhir, 'u' => [11, 10, 7, 9, 10], 'catatan' => 'x'])]);
cek('skor uraian melebihi maks ditolak', !str_contains($g->get('/guru/lkpd/' . $idLb)['isi'], 'nilai-angka'));
$g->req('POST', '/guru/lkpd/' . $idLb . '/nilai', ['post' => http_build_query(['_csrf' => $g->tokenTerakhir, 'u' => [8, 10, 7, 9, 10], 'catatan' => 'Uraian nomor 3 kurang lengkap.'])]);
$r = $g->get('/guru/lkpd/' . $idLb); cek('nilai LKPD = 40 + 44 = 84', str_contains($r['isi'], '>84<'));
$r = $a->get('/modul/1/lembar'); cek('siswa melihat nilai LKPD 84, skor uraian, dan catatan guru', str_contains($r['isi'], '>84<') && str_contains($r['isi'], '44 / 50') && str_contains($r['isi'], 'Uraian nomor 3 kurang lengkap.'));
$r = $a->get('/nilai'); cek('halaman nilai menampilkan nilai LKPD 84', str_contains($r['isi'], '>84<'));
$r = $tamu->get('/guru/lkpd/' . $idLb); cek('tamu tidak bisa membuka LKPD siswa', $r['kode'] === 302);
$r = $g->get('/guru/penilaian/' . $idPg); cek('detail penilaian menampilkan refleksi siswa', str_contains($g->get('/guru/penilaian/' . $idPg)['isi'], 'Menghitung broadcast.'));
cek('detail penilaian menampilkan jawaban + rubrik', str_contains($r['isi'], 'Menghubungkan perangkat dalam satu subnet.') && str_contains($r['isi'], 'name="r[0]"') && str_contains($r['isi'], 'max="20"'));
$r = $g->get('/lkpd/berkas/' . $idBerkas); cek('guru pengampu bisa melihat berkas siswa', $r['kode'] === 200);
$g->segarkan('/guru/penilaian/' . $idPg);
$r = $g->req('POST', '/guru/penilaian/' . $idPg . '/nilai', ['post' => http_build_query(['_csrf' => $g->tokenTerakhir, 'r' => [10, 15], 'catatan' => 'x'])]);
$r = $g->get('/guru/penilaian/' . $idPg); cek('nilai tidak lengkap ditolak (belum ada nilai tersimpan)', !str_contains($r['isi'], 'kategori') && !str_contains($r['isi'], '>88<'));
$g->req('POST', '/guru/penilaian/' . $idPg . '/nilai', ['post' => http_build_query(['_csrf' => $g->tokenTerakhir, 'r' => [25, 15, 25, 25, 15], 'catatan' => 'ditolak: melebihi maks'])]);
$r = $g->get('/guru/penilaian/' . $idPg); cek('skor melebihi maks per kriteria ditolak', !str_contains($r['isi'], '>100<') && !str_contains($r['isi'], 'ditolak: melebihi'));
$g->req('POST', '/guru/penilaian/' . $idPg . '/nilai', ['post' => http_build_query(['_csrf' => $g->tokenTerakhir, 'r' => [20, 15, 22, 20, 11], 'catatan' => 'Bagus, perbaiki refleksimu.'])]);
$r = $g->get('/guru/penilaian/' . $idPg); cek('nilai rubrik berbobot disimpan (88/100 = 88)', str_contains($r['isi'], '>88<'));
$r = $a->get('/nilai'); cek('siswa melihat nilai 88 (Sangat Baik) dan catatan guru', str_contains($r['isi'], '88') && str_contains($r['isi'], 'Sangat Baik') && str_contains($r['isi'], 'perbaiki refleksimu'));
$r = $g->req('POST', '/guru/kelas', ['post' => http_build_query(['_csrf' => $g->tokenTerakhir, 'nama' => 'XI TKJ 2'])]);
cek('guru membuat kelas baru', str_contains($r['lokasi'], '/guru/kelas?k='), $r['lokasi']);
$r = $g->get('/guru/kelas'); cek('kelas baru muncul dengan kode', (bool) preg_match('/XI TKJ 2/', $r['isi']));
$g->req('POST', '/guru/penilaian/' . $idPg . '/kembalikan', ['post' => http_build_query(['_csrf' => $g->tokenTerakhir])]);
$r = $a->get('/modul/1/lkpd'); cek('LKPD dikembalikan -> siswa bisa edit lagi', str_contains($r['isi'], 'id="status-simpan"'));
$r = $a->get('/nilai'); cek('nilai lama dihapus saat dikembalikan', !str_contains($r['isi'], 'Sangat Baik'));
$r = $a->get('/modul/1/lkpd'); cek('catatan guru tampil di draf yang dikembalikan', str_contains($r['isi'], 'Catatan dari gurumu'));

echo "== XSS ==\n";
$xs = new Klien($base); $xs->tokenTerakhir = $xs->token('/daftar');
$xs->req('POST', '/daftar', ['post' => http_build_query(['_csrf' => $xs->tokenTerakhir, 'nama' => '<script>alert(1)</script>', 'nis' => '2025102', 'email' => 'xss@contoh.id', 'password' => 'rahasia123', 'password2' => 'rahasia123', 'kode' => 'TKJ1-7K2Q'])]);
$r = $g->get('/guru/kelas'); cek('nama berisi <script> di-escape di halaman guru', !str_contains($r['isi'], '<script>alert(1)') && str_contains($r['isi'], '&lt;script&gt;'));

echo "== Admin ==\n";
$ad = new Klien($base);
$r = $ad->masuk('admin@mikronet.test', 'Admin12345'); cek('login admin -> /admin', str_ends_with($r['lokasi'], '/admin'), $r['lokasi']);
$r = $ad->get('/admin'); cek('ringkasan admin tampil', $r['kode'] === 200);
$r = $ad->get('/admin/pengguna'); cek('daftar pengguna tampil semua peran', str_contains($r['isi'], 'Budi Santoso') && str_contains($r['isi'], 'Guru Pengajar 1'));
cek('guru dengan 2 kelas tampil sekali (tidak dobel)', substr_count($r['isi'], 'guru1@mikronet.test') === 1);
$ad->segarkan('/admin/pengguna');
$r = $ad->req('POST', '/admin/guru', ['post' => http_build_query(['_csrf' => $ad->tokenTerakhir, 'nama' => 'Guru Baru', 'email' => 'guru.baru@contoh.id'])]);
$r = $ad->get('/admin/pengguna'); preg_match('/Password sementara: (\S+)/', $r['isi'], $mp); $sementara = $mp[1] ?? '';
cek('akun guru dibuat + password sementara ditampilkan sekali', $sementara !== '', substr($r['isi'], 0, 0));
$r = $ad->get('/admin/pengguna'); cek('password sementara tidak tampil lagi setelah reload', !str_contains($r['isi'], 'Password sementara: '));
$gb = new Klien($base); $r = $gb->masuk('guru.baru@contoh.id', $sementara);
$r = $gb->get('/guru/kelas'); cek('guru dengan sandi sementara dipaksa ganti password', str_ends_with($r['lokasi'], '/akun/password'), $r['lokasi']);
$gb->segarkan('/akun/password');
$r = $gb->req('POST', '/akun/password', ['post' => http_build_query(['_csrf' => $gb->tokenTerakhir, 'lama' => 'salah', 'baru' => 'PasswordBaru99', 'baru2' => 'PasswordBaru99'])]);
$r = $gb->get('/guru/kelas'); cek('ganti password dengan sandi lama salah ditolak', str_ends_with($r['lokasi'], '/akun/password'));
$gb->req('POST', '/akun/password', ['post' => http_build_query(['_csrf' => $gb->tokenTerakhir, 'lama' => $sementara, 'baru' => 'PasswordBaru99', 'baru2' => 'PasswordBaru99'])]);
$r = $gb->get('/guru/kelas'); cek('setelah ganti password guru bisa masuk halaman guru', $r['kode'] === 200);
// reset & nonaktifkan
preg_match_all('#/admin/pengguna/(\d+)/aktif#', $ad->get('/admin/pengguna')['isi'], $ids); $semuaId = $ids[1];
$idBudi = null; foreach ($semuaId as $id) { if (str_contains($ad->get('/admin/pengguna?q=budi@contoh.id')['isi'], "/admin/pengguna/$id/aktif")) { $idBudi = $id; } }
cek('menemukan id Budi lewat pencarian', $idBudi !== null);
$ad->req('POST', "/admin/pengguna/$idBudi/aktif", ['post' => http_build_query(['_csrf' => $ad->tokenTerakhir])]);
$bb = new Klien($base); $r = $bb->masuk('budi@contoh.id', 'rahasia123'); $r2 = $bb->get('/siswa');
cek('akun nonaktif tidak bisa masuk', str_ends_with($r['lokasi'], '/masuk') && $r2['kode'] === 302);
$r = $asing->get('/siswa'); cek('sesi yang sedang berjalan ikut tertolak saat dinonaktifkan', $r['kode'] === 302);
$ad->req('POST', "/admin/pengguna/$idBudi/reset", ['post' => http_build_query(['_csrf' => $ad->tokenTerakhir])]);
cek('reset password menampilkan sandi sementara', (bool) preg_match('/Password sementara: (\S+)/', $ad->get('/admin/pengguna')['isi']));
$r = $g->get('/kelola/modul'); cek('guru bisa membuka kelola modul', $r['kode'] === 200);
$r = $a->get('/kelola/modul'); cek('siswa tidak bisa kelola modul (403)', $r['kode'] === 403);
$g->segarkan('/kelola/modul');
$g->req('POST', '/kelola/modul', ['post' => http_build_query(['_csrf' => $g->tokenTerakhir, 'judul' => 'Modul buatan guru'])]);
$r = $ad->get('/kelola/modul'); cek('guru menambah modul baru (nomor 11)', str_contains($r['isi'], 'Modul buatan guru') && str_contains($r['isi'], 'Modul 11'));
$r = $a->get('/modul'); cek('modul baru (terkunci) tampil di siswa tapi tidak bisa dibuka', str_contains($r['isi'], 'Modul buatan guru'));
$r = $ad->get('/kelola/modul'); cek('daftar modul admin 200', $r['kode'] === 200);
$r = $ad->get('/kelola/modul/1'); cek('form edit modul 200 dengan pilihan game "simulasi-p1"', $r['kode'] === 200 && str_contains($r['isi'], 'value="simulasi-p1"'));
$ad->segarkan('/kelola/modul/1');
$form = fn(array $d) => $ad->req('POST', '/kelola/modul/1', ['post' => http_build_query($d + ['_csrf' => $ad->tokenTerakhir, 'judul' => 'Penguatan IP address dan Cisco dasar', 'status' => 'terbit', 'tanggal' => '2026-10-05', 'game_folder' => 'simulasi-p1'])]);
$r = $ad->get('/kelola/modul/1'); cek('form modul tidak lagi punya kolom tautan pretest', !str_contains($r['isi'], 'pretest_url'));
$form(['status' => 'segera']);
$r = $a->get('/modul/1'); cek('modul di-set "segera" -> siswa tidak bisa membuka', str_ends_with($r['lokasi'], '/modul'));
$form(['status' => 'terbit']);
$form(['video_url' => 'javascript:alert(1)']);
cek('link video selain https ditolak', !str_contains($ad->get('/kelola/modul/1')['isi'], 'javascript:alert'));
$form(['video_url' => 'https://www.youtube.com/watch?v=abcDEF12345&t=10']);
$r = $a->get('/modul/1/materi'); cek('link YouTube dari guru tampil sebagai video di Materi', str_contains($r['isi'], 'youtube-nocookie.com/embed/abcDEF12345') && str_contains($r['isi'], 'Video penjelasan'));
$form(['video_url' => '']);
cek('link video dikosongkan -> bagian video hilang (modul tanpa video bawaan)', !str_contains($a->get('/modul/1/materi')['isi'], 'Video penjelasan'));
// unggah PDF materi
$tmp = tempnam(sys_get_temp_dir(), 'pdf'); copy(__DIR__ . '/../database/content/contoh-materi.pdf', $tmp);
$ch = curl_init($base . '/kelola/modul/1/materi'); curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => $ad->jar, CURLOPT_COOKIEJAR => $ad->jar, CURLOPT_POSTFIELDS => ['_csrf' => $ad->tokenTerakhir, 'materi' => new CURLFile($tmp, 'application/pdf', 'materi.pdf')]]);
curl_exec($ch); curl_close($ch);
$r = $ad->get('/kelola/modul/1'); cek('PDF materi terunggah oleh admin', str_contains($r['isi'], 'Memakai PDF yang diunggah'));
$r = $a->get('/modul/1/materi/pdf'); cek('PDF unggahan dilayani ke siswa', $r['kode'] === 200 && str_starts_with($r['isi'], '%PDF'));
$sebelum = count(glob(__DIR__ . '/../storage/uploads/materi/modul1-*.pdf'));
$ch = curl_init($base . '/kelola/modul/1/materi'); file_put_contents($tmp, 'bukan pdf'); curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => $ad->jar, CURLOPT_COOKIEJAR => $ad->jar, CURLOPT_POSTFIELDS => ['_csrf' => $ad->tokenTerakhir, 'materi' => new CURLFile($tmp, 'application/pdf', 'jahat.pdf')]]);
curl_exec($ch); curl_close($ch);
$r = $ad->get('/kelola/modul/1'); cek('berkas palsu berekstensi .pdf ditolak', count(glob(__DIR__ . '/../storage/uploads/materi/modul1-*.pdf')) === $sebelum);
$r = $ad->get('/admin/kelas'); cek('daftar kelas admin menampilkan kode', str_contains($r['isi'], 'TKJ1-7K2Q'));

echo "== Editor isi modul (guru) ==\n";
$isiDari = function (string $html) { return preg_match('#<script type="application/json" id="data-isi">(.*?)</script>#s', $html, $m) ? json_decode($m[1], true) : 'GAGAL'; };
$r = $g->get('/kelola/modul/1/isi/praktikum'); $pk1 = $isiDari($r['isi']);
cek('editor praktikum modul 1 terbuka dengan isi bawaan', $r['kode'] === 200 && !empty($pk1['tahap']) && str_contains($r['isi'], 'editor-konten.js'));
cek('jenis isi tidak dikenal -> 404', $g->get('/kelola/modul/1/isi/abc')['kode'] === 404);
cek('siswa tidak bisa membuka editor (403)', $a->get('/kelola/modul/1/isi/lembar')['kode'] === 403);
foreach (['lkpd', 'lembar', 'refleksi', 'remidi', 'pengayaan'] as $jj) { cek("editor $jj terbuka", $g->get("/kelola/modul/2/isi/$jj")['kode'] === 200); }
$g->segarkan('/kelola/modul/1/isi/refleksi');
$r = $g->postJson('/kelola/modul/1/isi/refleksi', ['isi' => ['pertanyaan' => ['Apa yang paling kamu ingat dari subnetting?', '', '  Bagian mana yang ingin diulang?  ']]]);
$j = json_decode($r['isi'], true);
cek('simpan refleksi modul 1 (baris kosong dibuang, spasi dipangkas)', $r['kode'] === 200 && ($j['isi']['pertanyaan'] ?? []) === ['Apa yang paling kamu ingat dari subnetting?', 'Bagian mana yang ingin diulang?'], $r['isi']);
$r = $g->get('/kelola/modul/1/isi/refleksi');
cek('editor menampilkan "diubah oleh" guru', str_contains($r['isi'], 'Terakhir diubah Guru'));
cek('refleksi tanpa isi bawaan tidak menambah riwayat palsu', !str_contains($r['isi'], '<b>Isi bawaan</b>'));
$rf2 = json_decode(file_get_contents(__DIR__ . '/../database/content/refleksi-p2.json'), true);
$g->postJson('/kelola/modul/2/isi/refleksi', ['isi' => ['pertanyaan' => array_merge($rf2['pertanyaan'], ['Tambahan guru?'])]]);
cek('isi bawaan masuk riwayat pada edit pertama (modul 2)', str_contains($g->get('/kelola/modul/2/isi/refleksi')['isi'], '<b>Isi bawaan</b>'));
cek('halaman edit modul menandai refleksi "Diubah"', str_contains($g->get('/kelola/modul/1')['isi'], 'Diubah'));
$r = $g->postJson('/kelola/modul/1/isi/refleksi', ['isi' => ['pertanyaan' => ['Versi kedua?']]]);
cek('simpan kedua -> riwayat bisa dipulihkan', str_contains($g->get('/kelola/modul/1/isi/refleksi')['isi'], '/pulihkan/'));
$r = $g->postJson('/kelola/modul/1/isi/refleksi', ['isi' => ['pertanyaan' => ['X']]], false); cek('simpan tanpa token CSRF ditolak (419)', $r['kode'] === 419);
$r = $a->postJson('/kelola/modul/1/isi/refleksi', ['isi' => ['pertanyaan' => ['X']]]); cek('siswa tidak bisa menyimpan isi (403)', $r['kode'] === 403);
// LKPD (lembar) modul 1 sudah dikumpulkan siswa -> soal lama terkunci
$lb1 = json_decode(file_get_contents(__DIR__ . '/../database/content/lembar-p1.json'), true);
$g->segarkan('/kelola/modul/1/isi/lembar');
$ubah = $lb1; $ubah['pg'][0]['kunci'] = ($ubah['pg'][0]['kunci'] + 1) % count($ubah['pg'][0]['opsi']);
$r = $g->postJson('/kelola/modul/1/isi/lembar', ['isi' => $ubah]);
cek('kunci jawaban soal yang sudah dijawab tidak bisa diubah (422)', $r['kode'] === 422 && str_contains($r['isi'], 'tidak bisa diubah'), $r['isi']);
$ubah = $lb1; array_shift($ubah['pg']);
$r = $g->postJson('/kelola/modul/1/isi/lembar', ['isi' => $ubah]); cek('soal yang sudah dijawab tidak bisa dihapus (422)', $r['kode'] === 422 && str_contains($r['isi'], 'tidak bisa dihapus'));
$ubah = $lb1; $ubah['pg'][0]['soal'] .= ' (dirapikan)'; $ubah['pg'][] = ['id' => 'pg99', 'soal' => 'Soal tambahan: 255.255.255.0 sama dengan prefix …', 'opsi' => ['/8', '/16', '/24', '/32'], 'kunci' => 2, 'bahas' => '24 bit bernilai 1.', 'topik' => 'Subnet mask'];
$r = $g->postJson('/kelola/modul/1/isi/lembar', ['isi' => $ubah]); $j = json_decode($r['isi'], true);
cek('teks soal lama boleh dirapikan dan soal baru boleh ditambah', $r['kode'] === 200 && count($j['isi']['pg'] ?? []) === count($lb1['pg']) + 1, $r['isi']);
$r = $a->get('/modul/1/lembar'); cek('siswa melihat soal LKPD hasil edit guru', str_contains($r['isi'], 'Soal tambahan: 255.255.255.0') && str_contains($r['isi'], '(dirapikan)'));
$r = $g->postJson('/kelola/modul/2/isi/lembar', ['isi' => ['pg' => [['soal' => 'Tanpa kunci', 'opsi' => ['a', 'b'], 'kunci' => -1]]]]);
cek('soal PG tanpa kunci ditolak dengan pesan jelas', $r['kode'] === 422 && str_contains($r['isi'], 'pilih kunci'));
// Pengumpulan (lkpd) modul 1 sudah dikumpulkan dan dinilai
$lk1 = json_decode(file_get_contents(__DIR__ . '/../database/content/lkpd-p1.json'), true);
$ubah = $lk1; array_shift($ubah['bagian'][0]['butir']);
$r = $g->postJson('/kelola/modul/1/isi/lkpd', ['isi' => $ubah]); cek('butir pengumpulan yang sudah diisi tidak bisa dihapus', $r['kode'] === 422 && str_contains($r['isi'], 'tidak bisa dihapus'));
$pdoU = new PDO('sqlite:' . __DIR__ . '/../storage/test.sqlite');
$pdoU->exec("UPDATE lkpd_pengumpulan SET rubrik_skor = '[1]' WHERE modul_id = 1 AND rubrik_skor IS NULL AND id = (SELECT MIN(id) FROM lkpd_pengumpulan WHERE modul_id = 1)");
$ubah = $lk1; $ubah['rubrik'][0]['maks'] += 5;
$r = $g->postJson('/kelola/modul/1/isi/lkpd', ['isi' => $ubah]); cek('rubrik yang sudah dipakai menilai dikunci', $r['kode'] === 422 && str_contains($r['isi'], 'rubrik'), $r['isi']);
$pdoU->exec("UPDATE lkpd_pengumpulan SET rubrik_skor = NULL WHERE rubrik_skor = '[1]'"); $pdoU = null;
// Praktikum: gambar harus ada; unggah gambar baru
$ubah = $pk1; $ubah['tahap'][0]['langkah'][0]['gambar'] = ['tidak-ada.png'];
$r = $g->postJson('/kelola/modul/1/isi/praktikum', ['isi' => $ubah]); cek('gambar praktikum yang tidak ada ditolak', $r['kode'] === 422 && str_contains($r['isi'], 'tidak ditemukan'));
$unggahGb = function (Klien $k, int $modul, string $nama, string $isi, string $mime) use ($base) {
    $tmp = tempnam(sys_get_temp_dir(), 'gb'); file_put_contents($tmp, $isi);
    $ch = curl_init($base . '/kelola/modul/' . $modul . '/gambar');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $k->jar, CURLOPT_COOKIEFILE => $k->jar,
        CURLOPT_POSTFIELDS => ['gambar' => new CURLFile($tmp, $mime, $nama)], CURLOPT_HTTPHEADER => ['Accept: application/json', 'X-Requested-With: fetch', 'X-CSRF-TOKEN: ' . $k->tokenTerakhir]]);
    $r = curl_exec($ch); $kode = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch); unlink($tmp);
    return ['kode' => $kode, 'j' => json_decode((string) $r, true) ?? []];
};
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
$u = $unggahGb($g, 1, 'topo.png', $png, 'image/png');
cek('guru mengunggah gambar praktikum', ($u['j']['ok'] ?? false) && preg_match('/^u-\d{6}-[a-f0-9]{8}\.png$/', $u['j']['nama'] ?? ''));
$u2 = $unggahGb($g, 1, 'x.php', '<?php echo 1;', 'image/png'); cek('unggahan bukan gambar ditolak', ($u2['j']['ok'] ?? true) === false && $u2['kode'] === 422);
$ubah = $pk1; $ubah['tahap'][0]['langkah'][0]['gambar'] = [$u['j']['nama'] ?? '']; $ubah['tahap'][0]['langkah'][] = ['judul' => 'Langkah tambahan guru', 'isi' => 'Cek lampu link.', 'kode' => 'ping 192.168.1.1', 'kode_label' => 'Command Prompt'];
$r = $g->postJson('/kelola/modul/1/isi/praktikum', ['isi' => $ubah]); cek('praktikum hasil edit tersimpan', $r['kode'] === 200, $r['isi']);
$r = $a->get('/modul/1/praktikum'); cek('siswa melihat langkah praktikum tambahan dan gambar baru', str_contains($r['isi'], 'Langkah tambahan guru') && str_contains($r['isi'], $u['j']['nama'] ?? 'xx'));
register_shutdown_function(function () { array_map('unlink', glob(__DIR__ . '/../public/assets/praktikum/p*/u-*.png') ?: []); });
// Remidi & pengayaan
$r = $g->postJson('/kelola/modul/2/isi/remidi', ['isi' => ['judul' => 'Remidi VLAN', 'poin_pg' => 10, 'pg' => [['soal' => 'VLAN membagi …', 'opsi' => ['satu switch menjadi beberapa jaringan logis', 'kabel', 'router'], 'kunci' => 0, 'topik' => 'VLAN']], 'uraian' => [['soal' => 'Jelaskan trunk.', 'maks' => 10]]]]);
$j = json_decode($r['isi'], true); cek('soal remidi modul 2 tersimpan dengan id otomatis', $r['kode'] === 200 && preg_match('/^pg/', $j['isi']['pg'][0]['id'] ?? '') && preg_match('/^u/', $j['isi']['uraian'][0]['id'] ?? ''), $r['isi']);
// Kosongkan & pulihkan
$g->segarkan('/kelola/modul/1/isi/refleksi');
$g->req('POST', '/kelola/modul/1/isi/refleksi/kosongkan', ['post' => http_build_query(['_csrf' => $g->tokenTerakhir])]);
cek('refleksi khusus modul 1 dikosongkan', str_contains($g->get('/kelola/modul/1')['isi'], 'Dikosongkan'));
$g->segarkan('/kelola/modul/1/isi/lembar');
$g->req('POST', '/kelola/modul/1/isi/lembar/kosongkan', ['post' => http_build_query(['_csrf' => $g->tokenTerakhir])]);
cek('LKPD yang sudah dipakai siswa tidak bisa dikosongkan', str_contains($a->get('/modul/1/lembar')['isi'], 'Soal tambahan'));
preg_match_all('#/isi/refleksi/pulihkan/(\d+)#', $g->get('/kelola/modul/1/isi/refleksi')['isi'], $mr);
$g->segarkan('/kelola/modul/1/isi/refleksi');
$g->req('POST', '/kelola/modul/1/isi/refleksi/pulihkan/' . ($mr[1][0] ?? 0), ['post' => http_build_query(['_csrf' => $g->tokenTerakhir])]);
cek('versi refleksi sebelumnya dipulihkan', str_contains($g->get('/kelola/modul/1/isi/refleksi')['isi'], 'Versi kedua?'));

echo "== Hapus modul & simulasi ==\n";
$g->segarkan('/kelola/modul');
$g->req('POST', '/kelola/modul', ['post' => http_build_query(['_csrf' => $g->tokenTerakhir, 'judul' => 'Modul uji hapus'])]);
preg_match('#/kelola/modul/(\d+)">Edit<span class="sr"> Modul (\d+)</span></a></td></tr>\s*$#m', $g->get('/kelola/modul')['isi'], $mh);
$r = $g->get('/kelola/modul'); preg_match_all('#/kelola/modul/(\d+)"#', $r['isi'], $semuaId); $idHapus = max(array_map('intval', $semuaId[1]));
$nomorHapus = preg_match('#Modul (\d+)</b><br><span class="mini">Modul uji hapus#', $r['isi'], $mn) ? $mn[1] : '';
cek('modul uji dibuat', $nomorHapus !== '');
$g->segarkan('/kelola/modul/' . $idHapus);
$g->req('POST', "/kelola/modul/$idHapus/hapus", ['post' => http_build_query(['_csrf' => $g->tokenTerakhir, 'konfirmasi' => '999'])]);
cek('hapus dengan konfirmasi salah ditolak', str_contains($g->get('/kelola/modul')['isi'], 'class="mini">Modul uji hapus'));
$g->req('POST', "/kelola/modul/$idHapus/hapus", ['post' => http_build_query(['_csrf' => $g->tokenTerakhir, 'konfirmasi' => $nomorHapus])]);
cek('modul tanpa data siswa terhapus', !str_contains($g->get('/kelola/modul')['isi'], 'class="mini">Modul uji hapus') && $g->get('/kelola/modul/' . $idHapus)['kode'] === 404);
$g->segarkan('/kelola/modul/1');
$g->req('POST', '/kelola/modul/1/hapus', ['post' => http_build_query(['_csrf' => $g->tokenTerakhir, 'konfirmasi' => '1'])]);
cek('modul yang punya data siswa tidak bisa dihapus', $g->get('/kelola/modul/1')['kode'] === 200 && str_contains($g->get('/kelola/modul/1')['isi'], 'sudah punya data siswa'));
$r = $g->get('/kelola/simulasi'); cek('guru melihat daftar simulasi', $r['kode'] === 200 && str_contains($r['isi'], 'simulasi-p1') && str_contains($r['isi'], 'hanya bisa dilakukan admin'));
$buatZip = function (array $isi) { $f = tempnam(sys_get_temp_dir(), 'zip') . '.zip'; $z = new ZipArchive(); $z->open($f, ZipArchive::CREATE); foreach ($isi as $n => $t) { $z->addFromString($n, $t); } $z->close(); return $f; };
$kirimZip = function (Klien $k, string $folder, string $zip, bool $timpa = false) use ($base) {
    $ch = curl_init($base . '/kelola/simulasi');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $k->jar, CURLOPT_COOKIEFILE => $k->jar,
        CURLOPT_POSTFIELDS => ['_csrf' => $k->tokenTerakhir, 'folder' => $folder, 'timpa' => $timpa ? '1' : '', 'zip' => new CURLFile($zip, 'application/zip', 'sim.zip')]]);
    curl_exec($ch); $kode = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch); return $kode;
};
$g->segarkan('/kelola/simulasi');
cek('guru tidak bisa mengunggah simulasi (403)', $kirimZip($g, 'uji-zip', $buatZip(['index.html' => '<h1>x</h1>'])) === 403);
$ad->segarkan('/kelola/simulasi');
$kirimZip($ad, 'uji-zip', $buatZip(['game/index.html' => '<h1>Simulasi uji</h1>', 'game/js/a.js' => 'var a=1;', 'game/img/b.png' => $png]));
cek('admin memasang simulasi dari ZIP (folder teratas dilepas)', str_contains($tamu->get('/games/uji-zip/index.html')['isi'], 'Simulasi uji') && is_file(__DIR__ . '/../public/games/uji-zip/js/a.js'));
$kirimZip($ad, 'uji-jahat', $buatZip(['index.html' => 'x', 'shell.php' => '<?php system($_GET[1]);']));
cek('ZIP berisi PHP ditolak', !is_dir(__DIR__ . '/../public/games/uji-jahat') && str_contains($ad->get('/kelola/simulasi')['isi'], 'tidak diizinkan'));
$kirimZip($ad, 'uji-jahat', $buatZip(['index.html' => 'x', '../../keluar.html' => 'x']));
cek('ZIP dengan jalur ../ ditolak', !is_dir(__DIR__ . '/../public/games/uji-jahat') && !is_file(__DIR__ . '/../keluar.html'));
$kirimZip($ad, 'uji-zip', $buatZip(['index.html' => 'baru']));
cek('folder yang sudah ada tidak ditimpa tanpa centang', str_contains($tamu->get('/games/uji-zip/index.html')['isi'], 'Simulasi uji'));
$kirimZip($ad, 'uji-zip', $buatZip(['index.html' => 'versi baru']), true);
cek('centang Timpa memperbarui simulasi', str_contains($tamu->get('/games/uji-zip/index.html')['isi'], 'versi baru') && !is_file(__DIR__ . '/../public/games/uji-zip/js/a.js'));
cek('simulasi baru muncul di pilihan modul', str_contains($ad->get('/kelola/modul/1')['isi'], 'value="uji-zip"'));
$ad->segarkan('/kelola/simulasi');
$ad->req('POST', '/kelola/simulasi/uji-zip/hapus', ['post' => http_build_query(['_csrf' => $ad->tokenTerakhir])]);
cek('simulasi yang tidak dipakai bisa dihapus admin', !is_dir(__DIR__ . '/../public/games/uji-zip'));
$ad->req('POST', '/kelola/simulasi/simulasi-p1/hapus', ['post' => http_build_query(['_csrf' => $ad->tokenTerakhir])]);
cek('simulasi yang masih dipakai modul tidak bisa dihapus', is_dir(__DIR__ . '/../public/games/simulasi-p1'));

echo "== Pretest & post-test ==\n";
// Jalur database untuk menguji pengatur waktu (opsional). Beri sebagai argumen ke-2 atau lewat DB_SQLITE.
$jalurDb = $argv[2] ?? getenv('DB_SQLITE') ?: '';
$pdo = ($jalurDb && is_file($jalurDb)) ? new PDO('sqlite:' . $jalurDb) : null;
$kuisHitung = fn(int $b, int $t) => (int) round($b / $t * 100);
$r = $a->get('/kuis'); cek('siswa melihat halaman Kuis dengan dua kartu', $r['kode'] === 200 && str_contains($r['isi'], 'Pretest MIKRO-NET') && str_contains($r['isi'], 'Post-test MIKRO-NET'));
cek('jenis kuis tidak dikenal -> 404', $a->get('/kuis/abc')['kode'] === 404);
cek('kuis masih draf: belum ada pengingat pretest di beranda', !str_contains($a->get('/siswa')['isi'], 'belum mengerjakan pretest'));
$r = $a->get('/kuis/pretest'); cek('kuis draf: siswa melihat "Belum dibuka"', str_contains($r['isi'], 'Belum dibuka') && !str_contains($r['isi'], 'Mulai kuis'));
$a->segarkan('/kuis/pretest');
$r = $a->req('POST', '/kuis/pretest/mulai', ['post' => http_build_query(['_csrf' => $a->tokenTerakhir])]); cek('mulai saat draf ditolak (kembali ke halaman info)', str_ends_with($r['lokasi'], '/kuis/pretest'), $r['lokasi']);
$r = $a->get('/kuis/pretest/kerjakan'); cek('kerjakan tanpa percobaan -> dialihkan', $r['kode'] === 302);
cek('siswa tidak bisa buka /kelola/kuis (403)', $a->get('/kelola/kuis')['kode'] === 403);
cek('siswa tidak bisa unduh CSV hasil (403)', $a->get('/kelola/kuis/hasil.csv?k=1')['kode'] === 403);
cek('tamu tidak bisa buka /kuis', str_ends_with($tamu->get('/kuis')['lokasi'], '/masuk'));

$r = $g->get('/kelola/kuis'); cek('guru membuka Kelola kuis: 30 soal asli, tanpa penanda contoh', $r['kode'] === 200 && !str_contains($r['isi'], 'masih contoh') && str_contains($r['isi'], 'Bank soal (30)') && str_contains($r['isi'], 'Umum'));
cek('admin juga bisa membuka Kelola kuis', $ad->get('/kelola/kuis')['kode'] === 200);
preg_match_all('#/kelola/kuis/(\d+)/atur#', $r['isi'], $mk); $idPre = (int) ($mk[1][0] ?? 0); $idPost = (int) ($mk[1][1] ?? 0);
cek('menemukan id pretest dan post-test', $idPre > 0 && $idPost > 0 && $idPre !== $idPost);
$g->segarkan('/kelola/kuis');
$atur = fn(int $id, array $d) => $g->req('POST', "/kelola/kuis/$id/atur", ['post' => http_build_query($d + ['_csrf' => $g->tokenTerakhir])]);
$atur($idPre, ['status' => 'dibuka', 'durasi' => 1, 'acak' => 1]);
cek('durasi di luar batas ditolak (tetap draf)', str_contains($a->get('/kuis/pretest')['isi'], 'Belum dibuka'));
$atur($idPre, ['status' => 'sembarang', 'durasi' => 30]);
cek('status tidak dikenal ditolak', str_contains($a->get('/kuis/pretest')['isi'], 'Belum dibuka'));
$atur($idPre, ['status' => 'dibuka', 'durasi' => 30, 'acak' => 1]);
$r = $a->get('/kuis/pretest'); cek('pretest dibuka -> tombol Mulai muncul', str_contains($r['isi'], 'Mulai kuis') && str_contains($r['isi'], '30 soal'));
$r = $a->get('/siswa'); cek('beranda menampilkan pretest sebagai tugas', str_contains($r['isi'], 'Pretest MIKRO-NET') && str_contains($r['isi'], 'Sudah dibuka gurumu'));
cek('beranda menampilkan pengingat "belum pretest" dengan tautan', str_contains($r['isi'], 'Kamu belum mengerjakan pretest') && str_contains($r['isi'], 'Kerjakan pretest di sini'));

// Peta kunci jawaban dibaca dari formulir guru (halaman siswa tidak pernah memuat kunci).
$petaKunci = function () use ($g): array {
    $daftar = $g->get('/kelola/kuis')['isi'];
    preg_match_all('#/kelola/kuis/soal/(\d+)"#', $daftar, $m);
    $peta = [];
    foreach (array_unique($m[1]) as $id) {
        $f = $g->get('/kelola/kuis/soal/' . $id)['isi'];
        preg_match('/name="kunci" value="([A-E])" checked/', $f, $k);
        $peta[(int) $id] = ord($k[1] ?? 'A') - 65;
    }
    return $peta;
};
$kunci = $petaKunci();
cek('30 soal asli terbaca beserta kuncinya', count($kunci) === 30, (string) count($kunci));

$a->segarkan('/kuis/pretest');
$r = $a->req('POST', '/kuis/pretest/mulai', ['post' => http_build_query(['_csrf' => $a->tokenTerakhir])]); cek('mulai pretest -> ke halaman kerjakan', str_ends_with($r['lokasi'], '/kuis/pretest/kerjakan'), $r['lokasi']);
$r = $a->get('/kuis/pretest/kerjakan');
preg_match_all('/name="j\[(\d+)\]"/', $r['isi'], $mi); $urutan1 = array_values(array_unique($mi[1]));
cek('halaman kerjakan memuat 30 soal dan pilihannya', $r['kode'] === 200 && count($urutan1) === 30 && substr_count($r['isi'], 'type="radio"') === 120, (string) substr_count($r['isi'], 'type="radio"'));
cek('halaman kerjakan tidak membocorkan kunci', !str_contains($r['isi'], 'kunci') && !str_contains($r['isi'], 'benar'));
$urutan2 = (function () use ($a) { preg_match_all('/name="j\[(\d+)\]"/', $a->get('/kuis/pretest/kerjakan')['isi'], $x); return array_values(array_unique($x[1])); })();
cek('urutan soal tetap sama saat dimuat ulang', $urutan1 === $urutan2);
$idMulai = array_keys($kunci);
$benarSebagian = []; foreach (array_slice($idMulai, 0, 6) as $id) { $benarSebagian[$id] = $kunci[$id]; }
$r = $a->postJson('/kuis/pretest/jawab', ['j' => $benarSebagian], false); cek('simpan otomatis tanpa token CSRF -> 419', $r['kode'] === 419);
$r = $a->postJson('/kuis/pretest/jawab', ['j' => $benarSebagian + [99999 => 1]]); $j = json_decode($r['isi'], true);
cek('simpan otomatis diterima, soal asing diabaikan', ($j['ok'] ?? false) && $j['terjawab'] === 6, $r['isi']);
$a->postJson('/kuis/pretest/jawab', ['j' => [$idMulai[0] => 99]]);
$r = $a->get('/kuis/pretest/kerjakan'); cek('jawaban tersimpan muncul lagi (6 terpilih); pilihan di luar rentang diabaikan', substr_count($r['isi'], 'checked>') === 6, (string) substr_count($r['isi'], 'checked>'));
$r = $a->get('/kuis/pretest/hasil'); cek('hasil belum tersedia sebelum selesai', str_ends_with($r['lokasi'], '/kuis/pretest'));

// Jawab: 24 benar, 6 salah => 80
$semua = []; foreach ($idMulai as $i => $id) { $semua[$id] = $i < 24 ? $kunci[$id] : ($kunci[$id] + 1) % 4; }
$r = $a->postJson('/kuis/pretest/selesai', ['j' => $semua]); $j = json_decode($r['isi'], true);
cek('kumpulkan pretest -> url hasil', ($j['ok'] ?? false) && str_ends_with($j['url'] ?? '', '/kuis/pretest/hasil'), $r['isi']);
$r = $a->get('/kuis/pretest/hasil'); cek('skor pretest 80 (24 dari 30) + rincian per modul', str_contains($r['isi'], '>80<') && str_contains($r['isi'], 'Modul 1') && str_contains($r['isi'], 'Umum') && str_contains($r['isi'], '24 benar dari 30'));
cek('halaman hasil tidak membocorkan kunci jawaban', !str_contains($r['isi'], 'Jawaban benar:'));
$a->postJson('/kuis/pretest/selesai', ['j' => array_map(fn($id) => $kunci[$id], array_combine($idMulai, $idMulai))]);
$r = $a->get('/kuis/pretest/hasil'); cek('kumpul dua kali tidak mengubah skor (idempoten)', str_contains($r['isi'], '>80<'));
cek('pengingat pretest hilang setelah selesai', !str_contains($a->get('/siswa')['isi'], 'belum mengerjakan pretest'));
$r = $a->postJson('/kuis/pretest/jawab', ['j' => [$idMulai[0] => 0]]); cek('simpan setelah selesai ditolak (409)', $r['kode'] === 409);
$a->segarkan('/kuis/pretest'); $r = $a->req('POST', '/kuis/pretest/mulai', ['post' => http_build_query(['_csrf' => $a->tokenTerakhir])]);
$r = $a->get('/kuis/pretest/kerjakan'); cek('sudah selesai: mulai/kerjakan lagi mengarah ke hasil, tidak ada percobaan kedua', str_ends_with($r['lokasi'], '/kuis/pretest/hasil'));

echo "-- kunci soal setelah ada yang mengerjakan --\n";
$idKunci = $idMulai[0];
$g->segarkan('/kelola/kuis/soal/' . $idKunci);
$ubah = fn(int $id, array $d) => $g->req('POST', '/kelola/kuis/soal/' . $id, ['post' => http_build_query($d + ['_csrf' => $g->tokenTerakhir])]);
$ubah($idKunci, ['modul_nomor' => 1, 'teks' => 'Teks yang seharusnya ditolak', 'opsi' => ['satu', 'dua'], 'kunci' => 'A', 'untuk' => 'semua']);
$r = $g->get('/kelola/kuis/soal/' . $idKunci); cek('mengubah soal yang sudah dikerjakan ditolak (dikunci)', !str_contains($r['isi'], 'seharusnya ditolak') && str_contains($r['isi'], 'hanya bisa dibaca'));
$g->req('POST', '/kelola/kuis/soal/' . $idKunci . '/hapus', ['post' => http_build_query(['_csrf' => $g->tokenTerakhir])]);
cek('menghapus soal yang sudah dikerjakan ditolak', $g->get('/kelola/kuis/soal/' . $idKunci)['kode'] === 200);
$tambah = fn(array $d) => $g->req('POST', '/kelola/kuis/soal', ['post' => http_build_query($d + ['_csrf' => $g->tokenTerakhir])]);
$jumlahSoal = fn() => (int) (preg_match('/Bank soal \((\d+)\)/', $g->get('/kelola/kuis')['isi'], $m) ? $m[1] : -1);
$dasarSoal = ['modul_nomor' => 2, 'teks' => 'Soal tambahan uji otomatis nomor satu', 'opsi' => ['a1', 'b2', 'c3'], 'kunci' => 'B', 'untuk' => 'semua'];
$tambah($dasarSoal); cek('soal baru untuk "semua" ditolak setelah pretest berjalan', $jumlahSoal() === 30);
$tambah(['untuk' => 'post', 'teks' => '<img src=x onerror=alert(1)> uji xss'] + $dasarSoal); cek('soal khusus post-test masih boleh ditambah', $jumlahSoal() === 31);
$tambah(['untuk' => 'post', 'kunci' => 'E'] + $dasarSoal); cek('kunci di luar pilihan ditolak', $jumlahSoal() === 31);
$tambah(['untuk' => 'post', 'opsi' => ['sama', 'SAMA']] + $dasarSoal); cek('dua pilihan yang sama ditolak', $jumlahSoal() === 31);
$tambah(['untuk' => 'post', 'modul_nomor' => 99] + $dasarSoal); cek('modul yang tidak ada ditolak', $jumlahSoal() === 31);
$r = $g->get('/kelola/kuis'); cek('teks soal berisi HTML di-escape', !str_contains($r['isi'], '<img src=x') && str_contains($r['isi'], '&lt;img src=x'));

echo "-- impor massal --\n";
$impor = fn(string $d) => $g->req('POST', '/kelola/kuis/impor', ['post' => http_build_query(['data' => $d, '_csrf' => $g->tokenTerakhir])]);
$g->segarkan('/kelola/kuis/impor');
cek('halaman impor 200', $g->get('/kelola/kuis/impor')['kode'] === 200);
$impor("0 | Soal impor pipa nomor satu | p | q | r | s | C | post\n4\tSoal impor tab nomor dua\tw\tx\ty\tz\tD\tpost"); cek('impor format pipa dan TAB berhasil (2 soal)', $jumlahSoal() === 33);
$impor("3 | Soal impor benar sekali | p | q | r | s | A | post\n3 | Soal impor kunci salah | p | q | s | t | Z | post");
$r = $g->get('/kelola/kuis/impor'); cek('galat impor menyebut nomor baris & isi kiriman dipertahankan', str_contains($r['isi'], 'Baris 2') && str_contains($r['isi'], 'Soal impor benar sekali'));
cek('impor semua-atau-tidak-sama-sekali: satu baris salah -> tidak ada yang tersimpan', $jumlahSoal() === 33);
$impor("3 | Soal impor ke pretest terkunci | p | q | r | s | A | semua"); cek('impor ke kuis yang sudah dikerjakan ditolak', $jumlahSoal() === 33);
$impor("   \n\n"); cek('impor kosong ditolak', $jumlahSoal() === 33);
$kunci = $petaKunci(); $idMulai = array_keys($kunci);
cek('bank soal sekarang 33 dengan kunci terbaca', count($kunci) === 33);

echo "-- post-test, hasil kelas, N-gain --\n";
$atur($idPost, ['status' => 'dibuka', 'durasi' => 20]);
cek('post-test dibuka', str_contains($a->get('/kuis/posttest')['isi'], 'Mulai kuis') && str_contains($a->get('/kuis/posttest')['isi'], '33 soal'));
$a->segarkan('/kuis/posttest'); $a->req('POST', '/kuis/posttest/mulai', ['post' => http_build_query(['_csrf' => $a->tokenTerakhir])]);
$rp = $a->get('/kuis/posttest/kerjakan'); preg_match_all('/name="j\[(\d+)\]"/', $rp['isi'], $mp);
$idPostSoal = array_values(array_unique($mp[1]));
cek('post-test memuat 33 soal (30 bersama + 3 khusus post), tanpa soal khusus pre', count($idPostSoal) === 33);
$pos = []; foreach ($idPostSoal as $id) { $pos[$id] = $kunci[(int) $id]; }
$a->postJson('/kuis/posttest/selesai', ['j' => $pos]);
$r = $a->get('/kuis/posttest/hasil'); cek('post-test semua benar -> skor 100', str_contains($r['isi'], '>100<'));
$r = $a->get('/kuis/pretest/hasil'); cek('hasil menampilkan N-gain 1,00 (Tinggi)', str_contains($r['isi'], '1,00') && str_contains($r['isi'], 'Tinggi'));
$r = $a->get('/nilai'); cek('halaman Nilai memuat skor pretest, post-test dan peningkatan', str_contains($r['isi'], 'Pretest dan post-test') && str_contains($r['isi'], '>80<') && str_contains($r['isi'], '>100<') && str_contains($r['isi'], '1,00'));

// Siswa kedua: uji toleransi & batas waktu lewat manipulasi jam di database.
$k2 = new Klien($base); $k2->tokenTerakhir = $k2->token('/daftar');
$k2->req('POST', '/daftar', ['post' => http_build_query(['_csrf' => $k2->tokenTerakhir, 'nama' => 'Kuis Dua', 'nis' => '2025300', 'email' => 'kuis2@contoh.id', 'password' => 'rahasia123', 'password2' => 'rahasia123', 'kode' => 'TKJ1-7K2Q'])]);
$k2->segarkan('/kuis/pretest'); $k2->req('POST', '/kuis/pretest/mulai', ['post' => http_build_query(['_csrf' => $k2->tokenTerakhir])]);
if ($pdo) {
    $pdo->exec("UPDATE kuis_percobaan SET batas_pada = '" . date('Y-m-d H:i:s', time() - 10) . "' WHERE siswa_id = (SELECT id FROM users WHERE email = 'kuis2@contoh.id')");
    preg_match_all('/name="j\[(\d+)\]"/', $k2->get('/kuis/pretest/kerjakan')['isi'], $mpre); $idsPre = array_values(array_unique($mpre[1]));
    cek('pretest siswa kedua memuat 30 soal bersama (tanpa soal khusus post)', count($idsPre) === 30);
    $formKirim = ['_csrf' => $k2->tokenTerakhir]; foreach ($idsPre as $id) { $formKirim['j'][$id] = $kunci[(int) $id]; }
    $r = $k2->req('POST', '/kuis/pretest/selesai', ['post' => http_build_query($formKirim)]);
    cek('kirim formulir biasa (tanpa JavaScript) diterima dan dialihkan ke hasil', str_ends_with($r['lokasi'], '/kuis/pretest/hasil'), $r['lokasi']);
    $r = $k2->get('/kuis/pretest/hasil'); cek('lewat 10 detik dari batas masih dalam toleransi 30 detik -> jawaban dihitung (100)', str_contains($r['isi'], '>100<'));

    $k2->segarkan('/kuis/posttest'); $k2->req('POST', '/kuis/posttest/mulai', ['post' => http_build_query(['_csrf' => $k2->tokenTerakhir])]);
    $rk = $k2->get('/kuis/posttest/kerjakan'); preg_match_all('/name="j\[(\d+)\]"/', $rk['isi'], $mm2); $ids2 = array_values(array_unique($mm2[1]));
    $tiga = []; foreach (array_slice($ids2, 0, 3) as $id) { $tiga[$id] = $kunci[(int) $id]; }
    $k2->postJson('/kuis/posttest/jawab', ['j' => $tiga]);
    $pdo->exec("UPDATE kuis_percobaan SET batas_pada = '" . date('Y-m-d H:i:s', time() - 300) . "' WHERE selesai_pada IS NULL AND siswa_id = (SELECT id FROM users WHERE email = 'kuis2@contoh.id')");
    $r = $k2->postJson('/kuis/posttest/jawab', ['j' => [$ids2[5] => $kunci[(int) $ids2[5]]]]); $j = json_decode($r['isi'], true);
    cek('simpan setelah waktu habis ditolak dan percobaan ditutup otomatis', $r['kode'] === 409 && ($j['selesai'] ?? false));
    $r = $k2->get('/kuis/posttest/hasil'); cek('waktu habis: hanya 3 jawaban tersimpan yang dinilai (3/33 = 9), jawaban terlambat tidak ikut', str_contains($r['isi'], '>' . $kuisHitung(3, 33) . '<') && str_contains($r['isi'], '3 benar dari 33'), $kuisHitung(3, 33) . '');
} else {
    echo "  [lewati] uji pengatur waktu (beri jalur SQLite sebagai argumen ke-2 atau DB_SQLITE)\n";
}

$r = $g->get('/kelola/kuis/hasil?k=1'); $awalHasil = $r['isi'];
cek('hasil kelas: guru melihat siswa, skor dan N-gain', $r['kode'] === 200 && str_contains($awalHasil, 'Ahmad Fauzi') && str_contains($awalHasil, '>80<') && str_contains($awalHasil, '>100<') && str_contains($awalHasil, '1,00') && str_contains($awalHasil, 'Ketuntasan per modul'));
$r = $g->get('/kelola/kuis/hasil.csv?k=1'); cek('CSV hasil: tipe, BOM, dan isi', $r['tipe'] === 'text/csv' && str_starts_with($r['isi'], "\xEF\xBB\xBF") && str_contains($r['isi'], 'Ahmad Fauzi') && str_contains($r['isi'], '1.00,Tinggi'), substr($r['isi'], 0, 120));
$r = $gb->get('/kelola/kuis/hasil?k=1'); cek('guru lain tidak melihat siswa kelas yang bukan miliknya', $r['kode'] === 200 && !str_contains($r['isi'], 'Ahmad Fauzi'));
cek('guru lain tidak bisa mengunduh CSV kelas orang lain (404)', $gb->get('/kelola/kuis/hasil.csv?k=1')['kode'] === 404);
preg_match('#/kelola/kuis/percobaan/(\d+)/reset#', $awalHasil, $mr); $idReset = (int) ($mr[1] ?? 0);
cek('ada tombol reset percobaan', $idReset > 0);
$gb->segarkan('/kelola/kuis/hasil?k=1'); $r = $gb->req('POST', "/kelola/kuis/percobaan/$idReset/reset", ['post' => http_build_query(['_csrf' => $gb->tokenTerakhir, 'k' => 1])]);
cek('guru lain tidak bisa mereset percobaan siswa orang lain (404)', $r['kode'] === 404);
$a->segarkan('/kuis'); $r = $a->req('POST', "/kelola/kuis/percobaan/$idReset/reset", ['post' => http_build_query(['_csrf' => $a->tokenTerakhir])]); cek('siswa tidak bisa mereset (403)', $r['kode'] === 403);
$g->segarkan('/kelola/kuis/hasil?k=1'); $g->req('POST', "/kelola/kuis/percobaan/$idReset/reset", ['post' => http_build_query(['_csrf' => $g->tokenTerakhir, 'k' => 1])]);
$r = $a->get('/kuis/pretest'); cek('setelah reset, siswa bisa mulai lagi', str_contains($r['isi'], 'Mulai kuis'));
$r = $a->get('/nilai'); cek('setelah reset, peningkatan tidak lagi dihitung', !str_contains($r['isi'], '1,00'));
$atur($idPre, ['status' => 'ditutup', 'durasi' => 30]);
$r = $a->get('/kuis/pretest'); cek('pretest ditutup -> siswa melihat "Ditutup"', str_contains($r['isi'], 'Ditutup') && !str_contains($r['isi'], 'Mulai kuis'));
$a->segarkan('/kuis/pretest'); $r = $a->req('POST', '/kuis/pretest/mulai', ['post' => http_build_query(['_csrf' => $a->tokenTerakhir])]);
cek('mulai saat ditutup ditolak', str_ends_with($r['lokasi'], '/kuis/pretest'), $r['lokasi']);
$g->req('POST', '/kelola/kuis/hapus-contoh', ['post' => http_build_query(['_csrf' => $g->tokenTerakhir])]);
$r = $g->get('/kelola/kuis'); cek('hapus contoh: tanpa soal contoh, soal asli tidak ikut terhapus', $r['kode'] === 200 && preg_match('/Bank soal \((\d+)\)/', $r['isi'], $mb) && (int) $mb[1] >= 30);
$r = $ad->get('/kelola/kuis/soal/baru'); cek('form soal baru 200', $r['kode'] === 200 && str_contains($r['isi'], 'name="opsi[]"'));
cek('rute /kelola/kuis/soal/baru tidak tertelan rute {id}', str_contains($r['isi'], 'Soal baru'));

echo "== Ruang guru: beranda, kemajuan, refleksi ==\n";
$r = $g->get('/guru'); cek('beranda guru tampil kelas dan pintasan', $r['kode'] === 200 && str_contains($r['isi'], 'Kelasmu') && str_contains($r['isi'], 'XI TKJ 1') && str_contains($r['isi'], 'Pintasan'));
$r = $g->get('/guru/kemajuan?k=1&m=1'); cek('kemajuan: tabel 6 langkah per siswa', $r['kode'] === 200 && str_contains($r['isi'], 'Ahmad Fauzi') && substr_count($r['isi'], 'titik-status--selesai') >= 6);
$r = $g->get('/guru/refleksi?k=1&m=1'); cek('refleksi kelas: jawaban siswa tampil', $r['kode'] === 200 && str_contains($r['isi'], 'Ahmad Fauzi') && str_contains($r['isi'], 'Paham'));
$r = $a->get('/guru'); cek('siswa tidak bisa membuka beranda guru (403)', $r['kode'] === 403);

echo "== Guru pendamping (satu kelas, dua guru) ==\n";
$r = $gb->get('/guru/kemajuan?k=1&m=1'); cek('sebelum ditambahkan: guru lain tidak melihat siswa XI TKJ 1', $r['kode'] === 200 && !str_contains($r['isi'], 'Ahmad Fauzi'));
cek('sebelum ditambahkan: kelas orang lain 404', $gb->get('/guru/kelas?k=1')['kode'] === 200 && !str_contains($gb->get('/guru/kelas?k=1')['isi'], 'TKJ1-7K2Q'));
$r = $g->get('/guru/kelas?k=1'); preg_match('/<option value="(\d+)">Guru Baru \(guru\.baru@contoh\.id\)/', $r['isi'], $mg); $idGb = (int) ($mg[1] ?? 0);
cek('pilihan "Tambah guru pengajar" berisi guru lain', $idGb > 0);
$g->segarkan('/guru/kelas?k=1'); $g->req('POST', '/guru/kelas/1/guru', ['post' => http_build_query(['_csrf' => $g->tokenTerakhir, 'guru_id' => $idGb])]);
$r = $g->get('/guru/kelas?k=1'); cek('guru pendamping tercatat di kelas', str_contains($r['isi'], 'Guru pengajar ·') && str_contains($r['isi'], 'guru.baru@contoh.id'));
$r = $gb->get('/guru/kelas?k=1'); cek('guru pendamping melihat kode dan anggota kelas', str_contains($r['isi'], 'TKJ1-7K2Q') && str_contains($r['isi'], 'Ahmad Fauzi'));
$r = $gb->get('/guru/kemajuan?k=1&m=1'); cek('guru pendamping melihat kemajuan siswa', str_contains($r['isi'], 'Ahmad Fauzi'));
preg_match('#/guru/lkpd/(\d+)#', $g->get('/guru/penilaian?k=1&m=1')['isi'], $ml);
cek('guru pendamping bisa membuka LKPD siswa', isset($ml[1]) && $gb->get('/guru/lkpd/' . $ml[1])['kode'] === 200);
$gb->segarkan('/guru/kelas?k=1'); $gb->req('POST', '/guru/kelas/1/guru/hapus', ['post' => http_build_query(['_csrf' => $gb->tokenTerakhir, 'guru_id' => 2])]);
$r = $g->get('/guru/kelas?k=1'); cek('guru pendamping tidak bisa menghapus pembuat kelas', str_contains($r['isi'], 'Pembuat kelas') && $g->get('/guru/kemajuan?k=1&m=1')['kode'] === 200 && str_contains($g->get('/guru/kemajuan?k=1&m=1')['isi'], 'Ahmad Fauzi'));
$r = $ad->get('/admin/kelas'); cek('admin melihat kedua guru di daftar kelas', str_contains($r['isi'], 'Guru Pengajar 1, Guru Baru') || str_contains($r['isi'], 'Guru Baru, Guru Pengajar 1'));

echo "== Langkah bebas + buka/tutup LKPD ==\n";
$envF = dirname(__DIR__) . '/.env'; $envAsli = (string) @file_get_contents($envF);
register_shutdown_function(fn() => file_put_contents($envF, $envAsli));
file_put_contents($envF, preg_replace('/^LANGKAH_URUT=.*$/m', '', $envAsli) . "\nLANGKAH_URUT=0\n");
$kz = new Klien($base); $kz->tokenTerakhir = $kz->token('/daftar');
$r = $kz->req('POST', '/daftar', ['post' => http_build_query(['_csrf' => $kz->tokenTerakhir, 'nama' => 'Fajar Nugroho', 'nis' => '31', 'email' => 'fajar@contoh.id', 'password' => 'rahasia123', 'password2' => 'rahasia123', 'kode' => 'TKJ1-7K2Q'])]);
cek('siswa baru (nomor absen 31) terdaftar di XI TKJ 1', str_ends_with($r['lokasi'], '/siswa'), $r['lokasi']);
$kx = $kz;
$r = $kx->get('/modul/2'); cek('mode bebas: Modul 2 tanpa langkah terkunci', $r['kode'] === 200 && !str_contains($r['isi'], 'lk--terkunci') && str_contains($r['isi'], 'boleh langsung ke langkah'));
cek('Modul 2: halaman simulasi memuat game simulasi-p2', str_contains($kx->get('/modul/2/simulasi')['isi'], '/games/simulasi-p2/index.html'));
cek('Modul 2: praktikum 200 dengan perintah CLI', ($rp = $kx->get('/modul/2/praktikum'))['kode'] === 200 && str_contains($rp['isi'], 'switchport mode trunk'));
cek('Modul 2: pengumpulan 200', $kx->get('/modul/2/lkpd')['kode'] === 200);
cek('Modul 2: LKPD 200 dengan 7 PG dan 3 uraian', ($rl = $kx->get('/modul/2/lembar'))['kode'] === 200 && substr_count($rl['isi'], 'type="radio"') === 28 && substr_count($rl['isi'], '<textarea') === 3, (string) substr_count($rl['isi'], 'type="radio"'));
cek('Modul 2: refleksi memuat pertanyaan khusus modul', str_contains($kx->get('/modul/2/refleksi')['isi'], 'Apa perbedaan jaringan biasa dengan jaringan yang memakai VLAN?'));
cek('Modul 2: materi slide P2 dilayani', ($rm = $kx->get('/modul/2/materi/pdf'))['tipe'] === 'application/pdf' && strlen($rm['isi']) > 50000, $rm['tipe']);
echo "== Modul 3 ==\n";
$r = $kx->get('/modul/3'); cek('Modul 3 terbit dan bisa dibuka', $r['kode'] === 200 && str_contains($r['isi'], 'MikroTik dan Winbox') && str_contains($r['isi'], 'kenali Winbox'));
cek('Modul 3: halaman simulasi memuat game simulasi-p3', str_contains($kx->get('/modul/3/simulasi')['isi'], '/games/simulasi-p3/index.html'));
$rp = $kx->get('/modul/3/praktikum');
cek('Modul 3: praktikum 30 langkah, Fase 1 & 2, label perintah sesuai', $rp['kode'] === 200 && substr_count($rp['isi'], 'class="pk-langkah card"') === 30 && str_contains($rp['isi'], 'Fase 2 · Tahap H · Firewall filter') && str_contains($rp['isi'], 'Command Prompt (administrator)') && !str_contains($rp['isi'], 'Packet Tracer'), (string) substr_count($rp['isi'], 'class="pk-langkah card"'));
preg_match_all('#/assets/praktikum/p3/(p3-\d+\.png)#', $rp['isi'], $mg);
cek('Modul 3: semua gambar praktikum ada', count($mg[1]) > 15 && !array_filter(array_unique($mg[1]), fn($f) => !is_file(dirname(__DIR__) . '/public/assets/praktikum/p3/' . $f)), (string) count($mg[1]));
$r = $kx->get('/modul/3/lkpd'); cek('Modul 3: pengumpulan 200, Fase 2 tanpa tanda wajib', $r['kode'] === 200 && str_contains($r['isi'], 'Fase 2: internet dan firewall (opsional)') && !preg_match('#Status DHCP Client ether1 <span class="wajib"#', $r['isi']));
$kx->kirim('/modul/3/lkpd/kumpul', ['j' => ['kelompok' => 'Kelompok 1', 'anggota' => 'Fajar (PC1)', 'ping12' => 'Berhasil (4 dari 4 Reply)', 'ping21' => 'Berhasil (4 dari 4 Reply)']], '/modul/3/lkpd');
$r = $kx->get('/modul/3/lkpd', ['ikuti' => false]); cek('Modul 3: kumpul tanpa bukti ping ditolak, butir opsional tidak diminta', str_contains($r['isi'], 'Bukti PC1 ping PC2') && str_contains($r['isi'], 'Masih ada yang belum diisi') && !preg_match('/Masih ada[^<]*Status DHCP/s', $r['isi']));
$u = unggah($kx, 'f_ping12', 'pc1.png', $png, 'image/png', 3); $u2 = unggah($kx, 'f_ping21', 'pc2.png', $png, 'image/png', 3);
cek('Modul 3: unggah bukti ping diterima', ($u['j']['ok'] ?? false) && ($u2['j']['ok'] ?? false), (string) $u['mentah']);
$kx->kirim('/modul/3/lkpd/kumpul', ['j' => []], '/modul/3/lkpd');
cek('Modul 3: kumpul hanya dengan butir wajib berhasil', str_contains($kx->get('/modul/3/lkpd')['isi'], 'sudah dikumpulkan'));
cek('Modul 3: LKPD 7 PG dan 3 uraian', ($rl = $kx->get('/modul/3/lembar'))['kode'] === 200 && substr_count($rl['isi'], 'type="radio"') === 28 && substr_count($rl['isi'], '<textarea') === 3);
cek('Modul 3: refleksi memuat pertanyaan khusus', str_contains($kx->get('/modul/3/refleksi')['isi'], 'Mengapa ping ke 8.8.8.8 bisa gagal walaupun ping antar-PC berhasil?'));
cek('Modul 3: materi slide P3 dilayani', ($rm = $kx->get('/modul/3/materi/pdf'))['tipe'] === 'application/pdf' && strlen($rm['isi']) > 100000, $rm['tipe']);
$r = $g->get('/guru/penilaian?k=1&m=3'); cek('guru: penilaian Modul 3 menampilkan pengumpulan Fajar', $r['kode'] === 200 && str_contains($r['isi'], 'Fajar Nugroho'));
echo "== Modul 4 ==\n";
$r = $kx->get('/modul/4'); cek('Modul 4 terbit, keterangan simulasi tampil', $r['kode'] === 200 && str_contains($r['isi'], 'hotspot &amp; koneksi, channel &amp; interferensi'));
cek('Modul 4: keterangan praktikum & pengumpulan sesuai wajanbolic (bukan Packet Tracer/.pkt)', str_contains($r['isi'], 'Panduan merakit wajanbolic') && str_contains($r['isi'], 'foto layar hotspot laptop pemancar') && !str_contains($r['isi'], 'Cisco Packet Tracer') && !str_contains($r['isi'], '.pkt'));
cek('Modul 4: halaman simulasi memuat game simulasi-p4', str_contains($kx->get('/modul/4/simulasi')['isi'], '/games/simulasi-p4/index.html'));
$r = $kx->get('/modul/4/materi'); cek('Modul 4: materi memuat video animasi bawaan', str_contains($r['isi'], 'video/materi-p4.mp4') && str_contains($r['isi'], 'poster='));
cek('Modul 4: berkas video dilayani', ($rv = $kx->get('/assets/video/materi-p4.mp4'))['kode'] === 200 && strlen($rv['isi']) > 1000000);
cek('Modul 4: materi slide P4 dilayani', ($rm = $kx->get('/modul/4/materi/pdf'))['tipe'] === 'application/pdf' && strlen($rm['isi']) > 100000);
$rp = $kx->get('/modul/4/praktikum');
cek('Modul 4: praktikum 22 langkah dengan tahap feeder dan hotspot, tanpa ping/netsh', $rp['kode'] === 200 && substr_count($rp['isi'], 'class="pk-langkah card"') === 22 && str_contains($rp['isi'], 'F = D² / (16 × d)') && str_contains($rp['isi'], 'Devices connected') && !str_contains($rp['isi'], 'netsh wlan show interfaces') && !str_contains($rp['isi'], 'ping 192.168.137.1'), (string) substr_count($rp['isi'], 'class="pk-langkah card"'));
preg_match_all('#/assets/praktikum/p4/(p4-\d+\.png)#', $rp['isi'], $mg4);
cek('Modul 4: semua gambar praktikum ada', count($mg4[1]) >= 8 && !array_filter(array_unique($mg4[1]), fn($f) => !is_file(dirname(__DIR__) . '/public/assets/praktikum/p4/' . $f)), (string) count($mg4[1]));
$r = $kx->get('/modul/4/lkpd'); cek('Modul 4: pengumpulan ringkas (foto wajan + layar hotspot pemancar, tanpa lembar data)', $r['kode'] === 200 && str_contains($r['isi'], 'Foto wajanbolic yang sudah dirakit') && str_contains($r['isi'], 'Foto layar Mobile hotspot di laptop pemancar') && !str_contains($r['isi'], '30 m dengan wajan') && !str_contains($r['isi'], 'netsh'));
foreach (['index', 'sinyal', 'hotspot', 'channel', 'hasil'] as $h) cek("Modul 4: game simulasi-p4/$h.html ada", is_file(__DIR__ . "/../public/games/simulasi-p4/$h.html"));
cek('Modul 4: halaman game lama (fokus/link) sudah dihapus', !is_file(__DIR__ . '/../public/games/simulasi-p4/fokus.html') && !is_file(__DIR__ . '/../public/games/simulasi-p4/link.html'));
cek('Modul 4: LKPD 8 PG dan 4 uraian', ($rl = $kx->get('/modul/4/lembar'))['kode'] === 200 && substr_count($rl['isi'], 'type="radio"') === 32 && substr_count($rl['isi'], '<textarea') === 4, (string) substr_count($rl['isi'], 'type="radio"'));
cek('Modul 4: refleksi memuat pertanyaan khusus', str_contains($kx->get('/modul/4/refleksi')['isi'], 'mengapa USB WiFi adapter harus berada di titik fokus'));

$gb->segarkan('/guru/penilaian?k=1&m=2'); $gb->req('POST', '/guru/lkpd-akses', ['post' => http_build_query(['_csrf' => $gb->tokenTerakhir, 'kelas_id' => 1, 'modul_id' => 2, 'buka' => '0', 'kembali' => '/guru/penilaian?k=1&m=2'])]);
cek('guru menutup LKPD: halaman penilaian menampilkan "LKPD ditutup"', str_contains($gb->get('/guru/penilaian?k=1&m=2')['isi'], 'LKPD ditutup'));
$r = $kx->get('/modul/2/lembar'); cek('LKPD ditutup: siswa melihat pemberitahuan, tanpa soal', str_contains($r['isi'], 'LKPD sedang ditutup gurumu') && !str_contains($r['isi'], 'type="radio"'));
$kx->segarkan('/modul/2'); $r = $kx->postJson('/modul/2/lembar/simpan', ['jawaban' => ['pg1' => '1']]); cek('LKPD ditutup: simpan ditolak (423)', $r['kode'] === 423);
cek('LKPD ditutup: keterangan langkah di halaman modul', str_contains($kx->get('/modul/2')['isi'], 'Sedang ditutup gurumu'));
cek('LKPD ditutup hanya untuk kelas itu (modul lain tetap terbuka)', !str_contains($kx->get('/modul/1/lembar')['isi'], 'LKPD sedang ditutup'));
$gb->segarkan('/guru/penilaian?k=1&m=2'); $gb->req('POST', '/guru/lkpd-akses', ['post' => http_build_query(['_csrf' => $gb->tokenTerakhir, 'kelas_id' => 1, 'modul_id' => 2, 'buka' => '1', 'kembali' => '/guru'])]);
$r = $kx->postJson('/modul/2/lembar/simpan', ['jawaban' => ['pg1' => '1']]); cek('LKPD dibuka lagi: simpan diterima', $r['kode'] === 200);
$a->segarkan('/siswa'); $r = $a->req('POST', '/guru/lkpd-akses', ['post' => http_build_query(['_csrf' => $a->tokenTerakhir, 'kelas_id' => 1, 'modul_id' => 2, 'buka' => '0'])]); cek('siswa tidak bisa menutup LKPD (403)', $r['kode'] === 403);
file_put_contents($envF, $envAsli);

echo "== NIS unik per kelas ==\n";
preg_match('/id="kode-kelas">([A-Z0-9-]+)</', $g->get('/guru/kelas?k=2')['isi'], $mk); $kode2 = $mk[1] ?? '';
cek('kode XI TKJ 2 terbaca', $kode2 !== '');
$daftarBaru = function (array $d) use ($base): array { $c = new Klien($base); $c->tokenTerakhir = $c->token('/daftar'); return $c->req('POST', '/daftar', ['post' => http_build_query($d + ['_csrf' => $c->tokenTerakhir, 'password' => 'rahasia123', 'password2' => 'rahasia123'])]); };
$r = $daftarBaru(['nama' => 'Citra Lestari', 'nis' => '2025001', 'email' => 'citra@contoh.id', 'kode' => $kode2]);
cek('NIS sama dengan siswa kelas lain diterima', str_ends_with($r['lokasi'], '/siswa'), $r['lokasi']);
$r = $daftarBaru(['nama' => 'Dewi Anggraini', 'nis' => '2025001', 'email' => 'dewi@contoh.id', 'kode' => 'TKJ1-7K2Q']);
cek('NIS sama di kelas yang sama ditolak', str_ends_with($r['lokasi'], '/daftar'), $r['lokasi']);
$r = $daftarBaru(['nama' => 'Eko Prasetyo', 'nis' => '7', 'email' => 'eko@contoh.id', 'kode' => $kode2]);
cek('nomor absen satu digit diterima sebagai NIS', str_ends_with($r['lokasi'], '/siswa'), $r['lokasi']);
$r = $ad->get('/admin/pengguna?peran=siswa&q=budi'); preg_match('#/admin/pengguna/(\d+)/siswa#', $r['isi'], $mb2); $idBudi = (int) ($mb2[1] ?? 0);
cek('admin melihat tombol ubah nama/NIS siswa', $idBudi > 0 && str_contains($r['isi'], 'Ubah nama/NIS'));
$ad->segarkan('/admin/pengguna');
$ad->req('POST', "/admin/pengguna/$idBudi/siswa", ['post' => http_build_query(['_csrf' => $ad->tokenTerakhir, 'nama' => 'Budi Santoso', 'nis' => '2025001'])]);
cek('admin: NIS bentrok di kelas yang sama ditolak', str_contains($ad->get('/admin/pengguna?peran=siswa&q=budi')['isi'], '2025101'));
$ad->req('POST', "/admin/pengguna/$idBudi/siswa", ['post' => http_build_query(['_csrf' => $ad->tokenTerakhir, 'nama' => 'Budi Santoso P.', 'nis' => '12'])]);
$r = $ad->get('/admin/pengguna?peran=siswa&q=12'); cek('admin mengubah nama dan NIS siswa', str_contains($r['isi'], 'Budi Santoso P.') && str_contains($r['isi'], '>12<'));

echo "== Nilai modul, KKM, remidi & pengayaan ==\n";
$pdoN = new PDO('sqlite:' . __DIR__ . '/../storage/test.sqlite');
$daftarF = new Klien($base); $daftarF->tokenTerakhir = $daftarF->token('/daftar');
$daftarF->req('POST', '/daftar', ['post' => http_build_query(['_csrf' => $daftarF->tokenTerakhir, 'nama' => 'Gilang Ramadhan', 'nis' => '9901', 'email' => 'gilang@contoh.id', 'kode' => 'TKJ1-7K2Q', 'password' => 'rahasia123', 'password2' => 'rahasia123'])]);
$fj = $daftarF; $idF = (int) $pdoN->query("SELECT id FROM users WHERE email = 'gilang@contoh.id'")->fetchColumn();
cek('siswa uji (Gilang) terdaftar di XI TKJ 1', $idF > 0);
$r = $fj->get('/modul/1/remidi'); cek('remidi belum terbuka saat nilai belum lengkap', $r['kode'] === 302 && str_ends_with($r['lokasi'], '/modul/1'));
cek('halaman modul menampilkan nilai belum lengkap', str_contains($fj->get('/modul/1')['isi'], 'Nilai modul muncul setelah semua bagian dinilai'));
$lb1 = json_decode(file_get_contents(__DIR__ . '/../database/content/lembar-p1.json'), true);
$jawF = [];
foreach ($lb1['pg'] as $i => $s) { $jawF[$s['id']] = (string) ($i < 5 ? $s['kunci'] : ($s['kunci'] + 1) % count($s['opsi'])); }
foreach ($lb1['uraian'] as $u) { $jawF[$u['id']] = 'jawaban uraian'; }
$kini = date('Y-m-d H:i:s');
$pdoN->prepare("INSERT INTO lembar_kerja (modul_id, siswa_id, jawaban, status, skor_pg, skor_uraian, nilai, dikumpulkan_pada, dinilai_pada, dibuat_pada, diperbarui_pada) VALUES (1, ?, ?, 'dikumpulkan', 25, '[5,5,5,5,5]', 50, ?, ?, ?, ?)")->execute([$idF, json_encode($jawF), $kini, $kini, $kini, $kini]);
$pdoN->prepare("INSERT INTO lkpd_pengumpulan (modul_id, siswa_id, jawaban, status, dikumpulkan_pada, rubrik_skor, skor, skor_maks, nilai, dibuat_pada, diperbarui_pada) VALUES (1, ?, '{}', 'dikumpulkan', ?, '[16,12,5,15,12]', 60, 100, 60, ?, ?)")->execute([$idF, $kini, $kini, $kini]);
$pdoN->prepare("INSERT INTO modul_siswa (modul_id, siswa_id, halaman_terakhir, total_halaman, game_skor, game_skor_maks, diperbarui_pada) VALUES (1, ?, 20, 20, 80, 100, ?)")->execute([$idF, $kini]);
$r = $fj->get('/modul/1');
cek('nilai modul = 40% praktik + 40% LKPD + 20% simulasi = 60, belum tuntas', str_contains($r['isi'], '>60<') && str_contains($r['isi'], 'Kamu perlu melakukan perbaikan (remidi)') && str_contains($r['isi'], 'Kerjakan remidi'));
cek('beranda siswa menampilkan tugas remidi', str_contains($fj->get('/siswa')['isi'], 'Remidi Modul 1'));
$r = $fj->get('/modul/1/pengayaan'); cek('pengayaan tertutup untuk siswa yang belum tuntas', $r['kode'] === 302);
$r = $fj->get('/modul/1/remidi'); cek('halaman remidi tampil dengan info KKM', $r['kode'] === 200 && str_contains($r['isi'], 'Remidi Pertemuan 1') && str_contains($r['isi'], 'paling tinggi 75'));
$rm1 = json_decode(file_get_contents(__DIR__ . '/../database/content/remidi-p1.json'), true);
$fj->segarkan('/modul/1/remidi');
$fj->req('POST', '/modul/1/remidi/kumpul', ['post' => http_build_query(['_csrf' => $fj->tokenTerakhir, 'j' => ['pg1' => (string) $rm1['pg'][0]['kunci']]])]);
cek('remidi belum lengkap tidak bisa dikumpulkan', str_contains($fj->get('/modul/1/remidi')['isi'], 'Masih ada yang belum dijawab'));
$jr = []; foreach ($rm1['pg'] as $s) { $jr[$s['id']] = (string) $s['kunci']; } foreach ($rm1['uraian'] as $u) { $jr[$u['id']] = 'Network 192.168.20.0, broadcast .127'; }
$r = $fj->postJson('/modul/1/remidi/simpan', ['jawaban' => $jr]); cek('jawaban remidi tersimpan otomatis', $r['kode'] === 200);
$fj->segarkan('/modul/1/remidi');
$fj->req('POST', '/modul/1/remidi/kumpul', ['post' => http_build_query(['_csrf' => $fj->tokenTerakhir])]);
$r = $fj->get('/modul/1/remidi'); cek('remidi terkumpul: PG 7 dari 7 benar, uraian menunggu', str_contains($r['isi'], '7 dari 7 benar') && str_contains($r['isi'], 'Menunggu'));
cek('status modul: remidi menunggu dinilai', str_contains($fj->get('/modul/1')['isi'], 'Remidi menunggu dinilai'));
$r = $fj->postJson('/modul/1/remidi/simpan', ['jawaban' => ['pg1' => '0']]); cek('remidi yang sudah dikumpulkan terkunci (409)', $r['kode'] === 409);
$r = $g->get('/guru/analisis?k=1&m=1');
cek('analisis guru: Gilang perlu remidi dan menunggu dinilai', $r['kode'] === 200 && str_contains($r['isi'], 'Gilang Ramadhan') && str_contains($r['isi'], 'Remidi dan pengayaan menunggu dinilai') && str_contains($r['isi'], 'Harus melakukan perbaikan'));
cek('analisis guru: kelemahan per topik dan rubrik', str_contains($r['isi'], 'chip--lemah') && str_contains($r['isi'], 'Perhitungan subnet') && str_contains($r['isi'], 'Pengaturan IP Address dan Subnet Mask benar'));
cek('analisis guru: soal tersulit dan topik kelas', str_contains($r['isi'], 'Soal pilihan ganda paling sulit') && str_contains($r['isi'], 'Topik yang perlu diulang'));
$csv = $g->get('/guru/analisis/unduh?k=1&m=1');
cek('rekap CSV memuat nilai dan tindak lanjut', str_contains($csv['tipe'], 'text/csv') && str_contains($csv['isi'], 'Gilang Ramadhan') && str_contains($csv['isi'], 'Perbaikan (remidi)'));
cek('siswa tidak bisa membuka analisis (403)', $fj->get('/guru/analisis')['kode'] === 403);
preg_match('#/guru/tindak/(\d+)#', $r['isi'], $mt); $idT = (int) ($mt[1] ?? 0);
cek('siswa tidak bisa membuka penilaian remidi (403)', $fj->get('/guru/tindak/' . $idT)['kode'] === 403);
$r = $g->get('/guru/tindak/' . $idT); cek('guru membuka jawaban remidi', $r['kode'] === 200 && str_contains($r['isi'], 'Remidi · Modul 1') && str_contains($r['isi'], 'Network 192.168.20.0'));
$g->segarkan('/guru/tindak/' . $idT);
$g->req('POST', "/guru/tindak/$idT/nilai", ['post' => http_build_query(['_csrf' => $g->tokenTerakhir, 'u' => ['16', '15'], 'catatan' => ''])]);
cek('skor uraian remidi melebihi maks ditolak', str_contains($g->get('/guru/tindak/' . $idT)['isi'], 'Isi skor setiap soal uraian'));
$g->req('POST', "/guru/tindak/$idT/nilai", ['post' => http_build_query(['_csrf' => $g->tokenTerakhir, 'u' => ['15', '15'], 'catatan' => 'Bagus'])]);
cek('nilai remidi 100 dibatasi KKM: nilai akhir 75', str_contains($g->get('/guru/tindak/' . $idT)['isi'], 'Nilai akhir modul: 75'));
$r = $fj->get('/modul/1'); cek('siswa tuntas setelah remidi dan ditawari pengayaan', str_contains($r['isi'], 'Tuntas setelah remidi') && str_contains($r['isi'], 'Kerjakan pengayaan'));
cek('halaman nilai siswa: nilai modul 75 tuntas', str_contains($fj->get('/nilai')['isi'], 'Tuntas setelah remidi'));
cek('pengayaan terbuka setelah tuntas', $fj->get('/modul/1/pengayaan')['kode'] === 200);
$g->segarkan('/guru/analisis?k=1&m=1');
$g->req('POST', '/guru/kelas/1/nilai', ['post' => http_build_query(['_csrf' => $g->tokenTerakhir, 'kkm' => '80', 'bobot_praktik' => '50', 'bobot_lkpd' => '40', 'bobot_simulasi' => '20'])]);
cek('bobot yang tidak berjumlah 100 ditolak', str_contains($g->get('/guru/analisis?k=1&m=1')['isi'], 'Jumlah bobot harus 100%'));
$g->req('POST', '/guru/kelas/1/nilai', ['post' => http_build_query(['_csrf' => $g->tokenTerakhir, 'kkm' => '80', 'bobot_praktik' => '0', 'bobot_lkpd' => '100', 'bobot_simulasi' => '0'])]);
$csv = $g->get('/guru/analisis/unduh?k=1&m=1'); $barisF = array_values(array_filter(explode("\n", $csv['isi']), fn($l) => str_contains($l, 'Gilang')))[0] ?? '';
cek('KKM 80, bobot LKPD 100%: nilai awal 50, remidi dibatasi 80', str_contains($barisF, ',50,100,80,80,') , $barisF);
$fj->segarkan('/siswa'); $r = $fj->req('POST', '/guru/kelas/1/nilai', ['post' => http_build_query(['_csrf' => $fj->tokenTerakhir, 'kkm' => '10', 'bobot_praktik' => '100', 'bobot_lkpd' => '0', 'bobot_simulasi' => '0'])]);
cek('siswa tidak bisa mengubah KKM (403)', $r['kode'] === 403);
$g->req('POST', '/guru/kelas/1/nilai', ['post' => http_build_query(['_csrf' => $g->tokenTerakhir, 'kkm' => '75', 'bobot_praktik' => '40', 'bobot_lkpd' => '40', 'bobot_simulasi' => '20'])]);
$g->segarkan('/guru/tindak/' . $idT); $g->req('POST', "/guru/tindak/$idT/kembalikan", ['post' => http_build_query(['_csrf' => $g->tokenTerakhir])]);
cek('guru mengembalikan remidi: siswa bisa mengerjakan lagi', str_contains($fj->get('/modul/1/remidi')['isi'], 'id="status-simpan"') && str_contains($fj->get('/modul/1')['isi'], 'Kamu perlu melakukan perbaikan'));
cek('beranda guru menampilkan tombol Analisis', str_contains($g->get('/guru')['isi'], '/guru/analisis?k='));
// Soal baru yang ditambah setelah siswa mengumpulkan tidak mengurangi nilai
$lbBaru = $lb1; $lbBaru['uraian'][] = ['id' => 'u9', 'soal' => 'Soal uraian tambahan guru', 'kunci' => 'x', 'maks' => 10];
$g->segarkan('/kelola/modul/1/isi/lembar'); $isiLb = json_decode(preg_match('#id="data-isi">(.*?)</script>#s', $g->get('/kelola/modul/1/isi/lembar')['isi'], $mi) ? $mi[1] : '{}', true);
$isiLb['uraian'][] = ['id' => 'u9', 'soal' => 'Soal uraian tambahan guru', 'kunci' => 'x', 'maks' => 10];
$r = $g->postJson('/kelola/modul/1/isi/lembar', ['isi' => $isiLb]); cek('guru menambah soal uraian baru setelah ada pengumpulan', $r['kode'] === 200, $r['isi']);
$idLbF = (int) $pdoN->query("SELECT id FROM lembar_kerja WHERE siswa_id = $idF")->fetchColumn();
$r = $g->get('/guru/lkpd/' . $idLbF); cek('jawaban lama: soal baru ditandai tidak dinilai', str_contains($r['isi'], 'tidak dinilai') && substr_count($r['isi'], 'name="u[') === 5);
$g->segarkan('/guru/lkpd/' . $idLbF);
$g->req('POST', "/guru/lkpd/$idLbF/nilai", ['post' => http_build_query(['_csrf' => $g->tokenTerakhir, 'u' => ['10', '10', '10', '10', '10'], 'catatan' => ''])]);
cek('nilai tetap dari soal yang dijawab (25 + 50 dari 100 = 75)', (int) $pdoN->query("SELECT nilai FROM lembar_kerja WHERE id = $idLbF")->fetchColumn() === 75);
$pdoN = null;

echo "== Ruang diskusi ==\n";
$hn = new Klien($base); $hn->tokenTerakhir = $hn->token('/daftar');
$hn->req('POST', '/daftar', ['post' => http_build_query(['_csrf' => $hn->tokenTerakhir, 'nama' => 'Hana Pertiwi', 'nis' => '9902', 'email' => 'hana@contoh.id', 'kode' => $kode2, 'password' => 'rahasia123', 'password2' => 'rahasia123'])]);
cek('siswa kelas lain (Hana, XI TKJ 2) terdaftar', $hn->get('/diskusi')['kode'] === 200);
$a->segarkan('/diskusi');
$r = $a->req('POST', '/diskusi', ['post' => http_build_query(['_csrf' => $a->tokenTerakhir, 'judul' => 'Kenapa ping ke PC2 Request timed out? <script>x</script>', 'isi' => "IP PC1 192.168.1.1/24\nIP PC2 192.168.1.2/24", 'ruang' => 'umum', 'modul' => '1'])]);
preg_match('#/diskusi/(\d+)$#', $r['lokasi'], $md); $idU = (int) ($md[1] ?? 0);
cek('siswa membuat pertanyaan untuk semua kelas tentang Modul 1', $idU > 0, $r['lokasi']);
$r = $a->get('/diskusi/' . $idU); cek('isi pertanyaan tampil aman (HTML di-escape, baris baru dipertahankan)', str_contains($r['isi'], '&lt;script&gt;x&lt;/script&gt;') && str_contains($r['isi'], '192.168.1.1/24<br'));
$a->segarkan('/diskusi');
$r = $a->req('POST', '/diskusi', ['post' => http_build_query(['_csrf' => $a->tokenTerakhir, 'judul' => 'Rahasia kelas satu', 'isi' => 'Hanya untuk XI TKJ 1', 'ruang' => '1', 'modul' => ''])]);
preg_match('#/diskusi/(\d+)$#', $r['lokasi'], $md); $idK = (int) ($md[1] ?? 0);
cek('siswa membuat pertanyaan khusus kelasnya', $idK > 0);
$a->segarkan('/diskusi');
$r = $a->req('POST', '/diskusi', ['post' => http_build_query(['_csrf' => $a->tokenTerakhir, 'judul' => 'Coba kelas lain', 'isi' => 'Tidak boleh', 'ruang' => '2', 'modul' => ''])]);
cek('siswa tidak bisa menulis di ruang kelas lain', str_contains($r['lokasi'], 'tulis=1'));
$r = $a->req('POST', '/diskusi', ['post' => http_build_query(['_csrf' => $a->tokenTerakhir, 'judul' => 'abc', 'isi' => 'x', 'ruang' => 'umum'])]);
cek('judul terlalu pendek ditolak dan isian dikembalikan', str_contains($r['lokasi'], 'tulis=1') && str_contains($a->get('/diskusi?tulis=1')['isi'], 'value="abc"'));
$r = $hn->get('/diskusi');
cek('siswa kelas lain melihat pertanyaan semua kelas, tidak melihat pertanyaan khusus kelas', str_contains($r['isi'], 'Kenapa ping ke PC2') && !str_contains($r['isi'], 'Rahasia kelas satu'));
cek('pertanyaan khusus kelas lain tidak bisa dibuka (404)', $hn->get('/diskusi/' . $idK)['kode'] === 404);
cek('teman sekelas melihat pertanyaan khusus kelas', str_contains($kx->get('/diskusi?ruang=1')['isi'], 'Rahasia kelas satu'));
$hn->segarkan('/diskusi/' . $idU);
$hn->req('POST', "/diskusi/$idU/ikut", ['post' => http_build_query(['_csrf' => $hn->tokenTerakhir])]);
cek('"Saya juga ingin tahu" tercatat', str_contains($hn->get('/diskusi/' . $idU)['isi'], 'Saya juga ingin tahu · 1') && str_contains($hn->get('/diskusi?status=saya')['isi'], 'Kenapa ping ke PC2'));
$hn->req('POST', "/diskusi/$idU/balas", ['post' => http_build_query(['_csrf' => $hn->tokenTerakhir, 'isi' => 'Aku juga sama, sudah cek kabel.'])]);
$r = $a->get('/diskusi/' . $idU); cek('balasan siswa kelas lain tampil dengan nama kelasnya', str_contains($r['isi'], 'Aku juga sama') && str_contains($r['isi'], 'XI TKJ 2'));
$a->segarkan('/diskusi/' . $idU);
cek('siswa tidak bisa menyematkan (403)', $a->req('POST', "/diskusi/$idU/sematkan", ['post' => http_build_query(['_csrf' => $a->tokenTerakhir])])['kode'] === 403);
cek('guru melihat pertanyaan di daftar "belum dijawab guru" dan di beranda', str_contains($g->get('/diskusi?status=belum')['isi'], 'Kenapa ping ke PC2') && str_contains($g->get('/guru')['isi'], 'pertanyaan siswa belum dijawab'));
$g->segarkan('/diskusi/' . $idU);
$r = $g->req('POST', "/diskusi/$idU/balas", ['post' => http_build_query(['_csrf' => $g->tokenTerakhir, 'isi' => 'Coba matikan Windows Firewall di PC2, lalu ping lagi.'])]);
preg_match('/#b(\d+)$/', $r['lokasi'], $mb); $idBal = (int) ($mb[1] ?? 0);
cek('guru membalas', $idBal > 0);
cek('setelah dibalas guru: lencana "Dijawab guru" dan hilang dari "belum dijawab"', str_contains($a->get('/diskusi')['isi'], 'Dijawab guru') && !str_contains($g->get('/diskusi?status=belum')['isi'], 'Kenapa ping ke PC2'));
$a->segarkan('/diskusi/' . $idU);
$a->req('POST', "/diskusi/balasan/$idBal/pilih", ['post' => http_build_query(['_csrf' => $a->tokenTerakhir])]);
$r = $a->get('/diskusi/' . $idU); cek('penanya menandai jawaban guru: topik selesai', str_contains($r['isi'], 'lencana--ok">') && str_contains($r['isi'], 'pesan--terpilih') && str_contains($r['isi'], 'Buka lagi'));
$hn->segarkan('/diskusi/' . $idU);
cek('siswa lain tidak bisa menghapus pertanyaan orang lain (403)', $hn->req('POST', "/diskusi/$idU/hapus", ['post' => http_build_query(['_csrf' => $hn->tokenTerakhir])])['kode'] === 403);
cek('siswa lain tidak bisa menandai jawaban (403)', $hn->req('POST', "/diskusi/balasan/$idBal/pilih", ['post' => http_build_query(['_csrf' => $hn->tokenTerakhir])])['kode'] === 403);
$g->segarkan('/diskusi/' . $idU); $g->req('POST', "/diskusi/$idU/sematkan", ['post' => http_build_query(['_csrf' => $g->tokenTerakhir])]);
cek('guru menyematkan pertanyaan', str_contains($hn->get('/diskusi')['isi'], 'Disematkan'));
cek('pencarian menemukan pertanyaan', str_contains($hn->get('/diskusi?q=Request+timed')['isi'], 'Kenapa ping ke PC2') && str_contains($hn->get('/diskusi?q=zzzz')['isi'], 'Tidak ada diskusi yang cocok'));
cek('halaman modul menampilkan diskusi modul itu', str_contains($a->get('/modul/1')['isi'], 'Diskusi Modul 1') && str_contains($a->get('/modul/1')['isi'], '/diskusi?modul=1'));
cek('admin bisa membuka pertanyaan khusus kelas', $ad->get('/diskusi/' . $idK)['kode'] === 200);
$g->segarkan('/diskusi/' . $idK); $g->req('POST', "/diskusi/$idK/hapus", ['post' => http_build_query(['_csrf' => $g->tokenTerakhir])]);
cek('guru pengajar menghapus pertanyaan (moderasi)', $a->get('/diskusi/' . $idK)['kode'] === 404);
for ($i = 0; $i < 12; $i++) { $hn->segarkan('/diskusi/' . $idU); $r = $hn->req('POST', "/diskusi/$idU/balas", ['post' => http_build_query(['_csrf' => $hn->tokenTerakhir, 'isi' => 'spam ' . $i])]); }
cek('batas kirim mencegah spam', str_contains($hn->get('/diskusi/' . $idU)['isi'], 'terlalu banyak pesan') && !str_contains($hn->get('/diskusi/' . $idU)['isi'], 'spam 11'));
cek('tamu tidak bisa membuka diskusi', $tamu->get('/diskusi')['kode'] === 302);

echo "== Modul ajar dan bahan bacaan ==\n";
$r = $g->get('/kelola/modul/1/dokumen/ajar');
cek('guru membuka modul ajar bawaan (PDF)', $r['kode'] === 200 && $r['tipe'] === 'application/pdf' && md5($r['isi']) === md5_file(__DIR__ . '/../database/content/modul-ajar-p1.pdf'));
cek('siswa tidak bisa membuka modul ajar (403)', $a->get('/kelola/modul/1/dokumen/ajar')['kode'] === 403);
cek('jenis dokumen tidak dikenal -> 404', $g->get('/kelola/modul/1/dokumen/kunci')['kode'] === 404);
$r = $a->get('/modul/1/bacaan'); cek('siswa membuka bahan bacaan (PDF)', $r['kode'] === 200 && $r['tipe'] === 'application/pdf' && str_starts_with($r['isi'], '%PDF'));
cek('langkah Materi menampilkan tombol bahan bacaan', str_contains($a->get('/modul/1/materi')['isi'], '/modul/1/bacaan?unduh=1'));
cek('bahan bacaan tanpa login ditolak', $tamu->get('/modul/1/bacaan')['kode'] === 302);
$r = $g->get('/kelola/modul'); cek('daftar modul: slide bawaan dan tautan modul ajar', str_contains($r['isi'], 'Slide bawaan') && str_contains($r['isi'], '/kelola/modul/1/dokumen/ajar'));
cek('halaman edit modul menampilkan kartu modul ajar dan bahan bacaan', str_contains($g->get('/kelola/modul/1')['isi'], 'Bahan bacaan siswa (PDF)'));
$unggahDok = function (Klien $k, string $jenis, string $isi, string $nama) use ($base) {
    $k->segarkan('/kelola/modul/1');
    $tmp = tempnam(sys_get_temp_dir(), 'pdf'); file_put_contents($tmp, $isi);
    $ch = curl_init($base . '/kelola/modul/1/dokumen/' . $jenis);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $k->jar, CURLOPT_COOKIEFILE => $k->jar,
        CURLOPT_POSTFIELDS => ['_csrf' => $k->tokenTerakhir, 'berkas' => new CURLFile($tmp, 'application/pdf', $nama)]]);
    curl_exec($ch); $kode = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch); unlink($tmp); return $kode;
};
$pdfBaru = file_get_contents(__DIR__ . '/../database/content/contoh-materi.pdf');
$unggahDok($g, 'bacaan', $pdfBaru, 'bacaan-guru.pdf');
cek('guru mengganti bahan bacaan dengan PDF sendiri', md5($a->get('/modul/1/bacaan')['isi']) === md5($pdfBaru) && str_contains($g->get('/kelola/modul/1')['isi'], 'Diunggah guru'));
$unggahDok($g, 'ajar', '<?php echo 1;', 'modul.pdf');
cek('unggahan modul ajar bukan PDF ditolak', md5($g->get('/kelola/modul/1/dokumen/ajar')['isi']) === md5_file(__DIR__ . '/../database/content/modul-ajar-p1.pdf'));
cek('siswa tidak bisa mengunggah dokumen (403)', $unggahDok($a, 'bacaan', $pdfBaru, 'x.pdf') === 403);
$g->segarkan('/kelola/modul/1'); $g->req('POST', '/kelola/modul/1/dokumen/bacaan/hapus', ['post' => http_build_query(['_csrf' => $g->tokenTerakhir])]);
cek('hapus unggahan kembali ke bahan bacaan bawaan', md5($a->get('/modul/1/bacaan')['isi']) === md5_file(__DIR__ . '/../database/content/bacaan-p1.pdf') && !glob(__DIR__ . '/../storage/uploads/dokumen/bacaan-*'));

echo "== Berkas internal tidak bocor ==\n";
foreach (['/.env', '/app/rute.php', '/database/schema.sql', '/storage/logs/app.log', '/public/index.php'] as $p) {
    $r = $tamu->get($p);
    cek("$p tidak bisa diakses langsung", $r['kode'] !== 200 || !str_contains($r['isi'], 'CREATE TABLE'), (string) $r['kode']);
}

echo "\nHasil: $lulus lulus, $gagal gagal\n";
exit($gagal ? 1 : 0);
