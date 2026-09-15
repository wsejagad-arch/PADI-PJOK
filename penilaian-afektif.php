<?php
require_once 'auth.php';
wajibLoginSiswa();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_afektif') {
    require 'koneksi.php';
    header('Content-Type: application/json');
    $nilai = intval($_POST['nilai'] ?? 0);
    $jurnal = $_POST['jurnal'] ?? '';
    $siswa_id = $_SESSION['siswa_id'];
    
    $stmt = $conn->prepare("SELECT id FROM penilaian_afektif WHERE siswa_id = ?");
    $stmt->bind_param("i", $siswa_id);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($res->num_rows > 0) {
        $stmt_upd = $conn->prepare("UPDATE penilaian_afektif SET nilai_rubrik = ?, jurnal_refleksi = ? WHERE siswa_id = ?");
        $stmt_upd->bind_param("isi", $nilai, $jurnal, $siswa_id);
        $stmt_upd->execute();
    } else {
        $stmt_ins = $conn->prepare("INSERT INTO penilaian_afektif (siswa_id, nilai_rubrik, jurnal_refleksi) VALUES (?, ?, ?)");
        $stmt_ins->bind_param("iis", $siswa_id, $nilai, $jurnal);
        $stmt_ins->execute();
    }
    
    echo json_encode(['success' => true]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Penilaian Afektif – PADI-PJOK</title>
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

    /* Info Top */
    .info-top{display:flex;align-items:center;gap:12px;background:var(--blue-light);border-radius:var(--radius-sm);padding:10px 14px}
    .info-top-icon{width:30px;height:30px;background:var(--blue);border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;color:white}
    .info-top-icon svg{width:16px;height:16px}
    .info-top-text{font-size:11px;color:var(--text-2);line-height:1.4}
    .info-top-img{width:65px;height:65px;object-fit:contain;flex-shrink:0}

    /* Rubric */
    .rubric-card{background:var(--white);border-radius:var(--radius);box-shadow:var(--shadow-md);padding:14px}
    .rubric-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px}
    .rubric-desc{font-size:9.5px;color:var(--text-3);text-align:right;max-width:140px;line-height:1.4}
    
    .rubric-list{display:flex;flex-direction:column;gap:12px}
    .rubric-item{display:flex;align-items:center;gap:10px;padding-bottom:12px;border-bottom:1px solid var(--border)}
    .rubric-item:last-child{border-bottom:none;padding-bottom:0}
    .r-icon{width:28px;height:28px;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
    .r-icon svg{width:16px;height:16px}
    .r-num{width:18px;height:18px;border-radius:50%;background:var(--blue);color:white;font-size:10px;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0}
    .r-label{font-size:11.5px;font-weight:600;color:var(--text-2);flex:1}
    
    .skala-opts{display:flex;align-items:center;gap:4px;flex-shrink:0}
    .skala-btn{width:26px;height:26px;border-radius:6px;border:1.5px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:var(--text-3);background:var(--white);cursor:pointer;transition:all .2s}
    .skala-btn:hover{border-color:var(--blue-mid);background:var(--blue-light)}
    .skala-btn.active{background:var(--blue);color:white;border-color:var(--blue)}

    /* Reflection Textarea */
    .reflect-card{background:var(--white);border-radius:var(--radius);box-shadow:var(--shadow-md);padding:14px}
    .textarea-wrap{position:relative}
    .form-textarea{width:100%;height:100px;padding:12px;border:1.5px solid var(--border);border-radius:var(--radius-sm);font-family:inherit;font-size:12px;color:var(--text);resize:none;outline:none;line-height:1.5;transition:all .2s;background:var(--bg)}
    .form-textarea:focus{border-color:var(--blue);background:var(--white);box-shadow:0 0 0 3px rgba(26,86,219,.12)}
    .char-count{position:absolute;bottom:10px;right:12px;font-size:10px;color:var(--text-4);font-weight:500}

    /* Abad 21 Info */
    .abad21-card{display:flex;align-items:center;gap:12px;background:#F0FDF4;border:1.5px solid #BBF7D0;border-radius:var(--radius-sm);padding:12px}
    .abad21-icon{width:36px;height:36px;border-radius:10px;background:var(--green);display:flex;align-items:center;justify-content:center;flex-shrink:0;color:white}
    .abad21-icon svg{width:20px;height:20px}
    .abad21-text h4{font-size:11px;font-weight:700;color:var(--green);margin-bottom:2px}
    .abad21-text p{font-size:10px;color:var(--text-2);line-height:1.4}
    .abad21-img{width:60px;height:60px;object-fit:contain;flex-shrink:0}

    /* Score Summary */
    .score-card{background:var(--blue-light);border:1.5px solid var(--blue-mid);border-radius:var(--radius-sm);padding:10px;display:flex;align-items:center;justify-content:center;gap:10px}
    .score-card svg{width:16px;height:16px;color:var(--blue)}
    .score-text{font-size:12px;color:var(--text-2);font-weight:500}
    .score-val{font-size:14px;font-weight:800;color:var(--blue)}

    /* Sticky bottom */
    .sticky-btn{position:fixed;bottom:var(--nav-h);left:50%;transform:translateX(-50%);width:100%;max-width:480px;background:var(--white);border-top:1px solid var(--border);padding:12px 16px;z-index:40}
    .btn-submit{width:100%;display:flex;align-items:center;justify-content:center;gap:10px;padding:15px;background:var(--blue);color:white;border:none;border-radius:var(--radius-sm);font-family:inherit;font-size:15px;font-weight:700;cursor:pointer;box-shadow:0 6px 20px rgba(26,86,219,.35);transition:all .25s}
    .btn-submit svg{width:20px;height:20px}
    .btn-submit:hover{background:var(--blue-dark);transform:translateY(-1px)}

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
    <a href="penilaian-psikomotor.php" class="back-btn" aria-label="Kembali">
      <svg viewBox="0 0 24 24" fill="none"><path d="M19 12H5M12 5l-7 7 7 7" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </a>
    <h1>Penilaian Afektif</h1>
    <p class="hero-subtitle">Kelas X-1 | Passing Bawah Bola Voli</p>
    <p class="hero-desc">Halaman ini berfokus pada sikap, disiplin, dan perilaku sosial selama pembelajaran.</p>
  </div>
  <img class="hero-img" src="siswa_hero.png" alt="Siswa PJOK"/>
</div>

<div class="content">

  <!-- Stepper -->
  <div class="stepper-card anim">
    <div class="stepper-label">Domain 3 dari 3</div>
    <div class="stepper-row">
      <div class="step">
        <div class="step-circle done"><svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
        <span class="step-name done">Kognitif</span>
      </div>
      <div class="step-line done"></div>
      <div class="step">
        <div class="step-circle done"><svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
        <span class="step-name done">Psikomotor</span>
      </div>
      <div class="step-line done"></div>
      <div class="step">
        <div class="step-circle active">3</div>
        <span class="step-name active">Afektif</span>
      </div>
    </div>
  </div>

  <!-- Info Top -->
  <div class="info-top anim">
    <div class="info-top-icon">
      <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/><path d="M12 16v-4M12 8h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
    </div>
    <p class="info-top-text">Halaman ini dapat diisi berdasarkan pengamatan diri sendiri dan interaksi dengan teman selama kegiatan berlangsung.</p>
    <img class="info-top-img" src="high_five.png" alt="Kerja sama"/>
  </div>

  <!-- Rubric -->
  <div class="rubric-card anim">
    <div class="rubric-header">
      <div class="sec-title" style="margin:0;color:var(--green)">
        <svg viewBox="0 0 24 24" fill="none"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z" stroke="currentColor" stroke-width="2"/></svg>
        Rubrik Penilaian Afektif
      </div>
      <div class="rubric-desc">Skala 1–4: 1 = belum tampak,<br/>2 = mulai tampak, 3 = baik, 4 = sangat baik.</div>
    </div>
    
    <div class="rubric-list">
      <div class="rubric-item">
        <div class="r-icon" style="background:#DCFCE7;color:#16A34A"><svg viewBox="0 0 24 24" fill="none"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/></svg></div>
        <div class="r-num">1</div>
        <div class="r-label">Jujur dalam menilai diri</div>
        <div class="skala-opts">
          <div class="skala-btn" onclick="setSkala(this)">1</div><div class="skala-btn" onclick="setSkala(this)">2</div><div class="skala-btn" onclick="setSkala(this)">3</div><div class="skala-btn active" onclick="setSkala(this)">4</div>
        </div>
      </div>
      <div class="rubric-item">
        <div class="r-icon" style="background:#DBEAFE;color:#1A56DB"><svg viewBox="0 0 24 24" fill="none"><rect x="8" y="2" width="8" height="4" rx="1" stroke="currentColor" stroke-width="2"/><rect x="4" y="4" width="16" height="18" rx="2" stroke="currentColor" stroke-width="2"/><path d="M9 14h6M9 18h6M9 10h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></div>
        <div class="r-num">2</div>
        <div class="r-label">Disiplin mengikuti instruksi</div>
        <div class="skala-opts">
          <div class="skala-btn" onclick="setSkala(this)">1</div><div class="skala-btn" onclick="setSkala(this)">2</div><div class="skala-btn active" onclick="setSkala(this)">3</div><div class="skala-btn" onclick="setSkala(this)">4</div>
        </div>
      </div>
      <div class="rubric-item">
        <div class="r-icon" style="background:#FEF3C7;color:#D97706"><svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="7" r="4" stroke="currentColor" stroke-width="2"/><path d="M4 21v-2a4 4 0 014-4h8a4 4 0 014 4v2" stroke="currentColor" stroke-width="2"/><path d="M19 8l2 2m0-2l-2 2M16 4l2 2m0-2l-2 2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></div>
        <div class="r-num">3</div>
        <div class="r-label">Percaya diri saat praktik</div>
        <div class="skala-opts">
          <div class="skala-btn" onclick="setSkala(this)">1</div><div class="skala-btn active" onclick="setSkala(this)">2</div><div class="skala-btn" onclick="setSkala(this)">3</div><div class="skala-btn" onclick="setSkala(this)">4</div>
        </div>
      </div>
      <div class="rubric-item">
        <div class="r-icon" style="background:#F3E8FF;color:#7C3AED"><svg viewBox="0 0 24 24" fill="none"><circle cx="9" cy="7" r="4" stroke="currentColor" stroke-width="2"/><circle cx="15" cy="7" r="4" stroke="currentColor" stroke-width="2"/><path d="M3 21v-2a4 4 0 014-4h10a4 4 0 014 4v2" stroke="currentColor" stroke-width="2"/></svg></div>
        <div class="r-num">4</div>
        <div class="r-label">Kerja sama dengan teman</div>
        <div class="skala-opts">
          <div class="skala-btn" onclick="setSkala(this)">1</div><div class="skala-btn" onclick="setSkala(this)">2</div><div class="skala-btn" onclick="setSkala(this)">3</div><div class="skala-btn active" onclick="setSkala(this)">4</div>
        </div>
      </div>
      <div class="rubric-item">
        <div class="r-icon" style="background:#FFEDD5;color:#EA580C"><svg viewBox="0 0 24 24" fill="none"><path d="M6 4h12v5c0 3.3-2.7 6-6 6s-6-2.7-6-6V4z" stroke="currentColor" stroke-width="2"/><path d="M12 15v6M8 21h8" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></div>
        <div class="r-num">5</div>
        <div class="r-label">Tanggung jawab/sportivitas</div>
        <div class="skala-opts">
          <div class="skala-btn" onclick="setSkala(this)">1</div><div class="skala-btn" onclick="setSkala(this)">2</div><div class="skala-btn active" onclick="setSkala(this)">3</div><div class="skala-btn" onclick="setSkala(this)">4</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Reflection Textarea -->
  <div class="reflect-card anim">
    <div class="sec-title" style="color:var(--blue);margin-bottom:4px">
      <svg viewBox="0 0 24 24" fill="none"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2v10z" stroke="currentColor" stroke-width="2"/><circle cx="8" cy="10" r="1" fill="currentColor"/><circle cx="12" cy="10" r="1" fill="currentColor"/><circle cx="16" cy="10" r="1" fill="currentColor"/></svg>
      Refleksi Sikap
    </div>
    <p style="font-size:10px;color:var(--text-3);margin-bottom:12px">Tuliskan bagaimana sikapmu selama kegiatan dan hal apa yang perlu kamu tingkatkan.</p>
    <div class="textarea-wrap">
      <textarea class="form-textarea" placeholder="Tulis refleksi di sini..." oninput="updateChar(this)">Saya berusaha disiplin dan fokus saat latihan, sudah bekerja sama dengan teman, namun saya masih perlu lebih percaya diri ketika melakukan passing bawah.</textarea>
      <span class="char-count" id="char-val">155/300</span>
    </div>
  </div>

  <!-- Abad 21 Info -->
  <div class="abad21-card anim">
    <div class="abad21-icon">
      <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="7" r="4" stroke="currentColor" stroke-width="2"/><path d="M4 21v-2a4 4 0 014-4h8a4 4 0 014 4v2" stroke="currentColor" stroke-width="2"/></svg>
    </div>
    <div class="abad21-text">
      <h4>Keterampilan Abad 21</h4>
      <p>Sikap positif mendukung kolaborasi dan komunikasi efektif dalam mencapai tujuan bersama.</p>
    </div>
    <img class="abad21-img" src="chatting_students.png" alt="Abad 21"/>
  </div>

  <!-- Score Summary -->
  <div class="score-card anim">
    <svg viewBox="0 0 24 24" fill="none"><path d="M12 20v-6M9 17l3 3 3-3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M3 10h18M5 10v10a2 2 0 002 2h10a2 2 0 002-2V10M7 3v4M17 3v4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
    <span class="score-text">Skor Afektif Saat Ini <span class="score-val" id="score-val">16</span> dari <strong style="color:var(--text-2)">20</strong> (Skala 1–4)</span>
  </div>

</div>

<!-- Sticky Button -->
<div class="sticky-btn">
  <button class="btn-submit" onclick="submitSemua()">
    <svg viewBox="0 0 24 24" fill="none"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
    Kirim Semua Penilaian
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
    const parent = el.parentElement;
    parent.querySelectorAll('.skala-btn').forEach(b => b.classList.remove('active'));
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
  function submitSemua(){
    const btn = document.querySelector('.btn-submit');
    if(btn) { btn.disabled = true; btn.innerHTML = 'Menyimpan...'; }
    
    const nilai = document.getElementById('score-val').textContent;
    const jurnal = document.querySelector('.form-textarea').value;

    fetch('penilaian-afektif.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        action: 'save_afektif',
        nilai: nilai,
        jurnal: jurnal
      })
    }).then(r=>r.json()).then(data=>{
      if(data.success){
        showToast('🚀 Mengirim semua penilaian...');
        setTimeout(()=>{window.location.href='aktivitas-siswa.php';},1500);
      } else {
        showToast('❌ Gagal menyimpan');
        if(btn) { btn.disabled = false; btn.innerHTML = 'Kirim Semua Penilaian'; }
      }
    }).catch(err=>{
        showToast('❌ Kesalahan jaringan');
        if(btn) { btn.disabled = false; btn.innerHTML = 'Kirim Semua Penilaian'; }
    });
  }
  function showToast(m){const t=document.getElementById('toast');t.textContent=m;t.classList.add('show');clearTimeout(t._t);t._t=setTimeout(()=>t.classList.remove('show'),3000);}
  
  calcScore();
</script>
</body>
</html>
