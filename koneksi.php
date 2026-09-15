<?php
// koneksi.php
// Konfigurasi koneksi database.
// Prioritas: environment variable (produksi) → file .env → default XAMPP (lokal).

/**
 * Baca file .env sederhana (KEY=VALUE per baris).
 * Tidak menimpa environment variable yang sudah ada.
 */
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

padi_muat_env(__DIR__ . '/.env');

$host = getenv('DB_HOST') ?: 'localhost';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
$db   = getenv('DB_NAME') ?: 'padi_pjok';
$port = (int)(getenv('DB_PORT') ?: 3306);

// Koneksi awal tanpa database untuk setup jika database belum ada
$conn_setup = new mysqli($host, $user, $pass, null, $port);
if ($conn_setup->connect_error) {
    die("Connection failed: " . $conn_setup->connect_error);
}

try {
    $conn = new mysqli($host, $user, $pass, $db, $port);
} catch (mysqli_sql_exception $e) {
    $conn = null; // DB might not exist yet
}

// Set timezone
date_default_timezone_set(getenv('APP_TZ') ?: 'Asia/Jakarta');
?>
