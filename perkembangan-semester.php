<?php
require_once 'auth.php';
wajibLoginGuru();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Perkembangan Semester – PADI-PJOK</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    :root{--blue:#1A56DB;--blue-dark:#1240A8;--blue-light:#EFF4FF;--blue-mid:#DBEAFE;--green:#16A34A;--green-light:#DCFCE7;--orange:#EA580C;--orange-light:#FFF7ED;--yellow:#D97706;--yellow-light:#FFFBEB;--purple:#7C3AED;--purple-light:#F5F3FF;--pink:#DB2777;--pink-light:#FDF2F8;--text:#111827;--text-2:#374151;--text-3:#6B7280;--text-4:#9CA3AF;--border:#E5E7EB;--bg:#F3F6FB;--white:#FFFFFF;--nav-h:68px;--radius:14px;--radius-sm:10px;--shadow:0 2px 8px rgba(0,0,0,.07);--shadow-md:0 4px 18px rgba(0,0,0,.10)}
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
    .hero-text{flex:1;padding-bottom:20px;z-index:1}
    .hero-text h1{font-size:22px;font-weight:800;color:var(--text);margin-bottom:4px}
    .hero-subtitle{font-size:13px;font-weight:700;color:var(--blue);margin-bottom:6px}
    .student-row{display:flex;align-items:center;gap:8px}
    .student-row span{font-size:13px;font-weight:700;color:var(--text-2)}
    .badge-aktif{background:var(--green-light);color:var(--green);font-size:10px;font-weight:700;padding:3px 8px;border-radius:10px}
    .hero-img{width:120px;height:140px;object-fit:contain;object-position:bottom;flex-shrink:0;z-index:1}

    .content{padding:16px;display:flex;flex-direction:column;gap:16px}
    .section-title{font-size:15px;font-weight:800;color:var(--text);margin-bottom:12px}

    /* Ringkasan Perkembangan */
    .perkembangan-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:8px}
    .perk-card{background:var(--white);border-radius:var(--radius-sm);padding:10px 8px;box-shadow:var(--shadow);text-align:center}
    .perk-icon{width:28px;height:28px;border-radius:8px;display:flex;align-items:center;justify-content:center;margin:0 auto 6px}
    .perk-icon svg{width:16px;height:16px}
    .perk-icon.blue{background:var(--blue-light);color:var(--blue)}
    .perk-icon.green{background:var(--green-light);color:var(--green)}
    .perk-icon.purple{background:var(--purple-light);color:var(--purple)}
    .perk-icon.orange{background:var(--orange-light);color:var(--orange)}
    .perk-icon.yellow{background:var(--yellow-light);color:var(--yellow)}
    .perk-label{font-size:9px;font-weight:600;color:var(--text-3);margin-bottom:4px}
    .perk-val{font-size:11px;font-weight:800;color:var(--text);margin-bottom:3px;line-height:1.3}
    .perk-val.blue{color:var(--blue)}
    .perk-val.green{color:var(--green)}
    .perk-arrow{font-size:9px;font-weight:600;color:var(--green)}

    /* Kompetensi Abad 21 */
    .kompetensi-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:10px}
    .komp-card{background:var(--white);border-radius:var(--radius-sm);padding:12px;box-shadow:var(--shadow)}
    .komp-header{display:flex;align-items:center;gap:6px;margin-bottom:8px}
    .komp-icon{width:24px;height:24px;border-radius:6px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
    .komp-icon svg{width:14px;height:14px}
    .komp-icon.yellow{background:var(--yellow-light);color:var(--yellow)}
    .komp-icon.green{background:var(--green-light);color:var(--green)}
    .komp-icon.orange{background:var(--orange-light);color:var(--orange)}
    .komp-icon.purple{background:var(--purple-light);color:var(--purple)}
    .komp-label{font-size:11px;font-weight:700;color:var(--text-2)}
    .komp-trend{width:100%;height:36px;margin-bottom:6px}
    .komp-val{font-size:13px;font-weight:800;color:var(--text)}
    .komp-up{font-size:10px;font-weight:600;color:var(--green)}

    /* Chart */
    .chart-card{background:var(--white);border-radius:var(--radius);box-shadow:var(--shadow-md);padding:16px;overflow:hidden}
    .chart-wrap{width:100%;overflow-x:auto;padding-bottom:8px;scrollbar-width:none}
    .chart-wrap::-webkit-scrollbar{display:none}
    canvas{display:block}

    /* Riwayat Feedback */
    .riwayat-card{background:var(--white);border-radius:var(--radius);box-shadow:var(--shadow-md);padding:16px}
    .riwayat-list{display:flex;flex-direction:column;gap:0}
    .riwayat-item{display:flex;gap:12px;padding:10px 0;position:relative}
    .riwayat-item+.riwayat-item{border-top:1px solid var(--border)}
    .riwayat-dot{width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:2px}
    .riwayat-dot svg{width:14px;height:14px}
    .riwayat-dot.blue{background:var(--blue-light);color:var(--blue)}
    .riwayat-dot.green{background:var(--green-light);color:var(--green)}
    .riwayat-dot.orange{background:var(--orange-light);color:var(--orange)}
    .riwayat-dot.purple{background:var(--purple-light);color:var(--purple)}
    .riwayat-dot.yellow{background:var(--yellow-light);color:var(--yellow)}
    .riwayat-info{}
    .riwayat-pertemuan{font-size:11px;font-weight:700;color:var(--text-3);margin-bottom:2px}
    .riwayat-desc{font-size:12px;color:var(--text-2);line-height:1.5}

    /* AI Rekap */
    .ai-card{background:var(--white);border-radius:var(--radius);box-shadow:var(--shadow-md);padding:16px}
    .ai-header{display:flex;align-items:center;gap:8px;margin-bottom:10px}
    .ai-icon{width:32px;height:32px;border-radius:8px;background:linear-gradient(135deg,#6366F1,#8B5CF6);display:flex;align-items:center;justify-content:center}
    .ai-icon svg{width:18px;height:18px;color:white}
    .ai-title{font-size:14px;font-weight:700;color:var(--text)}
    .ai-body{font-size:12px;color:var(--text-2);line-height:1.7;margin-bottom:14px}
    .ai-badges{display:flex;gap:8px;flex-wrap:wrap}
    .ai-badge{display:flex;align-items:center;gap:5px;padding:6px 10px;border-radius:8px;font-size:10px;font-weight:700}
    .ai-badge.green{background:var(--green-light);color:var(--green)}
    .ai-badge.blue{background:var(--blue-light);color:var(--blue)}
    .ai-badge.orange{background:var(--orange-light);color:var(--orange)}
    .ai-badge svg{width:13px;height:13px}

    /* Action */
    .action-row{display:grid;grid-template-columns:1fr 1fr;gap:10px}
    .btn-outline{display:flex;align-items:center;justify-content:center;gap:8px;padding:13px;border-radius:var(--radius-sm);font-family:inherit;font-size:13px;font-weight:700;cursor:pointer;transition:all .2s}
    .btn-outline svg{width:18px;height:18px}
    .btn-secondary{background:var(--white);border:1.5px solid var(--border);color:var(--text-2)}
    .btn-secondary:hover{border-color:var(--blue);color:var(--blue)}
    .btn-primary{background:var(--blue);border:none;color:var(--white);box-shadow:0 4px 14px rgba(26,86,219,.35)}
    .btn-primary:hover{background:var(--blue-dark);transform:translateY(-1px)}

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
  <button class="notif-btn" onclick="showToast('3 notifikasi baru')" aria-label="Notifikasi">
    <svg viewBox="0 0 24 24" fill="none"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M13.73 21a2 2 0 01-3.46 0" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
    <span class="notif-dot"></span>
  </button>
</header>

<div class="hero">
  <div class="hero-text">
    <h1>Perkembangan Semester</h1>
    <p class="hero-subtitle">Kelas X-1 | Semester Ganjil</p>
    <div class="student-row">
      <span>Ahmad Fajar</span>
      <span class="badge-aktif">Aktif</span>
    </div>
  </div>
  <img class="hero-img" src="guru_dashboard_hero.png" alt="Guru PJOK"/>
</div>

<div class="content">

  <!-- 1. Ringkasan Perkembangan -->
  <div class="anim">
    <p class="section-title">1. Ringkasan Perkembangan</p>
    <div class="perkembangan-grid">
      <div class="perk-card">
        <div class="perk-icon blue"><svg viewBox="0 0 24 24" fill="none"><path d="M12 2C8 2 5 5 5 9c0 2.5 1.3 4.7 3.2 6H12h3.8C17.7 13.7 19 11.5 19 9c0-4-3-7-7-7z" stroke="currentColor" stroke-width="1.8"/><path d="M9 21h6M10 17h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></div>
        <div class="perk-label">Kognitif</div>
        <div class="perk-val blue">80 → 88</div>
        <div class="perk-arrow">↑ +8 poin</div>
      </div>
      <div class="perk-card">
        <div class="perk-icon green"><svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="5" r="2.5" stroke="currentColor" stroke-width="1.8"/><path d="M12 8v5l-3 4M12 13l3 4M8 10l-3 2M16 10l3 2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></div>
        <div class="perk-label">Psikomotor</div>
        <div class="perk-val green">78 → 86</div>
        <div class="perk-arrow">↑ +8 poin</div>
      </div>
      <div class="perk-card">
        <div class="perk-icon purple"><svg viewBox="0 0 24 24" fill="none"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z" stroke="currentColor" stroke-width="1.8"/></svg></div>
        <div class="perk-label">Afektif</div>
        <div class="perk-val" style="color:var(--purple);font-size:10px">Baik → Sangat Baik</div>
        <div class="perk-arrow">↑ Meningkat</div>
      </div>
      <div class="perk-card">
        <div class="perk-icon orange"><svg viewBox="0 0 24 24" fill="none"><rect x="3" y="4" width="18" height="18" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M16 2v4M8 2v4M3 10h18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></div>
        <div class="perk-label">Kehadiran</div>
        <div class="perk-val orange">14/16</div>
        <div class="perk-arrow" style="color:var(--text-3)">88%</div>
      </div>
      <div class="perk-card">
        <div class="perk-icon yellow"><svg viewBox="0 0 24 24" fill="none"><path d="M12 2l3 6.3 6.9 1-5 4.9 1.2 7-6.1-3.2L5.9 21l1.2-7-5-4.9 6.9-1L12 2z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg></div>
        <div class="perk-label">Rata-rata Semester</div>
        <div class="perk-val yellow">85</div>
        <div class="perk-arrow">↑ +7 poin</div>
      </div>
    </div>
  </div>

  <!-- 2. Kompetensi Abad 21 -->
  <div class="anim">
    <p class="section-title">2. Kompetensi Abad 21</p>
    <div class="kompetensi-grid">
      <div class="komp-card">
        <div class="komp-header">
          <div class="komp-icon yellow"><svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.8"/><path d="M12 1v3M12 20v3M4.22 4.22l2.12 2.12M17.66 17.66l2.12 2.12M1 12h3M20 12h3M4.22 19.78l2.12-2.12M17.66 6.34l2.12-2.12" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></div>
          <span class="komp-label">Berpikir Kritis</span>
        </div>
        <svg class="komp-trend" viewBox="0 0 120 40" xmlns="http://www.w3.org/2000/svg">
          <polyline points="5,30 20,28 35,25 50,22 65,18 80,14 95,10 115,8" fill="none" stroke="#1A56DB" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
          <circle cx="5" cy="30" r="3" fill="#1A56DB"/>
          <circle cx="115" cy="8" r="3" fill="#1A56DB"/>
        </svg>
        <div class="komp-val">74 → 87</div>
        <div class="komp-up">↑ Meningkat</div>
      </div>
      <div class="komp-card">
        <div class="komp-header">
          <div class="komp-icon green"><svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="3" fill="currentColor"/><path d="M7 12c0-2.8 2.2-5 5-5s5 2.2 5 5M5 19c0-3.9 3.1-7 7-7s7 3.1 7 7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></div>
          <span class="komp-label">Kreativitas</span>
        </div>
        <svg class="komp-trend" viewBox="0 0 120 40" xmlns="http://www.w3.org/2000/svg">
          <polyline points="5,32 20,28 35,26 50,21 65,17 80,14 95,11 115,7" fill="none" stroke="#16A34A" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
          <circle cx="5" cy="32" r="3" fill="#16A34A"/>
          <circle cx="115" cy="7" r="3" fill="#16A34A"/>
        </svg>
        <div class="komp-val">72 → 84</div>
        <div class="komp-up">↑ Meningkat</div>
      </div>
      <div class="komp-card">
        <div class="komp-header">
          <div class="komp-icon orange"><svg viewBox="0 0 24 24" fill="none"><circle cx="9" cy="7" r="3" stroke="currentColor" stroke-width="1.8"/><circle cx="16" cy="7" r="3" stroke="currentColor" stroke-width="1.8"/><path d="M2 21c0-3.5 3-6 7-6h4c4 0 7 2.5 7 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></div>
          <span class="komp-label">Kolaborasi</span>
        </div>
        <svg class="komp-trend" viewBox="0 0 120 40" xmlns="http://www.w3.org/2000/svg">
          <polyline points="5,30 20,26 35,24 50,20 65,16 80,13 95,10 115,7" fill="none" stroke="#EA580C" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
          <circle cx="5" cy="30" r="3" fill="#EA580C"/>
          <circle cx="115" cy="7" r="3" fill="#EA580C"/>
        </svg>
        <div class="komp-val">76 → 88</div>
        <div class="komp-up">↑ Meningkat</div>
      </div>
      <div class="komp-card">
        <div class="komp-header">
          <div class="komp-icon purple"><svg viewBox="0 0 24 24" fill="none"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2v10z" stroke="currentColor" stroke-width="1.8"/></svg></div>
          <span class="komp-label">Komunikasi</span>
        </div>
        <svg class="komp-trend" viewBox="0 0 120 40" xmlns="http://www.w3.org/2000/svg">
          <polyline points="5,31 20,27 35,25 50,21 65,18 80,14 95,11 115,8" fill="none" stroke="#7C3AED" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
          <circle cx="5" cy="31" r="3" fill="#7C3AED"/>
          <circle cx="115" cy="8" r="3" fill="#7C3AED"/>
        </svg>
        <div class="komp-val">73 → 86</div>
        <div class="komp-up">↑ Meningkat</div>
      </div>
    </div>
  </div>

  <!-- 3. Grafik Perkembangan -->
  <div class="chart-card anim">
    <p class="section-title">3. Grafik Perkembangan</p>
    <div class="chart-wrap">
      <canvas id="chart" width="680" height="200"></canvas>
    </div>
  </div>

  <!-- 4. Riwayat Feedback -->
  <div class="riwayat-card anim">
    <p class="section-title">4. Riwayat Feedback per Pertemuan</p>
    <div class="riwayat-list">
      <div class="riwayat-item">
        <div class="riwayat-dot blue"><svg viewBox="0 0 24 24" fill="none"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2v10z" stroke="currentColor" stroke-width="2"/></svg></div>
        <div class="riwayat-info"><div class="riwayat-pertemuan">Pertemuan 1</div><div class="riwayat-desc">Masih kurang percaya diri saat passing bawah</div></div>
      </div>
      <div class="riwayat-item">
        <div class="riwayat-dot green"><svg viewBox="0 0 24 24" fill="none"><path d="M9 12l2 2 4-4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></div>
        <div class="riwayat-info"><div class="riwayat-pertemuan">Pertemuan 4</div><div class="riwayat-desc">Mulai memahami teknik dan berani mencoba</div></div>
      </div>
      <div class="riwayat-item">
        <div class="riwayat-dot orange"><svg viewBox="0 0 24 24" fill="none"><circle cx="9" cy="7" r="3" stroke="currentColor" stroke-width="1.8"/><circle cx="16" cy="7" r="3" stroke="currentColor" stroke-width="1.8"/><path d="M2 21c0-3.5 3-6 7-6h4c4 0 7 2.5 7 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></div>
        <div class="riwayat-info"><div class="riwayat-pertemuan">Pertemuan 8</div><div class="riwayat-desc">Kerja sama dengan teman semakin baik</div></div>
      </div>
      <div class="riwayat-item">
        <div class="riwayat-dot purple"><svg viewBox="0 0 24 24" fill="none"><path d="M12 2l3 6.3 6.9 1-5 4.9 1.2 7-6.1-3.2L5.9 21l1.2-7-5-4.9 6.9-1L12 2z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg></div>
        <div class="riwayat-info"><div class="riwayat-pertemuan">Pertemuan 12</div><div class="riwayat-desc">Mampu menilai diri sendiri dengan lebih kritis</div></div>
      </div>
      <div class="riwayat-item">
        <div class="riwayat-dot yellow"><svg viewBox="0 0 24 24" fill="none"><path d="M3 6h18M3 12h18M3 18h18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></div>
        <div class="riwayat-info"><div class="riwayat-pertemuan">Pertemuan 16</div><div class="riwayat-desc">Performa lebih stabil, komunikasi dan refleksi meningkat</div></div>
      </div>
    </div>
  </div>

  <!-- 5. Rekap Feedback Semester (AI) -->
  <div class="ai-card anim">
    <div class="ai-header">
      <div class="ai-icon"><svg viewBox="0 0 24 24" fill="none"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z" fill="rgba(255,255,255,.2)"/><path d="M9 12l2 2 4-4M12 7v1M12 16v1" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></div>
      <span class="ai-title">5. Rekap Feedback Semester</span>
    </div>
    <p style="font-size:11px;color:#7C3AED;font-weight:600;margin-bottom:8px;display:flex;align-items:center;gap:5px">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z" stroke="#7C3AED" stroke-width="2"/><path d="M9.5 9l3 3-3 3" stroke="#7C3AED" stroke-width="2" stroke-linecap="round"/></svg>
      AI membantu merangkum perkembangan, guru tetap meninjau dan mengedit sebelum dikirim.
    </p>
    <p class="ai-body">Ahmad menunjukkan perkembangan yang konsisten sepanjang semester. Pemahaman materi meningkat, teknik passing bawah semakin stabil, dan kemampuan berpikir kritis, komunikasi, serta kolaborasi terlihat lebih kuat dibanding awal semester.</p>
    <div class="ai-badges">
      <span class="ai-badge green"><svg viewBox="0 0 24 24" fill="none"><path d="M18 20V10M12 20V4M6 20v-6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>Tren Positif</span>
      <span class="ai-badge blue"><svg viewBox="0 0 24 24" fill="none"><path d="M12 22a10 10 0 100-20 10 10 0 000 20z" stroke="currentColor" stroke-width="2"/><path d="M9 12l2 2 4-4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>Tujuan Tercapai</span>
      <span class="ai-badge orange"><svg viewBox="0 0 24 24" fill="none"><path d="M12 2l3 6.3 6.9 1-5 4.9 1.2 7-6.1-3.2L5.9 21l1.2-7-5-4.9 6.9-1L12 2z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>Siap Melanjutkan</span>
    </div>
  </div>

  <!-- Actions -->
  <div class="action-row">
    <button class="btn-outline btn-secondary" onclick="showToast('Mengunduh portofolio...')">
      <svg viewBox="0 0 24 24" fill="none"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><polyline points="7 10 12 15 17 10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><line x1="12" y1="15" x2="12" y2="3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
      Unduh Portofolio
    </button>
    <button class="btn-outline btn-primary" onclick="showToast('Laporan dikirim!')">
      <svg viewBox="0 0 24 24" fill="none"><line x1="22" y1="2" x2="11" y2="13" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><polygon points="22 2 15 22 11 13 2 9 22 2" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>
      Kirim Laporan
    </button>
  </div>

</div>

<nav class="bottom-nav">
  <a href="dashboard-guru.php" class="nav-item" aria-label="Beranda"><div class="nav-indicator"></div><svg viewBox="0 0 24 24" fill="none"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z" stroke="currentColor" stroke-width="2"/><path d="M9 22V12h6v10" stroke="currentColor" stroke-width="2"/></svg><span>Beranda</span></a>
  <a href="#" class="nav-item" onclick="showToast('Aktivitas'); return false;" aria-label="Aktivitas"><div class="nav-indicator"></div><svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="8" r="3" stroke="currentColor" stroke-width="2"/><path d="M12 11v4l-2 3h4l-2-3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M6 10l-3 2M18 10l3 2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg><span>Aktivitas</span></a>
  <a href="#" class="nav-item active" aria-label="Nilai" aria-current="page"><div class="nav-indicator"></div><svg viewBox="0 0 24 24" fill="none"><rect x="5" y="2" width="14" height="20" rx="2" stroke="currentColor" stroke-width="2"/><path d="M9 7h6M9 11h6M9 15h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg><span>Nilai</span></a>
  <a href="#" class="nav-item" onclick="showToast('Profil'); return false;" aria-label="Profil"><div class="nav-indicator"></div><svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="8" r="4" stroke="currentColor" stroke-width="2"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg><span>Profil</span></a>
</nav>

<div class="toast" id="toast"></div>
<script>
  function showToast(m){const t=document.getElementById('toast');t.textContent=m;t.classList.add('show');clearTimeout(t._t);t._t=setTimeout(()=>t.classList.remove('show'),3000);}

  // Draw chart
  const canvas = document.getElementById('chart');
  const ctx = canvas.getContext('2d');
  const scores = [62,64,66,68,71,73,75,78,80,81,82,84,85,86,87,88];
  const W = 680, H = 200, PAD = { top: 20, right: 20, bottom: 40, left: 36 };
  const chartW = W - PAD.left - PAD.right;
  const chartH = H - PAD.top - PAD.bottom;
  const minV = 0, maxV = 100;
  const scaleX = i => PAD.left + (i / (scores.length - 1)) * chartW;
  const scaleY = v => PAD.top + chartH - ((v - minV) / (maxV - minV)) * chartH;

  // Grid
  ctx.strokeStyle = '#E5E7EB'; ctx.lineWidth = 1;
  [0,25,50,75,100].forEach(v => {
    const y = scaleY(v);
    ctx.beginPath(); ctx.moveTo(PAD.left, y); ctx.lineTo(W - PAD.right, y); ctx.stroke();
    ctx.fillStyle = '#9CA3AF'; ctx.font = '10px Inter'; ctx.textAlign = 'right';
    ctx.fillText(v, PAD.left - 4, y + 4);
  });

  // X labels
  ctx.fillStyle = '#9CA3AF'; ctx.textAlign = 'center'; ctx.font = '10px Inter';
  scores.forEach((_, i) => { ctx.fillText(i + 1, scaleX(i), H - 8); });

  // Axis labels
  ctx.fillStyle = '#6B7280'; ctx.font = '10px Inter'; ctx.textAlign = 'center';
  ctx.fillText('Pertemuan', W / 2, H - 0);
  ctx.save(); ctx.translate(10, H / 2); ctx.rotate(-Math.PI/2);
  ctx.fillText('Skor', 0, 0); ctx.restore();

  // Gradient fill
  const grad = ctx.createLinearGradient(0, PAD.top, 0, H - PAD.bottom);
  grad.addColorStop(0, 'rgba(26,86,219,.25)'); grad.addColorStop(1, 'rgba(26,86,219,0)');
  ctx.beginPath();
  scores.forEach((v, i) => { i === 0 ? ctx.moveTo(scaleX(i), scaleY(v)) : ctx.lineTo(scaleX(i), scaleY(v)); });
  ctx.lineTo(scaleX(scores.length - 1), H - PAD.bottom);
  ctx.lineTo(scaleX(0), H - PAD.bottom);
  ctx.closePath(); ctx.fillStyle = grad; ctx.fill();

  // Line
  ctx.beginPath(); ctx.strokeStyle = '#1A56DB'; ctx.lineWidth = 2.5; ctx.lineJoin = 'round'; ctx.lineCap = 'round';
  scores.forEach((v, i) => { i === 0 ? ctx.moveTo(scaleX(i), scaleY(v)) : ctx.lineTo(scaleX(i), scaleY(v)); });
  ctx.stroke();

  // Dots + labels
  scores.forEach((v, i) => {
    const x = scaleX(i), y = scaleY(v);
    ctx.beginPath(); ctx.arc(x, y, 4, 0, Math.PI * 2);
    ctx.fillStyle = '#1A56DB'; ctx.fill();
    ctx.beginPath(); ctx.arc(x, y, 2.5, 0, Math.PI * 2);
    ctx.fillStyle = '#fff'; ctx.fill();
    if (i % 2 === 0 || i === scores.length - 1) {
      ctx.fillStyle = '#374151'; ctx.font = '9px Inter'; ctx.textAlign = 'center';
      ctx.fillText(v, x, y - 9);
    }
  });

  // Start & end labels
  ctx.font = 'bold 10px Inter';
  ctx.fillStyle = '#1A56DB';
  const startX = scaleX(0), startY = scaleY(scores[0]);
  ctx.fillText('Awal', startX, startY + 20);
  ctx.fillText('Semester', startX, startY + 31);
  const endX = scaleX(scores.length - 1), endY = scaleY(scores[scores.length - 1]);
  const endBox = { x: endX - 38, y: endY + 8, w: 72, h: 26 };
  ctx.fillStyle = '#1A56DB'; roundRect(ctx, endBox.x, endBox.y, endBox.w, endBox.h, 6); ctx.fill();
  ctx.fillStyle = '#fff'; ctx.textAlign = 'center';
  ctx.fillText('Akhir Semester', endX - 2, endBox.y + 17);

  function roundRect(ctx, x, y, w, h, r) {
    ctx.beginPath(); ctx.moveTo(x + r, y);
    ctx.lineTo(x + w - r, y); ctx.quadraticCurveTo(x + w, y, x + w, y + r);
    ctx.lineTo(x + w, y + h - r); ctx.quadraticCurveTo(x + w, y + h, x + w - r, y + h);
    ctx.lineTo(x + r, y + h); ctx.quadraticCurveTo(x, y + h, x, y + h - r);
    ctx.lineTo(x, y + r); ctx.quadraticCurveTo(x, y, x + r, y);
    ctx.closePath();
  }
</script>
</body>
</html>
