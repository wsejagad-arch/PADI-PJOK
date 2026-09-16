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
        $hasil = loginSiswaTanpaAkun($conn, $_POST['nama'] ?? '', $_POST['token'] ?? '');
        if (!empty($hasil['success'])) {
            // Jika login pakai token langsung sukses, arahkan ke dashboard siswa atau aktivitas
            padi_kembali('dashboard-siswa.php');
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
  <meta name="description" content="Platform penilaian autentik digital integratif untuk mata pelajaran PJOK." />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --blue-primary: #1251c2;
      --blue-dark:    #0c3b8f;
      --text-dark:    #111827;
      --text-gray:    #4b5563;
      --text-light:   #9ca3af;
      --border:       #e5e7eb;
      --bg-page:      #f9fafb;
    }

    body {
      font-family: 'Inter', sans-serif;
      background: var(--bg-page);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px 16px;
    }

    .card {
      position: relative;
      width: 100%;
      max-width: 414px;
      background: #ffffff;
      border-radius: 28px;
      box-shadow: 0 20px 40px rgba(0,0,0,0.08);
      overflow: hidden;
    }

    /* Header */
    .header {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      padding: 12px 20px 8px;
      text-align: center;
      z-index: 20;
      /* Optional: gradient to ensure text readability if it overlaps the image */
      background: linear-gradient(to bottom, rgba(255,255,255,1) 40%, rgba(255,255,255,0) 100%);
    }

    .logo-row {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      margin-bottom: 2px;
    }

    .logo-icon {
      width: 42px;
      height: 42px;
    }

    .logo-text {
      font-size: 26px;
      font-weight: 800;
      color: var(--blue-primary);
      letter-spacing: -0.5px;
      margin-top: -4px;
    }

    .logo-subtitle {
      font-size: 13px;
      color: var(--text-gray);
      font-weight: 600; /* Increased slightly for better stroke rendering */
      margin-top: -4px;
      text-shadow: 
        -1px -1px 0 #fff,  
         1px -1px 0 #fff,
        -1px  1px 0 #fff,
         1px  1px 0 #fff,
         0px  2px 4px rgba(255,255,255,0.8);
    }

    /* Hero */
    .hero-wrap {
      width: 100%;
      position: relative;
      margin-bottom: -48px;
      margin-top: -24px; /* Pulls image up if needed, but header is absolute now */
    }
    
    .hero-wrap img {
      width: 100%;
      height: auto;
      display: block;
      -webkit-mask-image: linear-gradient(to bottom, rgba(0,0,0,1) 60%, rgba(0,0,0,0) 100%);
      mask-image: linear-gradient(to bottom, rgba(0,0,0,1) 60%, rgba(0,0,0,0) 100%);
    }

    /* Content Body */
    .content-body {
      padding: 0 24px 24px;
      position: relative;
      z-index: 10;
    }

    /* Welcome Banner */
    .welcome-banner {
      background: #ffffff;
      border-radius: 16px;
      padding: 16px;
      display: flex;
      align-items: flex-start;
      gap: 14px;
      box-shadow: 0 4px 24px rgba(0,0,0,0.06);
      margin-bottom: 16px;
    }

    .welcome-icon {
      width: 52px;
      height: 52px;
      border-radius: 50%;
      background: #eff4ff;
      color: var(--blue-primary);
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }

    .welcome-text {
      padding-top: 2px;
    }

    .welcome-text h2 {
      font-size: 14px;
      font-weight: 700;
      color: var(--text-dark);
      margin-bottom: 4px;
      line-height: 1.4;
      display: block;
    }

    .welcome-text p {
      font-size: 12px;
      color: var(--text-gray);
      line-height: 1.5;
      display: block;
    }

    /* Tabs */
    .tabs {
      display: flex;
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 4px;
      margin-bottom: 16px;
      background: #ffffff;
    }

    .tab-btn {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      padding: 10px;
      border-radius: 8px;
      font-family: inherit;
      font-size: 14px;
      font-weight: 600;
      border: none;
      background: transparent;
      color: var(--text-gray);
      cursor: pointer;
      transition: all 0.2s;
    }

    .tab-btn.active {
      background: var(--blue-primary);
      color: #ffffff;
    }

    /* Form Box */
    .form-card {
      border: 1px solid var(--border);
      border-radius: 16px;
      padding: 20px 16px;
      background: #ffffff;
      margin-bottom: 16px;
    }

    .form-group {
      margin-bottom: 16px;
    }

    .form-label {
      display: block;
      font-size: 13px;
      font-weight: 600;
      color: var(--text-dark);
      margin-bottom: 8px;
    }

    .input-wrap {
      position: relative;
    }

    .left-icon {
      position: absolute;
      left: 14px;
      top: 50%;
      transform: translateY(-50%);
      width: 18px;
      height: 18px;
      color: var(--text-light);
    }

    .form-input {
      width: 100%;
      padding: 12px 14px 12px 42px;
      border: 1px solid var(--border);
      border-radius: 8px;
      font-family: inherit;
      font-size: 13px;
      color: var(--text-dark);
      background: #ffffff;
      outline: none;
      transition: border-color 0.2s;
    }

    .form-input:focus {
      border-color: var(--blue-primary);
    }

    .form-input::placeholder {
      color: var(--text-light);
    }

    .toggle-pw {
      position: absolute;
      right: 14px;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      cursor: pointer;
      color: var(--text-light);
      display: flex;
      align-items: center;
    }

    .forgot-row {
      display: flex;
      justify-content: flex-end;
      margin-top: -6px;
      margin-bottom: 16px;
    }

    .forgot-link {
      font-size: 12px;
      font-weight: 600;
      color: var(--blue-primary);
      text-decoration: none;
    }

    .btn-submit {
      width: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      padding: 13px;
      background: var(--blue-primary);
      color: #ffffff;
      border: none;
      border-radius: 8px;
      font-family: inherit;
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
      transition: background 0.2s;
    }

    .btn-submit:hover {
      background: var(--blue-dark);
    }

    /* Student Banner */
    .student-banner {
      display: flex;
      align-items: center;
      gap: 12px;
      background: #f4f7ff;
      border: 1px solid #dbeafe;
      border-radius: 12px;
      padding: 16px;
      text-decoration: none;
      margin-bottom: 24px;
    }

    .student-banner-icon {
      width: 44px;
      height: 44px;
      border-radius: 50%;
      border: 1px solid #bfdbfe;
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--blue-primary);
      flex-shrink: 0;
    }

    .student-banner-text {
      flex: 1;
    }

    .student-banner-text h3 {
      font-size: 13px;
      font-weight: 700;
      color: var(--text-dark);
      margin-bottom: 2px;
    }

    .student-banner-text p {
      font-size: 11px;
      color: var(--text-gray);
    }

    .student-banner-arrow {
      color: var(--blue-primary);
    }

    /* Footer Badges */
    .footer-badges {
      background: #f3f4f6;
      border-radius: 10px;
      padding: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      margin-bottom: 16px;
    }

    .badge-item {
      display: flex;
      align-items: center;
      gap: 6px;
      font-size: 10px;
      font-weight: 500;
      color: var(--text-gray);
    }

    .badge-dot {
      width: 4px;
      height: 4px;
      border-radius: 50%;
      background: #d1d5db;
    }

    .footer-version {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
      font-size: 11px;
      font-weight: 500;
      color: var(--text-light);
    }

    .form-section {
      animation: fadeIn 0.3s ease;
    }
    
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(5px); }
      to { opacity: 1; transform: translateY(0); }
    }

    /* ── Responsive adjustments for mobile screens ── */
    @media (max-width: 480px) {
      body {
        padding: 0; /* Remove padding to make it full screen on mobile */
      }
      .card {
        border-radius: 0;
        box-shadow: none;
        min-height: 100vh;
      }
      .content-body {
        padding: 0 16px 24px; /* Less side padding */
      }
      .header {
        padding: 16px 16px 8px;
      }
      .logo-icon {
        width: 36px;
        height: 36px;
      }
      .logo-text {
        font-size: 22px;
      }
      .logo-subtitle {
        font-size: 11px;
      }
      .welcome-banner {
        padding: 12px;
        gap: 10px;
      }
      .welcome-icon {
        width: 44px;
        height: 44px;
      }
      .welcome-text h2 {
        font-size: 13px;
      }
      .welcome-text p {
        font-size: 11px;
      }
      .form-card {
        padding: 16px 12px;
      }
      .footer-badges {
        flex-wrap: wrap; /* Allow wrapping if screen is too narrow */
        padding: 12px;
      }
      .badge-item {
        font-size: 9px; /* Slightly smaller to fit 3 items */
      }
    }
  </style>
