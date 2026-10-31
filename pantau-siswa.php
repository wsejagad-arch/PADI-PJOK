<?php
require_once 'auth.php';
wajibLoginGuru();
require 'koneksi.php';

// Filter: kelas, nama, dan status asesmen (isi/kosong).
$filterKelas = strtoupper(trim((string)($_GET['kelas'] ?? '')));
$cari = trim((string)($_GET['q'] ?? ''));
$filterAsesmen = (string)($_GET['asesmen'] ?? '');
$pilihanAsesmen = [
    'kognitif-isi' => 'Kognitif sudah', 'kognitif-kosong' => 'Kognitif belum',
    'video-isi' => 'Video sudah', 'video-kosong' => 'Video belum',
    'psikomotor-isi' => 'Psikomotor sudah', 'psikomotor-kosong' => 'Psikomotor belum',
    'afektif-isi' => 'Afektif sudah', 'afektif-kosong' => 'Afektif belum',
    'rekan-isi' => 'Rekan sudah', 'rekan-kosong' => 'Rekan belum',
];
if (!isset($pilihanAsesmen[$filterAsesmen])) $filterAsesmen = '';

$daftar = [];
$ringkas = ['terhubung' => 0, 'kognitif' => 0, 'video' => 0];
if ($conn) {
    $sql = "SELECT p.id, p.nama, p.created_at,
                   s.id AS sesi_id, s.token, s.kelas, s.materi,
                   (SELECT nilai_total FROM penilaian_kognitif WHERE siswa_id = p.id ORDER BY id DESC LIMIT 1) AS kog,
                   (SELECT nilai_rubrik FROM penilaian_psikomotor WHERE siswa_id = p.id ORDER BY id DESC LIMIT 1) AS psi,
                   (SELECT video_path FROM penilaian_psikomotor WHERE siswa_id = p.id ORDER BY id DESC LIMIT 1) AS video,
                   (SELECT nilai_rubrik FROM penilaian_afektif WHERE siswa_id = p.id ORDER BY id DESC LIMIT 1) AS afek,
                   (SELECT nilai_rubrik FROM penilaian_rekan WHERE penilai_id = p.id ORDER BY id DESC LIMIT 1) AS rekan
            FROM siswa p
            JOIN sesi s ON s.id = p.sesi_id
            WHERE s.status = 'aktif'
            ORDER BY s.kelas ASC, p.nama ASC";
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $row['video'] = trim((string)$row['video']);
            $ringkas['terhubung']++;
            if ($row['kog'] !== null) $ringkas['kognitif']++;
            if ($row['video'] !== '') $ringkas['video']++;
            $daftar[] = $row;
        }
    }
}

/** Apakah siswa sudah punya data untuk jenis asesmen tertentu. */
if (!function_exists('padi_asesmen_terisi')) {
function padi_asesmen_terisi(array $row, $jenis)
{
    if ($jenis === 'kognitif') return $row['kog'] !== null;
    if ($jenis === 'video') return $row['video'] !== '';
    if ($jenis === 'psikomotor') return $row['psi'] !== null;
    if ($jenis === 'afektif') return $row['afek'] !== null;
    if ($jenis === 'rekan') return $row['rekan'] !== null;
    return true;
}
}

// Filter diterapkan di PHP agar subquery tetap sederhana.
$tampil = [];
$kelasAda = [];
foreach ($daftar as $row) {
    $kelasAda[$row['kelas']] = true;
    if ($filterKelas !== '' && strcasecmp((string)$row['kelas'], $filterKelas) !== 0) continue;
    if ($cari !== '' && stripos((string)$row['nama'], $cari) === false) continue;
    if ($filterAsesmen !== '') {
        [$jenis, $status] = explode('-', $filterAsesmen, 2);
        $terisi = padi_asesmen_terisi($row, $jenis);
        if ($status === 'isi' && !$terisi) continue;
        if ($status === 'kosong' && $terisi) continue;
    }
    $tampil[] = $row;
}
$kelasAda = array_keys($kelasAda);
sort($kelasAda);

