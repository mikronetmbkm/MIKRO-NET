-- Migrasi 007: Modul 2, guru pendamping kelas, NIS unik per kelas, buka/tutup LKPD.
-- Jalankan SEKALI pada database yang sudah berjalan (phpMyAdmin > pilih database > tab SQL > tempel > Kirim).
-- Database baru tidak perlu (sudah ada di schema.sql dan seed-modul.sql).
-- Bila muncul "already exists" atau "Can't DROP", bagian itu sudah pernah dijalankan.

-- 1. Satu kelas bisa diajar lebih dari satu guru
CREATE TABLE kelas_guru (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  kelas_id INT UNSIGNED NOT NULL,
  guru_id INT UNSIGNED NOT NULL,
  ditambahkan_pada DATETIME NOT NULL,
  FOREIGN KEY (kelas_id) REFERENCES kelas (id) ON DELETE CASCADE,
  FOREIGN KEY (guru_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE UNIQUE INDEX ux_kelas_guru ON kelas_guru (kelas_id, guru_id);

-- 2. Guru bisa membuka/menutup LKPD per kelas per modul
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

-- 3. NIS tidak lagi unik di seluruh sekolah, cukup unik di dalam satu kelas (dicek aplikasi)
ALTER TABLE users DROP INDEX ux_users_nis;
CREATE INDEX ix_users_nis ON users (nis);

-- 4. Isi Modul 2 dan terbitkan (ubah status ke 'segera' di Kelola modul kalau belum mau dibuka)
UPDATE modul SET
  judul = 'VLAN, DHCP, VLSM, dan static route',
  ringkasan = 'Membangun jaringan dua router dengan VLAN, router-on-a-stick, DHCP, VLSM, dan static route di Cisco Packet Tracer.',
  tujuan = 'Menjelaskan fungsi switch, router, dan access point dalam jaringan.
Menjelaskan konsep topologi dan segmentasi jaringan.
Mengidentifikasi permasalahan umum pada jaringan LAN.
Mengonfigurasi VLAN dan inter-VLAN routing (router-on-a-stick).
Mengonfigurasi DHCP server di router.
Menentukan pembagian subnet dengan VLSM sesuai kebutuhan host.
Mengonfigurasi static route dan menguji koneksi antar-jaringan.',
  tanggal = '2026-10-12', status = 'terbit', ikon = 'network', game_folder = 'simulasi-p2', diperbarui_pada = NOW()
WHERE nomor = 2;
