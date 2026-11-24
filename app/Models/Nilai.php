<?php
// Nilai akhir modul, ketuntasan (KKM), remidi/pengayaan, dan analisis kelemahan siswa.
// Nilai modul = rata-rata berbobot dari praktik (pengumpulan), LKPD, dan simulasi. KKM dan bobot diatur per kelas.
final class Nilai
{
    public const BAWAAN = ['kkm' => 75, 'praktik' => 40, 'lkpd' => 40, 'simulasi' => 20];
    public const BATAS_LEMAH = 60; // topik/kriteria di bawah persen ini ditandai lemah

    public const STATUS = [
        'belum' => ['Belum lengkap', 'grey'],
        'tuntas' => ['Tuntas', 'ok'],
        'tuntas_remidi' => ['Tuntas setelah remidi', 'ok'],
        'remidi' => ['Belum tuntas', 'bahaya'],
        'menunggu' => ['Remidi menunggu dinilai', 'warn'],
        'belum_tuntas' => ['Belum tuntas setelah remidi', 'bahaya'],
    ];

    /** KKM dan bobot dari baris kelas (null = bawaan). */
    public static function aturan(?array $kelas): array
    {
        $a = self::BAWAAN;
        if ($kelas && isset($kelas['kkm'])) {
            $a = ['kkm' => (int) $kelas['kkm'], 'praktik' => (int) $kelas['bobot_praktik'], 'lkpd' => (int) $kelas['bobot_lkpd'], 'simulasi' => (int) $kelas['bobot_simulasi']];
        }
        return $a;
    }

    public static function aturanSiswa(int $siswaId): array
    {
        return self::aturan(Kelas::dariSiswa($siswaId));
    }

    public static function simpanAturan(int $kelasId, array $a): void
    {
        DB::jalankan('UPDATE kelas SET kkm = ?, bobot_praktik = ?, bobot_lkpd = ?, bobot_simulasi = ? WHERE id = ?', [$a['kkm'], $a['praktik'], $a['lkpd'], $a['simulasi'], $kelasId]);
    }

    public static function adaGame(array $m): bool
    {
        return (string) $m['game_folder'] !== '' && preg_match('/^[a-z0-9_-]+$/i', (string) $m['game_folder']) === 1
            && is_file(ROOT . '/public/games/' . $m['game_folder'] . '/index.html');
    }

    /** Nilai satu siswa di satu modul beserta status ketuntasan dan tindak lanjutnya. */
    public static function modul(array $m, int $siswaId, array $aturan): array
    {
        $id = (int) $m['id'];
        $nomor = (int) $m['nomor'];
        $k = [];
        if (Lkpd::definisi($nomor)) {
            $p = Lkpd::ambil($id, $siswaId);
            $k['praktik'] = ['label' => 'Praktik', 'bobot' => $aturan['praktik'], 'nilai' => $p && $p['nilai'] !== null ? (int) $p['nilai'] : null,
                'ket' => !$p || $p['status'] !== 'dikumpulkan' ? 'Belum dikumpulkan' : ($p['nilai'] === null ? 'Menunggu dinilai guru' : '')];
        }
        if (Lembar::definisi($nomor)) {
            $lb = Lembar::ambil($id, $siswaId);
            $k['lkpd'] = ['label' => 'LKPD', 'bobot' => $aturan['lkpd'], 'nilai' => $lb && $lb['nilai'] !== null ? (int) $lb['nilai'] : null,
                'ket' => !$lb || $lb['status'] !== 'dikumpulkan' ? 'Belum dikumpulkan' : ($lb['nilai'] === null ? 'Uraian menunggu dinilai guru' : '')];
        }
        if (self::adaGame($m)) {
            $pr = Modul::progres($id, $siswaId);
            $n = $pr['game_skor'] !== null ? (int) round((int) $pr['game_skor'] / max(1, (int) ($pr['game_skor_maks'] ?: 100)) * 100) : null;
            $k['simulasi'] = ['label' => 'Simulasi', 'bobot' => $aturan['simulasi'], 'nilai' => $n !== null ? min(100, $n) : null, 'ket' => $n === null ? 'Belum dimainkan' : ''];
        }
        $dihitung = array_filter($k, fn($x) => $x['bobot'] > 0);
        $lengkap = $dihitung && !array_filter($dihitung, fn($x) => $x['nilai'] === null);
        $awal = $lengkap ? (int) round(array_sum(array_map(fn($x) => $x['nilai'] * $x['bobot'], $dihitung)) / array_sum(array_column($dihitung, 'bobot'))) : null;

        $remidi = Tindak::ambil($id, $siswaId, 'remidi');
        $pengayaan = Tindak::ambil($id, $siswaId, 'pengayaan');
        $nRemidi = $remidi && $remidi['nilai'] !== null ? (int) $remidi['nilai'] : null;
        $kkm = $aturan['kkm'];
        // Nilai remidi paling tinggi sama dengan KKM; nilai akhir tidak pernah turun karena remidi.
        $akhir = $awal === null ? null : ($nRemidi !== null ? max($awal, min($nRemidi, $kkm)) : $awal);
        $status = match (true) {
            $awal === null => 'belum',
            $awal >= $kkm => 'tuntas',
            $akhir >= $kkm => 'tuntas_remidi',
            $remidi && $remidi['status'] === 'dikumpulkan' && $nRemidi === null => 'menunggu',
            $nRemidi !== null => 'belum_tuntas',
            default => 'remidi',
        };
        $tuntas = in_array($status, ['tuntas', 'tuntas_remidi'], true);
        return [
            'komponen' => $k, 'lengkap' => (bool) $lengkap, 'awal' => $awal, 'akhir' => $akhir, 'kkm' => $kkm, 'status' => $status, 'tuntas' => $tuntas,
            'kurang' => array_values(array_map(fn($x) => $x['label'] . ': ' . $x['ket'], array_filter($dihitung, fn($x) => $x['nilai'] === null))),
            'rekomendasi' => $status === 'belum' ? null : ($tuntas ? 'pengayaan' : 'remidi'),
            'remidi' => $remidi, 'pengayaan' => $pengayaan,
            'adaRemidi' => Tindak::definisi($nomor, 'remidi') !== null, 'adaPengayaan' => Tindak::definisi($nomor, 'pengayaan') !== null,
        ];
    }

