#!/usr/bin/env bash
# Patch nginx PADI-PJOK: sembunyikan berkas internal/rahasia + alamat samar bertoken.
# Jalankan sebagai root di server yang melayani domain ini.
set -uo pipefail

NG="${NGINX_DIR:-/etc/nginx}"
DOMAIN="${DOMAIN:-padipjok.pintarhub.com}"
MARK="# PADI-SECURITY-BLOCK"

echo "== Cari vhost untuk $DOMAIN =="
mapfile -t VHOSTS < <(grep -rlE "server_name[^;]*${DOMAIN}" "$NG/sites-enabled" "$NG/conf.d" 2>/dev/null | sort -u)
if [ "${#VHOSTS[@]}" -eq 0 ]; then
    echo "TIDAK ADA vhost '$DOMAIN' di $NG/sites-enabled atau $NG/conf.d"
    echo "Berarti nginx BUKAN di host ini. Jalankan skrip ini di host yang melayani situs"
    echo "(cek: curl -sI https://$DOMAIN | head -3 dan 'ss -ltnp | grep :80')."
    exit 1
fi
printf 'vhost: %s\n' "${VHOSTS[@]}"

FAIL=0
for V in "${VHOSTS[@]}"; do
    echo "-- $V"
    SOCK="$(grep -o 'fastcgi_pass [^;]*' "$V" | head -1 | awk '{print $2}')"
    echo "   fastcgi_pass: ${SOCK:-<tidak ada>}"
    if [ -z "$SOCK" ]; then echo "   [lewati] bukan vhost PHP"; continue; fi

    if grep -q "$MARK" "$V"; then
        echo "   [skip] blok sudah ada"
        continue
    fi

    cp -a "$V" "${V}.bak.$(date +%Y%m%d%H%M%S)"
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
    awk 'BEGIN{d=0} !d && /location[[:space:]]+\/[[:space:]]*\{/ { while ((getline l < "/tmp/padi_block.conf") > 0) print l; d=1 } {print}' \
        "$V" > "${V}.new" && mv "${V}.new" "$V"
    rm -f /tmp/padi_block.conf
    echo "   [baru] blok keamanan disisipkan"
done

echo "== nginx -t =="
if ! nginx -t; then
    echo "!! nginx -t GAGAL — pulihkan dari berkas .bak.* terbaru lalu jalankan ulang dengan NGINX_DIR/DOMAIN yang benar."
    exit 1
fi
systemctl reload nginx && echo "[ok] nginx reload"

echo "== Verifikasi (dari server, lewat Cloudflare) =="
chk() { c="$(curl -s -o /dev/null -w '%{http_code}' --max-time 15 "https://$DOMAIN$1" || echo 000)"
        if [ "$c" = "$2" ]; then echo "  [OK]  $1 -> $c"; else echo "  [CEK] $1 -> $c (harap $2)"; FAIL=$((FAIL+1)); fi; }
chk "/setup_db.php" 404
chk "/auth.php" 404
chk "/README.md" 404
chk "/perintah-pasang-tunnel.txt" 404
chk "/instal" 404
chk "/sesi/TES123" 302
chk "/" 200

echo
if [ "$FAIL" -eq 0 ]; then echo "SEMUA CEK LOLOS"; else echo "$FAIL cek belum sesuai"; fi
