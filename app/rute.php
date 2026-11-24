<?php
// Daftar semua alamat (rute) aplikasi. Format: $router->get('/alamat', [Controller::class, 'fungsi'], ['middleware']);
/** @var Router $router */

$router->get('/', [AuthController::class, 'landing']);
$router->get('/health', [AuthController::class, 'health']);

// --- Masuk / daftar ---
$router->get('/masuk', [AuthController::class, 'formMasuk'], ['tamu']);
$router->post('/masuk', [AuthController::class, 'masuk'], ['tamu', 'csrf']);
$router->get('/daftar', [AuthController::class, 'formDaftar'], ['tamu']);
$router->post('/daftar', [AuthController::class, 'daftar'], ['tamu', 'csrf']);
$router->get('/daftar/cek-kode', [AuthController::class, 'cekKode'], ['tamu']);
$router->post('/keluar', [AuthController::class, 'keluar'], ['login', 'csrf']);
$router->get('/akun/password', [AuthController::class, 'formPassword'], ['login']);
$router->post('/akun/password', [AuthController::class, 'ubahPassword'], ['login', 'csrf']);

// --- Siswa ---
$s = ['login', 'peran:siswa'];
$sp = ['login', 'peran:siswa', 'csrf'];
$router->get('/siswa', [SiswaController::class, 'beranda'], $s);
$router->get('/modul', [SiswaController::class, 'daftarModul'], $s);
$router->get('/modul/{id}', [SiswaController::class, 'detailModul'], $s);
$router->get('/modul/{id}/materi', [SiswaController::class, 'materi'], $s);
$router->get('/modul/{id}/materi/pdf', [SiswaController::class, 'materiPdf'], $s);
$router->get('/modul/{id}/bacaan', [SiswaController::class, 'bacaan'], $s);
$router->post('/modul/{id}/materi/progres', [SiswaController::class, 'materiProgres'], $sp);
$router->get('/modul/{id}/simulasi', [SiswaController::class, 'simulasi'], $s);
$router->post('/modul/{id}/simulasi/hasil', [SiswaController::class, 'simulasiHasil'], $sp);
$router->post('/modul/{id}/simulasi/progres', [SiswaController::class, 'simulasiProgres'], $sp);
$router->get('/modul/{id}/praktikum', [SiswaController::class, 'praktikum'], $s);
$router->post('/modul/{id}/praktikum/progres', [SiswaController::class, 'praktikumProgres'], $sp);
$router->get('/modul/{id}/lembar', [LembarController::class, 'halaman'], $s);
$router->post('/modul/{id}/lembar/simpan', [LembarController::class, 'simpan'], $sp);
$router->post('/modul/{id}/lembar/kumpul', [LembarController::class, 'kumpul'], $sp);
$router->get('/modul/{id}/refleksi', [SiswaController::class, 'refleksi'], $s);
$router->post('/modul/{id}/refleksi', [SiswaController::class, 'simpanRefleksi'], $sp);
$router->get('/modul/{id}/lkpd', [LkpdController::class, 'halaman'], $s);
$router->post('/modul/{id}/lkpd/simpan', [LkpdController::class, 'simpan'], $sp);
$router->post('/modul/{id}/lkpd/berkas', [LkpdController::class, 'unggah'], $sp);
$router->post('/modul/{id}/lkpd/kumpul', [LkpdController::class, 'kumpul'], $sp);
$router->post('/lkpd/berkas/{id}/hapus', [LkpdController::class, 'hapusBerkas'], $sp);
$router->get('/lkpd/berkas/{id}', [LkpdController::class, 'berkas'], ['login']);
$router->get('/kuis', [KuisController::class, 'daftar'], $s);
$router->get('/kuis/{jenis}', [KuisController::class, 'intro'], $s);
$router->post('/kuis/{jenis}/mulai', [KuisController::class, 'mulai'], $sp);
$router->get('/kuis/{jenis}/kerjakan', [KuisController::class, 'kerjakan'], $s);
$router->post('/kuis/{jenis}/jawab', [KuisController::class, 'jawab'], $sp);
$router->post('/kuis/{jenis}/selesai', [KuisController::class, 'selesai'], $sp);
$router->get('/kuis/{jenis}/hasil', [KuisController::class, 'hasil'], $s);
$router->get('/nilai', [SiswaController::class, 'nilai'], $s);
$router->get('/modul/{id}/remidi', [TindakController::class, 'remidi'], $s);
$router->post('/modul/{id}/remidi/simpan', [TindakController::class, 'simpanRemidi'], $sp);
$router->post('/modul/{id}/remidi/kumpul', [TindakController::class, 'kumpulRemidi'], $sp);
$router->get('/modul/{id}/pengayaan', [TindakController::class, 'pengayaan'], $s);
$router->post('/modul/{id}/pengayaan/simpan', [TindakController::class, 'simpanPengayaan'], $sp);
$router->post('/modul/{id}/pengayaan/kumpul', [TindakController::class, 'kumpulPengayaan'], $sp);
$router->get('/alat/subnet', [SiswaController::class, 'alatSubnet'], $s);

