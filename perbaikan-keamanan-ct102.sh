#!/usr/bin/env bash
# ============================================================================
# perbaikan-keamanan-ct102.sh — jalankan DI DALAM CT 102 sebagai root.
#   pct enter 102
#   bash /root/perbaikan-keamanan-ct102.sh
#
# Sifat: idempoten. Tidak menyentuh data database.
# Yang dilakukan:
#   1. Menambah PADI_SETUP_TOKEN & GURU_SEED_PASS ke .env bila belum ada.
#   2. Menyisipkan aturan nginx: blokir berkas rahasia, sembunyikan berkas
#      internal, dan alamat samar /instal/<TOKEN>, /sesi/<TOKEN>.
#   3. (opsional) memasang ulang token Cloudflare Tunnel yang baru.
#
# Pemakaian:
#   bash perbaikan-keamanan-ct102.sh
#   bash perbaikan-keamanan-ct102.sh --cf-token=eyJ...
# ============================================================================
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/padipjok}"
DOMAIN="${DOMAIN:-padipjok.pintarhub.com}"
MARK="# PADI-SECURITY-BLOCK"
CF_TOKEN=""

for arg in "$@"; do
    case "$arg" in
        --cf-token=*) CF_TOKEN="${arg#*=}" ;;
        *) echo "Argumen tidak dikenal: $arg"; exit 1 ;;
    esac
done

[ "$(id -u)" -eq 0 ] || { echo "Jalankan sebagai root (sudo bash $0)"; exit 1; }
[ -f "$APP_DIR/.env" ] || { echo "ERROR: $APP_DIR/.env tidak ditemukan"; exit 1; }

VHOST="${VHOST:-/etc/nginx/sites-available/$DOMAIN}"
if [ ! -f "$VHOST" ]; then
    VHOST="$(grep -rl "server_name[[:space:]]*$DOMAIN" /etc/nginx/sites-enabled /etc/nginx/conf.d 2>/dev/null | head -1 || true)"
fi
[ -n "$VHOST" ] && [ -f "$VHOST" ] || { echo "ERROR: vhost nginx untuk $DOMAIN tidak ditemukan"; exit 1; }

# ---------------------------------------------------------------- 1. .env
set_env() {
    key="$1"; val="$2"
    if grep -qE "^${key}=.+" "$APP_DIR/.env"; then
        echo "  [skip] $key sudah terisi"
        return 0
    fi
    if grep -qE "^${key}=" "$APP_DIR/.env"; then
        sed -i "s|^${key}=.*|${key}=${val}|" "$APP_DIR/.env"
    else
        printf '\n%s=%s\n' "$key" "$val" >> "$APP_DIR/.env"
    fi
    chmod 600 "$APP_DIR/.env" || true
    echo "  [baru] $key=$val"
    echo "         ^ simpan nilai ini, jangan dibagikan"
}

echo "== 1. Variabel keamanan di .env =="
SETUP_TOKEN="$(openssl rand -hex 16)"
GURU_PASS="$(openssl rand -base64 18 | tr -d '/+=' | cut -c1-20)"
set_env PADI_SETUP_TOKEN "$SETUP_TOKEN"
set_env GURU_SEED_PASS "$GURU_PASS"
chown www-data:www-data "$APP_DIR/.env" 2>/dev/null || true

# ---------------------------------------------------------------- 2. nginx
echo "== 2. Aturan nginx di $VHOST =="
SOCK="$(grep -o 'fastcgi_pass [^;]*' "$VHOST" | head -1 | awk '{print $2}')"
if [ -z "$SOCK" ]; then
    echo "ERROR: fastcgi_pass tidak ditemukan di vhost"; exit 1
fi

if grep -q "$MARK" "$VHOST"; then
    echo "  [skip] blok keamanan sudah ada"
else
    cp -a "$VHOST" "${VHOST}.bak.$(date +%Y%m%d%H%M%S)"
    BLOCK="$(cat <<'EON'
    # PADI-SECURITY-BLOCK
    location ~* \.(sql|bak|log|ini|sh|md|txt)$ { deny all; return 404; }
    location ~ /\.(?!well-known) { deny all; return 404; }
    location ~ ^/(auth|auth-boot|koneksi|pesan-db|dashboard-guru-tmp)\.php$ { return 404; }
    location = /setup_db.php {
        internal;
        include snippets/fastcgi-php.conf;
        fastcgi_pass @@SOCK@@;
        fastcgi_param HTTPS on;
    }
    rewrite ^/instal/?$ /setup_db.php last;
    rewrite ^/instal/([A-Za-z0-9_-]{20,80})/?$ /setup_db.php?setup_token=$1 last;
    rewrite ^/sesi/([A-Za-z0-9]{6,12})/?$ /input-token.php?kode=$1 last;
    rewrite ^/masuk/?$ /index.php last;
    rewrite ^/guru/?$ /login-guru.php last;
    rewrite ^/siswa/?$ /login-siswa.php last;
EON
)"
    printf '%s\n' "$BLOCK" | sed "s|@@SOCK@@|$SOCK|g" > /tmp/padi_block.conf
    awk 'BEGIN{done=0} !done && /location[[:space:]]+\/[[:space:]]*\{/ { while ((getline line < "/tmp/padi_block.conf") > 0) print line; done=1 } {print}' \
        "$VHOST" > "${VHOST}.new"
    mv "${VHOST}.new" "$VHOST"
    rm -f /tmp/padi_block.conf
    echo "  [baru] blok keamanan disisipkan"
fi

nginx -t
systemctl reload nginx
echo "  [ok] nginx reload"

# ---------------------------------------------------------------- 3. tunnel
if [ -n "$CF_TOKEN" ]; then
    echo "== 3. Pasang token Cloudflare Tunnel baru =="
    cloudflared service install "$CF_TOKEN"
    systemctl restart cloudflared
    sleep 3
    echo -n "  status cloudflared: "; systemctl is-active cloudflared
else
    echo "== 3. Token tunnel tidak diganti (pakai --cf-token=... bila sudah di-rotate) =="
fi

# ---------------------------------------------------------------- 4. verifikasi
echo "== 4. Verifikasi =="
check() {
    label="$1"; url="$2"; harap="$3"
    kode="$(curl -s -o /dev/null -w '%{http_code}' --max-time 15 "https://$DOMAIN$url" || echo 000)"
    if [ "$kode" = "$harap" ]; then echo "  [OK]   $label -> $kode"
    else echo "  [CEK]  $label -> $kode (harap $harap)"; fi
}
check "/setup_db.php (harus 404)"        "/setup_db.php"          404
check "/.env (harus 403/404)"            "/.env"                 404
check "/perintah-pasang-tunnel.txt"      "/perintah-pasang-tunnel.txt" 404
check "/instal tanpa token"              "/instal"               404
check "/sesi/TES123"                     "/sesi/TES123"          302
check "halaman utama"                    "/"                     200

echo
echo "Selesai. Installer: https://$DOMAIN/instal/<PADI_SETUP_TOKEN>"
