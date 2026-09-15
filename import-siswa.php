<?php
require_once 'auth.php';
wajibLoginGuru();
require 'koneksi.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file_csv'])) {
    $kelas = $_POST['kelas'] ?? '';
    
    if (!$kelas) {
        die("Kelas tidak ditemukan. Silakan kembali.");
    }
    
    $fileName = $_FILES['file_csv']['tmp_name'];
    
    if ($_FILES['file_csv']['size'] > 0) {
        $file = fopen($fileName, "r");
        
        // Skip baris pertama jika itu header
        $first_row = fgetcsv($file, 10000, ",");
        $is_header = false;
        if ($first_row && stripos($first_row[0], 'nama') !== false) {
            $is_header = true;
        } else {
            // rewind jika bukan header (meski ini langka, asumsi row 1 adalah data)
            fclose($file);
            $file = fopen($fileName, "r");
        }
        
        $stmt = $conn->prepare("INSERT INTO master_siswa (nama, nis, kelas) VALUES (?, ?, ?)");
        
        while (($column = fgetcsv($file, 10000, ",")) !== FALSE) {
            $nama = trim($column[0] ?? '');
            $nis = trim($column[1] ?? '');
            
            if ($nama) {
                $stmt->bind_param("sss", $nama, $nis, $kelas);
                $stmt->execute();
            }
        }
        $stmt->close();
        fclose($file);
    }
    
    header("Location: data-siswa.php?kelas=" . urlencode($kelas));
    exit;
}
