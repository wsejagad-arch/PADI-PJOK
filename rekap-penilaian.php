<?php
require_once 'auth.php';
wajibLoginGuru();
require 'koneksi.php';

$students = [];
$avg_kog = 0; $avg_afe = 0; $avg_psi = 0;
$count = 0;

if ($conn) {
    $q = "
        SELECT s.id, s.nama, 
               IFNULL(k.nilai_total, 0) as kognitif,
               IFNULL(a.nilai_rubrik, 0) as afektif,
               IFNULL(p.nilai_rubrik, 0) as psikomotor
        FROM siswa s
        LEFT JOIN penilaian_kognitif k ON s.id = k.siswa_id
        LEFT JOIN penilaian_afektif a ON s.id = a.siswa_id
        LEFT JOIN penilaian_psikomotor p ON s.id = p.siswa_id
        ORDER BY s.nama ASC
    ";
    $res = $conn->query($q);
    if ($res) {
        $sum_k = 0; $sum_a = 0; $sum_p = 0;
        while ($row = $res->fetch_assoc()) {
            $row['afektif_100'] = ($row['afektif'] / 20) * 100;
            $row['psikomotor_100'] = ($row['psikomotor'] / 20) * 100;
            $students[] = $row;
            $sum_k += $row['kognitif'];
            $sum_a += $row['afektif_100'];
            $sum_p += $row['psikomotor_100'];
            $count++;
        }
        if ($count > 0) {
            $avg_kog = round($sum_k / $count, 1);
            $avg_afe = round($sum_a / $count, 1);
            $avg_psi = round($sum_p / $count, 1);
        }
    }
}

