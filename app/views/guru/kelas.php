<?php meta('judul', 'Kelas');
$totalSiswa = array_sum(array_map('intval', $jumlah)); ?>
<?= View::bagian('partials/kepala', ['konteks' => 'Ruang guru', 'judul' => 'Kelas', 'sub' => 'Bagikan kode kelas ke siswa. Mereka mendaftar sendiri dan langsung masuk ke kelasmu.', 'naik' => true]) ?>
<div class="isi isi--naik">
  <div class="statistik statistik--3">
    <div class="stat" data-muncul><span class="ikon-kotak ikon-kotak--navy"><?= icon('grid', 24) ?></span><div><b data-hitung="<?= count($daftar) ?>"><?= count($daftar) ?></b><small>kelas yang kamu kelola</small></div></div>
    <div class="stat" data-muncul><span class="ikon-kotak"><?= icon('users', 24) ?></span><div><b data-hitung="<?= $totalSiswa ?>"><?= $totalSiswa ?></b><small>siswa di semua kelas</small></div></div>
    <div class="stat stat--kuning" data-muncul><span class="ikon-kotak"><?= icon('clip', 24) ?></span><div><b>Penilaian</b><small><a href="<?= e(url('/guru/penilaian')) ?>" style="color:inherit;font-weight:700">Buka LKPD yang perlu dinilai</a></small></div></div>
  </div>
  <div class="kolom">
    <div>
      <?php if ($daftar): ?>
        <nav class="tab" aria-label="Pilih kelas"><?php foreach ($daftar as $k): ?><a href="<?= e(url('/guru/kelas?k=' . $k['id'])) ?>"<?= $pilih && (int) $pilih['id'] === (int) $k['id'] ? ' aria-current="page"' : '' ?>><?= e($k['nama']) ?>&nbsp;·&nbsp;<?= (int) $jumlah[$k['id']] ?> siswa</a><?php endforeach; ?></nav>
      <?php endif; ?>
      <?php if ($pilih): ?>
        <section class="card" data-muncul>
          <h2>Kode kelas <?= e($pilih['nama']) ?></h2>
          <div class="tombol-baris" style="margin:4px 0 12px"><span class="kode-besar" id="kode-kelas"><?= e($pilih['kode']) ?></span>
            <button class="btn btn--utama btn--kecil" type="button" data-salin="#kode-kelas">Salin kode</button>
            <form method="post" action="<?= e(url('/guru/kelas/' . $pilih['id'] . '/kode')) ?>" data-konfirmasi="Buat kode baru? Kode lama tidak bisa dipakai lagi untuk mendaftar." data-ya="Buat kode baru"><?= csrf_field() ?><button class="btn btn--garis btn--kecil" type="submit">Buat kode baru</button></form></div>
          <p class="hint" style="margin:0">Siswa mendaftar di <b><?= e(url('/daftar')) ?></b> memakai kode ini.</p>
        </section>
        <section class="card guru-kelas" data-muncul style="margin-top:18px">
          <h2>Guru pengajar</h2>
          <p class="mini" style="margin-top:-4px">Semua guru di sini bisa melihat kemajuan, menilai, dan membuka/menutup LKPD kelas ini.</p>
          <ul class="guru-daftar">
            <?php $saya = Auth::pengguna(); $sayaPembuat = (int) $pilih['guru_id'] === (int) $saya['id'] || $saya['peran'] === 'admin';
            foreach ($guruKelas as $g): $bisaHapus = !(int) $g['pembuat'] && ($sayaPembuat || (int) $g['id'] === (int) $saya['id']); ?>
              <li><span class="avatar" aria-hidden="true"><?= e(inisial($g['nama'])) ?></span>
                <span class="guru-nama"><b><?= e($g['nama']) ?><?= (int) $g['id'] === (int) $saya['id'] ? ' (kamu)' : '' ?></b><small><?= (int) $g['pembuat'] ? 'Pembuat kelas' : 'Guru pengajar' ?> · <?= e($g['email']) ?></small></span>
                <?php if ($bisaHapus): ?><form method="post" action="<?= e(url('/guru/kelas/' . $pilih['id'] . '/guru/hapus')) ?>" data-konfirmasi="<?= (int) $g['id'] === (int) $saya['id'] ? 'Berhenti mengajar ' . e($pilih['nama']) . '? Kelas ini tidak akan muncul lagi di akunmu.' : 'Hapus ' . e($g['nama']) . ' dari kelas ini?' ?>" data-ya="<?= (int) $g['id'] === (int) $saya['id'] ? 'Berhenti' : 'Hapus' ?>"><?= csrf_field() ?><input type="hidden" name="guru_id" value="<?= (int) $g['id'] ?>"><button class="btn btn--garis btn--kecil" type="submit"><?= (int) $g['id'] === (int) $saya['id'] ? 'Berhenti mengajar' : 'Hapus' ?><span class="sr"> <?= e($g['nama']) ?></span></button></form><?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
          <?php $sudahAda = array_map('intval', array_column($guruKelas, 'id')); $calon = array_values(array_filter($guruSemua, fn($g) => !in_array((int) $g['id'], $sudahAda, true))); ?>
          <?php if ($calon): ?>
            <form method="post" action="<?= e(url('/guru/kelas/' . $pilih['id'] . '/guru')) ?>" class="form-sebaris"><?= csrf_field() ?>
              <div class="field"><label for="guru_id">Tambah guru pengajar</label><select class="input" id="guru_id" name="guru_id" required><option value="">Pilih guru…</option><?php foreach ($calon as $g): ?><option value="<?= (int) $g['id'] ?>"><?= e($g['nama']) ?> (<?= e($g['email']) ?>)</option><?php endforeach; ?></select></div>
              <button class="btn btn--utama" type="submit">Tambahkan</button></form>
          <?php else: ?><p class="hint" style="margin:0">Belum ada guru lain yang bisa ditambahkan. Akun guru dibuat oleh admin.</p><?php endif; ?>
        </section>
        <div class="judul-bagian" style="margin-top:28px"><h2>Anggota <span class="mini">(<?= count($anggota) ?> siswa)</span></h2></div>
        <div class="tabel-bungkus" data-muncul><table class="tabel tabel--tumpuk"><thead><tr><th scope="col">Nama</th><th scope="col">NIS / absen</th><th scope="col">Email</th><th scope="col"><span class="sr">Aksi</span></th></tr></thead><tbody>
          <?php foreach ($anggota as $a): ?><tr><td class="utama-sel"><b><?= e($a['nama']) ?></b></td><td data-label="NIS / absen" class="mono"><?= e($a['nis']) ?></td><td data-label="Email"><?= e($a['email']) ?></td>
            <td class="aksi-sel"><form method="post" action="<?= e(url('/guru/kelas/' . $pilih['id'] . '/keluarkan')) ?>" data-konfirmasi="Keluarkan <?= e($a['nama']) ?> dari kelas ini?" data-ya="Keluarkan"><?= csrf_field() ?><input type="hidden" name="siswa_id" value="<?= (int) $a['id'] ?>"><button class="btn btn--bahaya btn--kecil" type="submit">Keluarkan<span class="sr"> <?= e($a['nama']) ?></span></button></form></td></tr><?php endforeach; ?>
          <?php if (!$anggota): ?><tr><td colspan="4" class="kosong">Belum ada siswa. Bagikan kode kelas di atas supaya siswa bisa mendaftar.</td></tr><?php endif; ?>
        </tbody></table></div>
      <?php else: ?><section class="card"><h2>Belum ada kelas</h2><p style="margin:0">Buat kelas pertamamu dengan formulir di samping. Kode kelasnya muncul di sini.</p></section><?php endif; ?>
    </div>
    <section class="card" data-muncul><h2>Buat kelas baru</h2>
      <form method="post" action="<?= e(url('/guru/kelas')) ?>"><?= csrf_field() ?>
        <div class="field"><label for="nama">Nama kelas</label><input class="input" id="nama" name="nama" placeholder="mis. XI TKJ 2" required maxlength="60"></div>
        <button class="btn btn--kuning btn--penuh" type="submit">Buat kelas</button></form></section>
  </div>
</div>
