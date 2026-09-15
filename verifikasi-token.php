<?php
// verifikasi-token.php — Menampilkan token sesi aktif siswa setelah verifikasi password.
session_start();
require_once 'koneksi.php';
require_once 'auth.php';

header('Content-Type: application/json');

if (empty($_SESSION['siswa_id']) || empty($_SESSION['master_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesi berakhir. Silakan login ulang.']);
    exit;
}

$password = $_POST['password'] ?? '';
$res = verifikasiPasswordSiswa($conn, (int)$_SESSION['master_id'], $password);

echo json_encode([
    'success' => $res['success'],
    'message' => $res['message'],
    'token'   => $res['token']
]);
