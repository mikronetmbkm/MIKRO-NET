-- MIKRO-NET migrasi 008: isi dan terbitkan Modul 3 (MikroTik dan Winbox).
-- Jalankan sekali di phpMyAdmin (tab SQL) pada database hosting. Aman dijalankan ulang.
UPDATE modul SET
  judul = 'MikroTik dan Winbox',
  ringkasan = 'Mengonfigurasi router MikroTik dengan Winbox: IP address, gateway, uji koneksi antar-PC, akses internet (DHCP Client, DNS, NAT), dan firewall filter.',
  tujuan = 'Memahami konfigurasi jaringan menggunakan router MikroTik dan aplikasi Winbox.
Mengatur IP address pada interface router.
Mengatur IP address dan gateway pada PC1 dan PC2.
Menghubungkan jaringan lokal dengan internet melalui DHCP Client.
Mengonfigurasi DNS dan NAT masquerade.
Mengonfigurasi firewall filter sederhana.
Melakukan pengujian konektivitas jaringan.',
  tanggal = '2026-10-12', status = 'terbit', ikon = 'router', game_folder = 'simulasi-p3', diperbarui_pada = NOW()
WHERE nomor = 3;
