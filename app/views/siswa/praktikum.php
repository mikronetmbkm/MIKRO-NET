<?php meta('judul', 'Praktikum'); meta('skrip', ['js/praktikum.js']);
$semua = []; $no = 0;
foreach ($def['tahap'] as $ti => $t) { foreach ($t['langkah'] as $s) { $no++; $semua[] = $s + ['no' => $no, 'tahap' => $t['judul'], 'tahapNo' => $ti + 1]; } }
$total = count($semua);
$selesai = (int) $p['praktikum_selesai'] === 1;
$mulaiDi = $selesai ? 1 : max(1, min($total, (int) $p['praktikum_langkah'] + 1));
$gb = fn($f) => asset('praktikum/p' . $m['nomor'] . '/' . $f);
?>
<?= View::bagian('partials/kepala', ['konteks' => 'Modul ' . $m['nomor'] . ' · Praktikum 3 dari 6', 'judul' => $def['judul'] ?? 'Praktikum', 'sub' => $def['sub'] ?? '', 'kembali' => ['/modul/' . $m['id'], 'Modul ' . $m['nomor']], 'jejak' => $langkah, 'aktif' => 'praktikum']) ?>
<div class="isi isi--sempit">
  <section class="card pk-intro">
    <details<?= (int) $p['praktikum_langkah'] === 0 && !$selesai ? ' open' : '' ?>>
      <summary><h2>Sebelum mulai</h2><span class="mini">Durasi <?= e($def['durasi'] ?? '') ?></span></summary>
      <h3>Tujuan</h3><ul class="tujuan"><?php foreach ($def['tujuan'] ?? [] as $t): ?><li><?= e($t) ?></li><?php endforeach; ?></ul>
      <h3>Alat dan bahan</h3><ul class="tujuan"><?php foreach ($def['alat'] ?? [] as $t): ?><li><?= e($t) ?></li><?php endforeach; ?></ul>
      <h3>Dasar teori singkat</h3>
      <dl class="pk-teori"><?php foreach ($def['teori'] ?? [] as $t): ?><dt><?= e($t['istilah']) ?></dt><dd><?= e($t['arti']) ?></dd><?php endforeach; ?></dl>
      <?php if (!empty($def['topologi'])): $tp = $def['topologi']; ?>
        <h3>Topologi dan skema IP</h3>
        <img class="pk-gambar" src="<?= e($gb($tp['gambar'])) ?>" alt="<?= e($tp['alt'] ?? '') ?>" loading="lazy">
        <div class="tabel-bungkus"><table class="tabel"><thead><tr><?php foreach ($tp['kolom'] as $k): ?><th scope="col"><?= e($k) ?></th><?php endforeach; ?></tr></thead>
          <tbody><?php foreach ($tp['baris'] as $b): ?><tr><?php foreach ($b as $i => $c): ?><?= $i === 0 ? '<th scope="row">' . e($c) . '</th>' : '<td class="mono">' . e($c) . '</td>' ?><?php endforeach; ?></tr><?php endforeach; ?></tbody></table></div>
        <?php if (!empty($tp['catatan'])): ?><p class="mini"><?= e($tp['catatan']) ?></p><?php endif; ?>
      <?php endif; ?>
    </details>
  </section>

  <div class="pk" id="pk" data-total="<?= (int) $total ?>" data-mulai="<?= (int) $mulaiDi ?>" data-selesai="<?= $selesai ? '1' : '0' ?>" data-progres="<?= e(url('/modul/' . $m['id'] . '/praktikum/progres')) ?>">
    <div class="pk-atas" id="pk-atas" hidden>
      <b id="pk-info" aria-live="polite"></b>
      <div class="pk-batang" role="progressbar" aria-valuemin="0" aria-valuemax="<?= (int) $total ?>" aria-valuenow="0" id="pk-batang"><span id="pk-isi"></span></div>
    </div>
    <ol class="pk-daftar">
      <?php foreach ($semua as $s): ?>
        <li class="pk-langkah card" data-no="<?= (int) $s['no'] ?>" id="langkah-<?= (int) $s['no'] ?>">
          <p class="pk-tahap"><?= preg_match('/^(Tahap|Fase|Penutup)/', $s['tahap']) ? e($s['tahap']) : 'Tahap ' . (int) $s['tahapNo'] . ' · ' . e($s['tahap']) ?></p>
          <h2><span class="pk-no" aria-hidden="true"><?= (int) $s['no'] ?></span> <?= e($s['judul']) ?></h2>
          <p class="pk-isi"><?= e($s['isi']) ?></p>
          <div class="pk-badan">
          <?php if (!empty($s['tabel'])): ?><div class="tabel-bungkus pk-tabel"><table class="tabel"><thead><tr><?php foreach ($s['tabel']['kolom'] as $k): ?><th scope="col"><?= e($k) ?></th><?php endforeach; ?></tr></thead>
            <tbody><?php foreach ($s['tabel']['baris'] as $b): ?><tr><?php foreach ($b as $i => $c): ?><?= $i === 0 ? '<th scope="row">' . e($c) . '</th>' : '<td class="mono">' . e($c) . '</td>' ?><?php endforeach; ?></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
          <?php if (!empty($s['kode'])): ?><div class="pk-kode"><div class="pk-kode-atas"><span><?= e($s['kode_label'] ?? 'Perintah CLI') ?></span><button class="btn btn--garis btn--kecil" type="button" data-salin="#kode-<?= (int) $s['no'] ?>">Salin</button></div><pre id="kode-<?= (int) $s['no'] ?>"><code><?= e($s['kode']) ?></code></pre></div><?php endif; ?>
          <?php if (!empty($s['catatan'])): ?><p class="pk-catatan"><?= icon('chat', 18) ?><span><?= e($s['catatan']) ?></span></p><?php endif; ?>
          <?php if (!empty($s['cek'])): ?><div class="pk-cek"><b>Checkpoint, pastikan:</b><ul><?php foreach ($s['cek'] as $c): ?><li><?= e($c) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
          <?php if (!empty($s['gambar']) || (empty($s['kode']) && empty($s['tabel']))): ?>
          <div class="pk-media<?= empty($s['gambar']) ? ' pk-media--kosong' : '' ?><?= count($s['gambar'] ?? []) > 1 ? ' pk-media--banyak' : '' ?>">
            <?php foreach ($s['gambar'] ?? [] as $g): ?><a href="<?= e($gb($g)) ?>" target="_blank" rel="noopener" title="Buka gambar ukuran penuh"><img class="pk-gambar" src="<?= e($gb($g)) ?>" alt="Tangkapan layar untuk langkah <?= (int) $s['no'] ?>: <?= e($s['judul']) ?>" loading="lazy"></a><?php endforeach; ?>
            <?php if (empty($s['gambar'])): ?><span><?= icon('wrench', 28) ?><br>Langkah ini tanpa gambar. <?= e($def['tanpa_gambar'] ?? 'Kerjakan langsung di Packet Tracer.') ?></span><?php endif; ?>
          </div>
          <?php endif; ?>
          </div>
        </li>
      <?php endforeach; ?>
    </ol>
    <div class="pk-nav" id="pk-nav" hidden>
      <button class="btn btn--garis" type="button" id="pk-prev"><?= icon('back', 18) ?> Sebelumnya</button>
      <span class="sp"></span>
      <button class="btn btn--utama" type="button" id="pk-next">Sudah, lanjut</button>
    </div>
    <p class="mini pk-status" id="pk-status" aria-live="polite"></p>
  </div>

  <?php if (!empty($def['tantangan'])): ?>
  <section class="card"><details><summary><h2>Tantangan (opsional)</h2></summary>
    <ul class="tujuan"><?php foreach ($def['tantangan'] as $t): ?><li><?= e($t) ?></li><?php endforeach; ?></ul></details></section>
  <?php endif; ?>
  <?php if (!empty($def['masalah'])): ?>
  <section class="card"><details><summary><h2>Troubleshooting</h2><span class="mini">Kalau ada yang tidak jalan</span></summary>
    <div class="tabel-bungkus"><table class="tabel"><thead><tr><th scope="col">Gejala</th><th scope="col">Kemungkinan penyebab</th><th scope="col">Solusi</th></tr></thead>
    <tbody><?php foreach ($def['masalah'] as $r): ?><tr><th scope="row"><?= e($r[0]) ?></th><td><?= e($r[1]) ?></td><td><?= e($r[2]) ?></td></tr><?php endforeach; ?></tbody></table></div></details></section>
  <?php endif; ?>

  <?= View::bagian('partials/lanjut', ['langkah' => $langkah, 'dari' => 'praktikum', 'tampil' => $selesai, 'id' => 'lanjut']) ?>
</div>
