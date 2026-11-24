<?php
// Ruang diskusi: siswa bertanya, siswa lain ikut bertanya atau menjawab, guru menjawab dan memoderasi.
final class DiskusiController extends Kontroler
{
    /** Pilihan ruang untuk pengguna: 'umum' (semua kelas) + kelas yang boleh dibuka. */
    private function ruangPilihan(array $u): array
    {
        $r = ['umum' => 'Semua kelas'];
        $kelas = $u['peran'] === 'siswa' ? array_filter([Kelas::dariSiswa((int) $u['id'])]) : $this->kelasSaya();
        foreach ($kelas as $k) {
            $r[(string) $k['id']] = $k['nama'] . ($u['peran'] === 'siswa' ? ' (kelasku saja)' : '');
        }
        return $r;
    }

    private function saringan(): array
    {
        $f = [];
        foreach (['ruang' => 12, 'modul' => 12, 'status' => 10, 'q' => 60] as $k => $maks) {
            $v = trim((string) ($_GET[$k] ?? ''));
            if ($v !== '') {
                $f[$k] = mb_substr($v, 0, $maks);
            }
        }
        if (isset($f['status']) && !in_array($f['status'], ['belum', 'selesai', 'saya'], true)) {
            unset($f['status']);
        }
        return $f;
    }

    public function daftar(array $p): void
    {
        $u = Auth::pengguna();
        $f = $this->saringan();
        $hal = max(1, (int) ($_GET['hal'] ?? 1));
        $hasil = Diskusi::daftar($u, $f, $hal);
        $this->tampil('diskusi/daftar', [
            'u' => $u, 'f' => $f, 'hal' => $hal, 'hasil' => $hasil, 'ruang' => $this->ruangPilihan($u), 'modul' => Modul::terbit(),
            'lama' => $_SESSION['diskusi_lama'] ?? [], 'tulis' => isset($_GET['tulis']),
        ]);
        unset($_SESSION['diskusi_lama']);
    }

    public function buat(array $p): void
    {
        $u = Auth::pengguna();
        $judul = $this->teks('judul', 160);
        $isi = $this->teks('isi', 3000);
        $ruang = (string) ($_POST['ruang'] ?? 'umum');
        $modulId = (string) ($_POST['modul'] ?? '');
        $kembali = '/diskusi?tulis=1' . ($modulId !== '' ? '&modul=' . (int) $modulId : '');
        $_SESSION['diskusi_lama'] = ['judul' => $judul, 'isi' => $isi, 'ruang' => $ruang, 'modul' => $modulId];
        if (!array_key_exists($ruang, $this->ruangPilihan($u))) {
            flash('galat', 'Pilih ruang diskusi yang tersedia.');
            Respon::alihkan($kembali);
        }
        $m = null;
        if ($modulId !== '') {
            $m = Modul::byId((int) $modulId);
            if (!$m || $m['status'] !== 'terbit') {
                flash('galat', 'Modul itu tidak tersedia.');
                Respon::alihkan($kembali);
            }
        }
        if (mb_strlen($judul) < 5 || mb_strlen($isi) < 5) {
            flash('galat', 'Tulis judul pertanyaan (minimal 5 huruf) dan penjelasannya.');
            Respon::alihkan($kembali);
        }
        if (Diskusi::terlaluCepat((int) $u['id'])) {
            flash('galat', 'Kamu mengirim terlalu banyak pesan. Tunggu beberapa menit lalu coba lagi.');
            Respon::alihkan($kembali);
        }
        $id = Diskusi::buat($ruang === 'umum' ? null : (int) $ruang, $m ? (int) $m['id'] : null, (int) $u['id'], $judul, $isi);
        unset($_SESSION['diskusi_lama']);
        flash('sukses', 'Pertanyaan terkirim. Teman dan gurumu bisa membalas di sini.');
        Respon::alihkan('/diskusi/' . $id);
    }

    private function topikBoleh(int $id): array
    {
        $u = Auth::pengguna();
        $t = Diskusi::topik($id, (int) $u['id']);
        if (!$t || !Diskusi::bolehLihat($t, $u)) {
            Respon::galat(404, 'Diskusi tidak ditemukan.');
        }
        return [$t, $u];
    }

    public function lihat(array $p): void
    {
        [$t, $u] = $this->topikBoleh($this->id($p));
        $this->tampil('diskusi/topik', [
            't' => $t, 'u' => $u, 'balasan' => Diskusi::balasan((int) $t['id']), 'moderator' => Diskusi::bolehModerasi($t, $u),
            'pemilik' => (int) $t['penulis_id'] === (int) $u['id'], 'draf' => $_SESSION['diskusi_balas'] ?? '',
        ]);
        unset($_SESSION['diskusi_balas']);
    }

