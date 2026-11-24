<?php
/** Pilihan kelas + modul untuk halaman guru. Variabel: $aksi (path), $daftar, $modul, $kelas, $m. */
?>
<form class="penyaring pemilih-guru" method="get" action="<?= e(url($aksi)) ?>">
  <div><label class="label" for="pk-k">Kelas</label><select class="input" id="pk-k" name="k" onchange="this.form.submit()"><?php foreach ($daftar as $x): ?><option value="<?= (int) $x['id'] ?>" <?= $kelas && (int) $x['id'] === (int) $kelas['id'] ? 'selected' : '' ?>><?= e($x['nama']) ?></option><?php endforeach; ?></select></div>
  <div><label class="label" for="pk-m">Modul</label><select class="input" id="pk-m" name="m" onchange="this.form.submit()"><?php foreach ($modul as $x): ?><option value="<?= (int) $x['id'] ?>" <?= $m && (int) $x['id'] === (int) $m['id'] ? 'selected' : '' ?>>Modul <?= (int) $x['nomor'] ?> · <?= e($x['judul']) ?></option><?php endforeach; ?></select></div>
  <noscript><button class="btn btn--utama" type="submit">Tampilkan</button></noscript>
</form>
