<?php
// Dipakai juga untuk remidi/pengayaan: $label, $dasar (alamat aksi), $kembali opsional.
$label ??= 'LKPD'; $dasar ??= '/guru/lkpd/' . $lb['id'];
$kembali ??= ['/guru/penilaian?m=' . $m['id'] . ($kelas ? '&k=' . $kelas['id'] : ''), 'Daftar penilaian'];
meta('judul', $label . ' ' . $siswa['nama']);
$jaw = $lb['jawaban_arr']; $huruf = 'abcde'; $kumpul = $lb['status'] === 'dikumpulkan';
$jPg = count($def['pg'] ?? []); $poin = Lembar::poinPg($def); $benar = Lembar::jumlahBenar($def, $jaw); ?>
<?= View::bagian('partials/kepala', ['konteks' => $label . ' · Modul ' . $m['nomor'] . ($kelas ? ' · ' . $kelas['nama'] : '') . ' · NIS ' . $siswa['nis'], 'judul' => $siswa['nama'], 'sub' => $kumpul ? 'Dikumpulkan ' . hari_id($lb['dikumpulkan_pada'], true) . ', ' . date('H:i', strtotime($lb['dikumpulkan_pada'])) : 'Masih draf, belum dikumpulkan siswa.', 'kembali' => $kembali]) ?>
<div class="isi"><div class="kolom">
  <div>
    <section class="card lkpd-bagian">
      <h2>Pilihan ganda (dinilai otomatis)</h2>
      <p style="margin:0 0 10px"><b><?= $benar ?> dari <?= $jPg ?> benar</b> · skor <b class="mono"><?= $kumpul ? (int) $lb['skor_pg'] : $benar * $poin ?> / <?= $jPg * $poin ?></b></p>
      <ol class="pg-ringkas">
        <?php foreach ($def['pg'] ?? [] as $s): $pl = $jaw[$s['id']] ?? ''; $ok = $pl !== '' && (int) $pl === (int) $s['kunci']; ?>
          <li class="<?= $ok ? 'ok' : 'tidak' ?>" title="<?= e($s['soal']) ?>"><?= $pl === '' ? '–' : $huruf[(int) $pl] ?><?= $ok ? '' : '<small>kunci ' . $huruf[(int) $s['kunci']] . '</small>' ?></li>
        <?php endforeach; ?>
      </ol>
    </section>
    <section class="card lkpd-bagian">
      <h2>Uraian</h2>
      <?php foreach ($def['uraian'] ?? [] as $i => $s): $t = trim((string) ($jaw[$s['id']] ?? '')); ?>
        <div class="butir">
          <span class="label"><?= nl2br(e($s['soal'])) ?></span>
          <div class="jawaban-teks"><?= !array_key_exists($s['id'], $jaw) && $kumpul ? '<span class="mini">Soal ditambahkan setelah siswa mengumpulkan; tidak dinilai.</span>' : ($t === '' ? '<span class="mini">Tidak diisi</span>' : nl2br(e($t))) ?></div>
          <details class="contoh-jawab"><summary>Kunci / contoh jawaban</summary><p><?= nl2br(e($s['kunci'] ?? '')) ?></p></details>
        </div>
      <?php endforeach; ?>
    </section>
  </div>
  <div class="lengket">
    <section class="card"><h2>Nilai uraian</h2>
      <?php if (!$kumpul): ?><p class="mini" style="margin:0">Siswa belum mengumpulkan.</p>
      <?php else: ?>
      <form method="post" action="<?= e(url($dasar . '/nilai')) ?>"><?= csrf_field() ?>
        <?php foreach ($def['uraian'] ?? [] as $i => $s): $mk = (int) ($s['maks'] ?? 10); if (!array_key_exists($s['id'], $jaw)) { continue; } ?>
          <div class="skor-angka"><label for="u<?= $i ?>" style="flex:1">Uraian <?= $i + 1 ?></label><input class="input input--mono" id="u<?= $i ?>" name="u[<?= $i ?>]" type="number" inputmode="numeric" min="0" max="<?= $mk ?>" step="1" value="<?= isset($lb['skor_uraian_arr'][$i]) ? (int) $lb['skor_uraian_arr'][$i] : '' ?>" required> <span class="mini">/ <?= $mk ?></span></div>
        <?php endforeach; ?>
        <div class="field" style="margin-top:14px"><label for="catatan">Catatan untuk siswa</label><textarea class="input" id="catatan" name="catatan" maxlength="1000"><?= e($lb['catatan'] ?? '') ?></textarea></div>
        <button class="btn btn--utama btn--penuh" type="submit">Simpan nilai</button>
      </form>
      <p class="mini">Nilai akhir = skor pilihan ganda + skor uraian (maks <?= Lembar::maksUntuk($def, $jaw) ?>), dijadikan skala 100.</p>
      <?php if ($lb['nilai'] !== null): ?><div class="nilai-kotak" style="margin-top:10px"><b class="nilai-angka" style="font-size:36px"><?= (int) $lb['nilai'] ?></b><span><?= lencana('dinilai') ?> <span class="mini"><?= e(kategori_nilai((int) $lb['nilai'])) ?></span></span></div><?php endif; ?>
      <?php endif; ?>
    </section>
    <?php if ($kumpul): ?><section class="card"><form method="post" action="<?= e(url($dasar . '/kembalikan')) ?>" data-konfirmasi="Buka lagi <?= e($label) ?> ini? Skor dan nilainya dihapus, siswa bisa memperbaiki lalu mengumpulkan ulang."><?= csrf_field() ?><button class="btn btn--bahaya btn--penuh" type="submit">Kembalikan ke siswa</button></form></section><?php endif; ?>
  </div>
</div></div>
