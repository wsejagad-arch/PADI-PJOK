<?php
require_once 'auth.php';
wajibLoginGuru();
require 'koneksi.php';

// Siswa yang tergabung pada sesi berstatus aktif + progres terakhirnya.
$daftar = [];
$ringkas = ['terhubung' => 0, 'kognitif' => 0, 'video' => 0];
if ($conn) {
    $sql = "SELECT p.id, p.nama, p.created_at,
                   s.id AS sesi_id, s.token, s.kelas, s.materi,
                   (SELECT nilai_total FROM penilaian_kognitif WHERE siswa_id = p.id ORDER BY id DESC LIMIT 1) AS kog,
                   (SELECT nilai_rubrik FROM penilaian_psikomotor WHERE siswa_id = p.id ORDER BY id DESC LIMIT 1) AS psi,
                   (SELECT video_path FROM penilaian_psikomotor WHERE siswa_id = p.id ORDER BY id DESC LIMIT 1) AS video,
                   (SELECT nilai_rubrik FROM penilaian_afektif WHERE siswa_id = p.id ORDER BY id DESC LIMIT 1) AS afek,
                   (SELECT COUNT(*) FROM penilaian_rekan WHERE dinilai_id = p.id) AS rekan
            FROM siswa p
            JOIN sesi s ON s.id = p.sesi_id
            WHERE s.status = 'aktif'
            ORDER BY s.id DESC, p.nama ASC";
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $row['video'] = trim((string)$row['video']);
            $daftar[] = $row;
            $ringkas['terhubung']++;
            if ($row['kog'] !== null) $ringkas['kognitif']++;
            if ($row['video'] !== '') $ringkas['video']++;
        }
    }
}

// Kelompokkan per sesi agar tetap rapi bila ada beberapa sesi aktif.
$perSesi = [];
foreach ($daftar as $row) {
    $perSesi[$row['sesi_id']]['info'] = $row;
    $perSesi[$row['sesi_id']]['siswa'][] = $row;
}

