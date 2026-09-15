#!/usr/bin/env bash
# =============================================================================
#  PADI-PJOK — Skrip deploy otomatis
#  Target : Proxmox LXC/VM (Debian 12 / Ubuntu 22.04+)
#  Stack  : Nginx + PHP-FPM + MariaDB + Cloudflare (subdomain)
#  Domain : padipjok.pintarhub.com
#
#  Pemakaian (di dalam server, sebagai root):
#     bash deploy.sh
#  Variabel bisa di-override, contoh:
#     DOMAIN=padipjok.pintarhub.com bash deploy.sh
# =============================================================================
set -euo pipefail

# ------------------------------- Konfigurasi ---------------------------------
DOMAIN="${DOMAIN:-padipjok.pintarhub.com}"
APP_DIR="${APP_DIR:-/var/www/padipjok}"
DB_NAME="${DB_NAME:-padi_pjok}"
DB_USER="${DB_USER:-padi_user}"
GITHUB_REPO="${GITHUB_REPO:-https://github.com/wsejagad-arch/PADI-PJOK.git}"
PHP_VER="${PHP_VER:-}"          # kosong = deteksi otomatis
CF_IP_HEADER=1                   # percayai header Cloudflare (real IP)

# Warna keluaran
G='\033[0;32m'; Y='\033[1;33m'; R='\033[0;31m'; N='\033[0m'
info()  { echo -e "${G}[OK]${N} $*"; }
warn()  { echo -e "${Y}[..]${N} $*"; }
fail()  { echo -e "${R}[!!]${N} $*" >&2; exit 1; }

[ "$(id -u)" -eq 0 ] || fail "Jalankan skrip ini sebagai root (sudo bash deploy.sh)."

# --------------------------- 0. Deteksi sistem -------------------------------
if [ -f /etc/os-release ]; then . /etc/os-release; else fail "Tidak dapat membaca /etc/os-release"; fi
case "${ID:-}" in
  debian|ubuntu) ;;
  *) fail "Distro '${ID:-unknown}' belum didukung. Skrip ini untuk Debian/Ubuntu." ;;
esac
info "Sistem: ${PRETTY_NAME:-$ID}"

# --------------------------- 1. Paket dasar ----------------------------------
warn "Memasang paket dasar..."
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y -qq curl git unzip ca-certificates lsb-release gnupg2 >/dev/null

if [ -z "$PHP_VER" ]; then
  PHP_VER="$(apt-cache search --names-only '^php[0-9.]+-fpm$' 2>/dev/null \
             | grep -oE 'php[0-9]+\.[0-9]+' | sort -V | tail -1 | sed 's/php//')"
  PHP_VER="${PHP_VER:-8.2}"
fi
info "PHP versi: $PHP_VER"

apt-get install -y -qq \
  nginx mariadb-server \
  "php${PHP_VER}-fpm" "php${PHP_VER}-cli" "php${PHP_VER}-mysql" >/dev/null
# Ekstensi opsional: pasang satu per satu agar paket yang tidak tersedia
# (mis. php8.2-json yang sudah menyatu ke core di Debian 12) tidak menggagalkan seluruh deploy.
for EXT in curl mbstring xml zip gd intl json; do
  apt-get install -y -qq "php${PHP_VER}-${EXT}" >/dev/null 2>&1 || \
    warn "ekstensi php${PHP_VER}-${EXT} tidak tersedia (dilewati)."
done
info "Nginx, MariaDB, PHP-FPM terpasang."

systemctl enable --now nginx mariadb >/dev/null 2>&1 || true
systemctl enable --now "php${PHP_VER}-fpm" >/dev/null 2>&1 || true

# --------------------------- 2. Database -------------------------------------
warn "Menyiapkan database..."
DB_PASS="${DB_PASS:-$(openssl rand -base64 24 | tr -d '/+=' | head -c 24)}"
mysql --protocol=socket -uroot <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL
info "Database '${DB_NAME}' & user '${DB_USER}' siap."

