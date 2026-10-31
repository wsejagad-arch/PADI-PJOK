<?php
// verifikasi-nilai.php — guru mengonfirmasi/mengoreksi nilai rubrik siswa.
// nilai_rubrik = isian siswa (tidak dihapus); nilai_guru = nilai resmi yang dipakai rekap.
require_once 'auth.php';
wajibLoginGuru();
require 'koneksi.php';

pastikanKolomVerifikasi($conn);

$pesan = '';
$galat = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'simpan_verifikasi') {
    if (!padi_csrf_valid()) {
        $galat = 'Sesi formulir kedaluwarsa. Muat ulang halaman lalu coba lagi.';
    } else {
        $siswa_id = (int)($_POST['siswa_id'] ?? 0);
        $catatan = mb_substr(trim((string)($_POST['catatan'] ?? '')), 0, 300);
        $guru_id = (int)($_SESSION['guru_id'] ?? 0);

        $cek_siswa = $conn->prepare("SELECT id FROM siswa WHERE id = ? LIMIT 1");
        $cek_siswa->bind_param("i", $siswa_id);
        $cek_siswa->execute();
        $ada = $cek_siswa->get_result()->num_rows > 0;
        $cek_siswa->close();

        if (!$ada) {
            $galat = 'Data siswa tidak ditemukan.';
        } else {
            padi_simpan_nilai_guru($conn, 'penilaian_afektif', 'siswa_id', $siswa_id, $_POST['afektif'] ?? '', $catatan, $guru_id);
            padi_simpan_nilai_guru($conn, 'penilaian_psikomotor', 'siswa_id', $siswa_id, $_POST['psikomotor'] ?? '', $catatan, $guru_id);
            padi_simpan_nilai_guru($conn, 'penilaian_rekan', 'penilai_id', $siswa_id, $_POST['rekan'] ?? '', $catatan, $guru_id);
            $pesan = 'Nilai siswa berhasil disimpan.';
        }
    }
}

