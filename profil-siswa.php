<?php
require_once 'auth.php';
wajibLoginSiswa();
require 'koneksi.php';
require_once 'rubrik.php';

$masterId = (int)($_SESSION['master_id'] ?? 0);
$pesertaId = (int)($_SESSION['siswa_id'] ?? 0);

$profil = [
    'nama' => (string)($_SESSION['siswa_nama'] ?? ''),
    'nis' => (string)($_SESSION['siswa_nis'] ?? ''),
    'kelas' => (string)($_SESSION['siswa_kelas'] ?? ''),
    'created_at' => null,
    'punya_password' => false,
];
$nilai = ['kog' => null, 'psi' => null, 'afe' => null, 'diri' => null];
$sesiAktif = null;
$pesan = '';
$galat = '';

if ($conn && $masterId > 0) {
    $stmt = $conn->prepare("SELECT nama, nis, kelas, password, created_at FROM master_siswa WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $masterId);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && ($row = $res->fetch_assoc())) {
        $profil['nama'] = (string)$row['nama'];
        $profil['nis'] = (string)$row['nis'];
        $profil['kelas'] = (string)$row['kelas'];
        $profil['created_at'] = $row['created_at'];
        $profil['punya_password'] = trim((string)$row['password']) !== '';
        $_SESSION['siswa_nama'] = $profil['nama'];
    }
    $stmt->close();

    $sesiAktif = ambilSesiAktifKelas($conn, $profil['kelas']);

    if ($pesertaId > 0) {
        $q = $conn->query("SELECT nilai_total FROM penilaian_kognitif WHERE siswa_id = $pesertaId ORDER BY id DESC LIMIT 1");
        if ($q && ($x = $q->fetch_assoc())) $nilai['kog'] = (float)$x['nilai_total'];

        $q = $conn->query("SELECT COALESCE(nilai_guru, nilai_rubrik) AS n FROM penilaian_psikomotor WHERE siswa_id = $pesertaId ORDER BY id DESC LIMIT 1");
        if ($q && ($x = $q->fetch_assoc()) && $x['n'] !== null) $nilai['psi'] = (float)$x['n'] * 5;

        $q = $conn->query("SELECT COALESCE(nilai_guru, nilai_rubrik) AS n FROM penilaian_afektif WHERE siswa_id = $pesertaId ORDER BY id DESC LIMIT 1");
        if ($q && ($x = $q->fetch_assoc()) && $x['n'] !== null) $nilai['afe'] = (float)$x['n'] * 5;

        $q = $conn->query("SELECT nilai_rubrik FROM penilaian_rekan WHERE penilai_id = $pesertaId ORDER BY id DESC LIMIT 1");
        if ($q && ($x = $q->fetch_assoc())) $nilai['diri'] = (float)$x['nilai_rubrik'] * 5;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'ganti_password') {
        padi_wajib_csrf();
        $lama = (string)($_POST['password_lama'] ?? '');
        $baru = (string)($_POST['password_baru'] ?? '');
        $ulang = (string)($_POST['password_ulang'] ?? '');

        $stmt = $conn->prepare("SELECT password FROM master_siswa WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $masterId);
        $stmt->execute();
        $res = $stmt->get_result();
        $hash = ($res && ($row = $res->fetch_assoc())) ? (string)$row['password'] : '';
        $stmt->close();

        if ($hash !== '' && !password_verify($lama, $hash)) {
            $galat = 'Password lama tidak sesuai.';
        } elseif (mb_strlen($baru) < 8) {
            $galat = 'Password baru minimal 8 karakter.';
        } elseif ($baru !== $ulang) {
            $galat = 'Konfirmasi password tidak sama.';
        } else {
            $hashBaru = password_hash($baru, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE master_siswa SET password = ? WHERE id = ?");
            $stmt->bind_param('si', $hashBaru, $masterId);
            $stmt->execute();
            $stmt->close();
            $pesan = 'Password berhasil disimpan. Gunakan password baru saat login berikutnya.';
            $profil['punya_password'] = true;
        }
    }
}

$angka = [];
foreach ($nilai as $v) {
    if ($v !== null) $angka[] = $v;
}
$rata = $angka ? array_sum($angka) / count($angka) : null;
$predikat = padi_rubrik_predikat($rata);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Profil Siswa – PADI-PJOK</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --blue: #1A56DB; --blue-light: #EFF4FF; --text: #111827; --text-3: #6B7280; --border: #E5E7EB;
      --bg: #F3F6FB; --white: #FFFFFF; --radius: 16px; --radius-sm: 10px;
      --green: #16A34A; --green-light: #DCFCE7; --orange: #B45309; --orange-light: #FEF3C7;
      --red: #DC2626;
    }
    body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; padding-bottom: 24px; }
    .topbar { display: flex; align-items: center; gap: 10px; padding: 14px 18px; background: var(--white); position: sticky; top: 0; z-index: 50; }
    .back-btn { width: 36px; height: 36px; border-radius: 10px; background: var(--blue-light); border: none; display: flex; align-items: center; justify-content: center; cursor: pointer; flex-shrink: 0; }
    .back-btn svg { width: 20px; height: 20px; color: var(--blue); }
    .topbar-title { font-size: 15px; font-weight: 700; flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .content { max-width: 720px; margin: 0 auto; padding: 16px; display: flex; flex-direction: column; gap: 14px; }
    .card { background: var(--white); border-radius: var(--radius); padding: 18px; }
    .card h1 { font-size: 17px; margin-bottom: 6px; }
    .card h2 { font-size: 14px; margin-bottom: 10px; }
    .lead { font-size: 12.5px; color: var(--text-3); line-height: 1.55; }
    .identitas { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 10px; margin-top: 12px; }
    .identitas div { border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 10px; }
    .identitas span { display: block; font-size: 10.5px; font-weight: 700; text-transform: uppercase; color: var(--text-3); }
    .identitas strong { font-size: 14px; }
    .ringkas { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; margin-top: 12px; }
    @media (min-width: 560px) { .ringkas { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
    .ringkas-item { border-radius: var(--radius-sm); padding: 10px; text-align: center; background: var(--blue-light); }
    .ringkas-item.green { background: var(--green-light); }
    .ringkas-item.orange { background: var(--orange-light); }
    .ringkas-num { display: block; font-size: 19px; font-weight: 800; }
    .ringkas-label { font-size: 10.5px; font-weight: 600; color: var(--text-3); }
    .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 10px; margin-top: 6px; }
    .form-group label { display: block; font-size: 11px; font-weight: 700; color: var(--text-3); margin-bottom: 4px; }
    .form-input { width: 100%; font: inherit; font-size: 13px; padding: 9px 10px; border-radius: var(--radius-sm); background: #fff; }
    .flash { border-radius: var(--radius-sm); padding: 10px 12px; font-size: 12.5px; font-weight: 600; background: var(--green-light); color: #166534; margin-bottom: 10px; }
    .flash.galat { background: #FEE2E2; color: #991B1B; }
    .btn { display: inline-flex; align-items: center; justify-content: center; height: 38px; padding: 0 14px; border-radius: var(--radius-sm); font: inherit; font-size: 12.5px; font-weight: 700; cursor: pointer; background: var(--blue); color: #fff; text-decoration: none; border: 0; }
    .btn-ringan { background: #fff; color: var(--blue); border: 1px solid var(--border); }
    .btn-keluar { background: #fff; color: #B91C1C; border: 1px solid #FCA5A5; }
    .aksi { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
    .nav-item { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px; color: #9CA3AF; text-decoration: none; height: 100%; }
    .nav-item svg { width: 22px; height: 22px; }
    .nav-item span { font-size: 10.5px; font-weight: 600; }
    .nav-item.active { color: var(--blue); }
  </style>
<?php require_once __DIR__ . '/ui-head.php'; padi_ui_head(); ?>
</head>
<body>
  <header class="topbar">
    <button class="back-btn" type="button" aria-label="Kembali" onclick="window.location.href='dashboard-siswa.php'">
      <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </button>
    <div class="topbar-title">Profil Saya</div>
  </header>

  <main class="content">
    <section class="card">
      <h1>Profil Siswa</h1>
      <p class="lead">Identitas akun, sesi kelas yang sedang berjalan, dan capaian penilaian terakhir.</p>
      <div class="identitas">
        <div><span>Nama</span><strong><?= htmlspecialchars($profil['nama'] !== '' ? $profil['nama'] : '-', ENT_QUOTES, 'UTF-8') ?></strong></div>
        <div><span>NIS</span><strong><?= htmlspecialchars($profil['nis'] !== '' ? $profil['nis'] : '-', ENT_QUOTES, 'UTF-8') ?></strong></div>
        <div><span>Kelas</span><strong><?= htmlspecialchars($profil['kelas'] !== '' ? $profil['kelas'] : '-', ENT_QUOTES, 'UTF-8') ?></strong></div>
        <div><span>Sesi aktif</span><strong><?= $sesiAktif ? htmlspecialchars(ucwords(str_replace('-', ' ', (string)$sesiAktif['materi'])), ENT_QUOTES, 'UTF-8') : 'Belum ada' ?></strong></div>
      </div>
    </section>

    <section class="card">
      <h2>Capaian Terakhir</h2>
      <div class="ringkas">
        <div class="ringkas-item"><span class="ringkas-num"><?= $nilai['kog'] === null ? '–' : round($nilai['kog']) ?></span><span class="ringkas-label">Kognitif</span></div>
        <div class="ringkas-item green"><span class="ringkas-num"><?= $nilai['psi'] === null ? '–' : round($nilai['psi']) ?></span><span class="ringkas-label">Psikomotor</span></div>
        <div class="ringkas-item orange"><span class="ringkas-num"><?= $nilai['afe'] === null ? '–' : round($nilai['afe']) ?></span><span class="ringkas-label">Afektif</span></div>
        <div class="ringkas-item"><span class="ringkas-num"><?= $nilai['diri'] === null ? '–' : round($nilai['diri']) ?></span><span class="ringkas-label">Diri/Rekan</span></div>
      </div>
      <p class="lead" style="margin-top:10px;">Rata-rata <?= $rata === null ? '–' : round($rata) ?> · Predikat <strong><?= htmlspecialchars($predikat[0], ENT_QUOTES, 'UTF-8') ?></strong><?= ($rata !== null && $rata >= PADI_TUNTAS) ? ' · Tuntas' : '' ?>.</p>
    </section>

    <section class="card">
      <h2><?= $profil['punya_password'] ? 'Ganti Password' : 'Buat Password' ?></h2>
      <?php if ($pesan !== ''): ?><div class="flash"><?= htmlspecialchars($pesan, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
      <?php if ($galat !== ''): ?><div class="flash galat"><?= htmlspecialchars($galat, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
      <p class="lead"><?= $profil['punya_password'] ? 'Masukkan password lama lalu password baru (minimal 8 karakter).' : 'Akun Anda belum memiliki password. Buat password baru (minimal 8 karakter) agar bisa login tanpa token.' ?></p>
      <form method="post" action="profil-siswa.php">
        <?= padi_csrf_field() ?>
        <input type="hidden" name="aksi" value="ganti_password"/>
        <div class="form-grid">
          <?php if ($profil['punya_password']): ?>
            <div class="form-group">
              <label for="f-lama">Password lama</label>
              <input class="form-input" type="password" id="f-lama" name="password_lama" autocomplete="current-password" required/>
            </div>
          <?php endif; ?>
          <div class="form-group">
            <label for="f-baru">Password baru (min. 8)</label>
            <input class="form-input" type="password" id="f-baru" name="password_baru" minlength="8" autocomplete="new-password" required/>
          </div>
          <div class="form-group">
            <label for="f-ulang">Ulangi password baru</label>
            <input class="form-input" type="password" id="f-ulang" name="password_ulang" minlength="8" autocomplete="new-password" required/>
          </div>
        </div>
        <div class="aksi">
          <button class="btn" type="submit">Simpan Password</button>
        </div>
      </form>
    </section>

    <section class="card">
      <h2>Aksi</h2>
      <div class="aksi">
        <a class="btn btn-ringan" href="dashboard-siswa.php">Dashboard</a>
        <a class="btn btn-ringan" href="aktivitas-siswa.php">Materi &amp; Aktivitas</a>
        <a class="btn btn-keluar" href="logout.php" onclick="return confirm('Keluar dari akun siswa ini?');">Keluar</a>
      </div>
    </section>
  </main>

  <nav class="nav-bottom" role="navigation" aria-label="Navigasi utama">
    <a href="dashboard-siswa.php" class="nav-item" aria-label="Beranda">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 10.5L12 3l9 7.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M5 9.5V21h14V9.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
      <span>Beranda</span>
    </a>
    <a href="aktivitas-siswa.php" class="nav-item" aria-label="Aktivitas">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 016.5 17H20" stroke-linecap="round"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/></svg>
      <span>Aktivitas</span>
    </a>
    <a href="dashboard-siswa.php#nilai-terbaru" class="nav-item" aria-label="Nilai">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 20V10M12 20V4M6 20v-6" stroke-linecap="round"/></svg>
      <span>Nilai</span>
    </a>
    <a href="profil-siswa.php" class="nav-item active" aria-label="Profil" aria-current="page">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7" stroke-linecap="round"/></svg>
      <span>Profil</span>
    </a>
    <a href="logout.php" class="nav-item" aria-label="Keluar" onclick="return confirm('Keluar dari akun siswa ini?');">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 17l5-5-5-5" stroke-linecap="round" stroke-linejoin="round"/><path d="M20 12H9" stroke-linecap="round"/><path d="M12 3H6a2 2 0 00-2 2v14a2 2 0 002 2h6" stroke-linecap="round"/></svg>
      <span>Keluar</span>
    </a>
  </nav>
</body>
</html>
