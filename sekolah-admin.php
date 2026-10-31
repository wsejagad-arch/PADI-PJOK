<?php
// sekolah-admin.php — kelola sekolah (multi-tenant). Hanya untuk guru super-admin.
require_once 'auth.php';
wajibLoginGuru();
require 'koneksi.php';

if (empty($_SESSION['guru_is_super'])) {
    padi_kembali('profil-guru.php', 'Halaman ini hanya untuk super-admin platform.');
}

$pesan = '';
$galat = '';
$editId = (int)($_GET['edit'] ?? 0);
$form = ['id' => 0, 'slug' => '', 'nama' => '', 'instansi' => '', 'alamat' => '', 'kota' => '', 'npsn' => '', 'tahun_pelajaran' => '', 'kepala_sekolah' => '', 'kepala_nip' => '', 'status' => 'aktif'];

if ($conn && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = (string)($_POST['aksi'] ?? '');
    if (!padi_csrf_valid()) {
        $galat = 'Sesi formulir kedaluwarsa. Muat ulang halaman lalu coba lagi.';
    } elseif ($aksi === 'simpan') {
        $id = (int)($_POST['id'] ?? 0);
        foreach (['slug', 'nama', 'instansi', 'alamat', 'kota', 'npsn', 'tahun_pelajaran', 'kepala_sekolah', 'kepala_nip'] as $k) {
            $form[$k] = trim((string)($_POST[$k] ?? ''));
        }
        $form['id'] = $id;
        $form['status'] = (($_POST['status'] ?? 'aktif') === 'nonaktif') ? 'nonaktif' : 'aktif';
        $form['slug'] = padi_slugkan($form['slug'] !== '' ? $form['slug'] : $form['nama']);

        if ($form['nama'] === '' || mb_strlen($form['nama']) < 3) {
            $galat = 'Nama sekolah minimal 3 karakter.';
        } elseif ($form['slug'] === '') {
            $galat = 'Slug sekolah tidak valid (huruf/angka/tanda hubung).';
        } else {
            $cek = $conn->prepare("SELECT id FROM sekolah WHERE slug = ? AND id <> ? LIMIT 1");
            $cek->bind_param('si', $form['slug'], $id);
            $cek->execute();
            $bentrok = $cek->get_result()->num_rows > 0;
            $cek->close();

            if ($bentrok) {
                $galat = 'Slug "' . $form['slug'] . '" sudah dipakai sekolah lain.';
            } elseif ($id > 0) {
                $stmt = $conn->prepare("UPDATE sekolah SET slug = ?, nama = ?, instansi = ?, alamat = ?, kota = ?, npsn = ?,
                                        tahun_pelajaran = ?, kepala_sekolah = ?, kepala_nip = ?, status = ? WHERE id = ?");
                $stmt->bind_param('ssssssssssi', $form['slug'], $form['nama'], $form['instansi'], $form['alamat'], $form['kota'],
                                  $form['npsn'], $form['tahun_pelajaran'], $form['kepala_sekolah'], $form['kepala_nip'], $form['status'], $id);
                $stmt->execute();
                $stmt->close();
                $pesan = 'Data sekolah diperbarui.';
                $form = array_merge($form, ['id' => 0]);
            } else {
                $stmt = $conn->prepare("INSERT INTO sekolah (slug, nama, instansi, alamat, kota, npsn, tahun_pelajaran, kepala_sekolah, kepala_nip, status)
                                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param('ssssssssss', $form['slug'], $form['nama'], $form['instansi'], $form['alamat'], $form['kota'],
                                  $form['npsn'], $form['tahun_pelajaran'], $form['kepala_sekolah'], $form['kepala_nip'], $form['status']);
                $stmt->execute();
                $stmt->close();
                $pesan = 'Sekolah baru ditambahkan.';
                $form = array_fill_keys(array_keys($form), '');
                $form['id'] = 0;
                $form['status'] = 'aktif';
            }
        }
    } elseif ($aksi === 'status') {
        $id = (int)($_POST['id'] ?? 0);
        $baru = (($_POST['status'] ?? 'aktif') === 'aktif') ? 'aktif' : 'nonaktif';
        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE sekolah SET status = ? WHERE id = ?");
            $stmt->bind_param('si', $baru, $id);
            $stmt->execute();
            $stmt->close();
            $pesan = 'Status sekolah diubah menjadi ' . $baru . '.';
        }
    } elseif ($aksi === 'pilih') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $row = padi_sekolah_cari($conn, $id);
            if ($row) {
                padi_sekolah_aktifkan($id);
                // Redirect relatif + slug agar tetap bekerja tanpa DNS wildcard subdomain.
                header('Location: dashboard-guru.php?sekolah=' . urlencode((string)$row['slug']));
                exit;
            }
        }
        $galat = 'Sekolah tidak ditemukan.';
    }
}

