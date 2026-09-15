<?php
require_once 'auth.php';
wajibLoginSiswa();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_kognitif') {
    require 'koneksi.php';
    header('Content-Type: application/json');
    $jawaban = $_POST['jawaban'] ?? '{}';
    $siswa_id = $_SESSION['siswa_id'];
    
    // Calculate simple score
    $ans_arr = json_decode($jawaban, true);
    $nilai = 0;
    if (isset($ans_arr['q1']) && $ans_arr['q1'] === 'B') $nilai += 50;
    if (isset($ans_arr['q2']) && $ans_arr['q2'] === 'B') $nilai += 50;
    
    $stmt = $conn->prepare("SELECT id FROM penilaian_kognitif WHERE siswa_id = ?");
    $stmt->bind_param("i", $siswa_id);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($res->num_rows > 0) {
        $stmt_upd = $conn->prepare("UPDATE penilaian_kognitif SET nilai_total = ?, jawaban_detail = ? WHERE siswa_id = ?");
        $stmt_upd->bind_param("isi", $nilai, $jawaban, $siswa_id);
        $stmt_upd->execute();
    } else {
        $stmt_ins = $conn->prepare("INSERT INTO penilaian_kognitif (siswa_id, nilai_total, jawaban_detail) VALUES (?, ?, ?)");
        $stmt_ins->bind_param("iis", $siswa_id, $nilai, $jawaban);
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
  <title>Penilaian Kognitif – PADI-PJOK</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    :root{--blue:#1A56DB;--blue-dark:#1240A8;--blue-light:#EFF4FF;--blue-mid:#DBEAFE;--green:#16A34A;--green-light:#DCFCE7;--orange:#EA580C;--orange-light:#FFF7ED;--yellow:#D97706;--yellow-light:#FFFBEB;--text:#111827;--text-2:#374151;--text-3:#6B7280;--text-4:#9CA3AF;--border:#E5E7EB;--bg:#F3F6FB;--white:#FFFFFF;--nav-h:68px;--radius:14px;--radius-sm:10px;--shadow:0 2px 8px rgba(0,0,0,.07);--shadow-md:0 4px 18px rgba(0,0,0,.10)}
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
    .step-name.done{color:var(--green)}
    .step-name.active{color:var(--blue)}
    .step-name.todo{color:var(--text-4)}
    .step-check{color:var(--green)}
    .step-check svg{width:12px;height:12px}
    .step-line{flex:1;height:2.5px;margin-top:14px;border-radius:2px}
    .step-line.done{background:var(--green)}
    .step-line.todo{background:var(--border)}

    /* Section Title */
    .sec-title{display:flex;align-items:center;gap:8px;font-size:14px;font-weight:800;color:var(--text);margin-bottom:12px}
    .sec-title svg{width:20px;height:20px}

    /* Tujuan + Video two col */
    .two-col{display:grid;grid-template-columns:1fr 1fr;gap:12px;align-items:start}
    .card{background:var(--white);border-radius:var(--radius);box-shadow:var(--shadow-md);padding:14px}

    /* Tujuan list */
    .goal-list{display:flex;flex-direction:column;gap:7px}
    .goal-item{display:flex;align-items:flex-start;gap:7px;font-size:11px;color:var(--text-2);line-height:1.4}
    .goal-check{width:16px;height:16px;border-radius:50%;background:var(--green);display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:1px}
    .goal-check svg{width:10px;height:10px;color:white}

    /* Stimulus Videos */
    .video-pair{display:flex;flex-direction:column;gap:6px}
    .vthumb{border-radius:8px;overflow:hidden;position:relative;cursor:pointer}
    .vthumb img{width:100%;height:60px;object-fit:cover;display:block}
    .vthumb-play{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.3)}
    .vthumb-play svg{width:20px;height:20px;color:white}
    .vthumb-dur{position:absolute;bottom:4px;right:4px;background:rgba(0,0,0,.6);color:white;font-size:8px;padding:2px 4px;border-radius:3px;font-weight:600}
    .vthumb-badge{display:flex;align-items:center;gap:4px;font-size:9px;font-weight:700;justify-content:center;margin-top:2px}
    .vthumb-badge.green{color:var(--green)}
    .vthumb-badge.red{color:#DC2626}
    .vthumb-badge svg{width:10px;height:10px}

    /* Quiz */
    .quiz-card{background:var(--white);border-radius:var(--radius);box-shadow:var(--shadow-md);padding:14px}
    .quiz-header{display:flex;align-items:center;gap:8px;margin-bottom:12px}
    .quiz-badge{background:var(--blue-light);color:var(--blue);font-size:10px;font-weight:700;padding:4px 10px;border-radius:20px}
    .soal-label{font-size:11px;font-weight:700;color:var(--blue);background:var(--blue-light);padding:5px 10px;border-radius:6px;margin-bottom:8px;display:inline-block}
    .soal-label.hots{color:var(--orange);background:var(--orange-light)}
    .soal-q{font-size:13px;font-weight:700;color:var(--text);margin-bottom:12px;line-height:1.5}
    .options{display:flex;flex-direction:column;gap:8px;margin-bottom:16px}
    .option{display:flex;align-items:center;gap:10px;padding:12px 14px;border:1.5px solid var(--border);border-radius:var(--radius-sm);cursor:pointer;transition:all .2s}
    .option:hover{border-color:var(--blue-mid);background:var(--blue-light)}
    .option.selected{background:var(--blue);border-color:var(--blue)}
    .opt-letter{width:26px;height:26px;border-radius:50%;border:1.5px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0;transition:all .2s}
    .option.selected .opt-letter{background:rgba(255,255,255,.25);border-color:rgba(255,255,255,.4);color:white}
    .opt-text{font-size:12px;font-weight:500;color:var(--text-2);line-height:1.4;transition:color .2s}
    .option.selected .opt-text{color:white;font-weight:600}

    /* HOTS divider */
    .soal-divider{height:1px;background:var(--border);margin:4px 0 12px}

    /* Info box */
    .info-box{display:flex;align-items:flex-start;gap:10px;background:var(--yellow-light);border:1.5px solid #FDE68A;border-radius:var(--radius-sm);padding:12px 14px}
    .info-box-icon{width:32px;height:32px;border-radius:8px;background:#FEF3C7;display:flex;align-items:center;justify-content:center;flex-shrink:0}
    .info-box-icon svg{width:18px;height:18px;color:var(--yellow)}
    .info-box-title{font-size:12px;font-weight:700;color:var(--yellow);margin-bottom:3px}
    .info-box-desc{font-size:11px;color:#92400E;line-height:1.5}
    .info-box-img{width:60px;height:60px;object-fit:contain;flex-shrink:0}

    /* Progress bar */
    .progress-bar-card{background:var(--white);border-radius:var(--radius);box-shadow:var(--shadow);padding:14px 16px;display:flex;align-items:center;gap:10px}
    .progress-bar-card svg{width:18px;height:18px;color:var(--blue);flex-shrink:0}
    .prog-text{font-size:13px;font-weight:700;color:var(--text);flex:1}
    .prog-text span{color:var(--blue)}

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
    <a href="aktivitas-siswa.php" class="back-btn" aria-label="Kembali">
      <svg viewBox="0 0 24 24" fill="none"><path d="M19 12H5M12 5l-7 7 7 7" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </a>
    <h1>Penilaian Kognitif</h1>
    <p class="hero-subtitle">Kelas X-1 | Passing Bawah Bola Voli</p>
    <p class="hero-desc">Halaman ini mengukur pemahaman siswa terhadap materi melalui soal objektif dan soal berpikir tinggi (HOTS).</p>
  </div>
  <img class="hero-img" src="siswa_hero.png" alt="Siswa PJOK"/>
</div>

<div class="content">

  <!-- Stepper -->
  <div class="stepper-card anim">
    <div class="stepper-label">Domain 1 dari 3</div>
    <div class="stepper-row">
      <div class="step">
        <div class="step-circle active">1</div>
        <span class="step-name active">Kognitif</span>
      </div>
      <div class="step-line todo"></div>
      <div class="step">
        <div class="step-circle todo">2</div>
        <span class="step-name todo">Psikomotor</span>
      </div>
      <div class="step-line todo"></div>
      <div class="step">
        <div class="step-circle todo">3</div>
        <span class="step-name todo">Afektif</span>
      </div>
    </div>
  </div>

  <!-- Tujuan + Stimulus -->
  <div class="two-col anim">
    <div class="card">
      <div class="sec-title" style="margin-bottom:10px">
        <svg viewBox="0 0 24 24" fill="none" style="color:var(--blue)"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/><path d="M9 12l2 2 4-4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        Tujuan Belajar
      </div>
      <div class="goal-list">
        <div class="goal-item"><div class="goal-check"><svg viewBox="0 0 24 24" fill="none"><path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></div>Memahami tujuan passing bawah.</div>
        <div class="goal-item"><div class="goal-check"><svg viewBox="0 0 24 24" fill="none"><path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></div>Mengenali posisi tubuh yang benar.</div>
        <div class="goal-item"><div class="goal-check"><svg viewBox="0 0 24 24" fill="none"><path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></div>Menganalisis kesalahan umum saat melakukan passing bawah.</div>
      </div>
    </div>
    <div class="card">
      <div class="sec-title" style="margin-bottom:10px">
        <svg viewBox="0 0 24 24" fill="none" style="color:var(--blue)"><rect x="2" y="5" width="15" height="14" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M17 9l5-3v12l-5-3V9z" stroke="currentColor" stroke-width="1.8"/></svg>
        Stimulus Pembelajaran
      </div>
      <div class="video-pair">
        <div class="vthumb" onclick="showToast('Memutar contoh benar')">
          <img src="video_benar.png" alt="Contoh Benar"/>
          <div class="vthumb-play"><svg viewBox="0 0 24 24" fill="none"><polygon points="5 3 19 12 5 21 5 3" fill="white"/></svg></div>
          <span class="vthumb-dur">0:36</span>
          <div style="position:absolute;top:5px;left:5px;width:18px;height:18px;background:#22C55E;border-radius:50%;display:flex;align-items:center;justify-content:center"><svg viewBox="0 0 24 24" fill="none" width="10" height="10"><path d="M20 6L9 17l-5-5" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
        </div>
        <div class="vthumb-badge green"><svg viewBox="0 0 24 24" fill="none"><path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg> Contoh Benar</div>
        <div class="vthumb" onclick="showToast('Memutar contoh salah')">
          <img src="video_salah.png" alt="Contoh Salah"/>
          <div class="vthumb-play"><svg viewBox="0 0 24 24" fill="none"><polygon points="5 3 19 12 5 21 5 3" fill="white"/></svg></div>
          <span class="vthumb-dur">0:36</span>
          <div style="position:absolute;top:5px;left:5px;width:18px;height:18px;background:#DC2626;border-radius:50%;display:flex;align-items:center;justify-content:center"><svg viewBox="0 0 24 24" fill="none" width="10" height="10"><path d="M18 6L6 18M6 6l12 12" stroke="white" stroke-width="2.5" stroke-linecap="round"/></svg></div>
        </div>
        <div class="vthumb-badge red"><svg viewBox="0 0 24 24" fill="none"><path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/></svg> Contoh Salah</div>
      </div>
    </div>
  </div>

  <!-- Tes Kognitif -->
  <div class="quiz-card anim">
    <div class="quiz-header">
      <div class="sec-title" style="margin:0">
        <svg viewBox="0 0 24 24" fill="none" style="color:var(--blue)"><path d="M12 2C8 2 5 5 5 9c0 2.5 1.3 4.7 3.2 6H12h3.8C17.7 13.7 19 11.5 19 9c0-4-3-7-7-7z" stroke="currentColor" stroke-width="1.8"/><path d="M9 21h6M10 17h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
        Tes Kognitif
      </div>
      <span class="quiz-badge">Pilihan Ganda & HOTS</span>
    </div>

    <!-- Soal 1 -->
    <span class="soal-label">Soal 1 – Pilihan Ganda</span>
    <p class="soal-q">Apa tujuan utama passing bawah dalam permainan bola voli?</p>
    <div class="options" id="q1">
      <div class="option" onclick="selectOption(this,'q1')" data-val="A"><span class="opt-letter">A</span><span class="opt-text">Mencetak poin sebanyak-banyaknya.</span></div>
      <div class="option selected" onclick="selectOption(this,'q1')" data-val="B"><span class="opt-letter">B</span><span class="opt-text">Menerima servis lawan agar bola tetap dapat dimainkan.</span></div>
      <div class="option" onclick="selectOption(this,'q1')" data-val="C"><span class="opt-letter">C</span><span class="opt-text">Melakukan serangan pertama ke area lawan.</span></div>
      <div class="option" onclick="selectOption(this,'q1')" data-val="D"><span class="opt-letter">D</span><span class="opt-text">Menghalangi smes lawan di dekat net.</span></div>
    </div>

    <div class="soal-divider"></div>

    <!-- Soal 2 -->
    <span class="soal-label hots">Soal 2 – HOTS</span>
    <p class="soal-q">Setelah melihat video contoh, bagian mana yang paling perlu diperbaiki ketika arah bola sering terlalu tinggi saat passing bawah?</p>
    <div class="options" id="q2">
      <div class="option" onclick="selectOption(this,'q2')" data-val="A"><span class="opt-letter">A</span><span class="opt-text">Posisi kaki yang terlalu rapat.</span></div>
      <div class="option selected" onclick="selectOption(this,'q2')" data-val="B"><span class="opt-letter">B</span><span class="opt-text">Ayunan lengan yang kurang penuh dan perkenaan bola.</span></div>
      <div class="option" onclick="selectOption(this,'q2')" data-val="C"><span class="opt-letter">C</span><span class="opt-text">Pandangan mata yang tidak fokus ke arah bola.</span></div>
      <div class="option" onclick="selectOption(this,'q2')" data-val="D"><span class="opt-letter">D</span><span class="opt-text">Posisi badan yang terlalu tegak saat menerima bola.</span></div>
    </div>
  </div>

  <!-- HOTS info -->
  <div class="info-box anim">
    <div class="info-box-icon"><svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.8"/><path d="M12 1v3M12 20v3M4.22 4.22l2.12 2.12M17.66 17.66l2.12 2.12M1 12h3M20 12h3M4.22 19.78l2.12-2.12M17.66 6.34l2.12-2.12" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></div>
    <div>
      <p class="info-box-title">Latihan Berpikir Kritis</p>
      <p class="info-box-desc">Soal-soal ini dirancang untuk melatih kemampuanmu menganalisis, mengevaluasi, dan mengomunikasikan pemahaman sebagai bagian dari pembelajaran abad 21.</p>
    </div>
    <img class="info-box-img" src="siswa_hero.png" alt=""/>
  </div>

  <!-- Progress -->
  <div class="progress-bar-card anim">
    <svg viewBox="0 0 24 24" fill="none"><rect x="5" y="2" width="14" height="20" rx="2" stroke="currentColor" stroke-width="2"/><path d="M9 7h6M9 11h6M9 15h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
    <span class="prog-text">Progress Jawaban <span id="prog-val">2</span> dari 5 soal</span>
  </div>

</div>

<!-- Sticky Button -->
<div class="sticky-btn">
  <button class="btn-lanjut" onclick="nextSoal()">
    Lanjut ke Soal Berikutnya
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
  let answers={};
  function selectOption(el,group){
    document.querySelectorAll('#'+group+' .option').forEach(o=>o.classList.remove('selected'));
    el.classList.add('selected');
    answers[group]=el.dataset.val;
    updateProg();
  }
  function updateProg(){
    document.getElementById('prog-val').textContent=Object.keys(answers).length;
  }
  function nextSoal(){
    const btn = document.querySelector('.btn-lanjut');
    btn.disabled = true;
    btn.innerHTML = 'Menyimpan...';

    fetch('penilaian-kognitif.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        action: 'save_kognitif',
        jawaban: JSON.stringify(answers)
      })
    }).then(r=>r.json()).then(data=>{
      if(data.success){
        showToast('✅ Jawaban tersimpan! Memuat soal berikutnya...');
        setTimeout(()=>{window.location.href='penilaian-psikomotor.php';},1500);
      } else {
        showToast('❌ Gagal menyimpan jawaban');
        btn.disabled = false;
        btn.innerHTML = 'Lanjut ke Soal Berikutnya';
      }
    }).catch(err=>{
        showToast('❌ Kesalahan jaringan');
        btn.disabled = false;
        btn.innerHTML = 'Lanjut ke Soal Berikutnya';
    });
  }
  function showToast(m){const t=document.getElementById('toast');t.textContent=m;t.classList.add('show');clearTimeout(t._t);t._t=setTimeout(()=>t.classList.remove('show'),3000);}
</script>
</body>
</html>
