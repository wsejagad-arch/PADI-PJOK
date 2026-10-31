<?php
// index.php — Portal Utama & Autentikasi Terpadu PADI-PJOK (Anti-Slop, Clean, Responsive)
require_once __DIR__ . '/auth-boot.php';

// Database belum siap → tampilkan penanganan terstruktur
if (empty($conn)) {
    require_once __DIR__ . '/pesan-db.php';
    padi_halaman_db_mati($padi_db_error ?? 'Database tidak dapat dihubungi.');
}

// Redirect bila sesi sudah aktif
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

// Tangkap token dari query string bila dibuka via tautan / QR code
$token_url = preg_replace('/[^A-Za-z0-9]/', '', (string)($_GET['token'] ?? ($_GET['kode'] ?? '')));
$tab = ($token_url !== '' || (isset($_GET['tab']) && $_GET['tab'] === 'siswa')) ? 'siswa' : 'guru';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $peran = $_POST['peran'] ?? 'guru';
    $tab   = ($peran === 'siswa') ? 'siswa' : 'guru';

    if ($peran === 'siswa') {
        $nama_input  = trim((string)($_POST['nama'] ?? ''));
        $token_input = strtoupper(trim((string)($_POST['token'] ?? '')));

        $hasil = loginSiswaTanpaAkun($conn, $nama_input, $token_input);
        if (!empty($hasil['success'])) {
            padi_kembali('dashboard-siswa.php');
        }
        $err = $hasil['message'] ?? 'Login siswa gagal. Periksa nama dan token materi.';
    } else {
        $identitas = trim((string)($_POST['username'] ?? ''));
        $password  = (string)($_POST['password'] ?? '');

        $hasil = loginGuru($conn, $identitas, $password);
        if (!empty($hasil['success'])) {
            padi_kembali('dashboard-guru.php');
        }
        $err = $hasil['message'] ?? 'Login guru gagal. Periksa username dan password.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>PADI-PJOK — Penilaian Autentik Digital Integratif</title>
  <meta name="description" content="Platform evaluasi autentik PJOK digital: Kognitif, Psikomotor, Afektif, dan Penilaian Teman Sejawat."/>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  
  <style>
    /* ─── Layout Shell ─── */
    .auth-canvas {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px 16px;
      background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    }

    .auth-container {
      width: 100%;
      max-width: 1060px;
      display: grid;
      grid-template-columns: 1fr;
      background: #ffffff;
      border: 1px solid var(--padi-slate-200);
      border-radius: 24px;
      box-shadow: 0 10px 30px -5px rgba(15, 23, 42, 0.08), 0 4px 6px -2px rgba(15, 23, 42, 0.04);
      overflow: hidden;
    }

    /* ─── Desktop Left Brand Showcase ─── */
    .auth-showcase {
      display: none;
      background: linear-gradient(145deg, #0f172a 0%, #1e293b 100%);
      color: #ffffff;
      padding: 48px;
      flex-direction: column;
      justify-content: space-between;
      position: relative;
      overflow: hidden;
    }

    .auth-showcase::after {
      content: '';
      position: absolute;
      width: 320px;
      height: 320px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(29, 78, 216, 0.25) 0%, transparent 70%);
      bottom: -60px;
      right: -60px;
      pointer-events: none;
    }

    .showcase-brand {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 24px;
    }

    .showcase-logo-icon {
      width: 44px;
      height: 44px;
      background: rgba(255, 255, 255, 0.1);
      border: 1px solid rgba(255, 255, 255, 0.15);
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .showcase-logo-icon svg {
      width: 26px;
      height: 26px;
      color: #60a5fa;
    }

    .showcase-brand-name {
      font-size: 22px;
      font-weight: 800;
      letter-spacing: -0.02em;
      color: #ffffff;
    }

    .showcase-headline {
      font-size: 28px;
      font-weight: 800;
      line-height: 1.25;
      letter-spacing: -0.03em;
      margin-bottom: 12px;
      color: #f8fafc;
    }

    .showcase-desc {
      font-size: 14px;
      line-height: 1.6;
      color: #94a3b8;
      margin-bottom: 36px;
      max-width: 420px;
    }

    /* Assessment Pillar Cards */
    .pillar-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px;
    }

    .pillar-card {
      background: rgba(255, 255, 255, 0.04);
      border: 1px solid rgba(255, 255, 255, 0.08);
      border-radius: 14px;
      padding: 16px;
      transition: background 0.2s;
    }

    .pillar-card:hover {
      background: rgba(255, 255, 255, 0.07);
    }

    .pillar-icon-row {
      display: flex;
      align-items: center;
      gap: 8px;
      margin-bottom: 6px;
    }

    .pillar-icon-row svg {
      width: 18px;
      height: 18px;
      color: #60a5fa;
    }

    .pillar-title {
      font-size: 13px;
      font-weight: 700;
      color: #f1f5f9;
    }

    .pillar-info {
      font-size: 11.5px;
      line-height: 1.45;
      color: #94a3b8;
    }

    .showcase-footer {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding-top: 32px;
      border-top: 1px solid rgba(255, 255, 255, 0.1);
      font-size: 12px;
      color: #64748b;
    }

    .showcase-status {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      color: #34d399;
      font-weight: 600;
    }

    .status-dot {
      width: 7px;
      height: 7px;
      border-radius: 50%;
      background: #34d399;
      box-shadow: 0 0 8px rgba(52, 211, 153, 0.6);
    }

    /* ─── Right Form Panel ─── */
    .auth-main {
      padding: 40px 32px;
      display: flex;
      flex-direction: column;
      justify-content: center;
    }

    .auth-header {
      margin-bottom: 24px;
    }

    .mobile-brand {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 16px;
    }

    .mobile-brand svg {
      width: 32px;
      height: 32px;
      color: var(--padi-blue);
    }

    .mobile-brand span {
      font-size: 20px;
      font-weight: 800;
      color: var(--padi-blue);
      letter-spacing: -0.02em;
    }

    .auth-title {
      font-size: 22px;
      font-weight: 800;
      letter-spacing: -0.02em;
      color: var(--padi-slate-900);
      margin-bottom: 4px;
    }

    .auth-subtitle {
      font-size: 13px;
      color: var(--padi-slate-500);
    }

    /* Form Fields */
    .token-input {
      font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace !important;
      letter-spacing: 2px !important;
      font-weight: 700 !important;
      text-transform: uppercase !important;
      color: var(--padi-blue) !important;
    }

    .paste-token-btn {
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

    .paste-token-btn:hover {
      background: var(--padi-blue-light);
      color: var(--padi-blue);
      border-color: var(--padi-blue-border);
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
      transition: color 0.2s;
    }

    .pw-toggle-btn:hover {
      color: var(--padi-slate-700);
    }

    .form-options {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 20px;
      font-size: 12.5px;
    }

    .remember-wrap {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      color: var(--padi-slate-600);
      cursor: pointer;
    }

    .remember-wrap input {
      accent-color: var(--padi-blue);
      width: 15px;
      height: 15px;
      cursor: pointer;
    }

    .forgot-link {
      color: var(--padi-blue);
      text-decoration: none;
      font-weight: 600;
      transition: color 0.2s;
    }

    .forgot-link:hover {
      text-decoration: underline;
    }

    /* Minimal Footnote */
    .auth-footer {
      margin-top: 24px;
      text-align: center;
      font-size: 12px;
      color: var(--padi-slate-400);
    }

    /* ─── Responsive Media Queries ─── */
    @media (min-width: 1024px) {
      .auth-container {
        grid-template-columns: 1.15fr 1fr;
        min-height: 600px;
      }
      .auth-showcase {
        display: flex;
      }
      .mobile-brand {
        display: none;
      }
      .auth-main {
        padding: 48px 44px;
      }
    }

    @media (max-width: 640px) {
      .auth-canvas {
        padding: 0;
        background: #ffffff;
      }
      .auth-container {
        border-radius: 0;
        border: none;
        box-shadow: none;
      }
      .auth-main {
        padding: 24px 20px 36px;
      }
    }
  </style>
<?php require_once __DIR__ . '/ui-head.php'; padi_ui_head(); ?>
</head>
<body class="auth-page">

<div class="auth-canvas">
  <div class="auth-container">
    
    <!-- Left Showcase Panel (Desktop) -->
    <div class="auth-showcase">
      <div>
        <div class="showcase-brand">
          <div class="showcase-logo-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M18 8h1a4 4 0 0 1 0 8h-1"></path>
              <path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"></path>
              <line x1="6" y1="1" x2="6" y2="4"></line>
              <line x1="10" y1="1" x2="10" y2="4"></line>
              <line x1="14" y1="1" x2="14" y2="4"></line>
            </svg>
          </div>
          <span class="showcase-brand-name">PADI-PJOK</span>
        </div>

        <h1 class="showcase-headline">Penilaian Autentik Digital Integratif</h1>
        <p class="showcase-desc">
          Evaluasi pembelajaran PJOK terstandar berbasis Kurikulum Merdeka yang menggabungkan penguasaan konsep, analisis gerak, sikap karakter, dan penilaian formatif sejawat.
        </p>

        <!-- 4 Assessment Pillars -->
        <div class="pillar-grid">
          <div class="pillar-card">
            <div class="pillar-icon-row">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"></circle>
                <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
                <line x1="12" y1="17" x2="12.01" y2="17"></line>
              </svg>
              <span class="pillar-title">Kognitif</span>
            </div>
            <p class="pillar-info">Evaluasi pemahaman konsep gerak, strategi tim, dan aturan permainan.</p>
          </div>

          <div class="pillar-card">
            <div class="pillar-icon-row">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polygon points="23 7 16 12 23 17 23 7"></polygon>
                <rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect>
              </svg>
              <span class="pillar-title">Psikomotor</span>
            </div>
            <p class="pillar-info">Rekaman video performa gerak & rubrik capaian teknik berjenjang.</p>
          </div>

          <div class="pillar-card">
            <div class="pillar-icon-row">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
              </svg>
              <span class="pillar-title">Afektif</span>
            </div>
            <p class="pillar-info">Observasi kedisiplinan, sportivitas, dan integritas sikap siswa.</p>
          </div>

          <div class="pillar-card">
            <div class="pillar-icon-row">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                <circle cx="9" cy="7" r="4"></circle>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
              </svg>
              <span class="pillar-title">Rekan Sejawat</span>
            </div>
            <p class="pillar-info">Penilaian timbal balik antar peserta didik secara objektif.</p>
          </div>
        </div>
      </div>

      <div class="showcase-footer">
        <div class="showcase-status">
          <span class="status-dot"></span>
          <span>Sistem Penilaian Terintegrasi</span>
        </div>
        <span>PJOK Indonesia</span>
      </div>
    </div>

    <!-- Right Form Panel -->
    <div class="auth-main">
      <div class="mobile-brand">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M18 8h1a4 4 0 0 1 0 8h-1"></path>
          <path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"></path>
          <line x1="6" y1="1" x2="6" y2="4"></line>
          <line x1="10" y1="1" x2="10" y2="4"></line>
          <line x1="14" y1="1" x2="14" y2="4"></line>
        </svg>
        <span>PADI-PJOK</span>
      </div>

      <div class="auth-header">
        <h2 class="auth-title">Selamat Datang</h2>
        <p class="auth-subtitle">Pilih peran Anda untuk melanjutkan ke platform penilaian.</p>
      </div>

      <!-- Segmented Control Tabs (Guru / Siswa) -->
      <div class="padi-tabs" style="margin-bottom: 24px;">
        <button type="button" class="padi-tab-btn <?= $tab === 'guru' ? 'active' : '' ?>" id="tab-btn-guru" onclick="switchRole('guru')">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
            <circle cx="12" cy="7" r="4"></circle>
          </svg>
          Guru
        </button>
        <button type="button" class="padi-tab-btn <?= $tab === 'siswa' ? 'active' : '' ?>" id="tab-btn-siswa" onclick="switchRole('siswa')">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M22 10v6M2 10l10-5 10 5-10 5z"></path>
            <path d="M6 12v5c3 3 9 3 12 0v-5"></path>
          </svg>
          Siswa
        </button>
      </div>

      <!-- Error Alert -->
      <?php if ($err !== ''): ?>
        <div class="padi-alert padi-alert-danger" role="alert" id="auth-alert">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="12"></line>
            <line x1="12" y1="16" x2="12.01" y2="16"></line>
          </svg>
          <div style="flex: 1;"><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></div>
          <button type="button" onclick="document.getElementById('auth-alert').style.display='none'" style="background:none;border:none;cursor:pointer;color:inherit;padding:0;font-size:16px;">&times;</button>
        </div>
      <?php endif; ?>

      <!-- ─── FORM GURU ─── -->
      <form method="post" id="form-guru" style="<?= $tab === 'guru' ? '' : 'display:none;' ?>">
        <input type="hidden" name="peran" value="guru"/>
        
        <div class="padi-form-group">
          <label class="padi-label" for="username">Email atau Username</label>
          <div class="padi-input-wrap">
            <span class="padi-input-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                <polyline points="22,6 12,13 2,6"></polyline>
              </svg>
            </span>
            <input class="padi-input" type="text" id="username" name="username" placeholder="nama@sekolah.sch.id / username" autocomplete="username" required autofocus/>
          </div>
        </div>

        <div class="padi-form-group" style="margin-bottom: 12px;">
          <label class="padi-label" for="password-guru">Password</label>
          <div class="padi-input-wrap">
            <span class="padi-input-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
              </svg>
            </span>
            <input class="padi-input" type="password" id="password-guru" name="password" placeholder="Masukkan password" autocomplete="current-password" required/>
            <button type="button" class="pw-toggle-btn" onclick="togglePassword('password-guru', this)" aria-label="Tampilkan password">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                <circle cx="12" cy="12" r="3"></circle>
              </svg>
            </button>
          </div>
        </div>

        <div class="form-options">
          <label class="remember-wrap">
            <input type="checkbox" name="ingat_saya" value="1"/>
            <span>Ingat saya</span>
          </label>
          <a href="login-guru.php" class="forgot-link">Lupa password?</a>
        </div>

        <button type="submit" class="padi-btn-primary" id="btn-submit-guru">
          <span>Masuk sebagai Guru</span>
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="5" y1="12" x2="19" y2="12"></line>
            <polyline points="12 5 19 12 12 19"></polyline>
          </svg>
        </button>
      </form>

      <!-- ─── FORM SISWA ─── -->
      <form method="post" id="form-siswa" style="<?= $tab === 'siswa' ? '' : 'display:none;' ?>">
        <input type="hidden" name="peran" value="siswa"/>

        <div class="padi-form-group">
          <label class="padi-label" for="nama-siswa">Nama Lengkap atau NIS</label>
          <div class="padi-input-wrap">
            <span class="padi-input-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                <circle cx="12" cy="7" r="4"></circle>
              </svg>
            </span>
            <input class="padi-input" type="text" id="nama-siswa" name="nama" placeholder="Contoh: Muhammad Rizky / 12345" autocomplete="name" required/>
          </div>
        </div>

        <div class="padi-form-group" style="margin-bottom: 24px;">
          <label class="padi-label" for="token-materi">Token Sesi Materi</label>
          <div class="padi-input-wrap">
            <span class="padi-input-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
              </svg>
            </span>
            <input class="padi-input token-input" type="text" id="token-materi" name="token" 
                   value="<?= htmlspecialchars($token_url, ENT_QUOTES, 'UTF-8') ?>" 
                   placeholder="KODE-SESI" maxlength="16" required autocomplete="off"/>
            <button type="button" class="paste-token-btn" onclick="pasteToken()" title="Tempel dari clipboard">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path>
                <rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect>
              </svg>
              <span>Tempel</span>
            </button>
          </div>
        </div>

        <button type="submit" class="padi-btn-primary" id="btn-submit-siswa">
          <span>Masuk ke Sesi Belajar</span>
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="5" y1="12" x2="19" y2="12"></line>
            <polyline points="12 5 19 12 12 19"></polyline>
          </svg>
        </button>
      </form>

      <div class="auth-footer">
        PADI-PJOK &bull; Penilaian Autentik Digital Integratif
      </div>

    </div>
  </div>
</div>

<script>
  function switchRole(role) {
    const isGuru = (role === 'guru');
    document.getElementById('tab-btn-guru').classList.toggle('active', isGuru);
    document.getElementById('tab-btn-siswa').classList.toggle('active', !isGuru);
    
    document.getElementById('form-guru').style.display = isGuru ? 'block' : 'none';
    document.getElementById('form-siswa').style.display = !isGuru ? 'block' : 'none';

    if (isGuru) {
      document.getElementById('username').focus();
    } else {
      document.getElementById('nama-siswa').focus();
    }
  }

  function togglePassword(id, btn) {
    const input = document.getElementById(id);
    if (!input) return;
    const isPassword = input.type === 'password';
    input.type = isPassword ? 'text' : 'password';
    
    btn.innerHTML = isPassword ? 
      `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>` :
      `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>`;
  }

  async function pasteToken() {
    try {
      const text = await navigator.clipboard.readText();
      if (text) {
        const clean = text.replace(/[^A-Za-z0-9]/g, '').toUpperCase();
        const input = document.getElementById('token-materi');
        input.value = clean;
        input.focus();
      }
    } catch (e) {
      const input = document.getElementById('token-materi');
      input.focus();
    }
  }

  // Auto capitalize token input
  const tokenField = document.getElementById('token-materi');
  if (tokenField) {
    tokenField.addEventListener('input', function() {
      this.value = this.value.toUpperCase();
    });
  }

  // Submit button loading animation
  ['form-guru', 'form-siswa'].forEach(id => {
    const f = document.getElementById(id);
    if (f) {
      f.addEventListener('submit', function() {
        const btn = this.querySelector('button[type="submit"]');
        if (btn) {
          btn.disabled = true;
          btn.style.opacity = '0.8';
          btn.querySelector('span').textContent = 'Memverifikasi...';
        }
      });
    }
  });
</script>
</body>
</html>