    /** Boleh mengerjakan soal jenis ini? Remidi untuk yang belum tuntas, pengayaan untuk yang tuntas. Yang sudah mulai tetap boleh membuka. */
    public static function bolehTindak(array $n, string $jenis): bool
    {
        if ($n[$jenis] ?? null) {
            return true;
        }
        return $jenis === 'remidi' ? $n['status'] === 'remidi' : ($jenis === 'pengayaan' && $n['tuntas']);
    }

    /** Label topik soal; soal tanpa topik dikelompokkan sebagai "Soal pilihan ganda/uraian no. N". */
    private static function topik(array $s, string $awalan, int $i): string
    {
        $t = trim((string) ($s['topik'] ?? ''));
        return $t !== '' ? $t : $awalan . ' no. ' . ($i + 1);
    }

    /** Capaian per topik (LKPD) dan per kriteria rubrik praktik untuk satu siswa. */
    public static function kelemahan(array $m, int $siswaId): array
    {
        $topik = [];
        $def = Lembar::definisi((int) $m['nomor']);
        $lb = $def ? Lembar::ambil((int) $m['id'], $siswaId) : null;
        if ($def && $lb && $lb['status'] === 'dikumpulkan') {
            $jaw = $lb['jawaban_arr'];
            $poin = Lembar::poinPg($def);
            foreach ($def['pg'] ?? [] as $i => $s) {
                if (!array_key_exists($s['id'], $jaw)) {
                    continue;
                }
                $t = self::topik($s, 'Pilihan ganda', $i);
                $topik[$t] ??= ['dapat' => 0, 'maks' => 0];
                $topik[$t]['maks'] += $poin;
                $topik[$t]['dapat'] += (int) $jaw[$s['id']] === (int) $s['kunci'] && $jaw[$s['id']] !== '' ? $poin : 0;
            }
            if ($lb['nilai'] !== null) {
                foreach ($def['uraian'] ?? [] as $i => $u) {
                    if (!array_key_exists($u['id'], $jaw)) {
                        continue;
                    }
                    $t = self::topik($u, 'Uraian', $i);
                    $topik[$t] ??= ['dapat' => 0, 'maks' => 0];
                    $topik[$t]['maks'] += (int) ($u['maks'] ?? 10);
                    $topik[$t]['dapat'] += (int) ($lb['skor_uraian_arr'][$i] ?? 0);
                }
            }
        }
        $rubrik = [];
        $dl = Lkpd::definisi((int) $m['nomor']);
        $p = $dl ? Lkpd::ambil((int) $m['id'], $siswaId) : null;
        if ($p && $p['rubrik_skor']) {
            $skor = json_decode((string) $p['rubrik_skor'], true) ?: [];
            foreach ($dl['rubrik'] ?? [] as $i => $r) {
                if (isset($skor[$i])) {
                    $rubrik[(string) ($r['kriteria'] ?? 'Kriteria ' . ($i + 1))] = ['dapat' => (int) $skor[$i], 'maks' => isset($r['maks']) ? max(1, (int) $r['maks']) : 4];
                }
            }
        }
        $persen = function (array $x): array {
            foreach ($x as $k => $v) {
                $x[$k]['persen'] = (int) round($v['dapat'] / max(1, $v['maks']) * 100);
            }
            return $x;
        };
        $topik = $persen($topik);
        $rubrik = $persen($rubrik);
        $lemah = [];
        foreach ($topik as $k => $v) {
            if ($v['persen'] < self::BATAS_LEMAH) {
                $lemah[] = ['label' => $k, 'persen' => $v['persen'], 'asal' => 'LKPD'];
            }
        }
        foreach ($rubrik as $k => $v) {
            if ($v['persen'] < self::BATAS_LEMAH) {
                $lemah[] = ['label' => $k, 'persen' => $v['persen'], 'asal' => 'Praktik'];
            }
        }
        usort($lemah, fn($a, $b) => $a['persen'] <=> $b['persen']);
        return ['topik' => $topik, 'rubrik' => $rubrik, 'lemah' => $lemah];
    }

