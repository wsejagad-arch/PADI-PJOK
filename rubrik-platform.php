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
  <title>Rubrik Platform – PADI-PJOK</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --pink: #DB2777; --pink-light: #FDF2F8; --text: #111827; --text-3: #6B7280; --border: #E5E7EB;
      --bg: #F3F6FB; --white: #FFFFFF; --radius: 16px; --radius-sm: 10px;
      --shadow: 0 2px 8px rgba(0,0,0,.07); --shadow-md: 0 4px 18px rgba(0,0,0,.10);
    }
    body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; max-width: 480px; margin: 0 auto; }
    .topbar { display: flex; align-items: center; justify-content: space-between; padding: 14px 20px; background: var(--white); border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 50; }
    .back-btn { width: 36px; height: 36px; border-radius: 10px; background: var(--pink-light); border: none; display: flex; align-items: center; justify-content: center; cursor: pointer; }
    .back-btn svg { width: 20px; height: 20px; color: var(--pink); }
    .topbar-title { font-size: 15px; font-weight: 700; }
    .topbar-spacer { width: 36px; }
    .content { padding: 20px 16px; display: flex; flex-direction: column; gap: 16px; text-align: center; }
    .card { background: var(--white); border-radius: var(--radius); padding: 30px 20px; box-shadow: var(--shadow-md); margin-top: 20px; }
    .card h2 { font-size: 18px; margin-bottom: 10px; color: var(--pink); }
    .card p { font-size: 13px; color: var(--text-3); line-height: 1.5; margin-bottom: 20px; }
  </style>
</head>
<body>
  <header class="topbar">
    <button class="back-btn" onclick="window.location.href='dashboard-guru.php'">
      <svg viewBox="0 0 24 24" fill="none"><path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </button>
    <div class="topbar-title">Rubrik Platform</div>
    <div class="topbar-spacer"></div>
  </header>
  <main class="content">
    <div class="card">
      <h2>Rubrik Penilaian PJOK</h2>
      <p>Lihat standar dan kriteria rubrik penilaian yang digunakan untuk kognitif, afektif, dan psikomotor.</p>
      <div style="padding: 15px; border-radius: var(--radius-sm); background: var(--pink-light); color: var(--pink); font-size: 13px; font-weight: 600; text-align: left; margin-top: 10px;">
        1. Rubrik Bola Voli (Passing Bawah)
      </div>
      <div style="padding: 15px; border-radius: var(--radius-sm); background: var(--pink-light); color: var(--pink); font-size: 13px; font-weight: 600; text-align: left; margin-top: 10px;">
        2. Rubrik Bola Basket (Dribbling)
      </div>
    </div>
  </main>
</body>
</html>
