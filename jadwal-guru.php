<?php
require_once 'auth.php';
wajibLoginGuru();
require 'koneksi.php';
padi_pastikan_skema_auth($conn);
padi_pastikan_tabel_jadwal($conn);

$HARI = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];

/** Normalisasi nama hari (Senin/senin/1) menjadi angka 1–7. */
if (!function_exists('padi_hari_angka')) {
function padi_hari_angka($nilai)
{
    $peta = ['senin' => 1, 'selasa' => 2, 'rabu' => 3, 'kamis' => 4, 'jumat' => 5, "jum'at" => 5, 'sabtu' => 6, 'minggu' => 7, 'ahad' => 7];
    $teks = strtolower(trim((string)$nilai));
    if (isset($peta[$teks])) return $peta[$teks];
    $n = (int)$teks;
    return ($n >= 1 && $n <= 7) ? $n : 0;
}
}

/** Normalisasi jam "7:0", "07.00", "07:00:00" menjadi HH:MM. */
if (!function_exists('padi_jam_normal')) {
function padi_jam_normal($nilai)
{
    $teks = trim((string)$nilai);
    if ($teks === '') return '';
    $teks = str_replace(['.', ';'], ':', $teks);
    if (preg_match('/^(\d{1,2}):(\d{1,2})(?::\d{1,2})?$/', $teks, $m)) {
        $h = (int)$m[1];
        $i = (int)$m[2];
        if ($h >= 0 && $h <= 23 && $i >= 0 && $i <= 59) return sprintf('%02d:%02d', $h, $i);
    }
    return '';
}
}

