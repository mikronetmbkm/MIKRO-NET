<?php meta('judul', 'Kemajuan siswa');
$kunciLangkah = array_keys($judulLangkah);
$pendek = ['materi' => 'Materi', 'simulasi' => 'Simulasi', 'praktikum' => 'Praktikum', 'lkpd' => 'Pengumpulan', 'lembar' => 'LKPD', 'refleksi' => 'Refleksi'];
$jumlahSelesai = array_fill_keys($kunciLangkah, 0);
foreach ($baris as $r) { foreach ($r['langkah'] as $k => $st) { if ($st === 'selesai') { $jumlahSelesai[$k]++; } } }
$n = count($baris);
$simbol = ['selesai' => ['✓', 'Selesai'], 'berjalan' => ['◐', 'Sedang dikerjakan'], 'belum' => ['–', 'Belum'], 'terkunci' => ['–', 'Belum'], 'segera' => ['·', 'Belum tersedia']];
$q = $kelas && $m ? '?k=' . $kelas['id'] . '&m=' . $m['id'] : ''; ?>
<?= View::bagian('partials/kepala', ['konteks' => 'Ruang guru', 'judul' => 'Kemajuan siswa', 'sub' => 'Pantau langkah tiap siswa di satu modul: siapa yang sudah selesai, siapa yang masih tertinggal.', 'naik' => true]) ?>
<div class="isi isi--naik">
  <section class="card" style="margin-bottom:22px">
    <?= View::bagian('partials/pemilih-guru', ['aksi' => '/guru/kemajuan', 'daftar' => $daftar, 'modul' => $modul, 'kelas' => $kelas, 'm' => $m]) ?>
    <?php if ($kelas && $m && $adaLembar): ?><div style="margin-top:16px"><?= View::bagian('partials/lkpd-akses', ['kelas' => $kelas, 'm' => $m, 'buka' => $lkpdBuka, 'kembali' => '/guru/kemajuan' . $q]) ?></div><?php endif; ?>
  </section>
  <?php if (!$kelas): ?><section class="card"><h2>Belum ada kelas</h2><p style="margin:0">Buat kelas dulu di menu <a href="<?= e(url('/guru/kelas')) ?>">Kelas</a>.</p></section>
  <?php elseif (!$m): ?><section class="card"><h2>Belum ada modul terbit</h2><p style="margin:0">Terbitkan modul di menu <a href="<?= e(url('/kelola/modul')) ?>">Modul</a>.</p></section>
  <?php else: ?>
    <div class="langkah-ringkas" data-muncul>
      <?php foreach ($kunciLangkah as $k): $pct = $n ? (int) round($jumlahSelesai[$k] / $n * 100) : 0; ?>
        <div class="langkah-ringkas-item"><span class="mini"><?= e($pendek[$k] ?? $judulLangkah[$k]) ?></span><b><?= (int) $jumlahSelesai[$k] ?><small>/<?= $n ?></small></b><div class="bar" aria-hidden="true"><i style="width:<?= $pct ?>%"></i></div></div>
      <?php endforeach; ?>
    </div>
    <div class="judul-bagian"><h2><?= e($kelas['nama']) ?> <span class="mini">· <?= $n ?> siswa</span></h2><a href="<?= e(url('/guru/refleksi' . $q)) ?>">Baca semua refleksi</a></div>
    <div class="tabel-bungkus" data-muncul><table class="tabel tabel--tumpuk tabel-kemajuan"><thead><tr><th scope="col">Siswa</th>
      <?php foreach ($kunciLangkah as $k): ?><th scope="col" class="tengah"><?= e($pendek[$k] ?? $judulLangkah[$k]) ?></th><?php endforeach; ?>
      <th scope="col" class="tengah">Skor game</th><th scope="col" class="tengah">Nilai LKPD</th><th scope="col" class="tengah">Total</th></tr></thead><tbody>
      <?php foreach ($baris as $r): $s = $r['siswa']; ?>
        <tr><td class="utama-sel"><b><?= e($s['nama']) ?></b><br><span class="mini mono"><?= e($s['nis']) ?></span></td>
          <?php foreach ($kunciLangkah as $k): $st = $r['langkah'][$k] ?? 'belum'; [$sb, $tk] = $simbol[$st] ?? ['–', $st]; ?>
            <td data-label="<?= e($pendek[$k] ?? $k) ?>" class="tengah"><span class="titik-status titik-status--<?= e($st === 'terkunci' ? 'belum' : $st) ?>" title="<?= e($tk) ?>"><?= $sb ?></span><span class="sr"> <?= e($tk) ?></span></td>
          <?php endforeach; ?>
          <td data-label="Skor game" class="tengah mono"><?= $r['progres']['game_skor'] !== null ? (int) $r['progres']['game_skor'] : '<span class="mini">–</span>' ?></td>
          <td data-label="Nilai LKPD" class="tengah"><?php $lb = $r['lembar']; if ($lb && $lb['status'] === 'dikumpulkan'): ?><a href="<?= e(url('/guru/lkpd/' . $lb['id'])) ?>"><?= $lb['nilai'] !== null ? '<span class="nilai-angka">' . (int) $lb['nilai'] . '</span>' : 'Nilai uraian' ?></a><?php else: ?><span class="mini">–</span><?php endif; ?></td>
          <td data-label="Total" class="tengah"><span class="persen-pil<?= $r['persen'] === 100 ? ' persen-pil--penuh' : '' ?>"><?= (int) $r['persen'] ?>%</span></td></tr>
      <?php endforeach; ?>
      <?php if (!$baris): ?><tr><td colspan="<?= count($kunciLangkah) + 4 ?>" class="kosong">Belum ada siswa di kelas ini. Bagikan kode kelas dari menu Kelas.</td></tr><?php endif; ?>
    </tbody></table></div>
    <ul class="kemajuan-hp">
      <?php $hurufL = ['materi' => 'M', 'simulasi' => 'S', 'praktikum' => 'P', 'lkpd' => 'K', 'lembar' => 'L', 'refleksi' => 'R'];
      foreach ($baris as $r): $s = $r['siswa']; ?>
        <li class="card"><div class="kemajuan-hp-atas"><span><b><?= e($s['nama']) ?></b><small class="mono"><?= e($s['nis']) ?></small></span><span class="persen-pil<?= $r['persen'] === 100 ? ' persen-pil--penuh' : '' ?>"><?= (int) $r['persen'] ?>%</span></div>
          <div class="kemajuan-hp-titik"><?php foreach ($kunciLangkah as $k): $st = $r['langkah'][$k] ?? 'belum'; ?><span class="titik-status titik-status--<?= e($st === 'terkunci' ? 'belum' : $st) ?>" title="<?= e($pendek[$k] ?? $k) ?>"><?= $hurufL[$k] ?? '?' ?></span><span class="sr"> <?= e(($pendek[$k] ?? $k) . ": " . ($simbol[$st][1] ?? $st)) ?></span><?php endforeach; ?>
            <?php if ($r['progres']['game_skor'] !== null): ?><small>game <?= (int) $r['progres']['game_skor'] ?></small><?php endif; ?>
            <?php $lb = $r['lembar']; if ($lb && $lb['status'] === 'dikumpulkan'): ?><a href="<?= e(url('/guru/lkpd/' . $lb['id'])) ?>"><?= $lb['nilai'] !== null ? 'LKPD ' . (int) $lb['nilai'] : 'Nilai uraian' ?></a><?php endif; ?></div></li>
      <?php endforeach; ?>
    </ul>
    <p class="mini keterangan-status kemajuan-hp-ket">M materi · S simulasi · P praktikum · K pengumpulan · L LKPD · R refleksi. Hijau selesai, kuning sedang dikerjakan.</p>
    <p class="mini keterangan-status"><span class="titik-status titik-status--selesai">✓</span> selesai <span class="titik-status titik-status--berjalan">◐</span> sedang dikerjakan <span class="titik-status titik-status--belum">–</span> belum</p>
  <?php endif; ?>
</div>
