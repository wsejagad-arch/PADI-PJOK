<?php
require_once 'auth.php';
wajibLoginGuru();
require 'koneksi.php';

// --- HANDLE POST REQUESTS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!padi_csrf_valid()) {
        http_response_code(403);
        exit('Sesi formulir kedaluwarsa. Muat ulang halaman lalu coba lagi.');
    }
    $action = $_POST['action'];

    // 1. Tambah Siswa
    if ($action === 'add_siswa') {
        $nama = trim((string)($_POST['nama'] ?? ''));
        $nis = trim((string)($_POST['nis'] ?? ''));
        $kelas = strtoupper(trim((string)($_POST['kelas'] ?? '')));

        if ($nama !== '' && $kelas !== '') {
            $stmt = $conn->prepare("INSERT INTO master_siswa (nama, nis, kelas) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $nama, $nis, $kelas);
            $stmt->execute();
            $stmt->close();
        }
        header("Location: data-siswa.php?kelas=" . urlencode($kelas));
        exit;
    }

    // 2. Tambah Kelas
    if ($action === 'add_kelas') {
        $nama_kelas = strtoupper(trim((string)($_POST['nama_kelas'] ?? '')));
        if ($nama_kelas !== '') {
            $stmt = $conn->prepare("INSERT IGNORE INTO master_kelas (nama_kelas) VALUES (?)");
            $stmt->bind_param("s", $nama_kelas);
            $stmt->execute();
            $stmt->close();
        }
        header("Location: data-siswa.php?kelas=" . urlencode($nama_kelas));
        exit;
    }

    // 3. Hapus Siswa
    if ($action === 'delete_siswa' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $kelas = (string)($_POST['kelas'] ?? '');
        $stmt = $conn->prepare("DELETE FROM master_siswa WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        header("Location: data-siswa.php?kelas=" . urlencode($kelas));
        exit;
    }

    // 4. Hapus Kelas (beserta siswanya)
    if ($action === 'delete_kelas' && isset($_POST['kelas'])) {
        $kelas = (string)$_POST['kelas'];
        $stmt = $conn->prepare("DELETE FROM master_kelas WHERE nama_kelas = ?");
        $stmt->bind_param("s", $kelas);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("DELETE FROM master_siswa WHERE kelas = ?");
        $stmt->bind_param("s", $kelas);
        $stmt->execute();
        $stmt->close();

        header("Location: data-siswa.php");
        exit;
    }
}

// --- FILTER ---
$filterKelas = strtoupper(trim((string)($_GET['kelas'] ?? '')));
$cari = trim((string)($_GET['q'] ?? ''));

// --- DAFTAR KELAS ---
$kelas_list = [];
$res = $conn->query("SELECT nama_kelas FROM master_kelas ORDER BY nama_kelas ASC");
if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) $kelas_list[] = (string)$row['nama_kelas'];
}
if (!$kelas_list) {
    $res = $conn->query("SELECT DISTINCT kelas FROM master_siswa WHERE kelas <> '' ORDER BY kelas ASC");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $kelas_list[] = (string)$row['kelas'];
            $conn->query("INSERT IGNORE INTO master_kelas (nama_kelas) VALUES ('" . $conn->real_escape_string($row['kelas']) . "')");
        }
    }
}
$kelas_list = array_values(array_unique(array_filter($kelas_list)));

// --- AMBIL SISWA (kelas + nama) ---
$perKelas = [];
$totalSiswa = 0;
$sql = "SELECT id, nama, nis, kelas FROM master_siswa";
$kondisi = [];
$tipe = '';
$nilai = [];
if ($filterKelas !== '') {
    $kondisi[] = 'kelas = ?';
    $tipe .= 's';
    $nilai[] = $filterKelas;
}
if ($cari !== '') {
    $kondisi[] = 'nama LIKE ?';
    $tipe .= 's';
    $nilai[] = '%' . $cari . '%';
}
if ($kondisi) $sql .= ' WHERE ' . implode(' AND ', $kondisi);
$sql .= ' ORDER BY kelas ASC, nama ASC';

$stmt = $conn->prepare($sql);
if ($stmt) {
    if ($tipe !== '') $stmt->bind_param($tipe, ...$nilai);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($res && ($row = $res->fetch_assoc())) {
        $perKelas[(string)$row['kelas']][] = $row;
        $totalSiswa++;
    }
    $stmt->close();
}

