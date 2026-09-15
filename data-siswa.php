<?php
require_once 'auth.php';
wajibLoginGuru();
require 'koneksi.php';

// --- HANDLE POST REQUESTS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    // 1. Tambah Siswa
    if ($action === 'add_siswa') {
        $nama = $_POST['nama'] ?? '';
        $nis = $_POST['nis'] ?? '';
        $kelas = $_POST['kelas'] ?? '';
        
        if ($nama && $kelas) {
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
        $nama_kelas = strtoupper(trim($_POST['nama_kelas'] ?? ''));
        if ($nama_kelas) {
            $stmt = $conn->prepare("INSERT IGNORE INTO master_kelas (nama_kelas) VALUES (?)");
            $stmt->bind_param("s", $nama_kelas);
            $stmt->execute();
            $stmt->close();
        }
        header("Location: data-siswa.php?kelas=" . urlencode($nama_kelas));
        exit;
    }
}

// --- HANDLE GET (DELETE) ---
if (isset($_GET['action'])) {
    // 1. Delete Siswa
    if ($_GET['action'] === 'delete_siswa' && isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        $kelas = $_GET['kelas'] ?? '';
        $conn->query("DELETE FROM master_siswa WHERE id = $id");
        header("Location: data-siswa.php?kelas=" . urlencode($kelas));
        exit;
    }
    
    // 2. Delete Kelas
    if ($_GET['action'] === 'delete_kelas' && isset($_GET['kelas'])) {
        $kelas = $_GET['kelas'];
        $stmt = $conn->prepare("DELETE FROM master_kelas WHERE nama_kelas = ?");
        $stmt->bind_param("s", $kelas);
        $stmt->execute();
        $stmt->close();
        
        // Delete all students in that class
        $stmt = $conn->prepare("DELETE FROM master_siswa WHERE kelas = ?");
        $stmt->bind_param("s", $kelas);
        $stmt->execute();
        $stmt->close();
        
        header("Location: data-siswa.php");
        exit;
    }
}

// --- FETCH DATA ---
$kelas_list = [];
$res = $conn->query("SELECT nama_kelas FROM master_kelas ORDER BY nama_kelas ASC");
if ($res && $res->num_rows > 0) {
    while($row = $res->fetch_assoc()) {
        $kelas_list[] = $row['nama_kelas'];
    }
}

// If master_kelas is empty, fetch distinct from master_siswa just in case
if (empty($kelas_list)) {
    $res = $conn->query("SELECT DISTINCT kelas FROM master_siswa ORDER BY kelas ASC");
    if ($res) {
        while($row = $res->fetch_assoc()) {
            $kelas_list[] = $row['kelas'];
            // Sync to master_kelas
            $conn->query("INSERT IGNORE INTO master_kelas (nama_kelas) VALUES ('".$conn->real_escape_string($row['kelas'])."')");
        }
    }
}

$active_kelas = $_GET['kelas'] ?? ($kelas_list[0] ?? '');

