# Riwayat Pengeditan PADI-PJOK

Dokumen ini mencatat riwayat pengeditan kode terakhir beserta sebab, bukti, dan hasil
verifikasinya. Ditulis agar sesi berikutnya tidak perlu mengulang penyelidikan.

- **Proyek:** PADI-PJOK (Penilaian Autentik Digital Integratif untuk PJOK)
- **Lokasi:** `c:\xampp\htdocs\PADI`
- **Repo:** https://github.com/wsejagad-arch/PADI-PJOK (branch `main`)
- **Domain:** https://padipjok.pintarhub.com
- **Stack:** PHP native + MySQL/MariaDB (XAMPP), Nginx/PHP-FPM di server, Cloudflare Tunnel
- **Pembaruan terakhir:** 16 September 2026

---

## 1. Ringkasan kronologis (terbaru di atas)

| # | Tanggal | Commit | Perubahan | Status |
|---|---|---|---|---|
| 4 | 16 Sep 2026 | `6af66f9` | Kembalikan halaman utama ke tampilan awal (header sambutan + tombol Login Guru/Siswa) | ✅ terverifikasi |
| 3 | 16 Sep 2026 | `f8cf6a9` | Perbaiki `deploy.sh` agar satu paket hilang tidak menggagalkan deploy | ✅ terverifikasi |
| 2 | 16 Sep 2026 | `8411c0a` | Cegah halaman putih setelah login (guard per-peran, sesi berbagi, tahan DB mati) | ✅ terverifikasi |
| 1 | 15 Sep 2026 | `c18c6e6` | Skrip pasang Cloudflare Tunnel permanen (CT 102) | ✅ |

---

## 2. Perbaikan BLANK PUTIH setelah login — commit `8411c0a` (16 Sep 2026)

### Gejala
Login guru berhasil (`POST login-guru.php` → 302 ke `dashboard-guru.php`), tetapi
`dashboard-guru.php` membalas **HTTP 500 dengan body 0 byte** — halaman putih tanpa petunjuk.
Halaman login siswa juga terdampak.

### Penyebab (dua lapis)

**Lapis 1 — kode aplikasi**

| Berkas | Masalah |
|---|---|
| `verifikasi-token.php` | Memakai `session_start()` mentah + `require 'koneksi.php'` relatif. Bila ada satu saja teks/JSON keluar sebelum `session_start()`, PHP mematikan skrip → body kosong. |
| `koneksi.php` | `die()` saat koneksi DB gagal → halaman putih/500 ketika MySQL belum siap. |
| `auth.php` | Guard mengirim pengguna ke halaman login peran yang salah. |
| `index.php` | Menebak peran dengan memvalidasi input ke tabel `master_siswa`, sehingga login guru selalu gagal. |

**Lapis 2 — produksi tertinggal versi (penyebab utama 500)**

Dibandingkan produksi vs lokal:

| berkas | produksi | lokal | keterangan |
|---|---|---|---|
| `setup_db.php` | 507 B | 4.814 B | ❌ |
| `verifikasi-token.php` | 65 B | 1.407 B | ❌ masih berisi `padi_muat_env` (artefak skrip deploy lama) |
| `login-guru.php` | 9.764 B | 11.188 B | ❌ |
| `koneksi.php` | **0 B** | 4.250 B | ❌ tanpa penjaga idempoten |
| `auth-boot.php` | **404** | ada | ❌ belum ada |
| `pesan-db.php` | **404** | ada | ❌ belum ada |

Penyebab: `deploy.sh` meng-clone dari GitHub, tetapi deploy terakhir **gagal di tengah jalan**
karena `php8.2-json` tidak tersedia → direktori aplikasi tidak pernah ditulis ulang, sehingga
situs tetap menyajikan kode lama (masih bug "Cannot redeclare").

### Perbaikan kode

