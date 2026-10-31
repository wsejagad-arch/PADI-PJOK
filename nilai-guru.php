<?php
require_once 'auth.php';
wajibLoginGuru();
require 'koneksi.php';
require_once 'rubrik.php';

$filterKelas = strtoupper(trim((string)($_GET['kelas'] ?? '')));

// Agregat nilai per kelas dari sesi aktif (fallback: seluruh sesi bila tidak ada yang aktif).
$perKelas = [];
$kelasList = [];
if ($conn) {
    $sidNilai = padi_sekolah_id();
    $stmt = $conn->prepare("SELECT DISTINCT kelas FROM sesi WHERE sekolah_id = ? AND kelas <> '' ORDER BY kelas");
    $stmt->bind_param('i', $sidNilai);
    $stmt->execute();
    $q = $stmt->get_result();
    if ($q) while ($r = $q->fetch_assoc()) $kelasList[] = (string)$r['kelas'];
    $stmt->close();

    $sql = "SELECT p.id, s.kelas,
                   (SELECT nilai_total FROM penilaian_kognitif WHERE siswa_id = p.id ORDER BY id DESC LIMIT 1) AS kog,
                   (SELECT COALESCE(nilai_guru, nilai_rubrik) FROM penilaian_psikomotor WHERE siswa_id = p.id ORDER BY id DESC LIMIT 1) AS psi,
                   (SELECT COALESCE(nilai_guru, nilai_rubrik) FROM penilaian_afektif WHERE siswa_id = p.id ORDER BY id DESC LIMIT 1) AS afe,
                   (SELECT nilai_rubrik FROM penilaian_rekan WHERE penilai_id = p.id ORDER BY id DESC LIMIT 1) AS diri
            FROM siswa p
            JOIN sesi s ON s.id = p.sesi_id
            WHERE s.sekolah_id = ? AND s.status = 'aktif'";
    $tipeNilai = 'i';
    $nilaiNilai = [$sidNilai];
    if ($filterKelas !== '') { $sql .= " AND s.kelas = ?"; $tipeNilai .= 's'; $nilaiNilai[] = $filterKelas; }
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($tipeNilai, ...$nilaiNilai);
    $stmt->execute();
    $q = $stmt->get_result();
    if ($q) {
        while ($row = $q->fetch_assoc()) {
            $k = (string)$row['kelas'];
            if (!isset($perKelas[$k])) {
                $perKelas[$k] = ['siswa' => 0, 'kog' => [], 'psi' => [], 'afe' => [], 'diri' => [], 'tuntas' => 0, 'dinilai' => 0];
            }
            $perKelas[$k]['siswa']++;
            if ($row['kog'] !== null) $perKelas[$k]['kog'][] = (float)$row['kog'];
            if ($row['psi'] !== null) $perKelas[$k]['psi'][] = (float)$row['psi'] * 5;
            if ($row['afe'] !== null) $perKelas[$k]['afe'][] = (float)$row['afe'] * 5;
            if ($row['diri'] !== null) $perKelas[$k]['diri'][] = (float)$row['diri'] * 5;

            $angka = [];
            foreach (['kog' => 1, 'psi' => 5, 'afe' => 5, 'diri' => 5] as $kunci => $kali) {
                if ($row[$kunci] !== null) $angka[] = (float)$row[$kunci] * $kali;
            }
            if ($angka) {
                $perKelas[$k]['dinilai']++;
                $rata = array_sum($angka) / count($angka);
                if ($rata >= PADI_TUNTAS) $perKelas[$k]['tuntas']++;
            }
        }
    }
    $stmt->close();
}
ksort($perKelas);

if (!function_exists('nilai_rata')) {
function nilai_rata(array $arr)
{
    return $arr ? array_sum($arr) / count($arr) : null;
}
}

