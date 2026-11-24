# Kontrak konten (untuk tim materi, tim DMI, dan tim mobile)

Aplikasi sudah jalan **tanpa menunggu** konten. **Modul 1 sudah berisi konten asli** (slide, game DMI, panduan praktikum,
form pengumpulan, 30 soal kuis). Modul lain memakai format yang sama; kotak kuning "Konten contoh" muncul bila ada yang belum diganti. Batas waktu yang disarankan:
**Sabtu, 3 Oktober**, supaya ada satu hari uji sebelum P1 (Senin, 5 Oktober).

| # | Konten | Dari siapa | Format | Dipasang oleh | Cara pasang |
|---|---|---|---|---|---|
| 1 | Materi P1 | tim materi | PDF, maks 25 MB | admin | Login admin > **Modul** > Edit Modul 1 > **Unggah PDF** |
| 2 | Simulasi (game) | tim DMI | satu folder HTML5 | siapa saja yang punya akses berkas | salin folder ke `public/games/<nama>/`, lalu Kelola Modul 1 > pilih folder game |
| 3 | Panduan praktikum | tim materi | berkas JSON + gambar | siapa saja yang punya akses berkas | `database/content/praktikum-p{N}.json`, gambar di `public/assets/praktikum/p{N}/` |
| 3b | Form pengumpulan + rubrik | tim materi / guru | berkas JSON | siapa saja yang punya akses berkas | `database/content/lkpd-p{N}.json` (lihat templat) |
| 4 | Soal pretest dan post-test (Modul 1-4) | tim materi / guru | teks, format impor di bawah | guru/admin | menu **Kuis** > Impor massal atau Tambah soal |
| 5 | Tujuan pembelajaran + ringkasan | dari modul ajar | teks | guru/admin | Kelola Modul 1 (satu tujuan per baris) |

## Kontrak materi (PDF)

- Satu file PDF per pertemuan, **satu halaman PDF = satu slide**.
- **Orientasi landscape 16:9** (mis. 1920×1080 px atau ukuran halaman "Widescreen" di PowerPoint/Canva). Penampil memang dirancang
  untuk slide landscape; PDF portrait tetap terbuka tetapi tampil kecil.
- Teks minimal 24 pt agar terbaca di HP. Di HP, siswa bisa memutar layar ke samping atau memakai tombol **Layar penuh**.
- Siswa juga bisa memilih **mode gulir** (semua halaman turun ke bawah).
- Tanpa animasi dan tanpa video tersemat. Tautan luar boleh.
- Guru/admin mengganti atau mengunggah PDF lewat menu **Modul**; kemajuan baca siswa tidak hilang.

## Kontrak soal pretest dan post-test

Hanya ada **satu pretest dan satu post-test**, dan keduanya menguji **Modul 1 sampai 4 ditambah materi umum jaringan**. Soal disimpan dalam satu bank (sekarang 30 soal asli: Modul 1-4 masing-masing 5, Umum 10).

- Pilihan ganda, **2 sampai 5 pilihan**, tepat satu jawaban benar.
- Tiap soal ditandai modul yang diujinya (1 sampai 4, atau **0 = Umum**), supaya guru bisa melihat modul mana yang paling lemah.
- Kolom **Dipakai di**: `semua` (pretest dan post-test, **disarankan**), `pre`, atau `post`. Agar N-gain adil, sebagian besar soal sebaiknya `semua`.
- Soal contoh (bila ada) bertanda "Contoh" dan boleh dihapus lewat tombol *Hapus semua soal contoh*.
- **Kunci otomatis:** begitu ada siswa yang mengerjakan sebuah kuis, soal untuk kuis itu tidak bisa diubah atau dihapus. Guru bisa mereset percobaan siswa dari halaman *Hasil kelas* bila perlu.

**Impor massal** (menu Kuis > Impor massal): satu soal per baris, kolom dipisah `|` atau TAB (tempel langsung dari Excel/Sheets).
Urutan kolom: `modul | pertanyaan | pilihan A | pilihan B | ... | kunci | dipakai di (opsional)`. Contoh:

```
1 | Berapa jumlah bit pada satu alamat IPv4? | 8 | 16 | 32 | 128 | C | semua
2 | Perangkat yang meneruskan paket antarjaringan disebut? | Hub | Router | Repeater | Kabel UTP | B
```

Bila ada satu baris yang salah, **tidak ada soal yang disimpan** dan pesan galat menyebut nomor barisnya.

**Aturan kuis:** waktu dihitung di server (default 30 menit) dan tetap berjalan walau halaman ditutup; satu kesempatan per siswa;
jawaban tersimpan otomatis; soal dan pilihan diacak per siswa. Kunci jawaban tidak ditampilkan ke siswa.
**N-gain** = (post - pre) / (100 - pre): Tinggi >= 0,70, Sedang 0,30 sampai 0,69, Rendah < 0,30.

