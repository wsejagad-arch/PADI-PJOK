<?php
require_once 'auth.php';
wajibLoginGuru();
require 'koneksi.php';

// Ambil semua sesi aktif dari database
$sql = "SELECT * FROM sesi WHERE status='aktif' ORDER BY created_at DESC";
$result = $conn->query($sql);
$sesi_aktif = [];
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $sesi_aktif[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Mulai Sesi – PADI-PJOK</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --blue: #1A56DB; --blue-dark: #1240A8; --blue-light: #EFF4FF; --blue-mid: #DBEAFE;
      --text: #111827; --text-2: #374151; --text-3: #6B7280; --border: #E5E7EB;
      --bg: #F3F6FB; --white: #FFFFFF; --radius: 16px; --radius-sm: 10px;
      --shadow: 0 2px 8px rgba(0,0,0,.07); --shadow-md: 0 4px 18px rgba(0,0,0,.10);
    }
    body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; max-width: 480px; margin: 0 auto; }
    .topbar { display: flex; align-items: center; justify-content: space-between; padding: 14px 20px; background: var(--white); border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 50; }
    .back-btn { width: 36px; height: 36px; border-radius: 10px; background: var(--blue-light); border: none; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: background .2s; }
    .back-btn:hover { background: var(--blue-mid); }
    .back-btn svg { width: 20px; height: 20px; color: var(--blue); }
    .topbar-title { font-size: 15px; font-weight: 700; }
    .topbar-spacer { width: 36px; }
    
    .content { padding: 20px 16px; display: flex; flex-direction: column; gap: 16px; }
    .header-section { margin-bottom: 10px; }
    .header-section h2 { font-size: 20px; color: var(--blue); font-weight: 800; margin-bottom: 6px; }
    .header-section p { font-size: 13px; color: var(--text-3); line-height: 1.5; }
    
    .card { background: var(--white); border-radius: var(--radius); padding: 20px; box-shadow: var(--shadow-md); position: relative; overflow: hidden; display: flex; flex-direction: column; gap: 12px; }
    .card::before { content: ''; position: absolute; top: 0; left: 0; width: 6px; height: 100%; background: var(--blue); }
    
    .card-header { display: flex; justify-content: space-between; align-items: flex-start; }
    .kelas-badge { font-size: 11px; font-weight: 700; color: var(--blue); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; }
    .materi-title { font-size: 16px; color: var(--text); font-weight: 700; margin: 0; line-height: 1.3; }
    .token-badge { background: var(--blue-light); color: var(--blue); padding: 6px 12px; border-radius: 20px; font-size: 13px; font-weight: 700; letter-spacing: 1.5px; border: 1px dashed var(--blue-mid); }
    
    .meta-info { font-size: 12px; color: var(--text-3); display: flex; align-items: center; gap: 6px; }
    
    .btn-primary { background: var(--blue); color: var(--white); border: none; padding: 14px 20px; border-radius: var(--radius-sm); font-weight: 600; cursor: pointer; width: 100%; font-size: 14px; display: flex; justify-content: center; align-items: center; gap: 8px; transition: opacity .2s; margin-top: 8px; }
    .btn-primary:hover { opacity: 0.9; }
    
    .empty-state { text-align: center; padding: 40px 20px; }
    .empty-state::before { display: none; }
    .empty-icon { width: 64px; height: 64px; background: var(--blue-light); border-radius: 50%; display: flex; justify-content: center; align-items: center; margin: 0 auto 16px; }
    .empty-state h2 { font-size: 18px; color: var(--text); margin-bottom: 8px; font-weight: 700; }
    .empty-state p { font-size: 13px; color: var(--text-3); margin-bottom: 24px; line-height: 1.5; }
  </style>
</head>
<body>
  <header class="topbar">
    <button class="back-btn" onclick="window.location.href='dashboard-guru.php'">
      <svg viewBox="0 0 24 24" fill="none"><path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </button>
    <div class="topbar-title">Mulai Sesi</div>
    <div class="topbar-spacer"></div>
  </header>
  <main class="content">
    <div class="header-section">
      <h2>Pilih Sesi Kelas</h2>
      <p>Pilih sesi kelas yang telah Anda buat tokennya untuk mulai memantau progres siswa secara langsung.</p>
    </div>

    <?php if (count($sesi_aktif) > 0): ?>
      <div style="display: flex; flex-direction: column; gap: 16px;">
        <?php foreach ($sesi_aktif as $sesi): ?>
          <div class="card">
            <div class="card-header">
              <div>
                <div class="kelas-badge">Kelas <?= htmlspecialchars($sesi['kelas']) ?></div>
                <h3 class="materi-title"><?= htmlspecialchars($sesi['materi']) ?></h3>
              </div>
              <div class="token-badge"><?= htmlspecialchars($sesi['token']) ?></div>
            </div>
            
            <div class="meta-info">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
              Dibuat: <?= date('d M Y, H:i', strtotime($sesi['created_at'])) ?>
            </div>
            
            <button class="btn-primary" onclick="window.location.href='pantau-siswa.php?id_sesi=<?= $sesi['id'] ?>'">
              Masuk & Pantau
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
            </button>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="card empty-state">
        <div class="empty-icon">
          <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="var(--blue)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
            <line x1="9" y1="3" x2="9" y2="21"/>
          </svg>
        </div>
        <h2>Belum ada sesi aktif</h2>
        <p>Anda belum membuat sesi atau token materi apa pun. Silakan buat token terlebih dahulu agar siswa bisa bergabung.</p>
        <button class="btn-primary" onclick="window.location.href='buat-token.php'" style="margin-top: 0;">Buat Token Sekarang</button>
      </div>
    <?php endif; ?>
  </main>
</body>
</html>
