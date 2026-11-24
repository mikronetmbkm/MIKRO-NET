/* Mikronet — Simulasi Pertemuan 3 (multi-halaman): MikroTik dan Winbox.
   Misi 1: kenali Winbox · Misi 2: IP router & PC, uji ping · Misi 3: DHCP Client, DNS, NAT, firewall filter.
   Skor akhir dilaporkan ke MIKRO-NET: parent.postMessage({type:'mikronet:hasil', skor, skorMaks:100}, '*'). */
document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var $ = function (id) { return document.getElementById(id); };
  var halaman = document.body.getAttribute('data-halaman') || '';

  /* ---------- Penyimpanan ----------
     Di dalam iframe bersandbox, localStorage tidak dipakai. Progres disimpan di window.name (bertahan antarhalaman
     dalam satu iframe), dengan localStorage sebagai cadangan bila game dibuka langsung di browser. */
  var KUNCI = 'mikronet_p3_v1';
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
    m1: ['m1_login', 'm1_menu', 'm1_reset'],
    m2: ['m2_ip', 'm2_pc', 'm2_ping'],
    m3: ['m3_dhcp', 'm3_dns', 'm3_nat', 'm3_fw']
  };
  var NAMA_TUGAS = {
    m1_login: 'masuk lewat Neighbors', m1_menu: 'cari 5 menu', m1_reset: 'reset tanpa konfigurasi bawaan',
    m2_ip: 'IP ether2 & ether3', m2_pc: 'IP PC1 & PC2', m2_ping: 'ping dua arah',
    m3_dhcp: 'DHCP Client ether1', m3_dns: 'DNS', m3_nat: 'NAT masquerade', m3_fw: 'uji firewall filter'
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
    awan: '<svg viewBox="0 0 64 64" aria-hidden="true"><path d="M18 46h30a10 10 0 0 0 0-20 14 14 0 0 0-27-3A11 11 0 0 0 18 46z" fill="#009BFF"/><path d="M24 38h18" stroke="#fff" stroke-width="3" stroke-linecap="round"/></svg>',
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
      { q: 'Sistem operasi yang berjalan di router MikroTik disebut…', opsi: ['Windows Server', 'RouterOS', 'Cisco IOS', 'Android'], benar: 1,
        jelas: 'Router MikroTik menjalankan <b>RouterOS</b>. Winbox hanyalah aplikasi untuk mengaturnya.' },
      { q: 'Fungsi utama aplikasi <b>Winbox</b> adalah…', opsi: ['Mengatur router MikroTik lewat tampilan grafis', 'Membuat topologi di Packet Tracer', 'Mengganti IP PC Windows', 'Mempercepat internet'], benar: 0,
        jelas: 'Winbox adalah aplikasi GUI untuk mengatur router MikroTik tanpa mengetik perintah satu per satu.' },
      { q: 'Di tab Neighbors, router tampil dengan IP <span class="mono">0.0.0.0</span>. Artinya…', opsi: ['Router rusak', 'Router sudah tersambung ke internet', 'Router belum punya IP, jadi masuk lewat MAC Address', 'Kabelnya salah'], benar: 2,
        jelas: '0.0.0.0 berarti belum ada IP. Winbox tetap bisa masuk memakai MAC Address karena PC dan router ada di kabel yang sama.' },
      { q: 'User bawaan router MikroTik yang baru di-reset adalah…', opsi: ['root, password root', 'admin, password admin', 'user, password 1234', 'admin, password kosong'], benar: 3,
        jelas: 'Router yang di-reset memakai user <b>admin</b> tanpa password.' },
      { q: 'Mengapa router di-reset dengan pilihan <b>No Default Configuration</b> untuk praktik ini?', opsi: ['Supaya internet lebih cepat', 'Supaya ether2 dan ether3 tidak tergabung dalam bridge bawaan dan bisa diberi IP sendiri-sendiri', 'Supaya password hilang', 'Supaya Winbox tidak bisa masuk'], benar: 1,
        jelas: 'Konfigurasi bawaan menggabungkan ether2–ether5 dalam satu bridge (192.168.88.1). Praktik ini butuh ether2 dan ether3 sebagai dua jaringan terpisah.' },
      { q: 'Menu Winbox untuk memberi IP pada port router adalah…', opsi: ['Interfaces', 'System → Identity', 'IP → Addresses', 'Bridge'], benar: 2,
        jelas: '<b>IP → Addresses</b> berisi daftar IP pada setiap interface router.' },
      { q: 'Menu Winbox untuk menjalankan perintah seperti <span class="mono">ping</span> langsung di router adalah…', opsi: ['New Terminal', 'Files', 'Log', 'Quick Set'], benar: 0,
        jelas: '<b>New Terminal</b> membuka jendela perintah RouterOS. Di sana kamu bisa mengetik ping, misalnya ping 8.8.8.8.' },
      { q: 'Pada praktik hari ini, port <b>ether1</b> dipakai sebagai…', opsi: ['Port untuk PC1', 'Port WAN yang tersambung ke internet', 'Port untuk PC2', 'Port cadangan yang tidak dipakai'], benar: 1,
        jelas: 'ether1 = WAN (ke internet). ether2 ke PC1 dan ether3 ke PC2 adalah LAN.' }
    ],
    m2: [
      { q: 'ether2 diberi IP <span class="mono">192.168.11.1/24</span>. Default gateway PC1 yang benar adalah…', opsi: ['192.168.11.2', '192.168.22.1', '192.168.11.0', '192.168.11.1'], benar: 3,
        jelas: 'Gateway PC = IP port router yang tersambung dengannya. PC1 tersambung ke ether2 (192.168.11.1).' },
      { q: 'Mengapa alamat di IP → Addresses harus ditulis dengan <b>/24</b>?', opsi: ['Supaya tampilannya rapi', 'Tanpa prefix, router menganggap /32 sehingga jaringannya hanya 1 alamat dan PC tidak dianggap satu jaringan', 'Supaya bisa ke internet', '/24 adalah nomor port'], benar: 1,
        jelas: 'Tanpa prefix, RouterOS memakai /32. Network-nya hanya alamat itu sendiri, sehingga router tidak mengenali jaringan PC.' },
      { q: 'Subnet mask yang sama dengan prefix <b>/24</b> adalah…', opsi: ['255.255.255.0', '255.255.0.0', '255.255.255.255', '255.0.0.0'], benar: 0,
        jelas: '/24 berarti 24 bit pertama bernilai 1: 255.255.255.0.' },
      { q: 'PC1 (192.168.11.2) bisa ping PC2 (192.168.22.2) walaupun berbeda jaringan karena…', opsi: ['Keduanya memakai DNS 8.8.8.8', 'Router mengenal kedua jaringan dan meneruskan paket di antara ether2 dan ether3', 'Subnet mask keduanya sama', 'Kabelnya cross-over'], benar: 1,
        jelas: 'Kedua jaringan terpasang langsung di router (connected), jadi router tahu harus meneruskan paket ke port mana.' },
      { q: 'PC1 bisa ping 192.168.11.1 dan 192.168.22.1, tetapi ping ke PC2 <b>Request timed out</b>. Penyebab paling mungkin…', opsi: ['IP ether2 salah', 'Gateway PC1 salah', 'Windows Firewall PC2 memblokir ping masuk', 'Router belum di-reset'], benar: 2,
        jelas: 'Kedua gateway terjangkau, berarti router dan PC1 sudah benar. Paket sampai di PC2, tetapi Windows Firewall PC2 menolak ping.' },
      { q: 'Pesan <span class="mono">Reply from 192.168.11.2: Destination host unreachable</span> saat PC1 ping PC2 biasanya berarti…', opsi: ['PC2 mati', 'PC1 tidak menemukan gatewaynya, misalnya gateway salah tulis', 'Ping berhasil', 'DNS salah'], benar: 1,
        jelas: 'Pesan itu datang dari PC1 sendiri: ia tidak bisa mengirim paket karena gateway tidak ditemukan di jaringannya.' },
      { q: 'Urutan uji ping yang baik saat mencari masalah adalah…', opsi: ['Langsung ke google.com', 'PC tujuan dulu, lalu gateway', 'Gateway sendiri → gateway jaringan lain → PC lain', 'Acak saja'], benar: 2,
        jelas: 'Uji dari yang terdekat. Kalau gateway sendiri saja gagal, masalahnya ada di PC atau kabel, belum perlu memeriksa yang jauh.' },
      { q: 'Alamat <span class="mono">192.168.11.0</span> tidak boleh dipakai sebagai IP PC karena…', opsi: ['Itu alamat network (alamat jaringannya sendiri)', 'Itu alamat broadcast', 'Itu milik router', 'Angkanya terlalu kecil'], benar: 0,
        jelas: 'Alamat pertama di jaringan /24 adalah network address. Host memakai .1 sampai .254.' }
    ],
    m3: [
      { q: 'Status DHCP Client yang menandakan router <b>sudah mendapat IP</b> dari jaringan internet adalah…', opsi: ['searching', 'bound', 'running', 'disabled'], benar: 1,
        jelas: '<b>bound</b> = IP sudah diperoleh. <b>searching</b> = masih mencari server DHCP (misalnya karena tidak ada internet).' },
      { q: 'Apa gunanya pilihan <b>Add Default Route: yes</b> pada DHCP Client?', opsi: ['Membuat router punya jalan ke semua tujuan di internet lewat ether1', 'Membagikan IP ke PC', 'Mengaktifkan DNS', 'Memblokir ping'], benar: 0,
        jelas: 'Default route (0.0.0.0/0) adalah jalan untuk semua tujuan yang tidak dikenal. Tanpa itu, router tidak tahu harus mengirim paket internet ke mana.' },
      { q: 'Fungsi <b>NAT masquerade</b> pada ether1 adalah…', opsi: ['Memblokir situs tertentu', 'Membagikan IP otomatis ke PC', 'Mengganti IP privat PC dengan IP router di ether1 supaya balasan dari internet bisa kembali', 'Menerjemahkan nama domain'], benar: 2,
        jelas: 'IP privat (192.168.x.x) tidak dikenal di internet. Masquerade memakai IP router di ether1 sebagai alamat pengirim.' },
      { q: 'Pengaturan aturan NAT masquerade yang benar untuk praktik ini adalah…', opsi: ['chain dstnat, out-interface ether2', 'chain srcnat, out-interface ether1, action masquerade', 'chain srcnat, out-interface ether3', 'chain forward, action drop'], benar: 1,
        jelas: 'srcnat mengubah alamat pengirim; out-interface ether1 karena ether1 adalah jalan keluar ke internet.' },
      { q: 'PC1 berhasil ping 8.8.8.8, tetapi ping google.com menampilkan <span class="mono">could not find host</span>. Yang perlu diperiksa adalah…', opsi: ['NAT', 'DNS', 'IP ether2', 'Kabel PC1'], benar: 1,
        jelas: 'Jalan ke internet sudah ada (8.8.8.8 berhasil). Yang gagal adalah menerjemahkan nama google.com, yaitu tugas DNS.' },
      { q: 'Router bisa ping 8.8.8.8, tetapi PC1 tidak bisa. Penyebab paling mungkin…', opsi: ['DHCP Client belum bound', 'NAT masquerade belum dibuat', 'DNS salah', 'Router belum di-reset'], benar: 1,
        jelas: 'Router sendiri sudah tersambung ke internet. Paket dari PC1 butuh NAT agar alamat privatnya diganti.' },
      { q: 'Aturan firewall untuk memblokir ping dari PC1 ke PC2 (melewati router) memakai chain…', opsi: ['input', 'output', 'forward', 'srcnat'], benar: 2,
        jelas: '<b>forward</b> = lalu lintas yang melewati router. <b>input</b> = menuju router itu sendiri. <b>output</b> = keluar dari router.' },
      { q: 'Aturan forward <span class="mono">src 192.168.11.0/24, dst 192.168.22.0/24, icmp, drop</span> aktif. Ping PC2 → PC1 juga gagal karena…', opsi: ['Aturannya berlaku dua arah otomatis', 'Ping PC2 sampai di PC1, tetapi balasan PC1 (dari 11.x ke 22.x) cocok dengan aturan dan dibuang', 'PC2 belum punya gateway', 'Windows Firewall PC2 aktif'], benar: 1,
        jelas: 'Ping butuh pergi dan pulang. Balasan dari PC1 ke PC2 cocok dengan aturan drop, jadi PC2 tidak pernah menerima balasan.' }
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

  /* ============================================================ MODEL JARINGAN (dipakai Misi 2 & 3)
     Router: daftar IP (addr), DHCP Client (dhcpc), DNS (dns), NAT (nat), filter (filter).
     PC1 di ether2, PC2 di ether3. Hasil ping dihitung dari konfigurasi yang sedang tersimpan. */
  var PORT_PC = { pc1: 'ether2', pc2: 'ether3' };
  var WAN = { ip: '10.105.10.160', p: 24, gw: '10.105.10.1', dns: '203.6.148.2' };
  var GOOGLE = '142.251.10.101';
  function addrList() { return ambil('addr') || []; }
  function pcCfg() { var c = ambil('pc') || {}; c.pc1 = c.pc1 || {}; c.pc2 = c.pc2 || {}; return c; }
  function winfw() { return ambil('winfw') || { pc1: false, pc2: false }; }
  function dhcpc() { return ambil('dhcpc') || []; }
  function dnsCfg() { return ambil('dns') || { srv: '', remote: false }; }
  function natList() { return ambil('nat') || []; }
  function filterList() { return ambil('filter') || []; }
  function wan() {
    var c = dhcpc().filter(function (x) { return x.intf === 'ether1'; })[0];
    return c ? { ip: WAN.ip, p: WAN.p, defrt: c.defrt === 'yes', peerdns: !!c.peerdns } : null;
  }
  function netDari(ip, p) { var b = Math.pow(2, 32 - p); return intKeIP(Math.floor(ipKeInt(ip) / b) * b); }
  // "a.b.c.d/p" → {ip, p, tanpaPrefix}; null bila tidak valid
  function bacaAlamat(s) {
    s = String(s || '').trim(); var m = s.split('/');
    if (m.length > 2 || !parseIP(m[0])) return null;
    if (m.length === 1) return { ip: m[0].trim(), p: 32, tanpaPrefix: true };
    if (!/^\d{1,2}$/.test(m[1].trim())) return null;
    var p = parseInt(m[1], 10); if (p < 1 || p > 32) return null;
    return { ip: m[0].trim(), p: p, tanpaPrefix: false };
  }
  function cidrCocok(spec, ip) {
    if (!spec) return true;
    var a = bacaAlamat(spec); if (!a) return false;
    return dalamJaringan(ip, a.ip, a.p);
  }
  function terhubung() {
    var d = addrList().map(function (a) { return { ip: a.ip, p: a.p, intf: a.intf }; });
    var w = wan(); if (w) d.push({ ip: w.ip, p: w.p, intf: 'ether1' });
    return d;
  }
  function milikRouter(ip) { return terhubung().some(function (a) { return samaIP(a.ip, ip); }); }
  function rute(ip) {
    var best = null;
    terhubung().forEach(function (a) { if (dalamJaringan(ip, a.ip, a.p) && (!best || a.p > best.p)) best = a; });
    if (best) return best;
    var w = wan(); if (w && w.defrt) return { intf: 'ether1', p: 0, ip: w.ip, def: true };
    return null;
  }
  // Nomor aturan filter (1, 2, …) yang membuang paket; 0 = lolos.
  function diblokir(chain, src, dst, proto) {
    var L = filterList();
    for (var i = 0; i < L.length; i++) {
      var r = L[i]; if (r.mati || r.chain !== chain) continue;
      if (!cidrCocok(r.src, src) || !cidrCocok(r.dst, dst)) continue;
      if (r.proto && r.proto !== proto) continue;
      return r.aksi === 'drop' ? i + 1 : 0;
    }
    return 0;
  }
  function natUntuk(ip) {
    return natList().some(function (x) { return x.chain === 'srcnat' && x.aksi === 'masquerade' && (x.out === 'ether1' || x.out === ''); });
  }
  function NM(k) { return k.toUpperCase(); }

  // Perjalanan paket dari PC ke tujuan T. Hasil: {jenis:'ok'|'rto'|'unreach'|'general', alasan, jalur, putusDi, ttl, waktu, dari}
  function jalurPC(k, T, proto) {
    var c = pcCfg()[k], nm = NM(k), port = PORT_PC[k], p = maskKePrefix(String(c.mask || '').trim());
    if (!parseIP(c.ip || '') || p < 1) return { jenis: 'general', jalur: [nm], putusDi: nm, alasan: 'IP atau subnet mask ' + nm + ' belum diisi dengan benar.' };
    var diPort = addrList().filter(function (a) { return a.intf === port; });
    if (dalamJaringan(T, c.ip, p)) {
      if (samaIP(T, c.ip)) return { jenis: 'ok', jalur: [nm], ttl: 128, waktu: 0 };
      var e = diPort.filter(function (a) { return samaIP(a.ip, T); })[0];
      if (!e) return { jenis: 'unreach', dari: c.ip, jalur: [nm], putusDi: nm, alasan: nm + ' menganggap ' + T + ' satu jaringan dengannya, tetapi tidak ada perangkat ber-IP itu di kabel ' + nm + ' (' + port + ').' };
      if (!dalamJaringan(c.ip, e.ip, e.p)) return { jenis: 'rto', jalur: [nm, 'MikroTik'], putusDi: 'MikroTik', alasan: 'Router punya ' + e.ip + ' tetapi dengan prefix /' + e.p + ', jadi tidak menganggap ' + nm + ' satu jaringan dan tidak bisa membalas. Tulis /24.' };
      var n0 = diblokir('input', c.ip, T, proto);
      if (n0) return { jenis: 'rto', jalur: [nm, 'MikroTik'], putusDi: 'MikroTik', alasan: 'Diblokir firewall filter (aturan #' + n0 + ', chain input).', olehFilter: true };
      return { jenis: 'ok', jalur: [nm, 'MikroTik'], ttl: 64, waktu: 0 };
    }
    var g = String(c.gw || '').trim();
    if (!parseIP(g)) return { jenis: 'general', jalur: [nm], putusDi: nm, alasan: 'Default gateway ' + nm + ' kosong, jadi ' + nm + ' tidak tahu harus mengirim paket ke jaringan lain lewat mana.' };
    var gw = diPort.filter(function (a) { return samaIP(a.ip, g); })[0];
    if (!gw || !dalamJaringan(g, c.ip, p)) {
      var lain = addrList().filter(function (a) { return samaIP(a.ip, g); })[0];
      return { jenis: 'unreach', dari: c.ip, jalur: [nm], putusDi: nm, alasan: 'Gateway ' + g + ' tidak ditemukan di kabel ' + nm + ' (' + port + '). ' +
        (lain ? 'Alamat itu ada di ' + lain.intf + ', bukan di ' + port + '.' : 'Pastikan IP ' + port + ' di router sama dengan gateway ' + nm + '.') };
    }
    if (!dalamJaringan(c.ip, gw.ip, gw.p)) return { jenis: 'rto', jalur: [nm, 'MikroTik'], putusDi: 'MikroTik', alasan: 'IP ' + gw.ip + ' di router memakai /' + gw.p + ', jadi router tidak menganggap ' + nm + ' satu jaringan dan tidak bisa membalas. Tulis /24.' };
    if (milikRouter(T)) {
      var n1 = diblokir('input', c.ip, T, proto);
      if (n1) return { jenis: 'rto', jalur: [nm, 'MikroTik'], putusDi: 'MikroTik', alasan: 'Diblokir firewall filter (aturan #' + n1 + ', chain input).', olehFilter: true };
      return { jenis: 'ok', jalur: [nm, 'MikroTik'], ttl: 64, waktu: 0 };
    }
    var r = rute(T);
    if (!r) return { jenis: 'rto', jalur: [nm, 'MikroTik'], putusDi: 'MikroTik', alasan: 'Router tidak punya jalan ke ' + T + '. ' + (/^192\.168\./.test(T) ? 'Cek IP di IP → Addresses.' : 'Belum ada rute ke internet: DHCP Client ether1 belum bound atau Add Default Route bernilai no.') };
    var n2 = diblokir('forward', c.ip, T, proto);
    if (n2) return { jenis: 'rto', jalur: [nm, 'MikroTik'], putusDi: 'MikroTik', alasan: 'Paket dibuang router oleh firewall filter (aturan #' + n2 + ', chain forward).', olehFilter: true };
    if (r.intf === 'ether1') {
      if (!natUntuk(c.ip)) return { jenis: 'rto', jalur: [nm, 'MikroTik', 'Internet'], putusDi: 'Internet', alasan: 'Paket keluar ke internet dengan alamat pengirim ' + c.ip + ' (IP privat). Tanpa NAT masquerade, balasan dari internet tidak bisa kembali.' };
      var n3 = diblokir('forward', T, c.ip, proto);
      if (n3) return { jenis: 'rto', jalur: [nm, 'MikroTik', 'Internet'], putusDi: 'MikroTik', alasan: 'Balasan dari internet dibuang firewall filter (aturan #' + n3 + ').', olehFilter: true };
      return { jenis: 'ok', jalur: [nm, 'MikroTik', 'Internet'], ttl: 116, waktu: 22 };
    }
    var t = r.intf === 'ether2' ? 'pc1' : r.intf === 'ether3' ? 'pc2' : null;
    var tc = t ? pcCfg()[t] : null;
    if (!t || !samaIP(tc.ip, T)) return { jenis: 'rto', jalur: [nm, 'MikroTik'], putusDi: 'MikroTik', alasan: 'Router meneruskan paket ke ' + r.intf + ', tetapi tidak ada perangkat ber-IP ' + T + ' di sana.' };
    var nt = NM(t);
    if (proto === 'icmp' && !winfw()[t]) return { jenis: 'rto', jalur: [nm, 'MikroTik', nt], putusDi: nt, alasan: 'Ping sampai di ' + nt + ', tetapi Windows Firewall ' + nt + ' memblokir ping masuk. Izinkan ping masuk di ' + nt + '.' };
    var tp = maskKePrefix(String(tc.mask || '').trim());
    var tgw = addrList().filter(function (a) { return a.intf === r.intf && samaIP(a.ip, tc.gw); })[0];
    if (tp < 1 || !tgw) return { jenis: 'rto', jalur: [nm, 'MikroTik', nt], putusDi: nt, alasan: nt + ' menerima ping, tetapi tidak tahu jalan pulang: gateway ' + nt + ' (' + (tc.gw || 'kosong') + ') bukan IP router di ' + r.intf + '.' };
    var n4 = diblokir('forward', T, c.ip, proto);
    if (n4) return { jenis: 'rto', jalur: [nm, 'MikroTik', nt], putusDi: 'MikroTik', alasan: 'Ping sampai di ' + nt + ', tetapi balasannya dibuang router oleh firewall filter (aturan #' + n4 + ').', olehFilter: true };
    return { jenis: 'ok', jalur: [nm, 'MikroTik', nt], ttl: 127, waktu: 1 };
  }
  function jalurRouter(T) {
    if (milikRouter(T)) return { jenis: 'ok', jalur: ['MikroTik'], ttl: 64, waktu: 0 };
    var r = rute(T);
    if (!r) return { jenis: 'rto', jalur: ['MikroTik'], putusDi: 'MikroTik', alasan: 'Router belum punya jalan ke internet. Pastikan DHCP Client ether1 bound dan Add Default Route bernilai yes.' };
    if (r.intf === 'ether1') return { jenis: 'ok', jalur: ['MikroTik', 'Internet'], ttl: 117, waktu: 22 };
    return { jenis: 'ok', jalur: ['MikroTik'], ttl: 128, waktu: 0 };
  }
  // Menerjemahkan nama. dari = 'router' | 'pc1' | 'pc2'. Hasil {ip} atau {gagal}
  function resolve(dari, nama) {
    if (dari === 'router') {
      var d = dnsCfg(), w = wan();
      var adaServer = parseIP(String(d.srv || '').trim()) || (w && w.peerdns);
      if (!adaServer) return { gagal: 'Router belum punya server DNS. Isi Servers di IP → DNS.' };
      if (!(w && w.defrt)) return { gagal: 'Server DNS tidak bisa dihubungi karena router belum tersambung ke internet.' };
      return { ip: GOOGLE };
    }
    var c = pcCfg()[dari], dns = String(c.dns || '').trim();
    if (!parseIP(dns)) return { gagal: 'Preferred DNS server ' + NM(dari) + ' kosong.' };
    if (milikRouter(dns)) {
      if (!dnsCfg().remote) return { gagal: NM(dari) + ' memakai router (' + dns + ') sebagai DNS, tetapi Allow Remote Requests belum dicentang.' };
      var h0 = jalurPC(dari, dns, 'udp'); if (h0.jenis !== 'ok') return { gagal: 'Server DNS ' + dns + ' tidak terjangkau. ' + (h0.alasan || '') };
      return resolve('router', nama);
    }
    var h = jalurPC(dari, dns, 'udp');
    if (h.jenis !== 'ok') return { gagal: NM(dari) + ' tidak bisa menghubungi server DNS ' + dns + '. ' + (h.alasan || '') };
    return { ip: GOOGLE };
  }

  /* ---------- Teks keluaran ping ---------- */
  function teksWindows(cmd, T, nama, h) {
    var b = ['C:\\Users\\Siswa>ping ' + cmd, ''];
    if (h.jenis === 'nohost') { b.push('Ping request could not find host ' + nama + '. Please check the name and try again.'); return b; }
    b.push('Pinging ' + (nama ? nama + ' [' + T + ']' : T) + ' with 32 bytes of data:');
    var terima = 0;
    for (var i = 0; i < 4; i++) {
      if (h.jenis === 'ok') { var w = h.waktu + (h.waktu ? i % 3 : 0); b.push('Reply from ' + T + ': bytes=32 time' + (w < 1 ? '<1ms' : '=' + w + 'ms') + ' TTL=' + h.ttl); terima++; }
      else if (h.jenis === 'unreach') { b.push('Reply from ' + h.dari + ': Destination host unreachable.'); terima++; }
      else if (h.jenis === 'general') b.push('PING: transmit failed. General failure.');
      else b.push('Request timed out.');
    }
    b.push('', 'Ping statistics for ' + T + ':', '    Packets: Sent = 4, Received = ' + terima + ', Lost = ' + (4 - terima) + ' (' + ((4 - terima) * 25) + '% loss),');
    return b;
  }
  function teksRouter(arg, T, h, gagalNama) {
    var b = ['[admin@MikroTik] > ping ' + arg + ' count=4'];
    if (gagalNama) { b.push('gagal: nama ' + arg + ' tidak dapat diterjemahkan (DNS)'); return b; }
    b.push('  SEQ HOST                                     SIZE TTL TIME  STATUS');
    var t = 0;
    for (var i = 0; i < 4; i++) {
      var host = (T + '                                         ').slice(0, 41);
      if (h.jenis === 'ok') { b.push('    ' + i + ' ' + host + '56 ' + h.ttl + ' ' + (h.waktu + (h.waktu ? i % 3 : 0)) + 'ms'); t++; }
      else b.push('    ' + i + ' ' + host + '               timeout');
    }
    b.push('    sent=4 received=' + t + ' packet-loss=' + ((4 - t) * 25) + '%');
    return b;
  }

  /* ---------- Animasi ping di panggung ---------- */
  function animasiPing(pg, h, tujuanNode) {
    pg.bersih(); var pk = pg.paket, j = h.jalur.slice();
    if (h.jenis !== 'ok' && j.length === 1) { pg.node(j[0]).classList.add('gagal'); return jeda(300); }
    letakkan(pk, pg.titik(j[0])); labelPaket(pk, 'ping', 'kirim');
    var pr = jeda(150);
    j.slice(1).forEach(function (n) { pr = pr.then(function () { return geser(pk, pg.titik(n), 600); }); });
    return pr.then(function () {
      if (h.jenis === 'ok') {
        if (j.length > 1) pg.node(j[j.length - 1]).classList.add('sampai');
        labelPaket(pk, 'balasan', 'balas');
        var q = Promise.resolve();
        j.slice(0, -1).reverse().forEach(function (n) { q = q.then(function () { return geser(pk, pg.titik(n), 600); }); });
        return q.then(function () { pk.hidden = true; pg.node(j[0]).classList.add('sampai'); });
      }
      labelPaket(pk, 'gagal', 'gagal');
      var nd = pg.node(h.putusDi || j[j.length - 1]); if (nd) nd.classList.add('gagal');
      return jeda(700).then(function () { pk.hidden = true; });
    });
  }
  function nodeLan(c) {
    return {
      PC1: { x: 14, y: 72, ikon: 'pc', ket: (c && c.pc1.ip) || '?' },
      MikroTik: { x: 50, y: 30, ikon: 'router', ket: 'router' },
      PC2: { x: 86, y: 72, ikon: 'pc', ket: (c && c.pc2.ip) || '?' }
    };
  }

  // Kemungkinan menu kiri Winbox
  var MENU = [
    ['Quick Set'], ['CAPsMAN'], ['Interfaces'], ['Wireless'], ['Bridge'], ['PPP'], ['Switch'], ['Mesh'],
    ['IP', ['ARP', 'Accounting', 'Addresses', 'Cloud', 'DHCP Client', 'DHCP Relay', 'DHCP Server', 'DNS', 'Firewall', 'Hotspot', 'IPsec', 'Neighbors', 'Pool', 'Routes', 'Services', 'Settings']],
    ['MPLS', ['LDP Settings', 'MPLS Interface']], ['Routing', ['BGP', 'OSPF', 'RIP', 'Filters']],
    ['System', ['Clock', 'Identity', 'LEDs', 'Packages', 'Password', 'Reboot', 'Reset Configuration', 'Resources', 'Routerboard', 'Scheduler', 'Shutdown', 'Users']],
    ['Queues'], ['Files'], ['Log'], ['Tools', ['Bandwidth Test', 'IP Scan', 'Netwatch', 'Ping', 'Torch', 'Traceroute']], ['New Terminal'], ['Exit']
  ];
  var KET_MENU = {
    'Quick Set': 'Quick Set adalah pengaturan cepat bawaan. Untuk praktik ini semuanya diatur manual.',
    'Bridge': 'Bridge menggabungkan beberapa port menjadi satu jaringan.',
    'Wireless': 'Wireless mengatur WiFi pada router yang punya antena.',
    'IP>ARP': 'ARP berisi pasangan IP dan MAC perangkat yang dikenal router.',
    'IP>DHCP Server': 'DHCP Server membagikan IP ke perangkat lain. Router kita justru perlu meminta IP.',
    'IP>DNS': 'DNS mengatur penerjemah nama domain.',
    'IP>Routes': 'Routes berisi tabel routing router.',
    'IP>Pool': 'Pool berisi rentang IP untuk DHCP Server.',
    'IP>Neighbors': 'IP → Neighbors mengatur penemuan router tetangga.',
    'System>Identity': 'Identity mengganti nama router.',
    'System>Reboot': 'Reboot hanya menyalakan ulang router tanpa menghapus pengaturan.',
    'System>Users': 'Users mengatur akun login router.',
    'System>Password': 'Password mengganti password akun yang sedang login.',
    'Tools>Ping': 'Tools → Ping juga bisa, tetapi yang diminta adalah tempat mengetik perintah apa saja.',
    'Log': 'Log berisi catatan kejadian di router.',
    'Files': 'Files berisi berkas yang tersimpan di router.',
    'Interfaces': 'Interfaces menampilkan daftar port fisik beserta statusnya.',
    'IP>Addresses': 'IP → Addresses berisi daftar IP pada setiap port.',
    'New Terminal': 'New Terminal membuka jendela perintah router.',
    'IP>DHCP Client': 'DHCP Client membuat port meminta IP otomatis.',
    'IP>Firewall': 'Firewall berisi Filter Rules dan NAT.'
  };
  var SOAL_MENU = [
    { t: 'Melihat daftar <b>port fisik</b> router beserta statusnya (aktif atau tidak).', j: 'Interfaces', win: 'Interface List' },
    { t: 'Memberi <b>IP Address</b> pada ether2.', j: 'IP>Addresses', win: 'Address List' },
    { t: 'Membuka tempat mengetik <b>perintah</b> seperti ping langsung di router.', j: 'New Terminal', win: 'Terminal' },
    { t: 'Mengatur ether1 agar <b>meminta IP otomatis</b> dari jaringan internet.', j: 'IP>DHCP Client', win: 'DHCP Client' },
    { t: 'Membuat aturan <b>NAT masquerade</b> agar PC bisa ke internet.', j: 'IP>Firewall', win: 'Firewall' }
  ];

  /* ============================================================ BERANDA */
  if (halaman === 'beranda') {
    var p = prog(), d0 = muat();
    var kartu = [
      { id: 'm1', buka: true, mulai: d0.menuIdx || (d0.kuis && d0.kuis.m1) },
      { id: 'm2', buka: !!p.m1, mulai: d0.addr || d0.pc || (d0.kuis && d0.kuis.m2) },
      { id: 'm3', buka: !!p.m2, mulai: d0.dhcpc || d0.nat || d0.filter || (d0.kuis && d0.kuis.m3) }
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

  /* ============================================================ MISI 1: KENALI WINBOX */
  if (halaman === 'winbox') {
    // A. Login
    document.querySelectorAll('.wb-sel').forEach(function (b) {
      b.addEventListener('click', function () {
        $('lg-ke').value = b.getAttribute('data-isi');
        document.querySelectorAll('.wb-sel').forEach(function (x) { x.closest('tr').classList.toggle('dipilih', x === b); });
        $('lg-status').textContent = 'Connect To diisi ' + b.getAttribute('data-isi') + '.';
      });
    });
    $('lg-connect').addEventListener('click', function () {
      var ke = $('lg-ke').value.trim().toUpperCase(), user = $('lg-user').value.trim(), pass = $('lg-pass').value;
      var cek = [];
      if (!ke) cek.push(['err', 'Connect To masih kosong. Klik router di tab <b>Neighbors</b>.']);
      else if (ke === '0.0.0.0') cek.push(['err', '<span class="mono">0.0.0.0</span> berarti router <b>belum punya IP</b>, jadi tidak bisa dihubungi lewat IP. Klik <b>MAC Address</b>-nya.']);
      else if (ke !== 'F4:1E:57:95:7E:5B') cek.push(['err', 'Router dengan alamat ' + esc(ke) + ' tidak ditemukan. Pilih dari tab Neighbors.']);
      if (!user) cek.push(['err', 'Login masih kosong. User bawaan MikroTik adalah <span class="mono">admin</span>.']);
      else if (user.toLowerCase() !== 'admin') cek.push(['err', 'User <span class="mono">' + esc(user) + '</span> tidak ada. User bawaan MikroTik adalah <span class="mono">admin</span>.']);
      if (pass !== '') cek.push(['err', 'Password salah. Router yang baru di-reset <b>belum punya password</b>: kosongkan kolom Password.']);
      if (!cek.length) {
        cek.push(['ok', 'Connected to F4:1E:57:95:7E:5B. Kamu berhasil masuk ke router lewat MAC Address.']);
        cek.push(['info', 'Di lab, router yang sudah diberi password oleh guru tetap memakai password itu.']);
        $('lg-status').textContent = 'Connected.';
        if (!prog().m1_login) setProg({ m1_login: true });
        segarkanSelesai('m1');
      } else $('lg-status').textContent = 'Could not connect.';
      isiCek($('cek-login'), cek);
    });

    // B. Cari menu
    var idx = ambil('menuIdx') || 0, subAktif = null;
    function renderSoalMenu() {
      var el = $('menu-soal');
      if (idx >= SOAL_MENU.length) { el.innerHTML = '<b>Semua menu ditemukan ✓</b>'; return; }
      el.innerHTML = '<span class="menu-no">' + (idx + 1) + '/' + SOAL_MENU.length + '</span> Klik menu untuk: ' + SOAL_MENU[idx].t;
    }
    function renderMenu() {
      var nav = $('wb-menu'); nav.innerHTML = '';
      MENU.forEach(function (m) {
        var b = document.createElement('button'); b.type = 'button'; b.className = 'wb-menu-item' + (m[1] ? ' induk' : '') + (subAktif === m[0] ? ' terbuka' : '');
        b.innerHTML = '<span class="wb-ikon" aria-hidden="true"></span>' + esc(m[0]) + (m[1] ? '<span class="wb-panah" aria-hidden="true">▸</span>' : '');
        b.addEventListener('click', function () {
          if (m[1]) { subAktif = subAktif === m[0] ? null : m[0]; renderMenu(); renderSub(); return; }
          subAktif = null; renderMenu(); renderSub(); pilih(m[0]);
        });
        nav.appendChild(b);
      });
    }
    function renderSub() {
      var s = $('wb-sub');
      var m = MENU.filter(function (x) { return x[0] === subAktif; })[0];
      if (!m) { s.hidden = true; s.innerHTML = ''; return; }
      s.hidden = false; s.innerHTML = '<p class="wb-sub-judul">' + esc(m[0]) + ' ▸</p>';
      m[1].forEach(function (n) {
        var b = document.createElement('button'); b.type = 'button'; b.className = 'wb-menu-item'; b.textContent = n;
        b.addEventListener('click', function () { var jalur = m[0] + '>' + n; subAktif = null; renderMenu(); renderSub(); pilih(jalur); });
        s.appendChild(b);
      });
    }
    function pilih(jalur) {
      var tampil = jalur.replace('>', ' → '), h = $('hasil-menu');
      if (idx >= SOAL_MENU.length) { $('wb-layar').innerHTML = '<div class="wb-win wb-mini"><div class="wb-title"><span>' + esc(tampil) + '</span></div><div class="wb-body redup-teks">Jendela terbuka.</div></div>'; return; }
      var s = SOAL_MENU[idx];
      if (jalur === s.j) {
        $('wb-layar').innerHTML = '<div class="wb-win wb-mini"><div class="wb-title"><span>' + esc(s.win) + '</span><span aria-hidden="true">✕</span></div><div class="wb-body redup-teks">Jendela ' + esc(s.win) + ' terbuka.</div></div>';
        h.className = 'hasil-uji berhasil'; h.innerHTML = 'Benar: <b>' + esc(tampil) + '</b>. ' + esc(KET_MENU[jalur] || '');
        idx++; taruh('menuIdx', idx); renderSoalMenu();
        if (idx >= SOAL_MENU.length && !prog().m1_menu) { setProg({ m1_menu: true }); segarkanSelesai('m1'); }
      } else {
        h.className = 'hasil-uji gagal'; h.innerHTML = 'Bukan <b>' + esc(tampil) + '</b>. ' + esc(KET_MENU[jalur] || 'Menu itu dipakai untuk keperluan lain.') + ' Coba lagi.';
      }
    }
    renderSoalMenu(); renderMenu();

    // C. Reset
    $('rs-reset').addEventListener('click', function () { $('rs-tanya').hidden = false; $('rs-ya').focus(); });
    $('rs-batal').addEventListener('click', function () { $('rs-tanya').hidden = true; ['rs-keep', 'rs-caps', 'rs-nodef', 'rs-nobak'].forEach(function (k) { $(k).checked = false; }); });
    $('rs-tidak').addEventListener('click', function () { $('rs-tanya').hidden = true; });
    $('rs-ya').addEventListener('click', function () {
      $('rs-tanya').hidden = true;
      var win = $('wb-reset'); win.classList.add('restart');
      isiCek($('cek-reset'), [['info', 'Router memulai ulang… Winbox terputus.']]);
      jeda(1400).then(function () {
        win.classList.remove('restart');
        var cek = [];
        if ($('rs-nodef').checked) {
          cek.push(['ok', 'Router menyala lagi <b>tanpa konfigurasi bawaan</b>: belum ada IP, bridge, DHCP Server, maupun aturan firewall.']);
          cek.push(['info', 'Masuk lagi lewat tab <b>Neighbors</b> dengan MAC Address. IP router kembali 0.0.0.0.']);
          if ($('rs-keep').checked) cek.push(['info', 'Keep User Configuration menyimpan user dan password lama.']);
          if ($('rs-caps').checked) cek.push(['err', 'CAPS Mode untuk mengubah router menjadi access point yang dikendalikan CAPsMAN. Tidak dipakai di praktik ini.']);
          else if (!prog().m1_reset) { setProg({ m1_reset: true }); segarkanSelesai('m1'); }
        } else {
          cek.push(['err', 'Router menyala dengan <b>konfigurasi bawaan</b>: ether2–ether5 tergabung di <span class="mono">bridge</span> dengan IP 192.168.88.1/24. ether2 dan ether3 tidak bisa dijadikan dua jaringan terpisah.']);
          cek.push(['info', 'Centang <b>No Default Configuration</b>, lalu reset lagi.']);
        }
        isiCek($('cek-reset'), cek);
      });
    });

    pasangKuis('m1', function () { segarkanSelesai('m1'); });
    segarkanSelesai('m1');
    $('btn-m1-selesai').addEventListener('click', function () { window.location.href = 'ip.html'; });
  }

  /* ============================================================ MISI 2: PASANG IP & UJI PING */
  if (halaman === 'ip') {
    bannerUrut('m1', 'Misi 1', 'winbox.html');
    var pilihAddr = -1, ubahIdx = -1;
    function simpanAddr(L) { taruh('addr', L); taruh('pingOk', {}); }
    function renderAddr() {
      var L = addrList(), t = $('addr-tabel');
      t.innerHTML = '<thead><tr><th>Address</th><th>Network</th><th>Interface</th></tr></thead><tbody>' +
        (L.length ? L.map(function (a, i) { return '<tr data-i="' + i + '" tabindex="0" class="' + (i === pilihAddr ? 'dipilih' : '') + '"><td>' + esc(a.ip + '/' + a.p) + '</td><td>' + esc(netDari(a.ip, a.p)) + '</td><td>' + esc(a.intf) + '</td></tr>'; }).join('')
          : '<tr class="kosong"><td colspan="3">0 items</td></tr>') + '</tbody>';
      t.querySelectorAll('tr[data-i]').forEach(function (tr) {
        function pilih() { pilihAddr = +tr.getAttribute('data-i'); renderAddr(); }
        tr.addEventListener('click', pilih);
        tr.addEventListener('dblclick', function () { bukaAddr(+tr.getAttribute('data-i')); });
        tr.addEventListener('keydown', function (e) { if (e.key === 'Enter') bukaAddr(+tr.getAttribute('data-i')); if (e.key === ' ') { e.preventDefault(); pilih(); } });
      });
      $('addr-hapus').disabled = pilihAddr < 0 || pilihAddr >= L.length;
    }
    function bukaAddr(i) {
      ubahIdx = i; var a = i >= 0 ? addrList()[i] : null;
      $('addr-dialog-judul').textContent = a ? 'Address <' + a.ip + '/' + a.p + '>' : 'New Address';
      $('addr-ip').value = a ? a.ip + '/' + a.p : ''; $('addr-net').value = a ? netDari(a.ip, a.p) : '';
      $('addr-if').value = a ? a.intf : 'ether1'; $('addr-galat').textContent = '';
      $('addr-dialog').hidden = false; $('addr-ip').focus();
    }
    function terapAddr(tutup) {
      var a = bacaAlamat($('addr-ip').value);
      if (!a) { $('addr-galat').textContent = 'Address tidak valid. Contoh penulisan: 192.168.11.1/24'; return false; }
      var L = addrList(), baru = { ip: a.ip, p: a.p, intf: $('addr-if').value };
      if (ubahIdx >= 0) L[ubahIdx] = baru; else { L.push(baru); ubahIdx = L.length - 1; }
      simpanAddr(L); $('addr-net').value = netDari(a.ip, a.p); $('addr-galat').textContent = a.tanpaPrefix ? 'Tanpa prefix, router memakai /32.' : '';
      $('addr-dialog-judul').textContent = 'Address <' + a.ip + '/' + a.p + '>';
      $('cek-addr').classList.add('usang');
      if (tutup) $('addr-dialog').hidden = true;
      renderAddr(); renderLan(); return true;
    }
    $('addr-tambah').addEventListener('click', function () { bukaAddr(-1); });
    $('addr-hapus').addEventListener('click', function () { var L = addrList(); if (pilihAddr >= 0) { L.splice(pilihAddr, 1); pilihAddr = -1; simpanAddr(L); $('cek-addr').classList.add('usang'); renderAddr(); renderLan(); } });
    $('addr-apply').addEventListener('click', function () { terapAddr(false); });
    $('addr-ok').addEventListener('click', function () { terapAddr(true); });
    $('addr-batal').addEventListener('click', function () { $('addr-dialog').hidden = true; });

    function periksaAddr() {
      var L = addrList(), cek = [], ok = true;
      var TARGET = [{ ip: '192.168.11.1', intf: 'ether2', pc: 'PC1' }, { ip: '192.168.22.1', intf: 'ether3', pc: 'PC2' }];
      TARGET.forEach(function (t) {
        var a = L.filter(function (x) { return samaIP(x.ip, t.ip); })[0];
        if (!a) { ok = false; cek.push(['err', 'Belum ada alamat <span class="mono">' + t.ip + '/24</span> untuk ' + t.intf + '.']); return; }
        var m = [];
        if (a.intf !== t.intf) m.push(t.ip + ' dipasang di ' + a.intf + ', padahal ' + t.pc + ' tersambung ke <b>' + t.intf + '</b>.');
        if (a.p !== 24) m.push('Prefix ' + t.ip + ' tertulis /' + a.p + (a.p === 32 ? ' (karena tidak ditulis)' : '') + '. Seharusnya <b>/24</b>.');
        if (m.length) { ok = false; m.forEach(function (x) { cek.push(['err', x]); }); }
        else cek.push(['ok', '<span class="mono">' + t.ip + '/24</span> di ' + t.intf + ', network ' + netDari(a.ip, 24) + '.']);
      });
      L.forEach(function (a) {
        if (TARGET.some(function (t) { return samaIP(t.ip, a.ip); })) return;
        ok = false;
        var ket = netDari(a.ip, a.p) === a.ip && a.p < 31 ? ' Alamat itu adalah alamat network, bukan alamat host.' : '';
        cek.push(['err', 'Alamat <span class="mono">' + esc(a.ip + '/' + a.p) + '</span> di ' + a.intf + ' tidak ada di topologi. Pilih barisnya lalu hapus dengan tombol −.' + ket]);
      });
      if (ok) { cek.push(['info', 'Kedua IP ini akan menjadi <b>gateway</b>: 192.168.11.1 untuk PC1, 192.168.22.1 untuk PC2.']); if (!prog().m2_ip) setProg({ m2_ip: true }); }
      $('cek-addr').classList.remove('usang'); isiCek($('cek-addr'), cek); segarkanSelesai('m2');
    }
    $('btn-cek-addr').addEventListener('click', periksaAddr);

    // B. PC
    var c0 = pcCfg();
    ['pc1', 'pc2'].forEach(function (k) {
      ['ip', 'mask', 'gw', 'dns'].forEach(function (f) {
        var el = $(k + '-' + f); el.value = c0[k][f] || '';
        el.addEventListener('input', function () { var c = pcCfg(); c[k][f] = el.value.trim(); taruh('pc', c); taruh('pingOk', {}); $('cek-pc').classList.add('usang'); renderLan(); });
      });
    });
    function periksaPC() {
      var c = pcCfg(), cek = [], ok = true;
      [['pc1', '192.168.11', 'ether2'], ['pc2', '192.168.22', 'ether3']].forEach(function (x) {
        var k = x[0], nm = NM(k), d = c[k], gw = x[1] + '.1', m = [];
        var e = alasanTidakValid(d.ip || '');
        if (e) m.push('IP address: ' + e);
        else if (!dalamJaringan(d.ip, x[1] + '.0', 24)) m.push('IP ' + esc(d.ip) + ' bukan bagian jaringan ' + x[1] + '.0/24 milik ' + x[2] + '.');
        else { var h = +d.ip.split('.')[3]; if (h === 0) m.push(esc(d.ip) + ' adalah alamat network.'); else if (h === 255) m.push(esc(d.ip) + ' adalah alamat broadcast.'); else if (h === 1) m.push(esc(d.ip) + ' sudah dipakai router (gateway). Pakai alamat lain, misalnya ' + x[1] + '.2.'); }
        if (String(d.mask || '').trim() !== '255.255.255.0') m.push('Subnet mask harus <span class="mono">255.255.255.0</span> (sama dengan /24).');
        if (!samaIP(d.gw, gw)) m.push('Default gateway ' + nm + ' harus <span class="mono">' + gw + '</span>, yaitu IP ' + x[2] + ' yang tersambung ke ' + nm + '.' + (samaIP(d.gw, k === 'pc1' ? '192.168.22.1' : '192.168.11.1') ? ' Alamat yang kamu tulis adalah gateway PC lain.' : ''));
        if (!samaIP(d.dns, '8.8.8.8') && !samaIP(d.dns, gw)) m.push('Isi Preferred DNS server dengan <span class="mono">8.8.8.8</span> (dipakai di Misi 3).');
        if (m.length) { ok = false; m.forEach(function (t) { cek.push(['err', '<b>' + nm + ':</b> ' + t]); }); }
        else cek.push(['ok', '<b>' + nm + '</b>: ' + esc(d.ip) + '/24, gateway ' + gw + '.']);
      });
      if (ok && !prog().m2_pc) setProg({ m2_pc: true });
      $('cek-pc').classList.remove('usang'); isiCek($('cek-pc'), cek); segarkanSelesai('m2');
    }
    $('btn-cek-pc').addEventListener('click', periksaPC);

    // C. Ping
    var pgLan = null;
    function renderLan() {
      pgLan = panggung($('pg-lan'), { nodes: nodeLan(pcCfg()), links: [['PC1', 'MikroTik'], ['MikroTik', 'PC2']],
        labels: [{ x: 26, y: 46, t: 'ether2' }, { x: 74, y: 46, t: 'ether3' }] });
    }
    renderLan();
    var fw = winfw();
    ['pc1', 'pc2'].forEach(function (k) {
      var b = $('winfw-' + k);
      function tampil() { var on = !!winfw()[k]; b.classList.toggle('aktif', on); b.setAttribute('aria-pressed', on ? 'true' : 'false'); }
      tampil();
      b.addEventListener('click', function () { var f = winfw(); f[k] = !f[k]; taruh('winfw', f); taruh('pingOk', {}); tampil(); });
    });
    var sibuk = false;
    document.querySelectorAll('[data-ping]').forEach(function (b) {
      b.addEventListener('click', function () {
        if (sibuk) return; sibuk = true;
        var x = b.getAttribute('data-ping').split('|'), k = x[0], T = x[1];
        var h = jalurPC(k, T, 'icmp');
        $('terminal').textContent = teksWindows(T, T, '', h).join('\n');
        animasiPing(pgLan, h).then(function () {
          sibuk = false;
          var cek = [];
          if (h.jenis === 'ok') cek.push(['ok', NM(k) + ' → ' + T + ' berhasil.']);
          else cek.push(['err', h.alasan]);
          if (h.jenis === 'unreach') cek.push(['info', 'Windows menghitung pesan <i>Destination host unreachable</i> sebagai “Received”, padahal ping tetap gagal.']);
          var po = ambil('pingOk') || {};
          if (k === 'pc1' && T === '192.168.22.2') po.p12 = h.jenis === 'ok';
          if (k === 'pc2' && T === '192.168.11.2') po.p21 = h.jenis === 'ok';
          taruh('pingOk', po);
          if (po.p12 && po.p21) { cek.push(['ok', 'PC1 dan PC2 sudah saling terhubung lewat router.']); if (!prog().m2_ping) setProg({ m2_ping: true }); }
          else if (po.p12 || po.p21) cek.push(['info', 'Uji juga arah sebaliknya.']);
          isiCek($('cek-ping'), cek); segarkanSelesai('m2');
        });
      });
    });

    renderAddr();
    pasangKuis('m2', function () { segarkanSelesai('m2'); });
    segarkanSelesai('m2');
    $('btn-m2-selesai').addEventListener('click', function () { window.location.href = 'internet.html'; });
  }

  /* ============================================================ MISI 3: INTERNET & FIREWALL */
  if (halaman === 'internet') {
    bannerUrut('m2', 'Misi 2', 'ip.html');
    // tabel daftar umum
    function tabelDaftar(id, kolom, baris, pilihan, onPilih) {
      var t = $(id);
      t.innerHTML = '<thead><tr>' + kolom.map(function (k) { return '<th>' + k + '</th>'; }).join('') + '</tr></thead><tbody>' +
        (baris.length ? baris.map(function (r, i) { return '<tr data-i="' + i + '" tabindex="0" class="' + (i === pilihan ? 'dipilih ' : '') + (r.kelas || '') + '">' + r.sel.map(function (s) { return '<td>' + s + '</td>'; }).join('') + '</tr>'; }).join('')
          : '<tr class="kosong"><td colspan="' + kolom.length + '">0 items</td></tr>') + '</tbody>';
      t.querySelectorAll('tr[data-i]').forEach(function (tr) {
        tr.addEventListener('click', function () { onPilih(+tr.getAttribute('data-i')); });
        tr.addEventListener('keydown', function (e) { if (e.key === ' ' || e.key === 'Enter') { e.preventDefault(); onPilih(+tr.getAttribute('data-i')); } });
      });
    }
    function tabDialog(attr) {
      document.querySelectorAll('[data-tab-' + attr + ']').forEach(function (b) {
        b.addEventListener('click', function () {
          var n = b.getAttribute('data-tab-' + attr);
          document.querySelectorAll('[data-tab-' + attr + ']').forEach(function (x) { x.classList.toggle('aktif', x === b); });
          document.querySelectorAll('[data-panel-' + attr + ']').forEach(function (p) { p.hidden = p.getAttribute('data-panel-' + attr) !== n; });
        });
      });
    }
    function bukaTab(attr, n) { var b = document.querySelector('[data-tab-' + attr + '="' + n + '"]'); if (b) b.click(); }

    // A. DHCP Client
    var pilihDc = -1, mencari = false;
    function renderDc() {
      var L = dhcpc();
      tabelDaftar('dc-tabel', ['Interface', 'Use Peer DNS', 'Add Default Route', 'IP Address', 'Status'], L.map(function (x) {
        var bound = x.intf === 'ether1' && !mencari;
        return { sel: [esc(x.intf), x.peerdns ? 'yes' : 'no', esc(x.defrt), bound ? WAN.ip + '/24' : '', bound ? '<b class="st-up">bound</b>' : 'searching...'] };
      }), pilihDc, function (i) { pilihDc = i; renderDc(); });
      $('dc-hapus').disabled = pilihDc < 0 || pilihDc >= L.length;
      renderDns(); renderNet();
    }
    function periksaDc() {
      var L = dhcpc(), cek = [];
      if (!L.length) { isiCek($('cek-dhcp'), [['info', 'Klik + untuk membuat DHCP Client.']]); return; }
      L.forEach(function (x) {
        if (x.intf !== 'ether1') cek.push(['err', 'DHCP Client di ' + x.intf + ' tetap <b>searching</b>: di ' + x.intf + ' hanya ada PC, tidak ada server DHCP. Yang tersambung ke internet adalah <b>ether1</b>.']);
      });
      var e1 = L.filter(function (x) { return x.intf === 'ether1'; })[0];
      if (e1) {
        cek.push(['ok', 'ether1 berstatus <b>bound</b> dan mendapat IP ' + WAN.ip + '/24 dari ISP.']);
        if (e1.defrt !== 'yes') cek.push(['err', 'Add Default Route bernilai <b>no</b>: router punya IP, tetapi tidak tahu jalan ke internet. Hapus lalu buat ulang dengan <b>yes</b>.']);
        else cek.push(['ok', 'Default route ke ' + WAN.gw + ' ditambahkan otomatis.']);
        if (!e1.peerdns) cek.push(['info', 'Use Peer DNS mati: server DNS dari ISP tidak dipakai. Isi DNS manual di Bagian B.']);
      }
      if (e1 && e1.defrt === 'yes' && L.length === 1 && !prog().m3_dhcp) setProg({ m3_dhcp: true });
      isiCek($('cek-dhcp'), cek); segarkanSelesai('m3');
    }
    $('dc-tambah').addEventListener('click', function () { $('dc-dialog').hidden = false; $('dc-if').focus(); });
    $('dc-batal').addEventListener('click', function () { $('dc-dialog').hidden = true; });
    $('dc-hapus').addEventListener('click', function () { var L = dhcpc(); if (pilihDc >= 0) { L.splice(pilihDc, 1); pilihDc = -1; taruh('dhcpc', L); renderDc(); periksaDc(); } });
    $('dc-ok').addEventListener('click', function () {
      var L = dhcpc(), intf = $('dc-if').value;
      if (L.some(function (x) { return x.intf === intf; })) { isiCek($('cek-dhcp'), [['err', 'Sudah ada DHCP Client di ' + intf + '.']]); $('dc-dialog').hidden = true; return; }
      L.push({ intf: intf, peerdns: $('dc-peerdns').checked, peerntp: $('dc-peerntp').checked, defrt: $('dc-defrt').value });
      taruh('dhcpc', L); $('dc-dialog').hidden = true;
      mencari = true; renderDc(); isiCek($('cek-dhcp'), [['info', 'searching… router meminta IP lewat ' + intf + '.']]);
      jeda(1200).then(function () { mencari = false; renderDc(); periksaDc(); });
    });

    // B. DNS
    var d0 = dnsCfg(); $('dns-srv').value = d0.srv || ''; $('dns-remote').checked = !!d0.remote;
    function renderDns() { var w = wan(); $('dns-dyn').value = w && w.peerdns && !mencari ? WAN.dns : ''; }
    function terapDns(tutup) {
      var srv = $('dns-srv').value.trim(), cek = [];
      if (srv && !parseIP(srv)) { isiCek($('cek-dns'), [['err', 'Servers harus berupa alamat IP, misalnya 8.8.8.8.']]); return; }
      taruh('dns', { srv: srv, remote: $('dns-remote').checked });
      if (!srv) cek.push(['err', 'Servers masih kosong. Isi <span class="mono">8.8.8.8</span> (DNS milik Google).']);
      else if (!samaIP(srv, '8.8.8.8')) cek.push(['info', esc(srv) + ' bisa saja server DNS, tetapi praktik ini memakai <span class="mono">8.8.8.8</span>.']);
      else cek.push(['ok', 'Router memakai 8.8.8.8 untuk menerjemahkan nama domain.']);
      if (!$('dns-remote').checked) cek.push(['err', 'Centang <b>Allow Remote Requests</b> agar PC boleh memakai router sebagai server DNS.']);
      else cek.push(['ok', 'Allow Remote Requests aktif: PC boleh bertanya DNS ke router.']);
      if (samaIP(srv, '8.8.8.8') && $('dns-remote').checked && !prog().m3_dns) setProg({ m3_dns: true });
      isiCek($('cek-dns'), cek); segarkanSelesai('m3');
      if (tutup) $('dns-ok').blur();
    }
    $('dns-apply').addEventListener('click', function () { terapDns(false); });
    $('dns-ok').addEventListener('click', function () { terapDns(true); });

    // C. NAT
    var pilihNat = -1; tabDialog('nat');
    function renderNat() {
      tabelDaftar('nat-tabel', ['#', 'Action', 'Chain', 'Out. Interface'], natList().map(function (x, i) { return { sel: [i, esc(x.aksi), esc(x.chain), esc(x.out || '')] }; }), pilihNat, function (i) { pilihNat = i; renderNat(); });
      $('nat-hapus').disabled = pilihNat < 0 || pilihNat >= natList().length;
    }
    function periksaNat() {
      var L = natList(), cek = [], benar = false;
      if (!L.length) { isiCek($('cek-nat'), [['info', 'Klik + untuk membuat aturan NAT.']]); return; }
      L.forEach(function (x, i) {
        var m = [];
        if (x.chain !== 'srcnat') m.push('chain harus <b>srcnat</b> (mengubah alamat pengirim), bukan ' + x.chain);
        if (x.out !== 'ether1') m.push(x.out ? 'Out. Interface ' + x.out + ' adalah port LAN; jalan keluar ke internet adalah <b>ether1</b>' : 'isi Out. Interface dengan <b>ether1</b>');
        if (x.aksi !== 'masquerade') m.push('Action harus <b>masquerade</b>, bukan ' + x.aksi);
        if (m.length) cek.push(['err', 'Aturan #' + i + ': ' + m.join('; ') + '.']);
        else { benar = true; cek.push(['ok', 'Aturan #' + i + ': srcnat, out-interface ether1, masquerade. IP privat PC akan diganti IP ether1 (' + WAN.ip + ').']); }
      });
      if (benar && !prog().m3_nat) setProg({ m3_nat: true });
      isiCek($('cek-nat'), cek); segarkanSelesai('m3');
    }
    $('nat-tambah').addEventListener('click', function () { $('nat-dialog').hidden = false; bukaTab('nat', 'umum'); $('nat-chain').focus(); });
    $('nat-batal').addEventListener('click', function () { $('nat-dialog').hidden = true; });
    $('nat-hapus').addEventListener('click', function () { var L = natList(); if (pilihNat >= 0) { L.splice(pilihNat, 1); pilihNat = -1; taruh('nat', L); renderNat(); periksaNat(); } });
    $('nat-ok').addEventListener('click', function () {
      var L = natList(); L.push({ chain: $('nat-chain').value, out: $('nat-out').value, aksi: $('nat-aksi').value });
      taruh('nat', L); $('nat-dialog').hidden = true; renderNat(); periksaNat();
    });

    // D. Uji internet
    var pgNet = null;
    function renderNet() {
      var c = pcCfg();
      pgNet = panggung($('pg-net'), {
        nodes: { Internet: { x: 50, y: 13, ikon: 'awan', ket: '8.8.8.8' }, MikroTik: { x: 50, y: 50, ikon: 'router', ket: wan() && !mencari ? WAN.ip : 'ether1' },
          PC1: { x: 16, y: 80, ikon: 'pc', ket: c.pc1.ip || '?' }, PC2: { x: 84, y: 80, ikon: 'pc', ket: c.pc2.ip || '?' } },
        links: [['Internet', 'MikroTik', 'link'], ['MikroTik', 'PC1'], ['MikroTik', 'PC2']]
      });
    }
    var sibuk = false;
    document.querySelectorAll('[data-ping]').forEach(function (b) {
      b.addEventListener('click', function () {
        if (sibuk) return; sibuk = true;
        var x = b.getAttribute('data-ping').split('|'), dari = x[0], arg = x[1], nama = /[a-z]/.test(arg) ? arg : '';
        var T = arg, h, baris, cek = [];
        if (nama) {
          var rs = resolve(dari, nama);
          if (rs.gagal) {
            h = { jenis: 'nohost', jalur: [dari === 'router' ? 'MikroTik' : NM(dari)], putusDi: dari === 'router' ? 'MikroTik' : NM(dari), alasan: rs.gagal };
            baris = dari === 'router' ? teksRouter(arg, '', h, true) : teksWindows(arg, '', nama, h);
            cek.push(['err', rs.gagal]);
          } else T = rs.ip;
        }
        if (!h) {
          h = dari === 'router' ? jalurRouter(T) : jalurPC(dari, T, 'icmp');
          baris = dari === 'router' ? teksRouter(arg, T, h) : teksWindows(arg, T, nama, h);
          if (h.jenis === 'ok') cek.push(['ok', (dari === 'router' ? 'Router' : NM(dari)) + ' → ' + arg + ' berhasil' + (nama ? ' (DNS menerjemahkan ' + nama + ' menjadi ' + T + ')' : '') + '.']);
          else cek.push(['err', h.alasan]);
        }
        $('terminal').textContent = baris.join('\n');
        var anim = h.jenis === 'nohost' ? (pgNet.bersih(), pgNet.node(h.putusDi).classList.add('gagal'), jeda(300)) : animasiPing(pgNet, h);
        anim.then(function () { sibuk = false; isiCek($('cek-ping'), cek); });
      });
    });

    // E. Firewall filter
    var pilihFl = -1; tabDialog('fl');
    function renderFl() {
      var L = filterList();
      tabelDaftar('fl-tabel', ['#', 'Action', 'Chain', 'Src. Address', 'Dst. Address', 'Proto'], L.map(function (x, i) {
        return { kelas: x.mati ? 'nonaktif' : '', sel: [(x.mati ? 'X ' : '') + i, esc(x.aksi), esc(x.chain), esc(x.src || ''), esc(x.dst || ''), esc(x.proto || '')] };
      }), pilihFl, function (i) { pilihFl = i; renderFl(); });
      var ada = pilihFl >= 0 && pilihFl < L.length;
      $('fl-hapus').disabled = !ada; $('fl-enable').disabled = !ada || !L[pilihFl].mati; $('fl-disable').disabled = !ada || L[pilihFl].mati;
    }
    function ubahFl(fn) { var L = filterList(); fn(L); taruh('filter', L); renderFl(); }
    $('fl-tambah').addEventListener('click', function () { $('fl-dialog').hidden = false; $('fl-galat').textContent = ''; bukaTab('fl', 'umum'); $('fl-chain').focus(); });
    $('fl-batal').addEventListener('click', function () { $('fl-dialog').hidden = true; });
    $('fl-hapus').addEventListener('click', function () { if (pilihFl >= 0) ubahFl(function (L) { L.splice(pilihFl, 1); pilihFl = -1; }); });
    $('fl-enable').addEventListener('click', function () { if (pilihFl >= 0) ubahFl(function (L) { L[pilihFl].mati = false; }); });
    $('fl-disable').addEventListener('click', function () { if (pilihFl >= 0) ubahFl(function (L) { L[pilihFl].mati = true; }); });
    $('fl-ok').addEventListener('click', function () {
      var src = $('fl-src').value.trim(), dst = $('fl-dst').value.trim();
      if (src && !bacaAlamat(src)) { $('fl-galat').textContent = 'Src. Address tidak valid. Contoh: 192.168.11.0/24'; bukaTab('fl', 'umum'); return; }
      if (dst && !bacaAlamat(dst)) { $('fl-galat').textContent = 'Dst. Address tidak valid. Contoh: 192.168.22.0/24'; bukaTab('fl', 'umum'); return; }
      ubahFl(function (L) { L.push({ chain: $('fl-chain').value, src: src, dst: dst, proto: $('fl-proto').value, aksi: $('fl-aksi').value, mati: false }); pilihFl = L.length - 1; });
      $('fl-dialog').hidden = true;
      var r = filterList()[pilihFl], cek = [];
      if (r.chain === 'input') cek.push(['info', 'Chain <b>input</b> hanya untuk paket yang menuju router itu sendiri. Ping PC1 ke PC2 melewati router, jadi tidak terpengaruh.']);
      if (r.chain === 'output') cek.push(['info', 'Chain <b>output</b> untuk paket yang dibuat router sendiri. Ping PC1 ke PC2 tidak terpengaruh.']);
      if (r.aksi === 'accept') cek.push(['info', 'Action <b>accept</b> mengizinkan lalu lintas. Untuk memblokir, pakai <b>drop</b>.']);
      if (!r.src && !r.dst && r.aksi === 'drop' && r.chain === 'forward') cek.push(['info', 'Tanpa Src/Dst Address, aturan ini memblokir semua lalu lintas yang melewati router, termasuk ke internet.']);
      cek.push(['info', 'Sekarang uji ping PC1 → PC2.']);
      isiCek($('cek-fw'), cek);
    });
    var sibuk2 = false;
    document.querySelectorAll('[data-ping2]').forEach(function (b) {
      b.addEventListener('click', function () {
        if (sibuk2) return; sibuk2 = true;
        var x = b.getAttribute('data-ping2').split('|'), k = x[0], T = x[1];
        var h = jalurPC(k, T, 'icmp');
        $('terminal2').textContent = teksWindows(T, T, '', h).join('\n');
        animasiPing(pgNet, h).then(function () {
          sibuk2 = false;
          var cek = [], u = ambil('fwUji') || {};
          if (h.jenis === 'ok') cek.push(['ok', NM(k) + ' → ' + T + ' berhasil.']); else cek.push(['err', h.alasan]);
          if (k === 'pc1') {
            if (h.olehFilter) { u.blok = true; cek.push(['ok', 'Aturan firewall bekerja: ping PC1 → PC2 dibuang router. Sekarang pilih aturannya dan klik ✕ untuk menonaktifkan.']); }
            else if (h.jenis === 'ok' && u.blok && filterList().some(function (r) { return r.mati; })) { u.buka = true; }
            else if (h.jenis === 'ok' && !u.blok) cek.push(['info', 'Ping masih berhasil. Belum ada aturan aktif yang memblokir PC1 → PC2.']);
            else if (h.jenis !== 'ok' && !h.olehFilter) cek.push(['info', 'Ping gagal bukan karena firewall filter. Cek dulu konfigurasi di Misi 2.']);
          }
          if (k === 'pc2' && h.olehFilter) cek.push(['info', 'Ping PC2 sampai di PC1, tetapi <b>balasan</b> PC1 (dari 192.168.11.x ke 192.168.22.x) cocok dengan aturan drop.']);
          taruh('fwUji', u);
          if (u.blok && u.buka) { cek.push(['ok', 'Aturan dinonaktifkan dan ping kembali berhasil. Firewall filter terbukti mengendalikan lalu lintas.']); if (!prog().m3_fw) setProg({ m3_fw: true }); }
          isiCek($('cek-fw'), cek); segarkanSelesai('m3');
        });
      });
    });

    renderDc(); renderNat(); renderFl();
    if (dhcpc().length) periksaDc();
    if (natList().length) periksaNat();
    pasangKuis('m3', function () { segarkanSelesai('m3'); });
    segarkanSelesai('m3');
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
      ['Misi 1 · Tugas Winbox (' + s.m1.nTugas + '/3)', s.m1.tugas + ' / ' + s.m1.maksTugas],
      ['Misi 1 · Kuis (' + s.m1.benar + '/5 benar × 3)', s.m1.kuis + ' / ' + s.m1.maksKuis],
      ['Misi 2 · Tugas IP & ping (' + s.m2.nTugas + '/3)', s.m2.tugas + ' / ' + s.m2.maksTugas],
      ['Misi 2 · Kuis (' + s.m2.benar + '/5 benar × 3)', s.m2.kuis + ' / ' + s.m2.maksKuis],
      ['Misi 3 · Tugas internet & firewall (' + s.m3.nTugas + '/4)', s.m3.tugas + ' / ' + s.m3.maksTugas],
      ['Misi 3 · Kuis (' + s.m3.benar + '/5 benar × 4)', s.m3.kuis + ' / ' + s.m3.maksKuis]
    ];
    var ul = $('result-breakdown');
    baris.forEach(function (b) { var li = document.createElement('li'); li.innerHTML = '<span>' + esc(b[0]) + '</span><span class="result-value">' + b[1] + '</span>'; ul.appendChild(li); });
    var HAL = { m1: ['Misi 1', 'winbox.html'], m2: ['Misi 2', 'ip.html'], m3: ['Misi 3', 'internet.html'] };
    ['m1', 'm2', 'm3'].forEach(function (m) {
      if (ph[m] && s[m].tugas === s[m].maksTugas && s[m].benar === 5) return;
      var li = document.createElement('li'); li.className = 'saran';
      li.innerHTML = '<span>' + (ph[m] ? 'Mau skor penuh? Ulangi kuis ' + HAL[m][0] + '.' : HAL[m][0] + ' belum selesai.') + '</span><a class="btn btn-ghost" href="' + HAL[m][1] + '">Ke ' + HAL[m][0] + '</a>';
      ul.appendChild(li);
    });
  }
});
