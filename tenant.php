<?php
/**
 * Multi-tenant ringan PADI-PJOK.
 *
 * Model: satu database, setiap tabel inti punya kolom `sekolah_id`.
 * Sekolah aktif ditentukan dari (urutan prioritas):
 *   1. sekolah milik akun yang sedang login (guru/siswa)
 *   2. subdomain host, mis. sman1.padipjok.pintarhub.com → slug "sman1"
 *   3. parameter ?sekolah=slug (hanya lokal/dev atau super-admin)
 *   4. TENANT_DEFAULT di .env, lalu sekolah aktif pertama
 */

if (!defined('PADI_TENANT_VERSION')) define('PADI_TENANT_VERSION', '1');

/** Tabel inti yang wajib punya kolom sekolah_id. */
if (!function_exists('padi_tabel_tenant')) {
function padi_tabel_tenant()
{
    return ['guru', 'master_siswa', 'master_kelas', 'sesi', 'jadwal'];
}
}

if (!function_exists('padi_tenant_env')) {
function padi_tenant_env($kunci, $bawaan = '')
{
    $nilai = getenv($kunci);
    if ($nilai === false || trim((string)$nilai) === '') return $bawaan;
    return trim((string)$nilai);
}
}

if (!function_exists('padi_slugkan')) {
function padi_slugkan($teks)
{
    $slug = strtolower(trim((string)$teks));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    return trim((string)$slug, '-');
}
}

if (!function_exists('padi_tenant_kolom_ada')) {
function padi_tenant_kolom_ada($conn, $tabel, $kolom)
{
    if (!$conn) return false;
    $tabel = preg_replace('/[^A-Za-z0-9_]/', '', (string)$tabel);
    $stmt = $conn->prepare("SHOW COLUMNS FROM `" . $tabel . "` LIKE ?");
    if (!$stmt) return false;
    $stmt->bind_param('s', $kolom);
    $stmt->execute();
    $ada = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $ada;
}
}

if (!function_exists('padi_tenant_indeks_ada')) {
function padi_tenant_indeks_ada($conn, $tabel, $indeks)
{
    if (!$conn) return false;
    $tabel = preg_replace('/[^A-Za-z0-9_]/', '', (string)$tabel);
    $stmt = $conn->prepare("SHOW INDEX FROM `" . $tabel . "` WHERE Key_name = ?");
    if (!$stmt) return false;
    $stmt->bind_param('s', $indeks);
    $stmt->execute();
    $ada = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $ada;
}
}

/**
 * Siapkan skema multi-tenant (idempoten).
 * Dipanggil otomatis dari koneksi.php setelah koneksi database siap.
 */
