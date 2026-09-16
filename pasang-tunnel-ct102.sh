#!/usr/bin/env bash
# ============================================================================
# PASANG CLOUDFLARE TUNNEL DI CT 102  ->  padipjok.pintarhub.com
# Jalankan DI DALAM CT 102 (console Proxmox: pct enter 102):
#     bash pasang-tunnel-ct102.sh
#
# Hasil akhir skrip: 1 BARIS berisi UUID tunnel + JSON kredensial.
# >>> Kirim baris itu ke chat. <<<
# ============================================================================
set -u
ok(){ printf '\n\033[1;36m===== %s =====\033[0m\n' "$1"; }
bad(){ printf '\033[31m[X] %s\033[0m\n' "$1"; }
good(){ printf '\033[32m[v] %s\033[0m\n' "$1"; }
warn(){ printf '\033[33m[!] %s\033[0m\n' "$1"; }

DOMAIN="padipjok.pintarhub.com"
TUNNELNAME="padi-pjok"
ROOT="/var/www/padipjok"
PHPVER=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null || echo "8.2")

[ "$(id -u)" = "0" ] || { bad "jalankan sebagai root (pct enter 102 sudah root)"; exit 1; }

# ---------------------------------------------------------------- 1. PAKET DASAR
ok "1/6 PAKET DASAR (nginx, php-fpm, mariadb)"
export DEBIAN_FRONTEND=noninteractive
if command -v apt-get >/dev/null; then
  apt-get update -qq
  apt-get install -y -qq curl ca-certificates gnupg lsb-release \
      nginx mariadb-server php-fpm php-mysql php-cli php-mbstring php-curl php-xml 2>&1 | tail -3 | sed 's/^/  /'
  good "paket dasar terpasang"
else
  bad "bukan Debian/Ubuntu — skrip mengasumsikan apt"; exit 1
fi

# ---------------------------------------------------------------- 2. LAYANAN
ok "2/6 MENYALAKAN LAYANAN LOKAL"
for s in mariadb nginx php${PHPVER}-fpm; do
  systemctl list-unit-files 2>/dev/null | grep -q "^${s}\.service" && {
    systemctl enable --now "$s" >/dev/null 2>&1
    st=$(systemctl is-active "$s")
    [ "$st" = active ] && good "$s aktif" || bad "$s = $st"
  }
done

# ---------------------------------------------------------------- 3. CLOUDFLARED
ok "3/6 MEMASANG cloudflared"
if command -v cloudflared >/dev/null; then
  good "sudah ada: $(cloudflared --version 2>&1 | head -1)"
else
  mkdir -p /usr/share/keyrings
  curl -fsSL https://pkg.cloudflare.com/cloudflare-main.gpg \
    -o /usr/share/keyrings/cloudflare-main.gpg 2>/dev/null \
    || curl -fsSL https://pkg.cloudflare.com/cloudflare-main.gpg | tee /usr/share/keyrings/cloudflare-main.gpg >/dev/null
  echo "deb [signed-by=/usr/share/keyrings/cloudflare-main.gpg] https://pkg.cloudflare.com/cloudflared any main" \
    > /etc/apt/sources.list.d/cloudflared.list
  apt-get update -qq
  if apt-get install -y -qq cloudflared 2>&1 | tail -3 | sed 's/^/  /'; then
    good "cloudflared terpasang: $(cloudflared --version 2>&1 | head -1)"
  else
    warn "repo gagal, unduh biner langsung"
    ARCH=$(dpkg --print-architecture)
    curl -fsSL "https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-${ARCH}" \
      -o /usr/local/bin/cloudflared && chmod +x /usr/local/bin/cloudflared
    good "biner: $(cloudflared --version 2>&1 | head -1)"
  fi
fi

# ---------------------------------------------------------------- 4. WEB LOKAL
ok "4/6 WEB LOKAL (nginx + php-fpm) UNTUK TUJUAN TUNNEL"
[ -d "$ROOT" ] || { warn "$ROOT belum ada — membuat"; mkdir -p "$ROOT"; }
mkdir -p "$ROOT/uploads"
if [ ! -f "$ROOT/index.php" ]; then
  cat > "$ROOT/index.php" <<'PHP'