$daftar = [];
if ($conn) {
    $q = "SELECT s.id, s.nama,
                 a.nilai_rubrik AS afektif_siswa, a.nilai_guru AS afektif_guru,
                 p.nilai_rubrik AS psikomotor_siswa, p.nilai_guru AS psikomotor_guru,
                 r.nilai_rubrik AS rekan_siswa, r.nilai_guru AS rekan_guru,
                 COALESCE(a.catatan_guru, p.catatan_guru, r.catatan_guru) AS catatan
          FROM siswa s
          LEFT JOIN penilaian_afektif a ON a.siswa_id = s.id
          LEFT JOIN penilaian_psikomotor p ON p.siswa_id = s.id
          LEFT JOIN penilaian_rekan r ON r.penilai_id = s.id
          ORDER BY s.nama ASC
          LIMIT 200";
    $res = $conn->query($q);
    if ($res) {
        while ($row = $res->fetch_assoc()) $daftar[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Verifikasi Nilai – PADI-PJOK</title>
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    :root{--blue:#1A56DB;--green:#16A34A;--red:#DC2626;--text:#111827;--text-3:#6B7280;--border:#E5E7EB;--bg:#F3F6FB;--white:#fff}
    body{font-family:Inter,system-ui,sans-serif;background:var(--bg);color:var(--text);max-width:560px;margin:0 auto;padding:16px}
    .topbar{display:flex;align-items:center;gap:10px;margin-bottom:14px}
    .topbar a{display:flex;align-items:center;justify-content:center;width:34px;height:34px;background:var(--white);border:1px solid var(--border);border-radius:9px;color:var(--blue);text-decoration:none;font-size:16px}
    .topbar h1{font-size:17px;font-weight:800}
    .notif{padding:10px 12px;border-radius:9px;font-size:12.5px;margin-bottom:12px}
    .notif.ok{background:#DCFCE7;color:#166534}
    .notif.err{background:#FEE2E2;color:#991B1B}
    .card{background:var(--white);border:1px solid var(--border);border-radius:12px;padding:14px;margin-bottom:10px}
    .nama{font-size:14px;font-weight:700;margin-bottom:2px}
    .siswa{font-size:11.5px;color:var(--text-3);margin-bottom:10px}
    .grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}
    label{display:block;font-size:11px;font-weight:600;color:var(--text-3);margin-bottom:4px}
    input[type=number],input[type=text]{width:100%;padding:8px;border:1.5px solid var(--border);border-radius:8px;font:inherit;font-size:13px}
    input[type=number]{text-align:center;font-weight:700}
    .catatan{margin-top:8px}
    button{margin-top:10px;width:100%;padding:10px;background:var(--blue);color:#fff;border:0;border-radius:9px;font:inherit;font-size:13px;font-weight:700;cursor:pointer}
    .badge{display:inline-block;font-size:10px;font-weight:700;padding:2px 7px;border-radius:9px;background:#DCFCE7;color:var(--green);margin-left:6px}
    .kosong{text-align:center;color:var(--text-3);font-size:13px;padding:24px}
  </style>
</head>
<body>
  <header class="topbar">
    <a href="rekap-penilaian.php" aria-label="Kembali">&#8592;</a>
    <h1>Verifikasi Nilai Siswa</h1>
  </header>

  <?php if ($pesan !== ''): ?><div class="notif ok"><?= htmlspecialchars($pesan) ?></div><?php endif; ?>
  <?php if ($galat !== ''): ?><div class="notif err"><?= htmlspecialchars($galat) ?></div><?php endif; ?>

  <p style="font-size:11.5px;color:var(--text-3);margin-bottom:12px">
    Isian siswa ditampilkan sebagai pembanding. Nilai yang Anda simpan di sini menjadi nilai resmi pada rekap.
    Kosongkan kolom untuk mencabut verifikasi.
  </p>

  <?php if (count($daftar) === 0): ?>
    <div class="kosong">Belum ada siswa pada sesi aktif.</div>
  <?php else: ?>
    <?php foreach ($daftar as $s): ?>
      <form method="POST" class="card">
        <?= padi_csrf_field() ?>
        <input type="hidden" name="action" value="simpan_verifikasi"/>
        <input type="hidden" name="siswa_id" value="<?= (int)$s['id'] ?>"/>
        <div class="nama">
          <?= htmlspecialchars($s['nama']) ?>
          <?php if ($s['afektif_guru'] !== null || $s['psikomotor_guru'] !== null): ?><span class="badge">terverifikasi</span><?php endif; ?>
        </div>
        <div class="siswa">
          Isian siswa — Afektif: <?= (int)($s['afektif_siswa'] ?? 0) ?>/20 ·
          Psikomotor: <?= (int)($s['psikomotor_siswa'] ?? 0) ?>/20 ·
          Rekan: <?= (int)($s['rekan_siswa'] ?? 0) ?>/20
        </div>
        <div class="grid">
          <div>
            <label for="af-<?= (int)$s['id'] ?>">Afektif (0–20)</label>
            <input type="number" id="af-<?= (int)$s['id'] ?>" name="afektif" min="0" max="20" step="1"
                   value="<?= $s['afektif_guru'] !== null ? (int)$s['afektif_guru'] : '' ?>" placeholder="-"/>
          </div>
          <div>
            <label for="ps-<?= (int)$s['id'] ?>">Psikomotor (0–20)</label>
            <input type="number" id="ps-<?= (int)$s['id'] ?>" name="psikomotor" min="0" max="20" step="1"
                   value="<?= $s['psikomotor_guru'] !== null ? (int)$s['psikomotor_guru'] : '' ?>" placeholder="-"/>
          </div>
          <div>
            <label for="rk-<?= (int)$s['id'] ?>">Rekan (0–20)</label>
            <input type="number" id="rk-<?= (int)$s['id'] ?>" name="rekan" min="0" max="20" step="1"
                   value="<?= $s['rekan_guru'] !== null ? (int)$s['rekan_guru'] : '' ?>" placeholder="-"/>
          </div>
        </div>
        <div class="catatan">
          <label for="ct-<?= (int)$s['id'] ?>">Catatan guru (opsional)</label>
          <input type="text" id="ct-<?= (int)$s['id'] ?>" name="catatan" maxlength="300"
                 value="<?= htmlspecialchars((string)($s['catatan'] ?? '')) ?>" placeholder="Contoh: gerakan sudah sesuai, tinggal keseimbangan"/>
        </div>
        <button type="submit">Simpan nilai resmi</button>
      </form>
    <?php endforeach; ?>
  <?php endif; ?>
</body>
</html>