$totalSiswa = 0;
$totalDinilai = 0;
$totalTuntas = 0;
foreach ($perKelas as $info) {
    $totalSiswa += $info['siswa'];
    $totalDinilai += $info['dinilai'];
    $totalTuntas += $info['tuntas'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Rekapitulasi Nilai – PADI-PJOK</title>
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
    .content { max-width: 1000px; margin: 0 auto; padding: 16px; display: flex; flex-direction: column; gap: 14px; }
    .card { background: var(--white); border-radius: var(--radius); padding: 18px; }
    .card h1 { font-size: 17px; margin-bottom: 6px; }
    .card h2 { font-size: 14px; margin-bottom: 10px; }
    .lead { font-size: 12.5px; color: var(--text-3); line-height: 1.55; }
    .filter { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 10px; align-items: end; margin-top: 12px; }
    .filter label { display: block; font-size: 11px; font-weight: 700; color: var(--text-3); margin-bottom: 4px; }
    .filter select { width: 100%; font: inherit; font-size: 13px; padding: 9px 10px; border-radius: var(--radius-sm); background: #fff; }
    .filter-aksi { display: flex; gap: 8px; flex-wrap: wrap; }
    .btn { display: inline-flex; align-items: center; justify-content: center; height: 38px; padding: 0 14px; border-radius: var(--radius-sm); font: inherit; font-size: 12.5px; font-weight: 700; cursor: pointer; background: var(--blue); color: #fff; text-decoration: none; border: 0; }
    .btn-ringan { background: #fff; color: var(--blue); border: 1px solid var(--border); }
    .ringkas { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; margin-top: 12px; }
    .ringkas-item { border-radius: var(--radius-sm); padding: 10px; text-align: center; background: var(--blue-light); }
    .ringkas-item.green { background: var(--green-light); }
    .ringkas-item.orange { background: var(--orange-light); }
    .ringkas-num { display: block; font-size: 19px; font-weight: 800; }
    .ringkas-label { font-size: 10.5px; font-weight: 600; color: var(--text-3); }
    .table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
    table { width: 100%; border-collapse: collapse; font-size: 12.5px; min-width: 620px; }
    th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid var(--border); }
    th { font-size: 10.5px; text-transform: uppercase; letter-spacing: .04em; color: var(--text-3); background: #F8FAFC; white-space: nowrap; }
    tbody tr:last-child td { border-bottom: 0; }
    th.num, td.num { text-align: right; font-variant-numeric: tabular-nums; }
    .pill { display: inline-block; font-size: 10.5px; font-weight: 700; padding: 2px 8px; border-radius: 999px; background: var(--blue-light); color: var(--blue); }
    .kosong { text-align: center; color: var(--text-3); font-size: 12.5px; padding: 22px 8px; }
    .nav-item { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px; color: #9CA3AF; text-decoration: none; height: 100%; }
    .nav-item svg { width: 22px; height: 22px; }
    .nav-item span { font-size: 10.5px; font-weight: 600; }
    .nav-item.active { color: var(--blue); }
  </style>
<?php require_once __DIR__ . '/ui-head.php'; padi_ui_head(); ?>
</head>
<body>
<?php require_once __DIR__ . '/ui-cetak.php'; padi_cetak_siap('REKAPITULASI NILAI PJOK', 'Kelas ' . ($filterKelas !== '' ? $filterKelas : 'semua kelas'), ['orientasi' => 'landscape']); ?>

  <header class="topbar">
    <button class="back-btn" type="button" aria-label="Kembali" onclick="window.location.href='dashboard-guru.php'">
      <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </button>
    <div class="topbar-title">Nilai</div>
  </header>

  <main class="content">
    <section class="card">
      <h1>Rekapitulasi Nilai</h1>
      <p class="lead">Ringkasan capaian seluruh kelas pada sesi aktif, memakai ambang tuntas <?= PADI_TUNTAS ?> dan konversi rubrik 0–20 → 0–100.</p>
      <div class="ringkas">
        <div class="ringkas-item"><span class="ringkas-num"><?= $totalSiswa ?></span><span class="ringkas-label">Siswa</span></div>
        <div class="ringkas-item orange"><span class="ringkas-num"><?= $totalDinilai ?></span><span class="ringkas-label">Sudah dinilai</span></div>
        <div class="ringkas-item green"><span class="ringkas-num"><?= $totalTuntas ?></span><span class="ringkas-label">Tuntas (≥<?= PADI_TUNTAS ?>)</span></div>
      </div>
      <form method="get" action="nilai-guru.php" class="filter">
        <div>
          <label for="f-kelas">Kelas</label>
          <select id="f-kelas" name="kelas" onchange="this.form.submit()">
            <option value="">Semua kelas</option>
            <?php foreach ($kelasList as $k): ?>
              <option value="<?= htmlspecialchars($k, ENT_QUOTES, 'UTF-8') ?>" <?= (strcasecmp($filterKelas, $k) === 0) ? 'selected' : '' ?>><?= htmlspecialchars($k, ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="filter-aksi">
          <button class="btn btn-ringan" type="submit">Terapkan</button>
          <a class="btn btn-ringan" href="rekap-penilaian.php<?= $filterKelas !== '' ? '?kelas=' . urlencode($filterKelas) : '' ?>">Rekap detail per siswa</a>
          <a class="btn btn-ringan" href="laporan.php<?= $filterKelas !== '' ? '?kelas=' . urlencode($filterKelas) : '' ?>">Raport</a>
        </div>
      </form>
    </section>

    <section class="card">
      <h2>Ringkasan per Kelas</h2>
      <?php if (!$perKelas): ?>
        <div class="kosong">Belum ada data nilai pada sesi aktif. Buat token, minta siswa bergabung, lalu isi penilaian.</div>
      <?php else: ?>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Kelas</th>
                <th class="num">Siswa</th>
                <th class="num">Dinilai</th>
                <th class="num">Kognitif</th>
                <th class="num">Psikomotor</th>
                <th class="num">Afektif</th>
                <th class="num">Diri/Rekan</th>
                <th class="num">Tuntas</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($perKelas as $kelas => $info): ?>
                <tr>
                  <td><span class="pill"><?= htmlspecialchars($kelas, ENT_QUOTES, 'UTF-8') ?></span></td>
                  <td class="num"><?= (int)$info['siswa'] ?></td>
                  <td class="num"><?= (int)$info['dinilai'] ?></td>
                  <td class="num"><?= ($v = nilai_rata($info['kog'])) === null ? '–' : round($v) ?></td>
                  <td class="num"><?= ($v = nilai_rata($info['psi'])) === null ? '–' : round($v) ?></td>
                  <td class="num"><?= ($v = nilai_rata($info['afe'])) === null ? '–' : round($v) ?></td>
                  <td class="num"><?= ($v = nilai_rata($info['diri'])) === null ? '–' : round($v) ?></td>
                  <td class="num"><?= (int)$info['tuntas'] ?>/<?= (int)$info['dinilai'] ?></td>
                  <td><a class="btn btn-ringan" href="rekap-penilaian.php?kelas=<?= urlencode($kelas) ?>">Detail</a></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>

    <section class="card">
      <h2>Aksi Cepat</h2>
      <div class="filter-aksi">
        <a class="btn btn-ringan" href="verifikasi-nilai.php">Verifikasi / Koreksi Nilai</a>
        <a class="btn btn-ringan" href="pantau-siswa.php">Pantau Siswa</a>
        <a class="btn btn-ringan" href="penilaian-kognitif.php">Penilaian Kognitif</a>
        <a class="btn btn-ringan" href="penilaian-psikomotor.php">Penilaian Psikomotor</a>
        <a class="btn btn-ringan" href="penilaian-afektif.php">Penilaian Afektif</a>
        <a class="btn btn-ringan" href="rubrik-platform.php">Rubrik Penilaian</a>
      </div>
    </section>
  </main>

  <nav class="nav-bottom" role="navigation" aria-label="Navigasi utama">
    <a href="dashboard-guru.php" class="nav-item" id="nav-beranda" aria-label="Beranda">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
      <span>Beranda</span>
    </a>
    <a href="aktivitas-guru.php" class="nav-item" id="nav-aktivitas" aria-label="Aktivitas">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
      <span>Aktivitas</span>
    </a>
    <a href="nilai-guru.php" class="nav-item active" id="nav-nilai" aria-label="Nilai" aria-current="page">
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
