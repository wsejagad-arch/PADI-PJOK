<?php
// rubrik.php — sumber acuan rubrik & ambang nilai PADI-PJOK.
// Dipakai rubrik-platform.php; angka di sini harus sama dengan yang dipakai
// halaman penilaian (kognitif 2×50, rubrik 5 indikator × skala 1–4 = 0–20).

if (!defined('PADI_TUNTAS')) define('PADI_TUNTAS', 70);

if (!function_exists('padi_rubrik_konversi')) {
/** Rubrik 0–20 → skala 0–100. */
function padi_rubrik_konversi($nilai20)
{
    return max(0, min(100, (int)$nilai20 * 5));
}
}

if (!function_exists('padi_rubrik_predikat')) {
/** Predikat mengikuti ambang yang dipakai laporan & rekap. */
function padi_rubrik_predikat($nilai)
{
    if ($nilai === null) return ['Belum ada data', 'abu'];
    if ($nilai >= 85) return ['Sangat Baik', 'green'];
    if ($nilai >= 70) return ['Baik', 'blue'];
    if ($nilai >= 50) return ['Cukup', 'orange'];
    return ['Kurang', 'red'];
}
}

if (!function_exists('padi_rubrik_definisi')) {
function padi_rubrik_definisi()
{
    $deskriptor = [
        1 => 'Belum tampak',
        2 => 'Mulai tampak',
        3 => 'Baik',
        4 => 'Sangat baik',
    ];

    return [
        'kognitif' => [
            'nama' => 'Kognitif',
            'skala' => '0–100',
            'tabel' => 'penilaian_kognitif.nilai_total',
            'halaman' => 'penilaian-kognitif.php',
            'konversi' => 'Langsung (2 butir × 50 poin)',
            'butir' => [
                ['label' => 'Soal 1 — pilihan ganda (jawaban B)', 'bobot' => 50],
                ['label' => 'Soal 2 — pilihan ganda (jawaban B)', 'bobot' => 50],
            ],
            'indikator' => [],
            'deskriptor' => [],
        ],
        'psikomotor' => [
            'nama' => 'Psikomotor',
            'skala' => '1–4 per indikator (total 0–20)',
            'tabel' => 'penilaian_psikomotor.nilai_rubrik / nilai_guru',
            'halaman' => 'penilaian-psikomotor.php',
            'konversi' => '0–20 → ×5 → 0–100',
            'butir' => [],
            'indikator' => [
                'Posisi kaki siap dan seimbang',
                'Lutut menekuk dengan benar',
                'Lengan rapat dan lurus saat kontak bola',
                'Arah bola terkontrol',
                'Gerak lanjutan / follow-through',
            ],
            'deskriptor' => $deskriptor,
        ],
        'afektif' => [
            'nama' => 'Afektif',
            'skala' => '1–4 per indikator (total 0–20)',
            'tabel' => 'penilaian_afektif.nilai_rubrik / nilai_guru',
            'halaman' => 'penilaian-afektif.php',
            'konversi' => '0–20 → ×5 → 0–100',
            'butir' => [],
            'indikator' => [
                'Jujur dalam menilai diri',
                'Disiplin mengikuti instruksi',
                'Percaya diri saat praktik',
                'Kerja sama dengan teman',
                'Tanggung jawab/sportivitas',
            ],
            'deskriptor' => $deskriptor,
        ],
        'diri' => [
            'nama' => 'Penilaian Diri/Rekan',
            'skala' => '1–4 per indikator (total 0–20)',
            'tabel' => 'penilaian_rekan.nilai_rubrik',
            'halaman' => 'penilaian-rekan.php',
            'konversi' => '0–20 → ×5 → 0–100',
            'butir' => [],
            'indikator' => [
                'Posisi kaki siap dan seimbang',
                'Lutut menekuk dengan benar',
                'Lengan rapat dan lurus saat kontak bola',
                'Arah bola terkontrol',
                'Gerak lanjutan / follow-through',
            ],
            'deskriptor' => $deskriptor,
        ],
    ];
}
}
