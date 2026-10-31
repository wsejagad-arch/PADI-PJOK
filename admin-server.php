<?php
// admin-server.php — status server & ringkasan lintas sekolah. Khusus super-admin.
require_once 'auth.php';
padi_wajib_super();
require 'koneksi.php';

/** Jalankan perintah sistem dengan aman (tanpa input pengguna). */
if (!function_exists('padi_server_exec')) {
function padi_server_exec($perintah)
{
    if (!function_exists('shell_exec')) return '';
    if (function_exists('ini_get') && stripos((string)ini_get('disable_functions'), 'shell_exec') !== false) return '';
    $keluaran = @shell_exec($perintah . ' 2>/dev/null');
    return trim((string)$keluaran);
}
}

function padi_ukuran($byte)
{
    $byte = (float)$byte;
    $satuan = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($byte >= 1024 && $i < count($satuan) - 1) {
        $byte /= 1024;
        $i++;
    }
    return number_format($byte, $i >= 3 ? 2 : 0, ',', '.') . ' ' . $satuan[$i];
}

// ---- Status sistem ----
$root = __DIR__;
$diskTotal = @disk_total_space($root);
$diskFree  = @disk_free_space($root);
$diskPakai = ($diskTotal && $diskFree !== false) ? (float)$diskTotal - (float)$diskFree : null;
$diskPct   = ($diskTotal && $diskPakai !== null) ? round($diskPakai / (float)$diskTotal * 100) : null;

$memTotal = $memTersedia = null;
if (@is_readable('/proc/meminfo')) {
    foreach (file('/proc/meminfo') as $baris) {
        if (strpos($baris, 'MemTotal:') === 0) $memTotal = (int)preg_replace('/\D/', '', $baris) * 1024;
        if (strpos($baris, 'MemAvailable:') === 0) $memTersedia = (int)preg_replace('/\D/', '', $baris) * 1024;
    }
}
$memPct = ($memTotal && $memTersedia !== null) ? round(($memTotal - $memTersedia) / $memTotal * 100) : null;

$uptimeDetik = null;
if (@is_readable('/proc/uptime')) {
    $bagian = explode(' ', trim((string)file_get_contents('/proc/uptime')));
    if (isset($bagian[0])) $uptimeDetik = (int)round((float)$bagian[0]);
}
$uptimeTeks = '-';
if ($uptimeDetik !== null) {
    $hari = intdiv($uptimeDetik, 86400);
    $jam = intdiv($uptimeDetik % 86400, 3600);
    $menit = intdiv($uptimeDetik % 3600, 60);
    $uptimeTeks = ($hari > 0 ? $hari . 'h ' : '') . $jam . 'j ' . $menit . 'm';
}

$load = function_exists('sys_getloadavg') ? @sys_getloadavg() : null;
$loadTeks = is_array($load) ? number_format((float)$load[0], 2, ',', '.') . ' / ' . number_format((float)$load[1], 2, ',', '.') . ' / ' . number_format((float)$load[2], 2, ',', '.') : '-';

$layanan = [];
foreach ([
    'nginx' => 'systemctl is-active nginx',
    'php8.2-fpm' => 'systemctl is-active php8.2-fpm',
    'mariadb/mysql' => 'systemctl is-active mariadb || systemctl is-active mysql',
    'cloudflared' => 'systemctl is-active cloudflared',
] as $nama => $cmd) {
    $hasil = padi_server_exec($cmd);
    $layanan[$nama] = ($hasil === '') ? 'tidak diketahui' : $hasil;
}

$versiPhp = PHP_VERSION;
$versiDb = '';
$qDb = $conn ? $conn->query("SELECT VERSION() AS v") : null;
if ($qDb && ($rDb = $qDb->fetch_assoc())) $versiDb = (string)$rDb['v'];

$siagaDb = false;
if ($conn) {
    $qCek = $conn->query("SELECT 1 AS ok");
    $siagaDb = (bool)($qCek && $qCek->fetch_assoc());
}

$commit = '';
$gitHead = @file_get_contents(__DIR__ . '/.git/HEAD');
if ($gitHead) {
    $gitHead = trim($gitHead);
    if (strpos($gitHead, 'ref:') === 0) {
        $refPath = __DIR__ . '/.git/' . trim(substr($gitHead, 4));
        $sha = @file_get_contents($refPath);
        $commit = $sha ? substr(trim($sha), 0, 7) : '';
    } else {
        $commit = substr($gitHead, 0, 7);
    }
}

// ---- Ringkasan per sekolah ----
$sekolah = [];
if ($conn) {
    $sql = "SELECT s.id, s.nama, s.slug, s.status,
                   (SELECT COUNT(*) FROM guru g WHERE g.sekolah_id = s.id) AS guru,
                   (SELECT COUNT(*) FROM guru g WHERE g.sekolah_id = s.id AND g.status = 'aktif') AS guru_aktif,
                   (SELECT COUNT(*) FROM master_siswa m WHERE m.sekolah_id = s.id) AS siswa,
                   (SELECT COUNT(*) FROM sesi se WHERE se.sekolah_id = s.id) AS sesi,
                   (SELECT COUNT(*) FROM sesi se WHERE se.sekolah_id = s.id AND se.status = 'aktif') AS sesi_aktif,
                   (SELECT COUNT(*) FROM jadwal j WHERE j.sekolah_id = s.id) AS jadwal
            FROM sekolah s ORDER BY s.nama ASC";
    $q = $conn->query($sql);
    if ($q) {
        while ($row = $q->fetch_assoc()) $sekolah[] = $row;
    }
}

