<?php
require_once 'auth.php';
wajibLoginGuru();
require 'koneksi.php';

if (!isset($_GET['action'])) exit;

$action = $_GET['action'];

if ($action === 'template') {
    // Unduh template CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Template_Data_Siswa.csv');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Nama Lengkap', 'NIS']);
    fputcsv($output, ['Ahmad Fajar', '12345']);
    fputcsv($output, ['Siti Aminah', '12346']);
    fclose($output);
    exit;
}

if ($action === 'export') {
    // Unduh data siswa kelas tertentu
    $kelas = $_GET['kelas'] ?? '';
    if (!$kelas) exit('Kelas tidak ditemukan');
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Data_Siswa_Kelas_' . preg_replace('/[^A-Za-z0-9_-]/', '', $kelas) . '.csv');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Nama Lengkap', 'NIS', 'Kelas']);
    
    $stmt = $conn->prepare("SELECT nama, nis, kelas FROM master_siswa WHERE kelas = ? ORDER BY nama ASC");
    $stmt->bind_param("s", $kelas);
    $stmt->execute();
    $res = $stmt->get_result();
    
    while($row = $res->fetch_assoc()) {
        fputcsv($output, [$row['nama'], $row['nis'], $row['kelas']]);
    }
    
    $stmt->close();
    fclose($output);
    exit;
}
