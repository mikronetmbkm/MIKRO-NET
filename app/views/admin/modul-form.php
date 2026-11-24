<?php meta('judul', 'Edit Modul ' . $m['nomor']);
$bawaanPdf = is_file(ROOT . '/database/content/materi-p' . (int) $m['nomor'] . '.pdf');
$adaVideo = is_file(ROOT . '/public/assets/video/materi-p' . (int) $m['nomor'] . '.mp4');
$adaData = KontenController::adaDataSiswa((int) $m['id']); ?>
<?= View::bagian('partials/kepala', ['konteks' => 'Guru & Admin · Modul ' . $m['nomor'], 'judul' => $m['judul'], 'kembali' => ['/kelola/modul', 'Semua modul']]) ?>
<div class="isi"><div class="kolom">
  <div>
  <section class="card"><h2>Informasi modul</h2>
    <form method="post" action="<?= e(url('/kelola/modul/' . $m['id'])) ?>"><?= csrf_field() ?>
      <div class="field"><label for="judul">Judul</label><input class="input" id="judul" name="judul" value="<?= e($m['judul']) ?>" required maxlength="200"></div>
      <div class="field"><label for="ringkasan">Ringkasan (satu kalimat)</label><input class="input" id="ringkasan" name="ringkasan" value="<?= e($m['ringkasan']) ?>" maxlength="300"></div>
      <div class="field"><label for="tujuan">Tujuan pembelajaran (satu per baris)</label><textarea class="input" id="tujuan" name="tujuan"><?= e($m['tujuan']) ?></textarea></div>
      <div class="baris2">
        <div class="field"><label for="tanggal">Tanggal pertemuan</label><input class="input" id="tanggal" name="tanggal" type="date" value="<?= e($m['tanggal']) ?>"></div>
        <div class="field"><label for="status">Status</label><select class="input" id="status" name="status"><?php foreach (['terbit' => 'Terbit (siswa bisa buka)', 'segera' => 'Segera (terlihat, belum bisa dibuka)', 'terkunci' => 'Terkunci'] as $k => $v): ?><option value="<?= e($k) ?>" <?= $m['status'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
      </div>
      <div class="field"><label for="game_folder">Simulasi</label><select class="input" id="game_folder" name="game_folder" aria-describedby="game-hint"><option value="">Tidak ada simulasi</option><?php foreach ($games as $g): ?><option value="<?= e($g) ?>" <?= $m['game_folder'] === $g ? 'selected' : '' ?>><?= e($g) ?></option><?php endforeach; ?></select><div class="hint" id="game-hint">Daftar simulasi yang terpasang. Admin memasang simulasi baru (ZIP) di <a href="<?= e(url('/kelola/simulasi')) ?>">Kelola simulasi</a>.</div></div>
      <div class="field"><label for="video_url">Link video penjelasan (opsional)</label><input class="input" id="video_url" name="video_url" type="url" inputmode="url" maxlength="500" placeholder="https://www.youtube.com/watch?v=…" value="<?= e((string) ($m['video_url'] ?? '')) ?>" aria-describedby="video-hint"><div class="hint" id="video-hint">Tampil di langkah Materi, di atas slide. Link YouTube langsung diputar di halaman; link lain ditampilkan sebagai tautan.<?= $adaVideo ? ' Modul ini juga sudah punya video animasi bawaan.' : '' ?></div></div>
      <button class="btn btn--utama" type="submit">Simpan informasi</button>
    </form></section>

  <section class="card isi-modul"><h2>Isi langkah modul</h2>
    <p class="mini">Ubah isi tanpa koding. Isi bawaan bisa langsung diedit; setiap perubahan dicatat dan versi lama bisa dipulihkan.</p>
    <ul class="daftar-isi">
      <?php foreach (Konten::JENIS as $j => $info): $st = Konten::info($m, $j); ?>
        <li>
          <div><b><?= e($info['nama']) ?></b><span class="mini"><?= e($info['ket']) ?></span>
            <span class="mini"><?php if ($st['sumber'] === 'db' && $st['kosong']): ?><span class="lencana lencana--grey">Dikosongkan</span><?php elseif ($st['sumber'] === 'db'): ?><span class="lencana lencana--ok">Diubah</span> <?= e($st['oleh'] ?? '-') ?>, <?= e(tgl_pendek($st['pada'])) ?><?php elseif ($st['sumber'] === 'berkas'): ?><span class="lencana lencana--ok">Isi bawaan</span><?php else: ?><span class="lencana lencana--grey">Belum ada</span><?php endif; ?></span></div>
          <a class="btn btn--garis btn--kecil" href="<?= e(url('/kelola/modul/' . $m['id'] . '/isi/' . $j)) ?>"><?= $st['kosong'] ? 'Buat' : 'Edit' ?><span class="sr"> <?= e($info['nama']) ?></span></a>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
  </div>
  <div>
    <section class="card"><h2>Slide materi (PDF)</h2>
      <p class="mini"><?= $m['materi_berkas'] ? 'Memakai PDF yang diunggah: ' . e($m['materi_berkas']) : ($bawaanPdf ? 'Memakai slide bawaan modul ini.' : 'Belum ada slide; siswa melihat PDF contoh.') ?></p>
      <form method="post" action="<?= e(url('/kelola/modul/' . $m['id'] . '/materi')) ?>" enctype="multipart/form-data"><?= csrf_field() ?>
        <div class="field"><label class="sr" for="materi">Berkas PDF materi</label><input class="input" id="materi" type="file" name="materi" accept="application/pdf,.pdf" required></div>
        <button class="btn btn--utama btn--penuh" type="submit">Unggah PDF</button>
        <p class="hint" style="margin-bottom:0">Dari PowerPoint/Google Slides: simpan atau unduh sebagai PDF dulu. Maksimal 25 MB. Mengganti PDF tidak menghapus kemajuan siswa.</p></form></section>
    <?php foreach (['ajar' => ['Modul ajar', 'Khusus guru dan admin; siswa tidak bisa membukanya karena berisi kunci jawaban.'], 'bacaan' => ['Bahan bacaan siswa', 'Tampil sebagai tombol unduh di langkah Materi siswa.']] as $dj => [$dJudul, $dKet]): $dok = Modul::dokumen($m, $dj); ?>
    <section class="card dokumen-kartu"><h2><?= e($dJudul) ?> (PDF)</h2>
      <p class="mini"><?= e($dKet) ?></p>
      <p class="mini" style="margin-top:0"><?php if ($dok['sumber'] === 'unggah'): ?><span class="lencana lencana--ok">Diunggah guru</span><?php elseif ($dok['sumber'] === 'bawaan'): ?><span class="lencana lencana--ok">Bawaan</span><?php else: ?><span class="lencana lencana--grey">Belum ada</span><?php endif; ?>
        <?php if ($dok['sumber']): ?> <?= e(ukuran_berkas((int) $dok['ukuran'])) ?><?php endif; ?></p>
      <?php if ($dok['sumber']): ?><div class="tombol-baris" style="margin-bottom:12px"><a class="btn btn--garis btn--kecil" href="<?= e(url('/kelola/modul/' . $m['id'] . '/dokumen/' . $dj)) ?>" target="_blank" rel="noopener">Lihat<span class="sr"> <?= e($dJudul) ?> (tab baru)</span></a><a class="btn btn--garis btn--kecil" href="<?= e(url('/kelola/modul/' . $m['id'] . '/dokumen/' . $dj . '?unduh=1')) ?>">Unduh</a></div><?php endif; ?>
      <form method="post" action="<?= e(url('/kelola/modul/' . $m['id'] . '/dokumen/' . $dj)) ?>" enctype="multipart/form-data"><?= csrf_field() ?>
        <div class="field"><label class="sr" for="dok-<?= $dj ?>">Berkas PDF <?= e(mb_strtolower($dJudul)) ?></label><input class="input" id="dok-<?= $dj ?>" type="file" name="berkas" accept="application/pdf,.pdf" required></div>
        <button class="btn btn--garis btn--penuh" type="submit"><?= $dok['sumber'] ? 'Ganti PDF' : 'Unggah PDF' ?></button></form>
      <?php if ($dok['sumber'] === 'unggah'): ?><form method="post" action="<?= e(url('/kelola/modul/' . $m['id'] . '/dokumen/' . $dj . '/hapus')) ?>" data-konfirmasi="Hapus PDF yang diunggah? Bila ada versi bawaan, versi itu dipakai lagi." data-ya="Hapus" style="margin-top:8px"><?= csrf_field() ?><button class="btn btn--bahaya btn--kecil" type="submit">Hapus unggahan</button></form><?php endif; ?>
    </section>
    <?php endforeach; ?>
    <section class="card zona-bahaya"><h2>Hapus modul</h2>
      <?php if ($adaData): ?>
        <p class="mini" style="margin:0">Modul ini sudah punya data siswa (kemajuan atau jawaban), jadi tidak bisa dihapus. Bila tidak dipakai lagi, ubah statusnya menjadi <b>Terkunci</b>.</p>
      <?php else: ?>
        <form method="post" action="<?= e(url('/kelola/modul/' . $m['id'] . '/hapus')) ?>"><?= csrf_field() ?>
          <div class="field"><label for="konfirmasi">Ketik <b><?= (int) $m['nomor'] ?></b> untuk menghapus Modul <?= (int) $m['nomor'] ?> beserta isinya</label><input class="input" id="konfirmasi" name="konfirmasi" inputmode="numeric" autocomplete="off" required></div>
          <button class="btn btn--bahaya btn--penuh" type="submit">Hapus modul</button></form>
      <?php endif; ?>
    </section>
  </div>
</div></div>
