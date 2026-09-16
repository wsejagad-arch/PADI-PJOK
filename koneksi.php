<?php
// koneksi.php
// Konfigurasi koneksi database.
// Prioritas: environment variable (produksi) → file .env → default XAMPP (lokal).
//
// PENTING: berkas ini dimuat dengan `require` (bukan `require_once`) di banyak
// halaman, dan sebagian halaman juga memuat `auth.php` yang memuat berkas ini.
// Tanpa penjaga di bawah, fungsi akan dideklarasikan ulang dan memicu
// "Fatal error: Cannot redeclare padi_muat_env()".
if (defined('PADI_KONEKSI_SELESAI')) {
    return;
}
define('PADI_KONEKSI_SELESAI', true);

// Jangan tampilkan galat PHP ke pengguna (halaman putih/500). Catat ke log saja.
@ini_set('display_errors', '0');
@ini_set('log_errors', '1');

/**
 * Pengalih lingkungan: .env untuk produksi, .env.local untuk lokal.
 * Jangan menimpanya di server, dan jangan menimpa nilai environment yang sudah ada.
 */
if (!function_exists('padi_env_file')) {
function padi_env_file()
{
    $kandidat = [__DIR__ . '/.env'];
    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    $lokal = ($host === '' || strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false);
    if ($lokal) {
        $kandidat[] = __DIR__ . '/.env.local';
        $kandidat[] = __DIR__ . '/.env';
    }
    foreach ($kandidat as $berkas) {
        if (is_readable($berkas)) return $berkas;
    }
    return '';
}
}

/**
 * Baca file .env sederhana (KEY=VALUE per baris).
 * Tidak menimpa environment variable yang sudah ada.
 */
if (!function_exists('padi_muat_env')) {
function padi_muat_env($path)
{
    if (!is_readable($path)) return;
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $baris) {
        $baris = trim($baris);
        if ($baris === '' || $baris[0] === '#' || strpos($baris, '=') === false) continue;
        list($k, $v) = explode('=', $baris, 2);
        $k = trim($k);
        $v = trim($v);
        // buang tanda kutip pembungkus
        if (strlen($v) > 1 && ($v[0] === '"' || $v[0] === "'") && substr($v, -1) === $v[0]) {
            $v = substr($v, 1, -1);
        }
        if (getenv($k) === false) {
            putenv("$k=$v");
            $_ENV[$k] = $v;
        }
    }
}
}

padi_muat_env(padi_env_file());

$host = getenv('DB_HOST') ?: 'localhost';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
$db   = getenv('DB_NAME') ?: 'padi_pjok';
$port = (int)(getenv('DB_PORT') ?: 3306);

// Status koneksi dipakai halaman untuk menampilkan pesan yang ramah.
$conn_setup = null;
$conn = null;
$padi_db_error = '';

/**
 * Koneksi tanpa database (untuk setup bila database belum dibuat).
 * JANGAN memakai die()/exception di sini: bila server DB sedang mati atau
 * alamatnya salah, pengguna akan melihat "halaman putih" atau HTTP 500 tanpa
 * petunjuk. Cukup dicatat, halaman tetap tampil dengan pesan yang jelas.
 */
if (!function_exists('padi_coba_koneksi')) {
function padi_coba_koneksi($host, $user, $pass, $db, $port)
{
    try {
        mysqli_report(MYSQLI_REPORT_OFF);
        return new mysqli($host, $user, $pass, $db, $port);
    } catch (Throwable $e) {
        if (function_exists('error_log')) {
            error_log('PADI: koneksi database gagal (' . ($db ?: 'tanpa db') . ') - ' . $e->getMessage());
        }
        return null;
    }
}
}

// Koneksi awal tanpa database untuk setup jika database belum ada
$conn_setup = padi_coba_koneksi($host, $user, $pass, null, $port);
if (!$conn_setup) {
    $padi_db_error = 'Server database tidak dapat dihubungi. Periksa apakah MySQL/MariaDB sedang berjalan.';
} elseif (!empty($conn_setup->connect_error)) {
    $padi_db_error = 'Server database menolak koneksi: ' . $conn_setup->connect_error;
}

// Koneksi ke database aplikasi. Bila gagal, halaman tetap tampil dengan pesan
// yang jelas (bukan halaman putih maupun HTTP 500).
$conn = padi_coba_koneksi($host, $user, $pass, $db, $port);
if (!$conn) {
    if ($padi_db_error === '') {
        $padi_db_error = 'Database "' . $db . '" tidak dapat dibuka. Jalankan setup_db.php atau periksa kredensial .env.';
    }
}

// Set timezone
date_default_timezone_set(getenv('APP_TZ') ?: 'Asia/Jakarta');
?>