// --- Diskusi (semua pengguna yang masuk) ---
$l = ['login'];
$lp = ['login', 'csrf'];
$router->get('/diskusi', [DiskusiController::class, 'daftar'], $l);
$router->post('/diskusi', [DiskusiController::class, 'buat'], $lp);
$router->get('/diskusi/{id}', [DiskusiController::class, 'lihat'], $l);
$router->post('/diskusi/{id}/balas', [DiskusiController::class, 'balas'], $lp);
$router->post('/diskusi/{id}/ikut', [DiskusiController::class, 'ikut'], $lp);
$router->post('/diskusi/{id}/selesai', [DiskusiController::class, 'selesai'], $lp);
$router->post('/diskusi/{id}/sematkan', [DiskusiController::class, 'sematkan'], $lp);
$router->post('/diskusi/{id}/hapus', [DiskusiController::class, 'hapus'], $lp);
$router->post('/diskusi/balasan/{id}/hapus', [DiskusiController::class, 'hapusBalasan'], $lp);
$router->post('/diskusi/balasan/{id}/pilih', [DiskusiController::class, 'pilihJawaban'], $lp);

// --- Guru ---
$g = ['login', 'peran:guru,admin'];
$gp = ['login', 'peran:guru,admin', 'csrf'];
$router->get('/guru', [GuruController::class, 'beranda'], $g);
$router->get('/guru/kemajuan', [GuruController::class, 'kemajuan'], $g);
$router->get('/guru/refleksi', [GuruController::class, 'refleksi'], $g);
$router->post('/guru/lkpd-akses', [GuruController::class, 'aturLembar'], $gp);
$router->post('/guru/kelas/{id}/guru', [GuruController::class, 'tambahGuru'], $gp);
$router->post('/guru/kelas/{id}/guru/hapus', [GuruController::class, 'hapusGuru'], $gp);
$router->get('/guru/kelas', [GuruController::class, 'kelas'], $g);
$router->post('/guru/kelas', [GuruController::class, 'buatKelas'], $gp);
$router->post('/guru/kelas/{id}/kode', [GuruController::class, 'kodeBaru'], $gp);
$router->post('/guru/kelas/{id}/keluarkan', [GuruController::class, 'keluarkan'], $gp);
$router->get('/guru/penilaian', [GuruController::class, 'penilaian'], $g);
$router->get('/guru/penilaian/{id}', [GuruController::class, 'detailPenilaian'], $g);
$router->post('/guru/penilaian/{id}/nilai', [GuruController::class, 'simpanNilai'], $gp);
$router->post('/guru/penilaian/{id}/kembalikan', [GuruController::class, 'kembalikan'], $gp);
$router->get('/guru/lkpd/{id}', [GuruController::class, 'lembar'], $g);
$router->post('/guru/lkpd/{id}/nilai', [GuruController::class, 'nilaiLembar'], $gp);
$router->post('/guru/lkpd/{id}/kembalikan', [GuruController::class, 'kembalikanLembar'], $gp);
$router->get('/guru/praktikum', [GuruController::class, 'praktikum'], $g);
$router->get('/guru/analisis', [GuruController::class, 'analisis'], $g);
$router->get('/guru/analisis/unduh', [GuruController::class, 'analisisCsv'], $g);
$router->post('/guru/kelas/{id}/nilai', [GuruController::class, 'aturNilai'], $gp);
$router->get('/guru/tindak/{id}', [GuruController::class, 'tindak'], $g);
$router->post('/guru/tindak/{id}/nilai', [GuruController::class, 'nilaiTindak'], $gp);
$router->post('/guru/tindak/{id}/kembalikan', [GuruController::class, 'kembalikanTindak'], $gp);

