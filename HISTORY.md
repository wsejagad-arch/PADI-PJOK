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
| 6 | 16 Sep 2026 | `6233f62` | ✅ **Produksi diperbaiki: tampilan login baru LIVE** (sebab = `cloudflared` mati) + gate secret `deploy.yml` diperbaiki | ✅ terverifikasi publik |
| 5 | 16 Sep 2026 | `6233f62` | Pulihkan tampilan login satu halaman (tab Guru/Siswa, form, banner mode siswa, footer) | ✅ terverifikasi lokal |
| 4 | 16 Sep 2026 | `6af66f9` | Kembalikan halaman utama ke tampilan awal (header sambutan + tombol Login Guru/Siswa) | âœ… terverifikasi |
| 3 | 16 Sep 2026 | `f8cf6a9` | Perbaiki `deploy.sh` agar satu paket hilang tidak menggagalkan deploy | âœ… terverifikasi |
| 2 | 16 Sep 2026 | `8411c0a` | Cegah halaman putih setelah login (guard per-peran, sesi berbagi, tahan DB mati) | âœ… terverifikasi |
| 1 | 15 Sep 2026 | `c18c6e6` | Skrip pasang Cloudflare Tunnel permanen (CT 102) | âœ… |

---

## 2. Perbaikan BLANK PUTIH setelah login â€” commit `8411c0a` (16 Sep 2026)

### Gejala
Login guru berhasil (`POST login-guru.php` â†’ 302 ke `dashboard-guru.php`), tetapi
`dashboard-guru.php` membalas **HTTP 500 dengan body 0 byte** â€” halaman putih tanpa petunjuk.
Halaman login siswa juga terdampak.

### Penyebab (dua lapis)

**Lapis 1 â€” kode aplikasi**

| Berkas | Masalah |
|---|---|
| `verifikasi-token.php` | Memakai `session_start()` mentah + `require 'koneksi.php'` relatif. Bila ada satu saja teks/JSON keluar sebelum `session_start()`, PHP mematikan skrip â†’ body kosong. |
| `koneksi.php` | `die()` saat koneksi DB gagal â†’ halaman putih/500 ketika MySQL belum siap. |
| `auth.php` | Guard mengirim pengguna ke halaman login peran yang salah. |
| `index.php` | Menebak peran dengan memvalidasi input ke tabel `master_siswa`, sehingga login guru selalu gagal. |

**Lapis 2 â€” produksi tertinggal versi (penyebab utama 500)**

Dibandingkan produksi vs lokal:

| berkas | produksi | lokal | keterangan |
|---|---|---|---|
| `setup_db.php` | 507 B | 4.814 B | âŒ |
| `verifikasi-token.php` | 65 B | 1.407 B | âŒ masih berisi `padi_muat_env` (artefak skrip deploy lama) |
| `login-guru.php` | 9.764 B | 11.188 B | âŒ |
| `koneksi.php` | **0 B** | 4.250 B | âŒ tanpa penjaga idempoten |
| `auth-boot.php` | **404** | ada | âŒ belum ada |
| `pesan-db.php` | **404** | ada | âŒ belum ada |

Penyebab: `deploy.sh` meng-clone dari GitHub, tetapi deploy terakhir **gagal di tengah jalan**
karena `php8.2-json` tidak tersedia â†’ direktori aplikasi tidak pernah ditulis ulang, sehingga
situs tetap menyajikan kode lama (masih bug "Cannot redeclare").

### Perbaikan kode

