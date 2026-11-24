<?php
/** Jawaban refleksi siswa untuk guru. Variabel: $refleksi (array|null), $tujuan (array), $khusus (array pertanyaan khusus modul). */
$khusus = $khusus ?? [];
if (!$refleksi): ?><p class="mini" style="margin:0">Siswa belum mengisi refleksi.</p>
<?php else: ?>
  <div class="butir"><span class="label">Seberapa paham</span><div class="jawaban-teks"><?= (int) ($refleksi['paham'] ?? 0) ?>/5 · <?= e(Modul::SKALA_PAHAM[(int) ($refleksi['paham'] ?? 0)] ?? '-') ?></div></div>
  <?php if ($tujuan): ?><div class="butir"><span class="label">Tujuan yang menurut siswa sudah bisa</span><div class="jawaban-teks"><?php foreach ($tujuan as $i => $t): ?><?= in_array($i, (array) ($refleksi['bisa'] ?? []), true) ? '✓ ' : '✗ ' ?><?= e($t) ?><br><?php endforeach; ?></div></div><?php endif; ?>
  <?php // Pertanyaan yang tersimpan bersama jawaban dipakai lebih dulu (pertanyaan modul bisa diubah guru setelahnya).
  $daftarQ = !empty($refleksi['khusus_q']) && is_array($refleksi['khusus_q']) ? $refleksi['khusus_q'] : $khusus;
  foreach ($daftarQ as $i => $q): $t = trim((string) ($refleksi['khusus'][$i] ?? '')); ?>
    <div class="butir"><span class="label"><?= e((string) $q) ?></span><div class="jawaban-teks"><?= $t === '' ? '<span class="mini">Tidak diisi</span>' : e($t) ?></div></div>
  <?php endforeach; ?>
  <?php foreach (['penting' => 'Hal penting yang dipelajari', 'bingung' => 'Masih membingungkan', 'tanya' => 'Pertanyaan untuk guru'] as $k => $lbl): $t = trim((string) ($refleksi[$k] ?? '')); ?>
    <div class="butir"><span class="label"><?= e($lbl) ?></span><div class="jawaban-teks"><?= $t === '' ? '<span class="mini">Tidak diisi</span>' : e($t) ?></div></div>
  <?php endforeach; ?>
<?php endif;