if (!function_exists('padi_jam')) {
function padi_jam($stamp)
{
    $t = strtotime((string)$stamp);
    return $t ? date('d M • H:i', $t) : '-';
}
}

/** Badge status asesmen. */
function padi_badge($terisi, $teksIsi)
{
    return $terisi
        ? '<span class="badge ok">' . htmlspecialchars($teksIsi, ENT_QUOTES, 'UTF-8') . '</span>'
        : '<span class="badge">–</span>';
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
      --green: #16A34A; --green-light: #DCFCE7; --amber: #B45309; --amber-light: #FEF3C7;
    }
    body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; padding-bottom: 24px; }
    .topbar { display: flex; align-items: center; justify-content: space-between; padding: 14px 20px; background: var(--white); border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 50; }
    .back-btn, .refresh-btn { width: 36px; height: 36px; border-radius: 10px; background: var(--blue-light); border: none; display: flex; align-items: center; justify-content: center; cursor: pointer; flex-shrink: 0; }
    .back-btn svg, .refresh-btn svg { width: 20px; height: 20px; color: var(--blue); }
    .topbar-title { font-size: 15px; font-weight: 700; }
    .content { max-width: 1040px; margin: 0 auto; padding: 16px; display: flex; flex-direction: column; gap: 14px; }
    .card { background: var(--white); border-radius: var(--radius); padding: 18px; }
    .card h1 { font-size: 17px; margin-bottom: 6px; }
    .card h2 { font-size: 14px; margin-bottom: 10px; }
    .lead { font-size: 12.5px; color: var(--text-3); line-height: 1.5; }

    .ringkas { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-top: 14px; }
    .ringkas-item { border-radius: var(--radius-sm); padding: 10px; text-align: center; background: var(--blue-light); }
    .ringkas-item.ok { background: var(--green-light); }
    .ringkas-item.warn { background: var(--amber-light); }
    .ringkas-num { display: block; font-size: 20px; font-weight: 800; }
    .ringkas-label { font-size: 10.5px; font-weight: 600; color: var(--text-3); }

    .filter { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; align-items: end; }
    .filter label { display: block; font-size: 11px; font-weight: 700; color: var(--text-3); margin-bottom: 4px; }
    .filter input, .filter select { width: 100%; font: inherit; font-size: 13px; padding: 9px 10px; border-radius: var(--radius-sm); background: #fff; }
    .filter-aksi { display: flex; gap: 8px; }
    .btn { display: inline-flex; align-items: center; justify-content: center; height: 38px; padding: 0 14px; border-radius: var(--radius-sm); font: inherit; font-size: 12.5px; font-weight: 700; cursor: pointer; background: var(--blue); color: #fff; text-decoration: none; }
    .btn-ringan { background: #fff; color: var(--blue); border: 1px solid var(--border); }

    .table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
    table { width: 100%; border-collapse: collapse; font-size: 12.5px; min-width: 720px; }
    th, td { text-align: left; padding: 9px 10px; border-bottom: 1px solid var(--border); vertical-align: middle; }
    th { font-size: 10.5px; text-transform: uppercase; letter-spacing: .04em; color: var(--text-3); background: #F8FAFC; white-space: nowrap; }
    tbody tr:last-child td { border-bottom: 0; }
    td.nama { font-weight: 700; }
    td.waktu { white-space: nowrap; color: var(--text-3); font-size: 11px; }
    .pill { display: inline-block; font-size: 10.5px; font-weight: 700; padding: 2px 8px; border-radius: 999px; background: var(--blue-light); color: var(--blue); }
    .badge { display: inline-block; font-size: 10.5px; font-weight: 700; padding: 2px 8px; border-radius: 999px; border: 1px solid var(--border); color: var(--text-3); background: #F8FAFC; }
    .badge.ok { background: var(--green-light); border-color: #86EFAC; color: #166534; }
    .kosong { text-align: center; padding: 26px 12px; color: var(--text-3); font-size: 12.5px; line-height: 1.6; }
    .nav-item { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px; color: #9CA3AF; text-decoration: none; height: 100%; }
    .nav-item svg { width: 22px; height: 22px; }
    .nav-item span { font-size: 10.5px; font-weight: 600; }
    .nav-item.active { color: var(--blue); }
  </style>
<?php require_once __DIR__ . '/ui-head.php'; padi_ui_head(); ?>
</head>
<body>
  <header class="topbar">
    <button class="back-btn" type="button" aria-label="Kembali" onclick="window.location.href='dashboard-guru.php'">
      <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </button>
    <div class="topbar-title">Pantau Siswa</div>
    <button class="refresh-btn" type="button" aria-label="Muat ulang data" onclick="window.location.href='pantau-siswa.php<?= $filterKelas || $cari || $filterAsesmen ? '?' . htmlspecialchars(http_build_query(['kelas' => $filterKelas, 'q' => $cari, 'asesmen' => $filterAsesmen]), ENT_QUOTES, 'UTF-8') : '' ?>'">
      <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 12a9 9 0 1 0 3-6.7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M3 4v5h5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </button>
  </header>

  <main class="content">
    <section class="card">
      <h1>Pantau Siswa Terhubung</h1>
      <p class="lead">Tabel siswa pada sesi aktif. Saring berdasarkan kelas, nama, atau status tugas/asesmen.</p>
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

    <section class="card">
      <h2>Filter</h2>
      <form method="get" action="pantau-siswa.php" class="filter">
        <div>
          <label for="f-kelas">Kelas</label>
          <select id="f-kelas" name="kelas">
            <option value="">Semua kelas</option>
            <?php foreach ($kelasAda as $k): ?>
              <option value="<?= htmlspecialchars($k, ENT_QUOTES, 'UTF-8') ?>" <?= (strcasecmp($filterKelas, $k) === 0) ? 'selected' : '' ?>><?= htmlspecialchars($k, ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label for="f-nama">Nama siswa</label>
          <input type="text" id="f-nama" name="q" value="<?= htmlspecialchars($cari, ENT_QUOTES, 'UTF-8') ?>" placeholder="Cari nama…" autocomplete="off"/>
        </div>
        <div>
          <label for="f-asesmen">Tugas / asesmen</label>
          <select id="f-asesmen" name="asesmen">
            <option value="">Semua status</option>
            <?php foreach ($pilihanAsesmen as $nilai => $label): ?>
              <option value="<?= htmlspecialchars($nilai, ENT_QUOTES, 'UTF-8') ?>" <?= ($filterAsesmen === $nilai) ? 'selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="filter-aksi">
          <button class="btn" type="submit">Terapkan</button>
          <a class="btn btn-ringan" href="pantau-siswa.php">Reset</a>
        </div>
      </form>
    </section>

    <section class="card">
      <h2>Daftar Siswa (<?= count($tampil) ?>)</h2>
      <?php if (!$tampil): ?>
        <div class="kosong">
          Tidak ada siswa yang cocok dengan filter.<br>
          Ubah filter atau pastikan sesi sudah aktif dan siswa sudah bergabung.
        </div>
      <?php else: ?>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Nama</th>
                <th>Kelas</th>
                <th>Token sesi</th>
                <th>Kognitif</th>
                <th>Video</th>
                <th>Psikomotor</th>
                <th>Afektif</th>
                <th>Diri/Rekan</th>
                <th>Gabung</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($tampil as $s): ?>
                <tr>
                  <td class="nama"><?= htmlspecialchars($s['nama'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td><span class="pill"><?= htmlspecialchars($s['kelas'], ENT_QUOTES, 'UTF-8') ?></span></td>
                  <td><?= htmlspecialchars($s['token'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= padi_badge($s['kog'] !== null, 'Nilai ' . (int)$s['kog']) ?></td>
                  <td><?= padi_badge($s['video'] !== '', 'Masuk') ?></td>
                  <td><?= padi_badge($s['psi'] !== null, 'Nilai ' . (int)$s['psi']) ?></td>
                  <td><?= padi_badge($s['afek'] !== null, 'Nilai ' . (int)$s['afek']) ?></td>
                  <td><?= padi_badge($s['rekan'] !== null, 'Nilai ' . ((int)$s['rekan'] * 5)) ?></td>
                  <td class="waktu"><?= htmlspecialchars(padi_jam($s['created_at']), ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
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