    public function balas(array $p): void
    {
        [$t, $u] = $this->topikBoleh($this->id($p));
        $isi = $this->teks('isi', 3000);
        $ke = '/diskusi/' . $t['id'];
        if (mb_strlen($isi) < 2) {
            flash('galat', 'Tulis balasanmu dulu.');
            Respon::alihkan($ke . '#balas');
        }
        if (Diskusi::terlaluCepat((int) $u['id'])) {
            $_SESSION['diskusi_balas'] = $isi;
            flash('galat', 'Kamu mengirim terlalu banyak pesan. Tunggu beberapa menit lalu coba lagi.');
            Respon::alihkan($ke . '#balas');
        }
        $bid = Diskusi::balas((int) $t['id'], (int) $u['id'], $isi);
        Respon::alihkan($ke . '#b' . $bid);
    }

    public function ikut(array $p): void
    {
        [$t, $u] = $this->topikBoleh($this->id($p));
        if ((int) $t['penulis_id'] === (int) $u['id']) {
            Respon::alihkan('/diskusi/' . $t['id']);
        }
        $ikut = Diskusi::ikut((int) $t['id'], (int) $u['id']);
        flash('sukses', $ikut ? 'Kamu ikut menanyakan ini. Topik ini muncul di "Pertanyaanku".' : 'Kamu tidak lagi mengikuti pertanyaan ini.');
        Respon::alihkan($this->kembali('/diskusi/' . $t['id']));
    }

    public function selesai(array $p): void
    {
        [$t, $u] = $this->topikBoleh($this->id($p));
        if ((int) $t['penulis_id'] !== (int) $u['id'] && !Diskusi::bolehModerasi($t, $u)) {
            Respon::galat(403, 'Hanya penanya atau guru yang bisa menandai selesai.');
        }
        Diskusi::atur((int) $t['id'], 'selesai', !(int) $t['selesai']);
        flash('sukses', (int) $t['selesai'] ? 'Pertanyaan dibuka lagi.' : 'Pertanyaan ditandai selesai.');
        Respon::alihkan('/diskusi/' . $t['id']);
    }

    public function sematkan(array $p): void
    {
        [$t, $u] = $this->topikBoleh($this->id($p));
        if (!Diskusi::bolehModerasi($t, $u)) {
            Respon::galat(403, 'Hanya guru yang bisa menyematkan.');
        }
        Diskusi::atur((int) $t['id'], 'disematkan', !(int) $t['disematkan']);
        flash('sukses', (int) $t['disematkan'] ? 'Sematan dilepas.' : 'Disematkan di atas daftar diskusi.');
        Respon::alihkan('/diskusi/' . $t['id']);
    }

    public function hapus(array $p): void
    {
        [$t, $u] = $this->topikBoleh($this->id($p));
        if ((int) $t['penulis_id'] !== (int) $u['id'] && !Diskusi::bolehModerasi($t, $u)) {
            Respon::galat(403, 'Kamu tidak bisa menghapus pertanyaan ini.');
        }
        Diskusi::hapusTopik((int) $t['id']);
        flash('sukses', 'Pertanyaan dan semua balasannya dihapus.');
        Respon::alihkan('/diskusi');
    }

    private function balasanBoleh(array $p): array
    {
        $b = Diskusi::balasanById($this->id($p));
        if (!$b) {
            Respon::galat(404, 'Balasan tidak ditemukan.');
        }
        [$t, $u] = $this->topikBoleh((int) $b['topik_id']);
        return [$b, $t, $u];
    }

    public function hapusBalasan(array $p): void
    {
        [$b, $t, $u] = $this->balasanBoleh($p);
        if ((int) $b['penulis_id'] !== (int) $u['id'] && !Diskusi::bolehModerasi($t, $u)) {
            Respon::galat(403, 'Kamu tidak bisa menghapus balasan ini.');
        }
        Diskusi::hapusBalasan((int) $b['id']);
        flash('sukses', 'Balasan dihapus.');
        Respon::alihkan('/diskusi/' . $t['id']);
    }

    public function pilihJawaban(array $p): void
    {
        [$b, $t, $u] = $this->balasanBoleh($p);
        if ((int) $t['penulis_id'] !== (int) $u['id'] && !Diskusi::bolehModerasi($t, $u)) {
            Respon::galat(403, 'Hanya penanya atau guru yang bisa memilih jawaban.');
        }
        $pilih = !(int) $b['terpilih'];
        Diskusi::pilihJawaban($b, $pilih);
        flash('sukses', $pilih ? 'Balasan ditandai sebagai jawaban dan pertanyaan ditandai selesai.' : 'Tanda jawaban dilepas.');
        Respon::alihkan('/diskusi/' . $t['id'] . '#b' . $b['id']);
    }

    /** Alamat kembali dari formulir, hanya ke halaman diskusi sendiri. */
    private function kembali(string $bawaan): string
    {
        $k = (string) ($_POST['kembali'] ?? '');
        return preg_match('#^/diskusi(/\d+)?(\?[a-z0-9=&%+._-]*)?$#i', $k) ? $k : $bawaan;
    }
}
