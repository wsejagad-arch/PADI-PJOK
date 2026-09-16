<?php
require_once 'auth.php';
wajibLoginSiswa();
require_once 'koneksi.php';

$siswa_id = $_SESSION['siswa_id'] ?? 0;

// Handle Save Video Link
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_video_link') {
    header('Content-Type: application/json');
    $link = trim($_POST['link'] ?? '');
    
    // Konversi YouTube URL ke versi Embed
    if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/i', $link, $matches)) {
        $link = 'https://www.youtube.com/embed/' . $matches[1];
    }
    
    $cek = $conn->prepare("SELECT id FROM penilaian_psikomotor WHERE siswa_id = ?");
    $cek->bind_param("i", $siswa_id);
    $cek->execute();
    $res = $cek->get_result();
    
    if ($res->num_rows > 0) {
        $upd = $conn->prepare("UPDATE penilaian_psikomotor SET video_path = ? WHERE siswa_id = ?");
        $upd->bind_param("si", $link, $siswa_id);
        $upd->execute();
    } else {
        $ins = $conn->prepare("INSERT INTO penilaian_psikomotor (siswa_id, video_path) VALUES (?, ?)");
        $ins->bind_param("is", $siswa_id, $link);
        $ins->execute();
    }
    echo json_encode(['success' => true, 'embed_link' => $link]);
    exit;
}

// Check Self Assessment (Afektif)
$cek_self = $conn->prepare("SELECT id FROM penilaian_afektif WHERE siswa_id = ?");
$cek_self->bind_param("i", $siswa_id);
$cek_self->execute();
$has_self = $cek_self->get_result()->num_rows > 0;
$cek_self->close();

// Check Peer Assessment (Rekan)
$cek_peer = $conn->prepare("SELECT id FROM penilaian_rekan WHERE penilai_id = ?");
$cek_peer->bind_param("i", $siswa_id);
$cek_peer->execute();
$has_peer = $cek_peer->get_result()->num_rows > 0;
$cek_peer->close();

