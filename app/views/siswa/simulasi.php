<?php meta('judul', 'Simulasi'); meta('skrip', ['js/simulasi.js']); ?>
<?= View::bagian('partials/kepala', ['konteks' => 'Modul ' . $m['nomor'] . ' · Simulasi 2 dari 6', 'judul' => 'Simulasi interaktif', 'sub' => 'Coba konsepnya lewat permainan. Skor terbaikmu tercatat otomatis.', 'kembali' => ['/modul/' . $m['id'], 'Modul ' . $m['nomor']], 'jejak' => Modul::langkah($m, Auth::id()), 'aktif' => 'simulasi']) ?>
<div class="isi">
<?php if ($folder === ''): ?>
  <div class="card"><p style="margin:0">Simulasi untuk modul ini belum tersedia. Langkah ini terbuka setelah game dipasang oleh guru.</p></div>
<?php else: ?>
  <?php if ($folder === 'contoh'): ?><div class="catatan-contoh"><b>Konten contoh.</b> Game asli dari tim Desain Multimedia akan menggantikan ini.</div><?php endif; ?>
  <div class="bingkai-alat"><span class="mini">Layar terasa sempit? Buka simulasi di layar penuh.</span><button class="btn btn--utama btn--kecil" id="game-penuh" type="button"><?= icon('maximize', 18) ?> Layar penuh</button></div>
  <div class="bingkai-bungkus" id="bingkai-bungkus"><button class="btn btn--kuning btn--kecil bingkai-tutup" id="game-tutup" type="button">✕ Keluar layar penuh</button><iframe class="bingkai" id="game" title="Simulasi" src="<?= e(url('/games/' . $folder . '/index.html') . '?v=' . ASSET_VERSION) ?>" name="<?= e((string) ($p['game_data'] ?? '')) ?>" sandbox="allow-scripts allow-same-origin allow-pointer-lock" data-hasil="<?= e(url('/modul/' . $m['id'] . '/simulasi/hasil')) ?>" data-progres="<?= e(url('/modul/' . $m['id'] . '/simulasi/progres')) ?>"></iframe></div>
  <div class="bar-simulasi">
    <b id="game-status" aria-live="polite"><?= $p['game_skor'] !== null ? 'Skor terbaikmu: ' . (int) $p['game_skor'] . ' / ' . (int) $p['game_skor_maks'] : ((int) $p['simulasi_selesai'] ? 'Simulasi ditandai selesai' : 'Mainkan sampai selesai. Skor tercatat otomatis.') ?></b>
    <a class="btn btn--garis" href="<?= e(url('/modul/' . $m['id'])) ?>">Kembali ke modul</a>
  </div>
  <?= View::bagian('partials/lanjut', ['langkah' => $langkah, 'dari' => 'simulasi', 'tampil' => $p['game_skor'] !== null || (int) $p['simulasi_selesai'], 'id' => 'lanjut']) ?>
  <details class="cadangan-game"><summary>Game tidak bisa dimuat?</summary>
    <p class="mini">Kalau permainan tidak tampil di perangkatmu, tandai simulasi selesai secara manual agar bisa lanjut. Gurumu bisa melihat bahwa skor tidak tercatat.</p>
    <button class="btn btn--garis btn--kecil" id="game-selesai" type="button">Tandai selesai</button>
  </details>
<?php endif; ?>
</div>
