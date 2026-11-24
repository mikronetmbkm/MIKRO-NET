/* Mikronet — Simulasi Pertemuan 2 (multi-halaman): Cisco lebih dalam.
   Misi 1: VLAN & trunk · Misi 2: router-on-a-stick & DHCP · Misi 3: VLSM & static route.
   Skor akhir dilaporkan ke MIKRO-NET: parent.postMessage({type:'mikronet:hasil', skor, skorMaks:100}, '*'). */
document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var $ = function (id) { return document.getElementById(id); };
  var halaman = document.body.getAttribute('data-halaman') || '';

  /* ---------- Penyimpanan ----------
     Di dalam iframe bersandbox, localStorage tidak dipakai. Progres disimpan di window.name (bertahan antarhalaman
     dalam satu iframe), dengan localStorage sebagai cadangan bila game dibuka langsung di browser. */
  var KUNCI = 'mikronet_p2_v1';
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
  function ambil(k) { return muat()[k]; }
  function taruh(k, v) { var d = muat(); d[k] = v; simpan(d); }
  function prog() { var d = muat(); return d.prog || {}; }
  function setProg(patch) {
    var d = muat(); d.prog = d.prog || {};
    for (var k in patch) if (Object.prototype.hasOwnProperty.call(patch, k)) d.prog[k] = patch[k];
    simpan(d); segarkanHeader();
  }
  function reset() { simpan({}); }

  /* ---------- Skor ----------
     Misi 1: tugas 15 (3 × 5) + kuis 5 × 3 = 15 · Misi 2: tugas 15 (3 × 5) + kuis 5 × 3 = 15
     Misi 3: tugas 20 (4 × 5) + kuis 5 × 4 = 20 · Total 100. */
  var TUGAS = {
    m1: ['m1_pc1', 'm1_pc2', 'm1_trunk'],
    m2: ['m2_sub', 'm2_dora', 'm2_dhcp'],
    m3: ['m3_vlsm1', 'm3_vlsm2', 'm3_rute', 'm3_ping']
  };
  var NAMA_TUGAS = {
    m1_pc1: 'PC1 ke VLAN 10', m1_pc2: 'PC2 ke VLAN 20', m1_trunk: 'Fa0/1 trunk',
    m2_sub: 'subinterface R1', m2_dora: 'urutan DORA', m2_dhcp: 'pool DHCP',
    m3_vlsm1: 'VLSM latihan 1', m3_vlsm2: 'VLSM latihan 2', m3_rute: 'static route R1 & R2', m3_ping: 'ping dua arah'
  };
  var POIN_TUGAS = 5, POIN_SOAL = { m1: 3, m2: 3, m3: 4 };
  var CHECKPOINT = TUGAS.m1.concat(['m1_kuis'], TUGAS.m2, ['m2_kuis'], TUGAS.m3, ['m3_kuis']);

  function skor(p) {
    var r = { total: 0 };
    ['m1', 'm2', 'm3'].forEach(function (m) {
      var t = 0, n = 0;
      TUGAS[m].forEach(function (k) { if (p[k]) { t += POIN_TUGAS; n++; } });
      var benar = p[m + '_kuis'] ? p[m + '_kuis'].benar : 0;
      r[m] = { tugas: t, nTugas: n, maksTugas: TUGAS[m].length * POIN_TUGAS, kuis: benar * POIN_SOAL[m], benar: benar, maksKuis: 5 * POIN_SOAL[m] };
      r.total += t + benar * POIN_SOAL[m];
    });
    r.total = Math.max(0, Math.min(100, Math.round(r.total)));
    return r;
  }
  function segarkanHeader() {
    var p = prog();
    var pct = Math.round(CHECKPOINT.filter(function (k) { return !!p[k]; }).length / CHECKPOINT.length * 100);
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

  /* ---------- Utilitas ---------- */
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
    if (s === '') return 'Masih kosong.';
    var bagian = s.split('.');
    if (bagian.length !== 4) return 'Harus 4 angka yang dipisah titik (sekarang ' + bagian.length + ' bagian).';
    for (var i = 0; i < 4; i++) {
      if (!/^\d{1,3}$/.test(bagian[i].trim())) return 'Bagian ke-' + (i + 1) + ' bukan angka 0–255.';
      if (parseInt(bagian[i], 10) > 255) return 'Bagian ke-' + (i + 1) + ' lebih dari 255.';
    }
    return '';
  }
  function ipKeInt(s) { var o = typeof s === 'string' ? parseIP(s) : s; if (!o) return null; return ((o[0] * 256 + o[1]) * 256 + o[2]) * 256 + o[3]; }
  function intKeIP(n) { return [Math.floor(n / 16777216) % 256, Math.floor(n / 65536) % 256, Math.floor(n / 256) % 256, n % 256].join('.'); }
  function prefixKeMask(p) { var m = []; for (var i = 0; i < 4; i++) { var b = Math.max(0, Math.min(8, p - i * 8)); m.push(256 - Math.pow(2, 8 - b)); } return m.join('.'); }
  function maskKePrefix(mask) { for (var p = 0; p <= 32; p++) if (prefixKeMask(p) === mask) return p; return -1; }
  function dalamJaringan(ip, net, p) { var blok = Math.pow(2, 32 - p); return Math.floor(ipKeInt(ip) / blok) === Math.floor(ipKeInt(net) / blok); }
  function samaIP(a, b) { var x = ipKeInt(String(a || '')), y = ipKeInt(String(b || '')); return x !== null && x === y; }
  function esc(s) { return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function acak(arr) { var a = arr.slice(); for (var i = a.length - 1; i > 0; i--) { var j = Math.floor(Math.random() * (i + 1)); var t = a[i]; a[i] = a[j]; a[j] = t; } return a; }
  function jeda(ms) { return new Promise(function (r) { setTimeout(r, ms); }); }

  /* ---------- Ikon perangkat ---------- */
  var IKON = {
    pc: '<svg viewBox="0 0 64 64" aria-hidden="true"><rect x="8" y="14" width="48" height="32" rx="6" fill="#0B3B7B"/><rect x="14" y="20" width="36" height="22" rx="3" fill="#009BFF"/><circle cx="52" cy="16" r="2.5" fill="#F7B217"/><rect x="24" y="48" width="16" height="3" rx="1.5" fill="#003B7A"/><rect x="18" y="51" width="28" height="3" rx="1.5" fill="#0B3B7B"/></svg>',
    sw: '<svg viewBox="0 0 64 64" aria-hidden="true"><rect x="6" y="22" width="52" height="20" rx="4" fill="#0B3B7B"/><rect x="11" y="28" width="6" height="5" rx="1" fill="#009BFF"/><rect x="20" y="28" width="6" height="5" rx="1" fill="#009BFF"/><rect x="29" y="28" width="6" height="5" rx="1" fill="#009BFF"/><rect x="38" y="28" width="6" height="5" rx="1" fill="#009BFF"/><rect x="47" y="28" width="6" height="5" rx="1" fill="#F7B217"/></svg>',
    router: '<svg viewBox="0 0 64 64" aria-hidden="true"><circle cx="32" cy="32" r="24" fill="#0B3B7B"/><path d="M32 14v14M27 19l5-5 5 5M32 50V36M27 45l5 5 5-5M14 32h14M19 27l-5 5 5 5M50 32H36M45 27l5 5-5 5" stroke="#F7B217" stroke-width="3" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>'
  };

  /* ---------- Panggung animasi (diagram kecil + paket yang bergerak) ----------
     Posisi node dalam persen, jadi diagram ikut mengecil di HP. Area panggung tidak menahan sentuhan (halaman tetap bisa digulir). */
  function panggung(el, cfg) {
    var h = '<svg class="pg-garis" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">';
    cfg.links.forEach(function (l) {
      var a = cfg.nodes[l[0]], b = cfg.nodes[l[1]];
      h += '<line x1="' + a.x + '" y1="' + a.y + '" x2="' + b.x + '" y2="' + b.y + '" class="' + (l[2] || '') + '" vector-effect="non-scaling-stroke"/>';
    });
    h += '</svg>';
    (cfg.labels || []).forEach(function (t) { h += '<span class="pg-label ' + (t.k || '') + '" style="left:' + t.x + '%;top:' + t.y + '%">' + t.t + '</span>'; });
    Object.keys(cfg.nodes).forEach(function (id) {
      var n = cfg.nodes[id];
      h += '<div class="pg-node' + (n.sisi ? ' sisi-' + n.sisi : '') + '" data-node="' + id + '" style="left:' + n.x + '%;top:' + n.y + '%">' + IKON[n.ikon] + '<span class="pg-nama"><b>' + id + '</b>' + (n.ket ? '<small>' + n.ket + '</small>' : '') + '</span></div>';
    });
    h += '<div class="pg-paket" hidden><span class="pg-tag"></span></div>';
    el.innerHTML = h;
    var pk = el.querySelector('.pg-paket');
    return {
      paket: pk,
      titik: function (id, dx, dy) { var n = cfg.nodes[id]; return { x: n.x + (dx || 0), y: n.y + (dy || 0) }; },
      node: function (id) { return el.querySelector('[data-node="' + id + '"]'); },
      bersih: function () {
        el.querySelectorAll('.pg-node').forEach(function (n) { n.classList.remove('gagal', 'sampai', 'aktif'); });
        pk.hidden = true;
      }
    };
  }
  function letakkan(pk, t) { pk.hidden = false; pk.style.transition = 'none'; pk.style.left = t.x + '%'; pk.style.top = t.y + '%'; void pk.offsetWidth; }
  function geser(pk, t, ms) {
    ms = ms || 650;
    pk.style.transition = 'left ' + ms + 'ms ease-in-out, top ' + ms + 'ms ease-in-out';
    pk.style.left = t.x + '%'; pk.style.top = t.y + '%';
    return jeda(ms + 40);
  }
  function labelPaket(pk, teks, kelas) { pk.className = 'pg-paket ' + (kelas || ''); pk.querySelector('.pg-tag').textContent = teks || ''; pk.querySelector('.pg-tag').hidden = !teks; }

  /* ---------- Bank soal (5 soal diambil acak per misi) ---------- */
  /*BANK-MULAI*/
  var BANK = {
    m1: [
      { q: 'Tanpa VLAN, PC1 mengirim <b>broadcast</b> di sebuah switch. Siapa yang menerimanya?', opsi: ['Hanya PC tujuan', 'Semua perangkat di switch itu, karena satu broadcast domain', 'Tidak ada yang menerima', 'Hanya router'], benar: 1,
        jelas: 'Tanpa VLAN, semua port ada di satu broadcast domain. Broadcast dari satu PC sampai ke semua perangkat di switch itu.' },
      { q: 'Port switch yang tersambung ke <b>PC</b> sebaiknya diatur sebagai…', opsi: ['switchport mode trunk', 'shutdown', 'switchport mode access', 'encapsulation dot1Q'], benar: 2,
        jelas: 'Port access membawa satu VLAN dan cocok untuk perangkat akhir seperti PC dan printer. Trunk dipakai ke switch lain atau router.' },
      { q: 'Apa fungsi <b>tag 802.1Q</b> (dot1Q) pada kabel trunk?', opsi: ['Mengenkripsi data', 'Memberi IP ke PC', 'Mempercepat kabel', 'Menandai frame dengan VLAN ID supaya penerima tahu frame itu milik VLAN mana'], benar: 3,
        jelas: 'Saat frame keluar lewat trunk, switch menempelkan tag berisi VLAN ID. Di port access, tag dilepas lagi sehingga PC tidak pernah melihatnya.' },
      { q: 'Port switch Cisco yang belum diatur sama sekali berada di VLAN…', opsi: ['VLAN 1', 'VLAN 0', 'VLAN 10', 'VLAN 4094'], benar: 0,
        jelas: 'VLAN 1 adalah VLAN bawaan (default). Semua port awalnya ada di VLAN 1. Itu sebabnya port PC harus dipindahkan ke VLAN yang benar.' },
      { q: 'Rentang <b>VLAN ID</b> yang bisa dipakai adalah…', opsi: ['0–255', '1–4094', '1–1024', '1–65535'], benar: 1,
        jelas: 'VLAN ID memakai 12 bit: 1 sampai 4094 (0 dan 4095 dicadangkan).' },
      { q: 'PC1 (VLAN 10) dan PC2 (VLAN 20) ada di switch yang sama, tetapi tidak bisa saling ping. Mengapa?', opsi: ['Kabelnya pasti rusak', 'IP kedua PC sama', 'VLAN berbeda berarti jaringan berbeda; switch Layer 2 tidak menyeberangkan antar-VLAN, jadi butuh router', 'Switch kekurangan port'], benar: 2,
        jelas: 'Setiap VLAN adalah jaringan (subnet) terpisah. Untuk menyeberang antar-VLAN dibutuhkan router, misalnya dengan router-on-a-stick.' },
      { q: 'Perintah untuk melihat daftar VLAN beserta port anggotanya adalah…', opsi: ['show vlan brief', 'show ip route', 'show ip dhcp binding', 'ping'], benar: 0,
        jelas: '<span class="mono">show vlan brief</span> menampilkan VLAN ID, nama, status, dan port access anggotanya.' },
      { q: 'Pada <b>router-on-a-stick</b>, port switch yang menuju router harus diatur sebagai…', opsi: ['access VLAN 10', 'access VLAN 1', 'dimatikan (shutdown)', 'trunk'], benar: 3,
        jelas: 'Satu kabel ke router harus membawa banyak VLAN sekaligus. Itu tugas port trunk.' }
    ],
    m2: [
      { q: 'Urutan pesan DHCP yang benar adalah…', opsi: ['Discover → Request → Offer → Acknowledge', 'Discover → Offer → Request → Acknowledge', 'Offer → Discover → Request → Acknowledge', 'Request → Offer → Discover → Acknowledge'], benar: 1,
        jelas: 'DORA: PC mencari server (Discover), server menawarkan (Offer), PC meminta (Request), server menegaskan (Acknowledge).' },
      { q: 'Apa tujuan perintah <span class="mono">ip dhcp excluded-address 192.168.20.1 192.168.20.5</span>?', opsi: ['Memblokir PC .1–.5 dari internet', 'Membuat 5 pool baru', 'Mencegah alamat .1–.5 dibagikan DHCP karena dipakai perangkat ber-IP tetap, misalnya gateway', 'Mengatur DNS untuk PC'], benar: 2,
        jelas: 'Alamat yang dikecualikan tidak akan dipinjamkan. Tanpa ini, IP gateway bisa dibagikan ke PC dan terjadi IP conflict.' },
      { q: 'PC2 mendapat IP <span class="mono">169.254.37.112</span>. Artinya…', opsi: ['PC2 gagal menghubungi DHCP server (APIPA); cek kabel, VLAN, trunk, dan subinterface', 'DHCP berhasil', 'PC2 mendapat IP publik', 'DNS server salah'], benar: 0,
        jelas: '169.254.x.x adalah APIPA: alamat cadangan yang dibuat PC sendiri saat tidak ada jawaban dari DHCP server.' },
      { q: 'Subinterface Gi0/0.20 melayani VLAN 20. Perintah encapsulation yang benar adalah…', opsi: ['encapsulation dot1Q 1', 'encapsulation dot1Q 192.168.20.1', 'encapsulation vlan', 'encapsulation dot1Q 20'], benar: 3,
        jelas: 'Angka pada encapsulation dot1Q harus sama dengan VLAN ID di switch. Kalau beda, frame bertag 20 tidak punya “pintu” di router.' },
      { q: 'PC1 (VLAN 10) memakai default gateway <span class="mono">192.168.10.1</span>. Alamat itu harus menjadi IP dari…', opsi: ['PC2', 'subinterface Gi0/0.10 di R1', 'port Fa0/1 di SW1', 'DNS server'], benar: 1,
        jelas: 'IP subinterface adalah gateway untuk VLAN-nya. Gi0/0.10 = 192.168.10.1 menjadi pintu keluar PC di VLAN 10.' },
      { q: 'Semua subinterface R1 sudah benar, tetapi statusnya <b>down</b> dan ping gagal. Penyebab paling mungkin…', opsi: ['Lupa <span class="mono">no shutdown</span> di interface fisik g0/0', 'VLAN ID terlalu besar', 'DNS salah', 'PC belum di-restart'], benar: 0,
        jelas: 'Interface router mati secara bawaan. Kalau g0/0 (fisik) masih shutdown, semua subinterface di atasnya ikut mati.' },
      { q: 'Perangkat mana yang sebaiknya memakai <b>IP statis</b> (manual)?', opsi: ['Laptop tamu', 'HP siswa', 'Semua PC siswa', 'Router, server, dan printer'], benar: 3,
        jelas: 'Perangkat yang alamatnya harus tetap (router, server, printer) memakai IP statis. PC siswa, laptop, dan HP cocok memakai DHCP.' },
      { q: 'Perintah di router untuk melihat IP yang sudah dipinjamkan DHCP beserta MAC-nya adalah…', opsi: ['show vlan brief', 'show ip dhcp binding', 'show ip route', 'ip dhcp pool'], benar: 1,
        jelas: '<span class="mono">show ip dhcp binding</span> menampilkan daftar IP yang sedang dipinjamkan dan alamat MAC peminjamnya.' }
    ],
    m3: [
      { q: 'Sebuah segmen butuh <b>20 host</b>. Prefix VLSM yang paling hemat adalah…', opsi: ['/26', '/27', '/28', '/29'], benar: 1,
        jelas: '20 + 2 = 22. Blok terkecil yang muat adalah 32, yaitu /27 (30 host). /28 hanya 14 host, kurang.' },
      { q: 'Mengapa link antar-router biasanya memakai <b>/30</b>?', opsi: ['/30 paling cepat', '/30 wajib di Cisco', 'Link hanya butuh 2 host; /30 memberi tepat 2 host sehingga tidak boros', 'Supaya bisa memakai DHCP'], benar: 2,
        jelas: 'Blok /30 berisi 4 alamat: network, 2 host (R1 dan R2), dan broadcast. Pas untuk link titik-ke-titik.' },
      { q: 'Rumus jumlah host valid jika ada <b>n</b> bit host adalah…', opsi: ['2<sup>n</sup>', '2 × n − 2', 'n<sup>2</sup>', '2<sup>n</sup> − 2'], benar: 3,
        jelas: '2<sup>n</sup> alamat dikurangi 2: alamat pertama untuk network address dan alamat terakhir untuk broadcast address.' },
      { q: 'Dalam VLSM, segmen dialokasikan mulai dari…', opsi: ['kebutuhan host terbesar', 'kebutuhan host terkecil', 'urutan abjad nama ruangan', 'acak saja'], benar: 0,
        jelas: 'Mulai dari blok terbesar supaya setiap blok tetap sejajar batasnya dan tidak tumpang tindih.' },
      { q: 'Format perintah static route yang benar adalah…', opsi: ['ip route &lt;next-hop&gt; &lt;network&gt; &lt;mask&gt;', 'ip route &lt;network-tujuan&gt; &lt;subnet-mask&gt; &lt;next-hop&gt;', 'route add &lt;network&gt;', 'ip static &lt;network&gt; &lt;next-hop&gt;'], benar: 1,
        jelas: 'Contoh: <span class="mono">ip route 192.168.30.0 255.255.255.0 192.168.100.2</span>: tujuan, mask tujuan, lalu router berikutnya.' },
      { q: 'R1 (Gi0/1 = 192.168.100.1/30) ingin menuju 192.168.30.0/24 lewat R2. Next-hop yang benar adalah…', opsi: ['192.168.100.1', '192.168.30.1', '192.168.100.2', '0.0.0.0'], benar: 2,
        jelas: 'Next-hop = IP router tetangga yang terhubung langsung, yaitu Gi0/1 milik R2 (192.168.100.2). 192.168.100.1 adalah IP R1 sendiri.' },
      { q: 'R2 hanya punya satu jalan keluar, yaitu ke R1. Rute paling praktis untuk R2 adalah…', opsi: ['ip route 0.0.0.0 0.0.0.0 192.168.100.1', 'ip route 192.168.30.0 255.255.255.0 192.168.100.2', 'Tidak perlu rute apa pun', 'ip route 0.0.0.0 255.255.255.255 192.168.100.2'], benar: 0,
        jelas: 'Router ujung (stub) cukup memakai default route 0.0.0.0/0: semua tujuan yang tidak dikenal dikirim ke R1 (192.168.100.1).' },
      { q: 'R1 sudah punya rute ke 192.168.30.0, tetapi ping PC1 → PC3 tetap <b>Request timed out</b>. Penyebab paling mungkin…', opsi: ['Mask PC1 salah', 'R2 belum punya rute kembali ke 192.168.10.0', 'R1 butuh no shutdown lagi', 'PC3 memakai DHCP'], benar: 1,
        jelas: 'Ping butuh jalan pergi dan pulang. Permintaan sampai di PC3, tetapi balasannya dibuang di R2 karena R2 tidak tahu jalan ke 192.168.10.0.' },
      { q: 'Pada <span class="mono">show ip route</span>, kode <b>C</b> dan <b>S</b> artinya…', opsi: ['C = Cisco, S = switch', 'C = client, S = server', 'C = terhubung langsung (connected), S = rute statis yang ditulis manual', 'C = cepat, S = lambat'], benar: 2,
        jelas: 'C = jaringan yang menempel langsung di interface router. S = static route. S* = static default route.' }
    ]
  };
  /*BANK-SELESAI*/

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
    wadah.innerHTML = '<div class="feedback show feedback-correct"><span aria-hidden="true">✅</span><div><strong>Kuis ini sudah selesai: ' + info.benar + ' dari 5 benar.</strong>Kamu boleh mengulang kuisnya untuk memperbaiki skor.</div></div>';
    var b = document.createElement('button'); b.type = 'button'; b.className = 'btn btn-ghost'; b.style.marginTop = '12px'; b.textContent = 'Ulangi kuis';
    b.addEventListener('click', ulang); wadah.appendChild(b);
  }
  // Pasang kuis misi m: 5 soal acak dari BANK[m]; urutan acak disimpan supaya soalnya tetap sama setelah dimuat ulang.
  function pasangKuis(m, setelah) {
    var bank = BANK[m], wadah = $(m + '-soal'), skorEl = $(m + '-skor');
    function mulai() {
      var lama = kuisData(m), urut = lama && lama.urut;
      if (!urut || urut.length !== 5 || urut.some(function (x) { return !bank[x]; })) urut = acak(bank.map(function (_, i) { return i; })).slice(0, 5);
      if (!lama || lama.urut !== urut) kuisTulis(m, { urut: urut, jawab: lama && lama.urut === urut ? lama.jawab : {} });
      kuis(wadah, urut.map(function (x) { return bank[x]; }), skorEl, function (benar) {
        var lamaP = prog()[m + '_kuis'], patch = {};
        patch[m + '_kuis'] = { benar: Math.max(benar, lamaP ? lamaP.benar : 0) };
        setProg(patch); setelah();
      }, m, urut);
    }
    var p = prog();
    if (p[m + '_kuis']) { skorEl.textContent = p[m + '_kuis'].benar + '/5'; kuisSudah(wadah, p[m + '_kuis'], function () { kuisTulis(m, null); mulai(); }); }
    else mulai();
  }

  /* ---------- Bagian umum halaman misi ---------- */
  function centangTugas() {
    var p = prog();
    document.querySelectorAll('[data-tugas]').forEach(function (li) { li.classList.toggle('beres', !!p[li.getAttribute('data-tugas')]); });
  }
  // Tombol "Selesai" aktif bila semua tugas + kuis misi m beres. Status misi (p[m]) diset otomatis.
  function segarkanSelesai(m) {
    var p = prog(), kurang = [];
    TUGAS[m].forEach(function (k) { if (!p[k]) kurang.push(NAMA_TUGAS[k]); });
    if (!p[m + '_kuis']) kurang.push('kuis 5 soal');
    var ok = kurang.length === 0;
    if (ok && !p[m]) { var patch = {}; patch[m] = true; setProg(patch); }
    var tombol = $('btn-' + m + '-selesai'), syarat = $('syarat-' + m);
    if (tombol) tombol.disabled = !ok;
    if (syarat) syarat.textContent = ok ? 'Misi selesai! Tekan tombol di atas untuk lanjut.' : 'Untuk menyelesaikan misi ini: ' + kurang.join(', ') + '.';
    centangTugas();
    return ok;
  }
  // Misi bisa dibuka langsung (tanpa lewat beranda). Kalau misi sebelumnya belum selesai, cukup beri pengingat.
  function bannerUrut(prasyarat, nama, href) {
    var b = $('banner-urut');
    if (!b || prog()[prasyarat]) return;
    b.hidden = false;
    b.innerHTML = '<b>' + nama + ' belum selesai.</b> Misi ini tetap bisa dicoba, tetapi sebaiknya dikerjakan berurutan. <a href="' + href + '">Buka ' + nama + '</a>';
  }
  function isiCek(ul, daftar) {
    ul.innerHTML = '';
    daftar.forEach(function (x) { var li = document.createElement('li'); li.className = x[0]; li.innerHTML = x[1]; ul.appendChild(li); });
  }

  /* ============================================================ BERANDA */
  if (halaman === 'beranda') {
    var p = prog(), d0 = muat();
    var kartu = [
      { id: 'm1', buka: true, mulai: d0.sw1 || d0.kuis && d0.kuis.m1 },
      { id: 'm2', buka: !!p.m1, mulai: d0.r1 || d0.dora || (d0.kuis && d0.kuis.m2) },
      { id: 'm3', buka: !!p.m2, mulai: d0.rute || d0.vlsm1 || (d0.kuis && d0.kuis.m3) }
    ];
    kartu.forEach(function (k) {
      var btn = $('btn-' + k.id), st = $('status-' + k.id), card = $('card-' + k.id);
      var nT = TUGAS[k.id].filter(function (t) { return p[t]; }).length;
      var info = 'Tugas ' + nT + '/' + TUGAS[k.id].length + (p[k.id + '_kuis'] ? ' · Kuis ' + p[k.id + '_kuis'].benar + '/5' : '');
      if (k.buka) {
        card.classList.remove('locked'); btn.classList.remove('btn-lock'); btn.classList.add('btn-primary'); btn.removeAttribute('aria-disabled');
        btn.textContent = p[k.id] ? 'Buka lagi' : (k.mulai || nT ? 'Lanjutkan' : 'Mulai');
        st.innerHTML = p[k.id] ? '<span class="done-badge">Selesai ✓</span> ' + esc(info) : (k.mulai || nT ? esc(info) : 'Siap dikerjakan');
      } else {
        btn.addEventListener('click', function (e) { e.preventDefault(); });
      }
    });
    if (p.m1 && p.m2 && p.m3) $('btn-hasil').hidden = false;
  }

  /* ============================================================ MISI 1: VLAN & TRUNK */
  if (halaman === 'vlan') {
    var PORTS = [
      { id: 'fa0/1', nama: 'Fa0/1', ke: 'R1', jenis: 'router', ket: 'Gi0/0', atas: true },
      { id: 'fa0/2', nama: 'Fa0/2', ke: 'PC1', jenis: 'pc', ket: '192.168.10.2' },
      { id: 'fa0/3', nama: 'Fa0/3', ke: 'PC2', jenis: 'pc', ket: 'DHCP' },
      { id: 'fa0/4', nama: 'Fa0/4', ke: null },
      { id: 'fa0/5', nama: 'Fa0/5', ke: null }
    ];
    var NAMA_VLAN = { '1': 'default', '10': 'LAB-BAWAH', '20': 'LAB-ATAS' };
    var PILIHAN_PORT = [
      { v: '1', t: 'Access · VLAN 1', s: 'bawaan (default)' },
      { v: '10', t: 'Access · VLAN 10', s: 'LAB-BAWAH' },
      { v: '20', t: 'Access · VLAN 20', s: 'LAB-ATAS' },
      { v: 'trunk', t: 'Trunk', s: 'banyak VLAN, tag 802.1Q' }
    ];
    var sw = ambil('sw1') || {};
    var dipilih = null, sibukB = false;
    function nilaiPort(id) { return sw[id] || '1'; }
    function kelasPort(v) { return v === 'trunk' ? 'trunk' : 'v' + v; }
    function teksPort(v) { return v === 'trunk' ? 'Trunk' : 'VLAN ' + v; }
    function portById(id) { for (var i = 0; i < PORTS.length; i++) if (PORTS[i].id === id) return PORTS[i]; return null; }

    function renderSw() {
      var atas = $('sw-atas'), bawah = $('sw-bawah'), ports = $('sw-ports');
      atas.innerHTML = ''; bawah.innerHTML = ''; ports.innerHTML = '';
      PORTS.forEach(function (p, i) {
        var v = nilaiPort(p.id), k = kelasPort(v);
        var selA = document.createElement('div'), selB = document.createElement('div');
        selA.className = 'sw-sel'; selB.className = 'sw-sel';
        if (p.ke) {
          var dev = '<div class="dev" id="dev-' + p.ke + '">' + IKON[p.jenis] + '<b>' + p.ke + '</b><small class="mono">' + p.ket + '</small><em class="dev-status" id="st-' + p.ke + '"></em></div>';
          var kabel = '<div class="kabel-v ' + k + '" id="kabel-' + (i + 1) + '"></div>';
          if (p.atas) { selA.innerHTML = dev + kabel; } else { selB.innerHTML = kabel + dev; }
        } else {
          selB.innerHTML = '<span class="sw-kosong">kosong</span>';
        }
        atas.appendChild(selA); bawah.appendChild(selB);
        var b = document.createElement('button');
        b.type = 'button'; b.className = 'port-btn ' + k + (dipilih === p.id ? ' dipilih' : '');
        b.id = 'port-' + (i + 1);
        b.setAttribute('aria-pressed', dipilih === p.id ? 'true' : 'false');
        b.setAttribute('aria-label', 'Port ' + p.nama + (p.ke ? ', ke ' + p.ke : ', kosong') + ', ' + (v === 'trunk' ? 'mode trunk' : 'access VLAN ' + v));
        b.innerHTML = '<b>' + p.nama + '</b><span>' + (v === 'trunk' ? 'Trunk' : 'V' + v) + '</span>';
        b.addEventListener('click', function () { dipilih = p.id; renderSemua(); fokus('port-' + (i + 1)); });
        ports.appendChild(b);
      });
    }
    function renderPanel() {
      var pp = $('port-panel');
      if (!dipilih) { pp.innerHTML = '<p class="section-note">' + (Object.keys(sw).length ? 'Ketuk salah satu port di SW1 untuk melihat atau mengubah pengaturannya.' : 'Belum ada port yang dipilih. Ketuk <b>Fa0/2</b> (PC1) untuk mulai.') + '</p>'; return; }
      var p = portById(dipilih), v = nilaiPort(p.id);
      pp.innerHTML = '<p class="port-judul">Port <b>' + p.nama + '</b> · ' + (p.ke ? 'tersambung ke <b>' + p.ke + '</b>' : 'tidak tersambung ke perangkat') + '</p>';
      var grup = document.createElement('div'); grup.className = 'opsi-port'; grup.setAttribute('role', 'radiogroup'); grup.setAttribute('aria-label', 'Pengaturan port ' + p.nama);
      PILIHAN_PORT.forEach(function (o) {
        var b = document.createElement('button'); b.type = 'button'; b.className = 'opsi-port-btn ' + kelasPort(o.v) + (o.v === v ? ' aktif' : '');
        b.setAttribute('role', 'radio'); b.setAttribute('aria-checked', o.v === v ? 'true' : 'false');
        b.setAttribute('data-v', o.v);
        b.innerHTML = '<i aria-hidden="true"></i><b>' + o.t + '</b><span>' + o.s + '</span>';
        b.addEventListener('click', function () {
          if (o.v === '1') delete sw[p.id]; else sw[p.id] = o.v;
          taruh('sw1', sw);
          $('cek-m1').classList.add('usang');
          renderSemua();
          var akt = document.querySelector('.opsi-port-btn.aktif'); if (akt) akt.focus();
        });
        grup.appendChild(b);
      });
      pp.appendChild(grup);
      var ket = document.createElement('p'); ket.className = 'port-ket';
      ket.innerHTML = v === 'trunk' ? 'Trunk membawa semua VLAN lewat satu kabel; setiap frame diberi tag 802.1Q.' : 'Access hanya membawa <b>VLAN ' + v + '</b> (' + NAMA_VLAN[v] + '). Perangkat di port ini tidak melihat tag.';
      pp.appendChild(ket);
    }
    function renderCli() {
      var L = ['SW1# configure terminal', 'SW1(config)# vlan 10', 'SW1(config-vlan)# name LAB-BAWAH', 'SW1(config-vlan)# vlan 20', 'SW1(config-vlan)# name LAB-ATAS', 'SW1(config-vlan)# exit'];
      var ada = false;
      PORTS.forEach(function (p) {
        var v = nilaiPort(p.id);
        if (v === '1') return;
        ada = true;
        L.push('SW1(config)# interface ' + p.id);
        if (v === 'trunk') L.push('SW1(config-if)# switchport mode trunk');
        else { L.push('SW1(config-if)# switchport mode access'); L.push('SW1(config-if)# switchport access vlan ' + v); }
        L.push('SW1(config-if)# exit');
      });
      if (!ada) L.push('! Belum ada port yang diubah: semua port masih di VLAN 1.');
      L.push('SW1(config)# end');
      $('cli-sw1').textContent = L.join('\n');

      var anggota = { '1': [], '10': [], '20': [] }, trunk = [];
      PORTS.forEach(function (p) { var v = nilaiPort(p.id); if (v === 'trunk') trunk.push(p.nama); else anggota[v].push(p.nama); });
      $('vlan-brief').innerHTML = '<thead><tr><th>VLAN</th><th>Name</th><th>Status</th><th>Ports</th></tr></thead><tbody>' +
        ['1', '10', '20'].map(function (v) { return '<tr class="' + kelasPort(v) + '"><td>' + v + '</td><td>' + NAMA_VLAN[v] + '</td><td>active</td><td>' + (anggota[v].join(', ') || '&nbsp;') + '</td></tr>'; }).join('') + '</tbody>';
      $('cli-trunk').textContent = trunk.length
        ? 'Port   Mode  Status    Native\n' + trunk.map(function (t) { return (t + '      ').slice(0, 7) + 'on    trunking  1'; }).join('\n') + '\n\nPort   Vlans allowed on trunk\n' + trunk.map(function (t) { return (t + '      ').slice(0, 7) + '1-4094'; }).join('\n')
        : '(belum ada port trunk)';
    }
    function renderSemua() { renderSw(); renderPanel(); renderCli(); }
    function fokus(id) { var el = $(id); if (el) el.focus(); }

    /* --- uji broadcast --- */
    $('btn-broadcast').addEventListener('click', function () {
      if (sibukB) return;
      sibukB = true; var tombol = this; tombol.disabled = true;
      renderSw();
      var vPc1 = nilaiPort('fa0/2'), vlan = vPc1 === 'trunk' ? '1' : vPc1;
      var hasil = $('hasil-broadcast'); hasil.className = 'hasil-uji';
      hasil.textContent = 'PC1 mengirim broadcast…';
      $('dev-PC1').classList.add('kirim'); $('st-PC1').textContent = 'Mengirim';
      $('kabel-2').classList.add('aliran');
      var terima = [], tolak = [];
      jeda(700).then(function () {
        $('port-2').classList.add('lewat');
        return jeda(600);
      }).then(function () {
        PORTS.forEach(function (p, i) {
          if (!p.ke || p.ke === 'PC1') return;
          var v = nilaiPort(p.id), dapat, cat = '';
          if (v === 'trunk') {
            if (p.jenis === 'router') { dapat = true; cat = vlan === '1' ? 'tanpa tag (native VLAN 1)' : 'lewat trunk, bertag ' + vlan; }
            else { dapat = vlan === '1'; cat = dapat ? 'native VLAN 1' : 'frame bertag tidak dipahami PC'; }
          } else { dapat = v === vlan; cat = 'VLAN ' + v; }
          var dev = $('dev-' + p.ke), st = $('st-' + p.ke);
          $('port-' + (i + 1)).classList.add(dapat ? 'lewat' : 'tolak');
          if (dapat) { $('kabel-' + (i + 1)).classList.add('aliran'); dev.classList.add('terima'); st.textContent = 'Menerima'; terima.push(p.ke + ' (' + cat + ')'); }
          else { dev.classList.add('tolak'); st.textContent = 'Tidak menerima'; tolak.push(p.ke + ' (' + cat + ')'); }
        });
        var awal = vPc1 === 'trunk' ? 'Port PC1 diatur trunk. PC tidak memberi tag, jadi frame-nya masuk native VLAN 1. ' : '';
        hasil.innerHTML = awal + 'Broadcast PC1 (VLAN ' + vlan + ') <b>diterima</b>: ' + (terima.length ? esc(terima.join(', ')) : 'tidak ada') + '. <b>Tidak diterima</b>: ' + (tolak.length ? esc(tolak.join(', ')) : 'tidak ada') + '.' +
          (terima.some(function (t) { return t.indexOf('PC2') === 0; }) ? ' PC1 dan PC2 masih satu broadcast domain.' : ' VLAN memisahkan broadcast domain PC1 dan PC2.');
        return jeda(1600);
      }).then(function () {
        document.querySelectorAll('.kabel-v.aliran').forEach(function (k) { k.classList.remove('aliran'); });
        sibukB = false; tombol.disabled = false;
      });
    });

    /* --- pemeriksa --- */
    function periksaM1() {
      var daftar = [], patch = {}, p = prog();
      var v1 = nilaiPort('fa0/1'), v2 = nilaiPort('fa0/2'), v3 = nilaiPort('fa0/3');
      function cekPc(port, nama, target, kunci) {
        var v = nilaiPort(port);
        if (v === target) { daftar.push(['ok', port.replace('fa', 'Fa') + ' (' + nama + ') access <b>VLAN ' + target + '</b>.']); patch[kunci] = true; }
        else if (v === '1') daftar.push(['err', nama + ' masih di <b>VLAN 1</b> (bawaan). Pindahkan ' + port.replace('fa', 'Fa') + ' ke VLAN ' + target + '.']);
        else if (v === 'trunk') daftar.push(['err', port.replace('fa', 'Fa') + ' (' + nama + ') diatur <b>trunk</b>. Port ke PC harus <b>access</b>, lalu pilih VLAN ' + target + '.']);
        else daftar.push(['err', nama + ' ada di VLAN ' + v + ', seharusnya <b>VLAN ' + target + '</b> (' + NAMA_VLAN[target] + ').']);
      }
      cekPc('fa0/2', 'PC1', '10', 'm1_pc1');
      cekPc('fa0/3', 'PC2', '20', 'm1_pc2');
      if (v1 === 'trunk') { daftar.push(['ok', 'Fa0/1 (ke R1) mode <b>trunk</b>: VLAN 10 dan 20 bisa lewat satu kabel.']); patch.m1_trunk = true; }
      else daftar.push(['err', 'Port ke router (Fa0/1) masih <b>access VLAN ' + v1 + '</b>. Router-on-a-stick butuh <b>trunk</b> supaya VLAN 10 dan 20 lewat satu kabel.']);
      if (v2 !== 'trunk' && v2 === v3) daftar.push(['err', 'PC1 dan PC2 ada di VLAN yang sama (' + v2 + '), jadi masih satu broadcast domain.']);
      ['fa0/4', 'fa0/5'].forEach(function (id) {
        var v = nilaiPort(id);
        if (v === 'trunk') daftar.push(['info', id.replace('fa', 'Fa') + ' kosong tetapi diatur trunk. Port kosong sebaiknya tidak dijadikan trunk.']);
      });
      isiCek($('cek-m1'), daftar);
      $('cek-m1').classList.remove('usang');
      var baru = {}; Object.keys(patch).forEach(function (k) { if (!p[k]) baru[k] = true; });
      if (Object.keys(baru).length) setProg(baru);
      segarkanSelesai('m1');
    }
    $('btn-periksa-m1').addEventListener('click', periksaM1);
    duaKlik($('btn-reset-sw1'), 'Yakin? Klik lagi untuk mengembalikan', function () {
      sw = {}; taruh('sw1', sw); dipilih = null; renderSemua();
      isiCek($('cek-m1'), [['info', 'Semua port kembali ke VLAN 1 (bawaan).']]);
      $('hasil-broadcast').textContent = '';
    });

    renderSemua();
    pasangKuis('m1', function () { segarkanSelesai('m1'); });
    segarkanSelesai('m1');
    $('btn-m1-selesai').addEventListener('click', function () { window.location.href = 'dhcp.html'; });
  }

  /* ============================================================ MISI 2: ROUTER-ON-A-STICK & DHCP */
  if (halaman === 'dhcp') {
    bannerUrut('m1', 'Misi 1', 'vlan.html');
    var r1 = ambil('r1') || { noshut: false, enc10: '', ip10: '', enc20: '', ip20: '' };
    function simpanR1() { taruh('r1', r1); }

    /* --- Bagian A: konfigurasi R1 --- */
    function statusSub(v) {
      if (!r1.noshut) return { teks: 'administratively down', kelas: 'mati' };
      if (!r1['enc' + v] || !r1['ip' + v]) return { teks: 'belum lengkap', kelas: 'kuning' };
      return { teks: 'up', kelas: 'hidup' };
    }
    function renderR1() {
      var t = $('r1-noshut');
      t.setAttribute('aria-pressed', r1.noshut ? 'true' : 'false');
      t.classList.toggle('aktif', r1.noshut);
      ['enc10', 'ip10', 'enc20', 'ip20'].forEach(function (k) { $(k).value = r1[k]; });
      var g = $('lampu-g00');
      g.className = 'lampu ' + (r1.noshut ? 'hidup' : 'mati');
      g.textContent = 'Gi0/0: ' + (r1.noshut ? 'up' : 'administratively down');
      ['10', '20'].forEach(function (v) { var s = statusSub(v), l = $('lampu-' + v); l.className = 'lampu ' + s.kelas; l.textContent = 'Gi0/0.' + v + ': ' + s.teks; });
      var L = ['R1(config)# interface g0/0', 'R1(config-if)# no ip address', r1.noshut ? 'R1(config-if)# no shutdown' : '! (belum: no shutdown, Gi0/0 masih mati)'];
      ['10', '20'].forEach(function (v) {
        L.push((v === '10' ? 'R1(config-if)# ' : 'R1(config-subif)# ') + 'interface g0/0.' + v);
        L.push('R1(config-subif)# encapsulation dot1Q ' + (r1['enc' + v] || '?'));
        L.push('R1(config-subif)# ip address ' + (r1['ip' + v] || '?') + ' 255.255.255.0');
      });
      L.push('R1(config-subif)# end');
      $('cli-r1').textContent = L.join('\n');
      var baris = [['Gi0/0', 'unassigned', r1.noshut ? 'up' : 'administratively down', r1.noshut ? 'up' : 'down']];
      ['10', '20'].forEach(function (v) {
        var s = statusSub(v);
        baris.push(['Gi0/0.' + v, r1['ip' + v] || 'unassigned', r1.noshut ? 'up' : 'administratively down', s.kelas === 'hidup' ? 'up' : 'down']);
      });
      $('ip-brief').innerHTML = '<thead><tr><th>Interface</th><th>IP-Address</th><th>Status</th><th>Protocol</th></tr></thead><tbody>' +
        baris.map(function (b) { return '<tr><td>' + b[0] + '</td><td>' + b[1] + '</td><td class="' + (b[2] === 'up' ? 'st-up' : 'st-down') + '">' + b[2] + '</td><td class="' + (b[3] === 'up' ? 'st-up' : 'st-down') + '">' + b[3] + '</td></tr>'; }).join('') + '</tbody>';
    }
    $('r1-noshut').addEventListener('click', function () { r1.noshut = !r1.noshut; simpanR1(); renderR1(); $('cek-r1').classList.add('usang'); });
    ['enc10', 'ip10', 'enc20', 'ip20'].forEach(function (k) {
      $(k).addEventListener('change', function () { r1[k] = $(k).value; simpanR1(); renderR1(); $('cek-r1').classList.add('usang'); });
    });
    function masalahSub(v) {
      var m = [], enc = r1['enc' + v], ip = r1['ip' + v], gw = '192.168.' + v + '.1';
      if (!enc) m.push('Gi0/0.' + v + ': angka <span class="mono">encapsulation dot1Q</span> belum dipilih.');
      else if (enc !== v) m.push('Gi0/0.' + v + ': <span class="mono">encapsulation dot1Q ' + enc + '</span> salah. Angkanya harus sama dengan VLAN ID di switch: <b>' + v + '</b>.');
      if (!ip) m.push('Gi0/0.' + v + ': IP address belum dipilih.');
      else if (/\.0$/.test(ip)) m.push('Gi0/0.' + v + ': ' + ip + ' adalah <b>network address</b>. Tidak boleh dipakai interface.');
      else if (/\.255$/.test(ip)) m.push('Gi0/0.' + v + ': ' + ip + ' adalah <b>broadcast address</b>. Tidak boleh dipakai interface.');
      else if (ip !== gw) m.push('Gi0/0.' + v + ': ' + ip + ' bukan jaringan VLAN ' + v + '. PC di VLAN ' + v + ' memakai gateway <b>' + gw + '</b>, jadi IP subinterface harus ' + gw + '.');
      return m;
    }
    $('btn-periksa-r1').addEventListener('click', function () {
      var daftar = [];
      if (!r1.noshut) daftar.push(['err', 'Interface g0/0 masih <b>shutdown</b>. Tanpa <span class="mono">no shutdown</span>, semua subinterface ikut mati.']);
      else daftar.push(['ok', 'Gi0/0 sudah <b>no shutdown</b>.']);
      ['10', '20'].forEach(function (v) {
        var m = masalahSub(v);
        if (m.length) m.forEach(function (x) { daftar.push(['err', x]); });
        else daftar.push(['ok', 'Gi0/0.' + v + ': dot1Q ' + v + ', IP 192.168.' + v + '.1 (gateway VLAN ' + v + ').']);
      });
      isiCek($('cek-r1'), daftar); $('cek-r1').classList.remove('usang');
      if (daftar.every(function (x) { return x[0] === 'ok'; })) {
        if (!prog().m2_sub) setProg({ m2_sub: true });
        $('hasil-ros').textContent = 'R1 siap. Tekan “Jalankan ping PC1 → PC2” untuk melihat perjalanan paketnya.';
      }
      segarkanSelesai('m2');
    });

    /* --- animasi router-on-a-stick --- */
    var pgRos = panggung($('pg-ros'), {
      nodes: { R1: { x: 50, y: 15, ikon: 'router', ket: 'Gi0/0', sisi: 'kanan' }, SW1: { x: 50, y: 50, ikon: 'sw' }, PC1: { x: 15, y: 84, ikon: 'pc', ket: '.10.2' }, PC2: { x: 85, y: 84, ikon: 'pc', ket: '.20.6' } },
      links: [['R1', 'SW1', 'trunk'], ['SW1', 'PC1', 'v10'], ['SW1', 'PC2', 'v20']],
      labels: [{ x: 54, y: 34, t: 'trunk Fa0/1', k: 'kiri' }, { x: 30, y: 62, t: 'VLAN 10' }, { x: 70, y: 62, t: 'VLAN 20' }]
    });
    var sibukRos = false;
    function rencanaRos() {
      if (!r1.noshut) return { langkah: 2, alasan: 'Gi0/0 masih shutdown (administratively down), jadi R1 tidak menerima frame apa pun.' };
      if (r1.enc10 !== '10') return { langkah: 2, alasan: 'Frame bertag 10 tiba, tetapi tidak ada subinterface dengan <span class="mono">encapsulation dot1Q 10</span>. Frame dibuang.' };
      if (r1.ip10 !== '192.168.10.1') return { langkah: 2, alasan: 'Tidak ada interface R1 ber-IP 192.168.10.1, padahal itu gateway PC1. PC1 tidak menemukan gateway-nya.' };
      if (r1.ip20 !== '192.168.20.1') return { langkah: 2, alasan: 'R1 tidak punya jaringan 192.168.20.0 yang aktif (IP Gi0/0.20 salah), jadi paket ke PC2 dibuang.' };
      if (r1.enc20 !== '20') return { langkah: 4, alasan: 'R1 mengirim dengan tag ' + (r1.enc20 || '?') + ', bukan 20. SW1 memasukkannya ke VLAN yang salah, jadi PC2 (VLAN 20) tidak menerimanya.' };
      return null;
    }
    $('btn-ping-ros').addEventListener('click', function () {
      if (sibukRos) return;
      sibukRos = true; var tombol = this; tombol.disabled = true;
      var li = document.querySelectorAll('#langkah-ros li'), hasil = $('hasil-ros'), pk = pgRos.paket, gagal = rencanaRos();
      li.forEach(function (x) { x.className = ''; });
      pgRos.bersih(); hasil.className = 'hasil-uji'; hasil.textContent = 'Paket berjalan…';
      function aktif(i) { li.forEach(function (x, k) { if (k < i) x.className = 'lewat'; else if (k === i) x.className = 'aktif'; }); }
      function berhenti(i) {
        li.forEach(function (x, k) { x.className = k < i ? 'lewat' : (k === i ? 'gagal' : ''); });
        labelPaket(pk, 'dibuang', 'gagal');
        pgRos.node(i >= 4 ? 'PC2' : 'R1').classList.add('gagal');
        if (i >= 4) letakkan(pk, pgRos.titik('SW1', 3));
        hasil.className = 'hasil-uji gagal';
        hasil.innerHTML = '✘ <b>Ping gagal di langkah ' + (i + 1) + '.</b> ' + gagal.alasan;
        sibukRos = false; tombol.disabled = false;
      }
      var urutan = [
        function () { aktif(0); letakkan(pk, pgRos.titik('PC1')); labelPaket(pk, 'tanpa tag', 'kirim'); return geser(pk, pgRos.titik('SW1'), 800); },
        function () { aktif(1); labelPaket(pk, 'tag 10', 'tag10'); return geser(pk, pgRos.titik('R1', -3), 800); },
        function () { aktif(2); pgRos.node('R1').classList.add('aktif'); return jeda(700); },
        function () { aktif(3); pgRos.node('R1').classList.remove('aktif'); labelPaket(pk, 'tag ' + (r1.enc20 || '?'), r1.enc20 === '20' ? 'tag20' : 'gagal'); letakkan(pk, pgRos.titik('R1', 3)); return geser(pk, pgRos.titik('SW1', 3), 800); },
        function () { aktif(4); labelPaket(pk, 'tag dilepas', 'kirim'); return geser(pk, pgRos.titik('PC2'), 800); }
      ];
      var i = 0;
      (function lanjut() {
        if (gagal && i >= gagal.langkah) { berhenti(gagal.langkah); return; }
        if (i >= urutan.length) {
          li.forEach(function (x) { x.className = 'lewat'; });
          pgRos.node('PC2').classList.add('sampai');
          labelPaket(pk, 'Reply', 'balas');
          geser(pk, pgRos.titik('PC1'), 900).then(function () {
            pk.hidden = true;
            hasil.className = 'hasil-uji berhasil';
            hasil.innerHTML = '✔ <b>Reply from 192.168.20.6.</b> PC1 (VLAN 10) dan PC2 (VLAN 20) bisa saling ping lewat satu kabel trunk.';
            sibukRos = false; tombol.disabled = false;
          });
          return;
        }
        urutan[i]().then(function () { i++; lanjut(); });
      })();
    });

    /* --- Bagian B1: urutan DORA (ketuk untuk menyusun) --- */
    var DORA = [
      { nama: 'Discover', teks: 'PC2 broadcast: “Ada DHCP server? Saya butuh IP.”' },
      { nama: 'Offer', teks: 'R1 menawarkan: “Pakai 192.168.20.6.”' },
      { nama: 'Request', teks: 'PC2: “Saya mau IP yang kamu tawarkan.”' },
      { nama: 'Acknowledge', teks: 'R1: “Oke, IP itu resmi milikmu.”' }
    ];
    var dora = ambil('dora');
    if (!dora || !dora.acak) {
      var a; do { a = acak([0, 1, 2, 3]); } while (a.join() === '0,1,2,3');
      dora = { acak: a, isi: [], benar: false };
    }
    var doraSalah = false;
    function simpanDora() { taruh('dora', dora); }
    function renderDora() {
      var slot = $('dora-slot'), pilih = $('dora-pilih');
      slot.innerHTML = ''; pilih.innerHTML = '';
      for (var s = 0; s < 4; s++) {
        var li = document.createElement('li'), idx = dora.isi[s];
        if (idx === undefined) { li.className = 'dora-kosong'; li.innerHTML = '<span class="dora-no">' + (s + 1) + '</span><span>Langkah ' + (s + 1) + '</span>'; }
        else {
          var b = document.createElement('button'); b.type = 'button';
          var salah = doraSalah && idx !== s;
          b.className = 'dora-kartu terisi' + (dora.benar ? ' benar' : '') + (salah ? ' salah' : '');
          b.disabled = !!dora.benar;
          b.setAttribute('aria-label', 'Langkah ' + (s + 1) + ': ' + DORA[idx].nama + (dora.benar ? '' : '. Ketuk untuk mengembalikan.'));
          b.innerHTML = '<span class="dora-no">' + (s + 1) + '</span><span><b>' + DORA[idx].nama + '</b><small>' + DORA[idx].teks + '</small></span>';
          (function (posisi) { b.addEventListener('click', function () { dora.isi.splice(posisi, 1); doraSalah = false; simpanDora(); renderDora(); }); })(s);
          li.appendChild(b);
        }
        slot.appendChild(li);
      }
      dora.acak.forEach(function (idx) {
        if (dora.isi.indexOf(idx) >= 0) return;
        var b = document.createElement('button'); b.type = 'button'; b.className = 'dora-kartu';
        b.setAttribute('data-dora', DORA[idx].nama);
        b.innerHTML = '<span><b>' + DORA[idx].nama + '</b><small>' + DORA[idx].teks + '</small></span>';
        b.addEventListener('click', function () { if (dora.isi.length < 4) { dora.isi.push(idx); doraSalah = false; simpanDora(); renderDora(); } });
        pilih.appendChild(b);
      });
      if (dora.benar) pilih.innerHTML = '';
      $('btn-cek-dora').disabled = dora.benar || dora.isi.length < 4;
      $('btn-ulang-dora').disabled = !!dora.benar;
      if (dora.benar) { var h = $('hasil-dora'); h.className = 'hasil-uji berhasil'; h.innerHTML = '✔ <b>Benar: Discover → Offer → Request → Acknowledge (DORA).</b>'; }
    }
    $('btn-cek-dora').addEventListener('click', function () {
      var h = $('hasil-dora');
      if (dora.isi.join() === '0,1,2,3') {
        dora.benar = true; simpanDora();
        if (!prog().m2_dora) setProg({ m2_dora: true });
        renderDora(); segarkanSelesai('m2');
      } else {
        doraSalah = true; renderDora();
        var nBenar = dora.isi.filter(function (x, i) { return x === i; }).length;
        h.className = 'hasil-uji gagal';
        h.innerHTML = '✘ Baru ' + nBenar + ' dari 4 posisi yang tepat (kotak merah). Petunjuk: semuanya diawali PC yang <b>belum punya IP</b> berteriak mencari server. Ketuk kotak merah untuk mengembalikannya.';
      }
    });
    $('btn-ulang-dora').addEventListener('click', function () { if (dora.benar) return; dora.isi = []; doraSalah = false; simpanDora(); renderDora(); $('hasil-dora').textContent = ''; });

    /* --- Bagian B2: pool DHCP --- */
    var POOL = [
      { id: 'ex-awal', k: 'exAwal', benar: '192.168.20.1' },
      { id: 'ex-akhir', k: 'exAkhir', benar: '192.168.20.5' },
      { id: 'pool-net', k: 'net', benar: '192.168.20.0' },
      { id: 'pool-mask', k: 'mask', benar: '255.255.255.0' },
      { id: 'pool-gw', k: 'gw', benar: '192.168.20.1' },
      { id: 'pool-dns', k: 'dns', benar: '8.8.8.8' }
    ];
    var pool = ambil('pool') || {};
    POOL.forEach(function (f) {
      var el = $(f.id); el.value = pool[f.k] || '';
      el.addEventListener('input', function () { pool[f.k] = el.value.trim(); taruh('pool', pool); el.classList.remove('invalid', 'valid'); $('fb-' + f.id).textContent = ''; $('fb-' + f.id).className = 'field-fb'; });
    });
    function fb(id, jenis, teks) {
      var el = $(id), f = $('fb-' + id);
      el.classList.toggle('invalid', jenis === 'err'); el.classList.toggle('valid', jenis === 'ok');
      f.className = 'field-fb ' + jenis; f.innerHTML = teks;
    }
    // Periksa format (semua) + isi (sesuai lembar praktikum). Mengembalikan {format: bool, benar: bool}.
    function periksaPool() {
      var format = true, benar = true;
      POOL.forEach(function (f) {
        var v = pool[f.k] || '';
        var bolehKosong = f.k === 'exAwal' || f.k === 'exAkhir';
        if (v === '' && bolehKosong) { fb(f.id, 'err', f.k === 'exAwal' ? 'Kosong: tanpa excluded-address, alamat .1 (gateway) ikut dibagikan.' : 'Kosong: isi alamat akhir rentang yang dikecualikan.'); benar = false; return; }
        if (!parseIP(v)) { fb(f.id, 'err', esc(alasanTidakValid(v))); format = false; benar = false; return; }
        if (samaIP(v, f.benar)) { fb(f.id, 'ok', '✓ Tepat.'); return; }
        benar = false;
        var pesan = {
          exAwal: 'Mulailah dari gateway <b>192.168.20.1</b> supaya alamat gateway tidak dibagikan.',
          exAkhir: 'Lembar praktikum mencadangkan .1 sampai <b>.5</b> untuk perangkat ber-IP tetap.',
          net: /^192\.168\.20\./.test(v) ? 'Tulis <b>network address</b>-nya: angka terakhir 0.' : 'Pool harus sama dengan jaringan Gi0/0.20, yaitu 192.168.20.0.',
          mask: 'VLAN 20 memakai /24, mask-nya 255.255.255.0.',
          gw: 'default-router harus sama dengan IP Gi0/0.20 (gateway VLAN 20).',
          dns: 'Lembar praktikum memakai DNS publik Google 8.8.8.8.'
        }[f.k];
        fb(f.id, 'err', pesan);
      });
      var a = ipKeInt(pool.exAwal || ''), b = ipKeInt(pool.exAkhir || '');
      if (a !== null && b !== null && b < a) { fb('ex-akhir', 'err', 'Alamat akhir tidak boleh lebih kecil dari alamat awal.'); benar = false; }
      return { format: format, benar: benar };
    }
    $('btn-cek-pool').addEventListener('click', function () {
      var r = periksaPool(), h = $('hasil-pool');
      h.className = 'hasil-uji ' + (r.benar ? 'berhasil' : 'gagal');
      h.innerHTML = r.benar ? '✔ Pool sesuai lembar praktikum. Sekarang tekan <b>PC2 minta IP (DHCP)</b>.' : (r.format ? 'Ada isian yang belum tepat (lihat catatan merah). Kamu tetap bisa mencoba “PC2 minta IP” untuk melihat akibatnya.' : 'Ada isian yang formatnya belum valid. Perbaiki dulu yang merah.');
    });

    function simulasiDhcp() {
      function apipa(alasan) { return { apipa: true, ip: '169.254.37.112', mask: '255.255.0.0', gw: '0.0.0.0', dns: '0.0.0.0', masalah: [alasan], catatan: [], ok: false }; }
      if (!r1.noshut) return apipa('Discover tidak pernah dijawab: interface g0/0 R1 masih <b>shutdown</b> (lihat Bagian A).');
      if (r1.enc20 !== '20') return apipa('Discover dari VLAN 20 tiba dengan tag 20, tetapi R1 tidak punya subinterface <span class="mono">dot1Q 20</span> (lihat Bagian A).');
      if (r1.ip20 !== '192.168.20.1') return apipa('Gi0/0.20 belum memakai 192.168.20.1, jadi R1 tidak bisa melayani VLAN 20 (lihat Bagian A).');
      if (!samaIP(pool.net, '192.168.20.0') || !samaIP(pool.mask, '255.255.255.0'))
        return apipa('Network pool (' + esc((pool.net || 'kosong') + ' ' + (pool.mask || '')) + ') tidak cocok dengan subnet Gi0/0.20 (192.168.20.0/24). R1 tidak punya pool untuk VLAN 20, jadi tidak ada Offer.');
      var basis = ipKeInt('192.168.20.0'), ex1 = ipKeInt(pool.exAwal || ''), ex2 = ipKeInt(pool.exAkhir || ''), lease = null;
      if (ex1 !== null && ex2 === null) ex2 = ex1; // satu alamat saja
      for (var h = 1; h <= 254; h++) {
        var x = basis + h;
        if (ex1 !== null && ex2 !== null && x >= ex1 && x <= ex2) continue;
        lease = intKeIP(x); break;
      }
      if (!lease) return apipa('Semua alamat di pool dikecualikan, jadi tidak ada IP yang bisa ditawarkan.');
      var masalah = [], catatan = [];
      if (lease === '192.168.20.1') masalah.push('<b>IP conflict</b>: PC2 mendapat 192.168.20.1, padahal alamat itu milik gateway R1 (Gi0/0.20). Kecualikan .1 dengan <span class="mono">excluded-address</span>.');
      if (!parseIP(pool.gw || '')) masalah.push('<b>Gateway kosong/tidak valid</b>: PC2 dapat IP, tetapi tidak bisa keluar ke VLAN lain.');
      else if (!samaIP(pool.gw, '192.168.20.1')) masalah.push('<b>Gateway salah</b>: PC2 diberi gateway ' + esc(pool.gw) + ', bukan 192.168.20.1. PC2 dapat IP, tetapi ping ke VLAN lain gagal.');
      if (!parseIP(pool.dns || '')) catatan.push('DNS kosong: PC2 bisa ping alamat IP, tetapi tidak bisa membuka nama situs.');
      else if (!samaIP(pool.dns, '8.8.8.8')) catatan.push('DNS ' + esc(pool.dns) + ' berbeda dari lembar praktikum (8.8.8.8).');
      if (lease !== '192.168.20.6' && lease !== '192.168.20.1') catatan.push('PC2 mendapat ' + lease + '. Dengan excluded-address .1–.5 sesuai lembar praktikum, IP pertama yang dibagikan adalah 192.168.20.6.');
      return { apipa: false, ip: lease, mask: '255.255.255.0', gw: parseIP(pool.gw || '') ? pool.gw : '0.0.0.0', dns: parseIP(pool.dns || '') ? pool.dns : '0.0.0.0', masalah: masalah, catatan: catatan,
        ok: lease === '192.168.20.6' && !masalah.length && !catatan.length };
    }
    function tampilDhcp(r) {
      var box = $('hasil-dhcp');
      if (!r) { box.innerHTML = ''; return; }
      var h = '<div class="ipconfig ' + (r.ok ? 'ok' : (r.apipa || r.masalah.length ? 'err' : 'warn')) + '"><p class="ipconfig-judul">PC2 · IP Configuration: <b>DHCP</b></p><dl>' +
        '<dt>IPv4 Address</dt><dd class="mono">' + esc(r.ip) + '</dd><dt>Subnet Mask</dt><dd class="mono">' + esc(r.mask) + '</dd>' +
        '<dt>Default Gateway</dt><dd class="mono">' + esc(r.gw) + '</dd><dt>DNS Server</dt><dd class="mono">' + esc(r.dns) + '</dd></dl></div>';
      var cek = [];
      if (r.ok) cek.push(['ok', '<b>Berhasil!</b> PC2 mendapat 192.168.20.6 dengan gateway 192.168.20.1 dan DNS 8.8.8.8.']);
      if (r.apipa) cek.push(['err', '<b>169.254.x.x = APIPA.</b> PC2 tidak mendapat jawaban dari DHCP server. ' + r.masalah[0]]);
      else r.masalah.forEach(function (m) { cek.push(['err', m]); });
      r.catatan.forEach(function (c) { cek.push(['info', c]); });
      h += '<ul class="cek-list">' + cek.map(function (c) { return '<li class="' + c[0] + '">' + c[1] + '</li>'; }).join('') + '</ul>';
      h += '<p class="langkah-no">R1# show ip dhcp binding</p><div class="tabel-gulir"><table class="tabel-cli"><thead><tr><th>IP address</th><th>Hardware address</th><th>Type</th></tr></thead><tbody>' +
        (r.apipa ? '<tr><td colspan="3">(kosong: belum ada IP yang dipinjamkan)</td></tr>' : '<tr><td>' + esc(r.ip) + '</td><td>0060.47A1.2B3C</td><td>Automatic</td></tr>') + '</tbody></table></div>';
      box.innerHTML = h;
    }
    var pgDora = panggung($('pg-dora'), {
      nodes: { PC2: { x: 12, y: 55, ikon: 'pc', ket: 'VLAN 20' }, SW1: { x: 50, y: 55, ikon: 'sw' }, R1: { x: 88, y: 55, ikon: 'router', ket: 'Gi0/0.20' } },
      links: [['PC2', 'SW1', 'v20'], ['SW1', 'R1', 'trunk']]
    });
    var sibukDhcp = false;
    $('btn-minta-ip').addEventListener('click', function () {
      if (sibukDhcp) return;
      var cek = periksaPool(), h = $('hasil-pool');
      if (!cek.format) { h.className = 'hasil-uji gagal'; h.innerHTML = 'Ada isian yang formatnya belum valid. Perbaiki dulu yang merah, lalu coba lagi.'; return; }
      h.textContent = '';
      sibukDhcp = true; var tombol = this; tombol.disabled = true;
      var r = simulasiDhcp(), pk = pgDora.paket;
      tampilDhcp(null); pgDora.bersih();
      $('pg-dora').classList.add('tampil');
      function kirim(dari, ke, teks, kelas) { letakkan(pk, pgDora.titik(dari)); labelPaket(pk, teks, kelas); return geser(pk, pgDora.titik('SW1'), 600).then(function () { return geser(pk, pgDora.titik(ke), 600); }); }
      var jalan;
      if (r.apipa) {
        jalan = kirim('PC2', 'R1', 'DISCOVER', 'kirim').then(function () {
          labelPaket(pk, 'tidak ada Offer', 'gagal'); pgDora.node('R1').classList.add('gagal'); return jeda(1100);
        }).then(function () { pk.hidden = true; pgDora.node('PC2').classList.add('gagal'); });
      } else {
        jalan = kirim('PC2', 'R1', 'DISCOVER', 'kirim')
          .then(function () { return kirim('R1', 'PC2', 'OFFER .' + r.ip.split('.')[3], 'balas'); })
          .then(function () { return kirim('PC2', 'R1', 'REQUEST', 'kirim'); })
          .then(function () { return kirim('R1', 'PC2', 'ACK', 'balas'); })
          .then(function () { pk.hidden = true; pgDora.node('PC2').classList.add(r.ok ? 'sampai' : 'gagal'); });
      }
      jalan.then(function () {
        tampilDhcp(r); taruh('dhcpHasil', r);
        if (r.ok && !prog().m2_dhcp) setProg({ m2_dhcp: true });
        segarkanSelesai('m2');
        sibukDhcp = false; tombol.disabled = false;
      });
    });

    renderR1(); renderDora();
    var hasilLama = ambil('dhcpHasil');
    if (hasilLama) { $('pg-dora').classList.add('tampil'); tampilDhcp(hasilLama); }
    pasangKuis('m2', function () { segarkanSelesai('m2'); });
    segarkanSelesai('m2');
    $('btn-m2-selesai').addEventListener('click', function () { window.location.href = 'rute.html'; });
  }

  /* ============================================================ MISI 3: VLSM & STATIC ROUTE */
  if (halaman === 'rute') {
    bannerUrut('m2', 'Misi 2', 'dhcp.html');

    /* --- Latihan 1: link R1–R2 --- */
    var V1 = [
      { id: 'v-butuh', jenis: 'angka', benar: 4, pesan: 'Kebutuhan di soal ini sudah pasti 2 host (R1 dan R2). Tambahkan 2 untuk network address dan broadcast address: 2 + 2 = …' },
      { id: 'v-blok', jenis: 'angka', benar: 4, pesan: 'Pilih blok terkecil dari deret 4, 8, 16, 32, … yang ≥ hasil kotak pertama (2 + 2).' },
      { id: 'v-prefix', jenis: 'prefix', benar: 30, pesan: 'Prefix = 32 − jumlah bit host. Blok 4 = 2<sup>2</sup>, jadi ada 2 bit host.' },
      { id: 'v-mask', jenis: 'ip', benar: '255.255.255.252', pesan: 'Lihat tabel contekan: mask untuk prefix itu (256 − 4 = 252).' },
      { id: 'v-net', jenis: 'ip', benar: '192.168.100.0', pesan: 'Blok pertama dimulai tepat dari alamat awal: 192.168.100.0.' },
      { id: 'v-h1', jenis: 'ip', benar: '192.168.100.1', pesan: 'Host pertama = network address + 1.' },
      { id: 'v-h2', jenis: 'ip', benar: '192.168.100.2', pesan: 'Host terakhir = broadcast address − 1.' },
      { id: 'v-bc', jenis: 'ip', benar: '192.168.100.3', pesan: 'Broadcast = network address + ukuran blok − 1.' }
    ];
    var vl1 = ambil('vlsm1') || {};
    function normIP(s) { s = String(s || '').trim(); if (/^\.?\d{1,3}$/.test(s)) s = '192.168.100.' + s.replace('.', ''); return s; }
    function fbV(id, ok, teks) {
      var el = $(id), f = $('fb-' + id);
      el.classList.toggle('invalid', !ok); el.classList.toggle('valid', ok);
      f.className = 'field-fb ' + (ok ? 'ok' : 'err'); f.innerHTML = teks;
    }
    V1.forEach(function (f) {
      var el = $(f.id); el.value = vl1[f.id] || '';
      el.addEventListener('input', function () { vl1[f.id] = el.value; taruh('vlsm1', vl1); el.classList.remove('invalid', 'valid'); $('fb-' + f.id).textContent = ''; });
    });
    function periksaV1(diam) {
      var semua = true;
      V1.forEach(function (f) {
        var v = String(vl1[f.id] || '').trim(), ok;
        if (f.jenis === 'angka') ok = /^\d+$/.test(v) && parseInt(v, 10) === f.benar;
        else if (f.jenis === 'prefix') ok = /^\/?\s*\d+$/.test(v) && parseInt(v.replace('/', ''), 10) === f.benar;
        else ok = samaIP(normIP(v), f.benar);
        if (!ok) semua = false;
        if (!diam || v !== '') fbV(f.id, ok, ok ? '✓' : (v === '' ? 'Belum diisi. ' : '') + f.pesan);
      });
      return semua;
    }
    $('btn-cek-vlsm1').addEventListener('click', function () {
      var ok = periksaV1(false), h = $('hasil-vlsm1');
      h.className = 'hasil-uji ' + (ok ? 'berhasil' : 'gagal');
      h.innerHTML = ok ? '✔ <b>Tepat!</b> Link R1–R2 = 192.168.100.0/30: R1 memakai .1, R2 memakai .2, broadcast .3. Tidak ada alamat terbuang.' : 'Masih ada isian yang belum tepat. Baca petunjuk di bawah kotak merah.';
      if (ok && !prog().m3_vlsm1) setProg({ m3_vlsm1: true });
      segarkanSelesai('m3');
    });
    if (prog().m3_vlsm1) periksaV1(true);
    // Kalkulator coba sendiri (tidak dinilai): jumlah host bebas → +2, blok, prefix, mask, host valid.
    var cobaIn = $('coba-host');
    if (cobaIn) cobaIn.addEventListener('input', function () {
      var h = parseInt(cobaIn.value, 10), out = $('coba-hasil');
      if (!(h >= 1 && h <= 254)) { out.textContent = 'Isi angka 1 sampai 254.'; return; }
      var blok = 4; while (blok < h + 2) blok *= 2;
      var p = 32 - Math.round(Math.log(blok) / Math.LN2), mask = '255.255.255.' + (256 - blok);
      out.innerHTML = h + ' host + 2 = <b>' + (h + 2) + '</b><br>Blok terkecil yang muat: <b>' + blok + '</b><br>Prefix: <b>/' + p + '</b> · mask <b>' + mask + '</b><br>Host yang bisa dipakai: <b>' + (blok - 2) + '</b>' + (blok - 2 > h ? ' (sisa ' + (blok - 2 - h) + ' alamat cadangan)' : ' (pas)');
    });

    /* --- Latihan 2: VLSM 192.168.50.0/24 --- */
    var SEG = [
      { n: 'Lab Komputer', h: 100 }, { n: 'Ruang Guru', h: 50 }, { n: 'Perpustakaan', h: 20 }, { n: 'Ruang TU', h: 10 }, { n: 'Link antar-router', h: 2 }
    ];
    function prefixTerbaik(h) { for (var p = 30; p >= 24; p--) if (Math.pow(2, 32 - p) - 2 >= h) return p; return 24; }
    var vl2 = ambil('vlsm2') || ['', '', '', '', ''];
    var list2 = $('vlsm2-list');
    SEG.forEach(function (s, i) {
      var row = document.createElement('div'); row.className = 'vlsm2-baris';
      var opsi = '<option value="">— pilih —</option>';
      for (var p = 24; p <= 30; p++) opsi += '<option value="' + p + '">/' + p + ' · ' + (Math.pow(2, 32 - p) - 2) + ' host</option>';
      row.innerHTML = '<span class="vlsm2-nama"><b>' + (i + 1) + '. ' + s.n + '</b><small>butuh ' + s.h + ' host</small></span>' +
        '<label class="sr-only" for="vp-' + i + '">Prefix untuk ' + s.n + '</label><select class="input mono" id="vp-' + i + '">' + opsi + '</select>';
      list2.appendChild(row);
      var sel = row.querySelector('select'); sel.value = vl2[i] || '';
      sel.addEventListener('change', function () { vl2[i] = sel.value; taruh('vlsm2', vl2); });
    });
    function hitungV2(diam) {
      var box = $('hasil-vlsm2');
      if (vl2.some(function (v) { return !v; })) { if (!diam) box.innerHTML = '<p class="hasil-uji gagal">Pilih prefix untuk kelima segmen dulu.</p>'; return false; }
      var awal = 0, semua = true, baris = [], pita = '', muat = true;
      SEG.forEach(function (s, i) {
        var p = parseInt(vl2[i], 10), blok = Math.pow(2, 32 - p), host = blok - 2, terbaik = prefixTerbaik(s.h);
        awal = Math.ceil(awal / blok) * blok;
        var status, kelas;
        if (host < s.h) { status = '✘ /' + p + ' hanya ' + host + ' host, kurang dari ' + s.h + '.'; kelas = 'err'; semua = false; }
        else if (p !== terbaik) { status = '⚠ Muat, tetapi boros. Ada blok lebih kecil yang masih cukup.'; kelas = 'warn'; semua = false; }
        else { status = '✓ Pas: ' + s.h + ' + 2 = ' + (s.h + 2) + ' → blok ' + blok + '.'; kelas = 'ok'; }
        if (awal + blok > 256) {
          muat = false; semua = false;
          baris.push('<li class="vlsm-hasil err"><b>' + s.n + ' <span class="mono">/' + p + '</span></b><span>' + (kelas === 'ok' ? '' : status + ' ') + '✘ Tidak muat lagi: sisa alamat di 192.168.50.0/24 sudah habis dipakai blok sebelumnya.</span></li>');
          return;
        }
        baris.push('<li class="vlsm-hasil ' + kelas + '"><b>' + s.n + ' <span class="mono">/' + p + '</span></b>' +
          '<span class="mono">Network .' + awal + ' · Host .' + (awal + 1) + '–.' + (awal + blok - 2) + ' · Broadcast .' + (awal + blok - 1) + '</span><span>' + status + '</span></li>');
        pita += '<div class="vp-seg vp-' + i + '" style="width:' + (blok / 256 * 100) + '%" title="' + s.n + '">' + (blok >= 32 ? '<b>/' + p + '</b><span>.' + awal + '</span>' : '') + '</div>';
        awal += blok;
      });
      if (awal < 256 && muat) pita += '<div class="vp-seg vp-sisa" style="width:' + ((256 - awal) / 256 * 100) + '%" title="sisa"></div>';
      box.innerHTML = '<div class="vlsm-pita" aria-hidden="true">' + pita + '</div><div class="pita-skala mono"><span>.0</span><span>.128</span><span>.255</span></div>' +
        '<ul class="vlsm-hasil-list">' + baris.join('') + '</ul>' +
        (muat && awal < 256 ? '<p class="section-note">Sisa .' + awal + ' – .255 masih kosong untuk pengembangan.</p>' : '') +
        '<p class="hasil-uji ' + (semua ? 'berhasil' : 'gagal') + '">' + (semua ? '✔ <b>Alokasi VLSM tepat!</b> Semua segmen muat dalam satu /24 tanpa tumpang tindih.' : 'Perbaiki prefix yang bertanda ✘ atau ⚠, lalu hitung lagi.') + '</p>';
      return semua;
    }
    $('btn-cek-vlsm2').addEventListener('click', function () {
      var ok = hitungV2(false);
      if (ok && !prog().m3_vlsm2) setProg({ m3_vlsm2: true });
      segarkanSelesai('m3');
    });
    if (vl2.every(function (v) { return v; })) hitungV2(true);

    /* --- Bagian B: static route --- */
    var RTR = {
      R1: { conn: [{ net: '192.168.10.0', p: 24, intf: 'Gi0/0.10', ip: '192.168.10.1' }, { net: '192.168.20.0', p: 24, intf: 'Gi0/0.20', ip: '192.168.20.1' }, { net: '192.168.100.0', p: 30, intf: 'Gi0/1', ip: '192.168.100.1' }], tetangga: '192.168.100.2' },
      R2: { conn: [{ net: '192.168.30.0', p: 24, intf: 'Gi0/0.30', ip: '192.168.30.1' }, { net: '192.168.40.0', p: 24, intf: 'Gi0/0.40', ip: '192.168.40.1' }, { net: '192.168.100.0', p: 30, intf: 'Gi0/1', ip: '192.168.100.2' }], tetangga: '192.168.100.1' }
    };
    var PCS = {
      PC1: { ip: '192.168.10.2', r: 'R1', gw: '192.168.10.1' }, PC2: { ip: '192.168.20.6', r: 'R1', gw: '192.168.20.1' },
      PC3: { ip: '192.168.30.2', r: 'R2', gw: '192.168.30.1' }, PC4: { ip: '192.168.40.6', r: 'R2', gw: '192.168.40.1' }
    };
    var OPS_NET = ['192.168.10.0', '192.168.20.0', '192.168.30.0', '192.168.40.0', '192.168.100.0', '0.0.0.0'];
    var OPS_MASK = ['255.255.255.0', '255.255.255.252', '0.0.0.0'];
    var OPS_NH = ['192.168.100.1', '192.168.100.2', '192.168.10.1', '192.168.30.1'];
    var rute = ambil('rute') || { R1: [], R2: [] };
    var pingOk = ambil('pingOk') || {};
    function opsiHtml(arr) { return '<option value="">— pilih —</option>' + arr.map(function (x) { return '<option>' + x + '</option>'; }).join(''); }
    function maskSeharusnya(net) { return net === '0.0.0.0' ? '0.0.0.0' : net === '192.168.100.0' ? '255.255.255.252' : '255.255.255.0'; }

    function cariRute(r, ip) {
      var hasil = null;
      RTR[r].conn.forEach(function (c) { if (dalamJaringan(ip, c.net, c.p)) hasil = { kode: 'C', p: c.p, intf: c.intf }; });
      if (hasil) return hasil;
      rute[r].forEach(function (x) {
        var p = maskKePrefix(x.mask);
        if (p >= 0 && dalamJaringan(ip, x.net, p) && (!hasil || p > hasil.p)) hasil = { kode: p === 0 ? 'S*' : 'S', p: p, nh: x.nh };
      });
      return hasil;
    }
    function routerDariIP(ip) { return ip === '192.168.100.1' ? 'R1' : ip === '192.168.100.2' ? 'R2' : null; }

    function renderKartu(r) {
      var k = $('kartu-' + r), lain = r === 'R1' ? 'R2' : 'R1';
      k.innerHTML = '<h3 class="rute-judul">' + r + ' <small>· ' + (r === 'R1' ? 'kenal 10.0, 20.0, 100.0/30' : 'kenal 30.0, 40.0, 100.0/30') + '</small></h3>' +
        '<div class="rute-form">' +
        '<span class="mono rute-ip">ip route</span>' +
        '<div class="field"><label class="field-label" for="' + r + '-net">Network tujuan</label><select class="input mono" id="' + r + '-net">' + opsiHtml(OPS_NET) + '</select></div>' +
        '<div class="field"><label class="field-label" for="' + r + '-mask">Subnet mask</label><select class="input mono" id="' + r + '-mask">' + opsiHtml(OPS_MASK) + '</select></div>' +
        '<div class="field"><label class="field-label" for="' + r + '-nh">Next-hop</label><select class="input mono" id="' + r + '-nh">' + opsiHtml(OPS_NH) + '</select></div>' +
        '<button type="button" class="btn btn-primary" id="' + r + '-tambah">Tambah rute ke ' + r + '</button>' +
        '<span class="form-error" id="' + r + '-err" role="alert"></span></div>' +
        '<ul class="rute-list" id="' + r + '-list"></ul>' +
        '<p class="langkah-no">' + r + '# show ip route</p><p class="gw-last mono" id="' + r + '-gwl"></p>' +
        '<div class="tabel-gulir"><table class="tabel-cli tabel-rute" id="' + r + '-tabel"></table></div>';
      $(r + '-tambah').addEventListener('click', function () {
        var net = $(r + '-net').value, mask = $(r + '-mask').value, nh = $(r + '-nh').value, err = $(r + '-err');
        err.innerHTML = '';
        if (!net || !mask || !nh) { err.textContent = 'Pilih ketiga bagian dulu: network tujuan, subnet mask, dan next-hop.'; return; }
        if (mask !== maskSeharusnya(net)) { err.innerHTML = '% Inconsistent address and mask. ' + (net === '0.0.0.0' ? 'Default route memakai mask <b>0.0.0.0</b>.' : 'Mask untuk ' + net + ' adalah <b>' + maskSeharusnya(net) + '</b>.'); return; }
        var konek = RTR[r].conn.filter(function (c) { return c.net === net; })[0];
        if (konek) { err.innerHTML = 'Jaringan ' + net + ' sudah <b>terhubung langsung</b> ke ' + r + ' (kode C). Tidak perlu static route.'; return; }
        var milik = RTR[r].conn.filter(function (c) { return c.ip === nh; })[0];
        if (milik) { err.innerHTML = nh + ' adalah IP ' + r + ' sendiri (' + milik.intf + '). Next-hop = IP router tetangga: <b>' + RTR[r].tetangga + '</b> (' + lain + ').'; return; }
        if (!RTR[r].conn.some(function (c) { return dalamJaringan(nh, c.net, c.p); })) { err.innerHTML = 'Next-hop ' + nh + ' tidak terhubung langsung dengan ' + r + ' (ada di balik ' + lain + '). Next-hop harus di jaringan yang menempel ke ' + r + ', yaitu link 192.168.100.0/30.'; return; }
        if (rute[r].some(function (x) { return x.net === net && x.mask === mask; })) { err.textContent = 'Rute ke ' + net + ' sudah ada. Hapus dulu kalau mau menggantinya.'; return; }
        rute[r].push({ net: net, mask: mask, nh: nh }); taruh('rute', rute);
        pingOk = {}; taruh('pingOk', pingOk); // rute berubah: hasil ping lama tidak berlaku lagi
        $(r + '-net').value = ''; $(r + '-mask').value = ''; $(r + '-nh').value = '';
        renderRute(r); evaluasiRute();
      });
    }
    function renderRute(r) {
      var ul = $(r + '-list'); ul.innerHTML = '';
      if (!rute[r].length) ul.innerHTML = '<li class="rute-kosong">Belum ada static route di ' + r + '.</li>';
      rute[r].forEach(function (x, i) {
        var li = document.createElement('li');
        li.innerHTML = '<span class="mono">ip route ' + x.net + ' ' + x.mask + ' ' + x.nh + '</span>';
        var b = document.createElement('button'); b.type = 'button'; b.className = 'btn btn-ghost btn-kecil'; b.textContent = 'Hapus';
        b.setAttribute('aria-label', 'Hapus rute ' + x.net + ' di ' + r);
        b.addEventListener('click', function () { rute[r].splice(i, 1); taruh('rute', rute); pingOk = {}; taruh('pingOk', pingOk); renderRute(r); evaluasiRute(); });
        li.appendChild(b); ul.appendChild(li);
      });
      // show ip route
      var baris = [];
      RTR[r].conn.forEach(function (c) {
        baris.push({ k: 'C', net: c.net + '/' + c.p, ket: 'is directly connected, ' + c.intf, urut: ipKeInt(c.net) * 64 + c.p });
        baris.push({ k: 'L', net: c.ip + '/32', ket: 'is directly connected, ' + c.intf, urut: ipKeInt(c.ip) * 64 + 32 });
      });
      var def = null;
      rute[r].forEach(function (x) {
        var p = maskKePrefix(x.mask);
        if (p === 0) def = x;
        baris.push({ k: p === 0 ? 'S*' : 'S', net: x.net + '/' + p, ket: '[1/0] via ' + x.nh, urut: ipKeInt(x.net) * 64 + p });
      });
      baris.sort(function (a, b) { return a.urut - b.urut; });
      $(r + '-gwl').textContent = def ? 'Gateway of last resort is ' + def.nh + ' to network 0.0.0.0' : 'Gateway of last resort is not set';
      $(r + '-tabel').innerHTML = '<thead><tr><th>Kode</th><th>Tujuan</th><th>Keterangan</th></tr></thead><tbody>' +
        baris.map(function (b) { return '<tr class="kode-' + b.k.replace('*', 'x') + '"><td><span class="kode">' + b.k + '</span></td><td>' + b.net + '</td><td>' + b.ket + '</td></tr>'; }).join('') + '</tbody>';
    }
    function evaluasiRute() {
      // Dianggap benar bila router benar-benar punya jalan ke jaringan itu lewat tetangganya (rute spesifik ATAU default route).
      var lewat = function (r, ip, nh) { var x = cariRute(r, ip); return !!(x && x.nh === nh); };
      var c = {
        'r1-30': lewat('R1', '192.168.30.2', '192.168.100.2'),
        'r1-40': lewat('R1', '192.168.40.6', '192.168.100.2'),
        'r2-def': lewat('R2', '192.168.10.2', '192.168.100.1') && lewat('R2', '192.168.20.6', '192.168.100.1'),
        'ping-13': !!pingOk['PC1>PC3'],
        'ping-31': !!pingOk['PC3>PC1']
      };
      document.querySelectorAll('#tugas-rute [data-cek]').forEach(function (li) { li.classList.toggle('beres', !!c[li.getAttribute('data-cek')]); });
      var p = prog(), patch = {};
      if (c['r1-30'] && c['r1-40'] && c['r2-def'] && !p.m3_rute) patch.m3_rute = true;
      if (c['ping-13'] && c['ping-31'] && !p.m3_ping) patch.m3_ping = true;
      if (Object.keys(patch).length) setProg(patch);
      segarkanSelesai('m3');
    }

    /* --- simulasi ping antar-jaringan --- */
    var pgRute = panggung($('pg-rute'), {
      nodes: {
        PC1: { x: 9, y: 24, ikon: 'pc', ket: '.10.2' }, PC2: { x: 9, y: 76, ikon: 'pc', ket: '.20.6' },
        R1: { x: 36, y: 50, ikon: 'router', ket: '.100.1' }, R2: { x: 64, y: 50, ikon: 'router', ket: '.100.2' },
        PC3: { x: 91, y: 24, ikon: 'pc', ket: '.30.2' }, PC4: { x: 91, y: 76, ikon: 'pc', ket: '.40.6' }
      },
      links: [['PC1', 'R1', 'v10'], ['PC2', 'R1', 'v20'], ['R1', 'R2', 'link'], ['R2', 'PC3', 'v30'], ['R2', 'PC4', 'v40']],
      labels: [{ x: 50, y: 34, t: '100.0/30' }]
    });
    function jejak(dari, ke) {
      var src = PCS[dari], dst = PCS[ke], maju = [dari, src.r], r = src.r, n = 0, c;
      while (true) {
        c = cariRute(r, dst.ip);
        if (!c) return { maju: maju, gagal: 'maju', router: r };
        if (c.kode === 'C') { maju.push(ke); break; }
        r = routerDariIP(c.nh); maju.push(r);
        if (++n > 4) return { maju: maju, gagal: 'loop', router: r };
      }
      var balik = [ke, dst.r]; r = dst.r; n = 0;
      while (true) {
        c = cariRute(r, src.ip);
        if (!c) return { maju: maju, balik: balik, gagal: 'balik', router: r };
        if (c.kode === 'C') { balik.push(dari); break; }
        r = routerDariIP(c.nh); balik.push(r);
        if (++n > 4) return { maju: maju, balik: balik, gagal: 'loop', router: r };
      }
      return { maju: maju, balik: balik, gagal: null };
    }
    function jalankanJalur(ids, teks, kelas) {
      var pk = pgRute.paket;
      letakkan(pk, pgRute.titik(ids[0])); labelPaket(pk, teks, kelas);
      var i = 1;
      return new Promise(function (selesai) {
        (function lanjut() {
          if (i >= ids.length) { selesai(); return; }
          pgRute.node(ids[i]).classList.add('aktif');
          geser(pk, pgRute.titik(ids[i]), 650).then(function () { i++; lanjut(); });
        })();
      });
    }
    var sibukPing = false;
    function tulis(baris) { $('terminal').textContent = baris.join('\n'); }
    function kirimPing() {
      if (sibukPing) return;
      var dari = $('ping-dari').value, ke = $('ping-ke').value;
      if (dari === ke) { tulis(['Pilih PC tujuan yang berbeda.']); return; }
      sibukPing = true; $('btn-ping').disabled = true;
      var dst = PCS[ke], src = PCS[dari], j = jejak(dari, ke), log = ['C:\\> ping ' + dst.ip, '', 'Pinging ' + dst.ip + ' with 32 bytes of data:', ''];
      tulis(log); pgRute.bersih();
      var ttl = 128 - (j.balik ? j.balik.length - 2 : 0);
      var p = jalankanJalur(j.maju, 'Echo request', 'kirim');
      if (j.gagal === 'maju') {
        p = p.then(function () {
          labelPaket(pgRute.paket, 'dibuang', 'gagal'); pgRute.node(j.router).classList.add('gagal'); return jeda(900);
        }).then(function () {
          for (var i = 0; i < 4; i++) log.push('Reply from ' + src.gw + ': Destination host unreachable.');
          log.push('', 'Ping statistics for ' + dst.ip + ':', '    Packets: Sent = 4, Received = 0, Lost = 4 (100% loss),', '',
            '✘ Kenapa gagal? ' + j.router + ' tidak punya rute ke ' + dst.ip.replace(/\.\d+$/, '.0') + '. Paket dibuang di ' + j.router + ', lalu ' + j.router + ' membalas “Destination host unreachable”.',
            '  Tambahkan static route di ' + j.router + ' menuju jaringan itu.');
        });
      } else if (j.gagal === 'balik') {
        p = p.then(function () {
          pgRute.node(ke).classList.add('sampai'); return jalankanJalur(j.balik, 'Echo reply', 'balas');
        }).then(function () {
          labelPaket(pgRute.paket, 'balasan dibuang', 'gagal'); pgRute.node(j.router).classList.add('gagal'); return jeda(900);
        }).then(function () {
          for (var i = 0; i < 4; i++) log.push('Request timed out.');
          log.push('', 'Ping statistics for ' + dst.ip + ':', '    Packets: Sent = 4, Received = 0, Lost = 4 (100% loss),', '',
            '✘ Kenapa gagal? Permintaan SAMPAI di ' + ke + ', tetapi balasannya dibuang di ' + j.router + ':',
            '  ' + j.router + ' tidak punya rute kembali ke ' + src.ip.replace(/\.\d+$/, '.0') + '.',
            '  Rute harus ada di KEDUA arah. Tambahkan rute di ' + j.router + (j.router === 'R2' ? ' (misalnya default route ke 192.168.100.1).' : '.'));
        });
      } else if (j.gagal === 'loop') {
        p = p.then(function () {
          for (var i = 0; i < 4; i++) log.push('Reply from 192.168.100.1: TTL expired in transit.');
          log.push('', '✘ Paket berputar-putar antara R1 dan R2 (routing loop). Periksa next-hop setiap rute.');
        });
      } else {
        p = p.then(function () {
          pgRute.node(ke).classList.add('sampai'); return jalankanJalur(j.balik, 'Echo reply', 'balas');
        }).then(function () {
          pgRute.node(dari).classList.add('sampai');
          for (var i = 0; i < 4; i++) log.push('Reply from ' + dst.ip + ': bytes=32 time<1ms TTL=' + ttl);
          log.push('', 'Ping statistics for ' + dst.ip + ':', '    Packets: Sent = 4, Received = 4, Lost = 0 (0% loss),', '',
            '✔ Berhasil! Pergi: ' + j.maju.join(' → ') + '. Pulang: ' + j.balik.join(' → ') + '.');
          pingOk[dari + '>' + ke] = true; taruh('pingOk', pingOk);
        });
      }
      p.then(function () {
        tulis(log); pgRute.paket.hidden = true;
        sibukPing = false; $('btn-ping').disabled = false;
        evaluasiRute();
      });
    }
    $('btn-ping').addEventListener('click', kirimPing);
    document.querySelectorAll('[data-ping]').forEach(function (c) {
      c.addEventListener('click', function () {
        var x = c.getAttribute('data-ping').split(',');
        $('ping-dari').value = x[0]; $('ping-ke').value = x[1];
        kirimPing();
      });
    });

    renderKartu('R1'); renderKartu('R2'); renderRute('R1'); renderRute('R2');
    pasangKuis('m3', function () { segarkanSelesai('m3'); });
    evaluasiRute();
    $('btn-m3-selesai').addEventListener('click', function () { window.location.href = 'hasil.html'; });
  }

  /* ============================================================ HASIL */
  if (halaman === 'hasil') {
    var ph = prog(), s = skor(ph), semua = !!(ph.m1 && ph.m2 && ph.m3);
    $('result-final').textContent = String(s.total);
    if (semua) {
      $('hasil-judul').textContent = s.total >= 85 ? 'Luar biasa! Kamu siap praktik.' : s.total >= 65 ? 'Bagus! Kamu siap praktik.' : 'Selesai! Baca lagi bagian yang masih salah sebelum praktik.';
      try { if (window.parent && window.parent !== window) window.parent.postMessage({ type: 'mikronet:hasil', skor: s.total, skorMaks: 100 }, '*'); } catch (e) {}
    } else {
      $('hasil-judul').textContent = 'Simulasi belum selesai';
      $('result-caption').textContent = 'Skor sementara (dari 100). Skor baru dikirim ke MIKRO-NET setelah ketiga misi selesai.';
    }
    var baris = [
      ['Misi 1 · Tugas VLAN & trunk (' + s.m1.nTugas + '/3)', s.m1.tugas + ' / ' + s.m1.maksTugas],
      ['Misi 1 · Kuis (' + s.m1.benar + '/5 benar × 3)', s.m1.kuis + ' / ' + s.m1.maksKuis],
      ['Misi 2 · Tugas router-on-a-stick & DHCP (' + s.m2.nTugas + '/3)', s.m2.tugas + ' / ' + s.m2.maksTugas],
      ['Misi 2 · Kuis (' + s.m2.benar + '/5 benar × 3)', s.m2.kuis + ' / ' + s.m2.maksKuis],
      ['Misi 3 · Tugas VLSM & static route (' + s.m3.nTugas + '/4)', s.m3.tugas + ' / ' + s.m3.maksTugas],
      ['Misi 3 · Kuis (' + s.m3.benar + '/5 benar × 4)', s.m3.kuis + ' / ' + s.m3.maksKuis]
    ];
    var ul = $('result-breakdown');
    baris.forEach(function (b) { var li = document.createElement('li'); li.innerHTML = '<span>' + esc(b[0]) + '</span><span class="result-value">' + b[1] + '</span>'; ul.appendChild(li); });
    var HAL = { m1: ['Misi 1', 'vlan.html'], m2: ['Misi 2', 'dhcp.html'], m3: ['Misi 3', 'rute.html'] };
    ['m1', 'm2', 'm3'].forEach(function (m) {
      if (ph[m] && s[m].tugas === s[m].maksTugas && s[m].benar === 5) return;
      var li = document.createElement('li'); li.className = 'saran';
      li.innerHTML = '<span>' + (ph[m] ? 'Mau skor penuh? Ulangi kuis ' + HAL[m][0] + '.' : HAL[m][0] + ' belum selesai.') + '</span><a class="btn btn-ghost" href="' + HAL[m][1] + '">Ke ' + HAL[m][0] + '</a>';
      ul.appendChild(li);
    });
  }
});