## Kontrak game (untuk tim DMI)

Satu game = satu folder yang berdiri sendiri, dengan `index.html` di akarnya:

```
game-p1-subnet/
  index.html          titik masuk (wajib)
  css/  js/  img/  audio/   aset, semua pakai path RELATIF
```

Aturan teknis:
1. Murni HTML5 + CSS + JavaScript. **Tanpa CDN atau sumber luar** (jaringan sekolah bisa terbatas);
   library ditaruh di dalam folder.
2. Semua path **relatif** (`./js/game.js`, bukan `/js/game.js`).
3. Game berjalan di dalam `iframe` ber-**sandbox** (tanpa akses ke akun/cookie situs). Akibatnya:
   - **Jangan** memakai `fetch()` / `XMLHttpRequest` untuk memuat berkas lokal (JSON, dll.) — akan diblokir.
     Taruh data langsung di dalam JavaScript, atau pakai `<img>`, `<audio>`, `<script src>` biasa.
   - **Jangan** memakai `<script type="module">` (diblokir oleh sandbox). Pakai skrip biasa.
   - **Jangan** memakai `localStorage`/`sessionStorage` (tidak tersedia di sandbox).
4. Responsif: berfungsi dari lebar 360px (HP) sampai 1280px. Dukung sentuhan **dan** mouse
   (seret-dan-lepas harus jalan dengan jari; sediakan cara ketuk-lalu-ketuk sebagai cadangan).
5. Ukuran folder maks 20 MB. Serahkan sebagai ZIP dengan `index.html` di akar.
6. Laporan skor (disarankan): saat selesai, game mengirim pesan ke halaman induk:

```js
parent.postMessage({ type: 'mikronet:hasil', skor: 8, skorMaks: 10 }, '*');
```

   Aplikasi menyimpan **skor terbaik** siswa. Bila game tidak mengirim pesan, siswa tetap bisa bermain dan
   menekan "Tandai selesai".
7. Teks Bahasa Indonesia. Tombol "Ulangi" wajib ada.

Contoh kerja: `public/games/simulasi-p1/` (game Modul 1 dari tim DMI). Salin sebagai titik awal.
Catatan: game berjalan di iframe bersandbox tanpa `localStorage`; simpan kemajuan sementara di `window.name` seperti pada `script.js` contoh.

## Templat LKPD (JSON)

Isi soal dipisahkan dari kode. Satu berkas per pertemuan: `database/content/lkpd-p{nomor}.json`.
Contoh lengkap yang berfungsi: `database/content/lkpd-p1.json`. Ringkasnya:

```json
{
  "judul": "LKPD Pertemuan 1 · IP address dan subnetting",
  "petunjuk": "Kalimat pengantar di bagian atas halaman.",
  "bagian": [
    {
      "judul": "A. Konsep",
      "petunjuk": "opsional",
      "butir": [
        { "id": "a1", "jenis": "pilihan",  "label": "Pertanyaan?", "opsi": ["Kelas A", "Kelas B"] },
        { "id": "a2", "jenis": "paragraf", "label": "Jelaskan...", "petunjuk": "opsional" },
        { "id": "a3", "jenis": "teks",     "label": "Satu baris jawaban" },
        { "id": "a4", "jenis": "angka",    "label": "Hasil hitungan", "wajib": false },
        { "id": "a5", "jenis": "berkas",   "label": "Unggah screenshot", "maks_berkas": 3 }
      ]
    }
  ],
  "rubrik": [
    { "kriteria": "Ketepatan hitungan",
      "deskriptor": { "1": "Banyak salah", "2": "Sebagian benar", "3": "Hampir semua benar", "4": "Semua benar" } },
    { "kriteria": "Rubrik berbobot: guru mengisi angka 0 sampai maks", "maks": 20 }
  ]
}
```

Aturan:
- `id` unik dalam satu berkas, hanya huruf kecil/angka/garis bawah (`a1`, `b_ping`). **Jangan ganti `id` setelah siswa mulai mengisi**, karena jawaban tersimpan berdasarkan `id`.
- `jenis`: `teks`, `paragraf`, `pilihan` (wajib ada `opsi`), `angka`, `berkas`.
- `wajib` tidak diisi = wajib. `"wajib": false` = boleh kosong.
- Berkas yang boleh diunggah siswa: jpg, png, webp, pdf, pkt, zip (maks 20 MB per berkas).
- Rubrik skala 1-4 (tanpa `maks`): nilai = jumlah skor ÷ (jumlah kriteria × 4) × 100. Rubrik berbobot (ada `maks` per kriteria, mis. 20/15/25/25/15): nilai = jumlah skor ÷ jumlah `maks` × 100. Kategori: 86–100 Sangat Baik, 71–85 Baik, 56–70 Cukup, di bawah 56 Perlu Bimbingan.
- Tambahkan `"contoh": true` hanya untuk konten percobaan; kotak kuning "Konten contoh" muncul selama baris itu ada.
- Setelah mengganti berkas, cek dulu: buka `/modul/1/lkpd` sebagai siswa uji. Kalau JSON salah ketik, LKPD dianggap "belum tersedia".

