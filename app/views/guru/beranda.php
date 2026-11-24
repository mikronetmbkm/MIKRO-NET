<?php meta('judul', 'Beranda guru');
$jam = (int) date('G');
$sapa = $jam < 4 ? 'Selamat malam' : ($jam < 11 ? 'Selamat pagi' : ($jam < 15 ? 'Selamat siang' : ($jam < 18 ? 'Selamat sore' : 'Selamat malam')));
$totalSiswa = array_sum(array_column($kartu, 'siswa'));
$totalPerlu = array_sum(array_column($kartu, 'perluPraktik')) + array_sum(array_column($kartu, 'perluLkpd')) + array_sum(array_column($kartu, 'perluTindak'));
$namaDepan = explode(' ', trim($pengguna['nama']))[0]; ?>
<?= View::bagian('partials/kepala', ['konteks' => 'Ruang guru', 'judul' => $sapa . ', ' . $namaDepan, 'sub' => $aktif ? 'Modul minggu ini: Modul ' . $aktif['nomor'] . ' · ' . $aktif['judul'] . ($aktif['tanggal'] ? ' (' . hari_id($aktif['tanggal'], true) . ')' : '') : 'Belum ada modul yang terbit.', 'naik' => true]) ?>
<div class="isi isi--naik">
  <div class="statistik statistik--3">
    <a class="stat stat--tautan" href="<?= e(url('/guru/kelas')) ?>" data-muncul><span class="ikon-kotak ikon-kotak--navy"><?= icon('users', 24) ?></span><div><b data-hitung="<?= $totalSiswa ?>"><?= $totalSiswa ?></b><small>siswa di <?= count($kartu) ?> kelas</small></div></a>
    <a class="stat stat--tautan<?= $totalPerlu ? ' stat--kuning' : '' ?>" href="<?= e(url('/guru/penilaian')) ?>" data-muncul><span class="ikon-kotak ikon-kotak--kuning"><?= icon('clip', 24) ?></span><div><b data-hitung="<?= $totalPerlu ?>"><?= $totalPerlu ?></b><small><?= $totalPerlu ? 'tugas menunggu dinilai' : 'semua tugas sudah dinilai' ?></small></div></a>
    <a class="stat stat--tautan" href="<?= e(url('/diskusi?status=belum')) ?>" data-muncul><span class="ikon-kotak ikon-kotak--hijau"><?= icon('chat', 24) ?></span><div><b data-hitung="<?= (int) $diskusiBelum ?>"><?= (int) $diskusiBelum ?></b><small><?= $diskusiBelum ? 'pertanyaan siswa belum dijawab' : 'semua pertanyaan diskusi sudah dijawab' ?></small></div></a>
  </div>

  <?php if (!$kartu): ?>
    <section class="card kosong-besar" data-muncul>
      <span class="ikon-kotak ikon-kotak--navy"><?= icon('users', 28) ?></span>
      <h2>Belum ada kelas</h2>
      <p>Buat kelas, lalu bagikan kodenya ke siswa. Kalau kelasnya sudah dibuat guru lain, minta guru itu menambahkanmu sebagai guru pengajar.</p>
      <a class="btn btn--kuning" href="<?= e(url('/guru/kelas')) ?>">Buat kelas</a>
    </section>
  <?php else: ?>
    <div class="judul-bagian"><h2>Kelasmu</h2><a href="<?= e(url('/guru/kelas')) ?>">Kelola kelas</a></div>
    <div class="kelas-grid">
      <?php foreach ($kartu as $c): $k = $c['k']; $q = '?k=' . $k['id'] . ($aktif ? '&m=' . $aktif['id'] : ''); ?>
        <section class="card kelas-kartu" data-muncul>
          <div class="kelas-kartu-atas">
            <div><h3><?= e($k['nama']) ?></h3><p class="mini"><?= (int) $c['siswa'] ?> siswa · kode <span class="mono"><?= e($k['kode']) ?></span></p></div>
            <?php if (count($k['guru'] ?? []) > 1): ?><span class="lencana lencana--blue" title="<?= e($k['guru_nama_semua']) ?>"><?= count($k['guru']) ?> guru</span><?php endif; ?>
          </div>
          <?php if ($aktif): ?>
            <div class="kelas-kartu-kemajuan">
              <div class="baris"><span class="mini">Kemajuan Modul <?= (int) $aktif['nomor'] ?></span><b><?= (int) $c['rata'] ?>%</b></div>
              <div class="bar" aria-hidden="true"><i style="width:<?= (int) $c['rata'] ?>%"></i></div>
              <p class="mini" style="margin:6px 0 0"><?= (int) $c['selesai'] ?> dari <?= (int) $c['siswa'] ?> siswa menyelesaikan semua langkah</p>
            </div>
          <?php endif; ?>
          <ul class="kelas-kartu-tugas">
            <li><span><?= icon('upload', 18) ?> Pengumpulan praktik perlu dinilai</span><b class="<?= $c['perluPraktik'] ? 'ada' : '' ?>"><?= (int) $c['perluPraktik'] ?></b></li>
            <li><span><?= icon('edit', 18) ?> Uraian LKPD perlu dinilai</span><b class="<?= $c['perluLkpd'] ? 'ada' : '' ?>"><?= (int) $c['perluLkpd'] ?></b></li>
            <?php if ($c['perluTindak']): ?><li><span><?= icon('chart', 18) ?> <a href="<?= e(url('/guru/analisis' . $q)) ?>">Remidi/pengayaan perlu dinilai</a></span><b class="ada"><?= (int) $c['perluTindak'] ?></b></li><?php endif; ?>
          </ul>
          <?php if ($aktif && $adaLembar): ?><?= View::bagian('partials/lkpd-akses', ['kelas' => $k, 'm' => $aktif, 'buka' => $c['lkpdBuka'], 'kembali' => '/guru', 'ringkas' => true]) ?><?php endif; ?>
          <div class="tombol-baris">
            <a class="btn btn--utama btn--kecil" href="<?= e(url('/guru/kemajuan' . $q)) ?>">Kemajuan</a>
            <a class="btn <?= $c['perluPraktik'] + $c['perluLkpd'] ? 'btn--kuning' : 'btn--garis' ?> btn--kecil" href="<?= e(url('/guru/penilaian' . $q)) ?>">Penilaian</a>
            <a class="btn btn--garis btn--kecil" href="<?= e(url('/guru/analisis' . $q)) ?>">Analisis</a>
            <a class="btn btn--garis btn--kecil" href="<?= e(url('/guru/refleksi' . $q)) ?>">Refleksi</a>
            <a class="btn btn--garis btn--kecil" href="<?= e(url('/kelola/kuis/hasil?k=' . $k['id'])) ?>">Nilai kuis</a>
          </div>
        </section>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="judul-bagian" style="margin-top:30px"><h2>Pintasan</h2></div>
  <div class="pintasan">
    <?php foreach ($kuis as $kz): ?>
      <a class="card pintasan-item" href="<?= e(url('/kelola/kuis')) ?>"><span class="ikon-kotak"><?= icon('edit', 22) ?></span><div><b>Atur <?= $kz['jenis'] === 'pretest' ? 'pretest' : 'post-test' ?></b><span class="mini">Status: <?= e(['draf' => 'draf (belum dibuka)', 'dibuka' => 'dibuka untuk siswa', 'ditutup' => 'ditutup'][$kz['status']] ?? $kz['status']) ?></span></div></a>
    <?php endforeach; ?>
    <a class="card pintasan-item" href="<?= e(url('/kelola/kuis/hasil')) ?>"><span class="ikon-kotak ikon-kotak--hijau"><?= icon('award', 22) ?></span><div><b>Nilai pretest &amp; post-test</b><span class="mini">Skor per siswa, N-gain, unduh CSV</span></div></a>
    <a class="card pintasan-item" href="<?= e(url('/kelola/modul')) ?>"><span class="ikon-kotak"><?= icon('layers', 22) ?></span><div><b>Kelola modul</b><span class="mini">Buka modul, unggah PDF materi</span></div></a>
    <a class="card pintasan-item" href="<?= e(url('/guru/kelas')) ?>"><span class="ikon-kotak"><?= icon('users', 22) ?></span><div><b>Kode kelas &amp; guru</b><span class="mini">Bagikan kode, tambah guru pengajar</span></div></a>
  </div>
</div>
