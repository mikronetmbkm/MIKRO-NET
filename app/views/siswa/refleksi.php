<?php meta('judul', 'Refleksi');
$v = fn(string $k) => (string) ($jawab[$k] ?? '');
$bisa = array_map('intval', (array) ($jawab['bisa'] ?? [])); ?>
<?= View::bagian('partials/kepala', ['konteks' => 'Modul ' . $m['nomor'] . ' · Refleksi 6 dari 6', 'judul' => 'Refleksi', 'sub' => 'Langkah terakhir. Tidak ada jawaban benar atau salah: tulis jujur apa yang kamu rasakan dan pahami.', 'kembali' => ['/modul/' . $m['id'], 'Modul ' . $m['nomor']], 'jejak' => $langkah, 'aktif' => 'refleksi']) ?>
<div class="isi isi--sempit">
  <?php if ($sudah): ?><div class="flash flash--sukses">Refleksimu sudah tersimpan. Kamu masih boleh mengubahnya lalu menyimpan lagi.</div><?php endif; ?>
  <form method="post" action="<?= e(url('/modul/' . $m['id'] . '/refleksi')) ?>" class="refleksi">
    <?= csrf_field() ?>
    <section class="card">
      <fieldset class="skala-paham">
        <legend><h2>1. Seberapa paham kamu sekarang?</h2></legend>
        <?php foreach (Modul::SKALA_PAHAM as $n => $teks): ?>
          <label><input type="radio" name="paham" value="<?= $n ?>" <?= (int) $v('paham') === $n ? 'checked' : '' ?> required><span class="angka"><?= $n ?></span><span><?= e($teks) ?></span></label>
        <?php endforeach; ?>
      </fieldset>
    </section>
    <?php if ($tujuan): ?>
    <section class="card">
      <fieldset class="cek-tujuan">
        <legend><h2>2. Centang yang sudah bisa kamu lakukan</h2></legend>
        <p class="mini">Ini tujuan belajar Modul <?= (int) $m['nomor'] ?>. Yang belum dicentang tidak apa-apa; gurumu jadi tahu bagian mana yang perlu diulang.</p>
        <?php foreach ($tujuan as $i => $t): ?>
          <label><input type="checkbox" name="bisa[]" value="<?= (int) $i ?>" <?= in_array($i, $bisa, true) ? 'checked' : '' ?>><span>Aku bisa <?= e(lcfirst($t)) ?></span></label>
        <?php endforeach; ?>
      </fieldset>
    </section>
    <?php endif; ?>
    <?php if (!empty($khusus)): $no = 2 + ($tujuan ? 1 : 0); ?>
    <section class="card">
      <h2><?= $no ?>. Cek pemahamanmu</h2>
      <p class="mini">Jawab dengan kata-katamu sendiri, singkat saja.</p>
      <?php foreach ($khusus as $i => $q): ?>
        <div class="field"><label for="khusus-<?= (int) $i ?>"><b><?= e($q) ?></b></label>
          <textarea class="input" id="khusus-<?= (int) $i ?>" name="khusus[<?= (int) $i ?>]" maxlength="2000" required><?= e((string) ($jawab['khusus'][$i] ?? '')) ?></textarea></div>
      <?php endforeach; ?>
    </section>
    <?php endif; ?>
    <section class="card">
      <div class="field"><label for="penting"><b><?= $nb = 3 + (!empty($khusus) ? 1 : 0) - ($tujuan ? 0 : 1) ?>. Hal paling penting yang kamu pelajari hari ini</b></label>
        <textarea class="input" id="penting" name="penting" maxlength="2000" required placeholder="Contoh: ternyata dua PC bisa saling ping kalau network-nya sama."><?= e($v('penting')) ?></textarea></div>
      <div class="field"><label for="bingung"><b><?= $nb + 1 ?>. Bagian yang masih membingungkan</b></label>
        <textarea class="input" id="bingung" name="bingung" maxlength="2000" required placeholder="Contoh: cara menghitung broadcast. Kalau tidak ada, tulis &quot;tidak ada&quot;."><?= e($v('bingung')) ?></textarea></div>
      <div class="field"><label for="tanya"><b><?= $nb + 2 ?>. Pertanyaan untuk guru</b> <span class="mini">(boleh kosong)</span></label>
        <textarea class="input" id="tanya" name="tanya" maxlength="1000"><?= e($v('tanya')) ?></textarea></div>
      <button class="btn btn--kuning btn--penuh" type="submit"><?= $sudah ? 'Simpan perubahan' : 'Simpan refleksi dan selesaikan modul' ?></button>
    </section>
  </form>
</div>
