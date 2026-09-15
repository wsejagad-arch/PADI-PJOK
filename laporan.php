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
  <title>Laporan – PADI-PJOK</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --teal: #0891B2; --teal-light: #ECFEFF; --text: #111827; --text-3: #6B7280; --border: #E5E7EB;
      --bg: #F3F6FB; --white: #FFFFFF; --radius: 16px; --radius-sm: 10px;
      --shadow: 0 2px 8px rgba(0,0,0,.07); --shadow-md: 0 4px 18px rgba(0,0,0,.10);
    }
    body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; max-width: 480px; margin: 0 auto; }
    .topbar { display: flex; align-items: center; justify-content: space-between; padding: 14px 20px; background: var(--white); border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 50; }
    .back-btn { width: 36px; height: 36px; border-radius: 10px; background: var(--teal-light); border: none; display: flex; align-items: center; justify-content: center; cursor: pointer; }
    .back-btn svg { width: 20px; height: 20px; color: var(--teal); }
    .topbar-title { font-size: 15px; font-weight: 700; }
    .topbar-spacer { width: 36px; }
    .content { padding: 20px 16px; display: flex; flex-direction: column; gap: 16px; text-align: center; }
    .card { background: var(--white); border-radius: var(--radius); padding: 30px 20px; box-shadow: var(--shadow-md); margin-top: 20px; }
    .card h2 { font-size: 18px; margin-bottom: 10px; color: var(--teal); }
    .card p { font-size: 13px; color: var(--text-3); line-height: 1.5; margin-bottom: 20px; }
    .btn-primary { background: var(--teal); color: var(--white); border: none; padding: 12px 20px; border-radius: var(--radius-sm); font-weight: 600; cursor: pointer; width: 100%; font-size: 14px; }
  </style>
</head>
<body>
  <header class="topbar">
    <button class="back-btn" onclick="window.location.href='dashboard-guru.php'">
      <svg viewBox="0 0 24 24" fill="none"><path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </button>
    <div class="topbar-title">Laporan</div>
    <div class="topbar-spacer"></div>
  </header>
  <main class="content">
    <div class="card">
      <h2>Unduh Laporan Kelas</h2>
      <p>Sesi yang sudah diselesaikan akan tampil di sini untuk diekspor ke dalam format PDF maupun Excel.</p>
      <button class="btn-primary" onclick="alert('Fitur ekspor akan segera tersedia!')">Unduh Contoh Laporan</button>
    </div>
  </main>
</body>
</html>
