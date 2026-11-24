<?php
// Remidi dan pengayaan (siswa): terbuka setelah nilai modul lengkap. Remidi untuk yang belum mencapai KKM, pengayaan untuk yang tuntas.
final class TindakController extends Kontroler
{
    public function remidi(array $p): void { $this->halaman($p, 'remidi'); }
    public function pengayaan(array $p): void { $this->halaman($p, 'pengayaan'); }
    public function simpanRemidi(array $p): void { $this->simpan($p, 'remidi'); }
    public function simpanPengayaan(array $p): void { $this->simpan($p, 'pengayaan'); }
    public function kumpulRemidi(array $p): void { $this->kumpul($p, 'remidi'); }
    public function kumpulPengayaan(array $p): void { $this->kumpul($p, 'pengayaan'); }

    private function konteks(array $p, string $jenis, bool $json = false): array
    {
        $m = $this->modulTerbuka($p);
        $def = Tindak::definisi((int) $m['nomor'], $jenis);
        $n = Nilai::modul($m, Auth::id(), Nilai::aturanSiswa(Auth::id()));
        $tolak = !$def ? 'Soal ' . strtolower(Tindak::JENIS[$jenis]) . ' untuk modul ini belum disiapkan gurumu.'
            : (!Nilai::bolehTindak($n, $jenis) ? ($n['status'] === 'belum'
                ? 'Nilai modul ini belum lengkap. ' . Tindak::JENIS[$jenis] . ' terbuka setelah semua nilai masuk.'
                : ($jenis === 'remidi' ? 'Kamu sudah tuntas di modul ini, jadi tidak perlu remidi. Coba soal pengayaan.' : 'Pengayaan terbuka setelah kamu tuntas di modul ini.')) : null);
        if ($tolak) {
            if ($json) {
                Respon::json(['ok' => false, 'pesan' => $tolak], 403);
            }
            flash('info', $tolak);
            Respon::alihkan('/modul/' . $m['id']);
        }
        return [$m, $def, $n, Tindak::pastikan((int) $m['id'], Auth::id(), $jenis)];
    }

    private function halaman(array $p, string $jenis): void
    {
        [$m, $def, $n, $t] = $this->konteks($p, $jenis);
        $info = $jenis === 'remidi'
            ? 'Nilai modulmu ' . $n['awal'] . ', KKM ' . $n['kkm'] . '. Kerjakan soal perbaikan ini. Nilai remidi dihitung paling tinggi ' . $n['kkm'] . ' (sama dengan KKM), dan nilaimu tidak akan turun.'
            : 'Kamu sudah tuntas di modul ini. Soal pengayaan ini tantangan tambahan; nilainya dicatat terpisah dan tidak mengurangi nilai modulmu.';
        $this->tampil('siswa/lembar', [
            'm' => $m, 'def' => $def, 'lb' => $t, 'langkah' => [], 'mode' => $jenis, 'dasar' => '/modul/' . $m['id'] . '/' . $jenis,
            'terkunci' => $t['status'] === 'dikumpulkan', 'ditutup' => false, 'info' => $t['status'] === 'dikumpulkan' ? '' : $info,
        ]);
    }

    private function simpan(array $p, string $jenis): void
    {
        [$m, $def, $n, $t] = $this->konteks($p, $jenis, true);
        if ($t['status'] === 'dikumpulkan') {
            Respon::json(['ok' => false, 'pesan' => 'Sudah dikumpulkan dan dikunci.'], 409);
        }
        $j = input_json();
        Tindak::simpanJawaban((int) $t['id'], $def, is_array($j['jawaban'] ?? null) ? $j['jawaban'] : []);
        Respon::json(['ok' => true, 'waktu' => date('H:i')]);
    }

    private function kumpul(array $p, string $jenis): void
    {
        [$m, $def, $n, $t] = $this->konteks($p, $jenis);
        $ke = '/modul/' . $m['id'] . '/' . $jenis;
        if ($t['status'] === 'dikumpulkan') {
            Respon::alihkan($ke);
        }
        if (isset($_POST['j']) && is_array($_POST['j'])) {
            Tindak::simpanJawaban((int) $t['id'], $def, $_POST['j']);
        }
        $t = Tindak::byId((int) $t['id']);
        $kurang = Lembar::kurang($def, $t['jawaban_arr']);
        if ($kurang) {
            flash('galat', "Masih ada yang belum dijawab:\n• " . implode("\n• ", array_slice($kurang, 0, 6)) . (count($kurang) > 6 ? "\n• dan " . (count($kurang) - 6) . ' lainnya' : ''));
            Respon::alihkan($ke);
        }
        Tindak::kumpulkan($t, $def);
        $benar = Lembar::jumlahBenar($def, $t['jawaban_arr']);
        flash('sukses', Tindak::JENIS[$jenis] . ' terkumpul.' . (!empty($def['pg']) ? ' Pilihan ganda: ' . $benar . ' dari ' . count($def['pg']) . ' benar.' : '') . (!empty($def['uraian']) ? ' Uraian akan dinilai gurumu.' : ''));
        Respon::alihkan($ke);
    }
}
