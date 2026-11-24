<?php meta('judul', 'Nilai ' . $siswa['nama']); $rubrik = $def['rubrik'] ?? []; $jaw = $pg['jawaban_arr']; $sudahKumpul = $pg['status'] === 'dikumpulkan'; ?>
<?= View::bagian('partials/kepala', ['konteks' => 'Modul ' . $m['nomor'] . ($kelas ? ' · ' . $kelas['nama'] : '') . ' · NIS ' . $siswa['nis'], 'judul' => $siswa['nama'], 'sub' => $sudahKumpul ? 'Dikumpulkan ' . hari_id($pg['dikumpulkan_pada'], true) . ', ' . date('H:i', strtotime($pg['dikumpulkan_pada'])) : 'Masih draf, belum dikumpulkan siswa.', 'kembali' => ['/guru/penilaian?m=' . $m['id'] . ($kelas ? '&k=' . $kelas['id'] : ''), 'Daftar penilaian']]) ?>
<div class="isi"><div class="kolom">
  <div>
    <?php foreach ($def['bagian'] ?? [] as $bagian): ?>
      <section class="card lkpd-bagian"><h2><?= e($bagian['judul'] ?? '') ?></h2>
        <?php foreach ($bagian['butir'] ?? [] as $b): $id = $b['id']; ?>
          <div class="butir"><span class="label"><?= e($b['label']) ?></span>
            <?php if ($b['jenis'] === 'berkas'): ?>
              <div class="berkas-daftar"><?php foreach ($berkas[$id] ?? [] as $f): ?>
                <div class="berkas-item"><?php if (str_starts_with($f['mime'], 'image/')): ?><a href="<?= e(url('/lkpd/berkas/' . $f['id'])) ?>" target="_blank" rel="noopener"><img src="<?= e(url('/lkpd/berkas/' . $f['id'])) ?>" alt="Pratinjau <?= e($f['nama_asli']) ?>"></a><?php else: ?><?= icon('file', 22) ?><?php endif; ?>
                  <a href="<?= e(url('/lkpd/berkas/' . $f['id'])) ?>" target="_blank" rel="noopener"><?= e($f['nama_asli']) ?></a></div>
              <?php endforeach; ?></div><?php if (empty($berkas[$id])): ?><p class="mini" style="margin:0">Tidak ada berkas.</p><?php endif; ?>
            <?php else: $v = trim((string) ($jaw[$id] ?? '')); ?>
              <div class="jawaban-teks"><?= $v === '' ? '<span class="mini">Tidak diisi</span>' : e($v) ?></div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </section>
    <?php endforeach; ?>
    <section class="card lkpd-bagian"><h2>Refleksi siswa</h2>
      <?= View::bagian('partials/refleksi-jawaban', ['refleksi' => $refleksi, 'tujuan' => $tujuan, 'khusus' => Modul::refleksiKhusus((int) $m['nomor'])]) ?>
    </section>
  </div>
  <div class="lengket">
    <section class="card"><h2>Penilaian rubrik</h2>
      <?php if (!$rubrik): ?><p class="mini" style="margin:0">Rubrik untuk modul ini belum ada di berkas LKPD.</p>
      <?php elseif (!$sudahKumpul): ?><p class="mini" style="margin:0">Siswa belum mengumpulkan. Penilaian bisa dilakukan setelah dikumpulkan.</p>
      <?php else: ?>
      <form method="post" action="<?= e(url('/guru/penilaian/' . $pg['id'] . '/nilai')) ?>"><?= csrf_field() ?>
        <div class="rubrik-kotak">
        <?php foreach ($rubrik as $i => $r): ?>
          <fieldset class="rubrik" style="margin:0"><legend class="sr"><?= e($r['kriteria']) ?></legend><b aria-hidden="true"><?= e($r['kriteria']) ?></b>
            <?php if (isset($r['maks'])): $mk = (int) $r['maks']; ?>
              <div class="skor-angka"><label class="sr" for="r<?= (int) $i ?>">Skor <?= e($r['kriteria']) ?></label><input class="input input--mono" id="r<?= (int) $i ?>" name="r[<?= (int) $i ?>]" type="number" inputmode="numeric" min="0" max="<?= $mk ?>" step="1" value="<?= isset($rubrikSkor[$i]) ? (int) $rubrikSkor[$i] : '' ?>" required> <span class="mini">dari <?= $mk ?></span></div>
            <?php else: ?><div class="skala"><?php for ($s = 1; $s <= 4; $s++): ?><label><input type="radio" name="r[<?= (int) $i ?>]" value="<?= $s ?>" <?= (int) ($rubrikSkor[$i] ?? 0) === $s ? 'checked' : '' ?> required><strong><?= $s ?></strong><br><?= e($r['deskriptor'][(string) $s] ?? '') ?></label><?php endfor; ?></div><?php endif; ?></fieldset>
        <?php endforeach; ?>
        </div>
        <div class="field" style="margin-top:16px"><label for="catatan">Catatan untuk siswa</label><textarea class="input" id="catatan" name="catatan" maxlength="1000"><?= e($pg['catatan'] ?? '') ?></textarea></div>
        <button class="btn btn--utama btn--penuh" type="submit">Simpan nilai</button>
      </form>
      <?php if ($pg['nilai'] !== null): ?><div class="nilai-kotak" style="margin-top:14px"><b class="nilai-angka" style="font-size:36px"><?= (int) $pg['nilai'] ?></b><span><?= lencana('dinilai') ?> <span class="mini"><?= e(kategori_nilai((int) $pg['nilai'])) ?></span></span></div><?php endif; ?>
      <?php endif; ?>
    </section>
    <?php if ($sudahKumpul): ?><section class="card"><form method="post" action="<?= e(url('/guru/penilaian/' . $pg['id'] . '/kembalikan')) ?>" data-konfirmasi="Kembalikan ke siswa untuk diperbaiki?"><?= csrf_field() ?><button class="btn btn--bahaya btn--penuh" type="submit">Kembalikan ke siswa</button><p class="hint" style="margin-bottom:0">LKPD dibuka lagi supaya siswa bisa memperbaiki dan mengumpulkan ulang.</p></form></section><?php endif; ?>
  </div>
</div></div>
