#!/usr/bin/env bash
# ============================================================================
# DIAGNOSA + PERBAIKAN CT 102  —  jalankan DI DALAM CT 102
# Cara masuk: console Proxmox ->  pct enter 102
# Tempel  :  bash perintah-ct102.sh
# Aman    : hanya membaca status + tes lokal (read-only), tidak mengubah apa pun
# ============================================================================
set -u
ok(){ printf '\n\033[1;36m===== %s =====\033[0m\n' "$1"; }
bad(){ printf '\033[31m[X] %s\033[0m\n' "$1"; }
good(){ printf '\033[32m[v] %s\033[0m\n' "$1"; }

# ---------------------------------------------------------------- 1. IDENTITAS
ok "1/9 IDENTITAS CT"
echo "hostname : $(hostname)"
echo "os       : $( . /etc/os-release 2>/dev/null; echo "$PRETTY_NAME" )"
echo "kernel   : $(uname -r)"
echo "uptime   : $(uptime -p 2>/dev/null || uptime)"
echo "IP lokal :"; ip -4 -o addr show scope global | awk '{print "           "$2" "$4}'
echo "RAM      : $(free -h | awk '/^Mem:/{print $3" / "$2}')"
echo "Disk     :"; df -h / | awk 'NR==2{print "           "$3" / "$2" ("$5")"}'

# ---------------------------------------------------------------- 2. LAYANAN
ok "2/9 LAYANAN (nginx / php-fpm / mariadb)"
for s in nginx php*-fpm mariadb mysql; do
  if systemctl list-unit-files 2>/dev/null | grep -q "^${s}\.service"; then
    st=$(systemctl is-active "$s" 2>/dev/null)
    en=$(systemctl is-enabled "$s" 2>/dev/null)
    if [ "$st" = active ]; then good "$s: $st (boot: $en)"; else bad "$s: $st (boot: $en)"; fi
  fi
done
echo "--- versi ---"
nginx -v 2>&1 | sed 's/^/  /'
(php -v 2>/dev/null | head -1) | sed 's/^/  /'
(mariadb --version 2>/dev/null || mysql --version 2>/dev/null) | sed 's/^/  /'

# ---------------------------------------------------------------- 3. PORT
ok "3/9 PORT YANG MENDENGAR (80/443/3306/8000/8080)"
ss -lntp 2>/dev/null | awk 'NR==1 || /:(80|443|3306|8000|8080)\s/' | sed 's/^/  /'

# ---------------------------------------------------------------- 4. KODE APLIKASI
ok "4/9 KODE APLIKASI"
CANDIDATES="/var/www/padipjok /var/www/PADI /var/www/html/padipjok /var/www/html/PADI /opt/padipjok /srv/padipjok"
ROOT=""
for d in $CANDIDATES; do
  [ -d "$d" ] && { ROOT="$d"; good "ditemukan: $d"; }
done
if [ -z "$ROOT" ]; then
  bad "tidak ada di daftar umum; cari manual:"
  find /var/www /srv /opt /home -maxdepth 4 -name 'index.php' -o -maxdepth 4 -name 'koneksi.php' 2>/dev/null | head -20 | sed 's/^/  /'
else
  echo "--- isi $ROOT ---"
  ls -la "$ROOT" 2>/dev/null | head -40 | sed 's/^/  /'
  echo "--- berkas kunci ---"
  for f in index.php koneksi.php auth.php .env; do
    if [ -e "$ROOT/$f" ]; then
      good "$f ($(stat -c%s "$ROOT/$f") byte, $(stat -c%U:%G "$ROOT/$f"))"
    else
      bad "$f TIDAK ADA"
    fi
  done
fi

# ---------------------------------------------------------------- 5. NGINX
ok "5/9 KONFIGURASI NGINX"
ls -la /etc/nginx/sites-enabled/ 2>/dev/null | sed 's/^/  /'
echo "--- server_name & root yang aktif ---"
grep -rhE '^\s*(server_name|root|listen)\s' /etc/nginx/sites-enabled/ 2>/dev/null | sed 's/^/  /'
echo "--- uji sintaks nginx ---"
if nginx -t 2>&1 | sed 's/^/  /'; then :; fi

# ---------------------------------------------------------------- 6. UJI LOKAL
ok "6/9 UJI LOKAL (localhost, lewati Cloudflare)"
for u in "http://127.0.0.1/" "http://localhost/"; do
  printf '  %-28s -> ' "$u"
  curl -s -o /dev/null -w 'HTTP %{http_code}  %{size_download}B  %{time_total}s\n' --max-time 8 "$u" 2>&1
done
echo "--- judul halaman yang dilayani ---"
curl -s --max-time 8 http://127.0.0.1/ 2>/dev/null | grep -oiE '<title>[^<]*</title>' | head -1 | sed 's/^/  /'

# ---------------------------------------------------------------- 7. DATABASE
ok "7/9 DATABASE"
if [ -f "$ROOT/.env" ]; then
  echo "--- kredensial .env (nilai disamarkan) ---"
  grep -E '^(DB_|APP_)' "$ROOT/.env" 2>/dev/null | sed -E 's/(PASS|KEY|SECRET)=.*/\1=***/I' | sed 's/^/  /'
  DBU=$(grep -E '^DB_USER=' "$ROOT/.env" | cut -d= -f2- | tr -d '"'"'"'')
  DBP=$(grep -E '^DB_PASS=' "$ROOT/.env" | cut -d= -f2- | tr -d '"'"'"'')
  DBN=$(grep -E '^DB_NAME=' "$ROOT/.env" | cut -d= -f2- | tr -d '"'"'"'')
  if command -v mysql >/dev/null; then
    echo "--- uji koneksi & hitung tabel di '$DBN' ---"
    mysql -u"$DBU" -p"$DBP" -e "SELECT COUNT(*) AS jumlah_tabel FROM information_schema.tables WHERE table_schema='$DBN'; SHOW TABLES FROM \`$DBN\`;" 2>&1 | sed 's/^/  /' | head -25
  fi
else
  bad "tidak ada .env di $ROOT"
fi

# ---------------------------------------------------------------- 8. CLOUDFLARE
ok "8/9 STATUS CLOUDFLARE TUNNEL (bila dipakai)"
if command -v cloudflared >/dev/null; then
  cloudflared --version | sed 's/^/  /'
  systemctl is-active cloudflared 2>/dev/null | sed 's/^/  cloudflared.service: /'
  ls -la /etc/cloudflared/ 2>/dev/null | sed 's/^/  /'
  if [ -f /etc/cloudflared/config.yml ]; then
    echo "--- /etc/cloudflared/config.yml ---"
    sed -E 's/(secret|token|credentials-file).*/\1: ***/I' /etc/cloudflared/config.yml | sed 's/^/  /'
  fi
else
  echo "  cloudflared tidak terpasang di CT ini (DNS mungkin langsung ke IP publik)"
fi

# ---------------------------------------------------------------- 9. LOG
ok "9/9 GALAT TERAKHIR"
echo "--- nginx error.log (10 baris terakhir) ---"
tail -10 /var/log/nginx/error.log 2>/dev/null | sed 's/^/  /'
echo "--- php-fpm log ---"
tail -10 /var/log/php*-fpm.log 2>/dev/null | sed 's/^/  /'
echo "--- journal mariadb (5 baris) ---"
journalctl -u mariadb -n 5 --no-pager 2>/dev/null | sed 's/^/  /'

printf '\n\033[1;33m>>> SELESAI. Salin SELURUH keluaran di atas dan tempel ke chat.\033[0m\n'
