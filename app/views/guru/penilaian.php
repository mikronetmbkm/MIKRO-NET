<?php meta('judul', 'Penilaian');
// Hitung kategori tiap siswa: perlu dinilai (praktik atau uraian LKPD), sudah dinilai, belum mengumpulkan apa pun.
$perlu = $dinilai = $draf = $belum = 0; $kat = []; $nPerlu = $nSelesai = $nBelum = 0;
foreach ($rekap as $r) {
    $st = $r['status'] ?? null; $ls = $r['lembar_status'] ?? null;
    if ($st === 'dikumpulkan') { $r['nilai'] === null ? $perlu++ : $dinilai++; }
    elseif ($st) { $draf++; }
    else { $belum++; }
    $pPraktik = $st === 'dikumpulkan' && $r['nilai'] === null;
    $pLkpd = $ls === 'dikumpulkan' && $r['lembar_nilai'] === null;
    $k = [];
    if ($pPraktik || $pLkpd) { $k[] = 'perlu'; $nPerlu++; }
    if ($st === 'dikumpulkan' && $r['nilai'] !== null && (!$adaLembar || ($ls === 'dikumpulkan' && $r['lembar_nilai'] !== null))) { $k[] = 'selesai'; $nSelesai++; }
    if ($st !== 'dikumpulkan' && $ls !== 'dikumpulkan') { $k[] = 'belum'; $nBelum++; }
    $kat[$r['siswa_id']] = implode(' ', $k);
}
$totalSiswa = count($rekap);
$kumpul = $perlu + $dinilai;
$pgMaks = $mPilih ? (function ($m) { $d = Lembar::definisi((int) $m['nomor']); return $d ? count($d['pg'] ?? []) * Lembar::poinPg($d) : 0; })($mPilih) : 0;
$waktu = fn($t) => $t ? hari_id($t) . ', ' . date('H:i', strtotime($t)) : ''; ?>
<?= View::bagian('partials/kepala', ['konteks' => 'Ruang guru', 'judul' => 'Penilaian', 'sub' => 'Nilai hasil praktikum dengan rubrik, dan nilai uraian LKPD (pilihan gandanya dinilai otomatis).', 'naik' => true]) ?>
<div class="isi isi--naik">
  <nav class="tab tab--atas" aria-label="Jenis nilai"><a href="<?= e(url('/guru/penilaian')) ?>" aria-current="page">Praktikum &amp; LKPD</a><a href="<?= e(url('/kelola/kuis/hasil' . ($kelasPilih ? '?k=' . $kelasPilih['id'] : ''))) ?>">Pretest &amp; post-test</a></nav>
  <section class="card" style="margin-bottom:22px">
    <?= View::bagian('partials/pemilih-guru', ['aksi' => '/guru/penilaian', 'daftar' => $daftar, 'modul' => $modul, 'kelas' => $kelasPilih, 'm' => $mPilih]) ?>
    <?php if ($kelasPilih && $mPilih && $adaLembar): ?><div style="margin-top:16px"><?= View::bagian('partials/lkpd-akses', ['kelas' => $kelasPilih, 'm' => $mPilih, 'buka' => $lkpdBuka, 'kembali' => '/guru/penilaian?k=' . $kelasId . '&m=' . $modulId]) ?></div><?php endif; ?>
    <?php if ($kelasPilih && $totalSiswa): ?>
      <div class="rekap-kumpul">
        <div class="baris"><span><b style="color:var(--ink)"><?= $kumpul ?> dari <?= $totalSiswa ?></b> siswa sudah mengumpulkan praktikum<?php if ($adaLembar): ?> · <b style="color:var(--ink)"><?= count(array_filter($rekap, fn($r) => ($r['lembar_status'] ?? null) === 'dikumpulkan')) ?></b> sudah mengumpulkan LKPD<?php endif; ?></span><span><?= (int) round($kumpul / $totalSiswa * 100) ?>%</span></div>
        <div class="bar" aria-hidden="true"><i style="width:<?= (int) round($kumpul / $totalSiswa * 100) ?>%"></i></div>
      </div>
    <?php endif; ?>
  </section>
  <?php if (!$kelasPilih): ?><section class="card"><h2>Belum ada kelas</h2><p style="margin:0">Buat kelas dulu di menu <a href="<?= e(url('/guru/kelas')) ?>">Kelas</a>, lalu bagikan kodenya ke siswa.</p></section><?php else: ?>
  <div class="saring" role="group" aria-label="Tampilkan siswa">
    <button type="button" class="saring-btn" data-saring="" aria-pressed="true">Semua <b><?= $totalSiswa ?></b></button>
    <button type="button" class="saring-btn" data-saring="perlu" aria-pressed="false">Perlu dinilai <b><?= $nPerlu ?></b></button>
    <button type="button" class="saring-btn" data-saring="selesai" aria-pressed="false">Selesai dinilai <b><?= $nSelesai ?></b></button>
    <button type="button" class="saring-btn" data-saring="belum" aria-pressed="false">Belum mengumpulkan <b><?= $nBelum ?></b></button>
  </div>
  <div class="tabel-bungkus" data-muncul><table class="tabel tabel--tumpuk tabel-nilai" id="tabel-nilai"><thead><tr>
    <th scope="col">Siswa</th><th scope="col">Pengumpulan praktikum</th><th scope="col" class="tengah">Nilai praktik</th><th scope="col">LKPD</th><th scope="col" class="kanan">Tindakan</th></tr></thead><tbody>
    <?php foreach ($rekap as $r): $st = $r['status'] ?? null; $ls = $r['lembar_status'] ?? null;
      $pPraktik = $st === 'dikumpulkan' && $r['nilai'] === null; $pLkpd = $ls === 'dikumpulkan' && $r['lembar_nilai'] === null; ?>
      <tr data-kat="<?= e($kat[$r['siswa_id']]) ?>"<?= $pPraktik || $pLkpd ? ' class="perlu-nilai"' : '' ?>>
        <td class="utama-sel"><b><?= e($r['nama']) ?></b><span class="mini mono nis"><?= e($r['nis']) ?></span></td>
        <td data-label="Praktikum"><span class="sel-status"><?= $st ? lencana($st === 'dikumpulkan' && $r['nilai'] !== null ? 'dinilai' : $st) : lencana('belum') ?><?php if ($r['dikumpulkan_pada']): ?><small><?= e($waktu($r['dikumpulkan_pada'])) ?></small><?php endif; ?></span></td>
        <td data-label="Nilai praktik" class="tengah"><?= $r['nilai'] !== null ? '<span class="nilai-angka">' . (int) $r['nilai'] . '</span>' : '<span class="mini">–</span>' ?></td>
        <td data-label="LKPD"><span class="sel-status"><?php if (!$adaLembar): ?><span class="mini">–</span>
          <?php elseif (!$ls): ?><?= lencana('belum') ?>
          <?php elseif ($ls !== 'dikumpulkan'): ?><?= lencana('draf') ?>
          <?php elseif ($r['lembar_nilai'] === null): ?><span class="lencana lencana--warn">Menunggu uraian</span><small>PG <?= (int) $r['lembar_skor_pg'] ?>/<?= (int) $pgMaks ?></small>
          <?php else: ?><span class="nilai-angka"><?= (int) $r['lembar_nilai'] ?></span><small>PG <?= (int) $r['lembar_skor_pg'] ?>/<?= (int) $pgMaks ?></small><?php endif; ?></span></td>
        <td class="aksi-sel"><span class="aksi-tumpuk">
          <?php if ($r['pengumpulan_id'] && $st): ?><a class="btn <?= $pPraktik ? 'btn--kuning' : 'btn--garis' ?> btn--kecil" href="<?= e(url('/guru/penilaian/' . $r['pengumpulan_id'])) ?>"><?= $pPraktik ? 'Nilai praktik' : ($st === 'dikumpulkan' ? 'Lihat praktik' : 'Lihat draf') ?><span class="sr"> <?= e($r['nama']) ?></span></a><?php endif; ?>
          <?php if ($ls === 'dikumpulkan'): ?><a class="btn <?= $pLkpd ? 'btn--kuning' : 'btn--garis' ?> btn--kecil" href="<?= e(url('/guru/lkpd/' . $r['lembar_id'])) ?>"><?= $pLkpd ? 'Nilai LKPD' : 'Lihat LKPD' ?><span class="sr"> <?= e($r['nama']) ?></span></a><?php endif; ?>
        </span></td></tr>
    <?php endforeach; ?>
    <?php if (!$rekap): ?><tr><td colspan="5" class="kosong">Belum ada siswa di kelas ini. Bagikan kode kelas dari menu Kelas.</td></tr><?php endif; ?>
    <tr class="saring-kosong" hidden><td colspan="5" class="kosong">Tidak ada siswa di kategori ini.</td></tr>
  </tbody></table></div>
  <?php endif; ?>
</div>
