<?php meta('judul', 'Materi');
// PDF.js: pakai salinan lokal bila ada (public/assets/vendor), kalau tidak ambil dari CDN cdnjs.
$lokal = is_file(ROOT . '/public/assets/vendor/pdf.min.js') && is_file(ROOT . '/public/assets/vendor/pdf.worker.min.js');
$cdn = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/';
$pdfjs = $lokal ? 'vendor/pdf.min.js' : $cdn . 'pdf.min.js'; // layout memanggil asset() sendiri untuk jalur relatif
$worker = $lokal ? asset('vendor/pdf.worker.min.js') : $cdn . 'pdf.worker.min.js';
meta('skrip', [$pdfjs, 'js/pdf-viewer.js']); ?>
<?= View::bagian('partials/kepala', ['konteks' => 'Modul ' . $m['nomor'] . ' · Materi 1 dari 6', 'judul' => 'Materi', 'sub' => $m['judul'], 'kembali' => ['/modul/' . $m['id'], 'Modul ' . $m['nomor']], 'jejak' => Modul::langkah($m, Auth::id()), 'aktif' => 'materi']) ?>
<div class="isi">
<?php $v = $video ?? []; if (!empty($v['berkas']) || !empty($v['youtube']) || !empty($v['tautan'])): ?>
  <section class="card video-materi" aria-labelledby="judul-video">
    <h2 id="judul-video"><?= icon('play', 20) ?> Video penjelasan</h2>
    <?php if (!empty($v['berkas'])): ?>
      <video class="video-bingkai" controls preload="metadata" playsinline <?= !empty($v['poster']) ? 'poster="' . e(asset($v['poster'])) . '"' : '' ?>><source src="<?= e(asset($v['berkas'])) ?>" type="video/mp4">Browser ini tidak dapat memutar video.</video>
    <?php endif; ?>
    <?php if (!empty($v['youtube'])): ?>
      <div class="video-yt"><iframe src="https://www.youtube-nocookie.com/embed/<?= e($v['youtube']) ?>" title="Video penjelasan" loading="lazy" allow="accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe></div>
    <?php endif; ?>
    <?php if (!empty($v['tautan'])): ?>
      <p class="mini"><a href="<?= e($v['tautan']) ?>" target="_blank" rel="noopener noreferrer">Buka video dari gurumu<span class="sr"> (tab baru)</span></a></p>
    <?php endif; ?>
    <p class="mini">Tonton dulu videonya untuk mendapat gambaran, lalu baca slide di bawah.</p>
  </section>
<?php endif; ?>
<?php if (!$ada): ?>
  <div class="card"><p style="margin:0">Materi untuk modul ini belum diunggah. Tanyakan ke gurumu kapan PDF-nya tersedia.</p></div>
<?php else: ?>
  <?php if ($contoh): ?><div class="catatan-contoh"><b>Konten contoh.</b> Materi asli akan diganti setelah PDF dari tim materi diunggah admin.</div><?php endif; ?>
  <div class="pv" id="pv" data-pdf="<?= e(url('/modul/' . $m['id'] . '/materi/pdf')) ?>" data-progres="<?= e(url('/modul/' . $m['id'] . '/materi/progres')) ?>" data-mulai="<?= (int) $p['halaman_terakhir'] ?>" data-worker="<?= e($worker) ?>">
    <div class="pv-bar">
      <button class="btn btn--garis btn--kecil" id="pv-prev" type="button"><?= icon('back', 18) ?> Sebelumnya</button>
      <span class="pv-info" id="pv-info" aria-live="polite">Memuat…</span>
      <button class="btn btn--utama btn--kecil" id="pv-next" type="button">Berikutnya</button>
      <span class="sp"></span>
      <button class="btn btn--garis btn--kecil" id="pv-full" type="button">Layar penuh</button>
      <button class="btn btn--garis btn--kecil" id="pv-mode" type="button">Mode gulir</button>
      <a class="btn btn--garis btn--kecil" href="<?= e(url('/modul/' . $m['id'] . '/materi/pdf')) ?>" target="_blank" rel="noopener">Buka PDF<span class="sr"> (tab baru)</span></a>
    </div>
    <div class="pv-area memuat" id="pv-area"><canvas id="pv-kanvas" aria-label="Halaman materi"></canvas></div>
  </div>
  <?= View::bagian('partials/lanjut', ['langkah' => $langkah, 'dari' => 'materi', 'tampil' => (int) $p['total_halaman'] > 0 && (int) $p['halaman_terakhir'] >= (int) $p['total_halaman'], 'id' => 'lanjut']) ?>
  <p class="hint" id="pv-status" aria-live="polite">Halaman terjauh yang kamu baca tersimpan otomatis. Geser layar atau pakai tombol panah untuk pindah halaman.</p>
<?php endif; ?>
<?php if (!empty($bacaan['jalan'])): ?>
  <section class="card bacaan-kartu" aria-labelledby="judul-bacaan">
    <span class="ikon-kotak" aria-hidden="true"><?= icon('book', 24) ?></span>
    <div><h2 id="judul-bacaan">Bahan bacaan</h2>
      <p class="mini">Isi slide ini dalam bentuk teks lengkap dengan contoh dan gambar, untuk dibaca ulang atau dicetak. PDF, <?= e(ukuran_berkas((int) $bacaan['ukuran'])) ?>.</p></div>
    <div class="tombol-baris"><a class="btn btn--garis btn--kecil" href="<?= e(url('/modul/' . $m['id'] . '/bacaan')) ?>" target="_blank" rel="noopener">Baca<span class="sr"> bahan bacaan (tab baru)</span></a>
      <a class="btn btn--utama btn--kecil" href="<?= e(url('/modul/' . $m['id'] . '/bacaan?unduh=1')) ?>">Unduh PDF</a></div>
  </section>
<?php endif; ?>
</div>
