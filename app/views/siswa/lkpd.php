<?php meta('judul', 'Pengumpulan'); meta('skrip', ['js/lkpd.js']);
$jaw = $pg['jawaban_arr'];
$dinilai = $pg['nilai'] !== null && $terkunci; ?>
<?= View::bagian('partials/kepala', ['konteks' => 'Modul ' . $m['nomor'] . ' · Pengumpulan 4 dari 6', 'judul' => $def['judul'] ?? 'Pengumpulan', 'sub' => $def['petunjuk'] ?? '', 'kembali' => ['/modul/' . $m['id'], 'Modul ' . $m['nomor']], 'jejak' => Modul::langkah($m, Auth::id()), 'aktif' => 'lkpd']) ?>
<div class="isi isi--sempit">
  <?php if (!empty($def['contoh'])): ?><div class="catatan-contoh"><b>Konten contoh.</b> Simpan otomatis, unggah, kumpul, dan nilai sudah berfungsi.</div><?php endif; ?>
  <?php if ($terkunci): ?>
    <div class="flash flash--info">Hasil praktikum sudah dikumpulkan<?= $pg['dikumpulkan_pada'] ? ' pada ' . e(hari_id($pg['dikumpulkan_pada']) . ', ' . date('H:i', strtotime($pg['dikumpulkan_pada']))) : '' ?> dan tidak bisa diubah lagi.</div>
  <?php endif; ?>
  <?php if (!$terkunci && trim((string) $pg['catatan']) !== ''): ?>
    <div class="flash flash--info">Catatan dari gurumu: <?= e($pg['catatan']) ?></div>
  <?php endif; ?>
  <?php if ($dinilai): ?>
    <section class="card" style="margin-bottom:16px">
      <h2>Nilai dari guru</h2>
      <div class="nilai-kotak"><span class="angka"><?= (int) $pg['nilai'] ?></span><div><?= lencana('dinilai') ?> <b><?= e(kategori_nilai((int) $pg['nilai'])) ?></b><?php if ($pg['catatan']): ?><p style="margin:6px 0 0"><?= e($pg['catatan']) ?></p><?php endif; ?></div></div>
    </section>
  <?php endif; ?>
  <form method="post" action="<?= e(url('/modul/' . $m['id'] . '/lkpd/kumpul')) ?>" id="form-lkpd" data-simpan="<?= e(url('/modul/' . $m['id'] . '/lkpd/simpan')) ?>" data-unggah="<?= e(url('/modul/' . $m['id'] . '/lkpd/berkas')) ?>" data-hapus="<?= e(url('/lkpd/berkas/')) ?>" data-terkunci="<?= $terkunci ? '1' : '0' ?>">
    <?= csrf_field() ?>
    <?php foreach ($def['bagian'] ?? [] as $bagian): ?>
      <section class="card lkpd-bagian">
        <h2><?= e($bagian['judul'] ?? '') ?></h2>
        <?php if (!empty($bagian['petunjuk'])): ?><p class="mini"><?= e($bagian['petunjuk']) ?></p><?php endif; ?>
        <?php foreach ($bagian['butir'] ?? [] as $b): $id = $b['id']; $nilaiB = (string) ($jaw[$id] ?? ''); $wajib = ($b['wajib'] ?? true) !== false; ?>
          <div class="butir" data-butir="<?= e($id) ?>">
            <?php if ($b['jenis'] === 'pilihan'): ?>
              <span class="label" id="lbl-<?= e($id) ?>"><?= e($b['label']) ?><?= $wajib ? ' <span class="wajib" aria-label="wajib">*</span>' : '' ?></span>
              <?php if (!empty($b['petunjuk'])): ?><div class="ket"><?= e($b['petunjuk']) ?></div><?php endif; ?>
              <div class="pilihan" role="radiogroup" aria-labelledby="lbl-<?= e($id) ?>"><?php foreach ($b['opsi'] as $o): ?><label><input type="radio" name="j[<?= e($id) ?>]" value="<?= e($o) ?>" <?= $nilaiB === $o ? 'checked' : '' ?> <?= $terkunci ? 'disabled' : '' ?>><?= e($o) ?></label><?php endforeach; ?></div>
            <?php elseif ($b['jenis'] === 'berkas'): ?>
              <span class="label"><?= e($b['label']) ?><?= $wajib ? ' <span class="wajib" aria-label="wajib">*</span>' : '' ?></span>
              <?php if (!empty($b['petunjuk'])): ?><div class="ket"><?= e($b['petunjuk']) ?></div><?php endif; ?>
              <div class="berkas-daftar" data-daftar="<?= e($id) ?>"><?php foreach ($berkas[$id] ?? [] as $f): ?>
                  <div class="berkas-item" data-berkas="<?= (int) $f['id'] ?>">
                    <?php if (str_starts_with($f['mime'], 'image/')): ?><img src="<?= e(url('/lkpd/berkas/' . $f['id'])) ?>" alt=""><?php else: ?><?= icon('file', 22) ?><?php endif; ?>
                    <a href="<?= e(url('/lkpd/berkas/' . $f['id'])) ?>" target="_blank" rel="noopener"><?= e($f['nama_asli']) ?></a><span class="mini"><?= e(ukuran_berkas((int) $f['ukuran'])) ?></span>
                    <?php if (!$terkunci): ?><button type="button" data-hapus-berkas="<?= (int) $f['id'] ?>" aria-label="Hapus <?= e($f['nama_asli']) ?>">✕</button><?php endif; ?>
                  </div>
                <?php endforeach; ?></div>
              <?php if (!$terkunci): $maksB = (int) ($b['maks_berkas'] ?? 3); $jenisB = preg_match('/\.pkt|project/i', (string) $b['label']) ? 'file .pkt atau .zip' : (preg_match('/^(foto|screenshot|tangkapan|bukti)/i', (string) $b['label']) ? 'foto atau PDF' : 'foto, PDF, .pkt, atau .zip'); $penuh = count($berkas[$id] ?? []) >= $maksB; ?><label class="unggah" data-maks="<?= $maksB ?>"<?= $penuh ? ' hidden' : '' ?>><?= icon('upload', 20) ?> Pilih berkas: <?= e($jenisB) ?> (maks. 20 MB<?= $maksB > 1 ? ', paling banyak ' . $maksB . ' berkas' : ', 1 berkas' ?>)<input type="file" data-unggah-butir="<?= e($id) ?>" accept=".jpg,.jpeg,.png,.webp,.pdf,.pkt,.zip"></label><p class="mini penuh-teks"<?= $penuh ? '' : ' hidden' ?>>Sudah <?= $maksB ?> berkas. Untuk mengganti, hapus dulu berkas di atas (tombol ✕).</p><?php elseif (empty($berkas[$id])): ?><p class="mini" style="margin:0">Tidak ada berkas.</p><?php endif; ?>
            <?php else: ?>
              <label for="b-<?= e($id) ?>"><?= e($b['label']) ?><?= $wajib ? ' <span class="wajib" aria-label="wajib">*</span>' : '' ?></label>
              <?php if (!empty($b['petunjuk'])): ?><div class="ket"><?= e($b['petunjuk']) ?></div><?php endif; ?>
              <?php if ($b['jenis'] === 'paragraf'): ?>
                <textarea class="input" id="b-<?= e($id) ?>" name="j[<?= e($id) ?>]" maxlength="5000" <?= $terkunci ? 'readonly' : '' ?>><?= e($nilaiB) ?></textarea>
              <?php else: ?>
                <input class="input<?= $b['jenis'] === 'angka' ? ' input--mono' : '' ?>" id="b-<?= e($id) ?>" name="j[<?= e($id) ?>]" value="<?= e($nilaiB) ?>" maxlength="500" <?= $b['jenis'] === 'angka' ? 'inputmode="decimal"' : '' ?> <?= $terkunci ? 'readonly' : '' ?> autocomplete="off">
              <?php endif; ?>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </section>
    <?php endforeach; ?>
    <?php if (!$terkunci): ?>
      <div class="bar-lkpd">
        <span class="status-simpan" id="status-simpan" aria-live="polite"><?= icon('check', 16) ?> Tersimpan otomatis</span>
        <button class="btn btn--kuning" type="submit" data-konfirmasi="Kumpulkan hasil praktikum sekarang? Setelah dikumpulkan, jawaban dan berkas tidak bisa diubah." data-ya="Kumpulkan">Kumpulkan</button>
      </div>
    <?php else: ?>
      <?= View::bagian('partials/lanjut', ['langkah' => $langkah, 'dari' => 'lkpd', 'tampil' => true, 'id' => 'lanjut']) ?>
    <?php endif; ?>
  </form>
</div>
