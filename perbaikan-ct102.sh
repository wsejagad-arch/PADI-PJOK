#!/usr/bin/env bash
# ============================================================================
# PERBAIKAN CT 102  —  jalankan DI DALAM CT 102 setelah diagnosa
# Cara   : console Proxmox ->  pct enter 102  ->  bash perbaikan-ct102.sh
# Sifat  : idempoten (aman dijalankan berulang), TIDAK menyentuh data database
# Catatan: skrip ini TIDAK menghapus tabel/database apa pun.
# ============================================================================
set -u
ok(){ printf '\n\033[1;36m===== %s =====\033[0m\n' "$1"; }
bad(){ printf '\033[31m[X] %s\033[0m\n' "$1"; }
good(){ printf '\033[32m[v] %s\033[0m\n' "$1"; }
warn(){ printf '\033[33m[!] %s\033[0m\n' "$1"; }

ROOT="/var/www/padipjok"      # <<< UBAH bila hasil diagnosa menunjukkan lokasi lain
DOMAIN="padipjok.pintarhub.com"  # <<< UBAH ke domain final PADI
PHPVER=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null || echo "8.2")

# ---------------------------------------------------------------- 1. PAKET
ok "1/7 MEMASTIKAN PAKET TERPASANG"
export DEBIAN_FRONTEND=noninteractive
if command -v apt-get >/dev/null; then
  apt-get update -qq
  apt-get install -y -qq nginx mariadb-server php-fpm php-mysql php-cli php-mbstring php-curl php-xml curl unzip 2>&1 | tail -5 | sed 's/^/  /'
  good "paket selesai"
else
  warn "bukan Debian/Ubuntu — lewati apt"
fi

# ---------------------------------------------------------------- 2. LAYANAN
ok "2/7 MENYALAKAN & MENGAKTIFKAN LAYANAN"
for s in mariadb nginx php${PHPVER}-fpm; do
  if systemctl list-unit-files 2>/dev/null | grep -q "^${s}\.service"; then
    systemctl enable --now "$s" 2>&1 | tail -2 | sed 's/^/  /'
    st=$(systemctl is-active "$s")
    [ "$st" = active ] && good "$s aktif" || bad "$s = $st (lihat: journalctl -u $s -n 30)"
  fi
done

# ---------------------------------------------------------------- 3. DATABASE
ok "3/7 DATABASE (tanpa menghapus apa pun)"
if systemctl is-active --quiet mariadb || systemctl is-active --quiet mysql; then
  good "server database berjalan"
  # Buat DB + user bila belum ada — TIDAK menyentuh tabel yang sudah ada
  DB_NAME="padi_pjok"
  DB_USER="padi_user"
  DB_PASS="$(head -c 24 /dev/urandom | base64 | tr -d '/+=' | head -c 24)"
  mysql <<SQL 2>&1 | sed 's/^/  /'
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SELECT COUNT(*) AS jumlah_tabel FROM information_schema.tables WHERE table_schema='${DB_NAME}';
SQL
  good "database '${DB_NAME}' siap"
  echo "  >>> Simpan kredensial ini untuk .env:"
  echo "      DB_NAME=${DB_NAME}"
  echo "      DB_USER=${DB_USER}"
  echo "      DB_PASS=${DB_PASS}"
else
  bad "server database TIDAK berjalan — periksa: journalctl -u mariadb -n 40"
fi

# ---------------------------------------------------------------- 4. DIREKTORI
ok "4/7 DIREKTORI APLIKASI & IZIN"
if [ -d "$ROOT" ]; then
  good "kode ada di $ROOT"
else
  warn "$ROOT belum ada — siapkan lalu tempel kode PADI ke sana"
  mkdir -p "$ROOT"
fi
mkdir -p "$ROOT/uploads"
chown -R www-data:www-data "$ROOT" 2>/dev/null
find "$ROOT" -type d -exec chmod 755 {} \; 2>/dev/null
find "$ROOT" -type f -exec chmod 644 {} \; 2>/dev/null
chmod 775 "$ROOT/uploads" 2>/dev/null
# .env hanya boleh dibaca pemilik
[ -f "$ROOT/.env" ] && chmod 600 "$ROOT/.env" && good ".env diperketat ke 600"
good "izin disetel (direktori 755, berkas 644, uploads 775)"

# ---------------------------------------------------------------- 5. NGINX
ok "5/7 VIRTUAL HOST NGINX"
# Hapus default situs bawaan agar tidak menyerobot
if [ -L /etc/nginx/sites-enabled/default ]; then
  rm -f /etc/nginx/sites-enabled/default && good "situs 'default' dinonaktifkan"
fi
cat > /etc/nginx/sites-available/padipjok <<NGINX
server {
    listen 80;
    listen [::]:80;
    server_name ${DOMAIN} _;

    root ${ROOT};
    index index.php index.html;

    client_max_body_size 32M;

    # ---- berkas statis ----
    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    # ---- PHP-FPM ----
    location ~ \.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php${PHPVER}-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_read_timeout 300;
    }

    # ---- lindungi berkas sensitif ----
    location ~ /\.(env|git|ht) { deny all; return 404; }
    location ~* \.(sql|bak|log|sh|md)\$ { deny all; return 404; }
    location ^~ /uploads/ { location ~ \.php\$ { deny all; } }

    access_log /var/log/nginx/padipjok.access.log;
    error_log  /var/log/nginx/padipjok.error.log;
}
NGINX
ln -sfn /etc/nginx/sites-available/padipjok /etc/nginx/sites-enabled/padipjok
if nginx -t 2>&1 | sed 's/^/  /'; then
  systemctl reload nginx && good "nginx dimuat ulang"
else
  bad "sintaks nginx salah — perbaiki dulu sebelum reload"
fi

# ---------------------------------------------------------------- 6. UJI LOKAL
ok "6/7 UJI LOKAL DARI DALAM CT"
for u in http://127.0.0.1/ http://127.0.0.1/index.php; do
  printf '  %-32s -> ' "$u"
  curl -s -o /tmp/_p.html -w 'HTTP %{http_code}  %{size_download}B\n' --max-time 8 "$u"
done
echo "  judul: $(grep -oiE '<title>[^<]*</title>' /tmp/_p.html 2>/dev/null | head -1)"

# ---------------------------------------------------------------- 7. FIREWALL
ok "7/7 FIREWALL (bila aktif)"
if command -v ufw >/dev/null && ufw status 2>/dev/null | grep -qi active; then
  ufw allow 80/tcp  >/dev/null 2>&1 && good "ufw: port 80 diizinkan"
  ufw allow 443/tcp >/dev/null 2>&1 && good "ufw: port 443 diizinkan"
else
  echo "  ufw tidak aktif (baik, tidak menghalangi)"
fi
if command -v iptables >/dev/null; then
  echo "  aturan INPUT DROP? -> $(iptables -S INPUT 2>/dev/null | head -1)"
fi

printf '\n\033[1;33m>>> SELESAI. Uji dari luar: https://%s/\033[0m\n' "$DOMAIN"
echo "    Bila masih 404: DNS/CDN belum mengarah ke CT ini (cek IP publik CT)."
