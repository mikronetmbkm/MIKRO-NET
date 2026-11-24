/* MIKRO-NET — editor isi modul (praktikum, pengumpulan, LKPD, refleksi, remidi, pengayaan).
   Satu mesin form: setiap jenis isi digambarkan dengan "pembangun" yang mengembalikan {el, ambil()}.
   Hasil ambil() berformat JSON yang sama dengan berkas database/content/*.json, lalu dikirim ke server untuk diperiksa. */
(function () {
  'use strict';
  var akar = document.getElementById('editor');
  if (!akar) return;
  var JENIS = akar.dataset.jenis;
  var meta = JSON.parse(document.getElementById('data-kunci').textContent || '{}');
  var KUNCI = meta.kunci || {};
  var ASLI = meta.asli || null;
  var GALERI = meta.gambar || [];
  var kotor = false, urut = 0;

  function el(tag, at, anak) {
    var e = document.createElement(tag);
    for (var k in (at || {})) {
      if (k === 'teks') e.textContent = at[k];
      else if (k === 'html') e.innerHTML = at[k];
      else if (k === 'kelas') e.className = at[k];
      else if (k.indexOf('on') === 0) e.addEventListener(k.slice(2), at[k]);
      else if (at[k] !== false && at[k] != null) e.setAttribute(k, at[k] === true ? '' : at[k]);
    }
    (anak || []).forEach(function (c) { if (c) e.appendChild(typeof c === 'string' ? document.createTextNode(c) : c); });
    return e;
  }
  function idBaru() { return 'f' + (++urut); }
  function tandaiKotor() { kotor = true; status('Ada perubahan yang belum disimpan.', 'kotor'); }
  akar.addEventListener('input', tandaiKotor);
  akar.addEventListener('change', tandaiKotor);
  window.addEventListener('beforeunload', function (e) { if (kotor) { e.preventDefault(); e.returnValue = ''; } });
  function status(t, k) { var s = document.getElementById('editor-status'); s.textContent = t; s.className = 'mini ' + (k || ''); }

  /* ---------- widget dasar ---------- */
  function medan(label, kontrol, hint) {
    var id = kontrol.id || (kontrol.id = idBaru());
    var f = el('div', { kelas: 'field' }, [el('label', { for: id, teks: label }), kontrol]);
    if (hint) f.appendChild(el('div', { kelas: 'hint', teks: hint }));
    return f;
  }
  function teks(label, nilai, o) {
    o = o || {};
    var k = o.baris ? el('textarea', { kelas: 'input' + (o.mono ? ' input--mono' : ''), rows: o.baris, maxlength: o.maks || 2000, placeholder: o.contoh || '' })
      : el('input', { kelas: 'input' + (o.mono ? ' input--mono' : ''), maxlength: o.maks || 300, placeholder: o.contoh || '' });
    k.value = nilai == null ? '' : String(nilai);
    if (o.nonaktif) k.disabled = true;
    return { el: medan(label, k, o.hint), ambil: function () { return k.value.trim(); }, kontrol: k };
  }
  function angka(label, nilai, min, max, o) {
    o = o || {};
    var k = el('input', { kelas: 'input input--angka', type: 'number', min: min, max: max, step: 1 });
    k.value = nilai == null ? '' : nilai; if (o.nonaktif) k.disabled = true;
    return { el: medan(label, k, o.hint), ambil: function () { return parseInt(k.value, 10) || 0; }, kontrol: k };
  }
  function pilih(label, nilai, opsi, o) {
    o = o || {};
    var k = el('select', { kelas: 'input' });
    opsi.forEach(function (x) { var op = el('option', { value: x[0], teks: x[1] }); if (String(x[0]) === String(nilai)) op.selected = true; k.appendChild(op); });
    if (o.nonaktif) k.disabled = true;
    return { el: medan(label, k, o.hint), ambil: function () { return k.value; }, kontrol: k };
  }
  function centang(label, nilai) {
    var id = idBaru(), k = el('input', { type: 'checkbox', id: id }); k.checked = !!nilai;
    return { el: el('div', { kelas: 'field field--centang' }, [k, el('label', { for: id, teks: label })]), ambil: function () { return k.checked; }, kontrol: k };
  }
  function baris(label, arr, o) { // satu butir per baris
    o = o || {};
    var t = teks(label, (arr || []).join('\n'), { baris: o.baris || 3, maks: 6000, hint: o.hint || 'Satu butir per baris.', contoh: o.contoh });
    return { el: t.el, ambil: function () { return t.ambil().split('\n').map(function (s) { return s.trim(); }).filter(Boolean); } };
  }
  function tabel(label, nilai, o) { // {kolom:[], baris:[[]]}, ditulis dengan pemisah |
    o = o || {}; nilai = nilai || {};
    var wadah = el('fieldset', { kelas: 'editor-grup' }, [el('legend', { teks: label })]);
    var kol = teks('Judul kolom', (nilai.kolom || []).join(' | '), { contoh: 'Perangkat | IP Address | Gateway', hint: 'Pisahkan dengan tanda |' });
    var isi = teks('Isi tabel', (nilai.baris || []).map(function (r) { return r.join(' | '); }).join('\n'), { baris: 4, maks: 8000, mono: true, contoh: 'PC1 | 192.168.1.2 | 192.168.1.1', hint: 'Satu baris tabel per baris; pisahkan sel dengan |. Kosongkan bila tidak perlu tabel.' });
    wadah.appendChild(kol.el); wadah.appendChild(isi.el);
    return { el: wadah, ambil: function () {
      var k = kol.ambil() ? kol.ambil().split('|').map(function (s) { return s.trim(); }) : [];
      var b = isi.ambil() ? isi.ambil().split('\n').filter(function (s) { return s.trim(); }).map(function (r) { return r.split('|').map(function (s) { return s.trim(); }); }) : [];
      return k.length || b.length ? { kolom: k, baris: b } : null;
    } };
  }
  function tigaKolom(label, arr, kolom) { // array of [a,b,c]
    var t = teks(label, (arr || []).map(function (r) { return r.join(' | '); }).join('\n'), { baris: 5, maks: 12000, hint: 'Satu baris per masalah: ' + kolom.join(' | ') });
    return { el: t.el, ambil: function () { return t.ambil() ? t.ambil().split('\n').filter(function (s) { return s.trim(); }).map(function (r) { var c = r.split('|').map(function (s) { return s.trim(); }); return [c[0] || '', c[1] || '', c[2] || '']; }) : []; } };
  }
  function gambar(label, arr, maks) {
    var daftar = (arr || []).slice();
    var wadah = el('div', { kelas: 'field editor-gambar' }, [el('span', { kelas: 'label', teks: label })]);
    var deret = el('div', { kelas: 'gambar-deret' }), aksi = el('div', { kelas: 'gambar-aksi' });
    var galeri = el('select', { kelas: 'input', 'aria-label': 'Pilih gambar yang sudah diunggah' });
    var unggah = el('input', { type: 'file', accept: 'image/png,image/jpeg,image/webp,image/gif', kelas: 'sr', id: idBaru() });
    var tombolUnggah = el('label', { kelas: 'btn btn--garis btn--kecil', for: unggah.id, teks: 'Unggah gambar' });
    function isiGaleri() {
      galeri.innerHTML = ''; galeri.appendChild(el('option', { value: '', teks: '+ Pakai gambar yang sudah ada' }));
      GALERI.forEach(function (g) { galeri.appendChild(el('option', { value: g, teks: g })); });
    }
    function gambarUlang() {
      deret.innerHTML = '';
      daftar.forEach(function (g, i) {
        deret.appendChild(el('figure', { kelas: 'gambar-kecil' }, [
          el('img', { src: akar.dataset.folder + '/' + encodeURIComponent(g), alt: g, loading: 'lazy' }),
          el('figcaption', { teks: g }),
          el('button', { type: 'button', kelas: 'btn btn--bahaya btn--kecil', teks: 'Lepas', onclick: function () { daftar.splice(i, 1); gambarUlang(); tandaiKotor(); } })
        ]));
      });
      aksi.hidden = daftar.length >= maks;
    }
    galeri.addEventListener('change', function () { if (galeri.value && daftar.length < maks) { daftar.push(galeri.value); gambarUlang(); tandaiKotor(); } galeri.value = ''; });
    unggah.addEventListener('change', function () {
      var f = unggah.files[0]; if (!f) return;
      var fd = new FormData(); fd.append('gambar', f);
      tombolUnggah.textContent = 'Mengunggah…';
      fetch(akar.dataset.unggah, { method: 'POST', body: fd, headers: { 'X-CSRF-TOKEN': akar.dataset.csrf, 'X-Requested-With': 'fetch', Accept: 'application/json' } })
        .then(function (r) { return r.json(); }).then(function (j) {
          if (!j.ok) throw new Error(j.pesan || 'Unggahan gagal.');
          GALERI.push(j.nama); isiGaleri(); daftar.push(j.nama); gambarUlang(); tandaiKotor();
        }).catch(function (e) { alertGalat([e.message]); })
        .then(function () { tombolUnggah.textContent = 'Unggah gambar'; unggah.value = ''; });
    });
    isiGaleri(); gambarUlang();
    aksi.appendChild(tombolUnggah); aksi.appendChild(unggah); aksi.appendChild(galeri);
    wadah.appendChild(deret); wadah.appendChild(aksi);
    wadah.appendChild(el('div', { kelas: 'hint', teks: 'Maksimal ' + maks + ' gambar. PNG, JPG, WEBP, atau GIF, maks. 5 MB.' }));
    return { el: wadah, ambil: function () { return daftar.slice(); } };
  }

  /* ---------- daftar berulang: tambah, hapus, geser, lipat ---------- */
  function daftarUlang(label, items, buat, o) {
    o = o || {};
    var wadah = el('div', { kelas: 'editor-daftar' }), isi = el('ol', { kelas: 'daftar-item' }), entri = [];
    if (label) wadah.appendChild(el('h3', { teks: label }));
    function judul(e, i) { var d; try { d = e.w.ambil(); } catch (x) { d = {}; } return (o.awalan ? o.awalan + ' ' + (i + 1) + ': ' : '') + (o.ringkas ? o.ringkas(d) : ''); }
    function segar() {
      isi.innerHTML = '';
      entri.forEach(function (e, i) {
        var ringkas = el('span', { kelas: 'item-judul', teks: judul(e, i) || '(kosong)' });
        var kunciItem = o.terkunci && o.terkunci(e.asal);
        var tombol = el('span', { kelas: 'item-aksi' }, [
          el('button', { type: 'button', kelas: 'btn btn--garis btn--kecil', 'aria-label': 'Naikkan', teks: '↑', disabled: i === 0, onclick: function (ev) { ev.preventDefault(); entri.splice(i - 1, 0, entri.splice(i, 1)[0]); segar(); tandaiKotor(); } }),
          el('button', { type: 'button', kelas: 'btn btn--garis btn--kecil', 'aria-label': 'Turunkan', teks: '↓', disabled: i === entri.length - 1, onclick: function (ev) { ev.preventDefault(); entri.splice(i + 1, 0, entri.splice(i, 1)[0]); segar(); tandaiKotor(); } }),
          el('button', { type: 'button', kelas: 'btn btn--bahaya btn--kecil', teks: 'Hapus', disabled: !!kunciItem, title: kunciItem ? 'Sudah dijawab siswa' : '', onclick: function (ev) { ev.preventDefault(); if (!window.confirm || window.confirm('Hapus ' + (o.awalan || 'item') + ' ini?')) { entri.splice(i, 1); segar(); tandaiKotor(); } } })
        ]);
        var det = el('details', { kelas: 'item' + (kunciItem ? ' item--kunci' : ''), open: e.buka || false }, [el('summary', {}, [ringkas, kunciItem ? el('span', { kelas: 'lencana lencana--grey', teks: 'terkunci' }) : null, tombol]), e.w.el]);
        det.addEventListener('toggle', function () { e.buka = det.open; if (!det.open) ringkas.textContent = judul(e, i) || '(kosong)'; });
        isi.appendChild(el('li', {}, [det]));
      });
      if (o.berubah) o.berubah();
    }
    (items || []).forEach(function (d) { entri.push({ w: buat(d), asal: d }); });
    var tambah = el('button', { type: 'button', kelas: 'btn btn--garis btn--kecil tombol-tambah', teks: '+ ' + (o.tambah || 'Tambah'), onclick: function () {
      if (o.maks && entri.length >= o.maks) return;
      var d = o.baru ? o.baru(entri.length) : {}; entri.push({ w: buat(d), asal: null, buka: true }); segar(); tandaiKotor();
      var terakhir = isi.lastElementChild; if (terakhir) { var f = terakhir.querySelector('input,textarea,select'); if (f) f.focus(); }
    } });
    if (o.tanpaTambah) tambah.hidden = true;
    wadah.appendChild(isi); wadah.appendChild(tambah); segar();
    return { el: wadah, ambil: function () { return entri.map(function (e) { return e.w.ambil(); }); }, jumlah: function () { return entri.length; } };
  }
  function grup(judul, anak, o) {
    var g = el('section', { kelas: 'card editor-kartu' }, [el('h2', { teks: judul })]);
    if (o && o.ket) g.appendChild(el('p', { kelas: 'mini', teks: o.ket }));
    anak.forEach(function (a) { g.appendChild(a.el || a); });
    return g;
  }

  /* ---------- PRAKTIKUM ---------- */
  function editorPraktikum(d) {
    d = d || { judul: '', tahap: [] };
    var judul = teks('Judul praktikum', d.judul, { maks: 200 }), sub = teks('Keterangan singkat', d.sub, { baris: 2, maks: 600 }), durasi = teks('Durasi', d.durasi, { maks: 40, contoh: '± 90 menit' });
    var tujuan = baris('Tujuan praktikum', d.tujuan), alat = baris('Alat dan bahan', d.alat);
    var teori = daftarUlang('', d.teori, function (t) { var a = teks('Istilah', t.istilah, { maks: 100 }), b = teks('Penjelasan', t.arti, { baris: 2, maks: 500 }); return { el: el('div', {}, [a.el, b.el]), ambil: function () { return { istilah: a.ambil(), arti: b.ambil() }; } }; },
      { tambah: 'Tambah istilah', awalan: 'Istilah', ringkas: function (x) { return x.istilah; } });
    var tp = d.topologi || {};
    var tpGambar = gambar('Gambar topologi', tp.gambar ? [tp.gambar] : [], 1), tpAlt = teks('Keterangan gambar (untuk pembaca layar)', tp.alt, { maks: 200 }), tpTabel = tabel('Tabel skema IP', tp), tpCat = teks('Catatan', tp.catatan, { baris: 2, maks: 500 });
    function langkahW(l) {
      var j = teks('Judul langkah', l.judul, { maks: 150 }), isi = teks('Isi / instruksi', l.isi, { baris: 4, maks: 2500 }), gb = gambar('Gambar', l.gambar, 4);
      var kl = teks('Label kotak perintah', l.kode_label, { maks: 80, contoh: 'Command Prompt' }), kd = teks('Perintah (bisa disalin siswa)', l.kode, { baris: 3, maks: 6000, mono: true, hint: 'Kosongkan bila tidak ada perintah.' });
      var tb = tabel('Tabel (opsional)', l.tabel), ct = teks('Catatan (kotak kuning)', l.catatan, { baris: 2, maks: 1000 }), ck = baris('Checkpoint, pastikan… (opsional)', l.cek, { baris: 2 });
      var lanjut = el('details', { kelas: 'lanjutan' }, [el('summary', { teks: 'Perintah, tabel, catatan, checkpoint' }), kl.el, kd.el, tb.el, ct.el, ck.el]);
      if (l.kode || l.tabel || l.catatan || (l.cek && l.cek.length)) lanjut.open = true;
      return { el: el('div', {}, [j.el, isi.el, gb.el, lanjut]), ambil: function () { return { judul: j.ambil(), isi: isi.ambil(), gambar: gb.ambil(), kode_label: kl.ambil(), kode: kd.ambil(), tabel: tb.ambil(), catatan: ct.ambil(), cek: ck.ambil() }; } };
    }
    var tahap = daftarUlang('', d.tahap, function (t) {
      var j = teks('Judul tahap', t.judul, { maks: 120, contoh: 'Tahap A · Persiapan' });
      var l = daftarUlang('Langkah', t.langkah, langkahW, { tambah: 'Tambah langkah', awalan: 'Langkah', ringkas: function (x) { return x.judul; }, baru: function () { return { judul: '' }; } });
      return { el: el('div', {}, [j.el, l.el]), ambil: function () { return { judul: j.ambil(), langkah: l.ambil() }; } };
    }, { tambah: 'Tambah tahap', awalan: 'Tahap', ringkas: function (x) { return x.judul + ' (' + (x.langkah || []).length + ' langkah)'; }, baru: function (n) { return { judul: 'Tahap ' + String.fromCharCode(65 + n) + ' · ', langkah: [{ judul: '' }] }; } });
    var tantangan = baris('Tantangan pengayaan (opsional)', d.tantangan, { baris: 3 }), masalah = tigaKolom('Tabel troubleshooting (opsional)', d.masalah, ['Gejala', 'Kemungkinan penyebab', 'Solusi']);
    var tg = teks('Teks bila langkah tanpa gambar', d.tanpa_gambar, { maks: 200, contoh: 'Kerjakan langsung pada perangkat.' });
    akar.appendChild(grup('Informasi praktikum', [judul, sub, durasi, tujuan, alat]));
    akar.appendChild(grup('Dasar teori singkat', [teori]));
    akar.appendChild(grup('Topologi dan skema IP (opsional)', [tpGambar, tpAlt, tpTabel, tpCat]));
    akar.appendChild(grup('Tahap dan langkah', [tahap, tg], { ket: 'Siswa melihat langkah satu per satu. Klik judul untuk membuka atau menutup.' }));
    akar.appendChild(grup('Tantangan dan troubleshooting', [tantangan, masalah]));
    return function () {
      var t = tpTabel.ambil() || {};
      return { judul: judul.ambil(), sub: sub.ambil(), durasi: durasi.ambil(), tanpa_gambar: tg.ambil(), tujuan: tujuan.ambil(), alat: alat.ambil(), teori: teori.ambil(),
        topologi: { gambar: tpGambar.ambil()[0] || '', alt: tpAlt.ambil(), kolom: t.kolom || [], baris: t.baris || [], catatan: tpCat.ambil() },
        tahap: tahap.ambil(), tantangan: tantangan.ambil(), masalah: masalah.ambil() };
    };
  }

  /* ---------- PENGUMPULAN (lkpd) ---------- */
  function editorPengumpulan(d) {
    d = d || { bagian: [], rubrik: [] };
    var butirAsli = {};
    ((ASLI && ASLI.bagian) || []).forEach(function (b) { (b.butir || []).forEach(function (x) { butirAsli[x.id] = x.jenis; }); });
    var JENIS_BUTIR = [['teks', 'Isian singkat'], ['paragraf', 'Isian panjang'], ['angka', 'Angka'], ['pilihan', 'Pilihan (pilih satu)'], ['berkas', 'Unggah berkas/foto']];
    var judul = teks('Judul', d.judul, { maks: 200 }), petunjuk = teks('Petunjuk untuk siswa', d.petunjuk, { baris: 3, maks: 800 });
    function butirW(x) {
      var terkunci = KUNCI.terkumpul && x.id && butirAsli[x.id];
      var label = teks('Pertanyaan / label', x.label, { baris: 2, maks: 300 }), jenis = pilih('Jenis isian', x.jenis || 'teks', JENIS_BUTIR, { nonaktif: terkunci, hint: terkunci ? 'Terkunci karena sudah diisi siswa.' : '' });
      var pt = teks('Petunjuk (opsional)', x.petunjuk, { maks: 400 }), opsi = baris('Opsi jawaban', x.opsi, { baris: 3, hint: 'Satu opsi per baris (minimal 2).' });
      var maks = angka('Jumlah berkas maksimal', x.maks_berkas || 3, 1, 10), wajib = centang('Wajib diisi', x.wajib !== false);
      function tampil() { opsi.el.hidden = jenis.ambil() !== 'pilihan'; maks.el.hidden = jenis.ambil() !== 'berkas'; }
      jenis.kontrol.addEventListener('change', tampil); tampil();
      return { el: el('div', {}, [label.el, jenis.el, pt.el, opsi.el, maks.el, wajib.el]), ambil: function () {
        var o = { id: x.id || '', jenis: jenis.ambil(), label: label.ambil(), petunjuk: pt.ambil(), wajib: wajib.ambil() };
        if (o.jenis === 'pilihan') o.opsi = opsi.ambil(); if (o.jenis === 'berkas') o.maks_berkas = maks.ambil(); return o; } };
    }
    var bagian = daftarUlang('', d.bagian, function (b) {
      var j = teks('Judul bagian', b.judul, { maks: 150, contoh: 'A. Identitas kelompok' }), p = teks('Petunjuk bagian (opsional)', b.petunjuk, { baris: 2, maks: 600 });
      var butir = daftarUlang('Butir isian', b.butir, butirW, { tambah: 'Tambah butir', awalan: 'Butir', ringkas: function (x) { return x.label; }, baru: function () { return { jenis: 'teks', wajib: true }; },
        terkunci: function (asal) { return KUNCI.terkumpul && asal && asal.id && butirAsli[asal.id]; } });
      return { el: el('div', {}, [j.el, p.el, butir.el]), ambil: function () { return { judul: j.ambil(), petunjuk: p.ambil(), butir: butir.ambil() }; } };
    }, { tambah: 'Tambah bagian', awalan: 'Bagian', ringkas: function (x) { return x.judul; }, baru: function () { return { judul: '', butir: [{ jenis: 'teks', wajib: true }] }; } });
    var total = el('p', { kelas: 'mini total-skor' });
    var rubrik = daftarUlang('', d.rubrik, function (r) {
      var k = teks('Kriteria', r.kriteria, { baris: 2, maks: 300 }), m = angka('Skor maksimal', r.maks || 10, 1, 100, { nonaktif: KUNCI.dinilai });
      m.kontrol.addEventListener('input', hitung);
      return { el: el('div', {}, [k.el, m.el]), ambil: function () { return { kriteria: k.ambil(), maks: m.ambil() }; } };
    }, { tambah: 'Tambah kriteria', awalan: 'Kriteria', ringkas: function (x) { return x.kriteria + ' (' + x.maks + ')'; }, baru: function () { return { maks: 10 }; }, tanpaTambah: KUNCI.dinilai, terkunci: function () { return KUNCI.dinilai; }, berubah: function () { setTimeout(hitung); } });
    function hitung() { var t = rubrik.ambil().reduce(function (a, r) { return a + (r.maks || 0); }, 0); total.textContent = 'Total skor maksimal rubrik: ' + t + (t === 100 ? '' : ' (nilai tetap dihitung menjadi skala 100).'); }
    akar.appendChild(grup('Informasi', [judul, petunjuk]));
    akar.appendChild(grup('Bagian dan butir isian', [bagian], { ket: 'Butir bertanda wajib harus diisi sebelum siswa bisa mengumpulkan.' }));
    akar.appendChild(grup('Rubrik penilaian guru', [rubrik, total]));
    hitung();
    return function () { return { judul: judul.ambil(), petunjuk: petunjuk.ambil(), bagian: bagian.ambil(), rubrik: rubrik.ambil() }; };
  }

  /* ---------- SOAL: LKPD, remidi, pengayaan ---------- */
  function editorSoal(d) {
    d = d || { pg: [], uraian: [], poin_pg: 5 };
    var asliPg = {}, asliUr = {};
    ((ASLI && ASLI.pg) || []).forEach(function (s) { asliPg[s.id] = s; });
    ((ASLI && ASLI.uraian) || []).forEach(function (s) { asliUr[s.id] = s; });
    var maxNo = 0;
    (d.pg || []).concat(d.uraian || []).forEach(function (s) { var m = /(\d+)$/.exec(s.id || ''); if (m) maxNo = Math.max(maxNo, +m[1]); });
    var judul = teks('Judul', d.judul, { maks: 200 }), petunjuk = teks('Petunjuk untuk siswa', d.petunjuk, { baris: 2, maks: 800 }), tujuan = baris('Tujuan / indikator (opsional)', d.tujuan, { baris: 3 });
    var poin = angka('Poin setiap soal pilihan ganda', d.poin_pg || 5, 1, 20, { nonaktif: KUNCI.terkumpul });
    var total = el('p', { kelas: 'mini total-skor' });
    function pgW(s) {
      var kunciSoal = KUNCI.terkumpul && s.id && asliPg[s.id];
      var soal = teks('Soal', s.soal, { baris: 3, maks: 1500 });
      var nama = 'k' + idBaru(), opsiW = [], wadahOpsi = el('fieldset', { kelas: 'editor-grup opsi-pg' }, [el('legend', { teks: 'Pilihan jawaban (pilih bulatan pada kunci)' })]);
      var jumlah = kunciSoal ? (s.opsi || []).length : 5;
      for (var i = 0; i < jumlah; i++) {
        (function (i) {
          var r = el('input', { type: 'radio', name: nama, value: i, 'aria-label': 'Kunci ' + 'ABCDE'[i] }); r.checked = i === s.kunci; if (kunciSoal) r.disabled = true;
          var t = el('input', { kelas: 'input', maxlength: 400, placeholder: i < 4 ? '' : '(opsional)', 'aria-label': 'Pilihan ' + 'ABCDE'[i] }); t.value = (s.opsi || [])[i] || '';
          wadahOpsi.appendChild(el('div', { kelas: 'opsi-baris' }, [r, el('b', { teks: 'ABCDE'[i] }), t]));
          opsiW.push({ r: r, t: t });
        })(i);
      }
      if (kunciSoal) wadahOpsi.appendChild(el('div', { kelas: 'hint', teks: 'Kunci dan jumlah pilihan terkunci karena soal ini sudah dijawab siswa. Teks pilihan boleh dirapikan.' }));
      var bahas = teks('Pembahasan (tampil setelah siswa mengumpulkan)', s.bahas, { baris: 2, maks: 1500 }), topik = teks('Topik', s.topik, { maks: 60, contoh: 'Subnetting', hint: 'Dipakai untuk analisis kelemahan siswa per topik.' });
      return { el: el('div', {}, [soal.el, wadahOpsi, bahas.el, topik.el]), ambil: function () {
        var kunci = -1, opsi = opsiW.map(function (o, i) { if (o.r.checked) kunci = i; return o.t.value.trim(); });
        while (opsi.length > (kunciSoal ? (s.opsi || []).length : 2) && opsi[opsi.length - 1] === '') opsi.pop();
        if (kunciSoal) kunci = s.kunci;
        return { id: s.id || '', soal: soal.ambil(), opsi: opsi, kunci: kunci, bahas: bahas.ambil(), topik: topik.ambil() };
      } };
    }
    function urW(u) {
      var kunciSoal = KUNCI.terkumpul && u.id && asliUr[u.id];
      var soal = teks('Soal', u.soal, { baris: 3, maks: 2000 }), kunci = teks('Contoh jawaban / pedoman (untuk guru)', u.kunci, { baris: 3, maks: 3000 });
      var maks = angka('Skor maksimal', u.maks || 10, 1, 100, { nonaktif: kunciSoal, hint: kunciSoal ? 'Terkunci karena sudah dijawab siswa.' : '' }), topik = teks('Topik', u.topik, { maks: 60 });
      maks.kontrol.addEventListener('input', hitung);
      return { el: el('div', {}, [soal.el, kunci.el, maks.el, topik.el]), ambil: function () { return { id: u.id || '', soal: soal.ambil(), kunci: kunci.ambil(), maks: maks.ambil(), topik: topik.ambil() }; } };
    }
    var pg = daftarUlang('', d.pg, pgW, { tambah: 'Tambah soal pilihan ganda', awalan: 'PG', ringkas: function (x) { return x.soal; }, baru: function () { return { id: 'pg' + (++maxNo), kunci: -1, opsi: [] }; },
      terkunci: function (asal) { return KUNCI.terkumpul && asal && asal.id && asliPg[asal.id]; }, berubah: function () { setTimeout(hitung); } });
    var uraian = daftarUlang('', d.uraian, urW, { tambah: 'Tambah soal uraian', awalan: 'Uraian', ringkas: function (x) { return x.soal + ' (' + x.maks + ')'; }, baru: function () { return { id: 'u' + (++maxNo), maks: 10 }; },
      terkunci: function (asal) { return KUNCI.terkumpul && asal && asal.id && asliUr[asal.id]; }, berubah: function () { setTimeout(hitung); } });
    poin.kontrol.addEventListener('input', hitung);
    function hitung() { var t = pg.jumlah() * (poin.ambil() || 0) + uraian.ambil().reduce(function (a, u) { return a + (u.maks || 0); }, 0); total.textContent = 'Skor maksimal: ' + pg.jumlah() + ' PG × ' + poin.ambil() + ' + uraian = ' + t + (t === 100 ? '' : ' (nilai tetap dihitung menjadi skala 100).'); }
    akar.appendChild(grup('Informasi', [judul, petunjuk, tujuan, poin, total]));
    akar.appendChild(grup('Pilihan ganda', [pg], { ket: 'Dinilai otomatis saat siswa mengumpulkan.' }));
    akar.appendChild(grup('Uraian', [uraian], { ket: 'Dinilai guru di halaman Penilaian.' }));
    hitung();
    return function () { return { judul: judul.ambil(), petunjuk: petunjuk.ambil(), tujuan: tujuan.ambil(), poin_pg: poin.ambil(), pg: pg.ambil(), uraian: uraian.ambil() }; };
  }

  /* ---------- REFLEKSI ---------- */
  function editorRefleksi(d) {
    var q = baris('Pertanyaan khusus modul', (d && d.pertanyaan) || [], { baris: 5, hint: 'Satu pertanyaan per baris (maksimal 10). Pertanyaan umum (seberapa paham, hal penting, yang membingungkan) selalu ada.' });
    akar.appendChild(grup('Pertanyaan refleksi', [q]));
    return function () { return { pertanyaan: q.ambil() }; };
  }

  /* ---------- simpan ---------- */
  function alertGalat(daftar) {
    var g = document.getElementById('editor-galat');
    g.innerHTML = '<b>Belum bisa disimpan:</b><ul>' + daftar.map(function (x) { return '<li>' + String(x).replace(/[&<>]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c]; }) + '</li>'; }).join('') + '</ul>';
    g.hidden = false; g.scrollIntoView({ block: 'center' });
  }
  var ambilSemua;
  function bangun(data) {
    akar.innerHTML = '';
    ambilSemua = ({ praktikum: editorPraktikum, lkpd: editorPengumpulan, lembar: editorSoal, remidi: editorSoal, pengayaan: editorSoal, refleksi: editorRefleksi })[JENIS](data);
  }
  bangun(JSON.parse(document.getElementById('data-isi').textContent || 'null'));
  var tombol = document.getElementById('btn-simpan-isi');
  tombol.addEventListener('click', function () {
    document.getElementById('editor-galat').hidden = true;
    tombol.disabled = true; status('Menyimpan…');
    fetch(akar.dataset.simpan, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': akar.dataset.csrf, 'X-Requested-With': 'fetch', Accept: 'application/json' }, body: JSON.stringify({ isi: ambilSemua() }) })
      .then(function (r) { return r.json().catch(function () { return { ok: false, galat: ['Server membalas dengan galat (' + r.status + ').'] }; }); })
      .then(function (j) {
        if (!j.ok) { alertGalat(j.galat || [j.pesan || 'Gagal menyimpan.']); status('Belum tersimpan.', 'kotor'); return; }
        kotor = false; var y = window.scrollY; bangun(j.isi); window.scrollTo(0, y);
        status('Tersimpan pukul ' + j.waktu + '. ' + j.pesan, 'ok');
      })
      .catch(function () { alertGalat(['Koneksi terputus. Coba simpan lagi.']); status('Belum tersimpan.', 'kotor'); })
      .then(function () { tombol.disabled = false; });
  });
})();
