-- MIKRO-NET migrasi 011: KKM dan bobot nilai per kelas (bisa diubah guru) untuk analisis ketuntasan, remidi, dan pengayaan.
-- Jalankan sekali di phpMyAdmin (tab SQL) pada database hosting, setelah 010.
ALTER TABLE kelas ADD kkm INT NOT NULL DEFAULT 75;
ALTER TABLE kelas ADD bobot_praktik INT NOT NULL DEFAULT 40;
ALTER TABLE kelas ADD bobot_lkpd INT NOT NULL DEFAULT 40;
ALTER TABLE kelas ADD bobot_simulasi INT NOT NULL DEFAULT 20;
