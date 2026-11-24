-- Migrasi 006: simpan progres game simulasi per siswa (supaya tidak mengulang dari awal saat halaman dimuat ulang).
-- Jalankan SEKALI pada database yang sudah berjalan (phpMyAdmin > pilih database > tab SQL > tempel > Kirim).
-- Database baru tidak perlu (sudah ada di schema.sql). Bila muncul "Duplicate column name 'game_data'", artinya sudah pernah dijalankan.
ALTER TABLE modul_siswa ADD COLUMN game_data MEDIUMTEXT NULL AFTER praktikum_selesai;
