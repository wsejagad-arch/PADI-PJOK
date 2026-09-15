<?php
// koneksi.php
$host = "localhost";
$user = "root"; // Default XAMPP user
$pass = "";     // Default XAMPP password is empty
$db   = "padi_pjok";

// Koneksi awal tanpa database untuk setup jika database belum ada
$conn_setup = new mysqli($host, $user, $pass);
if ($conn_setup->connect_error) {
    die("Connection failed: " . $conn_setup->connect_error);
}

try {
    $conn = new mysqli($host, $user, $pass, $db);
} catch (mysqli_sql_exception $e) {
    $conn = null; // DB might not exist yet
}

// Set timezone
date_default_timezone_set('Asia/Jakarta');
?>
