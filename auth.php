<?php
// auth.php — Helper autentikasi (guru & siswa) + migrasi kolom/tabel.
// Dipakai oleh login-guru.php, login-siswa.php, dan guard halaman.

require_once __DIR__ . '/koneksi.php';

/* ============================================================
   SESI & PENGALIH
   ============================================================ */
/**
 * Pastikan sesi aktif. Dipanggil di SETIAP halaman lewat guard sehingga
 * session_start() dan header redirect selalu berjalan di satu bagian berkas ini.
 */
if (!function_exists('padi_mulai_sesi')) {
function padi_mulai_sesi()
{
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
}
}

/**
 * Berbagi sesi dengan folder lain di domain yang sama (mis. portal PintarHub).
 * Aman: TIDAK mengubah cookie yang sudah ada, hanya menyempitkan path bila perlu.
 */
if (!function_exists('padi_berbagi_sesi')) {
function padi_berbagi_sesi()
{
    if (session_status() !== PHP_SESSION_NONE || headers_sent()) return;
    $p = session_get_cookie_params();
    // '' = berlaku untuk seluruh domain (semua subfolder).
    if ($p['path'] !== '' || (isset($p['samesite']) && $p['samesite'] !== 'Lax')) {
        session_set_cookie_params(['lifetime' => (int)$p['lifetime'], 'path' => '', 'domain' => $p['domain'], 'secure' => (bool)$p['secure'], 'httponly' => (bool)$p['httponly'], 'samesite' => 'Lax']);
    }
}
}

/**
 * URL halaman login sesuai tipe pengguna.
 * Dipakai agar halaman guru TIDAK terlempar ke login siswa (dan sebaliknya).
 */
if (!function_exists('padi_url_login')) {
function padi_url_login($tipe)
{
    return $tipe === 'guru' ? 'login-guru.php' : 'login-siswa.php';
}
}

/**
 * Redirect internal yang aman (header + cadangan HTML/meta bila header gagal).
 * Bila header() gagal (output sudah terkirim), pengguna TIDAK dibiarkan
 * melihat halaman putih — langsung diarahkan lewat meta refresh + tautan manual.
 */
if (!function_exists('padi_kembali')) {
function padi_kembali($lokasi, $pesan = '')
{
    $lokasi = (string)$lokasi;
    if (!headers_sent()) {
        header('Location: ' . $lokasi);
        header('Content-Type: text/html; charset=utf-8');
    }
    $tujuan = htmlspecialchars($lokasi, ENT_QUOTES, 'UTF-8');
    $teks = $pesan !== '' ? htmlspecialchars($pesan, ENT_QUOTES, 'UTF-8') : 'Mengalihkan…';
    echo '<!DOCTYPE html><html lang="id"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta http-equiv="refresh" content="0;url=' . $tujuan . '">'
        . '<title>Mengalihkan…</title></head>'
        . '<body style="font-family:Inter,system-ui,sans-serif;padding:24px;text-align:center">'
        . '<p>' . $teks . '</p><p><a href="' . $tujuan . '">Lanjutkan</a></p>'
        . '<script>location.replace(' . json_encode($lokasi, JSON_UNESCAPED_SLASHES) . ');</script>'
        . '</body></html>';
    exit;
}
}

/* ============================================================
   PROTEKSI UMUM: CSRF, throttle, token, URL embed
   ============================================================ */
