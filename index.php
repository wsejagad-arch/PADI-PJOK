<?php
session_start();
require_once 'koneksi.php';
require_once 'auth.php';
pastikanTabelAuth($conn);

if (isLoginGuru()) {
    header('Location: dashboard-guru.php');
    exit;
}
if (isLoginSiswa()) {
    header('Location: dashboard-siswa.php');
    exit;
}
if (!empty($_SESSION['master_id']) && empty($_SESSION['siswa_id'])) {
    header('Location: input-token.php');
    exit;
}

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'login') {
        $hasil = loginSiswa($conn, $_POST['dokumen'] ?? '', $_POST['password'] ?? '');
        if ($hasil['success']) {
            header('Location: input-token.php');
            exit;
        }
        $err = $hasil['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>PADI-PJOK – Login Siswa</title>
  <meta name="description" content="Platform penilaian autentik digital integratif untuk mata pelajaran PJOK." />
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

    /* ── Form ── */
    .form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 16px; }

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

    .alert {
      display:flex; gap:10px; align-items:flex-start;
      background:#FEF2F2; border:1.5px solid #FECACA; color:#991B1B;
      border-radius:var(--radius-sm); padding:12px 14px;
      font-size:12.5px; line-height:1.5; margin-bottom:16px;
    }
    .alert svg { width:18px; height:18px; flex-shrink:0; margin-top:1px; }

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

    <?php if ($err !== ''): ?>
    <div class="alert" role="alert">
      <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/><path d="M12 8v4M12 16h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
      <span><?= htmlspecialchars($err) ?></span>
    </div>
    <?php endif; ?>

    <!-- ── Form Login Siswa ── -->
    <form method="post" id="form-login" novalidate>
      <input type="hidden" name="action" value="login"/>

      <div class="form-group">
        <label class="form-label" for="dokumen">Nomor Induk / NIS</label>
        <div class="input-wrap">
          <span class="input-icon"><svg viewBox="0 0 24 24" fill="none"><rect x="3" y="5" width="18" height="14" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M7 9h4M7 13h7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><circle cx="17" cy="10" r="2" stroke="currentColor" stroke-width="1.8"/></svg></span>
          <input class="form-input" type="text" id="dokumen" name="dokumen"
                 placeholder="Contoh: 12345" autocomplete="username"
                 inputmode="numeric" required/>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="password">Password</label>
        <div class="input-wrap">
          <span class="input-icon"><svg viewBox="0 0 24 24" fill="none"><rect x="5" y="11" width="14" height="10" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M8 11V7a4 4 0 018 0v4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></span>
          <input class="form-input" type="password" id="password" name="password"
                 placeholder="Masukkan password" autocomplete="current-password" required/>
          <button type="button" class="toggle-pw" aria-label="Tampilkan password" onclick="togglePw()">
            <svg viewBox="0 0 24 24" fill="none"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.8"/></svg>
          </button>
        </div>
      </div>

      <button type="submit" class="btn-submit" id="btn-masuk">
        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><polyline points="10 17 15 12 10 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><line x1="15" y1="12" x2="3" y2="12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        Login
      </button>
    </form>

  </div><!-- /body -->

</div>

<script>
  function togglePw() {
    const el = document.getElementById('password');
    el.type = el.type === 'password' ? 'text' : 'password';
    el.focus();
  }
</script>

</body>
</html>
