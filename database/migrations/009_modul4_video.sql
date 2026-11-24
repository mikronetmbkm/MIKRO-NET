-- MIKRO-NET migrasi 009: kolom link video di modul, lalu isi dan terbitkan Modul 4 (Wajanbolic).
-- Jalankan sekali di phpMyAdmin (tab SQL) pada database hosting.
ALTER TABLE modul ADD COLUMN video_url VARCHAR(500) NULL AFTER game_folder;

UPDATE modul SET
  judul = 'Wajanbolic dan link wireless Point-to-Point',
  ringkasan = 'Merakit antena wajanbolic, membangun link nirkabel Point-to-Point antara dua laptop, lalu membuktikan dengan data bahwa wajan memperkuat sinyal.',
  tujuan = 'Menjelaskan konsep komunikasi wireless Point-to-Point dan perbedaannya dengan access point biasa.
Menjelaskan prinsip kerja antena wajanbolic, frekuensi 2,4 GHz, polarisasi, dan line of sight.
Mengukur diameter dan kedalaman wajan, lalu menghitung panjang feeder F = D² / (16 × d).
Merakit antena wajanbolic sesuai perhitungan dan prosedur keselamatan kerja.
Mengonfigurasi laptop pemancar sebagai hotspot dan laptop penerima sebagai klien.
Menguji link dengan ipconfig, ping, dan netsh wlan show interfaces pada jarak berbeda.
Membandingkan data tanpa dan dengan wajan, lalu menganalisis penyebab link yang lemah.',
  tanggal = '2026-10-21', status = 'terbit', ikon = 'dish', game_folder = 'simulasi-p4', diperbarui_pada = NOW()
WHERE nomor = 4;
