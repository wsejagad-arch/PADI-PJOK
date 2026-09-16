/**
 * padi_halaman_db_mati() — Dipakai halaman login bila database tidak bisa dihubungi.
 * Tujuan: pengguna TIDAK pernah melihat halaman putih / HTTP 500; tampil pesan
 * yang jelas beserta langkah perbaikan yang bisa dilakukan.
 */
if (!function_exists('padi_halaman_db_mati')) {
function padi_halaman_db_mati($pesan)
{
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=utf-8');
    }
    $p = htmlspecialchars((string)$pesan, ENT_QUOTES, 'UTF-8');
    echo '<!DOCTYPE html><html lang="id"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>Database Belum Siap – PADI-PJOK</title>'
        . '<style>*,*::before,*::after{box-sizing:border-box}'
        . 'body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;'
        . 'background:#F3F6FB;font-family:Inter,system-ui,-apple-system,sans-serif;padding:20px}'
        . '.kotak{max-width:460px;width:100%;background:#fff;border-radius:20px;padding:26px 22px;'
        . 'box-shadow:0 10px 34px rgba(0,0,0,.12);border-top:5px solid #DC2626}'
        . 'h1{font-size:19px;margin:0 0 10px;color:#111827}'
        . 'p{font-size:13.5px;line-height:1.6;color:#374151;margin:0 0 12px}'
        . 'ul{font-size:13px;line-height:1.7;color:#4B5563;padding-left:20px;margin:0 0 16px}'
        . 'a.tombol{display:block;text-align:center;padding:14px;background:#1A56DB;color:#fff;'
        . 'border-radius:10px;text-decoration:none;font-weight:700;font-size:14px;min-height:48px}'
        . 'a.tautan{display:block;text-align:center;margin-top:12px;font-size:13px;color:#1A56DB}'
        . '</style></head><body><div class="kotak">'
        . '<h1>Database belum siap</h1>'
        . '<p>' . $p . '</p>'
        . '<ul>'
        . '<li>Pastikan layanan <strong>MySQL/MariaDB</strong> sudah berjalan di server.</li>'
        . '<li>Periksa <strong>DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS</strong> pada berkas <code>.env</code>.</li>'
        . '<li>Bila database baru dibuat, jalankan <code>setup_db.php</code> sekali.</li>'
        . '</ul>'
        . '<a class="tombol" href="setup_db.php">Coba perbaiki sekarang</a>'
        . '<a class="tautan" href="index.php">Kembali ke halaman utama</a>'
        . '</div></body></html>';
    exit;
}
}