// Kelola modul (guru dan admin)
$router->get('/kelola/modul', [AdminController::class, 'modul'], $g);
$router->get('/kelola/modul/{id}', [AdminController::class, 'formModul'], $g);
$router->post('/kelola/modul/{id}', [AdminController::class, 'simpanModul'], $gp);
$router->post('/kelola/modul/{id}/materi', [AdminController::class, 'unggahMateri'], $gp);
$router->get('/kelola/modul/{id}/dokumen/{jenis}', [KontenController::class, 'dokumen'], $g);
$router->post('/kelola/modul/{id}/dokumen/{jenis}', [KontenController::class, 'unggahDokumen'], $gp);
$router->post('/kelola/modul/{id}/dokumen/{jenis}/hapus', [KontenController::class, 'hapusDokumen'], $gp);
$router->post('/kelola/modul', [AdminController::class, 'tambahModul'], $gp);
$router->post('/kelola/modul/{id}/hapus', [KontenController::class, 'hapusModul'], $gp);
$router->post('/kelola/modul/{id}/gambar', [KontenController::class, 'unggahGambar'], $gp);
// Editor isi modul: praktikum, lkpd (pengumpulan), lembar (LKPD), refleksi, remidi, pengayaan
$router->get('/kelola/modul/{id}/isi/{jenis}', [KontenController::class, 'editor'], $g);
$router->post('/kelola/modul/{id}/isi/{jenis}', [KontenController::class, 'simpan'], $gp);
$router->post('/kelola/modul/{id}/isi/{jenis}/kosongkan', [KontenController::class, 'kosongkan'], $gp);
$router->post('/kelola/modul/{id}/isi/{jenis}/pulihkan/{rid}', [KontenController::class, 'pulihkan'], $gp);
$router->get('/kelola/simulasi', [KontenController::class, 'simulasi'], $g);

// Kelola pretest dan post-test (guru dan admin). Alamat tetap ("soal/baru") harus di atas alamat berparameter ("soal/{id}").
$router->get('/kelola/kuis', [KelolaKuisController::class, 'indeks'], $g);
$router->post('/kelola/kuis/{id}/atur', [KelolaKuisController::class, 'atur'], $gp);
$router->get('/kelola/kuis/soal/baru', [KelolaKuisController::class, 'soalBaru'], $g);
$router->post('/kelola/kuis/soal', [KelolaKuisController::class, 'soalSimpan'], $gp);
$router->get('/kelola/kuis/soal/{id}', [KelolaKuisController::class, 'soalForm'], $g);
$router->post('/kelola/kuis/soal/{id}', [KelolaKuisController::class, 'soalUbah'], $gp);
$router->post('/kelola/kuis/soal/{id}/hapus', [KelolaKuisController::class, 'soalHapus'], $gp);
$router->post('/kelola/kuis/hapus-contoh', [KelolaKuisController::class, 'hapusContoh'], $gp);
$router->get('/kelola/kuis/impor', [KelolaKuisController::class, 'impor'], $g);
$router->post('/kelola/kuis/impor', [KelolaKuisController::class, 'imporSimpan'], $gp);
$router->get('/kelola/kuis/hasil', [KelolaKuisController::class, 'hasil'], $g);
$router->get('/kelola/kuis/hasil.csv', [KelolaKuisController::class, 'csv'], $g);
$router->post('/kelola/kuis/percobaan/{id}/reset', [KelolaKuisController::class, 'reset'], $gp);

// --- Admin ---
$a = ['login', 'peran:admin'];
$ap = ['login', 'peran:admin', 'csrf'];
$router->get('/admin', [AdminController::class, 'ringkasan'], $a);
$router->get('/admin/pengguna', [AdminController::class, 'pengguna'], $a);
$router->post('/admin/guru', [AdminController::class, 'tambahGuru'], $ap);
$router->post('/admin/pengguna/{id}/reset', [AdminController::class, 'resetSandi'], $ap);
$router->post('/admin/pengguna/{id}/aktif', [AdminController::class, 'ubahAktif'], $ap);
$router->post('/admin/pengguna/{id}/siswa', [AdminController::class, 'ubahSiswa'], $ap);
$router->get('/admin/kelas', [AdminController::class, 'kelas'], $a);
$router->post('/kelola/simulasi', [KontenController::class, 'unggahSimulasi'], $ap);
$router->post('/kelola/simulasi/{folder}/hapus', [KontenController::class, 'hapusSimulasi'], $ap);