| Berkas | Perubahan |
|---|---|
| `koneksi.php` | Penjaga idempoten `PADI_KONEKSI_SELESAI` (berlaku untuk semua `require`, bukan hanya lewat `auth`); pengalih `.env` (produksi) vs `.env.local` (lokal); `padi_coba_koneksi()` â€” **tidak lagi `die()`** saat DB mati, galat dicatat ke log; `display_errors=0`; variabel `$padi_db_error`. |
| `auth-boot.php` **(baru)** | Titik masuk aman: memuat `koneksi.php` + `auth.php`, lalu `padi_berbagi_sesi()` (cookie `path=/` agar sesi tidak hilang bila domain disajikan dari folder lain) + `padi_mulai_sesi()`. |
| `auth.php` | `padi_mulai_sesi()`; `padi_berbagi_sesi()`; `padi_url_login($tipe)`; `padi_kembali()` â€” redirect anti-putih (header + meta refresh + tautan manual bila header gagal); guard `wajibLoginGuru()` / `wajibLoginSiswa()` memakai pengalih peran yang benar. |
| `pesan-db.php` **(baru)** | Halaman panduan saat database belum siap (langkah perbaikan + tautan), menggantikan HTTP 500. |
| `index.php` | Portal satu pintu: kartu **Guru** â†’ `login-guru.php` (tanpa menebak lewat tabel siswa); kartu **Siswa** â†’ form NIS + password. `?menu=1` untuk tetap di portal meski sudah login. |
| `verifikasi-token.php` | Ditulis ulang: `require auth-boot.php`, hapus `session_start()` ganda, jawaban JSON konsisten `{success, token, materi, kelas}`. |

### Perbaikan skrip deploy â€” commit `f8cf6a9`

| Perubahan | Alasan |
|---|---|
| Tahap tarik kode dipindah ke **tahap 2** (sebelum konfigurasi layanan) | Kegagalan langkah lanjutan tidak lagi meninggalkan versi lama |
| `json` dikeluarkan dari daftar ekstensi wajib; hanya dipasang bila tersedia | `php8.2-json` menyatu ke core sejak PHP 8 |
| Verifikasi binari PHP (`command -v php` + `php -v`) sebelum lanjut | Deteksi dini bila pemasangan PHP gagal |
| Bila direktori bukan repo git dan penarikan gagal â†’ **clone ulang bersih** ke `<appdir>.lama.<tanggal>` | Memulihkan kondisi yang membuat produksi tertinggal |
| Gagal `git clone` â†’ berhenti dengan pesan jelas | Menghindari situs setengah jadi |

### Perbaikan akar masalah sesi (temuan kunci)

Apache di **PC pengembangan** (XAMPP) ternyata adalah origin yang melayani
`padipjok.pintarhub.com` (dibuktikan: `curl -H "Host: padipjok.pintarhub.com" http://127.0.0.1/...` â†’ 200).
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
| `buat-token.php` | â€” | 200 / 30.308 B |
| `mulai-sesi.php` | â€” | 200 / 5.033 B |
| `data-siswa.php` | â€” | 200 / 12.933 B |
| `nilai-guru.php` | â€” | 200 / 5.149 B |
| `laporan.php` | â€” | 200 / 2.738 B |
| `pantau-siswa.php` | â€” | 200 / 2.641 B |
| `rekap-penilaian.php` | â€” | 200 / 15.849 B |
| `profil-guru.php` | â€” | 200 / 5.130 B |
| `index.php` | â€” | 200 / 15.701 B |
| `login-siswa.php` | â€” | 200 / 13.669 B |
| `input-token.php` | â€” | 302 (benar, belum ada token) |

Uji lokal (Apache + MySQL XAMPP hidup): **12/12 lulus**.
Uji DB dimatikan: halaman tetap **200** tanpa 500 (dulu `die()`).

---

## 3. Pengembalian tampilan halaman utama â€” commit `6af66f9` (16 Sep 2026)

### Permintaan
Halaman utama login dikembalikan ke tampilan awal: ada header **"Selamat datang di PADI-PJOK"**
dan tombol **Login Guru** serta **Login Siswa**.

### Tindakan
Dipulihkan dari berkas cadangan `index-portal.php.bak` (16.664 byte) sebagai `index.php`
â€” bukan dibuat ulang â€” lalu tetap disambungkan ke jalur aman.

### Isi tampilan
- Header: logo ikon pelari + teks **PADI-PJOK** + sub-judul "Penilaian Autentik Digital Integratif untuk PJOK"
- Ilustrasi hero: `padi_pjok_hero_1781370290670.png`
- Kartu sambutan: **"Selamat datang di PADI-PJOK!"** + penjelasan singkat
- Tombol **Login Siswa** (ikon biru) â†’ "Nomor Induk + password (token materi dari guru)"
- Tombol **Login Guru** (ikon ungu) â†’ "Kelola sesi, token materi, dan penilaian siswa"
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
- Bila DB belum siap â†’ halaman panduan, bukan HTTP 500.
- Pengguna yang sudah login diarahkan ke dashboard sesuai perannya; `index.php?menu=1` untuk tetap di sambutan.

