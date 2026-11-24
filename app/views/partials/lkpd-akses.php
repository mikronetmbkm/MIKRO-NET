<?php
/** Saklar buka/tutup LKPD untuk satu kelas + modul. Variabel: $kelas, $m, $buka (bool), $kembali (path), $ringkas (bool, opsional). */
$ringkas = $ringkas ?? false;
?>
<form method="post" action="<?= e(url('/guru/lkpd-akses')) ?>" class="lkpd-akses<?= $buka ? ' lkpd-akses--buka' : '' ?><?= $ringkas ? ' lkpd-akses--ringkas' : '' ?>"
  <?= $buka ? 'data-konfirmasi="Tutup LKPD Modul ' . (int) $m['nomor'] . ' untuk ' . e($kelas['nama']) . '? Siswa yang belum mengumpulkan tidak bisa mengerjakan sampai dibuka lagi." data-ya="Tutup LKPD"' : '' ?>>
  <?= csrf_field() ?>
  <input type="hidden" name="kelas_id" value="<?= (int) $kelas['id'] ?>"><input type="hidden" name="modul_id" value="<?= (int) $m['id'] ?>">
  <input type="hidden" name="buka" value="<?= $buka ? '0' : '1' ?>"><input type="hidden" name="kembali" value="<?= e($kembali) ?>">
  <span class="lkpd-akses-ikon" aria-hidden="true"><?= icon($buka ? 'clip' : 'lock', 20) ?></span>
  <span class="lkpd-akses-teks"><b>LKPD <?= $buka ? 'terbuka' : 'ditutup' ?></b><?php if (!$ringkas): ?><small><?= $buka ? 'Siswa bisa mengerjakan dan mengumpulkan.' : 'Siswa belum bisa mengerjakan. Hasil yang sudah dikumpulkan tetap bisa dilihat.' ?></small><?php endif; ?></span>
  <button class="btn btn--kecil <?= $buka ? 'btn--garis' : 'btn--kuning' ?>" type="submit"><?= $buka ? 'Tutup' : 'Buka sekarang' ?><span class="sr"> LKPD <?= e($kelas['nama']) ?></span></button>
</form>
