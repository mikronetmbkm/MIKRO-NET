<?php meta('judul', 'Kelola simulasi'); ?>
<?= View::bagian('partials/kepala', ['konteks' => 'Guru & Admin', 'judul' => 'Kelola simulasi', 'sub' => 'Simulasi (game) yang terpasang di website. Pilih simulasi untuk sebuah modul di halaman Edit modul.', 'kembali' => ['/kelola/modul', 'Semua modul']]) ?>
<div class="isi">
  <div class="tabel-bungkus" style="margin-bottom:24px"><table class="tabel tabel--tumpuk tabel-simulasi"><thead><tr><th scope="col">Folder</th><th scope="col">Dipakai di</th><th scope="col">Diperbarui</th><th scope="col"><span class="sr">Aksi</span></th></tr></thead><tbody>
  <?php foreach ($daftar as $s): ?><tr>
    <td class="utama-sel"><b class="mono"><?= e($s['folder']) ?></b><br><a class="mini" href="<?= e(url('/games/' . $s['folder'] . '/index.html')) ?>" target="_blank" rel="noopener">Coba buka<span class="sr"> <?= e($s['folder']) ?> (tab baru)</span></a></td>
    <td data-label="Dipakai di"><?= $s['dipakai'] ? implode('<br>', array_map(fn($m) => 'Modul ' . (int) $m['nomor'] . ' · ' . e($m['judul']), $s['dipakai'])) : '<span class="mini">Belum dipakai</span>' ?></td>
    <td data-label="Diperbarui"><?= e($s['diubah']) ?></td>
    <td class="aksi-sel"><?php if ($admin && !$s['dipakai']): ?><form method="post" action="<?= e(url('/kelola/simulasi/' . $s['folder'] . '/hapus')) ?>" data-konfirmasi="Hapus folder simulasi <?= e($s['folder']) ?>?" data-ya="Hapus"><?= csrf_field() ?><button class="btn btn--bahaya btn--kecil" type="submit">Hapus</button></form><?php endif; ?></td>
  </tr><?php endforeach; ?>
  <?php if (!$daftar): ?><tr><td colspan="4" class="kosong">Belum ada simulasi terpasang.</td></tr><?php endif; ?>
  </tbody></table></div>

  <?php if ($admin): ?>
  <section class="card"><h2>Pasang atau perbarui simulasi</h2>
    <form method="post" action="<?= e(url('/kelola/simulasi')) ?>" enctype="multipart/form-data"><?= csrf_field() ?>
      <div class="baris2">
        <div class="field"><label for="folder">Nama folder</label><input class="input mono" id="folder" name="folder" required maxlength="40" pattern="[a-z0-9][a-z0-9-]{1,40}" placeholder="simulasi-p5" autocomplete="off"><div class="hint">Huruf kecil, angka, dan tanda minus.</div></div>
        <div class="field"><label for="zip">Berkas ZIP</label><input class="input" id="zip" name="zip" type="file" accept=".zip,application/zip" required><div class="hint">Berisi <code>index.html</code> (di akar ZIP atau dalam satu folder). Maks. 40 MB.</div></div>
      </div>
      <div class="field field--centang"><input type="checkbox" id="timpa" name="timpa" value="1"><label for="timpa">Timpa bila folder sudah ada (memperbarui simulasi)</label></div>
      <button class="btn btn--utama" type="submit">Pasang simulasi</button>
    </form>
    <details class="lanjutan" style="margin-top:16px"><summary>Syarat isi simulasi</summary>
      <ul class="mini">
        <li>Hanya berkas web: HTML, CSS, JS, JSON, gambar, audio, video, dan font. Berkas PHP atau berkas tersembunyi ditolak.</li>
        <li>Simulasi berjalan di dalam bingkai dan berjalan dengan hak akses yang sama seperti website, jadi pasang hanya simulasi yang dibuat tim sendiri atau sumber tepercaya.</li>
        <li>Agar skor tercatat di MIKRO-NET, simulasi mengirim <code>parent.postMessage({type:'mikronet:hasil', skor:80, skorMaks:100}, '*')</code> saat selesai.</li>
        <li>Agar progres tersimpan, simulasi menyimpan keadaannya di <code>window.name</code> lalu mengirim <code>parent.postMessage({type:'mikronet:simpan', data: window.name}, '*')</code> (maks. 60.000 karakter).</li>
      </ul>
    </details>
  </section>
  <?php else: ?>
  <p class="mini">Memasang atau menghapus simulasi hanya bisa dilakukan admin.</p>
  <?php endif; ?>
</div>
