<?php
// ketentuan.php — Syarat & Ketentuan penggunaan PADI-PJOK
require_once __DIR__ . '/koneksi.php';

$namaSekolah = '';
if (function_exists('padi_sekolah')) {
    $namaSekolah = trim((string)padi_sekolah('nama'));
}
$kontak = trim((string)getenv('APP_SUPPORT_EMAIL'));
if ($kontak === '') $kontak = 'admin@' . (trim((string)getenv('BASE_DOMAIN')) ?: 'padipjok.pintarhub.com');
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Syarat &amp; Ketentuan – PADI-PJOK</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root { --blue: #1A56DB; --text: #111827; --text-3: #6B7280; --bg: #F3F6FB; --white: #fff; --radius: 16px; }
    body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); line-height: 1.65; padding: 24px 16px 48px; }
    .wrap { max-width: 760px; margin: 0 auto; background: var(--white); border: 2px solid #1E293B; border-radius: var(--radius); padding: 26px; }
    h1 { font-size: 20px; margin-bottom: 6px; }
    h2 { font-size: 15px; margin: 20px 0 6px; }
    p, li { font-size: 13.5px; color: #1F2937; }
    ul { margin: 6px 0 0 18px; }
    .meta { font-size: 12px; color: var(--text-3); margin-bottom: 14px; }
    a { color: var(--blue); }
    .kembali { display: inline-block; margin-top: 22px; font-size: 13px; font-weight: 700; }
  </style>
  <link rel="stylesheet" href="aset/app.css"/>
</head>
<body>
  <div class="wrap">
    <h1>Syarat &amp; Ketentuan PADI-PJOK</h1>
    <p class="meta">Layanan penilaian PJOK<?= $namaSekolah !== '' ? ' di ' . htmlspecialchars($namaSekolah, ENT_QUOTES, 'UTF-8') : '' ?>. Terakhir diperbarui: <?= date('d/m/Y') ?>.</p>

    <h2>1. Penerimaan</h2>
    <p>Dengan masuk dan menggunakan PADI-PJOK, Anda menyetujui ketentuan ini. Jika tidak setuju, mohon tidak menggunakan layanan.</p>

    <h2>2. Akun &amp; keamanan</h2>
    <ul>
      <li>Akun guru dibuat oleh admin sekolah atau melalui pendaftaran akun Belajar.id yang terdaftar pada domain yang diizinkan.</li>
      <li>Jaga kerahasiaan password. Aktivitas yang terjadi pada akun Anda menjadi tanggung jawab pemegang akun.</li>
      <li>Dilarang membagikan token sesi kelas kepada pihak di luar kelas yang bersangkutan.</li>
    </ul>

    <h2>3. Peran admin sekolah</h2>
    <ul>
      <li>Guru pertama pada suatu sekolah menjadi admin sekolah; admin dapat mengangkat/menurunkan admin lain di sekolahnya.</li>
      <li>Admin sekolah berwenang menambah, mengubah, menonaktifkan, mereset password, dan menghapus akun guru di sekolahnya.</li>
      <li>Super-admin platform berwenang mengelola daftar sekolah, seluruh akun, dan memantau status server.</li>
    </ul>

    <h2>4. Penggunaan yang dilarang</h2>
    <ul>
      <li>Mengakses data sekolah lain, memalsukan hasil penilaian, atau mengunggah konten yang melanggar hukum.</li>
      <li>Mengunggah video siswa tanpa izin sekolah/ orang tua sesuai aturan yang berlaku.</li>
    </ul>

    <h2>5. Konten &amp; kepemilikan data</h2>
    <p>Data siswa dan hasil penilaian adalah milik sekolah penyelenggara. Platform hanya memproses data untuk kepentingan pembelajaran dan pelaporan.</p>

    <h2>6. Ketersediaan layanan</h2>
    <p>Layanan disediakan sebagaimana adanya. Pemeliharaan terjadwal dapat menyebabkan gangguan singkat; perubahan akan diinformasikan melalui sekolah.</p>

    <h2>7. Perubahan ketentuan</h2>
    <p>Ketentuan dapat diperbarui sewaktu-waktu. Versi terbaru selalu tersedia pada halaman ini.</p>

    <h2>8. Kontak</h2>
    <p>Pertanyaan: <a href="mailto:<?= htmlspecialchars($kontak, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($kontak, ENT_QUOTES, 'UTF-8') ?></a></p>

    <a class="kembali" href="privasi.php">Kebijakan Privasi</a>
    <a class="kembali" href="index.php" style="margin-left:14px;">&larr; Kembali ke PADI-PJOK</a>
  </div>
</body>
</html>
