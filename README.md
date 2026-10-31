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
3. Isi `PADI_SETUP_TOKEN` (min. 20 karakter) di `.env`, lalu buka
   <http://localhost/PADI/instal/TOKEN> → membuat database `padi_pjok` + tabel.
   Alternatif tanpa web: `php setup_db.php` dari terminal (mode CLI).
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

## Akun guru pertama

Tidak ada password default di kode. Saat tabel `guru` masih kosong, aplikasi membuat
akun `guru` dengan password acak dan menuliskannya **sekali** ke
`.padi-guru-password.txt` di folder aplikasi (nama berawalan titik + `.txt`, sudah
diblokir `.htaccess`/Nginx), serta mencatat lokasinya di `error_log`.

Cara lain (disarankan): set `GURU_SEED_PASS` di `.env` sebelum akses pertama.

```env
GURU_SEED_PASS=password-kuat-anda
```

Setelah login, ganti password lalu hapus berkas `padi-guru-password.txt`.

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
verifikasi-nilai.php   # guru memverifikasi/mengoreksi nilai rubrik siswa
perkembangan-semester.php
laporan.php            # laporan & cetak
koneksi.php            # koneksi DB (baca .env)
auth.php               # helper autentikasi + migrasi tabel
setup_db.php           # pembuat skema database (akses via /instal/<TOKEN>)
perbaikan-keamanan-ct102.sh  # patch .env + nginx + token tunnel di server
deploy.sh              # skrip deploy otomatis (Proxmox/Debian)
```

## Tampilan 2 mode (otomatis)

Satu lapisan aset dipakai semua halaman lewat `ui-head.php`:

- **Mode PC** (≥1024px): navigasi bawah berubah jadi **sidebar kiri** 248px, konten melebar 1180px, daftar kartu jadi grid.
- **Mode mobile** (<1024px): tetap app-like — bottom-nav selebar layar (tanpa celah kiri/kanan meski layar >480px), `safe-area` iOS, target sentuh ≥40px.
- Deteksi otomatis via media query (`data-mode="pc|mobile"` di `<html>`), tanpa tombol manual.

### Tema "Cerah Tegas"

Lapisan tema di akhir `aset/app.css` (dimuat setelah CSS inline tiap halaman):

- Latar gradasi cerah biru → mint → kuning (`radial-gradient`, `background-attachment: fixed`).
- Border flat **2px**: kartu/hero/panel `#94A3B8`, topbar & navigasi `#1E293B`.
- Header (`.topbar`) selebar area konten di desktop — tanpa sisa ruang kiri/kanan.
- Tanpa bayangan, tanpa blur/glass; radius seragam 14px (kartu) dan 10px (kontrol).
- Kontrol (input/select/textarea) bergaris 2px + fokus outline; tombol utama bergaris tegas.

Berkas: `aset/app.css`, `aset/app.js`, `ui-head.php`.

### PWA (installable)
`aset/manifest.webmanifest` + `sw.js` (cache shell + halaman offline), ikon 192/512/maskable.
Tombol **Pasang aplikasi** muncul otomatis saat browser mendukung (`beforeinstallprompt`).
Untuk merilis aset baru, cukup ubah berkasnya — `?v=` diambil dari `filemtime`; naikkan `CACHE` di `sw.js` bila perlu memaksa update.

### Jadwal pelajaran & pantau siswa

- **Menu Jadwal** (guru, `jadwal-guru.php`): tambah satu jadwal atau **impor massal** dari CSV
  `kelas,hari,jam_mulai,jam_selesai,materi,catatan` (unduh template di halaman yang sama; unggah berkas atau tempel teks).
  Baris dengan `kelas + hari + jam_mulai` yang sama akan diperbarui, bukan digandakan.
- Tabel `jadwal` dibuat otomatis lewat `padi_pastikan_tabel_jadwal()` (aman untuk sesi lama, tanpa migrasi manual).
- **Dashboard siswa** menampilkan kartu “Jadwal Hari Ini” sesuai kelas siswa (`hari` = 1 Senin … 7 Minggu).
- **Pantau siswa** (`pantau-siswa.php`) berupa tabel dengan filter **kelas**, **nama**, dan **status tugas/asesmen**
  (kognitif, video praktik, psikomotor, afektif, penilaian rekan — sudah/belum), plus ringkasan jumlah.
- **Rekap penilaian** (`rekap-penilaian.php`) berupa tabel per indikator (Kognitif, Afektif, Psikomotor, Diri/Rekan,
  Rata-rata, Predikat) dengan filter kelas + nama siswa; rubrik 0–20 dikonversi ke skala 0–100.
  Tersedia **Ekspor CSV** dan **Ekspor Excel** yang mengikuti filter aktif.
- **Data siswa** (`data-siswa.php`) berupa tabel per kelas (No, NIS, Nama, Aksi) dengan filter kelas + nama,
  tombol Tambah Kelas / Import CSV, serta Ekspor & Cetak per kelas.

### Cetak / Simpan PDF

Halaman data guru memakai `ui-cetak.php` + `aset/cetak.css`:

- `padi_cetak_siap('JUDUL', 'subjudul', ['orientasi' => 'landscape'])` di awal `<body>` → memuat CSS cetak,
  kop surat (logo + instansi + nama sekolah + alamat/NPSN) dan tombol **Cetak / Simpan PDF**
  (dipindahkan `app.js` ke atas konten agar tidak menutupi form).
- `padi_cetak_ttd()` sebelum `</body>` → blok tanda tangan: Kepala Sekolah (kiri) dan Guru Mata Pelajaran PJOK
  (kanan, nama dari akun guru yang login) + NIP.
- Saat dicetak: topbar, navigasi, filter, tombol, dan bayangan dihilangkan; tabel bergaris hitam;
  kartu berubah menjadi blok polos. Halaman yang memakai: rekap penilaian, rekap per siswa, pantau siswa,
  jadwal, nilai, data siswa, laporan, verifikasi nilai, perkembangan semester.
- Identitas sekolah diisi di `.env`: `SEKOLAH_INSTANSI`, `SEKOLAH_NAMA`, `SEKOLAH_ALAMAT`, `SEKOLAH_NPSN`,
  `SEKOLAH_KOTA`, `TAHUN_PELAJARAN`, `KEPALA_SEKOLAH`, `KEPALA_SEKOLAH_NIP`, `GURU_NIP`.

## Keamanan

- Kredensial DB **hanya** di `.env` / environment variable (bukan di kode).
- `.htaccess` (Apache) dan Nginx memblokir `.env`, dotfile, `.git`, `*.sql`, `*.bak`, `*.log`, `*.sh`, `*.md`, `*.txt`.
- Berkas internal (`auth.php`, `koneksi.php`, `pesan-db.php`, `setup_db.php`, …) → 404 bila dibuka langsung.
- Alamat samar: `/instal/<token>` (installer), `/sesi/<token>` (siswa gabung), `/masuk`, `/guru`, `/siswa`.
- Password disimpan sebagai hash (`password_hash`); tidak ada password default di kode.
- Nilai rubrik siswa tidak dipakai langsung: guru memverifikasi di `verifikasi-nilai.php`, dan rekap memakai `COALESCE(nilai_guru, nilai_rubrik)`.
- Login/token dibatasi percobaan (throttle) dan formulir memakai token CSRF.

## Lisensi

Internal — SMAN 1 Sumber / PintarHub.
