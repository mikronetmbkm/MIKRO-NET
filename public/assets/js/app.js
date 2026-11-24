// JavaScript umum: tema terang/gelap, menu akun, kemunculan kartu, hitung angka, dialog konfirmasi, pembantu fetch (dengan token CSRF), salin kode, cek kode kelas.
(function () {
  'use strict';
  var meta = function (n) { var m = document.querySelector('meta[name="' + n + '"]'); return m ? m.content : ''; };
  var base = meta('base-url');
  window.MIKRO = {
    url: function (p) { return base.replace(/\/$/, '') + '/' + String(p).replace(/^\//, ''); },
    csrf: meta('csrf-token'),
    // Kirim JSON ke server. Mengembalikan Promise berisi objek JSON balasan.
    kirim: function (path, data) {
      return fetch(window.MIKRO.url(path), {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'fetch', 'X-CSRF-TOKEN': window.MIKRO.csrf },
        body: JSON.stringify(data || {})
      }).then(function (r) { return r.json().catch(function () { return { ok: false, pesan: 'Balasan server tidak dikenali.' }; }); });
    },
    kirimUrl: function (fullUrl, data) {
      return fetch(fullUrl, {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'fetch', 'X-CSRF-TOKEN': window.MIKRO.csrf },
        body: JSON.stringify(data || {})
      }).then(function (r) { return r.json().catch(function () { return { ok: false, pesan: 'Balasan server tidak dikenali.' }; }); });
    }
  };

  // Tema terang/gelap. Tanpa pilihan tersimpan, tema mengikuti pengaturan sistem.
  var akar = document.documentElement, gelapSistem = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;
  var temaSekarang = function () { return akar.getAttribute('data-theme') || (gelapSistem && gelapSistem.matches ? 'dark' : 'light'); };
  var tombolTema = document.querySelectorAll('[data-tema]');
  var segarkanTema = function () { tombolTema.forEach(function (b) { b.setAttribute('aria-pressed', temaSekarang() === 'dark' ? 'true' : 'false'); }); };
  tombolTema.forEach(function (b) {
    b.addEventListener('click', function () {
      var baru = temaSekarang() === 'dark' ? 'light' : 'dark';
      akar.setAttribute('data-theme', baru);
      try { localStorage.setItem('mikronet-tema', baru); } catch (e) {}
      segarkanTema();
    });
  });
  if (gelapSistem && gelapSistem.addEventListener) gelapSistem.addEventListener('change', segarkanTema);
  segarkanTema();

  // Menu akun di bilah atas: buka/tutup dengan klik, tutup dengan Escape atau klik di luar.
  var akunTombol = document.getElementById('akun-tombol'), akunPanel = document.getElementById('akun-panel');
  if (akunTombol && akunPanel) {
    // Panel tertutup memakai visibility:hidden (lewat CSS) supaya bisa bertransisi dan tetap tidak bisa difokus.
    var tutupAkun = function (kembalikanFokus) {
      if (!akunPanel.classList.contains('buka')) return;
      akunPanel.classList.remove('buka'); akunTombol.setAttribute('aria-expanded', 'false');
      if (kembalikanFokus) akunTombol.focus();
    };
    akunTombol.addEventListener('click', function () {
      var buka = !akunPanel.classList.contains('buka');
      akunPanel.classList.toggle('buka', buka); akunTombol.setAttribute('aria-expanded', buka ? 'true' : 'false');
      if (buka) { var pertama = akunPanel.querySelector('a,button'); if (pertama) pertama.focus(); }
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') tutupAkun(true); });
    document.addEventListener('click', function (e) { if (!e.target.closest('.akun')) tutupAkun(false); });
  }

  var gerakDikurangi = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // Angka statistik menghitung naik sekali saat tampil. Nilai akhir sudah ada di HTML (tetap benar tanpa JS).
  var hitung = function (el) {
    var akhir = parseInt(el.getAttribute('data-hitung'), 10);
    if (!(akhir > 0) || gerakDikurangi) return;
    var mulai = null, lama = 700;
    var langkah = function (t) {
      if (mulai === null) mulai = t;
      var p = Math.min(1, (t - mulai) / lama);
      el.textContent = String(Math.round(akhir * (1 - Math.pow(1 - p, 3))));
      if (p < 1) requestAnimationFrame(langkah);
    };
    requestAnimationFrame(langkah);
  };

  // Kartu bertanda data-muncul muncul saat masuk layar, sekali saja. Kartu bersebelahan diberi jeda bertahap.
  var muncul = document.querySelectorAll('[data-muncul]');
  var tampilkan = function (el) { el.classList.add('muncul'); el.querySelectorAll('[data-hitung]').forEach(hitung); };
  muncul.forEach(function (el) {
    var i = Array.prototype.indexOf.call(el.parentNode.children, el);
    el.style.setProperty('--tunda', Math.min(i, 6) * 0.06 + 's');
  });
  if ('IntersectionObserver' in window && !gerakDikurangi) {
    var pengamat = new IntersectionObserver(function (entri) {
      entri.forEach(function (en) { if (en.isIntersecting) { tampilkan(en.target); pengamat.unobserve(en.target); } });
    }, { rootMargin: '0px 0px -40px 0px' });
    muncul.forEach(function (el) { pengamat.observe(el); });
  } else {
    muncul.forEach(function (el) { el.classList.add('muncul'); });
  }

  // Dialog konfirmasi (pengganti confirm() bawaan). Tutup dengan Escape atau tombol Batal.
  var dialog = null;
  window.MIKRO.konfirmasi = function (pesan, tombolYa) {
    return new Promise(function (selesai) {
      if (!window.HTMLDialogElement) { selesai(window.confirm(pesan)); return; }
      if (!dialog) {
        dialog = document.createElement('dialog');
        dialog.className = 'dialog';
        dialog.setAttribute('aria-labelledby', 'dialog-judul');
        dialog.innerHTML = '<form method="dialog"><h2 id="dialog-judul">Yakin?</h2><p id="dialog-pesan"></p>' +
          '<div class="tombol-baris"><button class="btn btn--garis" value="batal">Batal</button><button class="btn btn--utama" value="ya" id="dialog-ya">Ya</button></div></form>';
        document.body.appendChild(dialog);
      }
      dialog.querySelector('#dialog-pesan').textContent = pesan;
      dialog.querySelector('#dialog-ya').textContent = tombolYa || 'Ya, lanjutkan';
      dialog.returnValue = '';
      dialog.addEventListener('close', function tutup() { dialog.removeEventListener('close', tutup); selesai(dialog.returnValue === 'ya'); });
      dialog.showModal();
      dialog.querySelector('#dialog-ya').focus();
    });
  };
  // Form atau tombol kirim bertanda data-konfirmasi meminta persetujuan dulu.
  document.addEventListener('submit', function (e) {
    var form = e.target, tombol = e.submitter;
    var pesan = (tombol && tombol.getAttribute('data-konfirmasi')) || form.getAttribute('data-konfirmasi');
    if (!pesan || form.dataset.disetujui === '1') { delete form.dataset.disetujui; return; }
    e.preventDefault();
    window.MIKRO.konfirmasi(pesan, (tombol && tombol.getAttribute('data-ya')) || form.getAttribute('data-ya')).then(function (ok) {
      if (!ok) return;
      form.dataset.disetujui = '1';
      if (form.requestSubmit) form.requestSubmit(tombol || undefined); else form.submit();
    });
  });

  // Pesan (toast) hilang saat diklik.
  document.querySelectorAll('.toast .flash').forEach(function (el) {
    el.addEventListener('click', function () { el.remove(); });
    if (el.classList.contains('flash--sukses') && el.textContent.indexOf('sementara') === -1) setTimeout(function () { el.remove(); }, 9000);
  });

  // Tombol "Salin".
  document.querySelectorAll('[data-salin]').forEach(function (b) {
    b.addEventListener('click', function () {
      var t = document.querySelector(b.getAttribute('data-salin'));
      if (!t) return;
      var teks = t.textContent.trim(), awal = b.textContent;
      var selesai = function () { b.textContent = 'Tersalin'; setTimeout(function () { b.textContent = awal; }, 1500); };
      if (navigator.clipboard) navigator.clipboard.writeText(teks).then(selesai, function () {}); else selesai();
    });
  });

  // Cek kode kelas langsung saat mengetik (halaman daftar).
  var kode = document.getElementById('kode'), hasil = document.getElementById('cek-kode');
  if (kode && hasil && kode.dataset.cek) {
    var tunda, cek = function () {
      var v = kode.value.trim();
      if (v.length < 4) { hasil.textContent = ''; hasil.className = 'cek-kode'; return; }
      fetch(kode.dataset.cek + '?kode=' + encodeURIComponent(v), { headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          hasil.textContent = d.ok ? 'Kelas ' + d.kelas + ' · guru ' + d.guru : d.pesan;
          hasil.className = 'cek-kode ' + (d.ok ? 'ok' : 'no');
        }).catch(function () {});
    };
    kode.addEventListener('input', function () { kode.value = kode.value.toUpperCase(); clearTimeout(tunda); tunda = setTimeout(cek, 350); });
    if (kode.value) cek();
  }

  // Tombol lihat/sembunyikan password di semua kolom password (login, daftar, ganti password, dll).
  var MATA = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>';
  var MATA_TUTUP = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 19c-6.5 0-10-7-10-7a18.5 18.5 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c6.5 0 10 7 10 7a18.5 18.5 0 0 1-2.16 3.19"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><line x1="2" y1="2" x2="22" y2="22"/></svg>';
  Array.prototype.forEach.call(document.querySelectorAll('input[type="password"]'), function (inp) {
    if (inp.closest('.sandi-bungkus')) return;
    var bungkus = document.createElement('span'); bungkus.className = 'sandi-bungkus';
    inp.parentNode.insertBefore(bungkus, inp); bungkus.appendChild(inp);
    var b = document.createElement('button'); b.type = 'button'; b.className = 'sandi-intip';
    var atur = function (lihat) {
      inp.type = lihat ? 'text' : 'password';
      b.innerHTML = lihat ? MATA_TUTUP : MATA;
      b.setAttribute('aria-label', lihat ? 'Sembunyikan password' : 'Lihat password');
      b.setAttribute('aria-pressed', lihat ? 'true' : 'false');
      b.title = b.getAttribute('aria-label');
    };
    atur(false);
    b.addEventListener('click', function () { atur(inp.type === 'password'); inp.focus(); });
    bungkus.appendChild(b);
    // Saat form dikirim, kembalikan ke password supaya browser tidak menyimpannya sebagai teks biasa.
    if (inp.form) inp.form.addEventListener('submit', function () { inp.type = 'password'; });
  });

  // Saring baris tabel (mis. halaman Penilaian): tombol [data-saring] menampilkan baris dengan data-kat yang cocok.
  var tombolSaring = document.querySelectorAll('[data-saring]');
  if (tombolSaring.length) {
    var tabelSaring = document.getElementById('tabel-nilai');
    Array.prototype.forEach.call(tombolSaring, function (b) {
      b.addEventListener('click', function () {
        var k = b.getAttribute('data-saring'), ada = 0;
        Array.prototype.forEach.call(tombolSaring, function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); });
        if (!tabelSaring) return;
        Array.prototype.forEach.call(tabelSaring.querySelectorAll('tbody tr[data-kat]'), function (tr) {
          var cocok = !k || (' ' + tr.getAttribute('data-kat') + ' ').indexOf(' ' + k + ' ') >= 0;
          tr.hidden = !cocok; if (cocok) ada++;
        });
        var kosong = tabelSaring.querySelector('.saring-kosong');
        if (kosong) kosong.hidden = ada > 0 || !tabelSaring.querySelector('tbody tr[data-kat]');
      });
    });
  }
})();

// Menu bawah (HP) yang bisa digeser: pastikan menu yang aktif terlihat.
(function () {
  var a = document.querySelector('.nav-bawah a[aria-current="page"]');
  if (!a) return;
  var n = a.parentNode;
  if (n.scrollWidth > n.clientWidth) n.scrollLeft = a.offsetLeft - (n.clientWidth - a.offsetWidth) / 2;
})();