// Kelas yang ditampilkan: kelas terfilter, atau semua kelas (termasuk yang kosong).
if ($filterKelas !== '') {
    $kelasTampil = [$filterKelas];
} elseif ($cari !== '') {
    $kelasTampil = array_keys($perKelas);
    sort($kelasTampil);
} else {
    $kelasTampil = $kelas_list;
}

$jumlahKelas = count($kelas_list);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Data Siswa – PADI-PJOK</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --blue: #1A56DB; --blue-light: #EFF4FF; --text: #111827; --text-3: #6B7280; --border: #E5E7EB;
      --bg: #F3F6FB; --white: #FFFFFF; --radius: 16px; --radius-sm: 10px; --red: #DC2626;
    }
    body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; padding-bottom: 24px; }
    .topbar { display: flex; align-items: center; justify-content: space-between; padding: 14px 18px; background: var(--white); position: sticky; top: 0; z-index: 50; }
    .back-btn { width: 36px; height: 36px; border-radius: 10px; background: var(--blue-light); border: none; display: flex; align-items: center; justify-content: center; cursor: pointer; flex-shrink: 0; }
    .back-btn svg { width: 20px; height: 20px; color: var(--blue); }
    .topbar-title { font-size: 15px; font-weight: 700; }
    .topbar-spacer { width: 36px; }
    .content { max-width: 1000px; margin: 0 auto; padding: 16px; display: flex; flex-direction: column; gap: 14px; }
    .card { background: var(--white); border-radius: var(--radius); padding: 18px; }
    .card h1 { font-size: 17px; margin-bottom: 6px; }
    .card h2 { font-size: 14px; margin-bottom: 10px; }
    .lead { font-size: 12.5px; color: var(--text-3); line-height: 1.5; }

    .top-actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
    .btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; height: 36px; padding: 0 12px; border-radius: var(--radius-sm); font: inherit; font-size: 12.5px; font-weight: 700; cursor: pointer; background: var(--blue); color: #fff; text-decoration: none; border: 0; }
    .btn-ringan { background: #fff; color: var(--blue); border: 1px solid var(--border); }
    .btn-hapus { background: #fff; color: var(--red); border: 1px solid #FCA5A5; height: 28px; padding: 0 10px; font-size: 11px; }

    .filter { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; align-items: end; margin-top: 14px; }
    .filter label { display: block; font-size: 11px; font-weight: 700; color: var(--text-3); margin-bottom: 4px; }
    .filter input, .filter select, .form-input { width: 100%; font: inherit; font-size: 13px; padding: 9px 10px; border-radius: var(--radius-sm); background: #fff; }
    .filter-aksi { display: flex; gap: 8px; }

    .kelas-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; margin-bottom: 10px; }
    .kelas-head h2 { margin: 0; font-size: 14.5px; }
    .kelas-head .pill { font-size: 10.5px; font-weight: 700; padding: 2px 8px; border-radius: 999px; background: var(--blue-light); color: var(--blue); }
    .kelas-aksi { display: flex; gap: 6px; flex-wrap: wrap; }

    .table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
    table { width: 100%; border-collapse: collapse; font-size: 12.5px; min-width: 420px; }
    th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid var(--border); vertical-align: middle; }
    th { font-size: 10.5px; text-transform: uppercase; letter-spacing: .04em; color: var(--text-3); background: #F8FAFC; white-space: nowrap; }
    tbody tr:last-child td { border-bottom: 0; }
    td.no, th.no { width: 44px; text-align: right; color: var(--text-3); }
    td.nis { width: 120px; white-space: nowrap; color: var(--text-3); }
    td.nama { font-weight: 600; }
    td.aksi { width: 64px; text-align: right; }

    .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; margin-top: 4px; }
    .form-group label { display: block; font-size: 11px; font-weight: 700; color: var(--text-3); margin-bottom: 4px; }
    .kosong { text-align: center; color: var(--text-3); font-size: 12.5px; padding: 18px 8px; }

    .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, .45); z-index: 90; align-items: center; justify-content: center; padding: 16px; }
    .modal { background: #fff; border-radius: var(--radius); padding: 20px; width: 100%; max-width: 380px; position: relative; }
    .modal h3 { font-size: 15px; margin-bottom: 6px; }
    .modal p { font-size: 12px; color: var(--text-3); margin-bottom: 12px; line-height: 1.5; }
    .modal-close { position: absolute; top: 8px; right: 12px; border: 0; background: transparent; font-size: 22px; line-height: 1; cursor: pointer; color: var(--text-3); }

    .nav-item { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px; color: #9CA3AF; text-decoration: none; height: 100%; }
    .nav-item svg { width: 22px; height: 22px; }
    .nav-item span { font-size: 10.5px; font-weight: 600; }
    .nav-item.active { color: var(--blue); }
  </style>
<?php require_once __DIR__ . '/ui-head.php'; padi_ui_head(); ?>
</head>
<body>
<?php require_once __DIR__ . '/ui-cetak.php'; padi_cetak_siap('DATA SISWA', 'Total ' . $totalSiswa . ' siswa • ' . ($filterKelas !== '' ? 'Kelas ' . $filterKelas : 'Semua kelas'), ['orientasi' => 'portrait']); ?>

  <header class="topbar">
    <button class="back-btn" type="button" aria-label="Kembali" onclick="window.location.href='dashboard-guru.php'">
      <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </button>
    <div class="topbar-title">Data Siswa</div>
    <div class="topbar-spacer"></div>
  </header>

  <main class="content">
    <section class="card">
      <h1>Data Siswa</h1>
      <p class="lead"><?= $totalSiswa ?> siswa ditampilkan dari <?= $jumlahKelas ?> kelas. Klik <strong>Ekspor</strong> atau <strong>Cetak</strong> pada tiap kelas.</p>
      <div class="top-actions">
        <a href="export-siswa.php?action=template" class="btn btn-ringan">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg> Template
        </a>
        <button type="button" class="btn btn-ringan" onclick="document.getElementById('modal-import').style.display='flex'">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg> Import
        </button>
        <button type="button" class="btn btn-ringan" onclick="document.getElementById('modal-kelas').style.display='flex'">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Kelas Baru
        </button>
      </div>

      <form method="get" action="data-siswa.php" class="filter">
        <div>
          <label for="f-kelas">Kelas</label>
          <select id="f-kelas" name="kelas">
            <option value="">Semua kelas</option>
            <?php foreach ($kelas_list as $k): ?>
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
          <a class="btn btn-ringan" href="data-siswa.php">Reset</a>
        </div>
      </form>
    </section>

    <?php if (!$kelasTampil): ?>
      <section class="card"><div class="kosong">Belum ada kelas. Tambahkan kelas baru terlebih dahulu.</div></section>
    <?php endif; ?>

    <?php foreach ($kelasTampil as $kls): $rows = $perKelas[$kls] ?? []; ?>
      <section class="card">
        <div class="kelas-head">
          <div>
            <h2>Kelas <?= htmlspecialchars($kls, ENT_QUOTES, 'UTF-8') ?></h2>
            <span class="pill"><?= count($rows) ?> siswa</span>
          </div>
          <div class="kelas-aksi">
            <a class="btn btn-ringan" href="export-siswa.php?action=export&kelas=<?= urlencode($kls) ?>">Ekspor</a>
            <a class="btn btn-ringan" href="cetak-siswa.php?kelas=<?= urlencode($kls) ?>" target="_blank" rel="noopener">Cetak</a>
            <form method="post" action="data-siswa.php" onsubmit="return confirm('Hapus kelas <?= htmlspecialchars($kls, ENT_QUOTES) ?> beserta seluruh siswanya?');" style="display:inline">
              <?= padi_csrf_field() ?>
              <input type="hidden" name="action" value="delete_kelas"/>
              <input type="hidden" name="kelas" value="<?= htmlspecialchars($kls, ENT_QUOTES) ?>"/>
              <button type="submit" class="btn btn-hapus">Hapus Kelas</button>
            </form>
          </div>
        </div>

        <?php if (!$rows): ?>
          <div class="kosong">Belum ada siswa<?= $cari !== '' ? ' yang cocok dengan “' . htmlspecialchars($cari, ENT_QUOTES, 'UTF-8') . '”' : '' ?> di kelas ini.</div>
        <?php else: ?>
          <div class="table-wrap">
            <table>
              <thead>
                <tr><th class="no">No</th><th>NIS</th><th>Nama Lengkap</th><th class="aksi">Aksi</th></tr>
              </thead>
              <tbody>
                <?php $no = 1; foreach ($rows as $siswa): ?>
                  <tr>
                    <td class="no"><?= $no++ ?></td>
                    <td class="nis"><?= htmlspecialchars($siswa['nis'] !== '' && $siswa['nis'] !== null ? $siswa['nis'] : '-', ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="nama"><?= htmlspecialchars($siswa['nama'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="aksi">
                      <form method="post" action="data-siswa.php" onsubmit="return confirm('Hapus <?= htmlspecialchars($siswa['nama'], ENT_QUOTES) ?> dari kelas ini?');" style="display:inline">
                        <?= padi_csrf_field() ?>
                        <input type="hidden" name="action" value="delete_siswa"/>
                        <input type="hidden" name="id" value="<?= (int)$siswa['id'] ?>"/>
                        <input type="hidden" name="kelas" value="<?= htmlspecialchars($kls, ENT_QUOTES) ?>"/>
                        <button type="submit" class="btn btn-hapus">Hapus</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </section>
    <?php endforeach; ?>

    <section class="card">
      <h2>Tambah Siswa</h2>
      <?php if (!$kelas_list): ?>
        <div class="kosong">Tambahkan kelas terlebih dahulu lewat tombol “Kelas Baru”.</div>
      <?php else: ?>
        <form method="post" action="data-siswa.php">
          <?= padi_csrf_field() ?>
          <input type="hidden" name="action" value="add_siswa"/>
          <div class="form-grid">
            <div class="form-group">
              <label for="f-add-kelas">Kelas</label>
              <select id="f-add-kelas" name="kelas" required>
                <?php foreach ($kelas_list as $k): ?>
                  <option value="<?= htmlspecialchars($k, ENT_QUOTES, 'UTF-8') ?>" <?= (strcasecmp($filterKelas, $k) === 0) ? 'selected' : '' ?>><?= htmlspecialchars($k, ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="f-add-nama">Nama Lengkap</label>
              <input class="form-input" type="text" id="f-add-nama" name="nama" placeholder="Contoh: Ahmad Fajar" required/>
            </div>
            <div class="form-group">
              <label for="f-add-nis">NIS (opsional)</label>
              <input class="form-input" type="text" id="f-add-nis" name="nis" placeholder="Contoh: 12345"/>
            </div>
          </div>
          <div class="top-actions">
            <button class="btn" type="submit">Simpan Siswa</button>
          </div>
        </form>
      <?php endif; ?>
    </section>
  </main>

  <!-- Modal Tambah Kelas -->
  <div class="modal-overlay" id="modal-kelas">
    <div class="modal">
      <button class="modal-close" type="button" onclick="document.getElementById('modal-kelas').style.display='none'" aria-label="Tutup">&times;</button>
      <h3>Tambah Kelas Baru</h3>
      <p>Masukkan nama kelas (misal: X-1, XI-IPA-2).</p>
      <form method="post" action="data-siswa.php">
        <?= padi_csrf_field() ?>
        <input type="hidden" name="action" value="add_kelas"/>
        <div class="form-group">
          <input class="form-input" type="text" name="nama_kelas" placeholder="Nama Kelas" required/>
        </div>
        <div class="top-actions">
          <button class="btn" type="submit">Simpan Kelas</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Modal Import CSV -->
  <div class="modal-overlay" id="modal-import">
    <div class="modal">
      <button class="modal-close" type="button" onclick="document.getElementById('modal-import').style.display='none'" aria-label="Tutup">&times;</button>
      <h3>Import Excel (CSV)</h3>
      <p>Unggah berkas .csv sesuai template untuk mengimpor banyak siswa sekaligus.</p>
      <form method="post" action="import-siswa.php" enctype="multipart/form-data">
        <input type="hidden" name="kelas" value="<?= htmlspecialchars($filterKelas, ENT_QUOTES) ?>"/>
        <div class="form-group">
          <input type="file" name="file_csv" accept=".csv" class="form-input" style="padding:6px" required/>
        </div>
        <div class="top-actions">
          <button class="btn" type="submit">Mulai Import</button>
        </div>
      </form>
    </div>
  </div>

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
    <a href="profil-guru.php" class="nav-item" id="nav-profil" aria-label="Profil">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
      <span>Profil</span>
    </a>
  </nav>
<?php padi_cetak_ttd(); ?>
</body>
</html>