    /** Analisis satu kelas untuk satu modul: baris per siswa + ringkasan kelas. */
    public static function analisisKelas(array $kelas, array $m): array
    {
        $aturan = self::aturan($kelas);
        $baris = [];
        foreach (Kelas::anggota((int) $kelas['id']) as $s) {
            $baris[] = ['siswa' => $s, 'nilai' => self::modul($m, (int) $s['id'], $aturan), 'kelemahan' => self::kelemahan($m, (int) $s['id'])];
        }
        // Statistik per soal pilihan ganda LKPD dan per topik/kriteria (rata-rata kelas).
        $soal = [];
        $def = Lembar::definisi((int) $m['nomor']);
        if ($def) {
            $jawab = DB::semua("SELECT l.jawaban FROM lembar_kerja l JOIN kelas_anggota a ON a.siswa_id = l.siswa_id WHERE a.kelas_id = ? AND l.modul_id = ? AND l.status = 'dikumpulkan'", [$kelas['id'], $m['id']]);
            foreach ($def['pg'] ?? [] as $i => $s) {
                $n = 0;
                $benar = 0;
                $pilih = array_fill(0, count($s['opsi']), 0);
                foreach ($jawab as $j) {
                    $a = json_decode((string) $j['jawaban'], true) ?: [];
                    if (!isset($a[$s['id']]) || $a[$s['id']] === '') {
                        continue;
                    }
                    $n++;
                    $pilih[(int) $a[$s['id']]] = ($pilih[(int) $a[$s['id']]] ?? 0) + 1;
                    $benar += (int) $a[$s['id']] === (int) $s['kunci'] ? 1 : 0;
                }
                if ($n) {
                    arsort($pilih);
                    $salahTerbanyak = null;
                    foreach ($pilih as $o => $c) {
                        if ($o !== (int) $s['kunci'] && $c > 0) {
                            $salahTerbanyak = ['opsi' => $o, 'jumlah' => $c];
                            break;
                        }
                    }
                    $soal[] = ['no' => $i + 1, 'soal' => $s['soal'], 'topik' => self::topik($s, 'Pilihan ganda', $i), 'benar' => $benar, 'n' => $n,
                        'persen' => (int) round($benar / $n * 100), 'kunci' => (int) $s['kunci'], 'opsi' => $s['opsi'], 'salah' => $salahTerbanyak];
                }
            }
            usort($soal, fn($a, $b) => $a['persen'] <=> $b['persen'] ?: $a['no'] <=> $b['no']);
        }
        $gabung = function (string $kunci) use ($baris): array {
            $t = [];
            foreach ($baris as $b) {
                foreach ($b['kelemahan'][$kunci] as $k => $v) {
                    $t[$k] ??= ['dapat' => 0, 'maks' => 0, 'siswa' => 0, 'lemah' => 0];
                    $t[$k]['dapat'] += $v['dapat'];
                    $t[$k]['maks'] += $v['maks'];
                    $t[$k]['siswa']++;
                    $t[$k]['lemah'] += $v['persen'] < self::BATAS_LEMAH ? 1 : 0;
                }
            }
            foreach ($t as $k => $v) {
                $t[$k]['persen'] = (int) round($v['dapat'] / max(1, $v['maks']) * 100);
            }
            uasort($t, fn($a, $b) => $a['persen'] <=> $b['persen']);
            return $t;
        };
        $hitung = array_count_values(array_map(fn($b) => $b['nilai']['status'], $baris));
        $nilaiAkhir = array_values(array_filter(array_map(fn($b) => $b['nilai']['akhir'], $baris), fn($x) => $x !== null));
        return [
            'aturan' => $aturan, 'baris' => $baris, 'soal' => $soal, 'topik' => $gabung('topik'), 'rubrik' => $gabung('rubrik'),
            'ringkas' => [
                'siswa' => count($baris),
                'tuntas' => ($hitung['tuntas'] ?? 0) + ($hitung['tuntas_remidi'] ?? 0),
                'remidi' => ($hitung['remidi'] ?? 0) + ($hitung['menunggu'] ?? 0) + ($hitung['belum_tuntas'] ?? 0),
                'belum' => $hitung['belum'] ?? 0,
                'rata' => $nilaiAkhir ? (int) round(array_sum($nilaiAkhir) / count($nilaiAkhir)) : null,
                'tertinggi' => $nilaiAkhir ? max($nilaiAkhir) : null, 'terendah' => $nilaiAkhir ? min($nilaiAkhir) : null,
            ],
        ];
    }
}
