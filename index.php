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
// (Buka index.php?menu=1 bila ingin tetap melihat halaman login ini.)
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

// ── Pemrosesan login satu halaman (Guru / Siswa) ──
$tab  = 'guru';            // tab aktif: guru | siswa
$err  = '';
$sukses = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $peran  = $_POST['peran'] ?? 'guru';
    $tab    = ($peran === 'siswa') ? 'siswa' : 'guru';

    if ($peran === 'siswa') {
        $hasil = loginSiswa($conn, $_POST['nis'] ?? $_POST['dokumen'] ?? '', $_POST['password'] ?? '');
        if (!empty($hasil['success'])) {
            padi_kembali('input-token.php');
        }
        $err = $hasil['message'] ?? 'Login gagal.';
    } else {
        $hasil = loginGuru($conn, $_POST['username'] ?? $_POST['email'] ?? '', $_POST['password'] ?? '');
        if (!empty($hasil['success'])) {
            padi_kembali('dashboard-guru.php');
        }
        $err = $hasil['message'] ?? 'Login gagal.';
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
    /* Header (logo + ilustrasi) sengaja DATAR/PERSEGI di bagian atas,
       persis seperti gambar acuan — bukan melengkung. */
    .card {
      position: relative;
      z-index: 1;
      width: 100%;
      max-width: 420px;
      background: var(--white);
      border-radius: 0 0 20px 20px;
      box-shadow: var(--shadow-lg);
      overflow: hidden;
      animation: slideUp .5s cubic-bezier(.22,1,.36,1) both;
    }

    @keyframes slideUp {
      from { opacity: 0; transform: translateY(32px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    /* ── Header (datar, tanpa lengkung di atas) ── */
    .header {
      padding: 18px 24px 14px;
      text-align: center;
      background: var(--white);
      border-radius: 0;
    }

    .logo-row {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      margin-bottom: 4px;
    }

    .logo-icon {
      width: 38px;
      height: 38px;
      flex-shrink: 0;
    }

    .logo-text {
      font-size: 25px;
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

    /* ── Hero illustration (datar, penuh lebar, tanpa lengkung) ── */
    .hero-wrap {
      margin: 0;
      height: 132px;
      width: 100%;
      overflow: hidden;
      position: relative;
      border-radius: 0;
      background: linear-gradient(180deg, #EFF6FF 0%, #DBEAFE 100%);
    }

    .hero-wrap img {
      width: 100%;
      height: 100%;
      object-fit: contain;
      object-position: center center;
      display: block;
    }

    /* ── Body ── */
    .body {
      padding: 16px 20px 18px;
    }

    /* ── Welcome banner ── */
    .welcome-banner {
      display: flex;
      align-items: flex-start;
      gap: 14px;
      background: var(--blue-light);
      border: 1.5px solid var(--blue-mid);
      border-radius: var(--radius-sm);
      padding: 12px 14px;
      margin-bottom: 16px;
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
      margin-bottom: 16px;
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
    .form-section { display: flex; flex-direction: column; gap: 13px; }

    .form-group { display: flex; flex-direction: column; gap: 5px; }

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
      padding: 11px 14px 11px 42px;
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
      padding: 13px;
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
      margin-top: 2px;
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
      padding: 12px 14px;
      margin-top: 14px;
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
      gap: 14px;
      padding: 11px 20px;
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
      padding: 8px;
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
    /* Di layar kecil ilustrasi dikecilkan (bukan dibesarkan) supaya
       seluruh kartu login tetap muat satu layar seperti gambar acuan. */
    @media (max-width: 440px) {
      .body { padding: 14px 16px 16px; }
      .header { padding: 14px 18px 12px; }
      .logo-icon { width: 32px; height: 32px; }
      .logo-text { font-size: 22px; }
      .logo-subtitle { font-size: 11.5px; }
      .hero-wrap { height: 112px; }
      .welcome-banner { padding: 10px 12px; margin-bottom: 13px; }
      .welcome-text h2 { font-size: 13.5px; }
      .welcome-text p { font-size: 11.5px; }
      .tabs { margin-bottom: 13px; }
      .tab-btn { padding: 9px 10px; font-size: 13.5px; }
      .form-section { gap: 11px; }
      .btn-submit { padding: 12px; font-size: 14.5px; }
      .student-banner { padding: 10px 12px; margin-top: 12px; }
      .student-banner-icon { width: 34px; height: 34px; }
      .student-banner-icon svg { width: 19px; height: 19px; }
      .footer-badges { gap: 10px; padding: 9px 14px; }
      .badge { font-size: 10px; }
    }

    /* Layar sangat pendek (mis. HP kecil/lanskap): sembunyikan ilustrasi
       agar tombol utama tetap terlihat tanpa menggulir. */
    @media (max-height: 700px) {
      .hero-wrap { display: none; }
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
        <p>Guru membuat sesi penilaian dan token materi. Siswa cukup masuk dengan Nomor Induk dan token tanpa membuat akun.</p>
      </div>
    </div>

    <!-- ── Tabs: Guru / Siswa ── -->
    <div class="tabs" role="tablist" aria-label="Pilih peran login">
      <button type="button" class="tab-btn <?= $tab === 'guru' ? 'active' : '' ?>"
              id="tab-guru" role="tab" aria-selected="<?= $tab === 'guru' ? 'true' : 'false' ?>"
              aria-controls="panel-guru" onclick="switchTab('guru')">
        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <circle cx="12" cy="7" r="4" stroke="currentColor" stroke-width="2"/>
          <path d="M4 21c0-4 3.6-7 8-7s8 3 8 7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        Guru
      </button>
      <button type="button" class="tab-btn <?= $tab === 'siswa' ? 'active' : '' ?>"
              id="tab-siswa" role="tab" aria-selected="<?= $tab === 'siswa' ? 'true' : 'false' ?>"
              aria-controls="panel-siswa" onclick="switchTab('siswa')">
        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path d="M12 3L22 8l-10 5L2 8l10-5z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
          <path d="M6 10.5v5a6 6 0 0012 0v-5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        Siswa
      </button>
    </div>

    <?php if ($err !== ''): ?>
      <div class="alert" role="alert" style="background:#FEF2F2;border:1.5px solid #FECACA;color:#B91C1C;border-radius:10px;padding:12px 14px;font-size:13px;font-weight:600;margin-bottom:16px;">
        <?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php endif; ?>

    <!-- ── Panel Guru ── -->
    <form method="post" id="panel-guru" class="form-section" role="tabpanel" aria-labelledby="tab-guru"
          style="<?= $tab === 'guru' ? '' : 'display:none;' ?>">
      <input type="hidden" name="peran" value="guru"/>

      <div class="form-group">
        <label class="form-label" for="username">Email / Username</label>
        <div class="input-wrap">
          <span class="input-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none"><rect x="3" y="5" width="18" height="14" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M4 7l8 6 8-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </span>
          <input class="form-input" type="text" id="username" name="username"
                 placeholder="Masukkan email atau username" autocomplete="username" required/>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="password-guru">Password</label>
        <div class="input-wrap">
          <span class="input-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none"><rect x="5" y="11" width="14" height="10" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M8 11V7a4 4 0 018 0v4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
          </span>
          <input class="form-input" type="password" id="password-guru" name="password"
                 placeholder="Masukkan password" autocomplete="current-password" required/>
          <button type="button" class="toggle-pw" aria-label="Tampilkan password" onclick="togglePw('password-guru', this)">
            <svg viewBox="0 0 24 24" fill="none"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.8"/></svg>
          </button>
        </div>
      </div>

      <div class="forgot-row">
        <a class="forgot-link" href="login-guru.php">Lupa password?</a>
      </div>

      <button type="submit" class="btn-submit">
        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <circle cx="12" cy="7" r="4" stroke="currentColor" stroke-width="2"/>
          <path d="M4 21c0-4 3.6-7 8-7s8 3 8 7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        Masuk sebagai Guru
      </button>
    </form>

    <!-- ── Panel Siswa ── -->
    <form method="post" id="panel-siswa" class="form-section" role="tabpanel" aria-labelledby="tab-siswa"
          style="<?= $tab === 'siswa' ? '' : 'display:none;' ?>">
      <input type="hidden" name="peran" value="siswa"/>

      <div class="form-group">
        <label class="form-label" for="nis">Nomor Induk / NIS</label>
        <div class="input-wrap">
          <span class="input-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none"><rect x="3" y="5" width="18" height="14" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M7 9h4M7 13h7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><circle cx="17" cy="10" r="2" stroke="currentColor" stroke-width="1.8"/></svg>
          </span>
          <input class="form-input" type="text" id="nis" name="nis"
                 placeholder="Masukkan Nomor Induk / NIS" autocomplete="username" required/>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="password-siswa">Password / Token</label>
        <div class="input-wrap">
          <span class="input-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none"><rect x="5" y="11" width="14" height="10" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M8 11V7a4 4 0 018 0v4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
          </span>
          <input class="form-input" type="password" id="password-siswa" name="password"
                 placeholder="Masukkan password / token materi" autocomplete="current-password" required/>
          <button type="button" class="toggle-pw" aria-label="Tampilkan password" onclick="togglePw('password-siswa', this)">
            <svg viewBox="0 0 24 24" fill="none"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.8"/></svg>
          </button>
        </div>
      </div>

      <button type="submit" class="btn-submit">
        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path d="M12 3L22 8l-10 5L2 8l10-5z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
          <path d="M6 10.5v5a6 6 0 0012 0v-5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        Masuk sebagai Siswa
      </button>

      <p style="font-size:12px; color:var(--text-gray); line-height:1.6; text-align:center;">
        Pertama kali login? Gunakan Nomor Induk sebagai password, lalu masukkan token materi dari guru.
      </p>
    </form>

    <!-- ── Banner mode siswa ── -->
    <a href="#" class="student-banner" role="button" onclick="switchTab('siswa'); return false;">
      <div class="student-banner-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none">
          <path d="M12 3L22 8l-10 5L2 8l10-5z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
          <path d="M6 10.5v5a6 6 0 0012 0v-5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
      </div>
      <div class="student-banner-text">
        <strong>Mode Siswa tersedia</strong>
        <span>Siswa login dengan Nomor Induk + Token Materi</span>
      </div>
      <span class="student-banner-arrow" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none">
          <path d="M9 18l6-6-6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </span>
    </a>

  </div><!-- /body -->

  <!-- ── Footer badges ── -->
  <div class="footer-badges">
    <span class="badge blue">
      <svg viewBox="0 0 24 24" fill="none"><rect x="6" y="2" width="12" height="20" rx="2" stroke="currentColor" stroke-width="2"/><path d="M10 18h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
      Mobile friendly
    </span>
    <span class="badge green">
      <svg viewBox="0 0 24 24" fill="none"><path d="M12 21s7-6.5 7-12a7 7 0 10-14 0c0 5.5 7 12 7 12z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>
      ringan
    </span>
    <span class="badge blue">
      <svg viewBox="0 0 24 24" fill="none"><path d="M12 3l7 3v6c0 4.4-3 8-7 9-4-1-7-4.6-7-9V6l7-3z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M9 12l2 2 4-4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
      tanpa akun untuk siswa
    </span>
  </div>

  <div class="footer-version">
    <svg viewBox="0 0 24 24" fill="none"><path d="M12 3l7 3v6c0 4.4-3 8-7 9-4-1-7-4.6-7-9V6l7-3z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>
    Versi Prototype
  </div>

</div>

<!-- Toast notification -->
<div class="toast" id="toast" role="alert" aria-live="polite"></div>

<script>
  /* ── Ganti tab Guru / Siswa ── */
  function switchTab(peran) {
    const guru  = peran !== 'siswa';
    const tabs  = { guru: document.getElementById('tab-guru'), siswa: document.getElementById('tab-siswa') };
    const panel = { guru: document.getElementById('panel-guru'), siswa: document.getElementById('panel-siswa') };

    Object.keys(tabs).forEach(function (k) {
      const aktif = (k === 'guru') === guru;
      tabs[k].classList.toggle('active', aktif);
      tabs[k].setAttribute('aria-selected', aktif ? 'true' : 'false');
      panel[k].style.display = aktif ? '' : 'none';
      panel[k].classList.toggle('form-section--aktif', aktif);
    });

    // Fokuskan isian pertama panel yang tampil (ramah keyboard/HP).
    const isian = panel[guru ? 'guru' : 'siswa'].querySelector('input:not([type=hidden])');
    if (isian && window.innerWidth > 640) { isian.focus(); }
  }

  /* ── Tampilkan / sembunyikan password ── */
  function togglePw(id, tombol) {
    const el = document.getElementById(id);
    if (!el) return;
    const tampil = el.type === 'password';
    el.type = tampil ? 'text' : 'password';
    tombol.setAttribute('aria-label', tampil ? 'Sembunyikan password' : 'Tampilkan password');
  }

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