// Fetch Students for active class
$siswa_list = [];
if ($active_kelas) {
    $stmt = $conn->prepare("SELECT * FROM master_siswa WHERE kelas = ? ORDER BY nama ASC");
    $stmt->bind_param("s", $active_kelas);
    $stmt->execute();
    $res = $stmt->get_result();
    while($row = $res->fetch_assoc()) {
        $siswa_list[] = $row;
    }
    $stmt->close();
}
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
      --blue: #1A56DB; --blue-dark: #1240A8; --blue-light: #EFF4FF; --blue-mid: #DBEAFE;
      --green: #16A34A; --red: #DC2626; --red-light: #FEF2F2;
      --text: #111827; --text-2: #374151; --text-3: #6B7280; --border: #E5E7EB;
      --bg: #F3F6FB; --white: #FFFFFF; --radius: 16px; --radius-sm: 10px;
      --shadow: 0 2px 8px rgba(0,0,0,.07); --shadow-md: 0 4px 18px rgba(0,0,0,.10);
    }
    body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; max-width: 480px; margin: 0 auto; padding-bottom: 40px; }
    
    .topbar { display: flex; align-items: center; justify-content: space-between; padding: 14px 20px; background: var(--white); border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 50; }
    .back-btn { width: 36px; height: 36px; border-radius: 10px; background: var(--blue-light); border: none; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: background .2s; }
    .back-btn:hover { background: var(--blue-mid); }
    .back-btn svg { width: 20px; height: 20px; color: var(--blue); }
    .topbar-title { font-size: 15px; font-weight: 700; }
    .topbar-spacer { width: 36px; }
    
    .content { padding: 20px 16px; display: flex; flex-direction: column; gap: 16px; }
    
    .header-section { margin-bottom: 5px; }
    .header-section h2 { font-size: 20px; color: var(--blue); font-weight: 800; margin-bottom: 6px; }
    .header-section p { font-size: 13px; color: var(--text-3); line-height: 1.5; }

    /* Action Buttons (Export, Import, Print) */
    .top-actions { display: flex; gap: 8px; margin-bottom: 8px; }
    .btn-outline { flex: 1; display: flex; align-items: center; justify-content: center; gap: 6px; padding: 10px 0; border: 1.5px solid var(--border); border-radius: var(--radius-sm); font-size: 12px; font-weight: 600; color: var(--text-2); background: var(--white); cursor: pointer; text-decoration: none; transition: all .2s; }
    .btn-outline:hover { background: var(--blue-light); border-color: var(--blue-mid); color: var(--blue); }
    .btn-outline svg { width: 16px; height: 16px; }

    /* Class Filter */
    .filter-scroll { display: flex; overflow-x: auto; gap: 10px; padding-bottom: 10px; scrollbar-width: none; }
    .filter-scroll::-webkit-scrollbar { display: none; }
    .filter-btn { padding: 8px 16px; border-radius: 20px; background: var(--white); border: 1.5px solid var(--border); font-size: 13px; font-weight: 600; color: var(--text-2); cursor: pointer; white-space: nowrap; transition: all .2s; display: flex; align-items: center; gap: 6px; }
    .filter-btn.active { background: var(--blue); color: var(--white); border-color: var(--blue); box-shadow: 0 4px 12px rgba(26,86,219,.25); }
    .btn-add-class { background: var(--blue-light); color: var(--blue); border-style: dashed; border-color: var(--blue); }

    /* Form Add Siswa */
    .form-card { background: var(--white); border-radius: var(--radius); padding: 16px; box-shadow: var(--shadow); border: 1.5px solid var(--blue-light); }
    .form-title { font-size: 14px; font-weight: 700; margin-bottom: 12px; display: flex; align-items: center; gap: 6px; color: var(--text); }
    .form-title svg { width: 18px; height: 18px; color: var(--blue); }
    
    .form-group { margin-bottom: 12px; }
    .form-group label { display: block; font-size: 12px; font-weight: 600; color: var(--text-2); margin-bottom: 6px; }
    .form-input { width: 100%; padding: 10px 12px; border: 1.5px solid var(--border); border-radius: var(--radius-sm); font-family: inherit; font-size: 13px; outline: none; }
    .form-input:focus { border-color: var(--blue); }
    
    .btn-submit { background: var(--blue); color: var(--white); border: none; padding: 10px 16px; border-radius: var(--radius-sm); font-weight: 600; cursor: pointer; width: 100%; font-size: 13px; margin-top: 4px; display: flex; align-items: center; justify-content: center; gap: 6px; }
    
    /* Class Header (with Delete button) */
    .class-header { display: flex; justify-content: space-between; align-items: center; margin-top: 10px; margin-bottom: 4px; }
    .class-title { font-size: 15px; font-weight: 700; color: var(--text); }
    .btn-delete-class { font-size: 11px; font-weight: 600; color: var(--red); background: var(--red-light); padding: 6px 10px; border-radius: 6px; text-decoration: none; display: flex; align-items: center; gap: 4px; border: 1px solid #FECACA; }
    
    /* Student List */
    .student-list { display: flex; flex-direction: column; gap: 10px; }
    .student-card { background: var(--white); border-radius: var(--radius-sm); padding: 14px 16px; display: flex; align-items: center; justify-content: space-between; box-shadow: var(--shadow); border: 1px solid var(--border); }
    .student-info { display: flex; align-items: center; gap: 12px; }
    .avatar { width: 36px; height: 36px; background: var(--blue-light); color: var(--blue); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px; }
    .student-name { font-size: 14px; font-weight: 600; color: var(--text); margin-bottom: 2px; }
    .student-nis { font-size: 11px; color: var(--text-3); }
    
    .btn-delete { background: var(--red-light); color: var(--red); border: none; width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; cursor: pointer; text-decoration: none; }
    .btn-delete:hover { background: #FEE2E2; }
    .btn-delete svg { width: 16px; height: 16px; }
    
    .empty-state { text-align: center; padding: 30px 20px; background: var(--white); border-radius: var(--radius); border: 1px dashed var(--border); }
    .empty-state p { font-size: 13px; color: var(--text-3); margin-top: 10px; }

    /* Modal Overlay */
    .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 100; display: none; align-items: center; justify-content: center; padding: 20px; }
    .modal { background: var(--white); width: 100%; max-width: 360px; border-radius: var(--radius); padding: 20px; box-shadow: var(--shadow-md); position: relative; }
    .modal-close { position: absolute; top: 16px; right: 16px; background: none; border: none; font-size: 20px; color: var(--text-3); cursor: pointer; }
    .modal h3 { font-size: 16px; font-weight: 700; margin-bottom: 8px; }
    .modal p { font-size: 12px; color: var(--text-3); margin-bottom: 16px; }
  </style>
</head>
<body>
  <header class="topbar">
    <button class="back-btn" onclick="window.location.href='dashboard-guru.php'">
      <svg viewBox="0 0 24 24" fill="none"><path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </button>
    <div class="topbar-title">Data Siswa</div>
    <div class="topbar-spacer"></div>
  </header>
  
  <main class="content">
    <div class="header-section">
      <h2>Daftar Siswa</h2>
      <p>Kelola data nama siswa untuk setiap kelas agar lebih terstruktur.</p>
    </div>

    <!-- Actions Row -->
    <div class="top-actions">
      <a href="export-siswa.php?action=template" class="btn-outline">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg> Template
      </a>
      <button class="btn-outline" onclick="document.getElementById('modal-import').style.display='flex'">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg> Import
      </button>
      <a href="export-siswa.php?action=export&kelas=<?= urlencode($active_kelas) ?>" class="btn-outline">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/></svg> Export
      </a>
      <a href="cetak-siswa.php?kelas=<?= urlencode($active_kelas) ?>" target="_blank" class="btn-outline">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg> Cetak
      </a>
    </div>

    <!-- Class Filter -->
    <div class="filter-scroll">
      <?php foreach($kelas_list as $kls): ?>
        <button class="filter-btn <?= $kls === $active_kelas ? 'active' : '' ?>" onclick="window.location.href='data-siswa.php?kelas=<?= urlencode($kls) ?>'">
          <?= htmlspecialchars($kls) ?>
        </button>
      <?php endforeach; ?>
      <!-- Tombol Tambah Kelas -->
      <button class="filter-btn btn-add-class" onclick="document.getElementById('modal-kelas').style.display='flex'">
        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Kelas
      </button>
    </div>

    <?php if ($active_kelas): ?>
    <div class="class-header">
      <span class="class-title">Siswa Kelas <?= htmlspecialchars($active_kelas) ?></span>
      <a href="data-siswa.php?action=delete_kelas&kelas=<?= urlencode($active_kelas) ?>" class="btn-delete-class" onclick="return confirm('Hapus kelas <?= htmlspecialchars($active_kelas) ?> beserta seluruh siswanya?');">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
        Hapus Kelas
      </a>
    </div>

    <!-- Add Form -->
    <div class="form-card">
      <div class="form-title">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
        Tambah Siswa Satuan
      </div>
      <form method="POST" action="data-siswa.php">
        <input type="hidden" name="action" value="add_siswa"/>
        <input type="hidden" name="kelas" value="<?= htmlspecialchars($active_kelas) ?>"/>
        
        <div class="form-group">
          <label>Nama Lengkap</label>
          <input type="text" name="nama" class="form-input" placeholder="Contoh: Ahmad Fajar" required/>
        </div>
        <div class="form-group">
          <label>NIS (Opsional)</label>
          <input type="text" name="nis" class="form-input" placeholder="Contoh: 12345"/>
        </div>
        <button type="submit" class="btn-submit">Simpan Siswa</button>
      </form>
    </div>

    <!-- List -->
    <div class="student-list">
      <?php if(count($siswa_list) > 0): ?>
        <?php foreach($siswa_list as $siswa): ?>
          <div class="student-card">
            <div class="student-info">
              <div class="avatar"><?= strtoupper(substr($siswa['nama'], 0, 1)) ?></div>
              <div>
                <div class="student-name"><?= htmlspecialchars($siswa['nama']) ?></div>
                <div class="student-nis">NIS: <?= htmlspecialchars($siswa['nis'] ?: '-') ?></div>
              </div>
            </div>
            <a href="data-siswa.php?action=delete_siswa&id=<?= $siswa['id'] ?>&kelas=<?= urlencode($active_kelas) ?>" class="btn-delete" onclick="return confirm('Hapus <?= htmlspecialchars($siswa['nama']) ?> dari kelas ini?');">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2M10 11v6M14 11v6"/></svg>
            </a>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="empty-state">
          <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="var(--border)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin:0 auto; display:block;">
            <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
            <circle cx="9" cy="7" r="4"/>
          </svg>
          <p>Belum ada data siswa di kelas <?= htmlspecialchars($active_kelas) ?>.</p>
        </div>
      <?php endif; ?>
    </div>
    <?php else: ?>
      <div class="empty-state" style="margin-top:20px;">
        <p>Belum ada kelas. Silakan tambah kelas baru.</p>
      </div>
    <?php endif; ?>
  </main>

  <!-- Modal Tambah Kelas -->
  <div class="modal-overlay" id="modal-kelas">
    <div class="modal">
      <button class="modal-close" onclick="document.getElementById('modal-kelas').style.display='none'">&times;</button>
      <h3>Tambah Kelas Baru</h3>
      <p>Masukkan nama kelas (misal: X-1, XI-IPA-2)</p>
      <form method="POST" action="data-siswa.php">
        <input type="hidden" name="action" value="add_kelas"/>
        <div class="form-group">
          <input type="text" name="nama_kelas" class="form-input" placeholder="Nama Kelas" required/>
        </div>
        <button type="submit" class="btn-submit">Simpan Kelas</button>
      </form>
    </div>
  </div>

  <!-- Modal Import CSV -->
  <div class="modal-overlay" id="modal-import">
    <div class="modal">
      <button class="modal-close" onclick="document.getElementById('modal-import').style.display='none'">&times;</button>
      <h3>Import Excel (CSV)</h3>
      <p>Unggah file berformat .csv sesuai template untuk mengimpor banyak siswa sekaligus.</p>
      <form method="POST" action="import-siswa.php" enctype="multipart/form-data">
        <input type="hidden" name="kelas" value="<?= htmlspecialchars($active_kelas) ?>"/>
        <div class="form-group">
          <input type="file" name="file_csv" accept=".csv" class="form-input" style="padding: 6px;" required/>
        </div>
        <button type="submit" class="btn-submit">Mulai Import</button>
      </form>
    </div>
  </div>
</body>
</html>
