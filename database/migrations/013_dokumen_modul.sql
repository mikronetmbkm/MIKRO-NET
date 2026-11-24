-- MIKRO-NET migrasi 013: modul ajar (PDF untuk guru) dan bahan bacaan (PDF untuk siswa) per modul.
-- Jalankan sekali di phpMyAdmin (tab SQL) pada database hosting, setelah 012.
-- Kolom berisi nama berkas yang diunggah guru; bila kosong, dipakai berkas bawaan database/content/modul-ajar-pN.pdf dan bacaan-pN.pdf.
ALTER TABLE modul ADD COLUMN modul_ajar_berkas VARCHAR(255) NULL;
ALTER TABLE modul ADD COLUMN bacaan_berkas VARCHAR(255) NULL;
