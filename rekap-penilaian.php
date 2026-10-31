<?php
require_once 'auth.php';
wajibLoginGuru();
require 'koneksi.php';

$filterKelas = strtoupper(trim((string)($_GET['kelas'] ?? '')));
$cari = trim((string)($_GET['q'] ?? ''));

/**
 * Nilai tiap indikator (skala 0–100).
 * Kognitif sudah 0–100; rubrik afektif/psikomotor/rekan 0–20 → dikali 5.
 */
$students = [];
$rata = ['kog' => null, 'psi' => null, 'afe' => null, 'diri' => null];
$kelasAda = [];

if ($conn) {
    pastikanKolomVerifikasi($conn);

    $sql = "SELECT p.id, p.nama, s.kelas, s.token,
                   (SELECT nilai_total FROM penilaian_kognitif WHERE siswa_id = p.id ORDER BY id DESC LIMIT 1) AS kog,
                   (SELECT COALESCE(nilai_guru, nilai_rubrik) FROM penilaian_psikomotor WHERE siswa_id = p.id ORDER BY id DESC LIMIT 1) AS psi,
                   (SELECT COALESCE(nilai_guru, nilai_rubrik) FROM penilaian_afektif WHERE siswa_id = p.id ORDER BY id DESC LIMIT 1) AS afe,
                   (SELECT nilai_rubrik FROM penilaian_rekan WHERE penilai_id = p.id ORDER BY id DESC LIMIT 1) AS diri,
                   (SELECT video_path FROM penilaian_psikomotor WHERE siswa_id = p.id ORDER BY id DESC LIMIT 1) AS video
            FROM siswa p
            JOIN sesi s ON s.id = p.sesi_id";

    $kondisi = [];
    $tipe = '';
    $nilai = [];
    if ($filterKelas !== '') {
        $kondisi[] = 's.kelas = ?';
        $tipe .= 's';
        $nilai[] = $filterKelas;
    }
    if ($cari !== '') {
        $kondisi[] = 'p.nama LIKE ?';
        $tipe .= 's';
        $nilai[] = '%' . $cari . '%';
    }
    if ($kondisi) $sql .= ' WHERE ' . implode(' AND ', $kondisi);
    $sql .= ' ORDER BY s.kelas ASC, p.nama ASC';

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if ($tipe !== '') $stmt->bind_param($tipe, ...$nilai);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($res && ($row = $res->fetch_assoc())) {
            $row['kog'] = ($row['kog'] === null) ? null : (float)$row['kog'];
            $row['psi'] = ($row['psi'] === null) ? null : (float)$row['psi'] * 5;
            $row['afe'] = ($row['afe'] === null) ? null : (float)$row['afe'] * 5;
            $row['diri'] = ($row['diri'] === null) ? null : (float)$row['diri'] * 5;
            $row['video'] = trim((string)$row['video']);

            $angka = [];
            foreach (['kog', 'psi', 'afe', 'diri'] as $kunci) {
                if ($row[$kunci] !== null) $angka[] = $row[$kunci];
            }
            $row['rata'] = $angka ? array_sum($angka) / count($angka) : null;
            $students[] = $row;
        }
        $stmt->close();
    }

    // Daftar kelas untuk filter
    $qKelas = $conn->query("SELECT DISTINCT kelas FROM sesi ORDER BY kelas");
    if ($qKelas) while ($r = $qKelas->fetch_assoc()) $kelasAda[] = (string)$r['kelas'];
}
sort($kelasAda);

// Rata-rata kelas (dari baris yang tampil)
$hitung = ['kog' => [], 'psi' => [], 'afe' => [], 'diri' => []];
foreach ($students as $s) {
    foreach (['kog', 'psi', 'afe', 'diri'] as $kunci) {
        if ($s[$kunci] !== null) $hitung[$kunci][] = $s[$kunci];
    }
}
foreach ($hitung as $kunci => $arr) {
    $rata[$kunci] = $arr ? array_sum($arr) / count($arr) : null;
}

if (!function_exists('getGrade')) {
function getGrade($val)
{
    if ($val === null) return ['–', 'abu'];
    if ($val >= 85) return ['Sangat Baik', 'green'];
    if ($val >= 70) return ['Baik', 'blue'];
    if ($val >= 50) return ['Cukup', 'orange'];
    return ['Kurang', 'red'];
}
}

