#!/bin/bash
# =============================================================================
#  PADI-PJOK — Pasang Cloudflare Tunnel permanen
#  Mengarahkan padipjok.pintarhub.com  →  CT 102 (http://localhost:80)
#  Menimpa wildcard DNS *.pintarhub.com milik platform pusat.
#
#  PRASYARAT: sudah menjalankan `cloudflared tunnel login` (cert.pem ada)
#  Pemakaian: bash pasang-tunnel.sh
# =============================================================================
set -euo pipefail

TUN="${TUN:-padipjok}"
DOM="${DOM:-padipjok.pintarhub.com}"
CERT="/root/.cloudflared/cert.pem"

G='\033[0;32m'; Y='\033[1;33m'; R='\033[0;31m'; N='\033[0m'
info(){ echo -e "${G}[OK]${N} $*"; }
warn(){ echo -e "${Y}[..]${N} $*"; }
fail(){ echo -e "${R}[!!]${N} $*" >&2; exit 1; }

command -v cloudflared >/dev/null || fail "cloudflared belum terpasang."
[ -f "$CERT" ] || fail "Belum login. Jalankan dulu: cloudflared tunnel login"

# --------- 1. Buat tunnel (bila belum ada) -----------------------------------
if cloudflared tunnel list 2>/dev/null | grep -qw "$TUN"; then
  info "Tunnel '$TUN' sudah ada."
else
  warn "Membuat tunnel '$TUN'..."
  cloudflared tunnel create "$TUN" >/dev/null
  info "Tunnel '$TUN' dibuat."
fi

# --------- 2. Ambil Tunnel ID ------------------------------------------------
TID="$(cloudflared tunnel list 2>/dev/null | awk -v t="$TUN" '$2==t{print $1}' | head -1)"
[ -n "$TID" ] || fail "Tidak dapat menemukan Tunnel ID untuk '$TUN'."
info "Tunnel ID: $TID"

CRED="/root/.cloudflared/${TID}.json"
[ -f "$CRED" ] || fail "Kredensial tunnel tidak ada: $CRED"

# --------- 3. Routing DNS (menimpa wildcard) ---------------------------------
warn "Membuat DNS route ${DOM} → tunnel..."
cloudflared tunnel route dns "$TUN" "$DOM" 2>&1 | tail -3 || \
  warn "DNS route mungkin sudah ada — lanjut."
info "DNS route selesai."

# --------- 4. Konfigurasi tunnel ---------------------------------------------
mkdir -p /etc/cloudflared
cp -f "$CRED" /etc/cloudflared/
cat > /etc/cloudflared/config.yml <<CFG
tunnel: ${TID}
credentials-file: /etc/cloudflared/${TID}.json
ingress:
  - hostname: ${DOM}
    service: http://localhost:80
    originRequest:
      noTLSVerify: true
      connectTimeout: 30s
  - service: http_status:404
CFG
info "Konfigurasi ditulis: /etc/cloudflared/config.yml"

# --------- 5. Layanan systemd (permanen + auto-start) ------------------------
cat > /etc/systemd/system/cloudflared-padipjok.service <<SVC
[Unit]
Description=Cloudflare Tunnel PADI-PJOK (${DOM})
After=network-online.target
Wants=network-online.target

[Service]
Type=simple
User=root
ExecStart=/usr/bin/cloudflared --no-autoupdate --config /etc/cloudflared/config.yml tunnel run
Restart=always
RestartSec=5
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
SVC

systemctl daemon-reload
systemctl enable cloudflared-padipjok >/dev/null 2>&1
systemctl restart cloudflared-padipjok
sleep 8

# --------- 6. Verifikasi -----------------------------------------------------
if systemctl is-active --quiet cloudflared-padipjok; then
  info "Layanan cloudflared-padipjok AKTIF (auto-start saat boot)."
else
  echo "--- log layanan ---"
  journalctl -u cloudflared-padipjok -n 25 --no-pager
  fail "Layanan gagal start."
fi

# hentikan quick tunnel bila masih jalan (agar tidak dobel)
pkill -f 'cloudflared tunnel --url http://localhost:80' 2>/dev/null || true

echo
warn "Menguji akses publik..."
for i in 1 2 3 4 5; do
  CODE="$(curl -s -o /dev/null -w '%{http_code}' --max-time 15 "https://${DOM}/" || echo 000)"
  echo "  percobaan $i → HTTP $CODE"
  [ "$CODE" = "200" ] && break
  sleep 6
done

echo
if [ "$CODE" = "200" ]; then
  echo -e "${G}============================================================${N}"
  echo -e "${G} SELESAI — https://${DOM} TERHUBUNG KE CT 102${N}"
  echo -e "${G}============================================================${N}"
else
  echo -e "${Y}============================================================${N}"
  echo -e "${Y} DNS/tunnel terpasang, tetapi HTTP = $CODE${N}"
  echo -e "${Y} Tunggu 1-3 menit (propagasi Cloudflare), lalu cek ulang:${N}"
  echo -e "${Y}   curl -sI https://${DOM} | head -3${N}"
  echo -e "${Y}============================================================${N}"
fi
echo "Status layanan : systemctl status cloudflared-padipjok --no-pager"
echo "Log            : journalctl -u cloudflared-padipjok -n 30 --no-pager"