### Verifikasi

| uji | hasil |
|---|---|
| `index.php` (publik) | 200 / 16.666 B |
| ada "Selamat datang di PADI-PJOK!" | âœ… |
| ada tombol Login Guru & Login Siswa | âœ… |
| ilustrasi hero tampil | âœ… |
| tombol â†’ `login-siswa.php` | 200 / 13.451 B |
| tombol â†’ `login-guru.php` | 200 / 9.917 B |
| alur login guru â†’ `dashboard-guru.php` | 200 / 41.001 B |
| tangkapan layar browser | tampilan sesuai versi awal âœ… |

---

## 4. Pemulihan tampilan login satu halaman — commit `6233f62` (16 Sep 2026)

### Permintaan
Kembalikan **tampilan login** seperti "pertama kali sebelum dirombak": satu halaman login
di `index.php` dengan **tab Guru / Siswa**, form email/username + password, tautan
"Lupa password?", banner **Mode Siswa tersedia**, tiga lencana fitur, dan footer
**Versi Prototype**.

### Temuan
Tampilan bertab itu **tidak ada di repo mana pun** (git history hanya punya varian form-login-siswa
tanpa tab dan varian portal dua tombol). Tampilan direkonstruksi dari tangkapan layar pengguna.

CSS pendukung (`.tabs`, `.tab-btn`, `.form-section`, `.forgot-row`, `.student-banner`,
`.footer-badges`, `.footer-version`, `.alert`) sudah ada di `index.php`; hanya bagian HTML/JS
yang dahulu diganti tombol tautan.

### Tindakan (hanya `index.php`)
| Bagian | Perubahan |
|---|---|
| Blok PHP atas | Tambah pemrosesan POST satu halaman: `peran=guru` â†’ `loginGuru()` â†’ `dashboard-guru.php`; `peran=siswa` â†’ `loginSiswa()` â†’ `input-token.php`; galat ditampilkan di `.alert`. Variabel `$tab` menentukan tab aktif setelah galat. |
| Body | Ganti dua tombol tautan â†’ tablist `Guru`/`Siswa` + dua panel `<form>` (Guru: email/username+password+Lupa password; Siswa: NIS+password/token) + banner Mode Siswa + footer badges + Versi Prototype. |
| Script | `switchTab()` nyata (ganti kelas `.active`, `aria-selected`, `display` panel, fokus isian pertama) dan `togglePw(id, tombol)`. |

### Tetap aman (perbaikan blank putih tidak dikorbankan)
- `require_once __DIR__ . '/auth-boot.php';` tetap di baris atas.
- DB belum siap â†’ `pesan-db.php`, bukan HTTP 500.
- Sesi aktif tetap dialihkan ke dashboard sesuai peran; `index.php?menu=1` untuk tetap di halaman login.

### Verifikasi (lokal, Apache + MySQL XAMPP hidup)

| uji | hasil |
|---|---|
| `php -l index.php` | No syntax errors |
| keseimbangan tag | `<div>` 24/24, `<form>` 2/2 |
| `GET index.php?menu=1` | 200 / 23.943 B |
| penanda tampilan | Mode Siswa tersedia âœ…, Masuk sebagai Guru âœ…, Masuk sebagai Siswa âœ…, Versi Prototype âœ…, Mobile friendly âœ…, Lupa password âœ…, tab-guru/tab-siswa âœ… |
| `POST peran=guru` | 302 â†’ `dashboard-guru.php` 200 / 41.001 B |
| klik tab Siswa (browser) | panel Siswa tampil, tab aktif berpindah âœ… |

> Catatan: `login-guru.php` dan `login-siswa.php` **tetap ada** (dipakai tautan "Lupa password?"
> dan jalur langsung); `index.php` kini kembali menjadi pintu masuk utama bertab.

---

## 5. PENYEBAB SEBENARNYA "server belum berubah" — 16 Sep 2026

### Gejala
Setelah `git push` (`6233f62`), situs `padipjok.pintarhub.com` **masih menampilkan versi lama**,
padahal workflow deploy GitHub Actions hijau.

### Akar masalah (DUA hal — bukan kode aplikasi)

