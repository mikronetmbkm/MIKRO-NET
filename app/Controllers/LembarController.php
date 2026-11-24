<?php
// LKPD siswa: pilihan ganda + uraian. Simpan otomatis, kumpulkan, lalu lihat pembahasan pilihan ganda.
final class LembarController extends Kontroler
{
    private function konteks(array $p, bool $json = false): array
    {
        $m = $this->modulTerbuka($p);
        $this->langkahBuka($m, 'lembar', $json);
        $def = Lembar::definisi((int) $m['nomor']);
        if (!$def) {
            flash('info', 'LKPD untuk modul ini belum tersedia.');
            Respon::alihkan('/modul/' . $m['id']);
        }
        return [$m, $def, Lembar::pastikan((int) $m['id'], Auth::id())];
    }

    public function halaman(array $p): void
    {
        [$m, $def, $lb] = $this->konteks($p);
        $this->tampil('siswa/lembar', [
            'm' => $m, 'def' => $def, 'lb' => $lb, 'langkah' => Modul::langkah($m, Auth::id()),
            'terkunci' => $lb['status'] === 'dikumpulkan',
            'ditutup' => $lb['status'] !== 'dikumpulkan' && !Lembar::terbukaUntuk((int) $m['id'], Auth::id()),
        ]);
    }

    public function simpan(array $p): void
    {
        [$m, $def, $lb] = $this->konteks($p, true);
        if ($lb['status'] === 'dikumpulkan') {
            Respon::json(['ok' => false, 'pesan' => 'LKPD sudah dikumpulkan dan dikunci.'], 409);
        }
        if (!Lembar::terbukaUntuk((int) $m['id'], Auth::id())) {
            Respon::json(['ok' => false, 'pesan' => 'LKPD sedang ditutup gurumu. Jawaban belum bisa disimpan.'], 423);
        }
        $j = input_json();
        Lembar::simpanJawaban((int) $lb['id'], $def, is_array($j['jawaban'] ?? null) ? $j['jawaban'] : []);
        Respon::json(['ok' => true, 'waktu' => date('H:i')]);
    }

    public function kumpul(array $p): void
    {
        [$m, $def, $lb] = $this->konteks($p);
        if ($lb['status'] === 'dikumpulkan') {
            Respon::alihkan('/modul/' . $m['id'] . '/lembar');
        }
        if (!Lembar::terbukaUntuk((int) $m['id'], Auth::id())) {
            flash('galat', 'LKPD sedang ditutup gurumu, jadi belum bisa dikumpulkan.');
            Respon::alihkan('/modul/' . $m['id'] . '/lembar');
        }
        if (isset($_POST['j']) && is_array($_POST['j'])) {
            Lembar::simpanJawaban((int) $lb['id'], $def, $_POST['j']);
        }
        $lb = Lembar::byId((int) $lb['id']);
        $kurang = Lembar::kurang($def, $lb['jawaban_arr']);
        if ($kurang) {
            flash('galat', "Masih ada yang belum dijawab:\n• " . implode("\n• ", array_slice($kurang, 0, 6)) . (count($kurang) > 6 ? "\n• dan " . (count($kurang) - 6) . ' lainnya' : ''));
            Respon::alihkan('/modul/' . $m['id'] . '/lembar');
        }
        Lembar::kumpulkan($lb, $def);
        $benar = Lembar::jumlahBenar($def, $lb['jawaban_arr']);
        flash('sukses', 'LKPD terkumpul. Pilihan ganda: ' . $benar . ' dari ' . count($def['pg'] ?? []) . ' benar.' . (!empty($def['uraian']) ? ' Uraian akan dinilai gurumu.' : ''));
        Respon::alihkan('/modul/' . $m['id'] . '/lembar');
    }
}
