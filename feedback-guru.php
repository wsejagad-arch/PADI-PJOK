<?php
require_once 'auth.php';
wajibLoginGuru();
require_once 'koneksi.php';

// Ambil video dari siswa untuk ditinjau guru (Mock data untuk demo, misalnya siswa_id = 1)
$siswa_id_demo = 1; 
$cek_video = $conn->prepare("SELECT video_path FROM penilaian_psikomotor WHERE siswa_id = ?");
$cek_video->bind_param("i", $siswa_id_demo);
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
  <title>Feedback Guru – PADI-PJOK</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    :root{--blue:#1A56DB;--blue-dark:#1240A8;--blue-light:#EFF4FF;--blue-mid:#DBEAFE;--green:#16A34A;--green-light:#DCFCE7;--orange:#EA580C;--orange-light:#FFF7ED;--yellow:#D97706;--yellow-light:#FFFBEB;--purple:#7C3AED;--purple-light:#F5F3FF;--pink:#DB2777;--pink-light:#FDF2F8;--text:#111827;--text-2:#374151;--text-3:#6B7280;--text-4:#9CA3AF;--border:#E5E7EB;--bg:#F3F6FB;--white:#FFFFFF;--nav-h:68px;--radius:14px;--radius-sm:10px;--shadow:0 2px 8px rgba(0,0,0,.07);--shadow-md:0 4px 18px rgba(0,0,0,.10)}
    body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);max-width:480px;margin:0 auto;padding-bottom:calc(var(--nav-h) + 80px)}

    /* Topbar */
    .topbar{position:sticky;top:0;z-index:50;display:flex;align-items:center;justify-content:space-between;padding:14px 20px;background:var(--white);border-bottom:1px solid var(--border);box-shadow:var(--shadow)}
    .topbar-logo{display:flex;align-items:center;gap:8px}
    .topbar-logo svg{width:26px;height:26px}
    .topbar-logo span{font-size:16px;font-weight:800;color:var(--blue)}
    .notif-btn{position:relative;width:36px;height:36px;background:var(--blue-light);border:none;border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer}
    .notif-btn svg{width:20px;height:20px;color:var(--blue)}
    .notif-dot{position:absolute;top:6px;right:6px;width:8px;height:8px;background:#EF4444;border-radius:50%;border:2px solid var(--white)}

    /* Hero */
    .hero{background:linear-gradient(135deg,#EFF6FF 0%,#DBEAFE 60%,#E0F2FE 100%);padding:20px 20px 0;display:flex;align-items:flex-end;min-height:155px;position:relative;overflow:hidden}
    .hero-text{flex:1;padding-bottom:20px;z-index:1}
    .hero-text h1{font-size:22px;font-weight:800;color:var(--text);margin-bottom:4px}
    .hero-subtitle{font-size:13px;font-weight:700;color:var(--blue);margin-bottom:6px}
    .student-row{display:flex;align-items:center;gap:8px}
    .student-row span{font-size:13px;font-weight:700;color:var(--text-2)}
    .badge-hadir{background:var(--green-light);color:var(--green);font-size:10px;font-weight:700;padding:3px 8px;border-radius:10px}
    .hero-img{width:120px;height:140px;object-fit:contain;object-position:bottom;flex-shrink:0;z-index:1}

    .content{padding:16px;display:flex;flex-direction:column;gap:14px}

    /* Ringkasan Penilaian */
    .ringkasan-card{background:var(--white);border-radius:var(--radius);box-shadow:var(--shadow-md);padding:16px}
    .section-title{font-size:14px;font-weight:700;color:var(--text);margin-bottom:12px}
    .ringkasan-row{display:flex;gap:12px;overflow-x:auto;scrollbar-width:none;padding-bottom:2px}
    .ringkasan-row::-webkit-scrollbar{display:none}
    .r-score{flex-shrink:0;text-align:center}
    .r-score-icon{width:28px;height:28px;border-radius:8px;display:flex;align-items:center;justify-content:center;margin:0 auto 4px}
    .r-score-icon svg{width:16px;height:16px}
    .r-score-icon.blue{background:var(--blue-light);color:var(--blue)}
    .r-score-icon.green{background:var(--green-light);color:var(--green)}
    .r-score-icon.purple{background:var(--purple-light);color:var(--purple)}
    .r-score-icon.orange{background:var(--orange-light);color:var(--orange)}
    .r-score-icon.pink{background:var(--pink-light);color:var(--pink)}
    .r-score-label{font-size:9px;color:var(--text-3);font-weight:600;margin-bottom:2px}
    .r-score-val{font-size:16px;font-weight:800}
    .r-score-val.blue{color:var(--blue)}
    .r-score-val.green{color:var(--green)}
    .r-score-val.purple{color:var(--purple)}
    .r-score-val.orange{color:var(--orange)}
    .r-score-val.pink{color:var(--pink)}
    .r-score-desc{font-size:9px;font-weight:600}
    .r-score-desc.blue{color:var(--blue)}
    .r-score-desc.green{color:var(--green)}
    .r-score-desc.orange{color:var(--orange)}

    /* Temuan + AI */
    .two-col{display:grid;grid-template-columns:1fr 1fr;gap:10px;align-items:start}
    .temuan-card{background:var(--white);border-radius:var(--radius);box-shadow:var(--shadow-md);padding:14px}
    .temuan-list{display:flex;flex-direction:column;gap:7px}
    .temuan-item{display:flex;align-items:flex-start;gap:7px;font-size:11px;color:var(--text-2);line-height:1.4}
    .temuan-icon{flex-shrink:0;margin-top:1px}
    .temuan-icon.done{color:var(--green)}
    .temuan-icon.warn{color:var(--orange)}
    .temuan-icon svg{width:14px;height:14px}

    .ai-help-card{background:var(--blue-light);border:1.5px solid var(--blue-mid);border-radius:var(--radius);padding:14px;display:flex;flex-direction:column;gap:10px}
    .ai-help-header{display:flex;align-items:center;gap:6px}
    .ai-help-icon{width:28px;height:28px;border-radius:8px;background:linear-gradient(135deg,#6366F1,#8B5CF6);display:flex;align-items:center;justify-content:center;flex-shrink:0}
    .ai-help-icon svg{width:16px;height:16px;color:white}
    .ai-help-title{font-size:12px;font-weight:700;color:var(--text)}
    .ai-help-desc{font-size:11px;color:var(--text-3);line-height:1.5}
    .btn-ai{display:flex;align-items:center;justify-content:center;gap:6px;padding:10px;background:var(--blue);color:var(--white);border:none;border-radius:var(--radius-sm);font-family:inherit;font-size:12px;font-weight:700;cursor:pointer;transition:all .2s;box-shadow:0 4px 12px rgba(26,86,219,.3)}
    .btn-ai:hover{background:var(--blue-dark)}
    .btn-ai svg{width:16px;height:16px}

    /* Draft Feedback */
    .draft-card{background:var(--white);border-radius:var(--radius);box-shadow:var(--shadow-md);padding:16px}
    .draft-header{display:flex;align-items:center;gap:8px;margin-bottom:12px}
    .draft-ai-icon{width:32px;height:32px;border-radius:8px;background:linear-gradient(135deg,#6366F1,#8B5CF6);display:flex;align-items:center;justify-content:center}
    .draft-ai-icon svg{width:18px;height:18px;color:white}
    .draft-title{font-size:14px;font-weight:700;color:var(--text)}
    .draft-text{font-size:12px;color:var(--text-2);line-height:1.7;background:var(--bg);border-radius:var(--radius-sm);padding:12px;margin-bottom:14px;border:1px solid var(--border)}

    /* Gaya Feedback */
    .gaya-label{font-size:12px;font-weight:700;color:var(--text-2);margin-bottom:8px}
    .gaya-tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px}
    .gaya-tab{padding:7px 14px;border-radius:20px;font-family:inherit;font-size:12px;font-weight:600;border:1.5px solid var(--border);background:var(--white);color:var(--text-3);cursor:pointer;transition:all .2s;display:flex;align-items:center;gap:5px}
    .gaya-tab svg{width:14px;height:14px}
    .gaya-tab.active{background:var(--purple-light);border-color:var(--purple);color:var(--purple)}
    .gaya-tab.active svg{color:var(--purple)}

    /* Edit area */
    .edit-label{display:flex;align-items:center;gap:6px;font-size:12px;font-weight:700;color:var(--text-2);margin-bottom:8px}
    .edit-label svg{width:16px;height:16px;color:var(--blue)}
    .edit-textarea{width:100%;min-height:160px;padding:12px;border:1.5px solid var(--border);border-radius:var(--radius-sm);font-family:inherit;font-size:12px;color:var(--text);line-height:1.7;resize:vertical;outline:none;transition:border-color .2s}
    .edit-textarea:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(26,86,219,.1)}
    .char-count{text-align:right;font-size:11px;color:var(--text-4);margin-top:4px}

    /* Info note */
    .info-note{display:flex;align-items:flex-start;gap:10px;background:var(--blue-light);border:1px solid var(--blue-mid);border-radius:var(--radius-sm);padding:12px 14px}
    .info-note svg{width:18px;height:18px;color:var(--blue);flex-shrink:0;margin-top:1px}
    .info-note p{font-size:11px;color:var(--text-3);line-height:1.6}

    /* Bottom Actions (sticky) */
    .sticky-actions{position:fixed;bottom:var(--nav-h);left:50%;transform:translateX(-50%);width:100%;max-width:480px;background:var(--white);border-top:1px solid var(--border);padding:12px 16px;display:grid;grid-template-columns:1fr 1fr;gap:10px;z-index:40}
    .btn-action{display:flex;align-items:center;justify-content:center;gap:8px;padding:13px;border-radius:var(--radius-sm);font-family:inherit;font-size:13px;font-weight:700;cursor:pointer;transition:all .2s}
    .btn-action svg{width:18px;height:18px}
    .btn-sec{background:var(--white);border:1.5px solid var(--border);color:var(--text-2)}
    .btn-sec:hover{border-color:var(--blue);color:var(--blue)}
    .btn-pri{background:var(--blue);border:none;color:var(--white);box-shadow:0 4px 14px rgba(26,86,219,.35)}
    .btn-pri:hover{background:var(--blue-dark);transform:translateY(-1px)}

    /* Bottom Nav */
    .bottom-nav{position:fixed;bottom:0;left:50%;transform:translateX(-50%);width:100%;max-width:480px;background:var(--white);border-top:1px solid var(--border);display:flex;box-shadow:0 -4px 16px rgba(0,0,0,.08);z-index:50;height:var(--nav-h)}
    .nav-item{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px;cursor:pointer;border:none;background:transparent;font-family:inherit;padding:8px 4px;color:var(--text-4);text-decoration:none;position:relative}
    .nav-item svg{width:22px;height:22px}
    .nav-item span{font-size:10px;font-weight:600}
    .nav-item.active{color:var(--blue)}
    .nav-indicator{position:absolute;top:0;left:50%;transform:translateX(-50%) scaleX(0);width:32px;height:3px;background:var(--blue);border-radius:0 0 4px 4px;transition:transform .25s}
    .nav-item.active .nav-indicator{transform:translateX(-50%) scaleX(1)}

    .toast{position:fixed;bottom:calc(var(--nav-h) + 90px);left:50%;transform:translateX(-50%) translateY(80px);background:#111827;color:#fff;padding:11px 20px;border-radius:10px;font-size:13px;font-weight:500;box-shadow:0 8px 30px rgba(0,0,0,.25);z-index:999;transition:transform .35s cubic-bezier(.22,1,.36,1);white-space:nowrap}
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
  <button class="notif-btn" onclick="showToast('Notifikasi baru')" aria-label="Notifikasi">
    <svg viewBox="0 0 24 24" fill="none"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M13.73 21a2 2 0 01-3.46 0" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
    <span class="notif-dot"></span>
  </button>
</header>

<div class="hero">
  <div class="hero-text">
    <h1>Feedback Guru</h1>
    <p class="hero-subtitle">Kelas X-1 | Passing Bawah Bola Voli</p>
    <div class="student-row">
      <span>Ahmad Fajar</span>
      <span class="badge-hadir">Hadir</span>
    </div>
  </div>
  <img class="hero-img" src="guru_dashboard_hero.png" alt="Guru PJOK"/>
</div>

<div class="content">

  <!-- Ringkasan Penilaian -->
  <div class="ringkasan-card anim">
    <p class="section-title">Ringkasan Penilaian</p>
    <div class="ringkasan-row">
      <div class="r-score">
        <div class="r-score-icon blue"><svg viewBox="0 0 24 24" fill="none"><path d="M12 2C8 2 5 5 5 9c0 2.5 1.3 4.7 3.2 6H12h3.8C17.7 13.7 19 11.5 19 9c0-4-3-7-7-7z" stroke="currentColor" stroke-width="1.8"/><path d="M9 21h6M10 17h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></div>
        <div class="r-score-label">Kognitif</div>
        <div class="r-score-val blue">80</div>
      </div>
      <div class="r-score">
        <div class="r-score-icon green"><svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="5" r="2.5" stroke="currentColor" stroke-width="1.8"/><path d="M12 8v5l-3 4M12 13l3 4M8 10l-3 2M16 10l3 2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></div>
        <div class="r-score-label">Psikomotor</div>
        <div class="r-score-val green">84</div>
      </div>
      <div class="r-score">
        <div class="r-score-icon purple"><svg viewBox="0 0 24 24" fill="none"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z" stroke="currentColor" stroke-width="1.8"/></svg></div>
        <div class="r-score-label">Afektif</div>
        <div class="r-score-val purple">Baik</div>
      </div>
      <div class="r-score">
        <div class="r-score-icon orange"><svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="8" r="4" stroke="currentColor" stroke-width="1.8"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M18 5l2 2-2 2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></div>
        <div class="r-score-label">Penilaian Diri</div>
        <div class="r-score-val orange" style="font-size:12px">Selesai</div>
      </div>
      <div class="r-score">
        <div class="r-score-icon pink"><svg viewBox="0 0 24 24" fill="none"><circle cx="9" cy="7" r="3" stroke="currentColor" stroke-width="1.8"/><circle cx="16" cy="7" r="3" stroke="currentColor" stroke-width="1.8"/><path d="M2 21c0-3.5 3-6 7-6h4c4 0 7 2.5 7 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></div>
        <div class="r-score-label">Penilaian Rekan</div>
        <div class="r-score-val pink" style="font-size:12px">Masuk</div>
      </div>
    </div>
  </div>

  <!-- Video Praktik Siswa -->
  <div class="ringkasan-card anim" style="margin-bottom:14px; padding:16px;">
    <p class="section-title">Video Praktik Siswa</p>
    <?php if (!empty($video_path)): ?>
      <div style="border-radius:10px; overflow:hidden; border:1px solid var(--border); height:220px; background:#000;">
        <iframe src="<?= htmlspecialchars($video_path) ?>" width="100%" height="100%" frameborder="0" allowfullscreen></iframe>
      </div>
    <?php else: ?>
      <div style="background:var(--bg); border:1px dashed var(--border); border-radius:10px; padding:20px; text-align:center;">
        <p style="font-size:12px; color:var(--text-3);">Siswa belum menautkan video praktik.</p>
      </div>
    <?php endif; ?>
  </div>

  <!-- Temuan Utama + Bantuan AI -->
  <div class="two-col">
    <div class="temuan-card anim">
      <p class="section-title">Temuan Utama</p>
      <div class="temuan-list">
        <div class="temuan-item">
          <span class="temuan-icon done"><svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" fill="#DCFCE7"/><path d="M9 12l2 2 4-4" stroke="#16A34A" stroke-width="2" stroke-linecap="round"/></svg></span>
          Memahami tujuan passing bawah dengan baik
        </div>
        <div class="temuan-item">
          <span class="temuan-icon done"><svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" fill="#DCFCE7"/><path d="M9 12l2 2 4-4" stroke="#16A34A" stroke-width="2" stroke-linecap="round"/></svg></span>
          Posisi kaki sudah cukup siap
        </div>
        <div class="temuan-item">
          <span class="temuan-icon warn"><svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="#EA580C" stroke-width="2" fill="#FFF7ED"/><path d="M12 8v4M12 16h.01" stroke="#EA580C" stroke-width="2" stroke-linecap="round"/></svg></span>
          Lutut masih kurang menekuk
        </div>
        <div class="temuan-item">
          <span class="temuan-icon done"><svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" fill="#DCFCE7"/><path d="M9 12l2 2 4-4" stroke="#16A34A" stroke-width="2" stroke-linecap="round"/></svg></span>
          Lengan perlu lebih rapat saat kontak bola
        </div>
        <div class="temuan-item">
          <span class="temuan-icon done"><svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" fill="#DCFCE7"/><path d="M9 12l2 2 4-4" stroke="#16A34A" stroke-width="2" stroke-linecap="round"/></svg></span>
          Sikap disiplin dan kerja sama baik
        </div>
      </div>
    </div>

    <div class="ai-help-card anim">
      <div class="ai-help-header">
        <div class="ai-help-icon"><svg viewBox="0 0 24 24" fill="none"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z" fill="rgba(255,255,255,.2)"/><path d="M9 12l2 2 4-4M12 7v1M12 16v1" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></div>
        <span class="ai-help-title">Bantuan AI</span>
      </div>
      <p class="ai-help-desc">AI membantu menyusun draft umpan balik. Guru tetap meninjau dan mengedit sebelum dikirim.</p>
      <button class="btn-ai" id="btn-ai-draft" onclick="generateDraft()">
        <svg viewBox="0 0 24 24" fill="none"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z" stroke="currentColor" stroke-width="2"/><path d="M9.5 9l3 3-3 3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        Buat Draft Feedback AI
      </button>
    </div>
  </div>

  <!-- Draft Feedback AI -->
  <div class="draft-card anim">
    <div class="draft-header">
      <div class="draft-ai-icon"><svg viewBox="0 0 24 24" fill="none"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z" fill="rgba(255,255,255,.2)"/><path d="M9 12l2 2 4-4M12 7v1M12 16v1" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></div>
      <span class="draft-title">Draft Feedback AI</span>
    </div>
    <div class="draft-text" id="draft-text">Ahmad sudah menunjukkan pemahaman yang cukup baik tentang tujuan passing bawah dan mampu mengikuti pembelajaran dengan disiplin. Berdasarkan video praktik, posisi kaki sudah cukup siap, namun lutut masih perlu lebih ditekuk dan lengan perlu lebih rapat saat kontak bola agar arah bola lebih stabil. Latihan berikutnya dapat difokuskan pada posisi tubuh rendah, perkenaan bola pada lengan bagian bawah, dan kontrol arah bola menuju target.</div>

    <div class="gaya-label">Gaya Feedback</div>
    <div class="gaya-tabs">
      <button class="gaya-tab" onclick="setGaya(this,'singkat')">
        <svg viewBox="0 0 24 24" fill="none"><line x1="4" y1="6" x2="20" y2="6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><line x1="4" y1="12" x2="14" y2="12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        Singkat
      </button>
      <button class="gaya-tab" onclick="setGaya(this,'detail')">
        <svg viewBox="0 0 24 24" fill="none"><line x1="4" y1="6" x2="20" y2="6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><line x1="4" y1="12" x2="20" y2="12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><line x1="4" y1="18" x2="16" y2="18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        Detail
      </button>
      <button class="gaya-tab active" onclick="setGaya(this,'motivatif')">
        <svg viewBox="0 0 24 24" fill="none"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z" stroke="currentColor" stroke-width="2"/></svg>
        Motivatif
      </button>
      <button class="gaya-tab" onclick="setGaya(this,'remedial')">
        <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/><path d="M12 8v4M12 16h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        Remedial
      </button>
    </div>

    <div class="edit-label">
      <svg viewBox="0 0 24 24" fill="none"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
      Edit Guru
    </div>
    <textarea class="edit-textarea" id="edit-area" maxlength="1000" oninput="updateChar()" placeholder="Tulis feedback untuk siswa...">Ahmad sudah menunjukkan pemahaman yang cukup baik tentang tujuan passing bawah dan mampu mengikuti pembelajaran dengan disiplin. Berdasarkan video praktik, posisi kaki sudah cukup siap, namun lutut masih perlu lebih ditekuk dan lengan perlu lebih rapat saat kontak bola agar arah bola lebih stabil. Latihan berikutnya dapat difokuskan pada posisi tubuh rendah, perkenaan bola pada lengan bagian bawah, dan kontrol arah bola menuju target.</textarea>
    <div class="char-count" id="char-count">457/1000</div>
  </div>

  <!-- Info Note -->
  <div class="info-note anim">
    <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/><path d="M12 16v-4M12 8h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
    <p>Feedback personal untuk setiap siswa mendukung <em>assessment for learning</em> dan pembelajaran abad 21 yang bermakna.</p>
  </div>

</div>

<!-- Sticky Action Buttons -->
<div class="sticky-actions">
  <button class="btn-action btn-sec" onclick="showToast('Draft disimpan!')">
    <svg viewBox="0 0 24 24" fill="none"><rect x="5" y="2" width="14" height="20" rx="2" stroke="currentColor" stroke-width="2"/><path d="M9 7h6M9 11h6M9 15h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
    Simpan Draft
  </button>
  <button class="btn-action btn-pri" onclick="kirimFeedback()">
    <svg viewBox="0 0 24 24" fill="none"><line x1="22" y1="2" x2="11" y2="13" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><polygon points="22 2 15 22 11 13 2 9 22 2" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>
    Kirim ke Siswa
  </button>
</div>

<nav class="bottom-nav">
  <a href="dashboard-guru.php" class="nav-item" aria-label="Beranda"><div class="nav-indicator"></div><svg viewBox="0 0 24 24" fill="none"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z" stroke="currentColor" stroke-width="2"/><path d="M9 22V12h6v10" stroke="currentColor" stroke-width="2"/></svg><span>Beranda</span></a>
  <a href="#" class="nav-item" onclick="showToast('Aktivitas'); return false;" aria-label="Aktivitas"><div class="nav-indicator"></div><svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="8" r="3" stroke="currentColor" stroke-width="2"/><path d="M12 11v4l-2 3h4l-2-3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg><span>Aktivitas</span></a>
  <a href="#" class="nav-item active" aria-label="Nilai" aria-current="page"><div class="nav-indicator"></div><svg viewBox="0 0 24 24" fill="none"><rect x="5" y="2" width="14" height="20" rx="2" stroke="currentColor" stroke-width="2"/><path d="M9 7h6M9 11h6M9 15h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg><span>Nilai</span></a>
  <a href="#" class="nav-item" onclick="showToast('Profil'); return false;" aria-label="Profil"><div class="nav-indicator"></div><svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="8" r="4" stroke="currentColor" stroke-width="2"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg><span>Profil</span></a>
</nav>

<div class="toast" id="toast"></div>
<script>
  const drafts = {
    singkat: 'Ahmad: posisi kaki baik. Fokus: tekuk lutut lebih dalam dan rapat lengan saat kontak bola. Terus berlatih!',
    detail: 'Ahmad sudah menunjukkan pemahaman yang cukup baik tentang tujuan passing bawah dan mampu mengikuti pembelajaran dengan disiplin. Berdasarkan video praktik, posisi kaki sudah cukup siap, namun lutut masih perlu lebih ditekuk dan lengan perlu lebih rapat saat kontak bola agar arah bola lebih stabil. Latihan berikutnya dapat difokuskan pada posisi tubuh rendah, perkenaan bola pada lengan bagian bawah, dan kontrol arah bola menuju target.',
    motivatif: 'Ahmad, kamu sudah menunjukkan semangat belajar yang luar biasa! Posisi kaki kamu sudah benar, itu modal yang bagus. Satu langkah kecil yang perlu kamu perbaiki: tekuk lututmu sedikit lebih dalam dan rapatkan kedua lengan saat bola datang. Kamu pasti bisa! Terus berlatih dan jangan menyerah. Guru percaya kamu akan semakin hebat di pertemuan berikutnya! 💪',
    remedial: 'Ahmad perlu memberikan perhatian khusus pada teknik dasar passing bawah: (1) Posisi lutut harus lebih ditekuk – lakukan squat ringan sebelum menerima bola, (2) Kedua lengan harus benar-benar rapat dan lurus saat kontak bola, (3) Fokus pandangan ke arah sasaran saat mendorong bola. Dianjurkan latihan tambahan dengan bola balon terlebih dahulu untuk melatih kontrol.'
  };

  function setGaya(el, type) {
    document.querySelectorAll('.gaya-tab').forEach(b => b.classList.remove('active'));
    el.classList.add('active');
    document.getElementById('draft-text').textContent = drafts[type];
    document.getElementById('edit-area').value = drafts[type];
    updateChar();
  }

  function updateChar() {
    const ta = document.getElementById('edit-area');
    document.getElementById('char-count').textContent = ta.value.length + '/1000';
  }

  function generateDraft() {
    const btn = document.getElementById('btn-ai-draft');
    const orig = btn.innerHTML;
    btn.innerHTML = '<svg class="spin" viewBox="0 0 24 24" fill="none" width="16" height="16"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg> Menyusun...';
    btn.disabled = true;
    setTimeout(() => {
      btn.innerHTML = orig; btn.disabled = false;
      showToast('✅ Draft AI berhasil dibuat!');
    }, 1800);
  }

  function kirimFeedback() {
    const btn = event.target.closest('button');
    const orig = btn.innerHTML;
    btn.innerHTML = '<svg class="spin" viewBox="0 0 24 24" fill="none" width="18" height="18"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg> Mengirim...';
    btn.disabled = true;
    setTimeout(() => {
      btn.innerHTML = orig; btn.disabled = false;
      showToast('✅ Feedback berhasil dikirim ke Ahmad Fajar!');
    }, 1500);
  }

  function showToast(m){const t=document.getElementById('toast');t.textContent=m;t.classList.add('show');clearTimeout(t._t);t._t=setTimeout(()=>t.classList.remove('show'),3000);}

  // Init char count
  updateChar();
</script>
</body>
</html>