| Berkas | Perubahan |
|---|---|
| `koneksi.php` | Penjaga idempoten `PADI_KONEKSI_SELESAI` (berlaku untuk semua `require`, bukan hanya lewat `auth`); pengalih `.env` (produksi) vs `.env.local` (lokal); `padi_coba_koneksi()` — **tidak lagi `die()`** saat DB mati, galat dicatat ke log; `display_errors=0`; variabel `$padi_db_error`. |
| `auth-boot.php` **(baru)** | Titik masuk aman: memuat `koneksi.php` + `auth.php`, lalu `padi_berbagi_sesi()` (cookie `path=/` agar sesi tidak hilang bila domain disajikan dari folder lain) + `padi_mulai_sesi()`. |
| `auth.php` | `padi_mulai_sesi()`; `padi_berbagi_sesi()`; `padi_url_login($tipe)`; `padi_kembali()` — redirect anti-putih (header + meta refresh + tautan manual bila header gagal); guard `wajibLoginGuru()` / `wajibLoginSiswa()` memakai pengalih peran yang benar. |
| `pesan-db.php` **(baru)** | Halaman panduan saat database belum siap (langkah perbaikan + tautan), menggantikan HTTP 500. |
| `index.php` | Portal satu pintu: kartu **Guru** → `login-guru.php` (tanpa menebak lewat tabel siswa); kartu **Siswa** → form NIS + password. `?menu=1` untuk tetap di portal meski sudah login. |
| `verifikasi-token.php` | Ditulis ulang: `require auth-boot.php`, hapus `session_start()` ganda, jawaban JSON konsisten `{success, token, materi, kelas}`. |

### Perbaikan skrip deploy — commit `f8cf6a9`

| Perubahan | Alasan |
|---|---|
| Tahap tarik kode dipindah ke **tahap 2** (sebelum konfigurasi layanan) | Kegagalan langkah lanjutan tidak lagi meninggalkan versi lama |
| `json` dikeluarkan dari daftar ekstensi wajib; hanya dipasang bila tersedia | `php8.2-json` menyatu ke core sejak PHP 8 |
| Verifikasi binari PHP (`command -v php` + `php -v`) sebelum lanjut | Deteksi dini bila pemasangan PHP gagal |
| Bila direktori bukan repo git dan penarikan gagal → **clone ulang bersih** ke `<appdir>.lama.<tanggal>` | Memulihkan kondisi yang membuat produksi tertinggal |
| Gagal `git clone` → berhenti dengan pesan jelas | Menghindari situs setengah jadi |

### Perbaikan akar masalah sesi (temuan kunci)

Apache di **PC pengembangan** (XAMPP) ternyata adalah origin yang melayani
`padipjok.pintarhub.com` (dibuktikan: `curl -H "Host: padipjok.pintarhub.com" http://127.0.0.1/...` → 200).
Domain publik tampak "versi lama" karena **connector Cloudflare Tunnel mati**, sehingga
Cloudflare menyajikan salinan lama. Perbaikan cukup dijalankan dari PC pengembangan.

Langkah yang dijalankan:

```powershell
$cf  = 'C:\Program Files (x86)\cloudflared\cloudflared.exe'
$tok = (Get-Content 'C:\Users\sman1\.cloudflared\token-padi-pjok.txt' -Raw).Trim()
Start-Process -FilePath $cf -ArgumentList @('tunnel','--no-autoupdate','run','--token',$tok) -WindowStyle Hidden
```

### Verifikasi (domain publik)

| uji | sebelum | sesudah |
|---|---|---|
| `POST login-guru.php` | 302 | 302 |
| `dashboard-guru.php` | **500 / 0 byte** | **200 / 41.001 B** |
| `auth-boot.php` | 404 | 200 |
| `pesan-db.php` | 404 | 200 / 2.370 B |
| `buat-token.php` | — | 200 / 30.308 B |
| `mulai-sesi.php` | — | 200 / 5.033 B |
| `data-siswa.php` | — | 200 / 12.933 B |
| `nilai-guru.php` | — | 200 / 5.149 B |
| `laporan.php` | — | 200 / 2.738 B |
| `pantau-siswa.php` | — | 200 / 2.641 B |
| `rekap-penilaian.php` | — | 200 / 15.849 B |
| `profil-guru.php` | — | 200 / 5.130 B |
| `index.php` | — | 200 / 15.701 B |
| `login-siswa.php` | — | 200 / 13.669 B |
| `input-token.php` | — | 302 (benar, belum ada token) |

