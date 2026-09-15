<?php
// setup_db.php
require 'koneksi.php';

echo "Memulai setup database...<br/>\n";

// 1. Buat Database
$sql_db = "CREATE DATABASE IF NOT EXISTS padi_pjok CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;";
if ($conn_setup->query($sql_db) === TRUE) {
    echo "✅ Database 'padi_pjok' berhasil dipastikan ada.<br/>\n";
} else {
    die("❌ Gagal membuat database: " . $conn_setup->error);
}

// Gunakan database tersebut
$conn_setup->select_db("padi_pjok");

// 2a. Buat Tabel Master Kelas
$sql_master_kelas = "CREATE TABLE IF NOT EXISTS master_kelas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_kelas VARCHAR(50) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);";

// 2b. Buat Tabel Sesi (Token Guru)
$sql_sesi = "CREATE TABLE IF NOT EXISTS sesi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    token VARCHAR(20) NOT NULL UNIQUE,
    kelas VARCHAR(50) NOT NULL,
    materi VARCHAR(100) NOT NULL,
    status ENUM('aktif', 'selesai') DEFAULT 'aktif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);";

// 2b. Buat Tabel Master Siswa (Daftar Siswa per Kelas)
$sql_master_siswa = "CREATE TABLE IF NOT EXISTS master_siswa (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nis VARCHAR(50) NULL,
    nama VARCHAR(100) NOT NULL,
    kelas VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);";

// 3. Buat Tabel Siswa
$sql_siswa = "CREATE TABLE IF NOT EXISTS siswa (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    sesi_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sesi_id) REFERENCES sesi(id) ON DELETE CASCADE
);";

// 4. Buat Tabel Penilaian Kognitif
$sql_kognitif = "CREATE TABLE IF NOT EXISTS penilaian_kognitif (
    id INT AUTO_INCREMENT PRIMARY KEY,
    siswa_id INT NOT NULL,
    nilai_total INT DEFAULT 0,
    jawaban_detail JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE
);";

// 5. Buat Tabel Penilaian Psikomotor
$sql_psikomotor = "CREATE TABLE IF NOT EXISTS penilaian_psikomotor (
    id INT AUTO_INCREMENT PRIMARY KEY,
    siswa_id INT NOT NULL,
    nilai_rubrik INT DEFAULT 0,
    analisis_gerak TEXT,
    video_path VARCHAR(255),
    feedback_guru TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE
);";

// 6. Buat Tabel Penilaian Afektif
$sql_afektif = "CREATE TABLE IF NOT EXISTS penilaian_afektif (
    id INT AUTO_INCREMENT PRIMARY KEY,
    siswa_id INT NOT NULL,
    nilai_rubrik INT DEFAULT 0,
    refleksi TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE
);";

// 7. Buat Tabel Penilaian Rekan
$sql_rekan = "CREATE TABLE IF NOT EXISTS penilaian_rekan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    penilai_id INT NOT NULL,
    dinilai_id INT, /* Bisa direferensikan ke tabel siswa jika ada sistem assign */
    nilai_rubrik INT DEFAULT 0,
    umpan_balik TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (penilai_id) REFERENCES siswa(id) ON DELETE CASCADE
);";

// 8. Buat Tabel Notifikasi
$sql_notifikasi = "CREATE TABLE IF NOT EXISTS notifikasi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pesan VARCHAR(255) NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);";

// Eksekusi pembuatan tabel
$tables = [
    'master_kelas' => $sql_master_kelas,
    'sesi' => $sql_sesi,
    'master_siswa' => $sql_master_siswa,
    'siswa' => $sql_siswa,
    'penilaian_kognitif' => $sql_kognitif,
    'penilaian_psikomotor' => $sql_psikomotor,
    'penilaian_afektif' => $sql_afektif,
    'penilaian_rekan' => $sql_rekan,
    'notifikasi' => $sql_notifikasi
];

foreach ($tables as $name => $sql) {
    if ($conn_setup->query($sql) === TRUE) {
        echo "✅ Tabel '$name' siap.<br/>\n";
    } else {
        echo "❌ Gagal membuat tabel '$name': " . $conn_setup->error . "<br/>\n";
    }
}

// 9. Insert default notifications
$sql_check_notif = "SELECT COUNT(*) AS count FROM notifikasi";
$result_notif = $conn_setup->query($sql_check_notif);
$row_notif = $result_notif->fetch_assoc();
if ($row_notif['count'] == 0) {
    $conn_setup->query("INSERT INTO notifikasi (pesan) VALUES 
        ('Sistem berhasil diperbarui, mari mulai sesi pertama Anda.'),
        ('Gunakan tombol [Buat Token Materi] untuk memulai kelas.'),
        ('Selamat datang di dashboard baru PADI-PJOK!')");
    echo "✅ Notifikasi default berhasil ditambahkan.<br/>\n";
}

echo "<br/>🎉 Setup Selesai! Anda dapat menghapus file ini jika sudah berada di server produksi.";
?>
