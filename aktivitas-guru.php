<?php
require_once 'auth.php';
wajibLoginGuru();
require 'koneksi.php';

$filterKelas = strtoupper(trim((string)($_GET['kelas'] ?? '')));
$filterStatus = (string)($_GET['status'] ?? '');
if (!in_array($filterStatus, ['aktif', 'selesai'], true)) $filterStatus = '';

$kelasList = [];
$sesiList = [];
$ringkas = ['aktif' => 0, 'selesai' => 0, 'siswa' => 0, 'hari_ini' => 0];
if ($conn) {
    $q = $conn->query("SELECT DISTINCT kelas FROM sesi WHERE kelas <> '' ORDER BY kelas");
    if ($q) while ($r = $q->fetch_assoc()) $kelasList[] = (string)$r['kelas'];

    $sql = "SELECT s.id, s.token, s.kelas, s.materi, s.status, s.created_at,
                   (SELECT COUNT(*) FROM siswa p WHERE p.sesi_id = s.id) AS jumlah_siswa,
                   (SELECT COUNT(*) FROM siswa p JOIN penilaian_kognitif k ON k.siswa_id = p.id WHERE p.sesi_id = s.id) AS jumlah_kog,
                   (SELECT COUNT(*) FROM siswa p JOIN penilaian_psikomotor ps ON ps.siswa_id = p.id WHERE p.sesi_id = s.id AND ps.video_path IS NOT NULL AND ps.video_path <> '') AS jumlah_video,
                   (SELECT COUNT(*) FROM siswa p JOIN penilaian_afektif af ON af.siswa_id = p.id WHERE p.sesi_id = s.id) AS jumlah_afe
            FROM sesi s";
    $kondisi = [];
    if ($filterKelas !== '') $kondisi[] = "s.kelas = '" . $conn->real_escape_string($filterKelas) . "'";
    if ($filterStatus !== '') $kondisi[] = "s.status = '" . $conn->real_escape_string($filterStatus) . "'";
    if ($kondisi) $sql .= ' WHERE ' . implode(' AND ', $kondisi);
    $sql .= ' ORDER BY s.created_at DESC, s.id DESC LIMIT 100';

    $q = $conn->query($sql);
    if ($q) {
        while ($row = $q->fetch_assoc()) {
            $sesiList[] = $row;
            if ($row['status'] === 'aktif') $ringkas['aktif']++; else $ringkas['selesai']++;
            $ringkas['siswa'] += (int)$row['jumlah_siswa'];
            if (date('Y-m-d', strtotime((string)$row['created_at'])) === date('Y-m-d')) $ringkas['hari_ini']++;
        }
    }
}

if (!function_exists('aktivitas_tanggal')) {
function aktivitas_tanggal($stamp)
{
    $hari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    $bulan = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $t = strtotime((string)$stamp);
    if (!$t) return '-';
    return $hari[(int)date('w', $t)] . ', ' . date('j', $t) . ' ' . $bulan[(int)date('n', $t)] . ' ' . date('Y', $t) . ' • ' . date('H:i', $t);
}
}

