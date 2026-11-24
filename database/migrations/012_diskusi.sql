-- MIKRO-NET migrasi 012: ruang diskusi siswa dan guru (lintas kelas / dalam kelas, umum / per modul).
-- Jalankan sekali di phpMyAdmin (tab SQL) pada database hosting, setelah 011.

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