/** Simpan satu baris jadwal. Mengembalikan pesan galat atau '' bila sukses. */
if (!function_exists('padi_simpan_jadwal')) {
function padi_simpan_jadwal($conn, $kelas, $hari, $mulai, $selesai, $materi, $catatan)
{
    $kelas = strtoupper(trim((string)$kelas));
    $materi = trim((string)$materi);
    $catatan = trim((string)$catatan);
    $hari = padi_hari_angka($hari);
    $mulai = padi_jam_normal($mulai);
    $selesai = padi_jam_normal($selesai);

    if ($kelas === '' || $hari === 0 || $mulai === '' || $selesai === '' || $materi === '') {
        return 'Data belum lengkap (kelas, hari, jam, materi wajib diisi).';
    }
    if ($selesai <= $mulai) {
        return 'Jam selesai harus setelah jam mulai.';
    }

    $sidJadwal = padi_sekolah_id();
    $stmt = $conn->prepare("INSERT INTO jadwal (sekolah_id, kelas, hari, jam_mulai, jam_selesai, materi, catatan)
                            VALUES (?, ?, ?, ?, ?, ?, ?)
                            ON DUPLICATE KEY UPDATE materi = VALUES(materi), jam_selesai = VALUES(jam_selesai), catatan = VALUES(catatan)");
    if (!$stmt) return 'Gagal menyiapkan query.';
    $stmt->bind_param('isissss', $sidJadwal, $kelas, $hari, $mulai, $selesai, $materi, $catatan);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok ? '' : 'Gagal menyimpan jadwal.';
}
}

$pesan = '';
$galat = '';

// Kirim template CSV
if (isset($_GET['template'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="template-jadwal-padi.csv"');
    echo "kelas,hari,jam_mulai,jam_selesai,materi,catatan\n";
    echo "X-1,Senin,07:00,08:30,Passing Bawah Bola Voli,Latihan di lapangan\n";
    echo "X-1,Rabu,07:00,08:30,Smash Bola Voli,\n";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    padi_wajib_csrf();
    $aksi = (string)($_POST['aksi'] ?? '');

    if ($aksi === 'tambah') {
        $galat = padi_simpan_jadwal(
            $conn,
            $_POST['kelas'] ?? '',
            $_POST['hari'] ?? '',
            $_POST['jam_mulai'] ?? '',
            $_POST['jam_selesai'] ?? '',
            $_POST['materi'] ?? '',
            $_POST['catatan'] ?? ''
        );
        if ($galat === '') $pesan = 'Jadwal tersimpan.';
    } elseif ($aksi === 'impor') {
        $teks = (string)($_POST['data'] ?? '');
        if (!empty($_FILES['berkas']['tmp_name']) && is_uploaded_file($_FILES['berkas']['tmp_name'])) {
            $isi = @file_get_contents($_FILES['berkas']['tmp_name']);
            if ($isi !== false) $teks = $isi;
        }
        $teks = str_replace(["\r\n", "\r"], "\n", trim($teks));
        if ($teks === '') {
            $galat = 'Tempel data CSV atau pilih berkas terlebih dahulu.';
        } else {
            $sukses = 0;
            $gagal = 0;
            $pesanGagal = [];
            foreach (explode("\n", $teks) as $nomor => $baris) {
                $baris = trim($baris);
                if ($baris === '') continue;
                $kolom = array_map('trim', str_getcsv($baris, ',', '"'));
                if ($nomor === 0 && strtolower((string)($kolom[0] ?? '')) === 'kelas') continue; // header
                $kolom = array_pad($kolom, 6, '');
                $err = padi_simpan_jadwal($conn, $kolom[0], $kolom[1], $kolom[2], $kolom[3], $kolom[4], $kolom[5]);
                if ($err === '') {
                    $sukses++;
                } else {
                    $gagal++;
                    if (count($pesanGagal) < 3) $pesanGagal[] = 'baris ' . ($nomor + 1) . ': ' . $err;
                }
            }
            $pesan = $sukses . ' jadwal diimpor' . ($gagal ? ', ' . $gagal . ' baris gagal (' . implode('; ', $pesanGagal) . ')' : '.');
            if ($sukses === 0 && $gagal > 0) {
                $galat = $pesan;
                $pesan = '';
            }
        }
    } elseif ($aksi === 'hapus') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $conn->prepare("DELETE FROM jadwal WHERE id = ? AND sekolah_id = ?");
            $stmt->bind_param('ii', $id, padi_sekolah_id());
            $stmt->execute();
            $stmt->close();
            $pesan = 'Jadwal dihapus.';
        }
    } elseif ($aksi === 'kosongkan') {
        $kelas = strtoupper(trim((string)($_POST['kelas'] ?? '')));
        if ($kelas !== '') {
            $stmt = $conn->prepare("DELETE FROM jadwal WHERE kelas = ? AND sekolah_id = ?");
            $stmt->bind_param('si', $kelas, padi_sekolah_id());
            $stmt->execute();
            $hapus = $stmt->affected_rows;
            $stmt->close();
            $pesan = 'Jadwal kelas ' . $kelas . ' dikosongkan (' . $hapus . ' baris).';
        }
    }
}

// Daftar kelas untuk filter & form
$sidJadwal = padi_sekolah_id();
$daftarKelas = [];
if ($conn) {
    $stmtKelas = $conn->prepare("SELECT nama_kelas FROM master_kelas WHERE sekolah_id = ? ORDER BY nama_kelas");
    if ($stmtKelas) {
        $stmtKelas->bind_param('i', $sidJadwal);
        $stmtKelas->execute();
        $q = $stmtKelas->get_result();
        if ($q) while ($r = $q->fetch_assoc()) $daftarKelas[] = (string)$r['nama_kelas'];
        $stmtKelas->close();
    }
}
require_once 'pesan-db.php';
if ($conn) {
    $stmtSesi = $conn->prepare("SELECT DISTINCT kelas FROM sesi WHERE sekolah_id = ? ORDER BY kelas");
    if ($stmtSesi) {
        $stmtSesi->bind_param('i', $sidJadwal);
        $stmtSesi->execute();
        $q = $stmtSesi->get_result();
        if ($q) while ($r = $q->fetch_assoc()) $daftarKelas[] = (string)$r['kelas'];
        $stmtSesi->close();
    }
}
$daftarKelas = array_values(array_unique(array_filter($daftarKelas)));
sort($daftarKelas);

$filterKelas = strtoupper(trim((string)($_GET['kelas'] ?? '')));
$baris = [];
if ($conn) {
    if ($filterKelas !== '') {
        $stmt = $conn->prepare("SELECT id, kelas, hari, jam_mulai, jam_selesai, materi, catatan FROM jadwal WHERE sekolah_id = ? AND kelas = ? ORDER BY hari, jam_mulai");
        $stmt->bind_param('is', $sidJadwal, $filterKelas);
        $stmt->execute();
        $res = $stmt->get_result();
        $stmt->close();
    } else {
        $stmt = $conn->prepare("SELECT id, kelas, hari, jam_mulai, jam_selesai, materi, catatan FROM jadwal WHERE sekolah_id = ? ORDER BY kelas, hari, jam_mulai");
        $stmt->bind_param('i', $sidJadwal);
        $stmt->execute();
        $res = $stmt->get_result();
        $stmt->close();
    }
    if ($res) while ($r = $res->fetch_assoc()) $baris[] = $r;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Jadwal Pelajaran – PADI-PJOK</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root { --blue: #1A56DB; --blue-light: #EFF4FF; --text: #111827; --text-3: #6B7280; --border: #E5E7EB; --bg: #F3F6FB; --white: #FFFFFF; --radius: 14px; --radius-sm: 10px; --green: #16A34A; --green-light: #DCFCE7; --red: #DC2626; }
    body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }
    .topbar { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 14px 18px; background: var(--white); position: sticky; top: 0; z-index: 50; }
    .back-btn { width: 36px; height: 36px; border-radius: 10px; background: var(--blue-light); border: none; display: flex; align-items: center; justify-content: center; cursor: pointer; flex-shrink: 0; }
    .back-btn svg { width: 20px; height: 20px; color: var(--blue); }
    .topbar-title { font-size: 15px; font-weight: 700; flex: 1; }
    .content { max-width: 960px; margin: 0 auto; padding: 16px; display: flex; flex-direction: column; gap: 14px; }
    .card { background: var(--white); border-radius: var(--radius); padding: 18px; }
    .card h1 { font-size: 16px; margin-bottom: 4px; }
    .card h2 { font-size: 14px; margin-bottom: 10px; }
    .lead { font-size: 12.5px; color: var(--text-3); line-height: 1.5; margin-bottom: 12px; }
    .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 10px; }
    label { display: block; font-size: 11.5px; font-weight: 700; color: var(--text-3); margin-bottom: 4px; }
    input[type="text"], input[type="time"], select, textarea {
      width: 100%; font: inherit; font-size: 13px; padding: 9px 10px; border-radius: var(--radius-sm);
      background: #fff; color: var(--text);
    }
    textarea { min-height: 110px; resize: vertical; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 12px; }
    .btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; height: 40px; padding: 0 16px; border-radius: var(--radius-sm); font: inherit; font-size: 13px; font-weight: 700; cursor: pointer; background: var(--blue); color: #fff; }
    .btn-ringan { background: #fff; color: var(--blue); border: 1px solid var(--border); }
    .btn-hapus { background: #fff; color: var(--red); border: 1px solid #FCA5A5; height: 32px; padding: 0 10px; font-size: 11.5px; }
    .baris-aksi { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
    .flash { border-radius: var(--radius-sm); padding: 10px 12px; font-size: 12.5px; font-weight: 600; background: var(--green-light); color: #166534; }
    .flash.galat { background: #FEE2E2; color: #991B1B; }
    .table-wrap { overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; font-size: 12.5px; min-width: 620px; }
    th, td { text-align: left; padding: 9px 10px; border-bottom: 1px solid var(--border); vertical-align: top; }
    th { font-size: 11px; text-transform: uppercase; letter-spacing: .04em; color: var(--text-3); background: #F8FAFC; }
    td.jam { white-space: nowrap; font-variant-numeric: tabular-nums; }
    .pill { display: inline-block; font-size: 10.5px; font-weight: 700; padding: 2px 8px; border-radius: 999px; background: var(--blue-light); color: var(--blue); }
    .kosong { text-align: center; color: var(--text-3); font-size: 12.5px; padding: 24px 8px; }
    .nav-item { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px; color: #9CA3AF; text-decoration: none; height: 100%; }
    .nav-item svg { width: 22px; height: 22px; }
    .nav-item span { font-size: 10.5px; font-weight: 600; }
    .nav-item.active { color: var(--blue); }
  </style>
<?php require_once __DIR__ . '/ui-head.php'; padi_ui_head(); ?>
</head>
<body>
<?php require_once __DIR__ . '/ui-cetak.php'; padi_cetak_siap('JADWAL PELAJARAN PJOK', 'Kelas: ' . ($filterKelas !== '' ? $filterKelas : 'Semua kelas') . ' · ' . count($baris) . ' jadwal', ['orientasi' => 'landscape']); ?>

  <header class="topbar">
    <button class="back-btn" type="button" aria-label="Kembali" onclick="window.location.href='dashboard-guru.php'">
      <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </button>
    <div class="topbar-title">Jadwal Pelajaran</div>
  </header>

  <main class="content">
    <?php if ($pesan !== ''): ?><div class="flash"><?= htmlspecialchars($pesan, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <?php if ($galat !== ''): ?><div class="flash galat"><?= htmlspecialchars($galat, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

    <section class="card">
      <h1>Tambah Jadwal</h1>
      <p class="lead">Jadwal per kelas ini otomatis tampil di dashboard siswa sesuai kelasnya.</p>
      <form method="post" action="jadwal-guru.php">
        <input type="hidden" name="aksi" value="tambah"/>
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(padi_csrf_token(), ENT_QUOTES, 'UTF-8') ?>"/>
        <div class="grid">
          <div>
            <label for="f-kelas">Kelas</label>
            <select id="f-kelas" name="kelas" required>
              <?php if (!$daftarKelas): ?><option value="">(belum ada kelas)</option><?php endif; ?>
              <?php foreach ($daftarKelas as $k): ?>
                <option value="<?= htmlspecialchars($k, ENT_QUOTES, 'UTF-8') ?>" <?= ($filterKelas !== '' && $filterKelas === $k) ? 'selected' : '' ?>><?= htmlspecialchars($k, ENT_QUOTES, 'UTF-8') ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label for="f-hari">Hari</label>
            <select id="f-hari" name="hari" required>
              <?php foreach ($HARI as $angka => $nama): ?>
                <option value="<?= $angka ?>" <?= ((int)date('N') === $angka) ? 'selected' : '' ?>><?= $nama ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label for="f-mulai">Jam mulai</label>
            <input type="time" id="f-mulai" name="jam_mulai" value="07:00" required/>
          </div>
          <div>
            <label for="f-selesai">Jam selesai</label>
            <input type="time" id="f-selesai" name="jam_selesai" value="08:30" required/>
          </div>
          <div style="grid-column: 1 / -1;">
            <label for="f-materi">Materi</label>
            <input type="text" id="f-materi" name="materi" maxlength="150" placeholder="Passing Bawah Bola Voli" required/>
          </div>
          <div style="grid-column: 1 / -1;">
            <label for="f-catatan">Catatan (opsional)</label>
            <input type="text" id="f-catatan" name="catatan" maxlength="255" placeholder="Lapangan, kelas cadangan, dsb."/>
          </div>
        </div>
        <div class="baris-aksi">
          <button class="btn" type="submit">Simpan Jadwal</button>
        </div>
      </form>
    </section>

    <section class="card">
      <h2>Impor Massal (Template)</h2>
      <p class="lead">Format CSV: <code>kelas,hari,jam_mulai,jam_selesai,materi,catatan</code>. Baris dengan kelas + hari + jam mulai yang sama akan diperbarui, bukan digandakan.</p>
      <form method="post" action="jadwal-guru.php" enctype="multipart/form-data">
        <input type="hidden" name="aksi" value="impor"/>
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(padi_csrf_token(), ENT_QUOTES, 'UTF-8') ?>"/>
        <label for="f-data">Tempel CSV</label>
        <textarea id="f-data" name="data" placeholder="kelas,hari,jam_mulai,jam_selesai,materi,catatan&#10;X-1,Senin,07:00,08:30,Passing Bawah Bola Voli,Latihan"></textarea>
        <div class="grid" style="margin-top:10px;">
          <div>
            <label for="f-berkas">Atau pilih berkas CSV</label>
            <input type="file" id="f-berkas" name="berkas" accept=".csv,text/csv" style="font-size:12px;"/>
          </div>
        </div>
        <div class="baris-aksi">
          <button class="btn" type="submit">Impor Sekarang</button>
          <a class="btn btn-ringan" href="jadwal-guru.php?template=1">Unduh Template CSV</a>
        </div>
      </form>
    </section>

    <section class="card">
      <div class="siswa-head" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;justify-content:space-between;margin-bottom:10px;">
        <h2 style="margin:0;">Daftar Jadwal (<?= count($baris) ?>)</h2>
        <form method="get" action="jadwal-guru.php" style="display:flex;gap:8px;align-items:flex-end;">
          <div>
            <label for="f-filter">Filter kelas</label>
            <select id="f-filter" name="kelas" onchange="this.form.submit()">
              <option value="">Semua kelas</option>
              <?php foreach ($daftarKelas as $k): ?>
                <option value="<?= htmlspecialchars($k, ENT_QUOTES, 'UTF-8') ?>" <?= ($filterKelas === $k) ? 'selected' : '' ?>><?= htmlspecialchars($k, ENT_QUOTES, 'UTF-8') ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <noscript><button class="btn btn-ringan" type="submit">Terapkan</button></noscript>
        </form>
      </div>

      <?php if (!$baris): ?>
        <div class="kosong">Belum ada jadwal. Tambahkan satu per satu atau impor dari template CSV.</div>
      <?php else: ?>
        <div class="table-wrap">
          <table>
            <thead>
              <tr><th>Hari</th><th>Jam</th><th>Kelas</th><th>Materi</th><th>Catatan</th><th></th></tr>
            </thead>
            <tbody>
              <?php foreach ($baris as $b): ?>
                <tr>
                  <td><?= htmlspecialchars($HARI[(int)$b['hari']] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                  <td class="jam"><?= htmlspecialchars(substr((string)$b['jam_mulai'], 0, 5), ENT_QUOTES, 'UTF-8') ?>–<?= htmlspecialchars(substr((string)$b['jam_selesai'], 0, 5), ENT_QUOTES, 'UTF-8') ?></td>
                  <td><span class="pill"><?= htmlspecialchars($b['kelas'], ENT_QUOTES, 'UTF-8') ?></span></td>
                  <td><?= htmlspecialchars($b['materi'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= htmlspecialchars((string)$b['catatan'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td>
                    <form method="post" action="jadwal-guru.php" onsubmit="return confirm('Hapus jadwal ini?');">
                      <input type="hidden" name="aksi" value="hapus"/>
                      <input type="hidden" name="id" value="<?= (int)$b['id'] ?>"/>
                      <input type="hidden" name="csrf" value="<?= htmlspecialchars(padi_csrf_token(), ENT_QUOTES, 'UTF-8') ?>"/>
                      <button class="btn btn-hapus" type="submit">Hapus</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php if ($filterKelas !== ''): ?>
          <form method="post" action="jadwal-guru.php" style="margin-top:12px;" onsubmit="return confirm('Kosongkan seluruh jadwal kelas ini?');">
            <input type="hidden" name="aksi" value="kosongkan"/>
            <input type="hidden" name="kelas" value="<?= htmlspecialchars($filterKelas, ENT_QUOTES, 'UTF-8') ?>"/>
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(padi_csrf_token(), ENT_QUOTES, 'UTF-8') ?>"/>
            <button class="btn btn-hapus" type="submit">Kosongkan jadwal kelas <?= htmlspecialchars($filterKelas, ENT_QUOTES, 'UTF-8') ?></button>
          </form>
        <?php endif; ?>
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
    <a href="nilai-guru.php" class="nav-item" id="nav-nilai" aria-label="Nilai">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
      <span>Nilai</span>
    </a>
    <a href="jadwal-guru.php" class="nav-item active" id="nav-jadwal" aria-label="Jadwal" aria-current="page">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="16" rx="2"/><path stroke-linecap="round" d="M8 3v4M16 3v4M3 11h18"/></svg>
      <span>Jadwal</span>
    </a>
    <a href="profil-guru.php" class="nav-item" id="nav-profil" aria-label="Profil">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
      <span>Profil</span>
    </a>
  </nav>
<?php padi_cetak_ttd(); ?>
</body>
</html>
