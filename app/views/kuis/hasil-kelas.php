<?php meta('judul', 'Hasil kuis');
$f = fn($v) => $v === null ? '–' : number_format((float) $v, 0);
$g = fn($v) => $v === null ? '–' : number_format((float) $v, 2, ',', ''); ?>
<?= View::bagian('partials/kepala', ['konteks' => 'Guru & Admin · Kuis', 'judul' => 'Hasil pretest dan post-test', 'sub' => 'Bandingkan skor sebelum dan sesudah belajar. N-gain menunjukkan seberapa banyak ruang untuk naik yang benar-benar terisi.', 'naik' => true]) ?>
<div class="isi isi--naik">
  <?php if (in_array($pengguna['peran'], ['guru', 'admin'], true)): ?><nav class="tab tab--atas" aria-label="Jenis nilai"><a href="<?= e(url('/guru/penilaian' . ($pilih ? '?k=' . $pilih['id'] : ''))) ?>">Praktikum &amp; LKPD</a><a href="<?= e(url('/kelola/kuis/hasil' . ($pilih ? '?k=' . $pilih['id'] : ''))) ?>" aria-current="page">Pretest &amp; post-test</a></nav><?php endif; ?>
  <section class="card" style="margin-bottom:22px">
    <form class="penyaring" method="get" action="<?= e(url('/kelola/kuis/hasil')) ?>">
      <div><label class="label" for="k">Kelas</label><select class="input" id="k" name="k" onchange="this.form.submit()"><?php foreach ($daftar as $k): ?><option value="<?= (int) $k['id'] ?>" <?= $pilih && (int) $k['id'] === (int) $pilih['id'] ? 'selected' : '' ?>><?= e($k['nama']) ?></option><?php endforeach; ?></select></div>
      <noscript><button class="btn btn--utama" type="submit">Tampilkan</button></noscript>
      <?php if ($pilih): ?><a class="btn btn--garis" href="<?= e(url('/kelola/kuis/hasil.csv?k=' . (int) $pilih['id'])) ?>">Unduh CSV</a><?php endif; ?>
    </form>
  </section>
  <?php if (!$pilih): ?><section class="card"><h2>Belum ada kelas</h2><p style="margin:0">Buat kelas dulu di menu Kelas, lalu bagikan kodenya ke siswa.</p></section><?php else: ?>
  <div class="statistik">
    <div class="stat" data-muncul><span class="ikon-kotak"><?= icon('edit', 24) ?></span><div><b><?= $f($rekap['rata_pre']) ?></b><small>rata-rata pretest (<?= (int) $rekap['n_pre'] ?> siswa)</small></div></div>
    <div class="stat" data-muncul><span class="ikon-kotak ikon-kotak--kuning"><?= icon('edit', 24) ?></span><div><b><?= $f($rekap['rata_post']) ?></b><small>rata-rata post-test (<?= (int) $rekap['n_post'] ?> siswa)</small></div></div>
    <div class="stat" data-muncul><span class="ikon-kotak ikon-kotak--hijau"><?= icon('award', 24) ?></span><div><b><?= $g($rekap['rata_ngain']) ?></b><small>rata-rata N-gain · <?= e(Kuis::kategoriNgain($rekap['rata_ngain'])) ?> (<?= (int) $rekap['n_ngain'] ?> siswa)</small></div></div>
  </div>

  <?php if ($rekap['per_modul']): ?>
  <section class="card" style="margin-bottom:22px" data-muncul aria-labelledby="jd-modul"><h2 id="jd-modul">Ketuntasan per modul (persen benar)</h2>
    <p class="per-legenda"><span><i></i>Pretest</span><span><i class="k"></i>Post-test</span></p>
    <ul class="per-modul per-modul--dua">
      <?php foreach ($rekap['per_modul'] as $m => $x): ?>
        <li><span><?= e(label_modul((int) $m)) ?></span>
          <div class="pasang" aria-hidden="true"><div class="bar"><i style="width:<?= (int) round($x['pre'] ?? 0) ?>%"></i></div><div class="bar bar--kuning"><i style="width:<?= (int) round($x['post'] ?? 0) ?>%"></i></div></div>
          <span class="mono mini">Pre <?= $f($x['pre']) ?> · Post <?= $f($x['post']) ?></span></li>
      <?php endforeach; ?>
    </ul></section>
  <?php endif; ?>

  <div class="tabel-bungkus" data-muncul><table class="tabel tabel--tumpuk"><thead><tr><th scope="col">Siswa</th><th scope="col">Pretest</th><th scope="col">Post-test</th><th scope="col">N-gain</th><th scope="col"><span class="sr">Aksi</span></th></tr></thead><tbody>
    <?php foreach ($rekap['baris'] as $b): ?>
      <tr><td class="utama-sel"><b><?= e($b['nama']) ?></b><br><span class="mini mono"><?= e($b['nis']) ?></span></td>
        <td data-label="Pretest"><?= $b['pre'] !== null ? '<span class="nilai-angka">' . (int) $b['pre'] . '</span>' : ($b['pre_id'] ? lencana('berjalan') : '<span class="mini">Belum</span>') ?></td>
        <td data-label="Post-test"><?= $b['post'] !== null ? '<span class="nilai-angka">' . (int) $b['post'] . '</span>' : ($b['post_id'] ? lencana('berjalan') : '<span class="mini">Belum</span>') ?></td>
        <td data-label="N-gain"><?= $b['ngain'] !== null ? '<span class="mono">' . $g($b['ngain']) . '</span> <span class="mini">' . e(Kuis::kategoriNgain($b['ngain'])) . '</span>' : '<span class="mini">–</span>' ?></td>
        <td class="aksi-sel"><?php foreach (['pre' => 'pretest', 'post' => 'post-test'] as $sisi => $nama): if ($b[$sisi . '_id']): ?>
          <form method="post" action="<?= e(url('/kelola/kuis/percobaan/' . $b[$sisi . '_id'] . '/reset')) ?>" data-konfirmasi="Reset <?= e($nama) ?> milik <?= e($b['nama']) ?>? Jawaban dan skornya dihapus, lalu ia bisa mengerjakan dari awal." data-ya="Reset"><?= csrf_field() ?><input type="hidden" name="k" value="<?= (int) $pilih['id'] ?>"><button class="btn btn--garis btn--kecil" type="submit">Reset <?= e($nama) ?><span class="sr"> <?= e($b['nama']) ?></span></button></form>
        <?php endif; endforeach; ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$rekap['baris']): ?><tr><td colspan="5" class="kosong">Belum ada siswa di kelas ini.</td></tr><?php endif; ?>
  </tbody></table></div>
  <p class="hint">N-gain = (post − pre) / (100 − pre). Kategori: Tinggi ≥ 0,70 · Sedang 0,30 sampai 0,69 · Rendah &lt; 0,30. Siswa yang pretest-nya 100 tidak dihitung karena tidak ada ruang naik.</p>
  <?php endif; ?>
</div>
