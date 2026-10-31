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

## Multi-tenant (beberapa sekolah dalam satu instalasi)

Satu database, setiap tabel inti punya kolom `sekolah_id`, dan sekolah aktif ditentukan
berdasarkan (urutan prioritas):

1. `?sekolah=slug` — hanya di lokal/dev atau untuk super-admin (tombol **Masuk** di halaman kelola sekolah)
2. sekolah milik akun yang login (`guru.sekolah_id` / `master_siswa.sekolah_id`)
3. subdomain host: `sman1.<BASE_DOMAIN>` → slug `sman1`
4. `TENANT_DEFAULT` → sekolah aktif pertama

Tabel yang terisolasi: `guru`, `master_siswa`, `master_kelas`, `sesi`, `jadwal`
(penilaian mengikuti siswa/sesi sehingga otomatis ikut terisolasi). Kunci unik kelas &
jadwal menyertakan `sekolah_id` agar nama kelas sama boleh dipakai lintas sekolah.

- Skema disiapkan otomatis oleh [tenant.php](tenant.php) (dipanggil dari `koneksi.php`).
- **Kelola Sekolah** ([sekolah-admin.php](sekolah-admin.php)) untuk menambah/mengubah sekolah,
  mengisi identitas kop (instansi, alamat, NPSN, kepala sekolah + NIP), aktif/nonaktif, dan
  berpindah konteks sekolah. Hanya untuk guru dengan `guru.is_super = 1`
  (akun guru terlama otomatis menjadi super-admin).
- Identitas kop cetak kini diambil dari data sekolah aktif, `.env` hanya fallback.

```env
BASE_DOMAIN=padipjok.pintarhub.com
TENANT_DEFAULT=utama
```

## Login guru dengan akun Belajar.id

Login memakai Google OAuth 2.0 / OpenID Connect (`oauth.php`, `oauth-belajar.php`,
`oauth-callback.php`). Tombol **Masuk dengan Belajar.id** muncul di [login-guru.php](login-guru.php) bila
kredensial sudah diisi; bila belum, tombol tampil nonaktif dengan petunjuk.

```env
GOOGLE_CLIENT_ID=xxxx.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=xxxx
GOOGLE_REDIRECT_URI=            # opsional, default https://<host>/oauth-callback.php
BELAJAR_ID_DOMAIN=belajar.id,*.belajar.id   # boleh beberapa, pisah koma; mis. juga *.sch.id
BELAJAR_ID_AUTO_DAFTAR=1        # 1 = akun guru otomatis dibuat saat login pertama
BELAJAR_ID_HD=                  # opsional, batasi consent ke satu domain
```

Langkah di Google Cloud Console: buat **OAuth client ID** tipe *Web application*, daftarkan
*Authorized redirect URI* `https://<domain>/oauth-callback.php` (tambahkan juga versi
subdomain bila memakai multi-tenant), lalu salin Client ID/Secret ke `.env` server.
Akun dicari berdasarkan `guru.google_sub` atau `guru.email`; guru tanpa akun dibuatkan
otomatis di sekolah aktif dengan status `aktif` dan `is_super = 0`.

## Peran pengguna (guru)

| Peran | Kewenangan |
|---|---|
| **super-admin** (`guru.is_super=1`) | Semua sekolah: tambah/ubah/nonaktifkan sekolah, kelola semua akun guru, reset password massal, hapus akun, pantau status server ([admin-server.php](admin-server.php)) |
| **admin sekolah** (`guru.is_admin=1`) | Sekolahnya sendiri: [admin-pengguna.php](admin-pengguna.php) (tambah/ubah/nonaktifkan/reset/hapus akun guru), ubah identitas kop sekolah ([sekolah-admin.php](sekolah-admin.php)) |
| **guru** | Data kelas sendiri: siswa, sesi/token, jadwal, penilaian, rekap, laporan |

- Guru yang mendaftar/masuk lewat **Belajar.id** otomatis menjadi **admin sekolah** pada tenant tempat ia masuk.
- Setiap sekolah selalu punya minimal satu admin (guru pertama otomatis diangkat; admin terakhir tidak bisa dihapus).
- Reset password: per akun (tombol **Reset**) atau massal (pilih akun → **Reset Terpilih** / **Reset Semua**). Password sementara ditampilkan **sekali** di halaman.
- Reset password siswa: per siswa (**Reset**) atau seluruh kelas (**Reset Password Kelas**) di [data-siswa.php](data-siswa.php) — siswa masuk lagi memakai token sesi kelas aktif.
- Halaman legal untuk OAuth: [privasi.php](privasi.php) dan [ketentuan.php](ketentuan.php).

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
tenant.php             # multi-tenant: tabel sekolah, resolusi sekolah aktif
sekolah-admin.php      # kelola sekolah (super-admin)
oauth.php              # helper login Belajar.id (Google OAuth)
oauth-belajar.php      # mulai login Belajar.id
oauth-callback.php     # callback Google → sesi guru
admin-pengguna.php     # kelola akun guru (admin sekolah / super-admin)
admin-server.php       # status server & ringkasan tenant (super-admin)
privasi.php            # kebijakan privasi (OAuth consent)
ketentuan.php          # syarat & ketentuan
koneksi.php            # koneksi DB (baca .env) + migrasi tenant
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
  tombol Tambah Kelas / Import CSV, serta Ekspor & Cetak per kelas. Kolom **Aksi** berisi
  **Edit** (modal nama/NIS/kelas, siswa bisa dipindah kelas), **Reset** (password siswa dikosongkan →
  siswa masuk lagi memakai token sesi kelas aktif lalu membuat password baru), dan **Hapus**.
  Di layar < 640 px tiap baris berubah menjadi kartu bertumpuk agar tanpa gulir horizontal.
- **Laporan pembelajaran** (`laporan.php`) = raport kelas + raport individu dengan filter kelas dan pilihan siswa:
  ringkasan rata-rata indikator, sebaran predikat, daftar remedial/pengayaan/data belum lengkap, narasi
  **analisis AI ringan** (aturan kriteria: ≥85 Sangat Baik · 70–84 Baik · 50–69 Cukup · <50 Kurang · tuntas ≥70),
  rekomendasi tindak lanjut per indikator, Ekspor CSV, dan cetak PDF berkop.

### Cetak / Simpan PDF

Halaman data guru memakai `ui-cetak.php` + `aset/cetak.css`:

- `padi_cetak_siap('JUDUL', 'subjudul', ['orientasi' => 'landscape'])` di awal `<body>` → memuat CSS cetak,
  kop surat (logo + instansi + nama sekolah + alamat/NPSN) dan tombol **Cetak / Simpan PDF**
  (dipindahkan `app.js` ke atas konten agar tidak menutupi form).
- `padi_cetak_ttd()` sebelum `</body>` → blok tanda tangan: Kepala Sekolah (kiri) dan Guru Mata Pelajaran PJOK
  (kanan, nama + NIP dari akun guru yang login) + NIP.
- Nama & NIP guru diisi sekali di **Profil → “Nama & NIP untuk Tanda Tangan Laporan”**
  (tersimpan di tabel `guru` kolom `nama` + `nip`, sesi ikut diperbarui). Cetakan memakai urutan
  sesi → database → `.env` (`GURU_NAMA`/`GURU_NIP`), jadi semua kop tanda tangan otomatis sinkron.
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
