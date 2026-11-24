-- =====================================================================
-- MIKRO-NET · SETUP LAPTOP (dengan akun uji)
-- Satu berkas untuk membuat ULANG seluruh database dari nol.
-- Cara: phpMyAdmin → klik database (mis. mikronet) → tab Import → pilih berkas ini → Go.
-- PERINGATAN: semua tabel MIKRO-NET yang sudah ada DIHAPUS dulu (akun, progres, nilai, soal ikut hilang).
-- Akun uji: admin@mikronet.test / Admin12345 · guru1@mikronet.test / Guru12345 · siswa1@mikronet.test / Siswa12345 · kode kelas TKJ1-7K2Q
-- JANGAN dipakai di hosting.
-- =====================================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS lembar_akses;
DROP TABLE IF EXISTS lembar_kerja;
DROP TABLE IF EXISTS kuis_percobaan;
DROP TABLE IF EXISTS kuis_soal;
DROP TABLE IF EXISTS kuis;
DROP TABLE IF EXISTS login_percobaan;
DROP TABLE IF EXISTS diskusi_ikut;
DROP TABLE IF EXISTS diskusi_balasan;
DROP TABLE IF EXISTS diskusi_topik;
DROP TABLE IF EXISTS lkpd_berkas;
DROP TABLE IF EXISTS lkpd_pengumpulan;
DROP TABLE IF EXISTS modul_siswa;
DROP TABLE IF EXISTS tindak_lanjut;
DROP TABLE IF EXISTS modul_konten_riwayat;
DROP TABLE IF EXISTS modul_konten;
DROP TABLE IF EXISTS modul;
DROP TABLE IF EXISTS kelas_anggota;
DROP TABLE IF EXISTS kelas_guru;
DROP TABLE IF EXISTS kelas;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------- 1. Struktur tabel (schema.sql) ----------
-- Struktur database MIKRO-NET (MySQL / MariaDB, utf8mb4).
-- Ditulis dengan SQL sederhana supaya sama di MySQL (hosting) dan SQLite (pengujian otomatis).
-- Impor lewat phpMyAdmin: pilih database "mikronet" > Import > berkas ini.

CREATE TABLE users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(100) NOT NULL,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  peran VARCHAR(10) NOT NULL,                       -- admin | guru | siswa
  nis VARCHAR(30) NULL,                             -- hanya siswa
  aktif TINYINT NOT NULL DEFAULT 1,
  harus_ganti_password TINYINT NOT NULL DEFAULT 0,  -- 1 = sandi sementara, wajib diganti saat masuk
  dibuat_pada DATETIME NOT NULL,
  diperbarui_pada DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE UNIQUE INDEX ux_users_email ON users (email);
CREATE INDEX ix_users_nis ON users (nis);                -- NIS cukup unik di dalam satu kelas (dicek aplikasi), boleh sama di kelas lain

