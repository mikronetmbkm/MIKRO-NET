/* Mikronet — Simulasi Pertemuan 4 (multi-halaman): jaringan nirkabel.
   Misi 1: sifat sinyal · Misi 2: hotspot, SSID & koneksi · Misi 3: channel & interferensi.
   Skor akhir dilaporkan ke MIKRO-NET: parent.postMessage({type:'mikronet:hasil', skor, skorMaks:100}, '*'). */
document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var $ = function (id) { return document.getElementById(id); };
  var halaman = document.body.getAttribute('data-halaman') || '';

  /* ---------- Penyimpanan ----------
     Di dalam iframe bersandbox, localStorage tidak dipakai. Progres disimpan di window.name (bertahan antarhalaman
     dalam satu iframe), dengan localStorage sebagai cadangan bila game dibuka langsung di browser. */
  var KUNCI = 'mikronet_p4_v2';
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
    m1: ['m1_jarak', 'm1_halangan', 'm1_arah'],
    m2: ['m2_hotspot', 'm2_konek', 'm2_status'],
    m3: ['m3_overlap', 'm3_channel', 'm3_band', 'm3_kasus']
  };
  var NAMA_TUGAS = {
    m1_jarak: 'sinyal & jarak', m1_halangan: 'halangan', m1_arah: 'pola pancaran',
    m2_hotspot: 'atur hotspot', m2_konek: 'sambungkan penerima', m2_status: 'baca status koneksi',
    m3_overlap: 'channel tidak tumpang tindih', m3_channel: 'pilih channel di dua lokasi', m3_band: '2,4 atau 5 GHz', m3_kasus: 'kasus troubleshooting'
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
      { q: 'WiFi mengirim data memakai…', opsi: ['Kabel UTP', 'Gelombang radio', 'Cahaya inframerah', 'Gelombang suara'], benar: 1,
        jelas: 'WiFi memakai <b>gelombang radio</b> (gelombang elektromagnetik) pada pita 2,4 GHz atau 5 GHz.' },
      { q: 'Sinyal −55 dBm pada jarak 10 m. Di ruang bebas, perkiraan sinyal pada jarak 20 m adalah…', opsi: ['−49 dBm', '−55 dBm', '−61 dBm', '−110 dBm'], benar: 2,
        jelas: 'Setiap jarak dua kali lipat, sinyal melemah sekitar 6 dB: −55 − 6 = <b>−61 dBm</b>.' },
      { q: 'Manakah sinyal yang paling <b>kuat</b>?', opsi: ['−85 dBm', '−70 dBm', '−60 dBm', '−90 dBm'], benar: 2,
        jelas: 'Nilai dBm selalu negatif. Makin mendekati 0, makin kuat. −60 dBm paling kuat di antara pilihan itu.' },
      { q: 'Benda yang paling banyak <b>memantulkan</b> gelombang WiFi adalah…', opsi: ['Kayu', 'Kaca', 'Logam', 'Kain'], benar: 2,
        jelas: 'Logam memantulkan hampir seluruh gelombang radio. Karena itu access point sebaiknya tidak dipasang di dalam lemari besi atau di balik pagar logam.' },
      { q: 'Sinyal WiFi di kelas terasa melemah saat ruangan penuh siswa. Penyebabnya…', opsi: ['Siswa memakai terlalu banyak kuota', 'Tubuh manusia banyak mengandung air yang menyerap sinyal 2,4 GHz', 'Access point menjadi panas', 'Tidak ada pengaruhnya'], benar: 1,
        jelas: 'Air menyerap gelombang 2,4 GHz. Tubuh manusia dan pohon rimbun dapat menurunkan sinyal beberapa dB.' },
      { q: 'Access point biasa memancarkan sinyal…', opsi: ['Ke segala arah (omnidirectional)', 'Hanya ke satu arah', 'Hanya ke atas', 'Hanya ke bawah'], benar: 0,
        jelas: 'Access point biasa melayani banyak perangkat di sekitarnya, jadi sinyalnya menyebar ke segala arah.' },
      { q: 'Kelebihan antena terarah (directional) adalah…', opsi: ['Melayani lebih banyak pengguna di sekitarnya', 'Jangkauan lebih jauh ke satu arah karena sinyal dipusatkan', 'Tidak perlu diarahkan', 'Tidak butuh adapter'], benar: 1,
        jelas: 'Antena terarah memusatkan sinyal ke satu arah sehingga lebih jauh, dengan syarat diarahkan tepat ke tujuan.' },
      { q: 'Panjang gelombang WiFi 2,4 GHz kira-kira…', opsi: ['1,25 mm', '12,5 cm', '12,5 m', '1,25 km'], benar: 1,
        jelas: 'λ = c / f = 3 × 10⁸ / 2,4 × 10⁹ ≈ 0,125 m = <b>12,5 cm</b>. Pada 5 GHz, panjang gelombangnya sekitar 6 cm.' }
    ],
    m2: [
      { q: 'SSID pada jaringan WiFi adalah…', opsi: ['Nama jaringan yang terlihat di daftar WiFi', 'Password jaringan', 'Alamat IP access point', 'Nomor channel'], benar: 0,
        jelas: '<b>SSID</b> (Service Set Identifier) adalah nama jaringan yang muncul di daftar WiFi, misalnya KEL1-TKJ.' },
      { q: 'Saat Mobile hotspot aktif, laptop pemancar berperan sebagai…', opsi: ['Station/klien', 'Switch', 'Access point', 'Modem'], benar: 2,
        jelas: 'Laptop pemancar memancarkan SSID dan melayani perangkat lain, jadi berperan sebagai <b>access point</b>. Laptop penerima menjadi station/klien.' },
      { q: 'Password hotspot dengan keamanan WPA2 paling sedikit…', opsi: ['4 karakter', '6 karakter', '8 karakter', '16 karakter'], benar: 2,
        jelas: 'WPA2-Personal mensyaratkan password minimal <b>8 karakter</b>. Windows menolak password yang lebih pendek.' },
      { q: 'Laptop penerima mendapat IP 192.168.137.x secara otomatis dari hotspot melalui layanan…', opsi: ['DNS', 'VLAN', 'FTP', 'DHCP'], benar: 3,
        jelas: 'Mobile hotspot Windows menjalankan <b>DHCP</b> dan membagikan alamat 192.168.137.x, dengan gateway 192.168.137.1.' },
      { q: 'Laptop penerima tersambung, tetapi IP-nya 169.254.20.7. Artinya…', opsi: ['Koneksi berhasil sempurna', 'Password salah', 'Penerima tidak mendapat IP dari DHCP hotspot', 'Sinyal terlalu kuat'], benar: 2,
        jelas: '169.254.x.x adalah alamat darurat (APIPA) yang dibuat Windows sendiri karena tidak ada balasan dari DHCP.' },
      { q: 'Penerima mendapat IP 192.168.137.30 tetapi statusnya <i>No internet</i>. Yang perlu diperiksa adalah…', opsi: ['Sumber internet di laptop pemancar (kabel LAN/tethering)', 'Password hotspot', 'Channel WiFi', 'Nama SSID'], benar: 0,
        jelas: 'IP dari hotspot sudah didapat, jadi koneksi WiFi berhasil. Masalahnya ada pada internet yang dibagikan pemancar.' },
      { q: 'Bukti paling sederhana bahwa ada perangkat yang tersambung ke hotspot dapat dilihat di…', opsi: ['Device Manager laptop penerima', 'Task Manager', 'Control Panel › Sound', 'Halaman Mobile hotspot laptop pemancar, bagian Devices connected'], benar: 3,
        jelas: 'Halaman Mobile hotspot menampilkan jumlah perangkat beserta nama, IP, dan MAC-nya. Bagian inilah yang difoto saat praktik.' },
      { q: 'Adapter penerima hanya mendukung 2,4 GHz, tetapi hotspot diatur 5 GHz. Yang terjadi adalah…', opsi: ['Penerima tetap bisa tersambung', 'SSID hotspot tidak muncul di laptop penerima', 'Internet menjadi lebih cepat', 'IP berubah menjadi 169.254.x.x'], benar: 1,
        jelas: 'Perangkat hanya "mendengar" pita yang didukungnya, sehingga SSID 5 GHz tidak terlihat. Atur band hotspot ke 2.4 GHz.' }
    ],
    m3: [
      { q: 'Di pita 2,4 GHz, tiga channel yang tidak saling tumpang tindih dan paling umum dipakai adalah…', opsi: ['1, 2, 3', '2, 4, 6', '1, 6, 11', '5, 10, 13'], benar: 2,
        jelas: 'Setiap channel selebar ±22 MHz, sedangkan jarak antar-channel hanya 5 MHz. Channel <b>1, 6, dan 11</b> berjarak cukup jauh sehingga tidak bertabrakan.' },
      { q: 'Interferensi pada WiFi adalah…', opsi: ['Gangguan dari sinyal lain pada frekuensi yang sama atau berdekatan', 'Password yang salah', 'Kabel yang putus', 'IP yang bentrok'], benar: 0,
        jelas: 'Jaringan atau perangkat lain yang memakai frekuensi berdekatan "berebut udara", sehingga data harus dikirim ulang dan koneksi melambat.' },
      { q: 'Channel 3 tumpang tindih dengan…', opsi: ['Hanya channel 3', 'Tidak ada', 'Channel 11 saja', 'Channel 1 dan channel 6'], benar: 3,
        jelas: 'Channel yang jaraknya kurang dari 5 akan tumpang tindih. Channel 3 bertabrakan dengan channel 1–7, termasuk channel 1 dan 6.' },
      { q: 'Dibandingkan 5 GHz, kelebihan pita 2,4 GHz adalah…', opsi: ['Lebih cepat', 'Jangkauan lebih jauh dan lebih tembus dinding', 'Lebih sedikit gangguan', 'Lebih banyak channel'], benar: 1,
        jelas: 'Gelombang 2,4 GHz lebih panjang sehingga lebih jauh dan lebih tembus dinding, tetapi lebih padat dan lebih lambat.' },
      { q: 'Kelebihan pita 5 GHz adalah…', opsi: ['Kecepatan lebih tinggi dan lebih banyak channel yang tidak tumpang tindih', 'Jangkauan paling jauh', 'Didukung semua adapter murah', 'Tembus tembok tebal'], benar: 0,
        jelas: '5 GHz lebih cepat dan lebih lega, tetapi jangkauannya lebih pendek dan tidak semua perangkat mendukung.' },
      { q: 'Aplikasi WiFi analyzer dipakai untuk…', opsi: ['Membobol password WiFi', 'Membuat hotspot', 'Menambah kecepatan internet', 'Melihat jaringan sekitar beserta channel dan kekuatan sinyalnya'], benar: 3,
        jelas: 'WiFi analyzer menampilkan SSID tetangga, channel, dan sinyalnya, sehingga kita dapat memilih channel yang paling sepi.' },
      { q: 'Sinyal WiFi kuat (−50 dBm) tetapi koneksi lambat dan sering putus. Penyebab yang paling mungkin adalah…', opsi: ['Jarak terlalu jauh', 'Interferensi dari banyak jaringan di channel yang sama', 'Password terlalu panjang', 'SSID terlalu pendek'], benar: 1,
        jelas: 'Sinyal kuat berarti jarak bukan masalahnya. Channel yang padat membuat perangkat sering menunggu dan mengirim ulang data.' },
      { q: 'Selain jaringan WiFi lain, perangkat yang dapat mengganggu pita 2,4 GHz adalah…', opsi: ['Lampu LED', 'Kabel UTP', 'Microwave dan perangkat Bluetooth', 'Monitor'], benar: 2,
        jelas: 'Microwave dan Bluetooth juga bekerja di sekitar 2,4 GHz sehingga dapat menimbulkan interferensi.' }
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

  /* ============================================================ UTILITAS KANVAS
     Kanvas digambar dalam koordinat logis (lebar 900) lalu diskalakan ke lebar tampilan. Kanvas tidak menahan sentuhan,
     jadi halaman tetap bisa digulir di HP. Animasi berhenti saat kanvas tidak terlihat. */
  function kanvas(el, gambar) {
    if (!el.parentNode.classList.contains('kanvas-gulir')) { var bungkus = document.createElement('div'); bungkus.className = 'kanvas-gulir'; el.parentNode.insertBefore(bungkus, el); bungkus.appendChild(el); }
    var ctx = el.getContext('2d'), W = +el.getAttribute('width'), H = +el.getAttribute('height'), t0 = performance.now(), tampak = true;
    function ukur() {
      var dpr = Math.min(window.devicePixelRatio || 1, 2), w = el.clientWidth || W;
      el.width = Math.round(w * dpr); el.height = Math.round(w * H / W * dpr);
      ctx.setTransform(el.width / W, 0, 0, el.height / H, 0, 0);
    }
    ukur(); window.addEventListener('resize', ukur);
    if ('IntersectionObserver' in window) new IntersectionObserver(function (e) { tampak = e[0].isIntersecting; }).observe(el);
    (function loop(now) {
      if (tampak) { ctx.clearRect(0, 0, W, H); gambar(ctx, Math.max(0, (now - t0) / 1000), W, H); }
      requestAnimationFrame(loop);
    })(t0);
  }
  function teksK(ctx, s, x, y, opt) {
    opt = opt || {}; ctx.font = (opt.b ? '700 ' : '500 ') + (opt.u || 16) + 'px Poppins, Arial, sans-serif';
    ctx.fillStyle = opt.c || '#12233F'; ctx.textAlign = opt.a || 'center'; ctx.textBaseline = 'middle'; ctx.fillText(s, x, y);
  }
  function apK(ctx, x, y) { // access point / adapter kecil
    ctx.fillStyle = '#0B3B7B'; ctx.beginPath(); ctx.roundRect ? ctx.roundRect(x - 22, y - 14, 44, 28, 6) : ctx.rect(x - 22, y - 14, 44, 28); ctx.fill();
    ctx.strokeStyle = '#0B3B7B'; ctx.lineWidth = 4; ctx.beginPath(); ctx.moveTo(x - 12, y - 14); ctx.lineTo(x - 18, y - 36); ctx.moveTo(x + 12, y - 14); ctx.lineTo(x + 18, y - 36); ctx.stroke();
    ctx.fillStyle = '#F7B217'; ctx.beginPath(); ctx.arc(x + 12, y, 3.5, 0, 7); ctx.fill();
  }
  function laptopK(ctx, x, y) {
    ctx.fillStyle = '#0B3B7B'; ctx.fillRect(x - 32, y - 24, 64, 40); ctx.fillStyle = '#E3F2FF'; ctx.fillRect(x - 27, y - 19, 54, 30);
    ctx.fillStyle = '#003B7A'; ctx.fillRect(x - 40, y + 16, 80, 7);
  }
  function batang(ctx, x, y, pct) {
    for (var i = 0; i < 4; i++) { ctx.fillStyle = pct >= (i + 1) * 22 ? '#16A34A' : '#c9d3df'; ctx.fillRect(x + i * 9, y - (i + 1) * 7, 6, (i + 1) * 7); }
  }
  function persen(dbm) { return Math.max(0, Math.min(100, Math.round(2 * (dbm + 100)))); }
  function kualitas(dbm) { return dbm >= -50 ? 'Sangat baik' : dbm >= -60 ? 'Baik' : dbm >= -70 ? 'Cukup' : dbm >= -80 ? 'Lemah' : dbm >= -90 ? 'Sangat lemah' : 'Tidak layak'; }
  function meterHtml(dbm) {
    var p = persen(dbm), k = dbm >= -60 ? 'baik' : dbm >= -75 ? 'sedang' : 'buruk';
    return '<div class="meter-isi ' + k + '"><span class="meter-batang"><i style="width:' + p + '%"></i></span><b class="mono">' + Math.round(dbm) + ' dBm</b><span>' + p + '% · ' + kualitas(dbm) + '</span></div>';
  }
  function angka(s) { s = String(s || '').trim().replace('−', '-').replace(',', '.'); return s === '' || isNaN(+s) ? null : +s; }

  /* ============================================================ BERANDA */
  if (halaman === 'beranda') {
    var p = prog(), d0 = muat();
    var kartu = [
      { id: 'm1', buka: true, mulai: d0.lihat || (d0.kuis && d0.kuis.m1) },
      { id: 'm2', buka: !!p.m1, mulai: d0.hs || (d0.kuis && d0.kuis.m2) },
      { id: 'm3', buka: !!p.m2, mulai: d0.chSel || (d0.kuis && d0.kuis.m3) }
    ];
    kartu.forEach(function (k) {
      var btn = $('btn-' + k.id), st = $('status-' + k.id), card = $('card-' + k.id);
      var nT = TUGAS[k.id].filter(function (t) { return p[t]; }).length;
      var info = 'Tugas ' + nT + '/' + TUGAS[k.id].length + (p[k.id + '_kuis'] ? ' · Kuis ' + p[k.id + '_kuis'].benar + '/5' : '');
      if (k.buka) {
        card.classList.remove('locked'); btn.classList.remove('btn-lock'); btn.classList.add('btn-primary'); btn.removeAttribute('aria-disabled');
        btn.textContent = p[k.id] ? 'Buka lagi' : (k.mulai || nT ? 'Lanjutkan' : 'Mulai');
        st.innerHTML = p[k.id] ? '<span class="done-badge">Selesai ✓</span> ' + esc(info) : (k.mulai || nT ? esc(info) : 'Siap dikerjakan');
      } else btn.addEventListener('click', function (e) { e.preventDefault(); });
    });
    if (p.m1 && p.m2 && p.m3) $('btn-hasil').hidden = false;
  }

  /* ============================================================ MISI 1: SIFAT SINYAL */
  if (halaman === 'sinyal') {
    // A. Jarak
    var jarak = 10;
    function dbmJarak(d) { return -55 - 20 * Math.log10(d / 10); }
    function segarJarak() { $('lbl-jarak').textContent = jarak + ' m'; $('meter-jarak').innerHTML = meterHtml(dbmJarak(jarak)); }
    $('sl-jarak').addEventListener('input', function () { jarak = +this.value; segarJarak(); });
    segarJarak();
    kanvas($('kv-jarak'), function (ctx, t, W, H) {
      var ax = 80, ay = 150, lx = ax + jarak * (760 / 80);
      for (var i = 0; i < 7; i++) {
        var r = ((t * 120 + i * 120) % 840);
        ctx.strokeStyle = 'rgba(0,155,255,' + Math.max(0, 0.9 - r / 840) * (60 / (60 + r)) * 1.6 + ')';
        ctx.lineWidth = 3; ctx.beginPath(); ctx.arc(ax, ay, r + 10, -1.2, 1.2); ctx.stroke();
      }
      apK(ctx, ax, ay); teksK(ctx, 'Access point', ax, ay + 40, { u: 14, b: true });
      laptopK(ctx, lx, ay + 6); batang(ctx, lx - 18, ay - 34, persen(dbmJarak(jarak)));
      ctx.strokeStyle = '#5B7086'; ctx.lineWidth = 1.5; ctx.setLineDash([6, 6]); ctx.beginPath(); ctx.moveTo(ax, 232); ctx.lineTo(lx, 232); ctx.stroke(); ctx.setLineDash([]);
      teksK(ctx, jarak + ' m', (ax + lx) / 2, 246, { u: 14, c: '#5B7086', b: true });
    });
    function cekJarak(diam) {
      var a = angka($('in-20').value), b = angka($('in-40').value), h = $('hasil-jarak');
      if (a === null || b === null) { if (!diam) { h.className = 'hasil-uji gagal'; h.textContent = 'Isi kedua kotak dulu (angka negatif, misalnya −58).'; } return; }
      var ok20 = Math.abs(a - (-61)) <= 1, ok40 = Math.abs(b - (-67)) <= 1;
      if (ok20 && ok40) {
        h.className = 'hasil-uji berhasil'; h.innerHTML = '✔ Benar: 20 m ≈ <b>−61 dBm</b>, 40 m ≈ <b>−67 dBm</b>. Setiap jarak dua kali lipat, sinyal turun ±6 dB. Di lapangan hasilnya bisa berbeda karena halangan dan interferensi.';
        if (!prog().m1_jarak) setProg({ m1_jarak: true });
      } else if (!diam) {
        h.className = 'hasil-uji gagal';
        h.innerHTML = (a > 0 || b > 0 ? 'Nilai dBm selalu <b>negatif</b>. ' : '') + 'Petunjuk: dari 10 m ke 20 m jaraknya dua kali lipat, jadi kurangi 6 dB dari −55. Dari 20 m ke 40 m juga dua kali lipat. Coba geser laptop ke 20 m dan 40 m untuk mengecek.';
      }
      segarkanSelesai('m1');
    }
    $('btn-cek-jarak').addEventListener('click', function () { taruh('jarakIsi', [$('in-20').value, $('in-40').value]); cekJarak(false); });
    var ji = ambil('jarakIsi'); if (ji) { $('in-20').value = ji[0]; $('in-40').value = ji[1]; if (prog().m1_jarak) cekJarak(true); }

    // B. Halangan
    var BENDA = {
      kaca: { nama: 'Kaca jendela', loss: 3, t: 0.8, r: 0.2, ket: 'Kaca: sebagian kecil sinyal dipantulkan, sebagian besar tembus. Sinyal hanya melemah sedikit (±3 dB).' },
      orang: { nama: 'Tubuh manusia', loss: 5, t: 0.55, r: 0.1, ket: 'Tubuh manusia banyak mengandung air yang menyerap gelombang 2,4 GHz (±5 dB). Karena itu sinyal di ruangan penuh orang terasa lebih lemah.' },
      tembok: { nama: 'Tembok bata', loss: 10, t: 0.3, r: 0.35, ket: 'Tembok bata memantulkan sebagian dan menyerap sebagian besar sinyal. Yang tembus jauh lebih lemah (±10 dB).' },
      pohon: { nama: 'Pohon rimbun', loss: 12, t: 0.25, r: 0.08, ket: 'Daun yang rimbun dan basah menyerap sinyal cukup besar (±12 dB), apalagi saat hujan.' },
      logam: { nama: 'Pagar logam', loss: 25, t: 0.08, r: 0.9, ket: 'Logam memantulkan hampir seluruh sinyal, sehingga yang sampai ke penerima sangat lemah. Jangan memasang access point di balik logam.' }
    };
    var benda = '', lihat = ambil('lihat') || {};
    var wadahB = $('pilih-halangan');
    [['', 'Tanpa halangan']].concat(Object.keys(BENDA).map(function (k) { return [k, BENDA[k].nama]; })).forEach(function (x) {
      var b = document.createElement('button'); b.type = 'button'; b.className = 'chip-btn' + (x[0] === '' ? ' aktif' : ''); b.textContent = x[1]; b.setAttribute('data-benda', x[0]);
      b.addEventListener('click', function () {
        benda = x[0]; wadahB.querySelectorAll('.chip-btn').forEach(function (c) { c.classList.toggle('aktif', c === b); });
        if (benda) { lihat[benda] = true; taruh('lihat', lihat); }
        var dbm = -55 - (benda ? BENDA[benda].loss : 0), h = $('ket-halangan');
        h.className = 'hasil-uji ' + (benda ? 'info' : 'berhasil');
        h.innerHTML = (benda ? esc(BENDA[benda].ket) : 'Tanpa halangan, sinyal sampai utuh.') + ' Penerima: <b class="mono">' + dbm + ' dBm (' + persen(dbm) + '%)</b>.' +
          ' <span class="redup-teks">(' + Object.keys(lihat).length + '/5 halangan dicoba)</span>';
      });
      wadahB.appendChild(b);
    });
    function gbBenda(ctx, k, x, y) {
      if (k === 'kaca') { ctx.fillStyle = 'rgba(150,200,240,.55)'; ctx.fillRect(x - 8, y - 80, 16, 160); ctx.strokeStyle = '#7fb4dc'; ctx.strokeRect(x - 8, y - 80, 16, 160); }
      if (k === 'tembok') { ctx.fillStyle = '#c2643c'; ctx.fillRect(x - 20, y - 85, 40, 170); ctx.strokeStyle = '#f2d0bf'; ctx.lineWidth = 2; for (var r = -85; r < 85; r += 17) { ctx.beginPath(); ctx.moveTo(x - 20, y + r); ctx.lineTo(x + 20, y + r); ctx.stroke(); } }
      if (k === 'pohon') { ctx.fillStyle = '#7a5230'; ctx.fillRect(x - 8, y + 10, 16, 80); ctx.fillStyle = '#2f8f46'; [[0, -30, 55], [-30, 0, 40], [30, 0, 40], [0, 20, 42]].forEach(function (c) { ctx.beginPath(); ctx.arc(x + c[0], y + c[1], c[2], 0, 7); ctx.fill(); }); }
      if (k === 'logam') { ctx.strokeStyle = '#5B7086'; ctx.lineWidth = 6; for (var i = -1; i <= 1; i++) { ctx.beginPath(); ctx.moveTo(x + i * 10, y - 85); ctx.lineTo(x + i * 10, y + 85); ctx.stroke(); } ctx.lineWidth = 5; [-60, 0, 60].forEach(function (yy) { ctx.beginPath(); ctx.moveTo(x - 14, y + yy); ctx.lineTo(x + 14, y + yy); ctx.stroke(); }); }
      if (k === 'orang') { ctx.fillStyle = '#e0a87a'; ctx.beginPath(); ctx.arc(x, y - 55, 18, 0, 7); ctx.fill(); ctx.fillStyle = '#F7B217'; ctx.fillRect(x - 22, y - 34, 44, 70); ctx.fillStyle = '#0B3B7B'; ctx.fillRect(x - 18, y + 36, 14, 50); ctx.fillRect(x + 4, y + 36, 14, 50); }
    }
    kanvas($('kv-halangan'), function (ctx, t, W, H) {
      var y = 130, ox = 450, B = benda ? BENDA[benda] : { t: 1, r: 0 };
      for (var i = 0; i < 6; i++) {
        var x = 110 + ((t * 140 + i * 130) % 780);
        if (x < ox - 25) { ctx.strokeStyle = 'rgba(0,155,255,.85)'; }
        else { if (B.t < 0.02) continue; ctx.strokeStyle = 'rgba(0,155,255,' + (0.85 * B.t) + ')'; }
        ctx.lineWidth = 4; ctx.beginPath(); ctx.arc(x - 40, y, 60, -0.6, 0.6); ctx.stroke();
        if (B.r > 0.05 && x > ox - 25) { var xr = ox - 30 - (x - ox); if (xr > 110) { ctx.strokeStyle = 'rgba(220,38,38,' + (0.85 * B.r) + ')'; ctx.beginPath(); ctx.arc(xr + 40, y, 60, Math.PI - 0.6, Math.PI + 0.6); ctx.stroke(); } }
      }
      apK(ctx, 80, y); teksK(ctx, 'Pemancar', 80, y + 44, { u: 14, b: true });
      laptopK(ctx, 830, y + 6); teksK(ctx, 'Penerima', 830, y + 50, { u: 14, b: true });
      batang(ctx, 812, y - 30, persen(-55 - (benda ? BENDA[benda].loss : 0)));
      if (benda) { gbBenda(ctx, benda, ox, y); teksK(ctx, BENDA[benda].nama, ox, 240, { u: 14, b: true, c: '#5B7086' }); }
      if (B.r > 0.3) teksK(ctx, 'dipantulkan', ox - 110, 40, { u: 13, c: '#b91c1c', b: true });
    });
    function cekHalangan(diam) {
      var n = Object.keys(lihat).length, h = $('hasil-halangan'), m = [];
      if (n < 5) m.push('Baru ' + n + ' dari 5 halangan yang dicoba.');
      if ($('sel-pantul').value !== 'logam') m.push($('sel-pantul').value ? 'Benda yang paling memantulkan belum tepat. Lihat lagi animasi: gelombang merah yang kembali ke kiri adalah pantulan.' : 'Jawab pertanyaan pertama.');
      if ($('sel-lokasi').value !== 'b') m.push($('sel-lokasi').value ? 'Lokasi terbaik belum tepat: pilih tempat yang tidak ada penghalang di antara dua antena.' : 'Jawab pertanyaan kedua.');
      if (!m.length) { h.className = 'hasil-uji berhasil'; h.innerHTML = '✔ Benar. <b>Logam memantulkan</b>, sedangkan air pada pohon dan tubuh manusia <b>menyerap</b>. Link P2P butuh <b>line of sight</b>: area lapang tanpa halangan.'; if (!prog().m1_halangan) setProg({ m1_halangan: true }); }
      else if (!diam) { h.className = 'hasil-uji gagal'; h.innerHTML = m.join(' '); }
      segarkanSelesai('m1');
    }
    $('btn-cek-halangan').addEventListener('click', function () { taruh('jwbHal', [$('sel-pantul').value, $('sel-lokasi').value]); cekHalangan(false); });
    var jh = ambil('jwbHal'); if (jh) { $('sel-pantul').value = jh[0]; $('sel-lokasi').value = jh[1]; if (prog().m1_halangan) cekHalangan(true); }

    // C. Pola pancaran
    var pola = 'omni', polaLihat = ambil('polaLihat') || { omni: true };
    document.querySelectorAll('[data-pola]').forEach(function (b) {
      b.addEventListener('click', function () {
        pola = b.getAttribute('data-pola'); polaLihat[pola] = true; taruh('polaLihat', polaLihat);
        document.querySelectorAll('[data-pola]').forEach(function (x) { x.classList.toggle('aktif', x === b); });
      });
    });
    kanvas($('kv-pola'), function (ctx, t, W, H) {
      var cx = 150, cy = 150, tx = 800;
      ctx.fillStyle = '#eef7ef'; ctx.fillRect(0, 0, W, H);
      if (pola === 'omni') {
        ctx.fillStyle = 'rgba(0,155,255,.14)'; ctx.beginPath(); ctx.arc(cx, cy, 140, 0, 7); ctx.fill();
        for (var i = 0; i < 4; i++) { var r = (t * 70 + i * 35) % 140; ctx.strokeStyle = 'rgba(0,155,255,' + (1 - r / 140) + ')'; ctx.lineWidth = 3; ctx.beginPath(); ctx.arc(cx, cy, r, 0, 7); ctx.stroke(); }
        apK(ctx, cx, cy + 6);
        teksK(ctx, 'menyebar ke segala arah, jangkauan pendek', cx + 230, 40, { u: 15, b: true, c: '#0B3B7B' });
      } else {
        ctx.fillStyle = 'rgba(0,155,255,.16)'; ctx.beginPath(); ctx.moveTo(cx, cy); ctx.lineTo(tx + 40, cy - 46); ctx.quadraticCurveTo(tx + 80, cy, tx + 40, cy + 46); ctx.closePath(); ctx.fill();
        ctx.fillStyle = 'rgba(0,155,255,.10)'; ctx.beginPath(); ctx.ellipse(cx - 24, cy, 30, 22, 0, 0, 7); ctx.fill();
        for (var j = 0; j < 7; j++) { var x = cx + 30 + ((t * 160 + j * 100) % 680); var w = 10 + (x - cx) * 0.06; ctx.strokeStyle = 'rgba(0,155,255,.85)'; ctx.lineWidth = 3; ctx.beginPath(); ctx.moveTo(x, cy - w); ctx.quadraticCurveTo(x + 10, cy, x, cy + w); ctx.stroke(); }
        ctx.strokeStyle = '#5B7086'; ctx.lineWidth = 9; ctx.beginPath(); ctx.arc(cx + 28, cy, 44, Math.PI * 0.62, Math.PI * 1.38); ctx.stroke();
        ctx.fillStyle = '#0B3B7B'; ctx.fillRect(cx - 6, cy - 8, 22, 16);
        teksK(ctx, 'dipusatkan ke satu arah, jangkauan jauh', cx + 260, 40, { u: 15, b: true, c: '#0B3B7B' });
      }
      laptopK(ctx, tx, cy + 4);
      var sampai = pola === 'wajan';
      teksK(ctx, sampai ? 'Gedung B: terjangkau ✓' : 'Gedung B: di luar jangkauan ✗', tx, cy + 52, { u: 14, b: true, c: sampai ? '#0c6b31' : '#b91c1c' });
      teksK(ctx, '50 m', (cx + tx) / 2, H - 22, { u: 14, c: '#5B7086', b: true });
    });
    function cekArah(diam) {
      var h = $('hasil-arah'), m = [];
      if (!polaLihat.wajan) m.push('Lihat dulu pola pancaran antena terarah.');
      if ($('sel-arah').value !== 'b') m.push($('sel-arah').value ? 'Belum tepat. Untuk jarak jauh ke satu tujuan, sinyal perlu dipusatkan ke arah tujuan.' : 'Pilih jawabanmu.');
      if (!m.length) { h.className = 'hasil-uji berhasil'; h.innerHTML = '✔ Benar. Inilah <b>link Point-to-Point</b>: dua antena terarah yang saling berhadapan.'; if (!prog().m1_arah) setProg({ m1_arah: true }); }
      else if (!diam) { h.className = 'hasil-uji gagal'; h.innerHTML = m.join(' '); }
      segarkanSelesai('m1');
    }
    $('btn-cek-arah').addEventListener('click', function () { taruh('jwbArah', $('sel-arah').value); cekArah(false); });
    var ja = ambil('jwbArah'); if (ja) { $('sel-arah').value = ja; if (prog().m1_arah) cekArah(true); }

    pasangKuis('m1', function () { segarkanSelesai('m1'); });
    segarkanSelesai('m1');
    $('btn-m1-selesai').addEventListener('click', function () { window.location.href = 'hotspot.html'; });
  }

  /* ============================================================ MISI 2: HOTSPOT, SSID, DAN KONEKSI */
  if (halaman === 'hotspot') {
    bannerUrut('m1', 'Misi 1', 'sinyal.html');
    var SSID_AWAL = 'DESKTOP-7QK2 4821';
    var TETANGGA = [
      { ssid: 'SMKN1-SAWIT', aman: true, bar: 4 },
      { ssid: 'Lab-TKJ', aman: true, bar: 3 },
      { ssid: 'AndroidAP-3F2', aman: true, bar: 2 },
      { ssid: 'Guest-Kantin', aman: false, bar: 2 }
    ];
    var hs = ambil('hs') || { share: '', ssid: SSID_AWAL, pass: 'rahasia', band: 'any', aktif: false };
    var rx = ambil('rx') || { konek: false, ip: 0 };
    var buka = '';   // SSID yang sedang dibuka di daftar WiFi
    var pesanRx = '';
    $('hs-share').value = hs.share; $('hs-ssid').value = hs.ssid; $('hs-pass').value = hs.pass; $('hs-band').value = hs.band;

    function bars(n) { var h = '<span class="wifi-bar" aria-label="sinyal ' + n + ' dari 4">'; for (var i = 1; i <= 4; i++) h += '<i class="' + (i <= n ? 'on' : '') + '" style="height:' + (i * 4 + 2) + 'px"></i>'; return h + '</span>'; }
    function segarLampu() {
      var l = $('hs-lampu');
      l.className = 'lampu ' + (hs.aktif ? 'hidup' : 'mati');
      l.textContent = hs.aktif ? 'Hotspot aktif' : 'Hotspot mati';
    }
    function semua() { segarLampu(); renderWifi(); renderPanel(); renderRx(); }

    ['hs-share', 'hs-ssid', 'hs-pass', 'hs-band'].forEach(function (id) {
      var ev = $(id).tagName === 'SELECT' ? 'change' : 'input';
      $(id).addEventListener(ev, function () {
        var f = { share: $('hs-share').value, ssid: $('hs-ssid').value, pass: $('hs-pass').value, band: $('hs-band').value };
        if (hs.aktif) {   // pengaturan berubah: hotspot dimatikan dan penerima terputus, seperti di Windows
          hs.aktif = false; rx = { konek: false, ip: 0 }; taruh('rx', rx); buka = ''; pesanRx = '';
          isiCek($('cek-hs'), [['info', 'Pengaturan berubah, hotspot dimatikan. Tekan Aktifkan hotspot lagi.']]);
        }
        hs.share = f.share; hs.ssid = f.ssid; hs.pass = f.pass; hs.band = f.band; taruh('hs', hs);
        semua();
      });
    });

    function periksaHs() {
      var c = [], ok = true, s = hs.ssid.trim();
      function salah(t) { ok = false; c.push(['err', t]); }
      if (!hs.share) salah('Pilih sumber internet yang dibagikan.');
      else if (hs.share === 'wifi') salah('Wi-Fi 2 (USB adapter) dipakai untuk memancarkan hotspot, jadi tidak bisa sekaligus menjadi sumber internet. Bagikan internet dari <b>Ethernet</b>.');
      else c.push(['ok', 'Internet dari Ethernet akan dibagikan ke perangkat yang tersambung.']);
      if (!s) salah('SSID masih kosong.');
      else if (s === SSID_AWAL) salah('SSID masih nama bawaan Windows. Ganti dengan nama yang jelas, misalnya nama kelompokmu (contoh: <b>KEL1-TKJ</b>), supaya penerima mudah menemukannya.');
      else if (s.length > 32) salah('SSID paling panjang 32 karakter.');
      else if (TETANGGA.some(function (t) { return t.ssid.toLowerCase() === s.toLowerCase(); })) salah('Nama <b>' + esc(s) + '</b> sudah dipakai jaringan lain di sekitar. Pakai nama yang unik.');
      else c.push(['ok', 'SSID <b>' + esc(s) + '</b> jelas dan unik.']);
      if (hs.pass.length < 8) salah('Password WPA2 minimal <b>8 karakter</b> (sekarang ' + hs.pass.length + ').');
      else c.push(['ok', 'Password ' + hs.pass.length + ' karakter.']);
      if (hs.band === '5') salah('Adapter penerima hanya mendukung 2,4 GHz. Hotspot 5 GHz tidak akan terlihat olehnya.');
      else if (hs.band === 'any') salah('<i>Any available</i> bisa saja memakai 5 GHz. Karena adapter penerima hanya 2,4 GHz, pilih <b>2.4 GHz</b>.');
      else c.push(['ok', 'Band 2,4 GHz didukung adapter penerima.']);
      return { ok: ok, cek: c };
    }
    $('btn-hs').addEventListener('click', function () {
      var r = periksaHs();
      if (r.ok) { hs.aktif = true; taruh('hs', hs); r.cek.push(['ok', '<b>Hotspot aktif.</b> Lanjut ke Bagian B untuk menyambungkan penerima.']); if (!prog().m2_hotspot) setProg({ m2_hotspot: true }); }
      isiCek($('cek-hs'), r.cek);
      semua(); segarkanSelesai('m2');
    });

    function daftarWifi() {
      var d = TETANGGA.slice();
      if (hs.aktif) d.splice(2, 0, { ssid: hs.ssid.trim(), aman: true, bar: 4, milik: true });
      return d;
    }
    function renderWifi() {
      var w = $('wifi-list'), h = '';
      if (!hs.aktif) h += '<p class="wifi-catatan">SSID kelompokmu belum muncul karena hotspot belum aktif (Bagian A).</p>';
      daftarWifi().forEach(function (n, i) {
        var tersambung = n.milik && rx.konek;
        h += '<div class="wifi-item' + (buka === n.ssid ? ' buka' : '') + (tersambung ? ' tersambung' : '') + '" role="listitem">' +
          '<button type="button" class="wifi-kepala" data-i="' + i + '">' + bars(n.bar) + '<span class="wifi-nama"><b>' + esc(n.ssid) + '</b><small>' +
          (tersambung ? 'Connected, secured' : (n.aman ? 'Secured' : 'Open')) + '</small></span></button>';
        if (buka === n.ssid && !tersambung) {
          if (n.milik) h += '<div class="wifi-form"><label class="field-label" for="rx-pass">Enter the network security key</label><input class="win-input" id="rx-pass" autocomplete="off" spellcheck="false"><button type="button" class="btn btn-primary btn-kecil" id="btn-rx">Connect</button></div>';
          else h += '<div class="wifi-form"><p class="wifi-catatan">Ini jaringan milik orang lain. Cari SSID hotspot kelompokmu.</p></div>';
        }
        h += '</div>';
      });
      if (pesanRx) h += '<p class="hasil-uji gagal">' + pesanRx + '</p>';
      w.innerHTML = h;
      var dl = daftarWifi();
      w.querySelectorAll('.wifi-kepala').forEach(function (b) {
        b.addEventListener('click', function () { var n = dl[+b.getAttribute('data-i')]; buka = buka === n.ssid ? '' : n.ssid; pesanRx = ''; renderWifi(); if ($('rx-pass')) $('rx-pass').focus(); });
      });
      if ($('btn-rx')) {
        var sambung = function () {
          if ($('rx-pass').value !== hs.pass) { pesanRx = '<b>The network security key isn\'t correct.</b> Periksa lagi password hotspot (huruf besar dan kecil berpengaruh).'; renderWifi(); return; }
          rx = { konek: true, ip: 2 + Math.floor(Math.random() * 200) }; taruh('rx', rx); buka = ''; pesanRx = '';
          if (!prog().m2_konek) setProg({ m2_konek: true });
          semua(); segarkanSelesai('m2');
        };
        $('btn-rx').addEventListener('click', sambung);
        $('rx-pass').addEventListener('keydown', function (e) { if (e.key === 'Enter') sambung(); });
      }
    }
    function renderPanel() {
      var p = $('hs-panel');
      if (!hs.aktif) { p.innerHTML = '<div class="hs-baris"><span>Mobile hotspot</span><span class="lampu mati">Off</span></div>'; return; }
      var n = rx.konek ? 1 : 0;
      p.innerHTML = '<div class="hs-baris"><span>Mobile hotspot</span><span class="lampu hidup">On</span></div>' +
        '<dl class="hs-prop"><dt>Network name</dt><dd>' + esc(hs.ssid.trim()) + '</dd><dt>Network band</dt><dd>2.4 GHz</dd><dt>Devices connected</dt><dd><b>' + n + ' of 8</b></dd></dl>' +
        (n ? '<div class="tabel-gulir"><table class="kelas-table hs-tabel"><thead><tr><th>Device name</th><th>IP address</th><th>Physical address</th></tr></thead><tbody><tr><td>LAPTOP-PENERIMA</td><td class="mono">192.168.137.' + rx.ip + '</td><td class="mono">3c:7c:3f:9a:21:5e</td></tr></tbody></table></div>' : '<p class="wifi-catatan">Belum ada perangkat yang tersambung.</p>');
    }
    function renderRx() {
      var r = $('rx-status');
      if (!(hs.aktif && rx.konek)) { r.innerHTML = ''; return; }
      r.innerHTML = '<div class="ipconfig ok"><p class="ipconfig-judul">Laptop penerima</p><dl>' +
        '<dt>Status</dt><dd>Connected, secured · Internet access</dd><dt>SSID</dt><dd>' + esc(hs.ssid.trim()) + '</dd>' +
        '<dt>IPv4 address</dt><dd class="mono">192.168.137.' + rx.ip + '</dd><dt>Default gateway</dt><dd class="mono">192.168.137.1</dd>' +
        '<dt>Uji internet</dt><dd>situs web terbuka ✓</dd></dl></div>' +
        '<p class="hasil-uji berhasil">✔ Penerima mendapat IP otomatis dari hotspot (DHCP) dan ikut memakai internet pemancar. <b>Saat praktik</b>, foto layar Mobile hotspot di laptop pemancar seperti di atas: bagian <i>Devices connected</i> menjadi bukti ada perangkat yang tersambung.</p>';
    }
    semua();
    if (prog().m2_hotspot && hs.aktif) isiCek($('cek-hs'), [['ok', 'Hotspot aktif dengan SSID <b>' + esc(hs.ssid.trim()) + '</b>.']]);

    // C. Status koneksi
    var OPSI_ST = ['Berhasil: mendapat IP dan internet', 'Password salah', 'Tidak mendapat IP dari DHCP hotspot', 'Dapat IP, tetapi internet tidak dibagikan'];
    var STATUS = [
      { k: 'Kelompok A', d: [['Status', 'Connected, no internet'], ['IPv4', '169.254.88.12'], ['Gateway', '(kosong)']], b: 2,
        j: '169.254.x.x adalah alamat darurat yang dibuat Windows sendiri saat tidak mendapat IP dari DHCP. Matikan-hidupkan hotspot, lalu sambungkan ulang.' },
      { k: 'Kelompok B', d: [['Status', 'Connected, secured · Internet access'], ['IPv4', '192.168.137.57'], ['Gateway', '192.168.137.1']], b: 0,
        j: 'IP 192.168.137.x dari hotspot dan ada akses internet. Inilah keadaan yang diharapkan.' },
      { k: 'Kelompok C', d: [['Pesan', 'The network security key isn\'t correct'], ['IPv4', '—']], b: 1,
        j: 'Penerima belum tersambung sama sekali karena kunci keamanan (password) tidak cocok.' },
      { k: 'Kelompok D', d: [['Status', 'Connected, no internet'], ['IPv4', '192.168.137.23'], ['Gateway', '192.168.137.1']], b: 3,
        j: 'Koneksi WiFi berhasil (IP dari hotspot didapat). Yang bermasalah sumber internetnya: periksa kabel LAN atau tethering di laptop pemancar.' }
    ];
    var js = ambil('status') || [];
    $('status-grid').innerHTML = STATUS.map(function (s, i) {
      return '<div class="status-kartu"><p class="ipconfig-judul">' + s.k + '</p><dl>' + s.d.map(function (x) { return '<dt>' + x[0] + '</dt><dd class="' + (/^[\d(—]/.test(x[1]) ? 'mono' : '') + '">' + esc(x[1]) + '</dd>'; }).join('') + '</dl>' +
        '<label class="field-label" for="st-' + i + '">Artinya…</label><select class="input" id="st-' + i + '"><option value="">— pilih —</option>' +
        OPSI_ST.map(function (o, j) { return '<option value="' + j + '"' + (String(js[i]) === String(j) ? ' selected' : '') + '>' + esc(o) + '</option>'; }).join('') + '</select></div>';
    }).join('');
    function cekStatus(diam) {
      var cek = [], ok = true;
      STATUS.forEach(function (s, i) {
        var v = $('st-' + i).value;
        if (v === '') { ok = false; cek.push(['err', s.k + ': belum dijawab.']); }
        else if (+v === s.b) cek.push(['ok', s.k + ': ' + s.j]);
        else { ok = false; cek.push(['err', s.k + ': belum tepat. Perhatikan alamat IP dan pesan statusnya.']); }
      });
      if (ok && !prog().m2_status) setProg({ m2_status: true });
      if (ok || !diam) isiCek($('cek-status'), cek);
      segarkanSelesai('m2');
    }
    $('btn-cek-status').addEventListener('click', function () { taruh('status', STATUS.map(function (s, i) { return $('st-' + i).value; })); cekStatus(false); });
    if (prog().m2_status) cekStatus(true);

    pasangKuis('m2', function () { segarkanSelesai('m2'); });
    segarkanSelesai('m2');
    $('btn-m2-selesai').addEventListener('click', function () { window.location.href = 'channel.html'; });
  }

  /* ============================================================ MISI 3: CHANNEL DAN INTERFERENSI */
  if (halaman === 'channel') {
    bannerUrut('m2', 'Misi 2', 'hotspot.html');
    // Spektrum 2,4 GHz: channel n berpusat di 2407 + 5n MHz, lebar ±11 MHz.
    function fx(f, W) { return 50 + (f - 2398) / 90 * (W - 100); }
    function lengkung(ctx, ch, tinggi, W, dasar, warna, isi, garis) {
      var c = 2407 + 5 * ch, x1 = fx(c - 11, W), x2 = fx(c + 11, W), xm = fx(c, W);
      ctx.beginPath(); ctx.moveTo(x1, dasar);
      ctx.bezierCurveTo(x1 + (xm - x1) * 0.25, dasar - tinggi * 1.3, x2 - (x2 - xm) * 0.25, dasar - tinggi * 1.3, x2, dasar);
      ctx.fillStyle = isi; ctx.fill();
      ctx.strokeStyle = warna; ctx.lineWidth = 3; if (garis) ctx.setLineDash(garis); ctx.stroke(); ctx.setLineDash([]);
    }
    function sumbu(ctx, W, dasar) {
      ctx.strokeStyle = '#8A99AD'; ctx.lineWidth = 2; ctx.beginPath(); ctx.moveTo(30, dasar); ctx.lineTo(W - 30, dasar); ctx.stroke();
      for (var n = 1; n <= 13; n++) { var x = fx(2407 + 5 * n, W); ctx.beginPath(); ctx.moveTo(x, dasar); ctx.lineTo(x, dasar + 6); ctx.stroke(); teksK(ctx, String(n), x, dasar + 18, { u: 14, b: true, c: '#5B7086' }); }
      teksK(ctx, 'channel', W - 40, dasar + 18, { u: 12, c: '#8A99AD', a: 'right' });
    }
    function tumpang(a, b) { return Math.abs(a - b) < 5; }

    // A. Channel tumpang tindih
    var sel = ambil('chSel') || [];
    var wadahCh = $('ch-pilih');
    for (var n = 1; n <= 13; n++) {
      (function (ch) {
        var b = document.createElement('button'); b.type = 'button'; b.className = 'chip-btn ch-btn'; b.textContent = ch; b.setAttribute('aria-pressed', 'false');
        b.addEventListener('click', function () {
          if (prog().m3_overlap) return;
          var i = sel.indexOf(ch);
          if (i >= 0) sel.splice(i, 1); else sel.push(ch);
          sel.sort(function (a, c) { return a - c; }); taruh('chSel', sel); segarCh(); $('hasil-overlap').textContent = '';
        });
        wadahCh.appendChild(b);
      })(n);
    }
    function segarCh() {
      wadahCh.querySelectorAll('.ch-btn').forEach(function (b) { var on = sel.indexOf(+b.textContent) >= 0; b.classList.toggle('aktif', on); b.setAttribute('aria-pressed', on ? 'true' : 'false'); });
    }
    segarCh();
    kanvas($('kv-spektrum'), function (ctx, t, W, H) {
      var dasar = H - 40;
      ctx.fillStyle = '#f6f9fd'; ctx.fillRect(0, 0, W, H);
      teksK(ctx, 'Pita 2,4 GHz (2400–2483 MHz)', 40, 22, { u: 14, b: true, c: '#0B3B7B', a: 'left' });
      sel.forEach(function (ch) {
        var bentrok = sel.some(function (o) { return o !== ch && tumpang(o, ch); });
        lengkung(ctx, ch, 120, W, dasar, bentrok ? '#DC2626' : '#16A34A', bentrok ? 'rgba(220,38,38,.18)' : 'rgba(22,163,74,.18)');
      });
      if (!sel.length) teksK(ctx, 'Ketuk nomor channel di bawah', W / 2, dasar - 70, { u: 15, c: '#8A99AD' });
      else teksK(ctx, 'hijau = tidak bertabrakan · merah = tumpang tindih', W - 40, 22, { u: 13, c: '#5B7086', a: 'right' });
      sumbu(ctx, W, dasar);
    });
    function cekOverlap(diam) {
      var h = $('hasil-overlap'), bentrok = [];
      for (var i = 0; i < sel.length; i++) for (var j = i + 1; j < sel.length; j++) if (tumpang(sel[i], sel[j])) bentrok.push(sel[i] + ' dan ' + sel[j]);
      if (sel.length === 3 && !bentrok.length) {
        h.className = 'hasil-uji berhasil';
        h.innerHTML = '✔ Benar, channel ' + sel.join(', ') + ' tidak saling tumpang tindih karena jaraknya minimal 5 channel (25 MHz).' + (sel.join() === '1,6,11' ? '' : ' Yang paling umum dipakai adalah <b>1, 6, dan 11</b>.');
        if (!prog().m3_overlap) setProg({ m3_overlap: true });
      } else if (!diam) {
        h.className = 'hasil-uji gagal';
        h.innerHTML = sel.length !== 3 ? 'Pilih tepat tiga channel (sekarang ' + sel.length + ').' : 'Masih ada yang tumpang tindih: ' + bentrok.join('; ') + '. Jarak antar-channel harus minimal 5.';
      }
      segarkanSelesai('m3');
    }
    $('btn-cek-overlap').addEventListener('click', function () { cekOverlap(false); });
    $('btn-ulang-overlap').addEventListener('click', function () { if (prog().m3_overlap) return; sel = []; taruh('chSel', sel); segarCh(); $('hasil-overlap').textContent = ''; });
    if (prog().m3_overlap) cekOverlap(true);

    // B. Pilih channel paling sepi
    var LOKASI = [
      { nama: 'Lab TKJ', t: [['SMKN1-SAWIT', 1, -45], ['AndroidAP-3F2', 1, -68], ['Lab-TKJ', 6, -52], ['Ruang-Guru', 6, -60], ['Printer-DIRECT', 11, -82]] },
      { nama: 'Perpustakaan', t: [['Perpus', 11, -48], ['Guest-Kantin', 11, -55], ['Lab-MM', 6, -50], ['AndroidAP-77A', 3, -78]] }
    ];
    var WARNA = ['#009BFF', '#F7B217', '#9B5DE5', '#0B3B7B', '#5B7086'];
    function ganggu(ch, lok) {
      return Math.round(LOKASI[lok].t.reduce(function (s, x) { return s + Math.max(0, (22 - 5 * Math.abs(x[1] - ch)) / 22) * (x[2] + 100); }, 0));
    }
    function terbaik(lok) { return [1, 6, 11].reduce(function (a, b) { return ganggu(b, lok) < ganggu(a, lok) ? b : a; }); }
    var cp = ambil('chPasang') || {}, lok = 0, apCh = 0;
    var wadahLok = $('lok-pilih'), wadahAp = $('ap-pilih');
    LOKASI.forEach(function (L, i) {
      var b = document.createElement('button'); b.type = 'button'; b.className = 'chip-btn'; b.setAttribute('data-lok', i);
      b.addEventListener('click', function () { lok = i; apCh = 0; segarLok(); });
      wadahLok.appendChild(b);
    });
    [1, 6, 11].forEach(function (ch) {
      var b = document.createElement('button'); b.type = 'button'; b.className = 'chip-btn'; b.setAttribute('data-ap', ch); b.textContent = 'Channel ' + ch;
      b.addEventListener('click', function () { apCh = ch; segarLok(); $('hasil-channel').textContent = ''; });
      wadahAp.appendChild(b);
    });
    function segarLok() {
      wadahLok.querySelectorAll('[data-lok]').forEach(function (b) { var i = +b.getAttribute('data-lok'); b.classList.toggle('aktif', i === lok); b.textContent = LOKASI[i].nama + (cp[i] ? ' ✓' : ''); });
      wadahAp.querySelectorAll('[data-ap]').forEach(function (b) { b.classList.toggle('aktif', +b.getAttribute('data-ap') === apCh); });
      $('tbl-tetangga').innerHTML = '<thead><tr><th>SSID tetangga</th><th>Channel</th><th>Sinyal</th></tr></thead><tbody>' +
        LOKASI[lok].t.map(function (x, i) { return '<tr><td><span class="titik-warna" style="background:' + WARNA[i] + '"></span>' + esc(x[0]) + '</td><td class="mono">' + x[1] + '</td><td class="mono">' + x[2] + ' dBm</td></tr>'; }).join('') + '</tbody>';
      var m = $('meter-ganggu');
      if (!apCh) m.innerHTML = '<div class="meter-isi"><span>Pilih channel untuk access point-mu.</span></div>';
      else {
        var g = ganggu(apCh, lok), k = g < 30 ? 'baik' : g < 60 ? 'sedang' : 'buruk';
        m.innerHTML = '<div class="meter-isi ' + k + '"><span>Gangguan di channel ' + apCh + '</span><span class="meter-batang"><i style="width:' + Math.min(100, g) + '%"></i></span><b>' + (k === 'baik' ? 'Rendah' : k === 'sedang' ? 'Sedang' : 'Tinggi') + '</b></div>';
      }
    }
    segarLok();
    kanvas($('kv-analyzer'), function (ctx, t, W, H) {
      var dasar = H - 40, L = LOKASI[lok];
      ctx.fillStyle = '#f6f9fd'; ctx.fillRect(0, 0, W, H);
      ctx.strokeStyle = '#e1e8f1'; ctx.lineWidth = 1;
      [-40, -60, -80].forEach(function (d) { var y = dasar - (d + 100) * 3; ctx.beginPath(); ctx.moveTo(30, y); ctx.lineTo(W - 30, y); ctx.stroke(); teksK(ctx, d + ' dBm', 32, y - 9, { u: 11, c: '#8A99AD', a: 'left' }); });
      teksK(ctx, 'WiFi analyzer · ' + L.nama, W - 30, 18, { u: 14, b: true, c: '#0B3B7B', a: 'right' });
      L.t.forEach(function (x, i) {
        var tg = (x[2] + 100) * 3 / 0.975;   // puncak lengkung = 0,975 × tinggi
        lengkung(ctx, x[1], tg, W, dasar, WARNA[i], 'rgba(0,0,0,0)');
        teksK(ctx, x[0], fx(2407 + 5 * x[1], W), dasar - tg * 0.975 - 9, { u: 12, b: true, c: WARNA[i] });
      });
      if (apCh) {
        var nyala = 0.5 + 0.5 * Math.sin(t * 4);
        lengkung(ctx, apCh, 120, W, dasar, '#16A34A', 'rgba(22,163,74,' + (0.12 + 0.1 * nyala) + ')', [8, 6]);
        teksK(ctx, 'AP-mu', fx(2407 + 5 * apCh, W), 34, { u: 13, b: true, c: '#0c6b31' });
      }
      sumbu(ctx, W, dasar);
    });
    $('btn-pasang').addEventListener('click', function () {
      var h = $('hasil-channel');
      if (!apCh) { h.className = 'hasil-uji gagal'; h.textContent = 'Pilih channel 1, 6, atau 11 dulu.'; return; }
      var best = terbaik(lok);
      if (apCh === best) {
        cp[lok] = true; taruh('chPasang', cp);
        var lain = LOKASI.map(function (_, i) { return i; }).filter(function (i) { return !cp[i]; });
        h.className = 'hasil-uji berhasil';
        h.innerHTML = '✔ Tepat. Di ' + LOKASI[lok].nama + ', channel ' + apCh + ' paling sepi.' + (lain.length ? ' Sekarang coba lokasi <b>' + LOKASI[lain[0]].nama + '</b>.' : ' Kedua lokasi selesai: channel terbaik bergantung pada tetangga di sekitar, jadi selalu periksa dulu dengan WiFi analyzer.');
        if (!lain.length && !prog().m3_channel) setProg({ m3_channel: true });
      } else {
        h.className = 'hasil-uji gagal';
        h.innerHTML = 'Channel ' + apCh + ' masih banyak gangguan. Lihat channel mana yang dipakai jaringan tetangga yang sinyalnya kuat, lalu hindari channel itu.';
      }
      segarLok(); segarkanSelesai('m3');
    });
    if (prog().m3_channel) { var hc = $('hasil-channel'); hc.className = 'hasil-uji berhasil'; hc.textContent = '✔ Kedua lokasi sudah mendapat channel terbaik.'; }

    // C & D: pilihan per butir
    function pasangPilihan(cfg) {
      var jw = ambil(cfg.kunci) || [];
      $(cfg.wadah).innerHTML = cfg.data.map(function (k, i) {
        return '<div class="field"><label class="field-label" for="' + cfg.kunci + '-' + i + '">' + (i + 1) + '. ' + esc(k.s) + '</label><select class="input" id="' + cfg.kunci + '-' + i + '"><option value="">' + cfg.kosong + '</option>' +
          k.o.map(function (o, j) { return '<option value="' + j + '"' + (String(jw[i]) === String(j) ? ' selected' : '') + '>' + esc(o) + '</option>'; }).join('') + '</select></div>';
      }).join('');
      function cek(diam) {
        var c = [], ok = true;
        cfg.data.forEach(function (k, i) {
          var v = $(cfg.kunci + '-' + i).value;
          if (v === '') { ok = false; c.push(['err', (i + 1) + ': belum dijawab.']); }
          else if (+v === k.b) c.push(['ok', (i + 1) + ': ' + esc(k.j)]);
          else { ok = false; c.push(['err', (i + 1) + ': belum tepat. ' + cfg.petunjuk]); }
        });
        if (ok && !prog()[cfg.tugas]) { var pt = {}; pt[cfg.tugas] = true; setProg(pt); }
        if (ok || !diam) isiCek($(cfg.cekEl), c);
        segarkanSelesai('m3');
      }
      $(cfg.tombol).addEventListener('click', function () { taruh(cfg.kunci, cfg.data.map(function (k, i) { return $(cfg.kunci + '-' + i).value; })); cek(false); });
      if (prog()[cfg.tugas]) cek(true);
    }
    var PITA = ['2,4 GHz', '5 GHz'];
    pasangPilihan({ kunci: 'band', wadah: 'band', tombol: 'btn-cek-band', cekEl: 'cek-band', tugas: 'm3_band', kosong: '— pilih pita —', petunjuk: 'Bandingkan jangkauan, kecepatan, dan dukungan perangkat di tabel.', data: [
      { s: 'Laptop berada di ruang sebelah, terhalang dua tembok dari access point.', o: PITA, b: 0, j: '2,4 GHz lebih jauh dan lebih tembus tembok.' },
      { s: 'Kelas penuh dengan 30 HP di dekat access point dan banyak WiFi tetangga; semua butuh koneksi cepat.', o: PITA, b: 1, j: '5 GHz lebih cepat dan punya banyak channel yang tidak tumpang tindih, jadi lebih lega.' },
      { s: 'USB WiFi adapter murah yang hanya mendukung 2,4 GHz, seperti di praktik nanti.', o: PITA, b: 0, j: 'Perangkat hanya bisa memakai pita yang didukungnya.' },
      { s: 'Menonton video di ruangan yang sama dengan access point, jarak 3 meter.', o: PITA, b: 1, j: 'Jarak dekat tanpa halangan cocok untuk 5 GHz yang lebih cepat.' }
    ] });
    pasangPilihan({ kunci: 'kasus', wadah: 'kasus', tombol: 'btn-cek-kasus', cekEl: 'cek-kasus', tugas: 'm3_kasus', kosong: '— pilih penyebab —', petunjuk: 'Pikirkan apa yang sudah berhasil dan apa yang belum.', data: [
      { s: 'Hotspot pemancar terlihat di HP, tetapi SSID-nya tidak muncul di laptop penerima yang memakai USB adapter 2,4 GHz.', o: ['Password kurang dari 8 karakter', 'Band hotspot diatur 5 GHz', 'DHCP gagal', 'Kabel LAN pemancar tercabut'], b: 1,
        j: 'HP mendukung 5 GHz sehingga bisa melihatnya, adapter 2,4 GHz tidak. Atur band hotspot ke 2.4 GHz.' },
      { s: 'Access point lab memakai channel 6, sama dengan belasan WiFi lain di sekitar. Sinyalnya kuat, tetapi koneksi lambat dan sering putus.', o: ['Password salah', 'Interferensi dengan jaringan lain di channel yang sama', 'Jarak terlalu jauh', 'Band 5 GHz tidak didukung'], b: 1,
        j: 'Sinyal kuat tetapi channel-nya padat. Pindahkan ke channel yang lebih sepi (1 atau 11) atau ke 5 GHz.' },
      { s: 'Laptop penerima tersambung dan mendapat IP 192.168.137.40, tetapi statusnya No internet. Hotspot membagikan internet dari Ethernet.', o: ['Kabel LAN laptop pemancar tidak tersambung ke internet', 'Password salah', 'Channel tumpang tindih', 'Adapter penerima rusak'], b: 0,
        j: 'Penerima sudah mendapat IP dari hotspot, jadi koneksi WiFi berhasil. Yang tidak ada adalah sumber internet di pemancar: periksa kabel LAN.' }
    ] });

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
      ['Misi 1 · Tugas sifat sinyal (' + s.m1.nTugas + '/3)', s.m1.tugas + ' / ' + s.m1.maksTugas],
      ['Misi 1 · Kuis (' + s.m1.benar + '/5 benar × 3)', s.m1.kuis + ' / ' + s.m1.maksKuis],
      ['Misi 2 · Tugas hotspot & koneksi (' + s.m2.nTugas + '/3)', s.m2.tugas + ' / ' + s.m2.maksTugas],
      ['Misi 2 · Kuis (' + s.m2.benar + '/5 benar × 3)', s.m2.kuis + ' / ' + s.m2.maksKuis],
      ['Misi 3 · Tugas channel & interferensi (' + s.m3.nTugas + '/4)', s.m3.tugas + ' / ' + s.m3.maksTugas],
      ['Misi 3 · Kuis (' + s.m3.benar + '/5 benar × 4)', s.m3.kuis + ' / ' + s.m3.maksKuis]
    ];
    var ul = $('result-breakdown');
    baris.forEach(function (b) { var li = document.createElement('li'); li.innerHTML = '<span>' + esc(b[0]) + '</span><span class="result-value">' + b[1] + '</span>'; ul.appendChild(li); });
    var HAL = { m1: ['Misi 1', 'sinyal.html'], m2: ['Misi 2', 'hotspot.html'], m3: ['Misi 3', 'channel.html'] };
    ['m1', 'm2', 'm3'].forEach(function (m) {
      if (ph[m] && s[m].tugas === s[m].maksTugas && s[m].benar === 5) return;
      var li = document.createElement('li'); li.className = 'saran';
      li.innerHTML = '<span>' + (ph[m] ? 'Mau skor penuh? Ulangi kuis ' + HAL[m][0] + '.' : HAL[m][0] + ' belum selesai.') + '</span><a class="btn btn-ghost" href="' + HAL[m][1] + '">Ke ' + HAL[m][0] + '</a>';
      ul.appendChild(li);
    });
  }
});
