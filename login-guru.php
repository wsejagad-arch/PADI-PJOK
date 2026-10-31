<?php
// login-guru.php — Halaman Login Khusus Guru (Anti-Slop, Clean, Responsive)
require_once __DIR__ . '/koneksi.php';
require_once __DIR__ . '/auth.php';

if (empty($conn)) {
    require_once __DIR__ . '/pesan-db.php';
    padi_halaman_db_mati($padi_db_error ?? 'Database tidak dapat dihubungi.');
}

padi_berbagi_sesi();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Sudah login? Langsung ke dashboard guru
if (!empty($_SESSION['guru_id'])) {
    padi_kembali('dashboard-guru.php');
}

padi_pastikan_skema_auth($conn);

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'login') {
        $identitas = trim((string)($_POST['username'] ?? ''));
        $password  = (string)($_POST['password'] ?? '');
        $hasil = loginGuru($conn, $identitas, $password);
        if (!empty($hasil['success'])) {
            padi_kembali('dashboard-guru.php');
        }
        $err = $hasil['message'] ?? 'Login gagal. Periksa username dan password.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Login Guru — PADI-PJOK</title>
  <meta name="description" content="Login guru PADI-PJOK untuk mengelola sesi penilaian, token materi, dan rekap nilai."/>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  
  <style>
    .auth-page-wrap {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px 16px;
      background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    }

    .auth-card {
      width: 100%;
      max-width: 440px;
      background: #ffffff;
      border: 1px solid var(--padi-slate-200);
      border-radius: 20px;
      box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.08), 0 4px 6px -2px rgba(15, 23, 42, 0.04);
      padding: 36px 32px;
    }

    .brand-row {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 24px;
    }

    .brand-icon {
      width: 38px;
      height: 38px;
      border-radius: 10px;
      background: var(--padi-blue-light);
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--padi-blue);
    }

    .brand-icon svg {
      width: 22px;
      height: 22px;
    }

    .brand-text {
      font-size: 19px;
      font-weight: 800;
      color: var(--padi-blue);
      letter-spacing: -0.02em;
    }

    .form-header {
      margin-bottom: 24px;
    }

    .form-title {
      font-size: 20px;
      font-weight: 800;
      color: var(--padi-slate-900);
      margin-bottom: 4px;
    }

    .form-subtitle {
      font-size: 13px;
      color: var(--padi-slate-500);
    }

    .pw-toggle-btn {
      position: absolute;
      right: 8px;
      top: 50%;
      transform: translateY(-50%);
      background: transparent;
      border: none;
      color: var(--padi-slate-400);
      padding: 6px;
      border-radius: 6px;
      cursor: pointer;
      display: flex;
      align-items: center;
    }

    .pw-toggle-btn:hover {
      color: var(--padi-slate-700);
    }

    .card-footer-nav {
      display: flex;
      flex-direction: column;
      gap: 12px;
      margin-top: 24px;
      padding-top: 20px;
      border-top: 1px solid var(--padi-slate-100);
      text-align: center;
      font-size: 13px;
    }

    .card-footer-nav a {
      color: var(--padi-slate-600);
      text-decoration: none;
      font-weight: 600;
      transition: color 0.2s;
    }

    .card-footer-nav a:hover {
      color: var(--padi-blue);
    }

    @media (max-width: 480px) {
      .auth-page-wrap {
        padding: 0;
        background: #ffffff;
      }
      .auth-card {
        border-radius: 0;
        border: none;
        box-shadow: none;
        padding: 24px 20px;
      }
    }
  </style>
<?php require_once __DIR__ . '/ui-head.php'; padi_ui_head(); ?>
</head>
<body class="auth-page">

<div class="auth-page-wrap">
  <div class="auth-card">
    
    <div class="brand-row">
      <div class="brand-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M18 8h1a4 4 0 0 1 0 8h-1"></path>
          <path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"></path>
          <line x1="6" y1="1" x2="6" y2="4"></line>
          <line x1="10" y1="1" x2="10" y2="4"></line>
          <line x1="14" y1="1" x2="14" y2="4"></line>
        </svg>
      </div>
      <span class="brand-text">PADI-PJOK</span>
    </div>

    <div class="form-header">
      <h1 class="form-title">Login Guru</h1>
      <p class="form-subtitle">Kelola penilaian autentik, materi pembelajaran, dan rekap siswa.</p>
    </div>

    <?php if ($err !== ''): ?>
      <div class="padi-alert padi-alert-danger" role="alert">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10"></circle>
          <line x1="12" y1="8" x2="12" y2="12"></line>
          <line x1="12" y1="16" x2="12.01" y2="16"></line>
        </svg>
        <span><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></span>
      </div>
    <?php endif; ?>

    <form method="post" id="form-login">
      <input type="hidden" name="action" value="login"/>

      <div class="padi-form-group">
        <label class="padi-label" for="username">Email atau Username</label>
        <div class="padi-input-wrap">
          <span class="padi-input-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
              <polyline points="22,6 12,13 2,6"></polyline>
            </svg>
          </span>
          <input class="padi-input" type="text" id="username" name="username" 
                 placeholder="nama@sekolah.sch.id / username" autocomplete="username" required autofocus/>
        </div>
      </div>

      <div class="padi-form-group" style="margin-bottom: 24px;">
        <label class="padi-label" for="password">Password</label>
        <div class="padi-input-wrap">
          <span class="padi-input-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
              <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
            </svg>
          </span>
          <input class="padi-input" type="password" id="password" name="password" 
                 placeholder="Masukkan password akun" autocomplete="current-password" required/>
          <button type="button" class="pw-toggle-btn" onclick="togglePassword('password', this)" aria-label="Tampilkan password">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
              <circle cx="12" cy="12" r="3"></circle>
            </svg>
          </button>
        </div>
      </div>

      <button type="submit" class="padi-btn-primary" id="btn-submit">
        <span>Masuk sebagai Guru</span>
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <line x1="5" y1="12" x2="19" y2="12"></line>
          <polyline points="12 5 19 12 12 19"></polyline>
        </svg>
      </button>
    </form>

    <div class="card-footer-nav">
      <a href="index.php?tab=siswa">&larr; Masuk sebagai Siswa</a>
      <a href="index.php">Kembali ke Beranda Utama</a>
    </div>

  </div>
</div>

<script>
  function togglePassword(id, btn) {
    const input = document.getElementById(id);
    if (!input) return;
    const isPassword = input.type === 'password';
    input.type = isPassword ? 'text' : 'password';
    btn.innerHTML = isPassword ? 
      `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>` :
      `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>`;
  }

  document.getElementById('form-login').addEventListener('submit', function() {
    const btn = document.getElementById('btn-submit');
    btn.disabled = true;
    btn.style.opacity = '0.8';
    btn.querySelector('span').textContent = 'Memverifikasi...';
  });
</script>
</body>
</html>
