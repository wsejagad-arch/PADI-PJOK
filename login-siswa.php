<?php
// login-siswa.php — Login siswa: Nomor Induk + password (token dari guru)
require_once 'koneksi.php';
require_once 'auth.php';

// Database belum siap → pesan jelas, bukan halaman putih.
if (empty($conn)) {
    require_once 'pesan-db.php';
    padi_halaman_db_mati($padi_db_error ?? 'Database tidak dapat dihubungi.');
}

padi_berbagi_sesi();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$err = '';
$show_token = false;
$token_sesi = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- Login: nomor induk + password ---
    if ($action === 'login') {
        $hasil = loginSiswa($conn, $_POST['dokumen'] ?? '', $_POST['password'] ?? '');
        if ($hasil['success']) {
            $_SESSION['token_sesi'] = $hasil['token'];
            padi_kembali('login-siswa.php?token=1');
        }
        $err = $hasil['message'];
    }

    // --- Tampilkan token (verifikasi password ulang) ---
    if ($action === 'lihat_token') {
        $res = verifikasiPasswordSiswa($conn, (int)($_SESSION['master_id'] ?? 0), $_POST['password'] ?? '');
        if ($res['success']) {
            $show_token = true;
            $token_sesi = $res['token'];
        } else {
            $err = $res['message'];
        }
    }
}

