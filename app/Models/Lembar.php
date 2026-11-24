<?php
// Model LKPD (lembar kerja): soal pilihan ganda (dinilai otomatis) + uraian (dinilai guru).
// Soal dibaca dari database/content/lembar-p{nomor}.json; jawaban siswa di tabel lembar_kerja.
final class Lembar
{
    public static function definisi(int $nomor): ?array
    {
        return self::definisiJenis($nomor, 'lembar');
    }

    /** Soal berformat LKPD (lembar, remidi, pengayaan) dari Konten. Null bila tidak ada soal sama sekali. */
    public static function definisiJenis(int $nomor, string $jenis): ?array
    {
        $d = Konten::ambil($nomor, $jenis);
        return is_array($d) && (!empty($d['pg']) || !empty($d['uraian'])) ? $d : null;
    }

    private static function buka(?array $r): ?array
    {
        if ($r) {
            $r['jawaban_arr'] = json_decode((string) $r['jawaban'], true) ?: [];
            $r['skor_uraian_arr'] = $r['skor_uraian'] ? (json_decode((string) $r['skor_uraian'], true) ?: []) : [];
        }
        return $r;
    }

    public static function ambil(int $modulId, int $siswaId): ?array
    {
        return self::buka(DB::satu('SELECT * FROM lembar_kerja WHERE modul_id = ? AND siswa_id = ?', [$modulId, $siswaId]));
    }

    public static function byId(int $id): ?array
    {
        return self::buka(DB::satu('SELECT * FROM lembar_kerja WHERE id = ?', [$id]));
    }

    public static function pastikan(int $modulId, int $siswaId): array
    {
        $r = self::ambil($modulId, $siswaId);
        if (!$r) {
            DB::jalankan(
                'INSERT INTO lembar_kerja (modul_id, siswa_id, jawaban, status, dibuat_pada, diperbarui_pada) VALUES (?, ?, ?, ?, ?, ?)',
                [$modulId, $siswaId, '{}', 'draf', sekarang(), sekarang()]
            );
            $r = self::ambil($modulId, $siswaId);
        }
        return $r;
    }

    public static function poinPg(array $def): int
    {
        return max(1, (int) ($def['poin_pg'] ?? 5));
    }

    public static function maksTotal(array $def): int
    {
        $t = count($def['pg'] ?? []) * self::poinPg($def);
        foreach ($def['uraian'] ?? [] as $u) {
            $t += (int) ($u['maks'] ?? 10);
        }
        return $t;
    }

    /** Simpan jawaban (daftar putih: hanya id soal yang ada; PG hanya indeks pilihan yang sah). */
    public static function simpanJawaban(int $id, array $def, array $baru): void
    {
        $lama = self::byId($id)['jawaban_arr'] ?? [];
        DB::jalankan('UPDATE lembar_kerja SET jawaban = ?, diperbarui_pada = ? WHERE id = ?', [json_encode(self::saring($def, $lama, $baru), JSON_UNESCAPED_UNICODE), sekarang(), $id]);
    }

    /** Gabungkan jawaban baru ke jawaban lama, hanya untuk id soal yang ada dan nilai yang sah. Dipakai juga oleh remidi/pengayaan. */
    public static function saring(array $def, array $lama, array $baru): array
    {
        $pg = [];
        foreach ($def['pg'] ?? [] as $s) {
            $pg[$s['id']] = count($s['opsi']);
        }
        $ur = array_column($def['uraian'] ?? [], 'id');
        foreach ($baru as $k => $v) {
            if (!is_string($v)) {
                continue;
            }
            if (isset($pg[$k])) {
                if ($v === '' || (ctype_digit($v) && (int) $v < $pg[$k])) {
                    $lama[$k] = $v;
                }
            } elseif (in_array($k, $ur, true)) {
                $lama[$k] = mb_substr($v, 0, 5000);
            }
        }
        return $lama;
    }

    /**
     * Skor maksimal untuk satu jawaban: hanya soal yang ikut dijawab siswa.
     * Soal yang ditambah guru setelah siswa mengumpulkan tidak mengurangi nilainya.
     */
    public static function maksUntuk(array $def, array $jaw): int
    {
        $t = 0;
        foreach ($def['pg'] ?? [] as $s) {
            if (array_key_exists($s['id'], $jaw)) {
                $t += self::poinPg($def);
            }
        }
        foreach ($def['uraian'] ?? [] as $u) {
            if (array_key_exists($u['id'], $jaw)) {
                $t += (int) ($u['maks'] ?? 10);
            }
        }
        return $t;
    }

