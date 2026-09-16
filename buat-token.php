<?php
require_once 'auth.php';
wajibLoginGuru();
require 'koneksi.php';

// Handle AJAX request to save token
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_token') {
    header('Content-Type: application/json');
    $token = $_POST['token'] ?? '';
    $kelas = $_POST['kelas'] ?? '';
    $materi = $_POST['materi'] ?? '';
    
    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'Database tidak terhubung']);
        exit;
    }
    
    $stmt = $conn->prepare("INSERT INTO sesi (token, kelas, materi) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $token, $kelas, $materi);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => $conn->error]);
    }
    $stmt->close();
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Buat Token Materi – PADI-PJOK</title>
  <meta name="description" content="Buat token materi untuk sesi penilaian PJOK. Bagikan token kepada siswa agar mereka dapat bergabung."/>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --blue:        #1A56DB;
      --blue-dark:   #1240A8;
      --blue-light:  #EFF4FF;
      --blue-mid:    #DBEAFE;
      --blue-pale:   #F0F7FF;
      --text:        #111827;
      --text-2:      #374151;
      --text-3:      #6B7280;
      --text-4:      #9CA3AF;
      --border:      #E5E7EB;
      --bg:          #F3F6FB;
      --white:       #FFFFFF;
      --green:       #16A34A;
      --nav-h:       68px;
      --radius:      16px;
      --radius-sm:   10px;
      --shadow:      0 2px 8px rgba(0,0,0,.07);
      --shadow-md:   0 4px 18px rgba(0,0,0,.10);
    }

    body {
      font-family: 'Inter', sans-serif;
      background: var(--bg);
      color: var(--text);
      min-height: 100vh;
      max-width: 480px;
      margin: 0 auto;
      padding-bottom: var(--nav-h);
    }

    /* ─── Topbar ─── */
    .topbar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 14px 20px;
      background: var(--white);
      border-bottom: 1px solid var(--border);
      box-shadow: var(--shadow);
      position: sticky;
      top: 0;
      z-index: 50;
    }

    .back-btn {
      width: 36px; height: 36px;
      border-radius: 10px;
      background: var(--blue-light);
      border: none;
      display: flex; align-items: center; justify-content: center;
      cursor: pointer;
      transition: background .2s;
    }
    .back-btn:hover { background: var(--blue-mid); }
    .back-btn svg { width: 20px; height: 20px; color: var(--blue); }

    .topbar-title {
      font-size: 15px;
      font-weight: 700;
      color: var(--text);
    }

    .topbar-spacer { width: 36px; }

    /* ─── Hero Section ─── */
    .hero {
      background: linear-gradient(135deg, #F0F7FF 0%, #E8F1FF 60%, #EFF4FF 100%);
      padding: 20px 20px 0;
      display: flex;
      align-items: flex-end;
      gap: 0;
      min-height: 140px;
      position: relative;
      overflow: hidden;
    }

    .hero::before {
      content: '';
      position: absolute;
      width: 200px; height: 200px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(26,86,219,.07) 0%, transparent 70%);
      top: -60px; right: -40px;
    }

    .hero-text {
      flex: 1;
      padding-bottom: 20px;
      z-index: 1;
    }
    .hero-text h1 {
      font-size: 22px;
      font-weight: 800;
      color: var(--blue);
      margin-bottom: 6px;
      line-height: 1.2;
    }
    .hero-text p {
      font-size: 12px;
      color: var(--text-3);
      line-height: 1.6;
      max-width: 200px;
    }

    .hero-img {
      width: 120px;
      height: 130px;
      object-fit: contain;
      object-position: bottom;
      flex-shrink: 0;
      z-index: 1;
    }

    /* ─── Content ─── */
    .content { padding: 20px 16px; display: flex; flex-direction: column; gap: 16px; }

    /* ─── Form Card ─── */
    .form-card {
      background: var(--white);
      border-radius: var(--radius);
      box-shadow: var(--shadow-md);
      padding: 20px;
      display: flex;
      flex-direction: column;
      gap: 20px;
      animation: fadeUp .4s .1s both;
    }

    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(16px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    /* ─── Form field ─── */
    .form-field { display: flex; align-items: flex-start; gap: 14px; }

    .field-icon {
      width: 42px; height: 42px;
      background: var(--blue-light);
      border-radius: 12px;
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
      margin-top: 2px;
    }
    .field-icon svg { width: 22px; height: 22px; color: var(--blue); }

    .field-body { flex: 1; display: flex; flex-direction: column; gap: 6px; }

    .field-label {
      font-size: 12px;
      font-weight: 600;
      color: var(--text-3);
    }

    .select-wrap { position: relative; }

    .field-select {
      width: 100%;
      appearance: none;
      -webkit-appearance: none;
      background: var(--white);
      border: 1.5px solid var(--border);
      border-radius: var(--radius-sm);
      padding: 12px 40px 12px 14px;
      font-family: inherit;
      font-size: 14px;
      font-weight: 600;
      color: var(--text);
      cursor: pointer;
      outline: none;
      transition: border-color .2s, box-shadow .2s;
    }
    .field-select:focus {
      border-color: var(--blue);
      box-shadow: 0 0 0 3px rgba(26,86,219,.12);
    }

    .select-arrow {
      position: absolute;
      right: 12px; top: 50%;
      transform: translateY(-50%);
      pointer-events: none;
      color: var(--text-3);
    }
    .select-arrow svg { width: 18px; height: 18px; }

    /* ─── Divider ─── */
    .field-divider {
      height: 1px;
      background: var(--border);
      margin: 0 -20px;
    }

    /* ─── Buat Token Button ─── */
    .btn-buat {
      width: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      padding: 16px;
      background: var(--blue);
      color: var(--white);
      border: none;
      border-radius: var(--radius-sm);
      font-family: inherit;
      font-size: 16px;
      font-weight: 700;
      cursor: pointer;
      box-shadow: 0 6px 20px rgba(26,86,219,.35);
      transition: all .25s cubic-bezier(.22,1,.36,1);
      letter-spacing: .2px;
      animation: fadeUp .4s .2s both;
    }
    .btn-buat svg { width: 22px; height: 22px; }
    .btn-buat:hover {
      background: var(--blue-dark);
      box-shadow: 0 8px 28px rgba(26,86,219,.45);
      transform: translateY(-2px);
    }
    .btn-buat:active { transform: translateY(0); }
    .btn-buat:disabled {
      opacity: .7; cursor: not-allowed; transform: none;
    }

    /* ─── Token Result Card ─── */
    .token-result {
      background: var(--blue-pale);
      border: 1.5px solid var(--blue-mid);
      border-radius: var(--radius);
      padding: 20px;
      display: none;
      flex-direction: column;
      gap: 16px;
      animation: popIn .5s cubic-bezier(.22,1,.36,1) both;
      position: relative;
      overflow: hidden;
    }

    @keyframes popIn {
      from { opacity: 0; transform: scale(.92); }
      to   { opacity: 1; transform: scale(1); }
    }

    .token-result.visible { display: flex; }

    /* Confetti dots */
    .confetti { position: absolute; top: 0; left: 0; right: 0; bottom: 0; pointer-events: none; overflow: hidden; }
    .dot {
      position: absolute;
      border-radius: 50%;
      animation: float 3s ease-in-out infinite;
    }
    @keyframes float {
      0%,100% { transform: translateY(0) rotate(0deg); opacity: .7; }
      50% { transform: translateY(-8px) rotate(180deg); opacity: 1; }
    }

    .token-success-label {
      font-size: 13px;
      font-weight: 700;
      color: var(--blue);
      text-align: center;
      margin-bottom: -4px;
    }

    .token-main {
      display: flex;
      align-items: center;
      gap: 16px;
    }

    .token-ticket-icon {
      width: 64px; height: 64px;
      background: var(--blue-light);
      border-radius: 14px;
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
    }
    .token-ticket-icon svg { width: 36px; height: 36px; color: var(--blue); }

    .token-code-box {
      flex: 1;
      border: 2.5px dashed var(--blue);
      border-radius: 12px;
      padding: 12px 16px;
      background: var(--white);
      text-align: center;
    }
    .token-code {
      font-size: 26px;
      font-weight: 800;
      color: var(--text);
      letter-spacing: 2px;
      font-variant-numeric: tabular-nums;
      line-height: 1;
    }

    /* ─── Action buttons row ─── */
    .token-actions {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 10px;
    }

    .btn-action {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      padding: 12px;
      border-radius: var(--radius-sm);
      font-family: inherit;
      font-size: 13px;
      font-weight: 700;
      cursor: pointer;
      transition: all .2s;
    }
    .btn-action svg { width: 18px; height: 18px; }

    .btn-salin {
      background: var(--white);
      border: 1.5px solid var(--border);
      color: var(--text-2);
    }
    .btn-salin:hover { border-color: var(--blue); color: var(--blue); background: var(--blue-light); }

    .btn-bagikan {
      background: var(--white);
      border: 1.5px solid var(--border);
      color: var(--text-2);
    }
    .btn-bagikan:hover { border-color: var(--blue); color: var(--blue); background: var(--blue-light); }

    /* ─── Info note ─── */
    .token-info {
      display: flex;
      align-items: flex-start;
      gap: 10px;
      background: var(--white);
      border-radius: var(--radius-sm);
      padding: 12px 14px;
      border: 1px solid var(--blue-mid);
    }
    .token-info svg { width: 18px; height: 18px; color: var(--blue); flex-shrink: 0; margin-top: 1px; }
    .token-info p { font-size: 11.5px; color: var(--text-3); line-height: 1.6; }
    .token-info p strong { color: var(--blue); font-weight: 700; }

    /* ─── Bottom Navigation ─── */
    .bottom-nav {
      position: fixed;
      bottom: 0; left: 50%;
      transform: translateX(-50%);
      width: 100%;
      max-width: 480px;
      background: var(--white);
      border-top: 1px solid var(--border);
      display: flex;
      box-shadow: 0 -4px 16px rgba(0,0,0,.08);
      z-index: 50;
      height: var(--nav-h);
    }

    .nav-item {
      flex: 1;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 4px;
      cursor: pointer;
      border: none;
      background: transparent;
      font-family: inherit;
      padding: 8px 4px;
      transition: all .2s;
      text-decoration: none;
      color: var(--text-4);
      position: relative;
    }
    .nav-item svg { width: 22px; height: 22px; }
    .nav-item span { font-size: 10px; font-weight: 600; }
    .nav-item.active { color: var(--blue); }
    .nav-item:hover { color: var(--blue); }

    .nav-indicator {
      position: absolute;
      top: 0; left: 50%;
      transform: translateX(-50%) scaleX(0);
      width: 32px; height: 3px;
      background: var(--blue);
      border-radius: 0 0 4px 4px;
      transition: transform .25s;
    }
    .nav-item.active .nav-indicator { transform: translateX(-50%) scaleX(1); }

    /* ─── Toast ─── */
    .toast {
      position: fixed; bottom: 84px; left: 50%;
      transform: translateX(-50%) translateY(80px);
      background: #111827; color: #fff;
      padding: 11px 20px; border-radius: 10px;
      font-size: 13px; font-weight: 500;
      box-shadow: 0 8px 30px rgba(0,0,0,.25);
      z-index: 999;
      transition: transform .35s cubic-bezier(.22,1,.36,1);
      white-space: nowrap;
    }
    .toast.show { transform: translateX(-50%) translateY(0); }

    /* ─── Loading spinner ─── */
    @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
    .spin { animation: spin .8s linear infinite; }
  </style>
</head>
<body>

<!-- ─── Topbar ─── -->
<header class="topbar">
  <button class="back-btn" id="back-btn" aria-label="Kembali" onclick="history.back()">
    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
      <path d="M19 12H5M12 5l-7 7 7 7" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
  </button>
  <span class="topbar-title">Buat Token Materi</span>
  <div class="topbar-spacer"></div>
</header>

<!-- ─── Hero ─── -->
<div class="hero">
  <div class="hero-text">
    <h1>Buat Token Materi</h1>
    <p>Buat token materi untuk sesi penilaian. Bagikan token kepada siswa agar mereka dapat bergabung.</p>
  </div>
  <img class="hero-img" src="guru_wanita_token.png" alt="Guru PJOK dengan clipboard"/>
</div>

<!-- ─── Content ─── -->
<div class="content">

  <!-- Form Card -->
  <div class="form-card" role="form" aria-label="Form buat token materi">

    <!-- Pilih Kelas -->
    <div class="form-field">
      <div class="field-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none">
          <path d="M22 10v6M2 10l10-5 10 5-10 5z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
          <path d="M6 12v5c0 1.7 2.7 3 6 3s6-1.3 6-3v-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
        </svg>
      </div>
      <div class="field-body">
        <label class="field-label" for="select-kelas">Pilih Kelas</label>
        <div class="select-wrap">
          <select class="field-select" id="select-kelas" name="kelas">
            <option value="X-1" selected>X-1</option>
            <option value="X-2">X-2</option>
            <option value="X-3">X-3</option>
            <option value="XI-1">XI-1</option>
            <option value="XI-2">XI-2</option>
            <option value="XII-1">XII-1</option>
            <option value="XII-2">XII-2</option>
          </select>
          <span class="select-arrow" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none">
              <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </span>
        </div>
      </div>
    </div>

    <div class="field-divider"></div>

    <!-- Materi Pembelajaran -->
    <div class="form-field">
      <div class="field-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none">
          <path d="M4 19.5A2.5 2.5 0 016.5 17H20" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
          <path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
          <path d="M9 7h6M9 11h6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
        </svg>
      </div>
      <div class="field-body">
        <label class="field-label" for="select-materi">Materi Pembelajaran</label>
        <div class="select-wrap">
          <select class="field-select" id="select-materi" name="materi">
            <option value="passing-bawah-bola-voli" selected>Passing Bawah Bola Voli</option>
            <option value="passing-atas-bola-voli">Passing Atas Bola Voli</option>
            <option value="smash-bola-voli">Smash Bola Voli</option>
            <option value="servis-bola-voli">Servis Bola Voli</option>
            <option value="dribbling-bola-basket">Dribbling Bola Basket</option>
            <option value="passing-bola-basket">Passing Bola Basket</option>
            <option value="lay-up-bola-basket">Lay Up Bola Basket</option>
            <option value="tendangan-sepak-bola">Tendangan Sepak Bola</option>
            <option value="lari-sprint">Lari Sprint</option>
            <option value="lompat-jauh">Lompat Jauh</option>
          </select>
          <span class="select-arrow" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none">
              <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </span>
        </div>
      </div>
    </div>

    <div class="field-divider"></div>

    <!-- Jenis Penilaian -->
    <div class="form-field">
      <div class="field-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none">
          <rect x="8" y="2" width="8" height="4" rx="1" stroke="currentColor" stroke-width="1.8"/>
          <path d="M8 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V4a2 2 0 00-2-2h-2" stroke="currentColor" stroke-width="1.8"/>
          <path d="M9 12l2 2 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </div>
      <div class="field-body">
        <label class="field-label" for="select-penilaian">Jenis Penilaian</label>
        <div class="select-wrap">
          <select class="field-select" id="select-penilaian" name="penilaian">
            <option value="kognitif-afektif-psikomotor" selected>Kognitif, Afektif, Psikomotor</option>
            <option value="kognitif">Kognitif</option>
            <option value="afektif">Afektif</option>
            <option value="psikomotor">Psikomotor</option>
            <option value="kognitif-psikomotor">Kognitif &amp; Psikomotor</option>
            <option value="afektif-psikomotor">Afektif &amp; Psikomotor</option>
          </select>
          <span class="select-arrow" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none">
              <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </span>
        </div>
      </div>
    </div>

    <div class="field-divider"></div>

    <!-- Token Kustom (Opsional) -->
    <div class="form-field">
      <div class="field-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none">
          <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
          <path d="M9 12l2 2 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </div>
      <div class="field-body">
        <label class="field-label" for="input-custom-token">Token (Opsional)</label>
        <div class="select-wrap">
          <input type="text" class="field-select" id="input-custom-token" name="custom-token" placeholder="Biarkan kosong untuk token acak" style="text-transform: uppercase;">
        </div>
      </div>
    </div>

    <div class="field-divider"></div>

    <!-- Durasi Token -->
    <div class="form-field">
      <div class="field-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none">
          <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.8"/>
          <path d="M12 6v6l4 2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </div>
      <div class="field-body">
        <label class="field-label" for="select-durasi">Durasi Token</label>
        <div class="select-wrap">
          <select class="field-select" id="select-durasi" name="durasi">
            <option value="30">30 menit</option>
            <option value="45">45 menit</option>
            <option value="60">60 menit</option>
            <option value="90" selected>90 menit</option>
            <option value="120">120 menit</option>
            <option value="180">180 menit</option>
            <option value="unlimited">Tanpa batas</option>
          </select>
          <span class="select-arrow" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none">
              <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </span>
        </div>
      </div>
    </div>

  </div><!-- /form-card -->

  <!-- Buat Token Button -->
  <button class="btn-buat" id="btn-buat" onclick="buatToken()">
    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
      <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
      <path d="M12 8v8M8 12h8" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
    </svg>
    Buat Token
  </button>

  <!-- ─── Token Result ─── -->
  <div class="token-result" id="token-result" role="region" aria-label="Token berhasil dibuat" aria-live="polite">

    <!-- Confetti decoration -->
    <div class="confetti" aria-hidden="true">
      <div class="dot" style="width:8px;height:8px;background:#FBBF24;top:12%;left:10%;animation-delay:0s;"></div>
      <div class="dot" style="width:6px;height:6px;background:#34D399;top:20%;left:25%;animation-delay:.4s;"></div>
      <div class="dot" style="width:10px;height:10px;background:#60A5FA;top:8%;right:20%;animation-delay:.2s;border-radius:2px;"></div>
      <div class="dot" style="width:7px;height:7px;background:#F472B6;top:30%;right:10%;animation-delay:.6s;"></div>
      <div class="dot" style="width:5px;height:5px;background:#FBBF24;top:60%;left:8%;animation-delay:.3s;"></div>
      <div class="dot" style="width:9px;height:9px;background:#A78BFA;top:70%;right:12%;animation-delay:.5s;border-radius:2px;"></div>
    </div>

    <p class="token-success-label">✨ Token materi berhasil dibuat!</p>

    <div class="token-main">
      <div class="token-ticket-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none">
          <path d="M2 9a2 2 0 012-2h16a2 2 0 012 2v1a2 2 0 010 4v1a2 2 0 01-2 2H4a2 2 0 01-2-2v-1a2 2 0 010-4V9z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
          <path d="M9 7v10M15 7v10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-dasharray="2 2"/>
        </svg>
      </div>
      <div class="token-code-box">
        <p class="token-code" id="token-display">VOLI-X1-482</p>
      </div>
    </div>

    <div class="token-actions">
      <button class="btn-action btn-salin" id="btn-salin" onclick="salinToken()">
        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <rect x="9" y="9" width="13" height="13" rx="2" stroke="currentColor" stroke-width="1.8"/>
          <path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
        </svg>
        Salin Token
      </button>
      <button class="btn-action btn-bagikan" id="btn-bagikan" onclick="bagikanToken()">
        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <circle cx="18" cy="5" r="3" stroke="currentColor" stroke-width="1.8"/>
          <circle cx="6" cy="12" r="3" stroke="currentColor" stroke-width="1.8"/>
          <circle cx="18" cy="19" r="3" stroke="currentColor" stroke-width="1.8"/>
          <path d="M8.59 13.51l6.83 3.98M15.41 6.51L8.59 10.49" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
        </svg>
        Bagikan
      </button>
    </div>

    <div class="token-info">
      <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.8"/>
        <path d="M12 16v-4M12 8h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      </svg>
      <p>Siswa bergabung menggunakan <strong>Nama Lengkap + Token Materi</strong>. Pastikan token dibagikan kepada siswa yang tepat.</p>
    </div>

  </div>

</div><!-- /content -->

<!-- ─── Bottom Navigation ─── -->
<nav class="bottom-nav" role="navigation" aria-label="Navigasi utama">
  <a href="dashboard-guru.php" class="nav-item" id="nav-beranda" aria-label="Beranda">
    <div class="nav-indicator"></div>
    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
      <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
      <path d="M9 22V12h6v10" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
    </svg>
    <span>Beranda</span>
  </a>
  <a href="#" class="nav-item" id="nav-siswa" aria-label="Siswa" onclick="showToast('Halaman Siswa segera hadir'); return false;">
    <div class="nav-indicator"></div>
    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
      <circle cx="9" cy="7" r="3" stroke="currentColor" stroke-width="2"/>
      <circle cx="16" cy="7" r="3" stroke="currentColor" stroke-width="2"/>
      <path d="M2 21c0-3.5 3-6 7-6h4c4 0 7 2.5 7 6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
    </svg>
    <span>Siswa</span>
  </a>
  <a href="#" class="nav-item active" id="nav-token" aria-label="Token Materi" aria-current="page">
    <div class="nav-indicator"></div>
    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
      <path d="M2 9a2 2 0 012-2h16a2 2 0 012 2v1a2 2 0 010 4v1a2 2 0 01-2 2H4a2 2 0 01-2-2v-1a2 2 0 010-4V9z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
    </svg>
    <span>Token Materi</span>
  </a>
  <a href="#" class="nav-item" id="nav-riwayat" aria-label="Riwayat" onclick="showToast('Halaman Riwayat segera hadir'); return false;">
    <div class="nav-indicator"></div>
    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
      <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
      <path d="M12 6v6l4 2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
    <span>Riwayat</span>
  </a>
  <a href="#" class="nav-item" id="nav-profil" aria-label="Profil" onclick="showToast('Halaman Profil segera hadir'); return false;">
    <div class="nav-indicator"></div>
    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
      <circle cx="12" cy="8" r="4" stroke="currentColor" stroke-width="2"/>
      <path d="M4 20c0-4 3.6-7 8-7s8 3 8 7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
    </svg>
    <span>Profil</span>
  </a>
</nav>

<!-- Toast -->
<div class="toast" id="toast" role="alert" aria-live="polite"></div>

<script>
  /* ── Token generation ── */
  const materiMap = {
    'passing-bawah-bola-voli': 'VOLI',
    'passing-atas-bola-voli':  'VOLIA',
    'smash-bola-voli':         'SMASH',
    'servis-bola-voli':        'SERV',
    'dribbling-bola-basket':   'DRIB',
    'passing-bola-basket':     'BASK',
    'lay-up-bola-basket':      'LAYU',
    'tendangan-sepak-bola':    'SEPK',
    'lari-sprint':             'SPRT',
    'lompat-jauh':             'LMPJ',
  };

  let currentToken = '';

  function generateToken() {
    const customToken = document.getElementById('input-custom-token').value.trim();
    if (customToken !== '') {
      return customToken.toUpperCase();
    }
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    let randomPart = '';
    for(let i=0; i<4; i++){
      randomPart += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    return randomPart;
  }

  function buatToken() {
    const btn = document.getElementById('btn-buat');
    btn.disabled = true;
    btn.innerHTML = `
      <svg class="spin" viewBox="0 0 24 24" fill="none" width="22" height="22">
        <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"
          stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      </svg>
      Membuat Token...`;

    currentToken = generateToken();
    const kelas = document.getElementById('select-kelas').value;
    const materi = document.getElementById('select-materi').value;

    fetch('buat-token.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        action: 'save_token',
        token: currentToken,
        kelas: kelas,
        materi: materi
      })
    })
    .then(res => res.json())
    .then(data => {
      if(data.success) {
        document.getElementById('token-display').textContent = currentToken;

        const resultEl = document.getElementById('token-result');
        resultEl.classList.add('visible');
        resultEl.style.animation = 'none';
        requestAnimationFrame(() => {
          resultEl.style.animation = 'popIn .5s cubic-bezier(.22,1,.36,1) both';
        });

        // Scroll into view
        setTimeout(() => resultEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' }), 100);

        btn.disabled = false;
        btn.innerHTML = `
          <svg viewBox="0 0 24 24" fill="none" width="22" height="22">
            <path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          Buat Token Baru`;

        showToast('✅ Token ' + currentToken + ' berhasil dibuat!');
      } else {
        showToast('❌ Gagal: ' + data.message);
        btn.disabled = false;
        btn.innerHTML = 'Coba Lagi';
      }
    })
    .catch(err => {
      showToast('❌ Terjadi kesalahan jaringan');
      btn.disabled = false;
      btn.innerHTML = 'Coba Lagi';
    });
  }

  function salinToken() {
    if (!currentToken) { showToast('Buat token terlebih dahulu'); return; }
    if (navigator.clipboard) {
      navigator.clipboard.writeText(currentToken)
        .then(() => showToast('✅ Token ' + currentToken + ' disalin!'))
        .catch(() => fallbackCopy(currentToken));
    } else {
      fallbackCopy(currentToken);
    }
  }

  function fallbackCopy(text) {
    const ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.select();
    document.execCommand('copy');
    document.body.removeChild(ta);
    showToast('✅ Token ' + text + ' disalin!');
  }

  function bagikanToken() {
    if (!currentToken) { showToast('Buat token terlebih dahulu'); return; }
    const msg = `Token Materi PADI-PJOK: ${currentToken}\nGunakan Nama Lengkap + Token ini untuk bergabung.`;
    if (navigator.share) {
      navigator.share({ title: 'Token PADI-PJOK', text: msg })
        .catch(() => {});
    } else {
      showToast('📤 Bagikan: ' + currentToken);
    }
  }

  /* ── Toast ── */
  function showToast(msg) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.classList.add('show');
    clearTimeout(t._timer);
    t._timer = setTimeout(() => t.classList.remove('show'), 3000);
  }
</script>

</body>
</html>
