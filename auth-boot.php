<?php
// auth-boot.php — Titik masuk yang aman: memuat koneksi + auth + memulai sesi berbagi.
// Tujuan: JANGAN pernah memanggil session_start() langsung di halaman,
// karena bila ada teks/JSON yang keluar sebelum session_start(), pengguna
// mendapat "halaman putih" atau respons 500 tanpa petunjuk apa pun.
require_once __DIR__ . '/koneksi.php';
require_once __DIR__ . '/auth.php';

padi_berbagi_sesi();
padi_mulai_sesi();