<?php
// Penanda sementara: ganti dengan kode PADI sebenarnya.
header('Content-Type: text/html; charset=utf-8');
echo "<!doctype html><meta charset=utf-8><title>PADI PJOK</title>";
echo "<h1>PADI PJOK aktif</h1>";
echo "<p>Origin CT 102 berjalan. PHP ".PHP_VERSION."</p>";
echo "<p>Waktu: ".date('c')."</p>";
PHP
  warn "index.php placeholder dibuat (kode PADI asli belum ada di $ROOT)"
fi
[ -f "$ROOT/koneksi.php" ] && good "kode PADI terdeteksi di $ROOT" || warn "koneksi.php belum ada di $ROOT"

rm -f /etc/nginx/sites-enabled/default
cat > /etc/nginx/sites-available/padipjok <<NGINX
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name ${DOMAIN} _;
    root ${ROOT};
    index index.php index.html;
    client_max_body_size 32M;
    location / { try_files \$uri \$uri/ /index.php?\$query_string; }
    location ~ \.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php${PHPVER}-fpm.sock;
        fastcgi_read_timeout 300;
    }
    location ~ /\.(env|git|ht) { deny all; return 404; }
    location ~* \.(sql|bak|log|sh|md)\$ { deny all; return 404; }
    location ^~ /uploads/ { location ~ \.php\$ { deny all; } }
}
NGINX
ln -sfn /etc/nginx/sites-available/padipjok /etc/nginx/sites-enabled/padipjok
nginx -t >/dev/null 2>&1 && systemctl reload nginx && good "nginx OK" || bad "nginx -t gagal"
printf '  uji lokal http://127.0.0.1/ -> '
curl -s -o /dev/null -w 'HTTP %{http_code}\n' --max-time 8 http://127.0.0.1/

# ---------------------------------------------------------------- 5. BUAT TUNNEL
ok "5/6 MEMBUAT TUNNEL (dengan nama '${TUNNELNAME}')"
mkdir -p /etc/cloudflared /root/.cloudflared
# Login cloudflared TIDAK bisa otomatis (butuh browser).
# Jadi kita pakai mode token: buat tunnel dari dasbor Cloudflare, lalu tempel token.
cat <<'INSTRUKSI'
  ---------------------------------------------------------------------------
  BUKA DASBOR CLOUDFLARE (sekali saja, di browser Anda):
    Zero Trust > Networks > Tunnels > Create a tunnel > Cloudflared
    Nama   : padi-pjok
    Simpan TUNNEL TOKEN yang muncul (panjang, format eyJ...)

  Lalu di CT 102 ini jalankan (ganti <TOKEN>):
    cloudflared service install <TOKEN>

  Konfigurasi ingress otomatis dibuat cloudflared dari dasbor:
    Public hostname : padipjok.pintarhub.com
    Service Type    : HTTP
    URL             : localhost:80
  ---------------------------------------------------------------------------
INSTRUKSI

# Simpan juga konfigurasi file (dipakai bila memilih mode kredensial, bukan token)
cat > /etc/cloudflared/config.yml.example <<YML
tunnel: <TUNNEL-UUID>
credentials-file: /etc/cloudflared/<TUNNEL-UUID>.json
ingress:
  - hostname: ${DOMAIN}
    service: http://localhost:80
  - service: http_status:404
YML
good "contoh konfigurasi: /etc/cloudflared/config.yml.example"

# ---------------------------------------------------------------- 6. RINGKASAN
ok "6/6 RINGKASAN UNTUK DIKIRIM KE CHAT"
echo "  ROOT        : $ROOT"
echo "  PHP         : $(php -v 2>/dev/null | head -1)"
echo "  nginx lokal : $(curl -s -o /dev/null -w '%{http_code}' --max-time 5 http://127.0.0.1/)"
echo "  cloudflared : $(command -v cloudflared || echo 'TIDAK ADA')"
echo "  hostname    : $(hostname)"
echo "  IP lokal    : $(hostname -I 2>/dev/null)"
echo "  UUID tunnel : (belum ada — dibuat dari dasbor Cloudflare)"
echo
warn "LANJUTKAN: buat tunnel di dasbor Cloudflare (lihat langkah 5 di atas),"
warn "lalu jalankan: cloudflared service install <TOKEN>"
