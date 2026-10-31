<?php
// admin-pengguna.php — kelola akun guru. Admin sekolah: akun sekolahnya sendiri.
// Super-admin: semua sekolah + reset massal + pantau status.
require_once 'auth.php';
padi_wajib_admin();
require 'koneksi.php';

$sidAktif = padi_sekolah_id();
$super = padi_guru_super();
$guruId = (int)($_SESSION['guru_id'] ?? 0);

/** Acak password sementara yang mudah dibaca. */
if (!function_exists('padi_password_sementara')) {
function padi_password_sementara()
{
    $alfabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $acak = '';
    for ($i = 0; $i < 8; $i++) {
        $acak .= $alfabet[random_int(0, strlen($alfabet) - 1)];
    }
    return 'Padi' . $acak;
}
}

/** Pastikan target akun berada dalam sekolah yang boleh dikelola. */
if (!function_exists('padi_admin_boleh_target')) {
function padi_admin_boleh_target($conn, $idTarget, $idSekolah, $super)
{
    $stmt = $conn->prepare("SELECT id, sekolah_id, is_super, is_admin FROM guru WHERE id = ? LIMIT 1");
    if (!$stmt) return null;
    $stmt->bind_param('i', $idTarget);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) return null;
    if (!$super) {
        if ((int)$row['sekolah_id'] !== (int)$idSekolah) return null;
        if ((int)$row['is_super'] === 1) return null;
    }
    return $row;
}
}

$pesan = '';
$galat = '';
$kredensial = [];

