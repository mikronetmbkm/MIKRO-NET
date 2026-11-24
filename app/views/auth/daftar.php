<?php meta('judul', 'Daftar'); meta('kiri', 'Satu kode kelas, satu akun.'); ?>
<h2>Buat akun siswa</h2>
<p>Kamu butuh kode kelas dari gurumu.</p>
<form method="post" action="<?= e(url('/daftar')) ?>" novalidate>
  <?= csrf_field() ?>
  <div class="field"><label for="kode">Kode kelas</label><input class="input input--mono" id="kode" name="kode" placeholder="mis. TKJ1-7K2Q" value="<?= lama('kode', $kodeAwal) ?>" autocapitalize="characters" autocomplete="off" required aria-describedby="cek-kode" data-cek="<?= e(url('/daftar/cek-kode')) ?>"><div class="cek-kode" id="cek-kode" aria-live="polite"></div></div>
  <div class="field"><label for="nama">Nama lengkap</label><input class="input" id="nama" name="nama" autocomplete="name" value="<?= lama('nama') ?>" required></div>
  <div class="baris2">
    <div class="field"><label for="nis">NIS atau nomor absen</label><input class="input input--mono" id="nis" name="nis" value="<?= lama('nis') ?>" maxlength="30" required aria-describedby="nis-hint"><div class="hint" id="nis-hint">Ikuti arahan gurumu: pakai NIS atau nomor absen. Tidak boleh sama dengan teman sekelas.</div></div>
    <div class="field"><label for="email">Email</label><input class="input" id="email" name="email" type="email" autocomplete="email" value="<?= lama('email') ?>" required></div>
  </div>
  <div class="baris2">
    <div class="field"><label for="password">Password</label><input class="input" id="password" name="password" type="password" autocomplete="new-password" minlength="8" required aria-describedby="password-hint"><div class="hint" id="password-hint">Minimal 8 karakter.</div></div>
    <div class="field"><label for="password2">Ulangi password</label><input class="input" id="password2" name="password2" type="password" autocomplete="new-password" required></div>
  </div>
  <button class="btn btn--utama btn--penuh" type="submit">Buat akun</button>
</form>
<div class="kotak-tautan"><span>Sudah punya akun?</span><a href="<?= e(url('/masuk')) ?>">Masuk</a></div>
