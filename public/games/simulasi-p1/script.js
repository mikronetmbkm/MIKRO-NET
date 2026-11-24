/* Mikronet — Simulasi Pertemuan 1 (multi-halaman).
   Misi 1: IP address · Misi 2: subnet mask & subnetting · Misi 3: topologi standar + uji ping.
   Skor akhir dilaporkan ke MIKRO-NET: parent.postMessage({type:'mikronet:hasil', skor, skorMaks:100}, '*'). */
document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var $ = function (id) { return document.getElementById(id); };
  var halaman = document.body.getAttribute('data-halaman') || '';

  /* ---------- Penyimpanan ----------
     Di dalam iframe bersandbox, localStorage diblokir. Progres disimpan di window.name (bertahan antarhalaman
     dalam satu iframe), dengan localStorage sebagai cadangan bila game dibuka langsung di browser. */
  var KUNCI = 'mikronet_p1_v2';
  // Di dalam MIKRO-NET (iframe) localStorage tidak dipakai, supaya progres siswa lain di PC lab yang sama tidak terbawa.
  var DI_IFRAME = (function () { try { return window.self !== window.top; } catch (e) { return true; } })();
  function muat() {
    var d = null;
    try { var o = JSON.parse(window.name); if (o && o[KUNCI]) d = o[KUNCI]; } catch (e) {}
    if (!d && !DI_IFRAME) { try { d = JSON.parse(localStorage.getItem(KUNCI)); } catch (e) {} }
    return d && typeof d === 'object' ? d : {};
  }
  function simpan(d) {
    try { var o = {}; o[KUNCI] = d; window.name = JSON.stringify(o); } catch (e) {}
    if (!DI_IFRAME) { try { localStorage.setItem(KUNCI, JSON.stringify(d)); } catch (e) {} }
    // Di MIKRO-NET: kirim progres ke halaman induk supaya tersimpan di server (tidak mengulang dari awal saat dibuka lagi).
    else { try { window.parent.postMessage({ type: 'mikronet:simpan', data: window.name }, '*'); } catch (e) {} }
  }
  function prog() { var d = muat(); return d.prog || {}; }
  function setProg(patch) {
    var d = muat(); d.prog = d.prog || {};
    for (var k in patch) if (Object.prototype.hasOwnProperty.call(patch, k)) d.prog[k] = patch[k];
    simpan(d); segarkanHeader();
  }
  function reset() { simpan({}); }

  function skor(p) {
    var r = {
      m1: (p.m1 ? p.m1.benar : 0) * 4,       // 5 soal x 4 = 20
      m2: (p.m2 ? p.m2.benar : 0) * 6,       // 5 soal x 6 = 30
      t1: p.t1 ? 20 : 0,
      ping: p.ping ? 15 : 0,
      t2: p.t2 ? 15 : 0
    };
    r.total = Math.min(100, r.m1 + r.m2 + r.t1 + r.ping + r.t2);
    return r;
  }
  function segarkanHeader() {
    var p = prog();
    var langkah = [p.m1 && p.m1.selesai, p.m2 && p.m2.selesai, p.t1, p.ping, p.m3];
    var pct = Math.round(langkah.filter(Boolean).length / langkah.length * 100);
    if ($('header-progress-bar')) $('header-progress-bar').style.width = pct + '%';
    if ($('header-progress-text')) $('header-progress-text').textContent = pct + '%';
  }
  segarkanHeader();

  /* Konfirmasi dua klik. window.confirm() diblokir di dalam iframe bersandbox MIKRO-NET (tanpa allow-modals),
     jadi tombol berbahaya minta diklik dua kali: klik pertama mengubah teksnya, klik kedua menjalankan aksi. */
  function duaKlik(tombol, teksYakin, aksi) {
    var asli = tombol.textContent, siap = false, t = null;
    tombol.addEventListener('click', function () {
      if (!siap) {
        siap = true; tombol.textContent = teksYakin; tombol.classList.add('yakin');
        t = setTimeout(function () { siap = false; tombol.textContent = asli; tombol.classList.remove('yakin'); }, 4000);
        return;
      }
      clearTimeout(t); siap = false; tombol.textContent = asli; tombol.classList.remove('yakin');
      aksi();
    });
  }

  document.querySelectorAll('#btn-reset-progress').forEach(function (b) {
    duaKlik(b, 'Yakin? Klik lagi untuk mengulang', function () { reset(); window.location.href = 'index.html'; });
  });

  /* ---------- Utilitas IP ---------- */
  function parseIP(str) {
    if (typeof str !== 'string') return null;
    var bagian = str.trim().split('.');
    if (bagian.length !== 4) return null;
    var o = [];
    for (var i = 0; i < 4; i++) {
      var s = bagian[i].trim();
      if (!/^\d{1,3}$/.test(s)) return null;
      var n = parseInt(s, 10);
      if (n > 255) return null;
      o.push(n);
    }
    return o;
  }
  function alasanTidakValid(str) {
    var s = String(str || '').trim();
    if (s === '') return 'IP masih kosong.';
    var bagian = s.split('.');
    if (bagian.length !== 4) return 'IP harus terdiri dari 4 angka yang dipisah titik. Yang kamu tulis punya ' + bagian.length + ' bagian.';
    for (var i = 0; i < 4; i++) {
      if (!/^\d{1,3}$/.test(bagian[i].trim())) return 'Bagian ke-' + (i + 1) + ' ("' + bagian[i] + '") bukan angka 0–255.';
      if (parseInt(bagian[i], 10) > 255) return 'Bagian ke-' + (i + 1) + ' bernilai ' + parseInt(bagian[i], 10) + ', padahal maksimal 255 (8 bit = 11111111 = 255).';
    }
    return '';
  }
  function biner(n) { var b = n.toString(2); while (b.length < 8) b = '0' + b; return b; }
  function kelasIP(o1) {
    if (o1 >= 1 && o1 <= 126) return 'A';
    if (o1 === 127) return 'loopback';
    if (o1 >= 128 && o1 <= 191) return 'B';
    if (o1 >= 192 && o1 <= 223) return 'C';
    if (o1 >= 224 && o1 <= 239) return 'D';
    if (o1 >= 240) return 'E';
    return '?';
  }
  function privat(o) { return o[0] === 10 || (o[0] === 172 && o[1] >= 16 && o[1] <= 31) || (o[0] === 192 && o[1] === 168); }
  function jaringan24(o) { return o[0] + '.' + o[1] + '.' + o[2]; }
  function esc(s) { return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function acak(arr) { var a = arr.slice(); for (var i = a.length - 1; i > 0; i--) { var j = Math.floor(Math.random() * (i + 1)); var t = a[i]; a[i] = a[j]; a[j] = t; } return a; }

  /* ---------- Komponen kuis pilihan ganda ----------
     Klik pilihan = memilih (belum dinilai). Tombol "Kunci jawaban" muncul untuk konfirmasi; setelah dikunci baru tampil benar/salah.
     Jawaban yang sudah dikunci disimpan di progres (kunciSimpan), jadi tidak hilang saat halaman dimuat ulang. */
  function kuisData(kunciSimpan) { var d = muat(); return (d.kuis && d.kuis[kunciSimpan]) || null; }
  function kuisTulis(kunciSimpan, isi) {
    var d = muat(); d.kuis = d.kuis || {};
    if (isi === null) delete d.kuis[kunciSimpan]; else d.kuis[kunciSimpan] = isi;
    simpan(d);
  }
  function kuis(wadah, soal, skorEl, selesai, kunciSimpan, urut) {
    var benar = 0, dijawab = 0;
    var data = (kunciSimpan && kuisData(kunciSimpan)) || { urut: urut || null, jawab: {} };
    data.jawab = data.jawab || {};
    wadah.innerHTML = '';
    function nilai(blok, opsi, fb, s, i, j, diam) {
      blok.dataset.selesai = '1'; dijawab++;
      var ok = j === s.benar; if (ok) benar++;
      opsi.querySelectorAll('.opsi-btn').forEach(function (x, k) {
        x.disabled = true; x.classList.remove('dipilih'); x.setAttribute('aria-pressed', 'false');
        if (k === s.benar) x.classList.add('benar');
        if (k === j && !ok) x.classList.add('salah');
      });
      fb.className = 'feedback show ' + (ok ? 'feedback-correct' : 'feedback-wrong');
      fb.innerHTML = '<span aria-hidden="true">' + (ok ? '✅' : '💡') + '</span><div><strong>' + (ok ? 'Benar!' : 'Belum tepat. Jawabannya ' + 'ABCD'[s.benar] + '.') + '</strong>' + s.jelas + '</div>';
      if (skorEl) skorEl.textContent = benar + '/' + soal.length;
      if (!diam && kunciSimpan) { data.jawab[i] = j; kuisTulis(kunciSimpan, data); }
      if (dijawab === soal.length) selesai(benar);
    }
    soal.forEach(function (s, i) {
      var blok = document.createElement('div'); blok.className = 'soal';
      blok.innerHTML = '<p class="soal-teks"><span class="soal-no">' + (i + 1) + '</span><span>' + s.q + '</span></p>';
      var opsi = document.createElement('div'); opsi.className = 'opsi';
      var fb = document.createElement('div'); fb.className = 'feedback'; fb.setAttribute('role', 'status');
      var konfirmasi = document.createElement('div'); konfirmasi.className = 'konfirmasi'; konfirmasi.hidden = true;
      konfirmasi.innerHTML = '<span class="konfirmasi-teks"></span><button type="button" class="btn btn-primary btn-kecil">Kunci jawaban</button>';
      var pilihan = -1;
      s.opsi.forEach(function (teks, j) {
        var b = document.createElement('button'); b.type = 'button'; b.className = 'opsi-btn'; b.setAttribute('aria-pressed', 'false');
        b.innerHTML = '<b>' + 'ABCD'[j] + '</b> ' + teks;
        b.addEventListener('click', function () {
          if (blok.dataset.selesai) return;
          pilihan = j;
          opsi.querySelectorAll('.opsi-btn').forEach(function (x, k) { x.classList.toggle('dipilih', k === j); x.setAttribute('aria-pressed', k === j ? 'true' : 'false'); });
          konfirmasi.hidden = false;
          konfirmasi.querySelector('.konfirmasi-teks').innerHTML = 'Yakin dengan jawaban <b>' + 'ABCD'[j] + '</b>? Masih bisa diganti sebelum dikunci.';
        });
        opsi.appendChild(b);
      });
      konfirmasi.querySelector('button').addEventListener('click', function () {
        if (pilihan < 0 || blok.dataset.selesai) return;
        konfirmasi.hidden = true;
        nilai(blok, opsi, fb, s, i, pilihan, false);
      });
      blok.appendChild(opsi); blok.appendChild(konfirmasi); blok.appendChild(fb); wadah.appendChild(blok);
      if (Object.prototype.hasOwnProperty.call(data.jawab, i)) nilai(blok, opsi, fb, s, i, data.jawab[i], true);
    });
    if (skorEl) skorEl.textContent = benar + '/' + soal.length;
    if (kunciSimpan && !kuisData(kunciSimpan)) kuisTulis(kunciSimpan, data);
  }
  function kuisSudah(wadah, info, ulang) {
    wadah.innerHTML = '<div class="feedback show feedback-correct"><span aria-hidden="true">✅</span><div><strong>Misi ini sudah selesai: ' + info.benar + ' dari 5 benar.</strong>Kamu boleh mengulang kuisnya untuk memperbaiki skor.</div></div>';
    var b = document.createElement('button'); b.type = 'button'; b.className = 'btn btn-ghost'; b.style.marginTop = '12px'; b.textContent = 'Ulangi kuis';
    b.addEventListener('click', ulang); wadah.appendChild(b);
  }

  /* ============================================================ BERANDA */
  if (halaman === 'beranda') {
    var p = prog();
    var kartu = [
      { id: 'm1', buka: true, selesai: p.m1 && p.m1.selesai, info: p.m1 ? p.m1.benar + '/5 benar' : '' },
      { id: 'm2', buka: p.m1 && p.m1.selesai, selesai: p.m2 && p.m2.selesai, info: p.m2 ? p.m2.benar + '/5 benar' : '' },
      { id: 'm3', buka: p.m2 && p.m2.selesai, selesai: p.m3, info: [p.t1 ? 'Tantangan 1 ✓' : '', p.ping ? 'Ping ✓' : '', p.t2 ? 'Bonus router ✓' : ''].filter(Boolean).join(' · ') }
    ];
    kartu.forEach(function (k) {
      var btn = $('btn-' + k.id), st = $('status-' + k.id), card = $('card-' + k.id);
      if (k.buka) {
        card.classList.remove('locked'); btn.classList.remove('btn-lock'); btn.classList.add('btn-primary'); btn.removeAttribute('aria-disabled');
        btn.textContent = k.selesai ? 'Buka lagi' : 'Mulai';
        st.innerHTML = k.selesai ? '<span class="done-badge">Selesai ✓</span> ' + esc(k.info) : (k.info ? esc(k.info) : 'Siap dikerjakan');
      } else {
        btn.addEventListener('click', function (e) { e.preventDefault(); });
      }
    });
    if (p.m3) $('btn-hasil').hidden = false;
  }

  /* ============================================================ MISI 1: IP */
  if (halaman === 'ip') {
    var inp = $('input-ip'), err = $('ip-error'), kartuOktet = $('octet-cards'), jelas = $('ip-penjelasan');
    function tampilIP() {
      var o = parseIP(inp.value);
      if (!o) {
        kartuOktet.innerHTML = ''; jelas.hidden = inp.value.trim() === '';
        if (inp.value.trim() !== '') {
          inp.classList.add('invalid'); err.textContent = '';
          jelas.className = 'penjelasan salah';
          jelas.innerHTML = '<b>IP tidak valid.</b> ' + esc(alasanTidakValid(inp.value)) + '<br>Ingat: IPv4 = 4 angka, masing-masing 0–255, dipisah titik.';
        } else { inp.classList.remove('invalid'); }
        return;
      }
      inp.classList.remove('invalid'); err.textContent = '';
      var k = kelasIP(o[0]);
      // Jumlah oktet network: mask bawaan kelas (A = /8, B = /16, C = /24) atau mask yang dipilih siswa.
      var nNet = maskPilih === 'auto' ? ({ A: 1, B: 2, C: 3 }[k] || 0) : parseInt(maskPilih, 10) / 8;
      var khusus = nNet === 0;
      document.querySelectorAll('#pilih-mask button').forEach(function (b) { b.classList.toggle('aktif', b.dataset.m === maskPilih); });
      kartuOktet.innerHTML = '';
      o.forEach(function (n, i) {
        var peran = khusus ? 'khusus' : (i < nNet ? 'net' : 'host');
        var c = document.createElement('div'); c.className = 'octet-card ' + peran;
        c.innerHTML = '<span class="octet-dec">' + n + '</span><span class="octet-bin">' + biner(n) + '</span><span class="octet-peran">' + { net: 'network', host: 'host', khusus: 'khusus' }[peran] + '</span>';
        kartuOktet.appendChild(c);
        setTimeout(function () { c.classList.add('show'); }, i * 90);
      });
      var catatan = [];
      if (k === 'loopback') catatan.push('<b>127.x.x.x = loopback</b>: alamat “diri sendiri”, tidak dipakai untuk perangkat di jaringan.');
      else if (k === 'D' || k === 'E') catatan.push('<b>Kelas ' + k + '</b>: dipakai untuk keperluan khusus (' + (k === 'D' ? 'multicast' : 'riset') + '), bukan untuk PC biasa.');
      else catatan.push('<b>Kelas ' + k + '</b> karena angka pertamanya ' + o[0] + ' (' + { A: '1–126', B: '128–191', C: '192–223' }[k] + ').');
      if (!khusus) {
        catatan.push(privat(o) ? 'Ini <b>IP privat</b>: dipakai di jaringan lokal seperti lab sekolah.' : 'Ini <b>IP publik</b>: dipakai di internet, bukan untuk lab.');
        var maskTeks = ['255.0.0.0', '255.255.0.0', '255.255.255.0'][nNet - 1], prefix = nNet * 8;
        var netBag = o.slice(0, nNet).join('.'), hostBag = o.slice(nNet).join('.');
        catatan.push((maskPilih === 'auto' ? 'Mask bawaan kelas ' + k + ' adalah ' : 'Dengan mask ') + '<b>' + maskTeks + ' (/' + prefix + ')</b>: ' + nNet + ' angka depan <span class="tag-net">' + netBag + '</span> = bagian network (nama jaringan), ' + (4 - nNet) + ' angka belakang <span class="tag-host">' + hostBag + '</span> = bagian host (nomor perangkat).');
        var hostOkt = o.slice(nNet);
        if (hostOkt.every(function (x) { return x === 0; })) catatan.push('⚠ Bagian host semuanya 0, jadi ini <b>network address</b>. Tidak boleh dipakai PC.');
        if (hostOkt.every(function (x) { return x === 255; })) catatan.push('⚠ Bagian host semuanya 255, jadi ini <b>broadcast address</b>. Tidak boleh dipakai PC.');
        if (maskPilih === 'auto' && k !== 'C') catatan.push('Catatan: di lab, IP kelas ' + k + ' juga sering dipakai dengan mask /24. Coba klik tombol <b>/24</b> di atas untuk melihat bedanya.');
      }
      jelas.hidden = false; jelas.className = 'penjelasan';
      jelas.innerHTML = catatan.map(function (c) { return '<p>' + c + '</p>'; }).join('');
    }
    var maskPilih = 'auto';
    document.querySelectorAll('#pilih-mask button').forEach(function (b) { b.addEventListener('click', function () { maskPilih = b.dataset.m; tampilIP(); }); });
    inp.addEventListener('input', tampilIP);
    document.querySelectorAll('#contoh-ip .chip').forEach(function (c) {
      c.addEventListener('click', function () { inp.value = c.dataset.ip; tampilIP(); });
    });
    inp.value = '192.168.1.10'; tampilIP();

    var SOAL1 = [
      { q: 'Mana IP address yang <b>valid</b>?', opsi: ['192.168.1.300', '192.168.1.30', '192.168.1', '256.10.1.1'], benar: 1,
        jelas: 'IP valid = 4 angka, masing-masing 0–255. 300 dan 256 melebihi 255, sedangkan 192.168.1 hanya punya 3 angka.' },
      { q: 'IP <span class="mono">172.16.5.10</span> termasuk kelas…', opsi: ['Kelas A', 'Kelas B', 'Kelas C', 'Kelas D'], benar: 1,
        jelas: 'Lihat angka pertama: 172 berada di rentang 128–191, jadi kelas B.' },
      { q: 'Pada <span class="mono">192.168.1.25</span> dengan mask 255.255.255.0, bagian <b>Host ID</b> (nomor perangkat) adalah…', opsi: ['192', '192.168.1', '25', '168.1'], benar: 2,
        jelas: 'Mask 255.255.255.0: tiga angka depan (192.168.1) = network, angka terakhir (25) = host.' },
      { q: 'PC1 memakai <span class="mono">192.168.1.5/24</span>. IP mana yang tepat untuk PC2 supaya bisa langsung saling ping lewat switch?', opsi: ['192.168.2.5', '192.168.1.5', '192.168.1.20', '10.168.1.20'], benar: 2,
        jelas: 'Network harus sama (192.168.1) dan nomor host harus beda. 192.168.1.5 bentrok dengan PC1 (IP conflict); 192.168.2.5 dan 10.168.1.20 beda jaringan.' },
      { q: 'Angka <span class="mono">192</span> dalam biner adalah…', opsi: ['11000000', '10101000', '11111111', '00001010'], benar: 0,
        jelas: '192 = 128 + 64, jadi bit bernilai 128 dan 64 diisi 1, sisanya 0: 11000000.' }
    ];
    var tombol1 = $('btn-m1-selesai');
    function mulai1() {
      kuis($('m1-soal'), SOAL1, $('m1-skor'), function (benar) {
        setProg({ m1: { selesai: true, benar: benar } });
        tombol1.disabled = false;
      }, 'm1');
    }
    var p1 = prog();
    if (p1.m1 && p1.m1.selesai) { $('m1-skor').textContent = p1.m1.benar + '/5'; tombol1.disabled = false; kuisSudah($('m1-soal'), p1.m1, function () { kuisTulis('m1', null); mulai1(); }); }
    else mulai1();
    tombol1.addEventListener('click', function () { window.location.href = 'subnetmask.html'; });
  }

  /* ============================================================ MISI 2: SUBNET */
  if (halaman === 'subnet') {
    if (!(prog().m1 && prog().m1.selesai)) { window.location.replace('index.html'); return; }
    var sIn = $('subnet-input'), prefix = 24, tugas = {};
    var tombolP = document.querySelectorAll('#pilih-prefix button');
    function centang(k) { if (tugas[k]) return; tugas[k] = true; var li = document.querySelector('[data-tugas="' + k + '"]'); if (li) li.classList.add('beres'); }
    function hitung() {
      tombolP.forEach(function (b) { var on = parseInt(b.dataset.p, 10) === prefix; b.classList.toggle('aktif', on); b.setAttribute('aria-checked', on ? 'true' : 'false'); });
      var p = prefix, blok = Math.pow(2, 32 - p), jumlah = 256 / blok;
      var maskBit = ''; for (var i = 0; i < 32; i++) { maskBit += i < p ? '1' : '0'; }
      var maskOkt = [0, 1, 2, 3].map(function (i) { return parseInt(maskBit.substr(i * 8, 8), 2); });
      $('mask-biner').innerHTML = [0, 1, 2, 3].map(function (i) {
        var bit = maskBit.substr(i * 8, 8).split('').map(function (x) { return '<i class="' + (x === '1' ? 'satu' : 'nol') + '">' + x + '</i>'; }).join('');
        return '<span class="mb-okt"><span class="mb-bit">' + bit + '</span><span class="mb-dec">' + maskOkt[i] + '</span></span>';
      }).join('<span class="mb-titik">.</span>') + '<span class="mb-ket">Mask <b>' + maskOkt.join('.') + '</b> = /' + p + '</span>';

      var o = parseIP(sIn.value);
      var pita = $('pita'); pita.innerHTML = '';
      var posisi = o ? Math.floor(o[3] / blok) : -1;
      for (var s2 = 0; s2 < jumlah; s2++) {
        var seg = document.createElement('div');
        seg.className = 'seg' + (s2 % 2 ? ' seg-b' : '') + (s2 === posisi ? ' aktif' : '') + (s2 === jumlah - 1 ? ' akhir' : '');
        seg.innerHTML = jumlah <= 8 ? '<b>' + (jumlah <= 4 ? 'Subnet ' : '#') + (s2 + 1) + '</b><span>.' + (s2 * blok) + '–.' + (s2 * blok + blok - 1) + '</span>' : '<b>' + (s2 + 1) + '</b>';
        pita.appendChild(seg);
      }
      if (o) { var penanda = document.createElement('div'); penanda.className = 'penanda'; penanda.style.left = ((o[3] + 0.5) / 256 * 100) + '%'; penanda.innerHTML = '<span>PC .' + o[3] + '</span>'; pita.appendChild(penanda); }
      $('pita-ket').innerHTML = p === 24
        ? 'Dengan <b>/24</b> jaringannya <b>tidak dibagi</b>: 1 subnet berisi 256 alamat. Coba pilih /25 atau /26.'
        : 'Dengan <b>/' + p + '</b> jaringan dibagi jadi <b>' + jumlah + ' subnet</b>. Setiap subnet berisi <b>' + blok + ' alamat</b>, jadi network address tiap subnet “melompat” setiap ' + blok + ': ' + Array.from({ length: Math.min(jumlah, 5) }, function (_, i) { return '.' + i * blok; }).join(', ') + (jumlah > 5 ? ', …' : '') + '.';
      if (p === 25) centang('a');

      if (!o) { sIn.classList.add('invalid'); $('subnet-error').textContent = alasanTidakValid(sIn.value); $('rt-kartu').innerHTML = ''; return; }
      sIn.classList.remove('invalid'); $('subnet-error').textContent = '';
      var net = posisi * blok, bc = net + blok - 1, awal = o[0] + '.' + o[1] + '.' + o[2] + '.';
      var catatan = o[3] === net ? '<p class="peringatan">⚠ .' + o[3] + ' adalah <b>network address</b> subnet ini, jadi tidak boleh dipakai PC.</p>'
        : o[3] === bc ? '<p class="peringatan">⚠ .' + o[3] + ' adalah <b>broadcast address</b> subnet ini, jadi tidak boleh dipakai PC.</p>'
        : '<p class="aman">✔ .' + o[3] + ' boleh dipakai PC. Teman satu subnet-nya bisa langsung saling ping; PC di subnet lain butuh router.</p>';
      $('rt-kartu').innerHTML = '<div class="rt-kartu"><div class="rt-kepala">Alamat ' + esc(sIn.value.trim()) + ' ada di <b>subnet ke-' + (posisi + 1) + '</b> dari ' + jumlah + '</div>'
        + '<div class="rt-tiga"><span class="papan"><small>Network address</small>' + awal + net + '</span><span class="rumah"><small>Boleh dipakai PC (' + (blok - 2) + ' PC)</small>' + awal + (net + 1) + ' – .' + (bc - 1) + '</span><span class="toa"><small>Broadcast address</small>' + awal + bc + '</span></div>'
        + catatan + '<p class="mask-kecil">Subnet mask: <b class="mono">' + maskOkt.join('.') + '</b> (/' + p + ')</p></div>';
      if (p === 26 && o[3] === 70) centang('b');
      if (p === 26 && o[3] === 200) centang('c');
    }
    tombolP.forEach(function (b) { b.addEventListener('click', function () { prefix = parseInt(b.dataset.p, 10); hitung(); }); });
    document.querySelectorAll('.chip[data-ip]').forEach(function (c) { c.addEventListener('click', function () { sIn.value = c.dataset.ip; hitung(); }); });
    sIn.addEventListener('input', hitung); hitung();

    var BANK2 = [
      { q: 'Berapa jumlah host yang bisa dipakai pada <span class="mono">/24</span>?', opsi: ['256', '255', '254', '24'], benar: 2, jelas: 'Di /24 ada alamat .0 sampai .255 (256 alamat). .0 = network address, .255 = broadcast address. Sisanya 254 untuk PC.' },
      { q: 'Subnet mask untuk <span class="mono">/26</span> adalah…', opsi: ['255.255.255.0', '255.255.255.128', '255.255.255.192', '255.255.255.224'], benar: 2, jelas: 'Lihat contekan: /25 → 128, /26 → 192, /27 → 224. Jadi /26 = 255.255.255.192.' },
      { q: 'Network address dari <span class="mono">192.168.1.50/24</span> adalah…', opsi: ['192.168.1.0', '192.168.1.50', '192.168.1.1', '192.168.0.0'], benar: 0, jelas: 'Network address = bagian host diganti 0. Di /24 bagian host adalah angka terakhir.' },
      { q: 'Broadcast address dari <span class="mono">192.168.1.0/26</span> adalah…', opsi: ['192.168.1.255', '192.168.1.63', '192.168.1.64', '192.168.1.62'], benar: 1, jelas: 'Ukuran blok /26 = 64. Lompat 64: subnet pertama .0, subnet kedua .64. Broadcast subnet pertama = 64 − 1 = .63.' },
      { q: 'Jaringan <span class="mono">192.168.10.0/24</span> mau dibagi menjadi 2 subnet. Prefix yang dipakai…', opsi: ['/23', '/25', '/26', '/30'], benar: 1, jelas: 'Setiap prefix naik 1, jaringannya dibagi 2. /24 dibagi 2 subnet = /25 (ukuran blok 128).' },
      { q: 'Untuk membuat <b>4 subnet</b> dari /24, prefix yang dipakai…', opsi: ['/25', '/26', '/27', '/28'], benar: 1, jelas: '/24 → /25 (2 subnet) → /26 (4 subnet). Ukuran bloknya 256 ÷ 4 = 64.' },
      { q: 'Sebuah lab butuh <b>25 komputer</b>. Prefix terkecil yang cukup…', opsi: ['/26', '/27', '/28', '/29'], benar: 1, jelas: 'Lihat kolom host per subnet: /28 cuma 14 (kurang), /27 = 30 (cukup dan paling hemat).' },
      { q: 'Jumlah host yang bisa dipakai pada <span class="mono">/30</span>…', opsi: ['4', '2', '0', '6'], benar: 1, jelas: 'Ukuran blok /30 = 4 alamat. Dikurangi network dan broadcast, sisa 2 PC.' },
      { q: 'IP <span class="mono">192.168.10.70/26</span> berada di subnet dengan network address…', opsi: ['192.168.10.0', '192.168.10.64', '192.168.10.70', '192.168.10.128'], benar: 1, jelas: 'Lompat 64: subnet mulai di .0, .64, .128, .192. Angka 70 ada di subnet .64 – .127, jadi network address-nya .64. Coba lihat batang di atas!' }
    ];
    var tombol2 = $('btn-m2-selesai');
    function mulai2() {
      // Soal diacak sekali lalu urutannya disimpan, supaya setelah dimuat ulang soalnya tetap sama.
      var lamaKuis = kuisData('m2'), urut = lamaKuis && lamaKuis.urut;
      if (!urut || urut.length !== 5 || urut.some(function (x) { return !BANK2[x]; })) urut = acak(BANK2.map(function (_, i) { return i; })).slice(0, 5);
      if (!lamaKuis || lamaKuis.urut !== urut) kuisTulis('m2', { urut: urut, jawab: lamaKuis && lamaKuis.urut === urut ? lamaKuis.jawab : {} });
      kuis($('m2-soal'), urut.map(function (x) { return BANK2[x]; }), $('m2-skor'), function (benar) {
        var lama = prog().m2;
        setProg({ m2: { selesai: true, benar: Math.max(benar, lama ? lama.benar : 0) } });
        tombol2.disabled = false;
      }, 'm2', urut);
    }
    var p2 = prog();
    if (p2.m2 && p2.m2.selesai) { $('m2-skor').textContent = p2.m2.benar + '/5'; tombol2.disabled = false; kuisSudah($('m2-soal'), p2.m2, function () { kuisTulis('m2', null); mulai2(); }); }
    else mulai2();
    tombol2.addEventListener('click', function () { window.location.href = 'topologi.html'; });
  }

  /* ============================================================ MISI 3: TOPOLOGI */
  if (halaman === 'topo') {
    if (!(prog().m2 && prog().m2.selesai)) { window.location.replace('index.html'); return; }
    var canvas = $('topo-canvas'), svg = $('topo-svg'), formEl = $('device-form');
    var IKON = {
      pc: document.querySelector('#btn-add-pc svg').outerHTML,
      switch: document.querySelector('#btn-add-switch svg').outerHTML,
      router: document.querySelector('#btn-add-router svg').outerHTML
    };
    var LABEL = { pc: 'PC', switch: 'Switch', router: 'Router' };
    var nodes = [], conns = [], nextId = 1, nextConn = 1;
    var tunggu = null, modeKabel = false, kabelDari = null, kabelTerpilih = null, aktif = null;
    var sudahArp = {}; // ping pertama antarpasangan: satu "Request timed out" (perangkat masih saling kenalan / ARP)

    function pesan(teks, jenis) {
      var m = $('topo-message');
      m.innerHTML = teks; m.className = 'topo-message show ' + (jenis || 'ok');
      clearTimeout(pesan._t); pesan._t = setTimeout(function () { m.className = 'topo-message'; }, jenis === 'warn' || jenis === 'err' ? 7000 : 4000);
    }
    function ukuran() { return canvas.getBoundingClientRect(); }
    function byId(id) { for (var i = 0; i < nodes.length; i++) if (nodes[i].id === id) return nodes[i]; return null; }
    function tetangga(n) { return conns.filter(function (c) { return c.a === n.id || c.b === n.id; }).map(function (c) { return byId(c.a === n.id ? c.b : c.a); }); }
    function namaBaru(type) {
      var i = 1; while (nodes.some(function (n) { return n.name === LABEL[type] + i; })) i++;
      return LABEL[type] + i;
    }

    /* --- simpan & muat susunan (bertahan saat halaman dimuat ulang) --- */
    function simpanTopo() {
      var r = ukuran(), d = muat();
      d.topo = { nodes: nodes.map(function (n) { return { id: n.id, type: n.type, name: n.name, ip: n.ip, gw: n.gw, fx: n.x / r.width, fy: n.y / r.height }; }), conns: conns.map(function (c) { return [c.a, c.b]; }) };
      simpan(d);
    }
    function berubah() { sudahArp = {}; tandaiBelumDiperiksa(); simpanTopo(); isiPilihanPing(); }

    function tambahNode(type, x, y, data) {
      data = data || {};
      var n = { id: data.id || nextId++, type: type, name: data.name || namaBaru(type), ip: data.ip || '', gw: data.gw || '', x: x, y: y, el: null };
      nextId = Math.max(nextId, n.id + 1);
      var el = document.createElement('div'); el.className = 'device-node device-' + type; el.tabIndex = 0;
      el.innerHTML = IKON[type] + '<span class="device-label"></span>';
      el.style.left = x + 'px'; el.style.top = y + 'px';
      canvas.appendChild(el); n.el = el; nodes.push(n);
      label(n); ikatNode(n);
      return n;
    }
    function label(n) {
      var teks = n.name + '\n' + (n.type === 'switch' ? 'tanpa IP' : (n.ip || 'IP belum diatur'));
      var l = n.el.querySelector('.device-label'); l.textContent = teks;
      n.el.classList.toggle('tanpa-ip', n.type !== 'switch' && !n.ip);
      n.el.setAttribute('aria-label', n.name + (n.type === 'switch' ? '' : ', IP ' + (n.ip || 'belum diatur')));
    }

    /* --- seret --- */
    var seret = null, abaikanKlik = false;
    function ikatNode(n) {
      n.el.addEventListener('pointerdown', function (e) {
        if (tunggu || modeKabel || aktif) return;
        e.preventDefault();
        seret = { n: n, sx: e.clientX, sy: e.clientY, ox: n.x, oy: n.y, gerak: false };
        try { n.el.setPointerCapture(e.pointerId); } catch (x) {}
      });
      n.el.addEventListener('pointermove', function (e) {
        if (!seret || seret.n !== n) return;
        var dx = e.clientX - seret.sx, dy = e.clientY - seret.sy;
        if (Math.abs(dx) > 5 || Math.abs(dy) > 5) {
          seret.gerak = true; n.el.classList.add('dragging');
          var r = ukuran();
          n.x = Math.max(40, Math.min(r.width - 40, seret.ox + dx)); n.y = Math.max(36, Math.min(r.height - 44, seret.oy + dy));
          n.el.style.left = n.x + 'px'; n.el.style.top = n.y + 'px'; gambarKabel();
        }
      });
      n.el.addEventListener('pointerup', function () {
        if (!seret || seret.n !== n) return;
        var gerak = seret.gerak; seret = null; n.el.classList.remove('dragging');
        if (gerak) { abaikanKlik = true; setTimeout(function () { abaikanKlik = false; }, 60); simpanTopo(); }
      });
      n.el.addEventListener('click', function (e) {
        if (abaikanKlik) return;
        e.stopPropagation();
        if (modeKabel) klikKabel(n); else bukaForm(n);
      });
      n.el.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); n.el.click(); } });
    }

    /* --- tambah perangkat --- */
    function batalTunggu() {
      tunggu = null; canvas.classList.remove('placing');
      ['pc', 'switch', 'router'].forEach(function (t) { $('btn-add-' + t).classList.remove('active'); });
    }
    ['pc', 'switch', 'router'].forEach(function (t) {
      $('btn-add-' + t).addEventListener('click', function () {
        if (tunggu === t) { batalTunggu(); return; }
        tutupForm(); setModeKabel(false);
        tunggu = t; canvas.classList.add('placing');
        ['pc', 'switch', 'router'].forEach(function (x) { $('btn-add-' + x).classList.toggle('active', x === t); });
        pesan('Klik di kanvas untuk menaruh ' + LABEL[t] + '.', 'ok');
      });
    });
    canvas.addEventListener('click', function (e) {
      if (e.target.closest('.device-node') || e.target.closest('.device-form')) return;
      if (!tunggu) { pilihKabel(null); return; }
      var r = ukuran();
      var n = tambahNode(tunggu, Math.max(40, Math.min(r.width - 40, e.clientX - r.left)), Math.max(36, Math.min(r.height - 44, e.clientY - r.top)));
      batalTunggu(); berubah();
      pesan(n.name + ' ditambahkan. ' + (n.type === 'switch' ? 'Switch tidak perlu IP.' : 'Klik perangkatnya untuk mengatur IP.'), 'ok');
    });

    /* --- kabel --- */
    function setModeKabel(on) {
      modeKabel = on; kabelDari = null;
      nodes.forEach(function (n) { n.el.classList.remove('connecting'); });
      var b = $('btn-mode-connect'); b.classList.toggle('active', on); b.textContent = on ? 'Mode kabel: AKTIF (klik untuk selesai)' : 'Mode kabel';
      canvas.classList.toggle('mode-kabel', on);
    }
    $('btn-mode-connect').addEventListener('click', function () {
      tutupForm(); batalTunggu(); pilihKabel(null);
      setModeKabel(!modeKabel);
      if (modeKabel) pesan('Klik perangkat pertama, lalu perangkat kedua. Kabel yang dipakai: Copper Straight-Through.', 'ok');
    });
    function peringatanKabel(a, b) {
      var t = [a.type, b.type].sort().join('-');
      if (t === 'pc-pc') return '⚠ <b>Tidak sesuai standar.</b> PC disambung langsung ke PC. Di lab, setiap PC disambung ke <b>switch</b>.';
      if (t === 'pc-router') return '⚠ <b>Tidak sesuai standar.</b> PC jangan langsung ke router. Urutannya <b>Router → Switch → PC</b>.';
      if (t === 'router-router') return '⚠ Router ke router tidak dibahas di pertemuan ini.';
      var pc = a.type === 'pc' ? a : (b.type === 'pc' ? b : null);
      if (pc && tetangga(pc).length > 1) return '⚠ <b>' + esc(pc.name) + ' sekarang punya ' + tetangga(pc).length + ' kabel.</b> PC hanya punya 1 port LAN (FastEthernet0). Sambungkan PC ke switch saja.';
      var rt = a.type === 'router' ? a : (b.type === 'router' ? b : null);
      if (rt && tetangga(rt).length > 1) return '⚠ Di simulasi ini router cukup punya <b>1 kabel ke switch</b>.';
      return '';
    }
    function klikKabel(n) {
      if (!kabelDari) { kabelDari = n; n.el.classList.add('connecting'); pesan('Sekarang klik perangkat tujuan untuk ' + esc(n.name) + '.', 'ok'); return; }
      if (kabelDari === n) { n.el.classList.remove('connecting'); kabelDari = null; return; }
      var a = kabelDari, ada = conns.some(function (c) { return (c.a === a.id && c.b === n.id) || (c.a === n.id && c.b === a.id); });
      a.el.classList.remove('connecting'); kabelDari = null;
      if (ada) { pesan('Kedua perangkat itu sudah tersambung.', 'err'); return; }
      tambahKabel(a.id, n.id);
      var w = peringatanKabel(a, n);
      pesan(w || ('Kabel terpasang: ' + esc(a.name) + ' ↔ ' + esc(n.name) + '.'), w ? 'warn' : 'ok');
      berubah();
    }
    function tambahKabel(aId, bId) {
      var id = nextConn++;
      var line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
      line.setAttribute('stroke-linecap', 'round'); line.setAttribute('class', 'kabel');
      svg.appendChild(line);
      var c = { id: id, a: aId, b: bId, line: line };
      line.addEventListener('click', function (e) { e.stopPropagation(); pilihKabel(c); });
      conns.push(c); gambarKabel();
      return c;
    }
    function gambarKabel() {
      conns.forEach(function (c) {
        var a = byId(c.a), b = byId(c.b); if (!a || !b) return;
        c.line.setAttribute('x1', a.x); c.line.setAttribute('y1', a.y); c.line.setAttribute('x2', b.x); c.line.setAttribute('y2', b.y);
        var t = [a.type, b.type].sort().join('-');
        c.line.classList.toggle('kabel-salah', t === 'pc-pc' || t === 'pc-router' || t === 'router-router');
      });
    }
    function pilihKabel(c) {
      if (kabelTerpilih) kabelTerpilih.line.classList.remove('terpilih');
      kabelTerpilih = c || null;
      if (c) c.line.classList.add('terpilih');
      $('btn-delete-connection').disabled = !c;
    }
    $('btn-delete-connection').addEventListener('click', function () {
      if (!kabelTerpilih) return;
      var c = kabelTerpilih; pilihKabel(null);
      conns = conns.filter(function (x) { return x !== c; }); c.line.remove();
      pesan('Kabel dihapus.', 'ok'); berubah();
    });

    /* --- formulir perangkat --- */
    function bukaForm(n) {
      tutupForm(); aktif = n;
      $('input-device-name').value = n.name; $('input-device-ip').value = n.ip; $('input-device-gw').value = n.gw;
      $('fld-ip').hidden = n.type === 'switch'; $('fld-mask').hidden = n.type === 'switch';
      $('fld-gw').hidden = n.type !== 'pc'; $('fld-switch').hidden = n.type !== 'switch';
      $('device-form-error').textContent = '';
      formEl.classList.add('show');
      var r = ukuran();
      formEl.style.left = Math.min(Math.max(n.x + 20, 8), Math.max(8, r.width - formEl.offsetWidth - 8)) + 'px';
      formEl.style.top = Math.min(Math.max(n.y - 40, 8), Math.max(8, r.height - formEl.offsetHeight - 8)) + 'px';
      n.el.classList.add('selected');
      setTimeout(function () { (n.type === 'switch' ? $('input-device-name') : $('input-device-ip')).focus(); }, 30);
    }
    function tutupForm() { formEl.classList.remove('show'); if (aktif) aktif.el.classList.remove('selected'); aktif = null; }
    $('btn-close-form').addEventListener('click', tutupForm);
    function simpanForm() {
      if (!aktif) return;
      var nama = $('input-device-name').value.trim(), ip = $('input-device-ip').value.trim(), gw = $('input-device-gw').value.trim();
      if (!nama) { $('device-form-error').textContent = 'Nama wajib diisi.'; return; }
      if (nodes.some(function (x) { return x !== aktif && x.name.toLowerCase() === nama.toLowerCase(); })) { $('device-form-error').textContent = 'Nama sudah dipakai perangkat lain.'; return; }
      if (aktif.type !== 'switch' && ip !== '' && !parseIP(ip)) { $('device-form-error').textContent = alasanTidakValid(ip); return; }
      if (aktif.type === 'pc' && gw !== '' && !parseIP(gw)) { $('device-form-error').textContent = 'Gateway: ' + alasanTidakValid(gw); return; }
      aktif.name = nama;
      if (aktif.type !== 'switch') aktif.ip = ip;
      if (aktif.type === 'pc') aktif.gw = gw;
      label(aktif); var n = aktif; tutupForm(); berubah();
      pesan(esc(n.name) + ' disimpan.', 'ok');
    }
    $('btn-save-device').addEventListener('click', simpanForm);
    formEl.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); simpanForm(); } if (e.key === 'Escape') tutupForm(); });
    $('btn-delete-device').addEventListener('click', function () {
      if (!aktif) return;
      var n = aktif; tutupForm();
      conns.filter(function (c) { return c.a === n.id || c.b === n.id; }).forEach(function (c) { c.line.remove(); });
      conns = conns.filter(function (c) { return c.a !== n.id && c.b !== n.id; });
      n.el.remove(); nodes = nodes.filter(function (x) { return x !== n; });
      pesan(esc(n.name) + ' dihapus.', 'ok'); berubah();
    });

    duaKlik($('btn-kosongkan'), 'Yakin? Klik lagi untuk menghapus semua', function () {
      kosongkan(); berubah(); pesan('Kanvas sudah dikosongkan.', 'ok');
    });
    function kosongkan() { tutupForm(); conns.forEach(function (c) { c.line.remove(); }); nodes.forEach(function (n) { n.el.remove(); }); conns = []; nodes = []; nextId = 1; }

    /* --- pemeriksa topologi --- */
    function cekTopologi() {
      var umum = [], t2 = [], baik = [];
      var pcs = nodes.filter(function (n) { return n.type === 'pc'; }), sws = nodes.filter(function (n) { return n.type === 'switch'; }), rts = nodes.filter(function (n) { return n.type === 'router'; });
      function e(daftar, teks) { daftar.push(teks); }
      if (pcs.length < 2) e(umum, 'Butuh minimal <b>2 PC</b> (sekarang ' + pcs.length + ').'); else baik.push('Ada ' + pcs.length + ' PC.');
      if (!sws.length) e(umum, 'Belum ada <b>switch</b>. PC di satu ruangan disambung lewat switch.'); else baik.push('Ada switch.');
      // kabel tidak standar
      conns.forEach(function (c) {
        var a = byId(c.a), b = byId(c.b), t = [a.type, b.type].sort().join('-');
        if (t === 'pc-pc') e(umum, '<b>Tidak sesuai standar:</b> ' + esc(a.name) + ' ↔ ' + esc(b.name) + ' tersambung langsung. Sambungkan setiap PC ke switch.');
        if (t === 'pc-router') { var pc = a.type === 'pc' ? a : b, rt = a.type === 'router' ? a : b; e(umum, '<b>Tidak sesuai standar:</b> ' + esc(pc.name) + ' tersambung langsung ke ' + esc(rt.name) + '. Urutannya <b>Router → Switch → PC</b>.'); }
        if (t === 'router-router') e(umum, 'Kabel router ↔ router tidak dipakai di pertemuan ini. Hapus kabelnya.');
      });
      pcs.forEach(function (pc) {
        var tg = tetangga(pc);
        if (!tg.length) e(umum, esc(pc.name) + ' belum tersambung kabel.');
        else if (tg.length > 1) e(umum, esc(pc.name) + ' punya ' + tg.length + ' kabel. PC hanya punya <b>1 port LAN</b>: sambungkan ke switch saja.');
      });
      sws.forEach(function (sw) { if (!tetangga(sw).length) e(umum, esc(sw.name) + ' belum tersambung ke perangkat mana pun.'); });
      rts.forEach(function (rt) {
        var tg = tetangga(rt);
        if (tg.length > 1) e(umum, esc(rt.name) + ' punya ' + tg.length + ' kabel. Di simulasi ini router cukup <b>1 kabel ke switch</b>.');
        if (!tg.length) e(t2, esc(rt.name) + ' belum tersambung. Sambungkan router ke <b>switch</b>.');
      });
      // IP setiap PC
      var adaIpSalah = false;
      pcs.forEach(function (pc) {
        if (!pc.ip) { e(umum, 'IP ' + esc(pc.name) + ' belum diisi.'); adaIpSalah = true; return; }
        var o = parseIP(pc.ip);
        if (!o) { e(umum, 'IP ' + esc(pc.name) + ' tidak valid: ' + esc(alasanTidakValid(pc.ip))); adaIpSalah = true; return; }
        if (o[3] === 0) { e(umum, esc(pc.name) + ' memakai .0, itu <b>alamat jaringan</b>. Pakai .1 sampai .254.'); adaIpSalah = true; }
        if (o[3] === 255) { e(umum, esc(pc.name) + ' memakai .255, itu <b>broadcast</b>. Pakai .1 sampai .254.'); adaIpSalah = true; }
        if (jaringan24(o) !== '192.168.1') { e(umum, 'Tantangan meminta jaringan <b>192.168.1.x</b>, tetapi ' + esc(pc.name) + ' memakai ' + esc(pc.ip) + '.'); adaIpSalah = true; }
        if (pc.gw && !rts.length) e(umum, 'Gateway ' + esc(pc.name) + ' diisi (' + esc(pc.gw) + ') padahal belum ada router. Untuk Tantangan 1, <b>kosongkan</b> gateway.');
      });
      // konflik IP
      var pakai = {};
      nodes.forEach(function (n) { if (n.type !== 'switch' && parseIP(n.ip)) { (pakai[n.ip] = pakai[n.ip] || []).push(n.name); } });
      Object.keys(pakai).forEach(function (ip) { if (pakai[ip].length > 1) { e(umum, '<b>IP conflict:</b> ' + pakai[ip].map(esc).join(' dan ') + ' sama-sama memakai ' + esc(ip) + '. Setiap perangkat harus punya IP berbeda.'); adaIpSalah = true; } });
      // satu switch tapi beda jaringan
      var dilihat = {};
      sws.forEach(function (sw) {
        if (dilihat[sw.id]) return;
        var antre = [sw], grup = [];
        dilihat[sw.id] = true;
        while (antre.length) {
          var x = antre.shift();
          tetangga(x).forEach(function (y) {
            if (y.type === 'switch' && !dilihat[y.id]) { dilihat[y.id] = true; antre.push(y); }
            if (y.type === 'pc' && grup.indexOf(y) < 0) grup.push(y);
          });
        }
        var jar = {};
        grup.forEach(function (pc) { var o = parseIP(pc.ip); if (o) (jar[jaringan24(o)] = jar[jaringan24(o)] || []).push(pc.name); });
        var kunciJar = Object.keys(jar);
        if (kunciJar.length > 1) e(umum, '<b>Beda jaringan di satu switch:</b> ' + kunciJar.map(function (k) { return jar[k].map(esc).join(', ') + ' (' + k + '.x)'; }).join(' vs ') + '. Tanpa router mereka tidak bisa saling ping. Samakan tiga angka depan.');
      });
      if (!adaIpSalah && pcs.length >= 2) baik.push('IP semua PC benar dan satu jaringan (192.168.1.x).');
      var kabelOk = pcs.length && pcs.every(function (pc) { var tg = tetangga(pc); return tg.length === 1 && tg[0].type === 'switch'; });
      if (kabelOk) baik.push('Setiap PC tersambung ke switch (standar).');

      // Tantangan 2: router sebagai gateway
      var rt = rts[0];
      if (!rt) t2.push('<i>Bonus:</i> tambahkan router, sambungkan ke switch, beri IP 192.168.1.1, lalu isi <b>gateway</b> setiap PC dengan IP router.');
      else {
        if (rts.length > 1) e(t2, 'Cukup <b>1 router</b> untuk tantangan ini.');
        var tgR = tetangga(rt);
        if (tgR.length === 1 && tgR[0].type !== 'switch') { /* sudah dilaporkan sebagai tidak standar */ }
        var oR = parseIP(rt.ip);
        if (!rt.ip) e(t2, 'IP ' + esc(rt.name) + ' belum diisi. Contoh: 192.168.1.1.');
        else if (!oR) e(t2, 'IP ' + esc(rt.name) + ' tidak valid.');
        else if (jaringan24(oR) !== '192.168.1' || oR[3] === 0 || oR[3] === 255) e(t2, 'IP router harus satu jaringan dengan PC (192.168.1.1 sampai .254).');
        pcs.forEach(function (pc) {
          if (!pc.gw) e(t2, 'Gateway ' + esc(pc.name) + ' belum diisi. Isi dengan IP router' + (rt.ip ? ' (' + esc(rt.ip) + ')' : '') + '.');
          else if (pc.gw !== rt.ip) e(t2, 'Gateway ' + esc(pc.name) + ' (' + esc(pc.gw) + ') tidak sama dengan IP router' + (rt.ip ? ' (' + esc(rt.ip) + ')' : '') + '.');
        });
        if (tgR.length === 1 && tgR[0].type === 'switch' && !t2.length) baik.push('Router → Switch → PC tersusun benar, gateway PC = IP router.');
      }
      return { umum: umum, t2: t2, baik: baik, t1: umum.length === 0, t2ok: umum.length === 0 && rts.length > 0 && t2.length === 0 };
    }
    function tandaiBelumDiperiksa() { $('cek-list').classList.add('usang'); }
    function tampilkanCek() {
      var h = cekTopologi(), ul = $('cek-list'); ul.innerHTML = ''; ul.classList.remove('usang');
      h.baik.forEach(function (t) { var li = document.createElement('li'); li.className = 'ok'; li.innerHTML = t; ul.appendChild(li); });
      h.umum.forEach(function (t) { var li = document.createElement('li'); li.className = 'err'; li.innerHTML = t; ul.appendChild(li); });
      h.t2.forEach(function (t) { var li = document.createElement('li'); li.className = h.t1 ? 'info' : 'info redup'; li.innerHTML = t; ul.appendChild(li); });
      var p = prog(), patch = {};
      if (h.t1 && !p.t1) patch.t1 = true;
      if (h.t2ok && !p.t2) patch.t2 = true;
      if (Object.keys(patch).length) setProg(patch);
      if (h.t1) pesan(h.t2ok ? '🎉 Topologi standar lengkap dengan router! Coba kirim ping.' : '✅ Tantangan 1 selesai! Sekarang uji dengan <b>ping</b> di bawah kanvas.', 'ok');
      else pesan('Masih ada ' + h.umum.length + ' hal yang perlu diperbaiki. Lihat daftar Pemeriksa.', 'err');
      segarkanTantangan();
      return h;
    }
    $('btn-validate-topo').addEventListener('click', tampilkanCek);

    function segarkanTantangan() {
      var p = prog();
      [['t1', p.t1], ['t2', p.ping], ['t3', p.t2]].forEach(function (x) {
        $(x[0]).classList.toggle('beres', !!x[1]); $(x[0] + '-status').textContent = x[1] ? 'Selesai ✓' : 'Belum';
      });
      $('btn-finish-topo').disabled = !(p.t1 && p.ping);
    }

    /* --- uji ping dengan animasi paket --- */
    function isiPilihanPing() {
      ['ping-dari', 'ping-ke'].forEach(function (id, i) {
        var s = $(id), lama = s.value; s.innerHTML = '';
        nodes.filter(function (n) { return i === 0 ? n.type === 'pc' : n.type !== 'switch'; }).forEach(function (n) {
          var o = document.createElement('option'); o.value = n.id; o.textContent = n.name + (n.ip ? ' (' + n.ip + ')' : ''); s.appendChild(o);
        });
        if (lama && s.querySelector('option[value="' + lama + '"]')) s.value = lama;
        else if (i === 1 && s.options.length > 1) s.selectedIndex = 1;
      });
    }
    function jalur(dari, ke) {
      // BFS: data hanya bisa diteruskan oleh switch. PC dan router tidak meneruskan paket di topologi ini.
      var asal = {}, antre = [dari]; asal[dari.id] = null;
      while (antre.length) {
        var x = antre.shift();
        if (x === ke) break;
        if (x !== dari && x.type !== 'switch') continue;
        tetangga(x).forEach(function (y) { if (!(y.id in asal)) { asal[y.id] = x; antre.push(y); } });
      }
      if (!(ke.id in asal)) return null;
      var r = [], c = ke; while (c) { r.unshift(c); c = asal[c.id]; }
      return r;
    }
    var paket = $('paket'), sibuk = false;
    function gerak(titik, kelas) {
      return new Promise(function (selesai) {
        paket.hidden = false; paket.className = 'paket ' + kelas;
        var i = 0;
        paket.style.transition = 'none'; paket.style.left = titik[0].x + 'px'; paket.style.top = titik[0].y + 'px';
        function lanjut() {
          i++;
          if (i >= titik.length) { setTimeout(selesai, 120); return; }
          paket.style.transition = 'left .45s linear, top .45s linear';
          paket.style.left = titik[i].x + 'px'; paket.style.top = titik[i].y + 'px';
          setTimeout(lanjut, 470);
        }
        requestAnimationFrame(function () { requestAnimationFrame(lanjut); });
      });
    }
    function tulis(baris) { $('terminal').textContent = baris.join('\n'); }
    $('btn-ping').addEventListener('click', function () {
      if (sibuk) return;
      var dari = byId(parseInt($('ping-dari').value, 10)), ke = byId(parseInt($('ping-ke').value, 10));
      if (!dari || !ke) { tulis(['Tambahkan minimal 2 PC dulu.']); return; }
      if (dari === ke) { tulis(['Pilih PC tujuan yang berbeda.']); return; }
      var oA = parseIP(dari.ip), oB = parseIP(ke.ip);
      var kepala = ['C:\\> ping ' + (ke.ip || '(IP belum diatur)'), ''];
      if (!oA) { tulis(kepala.concat(['IP ' + dari.name + ' belum diatur atau tidak valid.', '', 'Kenapa gagal? Pengirim belum punya alamat, jadi tidak bisa mengirim paket.'])); return; }
      if (!oB) { tulis(kepala.concat(['Ping request could not find host.', '', 'Kenapa gagal? ' + ke.name + ' belum punya IP yang valid.'])); return; }
      var rute = jalur(dari, ke), alasan = '';
      var konflik = nodes.filter(function (n) { return n !== ke && n.ip === ke.ip; }).length > 0 || dari.ip === ke.ip;
      if (!rute) alasan = conns.length ? 'Tidak ada jalur kabel lewat switch dari ' + dari.name + ' ke ' + ke.name + '. Ingat: PC tidak meneruskan data; sambungkan semua PC ke switch.' : 'Belum ada kabel sama sekali.';
      else if (jaringan24(oA) !== jaringan24(oB)) alasan = dari.name + ' (' + jaringan24(oA) + '.x) dan ' + ke.name + ' (' + jaringan24(oB) + '.x) beda jaringan. Tanpa router, beda jaringan tidak bisa saling ping.';
      else if (konflik) alasan = 'IP conflict: ada lebih dari satu perangkat memakai ' + ke.ip + '.';
      else if (oB[3] === 0 || oB[3] === 255 || oA[3] === 0 || oA[3] === 255) alasan = 'Alamat .0 dan .255 bukan alamat perangkat.';
      var titik = (rute || [dari]).map(function (n) { return { x: n.x, y: n.y }; });
      sibuk = true; $('btn-ping').disabled = true;
      tulis(kepala.concat(['Pinging ' + ke.ip + ' with 32 bytes of data:', '']));
      var log = kepala.concat(['Pinging ' + ke.ip + ' with 32 bytes of data:', '']);
      if (alasan) {
        var sampai = rute ? titik.slice(0, Math.max(1, titik.length - 1)) : titik;
        gerak(sampai.length > 1 ? sampai : [titik[0], { x: titik[0].x + 30, y: titik[0].y }], 'gagal').then(function () {
          for (var i = 0; i < 4; i++) log.push('Request timed out.');
          log.push('', 'Ping statistics for ' + ke.ip + ':', '    Packets: Sent = 4, Received = 0, Lost = 4 (100% loss),', '', '✘ Kenapa gagal? ' + alasan);
          tulis(log); paket.hidden = true; sibuk = false; $('btn-ping').disabled = false;
        });
        return;
      }
      var pasangan = [dari.id, ke.id].sort().join('-'), arp = !sudahArp[pasangan]; sudahArp[pasangan] = true;
      gerak(titik, 'kirim').then(function () { return gerak(titik.slice().reverse(), 'balas'); }).then(function () {
        var diterima = arp ? 3 : 4;
        if (arp) log.push('Request timed out.');
        for (var i = 0; i < diterima; i++) log.push('Reply from ' + ke.ip + ': bytes=32 time<1ms TTL=' + (ke.type === 'router' ? 255 : 128));
        log.push('', 'Ping statistics for ' + ke.ip + ':', '    Packets: Sent = 4, Received = ' + diterima + ', Lost = ' + (4 - diterima) + ' (' + (arp ? 25 : 0) + '% loss),', '');
        log.push('✔ Berhasil! Paket lewat ' + titik.length + ' perangkat: ' + rute.map(function (n) { return n.name; }).join(' → ') + ' lalu balasan kembali.');
        if (arp) log.push('  Paket pertama "timed out" karena perangkat masih saling kenalan (ARP). Ini normal; kirim lagi, hasilnya 4 Reply.');
        tulis(log); paket.hidden = true; sibuk = false; $('btn-ping').disabled = false;
        var h = cekTopologi();
        if (h.t1 && !prog().ping) { setProg({ ping: true, t1: true }); pesan('✅ Ping berhasil! Tantangan uji ping selesai.', 'ok'); }
        else if (!h.t1) pesan('Ping berhasil, tetapi topologimu belum lolos pemeriksa. Tekan <b>Periksa topologi</b> dan perbaiki dulu.', 'warn');
        segarkanTantangan();
      });
    });

    $('btn-finish-topo').addEventListener('click', function () { setProg({ m3: true }); window.location.href = 'hasil.html'; });

    /* --- muat susunan tersimpan --- */
    var tersimpan = muat().topo;
    if (tersimpan && tersimpan.nodes) {
      var r0 = ukuran();
      tersimpan.nodes.forEach(function (d) { tambahNode(d.type, d.fx * r0.width, d.fy * r0.height, d); });
      tersimpan.conns.forEach(function (c) { if (byId(c[0]) && byId(c[1])) tambahKabel(c[0], c[1]); });
    }
    isiPilihanPing(); segarkanTantangan();
    if (!nodes.length) pesan('Mulai dengan klik <b>PC</b> atau <b>Switch</b> di panel kiri, lalu klik kanvas untuk menaruhnya.', 'ok');

    window.addEventListener('resize', function () {
      var r = ukuran();
      nodes.forEach(function (n) { n.x = Math.max(40, Math.min(r.width - 40, n.x)); n.y = Math.max(36, Math.min(r.height - 44, n.y)); n.el.style.left = n.x + 'px'; n.el.style.top = n.y + 'px'; });
      gambarKabel();
    });
  }

  /* ============================================================ HASIL */
  if (halaman === 'hasil') {
    var ph = prog();
    if (!ph.m3) { window.location.replace('index.html'); return; }
    var s = skor(ph);
    $('result-final').textContent = String(s.total);
    $('hasil-judul').textContent = s.total >= 85 ? 'Luar biasa! Kamu siap praktik.' : s.total >= 65 ? 'Bagus! Kamu siap praktik.' : 'Selesai! Baca lagi bagian yang masih salah sebelum praktik.';
    try { if (window.parent && window.parent !== window) window.parent.postMessage({ type: 'mikronet:hasil', skor: s.total, skorMaks: 100 }, '*'); } catch (e) {}
    var baris = [
      ['Misi 1 · IP address (' + (ph.m1 ? ph.m1.benar : 0) + '/5 benar)', s.m1 + ' / 20'],
      ['Misi 2 · Subnetting (' + (ph.m2 ? ph.m2.benar : 0) + '/5 benar)', s.m2 + ' / 30'],
      ['Tantangan 1 · LAN 2 PC + switch', s.t1 + ' / 20'],
      ['Uji ping', s.ping + ' / 15'],
      ['Bonus · Router sebagai gateway', s.t2 + ' / 15']
    ];
    var ul = $('result-breakdown');
    baris.forEach(function (b) { var li = document.createElement('li'); li.innerHTML = '<span>' + esc(b[0]) + '</span><span class="result-value">' + b[1] + '</span>'; ul.appendChild(li); });
    if (!ph.t2) { var li = document.createElement('li'); li.className = 'saran'; li.innerHTML = '<span>Mau skor penuh? Kembali ke Misi 3 dan coba <b>bonus router</b>.</span><a class="btn btn-ghost" href="topologi.html">Ke Misi 3</a>'; ul.appendChild(li); }
  }
});