## Untuk tim mobile

API untuk aplikasi Flutter belum dibuat di sprint ini (target sekitar 9 Oktober). Sementara itu tim mobile bisa
membangun antarmuka dengan data tiruan. P3 dan P4 (foto praktikum) memakai aplikasi mobile, bukan web.

## Alur 6 langkah per modul (urut, terkunci)

Materi → Simulasi → Praktikum → Pengumpulan → LKPD → Refleksi. Siswa hanya bisa membuka langkah berikutnya setelah langkah sebelumnya selesai
(dijaga di server, bukan sekadar tombol yang disembunyikan). Syarat selesai:

| Langkah | Selesai bila |
|---|---|
| Materi | halaman terakhir PDF sudah dibuka |
| Simulasi | game melapor skor (`mikronet:hasil`) atau siswa menekan "Tandai selesai" di bagian "Game tidak bisa dimuat?" |
| Praktikum | siswa menekan "Selesai praktikum" di langkah terakhir (server menolak bila belum sampai langkah terakhir) |
| Pengumpulan | form dikumpulkan |
| LKPD | lembar kerja dikumpulkan (pilihan ganda dinilai otomatis; uraian dinilai guru tanpa menghalangi langkah berikutnya) |
| Refleksi | siswa menyimpan refleksi (pertanyaan sama untuk semua modul; tujuan pembelajaran modul dipakai sebagai daftar centang) |

Langkah yang kontennya belum ada (status "segera") dilewati, tidak mengunci langkah sesudahnya. Tombol **Lanjut ke ...** muncul di
akhir tiap langkah.

## Templat panduan praktikum (JSON)

`database/content/praktikum-p{nomor}.json`: `judul`, `sub`, `durasi`, `tujuan[]`, `alat[]`, `teori[{istilah,arti}]`, `topologi{gambar,alt,kolom[],baris[][],catatan}`,
`tahap[{judul, langkah[{judul, isi, gambar[]}]}]`, `tantangan[]`, `masalah[[gejala,penyebab,solusi]]`. Nama gambar relatif terhadap `public/assets/praktikum/p{nomor}/`.
Contoh lengkap: `praktikum-p1.json` (18 langkah).

## Simulasi Modul 1 (game)

`public/games/simulasi-p1/`: Misi 1 IP address (5 soal), Misi 2 subnet mask & subnetting (batang 0–255 + 5 soal acak dari 9), Misi 3 susun topologi
dengan pemeriksa standar (PC hanya ke switch, router ke switch, satu jaringan 192.168.1.x, tanpa IP conflict, tanpa .0/.255) dan uji ping beranimasi.
Skor: Misi 1 = 20, Misi 2 = 30, Tantangan 1 (LAN) = 20, uji ping = 15, bonus router + gateway = 15. Skor dikirim dari `hasil.html`.

## Templat LKPD (lembar kerja) JSON

`database/content/lembar-p{nomor}.json`: `judul`, `petunjuk`, `tujuan[]`, `poin_pg` (poin per soal PG, bawaan 5),
`pg[{id, soal, opsi[] (2–5 pilihan), kunci (indeks 0 = a), bahas}]`, `uraian[{id, soal, kunci (contoh jawaban untuk guru), maks}]`.
Nilai = (skor PG + skor uraian) ÷ total maksimum × 100. Kunci dan pembahasan baru tampil ke siswa setelah LKPD dikumpulkan.
Contoh lengkap: `lembar-p1.json` (10 PG × 5 poin + 5 uraian × 10 poin = 100).


## Simpan progres game (opsional)

Supaya siswa tidak mengulang dari awal saat halaman dimuat ulang atau pindah perangkat:

1. Simpan seluruh state game sebagai teks JSON di `window.name` (bertahan antarhalaman di dalam iframe).
2. Setiap kali state berubah, kirim ke MIKRO-NET:
   ```js
   parent.postMessage({ type: 'mikronet:simpan', data: window.name }, '*');
   ```
3. Saat siswa membuka simulasi lagi, MIKRO-NET memasang data terakhir sebagai atribut `name` iframe, jadi `window.name` game langsung berisi progres itu.

