<?php
require_once 'auth.php';
wajibLoginGuru();
require 'koneksi.php';

$kelas = $_GET['kelas'] ?? '';
if (!$kelas) {
    die("Kelas tidak valid.");
}

$siswa_list = [];
$stmt = $conn->prepare("SELECT nama, nis FROM master_siswa WHERE kelas = ? ORDER BY nama ASC");
$stmt->bind_param("s", $kelas);
$stmt->execute();
$res = $stmt->get_result();
while($row = $res->fetch_assoc()) {
    $siswa_list[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Cetak Data Siswa Kelas <?= htmlspecialchars($kelas) ?></title>
  <style>
    @page { margin: 20mm; }
    body { font-family: 'Times New Roman', Times, serif; font-size: 12pt; color: #000; background: #fff; margin: 0; padding: 20px; }
    
    .kop-surat { text-align: center; border-bottom: 3px solid #000; padding-bottom: 15px; margin-bottom: 20px; }
    .kop-surat h1 { margin: 0 0 5px 0; font-size: 18pt; text-transform: uppercase; }
    .kop-surat p { margin: 0; font-size: 12pt; }
    
    .judul-dokumen { text-align: center; margin-bottom: 20px; }
    .judul-dokumen h2 { margin: 0 0 5px 0; font-size: 14pt; text-decoration: underline; text-transform: uppercase; }
    .judul-dokumen p { margin: 0; font-size: 12pt; }
    
    table.data-tabel { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
    table.data-tabel th, table.data-tabel td { border: 1px solid #000; padding: 8px 10px; text-align: left; }
    table.data-tabel th { background-color: #f2f2f2; font-weight: bold; text-align: center; }
    table.data-tabel td.no { text-align: center; width: 40px; }
    table.data-tabel td.nis { width: 100px; text-align: center; }
    
    .tanda-tangan { width: 100%; margin-top: 50px; }
    .tanda-tangan table { width: 100%; border: none; }
    .tanda-tangan td { width: 50%; border: none; text-align: center; padding: 0; vertical-align: top; }
    .tanda-tangan .spacer { height: 80px; }
    
    .print-btn { display: block; width: 200px; margin: 0 auto 30px; padding: 10px; background: #1A56DB; color: #fff; text-align: center; border: none; border-radius: 5px; font-family: sans-serif; font-weight: bold; cursor: pointer; }
    
    @media print {
        .print-btn { display: none; }
        body { padding: 0; }
    }
  </style>
</head>
<body>

  <button class="print-btn" onclick="window.print()">Cetak Dokumen</button>

  <div class="kop-surat">
    <h1>DAFTAR NAMA SISWA PADI-PJOK</h1>
    <p>Penilaian Autentik Digital Integratif untuk Pendidikan Jasmani Olahraga dan Kesehatan</p>
  </div>

  <div class="judul-dokumen">
    <h2>DATA SISWA</h2>
    <p>Kelas: <strong><?= htmlspecialchars($kelas) ?></strong></p>
  </div>

  <table class="data-tabel">
    <thead>
      <tr>
        <th>No.</th>
        <th>NIS</th>
        <th>Nama Lengkap Siswa</th>
        <th>Keterangan</th>
      </tr>
    </thead>
    <tbody>
      <?php if(count($siswa_list) > 0): ?>
        <?php $no=1; foreach($siswa_list as $siswa): ?>
        <tr>
          <td class="no"><?= $no++ ?></td>
          <td class="nis"><?= htmlspecialchars($siswa['nis'] ?: '-') ?></td>
          <td><?= htmlspecialchars($siswa['nama']) ?></td>
          <td></td>
        </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr>
          <td colspan="4" style="text-align: center; padding: 20px;">Belum ada data siswa di kelas ini.</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>

  <div class="tanda-tangan">
    <table>
      <tr>
        <td></td>
        <td>
          <p>_____________, <?= date('d F Y') ?></p>
          <p>Guru Mata Pelajaran PJOK,</p>
          <div class="spacer"></div>
          <p>(___________________________)</p>
          <p>NIP. </p>
        </td>
      </tr>
    </table>
  </div>

  <script>
    // Langsung buka dialog print saat laman dimuat
    window.onload = function() {
      window.print();
    }
  </script>
</body>
</html>
