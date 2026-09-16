<?php
// index.php — Portal PADI-PJOK: satu pintu untuk guru & siswa.
// Titik masuk aman (sesi dimulai lewat helper, bukan session_start() mentah).
require_once 'auth-boot.php';

// Bila database belum siap, jangan biarkan halaman putih: tampilkan panduan.
if (empty($conn)) {
    require_once 'pesan-db.php';
    padi_halaman_db_mati($padi_db_error ?? 'Database tidak dapat dihubungi.');
}

// Pengunjung yang masih punya sesi aktif diarahkan ke halaman utamanya.
// (Buka index.php?menu=1 bila ingin tetap di portal meski sudah login.)
$minta_menu = isset($_GET['menu']) || isset($_GET['pilih']);
if ($minta_menu) {
    // sengaja tetap menampilkan portal
} elseif (!empty($_SESSION['guru_id'])) {
    padi_kembali('dashboard-guru.php');
} elseif (!empty($_SESSION['siswa_id'])) {
    padi_kembali('dashboard-siswa.php');
} elseif (!empty($_SESSION['master_id'])) {
    padi_kembali('input-token.php');
}

$err = '';
$peran_terpilih = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $peran_terpilih = strtolower(trim((string)($_POST['peran'] ?? '')));

    // Pilihan GURU: jangan verifikasi ke tabel siswa — langsung ke login guru.
    if ($peran_terpilih === 'guru') {
        padi_kembali('login-guru.php');
    }

    // Pilihan SISWA: verifikasi NIS + password, lalu minta token sesi.
    $hasil = loginSiswa($conn, $_POST['dokumen'] ?? '', $_POST['password'] ?? '');
    if ($hasil['success']) {
        padi_kembali('input-token.php');
    }
    $err = $hasil['message'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>PADI-PJOK – Login Siswa</title>
  <meta name="description" content="Platform penilaian autentik digital integratif untuk mata pelajaran PJOK." />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --blue-primary: #1A56DB;
      --blue-dark:    #1240A8;
      --blue-light:   #EFF4FF;
      --blue-mid:     #DBEAFE;
      --text-dark:    #111827;
      --text-gray:    #6B7280;
      --text-light:   #9CA3AF;
      --border:       #E5E7EB;
      --white:        #FFFFFF;
      --bg:           #F3F6FB;
      --green:        #22C55E;
      --shadow-sm:    0 1px 3px rgba(0,0,0,.08), 0 1px 2px rgba(0,0,0,.06);
      --shadow-md:    0 4px 16px rgba(0,0,0,.10);
      --shadow-lg:    0 10px 40px rgba(0,0,0,.14);
      --radius:       16px;
      --radius-sm:    10px;
    }

    body {
      font-family: 'Inter', sans-serif;
      background: var(--bg);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px 16px;
    }

    /* ── Floating decorative blobs ── */
    body::before, body::after {
      content: '';
      position: fixed;
      border-radius: 50%;
      filter: blur(80px);
      z-index: 0;
      pointer-events: none;
    }
    body::before {
      width: 400px; height: 400px;
      background: radial-gradient(circle, rgba(26,86,219,.18) 0%, transparent 70%);
      top: -100px; left: -100px;
    }
    body::after {
      width: 350px; height: 350px;
      background: radial-gradient(circle, rgba(34,197,94,.12) 0%, transparent 70%);
      bottom: -80px; right: -80px;
    }

    /* ── Card ── */
    .card {
      position: relative;
      z-index: 1;
      width: 100%;
      max-width: 420px;
      background: var(--white);
      border-radius: 28px;
      box-shadow: var(--shadow-lg);
      overflow: hidden;
      animation: slideUp .5s cubic-bezier(.22,1,.36,1) both;
    }

    @keyframes slideUp {
      from { opacity: 0; transform: translateY(32px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    /* ── Header ── */
    .header {
      padding: 28px 28px 0;
      text-align: center;
    }

    .logo-row {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      margin-bottom: 4px;
    }

    .logo-icon {
      width: 44px;
      height: 44px;
      flex-shrink: 0;
    }

    .logo-text {
      font-size: 28px;
      font-weight: 800;
      color: var(--blue-primary);
      letter-spacing: -.5px;
    }

    .logo-subtitle {
      font-size: 13px;
      color: var(--text-gray);
      font-weight: 500;
      margin-bottom: 0;
    }

    /* ── Hero illustration ── */
    .hero-wrap {
      margin: 16px 0 0;
      height: 200px;
      overflow: hidden;
      position: relative;
      background: linear-gradient(180deg, #EFF6FF 0%, #DBEAFE 100%);
    }

    .hero-wrap img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      object-position: center top;
      display: block;
    }

    /* ── Body ── */
    .body {
      padding: 20px 24px 24px;
    }

    /* ── Form ── */
    .form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 16px; }

    .form-label {
      font-size: 13px;
      font-weight: 600;
      color: var(--text-dark);
    }

    .input-wrap {
      position: relative;
      display: flex;
      align-items: center;
    }

    .input-icon {
      position: absolute;
      left: 14px;
      color: var(--text-light);
      display: flex;
      align-items: center;
    }

    .input-icon svg { width: 18px; height: 18px; }

    .form-input {
      width: 100%;
      padding: 12px 14px 12px 42px;
      border: 1.5px solid var(--border);
      border-radius: var(--radius-sm);
      font-family: inherit;
      font-size: 14px;
      color: var(--text-dark);
      background: var(--white);
      outline: none;
      transition: border-color .2s, box-shadow .2s;
    }

    .form-input::placeholder { color: var(--text-light); }

    .form-input:focus {
      border-color: var(--blue-primary);
      box-shadow: 0 0 0 3px rgba(26,86,219,.12);
    }

    .toggle-pw {
      position: absolute;
      right: 14px;
      background: none;
      border: none;
      cursor: pointer;
      color: var(--text-light);
      display: flex;
      align-items: center;
      padding: 0;
      transition: color .2s;
    }

    .toggle-pw:hover { color: var(--blue-primary); }
    .toggle-pw svg { width: 18px; height: 18px; }

    /* ── Submit button ── */
    .btn-submit {
      width: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      padding: 14px;
      background: var(--blue-primary);
      color: var(--white);
      border: none;
      border-radius: var(--radius-sm);
      font-family: inherit;
      font-size: 15px;
      font-weight: 700;
      cursor: pointer;
      box-shadow: 0 6px 20px rgba(26,86,219,.35);
      transition: all .25s cubic-bezier(.22,1,.36,1);
      margin-top: 4px;
      letter-spacing: .1px;
    }

    .btn-submit svg { width: 20px; height: 20px; }

    .btn-submit:hover {
      background: var(--blue-dark);
      box-shadow: 0 8px 28px rgba(26,86,219,.45);
      transform: translateY(-2px);
    }

    .btn-submit:active { transform: translateY(0); }

    .alert {
      display:flex; gap:10px; align-items:flex-start;
      background:#FEF2F2; border:1.5px solid #FECACA; color:#991B1B;
      border-radius:var(--radius-sm); padding:12px 14px;
      font-size:12.5px; line-height:1.5; margin-bottom:16px;
    }
    .alert svg { width:18px; height:18px; flex-shrink:0; margin-top:1px; }

    /* ── Pilih peran (Guru / Siswa) ── */
    .role-picker { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 18px; }
    .role-card {
      display: flex; align-items: center; gap: 10px;
      width: 100%; min-height: 64px; padding: 12px 12px;
      background: var(--white); color: var(--text-dark);
      border: 1.5px solid var(--border, #E5E7EB); border-radius: var(--radius-sm);
      font-family: inherit; text-align: left; cursor: pointer;
      transition: border-color .2s, box-shadow .2s, background .2s, transform .2s;
    }
    .role-card:hover { border-color: var(--blue-primary); transform: translateY(-1px); }
    .role-card[aria-selected="true"] {
      background: var(--blue-light); border-color: var(--blue-primary);
      box-shadow: 0 4px 14px rgba(26,86,219,.16);
    }
    .role-icon {
      display: flex; align-items: center; justify-content: center;
      width: 36px; height: 36px; flex-shrink: 0; border-radius: 10px;
      background: var(--blue-mid); color: var(--blue-primary);
    }
    .role-icon svg { width: 20px; height: 20px; }
    .role-text { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
    .role-text strong { font-size: 14px; font-weight: 700; }
    .role-text small { font-size: 11px; color: var(--text-gray); line-height: 1.35; }
    .role-arrow { margin-left: auto; color: var(--text-light); font-size: 16px; }

    .hint { font-size: 12px; color: var(--text-gray); line-height: 1.55; margin: 0 0 14px; }
    .catatan { font-size: 11.5px; color: var(--text-gray); line-height: 1.55; margin-top: 14px; text-align: center; }

    /* ── Responsive ── */
    @media (max-width: 520px) {
      .role-picker { grid-template-columns: 1fr; }
      .role-text small { font-size: 11.5px; }
    }
    @media (max-width: 440px) {
      .body { padding: 16px 18px 20px; }
      .hero-wrap { height: 170px; }
      .btn-submit { min-height: 48px; }
    }
  </style>
</head>
<body>

<div class="card" role="main">
  <!-- ── Header ── -->
  <div class="header">
    <div class="logo-row">
      <!-- Running person logo -->
      <svg class="logo-icon" viewBox="0 0 44 44" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <circle cx="28" cy="7" r="4.5" fill="#1A56DB"/>
        <path d="M10 38 L20 22 L17 14 L26 8" stroke="#1A56DB" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
        <path d="M17 14 L28 18 L36 14" stroke="#1A56DB" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
        <path d="M28 18 L24 30 L30 38" stroke="#1A56DB" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
        <!-- Swoosh/checkmark -->
        <path d="M4 28 Q14 22 24 26" stroke="#22C55E" stroke-width="2.5" stroke-linecap="round" fill="none"/>
      </svg>
      <span class="logo-text">PADI-PJOK</span>
    </div>
    <p class="logo-subtitle">Penilaian Autentik Digital Integratif untuk PJOK</p>
  </div>

  <!-- ── Hero ── -->
  <div class="hero-wrap">
    <img src="padi_pjok_hero_1781370290670.png"
         alt="Ilustrasi guru PJOK bersama siswa bermain olahraga" />
  </div>

  <!-- ── Body ── -->
  <div class="body">

    <?php if ($err !== ''): ?>
    <div class="alert" role="alert">
      <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/><path d="M12 8v4M12 16h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
      <span><?= htmlspecialchars($err) ?></span>
    </div>
    <?php endif; ?>

    <!-- ── Pilih peran: Guru atau Siswa ── -->
    <div class="role-picker" id="role-picker" role="tablist" aria-label="Pilih peran">

      <button type="button" class="role-card" id="kartu-guru" role="tab"
              aria-selected="false" data-peran="guru">
        <span class="role-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none"><path d="M3 7l9-4 9 4-9 4-9-4z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M7 9.5V15c0 1.7 2.2 3 5 3s5-1.3 5-3V9.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
        </span>
        <span class="role-text">
          <strong>Guru</strong>
          <small>Kelola sesi, token &amp; penilaian</small>
        </span>
        <span class="role-arrow" aria-hidden="true">&rarr;</span>
      </button>

      <button type="button" class="role-card" id="kartu-siswa" role="tab"
              aria-selected="true" data-peran="siswa">
        <span class="role-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="8" r="3.2" stroke="currentColor" stroke-width="1.8"/><path d="M5 20c0-3.6 3.1-6 7-6s7 2.4 7 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
        </span>
        <span class="role-text">
          <strong>Siswa</strong>
          <small>Masuk kelas dengan token sesi</small>
        </span>
        <span class="role-arrow" aria-hidden="true">&rarr;</span>
      </button>
    </div>

    <!-- ── Form Login ── -->
    <form method="post" id="form-login" novalidate>
      <input type="hidden" name="peran" id="peran" value="siswa"/>

      <div class="form-group" id="grup-dokumen">
        <label class="form-label" for="dokumen" id="label-identitas">Nomor Induk / NIS</label>
        <div class="input-wrap">
          <span class="input-icon"><svg viewBox="0 0 24 24" fill="none"><rect x="3" y="5" width="18" height="14" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M7 9h4M7 13h7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><circle cx="17" cy="10" r="2" stroke="currentColor" stroke-width="1.8"/></svg></span>
          <input class="form-input" type="text" id="dokumen" name="dokumen"
                 placeholder="Contoh: 12345" autocomplete="username"
                 inputmode="numeric" required/>
        </div>
      </div>

      <div class="form-group" id="grup-password">
        <label class="form-label" for="password">Password</label>
        <div class="input-wrap">
          <span class="input-icon"><svg viewBox="0 0 24 24" fill="none"><rect x="5" y="11" width="14" height="10" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M8 11V7a4 4 0 018 0v4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></span>
          <input class="form-input" type="password" id="password" name="password"
                 placeholder="Masukkan password" autocomplete="current-password"/>
          <button type="button" class="toggle-pw" aria-label="Tampilkan password" onclick="togglePw()">
            <svg viewBox="0 0 24 24" fill="none"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.8"/></svg>
          </button>
        </div>
      </div>

      <p class="hint" id="hint-guru" hidden>Halaman login guru dibuka terpisah (username &amp; password).</p>

      <button type="submit" class="btn-submit" id="btn-masuk">
        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><polyline points="10 17 15 12 10 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><line x1="15" y1="12" x2="3" y2="12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        <span id="teks-tombol">Login Siswa</span>
      </button>
    </form>

    <p class="catatan">Password siswa pertama kali = Nomor Induk. Setelah masuk, masukkan token sesi dari guru.</p>

  </div><!-- /body -->

</div>

<script>
  function togglePw() {
    var el = document.getElementById('password');
    el.type = el.type === 'password' ? 'text' : 'password';
    el.focus();
  }

  // Pengalih peran: kartu Guru membuka halaman login guru,
  // kartu Siswa menampilkan form NIS + password di portal ini.
  (function () {
    var peranInput = document.getElementById('peran');
    var kartuGuru  = document.getElementById('kartu-guru');
    var kartuSiswa = document.getElementById('kartu-siswa');
    var grupDok    = document.getElementById('grup-dokumen');
    var grupPass   = document.getElementById('grup-password');
    var hintGuru   = document.getElementById('hint-guru');
    var labelId    = document.getElementById('label-identitas');
    var tombol     = document.getElementById('teks-tombol');
    var form       = document.getElementById('form-login');

    function pilih(peran) {
      var guru = peran === 'guru';
      peranInput.value = guru ? 'guru' : 'siswa';
      kartuGuru.setAttribute('aria-selected', guru ? 'true' : 'false');
      kartuSiswa.setAttribute('aria-selected', guru ? 'false' : 'true');
      grupDok.hidden = guru;
      grupPass.hidden = guru;
      hintGuru.hidden = !guru;
      tombol.textContent = guru ? 'Lanjut ke Login Guru' : 'Login Siswa';
      if (!guru) {
        labelId.textContent = 'Nomor Induk / NIS';
        document.getElementById('dokumen').placeholder = 'Contoh: 12345';
      }
    }

    kartuGuru.addEventListener('click', function () { pilih('guru'); });
    kartuSiswa.addEventListener('click', function () { pilih('siswa'); });

    form.addEventListener('submit', function (e) {
      if (peranInput.value !== 'guru') {
        var d = document.getElementById('dokumen').value.trim();
        var p = document.getElementById('password').value.trim();
        if (!d || !p) {
          e.preventDefault();
          alert('Nomor induk dan password wajib diisi.');
          return;
        }
        var b = document.getElementById('btn-masuk');
        b.disabled = true;
        tombol.textContent = 'Memverifikasi...';
      }
    });

    pilih(<?= $peran_terpilih === 'guru' ? "'guru'" : "'siswa'" ?>);
  })();
</script>

</body>
</html>
