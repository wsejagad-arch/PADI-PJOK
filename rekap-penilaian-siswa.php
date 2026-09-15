<?php
require_once 'auth.php';
wajibLoginGuru();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Rekap Penilaian Siswa – PADI-PJOK</title>
  <meta name="description" content="Pantau seluruh aktivitas, progres, dan hasil penilaian siswa dalam satu tampilan."/>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --blue:#1A56DB; --blue-dark:#1240A8; --blue-light:#EFF4FF; --blue-mid:#DBEAFE;
      --green:#16A34A; --green-light:#DCFCE7; --orange:#EA580C; --orange-light:#FFF7ED;
      --yellow:#D97706; --yellow-light:#FFFBEB; --purple:#7C3AED; --purple-light:#F5F3FF;
      --red:#DC2626; --red-light:#FEF2F2; --gray-light:#F9FAFB;
      --text:#111827; --text-2:#374151; --text-3:#6B7280; --text-4:#9CA3AF;
      --border:#E5E7EB; --bg:#F3F6FB; --white:#FFFFFF;
      --nav-h:68px; --radius:14px; --radius-sm:10px;
      --shadow:0 2px 8px rgba(0,0,0,.07); --shadow-md:0 4px 18px rgba(0,0,0,.10);
    }
    body { font-family:'Inter',sans-serif; background:var(--bg); color:var(--text); max-width:480px; margin:0 auto; padding-bottom:var(--nav-h); }

    /* Topbar */
    .topbar { position:sticky; top:0; z-index:50; display:flex; align-items:center; justify-content:space-between; padding:14px 20px; background:var(--white); border-bottom:1px solid var(--border); box-shadow:var(--shadow); }
    .topbar-logo { display:flex; align-items:center; gap:8px; }
    .topbar-logo svg { width:26px; height:26px; }
    .topbar-logo span { font-size:16px; font-weight:800; color:var(--blue); }
    .notif-btn { position:relative; width:36px; height:36px; background:var(--blue-light); border:none; border-radius:50%; display:flex; align-items:center; justify-content:center; cursor:pointer; }
    .notif-btn svg { width:20px; height:20px; color:var(--blue); }
    .notif-dot { position:absolute; top:6px; right:6px; width:8px; height:8px; background:#EF4444; border-radius:50%; border:2px solid var(--white); }

    /* Hero */
    .hero { background:linear-gradient(135deg,#EFF6FF 0%,#DBEAFE 60%,#E0F2FE 100%); padding:20px 20px 0; display:flex; align-items:flex-end; min-height:150px; position:relative; overflow:hidden; }
    .hero-text { flex:1; padding-bottom:20px; z-index:1; }
    .hero-text h1 { font-size:22px; font-weight:800; color:var(--text); margin-bottom:4px; }
    .hero-subtitle { font-size:13px; font-weight:700; color:var(--blue); margin-bottom:6px; }
    .hero-text p { font-size:12px; color:var(--text-3); line-height:1.6; max-width:200px; }
    .hero-img { width:120px; height:140px; object-fit:contain; object-position:bottom; flex-shrink:0; z-index:1; }

    .content { padding:16px; display:flex; flex-direction:column; gap:14px; }

    /* Session Card */
    .session-card { background:var(--white); border-radius:var(--radius); box-shadow:var(--shadow-md); padding:16px; }
    .session-top { display:flex; align-items:center; justify-content:space-between; margin-bottom:14px; }
    .session-top h2 { font-size:14px; font-weight:700; color:var(--text); }
    .status-aktif { display:flex; align-items:center; gap:5px; background:var(--green-light); color:var(--green); font-size:11px; font-weight:700; padding:5px 10px; border-radius:20px; }
    .status-aktif::before { content:''; width:7px; height:7px; background:var(--green); border-radius:50%; }

    .session-stats { display:flex; gap:12px; margin-bottom:14px; }
    .s-stat { display:flex; align-items:center; gap:8px; flex:1; }
    .s-stat-icon { width:36px; height:36px; border-radius:8px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
    .s-stat-icon svg { width:18px; height:18px; }
    .s-stat-icon.blue { background:var(--blue-light); color:var(--blue); }
    .s-stat-icon.purple { background:var(--purple-light); color:var(--purple); }
    .s-stat-icon.yellow { background:var(--yellow-light); color:var(--yellow); }
    .s-stat-label { font-size:10px; color:var(--text-3); font-weight:500; }
    .s-stat-val { font-size:16px; font-weight:800; color:var(--text); line-height:1; }

    .session-divider { height:1px; background:var(--border); margin:12px -16px; }

    .completion-row { display:flex; gap:0; }
    .c-item { flex:1; display:flex; align-items:center; justify-content:center; gap:6px; }
    .c-item + .c-item { border-left:1px solid var(--border); }
    .c-num { font-size:18px; font-weight:800; }
    .c-label { font-size:10px; color:var(--text-3); }
    .c-num.green { color:var(--green); }
    .c-num.orange { color:var(--orange); }
    .c-num.gray { color:var(--text-4); }

    /* Filter Tabs */
    .filter-tabs { display:flex; gap:6px; overflow-x:auto; scrollbar-width:none; padding:2px 0; }
    .filter-tabs::-webkit-scrollbar { display:none; }
    .filter-tab { flex-shrink:0; display:flex; align-items:center; gap:5px; padding:8px 14px; border-radius:20px; font-size:12px; font-weight:700; border:1.5px solid var(--border); background:var(--white); color:var(--text-3); cursor:pointer; transition:all .2s; white-space:nowrap; }
    .filter-tab.active { background:var(--blue); color:var(--white); border-color:var(--blue); box-shadow:0 4px 12px rgba(26,86,219,.3); }
    .filter-tab svg { width:14px; height:14px; }

    /* Search */
    .search-wrap { position:relative; display:flex; align-items:center; gap:10px; }
    .search-input-wrap { flex:1; position:relative; }
    .search-icon { position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--text-4); }
    .search-icon svg { width:16px; height:16px; }
    .search-input { width:100%; padding:11px 12px 11px 38px; border:1.5px solid var(--border); border-radius:var(--radius-sm); font-family:inherit; font-size:13px; color:var(--text); background:var(--white); outline:none; transition:border-color .2s; }
    .search-input:focus { border-color:var(--blue); }
    .search-input::placeholder { color:var(--text-4); }
    .filter-btn { width:40px; height:40px; background:var(--white); border:1.5px solid var(--border); border-radius:var(--radius-sm); display:flex; align-items:center; justify-content:center; cursor:pointer; flex-shrink:0; }
    .filter-btn svg { width:18px; height:18px; color:var(--text-3); }

    /* Student Cards */
    .student-list { display:flex; flex-direction:column; gap:12px; }

    .student-card { background:var(--white); border-radius:var(--radius); box-shadow:var(--shadow); padding:14px; transition:box-shadow .2s; }
    .student-card:hover { box-shadow:var(--shadow-md); }

    .student-header { display:flex; align-items:center; gap:10px; margin-bottom:10px; }
    .student-avatar { width:44px; height:44px; border-radius:50%; object-fit:cover; flex-shrink:0; background:var(--blue-light); display:flex; align-items:center; justify-content:center; overflow:hidden; }
    .student-avatar svg { width:28px; height:28px; color:var(--blue); }
    .student-name { font-size:14px; font-weight:700; color:var(--text); }
    .badge-hadir { background:var(--green-light); color:var(--green); font-size:10px; font-weight:700; padding:3px 8px; border-radius:10px; margin-left:6px; }
    .student-score { margin-left:auto; width:36px; height:36px; border-radius:10px; background:var(--blue-light); display:flex; align-items:center; justify-content:center; font-size:15px; font-weight:800; color:var(--blue); flex-shrink:0; }
    .student-score.green { background:var(--green-light); color:var(--green); }
    .student-score.orange { background:var(--orange-light); color:var(--orange); }

    /* Activity Progress */
    .activity-bar-wrap { margin-bottom:8px; }
    .activity-bar-label { font-size:10px; color:var(--text-3); margin-bottom:4px; text-align:right; }
    .activity-bar { height:5px; background:var(--border); border-radius:99px; overflow:hidden; }
    .activity-bar-fill { height:100%; border-radius:99px; background:var(--blue); transition:width 1s ease; }

    .activity-steps { display:flex; gap:4px; margin-bottom:10px; }
    .act-step { flex:1; display:flex; flex-direction:column; align-items:center; gap:3px; }
    .act-step-num { font-size:8px; color:var(--text-4); font-weight:600; }
    .act-step-label { font-size:8px; color:var(--text-3); font-weight:500; text-align:center; line-height:1.2; }
    .act-step-icon { width:20px; height:20px; border-radius:50%; display:flex; align-items:center; justify-content:center; }
    .act-step-icon svg { width:12px; height:12px; }
    .act-step-icon.done { background:var(--green-light); color:var(--green); }
    .act-step-icon.pending { background:var(--orange-light); color:var(--orange); }
    .act-step-icon.empty { background:var(--gray-light); color:var(--text-4); border:1.5px solid var(--border); }
    .act-step-icon.error { background:var(--red-light); color:var(--red); }

    .student-status { font-size:11px; margin-bottom:10px; }
    .student-status.orange { color:var(--orange); font-weight:600; }
    .student-status.green { color:var(--green); font-weight:600; }
    .student-status.red { color:var(--red); font-weight:600; }

    .btn-detail { display:inline-flex; align-items:center; gap:4px; font-size:11px; font-weight:700; color:var(--blue); background:var(--white); border:1.5px solid var(--blue); border-radius:20px; padding:6px 14px; cursor:pointer; transition:all .2s; float:right; }
    .btn-detail:hover { background:var(--blue); color:var(--white); }
    .btn-detail svg { width:13px; height:13px; }

    /* Ringkasan Kelas */
    .ringkasan-card { background:var(--white); border-radius:var(--radius); box-shadow:var(--shadow-md); padding:16px; }
    .ringkasan-title { font-size:15px; font-weight:800; color:var(--text); margin-bottom:14px; }
    .ringkasan-grid { display:grid; grid-template-columns:repeat(5,1fr); gap:8px; }
    .r-item { display:flex; flex-direction:column; align-items:center; gap:4px; text-align:center; }
    .r-icon { width:32px; height:32px; border-radius:8px; display:flex; align-items:center; justify-content:center; }
    .r-icon svg { width:18px; height:18px; }
    .r-icon.blue { background:var(--blue-light); color:var(--blue); }
    .r-icon.green { background:var(--green-light); color:var(--green); }
    .r-icon.purple { background:var(--purple-light); color:var(--purple); }
    .r-icon.orange { background:var(--orange-light); color:var(--orange); }
    .r-icon.pink { background:#FDF2F8; color:#DB2777; }
    .r-label { font-size:9px; color:var(--text-3); font-weight:500; line-height:1.2; }
    .r-val { font-size:13px; font-weight:800; color:var(--text); }

    /* Action buttons */
    .action-row { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
    .btn-outline { display:flex; align-items:center; justify-content:center; gap:8px; padding:13px; border-radius:var(--radius-sm); font-family:inherit; font-size:13px; font-weight:700; cursor:pointer; transition:all .2s; }
    .btn-outline svg { width:18px; height:18px; }
    .btn-secondary { background:var(--white); border:1.5px solid var(--border); color:var(--text-2); }
    .btn-secondary:hover { border-color:var(--blue); color:var(--blue); }
    .btn-primary { background:var(--blue); border:none; color:var(--white); box-shadow:0 4px 14px rgba(26,86,219,.35); }
    .btn-primary:hover { background:var(--blue-dark); transform:translateY(-1px); }

    /* Bottom Nav */
    .bottom-nav { position:fixed; bottom:0; left:50%; transform:translateX(-50%); width:100%; max-width:480px; background:var(--white); border-top:1px solid var(--border); display:flex; box-shadow:0 -4px 16px rgba(0,0,0,.08); z-index:50; height:var(--nav-h); }
    .nav-item { flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:4px; cursor:pointer; border:none; background:transparent; font-family:inherit; padding:8px 4px; color:var(--text-4); text-decoration:none; position:relative; }
    .nav-item svg { width:22px; height:22px; }
    .nav-item span { font-size:10px; font-weight:600; }
    .nav-item.active { color:var(--blue); }
    .nav-indicator { position:absolute; top:0; left:50%; transform:translateX(-50%) scaleX(0); width:32px; height:3px; background:var(--blue); border-radius:0 0 4px 4px; transition:transform .25s; }
    .nav-item.active .nav-indicator { transform:translateX(-50%) scaleX(1); }

    /* Toast */
    .toast { position:fixed; bottom:84px; left:50%; transform:translateX(-50%) translateY(80px); background:#111827; color:#fff; padding:11px 20px; border-radius:10px; font-size:13px; font-weight:500; box-shadow:0 8px 30px rgba(0,0,0,.25); z-index:999; transition:transform .35s cubic-bezier(.22,1,.36,1); white-space:nowrap; }
    .toast.show { transform:translateX(-50%) translateY(0); }

    @keyframes fadeUp { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:translateY(0)} }
    .anim { animation:fadeUp .4s both; }
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
    <h1>Rekap Penilaian Siswa</h1>
    <p class="hero-subtitle">Kelas X-1 | Passing Bawah Bola Voli</p>
    <p>Pantau seluruh aktivitas, progres, dan hasil penilaian siswa dalam satu tampilan.</p>
  </div>
  <img class="hero-img" src="guru_dashboard_hero.png" alt="Guru PJOK"/>
</div>

<div class="content">

  <!-- Session Card -->
  <div class="session-card anim">
    <div class="session-top">
      <h2>Sesi Penilaian Aktif</h2>
      <span class="status-aktif">Aktif</span>
    </div>
    <div class="session-stats">
      <div class="s-stat">
        <div class="s-stat-icon blue"><svg viewBox="0 0 24 24" fill="none"><path d="M2 9a2 2 0 012-2h16a2 2 0 012 2v1a2 2 0 010 4v1a2 2 0 01-2 2H4a2 2 0 01-2-2v-1a2 2 0 010-4V9z" stroke="currentColor" stroke-width="1.8"/></svg></div>
        <div><div class="s-stat-label">Token</div><div class="s-stat-val" style="font-size:13px;letter-spacing:1px;color:var(--blue)">VOLI-X1-482</div></div>
      </div>
      <div class="s-stat">
        <div class="s-stat-icon purple"><svg viewBox="0 0 24 24" fill="none"><circle cx="9" cy="7" r="3" stroke="currentColor" stroke-width="1.8"/><circle cx="16" cy="7" r="3" stroke="currentColor" stroke-width="1.8"/><path d="M2 21c0-3.5 3-6 7-6h4c4 0 7 2.5 7 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></div>
        <div><div class="s-stat-label">Jumlah Siswa</div><div class="s-stat-val">28</div></div>
      </div>
      <div class="s-stat">
        <div class="s-stat-icon yellow"><svg viewBox="0 0 24 24" fill="none"><path d="M12 2l3 6.3 6.9 1-5 4.9 1.2 7-6.1-3.2L5.9 21l1.2-7-5-4.9 6.9-1L12 2z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg></div>
        <div><div class="s-stat-label">Rata-rata Nilai</div><div class="s-stat-val">82</div></div>
      </div>
    </div>
    <div class="session-divider"></div>
    <div class="completion-row">
      <div class="c-item">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="#16A34A" stroke-width="2"/><path d="M9 12l2 2 4-4" stroke="#16A34A" stroke-width="2" stroke-linecap="round"/></svg>
        <div><div class="c-num green">18</div><div class="c-label">lengkap</div></div>
      </div>
      <div class="c-item">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="#D97706" stroke-width="2"/><path d="M12 6v6l4 2" stroke="#D97706" stroke-width="2" stroke-linecap="round"/></svg>
        <div><div class="c-num orange">7</div><div class="c-label">proses</div></div>
      </div>
      <div class="c-item">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="#9CA3AF" stroke-width="2"/><path d="M12 8v4M12 16h.01" stroke="#9CA3AF" stroke-width="2" stroke-linecap="round"/></svg>
        <div><div class="c-num gray">3</div><div class="c-label">belum mulai</div></div>
      </div>
    </div>
  </div>

  <!-- Filter Tabs -->
  <div class="filter-tabs">
    <button class="filter-tab active" onclick="filterTab(this,'semua')">Semua</button>
    <button class="filter-tab" onclick="filterTab(this,'lengkap')">
      <svg viewBox="0 0 24 24" fill="none"><path d="M9 12l2 2 4-4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg> Lengkap
    </button>
    <button class="filter-tab" onclick="filterTab(this,'belum')">
      <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.8"/><path d="M12 6v6l4 2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg> Belum Lengkap
    </button>
    <button class="filter-tab" onclick="filterTab(this,'feedback')">
      <svg viewBox="0 0 24 24" fill="none"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2v10z" stroke="currentColor" stroke-width="1.8"/></svg> Perlu Feedback
    </button>
  </div>

  <!-- Search -->
  <div class="search-wrap">
    <div class="search-input-wrap">
      <span class="search-icon"><svg viewBox="0 0 24 24" fill="none"><circle cx="11" cy="11" r="8" stroke="currentColor" stroke-width="2"/><path d="M21 21l-4.35-4.35" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></span>
      <input class="search-input" type="search" id="student-search" placeholder="Cari nama siswa..." oninput="searchStudents(this.value)"/>
    </div>
    <button class="filter-btn" onclick="showToast('Filter siswa')" aria-label="Filter">
      <svg viewBox="0 0 24 24" fill="none"><line x1="4" y1="6" x2="20" y2="6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><line x1="7" y1="12" x2="17" y2="12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><line x1="10" y1="18" x2="14" y2="18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
    </button>
  </div>

  <!-- Student List -->
  <div class="student-list" id="student-list">

    <!-- Ahmad Fajar -->
    <div class="student-card" data-name="ahmad fajar" data-status="feedback">
      <div class="student-header">
        <div class="student-avatar"><svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="8" r="4" stroke="currentColor" stroke-width="1.8"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></div>
        <div><span class="student-name">Ahmad Fajar</span><span class="badge-hadir">Hadir</span></div>
        <div class="student-score" style="background:#EFF4FF;color:#1A56DB">84</div>
      </div>
      <div class="activity-bar-wrap">
        <div class="activity-bar-label">5 / 6 aktivitas lengkap</div>
        <div class="activity-bar"><div class="activity-bar-fill" style="width:83%"></div></div>
      </div>
      <div class="activity-steps">
        <div class="act-step"><span class="act-step-num">1.</span><span class="act-step-label">Kognitif</span><div class="act-step-icon done"><svg viewBox="0 0 24 24" fill="none"><path d="M9 12l2 2 4-4" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/></svg></div></div>
        <div class="act-step"><span class="act-step-num">2.</span><span class="act-step-label">Upload Video</span><div class="act-step-icon done"><svg viewBox="0 0 24 24" fill="none"><path d="M9 12l2 2 4-4" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/></svg></div></div>
        <div class="act-step"><span class="act-step-num">3.</span><span class="act-step-label">Penilaian Diri</span><div class="act-step-icon done"><svg viewBox="0 0 24 24" fill="none"><path d="M9 12l2 2 4-4" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/></svg></div></div>
        <div class="act-step"><span class="act-step-num">4.</span><span class="act-step-label">Penilaian Rekan</span><div class="act-step-icon done"><svg viewBox="0 0 24 24" fill="none"><path d="M9 12l2 2 4-4" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/></svg></div></div>
        <div class="act-step"><span class="act-step-num">5.</span><span class="act-step-label">Afektif</span><div class="act-step-icon done"><svg viewBox="0 0 24 24" fill="none"><path d="M9 12l2 2 4-4" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/></svg></div></div>
        <div class="act-step"><span class="act-step-num">6.</span><span class="act-step-label">Feedback Guru</span><div class="act-step-icon pending"><svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="1.5" fill="currentColor"/><path d="M12 6v2M12 16v2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></div></div>
      </div>
      <p class="student-status orange">Status akhir: Menunggu feedback guru</p>
      <button class="btn-detail" onclick="window.location='feedback-guru.php'">Lihat Detail <svg viewBox="0 0 24 24" fill="none"><path d="M9 18l6-6-6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></button>
      <div style="clear:both"></div>
    </div>

    <!-- Siti Rahma -->
    <div class="student-card" data-name="siti rahma" data-status="lengkap">
      <div class="student-header">
        <div class="student-avatar" style="background:#FDF2F8"><svg viewBox="0 0 24 24" fill="none" style="color:#DB2777"><circle cx="12" cy="8" r="4" stroke="currentColor" stroke-width="1.8"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></div>
        <div><span class="student-name">Siti Rahma</span><span class="badge-hadir">Hadir</span></div>
        <div class="student-score green">91</div>
      </div>
      <div class="activity-bar-wrap">
        <div class="activity-bar-label">6 / 6 aktivitas lengkap</div>
        <div class="activity-bar"><div class="activity-bar-fill" style="width:100%;background:var(--green)"></div></div>
      </div>
      <div class="activity-steps">
        <div class="act-step"><span class="act-step-num">1.</span><span class="act-step-label">Kognitif</span><div class="act-step-icon done"><svg viewBox="0 0 24 24" fill="none"><path d="M9 12l2 2 4-4" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/></svg></div></div>
        <div class="act-step"><span class="act-step-num">2.</span><span class="act-step-label">Upload Video</span><div class="act-step-icon done"><svg viewBox="0 0 24 24" fill="none"><path d="M9 12l2 2 4-4" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/></svg></div></div>
        <div class="act-step"><span class="act-step-num">3.</span><span class="act-step-label">Penilaian Diri</span><div class="act-step-icon done"><svg viewBox="0 0 24 24" fill="none"><path d="M9 12l2 2 4-4" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/></svg></div></div>
        <div class="act-step"><span class="act-step-num">4.</span><span class="act-step-label">Penilaian Rekan</span><div class="act-step-icon done"><svg viewBox="0 0 24 24" fill="none"><path d="M9 12l2 2 4-4" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/></svg></div></div>
        <div class="act-step"><span class="act-step-num">5.</span><span class="act-step-label">Afektif</span><div class="act-step-icon done"><svg viewBox="0 0 24 24" fill="none"><path d="M9 12l2 2 4-4" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/></svg></div></div>
        <div class="act-step"><span class="act-step-num">6.</span><span class="act-step-label">Feedback Guru</span><div class="act-step-icon done"><svg viewBox="0 0 24 24" fill="none"><path d="M9 12l2 2 4-4" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/></svg></div></div>
      </div>
      <p class="student-status green">Status akhir: Lengkap dan siap direkap</p>
      <button class="btn-detail" onclick="showToast('Detail Siti Rahma')">Lihat Detail <svg viewBox="0 0 24 24" fill="none"><path d="M9 18l6-6-6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></button>
      <div style="clear:both"></div>
    </div>

    <!-- Bima Pratama -->
    <div class="student-card" data-name="bima pratama" data-status="belum">
      <div class="student-header">
        <div class="student-avatar" style="background:#ECFEFF"><svg viewBox="0 0 24 24" fill="none" style="color:#0891B2"><circle cx="12" cy="8" r="4" stroke="currentColor" stroke-width="1.8"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></div>
        <div><span class="student-name">Bima Pratama</span><span class="badge-hadir">Hadir</span></div>
        <div class="student-score orange">78</div>
      </div>
      <div class="activity-bar-wrap">
        <div class="activity-bar-label">3 / 6 aktivitas lengkap</div>
        <div class="activity-bar"><div class="activity-bar-fill" style="width:50%;background:var(--orange)"></div></div>
      </div>
      <div class="activity-steps">
        <div class="act-step"><span class="act-step-num">1.</span><span class="act-step-label">Kognitif</span><div class="act-step-icon done"><svg viewBox="0 0 24 24" fill="none"><path d="M9 12l2 2 4-4" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/></svg></div></div>
        <div class="act-step"><span class="act-step-num">2.</span><span class="act-step-label">Upload Video</span><div class="act-step-icon error"><svg viewBox="0 0 24 24" fill="none"><path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/></svg></div></div>
        <div class="act-step"><span class="act-step-num">3.</span><span class="act-step-label">Penilaian Diri</span><div class="act-step-icon done"><svg viewBox="0 0 24 24" fill="none"><path d="M9 12l2 2 4-4" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/></svg></div></div>
        <div class="act-step"><span class="act-step-num">4.</span><span class="act-step-label">Penilaian Rekan</span><div class="act-step-icon pending"><svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="1.5" fill="currentColor"/></svg></div></div>
        <div class="act-step"><span class="act-step-num">5.</span><span class="act-step-label">Afektif</span><div class="act-step-icon empty"><svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="4" stroke="currentColor" stroke-width="1.5"/></svg></div></div>
        <div class="act-step"><span class="act-step-num">6.</span><span class="act-step-label">Feedback Guru</span><div class="act-step-icon empty"><svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="4" stroke="currentColor" stroke-width="1.5"/></svg></div></div>
      </div>
      <p class="student-status red">Status akhir: Belum unggah video praktik</p>
      <button class="btn-detail" onclick="showToast('Detail Bima Pratama')">Lihat Detail <svg viewBox="0 0 24 24" fill="none"><path d="M9 18l6-6-6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></button>
      <div style="clear:both"></div>
    </div>

  </div>

  <!-- Ringkasan Kelas -->
  <div class="ringkasan-card anim">
    <p class="ringkasan-title">Ringkasan Kelas</p>
    <div class="ringkasan-grid">
      <div class="r-item">
        <div class="r-icon blue"><svg viewBox="0 0 24 24" fill="none"><path d="M12 2C8 2 5 5 5 9c0 2.5 1.3 4.7 3.2 6H12h3.8C17.7 13.7 19 11.5 19 9c0-4-3-7-7-7z" stroke="currentColor" stroke-width="1.8"/><path d="M9 21h6M10 17h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></div>
        <span class="r-label">Tes kognitif selesai</span>
        <span class="r-val">22/28</span>
      </div>
      <div class="r-item">
        <div class="r-icon green"><svg viewBox="0 0 24 24" fill="none"><rect x="2" y="5" width="15" height="14" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M17 9l5-3v12l-5-3V9z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg></div>
        <span class="r-label">Video terkumpul</span>
        <span class="r-val">18/28</span>
      </div>
      <div class="r-item">
        <div class="r-icon purple"><svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="8" r="4" stroke="currentColor" stroke-width="1.8"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></div>
        <span class="r-label">Penilaian diri</span>
        <span class="r-val">14/28</span>
      </div>
      <div class="r-item">
        <div class="r-icon orange"><svg viewBox="0 0 24 24" fill="none"><circle cx="9" cy="7" r="3" stroke="currentColor" stroke-width="1.8"/><circle cx="16" cy="7" r="3" stroke="currentColor" stroke-width="1.8"/><path d="M2 21c0-3.5 3-6 7-6h4c4 0 7 2.5 7 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></div>
        <span class="r-label">Penilaian rekan</span>
        <span class="r-val">12/28</span>
      </div>
      <div class="r-item">
        <div class="r-icon pink"><svg viewBox="0 0 24 24" fill="none"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z" stroke="currentColor" stroke-width="1.8"/></svg></div>
        <span class="r-label">Data afektif masuk</span>
        <span class="r-val">20/28</span>
      </div>
    </div>
  </div>

  <!-- Action Buttons -->
  <div class="action-row">
    <button class="btn-outline btn-secondary" onclick="showToast('Mengunduh rekap...')">
      <svg viewBox="0 0 24 24" fill="none"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><polyline points="7 10 12 15 17 10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><line x1="12" y1="15" x2="12" y2="3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
      Unduh Rekap
    </button>
    <button class="btn-outline btn-primary" onclick="showToast('Kirim feedback ke semua siswa?')">
      <svg viewBox="0 0 24 24" fill="none"><line x1="22" y1="2" x2="11" y2="13" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><polygon points="22 2 15 22 11 13 2 9 22 2" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>
      Kirim Feedback
    </button>
  </div>

</div>

<nav class="bottom-nav">
  <a href="dashboard-guru.php" class="nav-item" aria-label="Beranda">
    <div class="nav-indicator"></div>
    <svg viewBox="0 0 24 24" fill="none"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z" stroke="currentColor" stroke-width="2"/><path d="M9 22V12h6v10" stroke="currentColor" stroke-width="2"/></svg>
    <span>Beranda</span>
  </a>
  <a href="#" class="nav-item" onclick="showToast('Aktivitas'); return false;" aria-label="Aktivitas">
    <div class="nav-indicator"></div>
    <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="8" r="3" stroke="currentColor" stroke-width="2"/><path d="M12 11v4l-2 3h4l-2-3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M6 10l-3 2M18 10l3 2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
    <span>Aktivitas</span>
  </a>
  <a href="#" class="nav-item active" aria-label="Nilai" aria-current="page">
    <div class="nav-indicator"></div>
    <svg viewBox="0 0 24 24" fill="none"><rect x="5" y="2" width="14" height="20" rx="2" stroke="currentColor" stroke-width="2"/><path d="M9 7h6M9 11h6M9 15h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
    <span>Nilai</span>
  </a>
  <a href="#" class="nav-item" onclick="showToast('Profil'); return false;" aria-label="Profil">
    <div class="nav-indicator"></div>
    <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="8" r="4" stroke="currentColor" stroke-width="2"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
    <span>Profil</span>
  </a>
</nav>

<div class="toast" id="toast"></div>
<script>
  function showToast(m){const t=document.getElementById('toast');t.textContent=m;t.classList.add('show');clearTimeout(t._t);t._t=setTimeout(()=>t.classList.remove('show'),3000);}
  function filterTab(el,type){document.querySelectorAll('.filter-tab').forEach(b=>b.classList.remove('active'));el.classList.add('active');const cards=document.querySelectorAll('.student-card');cards.forEach(c=>{if(type==='semua'){c.style.display='block';}else{c.style.display=c.dataset.status===type?'block':'none';}});}
  function searchStudents(v){const q=v.toLowerCase();document.querySelectorAll('.student-card').forEach(c=>{c.style.display=c.dataset.name.includes(q)?'block':'none';});}
</script>
</body>
</html>
