<?php
// oauth-belajar.php — mulai login guru dengan akun Belajar.id (Google OAuth)
require_once __DIR__ . '/koneksi.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/oauth.php';

if (empty($conn)) {
    require_once __DIR__ . '/pesan-db.php';
    padi_halaman_db_mati($padi_db_error ?? 'Database tidak dapat dihubungi.');
}

padi_berbagi_sesi();
padi_mulai_sesi();

if (!padi_oauth_siap()) {
    padi_kembali('login-guru.php', 'Login Belajar.id belum aktif: GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET belum diisi di .env server.');
}

$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state'] = $state;
$_SESSION['oauth_waktu'] = time();

header('Location: ' . padi_oauth_url_mulai($state));
exit;
