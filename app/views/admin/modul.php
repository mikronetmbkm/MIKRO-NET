<?php meta('judul', 'Modul'); ?>
<?= View::bagian('partials/kepala', ['konteks' => 'Guru & Admin', 'judul' => 'Kelola modul', 'sub' => 'Tambah, ubah, dan hapus modul beserta seluruh isinya.']) ?>
<div class="isi">
  <section class="card" style="margin-bottom:28px"><h2>Tambah modul baru</h2>
    <form method="post" action="<?= e(url('/kelola/modul')) ?>" class="form-sebaris"><?= csrf_field() ?>
      <div class="field" style="flex-basis:360px"><label for="mj">Judul modul</label><input class="input" id="mj" name="judul" required maxlength="200"></div>
      <button class="btn btn--utama" type="submit">Buat modul</button></form>
    <p class="hint" style="margin-bottom:0">Modul baru berstatus Terkunci sampai kamu menerbitkannya. Setelah dibuat, buka <b>Edit</b> untuk mengunggah slide dan mengisi praktikum, pengumpulan, LKPD, refleksi, serta soal remidi dan pengayaan. Simulasi dikelola di <a href="<?= e(url('/kelola/simulasi')) ?>">Kelola simulasi</a>.</p></section>
  <div class="tabel-bungkus"><table class="tabel tabel--tumpuk"><thead><tr><th scope="col">Modul</th><th scope="col">Jadwal</th><th scope="col">Status</th><th scope="col">Slide materi</th><th scope="col">Modul ajar</th><th scope="col"><span class="sr">Aksi</span></th></tr></thead><tbody>
  <?php foreach ($daftar as $m): ?><tr><td class="utama-sel"><b>Modul <?= (int) $m['nomor'] ?></b><br><span class="mini"><?= e($m['judul']) ?></span></td>
    <td data-label="Jadwal"><?= $m['tanggal'] ? e(hari_id($m['tanggal'], true)) : '<span class="mini">Belum dijadwalkan</span>' ?></td><td data-label="Status"><?= lencana($m['status']) ?></td>
    <?php $sl = Modul::berkasMateri($m); $aj = Modul::dokumen($m, 'ajar'); ?>
    <td data-label="Slide materi"><?= $m['materi_berkas'] ? '<span class="lencana lencana--ok">PDF diunggah</span>' : (!$sl['contoh'] ? '<span class="lencana lencana--ok">Slide bawaan</span>' : '<span class="mini">Belum ada slide</span>') ?></td>
    <td data-label="Modul ajar"><?php if ($aj['sumber']): ?><a href="<?= e(url('/kelola/modul/' . $m['id'] . '/dokumen/ajar')) ?>" target="_blank" rel="noopener">Buka PDF<span class="sr"> modul ajar Modul <?= (int) $m['nomor'] ?> (tab baru)</span></a><?php else: ?><span class="mini">Belum ada</span><?php endif; ?></td>
    <td class="aksi-sel"><a class="btn btn--garis btn--kecil" href="<?= e(url('/kelola/modul/' . $m['id'])) ?>">Edit<span class="sr"> Modul <?= (int) $m['nomor'] ?></span></a></td></tr><?php endforeach; ?>
  <?php if (!$daftar): ?><tr><td colspan="6" class="kosong">Belum ada modul. Tambahkan dengan formulir di atas.</td></tr><?php endif; ?>
</tbody></table></div></div>
