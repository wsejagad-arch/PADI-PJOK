<?php
// index.php — Halaman utama PADI-PJOK: sambutan + pilihan login (Guru / Siswa).
// Titik masuk aman: sesi dimulai lewat helper auth-boot.php, bukan session_start()
// mentah, supaya tidak pernah muncul halaman putih karena keluaran lebih awal.
require_once __DIR__ . '/auth-boot.php';

// Bila database belum siap, jangan biarkan halaman putih: tampilkan panduan.
if (empty($conn)) {
    require_once __DIR__ . '/pesan-db.php';
    padi_halaman_db_mati($padi_db_error ?? 'Database tidak dapat dihubungi.');
}

// Pengunjung yang masih punya sesi aktif diarahkan ke halaman utamanya.
// (Buka index.php?menu=1 bila ingin tetap melihat halaman sambutan ini.)
$minta_menu = isset($_GET['menu']) || isset($_GET['pilih']);
if (!$minta_menu) {
    if (!empty($_SESSION['guru_id'])) {
        padi_kembali('dashboard-guru.php');
    } elseif (!empty($_SESSION['siswa_id'])) {
        padi_kembali('dashboard-siswa.php');
    } elseif (!empty($_SESSION['master_id'])) {
        padi_kembali('input-token.php');
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>PADI-PJOK – Penilaian Autentik Digital Integratif untuk PJOK</title>
  <meta name="description" content="Platform penilaian autentik digital integratif untuk mata pelajaran PJOK. Guru membuat sesi penilaian, siswa masuk dengan nama lengkap dan token." />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --blue-primary: #1A56DB;
      --blue-dark:    #1240A8;
      --blue-light:   #EFF4FF;
      --blue-mid:     #DBEAFE;
      --text-dark:    #111827;
      --text-gray:    #6B7280;
      --text-light:   #9CA3AF;
      --border:       #E5E7EB;
      --white:        #FFFFFF;
      --bg:           #F3F6FB;
      --green:        #22C55E;
      --shadow-sm:    0 1px 3px rgba(0,0,0,.08), 0 1px 2px rgba(0,0,0,.06);
      --shadow-md:    0 4px 16px rgba(0,0,0,.10);
      --shadow-lg:    0 10px 40px rgba(0,0,0,.14);
      --radius:       16px;
      --radius-sm:    10px;
    }

    body {
      font-family: 'Inter', sans-serif;
      background: var(--bg);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px 16px;
    }

    /* ── Floating decorative blobs ── */
    body::before, body::after {
      content: '';
      position: fixed;
      border-radius: 50%;
      filter: blur(80px);
      z-index: 0;
      pointer-events: none;
    }
    body::before {
      width: 400px; height: 400px;
      background: radial-gradient(circle, rgba(26,86,219,.18) 0%, transparent 70%);
      top: -100px; left: -100px;
    }
    body::after {
      width: 350px; height: 350px;
      background: radial-gradient(circle, rgba(34,197,94,.12) 0%, transparent 70%);
      bottom: -80px; right: -80px;
    }

    /* ── Card ── */
    .card {
      position: relative;
      z-index: 1;
      width: 100%;
      max-width: 420px;
      background: var(--white);
      border-radius: 28px;
      box-shadow: var(--shadow-lg);
      overflow: hidden;
      animation: slideUp .5s cubic-bezier(.22,1,.36,1) both;
    }

    @keyframes slideUp {
      from { opacity: 0; transform: translateY(32px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    /* ── Header ── */
    .header {
      padding: 28px 28px 0;
      text-align: center;
    }

    .logo-row {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      margin-bottom: 4px;
    }

    .logo-icon {
      width: 44px;
      height: 44px;
      flex-shrink: 0;
    }

    .logo-text {
      font-size: 28px;
      font-weight: 800;
      color: var(--blue-primary);
      letter-spacing: -.5px;
    }

    .logo-subtitle {
      font-size: 13px;
      color: var(--text-gray);
      font-weight: 500;
      margin-bottom: 0;
    }

    /* ── Hero illustration ── */
    .hero-wrap {
      margin: 16px 0 0;
      height: 200px;
      overflow: hidden;
      position: relative;
      background: linear-gradient(180deg, #EFF6FF 0%, #DBEAFE 100%);
    }

    .hero-wrap img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      object-position: center top;
      display: block;
    }

    /* ── Body ── */
    .body {
      padding: 20px 24px 24px;
    }

    /* ── Welcome banner ── */
    .welcome-banner {
      display: flex;
      align-items: flex-start;
      gap: 14px;
      background: var(--blue-light);
      border: 1.5px solid var(--blue-mid);
      border-radius: var(--radius-sm);
      padding: 14px 16px;
      margin-bottom: 20px;
    }

    .welcome-icon {
      flex-shrink: 0;
      width: 42px;
      height: 42px;
      background: var(--white);
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: var(--shadow-sm);
    }

    .welcome-icon svg { width: 24px; height: 24px; }

    .welcome-text h2 {
      font-size: 14px;
      font-weight: 700;
      color: var(--text-dark);
      margin-bottom: 4px;
    }

    .welcome-text p {
      font-size: 12px;
      color: var(--text-gray);
      line-height: 1.55;
    }

    /* ── Tabs ── */
    .tabs {
      display: flex;
      background: #F1F5F9;
      border-radius: 12px;
      padding: 4px;
      margin-bottom: 20px;
    }

    .tab-btn {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 7px;
      padding: 10px 12px;
      border: none;
      border-radius: 9px;
      font-family: inherit;
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
      transition: all .25s cubic-bezier(.22,1,.36,1);
      background: transparent;
      color: var(--text-gray);
    }

    .tab-btn.active {
      background: var(--blue-primary);
      color: var(--white);
      box-shadow: 0 4px 14px rgba(26,86,219,.35);
      transform: translateY(-1px);
    }

    .tab-btn svg { width: 18px; height: 18px; flex-shrink: 0; }

    /* ── Form ── */
    .form-section { display: flex; flex-direction: column; gap: 16px; }

    .form-group { display: flex; flex-direction: column; gap: 6px; }

    .form-label {
      font-size: 13px;
      font-weight: 600;
      color: var(--text-dark);
    }

    .input-wrap {
      position: relative;
      display: flex;
      align-items: center;
    }

    .input-icon {
      position: absolute;
      left: 14px;
      color: var(--text-light);
      display: flex;
      align-items: center;
    }

    .input-icon svg { width: 18px; height: 18px; }

    .form-input {
      width: 100%;
      padding: 12px 14px 12px 42px;
      border: 1.5px solid var(--border);
      border-radius: var(--radius-sm);
      font-family: inherit;
      font-size: 14px;
      color: var(--text-dark);
      background: var(--white);
      outline: none;
      transition: border-color .2s, box-shadow .2s;
    }

    .form-input::placeholder { color: var(--text-light); }

    .form-input:focus {
      border-color: var(--blue-primary);
      box-shadow: 0 0 0 3px rgba(26,86,219,.12);
    }

    .toggle-pw {
      position: absolute;
      right: 14px;
      background: none;
      border: none;
      cursor: pointer;
      color: var(--text-light);
      display: flex;
      align-items: center;
      padding: 0;
      transition: color .2s;
    }

    .toggle-pw:hover { color: var(--blue-primary); }
    .toggle-pw svg { width: 18px; height: 18px; }

    .forgot-row {
      display: flex;
      justify-content: flex-end;
      margin-top: -6px;
    }

    .forgot-link {
      font-size: 13px;
      font-weight: 600;
      color: var(--blue-primary);
      text-decoration: none;
      transition: opacity .2s;
    }

    .forgot-link:hover { opacity: .75; text-decoration: underline; }

    /* ── Submit button ── */
    .btn-submit {
      width: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      padding: 14px;
      background: var(--blue-primary);
      color: var(--white);
      border: none;
      border-radius: var(--radius-sm);
      font-family: inherit;
      font-size: 15px;
      font-weight: 700;
      cursor: pointer;
      box-shadow: 0 6px 20px rgba(26,86,219,.35);
      transition: all .25s cubic-bezier(.22,1,.36,1);
      margin-top: 4px;
      letter-spacing: .1px;
    }

    .btn-submit svg { width: 20px; height: 20px; }

    .btn-submit:hover {
      background: var(--blue-dark);
      box-shadow: 0 8px 28px rgba(26,86,219,.45);
      transform: translateY(-2px);
    }

    .btn-submit:active { transform: translateY(0); }

    /* ── Student mode banner ── */
    .student-banner {
      display: flex;
      align-items: center;
      gap: 14px;
      background: var(--blue-light);
      border: 1.5px solid var(--blue-mid);
      border-radius: var(--radius-sm);
      padding: 14px 16px;
      margin-top: 16px;
      cursor: pointer;
      transition: background .2s, border-color .2s;
      text-decoration: none;
    }

    .student-banner:hover { background: var(--blue-mid); border-color: var(--blue-primary); }

    .student-banner-icon {
      width: 40px;
      height: 40px;
      background: var(--blue-primary);
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }

    .student-banner-icon svg { width: 22px; height: 22px; color: var(--white); }

    .student-banner-text { flex: 1; }

    .student-banner-text strong {
      display: block;
      font-size: 13px;
      font-weight: 700;
      color: var(--text-dark);
      margin-bottom: 2px;
    }

    .student-banner-text span {
      font-size: 12px;
      color: var(--text-gray);
    }

    .student-banner-arrow {
      color: var(--blue-primary);
      display: flex;
      align-items: center;
    }

    .student-banner-arrow svg { width: 18px; height: 18px; }

    /* ── Footer badges ── */
    .footer-badges {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 16px;
      padding: 14px 24px;
      border-top: 1px solid var(--border);
      flex-wrap: wrap;
    }

    .badge {
      display: flex;
      align-items: center;
      gap: 5px;
      font-size: 11px;
      font-weight: 600;
      color: var(--text-gray);
    }

    .badge svg { width: 14px; height: 14px; }
    .badge.green svg { color: var(--green); }
    .badge.blue  svg { color: var(--blue-primary); }

    .footer-version {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 5px;
      padding: 10px;
      font-size: 11px;
      font-weight: 500;
      color: var(--text-light);
      border-top: 1px solid var(--border);
    }

    .footer-version svg { width: 13px; height: 13px; }

    /* ── Student form (hidden by default) ── */
    .form-section { animation: fadeIn .3s ease; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }

    /* ── Toast ── */
    .toast {
      position: fixed;
      bottom: 24px;
      left: 50%;
      transform: translateX(-50%) translateY(100px);
      background: #111827;
      color: #fff;
      padding: 12px 20px;
      border-radius: 10px;
      font-size: 14px;
      font-weight: 500;
      box-shadow: 0 8px 30px rgba(0,0,0,.25);
      z-index: 999;
      transition: transform .35s cubic-bezier(.22,1,.36,1);
      white-space: nowrap;
    }

    .toast.show { transform: translateX(-50%) translateY(0); }

    /* ── Responsive ── */
    @media (max-width: 440px) {
      .body { padding: 16px 18px 20px; }
      .hero-wrap { height: 170px; }
    }
  </style>
</head>
<body>

<div class="card" role="main">
  <!-- ── Header ── -->
  <div class="header">
    <div class="logo-row">
      <!-- Running person logo -->
      <svg class="logo-icon" viewBox="0 0 44 44" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <circle cx="28" cy="7" r="4.5" fill="#1A56DB"/>
        <path d="M10 38 L20 22 L17 14 L26 8" stroke="#1A56DB" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
        <path d="M17 14 L28 18 L36 14" stroke="#1A56DB" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
        <path d="M28 18 L24 30 L30 38" stroke="#1A56DB" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
        <!-- Swoosh/checkmark -->
        <path d="M4 28 Q14 22 24 26" stroke="#22C55E" stroke-width="2.5" stroke-linecap="round" fill="none"/>
      </svg>
      <span class="logo-text">PADI-PJOK</span>
    </div>
    <p class="logo-subtitle">Penilaian Autentik Digital Integratif untuk PJOK</p>
  </div>

  <!-- ── Hero ── -->
  <div class="hero-wrap">
    <img src="padi_pjok_hero_1781370290670.png"
         alt="Ilustrasi guru PJOK bersama siswa bermain olahraga" />
  </div>

  <!-- ── Body ── -->
  <div class="body">

    <!-- Welcome banner -->
    <div class="welcome-banner" role="note">
      <div class="welcome-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <rect x="5" y="2" width="14" height="20" rx="2" stroke="#1A56DB" stroke-width="1.8"/>
          <path d="M9 7h6M9 11h6M9 15h4" stroke="#1A56DB" stroke-width="1.8" stroke-linecap="round"/>
          <circle cx="17" cy="17" r="4" fill="#22C55E"/>
          <path d="M15 17l1.5 1.5L19 15" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </div>
      <div class="welcome-text">
        <h2>Selamat datang di PADI-PJOK!</h2>
        <p>Guru membuat sesi penilaian dan token materi. Siswa masuk dengan Nomor Induk dan password (token dari guru).</p>
      </div>
    </div>

    <!-- ── Pilihan halaman login (terpisah) ── -->
    <div class="form-section" style="display:flex; flex-direction:column; gap:14px;">

      <a href="login-siswa.php" class="student-banner" role="button" style="margin:0; align-items:center;">
        <div class="student-banner-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none">
            <path d="M12 3L22 8l-10 5L2 8l10-5z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
            <path d="M6 10.5v5a6 6 0 0012 0v-5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </div>
        <div class="student-banner-text">
          <strong>Login Siswa</strong>
          <span>Nomor Induk + password (token materi dari guru)</span>
        </div>
        <span class="student-banner-arrow" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none">
            <path d="M9 18l6-6-6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </span>
      </a>

      <a href="login-guru.php" class="student-banner" role="button" style="margin:0; align-items:center;">
        <div class="student-banner-icon" aria-hidden="true" style="background:#7C3AED;">
          <svg viewBox="0 0 24 24" fill="none">
            <circle cx="12" cy="7" r="4" stroke="currentColor" stroke-width="2"/>
            <path d="M4 21c0-4 3.6-7 8-7s8 3 8 7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </div>
        <div class="student-banner-text">
          <strong>Login Guru</strong>
          <span>Kelola sesi, token materi, dan penilaian siswa</span>
        </div>
        <span class="student-banner-arrow" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none">
            <path d="M9 18l6-6-6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </span>
      </a>

      <p style="font-size:12.5px; color:var(--text-gray); line-height:1.6; text-align:center; margin-top:4px;">
        Halaman login guru dan siswa terpisah. Siswa memakai Nomor Induk dan password berupa token materi dari guru.
      </p>
    </div>

  </div><!-- /body -->


</div>

<!-- Toast notification -->
<div class="toast" id="toast" role="alert" aria-live="polite"></div>

<script>
  /* ── Login guru & siswa kini di halaman terpisah ── */
  function switchTab() { /* tidak dipakai lagi */ }

  /* ── Toast ── */
  function showToast(msg) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3200);
  }
</script>

<style>
  @keyframes spin {
    from { transform: rotate(0deg); }
    to   { transform: rotate(360deg); }
  }
</style>

</body>
</html>