# --------------------------- 3. Ambil kode aplikasi --------------------------
warn "Mengambil kode aplikasi..."
mkdir -p "$(dirname "$APP_DIR")"
if [ -d "$APP_DIR/.git" ]; then
  git -C "$APP_DIR" fetch --all --quiet
  git -C "$APP_DIR" reset --hard "origin/main" --quiet
  info "Kode diperbarui (git pull)."
elif [ -d "$APP_DIR" ] && [ -n "$(ls -A "$APP_DIR" 2>/dev/null)" ]; then
  warn "$APP_DIR sudah ada dan bukan repo git — isi dipertahankan, berkas disalin ulang."
  cp -r "$(pwd)"/* "$APP_DIR"/ 2>/dev/null || true
else
  git clone --quiet "$GITHUB_REPO" "$APP_DIR"
  info "Kode di-clone dari $GITHUB_REPO"
fi

# --------------------------- 4. Berkas .env ----------------------------------
ENV_FILE="$APP_DIR/.env"
if [ ! -f "$ENV_FILE" ]; then
  cat > "$ENV_FILE" <<ENV
DB_HOST=localhost
DB_PORT=3306
DB_NAME=${DB_NAME}
DB_USER=${DB_USER}
DB_PASS=${DB_PASS}
APP_TZ=Asia/Jakarta
APP_URL=https://${DOMAIN}
APP_ENV=production
ENV
  info "Berkas .env dibuat."
else
  # sinkronkan kredensial DB ke .env yang sudah ada
  sed -i "s/^DB_PASS=.*/DB_PASS=${DB_PASS}/" "$ENV_FILE" 2>/dev/null || true
  warn ".env sudah ada — kredensial DB disinkronkan, nilai lain dibiarkan."
fi
chmod 640 "$ENV_FILE"

# --------------------------- 5. Setup skema DB -------------------------------
warn "Membuat skema database (setup_db.php)..."
if php -r '
require "'"$APP_DIR"'/koneksi.php";
if (!isset($conn_setup) || $conn_setup->connect_error) { exit(2); }
$conn_setup->select_db("'"$DB_NAME"'");
exit(0);
' 2>/dev/null; then
  (cd "$APP_DIR" && php -r '
    ob_start();
    $_SERVER["REQUEST_METHOD"]="POST";
    include "setup_db.php";
    $out = ob_get_clean();
    echo strip_tags($out);
  ' >/dev/null 2>&1) && info "Skema database dibuat." \
                     || warn "setup_db.php tidak berjalan otomatis — jalankan manual bila perlu."
else
  warn "Koneksi DB dari PHP gagal — periksa .env, lalu buka /setup_db.php di browser."
fi

# --------------------------- 6. Nginx vhost ----------------------------------
warn "Menulis konfigurasi Nginx untuk ${DOMAIN}..."
SOCK="/run/php/php${PHP_VER}-fpm.sock"
[ -S "$SOCK" ] || SOCK="/var/run/php/php${PHP_VER}-fpm.sock"

cat > "/etc/nginx/sites-available/${DOMAIN}" <<NGINX
# Cloudflare real IP
set_real_ip_from 173.245.48.0/20;
set_real_ip_from 103.21.244.0/22;
set_real_ip_from 103.22.200.0/22;
set_real_ip_from 103.31.4.0/22;
set_real_ip_from 141.101.64.0/18;
set_real_ip_from 108.162.192.0/18;
set_real_ip_from 190.93.240.0/20;
set_real_ip_from 188.114.96.0/20;
set_real_ip_from 197.234.240.0/22;
set_real_ip_from 198.41.128.0/17;
set_real_ip_from 162.158.0.0/15;
set_real_ip_from 104.16.0.0/13;
set_real_ip_from 104.24.0.0/14;
set_real_ip_from 172.64.0.0/13;
set_real_ip_from 131.0.72.0/22;
set_real_ip_from 2400:cb00::/32;
set_real_ip_from 2606:4700::/32;
set_real_ip_from 2803:f800::/32;
set_real_ip_from 2405:b500::/32;
set_real_ip_from 2405:8100::/32;
set_real_ip_from 2a06:98c0::/29;
set_real_ip_from 2c0f:f248::/32;
real_ip_header CF-Connecting-IP;

server {
    listen 80;
    listen [::]:80;
    server_name ${DOMAIN};
    root ${APP_DIR};
    index index.php index.html;

    client_max_body_size 64M;

    access_log /var/log/nginx/${DOMAIN}.access.log;
    error_log  /var/log/nginx/${DOMAIN}.error.log;

    # Jangan layani berkas sensitif
    location ~* /\.(env|git|htaccess|htpasswd) { deny all; return 404; }
    location ~* \.(sql|bak|log|ini|sh)$ { deny all; return 404; }

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:${SOCK};
        fastcgi_param HTTPS on;
        fastcgi_read_timeout 300;
    }

    location ~ /\.ht { deny all; }
}
NGINX

