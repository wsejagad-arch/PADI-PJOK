<?php
require_once 'auth.php';
wajibLoginGuru();
require 'koneksi.php';

/**
 * Laporan / raport pembelajaran PJOK.
 * Analisis "AI ringan" = aturan kriteria tetap (tanpa layanan eksternal):
 *   ≥85 Sangat Baik · 70–84 Baik · 50–69 Cukup · <50 Kurang · tuntas ≥70.
 */

// ---------- Kriteria ----------
if (!function_exists('laporan_predikat')) {
function laporan_predikat($nilai)
{
    if ($nilai === null) return ['Belum ada data', 'abu'];
    if ($nilai >= 85) return ['Sangat Baik', 'green'];
    if ($nilai >= 70) return ['Baik', 'blue'];
    if ($nilai >= 50) return ['Cukup', 'orange'];
    return ['Kurang', 'red'];
}
}

if (!function_exists('laporan_angka')) {
function laporan_angka($nilai)
{
    return ($nilai === null) ? '–' : (string)round($nilai);
}
}

/** Narasi individu berbasis kriteria + rekomendasi tindak lanjut. */
if (!function_exists('laporan_narasi_individu')) {
function laporan_narasi_individu($nama, array $nilai, $rata)
{
    $label = ['kog' => 'Kognitif', 'psi' => 'Psikomotor', 'afe' => 'Afektif', 'diri' => 'Penilaian diri/rekan'];
    $isi = [];
    $kosong = [];
    foreach ($label as $k => $teks) {
        if ($nilai[$k] === null) $kosong[] = $teks;
        else $isi[$k] = (float)$nilai[$k];
    }

    if (!$isi) {
        return $nama . ' belum memiliki data penilaian pada sesi ini. Prioritaskan menyelesaikan tes kognitif dan mengunggah video praktik agar raport dapat disusun.';
    }

    arsort($isi);
    $kuat = [];
    $lemah = [];
    foreach ($isi as $k => $v) {
        if ($v >= 85) $kuat[] = $label[$k];
        if ($v < 70) $lemah[] = $label[$k];
    }

    $narasi = $nama . ' memperoleh rata-rata ' . round($rata) . ' (' . laporan_predikat($rata)[0] . ') dari ' . count($isi) . ' indikator yang terisi.';
    if ($kuat) {
        $narasi .= ' Kekuatan utama pada ' . implode(' dan ', $kuat) . ' yang sudah berada pada rentang sangat baik.';
    } else {
        $tertinggi = array_key_first($isi);
        $narasi .= ' Capaian terbaik ada pada ' . $label[$tertinggi] . ' (' . round($isi[$tertinggi]) . ').';
    }
    if ($lemah) {
        $narasi .= ' Indikator yang perlu perbaikan: ' . implode(' dan ', $lemah) . ' (di bawah 70).';
    } else {
        $narasi .= ' Seluruh indikator yang terisi sudah mencapai batas tuntas 70.';
    }
    if ($kosong) {
        $narasi .= ' Data yang belum terisi: ' . implode(', ', $kosong) . ', sehingga perlu dilengkapi sebelum raport final.';
    }
    return $narasi;
}
}

/** Rekomendasi tindak lanjut per indikator lemah/kosong. */
if (!function_exists('laporan_rekomendasi')) {
function laporan_rekomendasi(array $nilai)
{
    $saran = [];
    if ($nilai['kog'] === null) {
        $saran[] = 'Jadwalkan tes kognitif ulang (materi yang sama) untuk mengisi nilai.';
    } elseif ($nilai['kog'] < 70) {
        $saran[] = 'Remedial kognitif: penguatan konsep (passing/servis) lalu kuis ulang.';
    }

    if ($nilai['video'] === '') {
        $saran[] = 'Minta siswa mengunggah video praktik agar psikomotor dapat dinilai.';
    } elseif ($nilai['psi'] !== null && $nilai['psi'] < 70) {
        $saran[] = 'Latihan teknik bertahap (drill 10–15 menit) + umpan balik video.';
    }

    if ($nilai['afe'] === null) {
        $saran[] = 'Isi penilaian afektif (disiplin, kerja sama, tanggung jawab) pada sesi berikutnya.';
    } elseif ($nilai['afe'] < 70) {
        $saran[] = 'Pembiasaan sikap: kesepakatan kelas, jurnal refleksi, dan apresiasi perilaku positif.';
    }

    if ($nilai['diri'] === null) {
        $saran[] = 'Arahkan siswa mengisi penilaian diri/rekan sebelum sesi ditutup.';
    } elseif ($nilai['diri'] < 70) {
        $saran[] = 'Latih refleksi diri dan saling umpan balik antarteman secara terstruktur.';
    }

    if (!$saran) {
        $saran[] = 'Pertahankan capaian; berikan tugas pengayaan (variasi teknik/taktik) dan peran sebagai tutor sebaya.';
    }
    return $saran;
}
}

