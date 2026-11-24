<?php meta('judul', 'Refleksi siswa');
$sudah = array_values(array_filter($baris, fn($r) => $r['refleksi'] !== null));
$belum = array_values(array_filter($baris, fn($r) => $r['refleksi'] === null));
$rata = $sudah ? round(array_sum(array_map(fn($r) => (int) ($r['refleksi']['paham'] ?? 0), $sudah)) / count($sudah), 1) : 0; ?>
<?= View::bagian('partials/kepala', ['konteks' => 'Ruang guru', 'judul' => 'Refleksi siswa', 'sub' => 'Apa yang sudah dipahami siswa, apa yang masih membingungkan, dan pertanyaan mereka untukmu.', 'naik' => true]) ?>
<div class="isi isi--naik">
  <section class="card" style="margin-bottom:22px"><?= View::bagian('partials/pemilih-guru', ['aksi' => '/guru/refleksi', 'daftar' => $daftar, 'modul' => $modul, 'kelas' => $kelas, 'm' => $m]) ?></section>
  <?php if ($kelas && $m): ?>
    <div class="statistik statistik--3">
      <div class="stat"><span class="ikon-kotak ikon-kotak--hijau"><?= icon('check', 24) ?></span><div><b><?= count($sudah) ?><small style="display:inline;font-size:16px">/<?= count($baris) ?></small></b><small>siswa sudah mengisi</small></div></div>
      <div class="stat"><span class="ikon-kotak"><?= icon('star', 24) ?></span><div><b><?= $sudah ? str_replace('.', ',', (string) $rata) : '–' ?><small style="display:inline;font-size:16px">/5</small></b><small>rata-rata tingkat paham</small></div></div>
      <div class="stat"><span class="ikon-kotak ikon-kotak--kuning"><?= icon('chat', 24) ?></span><div><b><?= count(array_filter($sudah, fn($r) => trim((string) ($r['refleksi']['tanya'] ?? '')) !== '')) ?></b><small>pertanyaan untukmu</small></div></div>
    </div>
    <?php foreach ($sudah as $r): $rf = $r['refleksi']; ?>
      <details class="card refleksi-kartu" data-muncul>
        <summary><span><b><?= e($r['siswa']['nama']) ?></b> <span class="mini mono"><?= e($r['siswa']['nis']) ?></span></span>
          <span class="refleksi-ringkas"><span class="persen-pil">Paham <?= (int) ($rf['paham'] ?? 0) ?>/5</span><?= trim((string) ($rf['tanya'] ?? '')) !== '' ? ' <span class="lencana lencana--warn">Ada pertanyaan</span>' : '' ?></span></summary>
        <div class="lkpd-bagian"><?= View::bagian('partials/refleksi-jawaban', ['refleksi' => $rf, 'tujuan' => $tujuan, 'khusus' => $khusus]) ?></div>
      </details>
    <?php endforeach; ?>
    <?php if ($belum): ?><section class="card"><h2>Belum mengisi (<?= count($belum) ?>)</h2><p class="mini" style="margin:0"><?= e(implode(', ', array_map(fn($r) => $r['siswa']['nama'], $belum))) ?></p></section><?php endif; ?>
    <?php if (!$baris): ?><section class="card"><p style="margin:0">Belum ada siswa di kelas ini.</p></section><?php endif; ?>
  <?php endif; ?>
</div>