$daftar = [];
if ($conn) {
    $q = $conn->query("SELECT s.*,
                              (SELECT COUNT(*) FROM guru g WHERE g.sekolah_id = s.id) AS jumlah_guru,
                              (SELECT COUNT(*) FROM master_siswa m WHERE m.sekolah_id = s.id) AS jumlah_siswa,
                              (SELECT COUNT(*) FROM sesi se WHERE se.sekolah_id = s.id) AS jumlah_sesi
                       FROM sekolah s ORDER BY s.status ASC, s.nama ASC");
    if ($q) {
        while ($row = $q->fetch_assoc()) $daftar[] = $row;
    }
}

if ($editId > 0 && !$form['id']) {
    foreach ($daftar as $row) {
        if ((int)$row['id'] === $editId) {
            $form = array_merge($form, $row);
            break;
        }
    }
}
$sedangEdit = (int)$form['id'] > 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Kelola Sekolah – PADI-PJOK</title>
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
    .content { max-width: 980px; margin: 0 auto; padding: 16px; display: flex; flex-direction: column; gap: 14px; }
    .card { background: var(--white); border-radius: var(--radius); padding: 18px; }
    .card h1 { font-size: 17px; margin-bottom: 6px; }
    .card h2 { font-size: 14px; margin-bottom: 10px; }
    .lead { font-size: 12.5px; color: var(--text-3); line-height: 1.55; }
    .flash { border-radius: var(--radius-sm); padding: 10px 12px; font-size: 12.5px; font-weight: 600; background: #DCFCE7; color: #166534; border: 1px solid #86EFAC; }
    .flash.galat { background: #FEE2E2; color: #991B1B; border-color: #FCA5A5; }
    .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px; margin-top: 6px; }
    .form-group label { display: block; font-size: 11px; font-weight: 700; color: var(--text-3); margin-bottom: 4px; }
    .form-input { width: 100%; font: inherit; font-size: 13px; padding: 9px 10px; border-radius: var(--radius-sm); background: #fff; }
    .btn { display: inline-flex; align-items: center; justify-content: center; height: 36px; padding: 0 14px; border-radius: var(--radius-sm); font: inherit; font-size: 12.5px; font-weight: 700; cursor: pointer; background: var(--blue); color: #fff; text-decoration: none; border: 0; }
    .btn-ringan { background: #fff; color: var(--blue); border: 1px solid var(--border); }
    .btn-kecil { height: 28px; padding: 0 10px; font-size: 11px; background: #fff; color: var(--blue); border: 1px solid #BFDBFE; }
    .aksi { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
    .table-wrap { overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; font-size: 12.5px; min-width: 720px; }
    th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid var(--border); }
    th { font-size: 10.5px; text-transform: uppercase; letter-spacing: .04em; color: var(--text-3); background: #F8FAFC; white-space: nowrap; }
    td.nama { font-weight: 600; }
    .pill { display: inline-block; font-size: 10.5px; font-weight: 700; padding: 2px 8px; border-radius: 999px; background: var(--blue-light); color: var(--blue); }
    .pill.off { background: #FEE2E2; color: #B91C1C; }
    .baris-aksi { display: flex; gap: 6px; flex-wrap: wrap; }
  </style>
<?php require_once __DIR__ . '/ui-head.php'; padi_ui_head(); ?>
</head>
<body>
  <header class="topbar">
    <button class="back-btn" type="button" aria-label="Kembali" onclick="window.location.href='profil-guru.php'">
      <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </button>
    <div class="topbar-title">Kelola Sekolah</div>
  </header>

  <main class="content">
    <?php if ($pesan !== ''): ?><div class="flash" role="status"><?= htmlspecialchars($pesan, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <?php if ($galat !== ''): ?><div class="flash galat" role="alert"><?= htmlspecialchars($galat, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

    <section class="card">
      <h1>Multi-tenant: <?= count($daftar) ?> sekolah</h1>
      <p class="lead">Setiap sekolah punya akun guru, kelas, siswa, sesi, dan jadwal sendiri. Data tidak bercampur antar sekolah.</p>
    </section>

    <section class="card">
      <h2>Daftar Sekolah</h2>
      <div class="table-wrap">
        <table>
          <thead>
            <tr><th>Nama</th><th>Slug</th><th>NPSN</th><th>Guru</th><th>Siswa</th><th>Sesi</th><th>Status</th><th>Aksi</th></tr>
          </thead>
          <tbody>
            <?php if (!$daftar): ?>
              <tr><td colspan="8">Belum ada sekolah.</td></tr>
            <?php else: foreach ($daftar as $s): ?>
              <tr>
                <td class="nama"><?= htmlspecialchars((string)$s['nama'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string)$s['slug'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string)($s['npsn'] ?: '-'), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= (int)$s['jumlah_guru'] ?></td>
                <td><?= (int)$s['jumlah_siswa'] ?></td>
                <td><?= (int)$s['jumlah_sesi'] ?></td>
                <td><span class="pill <?= strtolower((string)$s['status']) === 'aktif' ? '' : 'off' ?>"><?= htmlspecialchars((string)$s['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
                <td>
                  <div class="baris-aksi">
                    <a class="btn btn-kecil" href="sekolah-admin.php?edit=<?= (int)$s['id'] ?>">Edit</a>
                    <form method="post" action="sekolah-admin.php">
                      <?= padi_csrf_field() ?>
                      <input type="hidden" name="aksi" value="status"/>
                      <input type="hidden" name="id" value="<?= (int)$s['id'] ?>"/>
                      <input type="hidden" name="status" value="<?= strtolower((string)$s['status']) === 'aktif' ? 'nonaktif' : 'aktif' ?>"/>
                      <button class="btn btn-kecil" type="submit"><?= strtolower((string)$s['status']) === 'aktif' ? 'Nonaktifkan' : 'Aktifkan' ?></button>
                    </form>
                    <form method="post" action="sekolah-admin.php">
                      <?= padi_csrf_field() ?>
                      <input type="hidden" name="aksi" value="pilih"/>
                      <input type="hidden" name="id" value="<?= (int)$s['id'] ?>"/>
                      <button class="btn btn-kecil" type="submit">Masuk</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </section>

    <section class="card">
      <h2><?= $sedangEdit ? 'Edit Sekolah #' . (int)$form['id'] : 'Tambah Sekolah' ?></h2>
      <form method="post" action="sekolah-admin.php">
        <?= padi_csrf_field() ?>
        <input type="hidden" name="aksi" value="simpan"/>
        <input type="hidden" name="id" value="<?= (int)$form['id'] ?>"/>
        <div class="form-grid">
          <div class="form-group"><label for="f-nama">Nama sekolah</label>
            <input class="form-input" type="text" id="f-nama" name="nama" maxlength="150" required value="<?= htmlspecialchars($form['nama'], ENT_QUOTES, 'UTF-8') ?>"/></div>
          <div class="form-group"><label for="f-slug">Slug / subdomain</label>
            <input class="form-input" type="text" id="f-slug" name="slug" maxlength="60" placeholder="sman1-surabaya" value="<?= htmlspecialchars($form['slug'], ENT_QUOTES, 'UTF-8') ?>"/></div>
          <div class="form-group"><label for="f-instansi">Instansi (kop)</label>
            <input class="form-input" type="text" id="f-instansi" name="instansi" maxlength="150" placeholder="Pemerintah Provinsi …" value="<?= htmlspecialchars($form['instansi'], ENT_QUOTES, 'UTF-8') ?>"/></div>
          <div class="form-group"><label for="f-alamat">Alamat</label>
            <input class="form-input" type="text" id="f-alamat" name="alamat" maxlength="255" value="<?= htmlspecialchars($form['alamat'], ENT_QUOTES, 'UTF-8') ?>"/></div>
          <div class="form-group"><label for="f-kota">Kota</label>
            <input class="form-input" type="text" id="f-kota" name="kota" maxlength="100" value="<?= htmlspecialchars($form['kota'], ENT_QUOTES, 'UTF-8') ?>"/></div>
          <div class="form-group"><label for="f-npsn">NPSN</label>
            <input class="form-input" type="text" id="f-npsn" name="npsn" maxlength="20" value="<?= htmlspecialchars($form['npsn'], ENT_QUOTES, 'UTF-8') ?>"/></div>
          <div class="form-group"><label for="f-tahun">Tahun pelajaran</label>
            <input class="form-input" type="text" id="f-tahun" name="tahun_pelajaran" maxlength="20" placeholder="2026/2027" value="<?= htmlspecialchars($form['tahun_pelajaran'], ENT_QUOTES, 'UTF-8') ?>"/></div>
          <div class="form-group"><label for="f-kepala">Kepala sekolah</label>
            <input class="form-input" type="text" id="f-kepala" name="kepala_sekolah" maxlength="100" value="<?= htmlspecialchars($form['kepala_sekolah'], ENT_QUOTES, 'UTF-8') ?>"/></div>
          <div class="form-group"><label for="f-kepala-nip">NIP kepala sekolah</label>
            <input class="form-input" type="text" id="f-kepala-nip" name="kepala_nip" maxlength="30" value="<?= htmlspecialchars($form['kepala_nip'], ENT_QUOTES, 'UTF-8') ?>"/></div>
          <div class="form-group"><label for="f-status">Status</label>
            <select class="form-input" id="f-status" name="status">
              <option value="aktif" <?= $form['status'] === 'aktif' ? 'selected' : '' ?>>aktif</option>
              <option value="nonaktif" <?= $form['status'] === 'nonaktif' ? 'selected' : '' ?>>nonaktif</option>
            </select></div>
        </div>
        <div class="aksi">
          <button class="btn" type="submit"><?= $sedangEdit ? 'Simpan Perubahan' : 'Tambah Sekolah' ?></button>
          <?php if ($sedangEdit): ?><a class="btn btn-ringan" href="sekolah-admin.php">Batal</a><?php endif; ?>
        </div>
      </form>
      <p class="lead" style="margin-top:10px;">Slug dipakai sebagai subdomain (mis. <code>sman1.domain-sekolah.sch.id</code>) atau parameter <code>?sekolah=slug</code> saat mode lokal.</p>
    </section>
  </main>
</body>
</html>
