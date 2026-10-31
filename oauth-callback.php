<?php
// oauth-callback.php — terima balasan Google, verifikasi, lalu login guru
require_once __DIR__ . '/koneksi.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/oauth.php';

if (empty($conn)) {
    require_once __DIR__ . '/pesan-db.php';
    padi_halaman_db_mati($padi_db_error ?? 'Database tidak dapat dihubungi.');
}

padi_berbagi_sesi();
padi_mulai_sesi();

/** Gagal → balik ke halaman login guru dengan pesan. */
function padi_oauth_gagal($pesan)
{
    $_SESSION['oauth_state'] = '';
    padi_kembali('login-guru.php', $pesan);
}

if (!padi_oauth_siap()) {
    padi_oauth_gagal('Login Belajar.id belum dikonfigurasi di server.');
}

$state = (string)($_GET['state'] ?? '');
$kode  = (string)($_GET['code'] ?? '');
$galat = (string)($_GET['error'] ?? '');

if ($galat !== '') {
    padi_oauth_gagal('Login Belajar.id dibatalkan (' . $galat . ').');
}
if ($state === '' || empty($_SESSION['oauth_state']) || !hash_equals((string)$_SESSION['oauth_state'], $state)) {
    padi_oauth_gagal('Sesi login Belajar.id tidak valid. Silakan coba lagi.');
}
if (!empty($_SESSION['oauth_waktu']) && (time() - (int)$_SESSION['oauth_waktu']) > 600) {
    padi_oauth_gagal('Sesi login Belajar.id kedaluwarsa. Silakan coba lagi.');
}
if ($kode === '') {
    padi_oauth_gagal('Kode otorisasi Google tidak diterima.');
}
$_SESSION['oauth_state'] = '';

$tukar = padi_oauth_tukar_code($kode);
if (empty($tukar['ok'])) {
    padi_oauth_gagal($tukar['pesan']);
}

$profil = padi_oauth_profil((string)$tukar['data']['access_token']);
if (empty($profil['ok'])) {
    padi_oauth_gagal($profil['pesan']);
}

$hasil = loginGuruBelajarId($conn, $profil['profil']);
if (empty($hasil['success'])) {
    padi_oauth_gagal($hasil['message']);
}

padi_kembali('dashboard-guru.php');
