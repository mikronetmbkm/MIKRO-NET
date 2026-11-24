<?php
// Model Modul: 10 modul, kemajuan belajar tiap siswa, dan langkah-langkah di dalam modul.
final class Modul
{
    public static function semua(): array
    {
        return DB::semua('SELECT * FROM modul ORDER BY nomor');
    }

    /** Modul yang sudah terbit, urut nomor. */
    public static function terbit(): array
    {
        return array_values(array_filter(self::semua(), fn($m) => $m['status'] === 'terbit'));
    }

    /** Modul "minggu ini": modul terbit terakhir yang tanggalnya sudah lewat/hari ini; kalau belum ada, modul terbit pertama. */
    public static function aktif(?array $terbit = null): ?array
    {
        $terbit ??= self::terbit();
        $pilih = null;
        foreach ($terbit as $m) {
            if ($m['tanggal'] && $m['tanggal'] <= date('Y-m-d')) {
                $pilih = $m;
            }
        }
        return $pilih ?? ($terbit[0] ?? null);
    }

    public static function byId(int $id): ?array
    {
        return DB::satu('SELECT * FROM modul WHERE id = ?', [$id]);
    }

    public static function tujuanDaftar(array $modul): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $modul['tujuan']))));
    }

    /** Baris kemajuan siswa untuk satu modul (dibuat kosong bila belum ada). */
    public static function progres(int $modulId, int $siswaId): array
    {
        $r = DB::satu('SELECT * FROM modul_siswa WHERE modul_id = ? AND siswa_id = ?', [$modulId, $siswaId]);
        return $r ?? [
            'halaman_terakhir' => 0, 'total_halaman' => 0, 'simulasi_selesai' => 0,
            'game_skor' => null, 'game_skor_maks' => null, 'praktikum_langkah' => 0, 'praktikum_selesai' => 0, 'refleksi' => null, 'game_data' => null,
        ];
    }

    /** Ubah beberapa kolom kemajuan. Nama kolom dibatasi daftar putih, bukan dari input pengguna. */
    public static function catat(int $modulId, int $siswaId, array $kolom): void
    {
        $boleh = ['halaman_terakhir', 'total_halaman', 'simulasi_selesai', 'game_skor', 'game_skor_maks', 'praktikum_langkah', 'praktikum_selesai', 'refleksi', 'game_data'];
        $kolom = array_intersect_key($kolom, array_flip($boleh));
        if (!$kolom) {
            return;
        }
        $ada = DB::nilai('SELECT id FROM modul_siswa WHERE modul_id = ? AND siswa_id = ?', [$modulId, $siswaId]);
        if ($ada) {
            $set = implode(', ', array_map(fn($k) => "$k = ?", array_keys($kolom)));
            DB::jalankan("UPDATE modul_siswa SET $set, diperbarui_pada = ? WHERE id = ?", [...array_values($kolom), sekarang(), $ada]);
        } else {
            $kolomSql = implode(', ', array_keys($kolom));
            $tanda = implode(', ', array_fill(0, count($kolom), '?'));
            DB::jalankan("INSERT INTO modul_siswa (modul_id, siswa_id, $kolomSql, diperbarui_pada) VALUES (?, ?, $tanda, ?)", [$modulId, $siswaId, ...array_values($kolom), sekarang()]);
        }
    }

    /**
     * Enam langkah di dalam modul beserta statusnya untuk seorang siswa: Materi, Simulasi, Praktikum, Pengumpulan, LKPD, Refleksi.
     * Status: selesai | berjalan | belum | segera (konten belum dipasang, dilewati) | terkunci (langkah sebelumnya belum selesai)
     * Aturan urutan: langkah baru bisa dibuka setelah langkah sebelumnya selesai. Langkah "segera" tidak menghalangi.
     */
    public static function langkah(array $modul, int $siswaId): array
    {
        $id = (int) $modul['id'];
        $p = self::progres($id, $siswaId);
        $lkpd = Lkpd::ambil($id, $siswaId);
        $adaLkpd = Lkpd::definisi((int) $modul['nomor']) !== null;
        $adaPraktikum = self::praktikum((int) $modul['nomor']) !== null;
        $adaLembar = Lembar::definisi((int) $modul['nomor']) !== null;
        $lembar = $adaLembar ? Lembar::ambil($id, $siswaId) : null;
        $materiSelesai = (int) $p['total_halaman'] > 0 && (int) $p['halaman_terakhir'] >= (int) $p['total_halaman'];
        $materiMulai = (int) $p['halaman_terakhir'] > 0;
        $adaGame = (string) $modul['game_folder'] !== '' && preg_match('/^[a-z0-9_-]+$/i', (string) $modul['game_folder'])
            && is_file(ROOT . '/public/games/' . $modul['game_folder'] . '/index.html');
        $simSelesai = (int) $p['simulasi_selesai'] || $p['game_skor'] !== null;

        $langkah = [
            ['kunci' => 'materi', 'judul' => 'Materi', 'ikon' => 'book',
             'ket' => $p['total_halaman'] ? ((int) $p['total_halaman'] . ' halaman · ' . (int) $p['halaman_terakhir'] . ' sudah dibaca') : 'Slide ajar, baca sampai halaman terakhir',
             'status' => $materiSelesai ? 'selesai' : ($materiMulai ? 'berjalan' : 'belum'),
             'tombol' => $materiMulai && !$materiSelesai ? 'Lanjut baca' : ($materiSelesai ? 'Baca lagi' : 'Mulai baca'), 'href' => '/modul/' . $id . '/materi'],
            ['kunci' => 'simulasi', 'judul' => 'Simulasi interaktif', 'ikon' => 'game',
             'ket' => self::KET_SIMULASI[(int) $modul['nomor']] ?? 'Permainan interaktif untuk mencoba konsep modul ini',
             'status' => $adaGame ? ($simSelesai ? 'selesai' : 'belum') : 'segera',
             'tombol' => $simSelesai ? 'Main lagi' : 'Main', 'href' => '/modul/' . $id . '/simulasi'],
            ['kunci' => 'praktikum', 'judul' => 'Praktikum', 'ikon' => 'wrench',
             'ket' => $adaPraktikum ? (self::KET_PRAKTIKUM[(int) $modul['nomor']] ?? 'Panduan praktikum langkah demi langkah') : 'Panduan praktikum',
             'status' => !$adaPraktikum ? 'segera' : ((int) $p['praktikum_selesai'] ? 'selesai' : ((int) $p['praktikum_langkah'] > 0 ? 'berjalan' : 'belum')),
             'tombol' => (int) $p['praktikum_selesai'] ? 'Buka lagi' : ((int) $p['praktikum_langkah'] > 0 ? 'Lanjut praktik' : 'Mulai praktik'), 'href' => '/modul/' . $id . '/praktikum'],
            ['kunci' => 'lkpd', 'judul' => 'Pengumpulan', 'ikon' => 'upload',
             'ket' => self::KET_PENGUMPULAN[(int) $modul['nomor']] ?? 'Unggah bukti hasil praktik',
             'status' => !$adaLkpd ? 'segera' : ($lkpd && $lkpd['status'] === 'dikumpulkan' ? 'selesai' : ($lkpd ? 'berjalan' : 'belum')),
             'tombol' => $lkpd && $lkpd['status'] === 'dikumpulkan' ? 'Lihat' : 'Kumpulkan', 'href' => '/modul/' . $id . '/lkpd'],
            ['kunci' => 'lembar', 'judul' => 'LKPD', 'ikon' => 'clip',
             'ket' => $adaLembar && !($lembar && $lembar['status'] === 'dikumpulkan') && !Lembar::terbukaUntuk($id, $siswaId)
                ? 'Sedang ditutup gurumu. Tunggu sampai dibuka.' : 'Soal pilihan ganda dan uraian tentang materi hari ini',
             'status' => !$adaLembar ? 'segera' : ($lembar && $lembar['status'] === 'dikumpulkan' ? 'selesai' : ($lembar ? 'berjalan' : 'belum')),
             'tombol' => $lembar && $lembar['status'] === 'dikumpulkan' ? 'Lihat hasil' : ($lembar ? 'Lanjut kerjakan' : 'Kerjakan'), 'href' => '/modul/' . $id . '/lembar'],
            ['kunci' => 'refleksi', 'judul' => 'Refleksi', 'ikon' => 'edit',
             'ket' => 'Ceritakan apa yang sudah kamu pahami dan yang masih bingung',
             'status' => ($p['refleksi'] ?? null) !== null && $p['refleksi'] !== '' ? 'selesai' : 'belum',
             'tombol' => ($p['refleksi'] ?? null) ? 'Lihat' : 'Isi refleksi', 'href' => '/modul/' . $id . '/refleksi'],
        ];
        // Bawaan: semua langkah bebas dibuka (siswa boleh melompat ke LKPD/refleksi bila waktu mepet).
        // Isi LANGKAH_URUT=1 di .env untuk mewajibkan urutan: langkah yang belum selesai mengunci langkah sesudahnya.
        if (!self::urutWajib()) {
            return $langkah;
        }
        $terbuka = true;
        foreach ($langkah as &$l) {
            if ($l['status'] === 'segera') {
                continue;
            }
            if (!$terbuka && $l['status'] !== 'selesai') {
                $l['status'] = 'terkunci';
            }
            if ($l['status'] !== 'selesai') {
                $terbuka = false;
            }
        }
        unset($l);
        return $langkah;
    }

    /** Apakah langkah modul wajib dikerjakan berurutan? Diatur lewat LANGKAH_URUT di .env (bawaan: tidak). */
    public static function urutWajib(): bool
    {
        return in_array(strtolower(trim((string) Env::get('LANGKAH_URUT', '0'))), ['1', 'true', 'ya', 'on'], true);
    }

    /** Langkah berikutnya sesudah $kunci yang kontennya sudah ada (bukan "segera"), atau null bila sudah yang terakhir. */
    public static function berikut(array $langkah, string $kunci): ?array
    {
        $lewat = false;
        foreach ($langkah as $l) {
            if ($lewat && $l['status'] !== 'segera') {
                return $l;
            }
            if ($l['kunci'] === $kunci) {
                $lewat = true;
            }
        }
        return null;
    }

    /** Apakah langkah ini boleh dibuka siswa sekarang? */
    public static function langkahTerbuka(array $modul, int $siswaId, string $kunci): bool
    {
        foreach (self::langkah($modul, $siswaId) as $l) {
            if ($l['kunci'] === $kunci) {
                return $l['status'] !== 'terkunci';
            }
        }
        return false;
    }

    /** Panduan praktikum dibaca dari database/content/praktikum-p{nomor}.json. Null = belum ada. */
    public static function praktikum(int $nomor): ?array
    {
        $d = Konten::ambil($nomor, 'praktikum');
        return is_array($d) && !empty($d['tahap']) ? $d : null;
    }

    /** Jumlah langkah praktikum (semua langkah di semua tahap). */
    public static function jumlahLangkahPraktikum(array $def): int
    {
        $n = 0;
        foreach ($def['tahap'] ?? [] as $t) {
            $n += count($t['langkah'] ?? []);
        }
        return $n;
    }

    /** Pertanyaan refleksi khusus modul (opsional): database/content/refleksi-p{nomor}.json {"pertanyaan": [...]}. */
    public static function refleksiKhusus(int $nomor): array
    {
        $d = Konten::ambil($nomor, 'refleksi');
        return is_array($d['pertanyaan'] ?? null) ? array_values(array_map('strval', $d['pertanyaan'])) : [];
    }

    /** Keterangan singkat game simulasi tiap modul (tampil di daftar langkah). */
    public const KET_SIMULASI = [
        1 => '3 misi: IP address, subnetting, susun topologi & uji ping',
        2 => '3 misi: VLAN & trunk, router-on-a-stick & DHCP, VLSM & static route',
        3 => '3 misi: kenali Winbox, pasang IP & uji ping, internet & firewall',
        4 => '3 misi: sifat sinyal, hotspot & koneksi, channel & interferensi',
    ];

    /** Keterangan langkah Praktikum dan Pengumpulan tiap modul (modul buatan guru memakai teks umum). */
    public const KET_PRAKTIKUM = [
        1 => 'Panduan langkah demi langkah di Cisco Packet Tracer',
        2 => 'Panduan langkah demi langkah di Cisco Packet Tracer',
        3 => 'Panduan langkah demi langkah konfigurasi MikroTik dengan Winbox',
        4 => 'Panduan merakit wajanbolic dan menyambungkan dua laptop lewat hotspot',
    ];
    public const KET_PENGUMPULAN = [
        1 => 'Unggah file .pkt dan screenshot hasil praktik',
        2 => 'Unggah file .pkt dan screenshot hasil praktik',
        3 => 'Unggah screenshot bukti ping, internet, dan firewall',
        4 => 'Unggah foto wajanbolic dan foto layar hotspot laptop pemancar',
    ];

    /** Pertanyaan refleksi (sama untuk semua modul). Skala paham 1-5. */
    public const SKALA_PAHAM = [1 => 'Belum paham sama sekali', 2 => 'Sedikit paham', 3 => 'Lumayan paham', 4 => 'Paham', 5 => 'Paham dan bisa menjelaskan ke teman'];

    /** Isi refleksi tersimpan (JSON di kolom modul_siswa.refleksi), atau null. */
    public static function refleksi(array $progres): ?array
    {
        $r = $progres['refleksi'] ?? null;
        if ($r === null || $r === '') {
            return null;
        }
        $d = json_decode((string) $r, true);
        return is_array($d) ? $d : ['penting' => (string) $r];
    }

    public static function persen(array $langkah): int
    {
        $selesai = count(array_filter($langkah, fn($l) => $l['status'] === 'selesai'));
        return (int) round($selesai / max(1, count($langkah)) * 100);
    }

    public static function simpan(int $id, array $d): void
    {
        DB::jalankan(
            'UPDATE modul SET judul = ?, ringkasan = ?, tujuan = ?, tanggal = ?, status = ?, game_folder = ?, video_url = ?, diperbarui_pada = ? WHERE id = ?',
            [$d['judul'], $d['ringkasan'], $d['tujuan'], $d['tanggal'], $d['status'], $d['game_folder'], $d['video_url'] ?? null, sekarang(), $id]
        );
    }

    /**
     * Video penjelasan untuk langkah Materi.
     * - berkas: video bawaan di public/assets/video/materi-p{nomor}.mp4 (opsional, ikut paket konten)
     * - youtube: id video bila guru menempel link YouTube di Kelola modul
     * - tautan: link https lain (ditampilkan sebagai tautan)
     */
    public static function video(array $modul): array
    {
        $n = (int) $modul['nomor'];
        $hasil = ['berkas' => null, 'poster' => null, 'youtube' => null, 'tautan' => null];
        if (is_file(ROOT . '/public/assets/video/materi-p' . $n . '.mp4')) {
            $hasil['berkas'] = 'video/materi-p' . $n . '.mp4';
            if (is_file(ROOT . '/public/assets/video/materi-p' . $n . '.jpg')) {
                $hasil['poster'] = 'video/materi-p' . $n . '.jpg';
            }
        }
        $u = trim((string) ($modul['video_url'] ?? ''));
        if ($u !== '') {
            if (preg_match('~(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/)|youtu\.be/)([A-Za-z0-9_-]{11})~', $u, $m)) {
                $hasil['youtube'] = $m[1];
            } elseif (preg_match('~^https://~i', $u)) {
                $hasil['tautan'] = $u;
            }
        }
        return $hasil;
    }

    /** Validasi link video dari form guru: kosong, atau https maksimal 500 karakter. */
    public static function videoUrlValid(string $u): bool
    {
        return $u === '' || (strlen($u) <= 500 && (bool) preg_match('~^https://[^\s<>"]+$~i', $u));
    }

    public static function setMateri(int $id, string $nama): void
    {
        DB::jalankan('UPDATE modul SET materi_berkas = ?, diperbarui_pada = ? WHERE id = ?', [$nama, sekarang(), $id]);
    }

    /** Lokasi PDF materi: unggahan admin, lalu slide bawaan modul, lalu PDF contoh (ditandai sebagai konten contoh). */
    public static function berkasMateri(array $modul): array
    {
        if ($modul['materi_berkas']) {
            $jalan = ROOT . '/storage/uploads/materi/' . basename($modul['materi_berkas']);
            if (is_file($jalan)) {
                return ['jalan' => $jalan, 'contoh' => false];
            }
        }
        // Slide bawaan dari tim materi: database/content/materi-p{nomor}.pdf (bukan konten contoh).
        $bawaan = ROOT . '/database/content/materi-p' . (int) $modul['nomor'] . '.pdf';
        if (is_file($bawaan)) {
            return ['jalan' => $bawaan, 'contoh' => false];
        }
        $contoh = ROOT . '/database/content/contoh-materi.pdf';
        return ['jalan' => is_file($contoh) ? $contoh : '', 'contoh' => true];
    }

    /** Dokumen PDF per modul: 'ajar' (modul ajar, khusus guru) dan 'bacaan' (bahan bacaan siswa). */
    public const DOKUMEN = [
        'ajar' => ['kolom' => 'modul_ajar_berkas', 'bawaan' => 'modul-ajar-p', 'nama' => 'Modul ajar', 'unduh' => 'Modul-Ajar-Pertemuan-'],
        'bacaan' => ['kolom' => 'bacaan_berkas', 'bawaan' => 'bacaan-p', 'nama' => 'Bahan bacaan', 'unduh' => 'Bahan-Bacaan-Pertemuan-'],
    ];

    /** Lokasi dokumen: unggahan guru lebih dulu, lalu berkas bawaan. sumber: 'unggah' | 'bawaan' | null. */
    public static function dokumen(array $modul, string $jenis): array
    {
        $d = self::DOKUMEN[$jenis];
        $nama = (string) ($modul[$d['kolom']] ?? '');
        if ($nama !== '') {
            $jalan = ROOT . '/storage/uploads/dokumen/' . basename($nama);
            if (is_file($jalan)) {
                return ['jalan' => $jalan, 'sumber' => 'unggah', 'ukuran' => filesize($jalan)];
            }
        }
        $bawaan = ROOT . '/database/content/' . $d['bawaan'] . (int) $modul['nomor'] . '.pdf';
        return is_file($bawaan) ? ['jalan' => $bawaan, 'sumber' => 'bawaan', 'ukuran' => filesize($bawaan)] : ['jalan' => '', 'sumber' => null, 'ukuran' => 0];
    }

    public static function setDokumen(int $id, string $jenis, ?string $nama): void
    {
        $kolom = self::DOKUMEN[$jenis]['kolom'];
        DB::jalankan("UPDATE modul SET $kolom = ?, diperbarui_pada = ? WHERE id = ?", [$nama, sekarang(), $id]);
    }

    /** Tambah modul baru di urutan berikutnya. Statusnya "terkunci" sampai guru/admin menerbitkannya. */
    public static function tambah(string $judul): int
    {
        // Nomor yang punya isi bawaan (slide, praktikum, video) tidak dipakai ulang agar modul baru tidak mewarisi isi lama.
        $nomor = (int) DB::nilai('SELECT MAX(nomor) FROM modul') + 1;
        while (Konten::adaBawaan($nomor)) {
            $nomor++;
        }
        return DB::sisip(
            "INSERT INTO modul (nomor, judul, status, ikon, diperbarui_pada) VALUES (?, ?, 'terkunci', 'book', ?)",
            [$nomor, $judul, sekarang()]
        );
    }
}