    /** Skor uraian yang sah: soal yang tidak ikut dijawab (soal baru) selalu 0. Null bila ada isian tidak valid. */
    public static function skorUraian(array $def, array $jaw, array $masukan): ?array
    {
        $skor = [];
        foreach ($def['uraian'] ?? [] as $i => $u) {
            if (!array_key_exists($u['id'], $jaw)) {
                $skor[] = 0;
                continue;
            }
            $maks = (int) ($u['maks'] ?? 10);
            $mentah = $masukan[$i] ?? '';
            $v = is_string($mentah) && preg_match('/^\d{1,3}$/', $mentah) ? (int) $mentah : -1;
            if ($v < 0 || $v > $maks) {
                return null;
            }
            $skor[] = $v;
        }
        return $skor;
    }

    /** Daftar label soal yang belum dijawab. */
    public static function kurang(array $def, array $jaw): array
    {
        $kurang = [];
        foreach ($def['pg'] ?? [] as $i => $s) {
            if (($jaw[$s['id']] ?? '') === '') {
                $kurang[] = 'Pilihan ganda no. ' . ($i + 1);
            }
        }
        foreach ($def['uraian'] ?? [] as $i => $s) {
            if (mb_strlen(trim((string) ($jaw[$s['id']] ?? ''))) < 3) {
                $kurang[] = 'Uraian no. ' . ($i + 1);
            }
        }
        return $kurang;
    }

    public static function jumlahBenar(array $def, array $jaw): int
    {
        $n = 0;
        foreach ($def['pg'] ?? [] as $s) {
            if (($jaw[$s['id']] ?? '') !== '' && (int) $jaw[$s['id']] === (int) $s['kunci']) {
                $n++;
            }
        }
        return $n;
    }

    public static function kumpulkan(array $lb, array $def): void
    {
        $skor = self::jumlahBenar($def, $lb['jawaban_arr']) * self::poinPg($def);
        // Tanpa soal uraian, nilai langsung final dari pilihan ganda.
        $nilai = empty($def['uraian']) ? (int) round($skor / max(1, self::maksUntuk($def, $lb['jawaban_arr'])) * 100) : null;
        DB::jalankan(
            "UPDATE lembar_kerja SET status = 'dikumpulkan', skor_pg = ?, nilai = ?, dikumpulkan_pada = ?, diperbarui_pada = ? WHERE id = ?",
            [$skor, $nilai, sekarang(), sekarang(), $lb['id']]
        );
    }

    public static function nilaiUraian(array $lb, array $def, array $skor, string $catatan): int
    {
        $total = (int) $lb['skor_pg'] + array_sum($skor);
        $nilai = (int) round($total / max(1, self::maksUntuk($def, $lb['jawaban_arr'])) * 100);
        DB::jalankan(
            'UPDATE lembar_kerja SET skor_uraian = ?, nilai = ?, catatan = ?, dinilai_pada = ?, diperbarui_pada = ? WHERE id = ?',
            [json_encode(array_values($skor)), $nilai, $catatan, sekarang(), sekarang(), $lb['id']]
        );
        return $nilai;
    }

    /** Buka lagi agar siswa bisa memperbaiki (nilai dihapus). */
    public static function kembalikan(int $id): void
    {
        DB::jalankan("UPDATE lembar_kerja SET status = 'draf', skor_pg = NULL, skor_uraian = NULL, nilai = NULL, dikumpulkan_pada = NULL, dinilai_pada = NULL, diperbarui_pada = ? WHERE id = ?", [sekarang(), $id]);
    }

    // ---------- Buka/tutup LKPD oleh guru (per kelas per modul) ----------

    /** Apakah LKPD modul ini terbuka untuk kelas tersebut? Tanpa pengaturan = terbuka. Siswa tanpa kelas selalu terbuka. */
    public static function terbuka(int $modulId, ?int $kelasId): bool
    {
        if (!$kelasId) {
            return true;
        }
        $v = DB::nilai('SELECT buka FROM lembar_akses WHERE kelas_id = ? AND modul_id = ?', [$kelasId, $modulId]);
        return $v === null || $v === false || (int) $v === 1;
    }

    /** Terbuka untuk siswa ini (mengikuti kelasnya)? */
    public static function terbukaUntuk(int $modulId, int $siswaId): bool
    {
        $k = DB::nilai('SELECT kelas_id FROM kelas_anggota WHERE siswa_id = ?', [$siswaId]);
        return self::terbuka($modulId, $k ? (int) $k : null);
    }

    public static function aturAkses(int $kelasId, int $modulId, bool $buka, int $olehId): void
    {
        $ada = DB::nilai('SELECT id FROM lembar_akses WHERE kelas_id = ? AND modul_id = ?', [$kelasId, $modulId]);
        if ($ada) {
            DB::jalankan('UPDATE lembar_akses SET buka = ?, diubah_oleh = ?, diubah_pada = ? WHERE id = ?', [$buka ? 1 : 0, $olehId, sekarang(), $ada]);
        } else {
            DB::jalankan('INSERT INTO lembar_akses (kelas_id, modul_id, buka, diubah_oleh, diubah_pada) VALUES (?, ?, ?, ?, ?)', [$kelasId, $modulId, $buka ? 1 : 0, $olehId, sekarang()]);
        }
    }
}
