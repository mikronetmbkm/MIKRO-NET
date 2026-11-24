<?php $nama = Konten::JENIS[$jenis]['nama'];
meta('judul', $nama . ' · Modul ' . $m['nomor']);
meta('skrip', ['js/editor-konten.js']);
$terkunci = !empty($kunci['terkumpul']); ?>
<?= View::bagian('partials/kepala', ['konteks' => 'Kelola modul · Modul ' . $m['nomor'], 'judul' => 'Edit ' . $nama, 'sub' => $m['judul'], 'kembali' => ['/kelola/modul/' . $m['id'], 'Modul ' . $m['nomor']]]) ?>
<div class="isi isi--sempit">
  <div class="card editor-info">
    <p style="margin:0"><?= e(Konten::JENIS[$jenis]['ket']) ?>.
      <?php if ($info['sumber'] === 'db'): ?>Terakhir diubah <?= e($info['oleh'] ?? '-') ?>, <?= e(hari_id((string) $info['pada'], true)) ?> <?= e(substr((string) $info['pada'], 11, 5)) ?>.
      <?php elseif ($info['sumber'] === 'berkas'): ?>Sekarang memakai isi bawaan; setelah disimpan, isi ini tersimpan di database.
      <?php else: ?>Belum ada isi. Langkah ini tidak tampil untuk siswa sampai diisi.<?php endif; ?></p>
    <?php if ($terkunci): ?><p class="flash flash--info" style="position:static;margin:12px 0 0">Sudah ada siswa yang mengisi atau mengumpulkan. Soal/butir lama ditandai <b>terkunci</b>: kunci jawaban, jenis isian, dan skornya tidak bisa diubah atau dihapus. Teks boleh dirapikan dan soal/butir baru boleh ditambah.</p><?php endif; ?>
    <?php if (!empty($kunci['dinilai'])): ?><p class="flash flash--info" style="position:static;margin:12px 0 0">Sudah ada pengumpulan yang dinilai, jadi jumlah kriteria dan skor maksimal rubrik dikunci.</p><?php endif; ?>
    <?php if ($jenis === 'remidi' || $jenis === 'pengayaan'): ?><p class="mini" style="margin:12px 0 0">Soal ini diberikan kepada siswa yang <?= $jenis === 'remidi' ? 'belum mencapai KKM (nilai remidi maksimal sama dengan KKM)' : 'sudah tuntas, sebagai tantangan tambahan' ?>.</p><?php endif; ?>
  </div>

  <div id="editor" class="editor" data-jenis="<?= e($jenis) ?>" data-nomor="<?= (int) $m['nomor'] ?>"
       data-simpan="<?= e(url('/kelola/modul/' . $m['id'] . '/isi/' . $jenis)) ?>" data-unggah="<?= e(url('/kelola/modul/' . $m['id'] . '/gambar')) ?>"
       data-folder="<?= e(url('assets/praktikum/p' . (int) $m['nomor'])) ?>" data-csrf="<?= e(Csrf::token()) ?>">
    <p class="mini">Memuat editor…</p>
  </div>
  <script type="application/json" id="data-isi"><?= json_encode($dataIsi, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
  <script type="application/json" id="data-kunci"><?= json_encode(['kunci' => $kunci, 'asli' => $dataIsi, 'gambar' => $gambar], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>

  <div class="editor-simpan" id="editor-simpan">
    <span id="editor-status" class="mini" aria-live="polite">Belum ada perubahan.</span>
    <button class="btn btn--utama" type="button" id="btn-simpan-isi">Simpan</button>
  </div>
  <div id="editor-galat" class="flash flash--galat" role="alert" hidden style="position:static"></div>

  <div class="kolom-dua" style="margin-top:22px">
    <section class="card"><h2>Riwayat perubahan</h2>
      <?php if (!$riwayat): ?><p class="mini" style="margin:0">Belum ada versi lama.</p><?php else: ?>
        <ul class="daftar-riwayat"><?php foreach (array_slice($riwayat, 0, 10) as $r): ?>
          <li><span><?php if ($r['nama'] === null): ?><b>Isi bawaan</b> (sebelum diubah guru)<?php else: ?><?= e(hari_id((string) $r['diubah_pada'], true)) ?> <?= e(substr((string) $r['diubah_pada'], 11, 5)) ?> · <?= e($r['nama']) ?><?php endif; ?></span>
            <form method="post" action="<?= e(url('/kelola/modul/' . $m['id'] . '/isi/' . $jenis . '/pulihkan/' . $r['id'])) ?>" data-konfirmasi="Pulihkan versi ini? Isi sekarang disimpan di riwayat." data-ya="Pulihkan"><?= csrf_field() ?><button class="btn btn--garis btn--kecil" type="submit">Pulihkan</button></form></li>
        <?php endforeach; ?></ul>
      <?php endif; ?>
    </section>
    <?php if (!$terkunci && $info['sumber'] !== null && !$info['kosong']): ?>
    <section class="card zona-bahaya"><h2>Kosongkan</h2>
      <p class="mini">Langkah ini disembunyikan dari siswa sampai diisi lagi. Isi sekarang tetap tersimpan di riwayat.</p>
      <form method="post" action="<?= e(url('/kelola/modul/' . $m['id'] . '/isi/' . $jenis . '/kosongkan')) ?>" data-konfirmasi="Kosongkan <?= e($nama) ?> Modul <?= (int) $m['nomor'] ?>?" data-ya="Kosongkan"><?= csrf_field() ?><button class="btn btn--bahaya" type="submit">Kosongkan <?= e(mb_strtolower($nama)) ?></button></form>
    </section>
    <?php endif; ?>
  </div>
</div>
