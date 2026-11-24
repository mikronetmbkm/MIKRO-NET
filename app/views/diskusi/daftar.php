<?php meta('judul', 'Diskusi');
$guru = Diskusi::guru($u);
$qs = function (array $ubah) use ($f): string {
    $x = array_filter(array_merge($f, $ubah), fn($v) => $v !== null && $v !== '');
    return '/diskusi' . ($x ? '?' . http_build_query($x) : '');
};
$bukaForm = $tulis || $lama;
$modulPilih = $lama['modul'] ?? (ctype_digit((string) ($f['modul'] ?? '')) ? $f['modul'] : '');
$ruangPilih = $lama['ruang'] ?? (isset($f['ruang']) && isset($ruang[$f['ruang']]) ? $f['ruang'] : 'umum');
$jmlHal = (int) ceil($hasil['total'] / Diskusi::PER_HALAMAN); ?>
<?= View::bagian('partials/kepala', ['konteks' => 'Ruang diskusi', 'judul' => 'Diskusi', 'sub' => $guru ? 'Jawab pertanyaan siswa, sematkan info penting, dan tandai jawaban yang tepat.' : 'Tanya apa saja tentang materi. Teman dari kelas lain bisa ikut bertanya atau membantu menjawab, dan gurumu ikut menjawab.', 'naik' => true]) ?>
<div class="isi isi--naik">
  <details class="card tulis-diskusi" id="tulis" <?= $bukaForm ? 'open' : '' ?>>
    <summary><span class="ikon-kotak ikon-kotak--kuning" aria-hidden="true"><?= icon('chat', 22) ?></span><span><b><?= $guru ? 'Tulis pengumuman atau pertanyaan' : 'Tulis pertanyaan' ?></b><span class="mini">Bingung soal materi, praktikum, atau LKPD? Tanyakan di sini.</span></span><span class="btn btn--kuning btn--kecil" aria-hidden="true">Tulis</span></summary>
    <form method="post" action="<?= e(url('/diskusi')) ?>" class="form-diskusi"><?= csrf_field() ?>
      <div class="field"><label for="judul">Judul pertanyaan</label><input class="input" id="judul" name="judul" maxlength="160" required minlength="5" value="<?= e($lama['judul'] ?? '') ?>" placeholder="Contoh: Kenapa PC tidak mendapat IP dari DHCP?"></div>
      <div class="field"><label for="isi">Penjelasan</label><textarea class="input" id="isi" name="isi" maxlength="3000" required minlength="5" rows="4" placeholder="Ceritakan apa yang sudah kamu coba dan di mana bingungnya. Sertakan IP atau pesan error bila ada."><?= e($lama['isi'] ?? '') ?></textarea></div>
      <div class="baris2">
        <fieldset class="field pilih-ruang"><legend class="label">Siapa yang bisa melihat</legend>
          <?php foreach ($ruang as $k => $label): ?><label class="pil"><input type="radio" name="ruang" value="<?= e((string) $k) ?>" <?= (string) $ruangPilih === (string) $k ? 'checked' : '' ?>><span><?= e($label) ?></span></label><?php endforeach; ?>
        </fieldset>
        <div class="field"><label for="modul-d">Tentang</label><select class="input" id="modul-d" name="modul"><option value="">Umum (bukan modul tertentu)</option><?php foreach ($modul as $x): ?><option value="<?= (int) $x['id'] ?>" <?= (string) $modulPilih === (string) $x['id'] ? 'selected' : '' ?>>Modul <?= (int) $x['nomor'] ?> · <?= e($x['judul']) ?></option><?php endforeach; ?></select></div>
      </div>
      <p class="mini" style="margin:0 0 12px">Tulis dengan sopan. Jangan membagikan password atau data pribadi. Gurumu bisa menghapus pesan yang tidak pantas.</p>
      <button class="btn btn--utama" type="submit">Kirim pertanyaan</button>
    </form>
  </details>

  <nav class="tab tab--atas" aria-label="Ruang diskusi" style="margin-top:22px">
    <a href="<?= e(url($qs(['ruang' => null, 'hal' => null]))) ?>" <?= !isset($f['ruang']) ? 'aria-current="page"' : '' ?>>Semua</a>
    <?php foreach ($ruang as $k => $label): ?><a href="<?= e(url($qs(['ruang' => (string) $k, 'hal' => null]))) ?>" <?= ($f['ruang'] ?? '') === (string) $k ? 'aria-current="page"' : '' ?>><?= e($k === 'umum' ? 'Lintas kelas' : ($u['peran'] === 'siswa' ? 'Kelasku' : $label)) ?></a><?php endforeach; ?>
  </nav>
  <form class="penyaring saring-diskusi" method="get" action="<?= e(url('/diskusi')) ?>">
    <?php if (isset($f['ruang'])): ?><input type="hidden" name="ruang" value="<?= e($f['ruang']) ?>"><?php endif; ?>
    <div><label class="label" for="s-modul">Topik</label><select class="input" id="s-modul" name="modul" onchange="this.form.submit()"><option value="">Semua topik</option><option value="umum" <?= ($f['modul'] ?? '') === 'umum' ? 'selected' : '' ?>>Umum</option><?php foreach ($modul as $x): ?><option value="<?= (int) $x['id'] ?>" <?= ($f['modul'] ?? '') === (string) $x['id'] ? 'selected' : '' ?>>Modul <?= (int) $x['nomor'] ?></option><?php endforeach; ?></select></div>
    <div><label class="label" for="s-status">Tampilkan</label><select class="input" id="s-status" name="status" onchange="this.form.submit()"><?php foreach (['' => 'Semua pertanyaan', 'belum' => 'Belum dijawab guru', 'selesai' => 'Sudah selesai', 'saya' => $guru ? 'Yang saya tulis/ikuti' : 'Pertanyaanku'] as $k => $v): ?><option value="<?= e($k) ?>" <?= ($f['status'] ?? '') === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div class="saring-cari"><label class="label" for="s-q">Cari</label><input class="input" id="s-q" name="q" type="search" maxlength="60" value="<?= e($f['q'] ?? '') ?>" placeholder="Kata kunci, mis. gateway"></div>
    <button class="btn btn--garis" type="submit">Cari</button>
  </form>

  <?php if (!$hasil['baris']): ?>
    <section class="card kosong-besar" style="margin-top:18px">
      <span class="ikon-kotak ikon-kotak--navy"><?= icon('chat', 28) ?></span>
      <h2><?= $f ? 'Tidak ada diskusi yang cocok' : 'Belum ada diskusi' ?></h2>
      <p><?= $f ? 'Coba ubah saringan atau kata kuncinya.' : 'Jadilah yang pertama bertanya. Pertanyaanmu mungkin juga sedang dipikirkan temanmu.' ?></p>
      <a class="btn btn--kuning" href="<?= e(url($qs(['tulis' => '1']) . '#tulis')) ?>">Tulis pertanyaan</a>
    </section>
  <?php else: ?>
    <p class="mini" style="margin:16px 0 10px"><?= (int) $hasil['total'] ?> diskusi</p>
    <ul class="daftar-diskusi">
      <?php foreach ($hasil['baris'] as $t): $guruPenulis = in_array($t['penulis_peran'], ['guru', 'admin'], true); ?>
        <li class="card diskusi-item<?= (int) $t['disematkan'] ? ' diskusi-item--sematan' : '' ?>">
          <div class="diskusi-utama">
            <div class="diskusi-label">
              <?php if ((int) $t['disematkan']): ?><span class="lencana lencana--warn">Disematkan</span><?php endif; ?>
              <span class="lencana lencana--<?= $t['kelas_id'] === null ? 'blue' : 'grey' ?>"><?= $t['kelas_id'] === null ? 'Semua kelas' : e($t['kelas_nama']) ?></span>
              <span class="chip"><?= $t['modul_id'] ? 'Modul ' . (int) $t['modul_nomor'] : 'Umum' ?></span>
              <?php if ((int) $t['selesai']): ?><span class="lencana lencana--ok">Selesai</span><?php elseif ((int) $t['balasan_guru']): ?><span class="lencana lencana--ok">Dijawab guru</span><?php endif; ?>
            </div>
            <h2><a href="<?= e(url('/diskusi/' . $t['id'])) ?>"><?= e($t['judul']) ?></a></h2>
            <p class="diskusi-cuplik"><?= e(mb_strimwidth(preg_replace('/\s+/', ' ', (string) $t['isi']), 0, 160, '…')) ?></p>
            <p class="mini diskusi-meta"><b><?= e($t['penulis_nama']) ?></b><?= $guruPenulis ? ' <span class="lencana lencana--blue lencana--kecil">Guru</span>' : '' ?> · <?= e(waktu_lalu((string) $t['dibuat_pada'])) ?><?= $t['aktivitas_pada'] !== $t['dibuat_pada'] ? ' · balasan terakhir ' . e(waktu_lalu((string) $t['aktivitas_pada'])) : '' ?></p>
          </div>
          <div class="diskusi-angka">
            <span title="Balasan"><?= icon('chat', 16) ?> <b><?= (int) $t['jumlah_balasan'] ?></b><span class="sr"> balasan</span></span>
            <span title="Ikut ingin tahu" class="<?= (int) $t['saya_ikut'] ? 'aktif' : '' ?>"><?= icon('users', 16) ?> <b><?= (int) $t['jumlah_ikut'] ?></b><span class="sr"> siswa juga ingin tahu</span></span>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
    <?php if ($jmlHal > 1): ?><nav class="halaman" aria-label="Halaman">
      <?php if ($hal > 1): ?><a class="btn btn--garis btn--kecil" href="<?= e(url($qs(['hal' => (string) ($hal - 1)]))) ?>">Sebelumnya</a><?php endif; ?>
      <span class="mini">Halaman <?= $hal ?> dari <?= $jmlHal ?></span>
      <?php if ($hal < $jmlHal): ?><a class="btn btn--garis btn--kecil" href="<?= e(url($qs(['hal' => (string) ($hal + 1)]))) ?>">Berikutnya</a><?php endif; ?>
    </nav><?php endif; ?>
  <?php endif; ?>
</div>