if (!function_exists('padi_jam')) {
function padi_jam($stamp)
{
    $t = strtotime((string)$stamp);
    return $t ? date('d M • H:i', $t) : '-';
}
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Pantau Siswa – PADI-PJOK</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --blue: #1A56DB; --blue-light: #EFF4FF; --text: #111827; --text-3: #6B7280; --border: #E5E7EB;
      --bg: #F3F6FB; --white: #FFFFFF; --radius: 16px; --radius-sm: 10px;
      --shadow: 0 2px 8px rgba(0,0,0,.07); --shadow-md: 0 4px 18px rgba(0,0,0,.10);
    }
    body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; max-width: 480px; margin: 0 auto; padding-bottom: 24px; }
    .topbar { display: flex; align-items: center; justify-content: space-between; padding: 14px 20px; background: var(--white); border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 50; }
    .back-btn, .refresh-btn { width: 36px; height: 36px; border-radius: 10px; background: var(--blue-light); border: none; display: flex; align-items: center; justify-content: center; cursor: pointer; }
    .back-btn svg, .refresh-btn svg { width: 20px; height: 20px; color: var(--blue); }
    .topbar-title { font-size: 15px; font-weight: 700; }
    .content { padding: 16px; display: flex; flex-direction: column; gap: 14px; }
    .card { background: var(--white); border-radius: var(--radius); padding: 18px; }
    .card h1 { font-size: 17px; margin-bottom: 6px; }
    .lead { font-size: 12.5px; color: var(--text-3); line-height: 1.5; }
    .ringkas { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-top: 14px; }
    .ringkas-item { border-radius: var(--radius-sm); padding: 10px; text-align: center; background: var(--blue-light); }
    .ringkas-item.ok { background: var(--green-light); }
    .ringkas-item.warn { background: var(--amber-light); }
    .ringkas-num { display: block; font-size: 20px; font-weight: 800; }
    .ringkas-label { font-size: 10.5px; font-weight: 600; color: var(--text-3); }
    .sesi-head { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; padding-bottom: 12px; border-bottom: 1px solid var(--border); }
    .sesi-kelas { font-size: 14px; font-weight: 800; }
    .sesi-materi { font-size: 12px; color: var(--text-3); flex: 1 1 140px; }
    .sesi-token { font-size: 11px; font-weight: 700; letter-spacing: .04em; padding: 4px 8px; border-radius: 999px; background: var(--blue-light); color: var(--blue); }
    .siswa-list { list-style: none; display: flex; flex-direction: column; }
    .siswa-row { display: flex; flex-direction: column; gap: 6px; padding: 12px 0; border-bottom: 1px solid var(--border); }
    .siswa-row:last-child { border-bottom: 0; padding-bottom: 0; }
    .siswa-top { display: flex; align-items: baseline; justify-content: space-between; gap: 8px; }
    .siswa-nama { font-size: 13.5px; font-weight: 700; }
    .siswa-waktu { font-size: 10.5px; color: var(--text-3); white-space: nowrap; }
    .badges { display: flex; flex-wrap: wrap; gap: 6px; }
    .badge { font-size: 10.5px; font-weight: 700; padding: 3px 8px; border-radius: 999px; border: 1px solid var(--border); color: var(--text-3); background: #F8FAFC; }
    .badge.ok { background: var(--green-light); border-color: #86EFAC; color: #166534; }
    .badge.warn { background: var(--amber-light); border-color: #FCD34D; color: var(--amber); }
    .badge.info { background: var(--blue-light); border-color: #BFDBFE; color: var(--blue); }
    .kosong { text-align: center; padding: 28px 12px; color: var(--text-3); font-size: 12.5px; line-height: 1.6; }
    .nav-item { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px; color: #9CA3AF; text-decoration: none; height: 100%; transition: color .2s; }
    .nav-item svg { width: 22px; height: 22px; }
    .nav-item span { font-size: 10.5px; font-weight: 600; }
    .nav-item.active { color: var(--blue); }
  </style>
<?php require_once __DIR__ . '/ui-head.php'; padi_ui_head(); ?>
</head>
<body>
  <header class="topbar">
    <button class="back-btn" onclick="window.location.href='dashboard-guru.php'">
      <svg viewBox="0 0 24 24" fill="none"><path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </button>
    <div class="topbar-title">Pantau Siswa</div>
    <button class="refresh-btn" type="button" aria-label="Muat ulang data" onclick="window.location.reload()">
      <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 12a9 9 0 1 0 3-6.7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M3 4v5h5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </button>
  </header>
  <main class="content">
    <section class="card">
      <h1>Pantau Siswa Terhubung</h1>
      <p class="lead">Siswa pada sesi aktif, lengkap dengan progres penilaian kognitif, video praktik, dan afektif.</p>
      <div class="ringkas">
        <div class="ringkas-item">
          <span class="ringkas-num" id="stat-terhubung"><?= (int)$ringkas['terhubung'] ?></span>
          <span class="ringkas-label">Terhubung</span>
        </div>
        <div class="ringkas-item ok">
          <span class="ringkas-num" id="stat-kognitif"><?= (int)$ringkas['kognitif'] ?></span>
          <span class="ringkas-label">Kognitif</span>
        </div>
        <div class="ringkas-item warn">
          <span class="ringkas-num" id="stat-video"><?= (int)$ringkas['video'] ?></span>
          <span class="ringkas-label">Video</span>
        </div>
      </div>
    </section>

    <?php if (!$daftar): ?>
      <section class="card">
        <div class="kosong">
          Belum ada siswa yang bergabung pada sesi aktif.<br>
          Buat token materi, lalu minta siswa masuk dengan token tersebut.
        </div>
      </section>
    <?php else: ?>
      <?php foreach ($perSesi as $grup): $sesi = $grup['info']; ?>
        <section class="card">
          <div class="sesi-head">
            <span class="sesi-kelas"><?= htmlspecialchars($sesi['kelas'], ENT_QUOTES, 'UTF-8') ?></span>
            <span class="sesi-materi"><?= htmlspecialchars(ucwords(str_replace('-', ' ', (string)$sesi['materi'])), ENT_QUOTES, 'UTF-8') ?></span>
            <span class="sesi-token"><?= htmlspecialchars($sesi['token'], ENT_QUOTES, 'UTF-8') ?></span>
          </div>
          <ul class="siswa-list">
            <?php foreach ($grup['siswa'] as $s): ?>
              <li class="siswa-row">
                <div class="siswa-top">
                  <span class="siswa-nama"><?= htmlspecialchars($s['nama'], ENT_QUOTES, 'UTF-8') ?></span>
                  <span class="siswa-waktu"><?= htmlspecialchars(padi_jam($s['created_at']), ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="badges">
                  <?php if ($s['kog'] !== null): ?>
                    <span class="badge ok">Kognitif <?= (int)$s['kog'] ?></span>
                  <?php else: ?>
                    <span class="badge">Kognitif belum</span>
                  <?php endif; ?>

                  <?php if ($s['video'] !== ''): ?>
                    <span class="badge ok">Video masuk</span>
                  <?php else: ?>
                    <span class="badge warn">Video belum</span>
                  <?php endif; ?>

                  <?php if ($s['psi'] !== null): ?>
                    <span class="badge info">Psikomotor <?= (int)$s['psi'] ?></span>
                  <?php endif; ?>

                  <?php if ($s['afek'] !== null): ?>
                    <span class="badge info">Afektif <?= (int)$s['afek'] ?></span>
                  <?php endif; ?>

                  <?php if ((int)$s['rekan'] > 0): ?>
                    <span class="badge info">Rekan <?= (int)$s['rekan'] ?>×</span>
                  <?php endif; ?>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        </section>
      <?php endforeach; ?>
    <?php endif; ?>
  </main>

  <nav class="nav-bottom" role="navigation" aria-label="Navigasi utama">
    <a href="dashboard-guru.php" class="nav-item" id="nav-beranda" aria-label="Beranda">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
      <span>Beranda</span>
    </a>
    <a href="aktivitas-guru.php" class="nav-item active" id="nav-aktivitas" aria-label="Aktivitas" aria-current="page">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
      <span>Aktivitas</span>
    </a>
    <a href="nilai-guru.php" class="nav-item" id="nav-nilai" aria-label="Nilai">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
      <span>Nilai</span>
    </a>
    <a href="profil-guru.php" class="nav-item" id="nav-profil" aria-label="Profil">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
      <span>Profil</span>
    </a>
  </nav>
</body>
</html>
