# Panduan Cepat — Pasang PADI-PJOK di Proxmox + Cloudflare

Domain: **padipjok.pintarhub.com**

---

## Langkah 1 — Siapkan kontainer di Proxmox

Di **Proxmox Web UI** (`https://<IP-Proxmox>:8006`):

1. Klik **Create CT** (LXC) — lebih ringan daripada VM.
2. **Template**: pilih `debian-12-standard` atau `ubuntu-22.04-standard`.
3. **Disk**: minimal 8 GB. **RAM**: 1 GB. **CPU**: 1 core.
4. **Network**: `vmbr0`, IPv4 DHCP (atau static di LAN Anda).
5. Selesai → **Start** kontainer → buka **Console**.

> Kalau butuh IP publik langsung, di langkah Network pilih mode **Bridged (vmbr0)**
> dan pastikan router meneruskan port 80/443 ke IP kontainer itu.

## Langkah 2 — Jalankan skrip deploy

Di **Console** kontainer (login sebagai `root`):

```bash
apt-get update -qq && apt-get install -y -qq git curl
git clone https://github.com/wsejagad-arch/PADI-PJOK.git /root/padi
cd /root/padi
bash deploy.sh
```

Skrip berjalan sendiri sampai selesai (install Nginx, PHP-FPM, MariaDB, buat
database, buat `.env`, buat skema, tulis vhost, atur izin).

Di akhir, skrip menampilkan:
- **password database** yang digenerate → **catat**, tidak ditampilkan lagi
- **IP publik** server → dipakai di langkah Cloudflare

## Langkah 3 — Cloudflare

1. Login <https://dash.cloudflare.com> → domain **pintarhub.com**
2. **DNS → Records → Add record**:
   - Type: `A`
   - Name: `padipjok`
   - IPv4 address: `<IP publik dari langkah 2>`
   - Proxy status: **Proxied** (awan oranye)
   - TTL: Auto
3. **SSL/TLS → Overview** → pilih **Flexible**
4. **SSL/TLS → Edge Certificates** → **Always Use HTTPS**: ON
5. Tunggu 1–5 menit.

## Langkah 4 — Verifikasi

Di server:

```bash
bash /root/padi/cek-deploy.sh
```

Semua baris harus `[OK]`. Lalu buka:

```
https://padipjok.pintarhub.com
```

## Langkah 5 — Amankan aplikasi

1. Login guru: user `guru`, password `guru123`
2. **Segera ganti password** guru bawaan
3. Buat kelas & siswa lewat menu Data Siswa

---

## Jika ada masalah

| Gejala | Penyebab umum | Perbaikan |
|---|---|---|
| Cloudflare **521 / 522** | Nginx mati atau IP di DNS salah | `systemctl restart nginx`; cek IP di DNS |
| Cloudflare **525 / 526** (SSL) | Mode SSL bukan *Flexible* | Ubah SSL/TLS → Overview → Flexible |
| **502 Bad Gateway** | PHP-FPM mati | `systemctl restart php8.2-fpm` (sesuaikan versi) |
| Halaman kosong / error DB | `.env` salah | `cat /var/www/padipjok/.env` lalu perbaiki |
| Redirect berulang | SSL Full tapi origin belum ada cert | Pakai Flexible, atau pasang origin cert |
| Tidak bisa upload besar | batas PHP | naikkan `upload_max_filesize` & `post_max_size` di `php.ini` |

Log yang berguna:

```bash
tail -50 /var/log/nginx/padipjok.pintarhub.com.error.log
journalctl -u nginx -n 30 --no-pager
php -r 'include "/var/www/padipjok/koneksi.php"; var_dump($conn ? "DB OK" : "DB GAGAL");'
```

## Update aplikasi setelah ada perubahan

```bash
cd /var/www/padipjok
git pull
chown -R www-data:www-data .
systemctl reload nginx php8.2-fpm
```

Atau otomatis: isi secret di **GitHub → Settings → Secrets and variables → Actions**
(`DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_SSH_KEY`, `DEPLOY_PATH`) — maka setiap
`git push` ke `main` langsung ter-deploy.

## Akses SSH dari komputer Anda

Tambahkan public key Anda ke server agar tidak perlu password:

```bash
# di server
mkdir -p ~/.ssh && chmod 700 ~/.ssh
nano ~/.ssh/authorized_keys
# tempel isi: C:\Users\sman1\.ssh\ai_agent_ed25519.pub
chmod 600 ~/.ssh/authorized_keys
```

Public key Anda:

```
ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIP3bRwgp4RmJtjxbnzFBxP61cnjlNk2EST3dF3+b1s6e sman1@windows-ai-agent
```