// Popup token otomatis setelah login berhasil
if (isset($_GET['token']) && !empty($_SESSION['siswa_id'])) {
    $show_token = true;
    $token_sesi = $_SESSION['token_sesi'] ?? '';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Login Siswa – PADI-PJOK</title>
  <meta name="description" content="Login siswa PADI-PJOK menggunakan Nomor Induk dan password (token dari guru)."/>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    :root{--blue:#1A56DB;--blue-dark:#1240A8;--blue-light:#EFF4FF;--blue-mid:#DBEAFE;--green:#16A34A;--green-light:#DCFCE7;--text:#111827;--text-2:#374151;--text-3:#6B7280;--text-4:#9CA3AF;--border:#E5E7EB;--bg:#F3F6FB;--white:#FFFFFF;--radius:14px;--radius-sm:10px;--shadow:0 2px 8px rgba(0,0,0,.07);--shadow-md:0 4px 18px rgba(0,0,0,.10)}
    body{font-family:'Inter',sans-serif;background:var(--bg);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px 16px}
    body::before{content:'';position:fixed;width:400px;height:400px;border-radius:50%;filter:blur(80px);background:radial-gradient(circle,rgba(26,86,219,.15) 0%,transparent 70%);top:-100px;left:-100px;pointer-events:none;z-index:0}
    body::after{content:'';position:fixed;width:300px;height:300px;border-radius:50%;filter:blur(80px);background:radial-gradient(circle,rgba(34,197,94,.10) 0%,transparent 70%);bottom:-60px;right:-60px;pointer-events:none;z-index:0}

    .card{position:relative;z-index:1;width:100%;max-width:420px;background:var(--white);border-radius:28px;box-shadow:0 10px 40px rgba(0,0,0,.14);overflow:hidden;animation:slideUp .5s cubic-bezier(.22,1,.36,1) both}
    @keyframes slideUp{from{opacity:0;transform:translateY(32px)}to{opacity:1;transform:translateY(0)}}

    .header{padding:28px 24px 0;text-align:center}
    .logo-row{display:flex;align-items:center;justify-content:center;gap:10px;margin-bottom:4px}
    .logo-row svg{width:36px;height:36px}
    .logo-text{font-size:26px;font-weight:800;color:var(--blue);letter-spacing:-.5px}
    .logo-sub{font-size:12px;color:var(--text-3);font-weight:500}

    .hero{display:flex;align-items:flex-end;gap:0;padding:16px 24px 0;min-height:120px;background:linear-gradient(135deg,#F0F7FF 0%,#DBEAFE 100%);position:relative;overflow:hidden;margin-top:16px}
    .hero::before{content:'';position:absolute;inset:0;background:radial-gradient(ellipse at 80% 30%,rgba(26,86,219,.07),transparent 60%)}
    .hero-text{flex:1;padding-bottom:18px;z-index:1}
    .hero-text h1{font-size:21px;font-weight:800;color:var(--text);margin-bottom:6px}
    .hero-text p{font-size:12px;color:var(--text-3);line-height:1.6;max-width:210px}

    .body{padding:20px 24px 24px}
    .form-group{display:flex;flex-direction:column;gap:6px;margin-bottom:16px}
    .form-label{font-size:13px;font-weight:600;color:var(--text)}
    .input-wrap{position:relative;display:flex;align-items:center}
    .input-icon{position:absolute;left:14px;color:var(--text-4);display:flex;align-items:center}
    .input-icon svg{width:18px;height:18px}
    .form-input{width:100%;padding:13px 44px 13px 42px;border:1.5px solid var(--border);border-radius:var(--radius-sm);font-family:inherit;font-size:14px;color:var(--text);background:var(--white);outline:none;transition:border-color .2s,box-shadow .2s}
    .form-input::placeholder{color:var(--text-4)}
    .form-input:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(26,86,219,.12)}
    .toggle-pw{position:absolute;right:10px;background:none;border:none;cursor:pointer;color:var(--text-4);display:flex;align-items:center;padding:6px;border-radius:8px;min-width:36px;min-height:36px;justify-content:center}
    .toggle-pw:hover{color:var(--blue);background:var(--blue-light)}
    .toggle-pw svg{width:18px;height:18px}

    .alert{display:flex;gap:10px;align-items:flex-start;background:#FEF2F2;border:1.5px solid #FECACA;color:#991B1B;border-radius:var(--radius-sm);padding:12px 14px;font-size:12.5px;line-height:1.5;margin-bottom:16px}
    .alert svg{width:18px;height:18px;flex-shrink:0;margin-top:1px}

    .btn-masuk{width:100%;display:flex;align-items:center;justify-content:center;gap:10px;padding:15px;background:var(--blue);color:var(--white);border:none;border-radius:var(--radius-sm);font-family:inherit;font-size:15px;font-weight:700;cursor:pointer;box-shadow:0 6px 20px rgba(26,86,219,.35);transition:all .25s cubic-bezier(.22,1,.36,1);letter-spacing:.1px;min-height:48px}
    .btn-masuk svg{width:20px;height:20px}
    .btn-masuk:hover{background:var(--blue-dark);transform:translateY(-2px)}
    .btn-masuk:disabled{opacity:.7;cursor:not-allowed;transform:none}

    .info-note{display:flex;align-items:center;gap:10px;background:#EFF4FF;border:1px solid var(--blue-mid);border-radius:var(--radius-sm);padding:12px 14px;margin-top:16px}
    .info-note-icon{width:30px;height:30px;border-radius:50%;background:var(--blue);display:flex;align-items:center;justify-content:center;flex-shrink:0}
    .info-note-icon svg{width:16px;height:16px;color:white}
    .info-note p{font-size:11.5px;color:var(--text-3);line-height:1.5}

    .back-link{display:block;text-align:center;margin-top:18px;font-size:13px;font-weight:600;color:var(--blue);text-decoration:none}
    .back-link:hover{text-decoration:underline}

    /* ── Popup token ── */
    .overlay{position:fixed;inset:0;background:rgba(17,24,39,.55);backdrop-filter:blur(3px);display:none;align-items:center;justify-content:center;padding:16px;z-index:200;overflow-y:auto}
    .overlay.show{display:flex}
    .modal{width:100%;max-width:380px;background:var(--white);border-radius:22px;padding:26px 22px;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.3);animation:pop .35s cubic-bezier(.22,1,.36,1) both;margin:auto}
    @keyframes pop{from{opacity:0;transform:scale(.92) translateY(10px)}to{opacity:1;transform:scale(1) translateY(0)}}
    .modal-icon{width:62px;height:62px;margin:0 auto 14px;border-radius:50%;background:var(--green-light);display:flex;align-items:center;justify-content:center}
    .modal-icon svg{width:32px;height:32px;color:var(--green)}
    .modal h2{font-size:19px;font-weight:800;color:var(--text);margin-bottom:6px}
    .modal p{font-size:12.5px;color:var(--text-3);line-height:1.6;margin-bottom:16px}
    .token-box{background:var(--blue-light);border:2px dashed var(--blue-mid);border-radius:var(--radius-sm);padding:16px;margin-bottom:8px}
    .token-label{font-size:11px;font-weight:700;color:var(--blue);text-transform:uppercase;letter-spacing:.8px;margin-bottom:6px}
    .token-code{font-size:26px;font-weight:800;letter-spacing:3px;color:var(--blue);word-break:break-all}
    .token-hint{font-size:11.5px;color:var(--text-4);line-height:1.5;margin-bottom:18px}
    .modal-actions{display:flex;flex-direction:column;gap:10px}
    .btn-modal{width:100%;padding:14px;border-radius:var(--radius-sm);font-family:inherit;font-size:14.5px;font-weight:700;cursor:pointer;border:none;min-height:46px}
    .btn-modal.primary{background:var(--blue);color:var(--white);box-shadow:0 6px 18px rgba(26,86,219,.32)}
    .btn-modal.primary:hover{background:var(--blue-dark)}
    .btn-modal.ghost{background:var(--blue-light);color:var(--blue)}
    .btn-modal.ghost:hover{background:var(--blue-mid)}
  </style>
</head>
<body>

<div class="card">
  <div class="header">
    <div class="logo-row">
      <svg viewBox="0 0 36 36" fill="none" aria-hidden="true">
        <circle cx="23" cy="6" r="4" fill="#1A56DB"/>
        <path d="M8 30L16 18 14 11 21 6" stroke="#1A56DB" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
        <path d="M14 11L22 15L30 11" stroke="#1A56DB" stroke-width="2.5" stroke-linecap="round"/>
        <path d="M22 15L19 24L24 30" stroke="#1A56DB" stroke-width="2.5" stroke-linecap="round"/>
        <path d="M3 22Q12 17 21 21" stroke="#22C55E" stroke-width="2.2" stroke-linecap="round"/>
      </svg>
      <span class="logo-text">PADI-PJOK</span>
    </div>
    <p class="logo-sub">Penilaian Autentik Digital Integratif untuk PJOK</p>
  </div>

  <div class="hero">
    <div class="hero-text">
      <h1>Login Siswa</h1>
      <p>Masuk dengan Nomor Induk dan password (token materi dari guru).</p>
    </div>
  </div>

  <div class="body">
    <?php if ($err !== ''): ?>
    <div class="alert" role="alert">
      <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/><path d="M12 8v4M12 16h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
      <span><?= htmlspecialchars($err) ?></span>
    </div>
    <?php endif; ?>

    <form method="post" id="form-login" novalidate>
      <input type="hidden" name="action" value="login"/>

      <div class="form-group">
        <label class="form-label" for="dokumen">Nomor Induk</label>
        <div class="input-wrap">
          <span class="input-icon"><svg viewBox="0 0 24 24" fill="none"><rect x="3" y="5" width="18" height="14" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M7 9h4M7 13h7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><circle cx="17" cy="10" r="2" stroke="currentColor" stroke-width="1.8"/></svg></span>
          <input class="form-input" type="text" id="dokumen" name="dokumen"
                 placeholder="Contoh: 12345" autocomplete="username"
                 inputmode="numeric" required/>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="password">Password</label>
        <div class="input-wrap">
          <span class="input-icon"><svg viewBox="0 0 24 24" fill="none"><rect x="5" y="11" width="14" height="10" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M8 11V7a4 4 0 018 0v4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></span>
          <input class="form-input" type="password" id="password" name="password"
                 placeholder="Masukkan token dari guru" autocomplete="current-password" required/>
          <button type="button" class="toggle-pw" aria-label="Tampilkan password" onclick="togglePw()">
            <svg viewBox="0 0 24 24" fill="none"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.8"/></svg>
          </button>
        </div>
      </div>

      <button type="submit" class="btn-masuk" id="btn-masuk">
        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><polyline points="10 17 15 12 10 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><line x1="15" y1="12" x2="3" y2="12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        Masuk
      </button>
    </form>

    <div class="info-note">
      <div class="info-note-icon">
        <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/><path d="M12 16v-4M12 8h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
      </div>
      <p>Password pertama kali = token materi dari guru. Bila gagal, minta token terbaru ke guru PJOK.</p>
    </div>

    <a class="back-link" href="index.php">&larr; Kembali ke halaman utama</a>
  </div>
</div>

<!-- ── Popup token ── -->
<div class="overlay <?= $show_token ? 'show' : '' ?>" id="overlay-token" role="dialog" aria-modal="true" aria-labelledby="token-title">
  <div class="modal">
    <div class="modal-icon">
      <svg viewBox="0 0 24 24" fill="none"><path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </div>
    <h2 id="token-title">Login Berhasil!</h2>
    <p>Simpan token sesi materi PJOK kamu. Token ini juga menjadi password keamanan akunmu.</p>

    <div class="token-box">
      <p class="token-label">Token Materi</p>
      <p class="token-code" id="token-code"><?= htmlspecialchars($token_sesi !== '' ? $token_sesi : '—') ?></p>
    </div>
    <p class="token-hint">Catat atau salin token ini. Jangan bagikan kepada siswa lain.</p>

    <div class="modal-actions">
      <button type="button" class="btn-modal primary" id="btn-masuk-siswa" <?= $token_sesi === '' ? 'disabled' : '' ?>>
        Masuk ke Halaman Siswa
      </button>
      <button type="button" class="btn-modal ghost" onclick="copyToken()">Salin Token</button>
    </div>
  </div>
</div>

<script>
  function togglePw() {
    const el = document.getElementById('password');
    el.type = el.type === 'password' ? 'text' : 'password';
    el.focus();
  }

  function copyToken() {
    const kode = document.getElementById('token-code').textContent.trim();
    if (!kode || kode === '—') return;
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(kode).then(
        () => alert('Token disalin: ' + kode),
        () => alert('Token: ' + kode)
      );
    } else {
      alert('Token: ' + kode);
    }
  }

  var btnSiswa = document.getElementById('btn-masuk-siswa');
  if (btnSiswa) {
    btnSiswa.addEventListener('click', function () {
      window.location.href = 'dashboard-siswa.php';
    });
  }

  document.getElementById('form-login').addEventListener('submit', function (e) {
    var dok = document.getElementById('dokumen').value.trim();
    var pw  = document.getElementById('password').value.trim();
    if (!dok || !pw) {
      e.preventDefault();
      alert('Nomor induk dan password wajib diisi.');
      return;
    }
    var btn = document.getElementById('btn-masuk');
    btn.disabled = true;
    btn.textContent = 'Memverifikasi...';
  });
</script>
</body>
</html>
