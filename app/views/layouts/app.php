<?php
$jalan = Kernel::jalan();
$peranLabel = ['siswa' => 'Portal Siswa', 'guru' => 'Ruang Guru', 'admin' => 'Pusat Kendali'][$pengguna['peran']] ?? '';
$kelasSaya = $pengguna['peran'] === 'siswa' ? Kelas::dariSiswa((int) $pengguna['id']) : null;
$aktif = function (string $href) use ($jalan): bool {
    if ($href === '/modul') {
        return str_starts_with($jalan, '/modul');
    }
    return $jalan === $href || (!in_array($href, ['/siswa', '/admin', '/guru'], true) && str_starts_with($jalan, $href . '/'));
};
?><!doctype html>
<html lang="id">
<head><?= View::bagian('partials/head') ?><title><?= e(meta('judul') ?? 'MIKRO-NET') ?> · MIKRO-NET</title></head>
<body>
<a class="lompat" href="#isi">Langsung ke isi halaman</a>
<header class="bilah">
  <div class="bilah-dalam">
    <a class="merek" href="<?= e(url(Auth::beranda($pengguna))) ?>"><img src="<?= e(asset('img/logo.png')) ?>" alt=""><span><b>MIKRO-NET</b><small><?= e($peranLabel) ?></small></span></a>
    <nav class="nav-atas" aria-label="Menu utama">
      <?php foreach ($menu as [$href, $label, $ikon]): ?>
        <a href="<?= e(url($href)) ?>"<?= $aktif($href) ? ' aria-current="page"' : '' ?>><?= icon($ikon, 18) ?><?= e($label) ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="bilah-kanan">
      <?= tombol_tema() ?>
      <div class="akun">
        <button type="button" class="akun-tombol" id="akun-tombol" aria-expanded="false" aria-controls="akun-panel">
          <span class="avatar" aria-hidden="true"><?= e(inisial($pengguna['nama'])) ?></span><span class="akun-nama"><?= e($pengguna['nama']) ?></span><span class="sr">Menu akun</span>
        </button>
        <div class="akun-panel" id="akun-panel">
          <div class="akun-info"><span class="avatar" aria-hidden="true"><?= e(inisial($pengguna['nama'])) ?></span><div><b><?= e($pengguna['nama']) ?></b><small><?= $kelasSaya ? e($kelasSaya['nama']) . ' · ' : '' ?><?= e(ucfirst($pengguna['peran'])) ?></small></div></div>
          <a href="<?= e(url('/akun/password')) ?>"><?= icon('lock', 18) ?>Ganti password</a>
          <form method="post" action="<?= e(url('/keluar')) ?>"><?= csrf_field() ?><button class="keluar" type="submit"><?= icon('back', 18) ?>Keluar</button></form>
        </div>
      </div>
    </div>
  </div>
</header>
<nav class="nav-bawah" aria-label="Menu utama">
  <?php foreach ($menu as [$href, $label, $ikon]): ?>
    <a href="<?= e(url($href)) ?>"<?= $aktif($href) ? ' aria-current="page"' : '' ?>><?= icon($ikon, 22) ?><?= e($label) ?></a>
  <?php endforeach; ?>
</nav>
<main class="utama" id="isi">
  <?= View::bagian('partials/flash', ['toast' => true]) ?>
  <?= $isi ?>
</main>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
<?php foreach ((array) meta('skrip') as $s): ?><script src="<?= e(str_starts_with($s, 'http') ? $s : asset($s)) ?>" defer></script><?php endforeach; ?>
</body>
</html>