</head>
<body>

<div class="card">
  <!-- Header -->
  <div class="header">
    <div class="logo-row">
      <svg class="logo-icon" viewBox="0 0 44 44" fill="none" xmlns="http://www.w3.org/2000/svg">
        <circle cx="28" cy="7" r="4.5" fill="#1A56DB"/>
        <path d="M10 38 L20 22 L17 14 L26 8" stroke="#1A56DB" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
        <path d="M17 14 L28 18 L36 14" stroke="#1A56DB" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
        <path d="M28 18 L24 30 L30 38" stroke="#1A56DB" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
        <path d="M4 28 Q14 22 24 26" stroke="#22C55E" stroke-width="2.5" stroke-linecap="round"/>
      </svg>
      <span class="logo-text">PADI-PJOK</span>
    </div>
    <p class="logo-subtitle">Penilaian Autentik Digital Integratif untuk PJOK</p>
  </div>

  <!-- Hero Image -->
  <div class="hero-wrap">
    <img src="padi_pjok_hero_1781370290670.png" alt="Hero PJOK" />
  </div>

  <!-- Content Body -->
  <div class="content-body">
    
    <!-- Welcome Banner -->
    <div class="welcome-banner">
      <div class="welcome-icon">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path>
          <rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect>
          <path d="M9 14l2 2 4-4"></path>
          <path d="M9 10h.01"></path>
          <path d="M9 18h.01"></path>
        </svg>
      </div>
      <div class="welcome-text">
        <h2>Selamat datang di PADI-PJOK!</h2>
        <p>Guru membuat sesi penilaian dan token materi. Siswa cukup masuk dengan nama lengkap dan token tanpa membuat akun.</p>
      </div>
    </div>

    <!-- Tabs -->
    <div class="tabs">
      <button type="button" class="tab-btn <?= $tab === 'guru' ? 'active' : '' ?>" id="tab-guru" onclick="switchTab('guru')">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
          <circle cx="12" cy="7" r="4"></circle>
        </svg>
        Guru
      </button>
      <button type="button" class="tab-btn <?= $tab === 'siswa' ? 'active' : '' ?>" id="tab-siswa" onclick="switchTab('siswa')">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M22 10v6M2 10l10-5 10 5-10 5z"></path>
          <path d="M6 12v5c3 3 9 3 12 0v-5"></path>
        </svg>
        Siswa
      </button>
    </div>

    <?php if ($err !== ''): ?>
      <div style="background:#FEF2F2;border:1px solid #FECACA;color:#B91C1C;border-radius:12px;padding:12px;font-size:13px;font-weight:600;margin-bottom:16px;text-align:center;">
        <?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php endif; ?>

    <!-- Form Box -->
    <div class="form-card">
      
      <!-- Panel Guru -->
      <form method="post" id="panel-guru" class="form-section" style="<?= $tab === 'guru' ? '' : 'display:none;' ?>">
        <input type="hidden" name="peran" value="guru"/>
        
        <div class="form-group">
          <label class="form-label" for="username">Email / Username</label>
          <div class="input-wrap">
            <svg class="left-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
              <polyline points="22,6 12,13 2,6"></polyline>
            </svg>
            <input class="form-input" type="text" id="username" name="username" placeholder="Masukkan email atau username" required/>
          </div>
        </div>

        <div class="form-group" style="margin-bottom: 24px;">
          <label class="form-label" for="password-guru">Password</label>
          <div class="input-wrap">
            <svg class="left-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
              <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
            </svg>
            <input class="form-input" type="password" id="password-guru" name="password" placeholder="Masukkan password" required/>
            <button type="button" class="toggle-pw" onclick="togglePw('password-guru')">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                <circle cx="12" cy="12" r="3"></circle>
              </svg>
            </button>
          </div>
        </div>

        <div class="forgot-row">
          <a class="forgot-link" href="login-guru.php">Lupa password?</a>
        </div>

        <button type="submit" class="btn-submit">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
            <circle cx="12" cy="7" r="4"></circle>
          </svg>
          Masuk sebagai Guru
        </button>
      </form>

      <!-- Panel Siswa -->
      <form method="post" id="panel-siswa" class="form-section" style="<?= $tab === 'siswa' ? '' : 'display:none;' ?>">
        <input type="hidden" name="peran" value="siswa"/>
        
        <div class="form-group">
          <label class="form-label" for="nama">Nama Lengkap / NIS</label>
          <div class="input-wrap">
            <svg class="left-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
              <circle cx="12" cy="7" r="4"></circle>
            </svg>
            <input class="form-input" type="text" id="nama" name="nama" placeholder="Masukkan Nama atau NIS" required/>
          </div>
        </div>

        <div class="form-group" style="margin-bottom: 24px;">
          <label class="form-label" for="token-siswa">Token Materi</label>
          <div class="input-wrap">
            <svg class="left-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
              <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
            </svg>
            <input class="form-input" type="text" id="token-siswa" name="token" placeholder="Masukkan Token Materi" required/>
          </div>
        </div>

        <button type="submit" class="btn-submit">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M22 10v6M2 10l10-5 10 5-10 5z"></path>
            <path d="M6 12v5c3 3 9 3 12 0v-5"></path>
          </svg>
          Masuk sebagai Siswa
        </button>
      </form>

    </div>

    <!-- Mode Siswa Banner -->
    <a href="#" class="student-banner" onclick="switchTab('siswa'); return false;">
      <div class="student-banner-icon">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M22 10v6M2 10l10-5 10 5-10 5z"></path>
          <path d="M6 12v5c3 3 9 3 12 0v-5"></path>
        </svg>
      </div>
      <div class="student-banner-text">
        <h3>Mode Siswa tersedia</h3>
        <p>Siswa login dengan Nama Lengkap + Token Materi</p>
      </div>
      <div class="student-banner-arrow">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M9 18l6-6-6-6"></path>
        </svg>
      </div>
    </a>

    <!-- Footer Badges -->
    <div class="footer-badges">
      <div class="badge-item">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#6B7280" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="5" y="2" width="14" height="20" rx="2" ry="2"></rect>
          <line x1="12" y1="18" x2="12.01" y2="18"></line>
        </svg>
        Mobile friendly
      </div>
      <div class="badge-dot"></div>
      <div class="badge-item">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#22C55E" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M11 20A7 7 0 019.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 1 8.3C18 19 12.33 20 11 20z"></path>
        </svg>
        ringan
      </div>
      <div class="badge-dot"></div>
      <div class="badge-item">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#22C55E" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
          <polyline points="22 4 12 14.01 9 11.01"></polyline>
        </svg>
        tanpa akun untuk siswa
      </div>
    </div>

    <div class="footer-version">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
      </svg>
      Versi Prototype
    </div>

  </div>
</div>

<script>
  function switchTab(peran) {
    const isGuru = (peran === 'guru');
    
    document.getElementById('tab-guru').classList.toggle('active', isGuru);
    document.getElementById('tab-siswa').classList.toggle('active', !isGuru);
    
    document.getElementById('panel-guru').style.display = isGuru ? 'block' : 'none';
    document.getElementById('panel-siswa').style.display = !isGuru ? 'block' : 'none';
  }

  function togglePw(id) {
    const el = document.getElementById(id);
    if (el) {
      el.type = el.type === 'password' ? 'text' : 'password';
    }
  }
</script>

</body>
</html>
