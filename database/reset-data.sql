-- RESET DATA sebelum dipakai sungguhan / dipindah ke hosting.
-- Menghapus: SEMUA akun (admin, guru, siswa), kelas, kemajuan belajar, refleksi, pengumpulan + daftar berkas, LKPD,
--            jawaban dan skor pretest/post-test, catatan percobaan login.
-- Dipertahankan: modul (judul, tujuan, tanggal, status, game, PDF materi yang diunggah), pretest/post-test
--            beserta pengaturannya, dan bank soal.
-- SETELAH ini WAJIB impor database/admin-awal.sql (edit email-nya dulu), kalau tidak, tidak ada yang bisa masuk.
-- Berkas unggahan siswa di storage/uploads/lkpd/ hapus manual lewat File Manager (berkas .gitkeep biarkan).

DELETE FROM diskusi_ikut;
DELETE FROM diskusi_balasan;
DELETE FROM diskusi_topik;
DELETE FROM kuis_percobaan;
DELETE FROM tindak_lanjut;
DELETE FROM lembar_kerja;
DELETE FROM lkpd_berkas;
DELETE FROM lkpd_pengumpulan;
DELETE FROM modul_siswa;
DELETE FROM lembar_akses;
DELETE FROM kelas_guru;
DELETE FROM kelas_anggota;
DELETE FROM kelas;
DELETE FROM login_percobaan;
DELETE FROM users;
-- Kuis kembali berstatus Draf supaya tidak langsung terbuka untuk siswa baru. Hapus baris ini bila ingin tetap seperti sekarang.
UPDATE kuis SET status = 'draf';