**1. Situs produksi ternyata DILAYANI PC PENGEMBANGAN ini, bukan Proxmox.**
Yang membuat domain publik tidak berubah bukan "server tertinggal versi", melainkan
proses **`cloudflared` (Cloudflare Tunnel) MATI** — sehingga Cloudflare menyajikan salinan lama.

Bukti seluruh jalur masuk ke server tertutup:

| Jalur uji | Hasil |
|---|---|
| `ping 192.168.18.39` | 100% loss — PC sendiri balas *Destination host unreachable* |
| ARP `192.168.18.x` | hanya router (`192.168.18.1`), tak ada host lain |
| `192.168.18.39` port 22/8006/80/443 | semua timeout |
| `8.215.13.99` port 22 | timeout |
| Tailscale peer | hanya `pintarhub` — offline 47 hari |
| DNS `proxmox` / `pve` | tidak ada |
| Tab browser `192.168.18.39:8006` | hanya `chrome-error://chromewebdata` |

Sesudah `cloudflared` dihidupkan, ukuran halaman langsung berubah
**10.136 B → 23.943 B** (identik dengan berkas lokal) → terbukti origin = Apache XAMPP PC ini.

**2. Workflow deploy "SUKSES PALSU".**
`gh secret list` **kosong** (tak ada `DEPLOY_HOST`/`DEPLOY_USER`/`DEPLOY_SSH_KEY`/`DEPLOY_PATH`),
sehingga langkah deploy dilewati tetapi job tetap hijau. Log membuktikan:
`::notice::DEPLOY_HOST belum di-set — langkah deploy dilewati.`
Job selesai hanya **4–7 detik** (deploy sungguhan butuh menit-an).

### Perbaikan

**A. Produksi (langsung, tanpa deploy):** hidupkan tunnel di PC pengembangan.

```powershell
$cf  = 'C:\Program Files (x86)\cloudflared\cloudflared.exe'
$tok = (Get-Content 'C:\Users\sman1\.cloudflared\token-padi-pjok.txt' -Raw).Trim()
Start-Process $cf -ArgumentList @('tunnel','--no-autoupdate','run','--token',$tok) -WindowStyle Hidden
```

**B. `.github/workflows/deploy.yml` — gate secret diperbaiki:**
- Ketiga secret (`DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_SSH_KEY`) kini **WAJIB**; bila ada
  yang kosong → `::error::` + **exit 1** (job MERAH), bukan hijau.
- Ditambah langkah **"Verifikasi hasil deploy"** (opsional, lewat secret `DEPLOY_URL`):
  curl URL, gagal bila bukan HTTP 200.
- Header berkas diberi peringatan bahwa produksi kini dilayani PC pengembangan
  + cara menonaktifkan sementara: `gh workflow disable "Deploy ke Proxmox (padipjok.pintarhub.com)"`.

### Verifikasi

| uji | hasil |
|---|---|
| YAML `deploy.yml` | valid; 4 langkah: Cek → Siapkan SSH → Deploy → Verifikasi |
| gate tanpa secret | **GAGAL** (kurang: DEPLOY_HOST DEPLOY_USER DEPLOY_SSH_KEY) ✅ |
| gate hanya `DEPLOY_HOST` | **GAGAL** (kurang: DEPLOY_USER DEPLOY_SSH_KEY) ✅ |
| gate lengkap | **LOLOS** ✅ |
| `index.php?menu=1` (publik) | **200 / 23.943 B** (dulu 10.136 B) ✅ |
| `auth-boot.php` (publik) | **200** (dulu 404) ✅ |
| `pesan-db.php` (publik) | **200 / 2.370 B** (dulu 404) ✅ |
| `koneksi.php` (publik) | **200** (dulu 0 B) ✅ |
| Penanda tampilan baru | Masuk sebagai Guru/Siswa, Mode Siswa tersedia, Versi Prototype, Mobile friendly, tab-guru, tab-siswa — semua ADA ✅ |
| Alur login guru (publik) | POST **302** → `dashboard-guru.php` **200 / 41.001 B** ✅ |

### ⚠️ Yang perlu diingat
- `cloudflared` berjalan sebagai **proses biasa, BUKAN service** → **mati setiap PC restart**
  dan situs ikut mati. Untuk permanen (PowerShell **as Administrator**):
  `cloudflared service install`
