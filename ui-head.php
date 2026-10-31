<?php
// ui-head.php — menyisipkan manifest PWA + CSS/JS mode PC/mobile ke <head>.
// Base path dihitung dari SCRIPT_NAME agar tetap benar di /PADI (lokal)
// maupun di root domain (produksi) dan untuk URL hasil rewrite (/sesi/<token>).

if (!function_exists('padi_ui_base')) {
function padi_ui_base()
{
    $script = (string)($_SERVER['SCRIPT_NAME'] ?? '/index.php');
    $dir = str_replace('\\', '/', dirname($script));
    if ($dir === '/' || $dir === '.' || $dir === '') return '';
    return rtrim($dir, '/');
}
}

if (!function_exists('padi_ui_head')) {
function padi_ui_head()
{
    $base = htmlspecialchars(padi_ui_base(), ENT_QUOTES, 'UTF-8');
    $v_css = @filemtime(__DIR__ . '/aset/app.css') ?: 1;
    $v_js  = @filemtime(__DIR__ . '/aset/app.js') ?: 1;
    echo '<link rel="manifest" href="' . $base . '/aset/manifest.webmanifest">' . "\n";
    echo '<meta name="theme-color" content="#1A56DB">' . "\n";
    echo '<meta name="mobile-web-app-capable" content="yes">' . "\n";
    echo '<meta name="apple-mobile-web-app-capable" content="yes">' . "\n";
    echo '<link rel="apple-touch-icon" href="' . $base . '/aset/icon-192.png">' . "\n";
    echo '<link rel="stylesheet" href="' . $base . '/aset/app.css?v=' . $v_css . '">' . "\n";
    echo '<script>window.PADI_BASE=' . json_encode($base . '/') . ';</script>' . "\n";
    echo '<script src="' . $base . '/aset/app.js?v=' . $v_js . '" defer></script>' . "\n";
}
}
