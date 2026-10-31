/* app.js — deteksi mode, integrasi PWA, dan penyempurnaan sidebar PADI-PJOK. */
(function () {
  'use strict';

  var akar = document.documentElement;

  function setMode() {
    var isPc = window.matchMedia('(min-width: 1024px)').matches;
    akar.setAttribute('data-mode', isPc ? 'pc' : 'mobile');
    setupSidebarDesktop(isPc);
  }

  function setupSidebarDesktop(isPc) {
    var nav = document.querySelector('nav.bottom-nav, nav.nav-bottom');
    if (!nav) return;

    // Pastikan label kategori 'Menu Utama' ada di desktop
    var heading = nav.querySelector('.sidebar-menu-heading');
    if (!heading) {
      heading = document.createElement('div');
      heading.className = 'sidebar-menu-heading';
      heading.textContent = 'Menu Utama';
      nav.insertBefore(heading, nav.firstChild);
    }

    // Pastikan tombol Keluar tersedia di desktop sidebar jika belum ada
    var logoutItem = nav.querySelector('.nav-item.nav-keluar, .nav-item-logout, a[href*="logout"]');
    if (!logoutItem) {
      var basis = window.PADI_BASE || '';
      logoutItem = document.createElement('a');
      logoutItem.href = basis + 'logout.php';
      logoutItem.className = 'nav-item nav-item-logout';
      logoutItem.setAttribute('aria-label', 'Keluar');
      logoutItem.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg><span>Keluar</span>';
      logoutItem.addEventListener('click', function (e) {
        if (!confirm('Keluar dari sesi akun saat ini?')) {
          e.preventDefault();
        }
      });
      nav.appendChild(logoutItem);
    }
  }

  setMode();
  window.addEventListener('resize', setMode, { passive: true });
  window.addEventListener('DOMContentLoaded', function () {
    setMode();
  });

  // PWA Service Worker
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
      var basis = window.PADI_BASE || '';
      navigator.serviceWorker.register(basis + 'sw.js').catch(function () {});
    });
  }

  // PWA Install Prompt
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
  window.addEventListener('load', function () { 
    document.body.appendChild(tombol); 
    setMode();
  });
})();
