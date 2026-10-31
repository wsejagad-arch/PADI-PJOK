<?php
/**
 * Konfigurasi & helper login Belajar.id (Google OAuth 2.0 / OpenID Connect).
 *
 * Isi di .env server:
 *   GOOGLE_CLIENT_ID=....apps.googleusercontent.com
 *   GOOGLE_CLIENT_SECRET=...
 *   GOOGLE_REDIRECT_URI=https://domain/oauth-callback.php   (opsional; dihitung otomatis)
 *   BELAJAR_ID_DOMAIN=belajar.id          (boleh lebih dari satu, pisah koma; *.sch.id didukung)
 *   BELAJAR_ID_AUTO_DAFTAR=1              (1 = akun guru baru dibuat otomatis saat login pertama)
 */

if (!function_exists('padi_oauth_env')) {
function padi_oauth_env($kunci, $bawaan = '')
{
    $nilai = getenv($kunci);
    if ($nilai === false || trim((string)$nilai) === '') return $bawaan;
    return trim((string)$nilai);
}
}

if (!function_exists('padi_oauth_redirect_uri')) {
function padi_oauth_redirect_uri()
{
    $tetap = padi_oauth_env('GOOGLE_REDIRECT_URI', '');
    if ($tetap !== '') return $tetap;

    $skema = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
    $dir = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');
    if ($dir === '.' || $dir === '/') $dir = '';
    return $skema . '://' . $host . $dir . '/oauth-callback.php';
}
}

if (!function_exists('padi_oauth_konfig')) {
function padi_oauth_konfig()
{
    return [
        'client_id'     => padi_oauth_env('GOOGLE_CLIENT_ID', ''),
        'client_secret' => padi_oauth_env('GOOGLE_CLIENT_SECRET', ''),
        'redirect_uri'  => padi_oauth_redirect_uri(),
        'domain'        => padi_oauth_env('BELAJAR_ID_DOMAIN', 'belajar.id,*.belajar.id'),
        'hd'            => padi_oauth_env('BELAJAR_ID_HD', ''),
        'auto_daftar'   => padi_oauth_env('BELAJAR_ID_AUTO_DAFTAR', '1'),
    ];
}
}

if (!function_exists('padi_oauth_siap')) {
function padi_oauth_siap()
{
    $k = padi_oauth_konfig();
    return $k['client_id'] !== '' && $k['client_secret'] !== '';
}
}

if (!function_exists('padi_oauth_url_mulai')) {
/** URL halaman consent Google. */
function padi_oauth_url_mulai($state)
{
    $k = padi_oauth_konfig();
    $params = [
        'client_id'     => $k['client_id'],
        'redirect_uri'  => $k['redirect_uri'],
        'response_type' => 'code',
        'scope'         => 'openid email profile',
        'state'         => $state,
        'prompt'        => 'select_account',
        'access_type'   => 'online',
    ];
    if ($k['hd'] !== '') $params['hd'] = $k['hd'];
    return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
}
}

if (!function_exists('padi_oauth_tukar_code')) {
/** Tukar authorization code → access token + id_token. */
function padi_oauth_tukar_code($code)
{
    $k = padi_oauth_konfig();
    $post = http_build_query([
        'code'          => $code,
        'client_id'     => $k['client_id'],
        'client_secret' => $k['client_secret'],
        'redirect_uri'  => $k['redirect_uri'],
        'grant_type'    => 'authorization_code',
    ]);

    if (!function_exists('curl_init')) {
        return ['ok' => false, 'pesan' => 'Ekstensi cURL PHP tidak aktif di server.'];
    }

    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $post,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
    ]);
    $body = curl_exec($ch);
    $errno = curl_errno($ch);
    $galat = curl_error($ch);
    curl_close($ch);

    if ($errno !== 0 || $body === false) {
        return ['ok' => false, 'pesan' => 'Gagal menghubungi Google: ' . $galat];
    }
    $data = json_decode((string)$body, true);
    if (!is_array($data) || empty($data['access_token'])) {
        $pesan = is_array($data) && isset($data['error_description']) ? $data['error_description'] : 'Respons token tidak valid.';
        return ['ok' => false, 'pesan' => 'Login Google gagal: ' . $pesan];
    }
    return ['ok' => true, 'data' => $data];
}
}

if (!function_exists('padi_oauth_profil')) {
/** Ambil profil OpenID Connect dari access token. */
function padi_oauth_profil($accessToken)
{
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'pesan' => 'Ekstensi cURL PHP tidak aktif di server.'];
    }
    $ch = curl_init('https://openidconnect.googleapis.com/v1/userinfo');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $accessToken],
    ]);
    $body = curl_exec($ch);
    $errno = curl_errno($ch);
    $galat = curl_error($ch);
    curl_close($ch);

    if ($errno !== 0 || $body === false) {
        return ['ok' => false, 'pesan' => 'Gagal mengambil profil Google: ' . $galat];
    }
    $data = json_decode((string)$body, true);
    if (!is_array($data) || empty($data['email'])) {
        return ['ok' => false, 'pesan' => 'Profil Google tidak memuat email.'];
    }
    if (isset($data['email_verified']) && !$data['email_verified']) {
        return ['ok' => false, 'pesan' => 'Email akun Google belum diverifikasi.'];
    }
    return ['ok' => true, 'profil' => $data];
}
}
