<?php
// input-token.php — Masukkan Token Sesi Materi (Anti-Slop, Clean, Responsive)
session_start();
require_once __DIR__ . '/koneksi.php';
require_once __DIR__ . '/auth.php';

// Jika belum ada identitas siswa di sesi, arahkan ke login
if (empty($_SESSION['master_id'])) {
    $kode_tautan = preg_replace('/[^A-Za-z0-9]/', '', (string)($_GET['kode'] ?? ''));
    if ($kode_tautan !== '') {
        $_SESSION['kode_sesi_awal'] = $kode_tautan;
        header('Location: login-siswa.php');
        exit;
    }
    header('Location: index.php');
    exit;
}

// Jika sudah bergabung dalam sesi (punya siswa_id), langsung ke dashboard
if (!empty($_SESSION['siswa_id'])) {
    header('Location: dashboard-siswa.php');
    exit;
}

$err = '';
$kode_awal = preg_replace('/[^A-Za-z0-9]/', '', (string)($_GET['kode'] ?? ($_SESSION['kode_sesi_awal'] ?? '')));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'join') {
        if (!padi_csrf_valid()) {
            $err = 'Sesi formulir kedaluwarsa. Silakan muat ulang halaman.';
        } else {
            $token_input = strtoupper(trim((string)($_POST['token'] ?? '')));
            $hasil = joinSesiSiswa($conn, $token_input);
            if (!empty($hasil['success'])) {
                unset($_SESSION['kode_sesi_awal']);
                header('Location: dashboard-siswa.php');
                exit;
            }
            $err = $hasil['message'] ?? 'Token sesi tidak ditemukan atau sudah tidak aktif.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Token Sesi Pembelajaran — PADI-PJOK</title>
  <meta name="description" content="Masukkan token materi PJOK dari guru untuk bergabung ke sesi penilaian."/>
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
      text-align: center;
    }

    .icon-badge {
      width: 56px;
      height: 56px;
      border-radius: 16px;
      background: var(--padi-blue-light);
      color: var(--padi-blue);
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 16px;
    }

    .icon-badge svg {
      width: 28px;
      height: 28px;
    }

    .card-title {
      font-size: 20px;
      font-weight: 800;
      color: var(--padi-slate-900);
      margin-bottom: 6px;
      letter-spacing: -0.02em;
    }

    .card-subtitle {
      font-size: 13px;
      color: var(--padi-slate-500);
      margin-bottom: 20px;
      line-height: 1.5;
    }

    .student-chip {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 6px 14px;
      background: var(--padi-slate-100);
      border: 1px solid var(--padi-slate-200);
      border-radius: 9999px;
      font-size: 12px;
      color: var(--padi-slate-700);
      margin-bottom: 24px;
    }

    .student-chip svg {
      width: 14px;
      height: 14px;
      color: var(--padi-blue);
    }

    .token-input-field {
      font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace !important;
      font-size: 18px !important;
      letter-spacing: 3px !important;
      text-align: center !important;
      text-transform: uppercase !important;
      font-weight: 800 !important;
      color: var(--padi-blue) !important;
      height: 50px !important;
      padding: 10px 60px 10px 20px !important;
    }

    .paste-btn {
      position: absolute;
      right: 8px;
      top: 50%;
      transform: translateY(-50%);
      background: var(--padi-slate-100);
      border: 1px solid var(--padi-slate-200);
      color: var(--padi-slate-600);
      padding: 5px 10px;
      border-radius: 6px;
      font-size: 11px;
      font-weight: 700;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 4px;
      transition: all 0.2s;
    }

    .paste-btn:hover {
      background: var(--padi-blue-light);
      color: var(--padi-blue);
      border-color: var(--padi-blue-border);
    }

    .card-footer-nav {
      margin-top: 24px;
      padding-top: 18px;
      border-top: 1px solid var(--padi-slate-100);
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 12.5px;
    }

    .card-footer-nav a {
      color: var(--padi-slate-500);
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
    
    <div class="icon-badge">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
      </svg>
    </div>

    <h1 class="card-title">Token Sesi Materi</h1>
    <p class="card-subtitle">Masukkan token materi dari guru PJOK untuk masuk ke ruang penilaian kelas.</p>

    <div class="student-chip">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
        <circle cx="12" cy="7" r="4"></circle>
      </svg>
      <span>Masuk sebagai: <strong><?= htmlspecialchars($_SESSION['siswa_nama'] ?? 'Peserta Didik', ENT_QUOTES, 'UTF-8') ?></strong></span>
    </div>

    <?php if ($err !== ''): ?>
      <div class="padi-alert padi-alert-danger" role="alert">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10"></circle>
          <line x1="12" y1="8" x2="12" y2="12"></line>
          <line x1="12" y1="16" x2="12.01" y2="16"></line>
        </svg>
        <span style="flex: 1; text-align: left;"><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></span>
      </div>
    <?php endif; ?>

    <form method="post" id="form-token">
      <?= padi_csrf_field() ?>
      <input type="hidden" name="action" value="join"/>

      <div class="padi-form-group" style="margin-bottom: 20px;">
        <div class="padi-input-wrap">
          <input type="text" class="padi-input token-input-field" id="token" name="token" 
                 placeholder="MASUKKAN TOKEN" value="<?= htmlspecialchars($kode_awal, ENT_QUOTES, 'UTF-8') ?>" 
                 required autofocus autocomplete="off" maxlength="16"/>
          <button type="button" class="paste-btn" onclick="pasteToken()" title="Tempel dari clipboard">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path>
              <rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect>
            </svg>
            <span>Tempel</span>
          </button>
        </div>
      </div>

      <button type="submit" class="padi-btn-primary" id="btn-submit">
        <span>Bergabung ke Sesi Kelas</span>
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <line x1="5" y1="12" x2="19" y2="12"></line>
          <polyline points="12 5 19 12 12 19"></polyline>
        </svg>
      </button>
    </form>

    <div class="card-footer-nav">
      <a href="logout.php">Ganti Siswa / Keluar</a>
      <a href="index.php">Ke Beranda</a>
    </div>

  </div>
</div>

<script>
  const tokenInput = document.getElementById('token');
  if (tokenInput) {
    tokenInput.addEventListener('input', function() {
      this.value = this.value.toUpperCase();
    });
  }

  async function pasteToken() {
    try {
      const text = await navigator.clipboard.readText();
      if (text) {
        tokenInput.value = text.replace(/[^A-Za-z0-9]/g, '').toUpperCase();
        tokenInput.focus();
      }
    } catch (e) {
      tokenInput.focus();
    }
  }

  document.getElementById('form-token').addEventListener('submit', function() {
    const btn = document.getElementById('btn-submit');
    btn.disabled = true;
    btn.style.opacity = '0.8';
    btn.querySelector('span').textContent = 'Menghubungkan Sesi...';
  });
</script>
</body>
</html>