// Kelompokkan per tanggal
$perTanggal = [];
foreach ($sesiList as $s) {
    $kunci = date('Y-m-d', strtotime((string)$s['created_at']));
    $perTanggal[$kunci][] = $s;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Aktivitas Harian – PADI-PJOK</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --blue: #1A56DB; --blue-light: #EFF4FF; --text: #111827; --text-3: #6B7280; --border: #E5E7EB;
      --bg: #F3F6FB; --white: #FFFFFF; --radius: 16px; --radius-sm: 10px;
      --green: #16A34A; --green-light: #DCFCE7; --orange: #B45309; --orange-light: #FEF3C7;
    }
    body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; padding-bottom: 24px; }
    .topbar { display: flex; align-items: center; gap: 10px; padding: 14px 18px; background: var(--white); position: sticky; top: 0; z-index: 50; }
    .back-btn { width: 36px; height: 36px; border-radius: 10px; background: var(--blue-light); border: none; display: flex; align-items: center; justify-content: center; cursor: pointer; flex-shrink: 0; }
    .back-btn svg { width: 20px; height: 20px; color: var(--blue); }
    .topbar-title { font-size: 15px; font-weight: 700; flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .content { max-width: 900px; margin: 0 auto; padding: 16px; display: flex; flex-direction: column; gap: 14px; }
    .card { background: var(--white); border-radius: var(--radius); padding: 18px; }
    .card h1 { font-size: 17px; margin-bottom: 6px; }
    .card h2 { font-size: 14px; margin-bottom: 10px; }
    .lead { font-size: 12.5px; color: var(--text-3); line-height: 1.55; }
    .filter { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; align-items: end; margin-top: 12px; }
    .filter label { display: block; font-size: 11px; font-weight: 700; color: var(--text-3); margin-bottom: 4px; }
    .filter select { width: 100%; font: inherit; font-size: 13px; padding: 9px 10px; border-radius: var(--radius-sm); background: #fff; }
    .filter-aksi { display: flex; gap: 8px; flex-wrap: wrap; }
    .btn { display: inline-flex; align-items: center; justify-content: center; height: 38px; padding: 0 14px; border-radius: var(--radius-sm); font: inherit; font-size: 12.5px; font-weight: 700; cursor: pointer; background: var(--blue); color: #fff; text-decoration: none; border: 0; }
    .btn-ringan { background: #fff; color: var(--blue); border: 1px solid var(--border); }
    .ringkas { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; margin-top: 12px; }
    @media (min-width: 560px) { .ringkas { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
    .ringkas-item { border-radius: var(--radius-sm); padding: 10px; text-align: center; background: var(--blue-light); }
    .ringkas-item.green { background: var(--green-light); }
    .ringkas-item.orange { background: var(--orange-light); }
    .ringkas-num { display: block; font-size: 19px; font-weight: 800; }
    .ringkas-label { font-size: 10.5px; font-weight: 600; color: var(--text-3); }
    .hari { margin-top: 6px; }
    .hari-judul { font-size: 12px; font-weight: 800; color: var(--text-3); text-transform: uppercase; letter-spacing: .04em; margin-bottom: 8px; }
    .aktivitas { display: flex; flex-direction: column; gap: 10px; }
    .akt-item { border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 12px; display: flex; flex-direction: column; gap: 8px; }
    .akt-top { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    .akt-kelas { font-size: 13px; font-weight: 800; }
    .akt-materi { font-size: 12px; color: var(--text-3); flex: 1 1 160px; }
    .badge { display: inline-block; font-size: 10.5px; font-weight: 700; padding: 2px 8px; border-radius: 999px; background: #F1F5F9; color: var(--text-3); }
    .badge.aktif { background: var(--green-light); color: #166534; }
    .badge.selesai { background: #E2E8F0; color: #334155; }
    .akt-meta { display: flex; flex-wrap: wrap; gap: 10px; font-size: 11px; color: var(--text-3); }
    .akt-aksi { display: flex; gap: 6px; flex-wrap: wrap; }
    .kosong { text-align: center; color: var(--text-3); font-size: 12.5px; padding: 22px 8px; }
    .nav-item { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px; color: #9CA3AF; text-decoration: none; height: 100%; }
    .nav-item svg { width: 22px; height: 22px; }
    .nav-item span { font-size: 10.5px; font-weight: 600; }
    .nav-item.active { color: var(--blue); }
  </style>
<?php require_once __DIR__ . '/ui-head.php'; padi_ui_head(); ?>
</head>
<body>
<?php require_once __DIR__ . '/ui-cetak.php'; padi_cetak_siap('AKTIVITAS PEMBELAJARAN PJOK', 'Kelas ' . ($filterKelas !== '' ? $filterKelas : 'semua kelas'), ['orientasi' => 'landscape']); ?>

  <header class="topbar">
    <button class="back-btn" type="button" aria-label="Kembali" onclick="window.location.href='dashboard-guru.php'">
      <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </button>
    <div class="topbar-title">Aktivitas</div>
  </header>

  <main class="content">
    <section class="card">
      <h1>Aktivitas Harian</h1>
      <p class="lead">Linimasa sesi pembelajaran (token materi) beserta progres penilaian siswanya.</p>
      <div class="ringkas">
        <div class="ringkas-item"><span class="ringkas-num"><?= (int)$ringkas['hari_ini'] ?></span><span class="ringkas-label">Sesi hari ini</span></div>
        <div class="ringkas-item green"><span class="ringkas-num"><?= (int)$ringkas['aktif'] ?></span><span class="ringkas-label">Sesi aktif</span></div>
        <div class="ringkas-item orange"><span class="ringkas-num"><?= (int)$ringkas['selesai'] ?></span><span class="ringkas-label">Sesi selesai</span></div>
        <div class="ringkas-item"><span class="ringkas-num"><?= (int)$ringkas['siswa'] ?></span><span class="ringkas-label">Siswa tergabung</span></div>
      </div>
      <form method="get" action="aktivitas-guru.php" class="filter">
        <div>
          <label for="f-kelas">Kelas</label>
          <select id="f-kelas" name="kelas" onchange="this.form.submit()">
            <option value="">Semua kelas</option>
            <?php foreach ($kelasList as $k): ?>
              <option value="<?= htmlspecialchars($k, ENT_QUOTES, 'UTF-8') ?>" <?= (strcasecmp($filterKelas, $k) === 0) ? 'selected' : '' ?>><?= htmlspecialchars($k, ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label for="f-status">Status</label>
          <select id="f-status" name="status" onchange="this.form.submit()">
            <option value="">Semua status</option>
            <option value="aktif" <?= $filterStatus === 'aktif' ? 'selected' : '' ?>>Aktif</option>
            <option value="selesai" <?= $filterStatus === 'selesai' ? 'selected' : '' ?>>Selesai</option>
          </select>
        </div>
        <div class="filter-aksi">
          <button class="btn btn-ringan" type="submit">Terapkan</button>
          <a class="btn btn-ringan" href="buat-token.php">Buat Token</a>
        </div>
      </form>
    </section>

    <?php if (!$sesiList): ?>
      <section class="card"><div class="kosong">Belum ada aktivitas. Buat token materi untuk memulai sesi.</div></section>
    <?php else: ?>
      <?php foreach ($perTanggal as $tanggal => $daftar): $contoh = $daftar[0]; ?>
        <section class="card hari">
          <div class="hari-judul"><?= htmlspecialchars(aktivitas_tanggal($contoh['created_at']), ENT_QUOTES, 'UTF-8') ?></div>
          <div class="aktivitas">
            <?php foreach ($daftar as $s): ?>
              <div class="akt-item">
                <div class="akt-top">
                  <span class="akt-kelas"><?= htmlspecialchars($s['kelas'], ENT_QUOTES, 'UTF-8') ?></span>
                  <span class="akt-materi"><?= htmlspecialchars(ucwords(str_replace('-', ' ', (string)$s['materi'])), ENT_QUOTES, 'UTF-8') ?></span>
                  <span class="badge <?= $s['status'] === 'aktif' ? 'aktif' : 'selesai' ?>"><?= $s['status'] === 'aktif' ? 'Aktif' : 'Selesai' ?></span>
                </div>
                <div class="akt-meta">
                  <span>Token <strong><?= htmlspecialchars($s['token'], ENT_QUOTES, 'UTF-8') ?></strong></span>
                  <span><?= (int)$s['jumlah_siswa'] ?> siswa</span>
                  <span>Kognitif <?= (int)$s['jumlah_kog'] ?></span>
                  <span>Afektif <?= (int)$s['jumlah_afe'] ?></span>
                  <span>Video <?= (int)$s['jumlah_video'] ?></span>
                </div>
                <div class="akt-aksi">
                  <a class="btn btn-ringan" href="pantau-siswa.php?kelas=<?= urlencode($s['kelas']) ?>">Pantau</a>
                  <a class="btn btn-ringan" href="rekap-penilaian.php?kelas=<?= urlencode($s['kelas']) ?>">Rekap</a>
                  <a class="btn btn-ringan" href="feedback-guru.php">Feedback</a>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
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
<?php padi_cetak_ttd(); ?>
</body>
</html>
