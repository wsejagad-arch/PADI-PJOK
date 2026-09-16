<?php
require_once 'auth.php';
wajibLoginGuru();
require 'koneksi.php';

$materi_aktif = 0; $sesi_hari_ini = 0; $siswa_terhubung = 0; $penilaian_selesai_pct = 0;
$sesi_aktif = false; $sesi_judul = ""; $sesi_kelas = ""; $sesi_token = ""; $sesi_siswa = 0; $sesi_kog = 0; $sesi_vid = 0;
$kog_pct = 0; $psi_pct = 0; $afe_pct = 0; $diri_pct = 0; $rekan_pct = 0;
$vid_cek = 0; $feed_belum = 0; $lap_siap = 0;

if ($conn) {
    $res = $conn->query("SELECT COUNT(DISTINCT materi) AS c FROM sesi WHERE status='aktif'");
    if($res) $materi_aktif = $res->fetch_assoc()["c"];
    
    $res = $conn->query("SELECT COUNT(*) AS c FROM sesi WHERE DATE(created_at) = CURDATE()");
    if($res) $sesi_hari_ini = $res->fetch_assoc()["c"];
    
    $res = $conn->query("SELECT COUNT(*) AS c FROM sesi WHERE status='aktif'");
    if($res && $res->num_rows > 0) {
        $sesi_res = $conn->query("SELECT * FROM sesi WHERE status='aktif' ORDER BY created_at DESC LIMIT 1");
        if($sesi_res && $row = $sesi_res->fetch_assoc()) {
            $sesi_aktif = true;
            $sesi_judul = $row["materi"];
            $sesi_kelas = $row["kelas"];
            $sesi_token = $row["token"];
            
            $res = $conn->query("SELECT COUNT(*) AS c FROM siswa WHERE sesi_id=".$row["id"]);
            if($res) $sesi_siswa = $res->fetch_assoc()["c"];
            
            $res = $conn->query("SELECT COUNT(*) AS c FROM penilaian_kognitif k JOIN siswa s ON k.siswa_id=s.id WHERE s.sesi_id=".$row["id"]);
            if($res) $sesi_kog = $res->fetch_assoc()["c"];
            
            $res = $conn->query("SELECT COUNT(*) AS c FROM penilaian_psikomotor p JOIN siswa s ON p.siswa_id=s.id WHERE s.sesi_id=".$row["id"]." AND video_path IS NOT NULL");
            if($res) $sesi_vid = $res->fetch_assoc()["c"];
        }
    }
    
    $res = $conn->query("SELECT COUNT(*) AS c FROM siswa");
    $tot_siswa = ($res) ? $res->fetch_assoc()["c"] : 0;
    
    $res = $conn->query("SELECT COUNT(*) AS c FROM siswa s JOIN sesi ON s.sesi_id = sesi.id WHERE sesi.status='aktif'");
    if($res) $siswa_terhubung = $res->fetch_assoc()["c"];
    
    if($tot_siswa > 0) {
        $r = $conn->query("SELECT COUNT(DISTINCT siswa_id) AS c FROM penilaian_kognitif");
        $kog = $r ? $r->fetch_assoc()["c"] : 0;
        
        $r = $conn->query("SELECT COUNT(DISTINCT siswa_id) AS c FROM penilaian_psikomotor WHERE video_path IS NOT NULL");
        $psi = $r ? $r->fetch_assoc()["c"] : 0;
        
        $r = $conn->query("SELECT COUNT(DISTINCT siswa_id) AS c FROM penilaian_afektif");
        $afe = $r ? $r->fetch_assoc()["c"] : 0;
        
        $r = $conn->query("SELECT COUNT(DISTINCT penilai_id) AS c FROM penilaian_rekan");
        $rek = $r ? $r->fetch_assoc()["c"] : 0;
        
        $kog_pct = round(($kog/$tot_siswa)*100);
        $psi_pct = round(($psi/$tot_siswa)*100);
        $afe_pct = round(($afe/$tot_siswa)*100);
        $rekan_pct = round(($rek/$tot_siswa)*100);
        
        $penilaian_selesai_pct = round((($kog+$psi+$afe+$rek)/(4*$tot_siswa))*100);
    }
    
    $r = $conn->query("SELECT COUNT(*) AS c FROM penilaian_psikomotor WHERE video_path IS NOT NULL AND (nilai_rubrik = 0 OR nilai_rubrik IS NULL)");
    if($r) $vid_cek = $r->fetch_assoc()["c"];
    
    $r = $conn->query("SELECT COUNT(*) AS c FROM penilaian_psikomotor WHERE video_path IS NOT NULL AND (feedback_guru IS NULL OR feedback_guru = '')");
    if($r) $feed_belum = $r->fetch_assoc()["c"];
    
    $r = $conn->query("SELECT COUNT(*) AS c FROM sesi WHERE status='selesai'");
    if($r) $lap_siap = $r->fetch_assoc()["c"];
}

