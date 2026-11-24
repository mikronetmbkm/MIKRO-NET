<?php
// Dipakai untuk LKPD (langkah 5) dan untuk soal remidi/pengayaan ($mode = 'remidi' | 'pengayaan', $dasar = alamat dasar, $info = teks penjelasan).
$mode ??= 'lembar'; $label = ['lembar' => 'LKPD', 'remidi' => 'Remidi', 'pengayaan' => 'Pengayaan'][$mode];
$dasar ??= '/modul/' . $m['id'] . '/lembar'; $langkahan = $mode === 'lembar';
meta('judul', $label); meta('skrip', ['js/lkpd.js']);
$jaw = $lb['jawaban_arr'];
$huruf = 'abcde';
$benar = Lembar::jumlahBenar($def, $jaw);
$poin = Lembar::poinPg($def);
$jPg = count($def['pg'] ?? []); ?>
<?= View::bagian('partials/kepala', ['konteks' => 'Modul ' . $m['nomor'] . ' · ' . ($langkahan ? 'LKPD 5 dari 6' : $label), 'judul' => $def['judul'] ?? $label, 'sub' => $def['petunjuk'] ?? '', 'kembali' => ['/modul/' . $m['id'], 'Modul ' . $m['nomor']]] + ($langkahan ? ['jejak' => $langkah, 'aktif' => 'lembar'] : [])) ?>
<div class="isi isi--sempit">
<?php if (!empty($info)): ?><div class="flash flash--info" style="position:static;margin-bottom:16px"><?= e($info) ?></div><?php endif; ?>
<?php if ($terkunci): ?>
  <section class="card" style="margin-bottom:16px">
    <h2>Hasilmu</h2>
    <div class="hasil-lembar">
      <?php if ($jPg): ?><div><span class="mini">Pilihan ganda (otomatis)</span><b class="mono"><?= (int) $lb['skor_pg'] ?> / <?= $jPg * $poin ?></b><span class="mini"><?= $benar ?> dari <?= $jPg ?> benar</span></div><?php endif; ?>
      <?php if (!empty($def['uraian'])): ?><div><span class="mini">Uraian (dinilai guru)</span><?php if ($lb['nilai'] !== null): ?><b class="mono"><?= (int) array_sum($lb['skor_uraian_arr']) ?> / <?= Lembar::maksUntuk($def, $jaw) - $jPg * $poin ?></b><?php else: ?><b>Menunggu</b><span class="mini">Gurumu belum menilai</span><?php endif; ?></div><?php endif; ?>
      <div class="hasil-total"><span class="mini">Nilai <?= e($label) ?></span><b class="mono"><?= $lb['nilai'] !== null ? (int) $lb['nilai'] : '–' ?></b><?php if ($lb['nilai'] !== null): ?><span class="mini"><?= e(kategori_nilai((int) $lb['nilai'])) ?></span><?php endif; ?></div>
    </div>
    <?php if ($lb['catatan']): ?><div class="flash flash--info" style="margin:12px 0 0">Catatan dari gurumu: <?= e($lb['catatan']) ?></div><?php endif; ?>
  </section>
  <?php if ($langkahan): ?><?= View::bagian('partials/lanjut', ['langkah' => $langkah, 'dari' => 'lembar', 'tampil' => true, 'id' => 'lanjut']) ?><?php else: ?><p style="margin:0 0 16px"><a class="btn btn--garis btn--kecil" href="<?= e(url('/modul/' . $m['id'])) ?>">Kembali ke Modul <?= (int) $m['nomor'] ?></a></p><?php endif; ?>
  <?php if ($jPg): ?>
  <section class="card lkpd-bagian" style="margin-top:16px">
    <h2>Pembahasan pilihan ganda</h2>
    <?php foreach ($def['pg'] ?? [] as $i => $s): $pilih = $jaw[$s['id']] ?? ''; $ok = $pilih !== '' && (int) $pilih === (int) $s['kunci']; ?>
      <div class="butir">
        <span class="label"><?= e($s['soal']) ?></span>
        <ul class="pg-hasil">
          <?php foreach ($s['opsi'] as $j => $o): $kelas = $j === (int) $s['kunci'] ? 'benar' : ($pilih !== '' && (int) $pilih === $j ? 'salah' : ''); ?>
            <li class="<?= $kelas ?>"><b><?= $huruf[$j] ?>.</b> <?= e($o) ?><?= $pilih !== '' && (int) $pilih === $j ? ' <span class="mini">(jawabanmu)</span>' : '' ?></li>
          <?php endforeach; ?>
        </ul>
        <p class="bahas <?= $ok ? 'ok' : 'tidak' ?>"><?= $ok ? '✔ Benar.' : '✘ Kunci: ' . $huruf[(int) $s['kunci']] . '.' ?> <?= e($s['bahas'] ?? '') ?></p>
      </div>
    <?php endforeach; ?>
  </section>
  <?php endif; ?>
  <?php if (!empty($def['uraian'])): ?>
  <section class="card lkpd-bagian">
    <h2>Uraian</h2>
    <?php foreach ($def['uraian'] as $i => $s): ?>
      <div class="butir">
        <span class="label"><?= nl2br(e($s['soal'])) ?></span>
        <div class="jawaban-teks"><?= array_key_exists($s['id'], $jaw) ? e($jaw[$s['id']]) : '<span class="mini">Soal ini ditambahkan setelah kamu mengumpulkan, jadi tidak dinilai.</span>' ?></div>
        <?php if ($lb['nilai'] !== null): ?><p class="mini" style="margin:6px 0 0">Skor: <b><?= (int) ($lb['skor_uraian_arr'][$i] ?? 0) ?></b> / <?= (int) ($s['maks'] ?? 10) ?></p><?php endif; ?>
        <details class="contoh-jawab"><summary>Lihat contoh jawaban</summary><p><?= nl2br(e($s['kunci'] ?? '')) ?></p></details>
      </div>
    <?php endforeach; ?>
  </section>
  <?php endif; ?>
