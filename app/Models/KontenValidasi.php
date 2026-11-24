<?php
// Membersihkan dan memeriksa isi modul kiriman editor guru sebelum disimpan.
// Hanya kunci yang dikenal yang diterima; teks dipangkas dan dibatasi panjangnya; gambar harus berkas yang ada.
// Soal yang sudah dijawab siswa dikunci: kunci jawaban, jumlah pilihan, dan skor maksimalnya tidak boleh berubah.
final class KontenValidasi
{
    private array $galat = [];

    public function __construct(private int $nomor, private ?array $lama, private array $kunci)
    {
    }

    /** @return array{0:?array,1:array} [isi bersih (null bila kosong), daftar galat] */
    public static function periksa(string $jenis, array $data, int $nomor, ?array $lama, array $kunci): array
    {
        $v = new self($nomor, $lama, $kunci);
        $isi = match ($jenis) {
            'praktikum' => $v->praktikum($data),
            'lkpd' => $v->lkpd($data),
            'lembar', 'remidi', 'pengayaan' => $v->soal($data, $jenis),
            'refleksi' => $v->refleksi($data),
        };
        return [$isi, $v->galat];
    }

    // ---------- alat bantu ----------
    private function t(mixed $v, int $maks): string
    {
        $s = is_scalar($v) ? (string) $v : '';
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s) ?? '';
        return mb_substr(trim(str_replace("\r\n", "\n", $s)), 0, $maks);
    }

    private function daftarTeks(mixed $v, int $maksItem, int $maksPanjang): array
    {
        $hasil = [];
        foreach (is_array($v) ? $v : [] as $x) {
            $s = $this->t($x, $maksPanjang);
            if ($s !== '') {
                $hasil[] = $s;
            }
        }
        return array_slice($hasil, 0, $maksItem);
    }

    private function arr(mixed $v, int $maks): array
    {
        return array_slice(is_array($v) ? array_values($v) : [], 0, $maks);
    }

    private function tabel(mixed $v): ?array
    {
        if (!is_array($v)) {
            return null;
        }
        $kolom = $this->daftarTeks($v['kolom'] ?? [], 8, 80);
        $baris = [];
        foreach ($this->arr($v['baris'] ?? [], 30) as $b) {
            $sel = array_map(fn($c) => $this->t($c, 300), $this->arr($b, 8));
            if (array_filter($sel, fn($c) => $c !== '')) {
                $baris[] = $sel;
            }
        }
        return $kolom || $baris ? ['kolom' => $kolom, 'baris' => $baris] : null;
    }

    private function gambar(mixed $v, int $maks): array
    {
        $hasil = [];
        foreach ($this->arr($v, $maks) as $g) {
            $g = is_string($g) ? basename($g) : '';
            if (preg_match('/^[A-Za-z0-9._-]{1,80}\.(png|jpe?g|webp|gif)$/i', $g) && is_file(ROOT . '/public/assets/praktikum/p' . $this->nomor . '/' . $g)) {
                $hasil[] = $g;
            } elseif ($g !== '') {
                $this->galat[] = 'Gambar "' . $g . '" tidak ditemukan. Unggah ulang gambarnya.';
            }
        }
        return $hasil;
    }

    private function idUnik(string $calon, array &$dipakai, string $awalan): string
    {
        $id = strtolower(preg_replace('/[^a-z0-9_]/i', '_', $calon) ?? '');
        $id = trim(preg_replace('/_+/', '_', $id) ?? '', '_');
        if (!preg_match('/^[a-z][a-z0-9_]{0,39}$/', $id)) {
            $id = $awalan;
        }
        $dasar = substr($id, 0, 34);
        $n = 1;
        while (isset($dipakai[$id])) {
            $id = $dasar . (++$n);
        }
        $dipakai[$id] = true;
        return $id;
    }

    // ---------- praktikum ----------
    private function praktikum(array $d): ?array
    {
        $isi = [
            'judul' => $this->t($d['judul'] ?? '', 200),
            'sub' => $this->t($d['sub'] ?? '', 600),
            'durasi' => $this->t($d['durasi'] ?? '', 40),
            'tanpa_gambar' => $this->t($d['tanpa_gambar'] ?? '', 200) ?: 'Kerjakan langsung pada perangkat.',
            'tujuan' => $this->daftarTeks($d['tujuan'] ?? [], 20, 300),
            'alat' => $this->daftarTeks($d['alat'] ?? [], 20, 300),
            'teori' => [],
            'tahap' => [],
            'tantangan' => $this->daftarTeks($d['tantangan'] ?? [], 10, 600),
            'masalah' => [],
        ];
        foreach ($this->arr($d['teori'] ?? [], 20) as $t) {
            $ist = $this->t($t['istilah'] ?? '', 100);
            $arti = $this->t($t['arti'] ?? '', 500);
            if ($ist !== '' || $arti !== '') {
                $isi['teori'][] = ['istilah' => $ist, 'arti' => $arti];
            }
        }
        $tp = is_array($d['topologi'] ?? null) ? $d['topologi'] : [];
        $gb = $this->gambar([$tp['gambar'] ?? ''], 1);
        $tb = $this->tabel($tp);
        if ($gb || $tb) {
            $isi['topologi'] = ['gambar' => $gb[0] ?? '', 'alt' => $this->t($tp['alt'] ?? '', 200), 'kolom' => $tb['kolom'] ?? [], 'baris' => $tb['baris'] ?? [], 'catatan' => $this->t($tp['catatan'] ?? '', 500)];
        }
        foreach ($this->arr($d['tahap'] ?? [], 15) as $ti => $t) {
            $tahap = ['judul' => $this->t($t['judul'] ?? '', 120) ?: 'Tahap ' . ($ti + 1), 'langkah' => []];
            foreach ($this->arr($t['langkah'] ?? [], 40) as $l) {
                $s = ['judul' => $this->t($l['judul'] ?? '', 150), 'isi' => $this->t($l['isi'] ?? '', 2500), 'gambar' => $this->gambar($l['gambar'] ?? [], 4)];
                if ($s['judul'] === '' && $s['isi'] === '') {
                    continue;
                }
                if ($s['judul'] === '') {
                    $this->galat[] = $tahap['judul'] . ': ada langkah tanpa judul.';
                }
                if ($tb = $this->tabel($l['tabel'] ?? null)) {
                    $s['tabel'] = $tb;
                }
                $kode = $this->t($l['kode'] ?? '', 6000);
                if ($kode !== '') {
                    $s['kode'] = $kode;
                    $lbl = $this->t($l['kode_label'] ?? '', 80);
                    if ($lbl !== '') {
                        $s['kode_label'] = $lbl;
                    }
                }
                $cat = $this->t($l['catatan'] ?? '', 1000);
                if ($cat !== '') {
                    $s['catatan'] = $cat;
                }
                $cek = $this->daftarTeks($l['cek'] ?? [], 12, 300);
                if ($cek) {
                    $s['cek'] = $cek;
                }
                $tahap['langkah'][] = $s;
            }
            if ($tahap['langkah']) {
                $isi['tahap'][] = $tahap;
            }
        }
        foreach ($this->arr($d['masalah'] ?? [], 30) as $m) {
            $r = array_map(fn($c) => $this->t($c, 400), $this->arr($m, 3)) + ['', '', ''];
            if ($r[0] !== '') {
                $isi['masalah'][] = [$r[0], $r[1], $r[2]];
            }
        }
        if ($isi['judul'] === '') {
            $this->galat[] = 'Judul praktikum wajib diisi.';
        }
        if (!$isi['tahap']) {
            $this->galat[] = 'Praktikum butuh minimal satu tahap berisi satu langkah.';
        }
        return $isi;
    }

    // ---------- pengumpulan (lkpd) ----------
    private function lkpd(array $d): ?array
    {
        $isi = ['judul' => $this->t($d['judul'] ?? '', 200), 'petunjuk' => $this->t($d['petunjuk'] ?? '', 800), 'bagian' => [], 'rubrik' => []];
        $dipakai = [];
        $jenisOk = ['teks', 'paragraf', 'pilihan', 'angka', 'berkas'];
        foreach ($this->arr($d['bagian'] ?? [], 12) as $b) {
            $bag = ['judul' => $this->t($b['judul'] ?? '', 150), 'butir' => []];
            $p = $this->t($b['petunjuk'] ?? '', 600);
            if ($p !== '') {
                $bag['petunjuk'] = $p;
            }
            foreach ($this->arr($b['butir'] ?? [], 40) as $x) {
                $label = $this->t($x['label'] ?? '', 300);
                if ($label === '') {
                    continue;
                }
                $jenis = in_array($x['jenis'] ?? '', $jenisOk, true) ? $x['jenis'] : 'teks';
                $butir = ['id' => $this->idUnik((string) ($x['id'] ?? '') ?: $label, $dipakai, 'butir'), 'jenis' => $jenis, 'label' => $label];
                $pt = $this->t($x['petunjuk'] ?? '', 400);
                if ($pt !== '') {
                    $butir['petunjuk'] = $pt;
                }
                if ($jenis === 'pilihan') {
                    $butir['opsi'] = $this->daftarTeks($x['opsi'] ?? [], 10, 150);
                    if (count($butir['opsi']) < 2) {
                        $this->galat[] = '"' . $label . '": pilihan butuh minimal 2 opsi.';
                    }
                }
                if ($jenis === 'berkas') {
                    $butir['maks_berkas'] = max(1, min(10, (int) ($x['maks_berkas'] ?? 3)));
                }
                if (($x['wajib'] ?? true) === false || ($x['wajib'] ?? '') === '0') {
                    $butir['wajib'] = false;
                }
                $bag['butir'][] = $butir;
            }
            if ($bag['butir']) {
                $isi['bagian'][] = $bag;
            }
        }
        foreach ($this->arr($d['rubrik'] ?? [], 15) as $r) {
            $k = $this->t($r['kriteria'] ?? '', 300);
            if ($k !== '') {
                $isi['rubrik'][] = ['kriteria' => $k, 'maks' => max(1, min(100, (int) ($r['maks'] ?? 10)))];
            }
        }
        if (!$isi['bagian']) {
            $this->galat[] = 'Pengumpulan butuh minimal satu bagian berisi satu butir.';
        }
        if (!$isi['rubrik']) {
            $this->galat[] = 'Tambahkan minimal satu kriteria rubrik penilaian.';
        }
        if ($this->kunci['terkumpul'] ?? false) {
            $baru = [];
            foreach ($isi['bagian'] as $b) {
                foreach ($b['butir'] as $x) {
                    $baru[$x['id']] = $x['jenis'];
                }
            }
            foreach ($this->lama['bagian'] ?? [] as $b) {
                foreach ($b['butir'] ?? [] as $x) {
                    if (!isset($baru[$x['id']])) {
                        $this->galat[] = 'Butir "' . ($x['label'] ?? $x['id']) . '" sudah diisi siswa, jadi tidak bisa dihapus.';
                    } elseif ($baru[$x['id']] !== $x['jenis']) {
                        $this->galat[] = 'Jenis isian "' . ($x['label'] ?? $x['id']) . '" tidak bisa diubah karena sudah diisi siswa.';
                    }
                }
            }
        }
        if ($this->kunci['dinilai'] ?? false) {
            $lamaR = array_column($this->lama['rubrik'] ?? [], 'maks');
            if (array_map('intval', $lamaR) !== array_column($isi['rubrik'], 'maks')) {
                $this->galat[] = 'Sudah ada pengumpulan yang dinilai, jadi jumlah kriteria dan skor maksimal rubrik tidak bisa diubah (teks kriteria boleh dirapikan).';
            }
        }
        return $isi;
    }

    // ---------- soal (LKPD, remidi, pengayaan) ----------
    private function soal(array $d, string $jenis): ?array
    {
        $isi = [
            'judul' => $this->t($d['judul'] ?? '', 200),
            'petunjuk' => $this->t($d['petunjuk'] ?? '', 800),
            'tujuan' => $this->daftarTeks($d['tujuan'] ?? [], 15, 300),
            'poin_pg' => max(1, min(20, (int) ($d['poin_pg'] ?? 5))),
            'pg' => [],
            'uraian' => [],
        ];
        $dipakai = [];
        foreach ($this->arr($d['pg'] ?? [], 60) as $i => $s) {
            $soal = $this->t($s['soal'] ?? '', 1500);
            if ($soal === '') {
                continue;
            }
            $opsi = array_map(fn($o) => $this->t($o, 400), $this->arr($s['opsi'] ?? [], 5));
            while ($opsi && end($opsi) === '') {
                array_pop($opsi);
            }
            $no = count($isi['pg']) + 1;
            if (count($opsi) < 2 || in_array('', $opsi, true)) {
                $this->galat[] = 'Pilihan ganda no. ' . $no . ': isi minimal 2 pilihan jawaban tanpa yang kosong di tengah.';
            }
            $kunci = (int) ($s['kunci'] ?? -1);
            if ($kunci < 0 || $kunci >= count($opsi)) {
                $this->galat[] = 'Pilihan ganda no. ' . $no . ': pilih kunci jawabannya.';
            }
            $item = ['id' => $this->idUnik((string) ($s['id'] ?? ''), $dipakai, 'pg' . ($i + 1)), 'soal' => $soal, 'opsi' => $opsi, 'kunci' => $kunci, 'bahas' => $this->t($s['bahas'] ?? '', 1500)];
            $tp = $this->t($s['topik'] ?? '', 60);
            if ($tp !== '') {
                $item['topik'] = $tp;
            }
            $isi['pg'][] = $item;
        }
        foreach ($this->arr($d['uraian'] ?? [], 20) as $i => $u) {
            $soal = $this->t($u['soal'] ?? '', 2000);
            if ($soal === '') {
                continue;
            }
            $item = ['id' => $this->idUnik((string) ($u['id'] ?? ''), $dipakai, 'u' . ($i + 1)), 'soal' => $soal, 'kunci' => $this->t($u['kunci'] ?? '', 3000), 'maks' => max(1, min(100, (int) ($u['maks'] ?? 10)))];
            $tp = $this->t($u['topik'] ?? '', 60);
            if ($tp !== '') {
                $item['topik'] = $tp;
            }
            $isi['uraian'][] = $item;
        }
        if (!$isi['pg'] && !$isi['uraian']) {
            $this->galat[] = 'Tambahkan minimal satu soal.';
        }
        if ($this->kunci['terkumpul'] ?? false) {
            if ((int) ($this->lama['poin_pg'] ?? 5) !== $isi['poin_pg']) {
                $this->galat[] = 'Poin per soal pilihan ganda tidak bisa diubah karena sudah ada siswa yang mengumpulkan.';
            }
            $baruPg = array_column($isi['pg'], null, 'id');
            foreach ($this->lama['pg'] ?? [] as $i => $s) {
                $b = $baruPg[$s['id']] ?? null;
                if (!$b) {
                    $this->galat[] = 'Pilihan ganda "' . mb_strimwidth((string) $s['soal'], 0, 60, '…') . '" sudah dijawab siswa, jadi tidak bisa dihapus.';
                } elseif ((int) $b['kunci'] !== (int) $s['kunci'] || count($b['opsi']) !== count($s['opsi'] ?? [])) {
                    $this->galat[] = 'Kunci jawaban dan jumlah pilihan soal "' . mb_strimwidth((string) $s['soal'], 0, 60, '…') . '" tidak bisa diubah karena sudah dijawab siswa.';
                }
            }
            $baruU = array_column($isi['uraian'], null, 'id');
            foreach ($this->lama['uraian'] ?? [] as $u) {
                $b = $baruU[$u['id']] ?? null;
                if (!$b) {
                    $this->galat[] = 'Soal uraian "' . mb_strimwidth((string) $u['soal'], 0, 60, '…') . '" sudah dijawab siswa, jadi tidak bisa dihapus.';
                } elseif ((int) $b['maks'] !== (int) ($u['maks'] ?? 10)) {
                    $this->galat[] = 'Skor maksimal soal uraian "' . mb_strimwidth((string) $u['soal'], 0, 60, '…') . '" tidak bisa diubah karena sudah dijawab siswa.';
                }
            }
        }
        if ($isi['judul'] === '') {
            $isi['judul'] = ['lembar' => 'LKPD', 'remidi' => 'Soal remidi', 'pengayaan' => 'Soal pengayaan'][$jenis];
        }
        return $isi;
    }

    // ---------- refleksi ----------
    private function refleksi(array $d): ?array
    {
        $q = $this->daftarTeks($d['pertanyaan'] ?? [], 10, 300);
        return $q ? ['pertanyaan' => $q] : null;
    }
}