if (!function_exists('laporan_narasi_kelas')) {
function laporan_narasi_kelas($jumlah, array $rata, $tuntas, $predikatSehat)
{
    if ($jumlah === 0) {
        return 'Belum ada siswa pada sesi kelas ini sehingga analisis belum dapat dilakukan.';
    }
    $bagian = [];
    $bagian[] = 'Kelas diikuti ' . $jumlah . ' siswa dengan ' . $tuntas . ' siswa (' . round($jumlah ? $tuntas / $jumlah * 100 : 0) . '%) mencapai batas tuntas 70.';

    $terisi = [];
    $namaIndikator = ['kog' => 'Kognitif', 'afe' => 'Afektif', 'psi' => 'Psikomotor', 'diri' => 'Diri/rekan'];
    foreach ($namaIndikator as $k => $teks) {
        if ($rata[$k] !== null) $terisi[$k] = $rata[$k];
    }
    if ($terisi) {
        arsort($terisi);
        $kuat = array_key_first($terisi);
        $lemah = array_key_last($terisi);
        $bagian[] = 'Rata-rata terbaik pada ' . $namaIndikator[$kuat] . ' (' . round($terisi[$kuat]) . ') dan terendah pada ' . $namaIndikator[$lemah] . ' (' . round($terisi[$lemah]) . ').';
        if ($lemah === 'kog') $bagian[] = 'Prioritas tindak lanjut: penguatan konsep dan kuis ulang.';
        elseif ($lemah === 'psi') $bagian[] = 'Prioritas tindak lanjut: latihan teknik dan pemeriksaan video praktik.';
        elseif ($lemah === 'afe') $bagian[] = 'Prioritas tindak lanjut: pembiasaan sikap dan kesepakatan kelas.';
        else $bagian[] = 'Prioritas tindak lanjut: pembiasaan refleksi diri dan umpan balik antarteman.';
    }
    if ($predikatSehat > 0) {
        $bagian[] = 'Sebanyak ' . $predikatSehat . ' siswa berada pada predikat Baik/Sangat Baik; pertimbangkan pengayaan bagi capaian ≥85.';
    }
    return implode(' ', $bagian);
}
}

// ---------- Filter ----------
$filterKelas = strtoupper(trim((string)($_GET['kelas'] ?? '')));
$idSiswa = (int)($_GET['siswa'] ?? 0);

// Daftar kelas
$sidLaporan = $conn ? padi_sekolah_id() : 1;
$kelasList = [];
if ($conn) {
    $stmt = $conn->prepare("SELECT DISTINCT kelas FROM sesi WHERE sekolah_id = ? AND kelas <> '' ORDER BY kelas");
    $stmt->bind_param('i', $sidLaporan);
    $stmt->execute();
    $q = $stmt->get_result();
    if ($q) while ($r = $q->fetch_assoc()) $kelasList[] = (string)$r['kelas'];
    $stmt->close();

    $stmt = $conn->prepare("SELECT nama_kelas FROM master_kelas WHERE sekolah_id = ? ORDER BY nama_kelas");
    $stmt->bind_param('i', $sidLaporan);
    $stmt->execute();
    $q = $stmt->get_result();
    if ($q) while ($r = $q->fetch_assoc()) $kelasList[] = (string)$r['nama_kelas'];
    $stmt->close();
    $kelasList = array_values(array_unique(array_filter($kelasList)));
    sort($kelasList);
}
if ($filterKelas === '' && $kelasList) {
    // Default: kelas dari sesi terbaru (biasanya sudah ada siswa), lalu kelas pertama.
    $filterKelas = $kelasList[0];
    $stmt = $conn->prepare("SELECT kelas FROM sesi WHERE sekolah_id = ? AND kelas <> '' ORDER BY id DESC LIMIT 1");
    $stmt->bind_param('i', $sidLaporan);
    $stmt->execute();
    $qBaru = $stmt->get_result();
    if ($qBaru && ($rBaru = $qBaru->fetch_assoc()) && trim((string)$rBaru['kelas']) !== '') {
        $filterKelas = strtoupper((string)$rBaru['kelas']);
    }
    $stmt->close();
}

