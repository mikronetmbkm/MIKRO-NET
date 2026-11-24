<?php meta('judul', 'Kelas'); ?>
<?= View::bagian('partials/kepala', ['konteks' => 'Pusat kendali', 'judul' => 'Semua kelas', 'sub' => 'Daftar kelas beserta guru dan kode kelasnya.']) ?>
<div class="isi"><div class="tabel-bungkus"><table class="tabel tabel--tumpuk"><thead><tr><th scope="col">Kelas</th><th scope="col">Guru</th><th scope="col">Kode</th><th scope="col">Siswa</th></tr></thead><tbody>
  <?php foreach ($daftar as $k): ?><tr><td class="utama-sel"><b><?= e($k['nama']) ?></b></td><td data-label="Guru"><?= e($k['guru_nama_semua'] ?? $k['guru_nama']) ?></td><td data-label="Kode"><code><?= e($k['kode']) ?></code></td><td data-label="Siswa" class="mono"><?= (int) $k['jumlah_siswa'] ?></td></tr><?php endforeach; ?>
  <?php if (!$daftar): ?><tr><td colspan="4" class="kosong">Belum ada kelas. Guru membuatnya dari menu Kelas.</td></tr><?php endif; ?>
</tbody></table></div></div>