CREATE TABLE kelas (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(60) NOT NULL,
  kode VARCHAR(20) NOT NULL,                        -- kode yang dibagikan guru ke siswa
  guru_id INT UNSIGNED NOT NULL,
  aktif TINYINT NOT NULL DEFAULT 1,
  dibuat_pada DATETIME NOT NULL,
  kkm INT NOT NULL DEFAULT 75,                      -- kriteria ketuntasan minimal kelas ini
  bobot_praktik INT NOT NULL DEFAULT 40,            -- bobot nilai akhir modul (persen): pengumpulan praktik
  bobot_lkpd INT NOT NULL DEFAULT 40,               -- LKPD (PG + uraian)
  bobot_simulasi INT NOT NULL DEFAULT 20,           -- skor simulasi
  FOREIGN KEY (guru_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE UNIQUE INDEX ux_kelas_kode ON kelas (kode);

-- Guru pendamping: satu kelas bisa diajar lebih dari satu guru (kelas.guru_id = guru pembuat kelas).
CREATE TABLE kelas_guru (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  kelas_id INT UNSIGNED NOT NULL,
  guru_id INT UNSIGNED NOT NULL,
  ditambahkan_pada DATETIME NOT NULL,
  FOREIGN KEY (kelas_id) REFERENCES kelas (id) ON DELETE CASCADE,
  FOREIGN KEY (guru_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE UNIQUE INDEX ux_kelas_guru ON kelas_guru (kelas_id, guru_id);

CREATE TABLE kelas_anggota (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  kelas_id INT UNSIGNED NOT NULL,
  siswa_id INT UNSIGNED NOT NULL,
  bergabung_pada DATETIME NOT NULL,
  FOREIGN KEY (kelas_id) REFERENCES kelas (id) ON DELETE CASCADE,
  FOREIGN KEY (siswa_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE UNIQUE INDEX ux_anggota_siswa ON kelas_anggota (siswa_id);  -- satu siswa = satu kelas
CREATE INDEX ix_anggota_kelas ON kelas_anggota (kelas_id);

CREATE TABLE modul (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  nomor INT NOT NULL,
  judul VARCHAR(200) NOT NULL,
  ringkasan VARCHAR(300) NULL,
  tujuan TEXT NULL,                                 -- satu tujuan per baris
  tanggal DATE NULL,                                -- tanggal pertemuan
  status VARCHAR(10) NOT NULL DEFAULT 'terkunci',   -- terbit | segera | terkunci
  ikon VARCHAR(20) NULL,
  materi_berkas VARCHAR(255) NULL,                  -- nama berkas PDF di storage/uploads/materi
  pretest_url VARCHAR(500) NULL,                    -- tidak dipakai lagi (pretest kini ada di menu Kuis); dibiarkan agar database lama tetap cocok
  game_folder VARCHAR(100) NULL,                    -- nama folder di public/games
  video_url VARCHAR(500) NULL,                      -- link video tambahan dari guru (YouTube atau https), tampil di langkah Materi
  modul_ajar_berkas VARCHAR(255) NULL,              -- modul ajar (PDF, khusus guru) unggahan; kosong = database/content/modul-ajar-pN.pdf
  bacaan_berkas VARCHAR(255) NULL,                  -- bahan bacaan siswa (PDF) unggahan; kosong = database/content/bacaan-pN.pdf
  diperbarui_pada DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE UNIQUE INDEX ux_modul_nomor ON modul (nomor);

-- Isi modul yang diedit guru (praktikum, lkpd, lembar, refleksi, remidi, pengayaan) dalam format JSON.
-- Bila belum ada baris, isi dibaca dari berkas bawaan database/content/{jenis}-p{nomor}.json.
CREATE TABLE modul_konten (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  modul_id INT UNSIGNED NOT NULL,
  jenis VARCHAR(20) NOT NULL,
  isi MEDIUMTEXT NOT NULL,
  diubah_oleh INT UNSIGNED NULL,
  diubah_pada DATETIME NOT NULL,
  FOREIGN KEY (modul_id) REFERENCES modul (id) ON DELETE CASCADE,
  FOREIGN KEY (diubah_oleh) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE UNIQUE INDEX ux_modul_konten ON modul_konten (modul_id, jenis);

CREATE TABLE modul_konten_riwayat (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  modul_id INT UNSIGNED NOT NULL,
  jenis VARCHAR(20) NOT NULL,
  isi MEDIUMTEXT NOT NULL,
  diubah_oleh INT UNSIGNED NULL,
  diubah_pada DATETIME NOT NULL,
  FOREIGN KEY (modul_id) REFERENCES modul (id) ON DELETE CASCADE,
  FOREIGN KEY (diubah_oleh) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX ix_modul_konten_riwayat ON modul_konten_riwayat (modul_id, jenis);

-- Remidi dan pengayaan: jawaban siswa atas soal tindak lanjut per modul.
CREATE TABLE tindak_lanjut (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  modul_id INT UNSIGNED NOT NULL,
  siswa_id INT UNSIGNED NOT NULL,
  jenis VARCHAR(12) NOT NULL,                       -- remidi | pengayaan
  jawaban TEXT NOT NULL,
  status VARCHAR(12) NOT NULL DEFAULT 'draf',       -- draf | dikumpulkan
  skor_pg INT NULL,
  skor_uraian TEXT NULL,
  nilai INT NULL,
  catatan TEXT NULL,
  dikumpulkan_pada DATETIME NULL,
  dinilai_pada DATETIME NULL,
  dibuat_pada DATETIME NOT NULL,
  diperbarui_pada DATETIME NOT NULL,
  FOREIGN KEY (modul_id) REFERENCES modul (id) ON DELETE CASCADE,
  FOREIGN KEY (siswa_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE UNIQUE INDEX ux_tindak_lanjut ON tindak_lanjut (modul_id, siswa_id, jenis);

CREATE TABLE modul_siswa (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  modul_id INT UNSIGNED NOT NULL,
  siswa_id INT UNSIGNED NOT NULL,
  halaman_terakhir INT NOT NULL DEFAULT 0,
  total_halaman INT NOT NULL DEFAULT 0,
  pretest_selesai TINYINT NOT NULL DEFAULT 0,      -- tidak dipakai lagi
  simulasi_selesai TINYINT NOT NULL DEFAULT 0,
  game_skor INT NULL,
  game_skor_maks INT NULL,
  refleksi TEXT NULL,                               -- JSON refleksi siswa (langkah 5): paham, bisa[], penting, bingung, tanya
  praktikum_langkah INT NOT NULL DEFAULT 0,         -- langkah praktikum terjauh yang dibuka siswa
  praktikum_selesai TINYINT NOT NULL DEFAULT 0,
  game_data MEDIUMTEXT NULL,                        -- progres game simulasi (isi window.name game), supaya tidak mengulang dari awal
  diperbarui_pada DATETIME NOT NULL,
  FOREIGN KEY (modul_id) REFERENCES modul (id) ON DELETE CASCADE,
  FOREIGN KEY (siswa_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE UNIQUE INDEX ux_modul_siswa ON modul_siswa (modul_id, siswa_id);

CREATE TABLE lkpd_pengumpulan (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  modul_id INT UNSIGNED NOT NULL,
  siswa_id INT UNSIGNED NOT NULL,
  jawaban LONGTEXT NOT NULL,                        -- JSON: {"id_butir": "jawaban", ...}
  status VARCHAR(15) NOT NULL DEFAULT 'draf',       -- draf | dikumpulkan
  dikumpulkan_pada DATETIME NULL,
  rubrik_skor VARCHAR(255) NULL,                    -- JSON daftar skor rubrik, mis. [3,4,3]
  skor INT NULL,
  skor_maks INT NULL,
  nilai INT NULL,                                   -- skor / skor_maks x 100
  catatan TEXT NULL,
  dinilai_pada DATETIME NULL,
  dibuat_pada DATETIME NOT NULL,
  diperbarui_pada DATETIME NOT NULL,
  FOREIGN KEY (modul_id) REFERENCES modul (id) ON DELETE CASCADE,
  FOREIGN KEY (siswa_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE UNIQUE INDEX ux_lkpd_modul_siswa ON lkpd_pengumpulan (modul_id, siswa_id);

CREATE TABLE lkpd_berkas (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  pengumpulan_id INT UNSIGNED NOT NULL,
  butir_id VARCHAR(60) NOT NULL,
  nama_asli VARCHAR(255) NOT NULL,
  jalan VARCHAR(255) NOT NULL,                      -- nama acak di storage/uploads/lkpd
  mime VARCHAR(100) NOT NULL,
  ukuran INT UNSIGNED NOT NULL,
  dibuat_pada DATETIME NOT NULL,
  FOREIGN KEY (pengumpulan_id) REFERENCES lkpd_pengumpulan (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX ix_berkas_pengumpulan ON lkpd_berkas (pengumpulan_id);

-- Ruang diskusi: pertanyaan (topik) dan balasan. kelas_id NULL = terlihat semua kelas; modul_id NULL = diskusi umum.
CREATE TABLE diskusi_topik (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  kelas_id INT UNSIGNED NULL,
  modul_id INT UNSIGNED NULL,
  penulis_id INT UNSIGNED NOT NULL,
  judul VARCHAR(160) NOT NULL,
  isi TEXT NOT NULL,
  disematkan TINYINT NOT NULL DEFAULT 0,
  selesai TINYINT NOT NULL DEFAULT 0,
  dibuat_pada DATETIME NOT NULL,
  aktivitas_pada DATETIME NOT NULL,                 -- waktu balasan terakhir, untuk urutan daftar
  FOREIGN KEY (kelas_id) REFERENCES kelas (id) ON DELETE CASCADE,
  FOREIGN KEY (modul_id) REFERENCES modul (id) ON DELETE SET NULL,
  FOREIGN KEY (penulis_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX ix_diskusi_topik_aktivitas ON diskusi_topik (aktivitas_pada);

CREATE TABLE diskusi_balasan (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  topik_id INT UNSIGNED NOT NULL,
  penulis_id INT UNSIGNED NOT NULL,
  isi TEXT NOT NULL,
  terpilih TINYINT NOT NULL DEFAULT 0,              -- ditandai sebagai jawaban
  dibuat_pada DATETIME NOT NULL,
  FOREIGN KEY (topik_id) REFERENCES diskusi_topik (id) ON DELETE CASCADE,
  FOREIGN KEY (penulis_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX ix_diskusi_balasan_topik ON diskusi_balasan (topik_id);

-- "Saya juga ingin tahu": siswa lain ikut menanyakan topik yang sama.
CREATE TABLE diskusi_ikut (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  topik_id INT UNSIGNED NOT NULL,
  pengguna_id INT UNSIGNED NOT NULL,
  dibuat_pada DATETIME NOT NULL,
  FOREIGN KEY (topik_id) REFERENCES diskusi_topik (id) ON DELETE CASCADE,
  FOREIGN KEY (pengguna_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE UNIQUE INDEX ux_diskusi_ikut ON diskusi_ikut (topik_id, pengguna_id);

CREATE TABLE login_percobaan (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL,
  ip VARCHAR(45) NOT NULL,
  dibuat_pada DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX ix_percobaan ON login_percobaan (email, ip, dibuat_pada);

-- ---- Pretest dan post-test (satu pasang untuk Modul 1-4) ----
CREATE TABLE kuis (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  jenis VARCHAR(10) NOT NULL,                       -- pretest | posttest
  judul VARCHAR(120) NOT NULL,
  status VARCHAR(10) NOT NULL DEFAULT 'draf',       -- draf | dibuka | ditutup
  durasi_menit INT NOT NULL DEFAULT 30,
  acak TINYINT NOT NULL DEFAULT 1,                  -- 1 = urutan soal dan pilihan diacak per siswa
  diperbarui_pada DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE UNIQUE INDEX ux_kuis_jenis ON kuis (jenis);

CREATE TABLE kuis_soal (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  urutan INT NOT NULL DEFAULT 0,
  modul_nomor INT NOT NULL,                         -- soal ini menguji modul nomor berapa
  teks TEXT NOT NULL,
  opsi TEXT NOT NULL,                               -- JSON: ["pilihan A","pilihan B",...]
  kunci INT NOT NULL,                               -- indeks jawaban benar (0 = A)
  untuk VARCHAR(6) NOT NULL DEFAULT 'semua',        -- semua | pre | post
  contoh TINYINT NOT NULL DEFAULT 0,                -- 1 = soal contoh bawaan
  dibuat_pada DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX ix_soal_modul ON kuis_soal (modul_nomor);

CREATE TABLE kuis_percobaan (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  kuis_id INT UNSIGNED NOT NULL,
  siswa_id INT UNSIGNED NOT NULL,
  mulai_pada DATETIME NOT NULL,
  batas_pada DATETIME NOT NULL,                     -- waktu habis (dihitung server)
  selesai_pada DATETIME NULL,
  susunan TEXT NOT NULL,                            -- JSON: urutan soal dan urutan pilihan milik siswa ini
  jawaban TEXT NOT NULL,                            -- JSON: {"id_soal": indeks_pilihan_asli}
  skor INT NULL,                                    -- 0-100
  benar INT NULL,
  total INT NULL,
  per_modul TEXT NULL,                              -- JSON: {"1":[benar,total],...}
  FOREIGN KEY (kuis_id) REFERENCES kuis (id) ON DELETE CASCADE,
  FOREIGN KEY (siswa_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE UNIQUE INDEX ux_percobaan ON kuis_percobaan (kuis_id, siswa_id);

CREATE TABLE lembar_kerja (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  modul_id INT UNSIGNED NOT NULL,
  siswa_id INT UNSIGNED NOT NULL,
  jawaban LONGTEXT NOT NULL,                        -- JSON: {"pg1": "2", "u1": "teks", ...}
  status VARCHAR(15) NOT NULL DEFAULT 'draf',       -- draf | dikumpulkan
  skor_pg INT NULL,                                 -- dihitung otomatis saat dikumpulkan
  skor_uraian VARCHAR(255) NULL,                    -- JSON daftar skor uraian dari guru, mis. [8,10,7,9,10]
  nilai INT NULL,                                   -- skor_pg + jumlah skor uraian (maks 100)
  catatan TEXT NULL,
  dikumpulkan_pada DATETIME NULL,
  dinilai_pada DATETIME NULL,
  dibuat_pada DATETIME NOT NULL,
  diperbarui_pada DATETIME NOT NULL,
  FOREIGN KEY (modul_id) REFERENCES modul (id) ON DELETE CASCADE,
  FOREIGN KEY (siswa_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE UNIQUE INDEX ux_lembar_modul_siswa ON lembar_kerja (modul_id, siswa_id);

-- Buka/tutup LKPD per kelas per modul oleh guru. Tidak ada baris = terbuka.
CREATE TABLE lembar_akses (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  kelas_id INT UNSIGNED NOT NULL,
  modul_id INT UNSIGNED NOT NULL,
  buka TINYINT NOT NULL DEFAULT 1,
  diubah_oleh INT UNSIGNED NULL,
  diubah_pada DATETIME NOT NULL,
  FOREIGN KEY (kelas_id) REFERENCES kelas (id) ON DELETE CASCADE,
  FOREIGN KEY (modul_id) REFERENCES modul (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE UNIQUE INDEX ux_lembar_akses ON lembar_akses (kelas_id, modul_id);

-- ---------- 2. Data 10 modul (seed-modul.sql) ----------
-- Data 10 modul (dipakai di semua lingkungan, termasuk hosting).
INSERT INTO modul (nomor, judul, ringkasan, tujuan, tanggal, status, ikon, game_folder, diperbarui_pada) VALUES (1, 'Penguatan IP address dan Cisco dasar', 'IP address, subnetting, dan LAN pertama di Packet Tracer.', 'Menjelaskan konsep IP address, kelas IP, dan CIDR.
Menghitung subnetting dasar.
Mengoperasikan dasar Cisco Packet Tracer.
Membangun dan menguji LAN sederhana dalam simulasi.', '2026-10-05', 'terbit', 'globe', 'simulasi-p1', '2026-09-30 00:00:00');
INSERT INTO modul (nomor, judul, ringkasan, tujuan, tanggal, status, ikon, game_folder, diperbarui_pada) VALUES (2, 'VLAN, DHCP, VLSM, dan static route', 'Membangun jaringan dua router dengan VLAN, router-on-a-stick, DHCP, VLSM, dan static route di Cisco Packet Tracer.', 'Menjelaskan fungsi switch, router, dan access point dalam jaringan.
Menjelaskan konsep topologi dan segmentasi jaringan.
Mengidentifikasi permasalahan umum pada jaringan LAN.
Mengonfigurasi VLAN dan inter-VLAN routing (router-on-a-stick).
Mengonfigurasi DHCP server di router.
Menentukan pembagian subnet dengan VLSM sesuai kebutuhan host.
Mengonfigurasi static route dan menguji koneksi antar-jaringan.', '2026-10-12', 'terbit', 'network', 'simulasi-p2', '2026-10-08 00:00:00');
INSERT INTO modul (nomor, judul, ringkasan, tujuan, tanggal, status, ikon, game_folder, diperbarui_pada) VALUES (3, 'MikroTik dan Winbox', 'Mengonfigurasi router MikroTik dengan Winbox: IP address, gateway, uji koneksi antar-PC, akses internet (DHCP Client, DNS, NAT), dan firewall filter.', 'Memahami konfigurasi jaringan menggunakan router MikroTik dan aplikasi Winbox.
Mengatur IP address pada interface router.
Mengatur IP address dan gateway pada PC1 dan PC2.
Menghubungkan jaringan lokal dengan internet melalui DHCP Client.
Mengonfigurasi DNS dan NAT masquerade.
Mengonfigurasi firewall filter sederhana.
Melakukan pengujian konektivitas jaringan.', '2026-10-12', 'terbit', 'router', 'simulasi-p3', '2026-10-11 00:00:00');
INSERT INTO modul (nomor, judul, ringkasan, tujuan, tanggal, status, ikon, game_folder, diperbarui_pada) VALUES (4, 'Wajanbolic dan link wireless Point-to-Point', 'Merakit antena wajanbolic, membangun link nirkabel Point-to-Point antara dua laptop, lalu membuktikan dengan data bahwa wajan memperkuat sinyal.', 'Menjelaskan konsep komunikasi wireless Point-to-Point dan perbedaannya dengan access point biasa.
Menjelaskan prinsip kerja antena wajanbolic, frekuensi 2,4 GHz, polarisasi, dan line of sight.
Mengukur diameter dan kedalaman wajan, lalu menghitung panjang feeder F = D² / (16 × d).
Merakit antena wajanbolic sesuai perhitungan dan prosedur keselamatan kerja.
Mengonfigurasi laptop pemancar sebagai hotspot dan laptop penerima sebagai klien.
Menguji link dengan ipconfig, ping, dan netsh wlan show interfaces pada jarak berbeda.
Membandingkan data tanpa dan dengan wajan, lalu menganalisis penyebab link yang lemah.', '2026-10-21', 'terbit', 'dish', 'simulasi-p4', '2026-10-20 00:00:00');
INSERT INTO modul (nomor, judul, ringkasan, tujuan, tanggal, status, ikon, game_folder, diperbarui_pada) VALUES (5, 'Segera hadir', NULL, NULL, NULL, 'terkunci', 'lock', NULL, '2026-09-30 00:00:00');
INSERT INTO modul (nomor, judul, ringkasan, tujuan, tanggal, status, ikon, game_folder, diperbarui_pada) VALUES (6, 'Segera hadir', NULL, NULL, NULL, 'terkunci', 'lock', NULL, '2026-09-30 00:00:00');
INSERT INTO modul (nomor, judul, ringkasan, tujuan, tanggal, status, ikon, game_folder, diperbarui_pada) VALUES (7, 'Segera hadir', NULL, NULL, NULL, 'terkunci', 'lock', NULL, '2026-09-30 00:00:00');
INSERT INTO modul (nomor, judul, ringkasan, tujuan, tanggal, status, ikon, game_folder, diperbarui_pada) VALUES (8, 'Segera hadir', NULL, NULL, NULL, 'terkunci', 'lock', NULL, '2026-09-30 00:00:00');
INSERT INTO modul (nomor, judul, ringkasan, tujuan, tanggal, status, ikon, game_folder, diperbarui_pada) VALUES (9, 'Segera hadir', NULL, NULL, NULL, 'terkunci', 'lock', NULL, '2026-09-30 00:00:00');
INSERT INTO modul (nomor, judul, ringkasan, tujuan, tanggal, status, ikon, game_folder, diperbarui_pada) VALUES (10, 'Segera hadir', NULL, NULL, NULL, 'terkunci', 'lock', NULL, '2026-09-30 00:00:00');

-- ---------- 3. Pretest, post-test, 30 soal (seed-kuis.sql) ----------
-- Dua kuis (pretest, post-test) dan 30 soal ASLI dari berkas Soal Pretest MIKRO-NET.
-- Soal yang sama dipakai untuk pretest dan post-test (untuk='semua'). Modul 0 = materi umum jaringan.
-- Status awal 'draf': buka dari menu Kuis setelah semuanya siap.
INSERT INTO kuis (jenis, judul, status, durasi_menit, acak, diperbarui_pada) VALUES ('pretest', 'Pretest MIKRO-NET', 'draf', 30, 1, '2026-10-01 00:00:00');
INSERT INTO kuis (jenis, judul, status, durasi_menit, acak, diperbarui_pada) VALUES ('posttest', 'Post-test MIKRO-NET', 'draf', 30, 1, '2026-10-01 00:00:00');
INSERT INTO kuis_soal (urutan, modul_nomor, teks, opsi, kunci, untuk, contoh, dibuat_pada) VALUES (1, 1, 'IP address versi 4 (IPv4) terdiri dari berapa bit?', '["8 bit", "16 bit", "32 bit", "64 bit"]', 2, 'semua', 0, '2026-10-01 00:00:00');
INSERT INTO kuis_soal (urutan, modul_nomor, teks, opsi, kunci, untuk, contoh, dibuat_pada) VALUES (2, 1, 'IP address 172.16.5.10 termasuk ke dalam kelas...', '["Kelas A", "Kelas B", "Kelas C", "Kelas D"]', 1, 'semua', 0, '2026-10-01 00:00:00');
INSERT INTO kuis_soal (urutan, modul_nomor, teks, opsi, kunci, untuk, contoh, dibuat_pada) VALUES (3, 1, 'Berapa jumlah host yang dapat dipakai pada jaringan dengan prefix /26?', '["30", "62", "64", "126"]', 1, 'semua', 0, '2026-10-01 00:00:00');
INSERT INTO kuis_soal (urutan, modul_nomor, teks, opsi, kunci, untuk, contoh, dibuat_pada) VALUES (4, 1, 'Jaringan 192.168.10.0/24 dibagi menjadi 2 subnet dengan prefix /25. Subnet kedua dimulai dari network address...', '["192.168.10.64", "192.168.10.100", "192.168.10.128", "192.168.10.255"]', 2, 'semua', 0, '2026-10-01 00:00:00');
INSERT INTO kuis_soal (urutan, modul_nomor, teks, opsi, kunci, untuk, contoh, dibuat_pada) VALUES (5, 1, 'Pada Cisco Packet Tracer, kabel yang digunakan untuk menghubungkan PC ke Switch adalah...', '["Crossover", "Rollover", "Fiber", "Copper Straight-Through"]', 3, 'semua', 0, '2026-10-01 00:00:00');
INSERT INTO kuis_soal (urutan, modul_nomor, teks, opsi, kunci, untuk, contoh, dibuat_pada) VALUES (1, 2, 'Perangkat yang berfungsi menghubungkan dua jaringan (subnet) yang berbeda adalah...', '["Hub", "Switch", "Router", "Repeater"]', 2, 'semua', 0, '2026-10-01 00:00:00');
INSERT INTO kuis_soal (urutan, modul_nomor, teks, opsi, kunci, untuk, contoh, dibuat_pada) VALUES (2, 2, 'Switch bekerja pada layer...', '["Physical (Layer 1)", "Data Link (Layer 2)", "Network (Layer 3)", "Application (Layer 7)"]', 1, 'semua', 0, '2026-10-01 00:00:00');
INSERT INTO kuis_soal (urutan, modul_nomor, teks, opsi, kunci, untuk, contoh, dibuat_pada) VALUES (3, 2, 'Fungsi default gateway pada sebuah PC adalah...', '["Sebagai alamat router yang menjadi pintu keluar menuju jaringan lain", "Sebagai alamat yang memberi IP otomatis", "Sebagai nama komputer dalam jaringan", "Sebagai alamat untuk menyimpan data"]', 0, 'semua', 0, '2026-10-01 00:00:00');
INSERT INTO kuis_soal (urutan, modul_nomor, teks, opsi, kunci, untuk, contoh, dibuat_pada) VALUES (4, 2, 'IP conflict terjadi ketika...', '["Kabel jaringan terputus", "Dua perangkat memakai IP address yang sama", "Switch kekurangan port", "Router mati"]', 1, 'semua', 0, '2026-10-01 00:00:00');
INSERT INTO kuis_soal (urutan, modul_nomor, teks, opsi, kunci, untuk, contoh, dibuat_pada) VALUES (5, 2, 'PC1 (192.168.10.2/24) bisa ping ke PC2 di ruangan yang sama, tetapi tidak bisa ping ke PC3 (192.168.20.2/24) di ruangan lain. Penyebab yang paling mungkin adalah...', '["Kabel PC2 terputus", "IP PC1 sama dengan IP PC2", "Default gateway pada PC1 belum diisi atau salah", "Switch mati"]', 2, 'semua', 0, '2026-10-01 00:00:00');
INSERT INTO kuis_soal (urutan, modul_nomor, teks, opsi, kunci, untuk, contoh, dibuat_pada) VALUES (1, 3, 'Winbox adalah...', '["Antivirus untuk jaringan", "Game simulasi jaringan", "Bahasa pemrograman router", "Aplikasi berbasis GUI untuk mengakses dan mengonfigurasi router MikroTik"]', 3, 'semua', 0, '2026-10-01 00:00:00');
INSERT INTO kuis_soal (urutan, modul_nomor, teks, opsi, kunci, untuk, contoh, dibuat_pada) VALUES (2, 3, 'Menu pada Winbox yang digunakan untuk mengatur IP address pada interface router adalah...', '["IP → Addresses", "Files", "Log", "System → Reboot"]', 0, 'semua', 0, '2026-10-01 00:00:00');
INSERT INTO kuis_soal (urutan, modul_nomor, teks, opsi, kunci, untuk, contoh, dibuat_pada) VALUES (3, 3, 'Tab pada Winbox yang menampilkan daftar router MikroTik yang terdeteksi beserta MAC Address-nya adalah...', '["Terminal", "Log", "Files", "Neighbors"]', 3, 'semua', 0, '2026-10-01 00:00:00');
INSERT INTO kuis_soal (urutan, modul_nomor, teks, opsi, kunci, untuk, contoh, dibuat_pada) VALUES (4, 3, 'Login default router MikroTik (kondisi awal) umumnya menggunakan...', '["username: root, password: root", "username: admin, password: admin", "username: admin, tanpa password", "username: user, password: user"]', 2, 'semua', 0, '2026-10-01 00:00:00');
INSERT INTO kuis_soal (urutan, modul_nomor, teks, opsi, kunci, untuk, contoh, dibuat_pada) VALUES (5, 3, 'Interface ether1 router MikroTik diberi IP 192.168.10.1/24, dan sebuah PC diberi IP 192.168.10.2/24. Default gateway yang tepat untuk PC tersebut adalah...', '["192.168.10.1", "192.168.10.0", "192.168.10.255", "192.168.20.1"]', 0, 'semua', 0, '2026-10-01 00:00:00');
INSERT INTO kuis_soal (urutan, modul_nomor, teks, opsi, kunci, untuk, contoh, dibuat_pada) VALUES (1, 4, 'Komunikasi wireless Point-to-Point (P2P) adalah komunikasi yang...', '["Satu access point melayani banyak perangkat sekaligus", "Menggunakan jaringan listrik", "Menggunakan kabel fiber optik", "Menghubungkan dua titik secara langsung (satu pemancar dan satu penerima)"]', 3, 'semua', 0, '2026-10-01 00:00:00');
INSERT INTO kuis_soal (urutan, modul_nomor, teks, opsi, kunci, untuk, contoh, dibuat_pada) VALUES (2, 4, 'Fungsi wajan pada antena wajanbolic adalah...', '["Menghasilkan sinyal WiFi sendiri", "Sebagai reflektor yang memfokuskan dan memperkuat arah sinyal", "Menyimpan data jaringan", "Memberikan IP address otomatis"]', 1, 'semua', 0, '2026-10-01 00:00:00');
INSERT INTO kuis_soal (urutan, modul_nomor, teks, opsi, kunci, untuk, contoh, dibuat_pada) VALUES (3, 4, 'Panjang feeder dihitung dengan rumus F = D² / (16 × d). Jika diameter wajan D = 60 cm dan kedalaman d = 15 cm, panjang feeder adalah...', '["10 cm", "12 cm", "15 cm", "20 cm"]', 2, 'semua', 0, '2026-10-01 00:00:00');
INSERT INTO kuis_soal (urutan, modul_nomor, teks, opsi, kunci, untuk, contoh, dibuat_pada) VALUES (4, 4, 'Pada praktik wajanbolic P2P, USB WiFi adapter di sisi penerima disetel pada mode...', '["Access Point", "Router", "Station/Client", "Switch"]', 2, 'semua', 0, '2026-10-01 00:00:00');
INSERT INTO kuis_soal (urutan, modul_nomor, teks, opsi, kunci, untuk, contoh, dibuat_pada) VALUES (5, 4, 'Lakban aluminium foil dipasang pada permukaan dalam wajan bertujuan untuk...', '["Membuat permukaan wajan bersifat reflektif terhadap gelombang WiFi", "Mempercepat kinerja laptop", "Melindungi wajan dari hujan", "Menambah panjang kabel USB"]', 0, 'semua', 0, '2026-10-01 00:00:00');
INSERT INTO kuis_soal (urutan, modul_nomor, teks, opsi, kunci, untuk, contoh, dibuat_pada) VALUES (1, 0, 'Model referensi OSI memiliki berapa layer?', '["4", "5", "7", "9"]', 2, 'semua', 0, '2026-10-01 00:00:00');
INSERT INTO kuis_soal (urutan, modul_nomor, teks, opsi, kunci, untuk, contoh, dibuat_pada) VALUES (2, 0, 'Fungsi routing dan pengalamatan IP berada pada layer OSI...', '["Physical", "Network", "Transport", "Session"]', 1, 'semua', 0, '2026-10-01 00:00:00');
INSERT INTO kuis_soal (urutan, modul_nomor, teks, opsi, kunci, untuk, contoh, dibuat_pada) VALUES (3, 0, 'Pada topologi star, semua perangkat...', '["Terhubung ke satu titik pusat (misalnya switch)", "Terhubung secara berurutan dalam satu kabel utama", "Terhubung membentuk lingkaran tertutup", "Terhubung langsung satu sama lain tanpa perangkat perantara"]', 0, 'semua', 0, '2026-10-01 00:00:00');
INSERT INTO kuis_soal (urutan, modul_nomor, teks, opsi, kunci, untuk, contoh, dibuat_pada) VALUES (4, 0, 'Protokol yang berfungsi memberikan IP address secara otomatis kepada perangkat di jaringan adalah...', '["DNS", "HTTP", "FTP", "DHCP"]', 3, 'semua', 0, '2026-10-01 00:00:00');
INSERT INTO kuis_soal (urutan, modul_nomor, teks, opsi, kunci, untuk, contoh, dibuat_pada) VALUES (5, 0, 'Protokol yang berfungsi menerjemahkan nama domain (misalnya google.com) menjadi IP address adalah...', '["DNS", "DHCP", "SMTP", "SSH"]', 0, 'semua', 0, '2026-10-01 00:00:00');
INSERT INTO kuis_soal (urutan, modul_nomor, teks, opsi, kunci, untuk, contoh, dibuat_pada) VALUES (6, 0, 'Konektor yang umum dipasang pada ujung kabel UTP untuk jaringan LAN adalah...', '["BNC", "RJ-11", "RJ-45", "USB Type-C"]', 2, 'semua', 0, '2026-10-01 00:00:00');
INSERT INTO kuis_soal (urutan, modul_nomor, teks, opsi, kunci, untuk, contoh, dibuat_pada) VALUES (7, 0, 'Perintah yang digunakan untuk menguji konektivitas antar-perangkat dalam jaringan adalah...', '["ipconfig", "ping", "format", "shutdown"]', 1, 'semua', 0, '2026-10-01 00:00:00');
INSERT INTO kuis_soal (urutan, modul_nomor, teks, opsi, kunci, untuk, contoh, dibuat_pada) VALUES (8, 0, 'Jaringan komputer yang mencakup area terbatas, seperti satu gedung atau laboratorium sekolah, disebut...', '["WAN", "MAN", "LAN", "Internet"]', 2, 'semua', 0, '2026-10-01 00:00:00');
INSERT INTO kuis_soal (urutan, modul_nomor, teks, opsi, kunci, untuk, contoh, dibuat_pada) VALUES (9, 0, 'Port default untuk protokol HTTPS adalah...', '["21", "25", "80", "443"]', 3, 'semua', 0, '2026-10-01 00:00:00');
INSERT INTO kuis_soal (urutan, modul_nomor, teks, opsi, kunci, untuk, contoh, dibuat_pada) VALUES (10, 0, 'Kabel crossover pada konfigurasi konvensional digunakan untuk menghubungkan...', '["PC dengan Switch", "Switch dengan Router", "PC dengan PC (perangkat sejenis)", "PC dengan Access Point"]', 2, 'semua', 0, '2026-10-01 00:00:00');

-- ---------- 4. Akun ----------
-- DATA UJI UNTUK LAPTOP SAJA. Jangan diimpor ke hosting produksi.
-- Sandi: admin = Admin12345, guru = Guru12345, siswa = Siswa12345
INSERT INTO users (nama,email,password_hash,peran,nis,aktif,harus_ganti_password,dibuat_pada,diperbarui_pada) VALUES ('Admin Uji','admin@mikronet.test','$2y$12$s6m5kkZuHVPVHK.nE7a3Q.bCNsnZv0LPvXmC3Cl4sv3JLRKYGsuhS','admin',NULL,1,0,'2026-09-30 00:00:00','2026-09-30 00:00:00');
INSERT INTO users (nama,email,password_hash,peran,nis,aktif,harus_ganti_password,dibuat_pada,diperbarui_pada) VALUES ('Guru Pengajar 1','guru1@mikronet.test','$2y$12$hG53EjTl2PSNZ6B3.4fp4u00JXnWEYpZtq8FZPg/2svd9Zy8Ir8QC','guru',NULL,1,0,'2026-09-30 00:00:00','2026-09-30 00:00:00');
INSERT INTO kelas (nama,kode,guru_id,aktif,dibuat_pada) VALUES ('XI TKJ 1','TKJ1-7K2Q',2,1,'2026-09-30 00:00:00');
INSERT INTO users (nama,email,password_hash,peran,nis,aktif,harus_ganti_password,dibuat_pada,diperbarui_pada) VALUES ('Ahmad Fauzi','siswa1@mikronet.test','$2y$12$x22X6m/BwQ5QB.DRh8l2Luc2GiO2Ji6fnBaYG2U5RIqjdTrY2cgQi','siswa','2025001',1,0,'2026-09-30 00:00:00','2026-09-30 00:00:00');
INSERT INTO kelas_anggota (kelas_id,siswa_id,bergabung_pada) VALUES (1,3,'2026-09-30 00:00:00');