// ---- Akun terakhir login (lintas sekolah) ----
$loginTerakhir = [];
if ($conn) {
    $q = $conn->query("SELECT g.nama, g.username, g.terakhir_login, s.nama AS sekolah
                       FROM guru g LEFT JOIN sekolah s ON s.id = g.sekolah_id
                       WHERE g.terakhir_login IS NOT NULL
                       ORDER BY g.terakhir_login DESC LIMIT 10");
    if ($q) {
        while ($row = $q->fetch_assoc()) $loginTerakhir[] = $row;
    }
}

// ---- Ukuran data ----
$ukuran = [];
if ($conn) {
    $dbName = (string)getenv('DB_NAME');
    if ($dbName !== '') {
        $stmt = $conn->prepare("SELECT SUM(data_length + index_length) AS byte FROM information_schema.TABLES WHERE table_schema = ?");
        if ($stmt) {
            $stmt->bind_param('s', $dbName);
            $stmt->execute();
            $r = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $ukuran['Database'] = padi_ukuran((float)($r['byte'] ?? 0));
        }
    }
    foreach (['sesi', 'master_siswa', 'jadwal', 'guru'] as $t) {
        $q = $conn->query("SELECT COUNT(*) AS c FROM $t");
        if ($q && ($r = $q->fetch_assoc())) $ukuran['Baris ' . $t] = number_format((int)$r['c'], 0, ',', '.');
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Status Server – PADI-PJOK</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --blue: #1A56DB; --blue-light: #EFF4FF; --text: #111827; --text-3: #6B7280;
      --border: #E5E7EB; --bg: #F3F6FB; --white: #FFFFFF; --radius: 16px; --radius-sm: 10px;
      --green: #16A34A; --red: #DC2626;
    }
    body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; padding-bottom: 24px; }
    .topbar { display: flex; align-items: center; gap: 10px; padding: 14px 18px; background: var(--white); position: sticky; top: 0; z-index: 50; }
    .back-btn { width: 36px; height: 36px; border-radius: 10px; background: var(--blue-light); border: none; display: flex; align-items: center; justify-content: center; cursor: pointer; }
    .back-btn svg { width: 20px; height: 20px; color: var(--blue); }
    .topbar-title { font-size: 15px; font-weight: 700; }
    .content { max-width: 1040px; margin: 0 auto; padding: 16px; display: flex; flex-direction: column; gap: 14px; }
    .card { background: var(--white); border-radius: var(--radius); padding: 18px; }
    .card h1 { font-size: 17px; margin-bottom: 6px; }
    .card h2 { font-size: 14px; margin-bottom: 10px; }
    .lead { font-size: 12.5px; color: var(--text-3); line-height: 1.55; }
    .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 10px; margin-top: 12px; }
    .item { border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 10px; }
    .item span { display: block; font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .03em; color: var(--text-3); }
    .item strong { font-size: 14px; }
    .bar { height: 8px; border-radius: 999px; background: #E5E7EB; overflow: hidden; margin-top: 6px; }
    .bar i { display: block; height: 100%; background: var(--blue); }
    .bar i.warn { background: #F59E0B; }
    .bar i.bad { background: var(--red); }
    .table-wrap { overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; font-size: 12.5px; min-width: 720px; }
    th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid var(--border); }
    th { font-size: 10.5px; text-transform: uppercase; letter-spacing: .04em; color: var(--text-3); background: #F8FAFC; white-space: nowrap; }
    .pill { display: inline-block; font-size: 10.5px; font-weight: 700; padding: 2px 8px; border-radius: 999px; background: var(--blue-light); color: var(--blue); }
    .pill.ok { background: #DCFCE7; color: #166534; }
    .pill.off { background: #FEE2E2; color: #B91C1C; }
    .btn { display: inline-flex; align-items: center; justify-content: center; height: 34px; padding: 0 12px; border-radius: var(--radius-sm); font: inherit; font-size: 12px; font-weight: 700; cursor: pointer; background: var(--blue); color: #fff; text-decoration: none; border: 0; }
    .btn-ringan { background: #fff; color: var(--blue); border: 1px solid var(--border); }
    .aksi { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
  </style>
<?php require_once __DIR__ . '/ui-head.php'; padi_ui_head(); ?>
</head>
<body>
  <header class="topbar">
    <button class="back-btn" type="button" aria-label="Kembali" onclick="window.location.href='profil-guru.php'">
      <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </button>
    <div class="topbar-title">Status Server</div>
  </header>

  <main class="content">
    <section class="card">
      <h1>Kesehatan Platform</h1>
      <p class="lead">Ringkasan sumber daya server, layanan, dan aktivitas tenant. Halaman ini khusus super-admin.</p>
      <div class="grid">
        <div class="item"><span>Database</span><strong><?= $siagaDb ? 'terhubung' : 'gagal' ?></strong><?= $versiDb !== '' ? '<br><small>' . htmlspecialchars($versiDb, ENT_QUOTES, 'UTF-8') . '</small>' : '' ?></div>
        <div class="item"><span>PHP</span><strong><?= htmlspecialchars($versiPhp, ENT_QUOTES, 'UTF-8') ?></strong></div>
        <div class="item"><span>Uptime server</span><strong><?= htmlspecialchars($uptimeTeks, ENT_QUOTES, 'UTF-8') ?></strong></div>
        <div class="item"><span>Load (1/5/15 mnt)</span><strong><?= htmlspecialchars($loadTeks, ENT_QUOTES, 'UTF-8') ?></strong></div>
        <div class="item"><span>Commit aplikasi</span><strong><?= htmlspecialchars($commit !== '' ? $commit : '-', ENT_QUOTES, 'UTF-8') ?></strong></div>
      </div>
      <div class="grid">
        <div class="item">
          <span>Disk</span><strong><?= $diskPakai !== null ? padi_ukuran($diskPakai) . ' / ' . padi_ukuran((float)$diskTotal) : '-' ?></strong>
          <?php if ($diskPct !== null): ?><div class="bar"><i class="<?= $diskPct >= 90 ? 'bad' : ($diskPct >= 75 ? 'warn' : '') ?>" style="width:<?= (int)$diskPct ?>%"></i></div><small><?= (int)$diskPct ?>% terpakai</small><?php endif; ?>
        </div>
        <div class="item">
          <span>Memori</span><strong><?= $memTotal ? padi_ukuran($memTotal - (int)$memTersedia) . ' / ' . padi_ukuran($memTotal) : '-' ?></strong>
          <?php if ($memPct !== null): ?><div class="bar"><i class="<?= $memPct >= 90 ? 'bad' : ($memPct >= 75 ? 'warn' : '') ?>" style="width:<?= (int)$memPct ?>%"></i></div><small><?= (int)$memPct ?>% terpakai</small><?php endif; ?>
        </div>
      </div>
    </section>

    <section class="card">
      <h2>Layanan</h2>
      <div class="grid">
        <?php foreach ($layanan as $nama => $status): ?>
          <div class="item">
            <span><?= htmlspecialchars($nama, ENT_QUOTES, 'UTF-8') ?></span>
            <strong><span class="pill <?= $status === 'active' ? 'ok' : 'off' ?>"><?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?></span></strong>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="card">
      <h2>Data Platform</h2>
      <div class="grid">
        <?php foreach ($ukuran as $label => $nilai): ?>
          <div class="item"><span><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span><strong><?= htmlspecialchars((string)$nilai, ENT_QUOTES, 'UTF-8') ?></strong></div>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="card">
      <h2>Sekolah &amp; Pemakaian</h2>
      <div class="table-wrap">
        <table>
          <thead>
            <tr><th>Sekolah</th><th>Slug</th><th>Guru (aktif)</th><th>Siswa</th><th>Sesi (aktif)</th><th>Jadwal</th><th>Status</th></tr>
          </thead>
          <tbody>
            <?php if (!$sekolah): ?>
              <tr><td colspan="7">Belum ada sekolah.</td></tr>
            <?php else: foreach ($sekolah as $s): ?>
              <tr>
                <td><?= htmlspecialchars((string)$s['nama'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string)$s['slug'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= (int)$s['guru'] ?> (<?= (int)$s['guru_aktif'] ?>)</td>
                <td><?= (int)$s['siswa'] ?></td>
                <td><?= (int)$s['sesi'] ?> (<?= (int)$s['sesi_aktif'] ?>)</td>
                <td><?= (int)$s['jadwal'] ?></td>
                <td><span class="pill <?= strtolower((string)$s['status']) === 'aktif' ? 'ok' : 'off' ?>"><?= htmlspecialchars((string)$s['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
      <div class="aksi">
        <a class="btn" href="sekolah-admin.php">Kelola Sekolah</a>
        <a class="btn btn-ringan" href="admin-pengguna.php">Kelola Akun Guru</a>
      </div>
    </section>

    <section class="card">
      <h2>Login Terakhir</h2>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Guru</th><th>Username</th><th>Sekolah</th><th>Waktu</th></tr></thead>
          <tbody>
            <?php if (!$loginTerakhir): ?>
              <tr><td colspan="4">Belum ada data login.</td></tr>
            <?php else: foreach ($loginTerakhir as $l): ?>
              <tr>
                <td><?= htmlspecialchars((string)$l['nama'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string)$l['username'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string)($l['sekolah'] ?: '-'), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime((string)$l['terakhir_login'])), ENT_QUOTES, 'UTF-8') ?></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </section>
  </main>
</body>
</html>
