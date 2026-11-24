-- MIKRO-NET migrasi 010: isi modul bisa diedit guru (praktikum, pengumpulan, LKPD, refleksi, remidi, pengayaan).
-- Jalankan sekali di phpMyAdmin (tab SQL) pada database hosting, setelah 009.

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