Uji lokal (Apache + MySQL XAMPP hidup): **12/12 lulus**.
Uji DB dimatikan: halaman tetap **200** tanpa 500 (dulu `die()`).

---

## 3. Pengembalian tampilan halaman utama — commit `6af66f9` (16 Sep 2026)

### Permintaan
Halaman utama login dikembalikan ke tampilan awal: ada header **"Selamat datang di PADI-PJOK"**
dan tombol **Login Guru** serta **Login Siswa**.

### Tindakan
Dipulihkan dari berkas cadangan `index-portal.php.bak` (16.664 byte) sebagai `index.php`
— bukan dibuat ulang — lalu tetap disambungkan ke jalur aman.

### Isi tampilan
- Header: logo ikon pelari + teks **PADI-PJOK** + sub-judul "Penilaian Autentik Digital Integratif untuk PJOK"
- Ilustrasi hero: `padi_pjok_hero_1781370290670.png`
- Kartu sambutan: **"Selamat datang di PADI-PJOK!"** + penjelasan singkat
- Tombol **Login Siswa** (ikon biru) → "Nomor Induk + password (token materi dari guru)"
- Tombol **Login Guru** (ikon ungu) → "Kelola sesi, token materi, dan penilaian siswa"
- Catatan penutup di bawah kedua tombol

### Tetap aman (perbaikan blank putih tidak dikorbankan)

```php
require_once __DIR__ . '/auth-boot.php';

if (empty($conn)) {
    require_once __DIR__ . '/pesan-db.php';
    padi_halaman_db_mati($padi_db_error ?? 'Database tidak dapat dihubungi.');
}
```

- Sesi dimulai lewat helper (tidak ada `session_start()` mentah).
- Bila DB belum siap → halaman panduan, bukan HTTP 500.
- Pengguna yang sudah login diarahkan ke dashboard sesuai perannya; `index.php?menu=1` untuk tetap di sambutan.

### Verifikasi

| uji | hasil |
|---|---|
| `index.php` (publik) | 200 / 16.666 B |
| ada "Selamat datang di PADI-PJOK!" | ✅ |
| ada tombol Login Guru & Login Siswa | ✅ |
| ilustrasi hero tampil | ✅ |
| tombol → `login-siswa.php` | 200 / 13.451 B |
| tombol → `login-guru.php` | 200 / 9.917 B |
| alur login guru → `dashboard-guru.php` | 200 / 41.001 B |
| tangkapan layar browser | tampilan sesuai versi awal ✅ |

---

## 4. Berkas baru & berkas kunci

| Berkas | Fungsi |
|---|---|
| `auth-boot.php` | Titik masuk aman: `koneksi.php` + `auth.php` + sesi berbagi. **Wajib dipakai** di semua halaman, jangan `session_start()` mentah. |
| `pesan-db.php` | Halaman panduan saat database belum siap. |
| `auth.php` | Helper autentikasi: `padi_kembali()`, `padi_url_login()`, `padi_berbagi_sesi()`, `padi_mulai_sesi()`, `loginGuru()`, `loginSiswa()`, guard peran. |
| `koneksi.php` | Koneksi DB tahan-gagal + pengalih `.env`/`.env.local`. |
| `deploy.sh` | Deploy Proxmox; tarik kode di tahap awal, tahan-gagal. |
| `index-portal.php.bak` | Cadangan tampilan portal awal (sumber pemulihan). |

---

## 5. Aturan yang harus dipegang pada pengeditan berikutnya

