<?php meta('judul', 'Pengguna'); ?>
<?= View::bagian('partials/kepala', ['konteks' => 'Pusat kendali', 'judul' => 'Pengguna', 'sub' => 'Tambah guru, ubah NIS siswa, atur ulang password, dan nonaktifkan akun.']) ?>
<div class="isi">
  <section class="card" style="margin-bottom:28px"><h2>Tambah akun guru</h2>
    <form method="post" action="<?= e(url('/admin/guru')) ?>" class="form-sebaris"><?= csrf_field() ?>
      <div class="field"><label for="gn">Nama</label><input class="input" id="gn" name="nama" required maxlength="100"></div>
      <div class="field"><label for="ge">Email</label><input class="input" id="ge" name="email" type="email" required></div>
      <button class="btn btn--utama" type="submit">Buat akun</button></form>
    <p class="hint" style="margin-bottom:0">Password sementara dibuat otomatis dan ditampilkan sekali. Guru wajib menggantinya saat pertama masuk.</p></section>
  <form class="penyaring" method="get" action="<?= e(url('/admin/pengguna')) ?>" role="search">
    <div><label class="label" for="peran">Peran</label><select class="input" id="peran" name="peran" onchange="this.form.submit()"><?php foreach (['' => 'Semua', 'siswa' => 'Siswa', 'guru' => 'Guru', 'admin' => 'Admin'] as $k => $v): ?><option value="<?= e($k) ?>" <?= $peran === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div><label class="label" for="q">Cari</label><input class="input" id="q" name="q" value="<?= e($cari) ?>" placeholder="nama, email, atau NIS"></div>
    <button class="btn btn--garis" type="submit">Cari</button></form>
  <div class="tabel-bungkus"><table class="tabel tabel--tumpuk"><thead><tr><th scope="col">Nama</th><th scope="col">Peran</th><th scope="col">Kelas</th><th scope="col">Status</th><th scope="col"><span class="sr">Aksi</span></th></tr></thead><tbody>
    <?php foreach ($daftar as $u): ?>
      <tr><td class="utama-sel"><b><?= e($u['nama']) ?></b><br><span class="mini"><?= e($u['email']) ?><?= $u['nis'] ? ' · <span class="mono">' . e($u['nis']) . '</span>' : '' ?></span></td>
        <td data-label="Peran"><?= e(ucfirst($u['peran'])) ?></td><td data-label="Kelas"><?= e($u['kelas_nama'] ?? '–') ?></td>
        <td data-label="Status"><?= (int) $u['aktif'] === 1 ? '<span class="lencana lencana--ok">Aktif</span>' : '<span class="lencana lencana--grey">Nonaktif</span>' ?></td>
        <td class="aksi-sel">
          <?php if ($u['peran'] === 'siswa'): ?><details class="ubah-siswa"><summary class="btn btn--garis btn--kecil">Ubah nama/NIS<span class="sr"> <?= e($u['nama']) ?></span></summary>
            <form method="post" action="<?= e(url('/admin/pengguna/' . $u['id'] . '/siswa')) ?>"><?= csrf_field() ?><input type="hidden" name="q" value="<?= e($cari) ?>">
              <div class="field"><label for="un-<?= (int) $u['id'] ?>">Nama</label><input class="input" id="un-<?= (int) $u['id'] ?>" name="nama" value="<?= e($u['nama']) ?>" required maxlength="100"></div>
              <div class="field"><label for="ui-<?= (int) $u['id'] ?>">NIS / nomor absen</label><input class="input input--mono" id="ui-<?= (int) $u['id'] ?>" name="nis" value="<?= e((string) $u['nis']) ?>" required maxlength="30"></div>
              <button class="btn btn--utama btn--kecil" type="submit">Simpan</button></form></details><?php endif; ?>
          <form method="post" action="<?= e(url('/admin/pengguna/' . $u['id'] . '/reset')) ?>" data-konfirmasi="Atur ulang password akun ini?"><?= csrf_field() ?><button class="btn btn--garis btn--kecil" type="submit">Reset password<span class="sr"> <?= e($u['nama']) ?></span></button></form>
          <?php if ((int) $u['id'] !== Auth::id()): ?><form method="post" action="<?= e(url('/admin/pengguna/' . $u['id'] . '/aktif')) ?>"><?= csrf_field() ?><button class="btn btn--bahaya btn--kecil" type="submit"><?= (int) $u['aktif'] === 1 ? 'Nonaktifkan' : 'Aktifkan' ?><span class="sr"> <?= e($u['nama']) ?></span></button></form><?php endif; ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$daftar): ?><tr><td colspan="5" class="kosong">Tidak ada akun yang cocok dengan pencarian ini. Coba kata kunci lain atau pilih peran Semua.</td></tr><?php endif; ?>
  </tbody></table></div>
</div>
