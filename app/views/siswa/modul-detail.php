<?php meta('judul', 'Modul ' . $m['nomor']);
$selesai = count(array_filter($langkah, fn($l) => $l['status'] === 'selesai'));
$berikut = array_values(array_filter($langkah, fn($l) => !in_array($l['status'], ['selesai', 'segera', 'terkunci'], true)))[0]['kunci'] ?? ''; ?>
<?= View::bagian('partials/kepala', ['konteks' => 'Modul ' . $m['nomor'] . ($m['tanggal'] ? ' · ' . hari_id($m['tanggal'], true) : ''), 'judul' => $m['judul'], 'sub' => $m['ringkasan'], 'kembali' => ['/modul', 'Semua modul'], 'jejak' => $langkah, 'berikut' => $berikut]) ?>
<div class="isi"><?= View::bagian('partials/ingat-pretest', ['pre' => $pre ?? null]) ?><div class="kolom">
  <div>
  <ol class="langkah" aria-label="Langkah modul">
    <?php foreach ($langkah as $i => $l): $mati = $l['status'] === 'segera'; $kunci = $l['status'] === 'terkunci'; $iniBerikut = $l['kunci'] === $berikut; ?>
      <li class="lk lk--<?= e($l['status']) ?><?= $iniBerikut ? ' lk--berikut' : '' ?>" data-muncul>
        <span class="no" aria-hidden="true"><?= $l['status'] === 'selesai' ? icon('check', 18) : $i + 1 ?></span>
        <div><b><?= e($l['judul']) ?></b><small><?= e($l['ket']) ?></small></div>
        <div class="aksi"><?= lencana($l['status']) ?>
          <?php if ($mati): ?><span class="segera-teks">Belum tersedia</span>
          <?php elseif ($kunci): ?><span class="segera-teks">Selesaikan langkah sebelumnya</span>
          <?php else: ?><a class="btn <?= $iniBerikut ? 'btn--kuning' : ($l['status'] === 'selesai' ? 'btn--garis' : 'btn--utama') ?> btn--kecil" href="<?= e(url($l['href'])) ?>"><?= e($l['tombol']) ?><span class="sr">: <?= e($l['judul']) ?></span></a><?php endif; ?>
        </div>
      </li>
    <?php endforeach; ?>
  </ol>
  <?php if (!Modul::urutWajib()): ?><p class="mini catatan-urut"><?= icon('clock', 16) ?> Paling mudah dikerjakan berurutan, tapi kalau waktunya mepet kamu boleh langsung ke langkah mana pun, misalnya LKPD atau Refleksi.</p><?php endif; ?>
  <?php $n = $nilai; $st = $n['status']; if ($st !== 'belum'): $t = $st === 'tuntas' || $st === 'tuntas_remidi' ? $n['pengayaan'] : $n['remidi']; ?>
    <section class="card tindak-kartu" data-muncul id="tindak-lanjut">
      <?php if ($n['tuntas']): ?>
        <span class="ikon-kotak ikon-kotak--hijau" aria-hidden="true"><?= icon('award', 26) ?></span>
        <div><h2><?= $st === 'tuntas_remidi' ? 'Tuntas setelah remidi' : 'Kamu tuntas di modul ini' ?></h2>
          <p>Nilai modul <b><?= (int) $n['akhir'] ?></b> (KKM <?= (int) $n['kkm'] ?>).<?php if ($n['adaPengayaan']): ?> Coba soal pengayaan untuk tantangan tambahan. Nilainya dicatat terpisah dan tidak mengurangi nilaimu.<?php endif; ?></p>
          <?php if ($n['adaPengayaan']): ?><a class="btn <?= $t && $t['status'] === 'dikumpulkan' ? 'btn--garis' : 'btn--kuning' ?> btn--kecil" href="<?= e(url('/modul/' . $m['id'] . '/pengayaan')) ?>"><?= !$t ? 'Kerjakan pengayaan' : ($t['status'] === 'dikumpulkan' ? 'Lihat hasil pengayaan' : 'Lanjut pengayaan') ?></a><?php endif; ?></div>
      <?php else: ?>
        <span class="ikon-kotak ikon-kotak--merah" aria-hidden="true"><?= icon('edit', 26) ?></span>
        <div><h2><?= $st === 'menunggu' ? 'Remidi menunggu dinilai' : ($st === 'belum_tuntas' ? 'Belum tuntas setelah remidi' : 'Kamu perlu melakukan perbaikan (remidi)') ?></h2>
          <p><?php if ($st === 'menunggu'): ?>Jawaban remidimu sudah terkirim. Gurumu akan menilai bagian uraian.
            <?php elseif ($st === 'belum_tuntas'): ?>Nilai akhirmu <b><?= (int) $n['akhir'] ?></b>, masih di bawah KKM <?= (int) $n['kkm'] ?>. Temui gurumu untuk bimbingan; gurumu bisa membuka remidi lagi.
            <?php else: ?>Nilai modulmu <b><?= (int) $n['awal'] ?></b>, KKM <?= (int) $n['kkm'] ?>. <?= $n['adaRemidi'] ? 'Kerjakan soal remidi. Nilai remidi dihitung paling tinggi sama dengan KKM.' : 'Soal remidi belum disiapkan. Tanyakan gurumu.' ?><?php endif; ?></p>
          <?php if ($n['adaRemidi']): ?><a class="btn <?= $st === 'remidi' ? 'btn--kuning' : 'btn--garis' ?> btn--kecil" href="<?= e(url('/modul/' . $m['id'] . '/remidi')) ?>"><?= !$t ? 'Kerjakan remidi' : ($t['status'] === 'dikumpulkan' ? 'Lihat hasil remidi' : 'Lanjut remidi') ?></a><?php endif; ?></div>
      <?php endif; ?>
    </section>
  <?php endif; ?>
  </div>
  <div>
    <section class="card kemajuan" data-muncul>
      <?= cincin($selesai, count($langkah), 124) ?>
      <p class="mini" style="margin:0"><?= (int) $persen ?>% modul selesai</p>
    </section>
    <?php if ($nilai['komponen']): ?><section class="card" data-muncul><h2>Nilai modul</h2>
      <ul class="nilai-komponen"><?php foreach ($nilai['komponen'] as $x): if (!$x['bobot']) { continue; } ?><li><?= e($x['label']) ?> <b><?= $x['nilai'] !== null ? (int) $x['nilai'] : '–' ?></b> <span class="mini"><?= (int) $x['bobot'] ?>%</span></li><?php endforeach; ?></ul>
      <?php if ($nilai['akhir'] === null): ?><p class="mini" style="margin:0">Nilai modul muncul setelah semua bagian dinilai. Belum: <?= e(implode(', ', array_map(fn($k) => mb_strtolower(explode(':', $k)[0]), $nilai['kurang']))) ?>.</p>
      <?php else: ?><p style="margin:0"><span class="nilai-angka<?= $nilai['tuntas'] ? '' : ' nilai-angka--kurang' ?>"><?= (int) $nilai['akhir'] ?></span> <span class="mini">KKM <?= (int) $nilai['kkm'] ?></span> <span class="lencana lencana--<?= e(Nilai::STATUS[$nilai['status']][1]) ?>"><?= e(Nilai::STATUS[$nilai['status']][0]) ?></span></p><?php endif; ?>
    </section><?php endif; ?>
    <section class="card diskusi-modul" data-muncul><h2><?= icon('chat', 20) ?> Diskusi Modul <?= (int) $m['nomor'] ?></h2>
      <p class="mini"><?= $jumlahDiskusi ? $jumlahDiskusi . ' pertanyaan dari teman dan gurumu.' : 'Belum ada pertanyaan. Bingung di bagian mana? Tanyakan saja.' ?></p>
      <div class="tombol-baris"><a class="btn btn--kuning btn--kecil" href="<?= e(url('/diskusi?modul=' . $m['id'] . '&tulis=1#tulis')) ?>">Tanya</a><?php if ($jumlahDiskusi): ?><a class="btn btn--garis btn--kecil" href="<?= e(url('/diskusi?modul=' . $m['id'])) ?>">Lihat diskusi</a><?php endif; ?></div>
    </section>
    <?php if ($tujuan): ?><section class="card" data-muncul><h2>Tujuan pembelajaran</h2><ul class="tujuan"><?php foreach ($tujuan as $t): ?><li><?= e($t) ?></li><?php endforeach; ?></ul></section><?php endif; ?>
  </div>
</div></div>
