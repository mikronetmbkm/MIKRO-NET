<?php meta('judul', 'Kelola kuis');
$statusTeks = ['draf' => 'Draf (siswa belum bisa melihat)', 'dibuka' => 'Dibuka (siswa bisa mengerjakan)', 'ditutup' => 'Ditutup (tidak bisa mulai baru)'];
?>
<?= View::bagian('partials/kepala', ['konteks' => 'Guru & Admin', 'judul' => 'Pretest dan post-test', 'sub' => 'Satu pretest dan satu post-test yang menguji Modul 1 sampai 4 dan materi umum jaringan. Atur kapan dibuka, lalu isi bank soalnya.', 'naik' => true]) ?>
<div class="isi isi--naik">
  <?php if ($adaContoh): ?>
    <section class="card info-biru" data-muncul>
      <b>Soal yang ada sekarang masih contoh</b>
      <p>Ada soal contoh bawaan supaya kamu bisa mencoba alurnya. Ganti dengan soal aslimu lewat Tambah soal atau Impor, lalu hapus contohnya sebelum kuis dibuka ke siswa.</p>
      <form method="post" action="<?= e(url('/kelola/kuis/hapus-contoh')) ?>" data-konfirmasi="Hapus semua soal yang masih bertanda contoh? Soal yang kamu tambah atau ubah sendiri tidak ikut terhapus." data-ya="Hapus contoh" style="margin-top:14px"><?= csrf_field() ?><button class="btn btn--kaca btn--kecil" type="submit">Hapus semua soal contoh</button></form>
    </section>
  <?php endif; ?>

  <div class="kuis-daftar" style="margin-bottom:26px">
    <?php foreach ($kuis as $jenis => $k): ?>
      <section class="card" data-muncul aria-labelledby="atur-<?= e($jenis) ?>">
        <div class="judul-bagian"><h2 id="atur-<?= e($jenis) ?>"><?= e($k['judul']) ?></h2><?= lencana($k['status']) ?></div>
        <dl class="kuis-meta"><div><dt>Soal</dt><dd><?= (int) $k['jumlah_soal'] ?></dd></div><div><dt>Mengerjakan</dt><dd><?= (int) $k['berjalan'] ?></dd></div><div><dt>Selesai</dt><dd><?= (int) $k['selesai'] ?></dd></div></dl>
        <form method="post" action="<?= e(url('/kelola/kuis/' . $k['id'] . '/atur')) ?>"><?= csrf_field() ?>
          <div class="field"><label for="st-<?= e($jenis) ?>">Status</label><select class="input" id="st-<?= e($jenis) ?>" name="status"><?php foreach ($statusTeks as $v => $t): ?><option value="<?= e($v) ?>" <?= $k['status'] === $v ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?></select></div>
          <div class="baris2">
            <div class="field"><label for="du-<?= e($jenis) ?>">Durasi (menit)</label><input class="input" id="du-<?= e($jenis) ?>" name="durasi" type="number" min="5" max="180" value="<?= (int) $k['durasi_menit'] ?>" required></div>
            <div class="field"><span class="label">Urutan</span><label class="centang"><input type="checkbox" name="acak" value="1" <?= (int) $k['acak'] ? 'checked' : '' ?>> Acak soal dan pilihan</label></div>
          </div>
          <?php if ($k['terkunci']): ?><p class="hint" style="margin:-4px 0 14px">Sudah ada siswa yang mengerjakan, jadi soal untuk kuis ini dikunci. Durasi baru hanya berlaku untuk yang belum mulai.</p><?php endif; ?>
          <button class="btn btn--utama" type="submit">Simpan</button>
        </form>
      </section>
    <?php endforeach; ?>
  </div>

  <section id="bank-soal" aria-labelledby="judul-bank">
    <div class="judul-bagian"><h2 id="judul-bank">Bank soal (<?= count($soal) ?>)</h2>
      <span class="tombol-baris"><a class="btn btn--garis btn--kecil" href="<?= e(url('/kelola/kuis/impor')) ?>">Impor massal</a><a class="btn btn--utama btn--kecil" href="<?= e(url('/kelola/kuis/soal/baru')) ?>">Tambah soal</a><a class="btn btn--garis btn--kecil" href="<?= e(url('/kelola/kuis/hasil')) ?>">Lihat nilai siswa</a></span></div>
    <p class="mini" style="margin-top:-6px">Soal yang dipakai di <b>Keduanya</b> (pretest dan post-test) membuat N-gain bisa dihitung adil.</p>
    <div class="tabel-bungkus" data-muncul><table class="tabel tabel--tumpuk"><thead><tr><th scope="col">Modul</th><th scope="col">Soal</th><th scope="col">Kunci</th><th scope="col">Dipakai di</th><th scope="col"><span class="sr">Aksi</span></th></tr></thead><tbody>
      <?php foreach ($soal as $s): $opsi = $s['opsi_arr']; ?>
        <tr><td data-label="Modul"><span class="mono"><?= e(label_modul((int) $s['modul_nomor'], true)) ?></span></td>
          <td class="utama-sel"><?= e(mb_strimwidth($s['teks'], 0, 140, '…')) ?><?= (int) $s['contoh'] ? ' ' . '<span class="lencana lencana--warn">Contoh</span>' : '' ?></td>
          <td data-label="Kunci"><span class="mono"><?= e(chr(65 + (int) $s['kunci'])) ?></span> <span class="mini"><?= e(mb_strimwidth((string) ($opsi[(int) $s['kunci']] ?? ''), 0, 40, '…')) ?></span></td>
          <td data-label="Dipakai di"><?= ['semua' => 'Keduanya', 'pre' => 'Pretest saja', 'post' => 'Post-test saja'][$s['untuk']] ?? '' ?></td>
          <td class="aksi-sel"><a class="btn btn--garis btn--kecil" href="<?= e(url('/kelola/kuis/soal/' . $s['id'])) ?>">Buka<span class="sr"> soal <?= (int) $s['id'] ?></span></a></td></tr>
      <?php endforeach; ?>
      <?php if (!$soal): ?><tr><td colspan="5" class="kosong">Bank soal masih kosong. Tambah satu soal atau impor sekaligus.</td></tr><?php endif; ?>
    </tbody></table></div>
  </section>
</div>