// ---------- Data kelas ----------
$siswa = [];
if ($conn && $filterKelas !== '') {
    // Utamakan sesi aktif; bila tidak ada, pakai sesi terbaru kelas tersebut.
    $idSesi = [];
    $stmt = $conn->prepare("SELECT id FROM sesi WHERE sekolah_id = ? AND kelas = ? AND status = 'aktif' ORDER BY id DESC");
    $stmt->bind_param('is', $sidLaporan, $filterKelas);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($res && ($r = $res->fetch_assoc())) $idSesi[] = (int)$r['id'];
    $stmt->close();
    if (!$idSesi) {
        $stmt = $conn->prepare("SELECT id FROM sesi WHERE sekolah_id = ? AND kelas = ? ORDER BY id DESC LIMIT 1");
        $stmt->bind_param('is', $sidLaporan, $filterKelas);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && ($r = $res->fetch_assoc())) $idSesi[] = (int)$r['id'];
        $stmt->close();
    }

    if ($idSesi) {
        $in = implode(',', array_map('intval', $idSesi));
        $q = $conn->query("SELECT p.id, p.nama, p.created_at, s.token, s.materi
                           FROM siswa p JOIN sesi s ON s.id = p.sesi_id
                           WHERE p.sesi_id IN ($in)
                           ORDER BY p.nama ASC");
        if ($q) {
            while ($row = $q->fetch_assoc()) {
                $pid = (int)$row['id'];
                $kog = $conn->query("SELECT nilai_total FROM penilaian_kognitif WHERE siswa_id = $pid ORDER BY id DESC LIMIT 1");
                $row['kog'] = ($kog && ($x = $kog->fetch_assoc())) ? (float)$x['nilai_total'] : null;

                $psi = $conn->query("SELECT COALESCE(nilai_guru, nilai_rubrik) AS n, video_path FROM penilaian_psikomotor WHERE siswa_id = $pid ORDER BY id DESC LIMIT 1");
                if ($psi && ($x = $psi->fetch_assoc())) {
                    $row['psi'] = ($x['n'] !== null) ? (float)$x['n'] * 5 : null;
                    $row['video'] = trim((string)$x['video_path']);
                } else {
                    $row['psi'] = null;
                    $row['video'] = '';
                }

                $afe = $conn->query("SELECT COALESCE(nilai_guru, nilai_rubrik) AS n FROM penilaian_afektif WHERE siswa_id = $pid ORDER BY id DESC LIMIT 1");
                $row['afe'] = ($afe && ($x = $afe->fetch_assoc()) && $x['n'] !== null) ? (float)$x['n'] * 5 : null;

                $diri = $conn->query("SELECT nilai_rubrik FROM penilaian_rekan WHERE penilai_id = $pid ORDER BY id DESC LIMIT 1");
                $row['diri'] = ($diri && ($x = $diri->fetch_assoc())) ? (float)$x['nilai_rubrik'] * 5 : null;

                $angka = [];
                foreach (['kog', 'psi', 'afe', 'diri'] as $k) {
                    if ($row[$k] !== null) $angka[] = $row[$k];
                }
                $row['rata'] = $angka ? array_sum($angka) / count($angka) : null;
                $row['jumlah_data'] = count($angka);
                $siswa[] = $row;
            }
        }
    }
}

// ---------- Rekap kelas ----------
$rataKelas = ['kog' => null, 'psi' => null, 'afe' => null, 'diri' => null];
$sebaran = ['Sangat Baik' => 0, 'Baik' => 0, 'Cukup' => 0, 'Kurang' => 0, 'Belum ada data' => 0];
$tuntas = 0;
$remedial = [];
$pengayaan = [];
$belumLengkap = [];
$hitung = ['kog' => [], 'psi' => [], 'afe' => [], 'diri' => []];