if (!function_exists('padi_tenant_bootstrap')) {
function padi_tenant_bootstrap($conn)
{
    static $selesai = false;
    if ($selesai || !$conn || !empty($conn->connect_errno)) return;
    $selesai = true;

    $bolehSesi = function_exists('padi_mulai_sesi') || session_status() === PHP_SESSION_ACTIVE;
    if ($bolehSesi && function_exists('padi_mulai_sesi')) {
        padi_mulai_sesi();
        if (!empty($_SESSION['padi_skema_tenant'])) return;
    }

    $conn->query("CREATE TABLE IF NOT EXISTS sekolah (
        id INT AUTO_INCREMENT PRIMARY KEY,
        slug VARCHAR(60) NOT NULL UNIQUE,
        nama VARCHAR(150) NOT NULL,
        instansi VARCHAR(150) NULL DEFAULT NULL,
        alamat VARCHAR(255) NULL DEFAULT NULL,
        kota VARCHAR(100) NULL DEFAULT NULL,
        npsn VARCHAR(20) NULL DEFAULT NULL,
        tahun_pelajaran VARCHAR(20) NULL DEFAULT NULL,
        kepala_sekolah VARCHAR(100) NULL DEFAULT NULL,
        kepala_nip VARCHAR(30) NULL DEFAULT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'aktif',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    foreach (padi_tabel_tenant() as $tabel) {
        if (!padi_tenant_kolom_ada($conn, $tabel, 'sekolah_id')) {
            $conn->query("ALTER TABLE `$tabel` ADD COLUMN sekolah_id INT NOT NULL DEFAULT 1");
            $conn->query("ALTER TABLE `$tabel` ADD KEY idx_{$tabel}_sekolah (sekolah_id)");
        }
    }

    // Kolom akun guru untuk login Belajar.id (Google OAuth) + peran admin tenant.
    foreach ([
        'email'      => "ALTER TABLE guru ADD COLUMN email VARCHAR(150) NULL DEFAULT NULL",
        'google_sub' => "ALTER TABLE guru ADD COLUMN google_sub VARCHAR(64) NULL DEFAULT NULL",
        'is_super'   => "ALTER TABLE guru ADD COLUMN is_super TINYINT(1) NOT NULL DEFAULT 0",
        'is_admin'   => "ALTER TABLE guru ADD COLUMN is_admin TINYINT(1) NOT NULL DEFAULT 0",
        'status'     => "ALTER TABLE guru ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'aktif'",
        'terakhir_login' => "ALTER TABLE guru ADD COLUMN terakhir_login DATETIME NULL DEFAULT NULL",
    ] as $kolom => $sql) {
        if (!padi_tenant_kolom_ada($conn, 'guru', $kolom)) {
            $conn->query($sql);
        }
    }
    if (!padi_tenant_indeks_ada($conn, 'guru', 'uniq_guru_sekolah_email')) {
        $conn->query("ALTER TABLE guru ADD UNIQUE KEY uniq_guru_sekolah_email (sekolah_id, email)");
    }
    if (!padi_tenant_indeks_ada($conn, 'guru', 'uniq_guru_sekolah_google')) {
        $conn->query("ALTER TABLE guru ADD UNIQUE KEY uniq_guru_sekolah_google (sekolah_id, google_sub)");
    }

    // Kunci unik lama tidak menyertakan sekolah → bentrok antar sekolah.
    if (padi_tenant_indeks_ada($conn, 'master_kelas', 'nama_kelas')) {
        $conn->query("ALTER TABLE master_kelas DROP INDEX nama_kelas");
        $conn->query("ALTER TABLE master_kelas ADD UNIQUE KEY uniq_kelas_sekolah (sekolah_id, nama_kelas)");
    }
    if (padi_tenant_indeks_ada($conn, 'jadwal', 'uniq_jadwal')) {
        $conn->query("ALTER TABLE jadwal DROP INDEX uniq_jadwal");
        $conn->query("ALTER TABLE jadwal ADD UNIQUE KEY uniq_jadwal (sekolah_id, kelas, hari, jam_mulai)");
    }

    // Sekolah pertama: ambil nilai awal dari .env yang sudah ada.
    $q = $conn->query("SELECT COUNT(*) AS c FROM sekolah");
    $jumlah = ($q && ($r = $q->fetch_assoc())) ? (int)$r['c'] : 0;
    if ($jumlah === 0) {
        $slug = padi_slugkan(padi_tenant_env('TENANT_DEFAULT', 'utama'));
        if ($slug === '') $slug = 'utama';
        $nama    = padi_tenant_env('SEKOLAH_NAMA', 'Sekolah Utama');
        $instansi = padi_tenant_env('SEKOLAH_INSTANSI', '');
        $alamat  = padi_tenant_env('SEKOLAH_ALAMAT', '');
        $kota    = padi_tenant_env('SEKOLAH_KOTA', '');
        $npsn    = padi_tenant_env('SEKOLAH_NPSN', '');
        $tahun   = padi_tenant_env('TAHUN_PELAJARAN', '');
        $kepala  = padi_tenant_env('KEPALA_SEKOLAH', '');
        $kepalaNip = padi_tenant_env('KEPALA_SEKOLAH_NIP', '');
        $stmt = $conn->prepare("INSERT INTO sekolah (slug, nama, instansi, alamat, kota, npsn, tahun_pelajaran, kepala_sekolah, kepala_nip, status)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'aktif')");
        if ($stmt) {
            $stmt->bind_param('sssssssss', $slug, $nama, $instansi, $alamat, $kota, $npsn, $tahun, $kepala, $kepalaNip);
            $stmt->execute();
            $stmt->close();
        }
    }

    // Akun guru terlama menjadi super-admin agar bisa mengelola sekolah.
    $q = $conn->query("SELECT COUNT(*) AS c FROM guru WHERE is_super = 1");
    $adaSuper = ($q && ($r = $q->fetch_assoc())) ? (int)$r['c'] : 0;
    if ($adaSuper === 0) {
        $conn->query("UPDATE guru SET is_super = 1 ORDER BY id ASC LIMIT 1");
    }

    // Setiap sekolah wajib punya minimal satu admin tenant (guru pertama).
    $q = $conn->query("SELECT s.id FROM sekolah s
                       WHERE NOT EXISTS (SELECT 1 FROM guru g WHERE g.sekolah_id = s.id AND g.is_admin = 1)");
    if ($q) {
        while ($s = $q->fetch_assoc()) {
            $sidAwal = (int)$s['id'];
            $stmt = $conn->prepare("SELECT id FROM guru WHERE sekolah_id = ? ORDER BY id ASC LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('i', $sidAwal);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($row) {
                    $upd = $conn->prepare("UPDATE guru SET is_admin = 1 WHERE id = ?");
                    $upd->bind_param('i', $row['id']);
                    $upd->execute();
                    $upd->close();
                }
            }
        }
    }

    if ($bolehSesi) {
        $_SESSION['padi_skema_tenant'] = 1;
    }
}
}

if (!function_exists('padi_tenant_host')) {
function padi_tenant_host()
{
    $host = (string)($_SERVER['HTTP_HOST'] ?? '');
    $host = strtolower(preg_replace('/:\d+$/', '', $host));
    return $host;
}
}

if (!function_exists('padi_tenant_slug_host')) {
/** Slug sekolah dari subdomain host, mis. sman1.padipjok.pintarhub.com → sman1 */
function padi_tenant_slug_host()
{
    $host = padi_tenant_host();
    if ($host === '' || $host === 'localhost' || filter_var($host, FILTER_VALIDATE_IP)) return '';
    $dasar = strtolower(padi_tenant_env('BASE_DOMAIN', ''));
    if ($dasar !== '' && substr($host, -strlen($dasar)) === $dasar) {
        $sub = trim(substr($host, 0, strlen($host) - strlen($dasar)), '.');
    } else {
        $bagian = explode('.', $host);
        // Tanpa BASE_DOMAIN: anggap label pertama adalah slug bila ada >2 label.
        $sub = count($bagian) >= 3 ? $bagian[0] : '';
    }
    if ($sub === '' || in_array($sub, ['www', 'app', 'api'], true)) return '';
    return padi_slugkan($sub);
}
}

if (!function_exists('padi_tenant_lokal')) {
function padi_tenant_lokal()
{
    $host = padi_tenant_host();
    return PHP_SAPI === 'cli'
        || $host === 'localhost'
        || $host === '127.0.0.1'
        || $host === '[::1]'
        || strpos($host, '127.0.0.1') === 0
        || (strpos($host, '192.168.') === 0)
        || (strpos($host, '10.') === 0);
}
}

if (!function_exists('padi_sekolah_aktifkan')) {
/** Tetapkan sekolah aktif untuk sesi ini (dipakai saat login / super-admin). */
function padi_sekolah_aktifkan($id)
{
    $id = (int)$id;
    if ($id <= 0) return false;
    $GLOBALS['padi_tenant_override'] = $id;
    if (session_status() === PHP_SESSION_NONE || !isset($_SESSION)) {
        if (function_exists('padi_mulai_sesi')) padi_mulai_sesi();
    }
    $_SESSION['sekolah_pilihan'] = $id;
    return true;
}
}

if (!function_exists('padi_sekolah_cari')) {
/** Ambil satu sekolah (array) berdasarkan id atau slug; null bila tidak ada. */
function padi_sekolah_cari($conn, $idAtauSlug)
{
    if (!$conn) return null;
    $stmt = $conn->prepare("SELECT * FROM sekolah WHERE slug = ? OR id = ? LIMIT 1");
    if (!$stmt) return null;
    $slug = (string)$idAtauSlug;
    $id = (int)$idAtauSlug;
    $stmt->bind_param('si', $slug, $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    return $row ?: null;
}
}

if (!function_exists('padi_sekolah_id')) {
/** Id sekolah aktif untuk request ini. */
function padi_sekolah_id()
{
    if (!empty($GLOBALS['padi_tenant_id'])) return (int)$GLOBALS['padi_tenant_id'];
    if (!empty($GLOBALS['padi_tenant_override'])) {
        $GLOBALS['padi_tenant_id'] = (int)$GLOBALS['padi_tenant_override'];
        return (int)$GLOBALS['padi_tenant_id'];
    }

    $conn = $GLOBALS['conn'] ?? null;
    if (!$conn) return 1;

    $kandidat = [];
    $superAdmin = !empty($_SESSION['guru_is_super']);

    // 1. ?sekolah= — hanya lokal/dev atau super-admin (menang atas identitas login).
    $param = isset($_GET['sekolah']) ? trim((string)$_GET['sekolah']) : '';
    if ($param !== '' && (padi_tenant_lokal() || $superAdmin)) {
        $row = padi_sekolah_cari($conn, $param);
        if ($row) {
            $_SESSION['sekolah_pilihan'] = (int)$row['id'];
            $GLOBALS['padi_tenant_id'] = (int)$row['id'];
            return (int)$row['id'];
        }
    }

    // 2. Super-admin yang sudah memilih sekolah lain lewat tombol "Masuk".
    if ($superAdmin && !empty($_SESSION['sekolah_pilihan'])
        && (int)$_SESSION['sekolah_pilihan'] !== (int)($_SESSION['guru_sekolah_id'] ?? 0)) {
        $GLOBALS['padi_tenant_id'] = (int)$_SESSION['sekolah_pilihan'];
        return (int)$_SESSION['sekolah_pilihan'];
    }

    // 3. Sekolah milik akun yang login.
    if (!empty($_SESSION['guru_sekolah_id'])) $kandidat[] = (int)$_SESSION['guru_sekolah_id'];
    if (!empty($_SESSION['siswa_sekolah_id'])) $kandidat[] = (int)$_SESSION['siswa_sekolah_id'];

    // 4. Subdomain.
    $slugHost = padi_tenant_slug_host();
    if ($slugHost !== '') {
        $row = padi_sekolah_cari($conn, $slugHost);
        if ($row && strtolower((string)$row['status']) === 'aktif') $kandidat[] = (int)$row['id'];
    }

    // 5. Pilihan sesi, lalu default .env, lalu sekolah aktif pertama.
    if (!empty($_SESSION['sekolah_pilihan'])) $kandidat[] = (int)$_SESSION['sekolah_pilihan'];
    $default = padi_slugkan(padi_tenant_env('TENANT_DEFAULT', ''));
    if ($default !== '') {
        $row = padi_sekolah_cari($conn, $default);
        if ($row) $kandidat[] = (int)$row['id'];
    }

    foreach ($kandidat as $id) {
        if ($id > 0) {
            $GLOBALS['padi_tenant_id'] = $id;
            return $id;
        }
    }

    $q = $conn->query("SELECT id FROM sekolah WHERE status = 'aktif' ORDER BY id ASC LIMIT 1");
    $id = ($q && ($r = $q->fetch_assoc())) ? (int)$r['id'] : 1;
    $GLOBALS['padi_tenant_id'] = $id;
    return $id;
}
}

if (!function_exists('padi_sekolah')) {
/** Data sekolah aktif: array penuh, atau satu kolom bila $kolom diberikan. */
function padi_sekolah($kolom = null)
{
    $conn = $GLOBALS['conn'] ?? null;
    if (!empty($GLOBALS['padi_tenant_row'])) {
        $row = $GLOBALS['padi_tenant_row'];
    } else {
        $row = padi_sekolah_cari($conn, padi_sekolah_id());
        if (!$row) {
            $row = ['id' => 1, 'slug' => 'utama', 'nama' => padi_tenant_env('SEKOLAH_NAMA', ''), 'status' => 'aktif'];
        }
        $GLOBALS['padi_tenant_row'] = $row;
    }
    if ($kolom === null) return $row;
    return $row[$kolom] ?? '';
}
}

if (!function_exists('padi_sekolah_daftar')) {
/** Daftar sekolah (untuk super-admin / pemilih sekolah). */
function padi_sekolah_daftar($conn, $hanyaAktif = true)
{
    $daftar = [];
    if (!$conn) return $daftar;
    $sql = $hanyaAktif ? "SELECT * FROM sekolah WHERE status = 'aktif' ORDER BY nama ASC" : "SELECT * FROM sekolah ORDER BY nama ASC";
    $q = $conn->query($sql);
    if ($q) {
        while ($row = $q->fetch_assoc()) $daftar[] = $row;
    }
    return $daftar;
}
}

if (!function_exists('padi_sekolah_url')) {
/** URL halaman di sekolah tertentu: subdomain bila BASE_DOMAIN ada, kalau tidak ?sekolah=slug. */
function padi_sekolah_url($slug, $halaman = 'index.php', $skema = null)
{
    $slug = padi_slugkan($slug);
    $dasar = padi_tenant_env('BASE_DOMAIN', '');
    if ($skema === null) {
        $skema = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    }
    if ($dasar !== '' && $slug !== '') {
        return $skema . '://' . $slug . '.' . $dasar . '/' . ltrim((string)$halaman, '/');
    }
    $pemisah = strpos((string)$halaman, '?') === false ? '?' : '&';
    return '/' . ltrim((string)$halaman, '/') . $pemisah . 'sekolah=' . urlencode($slug);
}
}
