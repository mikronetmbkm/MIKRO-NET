# MIKRO-NET — Panduan dari nol (untuk yang belum pernah setup apa-apa)

Ikuti berurutan. Setiap langkah ada tanda **✅ Berhasil kalau...** supaya kamu tahu sudah benar sebelum lanjut.
Total waktu sekitar 45–60 menit untuk pertama kali.

Yang kamu punya: aplikasi web PHP lengkap (masuk/daftar dengan kode kelas, 10 modul, materi PDF, simulasi,
praktikum, pengumpulan hasil dengan simpan otomatis + unggah, penilaian rubrik guru, panel admin). Modul 1 sudah berisi
**konten asli**: slide ajar, game simulasi (tim DMI), panduan praktikum Packet Tracer, form pengumpulan, dan 30 soal pretest/post-test.
Modul 2-4 menyusul.

---

## Bagian A — Pasang alat (sekali saja)

### A1. XAMPP (PHP + MySQL + phpMyAdmin dalam satu paket)
1. Unduh dari https://www.apachefriends.org — pilih versi **PHP 8.2 atau lebih baru**.
2. Pasang dengan pengaturan bawaan. Di Windows pasang ke `C:\xampp` (bukan Program Files).
3. Buka **XAMPP Control Panel**, klik **Start** pada **MySQL** (Apache tidak wajib untuk cara di bawah).

✅ Berhasil kalau: baris MySQL berwarna hijau.
❌ MySQL tidak mau start → biasanya port 3306 dipakai program lain; tutup MySQL lain, atau lihat tabel masalah di bawah.

### A2. Supaya perintah `php` dikenali di terminal (Windows)
1. Tekan tombol Windows, ketik **"environment variables"** → *Edit the system environment variables* → **Environment Variables**.
2. Di bagian *Path* (User atau System) → **Edit** → **New** → isi `C:\xampp\php` → OK semua.
3. Tutup lalu buka lagi terminal, ketik `php -v`.

✅ Berhasil kalau: muncul tulisan `PHP 8.2...` atau lebih tinggi.

### A3. VS Code (editor kode)
Unduh dari https://code.visualstudio.com. Pasang ekstensi **PHP Intelephense** (opsional tapi membantu).

### A4. Git (untuk kerja tim)
Unduh dari https://git-scm.com. Pasang dengan pengaturan bawaan.

---

## Bagian B — Ambil proyek dan siapkan database

### B1. Taruh proyek
1. Ekstrak `mikronet.zip` ke folder yang mudah, misalnya `C:\mikronet`.
2. Buka VS Code → **File > Open Folder** → pilih folder `mikronet` tadi.
3. Buka terminal di dalam VS Code: menu **Terminal > New Terminal**.

### B2. Buat database
1. Buka browser: http://localhost/phpmyadmin
2. Klik tab **SQL**, tempel ini, klik **Go**:

