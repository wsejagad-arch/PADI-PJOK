<?php
require_once 'auth.php';
wajibLoginSiswa();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_psikomotor') {
    require 'koneksi.php';
    header('Content-Type: application/json');
    $nilai = intval($_POST['nilai'] ?? 0);
    $analisis = $_POST['analisis'] ?? '';
    $siswa_id = $_SESSION['siswa_id'];
    
    $stmt = $conn->prepare("SELECT id FROM penilaian_psikomotor WHERE siswa_id = ?");
    $stmt->bind_param("i", $siswa_id);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($res->num_rows > 0) {
        $stmt_upd = $conn->prepare("UPDATE penilaian_psikomotor SET nilai_rubrik = ?, analisis_siswa = ? WHERE siswa_id = ?");
        $stmt_upd->bind_param("isi", $nilai, $analisis, $siswa_id);
        $stmt_upd->execute();
    } else {
        $stmt_ins = $conn->prepare("INSERT INTO penilaian_psikomotor (siswa_id, nilai_rubrik, analisis_siswa) VALUES (?, ?, ?)");
        $stmt_ins->bind_param("iis", $siswa_id, $nilai, $analisis);
        $stmt_ins->execute();
    }
    
    echo json_encode(['success' => true]);
    exit;
}

require_once 'koneksi.php';
$siswa_id = $_SESSION['siswa_id'];
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
  <title>Penilaian Psikomotor – PADI-PJOK</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    :root{--blue:#1A56DB;--blue-dark:#1240A8;--blue-light:#EFF4FF;--blue-mid:#DBEAFE;--green:#16A34A;--green-light:#DCFCE7;--orange:#EA580C;--orange-light:#FFF7ED;--yellow:#D97706;--yellow-light:#FFFBEB;--purple:#7C3AED;--purple-light:#F5F3FF;--text:#111827;--text-2:#374151;--text-3:#6B7280;--text-4:#9CA3AF;--border:#E5E7EB;--bg:#F3F6FB;--white:#FFFFFF;--nav-h:68px;--radius:14px;--radius-sm:10px;--shadow:0 2px 8px rgba(0,0,0,.07);--shadow-md:0 4px 18px rgba(0,0,0,.10)}
    body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);max-width:480px;margin:0 auto;padding-bottom:calc(var(--nav-h) + 80px)}

    /* Topbar */
    .topbar{position:sticky;top:0;z-index:50;background:var(--white);border-bottom:1px solid var(--border);box-shadow:var(--shadow);padding:0 20px}
    .topbar-inner{display:flex;align-items:center;justify-content:space-between;height:54px}
    .topbar-logo{display:flex;align-items:center;gap:8px}
    .topbar-logo svg{width:24px;height:24px}
    .topbar-logo span{font-size:15px;font-weight:800;color:var(--blue)}
    .notif-btn{position:relative;width:34px;height:34px;background:var(--blue-light);border:none;border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer}
    .notif-btn svg{width:18px;height:18px;color:var(--blue)}
    .notif-dot{position:absolute;top:5px;right:5px;width:7px;height:7px;background:#EF4444;border-radius:50%;border:2px solid var(--white)}

    /* Hero */
    .hero{background:linear-gradient(135deg,#EFF6FF 0%,#DBEAFE 60%,#E0F2FE 100%);padding:16px 20px 0;display:flex;align-items:flex-end;min-height:160px;position:relative;overflow:hidden}
    .hero::before{content:'';position:absolute;inset:0;background:radial-gradient(ellipse at 80%,rgba(26,86,219,.07),transparent 60%)}
    .hero-text{flex:1;padding-bottom:16px;z-index:1}
    .back-btn{display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;background:rgba(255,255,255,.8);border:1px solid rgba(26,86,219,.2);border-radius:8px;margin-bottom:8px;cursor:pointer;color:var(--blue);text-decoration:none}
    .back-btn svg{width:16px;height:16px}
    .hero-text h1{font-size:20px;font-weight:800;color:var(--text);margin-bottom:3px}
    .hero-subtitle{font-size:12px;font-weight:700;color:var(--blue);margin-bottom:4px}
    .hero-desc{font-size:11px;color:var(--text-3);line-height:1.5;max-width:195px}
    .hero-img{width:115px;height:145px;object-fit:contain;object-position:bottom;flex-shrink:0;z-index:1}

    .content{padding:14px 16px;display:flex;flex-direction:column;gap:14px}

    /* Domain Stepper */
    .stepper-card{background:var(--white);border-radius:var(--radius);box-shadow:var(--shadow-md);padding:14px 16px}
    .stepper-label{font-size:10px;font-weight:700;color:var(--blue);text-align:right;margin-bottom:10px}
    .stepper-row{display:flex;align-items:flex-start;gap:0}
    .step{display:flex;flex-direction:column;align-items:center;gap:3px;flex:1}
    .step-circle{width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;border:2px solid transparent;position:relative;z-index:1}
    .step-circle.done{background:var(--green);color:white;border-color:var(--green)}
    .step-circle.active{background:var(--blue);color:white;border-color:var(--blue);box-shadow:0 0 0 4px rgba(26,86,219,.2)}
    .step-circle.todo{background:var(--bg);color:var(--text-4);border-color:var(--border)}
    .step-name{font-size:10px;font-weight:700;text-align:center}
    .step-name.done{color:var(--text);font-weight:600}
    .step-name.active{color:var(--blue)}
    .step-name.todo{color:var(--text-4)}
    .step-line{flex:1;height:2.5px;margin-top:14px;border-radius:2px}
    .step-line.done{background:var(--green)}
    .step-line.todo{background:var(--border)}

    /* Section Title */
    .sec-title{display:flex;align-items:center;gap:8px;font-size:14px;font-weight:800;color:var(--text);margin-bottom:12px}
    .sec-title svg{width:20px;height:20px}

    /* Video & Status */
    .video-card{background:var(--white);border-radius:var(--radius);box-shadow:var(--shadow-md);padding:14px}
    .video-grid{display:grid;grid-template-columns:1.2fr 1fr;gap:12px;align-items:center}
    .vthumb{border-radius:8px;overflow:hidden;position:relative;cursor:pointer}
    .vthumb img{width:100%;height:85px;object-fit:cover;display:block}
    .vthumb-play{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.25)}
    .vthumb-play svg{width:24px;height:24px;color:white}
    .vthumb-dur{position:absolute;bottom:5px;right:5px;background:rgba(0,0,0,.6);color:white;font-size:9px;padding:2px 5px;border-radius:4px;font-weight:600}
    
    .status-box{display:flex;flex-direction:column;gap:8px}
    .status-badge{display:inline-flex;align-items:center;gap:6px;background:var(--green-light);color:var(--green);font-size:10px;font-weight:700;padding:6px 10px;border-radius:20px;align-self:flex-start}
    .status-badge svg{width:14px;height:14px}
    .status-text{font-size:10px;color:var(--text-2);line-height:1.5}

    /* Rubric */
    .rubric-card{background:var(--white);border-radius:var(--radius);box-shadow:var(--shadow-md);padding:14px}
    .rubric-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px}
    .rubric-desc{font-size:9.5px;color:var(--text-3);text-align:right;max-width:140px;line-height:1.4}
    
    .rubric-table{width:100%;border-collapse:collapse}
    .rubric-table th{text-align:left;font-size:10px;color:var(--text-3);font-weight:600;padding-bottom:8px;border-bottom:1px solid var(--border)}
    .rubric-table td{padding:10px 0;border-bottom:1px solid var(--border)}
    .rubric-table tr:last-child td{border-bottom:none;padding-bottom:0}
    .rubric-no{font-size:11px;font-weight:700;color:var(--text);width:20px;text-align:center}
    .rubric-label{font-size:11.5px;font-weight:600;color:var(--text-2);line-height:1.3;padding-right:10px}
    
    .skala-opts{display:flex;align-items:center;gap:4px;justify-content:flex-end}
    .skala-btn{width:26px;height:26px;border-radius:6px;border:1.5px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:var(--text-3);background:var(--white);cursor:pointer;transition:all .2s}
    .skala-btn:hover{border-color:var(--blue-mid);background:var(--blue-light)}
    .skala-btn.active{background:var(--blue);color:white;border-color:var(--blue)}

    /* Analysis Textarea */
    .analysis-card{background:var(--white);border-radius:var(--radius);box-shadow:var(--shadow-md);padding:14px}
    .textarea-wrap{position:relative}
    .form-textarea{width:100%;height:100px;padding:12px;border:1.5px solid var(--border);border-radius:var(--radius-sm);font-family:inherit;font-size:12px;color:var(--text);resize:none;outline:none;line-height:1.5;transition:all .2s;background:var(--bg)}
    .form-textarea:focus{border-color:var(--blue);background:var(--white);box-shadow:0 0 0 3px rgba(26,86,219,.12)}
    .char-count{position:absolute;bottom:10px;right:12px;font-size:10px;color:var(--text-4);font-weight:500}

    /* Info box */
    .info-box{display:flex;align-items:center;gap:10px;background:var(--blue-light);border:1.5px solid var(--blue-mid);border-radius:var(--radius-sm);padding:10px 12px}
    .info-box-icon{width:28px;height:28px;border-radius:8px;background:var(--white);display:flex;align-items:center;justify-content:center;flex-shrink:0}
    .info-box-icon svg{width:16px;height:16px;color:var(--yellow)}
    .info-box-desc{font-size:10.5px;color:var(--text-2);line-height:1.4}
    .info-box-desc strong{color:var(--blue)}

    /* Score Summary */
    .score-card{background:var(--blue-light);border:1.5px solid var(--blue-mid);border-radius:var(--radius-sm);padding:10px;display:flex;align-items:center;justify-content:center;gap:10px}
    .score-card svg{width:16px;height:16px;color:var(--blue)}
    .score-text{font-size:12px;color:var(--text-2);font-weight:500}
    .score-val{font-size:14px;font-weight:800;color:var(--blue)}

    /* Sticky bottom */
    .sticky-btn{position:fixed;bottom:var(--nav-h);left:50%;transform:translateX(-50%);width:100%;max-width:480px;background:var(--white);border-top:1px solid var(--border);padding:12px 16px;z-index:40}
    .btn-lanjut{width:100%;display:flex;align-items:center;justify-content:center;gap:10px;padding:15px;background:var(--blue);color:white;border:none;border-radius:var(--radius-sm);font-family:inherit;font-size:15px;font-weight:700;cursor:pointer;box-shadow:0 6px 20px rgba(26,86,219,.35);transition:all .25s}
    .btn-lanjut svg{width:20px;height:20px}
    .btn-lanjut:hover{background:var(--blue-dark);transform:translateY(-1px)}

    /* Bottom Nav */
    .bottom-nav{position:fixed;bottom:0;left:50%;transform:translateX(-50%);width:100%;max-width:480px;background:var(--white);border-top:1px solid var(--border);display:flex;box-shadow:0 -4px 16px rgba(0,0,0,.08);z-index:50;height:var(--nav-h)}
    .nav-item{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px;cursor:pointer;border:none;background:transparent;font-family:inherit;padding:8px 4px;color:var(--text-4);text-decoration:none;position:relative}
    .nav-item svg{width:22px;height:22px}
    .nav-item span{font-size:10px;font-weight:600}
    .nav-item.active{color:var(--blue)}
    .nav-indicator{position:absolute;top:0;left:50%;transform:translateX(-50%) scaleX(0);width:32px;height:3px;background:var(--blue);border-radius:0 0 4px 4px;transition:transform .25s}
    .nav-item.active .nav-indicator{transform:translateX(-50%) scaleX(1)}

    .toast{position:fixed;bottom:calc(var(--nav-h)+88px);left:50%;transform:translateX(-50%) translateY(80px);background:#111827;color:#fff;padding:11px 20px;border-radius:10px;font-size:13px;font-weight:500;box-shadow:0 8px 30px rgba(0,0,0,.25);z-index:999;transition:transform .35s cubic-bezier(.22,1,.36,1);white-space:nowrap}
    .toast.show{transform:translateX(-50%) translateY(0)}
    @keyframes fadeUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}
    .anim{animation:fadeUp .4s both}
  </style>
</head>
<body>

<header class="topbar">
  <div class="topbar-inner">
    <div class="topbar-logo">
      <svg viewBox="0 0 28 28" fill="none"><circle cx="18" cy="5" r="3" fill="#1A56DB"/><path d="M6 24L13 14 11 9 17 5" stroke="#1A56DB" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/><path d="M11 9L18 12L24 9" stroke="#1A56DB" stroke-width="2.2" stroke-linecap="round"/><path d="M18 12L15 20L19 24" stroke="#1A56DB" stroke-width="2.2" stroke-linecap="round"/><path d="M2 18Q9 14 16 17" stroke="#22C55E" stroke-width="2" stroke-linecap="round"/></svg>
      <span>PADI-PJOK</span>
    </div>
    <button class="notif-btn" onclick="showToast('Notifikasi')" aria-label="Notifikasi">
      <svg viewBox="0 0 24 24" fill="none"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M13.73 21a2 2 0 01-3.46 0" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
      <span class="notif-dot"></span>
    </button>
  </div>
</header>

<div class="hero">
  <div class="hero-text">
    <a href="penilaian-kognitif.php" class="back-btn" aria-label="Kembali">
      <svg viewBox="0 0 24 24" fill="none"><path d="M19 12H5M12 5l-7 7 7 7" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </a>
    <h1>Penilaian Psikomotor</h1>
    <p class="hero-subtitle">Kelas X-1 | Passing Bawah Bola Voli</p>
    <p class="hero-desc">Fokus halaman ini: menilai performa gerak praktik siswa.</p>
  </div>
  <img class="hero-img" src="siswa_hero.png" alt="Siswa PJOK"/>
</div>

<div class="content">

  <!-- Stepper -->
  <div class="stepper-card anim">
    <div class="stepper-label">Domain 2 dari 3</div>
    <div class="stepper-row">
      <div class="step">
        <div class="step-circle done"><svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
        <span class="step-name done">Kognitif</span>
      </div>
      <div class="step-line done"></div>
      <div class="step">
        <div class="step-circle active">2</div>
        <span class="step-name active">Psikomotor</span>
      </div>
      <div class="step-line todo"></div>
      <div class="step">
        <div class="step-circle todo">3</div>
        <span class="step-name todo">Afektif</span>
      </div>
    </div>
  </div>

  <!-- Video -->
  <div class="video-card anim">
    <div class="sec-title">
      <svg viewBox="0 0 24 24" fill="none" style="color:var(--blue)"><rect x="3" y="6" width="18" height="12" rx="2" stroke="currentColor" stroke-width="2"/><path d="M10 9l4 3-4 3V9z" stroke="currentColor" stroke-width="2"/></svg>
      Video Praktik Saya
    </div>
    <div class="video-grid">
      <?php if (!empty($video_path)): ?>
        <div style="border-radius:10px; overflow:hidden; border:2px solid var(--blue-mid); height:160px; background:#000;">
          <iframe src="<?= htmlspecialchars($video_path) ?>" width="100%" height="100%" frameborder="0" allowfullscreen></iframe>
        </div>
        <div class="status-box">
          <div class="status-badge">
            <svg viewBox="0 0 24 24" fill="none"><path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Video Tautkan Sukses
          </div>
          <p class="status-text">Terima kasih! Videomu sudah ditautkan. Silakan periksa gerakanmu sambil mengisi rubrik di bawah ini.</p>
        </div>
      <?php else: ?>
        <div class="status-box" style="grid-column: span 2;">
          <div class="status-badge" style="background:#FEE2E2; color:#DC2626;">
            <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/><line x1="12" y1="8" x2="12" y2="12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><circle cx="12" cy="16" r="1" fill="currentColor"/></svg>
            Video Belum Ditautkan
          </div>
          <p class="status-text">Harap tautkan video di halaman Aktivitas terlebih dahulu agar kamu dapat memutar video praktikmu saat memberikan penilaian.</p>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Rubric -->
  <div class="rubric-card anim">
    <div class="rubric-header">
      <div class="sec-title" style="margin:0;color:var(--orange)">
        <svg viewBox="0 0 24 24" fill="none"><path d="M13 10V3L4 14h7v7l9-11h-7z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        Rubrik Psikomotor (Skala 1–4)
      </div>
      <div class="rubric-desc">Skala 1–4: 1 = belum terlihat,<br/>2 = mulai terlihat, 3 = baik, 4 = sangat baik.</div>
    </div>
    <table class="rubric-table">
      <thead>
        <tr>
          <th>No.</th>
          <th>Indikator Penilaian</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td class="rubric-no">1</td>
          <td class="rubric-label">Posisi kaki siap dan seimbang</td>
          <td>
            <div class="skala-opts">
              <div class="skala-btn" onclick="setSkala(this)">1</div>
              <div class="skala-btn" onclick="setSkala(this)">2</div>
              <div class="skala-btn active" onclick="setSkala(this)">3</div>
              <div class="skala-btn" onclick="setSkala(this)">4</div>
            </div>
          </td>
        </tr>
        <tr>
          <td class="rubric-no">2</td>
          <td class="rubric-label">Lutut menekuk dengan benar</td>
          <td>
            <div class="skala-opts">
              <div class="skala-btn" onclick="setSkala(this)">1</div>
              <div class="skala-btn active" onclick="setSkala(this)">2</div>
              <div class="skala-btn" onclick="setSkala(this)">3</div>
              <div class="skala-btn" onclick="setSkala(this)">4</div>
            </div>
          </td>
        </tr>
        <tr>
          <td class="rubric-no">3</td>
          <td class="rubric-label">Lengan rapat dan lurus saat kontak bola</td>
          <td>
            <div class="skala-opts">
              <div class="skala-btn" onclick="setSkala(this)">1</div>
              <div class="skala-btn" onclick="setSkala(this)">2</div>
              <div class="skala-btn" onclick="setSkala(this)">3</div>
              <div class="skala-btn active" onclick="setSkala(this)">4</div>
            </div>
          </td>
        </tr>
        <tr>
          <td class="rubric-no">4</td>
          <td class="rubric-label">Arah bola terkontrol</td>
          <td>
            <div class="skala-opts">
              <div class="skala-btn" onclick="setSkala(this)">1</div>
              <div class="skala-btn" onclick="setSkala(this)">2</div>
              <div class="skala-btn active" onclick="setSkala(this)">3</div>
              <div class="skala-btn" onclick="setSkala(this)">4</div>
            </div>
          </td>
        </tr>
        <tr>
          <td class="rubric-no">5</td>
          <td class="rubric-label">Gerak lanjutan / follow-through</td>
          <td>
            <div class="skala-opts">
              <div class="skala-btn" onclick="setSkala(this)">1</div>
              <div class="skala-btn active" onclick="setSkala(this)">2</div>
              <div class="skala-btn" onclick="setSkala(this)">3</div>
              <div class="skala-btn" onclick="setSkala(this)">4</div>
            </div>
          </td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- Analysis Textarea -->
  <div class="analysis-card anim">
    <div class="sec-title" style="color:var(--purple);margin-bottom:4px">
      <svg viewBox="0 0 24 24" fill="none"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2v10z" stroke="currentColor" stroke-width="2"/></svg>
      Analisis Gerak Saya
    </div>
    <p style="font-size:10px;color:var(--text-3);margin-bottom:12px">Tuliskan kesalahan atau hal yang masih perlu diperbaiki dari videomu.</p>
    <div class="textarea-wrap">
      <textarea class="form-textarea" placeholder="Tulis analisis gerak di sini..." oninput="updateChar(this)">Saya masih kurang menekuk lutut saat bola datang sehingga posisi tubuh kurang rendah. Kadang lengan masih sedikit menekuk saat kontak. Arah bola juga belum stabil dan kadang terlalu tinggi.
Saya akan latihan lebih fokus pada posisi tubuh dan kekuatan dorongan dari kaki.</textarea>
      <span class="char-count" id="char-val">273/300</span>
    </div>
  </div>

  <!-- Info box -->
  <div class="info-box anim">
    <div class="info-box-icon"><svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></div>
    <span class="info-box-desc">Kegiatan ini mendukung <strong>berpikir kritis</strong> dan <strong>kreativitas</strong> melalui analisis diri.</span>
  </div>

  <!-- Score Summary -->
  <div class="score-card anim">
    <svg viewBox="0 0 24 24" fill="none"><rect x="5" y="2" width="14" height="20" rx="2" stroke="currentColor" stroke-width="2"/><path d="M9 7h6M9 11h6M9 15h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
    <span class="score-text">Skor Psikomotor Sementara <span class="score-val" id="score-val">14</span> dari <strong style="color:var(--text-2)">20</strong> (Skala 1–4)</span>
  </div>

</div>

<!-- Sticky Button -->
<div class="sticky-btn">
  <button class="btn-lanjut" onclick="nextSoal()">
    Lanjut ke Afektif
    <svg viewBox="0 0 24 24" fill="none"><path d="M5 12h14M12 5l7 7-7 7" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
  </button>
</div>

<nav class="bottom-nav">
  <a href="dashboard-siswa.php" class="nav-item" aria-label="Beranda"><div class="nav-indicator"></div><svg viewBox="0 0 24 24" fill="none"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z" stroke="currentColor" stroke-width="2"/><path d="M9 22V12h6v10" stroke="currentColor" stroke-width="2"/></svg><span>Beranda</span></a>
  <a href="aktivitas-siswa.php" class="nav-item" aria-label="Aktivitas"><div class="nav-indicator"></div><svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="8" r="3" stroke="currentColor" stroke-width="2"/><path d="M12 11v4l-2 3h4l-2-3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg><span>Aktivitas</span></a>
  <a href="#" class="nav-item active" aria-label="Nilai" aria-current="page"><div class="nav-indicator"></div><svg viewBox="0 0 24 24" fill="none"><rect x="5" y="2" width="14" height="20" rx="2" stroke="currentColor" stroke-width="2"/><path d="M9 7h6M9 11h6M9 15h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg><span>Nilai</span></a>
  <a href="#" class="nav-item" onclick="showToast('Profil'); return false;" aria-label="Profil"><div class="nav-indicator"></div><svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="8" r="4" stroke="currentColor" stroke-width="2"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg><span>Profil</span></a>
</nav>

<div class="toast" id="toast"></div>
<script>
  function setSkala(el){
    const row = el.closest('tr');
    row.querySelectorAll('.skala-btn').forEach(b => b.classList.remove('active'));
    el.classList.add('active');
    calcScore();
  }
  function calcScore(){
    let total = 0;
    document.querySelectorAll('.skala-btn.active').forEach(b => {
      total += parseInt(b.textContent);
    });
    document.getElementById('score-val').textContent = total;
  }
  function updateChar(el){
    document.getElementById('char-val').textContent = el.value.length + '/300';
  }
  function nextSoal(){
    const btn = document.querySelector('.btn-lanjut');
    btn.disabled = true;
    btn.innerHTML = 'Menyimpan...';
    
    const nilai = document.getElementById('score-val').textContent;
    const analisis = document.querySelector('.form-textarea').value;

    fetch('penilaian-psikomotor.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        action: 'save_psikomotor',
        nilai: nilai,
        analisis: analisis
      })
    }).then(r=>r.json()).then(data=>{
      if(data.success){
        showToast('✅ Nilai sementara disimpan! Lanjut ke Afektif...');
        setTimeout(()=>{window.location.href='penilaian-afektif.php';},1500);
      } else {
        showToast('❌ Gagal menyimpan nilai');
        btn.disabled = false;
        btn.innerHTML = 'Lanjut ke Afektif';
      }
    }).catch(err=>{
        showToast('❌ Kesalahan jaringan');
        btn.disabled = false;
        btn.innerHTML = 'Lanjut ke Afektif';
    });
  }
  function showToast(m){const t=document.getElementById('toast');t.textContent=m;t.classList.add('show');clearTimeout(t._t);t._t=setTimeout(()=>t.classList.remove('show'),3000);}
  
  calcScore();
</script>
</body>
</html>
