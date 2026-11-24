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
