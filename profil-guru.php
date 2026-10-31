<?php
require_once 'auth.php';
wajibLoginGuru();
require 'koneksi.php';
padi_pastikan_kolom_nip_guru($conn);

$guruId = (int)($_SESSION['guru_id'] ?? 0);
$pesan = '';
$galat = '';

$profil = ['nama' => (string)($_SESSION['guru_nama'] ?? ''), 'username' => (string)($_SESSION['guru_username'] ?? ''), 'nip' => (string)($_SESSION['guru_nip'] ?? ''), 'created_at' => null];
$stat = ['sesi' => 0, 'sesi_aktif' => 0, 'siswa' => 0, 'penilaian' => 0];

if ($conn && $guruId > 0) {
    $stmt = $conn->prepare("SELECT nama, username, nip, created_at FROM guru WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $guruId);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && ($row = $res->fetch_assoc())) {
        $profil = $row;
        $_SESSION['guru_nama'] = (string)$row['nama'];
        $_SESSION['guru_nip']  = (string)($row['nip'] ?? '');
    }
    $stmt->close();

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'simpan_identitas') {
        padi_wajib_csrf();
        $namaBaru = trim((string)($_POST['nama_guru'] ?? ''));
        $nipBaru  = trim((string)($_POST['nip_guru'] ?? ''));
        $nipBaru  = preg_replace('/\s+/u', ' ', $nipBaru);

        if (mb_strlen($namaBaru) < 3) {
            $galat = 'Nama guru minimal 3 karakter.';
        } elseif (mb_strlen($namaBaru) > 100) {
            $galat = 'Nama guru maksimal 100 karakter.';
        } elseif ($nipBaru !== '' && !preg_match('/^[0-9][0-9.\- ]{5,29}$/', $nipBaru)) {
            $galat = 'NIP hanya boleh angka (boleh dipisah titik, spasi, atau tanda hubung), 6–30 karakter.';
        } else {
            $stmt = $conn->prepare("UPDATE guru SET nama = ?, nip = ? WHERE id = ?");
            $stmt->bind_param('ssi', $namaBaru, $nipBaru, $guruId);
            $stmt->execute();
            $stmt->close();
            $_SESSION['guru_nama'] = $namaBaru;
            $_SESSION['guru_nip']  = $nipBaru;
            $profil['nama'] = $namaBaru;
            $profil['nip']  = $nipBaru;
            $pesan = 'Nama dan NIP guru tersimpan. Kop tanda tangan laporan memakai data ini.';
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'ganti_password') {
        padi_wajib_csrf();
        $lama = (string)($_POST['password_lama'] ?? '');
        $baru = (string)($_POST['password_baru'] ?? '');
        $ulang = (string)($_POST['password_ulang'] ?? '');

        $stmt = $conn->prepare("SELECT password FROM guru WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $guruId);
        $stmt->execute();
        $res = $stmt->get_result();
        $hash = ($res && ($row = $res->fetch_assoc())) ? (string)$row['password'] : '';
        $stmt->close();

        if ($hash === '' || !password_verify($lama, $hash)) {
            $galat = 'Password lama tidak sesuai.';
        } elseif (mb_strlen($baru) < 8) {
            $galat = 'Password baru minimal 8 karakter.';
        } elseif ($baru !== $ulang) {
            $galat = 'Konfirmasi password tidak sama.';
        } elseif ($baru === $lama) {
            $galat = 'Password baru harus berbeda dari password lama.';
        } else {
            $hashBaru = password_hash($baru, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE guru SET password = ? WHERE id = ?");
            $stmt->bind_param('si', $hashBaru, $guruId);
            $stmt->execute();
            $stmt->close();
            $pesan = 'Password berhasil diperbarui. Gunakan password baru pada login berikutnya.';
        }
    }

    $sidProfil = padi_sekolah_id();
    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM sesi WHERE sekolah_id = ?");
    $stmt->bind_param('i', $sidProfil);
    $stmt->execute();
    if ($r = $stmt->get_result()->fetch_assoc()) $stat['sesi'] = (int)$r['c'];
    $stmt->close();

    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM sesi WHERE sekolah_id = ? AND status = 'aktif'");
    $stmt->bind_param('i', $sidProfil);
    $stmt->execute();
    if ($r = $stmt->get_result()->fetch_assoc()) $stat['sesi_aktif'] = (int)$r['c'];
    $stmt->close();

    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM siswa p JOIN sesi s ON s.id = p.sesi_id WHERE s.sekolah_id = ?");
    $stmt->bind_param('i', $sidProfil);
    $stmt->execute();
    if ($r = $stmt->get_result()->fetch_assoc()) $stat['siswa'] = (int)$r['c'];
    $stmt->close();

    foreach (['penilaian_kognitif' => 'siswa_id', 'penilaian_psikomotor' => 'siswa_id', 'penilaian_afektif' => 'siswa_id', 'penilaian_rekan' => 'penilai_id'] as $tabel => $kolom) {
        $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM $tabel t
                                JOIN siswa p ON p.id = t.$kolom
                                JOIN sesi s ON s.id = p.sesi_id
                                WHERE s.sekolah_id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $sidProfil);
            $stmt->execute();
            if ($r = $stmt->get_result()->fetch_assoc()) $stat['penilaian'] += (int)$r['c'];
            $stmt->close();
        }
    }
}