foreach ($siswa as $s) {
    foreach ($hitung as $k => $arr) {
        if ($s[$k] !== null) $hitung[$k][] = $s[$k];
    }
    $sebaran[laporan_predikat($s['rata'])[0]]++;
    if ($s['rata'] !== null && $s['rata'] >= 70) $tuntas++;
    if ($s['rata'] !== null && $s['rata'] < 70) $remedial[] = $s;
    if ($s['rata'] !== null && $s['rata'] >= 85) $pengayaan[] = $s;
    if ($s['jumlah_data'] < 4) $belumLengkap[] = $s;
}
foreach ($hitung as $k => $arr) {
    $rataKelas[$k] = $arr ? array_sum($arr) / count($arr) : null;
}
$predikatSehat = $sebaran['Sangat Baik'] + $sebaran['Baik'];

// ---------- Ekspor CSV ----------
if (strtolower((string)($_GET['ekspor'] ?? '')) === 'csv' && $filterKelas !== '') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="raport-pjok-' . preg_replace('/[^A-Za-z0-9]/', '', $filterKelas) . '-' . date('Ymd-His') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Nama', 'Kelas', 'Kognitif', 'Psikomotor', 'Afektif', 'Diri/Rekan', 'Rata-rata', 'Predikat', 'Tuntas', 'Rekomendasi'], ';');
    foreach ($siswa as $s) {
        $pred = laporan_predikat($s['rata'])[0];
        fputcsv($out, [
            $s['nama'], $filterKelas,
            laporan_angka($s['kog']), laporan_angka($s['psi']), laporan_angka($s['afe']), laporan_angka($s['diri']),
            laporan_angka($s['rata']), $pred,
            ($s['rata'] !== null && $s['rata'] >= 70) ? 'Tuntas' : 'Belum',
            implode(' ', array_slice(laporan_rekomendasi($s), 0, 2)),
        ], ';');
    }
    fclose($out);
    exit;
}