- Situs bergantung pada PC pengembangan hidup (Apache :80 + MySQL :3306 + connector).
- **Jangan percaya status hijau `deploy.yml`** sebelum secret diisi — sekarang ia akan
  merah bila belum lengkap.

---

## 6. Berkas baru & berkas kunci

| Berkas | Fungsi |
|---|---|
| `auth-boot.php` | Titik masuk aman: `koneksi.php` + `auth.php` + sesi berbagi. **Wajib dipakai** di semua halaman, jangan `session_start()` mentah. |
| `pesan-db.php` | Halaman panduan saat database belum siap. |
| `auth.php` | Helper autentikasi: `padi_kembali()`, `padi_url_login()`, `padi_berbagi_sesi()`, `padi_mulai_sesi()`, `loginGuru()`, `loginSiswa()`, guard peran. |
| `koneksi.php` | Koneksi DB tahan-gagal + pengalih `.env`/`.env.local`. |
| `deploy.sh` | Deploy Proxmox; tarik kode di tahap awal, tahan-gagal. |
| `index-portal.php.bak` | Cadangan tampilan portal awal (sumber pemulihan). |

---

## 7. Aturan yang harus dipegang pada pengeditan berikutnya

1. **Jangan pakai `session_start()` mentah.** Selalu `require_once 'auth-boot.php';`
2. **Jangan pakai `header('Location: ...')` + `exit` langsung** untuk halaman. Pakai `padi_kembali($url)`.
3. **Halaman guru wajib** `wajibLoginGuru()`, halaman siswa `wajibLoginSiswa()` â€” jangan tertukar.
4. **Bila menambah fungsi ke `koneksi.php`**, bungkus `if (!function_exists(...))` karena berkas ini
   dimuat dengan `require` (bukan `require_once`) di banyak tempat.
5. **Uji sebelum menyatakan selesai** â€” minimal: `php -l <berkas>` lalu alur login lewat `curl.exe`:
   `GET login-guru.php` â†’ `POST action=login` â†’ `GET dashboard-guru.php` (harus 302 â†’ 200, bukan 500).
6. **Jangan mengubah tampilan halaman utama** tanpa konfirmasi; versi yang disetujui ada di
   `index-portal.php.bak` dan commit `6af66f9`.

---

## 8. Cara menjalankan & menguji

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

> Catatan PowerShell 5.1: `Invoke-WebRequest` **tidak** punya `-SkipHttpErrorCheck` â†’ gunakan `curl.exe`.

### Tunnel Cloudflare (agar domain publik hidup)

```powershell
$cf  = 'C:\Program Files (x86)\cloudflared\cloudflared.exe'
$tok = (Get-Content 'C:\Users\sman1\.cloudflared\token-padi-pjok.txt' -Raw).Trim()
Start-Process -FilePath $cf -ArgumentList @('tunnel','--no-autoupdate','run','--token',$tok) -WindowStyle Hidden
```

---

## 9. Hal yang perlu diperhatikan (belum selesai)

- âš ï¸ **Connector Cloudflare berjalan sebagai proses biasa, bukan service** â†’ mati saat PC restart
  dan situs ikut mati. Untuk permanen, jalankan PowerShell **sebagai Administrator**:
  `cloudflared service install`
- âš ï¸ **Situs bergantung pada PC pengembangan hidup** (Apache :80 + MySQL :3306 + connector).
  Rencana pindah ke CT 102: jalankan `pasang-tunnel-ct102.sh` + token yang sama.
- âš ï¸ **SSH ke server tidak terjangkau** dari PC ini (timeout ke `192.168.18.39`,
  `sman1sumber.sch.id`, `8.215.13.99`). Satu-satunya jalan masuk: console Proxmox â†’ `pct enter 102`.
- âš ï¸ `require 'koneksi.php'` (tanpa `_once`) masih ada di berkas lain â€” jaga `koneksi.php` tetap idempoten.
- âš ï¸ Berkas skrip bantu berikut belum di-commit (sengaja): `pasang-tunnel-ct102.sh`,
  `perbaikan-ct102.sh`, `perintah-ct102.sh`, `perintah-pasang-tunnel.txt`.