if (file_exists(__DIR__ . '/ui-cetak.php')) require_once __DIR__ . '/ui-cetak.php';
$identitas = [
    'Sekolah' => padi_cetak_env('SEKOLAH_NAMA', '(belum diisi di .env)'),
    'Alamat' => padi_cetak_env('SEKOLAH_ALAMAT', '(belum diisi di .env)'),
    'NPSN' => padi_cetak_env('SEKOLAH_NPSN', '-'),
    'Tahun Pelajaran' => padi_cetak_env('TAHUN_PELAJARAN', '-'),
    'NIP Guru (tersimpan)' => ($profil['nip'] !== '' ? $profil['nip'] : padi_cetak_env('GURU_NIP', '-')),
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Profil Guru – PADI-PJOK</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --blue: #1A56DB; --blue-light: #EFF4FF; --text: #111827; --text-3: #6B7280; --border: #E5E7EB;
      --bg: #F3F6FB; --white: #FFFFFF; --radius: 16px; --radius-sm: 10px;
      --green: #16A34A; --green-light: #DCFCE7; --red: #DC2626;
    }
    body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; padding-bottom: 24px; }
    .topbar { display: flex; align-items: center; gap: 10px; padding: 14px 18px; background: var(--white); position: sticky; top: 0; z-index: 50; }
    .back-btn { width: 36px; height: 36px; border-radius: 10px; background: var(--blue-light); border: none; display: flex; align-items: center; justify-content: center; cursor: pointer; flex-shrink: 0; }
    .back-btn svg { width: 20px; height: 20px; color: var(--blue); }
    .topbar-title { font-size: 15px; font-weight: 700; flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .content { max-width: 820px; margin: 0 auto; padding: 16px; display: flex; flex-direction: column; gap: 14px; }
    .card { background: var(--white); border-radius: var(--radius); padding: 18px; }
    .card h1 { font-size: 17px; margin-bottom: 6px; }
    .card h2 { font-size: 14px; margin-bottom: 10px; }
    .lead { font-size: 12.5px; color: var(--text-3); line-height: 1.55; }
    .identitas { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin-top: 12px; }
    .identitas div { border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 10px; }
    .identitas span { display: block; font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .03em; color: var(--text-3); }
    .identitas strong { font-size: 14px; }
    .ringkas { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; margin-top: 12px; }
    @media (min-width: 560px) { .ringkas { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
    .ringkas-item { border-radius: var(--radius-sm); padding: 10px; text-align: center; background: var(--blue-light); }
    .ringkas-item.green { background: var(--green-light); }
    .ringkas-num { display: block; font-size: 19px; font-weight: 800; }
    .ringkas-label { font-size: 10.5px; font-weight: 600; color: var(--text-3); }
    .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 10px; margin-top: 6px; }
    .form-group label { display: block; font-size: 11px; font-weight: 700; color: var(--text-3); margin-bottom: 4px; }
    .form-input { width: 100%; font: inherit; font-size: 13px; padding: 9px 10px; border-radius: var(--radius-sm); background: #fff; }
    .form-hint { display: block; margin-top: 4px; font-size: 11px; line-height: 1.45; color: var(--text-3); }
    .flash { border-radius: var(--radius-sm); padding: 10px 12px; font-size: 12.5px; font-weight: 600; background: var(--green-light); color: #166534; margin-bottom: 10px; }
    .flash.galat { background: #FEE2E2; color: #991B1B; }
    .btn { display: inline-flex; align-items: center; justify-content: center; height: 38px; padding: 0 14px; border-radius: var(--radius-sm); font: inherit; font-size: 12.5px; font-weight: 700; cursor: pointer; background: var(--blue); color: #fff; text-decoration: none; border: 0; }
    .btn-ringan { background: #fff; color: var(--blue); border: 1px solid var(--border); }
    .btn-keluar { background: #fff; color: #B91C1C; border: 1px solid #FCA5A5; }
    .aksi { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
    .daftar { list-style: none; display: flex; flex-direction: column; gap: 6px; }
    .daftar li { font-size: 12.5px; color: var(--text-3); line-height: 1.5; padding-left: 16px; position: relative; }
    .daftar li::before { content: "•"; position: absolute; left: 2px; color: var(--blue); font-weight: 800; }
    .nav-item { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px; color: #9CA3AF; text-decoration: none; height: 100%; }
    .nav-item svg { width: 22px; height: 22px; }
    .nav-item span { font-size: 10.5px; font-weight: 600; }
    .nav-item.active { color: var(--blue); }
  </style>
<?php require_once __DIR__ . '/ui-head.php'; padi_ui_head(); ?>
</head>
<body>
<?php require_once __DIR__ . '/ui-cetak.php'; padi_cetak_siap('PROFIL GURU PJOK', '', ['orientasi' => 'portrait']); ?>

  <header class="topbar">
    <button class="back-btn" type="button" aria-label="Kembali" onclick="window.location.href='dashboard-guru.php'">
      <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </button>
    <div class="topbar-title">Profil</div>
  </header>

  <main class="content">
    <?php if ($pesan !== ''): ?><div class="flash"><?= htmlspecialchars($pesan, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <?php if ($galat !== ''): ?><div class="flash galat"><?= htmlspecialchars($galat, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <section class="card">
      <h1>Profil Pengguna</h1>
      <p class="lead">Akun guru yang sedang aktif, ringkasan pemakaian platform, dan pengaturan password.</p>
      <div class="identitas">
        <div><span>Nama Guru</span><strong><?= htmlspecialchars($profil['nama'] !== '' ? $profil['nama'] : '-', ENT_QUOTES, 'UTF-8') ?></strong></div>
        <div><span>NIP</span><strong><?= htmlspecialchars($profil['nip'] !== '' ? $profil['nip'] : '-', ENT_QUOTES, 'UTF-8') ?></strong></div>
        <div><span>Username</span><strong><?= htmlspecialchars($profil['username'] !== '' ? $profil['username'] : '-', ENT_QUOTES, 'UTF-8') ?></strong></div>
        <div><span>Sekolah</span><strong><?= htmlspecialchars((string)padi_sekolah('nama'), ENT_QUOTES, 'UTF-8') ?></strong></div>
        <div><span>Peran</span><strong><?= !empty($_SESSION['guru_is_super']) ? 'Super-admin' : 'Guru' ?></strong></div>
        <div><span>Akun dibuat</span><strong><?= $profil['created_at'] ? htmlspecialchars(date('d/m/Y', strtotime((string)$profil['created_at'])), ENT_QUOTES, 'UTF-8') : '-' ?></strong></div>
        <div><span>Mata Pelajaran</span><strong>PJOK</strong></div>
      </div>
    </section>

    <section class="card">
      <h2>Nama &amp; NIP untuk Tanda Tangan Laporan</h2>
      <p class="lead">Data ini dipakai otomatis pada blok tanda tangan di semua cetak/PDF (rekap nilai, laporan, data siswa, jadwal, dan lainnya).</p>
      <form method="post" action="profil-guru.php">
        <?= padi_csrf_field() ?>
        <input type="hidden" name="aksi" value="simpan_identitas"/>
        <div class="form-grid">
          <div class="form-group">
            <label for="f-nama-guru">Nama guru (beserta gelar)</label>
            <input class="form-input" type="text" id="f-nama-guru" name="nama_guru" maxlength="100" minlength="3"
                   value="<?= htmlspecialchars($profil['nama'], ENT_QUOTES, 'UTF-8') ?>"
                   placeholder="Contoh: Ahmad Fajar, S.Pd." required/>
          </div>
          <div class="form-group">
            <label for="f-nip-guru">NIP (opsional)</label>
            <input class="form-input" type="text" id="f-nip-guru" name="nip_guru" maxlength="30" inputmode="numeric"
                   value="<?= htmlspecialchars($profil['nip'], ENT_QUOTES, 'UTF-8') ?>"
                   placeholder="Contoh: 19850412 201001 1 003"/>
            <span class="form-hint">Kosongkan bila sekolah belum menerbitkan NIP; cetakan akan memakai garis titik-titik.</span>
          </div>
        </div>
        <div class="aksi">
          <button class="btn" type="submit">Simpan Nama &amp; NIP</button>
        </div>
      </form>
    </section>

    <section class="card">
      <h2>Ringkasan Pemakaian</h2>
      <div class="ringkas">
        <div class="ringkas-item"><span class="ringkas-num"><?= (int)$stat['sesi'] ?></span><span class="ringkas-label">Total sesi</span></div>
        <div class="ringkas-item green"><span class="ringkas-num"><?= (int)$stat['sesi_aktif'] ?></span><span class="ringkas-label">Sesi aktif</span></div>
        <div class="ringkas-item"><span class="ringkas-num"><?= (int)$stat['siswa'] ?></span><span class="ringkas-label">Siswa tergabung</span></div>
        <div class="ringkas-item"><span class="ringkas-num"><?= (int)$stat['penilaian'] ?></span><span class="ringkas-label">Data penilaian</span></div>
      </div>
    </section>

    <section class="card">
      <h2>Identitas Sekolah (untuk kop cetak)</h2>
      <div class="identitas">
        <?php foreach ($identitas as $label => $nilai): ?>
          <div><span><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span><strong><?= htmlspecialchars((string)$nilai, ENT_QUOTES, 'UTF-8') ?></strong></div>
        <?php endforeach; ?>
      </div>
      <p class="lead" style="margin-top:10px;">
        Identitas ini tersimpan di data sekolah aktif (multi-tenant).
        <?php if (!empty($_SESSION['guru_is_super'])): ?>
          Ubah lewat menu <a href="sekolah-admin.php">Kelola Sekolah</a>.
        <?php else: ?>
          Hubungi super-admin sekolah untuk mengubahnya.
        <?php endif; ?>
      </p>
    </section>

    <section class="card">
      <h2>Ganti Password</h2>
      <form method="post" action="profil-guru.php">
        <?= padi_csrf_field() ?>
        <input type="hidden" name="aksi" value="ganti_password"/>
        <div class="form-grid">
          <div class="form-group">
            <label for="f-lama">Password lama</label>
            <input class="form-input" type="password" id="f-lama" name="password_lama" autocomplete="current-password" required/>
          </div>
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
      <ul class="daftar" style="margin-top:12px;">
        <li>Minimal 8 karakter; disarankan kombinasi huruf besar-kecil, angka, dan simbol.</li>
        <li>Setelah ganti password, sesi ini tetap aktif; login berikutnya memakai password baru.</li>
      </ul>
    </section>

    <section class="card">
      <h2>Aksi</h2>
      <div class="aksi">
        <a class="btn btn-ringan" href="dashboard-guru.php">Dashboard</a>
        <a class="btn btn-ringan" href="rubrik-platform.php">Rubrik Penilaian</a>
        <a class="btn btn-ringan" href="jadwal-guru.php">Jadwal</a>
        <?php if (!empty($_SESSION['guru_is_super'])): ?>
          <a class="btn btn-ringan" href="sekolah-admin.php">Kelola Sekolah</a>
        <?php endif; ?>
        <a class="btn btn-keluar" href="logout.php" onclick="return confirm('Keluar dari akun guru ini?');">Keluar</a>
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
    <a href="nilai-guru.php" class="nav-item" id="nav-nilai" aria-label="Nilai">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
      <span>Nilai</span>
    </a>
    <a href="profil-guru.php" class="nav-item active" id="nav-profil" aria-label="Profil" aria-current="page">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
      <span>Profil</span>
    </a>
  </nav>
<?php padi_cetak_ttd(); ?>
</body>
</html>