ln -sf "/etc/nginx/sites-available/${DOMAIN}" "/etc/nginx/sites-enabled/${DOMAIN}"
[ -e /etc/nginx/sites-enabled/default ] && rm -f /etc/nginx/sites-enabled/default
nginx -t >/dev/null 2>&1 || fail "Konfigurasi Nginx tidak valid."
systemctl reload nginx
info "Nginx aktif untuk http://${DOMAIN}"

# --------------------------- 7. Izin berkas ----------------------------------
chown -R www-data:www-data "$APP_DIR"
find "$APP_DIR" -type d -exec chmod 755 {} \;
find "$APP_DIR" -type f -exec chmod 644 {} \;
chmod 640 "$ENV_FILE"
info "Izin berkas diatur."

# --------------------------- 8. Firewall -------------------------------------
if command -v ufw >/dev/null 2>&1 && ufw status 2>/dev/null | grep -q "Status: active"; then
  ufw allow 80/tcp  >/dev/null 2>&1 || true
  ufw allow 443/tcp >/dev/null 2>&1 || true
  info "UFW: port 80/443 dibuka."
fi

# --------------------------- Ringkasan ---------------------------------------
IP_PUBLIK="$(curl -4 -s --max-time 5 ifconfig.me 2>/dev/null || echo '<IP-PUBLIK-SERVER>')"
cat <<RINGKASAN

============================================================
 SELESAI — PADI-PJOK terpasang di server
============================================================
 Aplikasi   : ${APP_DIR}
 Domain     : ${DOMAIN}
 Database   : ${DB_NAME}  (user: ${DB_USER})
 IP publik  : ${IP_PUBLIK}

 KREDENSIAL DB (simpan, tidak ditampilkan lagi):
   DB_PASS=${DB_PASS}
   (tersimpan di ${ENV_FILE})

 LANGKAH DI CLOUDFLARE (wajib, sekali saja):
   1. DNS → Add record → Type A
        Name    : padipjok
        Content : ${IP_PUBLIK}
        Proxy   : Proxied (awan oranye)  ← penting untuk SSL
   2. SSL/TLS → Overview → mode: Flexible  (atau Full jika ada origin cert)
   3. SSL/TLS → Edge Certificates → Always Use HTTPS: ON
   4. Tunggu 1-5 menit → buka https://${DOMAIN}

 UJI CEPAT DARI SERVER:
   curl -I http://localhost -H "Host: ${DOMAIN}"
   php -v ; systemctl status nginx --no-pager -l | head -5

 JIKA GAGAL:
   tail -50 /var/log/nginx/${DOMAIN}.error.log
   php -r 'include "${APP_DIR}/koneksi.php"; var_dump(\$conn ? "DB OK" : "DB GAGAL");'
============================================================
RINGKASAN
