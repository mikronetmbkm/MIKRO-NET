<?php meta('judul', 'Analisis nilai');
$q = $kelas && $m ? '?k=' . $kelas['id'] . '&m=' . $m['id'] : '';
$huruf = 'abcde';
$warnaPersen = fn(int $p) => $p < Nilai::BATAS_LEMAH ? 'rendah' : ($p < 80 ? 'sedang' : 'tinggi'); ?>
<?= View::bagian('partials/kepala', ['konteks' => 'Ruang guru', 'judul' => 'Analisis nilai', 'sub' => 'Ketuntasan per siswa, siapa yang perlu remidi atau pengayaan, dan materi yang masih lemah.', 'naik' => true]) ?>
<div class="isi isi--naik">
  <section class="card" style="margin-bottom:22px">
    <?= View::bagian('partials/pemilih-guru', ['aksi' => '/guru/analisis', 'daftar' => $daftar, 'modul' => $modul, 'kelas' => $kelas, 'm' => $m]) ?>
  </section>
  <?php if (!$kelas): ?><section class="card"><h2>Belum ada kelas</h2><p style="margin:0">Buat kelas dulu di menu <a href="<?= e(url('/guru/kelas')) ?>">Kelas</a>.</p></section>
  <?php elseif (!$m): ?><section class="card"><h2>Belum ada modul terbit</h2><p style="margin:0">Terbitkan modul di menu <a href="<?= e(url('/kelola/modul')) ?>">Modul</a>.</p></section>
  <?php else: $r = $a['ringkas']; $at = $a['aturan']; ?>
    <div class="statistik statistik--4" data-muncul>
      <div class="stat"><span class="ikon-kotak ikon-kotak--hijau"><?= icon('check', 24) ?></span><div><b><?= (int) $r['tuntas'] ?><small class="stat-dari">/<?= (int) $r['siswa'] ?></small></b><small>siswa tuntas (KKM <?= (int) $at['kkm'] ?>)</small></div></div>
      <div class="stat<?= $r['remidi'] ? ' stat--bahaya' : '' ?>"><span class="ikon-kotak ikon-kotak--merah"><?= icon('edit', 24) ?></span><div><b><?= (int) $r['remidi'] ?></b><small>perlu perbaikan (remidi)</small></div></div>
      <div class="stat"><span class="ikon-kotak"><?= icon('clock', 24) ?></span><div><b><?= (int) $r['belum'] ?></b><small>nilai belum lengkap</small></div></div>
      <div class="stat"><span class="ikon-kotak ikon-kotak--navy"><?= icon('chart', 24) ?></span><div><b><?= $r['rata'] ?? '–' ?></b><small><?= $r['rata'] === null ? 'rata-rata muncul setelah ada nilai lengkap' : 'rata-rata nilai akhir' . ($r['terendah'] !== $r['tertinggi'] ? ' · rentang ' . $r['terendah'] . '–' . $r['tertinggi'] : '') ?></small></div></div>
    </div>

    <details class="card aturan-nilai" <?= isset($_GET['atur']) ? 'open' : '' ?>>
      <summary><span><b>Aturan nilai <?= e($kelas['nama']) ?></b><span class="mini">KKM <?= (int) $at['kkm'] ?> · praktik <?= (int) $at['praktik'] ?>% · LKPD <?= (int) $at['lkpd'] ?>% · simulasi <?= (int) $at['simulasi'] ?>%</span></span><span class="btn btn--garis btn--kecil" aria-hidden="true">Ubah</span></summary>
      <form method="post" action="<?= e(url('/guru/kelas/' . $kelas['id'] . '/nilai')) ?>" class="aturan-form" id="form-aturan"><?= csrf_field() ?>
        <div class="field"><label for="kkm">KKM</label><input class="input input--mono" id="kkm" name="kkm" type="number" min="1" max="100" inputmode="numeric" value="<?= (int) $at['kkm'] ?>" required></div>
        <div class="field"><label for="bp">Bobot praktik (%)</label><input class="input input--mono" id="bp" name="bobot_praktik" type="number" min="0" max="100" inputmode="numeric" value="<?= (int) $at['praktik'] ?>" required></div>
        <div class="field"><label for="bl">Bobot LKPD (%)</label><input class="input input--mono" id="bl" name="bobot_lkpd" type="number" min="0" max="100" inputmode="numeric" value="<?= (int) $at['lkpd'] ?>" required></div>
        <div class="field"><label for="bs">Bobot simulasi (%)</label><input class="input input--mono" id="bs" name="bobot_simulasi" type="number" min="0" max="100" inputmode="numeric" value="<?= (int) $at['simulasi'] ?>" required></div>
        <p class="mini aturan-ket" id="aturan-jumlah" aria-live="polite">Jumlah bobot harus 100%. Berlaku untuk semua modul di kelas ini. Nilai remidi dihitung paling tinggi sama dengan KKM.</p>
        <button class="btn btn--utama" type="submit">Simpan aturan</button>
      </form>
    </details>

    <?php if ($menunggu): ?>
      <section class="card" style="margin:22px 0 0"><h2>Remidi dan pengayaan menunggu dinilai</h2>
        <ul class="daftar-tugas"><?php foreach ($menunggu as $t): ?>
          <li><span><b><?= e($t['nama']) ?></b> <span class="mini">· <?= e(Tindak::JENIS[$t['jenis']] ?? $t['jenis']) ?> · dikumpulkan <?= e(hari_id((string) $t['dikumpulkan_pada'], true)) ?></span></span><a class="btn btn--kuning btn--kecil" href="<?= e(url('/guru/tindak/' . $t['id'])) ?>">Nilai uraian</a></li>
        <?php endforeach; ?></ul>
      </section>
    <?php endif; ?>

    <div class="judul-bagian" style="margin-top:26px"><h2><?= e($kelas['nama']) ?> <span class="mini">· Modul <?= (int) $m['nomor'] ?></span></h2><a href="<?= e(url('/guru/analisis/unduh' . $q)) ?>">Unduh rekap (CSV)</a></div>
    <div class="tabel-bungkus" data-muncul><table class="tabel tabel--tumpuk tabel-analisis"><thead><tr>
      <th scope="col">Siswa</th><th scope="col" class="tengah">Praktik</th><th scope="col" class="tengah">LKPD</th><th scope="col" class="tengah">Simulasi</th>
      <th scope="col" class="tengah">Nilai</th><th scope="col">Status dan tindak lanjut</th><th scope="col">Perlu diperkuat</th></tr></thead><tbody>
    <?php foreach ($a['baris'] as $b): $s = $b['siswa']; $n = $b['nilai']; [$stLabel, $stWarna] = Nilai::STATUS[$n['status']]; ?>
      <tr>
        <td class="utama-sel"><b><?= e($s['nama']) ?></b><br><span class="mini mono"><?= e($s['nis']) ?></span></td>
        <?php foreach (['praktik' => 'Praktik', 'lkpd' => 'LKPD', 'simulasi' => 'Simulasi'] as $kk => $lbl): $x = $n['komponen'][$kk] ?? null; ?>
          <td data-label="<?= $lbl ?>" class="tengah"><?php if (!$x): ?><span class="mini">–</span><?php elseif ($x['nilai'] === null): ?><span class="mini" title="<?= e($x['ket']) ?>"><?= e($x['ket'] === 'Belum dikumpulkan' || $x['ket'] === 'Belum dimainkan' ? 'belum' : 'menunggu') ?></span><?php else: ?><span class="mono"><?= (int) $x['nilai'] ?></span><?php if (!$x['bobot']): ?><span class="mini"> (0%)</span><?php endif; ?><?php endif; ?></td>
        <?php endforeach; ?>
        <td data-label="Nilai" class="tengah"><?php if ($n['akhir'] === null): ?><span class="mini">–</span><?php else: ?><span class="nilai-angka<?= $n['tuntas'] ? '' : ' nilai-angka--kurang' ?>"><?= (int) $n['akhir'] ?></span><?php if ($n['akhir'] !== $n['awal']): ?><br><span class="mini">awal <?= (int) $n['awal'] ?></span><?php endif; ?><?php endif; ?></td>
        <td data-label="Status">
          <span class="lencana lencana--<?= e($stWarna) ?>"><?= e($stLabel) ?></span>
          <?php if ($n['status'] === 'belum'): ?>
          <?php elseif ($n['rekomendasi'] === 'remidi'): ?><br><span class="mini">Harus melakukan perbaikan.
            <?php $t = $n['remidi']; if (!$n['adaRemidi']): ?>Soal remidi belum ada, <a href="<?= e(url('/kelola/modul/' . $m['id'] . '/isi/remidi')) ?>">buat soal</a>.
            <?php elseif (!$t): ?>Belum mulai remidi.
            <?php elseif ($t['status'] !== 'dikumpulkan'): ?>Sedang mengerjakan remidi.
            <?php else: ?><a href="<?= e(url('/guru/tindak/' . $t['id'])) ?>"><?= $t['nilai'] === null ? 'Nilai uraian remidi' : 'Nilai remidi ' . (int) $t['nilai'] ?></a><?php endif; ?></span>
          <?php else: ?><br><span class="mini">Siap pengayaan.
            <?php $t = $n['pengayaan']; if (!$n['adaPengayaan']): ?>Soal pengayaan belum ada, <a href="<?= e(url('/kelola/modul/' . $m['id'] . '/isi/pengayaan')) ?>">buat soal</a>.
            <?php elseif ($t && $t['status'] === 'dikumpulkan'): ?><a href="<?= e(url('/guru/tindak/' . $t['id'])) ?>"><?= $t['nilai'] === null ? 'Nilai uraian pengayaan' : 'Pengayaan ' . (int) $t['nilai'] ?></a>
            <?php elseif ($t): ?>Sedang mengerjakan pengayaan.<?php else: ?>Belum mulai pengayaan.<?php endif; ?></span>
          <?php endif; ?>
        </td>
        <td data-label="Perlu diperkuat"><?php $lemah = $b['kelemahan']['lemah']; if (!$lemah): ?><span class="mini"><?= $b['kelemahan']['topik'] || $b['kelemahan']['rubrik'] ? 'Tidak ada topik di bawah ' . Nilai::BATAS_LEMAH . '%' : 'Belum ada data' ?></span>
          <?php else: ?><ul class="chip-daftar"><?php foreach (array_slice($lemah, 0, 3) as $l): ?><li class="chip chip--lemah" title="<?= e($l['asal']) ?>"><?= e($l['label']) ?> <b><?= (int) $l['persen'] ?>%</b></li><?php endforeach; ?></ul>
            <?php if (count($lemah) > 3): ?><details class="lemah-lain"><summary class="mini"><?= count($lemah) - 3 ?> lainnya</summary><ul class="chip-daftar"><?php foreach (array_slice($lemah, 3) as $l): ?><li class="chip chip--lemah"><?= e($l['label']) ?> <b><?= (int) $l['persen'] ?>%</b></li><?php endforeach; ?></ul></details><?php endif; ?>
          <?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$a['baris']): ?><tr><td colspan="7" class="kosong">Belum ada siswa di kelas ini.</td></tr><?php endif; ?>
    </tbody></table></div>
    <p class="mini keterangan-status">Nilai = rata-rata berbobot praktik, LKPD, dan simulasi. Status muncul setelah semua komponen bernilai. "Perlu diperkuat" = topik soal LKPD atau kriteria rubrik praktik dengan capaian di bawah <?= Nilai::BATAS_LEMAH ?>%.</p>

    <div class="kolom-dua analisis-kelas">
      <section class="card"><h2>Topik yang perlu diulang</h2>
        <p class="mini">Capaian kelas per topik soal LKPD (pilihan ganda dan uraian yang sudah dinilai).</p>
        <?php if (!$a['topik']): ?><p class="mini" style="margin:0">Belum ada LKPD yang dikumpulkan.</p><?php else: ?>
        <ul class="daftar-persen"><?php foreach ($a['topik'] as $k => $v): ?>
          <li><div class="baris"><span><?= e($k) ?></span><b class="mono"><?= (int) $v['persen'] ?>%</b></div><div class="bar bar--<?= $warnaPersen((int) $v['persen']) ?>" aria-hidden="true"><i style="width:<?= (int) $v['persen'] ?>%"></i></div><?php if ($v['lemah']): ?><span class="mini"><?= (int) $v['lemah'] ?> dari <?= (int) $v['siswa'] ?> siswa di bawah <?= Nilai::BATAS_LEMAH ?>%</span><?php endif; ?></li>
        <?php endforeach; ?></ul><?php endif; ?>
      </section>
      <section class="card"><h2>Kriteria praktik</h2>
        <p class="mini">Rata-rata skor rubrik pengumpulan yang sudah dinilai.</p>
        <?php if (!$a['rubrik']): ?><p class="mini" style="margin:0">Belum ada pengumpulan yang dinilai.</p><?php else: ?>
        <ul class="daftar-persen"><?php foreach ($a['rubrik'] as $k => $v): ?>
          <li><div class="baris"><span><?= e($k) ?></span><b class="mono"><?= (int) $v['persen'] ?>%</b></div><div class="bar bar--<?= $warnaPersen((int) $v['persen']) ?>" aria-hidden="true"><i style="width:<?= (int) $v['persen'] ?>%"></i></div><?php if ($v['lemah']): ?><span class="mini"><?= (int) $v['lemah'] ?> dari <?= (int) $v['siswa'] ?> siswa di bawah <?= Nilai::BATAS_LEMAH ?>%</span><?php endif; ?></li>
        <?php endforeach; ?></ul><?php endif; ?>
      </section>
    </div>

    <?php if ($a['soal']): ?>
    <section class="card" style="margin-top:22px"><h2>Soal pilihan ganda paling sulit</h2>
      <p class="mini">Diurutkan dari persentase benar terendah. Pilihan salah yang paling banyak dipilih menunjukkan miskonsepsi yang perlu dibahas ulang.</p>
      <ol class="soal-sulit"><?php foreach (array_slice($a['soal'], 0, 5) as $sl): ?>
        <li><div class="baris"><span><b>No. <?= (int) $sl['no'] ?></b> · <?= e($sl['topik']) ?></span><b class="mono"><?= (int) $sl['benar'] ?>/<?= (int) $sl['n'] ?> benar (<?= (int) $sl['persen'] ?>%)</b></div>
          <p><?= e($sl['soal']) ?></p>
          <p class="mini">Kunci <b><?= $huruf[$sl['kunci']] ?>.</b> <?= e($sl['opsi'][$sl['kunci']] ?? '') ?><?php if ($sl['salah']): ?><br>Paling banyak salah pilih <b><?= $huruf[$sl['salah']['opsi']] ?>.</b> <?= e($sl['opsi'][$sl['salah']['opsi']] ?? '') ?> (<?= (int) $sl['salah']['jumlah'] ?> siswa)<?php endif; ?></p></li>
      <?php endforeach; ?></ol>
    </section>
    <?php endif; ?>
  <?php endif; ?>
</div>
<script>
(function () {
  var f = document.getElementById('form-aturan'); if (!f) return;
  var ket = document.getElementById('aturan-jumlah'), awal = ket.textContent;
  function cek() {
    var t = ['bp', 'bl', 'bs'].reduce(function (a, id) { return a + (parseInt(document.getElementById(id).value, 10) || 0); }, 0);
    ket.textContent = t === 100 ? awal : 'Jumlah bobot sekarang ' + t + '%. Harus 100%.';
    ket.classList.toggle('aturan-ket--salah', t !== 100);
  }
  f.addEventListener('input', cek);
})();
</script>
