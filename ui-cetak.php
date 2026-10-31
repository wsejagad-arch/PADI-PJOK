<?php
// ui-cetak.php — kop surat + tanda tangan + tombol cetak untuk semua halaman data.
// Pakai: padi_cetak_siap('JUDUL DOKUMEN', 'subjudul', ['orientasi' => 'landscape']);
//       ... isi halaman ...
//       padi_cetak_ttd();
// Identitas sekolah diambil dari .env (SEKOLAH_*), guru dari sesi login.

if (!function_exists('padi_cetak_env')) {
function padi_cetak_env($kunci, $bawaan = '')
{
    $nilai = getenv($kunci);
    if ($nilai === false || trim((string)$nilai) === '') {
        $nilai = $_ENV[$kunci] ?? ($_SERVER[$kunci] ?? '');
    }
    $nilai = trim((string)$nilai);
    return $nilai !== '' ? $nilai : $bawaan;
}
}

if (!function_exists('padi_cetak_base')) {
function padi_cetak_base()
{
    if (function_exists('padi_ui_base')) return padi_ui_base();
    $script = (string)($_SERVER['SCRIPT_NAME'] ?? '/index.php');
    $dir = str_replace('\\', '/', dirname($script));
    return ($dir === '/' || $dir === '.') ? '' : rtrim($dir, '/');
}
}

if (!function_exists('padi_cetak_siap')) {
function padi_cetak_siap($judul, $subjudul = '', $opsi = [])
{
    $base = htmlspecialchars(padi_cetak_base(), ENT_QUOTES, 'UTF-8');
    $v = @filemtime(__DIR__ . '/aset/cetak.css') ?: 1;
    $orientasi = strtolower((string)($opsi['orientasi'] ?? 'portrait')) === 'landscape' ? 'landscape' : 'portrait';

    $namaSekolah = padi_cetak_env('SEKOLAH_NAMA', 'NAMA SEKOLAH');
    $instansi    = padi_cetak_env('SEKOLAH_INSTANSI', 'PEMERINTAH DAERAH');
    $alamat      = padi_cetak_env('SEKOLAH_ALAMAT', 'Alamat sekolah — isi SEKOLAH_ALAMAT di .env');
    $npsn        = padi_cetak_env('SEKOLAH_NPSN', '');
    $tahun       = padi_cetak_env('TAHUN_PELAJARAN', '');

    $barisAlamat = $alamat;
    if ($npsn !== '') $barisAlamat .= ' • NPSN ' . $npsn;
    if ($tahun !== '') $barisAlamat .= ' • Tahun Pelajaran ' . $tahun;

    echo '<link rel="stylesheet" href="' . $base . '/aset/cetak.css?v=' . $v . '">' . "\n";
    echo '<style>@page{size:A4 ' . $orientasi . ';margin:14mm 14mm 16mm}</style>' . "\n";

    echo '<div class="cetak-toolbar" role="toolbar" aria-label="Aksi cetak">'
        . '<button type="button" onclick="window.print()" title="Cetak / Simpan PDF" aria-label="Cetak atau simpan PDF">'
        . '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 9V3h12v6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M6 18H4a2 2 0 01-2-2v-4a2 2 0 012-2h16a2 2 0 012 2v4a2 2 0 01-2 2h-2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><rect x="6" y="14" width="12" height="7" rx="1" stroke="currentColor" stroke-width="2"/></svg>'
        . '<span>Cetak / Simpan PDF</span>'
        . '</button>'
        . '</div>' . "\n";

    echo '<header class="cetak-kop">' . "\n";
    echo '  <img class="cetak-logo" src="' . $base . '/aset/icon-192.png" alt="Logo sekolah">' . "\n";
    echo '  <div class="cetak-identitas">' . "\n";
    echo '    <p class="cetak-instansi">' . htmlspecialchars($instansi, ENT_QUOTES, 'UTF-8') . '</p>' . "\n";
    echo '    <h1 class="cetak-sekolah">' . htmlspecialchars($namaSekolah, ENT_QUOTES, 'UTF-8') . '</h1>' . "\n";
    echo '    <p class="cetak-alamat">' . htmlspecialchars($barisAlamat, ENT_QUOTES, 'UTF-8') . '</p>' . "\n";
    echo '  </div>' . "\n";
    echo '</header>' . "\n";
    echo '<div class="cetak-garis-ganda"></div>' . "\n";
    echo '<div class="cetak-kepala">' . "\n";
    echo '  <h2 class="cetak-judul">' . htmlspecialchars($judul, ENT_QUOTES, 'UTF-8') . '</h2>' . "\n";
    if ($subjudul !== '') {
        echo '  <p class="cetak-sub">' . htmlspecialchars($subjudul, ENT_QUOTES, 'UTF-8') . '</p>' . "\n";
    }
    echo '</div>' . "\n";
}
}

