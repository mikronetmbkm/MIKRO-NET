// Simulasi: menerima skor dari game (di dalam iframe bersandbox) lewat postMessage, lalu mengirimnya ke server.
// Kontrak untuk tim game: parent.postMessage({type:'mikronet:hasil', skor: 8, skorMaks: 10}, '*')
// Simpan progres (opsional): game menyimpan state-nya di window.name lalu mengirim
//   parent.postMessage({type:'mikronet:simpan', data: window.name}, '*')
// Saat halaman dibuka lagi, data terakhir dipasang sebagai atribut name iframe, jadi window.name game langsung berisi progres itu.
(function () {
  'use strict';
  var frame = document.getElementById('game');
  if (!frame) return;
  var url = frame.dataset.hasil, status = document.getElementById('game-status'), lanjut = document.getElementById('lanjut');
  function buka() { if (lanjut) lanjut.hidden = false; }

  // Progres game dikirim ke server sedikit tertunda (supaya tidak tiap klik), dan langsung saat halaman ditutup.
  var urlProgres = frame.dataset.progres, antre = null, tunda = null, terakhir = frame.name || '';
  function kirimProgres(tetap) {
    clearTimeout(tunda);
    if (antre === null || antre === terakhir || !urlProgres) return;
    var data = antre; antre = null;
    fetch(urlProgres, {
      method: 'POST', credentials: 'same-origin', keepalive: !!tetap,
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'fetch', 'X-CSRF-TOKEN': window.MIKRO.csrf },
      body: JSON.stringify({ data: data })
    }).then(function (r) { if (r.ok) terakhir = data; }).catch(function () {});
  }
  function antreSimpan(data) {
    if (data.length > 60000) return;
    antre = data; clearTimeout(tunda); tunda = setTimeout(kirimProgres, 800);
  }
  window.addEventListener('pagehide', function () { kirimProgres(true); });
  document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'hidden') kirimProgres(true); });
  window.addEventListener('message', function (e) {
    // Hanya terima pesan dari iframe game kita sendiri.
    if (e.source !== frame.contentWindow) return;
    var d = e.data;
    if (d && d.type === 'mikronet:simpan' && typeof d.data === 'string') { antreSimpan(d.data); return; }
    if (!d || d.type !== 'mikronet:hasil') return;
    var skor = parseInt(d.skor, 10), maks = parseInt(d.skorMaks, 10);
    if (!(maks > 0) || !(skor >= 0) || skor > maks) return;
    window.MIKRO.kirimUrl(url, { skor: skor, skorMaks: maks }).then(function (r) {
      if (status) status.textContent = r.ok ? 'Skor tercatat: ' + skor + ' / ' + maks : (r.pesan || 'Skor gagal dicatat.');
      if (r.ok) buka();
    });
  });
  // Layar penuh: pakai Fullscreen API bila ada; kalau tidak (mis. iPhone), pakai mode fokus yang menutupi seluruh layar.
  var bungkus = document.getElementById('bingkai-bungkus'), penuh = document.getElementById('game-penuh'), tutup = document.getElementById('game-tutup');
  function modeFokus(on) { bungkus.classList.toggle('layar-penuh', on); document.documentElement.classList.toggle('kunci-gulir', on); }
  if (penuh && bungkus) {
    penuh.addEventListener('click', function () {
      if (bungkus.requestFullscreen) bungkus.requestFullscreen().then(function () { bungkus.classList.add('layar-penuh'); }).catch(function () { modeFokus(true); });
      else if (bungkus.webkitRequestFullscreen) { bungkus.webkitRequestFullscreen(); bungkus.classList.add('layar-penuh'); }
      else modeFokus(true);
    });
    tutup.addEventListener('click', function () {
      if (document.fullscreenElement && document.exitFullscreen) document.exitFullscreen();
      else if (document.webkitFullscreenElement && document.webkitExitFullscreen) document.webkitExitFullscreen();
      modeFokus(false);
    });
    function keluar() { if (!document.fullscreenElement && !document.webkitFullscreenElement) modeFokus(false); }
    document.addEventListener('fullscreenchange', keluar); document.addEventListener('webkitfullscreenchange', keluar);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && bungkus.classList.contains('layar-penuh')) modeFokus(false); });
  }
  var tombol = document.getElementById('game-selesai');
  if (tombol) tombol.addEventListener('click', function () {
    window.MIKRO.kirimUrl(url, { selesai: true }).then(function (r) { if (r.ok) { buka(); if (status) status.textContent = 'Simulasi ditandai selesai'; } });
  });
})();
