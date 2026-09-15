# PADI-PJOK

Aplikasi penilaian **PJOK** (Pendidikan Jasmani, Olahraga, dan Kesehatan) berbasis **token** —
guru membuat sesi + token, siswa bergabung lalu mengerjakan penilaian kognitif, afektif,
psikomotor, dan penilaian antar-rekan.

Live: <https://padipjok.pintarhub.com>

---

## Fitur

| Aktor | Fitur |
|---|---|
| **Guru** | Login, buat token sesi, pantau siswa, input nilai (kognitif/afektif/psikomotor/rekan), rekap & laporan, cetak/ekspor, feedback |
| **Siswa** | Login (nomor induk + password) atau gabung via token, kerjakan penilaian, lihat perkembangan semester |

## Stack

- PHP 8.2+ (mysqli), MySQL/MariaDB
- HTML/CSS/JS vanilla (tanpa build step)
- Nginx + PHP-FPM (produksi)

## Menjalankan lokal (XAMPP)

1. Letakkan folder ini di `C:\xampp\htdocs\PADI`
2. Nyalakan Apache + MySQL dari XAMPP Control Panel
3. Buka <http://localhost/PADI/setup_db.php> → membuat database `padi_pjok` + tabel
4. Buka <http://localhost/PADI/>

Tanpa `.env`, koneksi otomatis memakai default XAMPP (`root`, tanpa password).
Untuk kredensial lain, salin `.env.example` → `.env` lalu isi.

## Deploy ke server (Proxmox LXC/VM, Debian 12 / Ubuntu 22.04+)

```bash
# di dalam server, sebagai root
git clone https://github.com/wsejagad-arch/PADI-PJOK.git /root/padi-deploy
cd /root/padi-deploy
bash deploy.sh
```

Skrip akan otomatis: install Nginx + PHP-FPM + MariaDB → buat database & user →
clone kode ke `/var/www/padipjok` → buat `.env` → buat skema DB → tulis vhost Nginx →
atur izin → tampilkan kredensial + langkah Cloudflare.

Variabel opsional:

```bash
DOMAIN=padipjok.pintarhub.com APP_DIR=/var/www/padipjok DB_NAME=padi_pjok bash deploy.sh
```

## Konfigurasi Cloudflare

1. **DNS** → Add record → `A` → Name `padipjok`, Content `<IP publik server>`, Proxy **Proxied** (awan oranye)
2. **SSL/TLS → Overview** → mode **Flexible** (atau **Full** bila ada origin certificate)
3. **SSL/TLS → Edge Certificates** → **Always Use HTTPS: ON**
4. Tunggu 1–5 menit, buka `https://padipjok.pintarhub.com`

## Konfigurasi (.env)

```ini
DB_HOST=localhost
DB_PORT=3306
DB_NAME=padi_pjok
DB_USER=padi_user
DB_PASS=<password kuat>
APP_TZ=Asia/Jakarta
APP_URL=https://padipjok.pintarhub.com
APP_ENV=production
```

`.env` **tidak** ikut ter-commit (lihat `.gitignore`).

## Akun bawaan

| Peran | Username | Password | Catatan |
|---|---|---|---|
| Guru | `guru` | `guru123` | **WAJIB diganti setelah login pertama** |

## Struktur berkas

```
index.php              # halaman masuk
login-guru.php         # login guru
login-siswa.php        # login siswa (nomor induk + password / token)
dashboard-guru.php     # beranda guru
dashboard-siswa.php    # beranda siswa
buat-token.php         # guru membuat token sesi
input-token.php        # siswa gabung sesi via token
pantau-siswa.php       # monitoring siswa aktif
penilaian-*.php        # form penilaian (kognitif/afektif/psikomotor/rekan)
rekap-penilaian*.php   # rekap nilai
perkembangan-semester.php
laporan.php            # laporan & cetak
koneksi.php            # koneksi DB (baca .env)
auth.php               # helper autentikasi + migrasi tabel
setup_db.php           # pembuat skema database
deploy.sh              # skrip deploy otomatis (Proxmox/Debian)
```

## Keamanan

- Kredensial DB **hanya** di `.env` / environment variable (bukan di kode).
- Nginx memblokir akses ke `.env`, `.git`, `*.sql`, `*.bak`, `*.log`, `*.sh`.
- Password disimpan sebagai hash (`password_hash`).
- Selalu ubah password guru bawaan & gunakan password DB yang kuat di produksi.

## Lisensi

Internal — SMAN 1 Sumber / PintarHub.