if ($conn && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = (string)($_POST['aksi'] ?? '');
    if (!padi_csrf_valid()) {
        $galat = 'Sesi formulir kedaluwarsa. Muat ulang halaman lalu coba lagi.';
    } elseif ($aksi === 'tambah') {
        $nama = trim((string)($_POST['nama'] ?? ''));
        $username = strtolower(preg_replace('/[^a-z0-9._-]/', '', strtolower(trim((string)($_POST['username'] ?? '')))));
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $pass = (string)($_POST['password'] ?? '');
        $jadikanAdmin = !empty($_POST['is_admin']) ? 1 : 0;

        if (mb_strlen($nama) < 3) {
            $galat = 'Nama guru minimal 3 karakter.';
        } elseif (strlen($username) < 3) {
            $galat = 'Username minimal 3 karakter (huruf/angka/titik).';
        } elseif (strlen($pass) < 8) {
            $galat = 'Password minimal 8 karakter.';
        } else {
            $cek = $conn->prepare("SELECT id FROM guru WHERE LOWER(username) = ? LIMIT 1");
            $cek->bind_param('s', $username);
            $cek->execute();
            $sudah = $cek->get_result()->num_rows > 0;
            $cek->close();
            if ($sudah) {
                $galat = 'Username "' . $username . '" sudah dipakai.';
            } else {
                $hash = password_hash($pass, PASSWORD_DEFAULT);
                $emailNilai = $email !== '' ? $email : null;
                $stmt = $conn->prepare("INSERT INTO guru (nama, username, password, sekolah_id, email, is_super, is_admin, status)
                                        VALUES (?, ?, ?, ?, ?, 0, ?, 'aktif')");
                $stmt->bind_param('sssisi', $nama, $username, $hash, $sidAktif, $emailNilai, $jadikanAdmin);
                $stmt->execute();
                $stmt->close();
                $pesan = 'Akun guru "' . $nama . '" ditambahkan.';
            }
        }
    } elseif ($aksi === 'ubah') {
        $id = (int)($_POST['id'] ?? 0);
        $nama = trim((string)($_POST['nama'] ?? ''));
        $nip = trim((string)($_POST['nip'] ?? ''));
        $status = (($_POST['status'] ?? 'aktif') === 'nonaktif') ? 'nonaktif' : 'aktif';
        $adminBaru = !empty($_POST['is_admin']) ? 1 : 0;
        $target = padi_admin_boleh_target($conn, $id, $sidAktif, $super);

        if (!$target) {
            $galat = 'Akun tidak ditemukan atau di luar kewenangan Anda.';
        } elseif (mb_strlen($nama) < 3) {
            $galat = 'Nama guru minimal 3 karakter.';
        } elseif ((int)$target['id'] === $guruId && ($adminBaru === 0 || $status !== 'aktif')) {
            $galat = 'Anda tidak dapat menurunkan peran atau menonaktifkan akun sendiri.';
        } else {
            $stmt = $conn->prepare("UPDATE guru SET nama = ?, nip = ?, status = ?, is_admin = ? WHERE id = ?");
            $stmt->bind_param('sssii', $nama, $nip, $status, $adminBaru, $id);
            $stmt->execute();
            $stmt->close();
            if ($id === $guruId) {
                $_SESSION['guru_nama'] = $nama;
                $_SESSION['guru_nip'] = $nip;
                $_SESSION['guru_is_admin'] = $adminBaru;
            }
            $pesan = 'Akun guru diperbarui.';
        }
    } elseif ($aksi === 'reset') {
        $id = (int)($_POST['id'] ?? 0);
        $target = padi_admin_boleh_target($conn, $id, $sidAktif, $super);
        if (!$target) {
            $galat = 'Akun tidak ditemukan atau di luar kewenangan Anda.';
        } else {
            $baru = padi_password_sementara();
            $hash = password_hash($baru, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE guru SET password = ? WHERE id = ?");
            $stmt->bind_param('si', $hash, $id);
            $stmt->execute();
            $stmt->close();
            $u = $conn->prepare("SELECT username, nama FROM guru WHERE id = ? LIMIT 1");
            $u->bind_param('i', $id);
            $u->execute();
            $rowU = $u->get_result()->fetch_assoc();
            $u->close();
            $kredensial[] = ['username' => $rowU['username'], 'nama' => $rowU['nama'], 'password' => $baru];
            $pesan = 'Password 1 akun direset. Catat password sementara di bawah.';
        }
    } elseif ($aksi === 'reset_masal') {
        $daftarId = array_map('intval', (array)($_POST['ids'] ?? []));
        if (!$daftarId && !empty($_POST['semua'])) {
            $sqlIds = $super
                ? "SELECT id FROM guru WHERE is_super = 0"
                : "SELECT id FROM guru WHERE sekolah_id = ?";
            $stmt = $conn->prepare($sqlIds);
            if ($super) {
                $stmt->execute();
            } else {
                $stmt->bind_param('i', $sidAktif);
                $stmt->execute();
            }
            $r = $stmt->get_result();
            while ($x = $r->fetch_assoc()) $daftarId[] = (int)$x['id'];
            $stmt->close();
        }
        $daftarId = array_values(array_filter(array_unique($daftarId)));
        $berhasil = 0;
        foreach ($daftarId as $id) {
            $target = padi_admin_boleh_target($conn, $id, $sidAktif, $super);
            if (!$target) continue;
            $baru = padi_password_sementara();
            $hash = password_hash($baru, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE guru SET password = ? WHERE id = ?");
            $stmt->bind_param('si', $hash, $id);
            $stmt->execute();
            $stmt->close();
            $u = $conn->prepare("SELECT username, nama FROM guru WHERE id = ? LIMIT 1");
            $u->bind_param('i', $id);
            $u->execute();
            $rowU = $u->get_result()->fetch_assoc();
            $u->close();
            $kredensial[] = ['username' => $rowU['username'], 'nama' => $rowU['nama'], 'password' => $baru];
            $berhasil++;
        }
        $pesan = $berhasil . ' akun direset. Catat password sementara di bawah (hanya tampil sekali).';
    } elseif ($aksi === 'hapus') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id === $guruId) {
            $galat = 'Anda tidak dapat menghapus akun sendiri.';
        } else {
            $target = padi_admin_boleh_target($conn, $id, $sidAktif, $super);
            if (!$target) {
                $galat = 'Akun tidak ditemukan atau di luar kewenangan Anda.';
            } else {
                $qAdmin = $conn->prepare("SELECT COUNT(*) AS c FROM guru WHERE sekolah_id = ? AND is_admin = 1 AND id <> ?");
                $sidTarget = (int)$target['sekolah_id'];
                $qAdmin->bind_param('ii', $sidTarget, $id);
                $qAdmin->execute();
                $sisaAdmin = (int)$qAdmin->get_result()->fetch_assoc()['c'];
                $qAdmin->close();
                if ((int)$target['is_admin'] === 1 && $sisaAdmin === 0) {
                    $galat = 'Tidak bisa menghapus admin terakhir sekolah ini. Angkat admin lain dulu.';
                } else {
                    $stmt = $conn->prepare("DELETE FROM guru WHERE id = ?");
                    $stmt->bind_param('i', $id);
                    $stmt->execute();
                    $stmt->close();
                    $pesan = 'Akun guru dihapus.';
                }
            }
        }
    }
}

// Daftar akun
$daftar = [];
if ($conn) {
    if ($super) {
        $sql = "SELECT g.id, g.nama, g.username, g.email, g.nip, g.sekolah_id, g.is_super, g.is_admin, g.status, g.terakhir_login,
                       s.nama AS sekolah
                FROM guru g LEFT JOIN sekolah s ON s.id = g.sekolah_id
                ORDER BY g.sekolah_id ASC, g.is_admin DESC, g.nama ASC";
        $q = $conn->query($sql);
    } else {
        $stmt = $conn->prepare("SELECT g.id, g.nama, g.username, g.email, g.nip, g.sekolah_id, g.is_super, g.is_admin, g.status, g.terakhir_login,
                                       s.nama AS sekolah
                                FROM guru g LEFT JOIN sekolah s ON s.id = g.sekolah_id
                                WHERE g.sekolah_id = ? AND g.is_super = 0
                                ORDER BY g.is_admin DESC, g.nama ASC");
        $stmt->bind_param('i', $sidAktif);
        $stmt->execute();
        $q = $stmt->get_result();
    }
    if ($q) {
        while ($row = $q->fetch_assoc()) $daftar[] = $row;
    }
    if (isset($stmt)) $stmt->close();
}

$ringkas = ['total' => count($daftar), 'admin' => 0, 'aktif' => 0, 'nonaktif' => 0];
foreach ($daftar as $d) {
    if ((int)$d['is_admin'] === 1) $ringkas['admin']++;
    if (strtolower((string)$d['status']) === 'aktif') $ringkas['aktif']++; else $ringkas['nonaktif']++;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Kelola Akun Guru – PADI-PJOK</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --blue: #1A56DB; --blue-light: #EFF4FF; --text: #111827; --text-3: #6B7280;
      --border: #E5E7EB; --bg: #F3F6FB; --white: #FFFFFF; --radius: 16px; --radius-sm: 10px; --red: #DC2626;
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
    .flash { border-radius: var(--radius-sm); padding: 10px 12px; font-size: 12.5px; font-weight: 600; background: #DCFCE7; color: #166534; border: 1px solid #86EFAC; }
    .flash.galat { background: #FEE2E2; color: #991B1B; border-color: #FCA5A5; }
    .ringkas { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 10px; margin-top: 12px; }
    .ringkas-item { border-radius: var(--radius-sm); padding: 10px; text-align: center; background: var(--blue-light); }
    .ringkas-num { display: block; font-size: 19px; font-weight: 800; }
    .ringkas-label { font-size: 10.5px; font-weight: 600; color: var(--text-3); }
    .table-wrap { overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; font-size: 12.5px; min-width: 820px; }
    th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid var(--border); vertical-align: middle; }
    th { font-size: 10.5px; text-transform: uppercase; letter-spacing: .04em; color: var(--text-3); background: #F8FAFC; white-space: nowrap; }
    td.nama { font-weight: 600; }
    .pill { display: inline-block; font-size: 10.5px; font-weight: 700; padding: 2px 8px; border-radius: 999px; background: var(--blue-light); color: var(--blue); }
    .pill.off { background: #FEE2E2; color: #B91C1C; }
    .pill.admin { background: #FEF3C7; color: #92400E; }
    .baris-aksi { display: flex; gap: 6px; flex-wrap: wrap; }
    .btn { display: inline-flex; align-items: center; justify-content: center; height: 34px; padding: 0 12px; border-radius: var(--radius-sm); font: inherit; font-size: 12px; font-weight: 700; cursor: pointer; background: var(--blue); color: #fff; text-decoration: none; border: 0; }
    .btn-ringan { background: #fff; color: var(--blue); border: 1px solid var(--border); }
    .btn-kecil { height: 26px; padding: 0 9px; font-size: 11px; background: #fff; color: var(--blue); border: 1px solid #BFDBFE; }
    .btn-danger { background: #fff; color: var(--red); border: 1px solid #FCA5A5; }
    .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 10px; margin-top: 6px; }
    .form-group label { display: block; font-size: 11px; font-weight: 700; color: var(--text-3); margin-bottom: 4px; }
    .form-input { width: 100%; font: inherit; font-size: 13px; padding: 8px 10px; border-radius: var(--radius-sm); background: #fff; }
    .aksi { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
    .kredensial { width: 100%; border-collapse: collapse; font-size: 12.5px; margin-top: 8px; }
    .kredensial td, .kredensial th { border: 1px solid var(--border); padding: 6px 8px; }
    .kredensial code { font-size: 13px; font-weight: 800; }
    label.inline { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 600; }
  </style>
<?php require_once __DIR__ . '/ui-head.php'; padi_ui_head(); ?>
</head>
<body>
  <header class="topbar">
    <button class="back-btn" type="button" aria-label="Kembali" onclick="window.location.href='profil-guru.php'">
      <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </button>
    <div class="topbar-title">Kelola Akun Guru</div>
  </header>

  <main class="content">
    <?php if ($pesan !== ''): ?><div class="flash" role="status"><?= htmlspecialchars($pesan, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <?php if ($galat !== ''): ?><div class="flash galat" role="alert"><?= htmlspecialchars($galat, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

    <?php if ($kredensial): ?>
      <section class="card">
        <h2>Password sementara (tampil sekali)</h2>
        <p class="lead">Sampaikan ke guru terkait, minta segera ganti lewat menu Profil.</p>
        <table class="kredensial">
          <thead><tr><th>Nama</th><th>Username</th><th>Password baru</th></tr></thead>
          <tbody>
            <?php foreach ($kredensial as $k): ?>
              <tr>
                <td><?= htmlspecialchars((string)$k['nama'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string)$k['username'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><code><?= htmlspecialchars((string)$k['password'], ENT_QUOTES, 'UTF-8') ?></code></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </section>
    <?php endif; ?>

    <section class="card">
      <h1>Akun Guru <?= $super ? '(semua sekolah)' : '(' . htmlspecialchars((string)padi_sekolah('nama'), ENT_QUOTES, 'UTF-8') . ')' ?></h1>
      <p class="lead">Admin sekolah dapat mengelola akun gurunya sendiri: tambah, ubah, nonaktifkan, reset password (satuan/massal), dan hapus.</p>
      <div class="ringkas">
        <div class="ringkas-item"><span class="ringkas-num"><?= (int)$ringkas['total'] ?></span><span class="ringkas-label">Total akun</span></div>
        <div class="ringkas-item"><span class="ringkas-num"><?= (int)$ringkas['admin'] ?></span><span class="ringkas-label">Admin</span></div>
        <div class="ringkas-item"><span class="ringkas-num"><?= (int)$ringkas['aktif'] ?></span><span class="ringkas-label">Aktif</span></div>
        <div class="ringkas-item"><span class="ringkas-num"><?= (int)$ringkas['nonaktif'] ?></span><span class="ringkas-label">Nonaktif</span></div>
      </div>
    </section>

    <section class="card">
      <h2>Daftar Akun</h2>
      <form method="post" action="admin-pengguna.php">
        <?= padi_csrf_field() ?>
        <input type="hidden" name="aksi" value="reset_masal"/>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th><input type="checkbox" id="cek-semua" aria-label="Pilih semua"/></th>
                <th>Nama</th><th>Username</th><th>Email</th><th>Sekolah</th><th>Peran</th><th>Status</th><th>Login terakhir</th><th>Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$daftar): ?>
                <tr><td colspan="9">Belum ada akun guru.</td></tr>
              <?php else: foreach ($daftar as $g): ?>
                <tr>
                  <td><input type="checkbox" class="cek-akun" name="ids[]" value="<?= (int)$g['id'] ?>" aria-label="Pilih <?= htmlspecialchars((string)$g['nama'], ENT_QUOTES, 'UTF-8') ?>"/></td>
                  <td class="nama"><?= htmlspecialchars((string)$g['nama'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= htmlspecialchars((string)$g['username'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= htmlspecialchars((string)($g['email'] ?: '-'), ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= htmlspecialchars((string)($g['sekolah'] ?: '-'), ENT_QUOTES, 'UTF-8') ?></td>
                  <td>
                    <?php if ((int)$g['is_super'] === 1): ?><span class="pill admin">super-admin</span>
                    <?php elseif ((int)$g['is_admin'] === 1): ?><span class="pill admin">admin</span>
                    <?php else: ?><span class="pill">guru</span><?php endif; ?>
                  </td>
                  <td><span class="pill <?= strtolower((string)$g['status']) === 'aktif' ? '' : 'off' ?>"><?= htmlspecialchars((string)$g['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
                  <td><?= $g['terakhir_login'] ? htmlspecialchars(date('d/m/Y H:i', strtotime((string)$g['terakhir_login'])), ENT_QUOTES, 'UTF-8') : '-' ?></td>
                  <td>
                    <div class="baris-aksi">
                      <a class="btn btn-kecil" href="admin-pengguna.php?edit=<?= (int)$g['id'] ?>#form-akun">Ubah</a>
                      <form method="post" action="admin-pengguna.php" onsubmit="return confirm('Reset password akun ini?');">
                        <?= padi_csrf_field() ?>
                        <input type="hidden" name="aksi" value="reset"/>
                        <input type="hidden" name="id" value="<?= (int)$g['id'] ?>"/>
                        <button class="btn btn-kecil" type="submit">Reset</button>
                      </form>
                      <?php if ((int)$g['id'] !== $guruId): ?>
                        <form method="post" action="admin-pengguna.php" onsubmit="return confirm('Hapus akun ini permanen?');">
                          <?= padi_csrf_field() ?>
                          <input type="hidden" name="aksi" value="hapus"/>
                          <input type="hidden" name="id" value="<?= (int)$g['id'] ?>"/>
                          <button class="btn btn-kecil btn-danger" type="submit">Hapus</button>
                        </form>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
        <div class="aksi">
          <button class="btn" type="submit" onclick="return confirm('Reset password akun terpilih? Password sementara akan ditampilkan sekali.');">Reset Terpilih</button>
          <button class="btn btn-ringan" type="submit" name="semua" value="1" onclick="return confirm('Reset password SEMUA akun yang boleh Anda kelola?');">Reset Semua</button>
        </div>
      </form>
    </section>

    <?php
      $edit = null;
      $editId = (int)($_GET['edit'] ?? 0);
      if ($editId > 0) {
          foreach ($daftar as $g) {
              if ((int)$g['id'] === $editId) { $edit = $g; break; }
          }
      }
    ?>
    <section class="card" id="form-akun">
      <h2><?= $edit ? 'Ubah Akun: ' . htmlspecialchars((string)$edit['nama'], ENT_QUOTES, 'UTF-8') : 'Tambah Akun Guru' ?></h2>
      <?php if ($edit): ?>
        <form method="post" action="admin-pengguna.php">
          <?= padi_csrf_field() ?>
          <input type="hidden" name="aksi" value="ubah"/>
          <input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"/>
          <div class="form-grid">
            <div class="form-group"><label for="u-nama">Nama guru</label>
              <input class="form-input" type="text" id="u-nama" name="nama" value="<?= htmlspecialchars((string)$edit['nama'], ENT_QUOTES, 'UTF-8') ?>" required/></div>
            <div class="form-group"><label for="u-nip">NIP</label>
              <input class="form-input" type="text" id="u-nip" name="nip" value="<?= htmlspecialchars((string)($edit['nip'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"/></div>
            <div class="form-group"><label for="u-status">Status</label>
              <select class="form-input" id="u-status" name="status">
                <option value="aktif" <?= strtolower((string)$edit['status']) === 'aktif' ? 'selected' : '' ?>>aktif</option>
                <option value="nonaktif" <?= strtolower((string)$edit['status']) === 'nonaktif' ? 'selected' : '' ?>>nonaktif</option>
              </select></div>
            <div class="form-group"><label>Peran</label>
              <label class="inline"><input type="checkbox" name="is_admin" value="1" <?= (int)$edit['is_admin'] === 1 ? 'checked' : '' ?>/> Admin sekolah</label></div>
          </div>
          <div class="aksi">
            <button class="btn" type="submit">Simpan Perubahan</button>
            <a class="btn btn-ringan" href="admin-pengguna.php">Batal</a>
          </div>
        </form>
      <?php else: ?>
        <p class="lead">Akun baru langsung aktif. Guru dapat login dengan username + password, atau dengan akun Belajar.id bila emailnya diisi.</p>
        <form method="post" action="admin-pengguna.php">
          <?= padi_csrf_field() ?>
          <input type="hidden" name="aksi" value="tambah"/>
          <div class="form-grid">
            <div class="form-group"><label for="t-nama">Nama guru</label>
              <input class="form-input" type="text" id="t-nama" name="nama" placeholder="Nama lengkap, gelar" required/></div>
            <div class="form-group"><label for="t-username">Username</label>
              <input class="form-input" type="text" id="t-username" name="username" placeholder="mis. budi.pjok" required/></div>
            <div class="form-group"><label for="t-email">Email Belajar.id (opsional)</label>
              <input class="form-input" type="email" id="t-email" name="email" placeholder="nama@belajar.id"/></div>
            <div class="form-group"><label for="t-pass">Password awal (min. 8)</label>
              <input class="form-input" type="text" id="t-pass" name="password" minlength="8" placeholder="minimal 8 karakter" required/></div>
            <div class="form-group"><label>Peran</label>
              <label class="inline"><input type="checkbox" name="is_admin" value="1"/> Admin sekolah</label></div>
          </div>
          <div class="aksi">
            <button class="btn" type="submit">Tambah Akun</button>
          </div>
        </form>
      <?php endif; ?>
    </section>
  </main>

  <script>
    var semua = document.getElementById('cek-semua');
    if (semua) {
      semua.addEventListener('change', function () {
        document.querySelectorAll('.cek-akun').forEach(function (c) { c.checked = semua.checked; });
      });
    }
  </script>
</body>
</html>