function getGrade($val) {
    if ($val >= 85) return ['Sangat Baik', 'green'];
    if ($val >= 70) return ['Baik', 'blue'];
    if ($val >= 50) return ['Cukup', 'orange'];
    return ['Kurang', 'red'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Rekap Penilaian – PADI-PJOK</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    :root{--blue:#1A56DB;--blue-dark:#1240A8;--blue-light:#EFF4FF;--blue-mid:#DBEAFE;--green:#16A34A;--green-light:#DCFCE7;--orange:#EA580C;--orange-light:#FFF7ED;--text:#111827;--text-2:#374151;--text-3:#6B7280;--text-4:#9CA3AF;--border:#E5E7EB;--bg:#F3F6FB;--white:#FFFFFF;--nav-h:68px;--radius:14px;--radius-sm:10px;--shadow:0 2px 8px rgba(0,0,0,.07);--shadow-md:0 4px 18px rgba(0,0,0,.10)}
    body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);max-width:480px;margin:0 auto;padding-bottom:var(--nav-h)}

    /* Topbar */
    .topbar{position:sticky;top:0;z-index:50;padding:16px 20px;background:var(--white);border-bottom:1px solid var(--border);box-shadow:var(--shadow)}
    .topbar-logo-row{display:flex;align-items:center;justify-content:center;gap:10px;margin-bottom:4px}
    .topbar-logo-row svg{width:32px;height:32px}
    .topbar-logo-row span{font-size:20px;font-weight:800;color:var(--blue)}
    .topbar-sub{text-align:center;font-size:12px;color:var(--text-3)}
    .back-btn{position:absolute;left:20px;top:50%;transform:translateY(-50%);width:36px;height:36px;border-radius:10px;background:var(--blue-light);border:none;display:flex;align-items:center;justify-content:center;cursor:pointer}
    .back-btn svg{width:20px;height:20px;color:var(--blue)}
    .topbar-inner{position:relative;display:flex;flex-direction:column;align-items:center}

    /* Hero */
    .hero{background:linear-gradient(135deg,#EFF6FF 0%,#DBEAFE 60%,#E0F2FE 100%);padding:20px 20px 0;display:flex;align-items:flex-end;min-height:160px;position:relative;overflow:hidden}
    .hero-text{flex:1;padding-bottom:20px;z-index:1}
    .hero-text h1{font-size:22px;font-weight:800;color:var(--text);margin-bottom:4px}
    .hero-subtitle{font-size:13px;font-weight:600;color:var(--text-3);margin-bottom:4px}
    .hero-img{width:140px;height:160px;object-fit:contain;object-position:bottom;flex-shrink:0;z-index:1}

    .content{padding:16px;display:flex;flex-direction:column;gap:14px}

    /* Summary scores */
    .score-summary{background:var(--white);border-radius:var(--radius);box-shadow:var(--shadow-md);padding:16px}
    .score-row{display:grid;grid-template-columns:repeat(3,1fr);gap:0}
    .score-item{display:flex;align-items:center;gap:10px;padding:0 12px}
    .score-item+.score-item{border-left:1px solid var(--border)}
    .score-item:first-child{padding-left:0}
    .score-icon{width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
    .score-icon svg{width:20px;height:20px}
    .score-icon.blue{background:var(--blue-light);color:var(--blue)}
    .score-icon.green{background:var(--green-light);color:var(--green)}
    .score-icon.orange{background:var(--orange-light);color:var(--orange)}
    .score-label{font-size:10px;font-weight:600;color:var(--text-3)}
    .score-val{font-size:18px;font-weight:800;color:var(--text);line-height:1}
    .score-grade{font-size:10px;font-weight:700}
    .score-grade.blue{color:var(--blue)}
    .score-grade.green{color:var(--green)}
    .score-grade.orange{color:var(--orange)}

    /* Search + Filter */
    .search-row{display:flex;gap:10px;align-items:center}
    .search-input-wrap{flex:1;position:relative}
    .search-icon{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-4)}
    .search-icon svg{width:16px;height:16px}
    .search-input{width:100%;padding:11px 12px 11px 38px;border:1.5px solid var(--border);border-radius:var(--radius-sm);font-family:inherit;font-size:13px;color:var(--text);background:var(--white);outline:none;transition:border-color .2s}
    .search-input:focus{border-color:var(--blue)}
    .search-input::placeholder{color:var(--text-4)}
    .filter-btn2{display:flex;align-items:center;gap:6px;padding:10px 14px;background:var(--white);border:1.5px solid var(--border);border-radius:var(--radius-sm);font-family:inherit;font-size:13px;font-weight:600;color:var(--text-3);cursor:pointer}
    .filter-btn2 svg{width:16px;height:16px}

    /* Student List */
    .student-list{display:flex;flex-direction:column;gap:10px}
    .student-card{background:var(--white);border-radius:var(--radius);box-shadow:var(--shadow);padding:14px 16px;display:flex;align-items:center;gap:12px;cursor:pointer;transition:all .2s;text-decoration:none}
    .student-card:hover{box-shadow:var(--shadow-md);transform:translateX(3px)}
    .avatar{width:46px;height:46px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;overflow:hidden}
    .avatar svg{width:28px;height:28px}
    .student-info{flex:1}
    .student-name-row{display:flex;align-items:center;gap:6px;margin-bottom:4px}
    .s-name{font-size:14px;font-weight:700;color:var(--text)}
    .badge-hadir{background:var(--green-light);color:var(--green);font-size:10px;font-weight:700;padding:2px 8px;border-radius:10px}
    .scores-row{display:flex;gap:12px}
    .s-score-item{display:flex;flex-direction:column}
    .s-score-label{font-size:9px;color:var(--text-3);font-weight:600}
    .s-score-val{font-size:16px;font-weight:800;line-height:1}
    .s-score-val.blue{color:var(--blue)}
    .s-score-val.green{color:var(--green)}
    .s-score-val.orange{color:var(--orange)}
    .s-score-grade{font-size:9px;font-weight:600}
    .s-score-grade.blue{color:var(--blue)}
    .s-score-grade.green{color:var(--green)}
    .s-score-grade.orange{color:var(--orange)}
    .card-arrow{color:var(--text-4);flex-shrink:0}
    .card-arrow svg{width:18px;height:18px}

    /* Action Cards */
    .action-card{background:var(--white);border-radius:var(--radius);box-shadow:var(--shadow);padding:14px 16px;display:flex;align-items:center;gap:14px;cursor:pointer;transition:all .2s;border:1.5px solid transparent}
    .action-card:hover{border-color:var(--blue);box-shadow:var(--shadow-md)}
    .action-card-icon{width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
    .action-card-icon svg{width:26px;height:26px}
    .action-card-icon.blue{background:var(--blue-light);color:var(--blue)}
    .action-card-icon.teal{background:#ECFEFF;color:#0891B2}
    .action-card-info{flex:1}
    .action-card-title{font-size:14px;font-weight:700;color:var(--text);margin-bottom:2px}
    .action-card-desc{font-size:11px;color:var(--text-3)}
    .action-card-arrow{color:var(--text-4)}
    .action-card-arrow svg{width:18px;height:18px}

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
  </style>
</head>
<body>

<header class="topbar">
  <div class="topbar-inner">
    <a href="dashboard-guru.php" class="back-btn" aria-label="Kembali">
      <svg viewBox="0 0 24 24" fill="none"><path d="M19 12H5M12 5l-7 7 7 7" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </a>
    <div class="topbar-logo-row">
      <svg viewBox="0 0 28 28" fill="none"><circle cx="18" cy="5" r="3" fill="#1A56DB"/><path d="M6 24L13 14 11 9 17 5" stroke="#1A56DB" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/><path d="M11 9L18 12L24 9" stroke="#1A56DB" stroke-width="2.2" stroke-linecap="round"/><path d="M18 12L15 20L19 24" stroke="#1A56DB" stroke-width="2.2" stroke-linecap="round"/><path d="M2 18Q9 14 16 17" stroke="#22C55E" stroke-width="2" stroke-linecap="round"/></svg>
      <span>PADI-PJOK</span>
    </div>
    <p class="topbar-sub">Penilaian Autentik Digital Integratif untuk PJOK</p>
  </div>
</header>

<div class="hero">
  <div class="hero-text">
    <h1>Rekap Penilaian</h1>
    <p class="hero-subtitle">Kelas X-1 | Passing Bawah Bola Voli</p>
  </div>
  <img class="hero-img" src="guru_dashboard_hero.png" alt="Guru PJOK"/>
</div>

<div class="content">

  <!-- Score Summary -->
  <div class="score-summary anim">
    <div class="score-row">
      <div class="score-item">
        <div class="score-icon blue">
          <svg viewBox="0 0 24 24" fill="none"><path d="M12 2C8 2 5 5 5 9c0 2.5 1.3 4.7 3.2 6H12h3.8C17.7 13.7 19 11.5 19 9c0-4-3-7-7-7z" stroke="currentColor" stroke-width="1.8"/><path d="M9 21h6M10 17h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
        </div>
        <div><div class="score-label">Kognitif</div><div class="score-val"><?= str_replace('.', ',', $avg_kog) ?></div><div class="score-grade blue">Rata-rata</div></div>
      </div>
      <div class="score-item">
        <div class="score-icon green">
          <svg viewBox="0 0 24 24" fill="none"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z" stroke="currentColor" stroke-width="1.8"/></svg>
        </div>
        <div><div class="score-label">Afektif</div><div class="score-val"><?= str_replace('.', ',', $avg_afe) ?></div><div class="score-grade green">Rata-rata</div></div>
      </div>
      <div class="score-item">
        <div class="score-icon orange">
          <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="5" r="2.5" stroke="currentColor" stroke-width="1.8"/><path d="M12 8v5l-3 4M12 13l3 4M8 10l-3 2M16 10l3 2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
        </div>
        <div><div class="score-label">Psikomotor</div><div class="score-val"><?= str_replace('.', ',', $avg_psi) ?></div><div class="score-grade orange">Rata-rata</div></div>
      </div>
    </div>
  </div>

  <!-- Search -->
  <div class="search-row">
    <div class="search-input-wrap">
      <span class="search-icon"><svg viewBox="0 0 24 24" fill="none"><circle cx="11" cy="11" r="8" stroke="currentColor" stroke-width="2"/><path d="M21 21l-4.35-4.35" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></span>
      <input class="search-input" type="search" placeholder="Cari nama siswa" oninput="searchStudents2(this.value)"/>
    </div>
    <button class="filter-btn2" onclick="showToast('Filter penilaian')">
      <svg viewBox="0 0 24 24" fill="none"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>
      Filter
    </button>
  </div>

  <!-- Student List -->
  <div class="student-list" id="student-list2">
    <?php if (count($students) === 0): ?>
      <div style="text-align:center;padding:20px;color:var(--text-4);font-size:14px">Belum ada siswa yang mengerjakan</div>
    <?php else: ?>
      <?php foreach ($students as $s): 
        $k_grade = getGrade($s['kognitif']);
        $a_grade = getGrade($s['afektif_100']);
        $p_grade = getGrade($s['psikomotor_100']);
      ?>
      <a href="rekap-penilaian-siswa.php?id=<?= $s['id'] ?>" class="student-card anim" data-name="<?= htmlspecialchars(strtolower($s['nama'])) ?>">
        <div class="avatar" style="background:var(--blue-light)"><svg viewBox="0 0 24 24" fill="none" style="color:var(--blue)"><circle cx="12" cy="8" r="4" stroke="currentColor" stroke-width="1.8"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></div>
        <div class="student-info">
          <div class="student-name-row"><span class="s-name"><?= htmlspecialchars($s['nama']) ?></span><span class="badge-hadir">Hadir</span></div>
          <div class="scores-row">
            <div class="s-score-item"><span class="s-score-label">Kognitif</span><span class="s-score-val <?= $k_grade[1] ?>"><?= round($s['kognitif']) ?></span><span class="s-score-grade <?= $k_grade[1] ?>"><?= $k_grade[0] ?></span></div>
            <div class="s-score-item"><span class="s-score-label">Afektif</span><span class="s-score-val <?= $a_grade[1] ?>"><?= round($s['afektif_100']) ?></span><span class="s-score-grade <?= $a_grade[1] ?>"><?= $a_grade[0] ?></span></div>
            <div class="s-score-item"><span class="s-score-label">Psikomotor</span><span class="s-score-val <?= $p_grade[1] ?>"><?= round($s['psikomotor_100']) ?></span><span class="s-score-grade <?= $p_grade[1] ?>"><?= $p_grade[0] ?></span></div>
          </div>
        </div>
        <span class="card-arrow"><svg viewBox="0 0 24 24" fill="none"><path d="M9 18l6-6-6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></span>
      </a>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- Action Cards -->
  <div class="action-card anim" onclick="showToast('Mengunduh rekap PDF/Excel...')">
    <div class="action-card-icon blue">
      <svg viewBox="0 0 24 24" fill="none"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><polyline points="7 10 12 15 17 10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><line x1="12" y1="15" x2="12" y2="3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
    </div>
    <div class="action-card-info">
      <p class="action-card-title">Unduh Rekap</p>
      <p class="action-card-desc">Unduh rekap penilaian dalam format PDF atau Excel</p>
    </div>
    <span class="action-card-arrow"><svg viewBox="0 0 24 24" fill="none"><path d="M9 18l6-6-6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></span>
  </div>
  <div class="action-card anim" onclick="showToast('Lihat laporan penilaian')">
    <div class="action-card-icon teal">
      <svg viewBox="0 0 24 24" fill="none"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z" stroke="currentColor" stroke-width="2"/><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
    </div>
    <div class="action-card-info">
      <p class="action-card-title">Lihat Laporan</p>
      <p class="action-card-desc">Lihat laporan penilaian per kelas atau per siswa</p>
    </div>
    <span class="action-card-arrow"><svg viewBox="0 0 24 24" fill="none"><path d="M9 18l6-6-6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></span>
  </div>

</div>

<nav class="bottom-nav">
  <a href="dashboard-guru.php" class="nav-item" aria-label="Beranda"><div class="nav-indicator"></div><svg viewBox="0 0 24 24" fill="none"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z" stroke="currentColor" stroke-width="2"/><path d="M9 22V12h6v10" stroke="currentColor" stroke-width="2"/></svg><span>Beranda</span></a>
  <a href="#" class="nav-item" onclick="showToast('Materi'); return false;" aria-label="Materi"><div class="nav-indicator"></div><svg viewBox="0 0 24 24" fill="none"><path d="M4 19.5A2.5 2.5 0 016.5 17H20" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z" stroke="currentColor" stroke-width="2"/></svg><span>Materi</span></a>
  <a href="#" class="nav-item active" aria-label="Rekap" aria-current="page"><div class="nav-indicator"></div><svg viewBox="0 0 24 24" fill="none"><path d="M18 20V10M12 20V4M6 20v-6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg><span>Rekap</span></a>
  <a href="#" class="nav-item" onclick="showToast('Profil'); return false;" aria-label="Profil"><div class="nav-indicator"></div><svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="8" r="4" stroke="currentColor" stroke-width="2"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg><span>Profil</span></a>
</nav>

<div class="toast" id="toast"></div>
<script>
  function showToast(m){const t=document.getElementById('toast');t.textContent=m;t.classList.add('show');clearTimeout(t._t);t._t=setTimeout(()=>t.classList.remove('show'),3000);}
  function searchStudents2(v){const q=v.toLowerCase();document.querySelectorAll('#student-list2 .student-card').forEach(c=>{c.style.display=c.dataset.name.includes(q)?'flex':'none';});}
</script>
</body>
</html>
