<?php meta('judul', $t['judul']);
$guruTulis = fn(string $peran) => in_array($peran, ['guru', 'admin'], true);
$bolehTandai = $pemilik || $moderator; ?>
<?= View::bagian('partials/kepala', ['konteks' => ($t['kelas_id'] === null ? 'Semua kelas' : $t['kelas_nama']) . ' · ' . ($t['modul_id'] ? 'Modul ' . (int) $t['modul_nomor'] : 'Diskusi umum'), 'judul' => $t['judul'], 'kembali' => ['/diskusi' . ($t['modul_id'] ? '?modul=' . (int) $t['modul_id'] : ''), 'Semua diskusi']]) ?>
<div class="isi isi--sempit">
  <article class="card pesan pesan--utama">
    <header class="pesan-kepala">
      <span class="avatar" aria-hidden="true"><?= e(inisial($t['penulis_nama'])) ?></span>
      <div><b><?= e($t['penulis_nama']) ?></b><?= $guruTulis($t['penulis_peran']) ? ' <span class="lencana lencana--blue lencana--kecil">Guru</span>' : '' ?><span class="mini"><?= e(waktu_lalu((string) $t['dibuat_pada'])) ?></span></div>
      <div class="pesan-lencana">
        <?php if ((int) $t['disematkan']): ?><span class="lencana lencana--warn">Disematkan</span><?php endif; ?>
        <?php if ((int) $t['selesai']): ?><span class="lencana lencana--ok">Selesai</span><?php endif; ?>
      </div>
    </header>
    <div class="pesan-isi"><?= nl2br(e($t['isi'])) ?></div>
    <?php if ($t['modul_id']): ?><p class="mini" style="margin:12px 0 0">Tentang <a href="<?= e(url(Auth::pengguna()['peran'] === 'siswa' ? '/modul/' . $t['modul_id'] : '/kelola/modul/' . $t['modul_id'])) ?>">Modul <?= (int) $t['modul_nomor'] ?> · <?= e($t['modul_judul']) ?></a></p><?php endif; ?>
    <footer class="pesan-aksi">
      <?php if (!$pemilik): ?>
        <form method="post" action="<?= e(url('/diskusi/' . $t['id'] . '/ikut')) ?>"><?= csrf_field() ?><button class="btn btn--kecil <?= (int) $t['saya_ikut'] ? 'btn--utama' : 'btn--garis' ?>" type="submit" aria-pressed="<?= (int) $t['saya_ikut'] ? 'true' : 'false' ?>"><?= icon('users', 16) ?> Saya juga ingin tahu<?= (int) $t['jumlah_ikut'] ? ' · ' . (int) $t['jumlah_ikut'] : '' ?></button></form>
      <?php else: ?><span class="mini"><?= (int) $t['jumlah_ikut'] ? (int) $t['jumlah_ikut'] . ' teman juga ingin tahu' : 'Teman yang punya pertanyaan sama bisa menekan "Saya juga ingin tahu".' ?></span><?php endif; ?>
      <?php if ($bolehTandai): ?><form method="post" action="<?= e(url('/diskusi/' . $t['id'] . '/selesai')) ?>"><?= csrf_field() ?><button class="btn btn--garis btn--kecil" type="submit"><?= (int) $t['selesai'] ? 'Buka lagi' : 'Tandai selesai' ?></button></form><?php endif; ?>
      <?php if ($moderator): ?><form method="post" action="<?= e(url('/diskusi/' . $t['id'] . '/sematkan')) ?>"><?= csrf_field() ?><button class="btn btn--garis btn--kecil" type="submit"><?= (int) $t['disematkan'] ? 'Lepas sematan' : 'Sematkan' ?></button></form><?php endif; ?>
      <?php if ($pemilik || $moderator): ?><form method="post" action="<?= e(url('/diskusi/' . $t['id'] . '/hapus')) ?>" data-konfirmasi="Hapus pertanyaan ini beserta semua balasannya?" data-ya="Hapus"><?= csrf_field() ?><button class="btn btn--bahaya btn--kecil" type="submit">Hapus</button></form><?php endif; ?>
    </footer>
  </article>

  <h2 class="judul-balasan"><?= count($balasan) ? count($balasan) . ' balasan' : 'Belum ada balasan' ?></h2>
  <ol class="daftar-balasan">
    <?php foreach ($balasan as $b): $g = $guruTulis($b['penulis_peran']); ?>
      <li id="b<?= (int) $b['id'] ?>" class="card pesan<?= $g ? ' pesan--guru' : '' ?><?= (int) $b['terpilih'] ? ' pesan--terpilih' : '' ?>">
        <header class="pesan-kepala">
          <span class="avatar" aria-hidden="true"><?= e(inisial($b['penulis_nama'])) ?></span>
          <div><b><?= e($b['penulis_nama']) ?></b><?= $g ? ' <span class="lencana lencana--blue lencana--kecil">Guru</span>' : ($t['kelas_id'] === null && $b['kelas_nama'] ? ' <span class="mini">' . e($b['kelas_nama']) . '</span>' : '') ?><span class="mini"><?= e(waktu_lalu((string) $b['dibuat_pada'])) ?></span></div>
          <?php if ((int) $b['terpilih']): ?><div class="pesan-lencana"><span class="lencana lencana--ok"><?= icon('check', 14) ?> Jawaban</span></div><?php endif; ?>
        </header>
        <div class="pesan-isi"><?= nl2br(e($b['isi'])) ?></div>
        <?php if ($bolehTandai || (int) $b['penulis_id'] === (int) $u['id'] || $moderator): ?>
          <footer class="pesan-aksi">
            <?php if ($bolehTandai && (int) $b['penulis_id'] !== (int) $t['penulis_id']): ?><form method="post" action="<?= e(url('/diskusi/balasan/' . $b['id'] . '/pilih')) ?>"><?= csrf_field() ?><button class="btn btn--garis btn--kecil" type="submit"><?= (int) $b['terpilih'] ? 'Lepas tanda jawaban' : 'Tandai sebagai jawaban' ?></button></form><?php endif; ?>
            <?php if ((int) $b['penulis_id'] === (int) $u['id'] || $moderator): ?><form method="post" action="<?= e(url('/diskusi/balasan/' . $b['id'] . '/hapus')) ?>" data-konfirmasi="Hapus balasan ini?" data-ya="Hapus"><?= csrf_field() ?><button class="btn btn--bahaya btn--kecil" type="submit">Hapus</button></form><?php endif; ?>
          </footer>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ol>

  <section class="card" id="balas">
    <h2>Tulis balasan</h2>
    <form method="post" action="<?= e(url('/diskusi/' . $t['id'] . '/balas')) ?>"><?= csrf_field() ?>
      <div class="field"><label class="sr" for="isi-balas">Balasanmu</label><textarea class="input" id="isi-balas" name="isi" maxlength="3000" rows="4" required placeholder="<?= Diskusi::guru($u) ? 'Jawab atau beri petunjuk…' : 'Bantu jawab, atau tambahkan pertanyaanmu tentang hal yang sama…' ?>"><?= e($draf) ?></textarea></div>
      <button class="btn btn--utama" type="submit">Kirim balasan</button>
    </form>
  </section>
</div>