```sql
CREATE DATABASE mikronet CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

✅ Berhasil kalau: di kolom kiri muncul database **mikronet**.

### B3. Buat berkas pengaturan `.env`
Di VS Code, klik kanan `.env.example` → **Copy**, lalu **Paste** dan ubah namanya menjadi `.env` (tanpa `.example`).
Untuk laptop, isinya sudah cocok dengan XAMPP bawaan (pengguna `root`, tanpa sandi). Biarkan.

> `.env` menyimpan sandi dan pengaturan server. **Jangan pernah di-commit ke Git** (sudah otomatis diabaikan).

### B4. Impor tabel dan data
Di phpMyAdmin: klik database **mikronet** → tab **Import** → *Choose file* → **Go**. Lakukan **empat kali (lima di laptop), berurutan**:

| Urutan | Berkas (di folder `database/`) | Isinya |
|---|---|---|
| 1 | `schema.sql` | membuat semua tabel |
| 2 | `seed-modul.sql` | 10 modul (Modul 1 terbuka; 2–4 "segera"; 5–10 terkunci) |
| 3 | `seed-kuis.sql` | pretest + post-test (status Draf) dan 30 soal asli |
| 4 | `seed-dev.sql` | **hanya laptop:** akun uji + satu kelas uji |

✅ Berhasil kalau: di database mikronet ada 11 tabel (users, kelas, kelas_anggota, modul, modul_siswa, lkpd_pengumpulan, lkpd_berkas, login_percobaan, kuis, kuis_soal, kuis_percobaan).

**Sudah punya database dari versi sebelumnya?** Jangan impor `schema.sql` lagi. Impor migrasi yang belum pernah kamu jalankan, berurutan: `002_kuis.sql` (kuis), `003_praktikum.sql` (kemajuan praktikum + pasang game Modul 1), `004_soal-asli.sql` (ganti 12 soal contoh dengan 30 soal asli), lalu `005_lembar_kerja.sql` (tabel LKPD), lalu `006_progres_game.sql` (simpan progres game simulasi), lalu `007_modul2_guru_lkpd.sql` (Modul 2, guru pendamping kelas, NIS per kelas, buka/tutup LKPD). Kalau database kuis belum ada sama sekali, impor `seed-kuis.sql` (bukan 004). Pesan "Duplicate column" artinya migrasi itu sudah pernah jalan; aman diabaikan.
❌ Pesan error saat impor `schema.sql` → **jangan diutak-atik**; foto layarnya dan kirim ke ketua tim. (Struktur ini diuji otomatis di SQLite, belum di MySQL sungguhan — jadi langkah ini adalah uji MySQL pertamanya.)

---

## Bagian C — Jalankan

Di terminal VS Code (posisi di folder `mikronet`):

```
php -S localhost:8080 -t public
```

Biarkan terminal itu terbuka (itu servernya). Buka browser:

1. http://localhost:8080/health → harus tampil `{"ok":true,"modul":10,...}` ✅
2. http://localhost:8080 → halaman depan MIKRO-NET.

Untuk berhenti: tekan `Ctrl + C` di terminal.

### Akun uji (dari `seed-dev.sql`)

| Peran | Email | Sandi |
|---|---|---|
| Admin | admin@mikronet.test | Admin12345 |
| Guru | guru1@mikronet.test | Guru12345 |
| Siswa | siswa1@mikronet.test | Siswa12345 |

Kode kelas uji: **TKJ1-7K2Q** (kelas XI TKJ 1). Coba daftar sebagai siswa baru memakai kode itu.

### Jalan-jalan 10 menit (ini sekaligus uji manual P1)
1. **Siswa:** masuk sebagai `siswa1` → Modul → Modul 1. Enam langkah **berurutan** (langkah berikutnya terkunci sampai yang sebelumnya selesai):
   Materi (baca sampai slide terakhir → tombol *Lanjut ke Simulasi*), Simulasi (mainkan game sampai skor muncul → *Lanjut ke Praktikum*),
   Praktikum (18 langkah Packet Tracer, tekan *Sudah, lanjut* tiap langkah → *Selesai praktikum* → *Lanjut ke Pengumpulan*),
   Pengumpulan (isi form, unggah screenshot + file .pkt, Kumpulkan), LKPD (10 pilihan ganda dinilai otomatis + 5 uraian dinilai guru), lalu Refleksi (skala paham, hal penting, yang masih bingung).
   Di Beranda muncul pengingat "Kamu belum mengerjakan pretest" selama pretest sudah dibuka guru tapi belum dikerjakan.
2. **Guru:** keluar, masuk sebagai `guru1` → Penilaian → pilih siswa → isi skor tiap aspek (Topologi 20, Kabel & port 15, IP 25, Ping 25, Dokumentasi & .pkt 15; total 100) → Simpan nilai.
3. **Siswa lagi:** menu Nilai → nilai dan catatan guru muncul.
4. **Kuis:** masuk sebagai `guru1` → Kuis → ubah status pretest menjadi *Dibuka* → Simpan. Masuk sebagai `siswa1` → Kuis → kerjakan pretest. Lalu guru → Kuis → *Lihat hasil kelas*.
5. **Admin:** menu Pengguna → tambah guru (sandi sementara muncul sekali) → Modul → Edit Modul 1.

### Uji otomatis (opsional, 1 menit)
Sambil server jalan di satu terminal, buka terminal kedua:

```
php tests/smoke.php http://localhost:8080
```

Ini menjalankan sekitar 100 pemeriksaan (daftar, login, LKPD, unggah, nilai, hak akses…). Ia membuat data uji,
jadi jalankan di database yang **baru diimpor**. Ulang impor (Bagian B4) bila ingin mengulanginya.

---

## Bagian D — Memasang konten asli

Detail format ada di `docs/KONTRAK-KONTEN.md`. Ringkasnya:

| Konten | Cara pasang |
|---|---|
| PDF materi | Modul 1 sudah memakai `database/content/materi-p1.pdf` (slide dari materi tim). Untuk mengganti: login guru/admin → Kelola Modul → Edit → **Unggah PDF** |
| Game HTML5 | Modul 1 sudah memakai `public/games/simulasi-p1/`. Game baru: salin folder ke `public/games/<nama>/` → Kelola Modul → pilih folder game → Simpan |
| Soal pretest dan post-test | menu **Kuis** (guru/admin) → Tambah soal atau Impor massal → ubah status kuis menjadi *Dibuka* (30 soal asli sudah terpasang; modul "Umum" = materi umum jaringan) |
| Form pengumpulan + rubrik | edit `database/content/lkpd-p1.json` (sudah form asli P1) |
| Panduan praktikum | edit `database/content/praktikum-p1.json`; gambar di `public/assets/praktikum/p1/` |
| Membuka Modul 2, 3, 4 | Kelola Modul → ubah **Status** menjadi *Terbit* (pada tanggal pertemuannya) |

Penampil materi memakai PDF.js yang sudah disertakan di `public/assets/vendor/` (v3.11.174), jadi tidak perlu internet.
Kalau berkas itu terhapus, aplikasi mengambilnya dari cdnjs; bila itu juga gagal, dipakai penampil PDF bawaan browser (siswa lalu menekan tombol "Saya sudah selesai membaca").

---

## Bagian E — Kerja tim dengan Git & GitHub

```
git init
git add .
git commit -m "MIKRO-NET awal"
```
1. Buat repositori **private** di GitHub, lalu ikuti petunjuk "push an existing repository".
2. Aturan tim: satu orang = satu cabang (`git checkout -b nama-fitur`), gabung lewat Pull Request, ketua yang menyetujui.
3. Yang **tidak** masuk Git (sudah diatur `.gitignore`): `.env`, isi `storage/uploads`, log.

---

## Bagian F — Naik ke hosting

Ikuti `docs/DEPLOY-CPANEL.md` (langkah demi langkah, termasuk daftar centang keamanan).
**Jangan** impor `seed-dev.sql` ke hosting — gunakan `seed-modul.sql`, `seed-kuis.sql`, dan `admin-awal.sql`. Mau mengosongkan akun dan progres di database lama? Impor `database/reset-data.sql` lalu `admin-awal.sql` (detail di `docs/DEPLOY-CPANEL.md`).

---

## Masalah yang sering muncul

| Gejala | Penyebab | Solusi |
|---|---|---|
| `php` tidak dikenali | PATH belum diatur | Ulangi A2, buka terminal baru |
| `could not find driver` | ekstensi MySQL PHP mati | buka `C:\xampp\php\php.ini`, pastikan `extension=pdo_mysql` tidak diawali `;`, restart terminal |
| `Access denied for user` | sandi di `.env` tak cocok | samakan `DB_USER`/`DB_PASS` dengan akun MySQL-mu |
| `Unknown database 'mikronet'` | lupa B2 | buat databasenya |
| Halaman putih/"Terjadi kesalahan" | galat di kode | lihat `storage/logs/app.log`; pastikan `APP_ENV=local` untuk melihat detail |
| `/health` menampilkan `ok:false` | tabel belum diimpor / `.env` salah | ulangi B3–B4 |
| Unggah gambar gagal | batas ukuran PHP | di laptop biasanya aman; di hosting lihat DEPLOY-CPANEL |
| Port 8080 dipakai | program lain | ganti: `php -S localhost:8081 -t public` |

## Apa yang belum ada di sprint ini (jujur)

- **API untuk aplikasi mobile** (login, unggah foto praktikum P3/P4) — sprint berikutnya, target ± 9 Oktober.
- Halaman guru untuk melihat foto praktikum dan penilaian kelompok — menunggu API di atas.
- Konten Modul 2-4 (materi, game, soal praktik) belum ada; Modul 1 sudah lengkap. Cara memasangnya ada di `docs/KONTRAK-KONTEN.md`.
- Pengumpulan praktikum bersifat per siswa (praktiknya per kelompok), jadi tiap anggota mengumpulkan file yang sama.
- Ekspor nilai LKPD ke Excel — belum (hasil kuis sudah bisa diunduh CSV).
- Aplikasi ini diuji otomatis dengan SQLite dan server bawaan PHP; **belum diuji di MySQL/Apache sungguhan**.
  Uji pertamamu: impor `schema.sql` dan buka `/health`.


## Langkah modul boleh dilompati

Bawaan aplikasi: siswa boleh membuka langkah modul mana pun (misalnya langsung ke LKPD atau Refleksi kalau waktu mepet). Kalau ingin siswa wajib berurutan lagi, tambahkan baris `LANGKAH_URUT=1` di `.env`. Hapus baris itu (atau isi `0`) untuk membebaskan lagi.

## Ruang guru

- **Beranda** (`/guru`): ringkasan semua kelasmu, tugas yang menunggu dinilai, dan saklar buka/tutup LKPD modul minggu ini.
- **Kemajuan**: status 6 langkah tiap siswa (materi sampai refleksi), skor game, dan nilai LKPD.
- **Penilaian**: nilai pengumpulan praktikum dengan rubrik dan uraian LKPD.
- **Refleksi** (dari Beranda atau Kemajuan): semua refleksi siswa satu kelas untuk satu modul.
- **Guru pendamping**: di menu Kelas, bagian "Guru pengajar", pilih guru lain lalu Tambahkan. Kelas itu langsung muncul di akunnya. Akun guru dibuat admin.
- **Buka/tutup LKPD**: berlaku per kelas per modul. Saat ditutup, siswa tidak bisa mengerjakan atau mengumpulkan; yang sudah dikumpulkan tetap bisa dilihat.
- **NIS**: cukup unik di dalam satu kelas (nomor absen boleh dipakai). Admin bisa mengubah nama dan NIS siswa di menu Pengguna.
