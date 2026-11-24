<?php
// Menyusun ulang database/setup-laptop.sql dan database/setup-hosting.sql dari schema.sql + seed-*.sql.
// Jalankan setiap kali schema atau seed berubah:  php tests/buat-setup.php
$db = dirname(__DIR__) . '/database';
$tabel = [];
preg_match_all('/CREATE TABLE (\w+)/', file_get_contents("$db/schema.sql"), $m);
$tabel = array_reverse($m[1]); // hapus dari yang paling bergantung
$hapus = "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n" . implode('', array_map(fn($t) => "DROP TABLE IF EXISTS $t;\n", $tabel)) . "SET FOREIGN_KEY_CHECKS = 1;\n";
$isi = fn(string $f) => rtrim(file_get_contents("$db/$f")) . "\n";
$badan = "\n-- ---------- 1. Struktur tabel (schema.sql) ----------\n" . $isi('schema.sql')
    . "\n-- ---------- 2. Data 10 modul (seed-modul.sql) ----------\n" . $isi('seed-modul.sql')
    . "\n-- ---------- 3. Pretest, post-test, 30 soal (seed-kuis.sql) ----------\n" . $isi('seed-kuis.sql')
    . "\n-- ---------- 4. Akun ----------\n";
$garis = "-- =====================================================================\n";
$umum = "-- Satu berkas untuk membuat ULANG seluruh database dari nol.\n-- Cara: phpMyAdmin → klik database (mis. mikronet) → tab Import → pilih berkas ini → Go.\n-- PERINGATAN: semua tabel MIKRO-NET yang sudah ada DIHAPUS dulu (akun, progres, nilai, soal ikut hilang).\n";
file_put_contents("$db/setup-laptop.sql", $garis . "-- MIKRO-NET · SETUP LAPTOP (dengan akun uji)\n" . $umum
    . "-- Akun uji: admin@mikronet.test / Admin12345 · guru1@mikronet.test / Guru12345 · siswa1@mikronet.test / Siswa12345 · kode kelas TKJ1-7K2Q\n-- JANGAN dipakai di hosting.\n"
    . $garis . $hapus . $badan . $isi('seed-dev.sql'));
file_put_contents("$db/setup-hosting.sql", $garis . "-- MIKRO-NET · SETUP HOSTING (bersih, 1 admin)\n" . $umum
    . "-- SEBELUM import: cari teks admin@GANTI-DENGAN-EMAILMU.com di bagian paling bawah, ganti dengan email admin asli.\n-- Sandi awal admin: GantiSaya-2026 (wajib diganti saat masuk pertama).\n"
    . $garis . $hapus . $badan . $isi('admin-awal.sql'));
echo "OK: setup-laptop.sql dan setup-hosting.sql (" . count($tabel) . " tabel)\n";