1. **Jangan pakai `session_start()` mentah.** Selalu `require_once 'auth-boot.php';`
2. **Jangan pakai `header('Location: ...')` + `exit` langsung** untuk halaman. Pakai `padi_kembali($url)`.
3. **Halaman guru wajib** `wajibLoginGuru()`, halaman siswa `wajibLoginSiswa()` — jangan tertukar.
4. **Bila menambah fungsi ke `koneksi.php`**, bungkus `if (!function_exists(...))` karena berkas ini
   dimuat dengan `require` (bukan `require_once`) di banyak tempat.
5. **Uji sebelum menyatakan selesai** — minimal: `php -l <berkas>` lalu alur login lewat `curl.exe`:
   `GET login-guru.php` → `POST action=login` → `GET dashboard-guru.php` (harus 302 → 200, bukan 500).
6. **Jangan mengubah tampilan halaman utama** tanpa konfirmasi; versi yang disetujui ada di
   `index-portal.php.bak` dan commit `6af66f9`.

---

## 6. Cara menjalankan & menguji

### Lokal (XAMPP)

```powershell
Start-Process 'C:\xampp\mysql\bin\mysqld.exe' -ArgumentList '--defaults-file=C:\xampp\mysql\bin\my.ini' -WindowStyle Hidden
Start-Process 'C:\xampp\apache\bin\httpd.exe' -WindowStyle Hidden
# PHP CLI: pakai C:\xampp\php\php.exe (php sistem tidak punya mysqli)
```

Hanya boleh **satu** proses `mysqld` (dua proses = lock conflict, errno 32).

### Uji alur login (hemat token, pakai `curl.exe`)

```powershell
$b='https://padipjok.pintarhub.com'; $ck="$env:TEMP\ck.txt"
curl.exe -s -c $ck -o NUL "$b/login-guru.php"
curl.exe -s -b $ck -c $ck -o NUL -d "action=login&username=guru&password=guru123" "$b/login-guru.php"
curl.exe -s -b $ck -o NUL -w "%{http_code} %{size_download}`n" "$b/dashboard-guru.php"   # harap 200 41001
```

> Catatan PowerShell 5.1: `Invoke-WebRequest` **tidak** punya `-SkipHttpErrorCheck` → gunakan `curl.exe`.

### Tunnel Cloudflare (agar domain publik hidup)

```powershell
$cf  = 'C:\Program Files (x86)\cloudflared\cloudflared.exe'
$tok = (Get-Content 'C:\Users\sman1\.cloudflared\token-padi-pjok.txt' -Raw).Trim()
Start-Process -FilePath $cf -ArgumentList @('tunnel','--no-autoupdate','run','--token',$tok) -WindowStyle Hidden
```

---

## 7. Hal yang perlu diperhatikan (belum selesai)

- ⚠️ **Connector Cloudflare berjalan sebagai proses biasa, bukan service** → mati saat PC restart
  dan situs ikut mati. Untuk permanen, jalankan PowerShell **sebagai Administrator**:
  `cloudflared service install`
- ⚠️ **Situs bergantung pada PC pengembangan hidup** (Apache :80 + MySQL :3306 + connector).
  Rencana pindah ke CT 102: jalankan `pasang-tunnel-ct102.sh` + token yang sama.
- ⚠️ **SSH ke server tidak terjangkau** dari PC ini (timeout ke `192.168.18.39`,
  `sman1sumber.sch.id`, `8.215.13.99`). Satu-satunya jalan masuk: console Proxmox → `pct enter 102`.
- ⚠️ `require 'koneksi.php'` (tanpa `_once`) masih ada di berkas lain — jaga `koneksi.php` tetap idempoten.
- ⚠️ Berkas skrip bantu berikut belum di-commit (sengaja): `pasang-tunnel-ct102.sh`,
  `perbaikan-ct102.sh`, `perintah-ct102.sh`, `perintah-pasang-tunnel.txt`.
