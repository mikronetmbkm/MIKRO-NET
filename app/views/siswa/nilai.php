<?php meta('judul', 'Nilai'); ?>
<?= View::bagian('partials/kepala', ['konteks' => 'Hasil belajar', 'judul' => 'Nilai', 'sub' => 'Skor pretest dan post-test, nilai praktik dan LKPD dari gurumu, skor simulasimu, dan nilai akhir tiap modul.']) ?>
<div class="isi">
  <?php if ($kuis): ?>
  <section class="card" style="margin-bottom:22px" data-muncul aria-labelledby="jd-kuis">
    <div class="judul-bagian"><h2 id="jd-kuis">Pretest dan post-test</h2><a href="<?= e(url('/kuis')) ?>">Buka kuis</a></div>
    <dl class="kuis-meta kuis-meta--besar">
      <?php foreach ($kuis as $jenis => $x): ?>
        <div><dt><?= $jenis === 'pretest' ? 'Pretest' : 'Post-test' ?></dt><dd class="mono"><?= $x['skor'] !== null ? (int) $x['skor'] : '–' ?></dd><?php if ($x['skor'] === null): ?><span class="mini"><?= e(['berjalan' => 'Sedang dikerjakan', 'siap' => 'Sudah dibuka', 'ditutup' => 'Ditutup', 'belum' => 'Belum dibuka'][$x['status']] ?? '') ?></span><?php endif; ?></div>
      <?php endforeach; ?>
      <div><dt>Peningkatan</dt><dd class="mono"><?= $ngain !== null ? number_format($ngain, 2, ',', '') : '–' ?></dd><span class="mini"><?= $ngain !== null ? e(Kuis::kategoriNgain($ngain)) : 'Muncul setelah keduanya selesai' ?></span></div>
    </dl>
  </section>
  <?php endif; ?>
  <div class="tabel-bungkus"><table class="tabel tabel--tumpuk">
    <thead><tr><th scope="col">Modul</th><th scope="col">Pengumpulan</th><th scope="col">Nilai praktik</th><th scope="col">LKPD</th><th scope="col">Simulasi</th><th scope="col">Nilai modul</th></tr></thead><tbody>
    <?php foreach ($baris as $b): $l = $b['lkpd']; $pr = $b['progres']; ?>
      <tr>
        <td class="utama-sel"><b>Modul <?= (int) $b['m']['nomor'] ?></b><br><span class="mini"><?= e($b['m']['judul']) ?></span></td>
        <td data-label="Pengumpulan"><?= $l ? lencana($l['status']) : lencana('belum') ?></td>
        <td data-label="Nilai"><?php if ($l && $l['nilai'] !== null): ?><span><span class="nilai-angka"><?= (int) $l['nilai'] ?></span> <span class="mini"><?= e(kategori_nilai((int) $l['nilai'])) ?></span>
          <?php if ($l['catatan']): ?><br><span class="mini">Catatan guru: <?= e($l['catatan']) ?></span><?php endif; ?></span>
          <?php else: ?><span class="mini">Belum dinilai</span><?php endif; ?></td>
        <td data-label="LKPD"><?php $lb = $b['lembar']; if (!$lb || $lb['status'] !== 'dikumpulkan'): ?><span class="mini"><?= $lb ? 'Belum dikumpulkan' : 'Belum dikerjakan' ?></span><?php elseif ($lb['nilai'] === null): ?><span class="mini">PG <?= (int) $lb['skor_pg'] ?> · uraian menunggu guru</span><?php else: ?><span class="nilai-angka"><?= (int) $lb['nilai'] ?></span> <span class="mini"><?= e(kategori_nilai((int) $lb['nilai'])) ?></span><?php endif; ?></td>
        <td data-label="Simulasi"><?= $pr['game_skor'] !== null ? '<span class="mono">' . (int) $pr['game_skor'] . ' / ' . (int) $pr['game_skor_maks'] . '</span>' : '<span class="mini">Belum ada skor</span>' ?></td>
        <td data-label="Nilai modul"><?php $n = $b['nilai']; if ($n['akhir'] === null): ?><span class="mini">Belum lengkap</span><?php else: ?><span class="nilai-angka<?= $n['tuntas'] ? '' : ' nilai-angka--kurang' ?>"><?= (int) $n['akhir'] ?></span> <span class="lencana lencana--<?= e(Nilai::STATUS[$n['status']][1]) ?>"><?= e(Nilai::STATUS[$n['status']][0]) ?></span>
          <?php if ($n['status'] === 'remidi' && $n['adaRemidi']): ?><br><a href="<?= e(url('/modul/' . $b['m']['id'] . '/remidi')) ?>">Kerjakan remidi</a><?php elseif ($n['tuntas'] && $n['adaPengayaan'] && !$n['pengayaan']): ?><br><a href="<?= e(url('/modul/' . $b['m']['id'] . '/pengayaan')) ?>">Coba pengayaan</a><?php elseif ($n['pengayaan'] && $n['pengayaan']['nilai'] !== null): ?><br><span class="mini">Pengayaan <?= (int) $n['pengayaan']['nilai'] ?></span><?php endif; ?><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$baris): ?><tr><td colspan="6" class="kosong">Belum ada modul yang dibuka. Nilai muncul di sini setelah LKPD pertamamu dinilai guru.</td></tr><?php endif; ?>
  </tbody></table></div>
</div>