// ---------- Individu terpilih ----------
$individu = null;
foreach ($siswa as $s) {
    if ($idSiswa > 0 && (int)$s['id'] === $idSiswa) $individu = $s;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Laporan Pembelajaran – PADI-PJOK</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --blue: #1A56DB; --blue-light: #EFF4FF; --text: #111827; --text-3: #6B7280; --border: #E5E7EB;
      --bg: #F3F6FB; --white: #FFFFFF; --radius: 16px; --radius-sm: 10px;
      --green: #16A34A; --green-light: #DCFCE7; --orange: #B45309; --orange-light: #FEF3C7;
      --red: #DC2626; --red-light: #FEE2E2; --slate-light: #F1F5F9;
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

    .filter { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 10px; align-items: end; margin-top: 12px; }
    .filter label { display: block; font-size: 11px; font-weight: 700; color: var(--text-3); margin-bottom: 4px; }
    .filter select, .filter input { width: 100%; font: inherit; font-size: 13px; padding: 9px 10px; border-radius: var(--radius-sm); background: #fff; }
    .filter-aksi { display: flex; gap: 8px; flex-wrap: wrap; }
    .btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; height: 38px; padding: 0 14px; border-radius: var(--radius-sm); font: inherit; font-size: 12.5px; font-weight: 700; cursor: pointer; background: var(--blue); color: #fff; text-decoration: none; border: 0; }
    .btn-ringan { background: #fff; color: var(--blue); border: 1px solid var(--border); }

    .ringkas { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; margin-top: 14px; }
    @media (min-width: 560px) { .ringkas { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
    .ringkas-item { border-radius: var(--radius-sm); padding: 10px; text-align: center; background: var(--blue-light); }
    .ringkas-item.green { background: var(--green-light); }
    .ringkas-item.orange { background: var(--orange-light); }
    .ringkas-item.slate { background: var(--slate-light); }
    .ringkas-num { display: block; font-size: 19px; font-weight: 800; }
    .ringkas-label { font-size: 10.5px; font-weight: 600; color: var(--text-3); }

    .ai-box { border-left: 4px solid var(--blue); background: var(--blue-light); border-radius: 8px; padding: 12px 14px; margin-top: 12px; }
    .ai-box p { font-size: 12.5px; line-height: 1.6; }
    .ai-tag { display: inline-block; font-size: 10px; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; color: var(--blue); margin-bottom: 4px; }

    .table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
    table { width: 100%; border-collapse: collapse; font-size: 12.5px; min-width: 760px; }
    th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid var(--border); }
    th { font-size: 10.5px; text-transform: uppercase; letter-spacing: .04em; color: var(--text-3); background: #F8FAFC; white-space: nowrap; }
    tbody tr:last-child td { border-bottom: 0; }
    th.num, td.num { text-align: right; font-variant-numeric: tabular-nums; }
    .n.green { color: #166534; font-weight: 700; }
    .n.blue { color: var(--blue); font-weight: 700; }
    .n.orange { color: var(--orange); font-weight: 700; }
    .n.red { color: var(--red); font-weight: 700; }
    .n.abu { color: var(--text-3); font-weight: 700; }
    .badge { display: inline-block; font-size: 10.5px; font-weight: 700; padding: 2px 8px; border-radius: 999px; background: var(--slate-light); color: var(--text-3); }
    .badge.tuntas { background: var(--green-light); color: #166534; }
    .badge.belum { background: var(--red-light); color: #991B1B; }

    .daftar { list-style: none; display: flex; flex-direction: column; gap: 8px; }
    .daftar li { font-size: 12.5px; line-height: 1.5; padding-left: 18px; position: relative; }
    .daftar li::before { content: "•"; position: absolute; left: 4px; color: var(--blue); font-weight: 800; }

    .raport-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; margin: 12px 0; }
    .raport-item { border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 10px; }
    .raport-item span { display: block; font-size: 10.5px; font-weight: 700; color: var(--text-3); text-transform: uppercase; letter-spacing: .03em; }
    .raport-item strong { font-size: 18px; }
    .kosong { text-align: center; color: var(--text-3); font-size: 12.5px; padding: 22px 8px; }
    .nav-item { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px; color: #9CA3AF; text-decoration: none; height: 100%; }
    .nav-item svg { width: 22px; height: 22px; }
    .nav-item span { font-size: 10.5px; font-weight: 600; }
    .nav-item.active { color: var(--blue); }
  </style>
<?php require_once __DIR__ . '/ui-head.php'; padi_ui_head(); ?>
</head>
<body>
<?php require_once __DIR__ . '/ui-cetak.php'; padi_cetak_siap('LAPORAN PEMBELAJARAN PJOK', 'Kelas ' . ($filterKelas !== '' ? $filterKelas : '-') . ($individu ? ' • ' . $individu['nama'] : ''), ['orientasi' => 'landscape']); ?>

  <header class="topbar">
    <button class="back-btn" type="button" aria-label="Kembali" onclick="window.location.href='dashboard-guru.php'">
      <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </button>
    <div class="topbar-title">Laporan</div>
  </header>

  <main class="content">
    <section class="card">
      <h1>Raport Pembelajaran PJOK</h1>
      <p class="lead">Ringkasan kelas dan raport individu, dilengkapi analisis AI ringan berbasis kriteria tetap (tanpa layanan eksternal) sehingga siap dipakai untuk tindak lanjut.</p>
      <form method="get" action="laporan.php" class="filter">
        <div>
          <label for="f-kelas">Kelas</label>
          <select id="f-kelas" name="kelas" onchange="this.form.submit()">
            <?php if (!$kelasList): ?><option value="">(belum ada kelas)</option><?php endif; ?>
            <?php foreach ($kelasList as $k): ?>
              <option value="<?= htmlspecialchars($k, ENT_QUOTES, 'UTF-8') ?>" <?= (strcasecmp($filterKelas, $k) === 0) ? 'selected' : '' ?>><?= htmlspecialchars($k, ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label for="f-siswa">Raport individu (opsional)</label>
          <select id="f-siswa" name="siswa" onchange="this.form.submit()">
            <option value="0">Ringkasan kelas saja</option>
            <?php foreach ($siswa as $s): ?>
              <option value="<?= (int)$s['id'] ?>" <?= ($idSiswa === (int)$s['id']) ? 'selected' : '' ?>><?= htmlspecialchars($s['nama'], ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="filter-aksi">
          <button class="btn btn-ringan" type="submit">Tampilkan</button>
          <a class="btn btn-ringan" href="laporan.php?kelas=<?= urlencode($filterKelas) ?>&ekspor=csv">Ekspor CSV</a>
        </div>
      </form>
    </section>

    <?php if ($individu): ?>
      <section class="card">
        <h2>Raport Individu — <?= htmlspecialchars($individu['nama'], ENT_QUOTES, 'UTF-8') ?></h2>
        <p class="lead">Kelas <?= htmlspecialchars($filterKelas, ENT_QUOTES, 'UTF-8') ?> • token <?= htmlspecialchars($individu['token'], ENT_QUOTES, 'UTF-8') ?> • materi <?= htmlspecialchars(ucwords(str_replace('-', ' ', (string)$individu['materi'])), ENT_QUOTES, 'UTF-8') ?></p>

        <div class="raport-grid">
          <div class="raport-item"><span>Kognitif</span><strong class="n <?= laporan_predikat($individu['kog'])[1] ?>"><?= laporan_angka($individu['kog']) ?></strong></div>
          <div class="raport-item"><span>Psikomotor</span><strong class="n <?= laporan_predikat($individu['psi'])[1] ?>"><?= laporan_angka($individu['psi']) ?></strong></div>
          <div class="raport-item"><span>Afektif</span><strong class="n <?= laporan_predikat($individu['afe'])[1] ?>"><?= laporan_angka($individu['afe']) ?></strong></div>
          <div class="raport-item"><span>Diri/Rekan</span><strong class="n <?= laporan_predikat($individu['diri'])[1] ?>"><?= laporan_angka($individu['diri']) ?></strong></div>
          <div class="raport-item"><span>Rata-rata</span><strong class="n <?= laporan_predikat($individu['rata'])[1] ?>"><?= laporan_angka($individu['rata']) ?></strong></div>
          <div class="raport-item"><span>Predikat</span><strong><?= htmlspecialchars(laporan_predikat($individu['rata'])[0], ENT_QUOTES, 'UTF-8') ?></strong></div>
          <div class="raport-item"><span>Status</span><strong><?= ($individu['rata'] !== null && $individu['rata'] >= 70) ? 'Tuntas' : 'Belum tuntas' ?></strong></div>
          <div class="raport-item"><span>Kelengkapan</span><strong><?= (int)$individu['jumlah_data'] ?>/4</strong></div>
        </div>

        <div class="ai-box">
          <span class="ai-tag">Analisis AI ringan</span>
          <p><?= htmlspecialchars(laporan_narasi_individu($individu['nama'], $individu, $individu['rata']), ENT_QUOTES, 'UTF-8') ?></p>
        </div>

        <h2 style="margin-top:16px;">Rekomendasi Tindak Lanjut</h2>
        <ul class="daftar">
          <?php foreach (laporan_rekomendasi($individu) as $saran): ?>
            <li><?= htmlspecialchars($saran, ENT_QUOTES, 'UTF-8') ?></li>
          <?php endforeach; ?>
        </ul>

        <div class="filter-aksi" style="margin-top:14px;">
          <a class="btn btn-ringan" href="rekap-penilaian-siswa.php?id=<?= (int)$individu['id'] ?>">Detail penilaian</a>
          <a class="btn btn-ringan" href="verifikasi-nilai.php">Verifikasi nilai</a>
          <a class="btn btn-ringan" href="laporan.php?kelas=<?= urlencode($filterKelas) ?>">Kembali ke ringkasan kelas</a>
        </div>
      </section>
    <?php endif; ?>

    <?php if ($filterKelas === ''): ?>
      <section class="card"><div class="kosong">Belum ada kelas/sesi. Buat token materi dan mulai sesi terlebih dahulu.</div></section>
    <?php else: ?>
      <section class="card">
        <h2>Ringkasan Kelas <?= htmlspecialchars($filterKelas, ENT_QUOTES, 'UTF-8') ?> (<?= count($siswa) ?> siswa)</h2>
        <div class="ringkas">
          <div class="ringkas-item"><span class="ringkas-num n <?= laporan_predikat($rataKelas['kog'])[1] ?>"><?= laporan_angka($rataKelas['kog']) ?></span><span class="ringkas-label">Rata Kognitif</span></div>
          <div class="ringkas-item green"><span class="ringkas-num n <?= laporan_predikat($rataKelas['psi'])[1] ?>"><?= laporan_angka($rataKelas['psi']) ?></span><span class="ringkas-label">Rata Psikomotor</span></div>
          <div class="ringkas-item orange"><span class="ringkas-num n <?= laporan_predikat($rataKelas['afe'])[1] ?>"><?= laporan_angka($rataKelas['afe']) ?></span><span class="ringkas-label">Rata Afektif</span></div>
          <div class="ringkas-item slate"><span class="ringkas-num n <?= laporan_predikat($rataKelas['diri'])[1] ?>"><?= laporan_angka($rataKelas['diri']) ?></span><span class="ringkas-label">Rata Diri/Rekan</span></div>
        </div>

        <div class="ai-box">
          <span class="ai-tag">Analisis AI ringan — kelas</span>
          <p><?= htmlspecialchars(laporan_narasi_kelas(count($siswa), $rataKelas, $tuntas, $predikatSehat), ENT_QUOTES, 'UTF-8') ?></p>
        </div>

        <h2 style="margin-top:16px;">Rencana Tindak Lanjut</h2>
        <ul class="daftar">
          <li>Remedial (<?= count($remedial) ?> siswa): <?= $remedial ? htmlspecialchars(implode(', ', array_map(function ($s) { return $s['nama'] . ' (' . round((float)$s['rata']) . ')'; }, $remedial)), ENT_QUOTES, 'UTF-8') : 'tidak ada' ?>.</li>
          <li>Pengayaan (<?= count($pengayaan) ?> siswa): <?= $pengayaan ? htmlspecialchars(implode(', ', array_map(function ($s) { return $s['nama']; }, $pengayaan)), ENT_QUOTES, 'UTF-8') : 'belum ada, dorong capaian ≥85' ?>.</li>
          <li>Data belum lengkap (<?= count($belumLengkap) ?> siswa): <?= $belumLengkap ? htmlspecialchars(implode(', ', array_map(function ($s) { return $s['nama'] . ' (' . (int)$s['jumlah_data'] . '/4)'; }, $belumLengkap)), ENT_QUOTES, 'UTF-8') : 'semua indikator terisi' ?>.</li>
          <li>Sebaran predikat: Sangat Baik <?= $sebaran['Sangat Baik'] ?> · Baik <?= $sebaran['Baik'] ?> · Cukup <?= $sebaran['Cukup'] ?> · Kurang <?= $sebaran['Kurang'] ?> · Belum ada data <?= $sebaran['Belum ada data'] ?>.</li>
        </ul>
      </section>

      <section class="card">
        <h2>Daftar Nilai per Indikator</h2>
        <?php if (!$siswa): ?>
          <div class="kosong">Belum ada siswa pada sesi kelas ini.</div>
        <?php else: ?>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Nama</th>
                  <th class="num">Kognitif</th>
                  <th class="num">Psikomotor</th>
                  <th class="num">Afektif</th>
                  <th class="num">Diri/Rekan</th>
                  <th class="num">Rata-rata</th>
                  <th>Predikat</th>
                  <th>Status</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($siswa as $s): $pred = laporan_predikat($s['rata']); ?>
                  <tr>
                    <td><?= htmlspecialchars($s['nama'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="num"><span class="n <?= laporan_predikat($s['kog'])[1] ?>"><?= laporan_angka($s['kog']) ?></span></td>
                    <td class="num"><span class="n <?= laporan_predikat($s['psi'])[1] ?>"><?= laporan_angka($s['psi']) ?></span></td>
                    <td class="num"><span class="n <?= laporan_predikat($s['afe'])[1] ?>"><?= laporan_angka($s['afe']) ?></span></td>
                    <td class="num"><span class="n <?= laporan_predikat($s['diri'])[1] ?>"><?= laporan_angka($s['diri']) ?></span></td>
                    <td class="num"><span class="n <?= $pred[1] ?>"><?= laporan_angka($s['rata']) ?></span></td>
                    <td><span class="n <?= $pred[1] ?>"><?= htmlspecialchars($pred[0], ENT_QUOTES, 'UTF-8') ?></span></td>
                    <td><?php if ($s['rata'] === null): ?><span class="badge">Belum</span><?php elseif ($s['rata'] >= 70): ?><span class="badge tuntas">Tuntas</span><?php else: ?><span class="badge belum">Remedial</span><?php endif; ?></td>
                    <td><a class="btn btn-ringan" href="laporan.php?kelas=<?= urlencode($filterKelas) ?>&siswa=<?= (int)$s['id'] ?>">Raport</a></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </section>
    <?php endif; ?>
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
