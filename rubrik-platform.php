<?php
require_once 'auth.php';
wajibLoginGuru();
require 'koneksi.php';
require_once 'rubrik.php';

$rubrik = padi_rubrik_definisi();

// --- Cek kesesuaian data nyata di database ---
$cek = [
    'kognitif' => ['tabel' => 'penilaian_kognitif', 'kolom' => 'nilai_total', 'min' => null, 'max' => null, 'isi' => 0],
    'psikomotor' => ['tabel' => 'penilaian_psikomotor', 'kolom' => 'COALESCE(nilai_guru, nilai_rubrik)', 'min' => null, 'max' => null, 'isi' => 0],
    'afektif' => ['tabel' => 'penilaian_afektif', 'kolom' => 'COALESCE(nilai_guru, nilai_rubrik)', 'min' => null, 'max' => null, 'isi' => 0],
    'diri' => ['tabel' => 'penilaian_rekan', 'kolom' => 'nilai_rubrik', 'min' => null, 'max' => null, 'isi' => 0],
];
if ($conn) {
    foreach ($cek as $kunci => $info) {
        $q = $conn->query("SELECT COUNT(*) AS jml, MIN({$info['kolom']}) AS mn, MAX({$info['kolom']}) AS mx FROM {$info['tabel']} WHERE {$info['kolom']} IS NOT NULL");
        if ($q && ($r = $q->fetch_assoc())) {
            $cek[$kunci]['isi'] = (int)$r['jml'];
            $cek[$kunci]['min'] = $r['mn'] !== null ? (float)$r['mn'] : null;
            $cek[$kunci]['max'] = $r['mx'] !== null ? (float)$r['mx'] : null;
        }
    }
}