if (!function_exists('padi_csrf_token')) {
function padi_csrf_token()
{
    padi_mulai_sesi();
    if (empty($_SESSION['padi_csrf'])) {
        $_SESSION['padi_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['padi_csrf'];
}
}

if (!function_exists('padi_csrf_field')) {
function padi_csrf_field()
{
    return '<input type="hidden" name="csrf" value="'
        . htmlspecialchars(padi_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}
}

if (!function_exists('padi_csrf_valid')) {
function padi_csrf_valid($token = null)
{
    padi_mulai_sesi();
    if ($token === null) {
        $token = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    }
    if (!is_string($token) || $token === '' || empty($_SESSION['padi_csrf'])) {
        return false;
    }
    return hash_equals((string)$_SESSION['padi_csrf'], $token);
}
}

/** Hentikan permintaan (403 JSON) bila token CSRF tidak cocok. */
if (!function_exists('padi_wajib_csrf')) {
function padi_wajib_csrf()
{
    if (padi_csrf_valid()) return;
    http_response_code(403);
    if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Sesi formulir kedaluwarsa. Muat ulang halaman.']);
    exit;
}
}

/**
 * Pembatas percobaan lintas-proses (berbasis berkas + flock, tanpa ekstensi).
 * Sisa detik blokir, 0 = masih diizinkan. Bila berkas tak bisa dibuat,
 * otomatis jatuh ke penghitung $_SESSION.
 */
if (!function_exists('padi_throttle_berkas')) {
function padi_throttle_berkas($kunci)
{
    return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'padi_thr_' . md5($kunci) . '.txt';
}
}

if (!function_exists('padi_throttle')) {
function padi_throttle($kunci, $maks = 5, $jeda = 600)
{
    $berkas = padi_throttle_berkas($kunci);
    $fp = @fopen($berkas, 'c+');
    if (!$fp) {
        return padi_throttle_sesi($kunci, $maks, $jeda);
    }
    $now = time();
    $sisa = 0;
    if (flock($fp, LOCK_EX)) {
        $isi = trim((string)stream_get_contents($fp));
        $n = 0; $sampai = 0;
        if ($isi !== '') {
            $bagian = explode(' ', $isi);
            $n = (int)($bagian[0] ?? 0);
            $sampai = (int)($bagian[1] ?? 0);
        }
        if ($sampai > $now) {
            $sisa = $sampai - $now;
        } elseif ($sampai !== 0) {
            $n = 0; // masa blokir berakhir → mulai dari nol
            ftruncate($fp, 0); rewind($fp); fwrite($fp, '0 0');
        } elseif ($n >= $maks) {
            $sampai = $now + $jeda;
            ftruncate($fp, 0); rewind($fp); fwrite($fp, $n . ' ' . $sampai);
            $sisa = $jeda;
        }
        flock($fp, LOCK_UN);
    }
    fclose($fp);
    return $sisa;
}
}

if (!function_exists('padi_throttle_sesi')) {
function padi_throttle_sesi($kunci, $maks = 5, $jeda = 600)
{
    padi_mulai_sesi();
    $data = $_SESSION['padi_throttle'][$kunci] ?? ['n' => 0, 'sampai' => 0];
    $data = ['n' => (int)($data['n'] ?? 0), 'sampai' => (int)($data['sampai'] ?? 0)];
    $sisa = $data['sampai'] - time();
    if ($sisa > 0) return $sisa;
    if ($data['sampai'] !== 0) {
        $_SESSION['padi_throttle'][$kunci] = ['n' => 0, 'sampai' => 0];
        $data = ['n' => 0, 'sampai' => 0];
    }
    if ($data['n'] >= $maks) {
        $_SESSION['padi_throttle'][$kunci]['sampai'] = time() + $jeda;
        return $jeda;
    }
    return 0;
}
}

if (!function_exists('padi_throttle_gagal')) {
function padi_throttle_gagal($kunci)
{
    $berkas = padi_throttle_berkas($kunci);
    $fp = @fopen($berkas, 'c+');
    if ($fp) {
        if (flock($fp, LOCK_EX)) {
            $isi = trim((string)stream_get_contents($fp));
            $n = 0; $sampai = 0;
            if ($isi !== '') {
                $bagian = explode(' ', $isi);
                $n = (int)($bagian[0] ?? 0);
                $sampai = (int)($bagian[1] ?? 0);
            }
            if ($sampai > time()) { // sudah diblokir, jangan tambah
                flock($fp, LOCK_UN); fclose($fp); return;
            }
            $n++;
            ftruncate($fp, 0); rewind($fp); fwrite($fp, $n . ' 0');
            flock($fp, LOCK_UN);
        }
        fclose($fp);
        return;
    }

    padi_mulai_sesi();
    $data = $_SESSION['padi_throttle'][$kunci] ?? ['n' => 0, 'sampai' => 0];
    $_SESSION['padi_throttle'][$kunci] = [
        'n' => (int)($data['n'] ?? 0) + 1,
        'sampai' => (int)($data['sampai'] ?? 0),
    ];
}
}

if (!function_exists('padi_throttle_reset')) {
function padi_throttle_reset($kunci)
{
    @unlink(padi_throttle_berkas($kunci));
    padi_mulai_sesi();
    unset($_SESSION['padi_throttle'][$kunci]);
}
}

/**
 * Kunci throttle berbasis IP klien + tujuan (tanpa tabel/berkas tambahan).
 * Di belakang Cloudflare Tunnel, REMOTE_ADDR selalu 127.0.0.1, jadi pakai
 * CF-Connecting-IP agar satu pengguna tidak mengunci seluruh sekolah.
 */
if (!function_exists('padi_kunci_ip')) {
function padi_kunci_ip($tujuan)
{
    $ip = trim((string)($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''));
    if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) {
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'lokal');
    }
    return $tujuan . ':' . $ip;
}
}

/** Pesan tunggu yang ramah dari sisa detik blokir. */
if (!function_exists('padi_pesan_throttle')) {
function padi_pesan_throttle($detik)
{
    $menit = max(1, (int)ceil($detik / 60));
    return 'Terlalu banyak percobaan. Coba lagi dalam ' . $menit . ' menit.';
}
}

/** Token sesi acak 8 karakter A-Z0-9 (~41 bit) memakai random_int. */
if (!function_exists('padi_token_acak')) {
function padi_token_acak($panjang = 8)
{
    $alfabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $hasil = '';
    for ($i = 0; $i < $panjang; $i++) {
        $hasil .= $alfabet[random_int(0, 35)];
    }
    return $hasil;
}
}

/** URL yang aman untuk <iframe>: hanya https dari host yang diizinkan. */
if (!function_exists('padi_embed_aman')) {
function padi_embed_aman($url)
{
    $url = trim((string)$url);
    if ($url === '' || strlen($url) > 500) return '';
    $bagian = parse_url($url);
    if (!$bagian || strtolower((string)($bagian['scheme'] ?? '')) !== 'https' || empty($bagian['host'])) {
        return '';
    }
    $izin = ['www.youtube.com', 'youtube.com', 'youtu.be', 'www.youtube-nocookie.com', 'drive.google.com', 'docs.google.com'];
    return in_array(strtolower($bagian['host']), $izin, true) ? $url : '';
}
}

/* ============================================================
   MIGRASI RINGAN (aman dijalankan berulang)
   - master_siswa: tambah kolom password (hash)
   - tabel guru: akun guru (username + password hash)
   ============================================================ */
function pastikanTabelAuth($conn)
{
    if (!$conn || !empty($conn->connect_errno)) return;

    // 1. Kolom password di master_siswa
    $cek = $conn->query("SHOW COLUMNS FROM master_siswa LIKE 'password'");
    if ($cek && $cek->num_rows === 0) {
        $conn->query("ALTER TABLE master_siswa ADD COLUMN password VARCHAR(255) NULL DEFAULT NULL");
    }

    // 2. Tabel guru
    $conn->query("CREATE TABLE IF NOT EXISTS guru (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nama VARCHAR(100) NOT NULL,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        nip VARCHAR(30) NULL DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // 2a. Kolom NIP (untuk tanda tangan laporan) pada instalasi lama
    $cek = $conn->query("SHOW COLUMNS FROM guru LIKE 'nip'");
    if ($cek && $cek->num_rows === 0) {
        $conn->query("ALTER TABLE guru ADD COLUMN nip VARCHAR(30) NULL DEFAULT NULL AFTER password");
    }

    // 2b. Jadwal pelajaran per kelas (dipakai guru & dashboard siswa)
    $conn->query("CREATE TABLE IF NOT EXISTS jadwal (
        id INT AUTO_INCREMENT PRIMARY KEY,
        kelas VARCHAR(50) NOT NULL,
        hari TINYINT NOT NULL,
        jam_mulai TIME NOT NULL,
        jam_selesai TIME NOT NULL,
        materi VARCHAR(150) NOT NULL,
        catatan VARCHAR(255) NULL DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_jadwal (kelas, hari, jam_mulai),
        KEY idx_jadwal_kelas (kelas, hari)
    )");

    // 3. Akun guru bawaan: password TIDAK di-hardcode.
    //    Sumber: env GURU_SEED_PASS. Bila kosong, dibangkitkan acak dan
    //    dicatat SEKALI ke berkas di luar document root.
    $cek = $conn->query("SELECT id, username, password FROM guru ORDER BY id ASC LIMIT 1");
    $akun = $cek ? $cek->fetch_assoc() : null;
    if (!$akun) {
        $pass = trim((string)getenv('GURU_SEED_PASS'));
        $acak = ($pass === '');
        if ($acak) $pass = bin2hex(random_bytes(6));
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO guru (nama, username, password) VALUES (?, ?, ?)");
        if ($stmt) {
            $nama = 'Guru PJOK';
            $user = 'guru';
            $stmt->bind_param("sss", $nama, $user, $hash);
            $stmt->execute();
            $stmt->close();
        }
        if ($acak) padi_catat_password_guru($pass);
    } elseif (strtolower((string)$akun['username']) === 'guru'
        && password_verify('guru123', (string)$akun['password'])) {
        // Rotasi akun default lama agar kredensial publik tidak berlaku lagi.
        $pass = trim((string)getenv('GURU_SEED_PASS'));
        $acak = ($pass === '');
        if ($acak) $pass = bin2hex(random_bytes(6));
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $id = (int)$akun['id'];
        $stmt = $conn->prepare("UPDATE guru SET password = ? WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("si", $hash, $id);
            $stmt->execute();
            $stmt->close();
        }
        if ($acak) padi_catat_password_guru($pass);
    }
}

/** Catat password guru hasil pembangkitan ke berkas tersembunyi di docroot. */
if (!function_exists('padi_catat_password_guru')) {
function padi_catat_password_guru($pass)
{
    // Nama berawalan titik + .txt → diblokir Apache (.htaccess) dan Nginx.
    $berkas = __DIR__ . DIRECTORY_SEPARATOR . '.padi-guru-password.txt';
    $isi = "Akun guru : guru\nPassword   : " . $pass . "\nDibuat     : " . date('c')
        . "\n\nUbah password setelah login, lalu hapus berkas ini.\n";
    @file_put_contents($berkas, $isi);
    if (function_exists('error_log')) {
        error_log('PADI: password guru dibuat/dirotasi. Lihat ' . $berkas);
    }
}
}

/** Cek keberadaan kolom tanpa mengubah skema. */
if (!function_exists('padi_kolom_ada')) {
function padi_kolom_ada($conn, $tabel, $kolom)
{
    if (!$conn) return false;
    $tabel = preg_replace('/[^A-Za-z0-9_]/', '', (string)$tabel);
    $stmt = $conn->prepare("SHOW COLUMNS FROM `" . $tabel . "` LIKE ?");
    if (!$stmt) return false;
    $stmt->bind_param("s", $kolom);
    $stmt->execute();
    $ada = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $ada;
}
}

/**
 * Kolom verifikasi guru untuk nilai rubrik siswa (idempoten, sekali per request).
 * nilai_rubrik tetap = isian siswa; nilai_guru = keputusan guru (dipakai rekap).
 */
if (!function_exists('pastikanKolomVerifikasi')) {
function pastikanKolomVerifikasi($conn)
{
    static $selesai = false;
    if ($selesai || !$conn) return;
    $selesai = true;

    $kolom_baru = [
        'nilai_guru'   => 'TINYINT NULL DEFAULT NULL',
        'catatan_guru' => 'VARCHAR(300) NULL DEFAULT NULL',
        'guru_id'      => 'INT NULL DEFAULT NULL',
        'dinilai_at'   => 'DATETIME NULL DEFAULT NULL',
    ];
    foreach (['penilaian_psikomotor', 'penilaian_afektif', 'penilaian_rekan'] as $tabel) {
        foreach ($kolom_baru as $nama => $tipe) {
            if (!padi_kolom_ada($conn, $tabel, $nama)) {
                $conn->query("ALTER TABLE `$tabel` ADD COLUMN `$nama` $tipe");
            }
        }
    }
}
}

/**
 * Simpan/koreksi nilai guru untuk satu siswa. Input kosong = cabut verifikasi.
 * Tabel/kolom dibatasi daftar putih agar tidak bisa diarahkan ke tabel lain.
 */
if (!function_exists('padi_simpan_nilai_guru')) {
function padi_simpan_nilai_guru($conn, $tabel, $kolom_pemilik, $siswa_id, $input, $catatan, $guru_id)
{
    $izin = [
        'penilaian_afektif'    => 'siswa_id',
        'penilaian_psikomotor' => 'siswa_id',
        'penilaian_rekan'      => 'penilai_id',
    ];
    if (!$conn || !isset($izin[$tabel]) || $izin[$tabel] !== $kolom_pemilik) return;

    $input = trim((string)$input);
    $nilai = ($input === '') ? null : max(0, min(20, (int)$input));
    $catatan = $nilai === null ? null : mb_substr(trim((string)$catatan), 0, 300);

    $cek = $conn->prepare("SELECT id FROM `$tabel` WHERE `$kolom_pemilik` = ? LIMIT 1");
    $cek->bind_param("i", $siswa_id);
    $cek->execute();
    $baris = $cek->get_result()->fetch_assoc();
    $cek->close();

    if ($baris) {
        $id = (int)$baris['id'];
        if ($nilai === null) {
            $upd = $conn->prepare("UPDATE `$tabel` SET nilai_guru = NULL, catatan_guru = NULL, guru_id = NULL, dinilai_at = NULL WHERE id = ?");
            $upd->bind_param("i", $id);
        } else {
            $upd = $conn->prepare("UPDATE `$tabel` SET nilai_guru = ?, catatan_guru = ?, guru_id = ?, dinilai_at = NOW() WHERE id = ?");
            $upd->bind_param("isii", $nilai, $catatan, $guru_id, $id);
        }
        $upd->execute();
        $upd->close();
        return;
    }

    if ($nilai === null) return; // belum ada baris & tanpa nilai → tidak perlu dibuat

    $ins = $conn->prepare("INSERT INTO `$tabel` (`$kolom_pemilik`, nilai_guru, catatan_guru, guru_id, dinilai_at)
                           VALUES (?, ?, ?, ?, NOW())");
    $ins->bind_param("iisi", $siswa_id, $nilai, $catatan, $guru_id);
    $ins->execute();
    $ins->close();
}
}

/** Jalankan migrasi auth sekali per sesi (bukan tiap request). */
if (!function_exists('padi_pastikan_skema_auth')) {
function padi_pastikan_skema_auth($conn)
{
    if (!$conn || !empty($conn->connect_errno)) return;
    padi_mulai_sesi();
    if (!empty($_SESSION['padi_skema_auth'])) return;
    pastikanTabelAuth($conn);
    $_SESSION['padi_skema_auth'] = 1;
}
}

/**
 * Pastikan tabel `jadwal` ada. Dipisah dari migrasi auth karena sesi lama
 * (sebelum tabel dibuat) tidak menjalankan migrasi lagi.
 */
if (!function_exists('padi_pastikan_tabel_jadwal')) {
function padi_pastikan_tabel_jadwal($conn)
{
    static $selesai = false;
    if ($selesai || !$conn || !empty($conn->connect_errno)) return;
    $selesai = true;
    padi_mulai_sesi();
    if (!empty($_SESSION['padi_skema_jadwal'])) return;
    $ok = $conn->query("CREATE TABLE IF NOT EXISTS jadwal (
        id INT AUTO_INCREMENT PRIMARY KEY,
        kelas VARCHAR(50) NOT NULL,
        hari TINYINT NOT NULL,
        jam_mulai TIME NOT NULL,
        jam_selesai TIME NOT NULL,
        materi VARCHAR(150) NOT NULL,
        catatan VARCHAR(255) NULL DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_jadwal (kelas, hari, jam_mulai),
        KEY idx_jadwal_kelas (kelas, hari)
    )");
    if ($ok) $_SESSION['padi_skema_jadwal'] = 1;
}
}

/**
 * Pastikan kolom `nip` pada tabel guru ada (dipakai nama + NIP di tanda
 * tangan laporan). Idempoten dan aman dipanggil berulang.
 */
if (!function_exists('padi_pastikan_kolom_nip_guru')) {
function padi_pastikan_kolom_nip_guru($conn)
{
    static $selesai = false;
    if ($selesai || !$conn || !empty($conn->connect_errno)) return;
    $selesai = true;
    $cek = $conn->query("SHOW COLUMNS FROM guru LIKE 'nip'");
    if ($cek && $cek->num_rows === 0) {
        $conn->query("ALTER TABLE guru ADD COLUMN nip VARCHAR(30) NULL DEFAULT NULL AFTER password");
    }
}
}

/* ============================================================
   SISWA
   ============================================================ */
function normalisasiNis($nis)
{
    $bersih = preg_replace('/[^A-Za-z0-9]/', '', (string)$nis);
    return strtoupper($bersih);
}

/**
 * Ambil sesi aktif terbaru milik kelas siswa.
 * @return array|null ['id'=>int,'token'=>string,'materi'=>string,'kelas'=>string]
 */
function ambilSesiAktifKelas($conn, $kelas)
{
    $sid = function_exists('padi_sekolah_id') ? padi_sekolah_id() : 1;
    $stmt = $conn->prepare("SELECT id, token, materi, kelas FROM sesi
                            WHERE sekolah_id = ? AND kelas = ? AND status = 'aktif'
                            ORDER BY created_at DESC LIMIT 1");
    $stmt->bind_param("is", $sid, $kelas);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->num_rows > 0 ? $res->fetch_assoc() : null;
    $stmt->close();
    return $row;
}

/**
 * Pastikan baris di tabel `siswa` (peserta sesi) ada; buat bila belum.
 */
function pastikanSiswaSesi($conn, $nama, $sesi_id)
{
    $stmt = $conn->prepare("SELECT id FROM siswa WHERE nama = ? AND sesi_id = ? LIMIT 1");
    $stmt->bind_param("si", $nama, $sesi_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $stmt->close();
        return (int)$row['id'];
    }
    $stmt->close();

    $stmt = $conn->prepare("INSERT INTO siswa (nama, sesi_id) VALUES (?, ?)");
    $stmt->bind_param("si", $nama, $sesi_id);
    $stmt->execute();
    $id = (int)$stmt->insert_id;
    $stmt->close();
    return $id;
}

/**
 * Login siswa: Nomor Induk + password.
 * Bila siswa belum punya password, password pertama = token sesi aktif kelas
 * (diterbitkan guru) lalu disimpan sebagai hash.
 *
 * @return array ['success'=>bool,'message'=>string]
 */
function loginSiswa($conn, $nis, $password)
{
    $hasil = ['success' => false, 'message' => ''];
    if (!$conn) {
        $hasil['message'] = 'Database tidak terhubung.';
        return $hasil;
    }

    $nis_bersih = normalisasiNis($nis);
    if ($nis_bersih === '') {
        $hasil['message'] = 'Nomor induk wajib diisi.';
        return $hasil;
    }
    $password_in = trim((string)$password);
    if ($password_in === '') {
        $hasil['message'] = 'Password wajib diisi.';
        return $hasil;
    }

    $kunci_throttle = padi_kunci_ip('login-siswa');
    $tunggu = padi_throttle($kunci_throttle);
    if ($tunggu > 0) {
        $hasil['message'] = padi_pesan_throttle($tunggu);
        return $hasil;
    }

    // Cari siswa berdasarkan nomor induk (dibatasi per sekolah)
    $sid_sekolah = function_exists('padi_sekolah_id') ? padi_sekolah_id() : 1;
    $stmt = $conn->prepare("SELECT id, nama, nis, kelas, password, sekolah_id FROM master_siswa
                            WHERE sekolah_id = ?
                              AND UPPER(REPLACE(REPLACE(REPLACE(REPLACE(IFNULL(nis,''),' ',''),'-',''),'.',''),'/','')) = ?
                            ORDER BY id ASC LIMIT 1");
    $stmt->bind_param("is", $sid_sekolah, $nis_bersih);
    $stmt->execute();
    $res = $stmt->get_result();
    if (!$res || $res->num_rows === 0) {
        $stmt->close();
        $hasil['message'] = 'Nomor induk tidak terdaftar. Hubungi guru PJOK.';
        return $hasil;
    }
    $m = $res->fetch_assoc();
    $stmt->close();

    $siswa_id = (int)$m['id'];
    $kelas    = (string)$m['kelas'];
    $nama     = (string)$m['nama'];
    $hash     = (string)($m['password'] ?? '');

    $terverifikasi = false;
    if ($hash !== '' && password_verify($password_in, $hash)) {
        $terverifikasi = true;
    }

    // Bootstrap: password pertama = token sesi aktif kelas (diterbitkan guru).
    // Tidak ada lagi password default (NIS/123456) yang bisa ditebak.
    if (!$terverifikasi && $hash === '') {
        $sesi_aktif = ambilSesiAktifKelas($conn, $kelas);
        if ($sesi_aktif && strcasecmp($password_in, (string)$sesi_aktif['token']) === 0) {
            $terverifikasi = true;
            $baru = password_hash($password_in, PASSWORD_DEFAULT);
            $upd = $conn->prepare("UPDATE master_siswa SET password = ? WHERE id = ?");
            $upd->bind_param("si", $baru, $siswa_id);
            $upd->execute();
            $upd->close();
        }
    }

    if (!$terverifikasi) {
        padi_throttle_gagal($kunci_throttle);
        $hasil['message'] = 'Password salah. Jika baru pertama login, gunakan token materi dari guru PJOK.';
        return $hasil;
    }
    padi_throttle_reset($kunci_throttle);

    // Bersihkan sesi lalu isi ulang
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
    
    // Set sesi identitas utama
    $_SESSION['master_id']  = $siswa_id;
    $_SESSION['siswa_nama'] = $nama;
    $_SESSION['siswa_nis']  = $m['nis'];
    $_SESSION['siswa_kelas'] = $kelas;
    $_SESSION['siswa_sekolah_id'] = (int)($m['sekolah_id'] ?? $sid_sekolah);

    $sesi_aktif = ambilSesiAktifKelas($conn, $kelas);
    $hasil['token'] = $sesi_aktif ? (string)$sesi_aktif['token'] : '';

    $hasil['success'] = true;
    $hasil['message'] = 'Login berhasil.';
    return $hasil;
}

/**
 * Siswa memasukkan token untuk bergabung ke sesi kelas.
 */
function joinSesiSiswa($conn, $token)
{
    $hasil = ['success' => false, 'message' => ''];
    if (session_status() === PHP_SESSION_NONE) session_start();
    
    if (empty($_SESSION['master_id'])) {
        $hasil['message'] = 'Anda belum login.';
        return $hasil;
    }

    $token_bersih = trim((string)$token);
    if ($token_bersih === '') {
        $hasil['message'] = 'Token tidak boleh kosong.';
        return $hasil;
    }

    $kunci_throttle = padi_kunci_ip('join-token');
    $tunggu = padi_throttle($kunci_throttle, 6, 900);
    if ($tunggu > 0) {
        $hasil['message'] = padi_pesan_throttle($tunggu);
        return $hasil;
    }

    // Cari sesi berdasarkan token yang masih aktif
    $stmt = $conn->prepare("SELECT id, kelas, materi FROM sesi WHERE UPPER(token) = UPPER(?) AND status = 'aktif' LIMIT 1");
    $stmt->bind_param("s", $token_bersih);
    $stmt->execute();
    $res = $stmt->get_result();
    if (!$res || $res->num_rows === 0) {
        $stmt->close();
        padi_throttle_gagal($kunci_throttle);
        $hasil['message'] = 'Token tidak valid atau sesi sudah ditutup.';
        return $hasil;
    }
    $sesi = $res->fetch_assoc();
    $stmt->close();

    // Token hanya berlaku untuk kelas siswa yang login.
    if (strcasecmp((string)($_SESSION['siswa_kelas'] ?? ''), (string)$sesi['kelas']) !== 0) {
        padi_throttle_gagal($kunci_throttle);
        $hasil['message'] = 'Token ini bukan untuk kelas Anda.';
        return $hasil;
    }
    padi_throttle_reset($kunci_throttle);

    $peserta_id = pastikanSiswaSesi($conn, $_SESSION['siswa_nama'], (int)$sesi['id']);

    $_SESSION['siswa_id'] = $peserta_id;
    $_SESSION['sesi_id']  = (int)$sesi['id'];
    $_SESSION['materi']   = $sesi['materi'];

    $hasil['success'] = true;
    $hasil['message'] = 'Berhasil masuk ke sesi.';
    return $hasil;
}

/**
 * Siswa login menggunakan Nama (atau NIS) dan Token sesi.
 * Memastikan siswa terdaftar di data admin (master_siswa) agar rekap nilai sinkron.
 */
function loginSiswaTanpaAkun($conn, $nama, $token)
{
    $hasil = ['success' => false, 'message' => ''];
    if (session_status() === PHP_SESSION_NONE) session_start();
    
    $nama_bersih = trim((string)$nama);
    $token_bersih = trim((string)$token);
    
    if ($nama_bersih === '') {
        $hasil['message'] = 'Nama lengkap atau NIS wajib diisi.';
        return $hasil;
    }
    if ($token_bersih === '') {
        $hasil['message'] = 'Token wajib diisi.';
        return $hasil;
    }

    $kunci_throttle = padi_kunci_ip('login-tanpa-akun');
    $tunggu = padi_throttle($kunci_throttle, 6, 900);
    if ($tunggu > 0) {
        $hasil['message'] = padi_pesan_throttle($tunggu);
        return $hasil;
    }

    // 1. Cari sesi aktif berdasarkan token
    $stmt = $conn->prepare("SELECT id, materi, kelas FROM sesi WHERE UPPER(token) = UPPER(?) AND status = 'aktif' LIMIT 1");
    $stmt->bind_param("s", $token_bersih);
    $stmt->execute();
    $res = $stmt->get_result();
    if (!$res || $res->num_rows === 0) {
        $stmt->close();
        $hasil['message'] = 'Token tidak valid atau sesi sudah ditutup.';
        return $hasil;
    }
    $sesi = $res->fetch_assoc();
    $stmt->close();

    // 2. Cari siswa di master_siswa (cocok PERSIS: nama identik atau NIS)
    $nis_clean = normalisasiNis($nama_bersih);
    $nama_persis = trim($nama_bersih);

    $stmt_m = $conn->prepare("SELECT id, nama, nis, kelas FROM master_siswa
                              WHERE LOWER(nama) = LOWER(?) OR UPPER(IFNULL(nis,'')) = ?
                              ORDER BY id ASC LIMIT 1");
    $stmt_m->bind_param("ss", $nama_persis, $nis_clean);
    $stmt_m->execute();
    $res_m = $stmt_m->get_result();
    if (!$res_m || $res_m->num_rows === 0) {
        $stmt_m->close();
        padi_throttle_gagal(padi_kunci_ip('login-tanpa-akun'));
        $hasil['message'] = 'Siswa tidak ditemukan di data kelas. Pastikan nama sesuai dengan absen.';
        return $hasil;
    }
    $master = $res_m->fetch_assoc();
    $stmt_m->close();

    // 3. Sesi kelas harus cocok dengan kelas siswa (cegah masuk kelas lain)
    if (strcasecmp((string)$master['kelas'], (string)$sesi['kelas']) !== 0) {
        padi_throttle_gagal(padi_kunci_ip('login-tanpa-akun'));
        $hasil['message'] = 'Token ini bukan untuk kelas Anda.';
        return $hasil;
    }

    // 4. Pastikan siswa masuk ke tabel peserta sesi (tabel siswa)
    // agar muncul di pantauan guru
    $peserta_id = pastikanSiswaSesi($conn, $master['nama'], (int)$sesi['id']);

    // 5. Regenerasi sesi untuk keamanan
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }

    // 6. Isi sesi menggunakan data master agar fitur rekap nilai berfungsi normal
    $_SESSION['master_id'] = (int)$master['id'];
    $_SESSION['siswa_id'] = $peserta_id;
    $_SESSION['siswa_nama'] = $master['nama'];
    $_SESSION['siswa_nis'] = $master['nis'];
    $_SESSION['siswa_kelas'] = $master['kelas'];
    $_SESSION['sesi_id'] = (int)$sesi['id'];
    $_SESSION['materi'] = $sesi['materi'];

    $hasil['success'] = true;
    $hasil['message'] = 'Login berhasil.';
    return $hasil;
}

/**
 * Cek password keamanan siswa (untuk popup token/rekap, tanpa mengubah sesi).
 * @return array ['success'=>bool,'message'=>string,'token'=>string]
 */
function verifikasiPasswordSiswa($conn, $master_id, $password)
{
    $out = ['success' => false, 'message' => '', 'token' => ''];
    $kunci_throttle = padi_kunci_ip('verifikasi-password');
    $tunggu = padi_throttle($kunci_throttle, 6, 900);
    if ($tunggu > 0) {
        $out['message'] = padi_pesan_throttle($tunggu);
        return $out;
    }

    $stmt = $conn->prepare("SELECT nama, kelas, password FROM master_siswa WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $master_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if (!$res || $res->num_rows === 0) {
        $stmt->close();
        $out['message'] = 'Data siswa tidak ditemukan.';
        return $out;
    }
    $m = $res->fetch_assoc();
    $stmt->close();

    if (empty($m['password']) || !password_verify(trim((string)$password), $m['password'])) {
        padi_throttle_gagal($kunci_throttle);
        $out['message'] = 'Password salah.';
        return $out;
    }
    padi_throttle_reset($kunci_throttle);

    $sesi = ambilSesiAktifKelas($conn, $m['kelas']);
    if ($sesi) {
        $out['token'] = (string)$sesi['token'];
    }
    $out['success'] = true;
    return $out;
}

/* ============================================================
   GURU
   ============================================================ */
function loginGuru($conn, $username, $password)
{
    $out = ['success' => false, 'message' => ''];
    if (!$conn) {
        $out['message'] = 'Database tidak terhubung.';
        return $out;
    }
    padi_pastikan_kolom_nip_guru($conn);
    $username = strtolower(trim((string)$username));
    $password = trim((string)$password);
    if ($username === '' || $password === '') {
        $out['message'] = 'Email/username dan password wajib diisi.';
        return $out;
    }

    $kunci_throttle = padi_kunci_ip('login-guru');
    $tunggu = padi_throttle($kunci_throttle);
    if ($tunggu > 0) {
        $out['message'] = padi_pesan_throttle($tunggu);
        return $out;
    }

    $stmt = $conn->prepare("SELECT id, nama, username, password, nip, sekolah_id, is_super, status FROM guru WHERE LOWER(username) = ? LIMIT 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $res = $stmt->get_result();
    if (!$res || $res->num_rows === 0) {
        $stmt->close();
        padi_throttle_gagal($kunci_throttle);
        $out['message'] = 'Akun guru tidak ditemukan.';
        return $out;
    }
    $g = $res->fetch_assoc();
    $stmt->close();

    if (!password_verify($password, $g['password'])) {
        padi_throttle_gagal($kunci_throttle);
        $out['message'] = 'Password guru salah.';
        return $out;
    }
    if (isset($g['status']) && strtolower((string)$g['status']) !== 'aktif') {
        $out['message'] = 'Akun guru ini sedang dinonaktifkan. Hubungi admin sekolah.';
        return $out;
    }
    padi_throttle_reset($kunci_throttle);

    padi_sesi_guru_isi($g);

    $out['success'] = true;
    $out['message'] = 'Login berhasil.';
    return $out;
}

/** Isi sesi guru dari baris tabel `guru` (dipakai login password & Belajar.id). */
if (!function_exists('padi_sesi_guru_isi')) {
function padi_sesi_guru_isi(array $g)
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
    $_SESSION['guru_id']         = (int)$g['id'];
    $_SESSION['guru_nama']       = (string)$g['nama'];
    $_SESSION['guru_nip']        = (string)($g['nip'] ?? '');
    $_SESSION['guru_username']   = (string)$g['username'];
    $_SESSION['guru_sekolah_id'] = (int)($g['sekolah_id'] ?? 1);
    $_SESSION['guru_is_super']   = (int)($g['is_super'] ?? 0);
    if (function_exists('padi_sekolah_aktifkan')) {
        padi_sekolah_aktifkan((int)$_SESSION['guru_sekolah_id']);
    }
}
}

/**
 * Login guru memakai akun Belajar.id (Google OAuth / OpenID Connect).
 * Akun dicari berdasarkan google_sub atau email; bila belum ada dan
 * BELAJAR_ID_AUTO_DAFTAR=1 (default) akun guru dibuat di sekolah aktif.
 *
 * @param array $profil ['sub' => ..., 'email' => ..., 'name' => ...]
 */
if (!function_exists('loginGuruBelajarId')) {
function loginGuruBelajarId($conn, array $profil)
{
    $out = ['success' => false, 'message' => ''];

    if (!$conn) {
        $out['message'] = 'Database tidak terhubung.';
        return $out;
    }
    padi_pastikan_kolom_nip_guru($conn);
    padi_tenant_bootstrap($conn);

    $email = strtolower(trim((string)($profil['email'] ?? '')));
    $sub   = trim((string)($profil['sub'] ?? ''));
    $nama  = trim((string)($profil['name'] ?? ''));

    if ($email === '') {
        $out['message'] = 'Akun Belajar.id tidak mengirim alamat email. Coba masuk ulang.';
        return $out;
    }

    // Batasi domain email (default: belajar.id).
    $domain = substr($email, (int)strpos($email, '@') + 1);
    $izin = array_filter(array_map('trim', explode(',', strtolower(padi_tenant_env('BELAJAR_ID_DOMAIN', 'belajar.id')))));
    $cocok = false;
    foreach ($izin as $pola) {
        if ($pola === '') continue;
        if ($pola[0] === '*') {
            $akhiran = substr($pola, 1); // "*.sch.id" → ".sch.id"
            if (substr($domain, -strlen($akhiran)) === $akhiran) $cocok = true;
        } elseif ($domain === $pola) {
            $cocok = true;
        }
    }
    if (!$cocok) {
        $out['message'] = 'Email ' . $email . ' bukan akun Belajar.id yang diizinkan.';
        return $out;
    }

    $sid = function_exists('padi_sekolah_id') ? padi_sekolah_id() : 1;

    $stmt = $conn->prepare("SELECT id, nama, username, nip, sekolah_id, is_super, status
                            FROM guru
                            WHERE (google_sub = ? AND google_sub <> '') OR (email IS NOT NULL AND email = ?)
                            ORDER BY (sekolah_id = ?) DESC, id ASC LIMIT 1");
    $stmt->bind_param('ssi', $sub, $email, $sid);
    $stmt->execute();
    $res = $stmt->get_result();
    $g = $res && $res->num_rows > 0 ? $res->fetch_assoc() : null;
    $stmt->close();

    if ($g && strtolower((string)$g['status']) !== 'aktif') {
        $out['message'] = 'Akun guru ini sedang dinonaktifkan. Hubungi admin sekolah.';
        return $out;
    }

    if (!$g) {
        $auto = padi_tenant_env('BELAJAR_ID_AUTO_DAFTAR', '1');
        if ($auto === '0' || strtolower($auto) === 'false') {
            $out['message'] = 'Akun Belajar.id ini belum terdaftar sebagai guru di sekolah ini. Minta admin sekolah menambahkan akun Anda.';
            return $out;
        }

        $namaGuru = $nama !== '' ? $nama : ucfirst(strtok($email, '@'));
        $username = strtolower(preg_replace('/[^a-z0-9._-]/', '', strtok($email, '@')));
        // username unik per sekolah
        $cek = $conn->prepare("SELECT id FROM guru WHERE sekolah_id = ? AND LOWER(username) = ? LIMIT 1");
        $cek->bind_param('is', $sid, $username);
        $cek->execute();
        $sudahAda = $cek->get_result()->num_rows > 0;
        $cek->close();
        if ($sudahAda) $username .= '-' . substr(md5($email . microtime()), 0, 4);

        $acak = bin2hex(random_bytes(16));
        $hash = password_hash($acak, PASSWORD_DEFAULT);
        $ins = $conn->prepare("INSERT INTO guru (nama, username, password, sekolah_id, email, google_sub, is_super, status)
                               VALUES (?, ?, ?, ?, ?, ?, 0, 'aktif')");
        if (!$ins) {
            $out['message'] = 'Gagal membuat akun guru baru.';
            return $out;
        }
        $ins->bind_param('sssisss', $namaGuru, $username, $hash, $sid, $email, $sub);
        $ins->execute();
        $baruId = (int)$ins->insert_id;
        $ins->close();

        $stmt = $conn->prepare("SELECT id, nama, username, nip, sekolah_id, is_super, status FROM guru WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $baruId);
        $stmt->execute();
        $g = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    } else {
        $upd = $conn->prepare("UPDATE guru SET google_sub = ?, email = ? WHERE id = ?");
        if ($upd) {
            $upd->bind_param('ssi', $sub, $email, $g['id']);
            $upd->execute();
            $upd->close();
        }
    }

    if (!$g) {
        $out['message'] = 'Akun guru tidak dapat disiapkan.';
        return $out;
    }

    padi_sesi_guru_isi($g);
    $out['success'] = true;
    $out['message'] = 'Login Belajar.id berhasil.';
    return $out;
}
}

/* ============================================================
   GUARD HALAMAN
   ============================================================ */function wajibLoginGuru()
{
    padi_mulai_sesi();
    if (empty($_SESSION['guru_id'])) {
        padi_kembali(padi_url_login('guru'), 'Sesi guru belum aktif. Silakan masuk kembali.');
    }

       // Sesi lama (sebelum multi-tenant) belum menyimpan sekolah_id.
       if (empty($_SESSION['guru_sekolah_id']) && !empty($GLOBALS['conn'])) {
           $stmt = $GLOBALS['conn']->prepare("SELECT sekolah_id, is_super FROM guru WHERE id = ? LIMIT 1");
           if ($stmt) {
               $gid = (int)$_SESSION['guru_id'];
               $stmt->bind_param('i', $gid);
               $stmt->execute();
               $row = $stmt->get_result()->fetch_assoc();
               $stmt->close();
               if ($row) {
                   $_SESSION['guru_sekolah_id'] = (int)($row['sekolah_id'] ?? 1);
                   $_SESSION['guru_is_super']   = (int)($row['is_super'] ?? 0);
               }
           }
       }
   }

function wajibLoginSiswa()
{
    padi_mulai_sesi();

    // Belum login sama sekali -> ke halaman login siswa
    if (empty($_SESSION['master_id'])) {
        padi_kembali(padi_url_login('siswa'), 'Sesi siswa belum aktif. Silakan masuk kembali.');
    }

    // Sudah login, tapi belum input token sesi -> ke input-token.php
    if (empty($_SESSION['siswa_id'])) {
        padi_kembali('input-token.php', 'Masukkan token sesi dari guru untuk melanjutkan.');
    }
}

function isLoginGuru()
{
    padi_mulai_sesi();
    return !empty($_SESSION['guru_id']);
}

function isLoginSiswa()
{
    padi_mulai_sesi();
    // Dianggap login siswa utuh jika sudah mengisi token (punya siswa_id)
    return !empty($_SESSION['siswa_id']);
}

/* ============================================================
   Halaman guru (butuh login guru)
   ============================================================ */
function daftarHalamanGuru()
{
    return [
        'dashboard-guru.php', 'dashboard-guru-tmp.php', 'buat-token.php', 'mulai-sesi.php',
        'data-siswa.php', 'import-siswa.php', 'export-siswa.php', 'cetak-siswa.php',
        'nilai-guru.php', 'pantau-siswa.php', 'laporan.php',
        'rekap-penilaian.php', 'rekap-penilaian-siswa.php', 'perkembangan-semester.php',
        'penilaian-afektif.php', 'penilaian-kognitif.php', 'penilaian-psikomotor.php',
        'penilaian-rekan.php', 'feedback-guru.php', 'profil-guru.php', 'aktivitas-guru.php',
        'rubrik-platform.php', 'verifikasi-nilai.php'
    ];
}

/* ============================================================
   Halaman siswa (butuh login siswa)
   ============================================================ */
function daftarHalamanSiswa()
{
    return ['dashboard-siswa.php', 'aktivitas-siswa.php'];
}
