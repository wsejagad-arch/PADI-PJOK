<?php
// auth.php — Helper autentikasi (guru & siswa) + migrasi kolom/tabel.
// Dipakai oleh login-guru.php, login-siswa.php, dan guard halaman.

require_once __DIR__ . '/koneksi.php';

/* ============================================================
   MIGRASI RINGAN (aman dijalankan berulang)
   - master_siswa: tambah kolom password (hash)
   - tabel guru: akun guru (username + password hash)
   ============================================================ */
function pastikanTabelAuth($conn)
{
    if (!$conn) return;

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
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // 3. Akun guru bawaan (hanya bila tabel masih kosong)
    $cek = $conn->query("SELECT COUNT(*) AS c FROM guru");
    if ($cek && ($r = $cek->fetch_assoc()) && (int)$r['c'] === 0) {
        $nama = 'Guru PJOK';
        $user = 'guru';
        $hash = password_hash('guru123', PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO guru (nama, username, password) VALUES (?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("sss", $nama, $user, $hash);
            $stmt->execute();
            $stmt->close();
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
    $stmt = $conn->prepare("SELECT id, token, materi, kelas FROM sesi
                            WHERE kelas = ? AND status = 'aktif'
                            ORDER BY created_at DESC LIMIT 1");
    $stmt->bind_param("s", $kelas);
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
 * Bila siswa belum punya password, gunakan Nomor Induk (NIS) sebagai default awal
 * dan simpan sebagai hash agar aman.
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

    // Cari siswa berdasarkan nomor induk
    $stmt = $conn->prepare("SELECT id, nama, nis, kelas, password FROM master_siswa
                            WHERE UPPER(REPLACE(REPLACE(REPLACE(REPLACE(IFNULL(nis,''),' ',''),'-',''),'.',''),'/','')) = ?
                            ORDER BY id ASC LIMIT 1");
    $stmt->bind_param("s", $nis_bersih);
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

    // Bootstrap: password pertama kali = NIS
    if (!$terverifikasi && $hash === '') {
        if (strcasecmp($password_in, $nis_bersih) === 0 || strcasecmp($password_in, '123456') === 0) {
            $terverifikasi = true;
            $baru = password_hash($password_in, PASSWORD_DEFAULT);
            $upd = $conn->prepare("UPDATE master_siswa SET password = ? WHERE id = ?");
            $upd->bind_param("si", $baru, $siswa_id);
            $upd->execute();
            $upd->close();
        }
    }

    if (!$terverifikasi) {
        $hasil['message'] = 'Password salah. (Jika baru pertama login, gunakan NIS Anda sebagai password).';
        return $hasil;
    }

    // Bersihkan sesi lalu isi ulang
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
    
    // Set sesi identitas utama
    $_SESSION['master_id']  = $siswa_id;
    $_SESSION['siswa_nama'] = $nama;
    $_SESSION['siswa_nis']  = $m['nis'];
    $_SESSION['siswa_kelas'] = $kelas;

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

    // Cari sesi berdasarkan token yang masih aktif
    $stmt = $conn->prepare("SELECT id, kelas, materi FROM sesi WHERE token = ? AND status = 'aktif' LIMIT 1");
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

    // Opsional: Cek apakah sesi ini untuk kelas siswa? 
    // Kita anggap jika token tahu, dia bisa ikut (beberapa skenario mungkin membatasi kelas)
    if (strcasecmp($_SESSION['siswa_kelas'], $sesi['kelas']) !== 0) {
        // $hasil['message'] = 'Token ini bukan untuk kelas Anda.';
        // return $hasil;
        // Opsional: biarkan saja jika token benar.
    }

    $peserta_id = pastikanSiswaSesi($conn, $_SESSION['siswa_nama'], (int)$sesi['id']);

    $_SESSION['siswa_id'] = $peserta_id;
    $_SESSION['sesi_id']  = (int)$sesi['id'];
    $_SESSION['materi']   = $sesi['materi'];

    $hasil['success'] = true;
    $hasil['message'] = 'Berhasil masuk ke sesi.';
    return $hasil;
}

/**
 * Cek password keamanan siswa (untuk popup token/rekap, tanpa mengubah sesi).
 * @return array ['success'=>bool,'message'=>string,'token'=>string]
 */
function verifikasiPasswordSiswa($conn, $master_id, $password)
{
    $out = ['success' => false, 'message' => '', 'token' => ''];
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
        $out['message'] = 'Password salah.';
        return $out;
    }

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
    $username = strtolower(trim((string)$username));
    $password = trim((string)$password);
    if ($username === '' || $password === '') {
        $out['message'] = 'Email/username dan password wajib diisi.';
        return $out;
    }

    $stmt = $conn->prepare("SELECT id, nama, username, password FROM guru WHERE LOWER(username) = ? LIMIT 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $res = $stmt->get_result();
    if (!$res || $res->num_rows === 0) {
        $stmt->close();
        $out['message'] = 'Akun guru tidak ditemukan.';
        return $out;
    }
    $g = $res->fetch_assoc();
    $stmt->close();

    if (!password_verify($password, $g['password'])) {
        $out['message'] = 'Password guru salah.';
        return $out;
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
    $_SESSION['guru_id']       = (int)$g['id'];
    $_SESSION['guru_nama']     = $g['nama'];
    $_SESSION['guru_username'] = $g['username'];

    $out['success'] = true;
    $out['message'] = 'Login berhasil.';
    return $out;
}

/* ============================================================
   GUARD HALAMAN
   ============================================================ */
function wajibLoginGuru()
{
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['guru_id'])) {
        header('Location: login-guru.php');
        exit;
    }
}

function wajibLoginSiswa()
{
    if (session_status() === PHP_SESSION_NONE) session_start();
    
    // Belum login sama sekali -> ke index
    if (empty($_SESSION['master_id'])) {
        header('Location: index.php');
        exit;
    }
    
    // Sudah login, tapi belum input token sesi -> ke input-token.php
    if (empty($_SESSION['siswa_id'])) {
        header('Location: input-token.php');
        exit;
    }
}

function isLoginGuru()
{
    if (session_status() === PHP_SESSION_NONE) session_start();
    return !empty($_SESSION['guru_id']);
}

function isLoginSiswa()
{
    if (session_status() === PHP_SESSION_NONE) session_start();
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
        'rubrik-platform.php'
    ];
}

/* ============================================================
   Halaman siswa (butuh login siswa)
   ============================================================ */
function daftarHalamanSiswa()
{
    return ['dashboard-siswa.php', 'aktivitas-siswa.php'];
}