Batas: JSON valid, maksimal 60.000 karakter. Disimpan di kolom `modul_siswa.game_data` (migrasi 006).


## Tambahan untuk langkah praktikum (dipakai Modul 2)

Setiap objek di `tahap[].langkah[]` boleh punya kolom opsional:

- `kode`: perintah CLI (teks, baris dipisah `\n`). Tampil di kotak gelap dengan tombol Salin.
- `tabel`: `{ "kolom": [...], "baris": [[...], ...] }` untuk tabel kecil (kabel, IP).
- `catatan`: kalimat peringatan/pertanyaan diskusi (kotak kuning).
- `cek`: daftar checkpoint (kotak hijau "Checkpoint, pastikan:").

Judul tahap yang diawali kata "Tahap" ditampilkan apa adanya (tanpa nomor tambahan).

## Pertanyaan refleksi khusus modul

`database/content/refleksi-p{nomor}.json`: `{ "pertanyaan": ["...", "..."] }`. Opsional. Bila ada, siswa menjawab pertanyaan ini di halaman Refleksi (wajib diisi), dan guru melihat jawabannya di halaman Refleksi kelas.

## Isi modul diedit guru (sejak v10)

Semua isi langkah modul sekarang bisa diubah guru dari **Kelola modul → Edit modul → Isi langkah modul**, tanpa menyentuh berkas:

| Jenis | Berkas bawaan (dipakai bila belum diedit) | Keterangan |
|---|---|---|
| `praktikum` | `database/content/praktikum-pN.json` | Gambar diunggah lewat editor ke `public/assets/praktikum/pN/` (nama `u-…`) |
| `lkpd` | `lkpd-pN.json` | Langkah **Pengumpulan** |
| `lembar` | `lembar-pN.json` | Langkah **LKPD** (PG + uraian). Isi `topik` di tiap soal untuk analisis kelemahan |
| `refleksi` | `refleksi-pN.json` | Pertanyaan khusus modul |
| `remidi`, `pengayaan` | `remidi-pN.json`, `pengayaan-pN.json` | Format sama dengan `lembar` |

- Hasil edit disimpan di tabel `modul_konten` dan **mengalahkan** berkas JSON. Versi lama (termasuk isi bawaan) masuk `modul_konten_riwayat` (20 versi) dan bisa dipulihkan.
- Setelah ada jawaban siswa, soal/butir lama terkunci: kunci jawaban, jumlah pilihan, jenis isian, dan skor maksimal tidak bisa diubah atau dihapus. Teks boleh dirapikan dan soal baru boleh ditambah.
- Slide materi: guru mengunggah **PDF** (PowerPoint/Google Slides disimpan sebagai PDF dulu).
- Simulasi: **admin** memasang folder simulasi dari ZIP di **Kelola simulasi** (harus ada `index.html`; hanya berkas web statis, tanpa PHP). Lalu folder dipilih di pengaturan modul. Kontrak `postMessage` di atas tetap berlaku.

## Nilai modul, KKM, remidi, dan pengayaan (sejak v11)

- **Nilai modul** = rata-rata berbobot nilai praktik (Pengumpulan, rubrik), LKPD, dan simulasi (skor game dijadikan skala 100). KKM dan bobot diatur guru per kelas di **Analisis → Aturan nilai** (bawaan KKM 75, bobot 40/40/20). Komponen yang tidak ada di modul itu tidak ikut dihitung.
- Status muncul setelah semua komponen bernilai: **Tuntas** (≥ KKM) → ditawari *pengayaan*; **Belum tuntas** → wajib *remidi*.
- Nilai akhir = max(nilai awal, min(nilai remidi, KKM)). Nilai pengayaan dicatat terpisah.
- Soal remidi/pengayaan: `remidi-pN.json` dan `pengayaan-pN.json` (format sama dengan `lembar`), bisa diedit guru.
- Analisis kelemahan memakai `topik` di setiap soal LKPD dan nama `kriteria` rubrik praktik; capaian di bawah 60% ditandai "perlu diperkuat".

## Ruang diskusi (sejak v12)

- Menu **Diskusi** untuk siswa, guru, dan admin. Setiap pertanyaan punya *ruang* (Semua kelas = lintas kelas, atau satu kelas saja) dan *topik* (Umum atau Modul tertentu).
- Siswa lain bisa membalas atau menekan **Saya juga ingin tahu**. Balasan guru diberi lencana *Guru*; penanya atau guru bisa menandai satu balasan sebagai **Jawaban** (topik otomatis selesai).
- Guru pengajar dan admin bisa menyematkan, menandai selesai, dan menghapus pertanyaan/balasan. Batas kirim 12 pesan per 10 menit per akun.
