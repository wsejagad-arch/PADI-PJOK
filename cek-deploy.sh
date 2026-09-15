#!/usr/bin/env bash
# =============================================================================
#  PADI-PJOK — Periksa kesehatan deploy (jalankan di server)
#  Pemakaian: bash cek-deploy.sh [domain]
# =============================================================================
DOMAIN="${1:-padipjok.pintarhub.com}"
APP_DIR="${APP_DIR:-/var/www/padipjok}"
OK=0; BAD=0
g() { printf '\033[0;32m[OK]\033[0m %s\n' "$*"; OK=$((OK+1)); }
b() { printf '\033[0;31m[!!]\033[0m %s\n' "$*"; BAD=$((BAD+1)); }
h() { printf '\n\033[1;34m== %s ==\033[0m\n' "$*"; }

h "Layanan"
systemctl is-active --quiet nginx      && g "nginx aktif"      || b "nginx TIDAK aktif"
systemctl is-active --quiet mariadb    && g "mariadb aktif"    || b "mariadb TIDAK aktif"
systemctl is-active --quiet php*-fpm   && g "php-fpm aktif"    || b "php-fpm TIDAK aktif"

h "Berkas aplikasi"
[ -f "$APP_DIR/index.php" ]    && g "index.php ada"    || b "index.php TIDAK ada"
[ -f "$APP_DIR/.env" ]         && g ".env ada"         || b ".env TIDAK ada"
[ -f "$APP_DIR/koneksi.php" ]  && g "koneksi.php ada"  || b "koneksi.php TIDAK ada"

h "Sintaks PHP"
bad=0
while IFS= read -r f; do php -l "$f" >/dev/null 2>&1 || { b "sintaks rusak: $f"; bad=1; }; done \
  < <(find "$APP_DIR" -maxdepth 1 -name '*.php')
[ "$bad" -eq 0 ] && g "semua berkas PHP lolos php -l"

h "Koneksi database"
php -r "
require '$APP_DIR/koneksi.php';
echo \$conn ? 'DB TERHUBUNG' : 'DB GAGAL (conn null)';
" 2>&1 | grep -q "DB TERHUBUNG" && g "koneksi DB berhasil" || b "koneksi DB gagal"

h "Nginx vhost"
[ -f "/etc/nginx/sites-enabled/${DOMAIN}" ] && g "vhost ${DOMAIN} aktif" || b "vhost ${DOMAIN} TIDAK aktif"
nginx -t >/dev/null 2>&1 && g "konfigurasi nginx valid" || b "konfigurasi nginx INVALID"

h "HTTP lokal"
code=$(curl -s -o /dev/null -w '%{http_code}' -H "Host: ${DOMAIN}" http://127.0.0.1/index.php || echo 000)
[ "$code" = "200" ] && g "HTTP 200 dari server" || b "HTTP $code dari server (harap 200)"

h "HTTP publik"
if command -v curl >/dev/null; then
  pcode=$(curl -s -o /dev/null -w '%{http_code}' --max-time 10 "https://${DOMAIN}/" || echo 000)
  [ "$pcode" = "200" ] && g "https://${DOMAIN} → 200" || b "https://${DOMAIN} → $pcode"
fi

printf '\n\033[1mRingkasan:\033[0m %d lulus, %d gagal\n' "$OK" "$BAD"
[ "$BAD" -eq 0 ] && exit 0 || exit 1