<?php elseif (!empty($ditutup)): ?>
  <section class="card lkpd-tutup">
    <span class="ikon-kotak ikon-kotak--kuning" aria-hidden="true"><?= icon('lock', 26) ?></span>
    <div><h2>LKPD sedang ditutup gurumu</h2>
      <p>Soal LKPD akan muncul di sini setelah gurumu membukanya. Sambil menunggu, kamu bisa mengulang materi atau mengerjakan langkah lain.</p>
      <a class="btn btn--garis btn--kecil" href="<?= e(url('/modul/' . $m['id'])) ?>">Kembali ke Modul <?= (int) $m['nomor'] ?></a></div>
  </section>
<?php else: ?>
  <?php if (trim((string) $lb['catatan']) !== ''): ?><div class="flash flash--info">Catatan dari gurumu: <?= e($lb['catatan']) ?></div><?php endif; ?>
  <form method="post" action="<?= e(url($dasar . '/kumpul')) ?>" id="form-lkpd" data-simpan="<?= e(url($dasar . '/simpan')) ?>" data-terkunci="0">
    <?= csrf_field() ?>
    <?php if ($jPg): ?>
    <section class="card lkpd-bagian">
      <h2>A. Pilihan ganda</h2>
      <p class="mini">Pilih satu jawaban yang paling tepat. Dinilai otomatis setelah dikumpulkan (<?= $poin ?> poin per soal).</p>
      <?php foreach ($def['pg'] ?? [] as $s): $id = $s['id']; $pilih = (string) ($jaw[$id] ?? ''); ?>
        <div class="butir">
          <span class="label" id="lbl-<?= e($id) ?>"><?= e($s['soal']) ?> <span class="wajib" aria-label="wajib">*</span></span>
          <div class="pilihan pilihan--pg" role="radiogroup" aria-labelledby="lbl-<?= e($id) ?>"><?php foreach ($s['opsi'] as $j => $o): ?><label><input type="radio" name="j[<?= e($id) ?>]" value="<?= $j ?>" <?= $pilih === (string) $j ? 'checked' : '' ?>><b><?= $huruf[$j] ?>.</b> <?= e($o) ?></label><?php endforeach; ?></div>
        </div>
      <?php endforeach; ?>
    </section>
    <?php endif; ?>
    <?php if (!empty($def['uraian'])): ?>
    <section class="card lkpd-bagian">
      <h2><?= $jPg ? 'B. ' : '' ?>Uraian</h2>
      <p class="mini">Jawab dengan kalimatmu sendiri, jelas dan runtut. Tunjukkan langkah hitungan bila perlu. Dinilai gurumu.</p>
      <?php foreach ($def['uraian'] as $s): $id = $s['id']; ?>
        <div class="butir">
          <label for="b-<?= e($id) ?>"><?= nl2br(e($s['soal'])) ?> <span class="wajib" aria-label="wajib">*</span></label>
          <textarea class="input" id="b-<?= e($id) ?>" name="j[<?= e($id) ?>]" maxlength="5000"><?= e($jaw[$id] ?? '') ?></textarea>
        </div>
      <?php endforeach; ?>
    </section>
    <?php endif; ?>
    <div class="bar-lkpd">
      <span class="status-simpan" id="status-simpan" aria-live="polite"><?= icon('check', 16) ?> Tersimpan otomatis</span>
      <button class="btn btn--kuning" type="submit" data-konfirmasi="Kumpulkan <?= e($label) ?> sekarang? Setelah dikumpulkan, jawaban tidak bisa diubah dan pembahasan pilihan ganda akan muncul." data-ya="Kumpulkan">Kumpulkan</button>
    </div>
  </form>
<?php endif; ?>
</div>
