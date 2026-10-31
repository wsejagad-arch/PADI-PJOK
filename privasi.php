<?php
// privasi.php — Kebijakan Privasi PADI-PJOK (dipakai juga untuk OAuth consent Google)
require_once __DIR__ . '/koneksi.php';

$namaSekolah = '';
if (function_exists('padi_sekolah')) {
    $namaSekolah = trim((string)padi_sekolah('nama'));
}
$kontak = trim((string)getenv('APP_SUPPORT_EMAIL'));
if ($kontak === '') $kontak = trim((string)getenv('SEKOLAH_EMAIL'));
if ($kontak === '') $kontak = 'admin@' . (trim((string)getenv('BASE_DOMAIN')) ?: 'padipjok.pintarhub.com');
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Kebijakan Privasi – PADI-PJOK</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root { --blue: #1A56DB; --text: #111827; --text-3: #6B7280; --border: #E5E7EB; --bg: #F3F6FB; --white: #fff; --radius: 16px; }
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
    <h1>Kebijakan Privasi PADI-PJOK</h1>
    <p class="meta">Berlaku untuk layanan penilaian PJOK<?= $namaSekolah !== '' ? ' di ' . htmlspecialchars($namaSekolah, ENT_QUOTES, 'UTF-8') : '' ?>. Terakhir diperbarui: <?= date('d/m/Y') ?>.</p>

    <h2>1. Data yang kami proses</h2>
    <ul>
      <li><strong>Akun guru:</strong> nama, NIP, username, alamat email (termasuk email Belajar.id bila memakai tombol Masuk dengan Belajar.id), peran, dan waktu login terakhir.</li>
      <li><strong>Data siswa:</strong> nama, nomor induk (NIS), kelas, serta hasil penilaian kognitif, afektif, psikomotor, dan penilaian diri/rekan.</li>
      <li><strong>Konten kelas:</strong> sesi/materi, token sesi, jadwal pelajaran, video praktik bila guru mengaktifkan unggahan.</li>
      <li><strong>Data teknis:</strong> waktu akses dan alamat IP untuk keamanan (pembatasan percobaan login).</li>
    </ul>

    <h2>2. Tujuan penggunaan</h2>
    <ul>
      <li>Menjalankan penilaian autentik PJOK dan menyusun laporan/rekap nilai.</li>
      <li>Autentikasi guru, termasuk verifikasi identitas melalui akun Belajar.id (Google).</li>
      <li>Keamanan layanan: mencegah penyalahgunaan token dan percobaan login berulang.</li>
    </ul>

    <h2>3. Login dengan Belajar.id</h2>
    <p>Bila Anda memilih <em>Masuk dengan Belajar.id</em>, kami hanya meminta scope <code>openid email profile</code>. Yang kami simpan adalah email, nama tampilan, dan ID akun Google Anda. Kami <strong>tidak</strong> mengakses Gmail, Drive, atau data lain di akun Google Anda, dan tidak menyimpan password Google.</p>

    <h2>4. Penyimpanan &amp; keamanan</h2>
    <ul>
      <li>Data disimpan pada server sekolah/penyelenggara dengan akses terbatas dan koneksi HTTPS.</li>
      <li>Password guru disimpan sebagai hash (bcrypt), bukan teks biasa.</li>
      <li>Setiap sekolah (tenant) hanya dapat mengakses data miliknya sendiri.</li>
    </ul>

    <h2>5. Berbagi data</h2>
    <p>Kami tidak menjual atau membagikan data ke pihak ketiga. Data hanya dapat diakses oleh guru/admin sekolah terkait dan pengelola platform untuk keperluan teknis.</p>

    <h2>6. Hak pengguna</h2>
    <ul>
      <li>Guru dapat memperbaiki nama/NIP dan mengganti password sendiri di menu Profil.</li>
      <li>Admin sekolah dapat memperbaiki, menonaktifkan, mereset password, atau menghapus akun guru di sekolahnya.</li>
      <li>Permintaan penghapusan data siswa diajukan ke guru PJOK/admin sekolah.</li>
    </ul>

    <h2>7. Retensi</h2>
    <p>Data disimpan selama tahun pelajaran berjalan dan periode pelaporan yang diwajibkan sekolah. Setelah tidak diperlukan, data dapat dihapus oleh admin sekolah.</p>

    <h2>8. Kontak</h2>
    <p>Pertanyaan tentang privasi: <a href="mailto:<?= htmlspecialchars($kontak, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($kontak, ENT_QUOTES, 'UTF-8') ?></a></p>

    <a class="kembali" href="index.php">&larr; Kembali ke PADI-PJOK</a>
  </div>
</body>
</html>
