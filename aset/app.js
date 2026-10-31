/* app.js — deteksi mode, daftar service worker, tombol install PWA. */
(function () {
  'use strict';

  var akar = document.documentElement;

  function setMode() {
    akar.setAttribute('data-mode', window.matchMedia('(min-width: 1024px)').matches ? 'pc' : 'mobile');
  }
  setMode();
  window.addEventListener('resize', setMode, { passive: true });

  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
      var basis = window.PADI_BASE || '';
      navigator.serviceWorker.register(basis + 'sw.js').catch(function () {});
    });
  }

  var prompt = null;
  var tombol = document.createElement('button');
  tombol.id = 'padi-install';
  tombol.type = 'button';
  tombol.textContent = 'Pasang aplikasi';
  tombol.addEventListener('click', function () {
    if (!prompt) { tombol.classList.remove('show'); return; }
    prompt.prompt();
    prompt.userChoice.then(function () { prompt = null; tombol.classList.remove('show'); });
  });

  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault();
    prompt = e;
    tombol.classList.add('show');
  });
  window.addEventListener('appinstalled', function () {
    prompt = null;
    tombol.classList.remove('show');
  });
  window.addEventListener('load', function () { document.body.appendChild(tombol); });
})();