/** Tampilkan angka 0–100 atau tanda hubung bila belum ada nilai. */
function padi_angka($val)
{
    return ($val === null) ? '–' : (string)round($val);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Rekap Penilaian – PADI-PJOK</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --blue: #1A56DB; --blue-light: #EFF4FF; --text: #111827; --text-3: #6B7280; --border: #E5E7EB;
      --bg: #F3F6FB; --white: #FFFFFF; --radius: 16px; --radius-sm: 10px;
      --green: #16A34A; --green-light: #DCFCE7; --amber: #B45309; --amber-light: #FEF3C7;
      --red: #DC2626; --red-light: #FEE2E2;
    }
    body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; padding-bottom: 24px; }
    .topbar { display: flex; align-items: center; gap: 10px; padding: 14px 18px; background: var(--white); position: sticky; top: 0; z-index: 50; }
    .back-btn { width: 36px; height: 36px; border-radius: 10px; background: var(--blue-light); border: none; display: flex; align-items: center; justify-content: center; cursor: pointer; flex-shrink: 0; }
    .back-btn svg { width: 20px; height: 20px; color: var(--blue); }
    .topbar-title { font-size: 15px; font-weight: 700; flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .topbar .btn { height: 34px; padding: 0 10px; font-size: 11.5px; white-space: nowrap; }
    .content { max-width: 1060px; margin: 0 auto; padding: 16px; display: flex; flex-direction: column; gap: 14px; }
    .card { background: var(--white); border-radius: var(--radius); padding: 18px; }
    .card h1 { font-size: 17px; margin-bottom: 6px; }
    .card h2 { font-size: 14px; margin-bottom: 10px; }
    .lead { font-size: 12.5px; color: var(--text-3); line-height: 1.5; }

    .ringkas { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; margin-top: 14px; }
    @media (min-width: 560px) {
      .ringkas { grid-template-columns: repeat(4, minmax(0, 1fr)); }
    }
    .ringkas-item { border-radius: var(--radius-sm); padding: 10px; text-align: center; background: var(--blue-light); }
    .ringkas-item.green { background: var(--green-light); }
    .ringkas-item.amber { background: var(--amber-light); }
    .ringkas-item.slate { background: #F1F5F9; }
    .ringkas-num { display: block; font-size: 19px; font-weight: 800; }
    .ringkas-label { font-size: 10.5px; font-weight: 600; color: var(--text-3); }

    .filter { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; align-items: end; }
    .filter label { display: block; font-size: 11px; font-weight: 700; color: var(--text-3); margin-bottom: 4px; }
    .filter input, .filter select { width: 100%; font: inherit; font-size: 13px; padding: 9px 10px; border-radius: var(--radius-sm); background: #fff; }
    .filter-aksi { display: flex; gap: 8px; }
    .btn { display: inline-flex; align-items: center; justify-content: center; height: 38px; padding: 0 14px; border-radius: var(--radius-sm); font: inherit; font-size: 12.5px; font-weight: 700; cursor: pointer; background: var(--blue); color: #fff; text-decoration: none; }
    .btn-ringan { background: #fff; color: var(--blue); border: 1px solid var(--border); }

    .table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
    table { width: 100%; border-collapse: collapse; font-size: 12.5px; min-width: 780px; }
    th, td { text-align: left; padding: 9px 10px; border-bottom: 1px solid var(--border); vertical-align: middle; }
    th { font-size: 10.5px; text-transform: uppercase; letter-spacing: .04em; color: var(--text-3); background: #F8FAFC; white-space: nowrap; }
    th.num, td.num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
    tbody tr:last-child td { border-bottom: 0; }
    td.nama { font-weight: 700; }
    td.nama a { color: var(--text); text-decoration: none; }
    td.nama a:hover { color: var(--blue); }
    .pill { display: inline-block; font-size: 10.5px; font-weight: 700; padding: 2px 8px; border-radius: 999px; background: var(--blue-light); color: var(--blue); }
    .n { font-weight: 700; }
    .n.green { color: #166534; } .n.blue { color: var(--blue); } .n.orange { color: var(--amber); } .n.red { color: var(--red); } .n.abu { color: var(--text-3); }
    .video-tag { font-size: 10px; font-weight: 700; color: #166534; background: var(--green-light); border-radius: 999px; padding: 2px 6px; margin-left: 4px; }
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
    <div class="topbar-title">Rekap Penilaian</div>
    <a class="btn btn-ringan" href="verifikasi-nilai.php">Verifikasi</a>
  </header>

  <main class="content">
    <section class="card">
      <h1>Rekap Nilai per Indikator</h1>
      <p class="lead">
        <?= count($students) ?> siswa ditampilkan<?= $filterKelas !== '' ? ' · kelas ' . htmlspecialchars($filterKelas, ENT_QUOTES, 'UTF-8') : ' · semua kelas' ?><?= $cari !== '' ? ' · pencarian “' . htmlspecialchars($cari, ENT_QUOTES, 'UTF-8') . '”' : '' ?>.
        Nilai rubrik (0–20) sudah dikonversi ke skala 0–100.
      </p>
      <div class="ringkas">
        <div class="ringkas-item">
          <span class="ringkas-num"><?= $rata['kog'] === null ? '–' : round($rata['kog']) ?></span>
          <span class="ringkas-label">Kognitif</span>
        </div>
        <div class="ringkas-item green">
          <span class="ringkas-num"><?= $rata['afe'] === null ? '–' : round($rata['afe']) ?></span>
          <span class="ringkas-label">Afektif</span>
        </div>
        <div class="ringkas-item amber">
          <span class="ringkas-num"><?= $rata['psi'] === null ? '–' : round($rata['psi']) ?></span>
          <span class="ringkas-label">Psikomotor</span>
        </div>
        <div class="ringkas-item slate">
          <span class="ringkas-num"><?= $rata['diri'] === null ? '–' : round($rata['diri']) ?></span>
          <span class="ringkas-label">Diri/Rekan</span>
        </div>
      </div>
    </section>

    <section class="card">
      <h2>Filter</h2>
      <form method="get" action="rekap-penilaian.php" class="filter">
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
        <div class="filter-aksi">
          <button class="btn" type="submit">Terapkan</button>
          <a class="btn btn-ringan" href="rekap-penilaian.php">Reset</a>
        </div>
      </form>
    </section>

    <section class="card">
      <h2>Tabel Rekap (<?= count($students) ?>)</h2>
      <?php if (!$students): ?>
        <div class="kosong">Tidak ada siswa yang cocok dengan filter.<br>Ubah kelas/nama, atau pastikan siswa sudah bergabung pada sesi.</div>
      <?php else: ?>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Nama</th>
                <th>Kelas</th>
                <th class="num">Kognitif</th>
                <th class="num">Afektif</th>
                <th class="num">Psikomotor</th>
                <th class="num">Diri/Rekan</th>
                <th class="num">Rata-rata</th>
                <th>Predikat</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($students as $s): $g = getGrade($s['rata']); ?>
                <tr>
                  <td class="nama">
                    <a href="rekap-penilaian-siswa.php?id=<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['nama'], ENT_QUOTES, 'UTF-8') ?></a>
                    <?php if ($s['video'] !== ''): ?><span class="video-tag">video</span><?php endif; ?>
                  </td>
                  <td><span class="pill"><?= htmlspecialchars($s['kelas'], ENT_QUOTES, 'UTF-8') ?></span></td>
                  <td class="num"><span class="n <?= getGrade($s['kog'])[1] ?>"><?= padi_angka($s['kog']) ?></span></td>
                  <td class="num"><span class="n <?= getGrade($s['afe'])[1] ?>"><?= padi_angka($s['afe']) ?></span></td>
                  <td class="num"><span class="n <?= getGrade($s['psi'])[1] ?>"><?= padi_angka($s['psi']) ?></span></td>
                  <td class="num"><span class="n <?= getGrade($s['diri'])[1] ?>"><?= padi_angka($s['diri']) ?></span></td>
                  <td class="num"><span class="n <?= $g[1] ?>"><?= padi_angka($s['rata']) ?></span></td>
                  <td><span class="n <?= $g[1] ?>"><?= htmlspecialchars($g[0], ENT_QUOTES, 'UTF-8') ?></span></td>
                  <td><a class="btn btn-ringan" href="rekap-penilaian-siswa.php?id=<?= (int)$s['id'] ?>">Detail</a></td>
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
</body>
</html>