$notifs = [];
$unread = 0;
if($conn) {
    $r = $conn->query("SELECT * FROM notifikasi ORDER BY created_at DESC LIMIT 5");
    if($r) {
        while($row = $r->fetch_assoc()){
            $notifs[] = $row;
            if(!$row["is_read"]) $unread++;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Dashboard Guru – PADI-PJOK</title>
  <meta name="description" content="Dashboard guru PADI-PJOK untuk mengelola sesi pembelajaran, token materi, penilaian, dan rekap siswa."/>
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
      --green:       #16A34A;
      --green-light: #DCFCE7;
      --orange:      #EA580C;
      --orange-light:#FFF7ED;
      --yellow:      #D97706;
      --yellow-light:#FFFBEB;
      --purple:      #7C3AED;
      --purple-light:#F5F3FF;
      --pink:        #DB2777;
      --pink-light:  #FDF2F8;
      --teal:        #0891B2;
      --teal-light:  #ECFEFF;
      --text:        #111827;
      --text-2:      #374151;
      --text-3:      #6B7280;
      --text-4:      #9CA3AF;
      --border:      #E5E7EB;
      --bg:          #F3F6FB;
      --white:       #FFFFFF;
      --nav-h:       68px;
      --radius:      14px;
      --radius-sm:   10px;
      --shadow:      0 2px 8px rgba(0,0,0,.07);
      --shadow-md:   0 4px 18px rgba(0,0,0,.10);
    }

    html { scroll-behavior: smooth; }

    body {
      font-family: 'Inter', sans-serif;
      background: var(--bg);
      color: var(--text);
      min-height: 100vh;
      padding-bottom: var(--nav-h);
      max-width: 480px;
      margin: 0 auto;
    }

    /* ─── Topbar ─── */
    .topbar {
      position: sticky;
      top: 0;
      z-index: 50;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 14px 20px;
      background: var(--white);
      border-bottom: 1px solid var(--border);
      box-shadow: var(--shadow);
    }

    .topbar-logo { display: flex; align-items: center; gap: 8px; }
    .topbar-logo svg { width: 28px; height: 28px; }
    .topbar-logo span { font-size: 17px; font-weight: 800; color: var(--blue); letter-spacing: -.3px; }

    .notif-btn {
      position: relative;
      width: 38px; height: 38px;
      background: var(--blue-light);
      border: none; border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      cursor: pointer;
      transition: background .2s;
    }
    .notif-btn:hover { background: var(--blue-mid); }
    .notif-btn svg { width: 20px; height: 20px; color: var(--blue); }
    .notif-dot {
      position: absolute; top: 6px; right: 6px;
      width: 9px; height: 9px;
      background: #EF4444; border-radius: 50%;
      border: 2px solid var(--white);
    }

    /* ─── Scrollable content ─── */
    .content { padding: 0 0 24px; }

    /* ─── Hero section ─── */
    .hero {
      background: linear-gradient(135deg, #EFF6FF 0%, #DBEAFE 60%, #E0F2FE 100%);
      padding: 20px 20px 0;
      display: flex;
      align-items: flex-end;
      gap: 0;
      min-height: 170px;
      position: relative;
      overflow: hidden;
    }

    .hero::before {
      content: '';
      position: absolute;
      inset: 0;
      background: radial-gradient(ellipse at 80% 50%, rgba(26,86,219,.08) 0%, transparent 60%);
    }

    .hero-text { flex: 1; padding-bottom: 24px; z-index: 1; }
    .hero-text h1 { font-size: 24px; font-weight: 800; color: var(--text); margin-bottom: 2px; }
    .hero-text .subtitle { font-size: 14px; font-weight: 700; color: var(--blue); margin-bottom: 6px; }
    .hero-text p { font-size: 12px; color: var(--text-3); line-height: 1.6; max-width: 220px; }

    .hero-img {
      width: 140px;
      height: 160px;
      object-fit: contain;
      object-position: bottom;
      flex-shrink: 0;
      z-index: 1;
      mix-blend-mode: multiply;
    }

    /* ─── Stat cards row ─── */
    .stats-row {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 10px;
      padding: 16px 16px 0;
    }

    .stat-card {
      background: var(--white);
      border-radius: var(--radius-sm);
      padding: 12px 10px;
      box-shadow: var(--shadow);
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 6px;
      text-align: center;
      transition: transform .2s;
      cursor: default;
    }
    .stat-card:hover { transform: translateY(-2px); }

    .stat-icon {
      width: 36px; height: 36px;
      border-radius: 8px;
      display: flex; align-items: center; justify-content: center;
    }
    .stat-icon svg { width: 20px; height: 20px; }
    .stat-icon.blue   { background: var(--blue-light); color: var(--blue); }
    .stat-icon.green  { background: var(--green-light); color: var(--green); }
    .stat-icon.purple { background: var(--purple-light); color: var(--purple); }
    .stat-icon.yellow { background: var(--yellow-light); color: var(--yellow); }

    .stat-label { font-size: 9.5px; font-weight: 600; color: var(--text-3); line-height: 1.3; }
    .stat-value { font-size: 20px; font-weight: 800; color: var(--text); line-height: 1; }
    .stat-value.yellow { color: var(--yellow); }

    /* ─── Section ─── */
    .section { padding: 20px 16px 0; }
    .section-title {
      font-size: 16px; font-weight: 800; color: var(--text);
      margin-bottom: 12px;
    }

    /* ─── Quick Actions grid ─── */
    .actions-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 10px;
    }

    .action-btn {
      background: var(--white);
      border: 1.5px solid var(--border);
      border-radius: var(--radius-sm);
      padding: 14px 10px;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 8px;
      cursor: pointer;
      transition: all .2s;
      text-decoration: none;
    }
    .action-btn:hover {
      border-color: var(--blue);
      background: var(--blue-light);
      transform: translateY(-2px);
      box-shadow: var(--shadow-md);
    }

    .action-icon {
      width: 40px; height: 40px;
      border-radius: 10px;
      display: flex; align-items: center; justify-content: center;
    }
    .action-icon svg { width: 22px; height: 22px; }
    .action-icon.blue   { background: var(--blue-light); color: var(--blue); }
    .action-icon.green  { background: var(--green-light); color: var(--green); }
    .action-icon.purple { background: var(--purple-light); color: var(--purple); }
    .action-icon.orange { background: var(--orange-light); color: var(--orange); }
    .action-icon.teal   { background: var(--teal-light); color: var(--teal); }
    .action-icon.pink   { background: var(--pink-light); color: var(--pink); }

    .action-label { font-size: 11px; font-weight: 600; color: var(--text-2); text-align: center; line-height: 1.3; }

    /* ─── Active session card ─── */
    .session-card {
      background: var(--white);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      overflow: hidden;
    }

    .session-top {
      display: flex;
      gap: 12px;
      padding: 14px;
    }

    .session-img {
      width: 90px; height: 90px;
      border-radius: var(--radius-sm);
      object-fit: cover;
      flex-shrink: 0;
    }

    .session-info { flex: 1; }

    .session-meta-label {
      font-size: 10px; font-weight: 600; color: var(--text-3);
      margin-bottom: 2px;
    }
    .session-title { font-size: 14px; font-weight: 700; color: var(--text); margin-bottom: 8px; }

    .session-meta { display: flex; flex-direction: column; gap: 3px; }
    .meta-row { display: flex; gap: 6px; align-items: center; }
    .meta-key { font-size: 11px; color: var(--text-3); width: 38px; }
    .meta-val { font-size: 11px; font-weight: 600; color: var(--text-2); }

    .session-badges {
      display: flex;
      flex-direction: column;
      align-items: flex-end;
      gap: 8px;
      flex-shrink: 0;
    }

    .badge-aktif {
      background: var(--green-light);
      color: var(--green);
      font-size: 11px;
      font-weight: 700;
      padding: 5px 12px;
      border-radius: 20px;
      border: 1.5px solid #86EFAC;
    }

    .btn-detail {
      display: flex; align-items: center; gap: 4px;
      background: var(--white);
      border: 1.5px solid var(--blue);
      color: var(--blue);
      font-size: 11px; font-weight: 700;
      padding: 6px 12px;
      border-radius: 20px;
      cursor: pointer;
      transition: all .2s;
      white-space: nowrap;
    }
    .btn-detail:hover { background: var(--blue); color: var(--white); }
    .btn-detail svg { width: 13px; height: 13px; }

    .session-stats {
      display: flex;
      border-top: 1px solid var(--border);
      padding: 12px 14px;
      gap: 0;
    }

    .sess-stat {
      flex: 1;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .sess-stat + .sess-stat {
      border-left: 1px solid var(--border);
      padding-left: 12px;
    }

    .sess-stat-icon {
      width: 32px; height: 32px;
      border-radius: 8px;
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
    }
    .sess-stat-icon svg { width: 17px; height: 17px; }
    .sess-stat-icon.blue   { background: var(--blue-light); color: var(--blue); }
    .sess-stat-icon.green  { background: var(--green-light); color: var(--green); }
    .sess-stat-icon.purple { background: var(--purple-light); color: var(--purple); }

    .sess-stat-info { }
    .sess-stat-num { font-size: 16px; font-weight: 800; color: var(--text); line-height: 1; }
    .sess-stat-label { font-size: 9px; font-weight: 500; color: var(--text-3); margin-top: 1px; line-height: 1.3; }

    /* ─── Progress Penilaian ─── */
    .progress-grid {
      display: flex;
      gap: 8px;
      overflow-x: auto;
      padding-bottom: 4px;
      scrollbar-width: none;
    }
    .progress-grid::-webkit-scrollbar { display: none; }

    .progress-card {
      background: var(--white);
      border-radius: var(--radius-sm);
      padding: 12px 14px;
      box-shadow: var(--shadow);
      min-width: 120px;
      flex-shrink: 0;
    }

    .progress-header {
      display: flex; align-items: center; gap: 6px;
      margin-bottom: 8px;
    }
    .progress-header svg { width: 16px; height: 16px; }
    .progress-header span { font-size: 11px; font-weight: 700; color: var(--text-2); }

    .progress-counts { font-size: 11px; color: var(--text-3); margin-bottom: 8px; }
    .progress-counts strong { color: var(--text); font-weight: 700; }

    .progress-bar-wrap {
      width: 100%;
      height: 6px;
      background: var(--border);
      border-radius: 99px;
      overflow: hidden;
      margin-bottom: 4px;
    }
    .progress-bar-fill {
      height: 100%;
      border-radius: 99px;
      transition: width 1.2s cubic-bezier(.22,1,.36,1);
    }
    .progress-pct { font-size: 11px; font-weight: 700; }

    /* ─── Tugas Guru ─── */
    .tugas-list { display: flex; flex-direction: column; gap: 10px; }

    .tugas-item {
      background: var(--white);
      border-radius: var(--radius-sm);
      padding: 14px 16px;
      display: flex;
      align-items: center;
      gap: 14px;
      box-shadow: var(--shadow);
      cursor: pointer;
      transition: all .2s;
      text-decoration: none;
    }
    .tugas-item:hover { transform: translateX(4px); box-shadow: var(--shadow-md); }

    .tugas-icon {
      width: 40px; height: 40px;
      border-radius: 10px;
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
    }
    .tugas-icon svg { width: 22px; height: 22px; }
    .tugas-icon.red    { background: #FEF2F2; color: #DC2626; }
    .tugas-icon.orange { background: var(--orange-light); color: var(--orange); }
    .tugas-icon.green  { background: var(--green-light); color: var(--green); }

    .tugas-info { flex: 1; }
    .tugas-label { font-size: 12px; font-weight: 600; color: var(--text-2); margin-bottom: 2px; }
    .tugas-count { font-size: 22px; font-weight: 800; }
    .tugas-count.red    { color: #DC2626; }
    .tugas-count.orange { color: var(--orange); }
    .tugas-count.green  { color: var(--green); }

    .tugas-arrow { color: var(--text-4); }
    .tugas-arrow svg { width: 16px; height: 16px; }

    /* Tugas 3-col layout */
    .tugas-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 10px;
    }
    .tugas-grid .tugas-item {
      flex-direction: column;
      text-align: center;
      gap: 8px;
      padding: 16px 10px;
    }
    .tugas-grid .tugas-item:hover { transform: translateY(-3px); }
    .tugas-grid .tugas-arrow { display: none; }
    .tugas-grid .tugas-info { width: 100%; }

    /* ─── Abad 21 banner ─── */
    .abad-banner {
      background: linear-gradient(135deg, #EFF6FF 0%, #E0F2FE 100%);
      border: 1.5px solid var(--blue-mid);
      border-radius: var(--radius);
      display: flex;
      align-items: center;
      gap: 0;
      overflow: hidden;
      box-shadow: var(--shadow);
    }

    .abad-content { flex: 1; padding: 16px 14px; }
    .abad-icon {
      width: 36px; height: 36px;
      background: var(--yellow-light);
      border-radius: 10px;
      display: flex; align-items: center; justify-content: center;
      margin-bottom: 8px;
    }
    .abad-icon svg { width: 20px; height: 20px; color: var(--yellow); }
    .abad-title { font-size: 13px; font-weight: 700; color: var(--text); margin-bottom: 4px; }
    .abad-desc { font-size: 11px; color: var(--text-3); line-height: 1.5; }

    .abad-img {
      width: 120px; height: 110px;
      object-fit: cover;
      object-position: center top;
      flex-shrink: 0;
    }

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
      color: var(--text-3);
    }

    .nav-item svg { width: 22px; height: 22px; transition: all .2s; }
    .nav-item span { font-size: 10px; font-weight: 600; transition: color .2s; }

    .nav-item.active { color: var(--blue); }
    .nav-item.active svg { color: var(--blue); }
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

    /* ─── Animations ─── */
    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(20px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    .anim-1 { animation: fadeUp .4s .05s both; }
    .anim-2 { animation: fadeUp .4s .15s both; }
    .anim-3 { animation: fadeUp .4s .25s both; }
    .anim-4 { animation: fadeUp .4s .35s both; }
    .anim-5 { animation: fadeUp .4s .45s both; }
    .anim-6 { animation: fadeUp .4s .55s both; }
    .anim-7 { animation: fadeUp .4s .65s both; }

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

    /* ─── Responsive ─── */
    @media (max-width: 380px) {
      .stats-row { grid-template-columns: repeat(2, 1fr); }
      .stat-value { font-size: 18px; }
    }
  </style>
</head>
<body>

<!-- ─── Topbar ─── -->
<header class="topbar" role="banner">
  <div class="topbar-logo">
    <svg viewBox="0 0 28 28" fill="none" aria-hidden="true">
      <circle cx="18" cy="5" r="3" fill="#1A56DB"/>
      <path d="M6 24L13 14 11 9 17 5" stroke="#1A56DB" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
      <path d="M11 9L18 12L24 9" stroke="#1A56DB" stroke-width="2.2" stroke-linecap="round"/>
      <path d="M18 12L15 20L19 24" stroke="#1A56DB" stroke-width="2.2" stroke-linecap="round"/>
      <path d="M2 18Q9 14 16 17" stroke="#22C55E" stroke-width="2" stroke-linecap="round"/>
    </svg>
    <span>PADI-PJOK</span>
  </div>
  <div style="position: relative; display: flex; gap: 8px; align-items: center;">
    <button class="notif-btn" id="notif-btn" aria-label="Notifikasi" onclick="document.getElementById('notif-dropdown').style.display = document.getElementById('notif-dropdown').style.display === 'none' ? 'block' : 'none'">
      <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        <path d="M13.73 21a2 2 0 01-3.46 0" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      </svg>
      <?php if($unread > 0): ?>
      <span class="notif-dot" aria-label="<?=$unread?> notifikasi baru"></span>
      <?php endif; ?>
    </button>
    <a href="logout.php" aria-label="Keluar" style="display: flex; align-items: center; justify-content: center; width: 38px; height: 38px; border-radius: 50%; background: #FEF2F2; color: #DC2626; text-decoration: none; transition: background .2s;" onmouseover="this.style.background='#FEE2E2'" onmouseout="this.style.background='#FEF2F2'">
      <svg viewBox="0 0 24 24" fill="none" style="width: 20px; height: 20px;" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"></path>
        <polyline points="16 17 21 12 16 7"></polyline>
        <line x1="21" y1="12" x2="9" y2="12"></line>
      </svg>
    </a>
    <div id="notif-dropdown" style="display:none; position: absolute; top: 45px; right: 0; background: var(--white); border: 1px solid var(--border); border-radius: var(--radius-sm); box-shadow: var(--shadow-md); width: 250px; z-index: 100; text-align: left;">
      <div style="padding: 10px; border-bottom: 1px solid var(--border); font-weight: bold; font-size: 12px; color: var(--text);">Notifikasi Terbaru</div>
      <div style="max-height: 200px; overflow-y: auto;">
        <?php if(empty($notifs)): ?>
          <div style="padding: 10px; font-size: 11px; color: var(--text-3); text-align: center;">Belum ada notifikasi</div>
        <?php else: ?>
          <?php foreach($notifs as $n): ?>
            <div style="padding: 10px; border-bottom: 1px solid var(--border); font-size: 11px; color: var(--text-2); <?php if(!$n['is_read']) echo 'background: var(--blue-light);'; ?>">
              <?= htmlspecialchars($n['pesan']) ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</header>

<main class="content" id="beranda-panel">

  <!-- ─── Hero ─── -->
  <div class="hero anim-1">
    <div class="hero-text">
      <h1>Dashboard Guru</h1>
      <p class="subtitle">Selamat datang Guru PJOK</p>
      <p>Kelola sesi pembelajaran, token materi, penilaian, dan rekap siswa secara terintegrasi.</p>
    </div>
    <img class="hero-img" src="guru_dashboard_hero.png" alt="Guru PJOK dengan tablet" />
  </div>

  <!-- ─── Stat Cards ─── -->
  <div class="stats-row anim-2" role="region" aria-label="Statistik hari ini">
    <div class="stat-card">
      <div class="stat-icon blue" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none">
          <rect x="5" y="2" width="14" height="20" rx="2" stroke="currentColor" stroke-width="1.8"/>
          <path d="M9 7h6M9 11h6M9 15h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
        </svg>
      </div>
      <span class="stat-label">Materi Aktif</span>
      <span class="stat-value"><?= $materi_aktif ?></span>
    </div>
    <div class="stat-card">
      <div class="stat-icon green" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none">
          <rect x="3" y="4" width="18" height="18" rx="2" stroke="currentColor" stroke-width="1.8"/>
          <path d="M16 2v4M8 2v4M3 10h18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
          <path d="M8 14h2v2H8z" fill="currentColor"/>
          <path d="M14 14h2v2h-2z" fill="currentColor"/>
        </svg>
      </div>
      <span class="stat-label">Sesi Hari Ini</span>
      <span class="stat-value"><?= $sesi_hari_ini ?></span>
    </div>
    <div class="stat-card">
      <div class="stat-icon purple" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none">
          <circle cx="9" cy="7" r="3" stroke="currentColor" stroke-width="1.8"/>
          <circle cx="16" cy="7" r="3" stroke="currentColor" stroke-width="1.8"/>
          <path d="M2 21c0-3.5 3-6 7-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
          <path d="M22 21c0-3.5-3-6-7-6h-2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
        </svg>
      </div>
      <span class="stat-label">Siswa Terhubung</span>
      <span class="stat-value"><?= $siswa_terhubung ?></span>
    </div>
    <div class="stat-card">
      <div class="stat-icon yellow" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none">
          <path d="M12 2l3 6.3 6.9 1-5 4.9 1.2 7-6.1-3.2L5.9 21l1.2-7-5-4.9 6.9-1L12 2z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
        </svg>
      </div>
      <span class="stat-label">Penilaian Selesai</span>
      <span class="stat-value yellow"><?= $penilaian_selesai_pct ?>%</span>
    </div>
  </div>

  <!-- ─── Aksi Cepat ─── -->
  <div class="section anim-3">
    <h2 class="section-title">Aksi Cepat</h2>
    <div class="actions-grid">
      <a href="buat-token.php" class="action-btn" id="btn-token">
        <div class="action-icon blue" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none">
            <rect x="3" y="3" width="8" height="8" rx="2" stroke="currentColor" stroke-width="1.8"/>
            <rect x="13" y="3" width="8" height="8" rx="2" stroke="currentColor" stroke-width="1.8"/>
            <rect x="3" y="13" width="8" height="8" rx="2" stroke="currentColor" stroke-width="1.8"/>
            <path d="M17 13v8M13 17h8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
          </svg>
        </div>
        <span class="action-label">Buat Token Materi</span>
      </a>
      <a href="mulai-sesi.php" class="action-btn" id="btn-sesi">
        <div class="action-icon green" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none">
            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.8"/>
            <path d="M10 8l6 4-6 4V8z" fill="currentColor"/>
          </svg>
        </div>
        <span class="action-label">Mulai Sesi</span>
      </a>
      <a href="pantau-siswa.php" class="action-btn" id="btn-pantau">
        <div class="action-icon purple" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none">
            <circle cx="9" cy="7" r="4" stroke="currentColor" stroke-width="1.8"/>
            <path d="M3 21v-2a4 4 0 014-4h4a4 4 0 014 4v2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            <path d="M19 8v6M22 11h-6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </div>
        <span class="action-label">Pantau Siswa</span>
      </a>
      <a href="rekap-penilaian.php" class="action-btn" id="btn-rekap">
        <div class="action-icon orange" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none">
            <path d="M18 20V10M12 20V4M6 20v-6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </div>
        <span class="action-label">Rekap Nilai</span>
      </a>
      <a href="laporan.php" class="action-btn" id="btn-laporan">
        <div class="action-icon teal" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none">
            <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z" stroke="currentColor" stroke-width="1.8"/>
            <path d="M14 2v6h6M16 13H8M16 17H8M10 9H8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
          </svg>
        </div>
        <span class="action-label">Laporan</span>
      </a>
      <a href="rubrik-platform.php" class="action-btn" id="btn-rubrik">
        <div class="action-icon pink" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none">
            <rect x="5" y="2" width="14" height="20" rx="2" stroke="currentColor" stroke-width="1.8"/>
            <path d="M9 7h6M9 11h6M9 15h6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            <path d="M7 7h.01M7 11h.01M7 15h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </div>
        <span class="action-label">Rubrik Platform</span>
      </a>
      <a href="data-siswa.php" class="action-btn" id="btn-data-siswa">
        <div class="action-icon teal" aria-hidden="true" style="background: var(--blue-light); color: var(--blue);">
          <svg viewBox="0 0 24 24" fill="none">
            <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            <circle cx="9" cy="7" r="4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            <path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </div>
        <span class="action-label">Data Siswa</span>
      </a>
    </div>
  </div>

  <!-- ─── Sesi Aktif ─── -->
  <div class="section anim-4">
    <h2 class="section-title">Sesi Aktif Hari Ini</h2>
    <?php if($sesi_aktif): ?>
    <div class="session-card">
      <div class="session-top">
        <img class="session-img" src="volleyball_session.png" alt="Bola voli" style="mix-blend-mode: multiply;" />
        <div class="session-info">
          <p class="session-meta-label">Materi</p>
          <p class="session-title"><?= htmlspecialchars($sesi_judul) ?></p>
          <div class="session-meta">
            <div class="meta-row">
              <span class="meta-key">Kelas</span>
              <span class="meta-val"><?= htmlspecialchars($sesi_kelas) ?></span>
            </div>
            <div class="meta-row">
              <span class="meta-key">Token</span>
              <span class="meta-val" style="color:var(--blue);letter-spacing:1px;"><?= htmlspecialchars($sesi_token) ?></span>
            </div>
          </div>
        </div>
        <div class="session-badges">
          <span class="badge-aktif">Sesi Aktif</span>
          <button class="btn-detail" onclick="window.location.href='pantau-siswa.php'">
            Lihat Detail
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path d="M9 18l6-6-6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
          </button>
        </div>
      </div>
      <div class="session-stats" role="region" aria-label="Statistik sesi aktif">
        <div class="sess-stat">
          <div class="sess-stat-icon blue" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none">
              <circle cx="9" cy="7" r="3" stroke="currentColor" stroke-width="1.8"/>
              <circle cx="16" cy="7" r="3" stroke="currentColor" stroke-width="1.8"/>
              <path d="M2 21c0-3.5 3-6 7-6h4c4 0 7 2.5 7 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            </svg>
          </div>
          <div class="sess-stat-info">
            <div class="sess-stat-num"><?= $sesi_siswa ?></div>
            <div class="sess-stat-label">siswa terhubung</div>
          </div>
        </div>
        <div class="sess-stat">
          <div class="sess-stat-icon green" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none">
              <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.8"/>
              <path d="M9 12l2 2 4-4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </div>
          <div class="sess-stat-info">
            <div class="sess-stat-num"><?= $sesi_kog ?></div>
            <div class="sess-stat-label">tes kognitif selesai</div>
          </div>
        </div>
        <div class="sess-stat">
          <div class="sess-stat-icon purple" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none">
              <rect x="2" y="4" width="20" height="16" rx="2" stroke="currentColor" stroke-width="1.8"/>
              <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.8"/>
              <path d="M2 8l10 6 10-6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
            </svg>
          </div>
          <div class="sess-stat-info">
            <div class="sess-stat-num"><?= $sesi_vid ?></div>
            <div class="sess-stat-label">video praktik diunggah</div>
          </div>
        </div>
      </div>
    </div>
    <?php else: ?>
    <div style="background: var(--white); padding: 20px; border-radius: var(--radius); text-align: center; color: var(--text-3); font-size: 13px;">Belum ada sesi aktif. Klik <a href="mulai-sesi.php" style="color: var(--blue);">Mulai Sesi</a> untuk membuat sesi.</div>
    <?php endif; ?>
  </div>
        <div class="session-badges">
          <span class="badge-aktif">Sesi Aktif</span>
          <button class="btn-detail" onclick="showToast('Membuka detail sesi...')">
            Lihat Detail
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path d="M9 18l6-6-6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
          </button>
        </div>
      </div>
      <div class="session-stats" role="region" aria-label="Statistik sesi aktif">
        <div class="sess-stat">
          <div class="sess-stat-icon blue" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none">
              <circle cx="9" cy="7" r="3" stroke="currentColor" stroke-width="1.8"/>
              <circle cx="16" cy="7" r="3" stroke="currentColor" stroke-width="1.8"/>
              <path d="M2 21c0-3.5 3-6 7-6h4c4 0 7 2.5 7 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            </svg>
          </div>
          <div class="sess-stat-info">
            <div class="sess-stat-num">28</div>
            <div class="sess-stat-label">siswa terhubung</div>
          </div>
        </div>
        <div class="sess-stat">
          <div class="sess-stat-icon green" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none">
              <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.8"/>
              <path d="M9 12l2 2 4-4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </div>
          <div class="sess-stat-info">
            <div class="sess-stat-num">22</div>
            <div class="sess-stat-label">tes kognitif selesai</div>
          </div>
        </div>
        <div class="sess-stat">
          <div class="sess-stat-icon purple" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none">
              <rect x="2" y="4" width="20" height="16" rx="2" stroke="currentColor" stroke-width="1.8"/>
              <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.8"/>
              <path d="M2 8l10 6 10-6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
            </svg>
          </div>
          <div class="sess-stat-info">
            <div class="sess-stat-num">18</div>
            <div class="sess-stat-label">video praktik diunggah</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ─── Progress Penilaian ─── -->
  <div class="section anim-5">
    <h2 class="section-title">Progress Penilaian</h2>
    <div class="progress-grid" role="region" aria-label="Progress penilaian siswa">

      <!-- Kognitif -->
      <div class="progress-card">
        <div class="progress-header" style="color:var(--blue)">
          <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M12 2C8 2 5 5 5 9c0 2.5 1.3 4.7 3.2 6H12h3.8C17.7 13.7 19 11.5 19 9c0-4-3-7-7-7z" stroke="currentColor" stroke-width="1.8"/>
            <path d="M9 21h6M10 17h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
          </svg>
          <span>Kognitif</span>
        </div>
        <p class="progress-counts"><strong><?= $tot_siswa > 0 ? round(($kog_pct/100)*$tot_siswa) : 0 ?></strong>/<?= $tot_siswa ?> siswa selesai</p>
        <div class="progress-bar-wrap">
          <div class="progress-bar-fill" style="width:<?= $kog_pct ?>%;background:var(--blue);" data-width="<?= $kog_pct ?>"></div>
        </div>
        <span class="progress-pct" style="color:var(--blue)"><?= $kog_pct ?>%</span>
      </div>

      <!-- Psikomotor -->
      <div class="progress-card">
        <div class="progress-header" style="color:var(--green)">
          <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle cx="12" cy="5" r="2.5" stroke="currentColor" stroke-width="1.8"/>
            <path d="M12 8v5l-3 4M12 13l3 4M8 10l-3 2M16 10l3 2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
          </svg>
          <span>Psikomotor</span>
        </div>
        <p class="progress-counts"><strong><?= $tot_siswa > 0 ? round(($psi_pct/100)*$tot_siswa) : 0 ?></strong>/<?= $tot_siswa ?> video terkumpul</p>
        <div class="progress-bar-wrap">
          <div class="progress-bar-fill" style="width:<?= $psi_pct ?>%;background:var(--green);" data-width="<?= $psi_pct ?>"></div>
        </div>
        <span class="progress-pct" style="color:var(--green)"><?= $psi_pct ?>%</span>
      </div>

      <!-- Afektif -->
      <div class="progress-card">
        <div class="progress-header" style="color:var(--pink)">
          <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
          </svg>
          <span>Afektif</span>
        </div>
        <p class="progress-counts"><strong><?= $tot_siswa > 0 ? round(($afe_pct/100)*$tot_siswa) : 0 ?></strong>/<?= $tot_siswa ?> data masuk</p>
        <div class="progress-bar-wrap">
          <div class="progress-bar-fill" style="width:<?= $afe_pct ?>%;background:var(--pink);" data-width="<?= $afe_pct ?>"></div>
        </div>
        <span class="progress-pct" style="color:var(--pink)"><?= $afe_pct ?>%</span>
      </div>

      <!-- Penilaian Diri -->
      <div class="progress-card">
        <div class="progress-header" style="color:var(--orange)">
          <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle cx="12" cy="8" r="4" stroke="currentColor" stroke-width="1.8"/>
            <path d="M4 20c0-4 3.6-7 8-7s8 3 8 7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            <path d="M16 4l2 2-2 2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
          </svg>
          <span>Penilaian Diri</span>
        </div>
        <p class="progress-counts"><strong><?= $tot_siswa > 0 ? round(($diri_pct/100)*$tot_siswa) : 0 ?></strong> siswa selesai</p>
        <div class="progress-bar-wrap">
          <div class="progress-bar-fill" style="width:<?= $diri_pct ?>%;background:var(--orange);" data-width="<?= $diri_pct ?>"></div>
        </div>
        <span class="progress-pct" style="color:var(--orange)"><?= $diri_pct ?>%</span>
      </div>

      <!-- Penilaian Rekan -->
      <div class="progress-card">
        <div class="progress-header" style="color:var(--purple)">
          <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle cx="8" cy="7" r="3" stroke="currentColor" stroke-width="1.8"/>
            <circle cx="16" cy="7" r="3" stroke="currentColor" stroke-width="1.8"/>
            <path d="M2 21c0-3.5 2.7-5.5 6-6M22 21c0-3.5-2.7-5.5-6-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            <path d="M8 15c1.2-.3 2.5-.5 4-.5s2.8.2 4 .5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
          </svg>
          <span>Penilaian Rekan</span>
        </div>
        <p class="progress-counts"><strong><?= $tot_siswa > 0 ? round(($rekan_pct/100)*$tot_siswa) : 0 ?></strong> siswa selesai</p>
        <div class="progress-bar-wrap">
          <div class="progress-bar-fill" style="width:<?= $rekan_pct ?>%;background:var(--purple);" data-width="<?= $rekan_pct ?>"></div>
        </div>
        <span class="progress-pct" style="color:var(--purple)"><?= $rekan_pct ?>%</span>
      </div>

    </div>
  </div>

  <!-- ─── Tugas Guru ─── -->
  <div class="section anim-6">
    <h2 class="section-title">Tugas Guru</h2>
    <div class="tugas-grid">
      <a href="penilaian-psikomotor.php" class="tugas-item" id="tugas-video">
        <div class="tugas-icon red" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none">
            <rect x="2" y="5" width="15" height="14" rx="2" stroke="currentColor" stroke-width="1.8"/>
            <path d="M17 9l5-3v12l-5-3V9z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
          </svg>
        </div>
        <div class="tugas-info">
          <p class="tugas-label">Video perlu dicek</p>
          <p class="tugas-count red"><?= $vid_cek ?></p>
        </div>
        <span class="tugas-arrow" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none"><path d="M9 18l6-6-6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        </span>
      </a>
      <a href="penilaian-psikomotor.php" class="tugas-item" id="tugas-feedback">
        <div class="tugas-icon orange" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none">
            <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2v10z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
          </svg>
        </div>
        <div class="tugas-info">
          <p class="tugas-label">Feedback belum diberikan</p>
          <p class="tugas-count orange"><?= $feed_belum ?></p>
        </div>
        <span class="tugas-arrow" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none"><path d="M9 18l6-6-6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        </span>
      </a>
      <a href="laporan.php" class="tugas-item" id="tugas-laporan">
        <div class="tugas-icon green" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none">
            <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z" stroke="currentColor" stroke-width="1.8"/>
            <path d="M14 2v6h6M12 18v-6M9 15l3 3 3-3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </div>
        <div class="tugas-info">
          <p class="tugas-label">Laporan siap diunduh</p>
          <p class="tugas-count green"><?= $lap_siap ?></p>
        </div>
        <span class="tugas-arrow" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none"><path d="M9 18l6-6-6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        </span>
      </a>
    </div>
  </div>

  <!-- ─── Abad 21 Banner ─── -->
  <div class="section anim-7" style="padding-bottom:0;">
    <div class="abad-banner">
      <div class="abad-content">
        <div class="abad-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none">
            <path d="M8 21l4-18 4 18M4 9h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </div>
        <p class="abad-title">PADI-PJOK mendukung Pembelajaran Abad 21</p>
        <p class="abad-desc">Berpikir kritis, Kolaborasi, Komunikasi, dan Refleksi untuk mewujudkan pembelajaran PJOK yang bermakna.</p>
      </div>
      <img class="abad-img" src="team_abad21.png" alt="Tim siswa PJOK belajar bersama" />
    </div>
  </div>

</main>

<!-- ─── Bottom Navigation ─── -->
<nav class="bottom-nav" role="navigation" aria-label="Navigasi utama">
  <a href="dashboard-guru.php" class="nav-item active" id="nav-beranda" aria-label="Beranda" aria-current="page">
    <div class="nav-indicator"></div>
    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
      <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
      <path d="M9 22V12h6v10" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
    </svg>
    <span>Beranda</span>
  </a>
  <a href="aktivitas-guru.php" class="nav-item" id="nav-aktivitas" aria-label="Aktivitas">
    <div class="nav-indicator"></div>
    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
      <circle cx="12" cy="8" r="3" stroke="currentColor" stroke-width="2"/>
      <path d="M12 11v4l-2 3h4l-2-3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
      <path d="M6 10l-3 2M18 10l3 2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
    </svg>
    <span>Aktivitas</span>
  </a>
  <a href="nilai-guru.php" class="nav-item" id="nav-nilai" aria-label="Nilai">
    <div class="nav-indicator"></div>
    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
      <rect x="5" y="2" width="14" height="20" rx="2" stroke="currentColor" stroke-width="2"/>
      <path d="M9 7h6M9 11h6M9 15h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
    </svg>
    <span>Nilai</span>
  </a>
  <a href="profil-guru.php" class="nav-item" id="nav-profil" aria-label="Profil">
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
  /* ── Bottom nav ── */
  function setNav(id) {
    document.querySelectorAll('.nav-item').forEach(el => {
      el.classList.remove('active');
      el.removeAttribute('aria-current');
    });
    const target = document.getElementById('nav-' + id);
    if (target) {
      target.classList.add('active');
      target.setAttribute('aria-current', 'page');
    }
    if (id !== 'beranda') showToast('Halaman ' + id.charAt(0).toUpperCase() + id.slice(1) + ' (segera hadir)');
  }

  /* ── Toast ── */
  function showToast(msg) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.classList.add('show');
    clearTimeout(t._timer);
    t._timer = setTimeout(() => t.classList.remove('show'), 3000);
  }

  /* ── Animate progress bars on scroll ── */
  const bars = document.querySelectorAll('.progress-bar-fill');
  const stored = {};
  bars.forEach(bar => {
    stored[bar] = bar.style.width;
    bar.style.width = '0%';
  });

  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        const bar = entry.target;
        bar.style.width = stored[bar];
        observer.unobserve(bar);
      }
    });
  }, { threshold: 0.3 });

  bars.forEach(bar => observer.observe(bar));
</script>

</body>
</html>
