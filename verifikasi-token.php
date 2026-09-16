<?php
// verifikasi-token.php — Menampilkan token sesi aktif siswa (JSON) + gerbang token.
// Dimuat oleh dashboard siswa (tombol "Token") dan bisa juga dibuka dari portal.

// Titik masuk yang aman: sesi dimulai lewat helper, bukan session_start() mentah.
require_once __DIR__ . '/auth-boot.php';

// Gerbang input: boleh dipanggil dengan ?token=<kode> untuk masuk halaman login siswa.
foreach (['token', 'kode', 't'] as $kunci) {
    if (!empty($_GET[$kunci])) {
        $kode = (string)$_GET[$kunci];
        if (!preg_match('/^[A-Za-z0-9]+$/', $kode)) {
            break;
        }
        padi_kembali('login-siswa.php?token=' . urlencode($kode));
    }
}

// Mulai dari sini: jawaban selalu JSON (alat, bukan halaman).
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['siswa_id']) || empty($_SESSION['master_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesi berakhir. Silakan login ulang.']);
    exit;
}

$password = $_POST['password'] ?? '';
$hasil = verifikasiPasswordSiswa($conn, (int)$_SESSION['master_id'], $password);

if (!$hasil['success']) {
    echo json_encode(['success' => false, 'message' => $hasil['message']]);
    exit;
}

echo json_encode([
    'success' => true,
    'token' => $hasil['token'],
    'materi' => $_SESSION['materi'] ?? '',
    'kelas' => $_SESSION['siswa_kelas'] ?? '',
]);
