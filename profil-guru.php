<?php
require_once 'auth.php';
wajibLoginGuru();
require 'koneksi.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Profil – PADI-PJOK</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --blue: #1A56DB; --blue-light: #EFF4FF; --text: #111827; --text-3: #6B7280; --border: #E5E7EB;
      --bg: #F3F6FB; --white: #FFFFFF; --radius: 16px; --radius-sm: 10px;
      --shadow: 0 2px 8px rgba(0,0,0,.07); --shadow-md: 0 4px 18px rgba(0,0,0,.10);
      --nav-h: 68px;
    }
    body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; max-width: 480px; margin: 0 auto; padding-bottom: var(--nav-h); }
    .topbar { display: flex; align-items: center; justify-content: space-between; padding: 14px 20px; background: var(--white); border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 50; }
    .back-btn { width: 36px; height: 36px; border-radius: 10px; background: var(--blue-light); border: none; display: flex; align-items: center; justify-content: center; cursor: pointer; }
    .back-btn svg { width: 20px; height: 20px; color: var(--blue); }
    .topbar-title { font-size: 15px; font-weight: 700; }
    .topbar-spacer { width: 36px; }
    .content { padding: 20px 16px; display: flex; flex-direction: column; gap: 16px; text-align: center; }
    .card { background: var(--white); border-radius: var(--radius); padding: 30px 20px; box-shadow: var(--shadow-md); margin-top: 20px; }
    .card h2 { font-size: 18px; margin-bottom: 10px; color: var(--blue); }
    .card p { font-size: 13px; color: var(--text-3); line-height: 1.5; margin-bottom: 20px; }
    /* Nav Bottom */
    .nav-bottom { position: fixed; bottom: 0; left: 50%; transform: translateX(-50%); width: 100%; max-width: 480px; height: var(--nav-h); background: var(--white); border-top: 1px solid var(--border); display: flex; justify-content: space-around; align-items: center; padding: 0 10px; z-index: 50; box-shadow: 0 -4px 20px rgba(0,0,0,.04); }
    .nav-item { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px; color: var(--text-4); text-decoration: none; width: 64px; height: 100%; transition: color .2s; }
    .nav-item:hover { color: var(--blue); }
    .nav-item svg { width: 24px; height: 24px; transition: transform .2s, stroke-width .2s; }
    .nav-item span { font-size: 11px; font-weight: 600; transition: font-weight .2s; }
    .nav-item.active { color: var(--blue); }
    .nav-item.active svg { stroke-width: 2.5; transform: translateY(-2px); }
    .nav-item.active span { font-weight: 700; }
  </style>
</head>
<body>
  <header class="topbar">
    <button class="back-btn" onclick="window.location.href='dashboard-guru.php'">
      <svg viewBox="0 0 24 24" fill="none"><path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </button>
    <div class="topbar-title">Profil</div>
    <div class="topbar-spacer"></div>
  </header>
  <main class="content">
    <div class="card">
      <h2>Profil Pengguna</h2>
      <p>Pengaturan akun, nama guru, dan preferensi aplikasi.</p>
    </div>
  </main>
  
  <nav class="nav-bottom">
    <a href="dashboard-guru.php" class="nav-item" id="nav-beranda" aria-label="Beranda">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
      <span>Beranda</span>
    </a>
    <a href="aktivitas-guru.php" class="nav-item" id="nav-aktivitas" aria-label="Aktivitas">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
      <span>Aktivitas</span>
    </a>
    <a href="nilai-guru.php" class="nav-item" id="nav-nilai" aria-label="Nilai">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
      <span>Nilai</span>
    </a>
    <a href="profil-guru.php" class="nav-item active" id="nav-profil" aria-label="Profil">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
      <span>Profil</span>
    </a>
  </nav>
</body>
</html>