// Check Video
$cek_video = $conn->prepare("SELECT video_path FROM penilaian_psikomotor WHERE siswa_id = ?");
$cek_video->bind_param("i", $siswa_id);
$cek_video->execute();
$res_vid = $cek_video->get_result();
$video_path = $res_vid->num_rows > 0 ? $res_vid->fetch_assoc()['video_path'] : null;
$cek_video->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Aktivitas Siswa – PADI-PJOK</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    :root{--blue:#1A56DB;--blue-dark:#1240A8;--blue-light:#EFF4FF;--blue-mid:#DBEAFE;--green:#16A34A;--green-light:#DCFCE7;--orange:#EA580C;--orange-light:#FFF7ED;--yellow:#D97706;--yellow-light:#FFFBEB;--purple:#7C3AED;--purple-light:#F5F3FF;--text:#111827;--text-2:#374151;--text-3:#6B7280;--text-4:#9CA3AF;--border:#E5E7EB;--bg:#F3F6FB;--white:#FFFFFF;--nav-h:68px;--radius:14px;--radius-sm:10px;--shadow:0 2px 8px rgba(0,0,0,.07);--shadow-md:0 4px 18px rgba(0,0,0,.10)}
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
    .hero{background:linear-gradient(135deg,#EFF6FF 0%,#DBEAFE 60%,#E0F2FE 100%);padding:20px 20px 0;display:flex;align-items:flex-end;min-height:160px;position:relative;overflow:hidden}
    .hero::before{content:'';position:absolute;inset:0;background:radial-gradient(ellipse at 80%,rgba(26,86,219,.07),transparent 60%)}
    .hero-text{flex:1;padding-bottom:20px;z-index:1}
    .hero-text h1{font-size:20px;font-weight:800;color:var(--text);margin-bottom:6px;line-height:1.3}
    .token-tag{display:inline-flex;align-items:center;gap:6px;background:var(--white);border:1.5px solid var(--blue-mid);color:var(--blue);font-size:12px;font-weight:700;padding:5px 12px;border-radius:10px;box-shadow:var(--shadow)}
    .token-tag svg{width:14px;height:14px}
    .hero-img{width:120px;height:150px;object-fit:contain;object-position:bottom;flex-shrink:0;z-index:1}

    .content{padding:14px 16px;display:flex;flex-direction:column;gap:14px}

    /* 4C card */
    .c4-card{background:var(--white);border-radius:var(--radius);box-shadow:var(--shadow-md);padding:14px 16px}
    .c4-title{font-size:13px;font-weight:700;color:var(--blue);margin-bottom:12px;display:flex;align-items:center;gap:6px}
    .c4-title::before{content:'';width:4px;height:16px;background:var(--blue);border-radius:4px;display:inline-block}
    .c4-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:6px}
    .c4-item{display:flex;flex-direction:column;align-items:center;gap:5px;padding:10px 4px;border-radius:var(--radius-sm);background:var(--bg);transition:all .2s;cursor:pointer}
    .c4-item:hover{background:var(--blue-light)}
    .c4-icon{width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center}
    .c4-icon svg{width:20px;height:20px}
    .c4-icon.blue{background:var(--blue-light);color:var(--blue)}
    .c4-icon.yellow{background:var(--yellow-light);color:var(--yellow)}
    .c4-icon.green{background:var(--green-light);color:var(--green)}
    .c4-icon.purple{background:var(--purple-light);color:var(--purple)}
    .c4-label{font-size:9.5px;font-weight:700;color:var(--text-2);text-align:center;line-height:1.3}

    /* Steps */
    .steps-card{background:var(--white);border-radius:var(--radius);box-shadow:var(--shadow-md);padding:14px 16px}
    .steps-title{font-size:14px;font-weight:700;color:var(--text);margin-bottom:14px;display:flex;align-items:center;gap:8px}
    .steps-title svg{width:18px;height:18px;color:var(--blue)}

    .steps-row{display:flex;gap:4px;margin-bottom:16px;overflow-x:auto;scrollbar-width:none;padding-bottom:4px}
    .steps-row::-webkit-scrollbar{display:none}
    .step-item{flex-shrink:0;display:flex;flex-direction:column;align-items:center;gap:4px;width:60px}
    .step-num{width:28px;height:28px;border-radius:50%;background:var(--blue);color:white;font-size:12px;font-weight:800;display:flex;align-items:center;justify-content:center;position:relative;z-index:1}
    .step-num.done{background:var(--green)}
    .step-num.pending{background:var(--text-4)}
    .step-icon-wrap{width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;margin:2px 0}
    .step-icon-wrap svg{width:20px;height:20px}
    .step-icon-wrap.blue{background:var(--blue-light);color:var(--blue)}
    .step-icon-wrap.green{background:var(--green-light);color:var(--green)}
    .step-icon-wrap.orange{background:var(--orange-light);color:var(--orange)}
    .step-icon-wrap.purple{background:var(--purple-light);color:var(--purple)}
    .step-icon-wrap.gray{background:var(--bg);color:var(--text-4)}
    .step-label{font-size:8.5px;font-weight:600;color:var(--text-3);text-align:center;line-height:1.3}

    /* Connector */
    .steps-connector{display:flex;gap:4px;margin-bottom:16px;align-items:center;padding:0 16px}
    .steps-connector-inner{display:flex;align-items:center;gap:0;flex:1}
    .conn-line{flex:1;height:2px;background:var(--border);border-radius:1px}
    .conn-line.done{background:var(--green)}
    .conn-dot{width:6px;height:6px;border-radius:50%;background:var(--border);flex-shrink:0}
    .conn-dot.done{background:var(--green)}

    /* Video section */
    .video-title{font-size:12px;font-weight:600;color:var(--text-3);margin-bottom:8px}
    .video-row{display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;align-items:stretch}
    .video-thumb{border-radius:10px;overflow:hidden;position:relative;cursor:pointer;transition:transform .2s}
    .video-thumb:hover{transform:scale(1.02)}
    .video-thumb img{width:100%;height:90px;object-fit:cover;display:block}
    .video-label{position:absolute;top:6px;left:6px;font-size:9px;font-weight:800;padding:3px 7px;border-radius:6px;z-index:2}
    .video-label.green{background:#16A34A;color:white}
    .video-label.red{background:#DC2626;color:white}
    .video-badge{position:absolute;top:6px;right:6px;width:20px;height:20px;border-radius:50%;display:flex;align-items:center;justify-content:center;z-index:2}
    .video-badge.ok{background:#16A34A}
    .video-badge.err{background:#DC2626}
    .video-badge svg{width:12px;height:12px;color:white}
    .video-duration{position:absolute;bottom:6px;right:6px;background:rgba(0,0,0,.65);color:white;font-size:9px;font-weight:600;padding:2px 5px;border-radius:4px}
    .video-play{position:absolute;inset:0;display:flex;align-items:center;justify-content:center}
    .video-play svg{width:28px;height:28px;color:white;filter:drop-shadow(0 2px 4px rgba(0,0,0,.4))}

    .upload-box{border:2.5px dashed var(--blue-mid);border-radius:10px;height:90px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;cursor:pointer;background:var(--blue-light);transition:all .2s}
    .upload-box:hover{border-color:var(--blue);background:var(--blue-mid)}
    .upload-box svg{width:24px;height:24px;color:var(--blue)}
    .upload-box p{font-size:10px;font-weight:700;color:var(--blue);text-align:center;line-height:1.4}

    /* Domains */
    .domain-card{background:var(--white);border-radius:var(--radius);box-shadow:var(--shadow-md);padding:14px 16px}
    .domain-title{font-size:14px;font-weight:700;color:var(--text);margin-bottom:12px}
    .domain-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}
    .domain-item{background:var(--bg);border-radius:var(--radius-sm);padding:10px 8px;display:flex;flex-direction:column;align-items:center;gap:5px;text-align:center}
    .domain-icon{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center}
    .domain-icon svg{width:18px;height:18px}
    .domain-icon.blue{background:var(--blue-light);color:var(--blue)}
    .domain-icon.green{background:var(--green-light);color:var(--green)}
    .domain-icon.orange{background:var(--orange-light);color:var(--orange)}
    .domain-label{font-size:11px;font-weight:700;color:var(--text)}
    .domain-desc{font-size:9px;color:var(--text-3);line-height:1.4}

    /* Self + Peer */
    .two-col{display:grid;grid-template-columns:1fr 1fr;gap:10px}
    .self-card{background:var(--white);border-radius:var(--radius-sm);box-shadow:var(--shadow);padding:14px;display:flex;flex-direction:column;gap:10px}
    .self-header{display:flex;align-items:center;gap:8px}
    .self-icon{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
    .self-icon svg{width:18px;height:18px}
    .self-icon.blue{background:var(--blue-light);color:var(--blue)}
    .self-icon.purple{background:var(--purple-light);color:var(--purple)}
    .self-title{font-size:12px;font-weight:700;color:var(--text);line-height:1.3}
    .self-desc{font-size:10px;color:var(--text-3);line-height:1.4}
    .btn-isi{display:flex;align-items:center;justify-content:center;gap:5px;padding:10px;background:var(--blue-light);color:var(--blue);border:1.5px solid var(--blue-mid);border-radius:var(--radius-sm);font-family:inherit;font-size:11px;font-weight:700;cursor:pointer;transition:all .2s;text-decoration:none}
    .btn-isi:hover{background:var(--blue);color:white;border-color:var(--blue)}
    .btn-isi svg{width:14px;height:14px}

    .peer-card{background:var(--white);border-radius:var(--radius-sm);box-shadow:var(--shadow);padding:14px;display:flex;flex-direction:column;gap:10px;overflow:hidden}
    .peer-thumb{border-radius:8px;overflow:hidden;position:relative;cursor:pointer}
    .peer-thumb img{width:100%;height:70px;object-fit:cover;display:block}
    .peer-play{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.25)}
    .peer-play svg{width:24px;height:24px;color:white}
    .peer-duration{position:absolute;bottom:5px;right:5px;background:rgba(0,0,0,.65);color:white;font-size:9px;padding:2px 5px;border-radius:4px;font-weight:600}
    .btn-nilai{display:flex;align-items:center;justify-content:center;gap:5px;padding:10px;background:var(--blue);color:white;border:none;border-radius:var(--radius-sm);font-family:inherit;font-size:11px;font-weight:700;cursor:pointer;transition:all .2s;box-shadow:0 4px 12px rgba(26,86,219,.3)}
    .btn-nilai:hover{background:var(--blue-dark)}
    .btn-nilai svg{width:14px;height:14px}

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
    @keyframes spin{from{transform:rotate(0deg)}to{transform:rotate(360deg)}}
    .spin{animation:spin .8s linear infinite}
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
    <h1>Passing Bawah Bola Voli – Kelas X-1</h1>
    <div class="token-tag">
      <svg viewBox="0 0 24 24" fill="none"><rect x="3" y="11" width="18" height="11" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M7 11V7a5 5 0 0110 0v4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
      Token aktif: VOLI-X1-482
    </div>
  </div>
  <img class="hero-img" src="siswa_hero.png" alt="Siswa PJOK"/>
</div>

<div class="content">

  <!-- Belajar Abad 21: 4C -->
  <div class="c4-card anim">
    <div class="c4-title">Belajar Abad 21: 4C</div>
    <div class="c4-grid">
      <div class="c4-item" onclick="showToast('Critical Thinking: Berpikir kritis')">
        <div class="c4-icon blue"><svg viewBox="0 0 24 24" fill="none"><path d="M12 2C8 2 5 5 5 9c0 2.5 1.3 4.7 3.2 6H12h3.8C17.7 13.7 19 11.5 19 9c0-4-3-7-7-7z" stroke="currentColor" stroke-width="1.8"/><path d="M9 21h6M10 17h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></div>
        <span class="c4-label">Critical Thinking</span>
      </div>
      <div class="c4-item" onclick="showToast('Creativity: Kreativitas')">
        <div class="c4-icon yellow"><svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.8"/><path d="M12 1v3M12 20v3M4.22 4.22l2.12 2.12M17.66 17.66l2.12 2.12M1 12h3M20 12h3M4.22 19.78l2.12-2.12M17.66 6.34l2.12-2.12" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></div>
        <span class="c4-label">Creativity</span>
      </div>
      <div class="c4-item" onclick="showToast('Collaboration: Kerja sama')">
        <div class="c4-icon green"><svg viewBox="0 0 24 24" fill="none"><circle cx="9" cy="7" r="3" stroke="currentColor" stroke-width="1.8"/><circle cx="16" cy="7" r="3" stroke="currentColor" stroke-width="1.8"/><path d="M2 21c0-3.5 3-6 7-6h4c4 0 7 2.5 7 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></div>
        <span class="c4-label">Collaboration</span>
      </div>
      <div class="c4-item" onclick="showToast('Communication: Komunikasi')">
        <div class="c4-icon purple"><svg viewBox="0 0 24 24" fill="none"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2v10z" stroke="currentColor" stroke-width="1.8"/></svg></div>
        <span class="c4-label">Communication</span>
      </div>
    </div>
  </div>

  <!-- Langkah Belajar -->
  <div class="steps-card anim">
    <div class="steps-title">
      <svg viewBox="0 0 24 24" fill="none"><line x1="8" y1="6" x2="21" y2="6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><line x1="8" y1="12" x2="21" y2="12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><line x1="8" y1="18" x2="21" y2="18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><line x1="3" y1="6" x2="3.01" y2="6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><line x1="3" y1="12" x2="3.01" y2="12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><line x1="3" y1="18" x2="3.01" y2="18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
      Langkah Belajar di Sesi Ini
    </div>

    <!-- Stepper -->
    <div style="display:flex;justify-content:space-between;align-items:flex-start;position:relative;margin-bottom:6px;padding:0 4px">
      <!-- Connector line -->
      <?php
        $progress = 50;
        if($has_self) $progress = 75;
        if($has_peer) $progress = 100;
      ?>
      <div style="position:absolute;top:14px;left:10%;right:10%;height:2.5px;background:linear-gradient(90deg,#22C55E 0%,#22C55E <?= $progress ?>%,#9CA3AF <?= $progress ?>%,#9CA3AF 100%);z-index:0;border-radius:2px"></div>

      <div style="display:flex;flex-direction:column;align-items:center;gap:4px;width:60px;z-index:1">
        <div style="width:28px;height:28px;border-radius:50%;background:#22C55E;display:flex;align-items:center;justify-content:center"><svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M20 6L9 17l-5-5" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
        <div style="width:36px;height:36px;border-radius:10px;background:var(--green-light);display:flex;align-items:center;justify-content:center"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" style="color:#16A34A"><rect x="2" y="5" width="15" height="14" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M17 9l5-3v12l-5-3V9z" stroke="currentColor" stroke-width="1.8"/></svg></div>
        <span style="font-size:8px;font-weight:600;color:var(--text-3);text-align:center;line-height:1.3">Tonton video contoh gerakan benar dan salah.</span>
      </div>

      <div style="display:flex;flex-direction:column;align-items:center;gap:4px;width:60px;z-index:1">
        <div style="width:28px;height:28px;border-radius:50%;background:#22C55E;display:flex;align-items:center;justify-content:center"><svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M20 6L9 17l-5-5" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
        <div style="width:36px;height:36px;border-radius:10px;background:var(--orange-light);display:flex;align-items:center;justify-content:center"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" style="color:#EA580C"><circle cx="12" cy="5" r="2.5" stroke="currentColor" stroke-width="1.8"/><path d="M12 8v5l-3 4M12 13l3 4M8 10l-3 2M16 10l3 2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></div>
        <span style="font-size:8px;font-weight:600;color:var(--text-3);text-align:center;line-height:1.3">Praktikkan gerakan.</span>
      </div>

      <div style="display:flex;flex-direction:column;align-items:center;gap:4px;width:60px;z-index:1">
        <div style="width:28px;height:28px;border-radius:50%;background:var(--blue);display:flex;align-items:center;justify-content:center;box-shadow:0 0 0 4px rgba(26,86,219,.2)"><span style="font-size:12px;font-weight:800;color:white">3</span></div>
        <div style="width:36px;height:36px;border-radius:10px;background:var(--blue-light);display:flex;align-items:center;justify-content:center;border:2px solid var(--blue-mid)"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" style="color:#1A56DB"><polyline points="16 16 12 12 8 16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><line x1="12" y1="12" x2="12" y2="21" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M20.39 18.39A5 5 0 0018 9h-1.26A8 8 0 103 16.3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></div>
        <span style="font-size:8px;font-weight:700;color:var(--blue);text-align:center;line-height:1.3">Rekam lalu unggah video.</span>
      </div>

      <div style="display:flex;flex-direction:column;align-items:center;gap:4px;width:60px;z-index:1">
        <?php if($has_self): ?>
        <div style="width:28px;height:28px;border-radius:50%;background:#22C55E;display:flex;align-items:center;justify-content:center"><svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M20 6L9 17l-5-5" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
        <div style="width:36px;height:36px;border-radius:10px;background:var(--blue-light);display:flex;align-items:center;justify-content:center"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" style="color:var(--blue)"><path d="M21 8v13H3V8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M23 3H1v5h22V3z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><line x1="10" y1="12" x2="14" y2="12" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></div>
        <?php else: ?>
        <div style="width:28px;height:28px;border-radius:50%;background:var(--text-4);display:flex;align-items:center;justify-content:center"><span style="font-size:12px;font-weight:800;color:white">4</span></div>
        <div style="width:36px;height:36px;border-radius:10px;background:var(--bg);display:flex;align-items:center;justify-content:center"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" style="color:var(--text-4)"><path d="M21 8v13H3V8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M23 3H1v5h22V3z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><line x1="10" y1="12" x2="14" y2="12" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></div>
        <?php endif; ?>
        <span style="font-size:8px;font-weight:600;color:var(--text-3);text-align:center;line-height:1.3">Lihat ulang gerakan sendiri dan refleksi.</span>
      </div>

      <div style="display:flex;flex-direction:column;align-items:center;gap:4px;width:60px;z-index:1">
        <?php if($has_peer): ?>
        <div style="width:28px;height:28px;border-radius:50%;background:#22C55E;display:flex;align-items:center;justify-content:center"><svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M20 6L9 17l-5-5" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
        <div style="width:36px;height:36px;border-radius:10px;background:var(--purple-light);display:flex;align-items:center;justify-content:center"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" style="color:var(--purple)"><circle cx="9" cy="7" r="3" stroke="currentColor" stroke-width="1.8"/><circle cx="16" cy="7" r="3" stroke="currentColor" stroke-width="1.8"/><path d="M2 21c0-3.5 3-6 7-6h4c4 0 7 2.5 7 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></div>
        <?php else: ?>
        <div style="width:28px;height:28px;border-radius:50%;background:var(--text-4);display:flex;align-items:center;justify-content:center"><span style="font-size:12px;font-weight:800;color:white">5</span></div>
        <div style="width:36px;height:36px;border-radius:10px;background:var(--bg);display:flex;align-items:center;justify-content:center"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" style="color:var(--text-4)"><circle cx="9" cy="7" r="3" stroke="currentColor" stroke-width="1.8"/><circle cx="16" cy="7" r="3" stroke="currentColor" stroke-width="1.8"/><path d="M2 21c0-3.5 3-6 7-6h4c4 0 7 2.5 7 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></div>
        <?php endif; ?>
        <span style="font-size:8px;font-weight:600;color:var(--text-3);text-align:center;line-height:1.3">Nilai video teman yang tampil acak.</span>
      </div>
    </div>

    <!-- Video examples -->
    <div class="video-title" style="margin-top:14px">Contoh Video & Unggah Praktik</div>
    <div class="video-row">
      <div class="video-thumb" onclick="showToast('Memutar: Contoh Benar')">
        <img src="video_benar.png" alt="Contoh gerakan benar"/>
        <span class="video-label green">Contoh Benar</span>
        <span class="video-badge ok"><svg viewBox="0 0 24 24" fill="none"><path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
        <span class="video-duration">0:24</span>
        <div class="video-play"><svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" fill="rgba(0,0,0,.5)"/><polygon points="10 8 16 12 10 16 10 8" fill="white"/></svg></div>
      </div>
      <div class="video-thumb" onclick="showToast('Memutar: Contoh Salah')">
        <img src="video_salah.png" alt="Contoh gerakan salah"/>
        <span class="video-label red">Contoh Salah</span>
        <span class="video-badge err"><svg viewBox="0 0 24 24" fill="none"><path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/></svg></span>
        <span class="video-duration">0:24</span>
        <div class="video-play"><svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" fill="rgba(0,0,0,.5)"/><polygon points="10 8 16 12 10 16 10 8" fill="white"/></svg></div>
      </div>
      <div id="video-upload-area" style="grid-column: span 2;">
        <?php if (!empty($video_path)): ?>
          <div style="border-radius:10px; overflow:hidden; border:2px solid var(--blue-mid); height:160px; background:#000;">
            <iframe src="<?= htmlspecialchars($video_path) ?>" width="100%" height="100%" frameborder="0" allowfullscreen></iframe>
          </div>
          <p style="text-align:center; font-size:11px; margin-top:8px; color:var(--green); font-weight:600;">✅ Video Praktik Tersimpan</p>
        <?php else: ?>
          <div class="upload-box" style="height:auto; padding:16px;" onclick="document.getElementById('link-video').focus()">
            <svg viewBox="0 0 24 24" fill="none" style="margin-bottom:8px;"><polyline points="16 16 12 12 8 16" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><line x1="12" y1="12" x2="12" y2="21" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M20.39 18.39A5 5 0 0018 9h-1.26A8 8 0 103 16.3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            <p style="margin-bottom:10px;">Tautkan Video Praktik<br/><span style="font-weight:400;color:var(--text-3)">YouTube / Google Drive</span></p>
            <div style="display:flex; gap:6px; width:100%;">
              <input type="url" id="link-video" placeholder="Tempel link di sini..." style="flex:1; padding:8px 12px; border:1px solid var(--border); border-radius:6px; font-size:12px; outline:none;" onclick="event.stopPropagation()">
              <button onclick="simpanVideo(event)" style="background:var(--blue); color:white; border:none; border-radius:6px; padding:0 12px; font-size:12px; font-weight:600; cursor:pointer;">Simpan</button>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- 3 Domain Penilaian -->
  <div class="domain-card anim">
    <div class="domain-title">3 Domain Penilaian</div>
    <div class="domain-grid">
      <div class="domain-item">
        <div class="domain-icon blue"><svg viewBox="0 0 24 24" fill="none"><path d="M12 2C8 2 5 5 5 9c0 2.5 1.3 4.7 3.2 6H12h3.8C17.7 13.7 19 11.5 19 9c0-4-3-7-7-7z" stroke="currentColor" stroke-width="1.8"/><path d="M9 21h6M10 17h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></div>
        <span class="domain-label">Kognitif</span>
        <span class="domain-desc">Pemahaman konsep teknik passing bawah.</span>
      </div>
      <div class="domain-item">
        <div class="domain-icon green"><svg viewBox="0 0 24 24" fill="none"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z" stroke="currentColor" stroke-width="1.8"/></svg></div>
        <span class="domain-label">Afektif</span>
        <span class="domain-desc">Sikap sportivitas, kerja sama, disiplin.</span>
      </div>
      <div class="domain-item">
        <div class="domain-icon orange"><svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="5" r="2.5" stroke="currentColor" stroke-width="1.8"/><path d="M12 8v5l-3 4M12 13l3 4M8 10l-3 2M16 10l3 2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></div>
        <span class="domain-label">Psikomotor</span>
        <span class="domain-desc">Teknik gerak passing bawah yang benar.</span>
      </div>
    </div>
  </div>

  <!-- Self + Peer assessment -->
  <div class="two-col anim">
    <div class="self-card">
      <div class="self-header">
        <div class="self-icon blue"><svg viewBox="0 0 24 24" fill="none"><rect x="5" y="2" width="14" height="20" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M9 7h6M9 11h6M9 15h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><circle cx="17" cy="17" r="4" fill="#22C55E"/><path d="M15 17l1.5 1.5L19 15" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
        <span class="self-title">Penilaian Diri</span>
      </div>
      <p class="self-desc">Nilai dirimu sendiri menggunakan rubrik penilaian.</p>
      <a class="btn-isi" href="penilaian-afektif.php">
        <?php if($has_self): ?>
        <svg viewBox="0 0 24 24" fill="none"><path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        Sudah Diisi (Edit)
        <?php else: ?>
        <svg viewBox="0 0 24 24" fill="none"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        Isi Penilaian Diri
        <?php endif; ?>
      </a>
    </div>

    <div class="peer-card">
      <div class="self-header">
        <div class="self-icon purple"><svg viewBox="0 0 24 24" fill="none"><circle cx="9" cy="7" r="3" stroke="currentColor" stroke-width="1.8"/><circle cx="16" cy="7" r="3" stroke="currentColor" stroke-width="1.8"/><path d="M2 21c0-3.5 3-6 7-6h4c4 0 7 2.5 7 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></div>
        <span class="self-title">Nilai Teman Secara Acak</span>
      </div>
      <div class="peer-thumb" onclick="window.location.href='penilaian-rekan.php'">
        <img src="video_benar.png" alt="Video teman"/>
        <div class="peer-play"><svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" fill="rgba(0,0,0,.5)"/><polygon points="10 8 16 12 10 16 10 8" fill="white"/></svg></div>
        <span class="peer-duration">0:30</span>
      </div>
      <a href="penilaian-rekan.php" class="btn-nilai" style="text-decoration:none">
        <?php if($has_peer): ?>
        <svg viewBox="0 0 24 24" fill="none"><path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        Sudah Menilai (Edit)
        <?php else: ?>
        <svg viewBox="0 0 24 24" fill="none"><path d="M12 2l3 6.3 6.9 1-5 4.9 1.2 7-6.1-3.2L5.9 21l1.2-7-5-4.9 6.9-1L12 2z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>
        Mulai Menilai
        <?php endif; ?>
      </a>
    </div>
  </div>

</div>

<nav class="bottom-nav">
  <a href="dashboard-siswa.php" class="nav-item" aria-label="Beranda">
    <div class="nav-indicator"></div>
    <svg viewBox="0 0 24 24" fill="none"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z" stroke="currentColor" stroke-width="2"/><path d="M9 22V12h6v10" stroke="currentColor" stroke-width="2"/></svg>
    <span>Beranda</span>
  </a>
  <a href="#" class="nav-item active" aria-label="Aktivitas" aria-current="page">
    <div class="nav-indicator"></div>
    <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="8" r="3" stroke="currentColor" stroke-width="2"/><path d="M12 11v4l-2 3h4l-2-3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M6 10l-3 2M18 10l3 2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
    <span>Aktivitas</span>
  </a>
  <a href="rekap-penilaian.php" class="nav-item" aria-label="Nilai">
    <div class="nav-indicator"></div>
    <svg viewBox="0 0 24 24" fill="none"><rect x="5" y="2" width="14" height="20" rx="2" stroke="currentColor" stroke-width="2"/><path d="M9 7h6M9 11h6M9 15h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
    <span>Nilai</span>
  </a>
  <a href="#" class="nav-item" onclick="showToast('Profil Siswa'); return false;" aria-label="Profil">
    <div class="nav-indicator"></div>
    <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="8" r="4" stroke="currentColor" stroke-width="2"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
    <span>Profil</span>
  </a>
</nav>

<div class="toast" id="toast"></div>
<script>
  function showToast(m){const t=document.getElementById('toast');t.textContent=m;t.classList.add('show');clearTimeout(t._t);t._t=setTimeout(()=>t.classList.remove('show'),3000);}
  function simpanVideo(e){
    e.stopPropagation();
    const btn = e.target;
    const input = document.getElementById('link-video');
    const link = input.value.trim();
    
    if(!link){
      showToast('❌ Masukkan link video terlebih dahulu!');
      return;
    }
    
    btn.disabled = true;
    btn.textContent = 'Menyimpan...';
    
    fetch('aktivitas-siswa.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        action: 'save_video_link',
        link: link
      })
    })
    .then(r => r.json())
    .then(data => {
      if(data.success){
        showToast('✅ Video berhasil ditautkan!');
        setTimeout(() => location.reload(), 1000);
      } else {
        showToast('❌ Gagal menyimpan tautan video.');
        btn.disabled = false;
        btn.textContent = 'Simpan';
      }
    })
    .catch(err => {
      showToast('❌ Terjadi kesalahan koneksi.');
      btn.disabled = false;
      btn.textContent = 'Simpan';
    });
  }
</script>
</body>
</html>