/** Identitas guru yang sedang login (nama/nip): sesi → DB → env. */
if (!function_exists('padi_cetak_identitas_guru')) {
function padi_cetak_identitas_guru($kolom)
{
    $kolom = ($kolom === 'nip') ? 'nip' : 'nama';
    $nilai = trim((string)($_SESSION['guru_' . $kolom] ?? ''));
    $id = (int)($_SESSION['guru_id'] ?? 0);
    $koneksi = $GLOBALS['conn'] ?? null;

    if ($nilai === '' && $id > 0 && $koneksi instanceof mysqli) {
        $stmt = $koneksi->prepare("SELECT nama, nip FROM guru WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $res = $stmt->get_result();
            $baris = $res ? $res->fetch_assoc() : null;
            $stmt->close();
            if ($baris) {
                $_SESSION['guru_nama'] = (string)($baris['nama'] ?? '');
                $_SESSION['guru_nip']  = (string)($baris['nip'] ?? '');
                $nilai = trim((string)($_SESSION['guru_' . $kolom] ?? ''));
            }
        }
    }

    if ($nilai === '') {
        $nilai = padi_cetak_env($kolom === 'nip' ? 'GURU_NIP' : 'GURU_NAMA', '');
    }
    return $nilai;
}
}

if (!function_exists('padi_cetak_ttd')) {
function padi_cetak_ttd()
{
    $guru = padi_cetak_identitas_guru('nama');
    if ($guru === '') $guru = '________________';

    $kepala = padi_cetak_env('KEPALA_SEKOLAH', '________________');
    $kota   = padi_cetak_env('SEKOLAH_KOTA', '');
    $nipGuru = padi_cetak_identitas_guru('nip');
    $nipKepala = padi_cetak_env('KEPALA_SEKOLAH_NIP', '');

    $hari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    $bulan = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $stempel = ($kota !== '' ? $kota . ', ' : '') . date('j') . ' ' . $bulan[(int)date('n')] . ' ' . date('Y');

    echo '<div class="cetak-ttd">' . "\n";
    echo '  <div class="cetak-ttd-kolom">' . "\n";
    echo '    <p>Mengetahui,</p><p>Kepala Sekolah</p>' . "\n";
    echo '    <div class="cetak-ruang-ttd"></div>' . "\n";
    echo '    <p class="cetak-nama-ttd">' . htmlspecialchars($kepala, ENT_QUOTES, 'UTF-8') . '</p>' . "\n";
    echo '    <p>NIP. ' . htmlspecialchars($nipKepala !== '' ? $nipKepala : '..............................', ENT_QUOTES, 'UTF-8') . '</p>' . "\n";
    echo '  </div>' . "\n";
    echo '  <div class="cetak-ttd-kolom">' . "\n";
    echo '    <p class="cetak-tanggal">' . htmlspecialchars($stempel, ENT_QUOTES, 'UTF-8') . '</p>' . "\n";
    echo '    <p>Guru Mata Pelajaran PJOK</p>' . "\n";
    echo '    <div class="cetak-ruang-ttd"></div>' . "\n";
    echo '    <p class="cetak-nama-ttd">' . htmlspecialchars($guru, ENT_QUOTES, 'UTF-8') . '</p>' . "\n";
    echo '    <p>NIP. ' . htmlspecialchars($nipGuru !== '' ? $nipGuru : '..............................', ENT_QUOTES, 'UTF-8') . '</p>' . "\n";
    echo '  </div>' . "\n";
    echo '</div>' . "\n";
    echo '<p class="cetak-catatan">Dokumen ini dihasilkan otomatis oleh PADI-PJOK pada ' . date('d/m/Y H:i') . '.</p>' . "\n";
}
}
