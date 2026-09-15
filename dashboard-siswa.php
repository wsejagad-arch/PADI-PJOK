<?php
require_once 'auth.php';
wajibLoginSiswa();
$siswa_nama = $_SESSION['siswa_nama'] ?? 'Siswa';
$materi_id = $_SESSION['materi'] ?? '';
$materi_title = ucwords(str_replace('-', ' ', $materi_id));

// To get the actual scores, we should query them from DB.
// For now, let's keep them zero or fetch if they exist.
require 'koneksi.php';
$kognitif = 0; $psikomotor = 0; $afektif = 0;
if ($conn) {
    // Fetch kognitif
    $q = $conn->query("SELECT nilai_total FROM penilaian_kognitif WHERE siswa_id = " . intval($_SESSION['siswa_id']));
    if ($q && $r = $q->fetch_assoc()) $kognitif = $r['nilai_total'];
    // Fetch psikomotor
    $q = $conn->query("SELECT nilai_rubrik FROM penilaian_psikomotor WHERE siswa_id = " . intval($_SESSION['siswa_id']));
    if ($q && $r = $q->fetch_assoc()) $psikomotor = $r['nilai_rubrik'];
    // Fetch afektif
    $q = $conn->query("SELECT nilai_rubrik FROM penilaian_afektif WHERE siswa_id = " . intval($_SESSION['siswa_id']));
    if ($q && $r = $q->fetch_assoc()) $afektif = $r['nilai_rubrik'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Dashboard Siswa – PADI-PJOK</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    :root{--blue:#1A56DB;--blue-dark:#1240A8;--blue-light:#EFF4FF;--blue-mid:#DBEAFE;--green:#16A34A;--green-light:#DCFCE7;--orange:#EA580C;--orange-light:#FFF7ED;--yellow:#D97706;--yellow-light:#FFFBEB;--purple:#7C3AED;--text:#111827;--text-2:#374151;--text-3:#6B7280;--text-4:#9CA3AF;--border:#E5E7EB;--bg:#F3F6FB;--white:#FFFFFF;--nav-h:68px;--radius:14px;--radius-sm:10px;--shadow:0 2px 8px rgba(0,0,0,.07);--shadow-md:0 4px 18px rgba(0,0,0,.10)}
    body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);max-width:480px;margin:0 auto;padding-bottom:var(--nav-h)}

    /* Topbar */
    .topbar{position:sticky;top:0;z-index:50;display:flex;align-items:center;justify-content:space-between;padding:14px 20px;background:var(--white);border-bottom:1px solid var(--border);box-shadow:var(--shadow)}
    .topbar-logo{display:flex;align-items:center;gap:8px}
    .topbar-logo svg{width:26px;height:26px}
    .topbar-logo span{font-size:16px;font-weight:800;color:var(--blue)}
    .notif-btn{position:relative;width:36px;height:36px;background:var(--blue-light);border:none;border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer}
    .notif-btn svg{width:20px;height:20px;color:var(--blue)}
    .notif-dot{position:absolute;top:6px;right:6px;width:8px;height:8px;background:#EF4444;border-radius:50%;border:2px solid var(--white)}

    /* Hero */
    .hero{background:linear-gradient(135deg,#EFF6FF 0%,#DBEAFE 60%,#E0F2FE 100%);padding:20px 20px 0;display:flex;align-items:flex-end;min-height:155px;position:relative;overflow:hidden}
    .hero::before{content:'';position:absolute;inset:0;background:radial-gradient(ellipse at 80%,rgba(26,86,219,.07),transparent 60%)}
    .hero-text{flex:1;padding-bottom:20px;z-index:1}
    .hero-text h1{font-size:22px;font-weight:800;color:var(--text);margin-bottom:4px}
    .hero-sub{font-size:13px;color:var(--text-3);font-weight:500}
    .hero-img{width:130px;height:150px;object-fit:contain;object-position:bottom;flex-shrink:0;z-index:1}

    .content{padding:16px;display:flex;flex-direction:column;gap:14px}

    /* Materi Aktif */
    .materi-card{background:var(--white);border-radius:var(--radius);box-shadow:var(--shadow-md);padding:16px;cursor:pointer;transition:box-shadow .2s}
    .materi-card:hover{box-shadow:var(--shadow-md)}
    .materi-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}
    .materi-badge{display:flex;align-items:center;gap:8px}
    .materi-icon{width:36px;height:36px;background:var(--blue-light);border-radius:10px;display:flex;align-items:center;justify-content:center}
    .materi-icon svg{width:20px;height:20px;color:var(--blue)}
    .materi-badge-text{font-size:14px;font-weight:700;color:var(--blue)}
    .materi-arrow{color:var(--text-4)}
    .materi-arrow svg{width:18px;height:18px}
    .materi-title{font-size:16px;font-weight:800;color:var(--text);margin-bottom:8px}
    .materi-date{display:flex;align-items:center;gap:6px;font-size:12px;color:var(--text-3);margin-bottom:12px}
    .materi-date svg{width:14px;height:14px}
    .materi-status{display:flex;align-items:center;justify-content:space-between;padding:10px 12px;background:var(--bg);border-radius:var(--radius-sm)}
    .status-label{font-size:12px;color:var(--text-3);font-weight:600}
    .status-connected{display:flex;align-items:center;gap:6px;background:var(--green-light);color:var(--green);font-size:12px;font-weight:700;padding:6px 12px;border-radius:20px}
    .status-connected::before{content:'';width:7px;height:7px;border-radius:50%;background:var(--green);flex-shrink:0}
    .status-connected svg{width:14px;height:14px}

    /* Section header */
    .section-hdr{display:flex;align-items:center;justify-content:space-between}
    .section-hdr h2{font-size:16px;font-weight:800;color:var(--text)}
    .lihat-semua{display:flex;align-items:center;gap:3px;font-size:13px;font-weight:600;color:var(--blue);text-decoration:none}
    .lihat-semua svg{width:14px;height:14px}

    /* Nilai */
    .nilai-row{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}
    .nilai-card{background:var(--white);border-radius:var(--radius-sm);box-shadow:var(--shadow);padding:12px;display:flex;flex-direction:column;align-items:center;gap:5px;text-align:center}
    .nilai-icon{width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center}
    .nilai-icon svg{width:20px;height:20px}
    .nilai-icon.blue{background:var(--blue-light);color:var(--blue)}
    .nilai-icon.green{background:var(--green-light);color:var(--green)}
    .nilai-icon.orange{background:var(--orange-light);color:var(--orange)}
    .nilai-label{font-size:10px;font-weight:600;color:var(--text-3)}
    .nilai-val{font-size:28px;font-weight:800;color:var(--text);line-height:1}
    .nilai-grade{font-size:10px;font-weight:700}
    .nilai-grade.blue{color:var(--blue)}
    .nilai-grade.green{color:var(--green)}
    .nilai-grade.orange{color:var(--orange)}

    /* Feedback */
    .feedback-card{background:var(--white);border-radius:var(--radius);box-shadow:var(--shadow-md);padding:16px;cursor:pointer;transition:all .2s;text-decoration:none;display:block}
    .feedback-card:hover{box-shadow:0 6px 24px rgba(0,0,0,.12);transform:translateY(-2px)}
    .feedback-header{display:flex;align-items:center;gap:10px;margin-bottom:8px}
    .feedback-icon{width:36px;height:36px;background:var(--blue-light);border-radius:10px;display:flex;align-items:center;justify-content:center}
    .feedback-icon svg{width:20px;height:20px;color:var(--blue)}
    .feedback-title{font-size:14px;font-weight:700;color:var(--blue);flex:1}
    .feedback-new{background:#EF4444;color:white;font-size:10px;font-weight:700;padding:3px 8px;border-radius:10px}
    .feedback-text{font-size:12px;color:var(--text-2);line-height:1.6}

    /* Riwayat */
    .riwayat-list{display:flex;flex-direction:column;gap:10px}
    .riwayat-card{background:var(--white);border-radius:var(--radius-sm);box-shadow:var(--shadow);padding:12px 14px;display:flex;align-items:center;gap:12px;cursor:pointer;transition:all .2s;text-decoration:none}
    .riwayat-card:hover{box-shadow:var(--shadow-md);transform:translateX(3px)}
    .riwayat-icon{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
    .riwayat-icon svg{width:22px;height:22px}
    .riwayat-icon.orange{background:var(--orange-light);color:var(--orange)}
    .riwayat-icon.blue{background:var(--blue-light);color:var(--blue)}
    .riwayat-info{flex:1}
    .riwayat-title{font-size:13px;font-weight:700;color:var(--text);margin-bottom:2px}
    .riwayat-date{font-size:11px;color:var(--text-3)}
    .riwayat-score{font-size:15px;font-weight:800;color:var(--green);background:var(--green-light);width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
    .riwayat-arrow{color:var(--text-4);flex-shrink:0}
    .riwayat-arrow svg{width:16px;height:16px}

    /* Bottom Nav */
    .bottom-nav{position:fixed;bottom:0;left:50%;transform:translateX(-50%);width:100%;max-width:480px;background:var(--white);border-top:1px solid var(--border);display:flex;box-shadow:0 -4px 16px rgba(0,0,0,.08);z-index:50;height:var(--nav-h)}
    .nav-item{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px;cursor:pointer;border:none;background:transparent;font-family:inherit;padding:8px 4px;color:var(--text-4);text-decoration:none;position:relative}
    .nav-item svg{width:22px;height:22px}
    .nav-item span{font-size:10px;font-weight:600}
    .nav-item.active{color:var(--blue)}
    .nav-indicator{position:absolute;top:0;left:50%;transform:translateX(-50%) scaleX(0);width:32px;height:3px;background:var(--blue);border-radius:0 0 4px 4px;transition:transform .25s}
    .nav-item.active .nav-indicator{transform:translateX(-50%) scaleX(1)}

    .toast{position:fixed;bottom:84px;left:50%;transform:translateX(-50%) translateY(80px);background:#111827;color:#fff;padding:11px 20px;border-radius:10px;font-size:13px;font-weight:500;box-shadow:0 8px 30px rgba(0,0,0,.25);z-index:999;transition:transform .35s cubic-bezier(.22,1,.36,1);white-space:nowrap}
    .toast.show{transform:translateX(-50%) translateY(0)}
    @keyframes fadeUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}
    .anim{animation:fadeUp .4s both}
  </style>
</head>
<body>

<header class="topbar">
  <div class="topbar-logo">
    <svg viewBox="0 0 28 28" fill="none"><circle cx="18" cy="5" r="3" fill="#1A56DB"/><path d="M6 24L13 14 11 9 17 5" stroke="#1A56DB" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/><path d="M11 9L18 12L24 9" stroke="#1A56DB" stroke-width="2.2" stroke-linecap="round"/><path d="M18 12L15 20L19 24" stroke="#1A56DB" stroke-width="2.2" stroke-linecap="round"/><path d="M2 18Q9 14 16 17" stroke="#22C55E" stroke-width="2" stroke-linecap="round"/></svg>
    <span>PADI-PJOK</span>
  </div>
  <button class="notif-btn" onclick="showToast('1 notifikasi baru')" aria-label="Notifikasi">
    <svg viewBox="0 0 24 24" fill="none"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M13.73 21a2 2 0 01-3.46 0" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
    <span class="notif-dot"></span>
  </button>
</header>

<!-- Hero -->
<div class="hero">
  <div class="hero-text">
    <h1>Halo, <?= htmlspecialchars($siswa_nama) ?> 👋</h1>
    <p class="hero-sub">Sesi penilaian PJOK hari ini</p>
  </div>
  <img class="hero-img" src="siswa_hero.png" alt="Siswa PJOK"/>
</div>

<div class="content">

  <!-- Materi Aktif -->
  <a href="aktivitas-siswa.php" class="materi-card anim" style="text-decoration:none;display:block">
    <div class="materi-header">
      <div class="materi-badge">
        <div class="materi-icon"><svg viewBox="0 0 24 24" fill="none"><path d="M4 19.5A2.5 2.5 0 016.5 17H20" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z" stroke="currentColor" stroke-width="1.8"/></svg></div>
        <span class="materi-badge-text">Materi Aktif</span>
      </div>
      <span class="materi-arrow"><svg viewBox="0 0 24 24" fill="none"><path d="M9 18l6-6-6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></span>
    </div>
    <p class="materi-title"><?= htmlspecialchars($materi_title) ?></p>
    <div class="materi-date">
      <svg viewBox="0 0 24 24" fill="none"><rect x="3" y="4" width="18" height="18" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M16 2v4M8 2v4M3 10h18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
      Sesi hari ini &nbsp;•&nbsp; 08 Mei 2025
    </div>
    <div class="materi-status">
      <span class="status-label">Status Sesi</span>
      <span class="status-connected">
        <svg viewBox="0 0 24 24" fill="none"><path d="M5 12.55a11 11 0 0114.08 0" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M1.42 9a16 16 0 0121.16 0" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M8.53 16.11a6 6 0 016.95 0" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><line x1="12" y1="20" x2="12.01" y2="20" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        Terhubung
      </span>
    </div>
  </a>

  <!-- Nilai Terbaru -->
  <div class="anim">
    <div class="section-hdr" style="margin-bottom:12px">
      <h2>Nilai Terbaru</h2>
      <a href="rekap-penilaian.php" class="lihat-semua">Lihat semua <svg viewBox="0 0 24 24" fill="none"><path d="M9 18l6-6-6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></a>
    </div>
    <div class="nilai-row">
      <div class="nilai-card">
        <div class="nilai-icon blue"><svg viewBox="0 0 24 24" fill="none"><path d="M12 2C8 2 5 5 5 9c0 2.5 1.3 4.7 3.2 6H12h3.8C17.7 13.7 19 11.5 19 9c0-4-3-7-7-7z" stroke="currentColor" stroke-width="1.8"/><path d="M9 21h6M10 17h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></div>
        <span class="nilai-label">Kognitif</span>
        <span class="nilai-val"><?= $kognitif ?></span>
        <span class="nilai-grade blue"><?= $kognitif >= 85 ? 'Sangat Baik' : ($kognitif >= 70 ? 'Baik' : 'Cukup') ?></span>
      </div>
      <div class="nilai-card">
        <div class="nilai-icon green"><svg viewBox="0 0 24 24" fill="none"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z" stroke="currentColor" stroke-width="1.8"/></svg></div>
        <span class="nilai-label">Afektif</span>
        <span class="nilai-val"><?= $afektif ?></span>
        <span class="nilai-grade green"><?= $afektif >= 16 ? 'Sangat Baik' : ($afektif >= 12 ? 'Baik' : 'Cukup') ?></span>
      </div>
      <div class="nilai-card">
        <div class="nilai-icon orange"><svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="5" r="2.5" stroke="currentColor" stroke-width="1.8"/><path d="M12 8v5l-3 4M12 13l3 4M8 10l-3 2M16 10l3 2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></div>
        <span class="nilai-label">Psikomotor</span>
        <span class="nilai-val"><?= $psikomotor ?></span>
        <span class="nilai-grade orange"><?= $psikomotor >= 16 ? 'Sangat Baik' : ($psikomotor >= 12 ? 'Baik' : 'Cukup') ?></span>
      </div>
    </div>
  </div>

  <!-- Feedback Guru -->
  <div class="anim">
    <div class="feedback-card" onclick="showToast('Membuka feedback guru...')">
      <div class="feedback-header">
        <div class="feedback-icon"><svg viewBox="0 0 24 24" fill="none"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2v10z" stroke="currentColor" stroke-width="2"/></svg></div>
        <span class="feedback-title">Feedback Guru</span>
        <span class="feedback-new">Baru</span>
      </div>
      <p class="feedback-text">Teknik passing bawahmu sudah tepat dan konsisten. Pertahankan! Tingkatkan kekuatan dorongan tangan.</p>
    </div>
  </div>

  <!-- Riwayat Materi -->
  <div class="anim">
    <div class="section-hdr" style="margin-bottom:12px">
      <h2>Riwayat Materi</h2>
      <a href="#" class="lihat-semua" onclick="showToast('Riwayat lengkap'); return false;">Lihat semua <svg viewBox="0 0 24 24" fill="none"><path d="M9 18l6-6-6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></a>
    </div>
    <div class="riwayat-list">
      <a href="#" class="riwayat-card" onclick="showToast('Servis Bawah Bola Voli'); return false;">
        <div class="riwayat-icon orange">
          <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.8"/><path d="M8 12c0-2.2 1.8-4 4-4s4 1.8 4 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M12 16v.01" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/></svg>
        </div>
        <div class="riwayat-info">
          <p class="riwayat-title">Servis Bawah Bola Voli – Kelas X-1</p>
          <p class="riwayat-date">02 Mei 2025 &nbsp;•&nbsp; Selesai</p>
        </div>
        <div class="riwayat-score">86</div>
        <span class="riwayat-arrow"><svg viewBox="0 0 24 24" fill="none"><path d="M9 18l6-6-6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></span>
      </a>
      <a href="#" class="riwayat-card" onclick="showToast('Dribbling Sepak Bola'); return false;">
        <div class="riwayat-icon blue">
          <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.8"/><path d="M12 2a15.3 15.3 0 014 10 15.3 15.3 0 01-4 10 15.3 15.3 0 01-4-10 15.3 15.3 0 014-10z" stroke="currentColor" stroke-width="1.5"/><path d="M2 12h20" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
        </div>
        <div class="riwayat-info">
          <p class="riwayat-title">Dribbling Sepak Bola – Kelas X-1</p>
          <p class="riwayat-date">28 Apr 2025 &nbsp;•&nbsp; Selesai</p>
        </div>
        <div class="riwayat-score">89</div>
        <span class="riwayat-arrow"><svg viewBox="0 0 24 24" fill="none"><path d="M9 18l6-6-6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></span>
      </a>
    </div>
  </div>

</div>

<nav class="bottom-nav">
  <a href="#" class="nav-item active" aria-label="Beranda" aria-current="page">
    <div class="nav-indicator"></div>
    <svg viewBox="0 0 24 24" fill="none"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z" stroke="currentColor" stroke-width="2"/><path d="M9 22V12h6v10" stroke="currentColor" stroke-width="2"/></svg>
    <span>Beranda</span>
  </a>
  <a href="rekap-penilaian.php" class="nav-item" aria-label="Nilai">
    <div class="nav-indicator"></div>
    <svg viewBox="0 0 24 24" fill="none"><rect x="5" y="2" width="14" height="20" rx="2" stroke="currentColor" stroke-width="2"/><path d="M9 7h6M9 11h6M9 15h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
    <span>Nilai</span>
  </a>
  <a href="#" class="nav-item" onclick="showToast('Halaman Feedback'); return false;" aria-label="Feedback">
    <div class="nav-indicator"></div>
    <svg viewBox="0 0 24 24" fill="none"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2v10z" stroke="currentColor" stroke-width="2"/></svg>
    <span>Feedback</span>
  </a>
  <a href="#" class="nav-item" onclick="bukaToken(); return false;" aria-label="Token Saya">
    <div class="nav-indicator"></div>
    <svg viewBox="0 0 24 24" fill="none"><rect x="3" y="11" width="18" height="10" rx="2" stroke="currentColor" stroke-width="2"/><path d="M7 11V7a5 5 0 0110 0v4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><circle cx="12" cy="16" r="1.5" fill="currentColor"/></svg>
    <span>Token</span>
  </a>
</nav>

<!-- ── Popup token (verifikasi password) ── -->
<div class="overlay" id="overlay-token">
  <div class="modal">
    <h2>Token Materi Saya</h2>
    <p id="modal-pesan">Masukkan password (token sesi) untuk melihat token materi.</p>

    <div id="modal-langkah-1">
      <div class="form-group" style="text-align:left;margin:14px 0;">
        <label class="form-label" for="token-password">Password</label>
        <input class="form-input" type="password" id="token-password" placeholder="Masukkan password"/>
      </div>
      <button type="button" class="btn-modal primary" onclick="ceklahToken()">Tampilkan Token</button>
    </div>

    <div id="modal-langkah-2" style="display:none;">
      <div class="token-box">
        <p class="token-label">Token Materi</p>
        <p class="token-code" id="token-kode">—</p>
      </div>
      <p class="token-hint">Simpan token ini. Jangan bagikan kepada siswa lain.</p>
      <button type="button" class="btn-modal ghost" onclick="document.getElementById('overlay-token').classList.remove('show')">Tutup</button>
    </div>

    <div id="modal-langkah-3" style="display:none;">
      <p class="token-hint" id="token-err">Password salah.</p>
      <button type="button" class="btn-modal ghost" onclick="ulangToken()">Coba Lagi</button>
    </div>
  </div>
</div>

<style>
  .overlay{position:fixed;inset:0;background:rgba(17,24,39,.55);display:none;align-items:center;justify-content:center;padding:16px;z-index:200;overflow-y:auto}
  .overlay.show{display:flex}
  .modal{width:100%;max-width:360px;background:#fff;border-radius:20px;padding:24px 20px;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.3);margin:auto}
  .modal h2{font-size:18px;font-weight:800;color:#111827;margin-bottom:6px}
  .modal p{font-size:12.5px;color:#6B7280;line-height:1.6}
  .token-box{background:#EFF4FF;border:2px dashed #DBEAFE;border-radius:10px;padding:16px;margin:14px 0 8px}
  .token-label{font-size:11px;font-weight:700;color:#1A56DB;text-transform:uppercase;letter-spacing:.8px;margin-bottom:6px}
  .token-code{font-size:24px;font-weight:800;letter-spacing:2px;color:#1A56DB;word-break:break-all}
  .token-hint{font-size:11.5px;color:#9CA3AF;line-height:1.5;margin-bottom:14px}
  .btn-modal{width:100%;padding:13px;border-radius:10px;font-family:inherit;font-size:14px;font-weight:700;cursor:pointer;border:none;min-height:46px}
  .btn-modal.primary{background:#1A56DB;color:#fff}
  .btn-modal.ghost{background:#EFF4FF;color:#1A56DB}
</style>

<div class="toast" id="toast"></div>
<script>
  function showToast(m){const t=document.getElementById('toast');t.textContent=m;t.classList.add('show');clearTimeout(t._t);t._t=setTimeout(()=>t.classList.remove('show'),3000);}

  function bukaToken() {
    document.getElementById('modal-langkah-1').style.display = 'block';
    document.getElementById('modal-langkah-2').style.display = 'none';
    document.getElementById('modal-langkah-3').style.display = 'none';
    document.getElementById('token-password').value = '';
    document.getElementById('overlay-token').classList.add('show');
    setTimeout(() => document.getElementById('token-password').focus(), 120);
  }

  function ulangToken() {
    document.getElementById('modal-langkah-3').style.display = 'none';
    document.getElementById('modal-langkah-1').style.display = 'block';
    document.getElementById('token-password').value = '';
    document.getElementById('token-password').focus();
  }

  function ceklahToken() {
    var pw = document.getElementById('token-password').value.trim();
    if (!pw) { showToast('Masukkan password kamu'); return; }

    fetch('verifikasi-token.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ password: pw })
    })
    .then(r => r.json())
    .then(d => {
      if (d.success) {
        document.getElementById('token-kode').textContent = d.token || 'Belum ada sesi aktif';
        document.getElementById('modal-langkah-1').style.display = 'none';
        document.getElementById('modal-langkah-2').style.display = 'block';
      } else {
        document.getElementById('token-err').textContent = d.message || 'Password salah.';
        document.getElementById('modal-langkah-1').style.display = 'none';
        document.getElementById('modal-langkah-3').style.display = 'block';
      }
    })
    .catch(() => showToast('Kesalahan jaringan'));
  }
</script>
</body>
</html>