// Konversi 0–20 → 0–100 untuk tabel referensi
$konversi = [];
for ($i = 20; $i >= 0; $i--) {
    $konversi[$i] = padi_rubrik_konversi($i);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Rubrik Platform – PADI-PJOK</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --blue: #1A56DB; --blue-light: #EFF4FF; --text: #111827; --text-3: #6B7280; --border: #E5E7EB;
      --bg: #F3F6FB; --white: #FFFFFF; --radius: 16px; --radius-sm: 10px;
      --green: #16A34A; --green-light: #DCFCE7; --orange: #B45309; --orange-light: #FEF3C7;
      --red: #DC2626; --red-light: #FEE2E2;
    }
    body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; padding-bottom: 24px; }
    .topbar { display: flex; align-items: center; gap: 10px; padding: 14px 18px; background: var(--white); position: sticky; top: 0; z-index: 50; }
    .back-btn { width: 36px; height: 36px; border-radius: 10px; background: var(--blue-light); border: none; display: flex; align-items: center; justify-content: center; cursor: pointer; flex-shrink: 0; }
    .back-btn svg { width: 20px; height: 20px; color: var(--blue); }
    .topbar-title { font-size: 15px; font-weight: 700; flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .content { max-width: 1060px; margin: 0 auto; padding: 16px; display: flex; flex-direction: column; gap: 14px; }
    .card { background: var(--white); border-radius: var(--radius); padding: 18px; }
    .card h1 { font-size: 17px; margin-bottom: 6px; }
    .card h2 { font-size: 14px; margin-bottom: 10px; }
    .lead { font-size: 12.5px; color: var(--text-3); line-height: 1.55; }

    .instrumen { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; margin-top: 14px; }
    @media (min-width: 640px) { .instrumen { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
    .instrumen-item { border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 10px; }
    .instrumen-item strong { display: block; font-size: 13px; }
    .instrumen-item span { font-size: 10.5px; color: var(--text-3); }

    .table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
    table { width: 100%; border-collapse: collapse; font-size: 12.5px; min-width: 520px; }
    th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid var(--border); vertical-align: top; }
    th { font-size: 10.5px; text-transform: uppercase; letter-spacing: .04em; color: var(--text-3); background: #F8FAFC; white-space: nowrap; }
    tbody tr:last-child td { border-bottom: 0; }
    td.num, th.num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .badge { display: inline-block; font-size: 10.5px; font-weight: 700; padding: 2px 8px; border-radius: 999px; background: #F1F5F9; color: var(--text-3); }
    .badge.ok { background: var(--green-light); color: #166534; }
    .badge.warn { background: var(--orange-light); color: var(--orange); }
    .badge.err { background: var(--red-light); color: #991B1B; }

    .daftar { list-style: none; display: flex; flex-direction: column; gap: 8px; }
    .daftar li { font-size: 12.5px; line-height: 1.55; padding-left: 18px; position: relative; }
    .daftar li::before { content: "•"; position: absolute; left: 4px; color: var(--blue); font-weight: 800; }

    .aksi { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
    .btn { display: inline-flex; align-items: center; justify-content: center; height: 38px; padding: 0 14px; border-radius: var(--radius-sm); font: inherit; font-size: 12.5px; font-weight: 700; cursor: pointer; background: var(--blue); color: #fff; text-decoration: none; border: 0; }
    .btn-ringan { background: #fff; color: var(--blue); border: 1px solid var(--border); }
    .nav-item { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px; color: #9CA3AF; text-decoration: none; height: 100%; }
    .nav-item svg { width: 22px; height: 22px; }
    .nav-item span { font-size: 10.5px; font-weight: 600; }
    .nav-item.active { color: var(--blue); }
  </style>
<?php require_once __DIR__ . '/ui-head.php'; padi_ui_head(); ?>
</head>
<body>
<?php require_once __DIR__ . '/ui-cetak.php'; padi_cetak_siap('RUBRIK PENILAIAN PJOK', 'Acuan instrumen kognitif, psikomotor, afektif, dan penilaian diri/rekan', ['orientasi' => 'landscape']); ?>

  <header class="topbar">
    <button class="back-btn" type="button" aria-label="Kembali" onclick="window.location.href='dashboard-guru.php'">
      <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </button>
    <div class="topbar-title">Rubrik Platform</div>
  </header>

  <main class="content">
    <section class="card">
      <h1>Rubrik Penilaian PJOK</h1>
      <p class="lead">Acuan resmi kriteria yang dipakai sistem: bobot instrumen, deskriptor skala 1–4, konversi nilai, dan ambang tuntas/predikat. Semua angka di halaman ini bersumber dari <strong>rubrik.php</strong> sehingga tetap sinkron dengan halaman penilaian dan laporan.</p>
      <div class="instrumen">
        <?php foreach ($rubrik as $kunci => $r): ?>
          <div class="instrumen-item">
            <strong><?= htmlspecialchars($r['nama'], ENT_QUOTES, 'UTF-8') ?></strong>
            <span><?= htmlspecialchars($r['skala'], ENT_QUOTES, 'UTF-8') ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="card">
      <h2>Kesesuaian dengan Sistem</h2>
      <div class="table-wrap">
        <table>
          <thead>
            <tr><th>Instrumen</th><th>Sumber data</th><th>Halaman input</th><th>Skala</th><th>Konversi</th></tr>
          </thead>
          <tbody>
            <?php foreach ($rubrik as $r): ?>
              <tr>
                <td><strong><?= htmlspecialchars($r['nama'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                <td><code><?= htmlspecialchars($r['tabel'], ENT_QUOTES, 'UTF-8') ?></code></td>
                <td><a href="<?= htmlspecialchars($r['halaman'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($r['halaman'], ENT_QUOTES, 'UTF-8') ?></a></td>
                <td><?= htmlspecialchars($r['skala'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($r['konversi'], ENT_QUOTES, 'UTF-8') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>

    <?php foreach (['psikomotor', 'afektif'] as $kunci): $r = $rubrik[$kunci]; ?>
      <section class="card">
        <h2>Rubrik <?= htmlspecialchars($r['nama'], ENT_QUOTES, 'UTF-8') ?> (<?= count($r['indikator']) ?> indikator × skala 1–4 = 0–20)</h2>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th class="num">No</th>
                <th>Indikator Penilaian</th>
                <th>1</th><th>2</th><th>3</th><th>4</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($r['indikator'] as $i => $label): ?>
                <tr>
                  <td class="num"><?= $i + 1 ?></td>
                  <td><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></td>
                  <?php foreach ($r['deskriptor'] as $skor => $teks): ?>
                    <td><?= htmlspecialchars($teks, ENT_QUOTES, 'UTF-8') ?></td>
                  <?php endforeach; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <p class="lead" style="margin-top:10px;">Total skor 5–20 (0 = belum diisi) → nilai akhir = total × 5. Nilai guru (bila dikoreksi) menggantikan nilai rubrik pada rekap dan laporan.</p>
      </section>
    <?php endforeach; ?>

    <section class="card">
      <h2>Rubrik Penilaian Diri/Rekan (5 indikator × skala 1–4 = 0–20)</h2>
      <ul class="daftar">
        <?php foreach ($rubrik['diri']['indikator'] as $label): ?>
          <li><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></li>
        <?php endforeach; ?>
      </ul>
      <p class="lead" style="margin-top:10px;">Diisi siswa pada <a href="penilaian-rekan.php">penilaian-rekan.php</a>, disimpan di <code>penilaian_rekan.nilai_rubrik</code> (0–20) lalu dikonversi ×5 pada rekap/laporan.</p>
    </section>

    <section class="card">
      <h2>Rubrik Kognitif (2 butir × 50 poin = 0–100)</h2>
      <div class="table-wrap">
        <table>
          <thead><tr><th class="num">No</th><th>Butir</th><th class="num">Bobot</th></tr></thead>
          <tbody>
            <?php foreach ($rubrik['kognitif']['butir'] as $i => $b): ?>
              <tr><td class="num"><?= $i + 1 ?></td><td><?= htmlspecialchars($b['label'], ENT_QUOTES, 'UTF-8') ?></td><td class="num"><?= (int)$b['bobot'] ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <p class="lead" style="margin-top:10px;">Nilai kognitif langsung 0–100 (tidak dikonversi), disimpan pada <code>penilaian_kognitif.nilai_total</code>.</p>
    </section>

    <section class="card">
      <h2>Konversi Nilai &amp; Predikat</h2>
      <p class="lead">Ambang tuntas: <strong>≥<?= PADI_TUNTAS ?></strong>. Predikat: ≥85 Sangat Baik · 70–84 Baik · 50–69 Cukup · &lt;50 Kurang.</p>
      <div class="table-wrap" style="margin-top:10px;">
        <table>
          <thead><tr><th class="num">Nilai rubrik</th><th class="num">Nilai 0–100</th><th>Predikat</th><th>Status</th></tr></thead>
          <tbody>
            <?php foreach ([20, 19, 18, 17, 16, 15, 14, 13, 12, 10, 8, 5, 0] as $n20): $n100 = $konversi[$n20]; $p = padi_rubrik_predikat($n100); ?>
              <tr>
                <td class="num"><?= $n20 ?></td>
                <td class="num"><?= $n100 ?></td>
                <td><?= htmlspecialchars($p[0], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= ($n100 >= PADI_TUNTAS) ? '<span class="badge ok">Tuntas</span>' : '<span class="badge err">Belum tuntas</span>' ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>

    <section class="card">
      <h2>Cek Data pada Sistem</h2>
      <p class="lead">Rentang nilai yang benar-benar tersimpan — memastikan rubrik ini sesuai dengan data yang berjalan.</p>
      <div class="table-wrap" style="margin-top:10px;">
        <table>
          <thead><tr><th>Instrumen</th><th class="num">Jumlah data</th><th class="num">Nilai minimum</th><th class="num">Nilai maksimum</th><th>Kesesuaian</th></tr></thead>
          <tbody>
            <?php foreach ($cek as $kunci => $c):
              $batas = in_array($kunci, ['kognitif'], true) ? 100 : 20;
              $sesuai = ($c['min'] === null) || ($c['min'] >= 0 && $c['max'] <= $batas);
            ?>
              <tr>
                <td><?= htmlspecialchars($rubrik[$kunci]['nama'], ENT_QUOTES, 'UTF-8') ?></td>
                <td class="num"><?= (int)$c['isi'] ?></td>
                <td class="num"><?= $c['min'] === null ? '–' : htmlspecialchars((string)round($c['min'], 2), ENT_QUOTES, 'UTF-8') ?></td>
                <td class="num"><?= $c['max'] === null ? '–' : htmlspecialchars((string)round($c['max'], 2), ENT_QUOTES, 'UTF-8') ?></td>
                <td>
                  <?php if ($c['isi'] === 0): ?>
                    <span class="badge">Belum ada data</span>
                  <?php elseif ($sesuai): ?>
                    <span class="badge ok">Sesuai (0–<?= $batas ?>)</span>
                  <?php else: ?>
                    <span class="badge err">Di luar rentang</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="aksi">
        <a class="btn btn-ringan" href="penilaian-kognitif.php">Input Kognitif</a>
        <a class="btn btn-ringan" href="penilaian-psikomotor.php">Input Psikomotor</a>
        <a class="btn btn-ringan" href="penilaian-afektif.php">Input Afektif</a>
        <a class="btn btn-ringan" href="penilaian-rekan.php">Input Diri/Rekan</a>
        <a class="btn btn-ringan" href="rekap-penilaian.php">Rekap Nilai</a>
        <a class="btn btn-ringan" href="laporan.php">Laporan/Raport</a>
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
    <a href="profil-guru.php" class="nav-item" id="nav-profil" aria-label="Profil">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
      <span>Profil</span>
    </a>
  </nav>
<?php padi_cetak_ttd(); ?>
</body>
</html>
