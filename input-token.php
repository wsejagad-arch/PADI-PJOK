<?php
session_start();
require_once 'koneksi.php';
require_once 'auth.php';

// Jika belum login ke sistem sama sekali, kembali ke index
if (empty($_SESSION['master_id'])) {
    header('Location: index.php');
    exit;
}

// Jika sudah join sesi (punya siswa_id), langsung ke dashboard
if (!empty($_SESSION['siswa_id'])) {
    header('Location: dashboard-siswa.php');
    exit;
}

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'join') {
        $hasil = joinSesiSiswa($conn, $_POST['token'] ?? '');
        if ($hasil['success']) {
            header('Location: dashboard-siswa.php');
            exit;
        }
        $err = $hasil['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Masukkan Token Sesi – PADI-PJOK</title>
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
      background: radial-gradient(circle, rgba(26,86,219,.15) 0%, transparent 70%);
      top: -100px; left: -100px;
    }

    .card {
      position: relative;
      z-index: 1;
      width: 100%;
      max-width: 400px;
      background: var(--white);
      border-radius: 28px;
      box-shadow: 0 10px 40px rgba(0,0,0,.14);
      padding: 32px 24px;
      text-align: center;
      animation: slideUp .4s cubic-bezier(.22,1,.36,1) both;
    }

    @keyframes slideUp {
      from { opacity: 0; transform: translateY(20px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    .icon-box {
      width: 64px;
      height: 64px;
      margin: 0 auto 16px;
      background: var(--blue-light);
      border-radius: 16px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--blue-primary);
    }
    .icon-box svg { width: 32px; height: 32px; }

    h1 {
      font-size: 22px;
      font-weight: 800;
      color: var(--text-dark);
      margin-bottom: 8px;
    }
    p {
      font-size: 13px;
      color: var(--text-gray);
      margin-bottom: 24px;
      line-height: 1.5;
    }

    .form-group { text-align: left; margin-bottom: 16px; }
    .form-label {
      font-size: 13px;
      font-weight: 600;
      color: var(--text-dark);
      display: block;
      margin-bottom: 6px;
    }
    .form-input {
      width: 100%;
      padding: 14px;
      border: 1.5px solid var(--border);
      border-radius: var(--radius-sm);
      font-family: inherit;
      font-size: 16px;
      text-align: center;
      letter-spacing: 2px;
      font-weight: 700;
      color: var(--blue-primary);
      text-transform: uppercase;
      outline: none;
      transition: all .2s;
    }
    .form-input::placeholder {
      color: var(--text-light);
      letter-spacing: 0;
      font-weight: 400;
      text-transform: none;
    }
    .form-input:focus {
      border-color: var(--blue-primary);
      box-shadow: 0 0 0 3px rgba(26,86,219,.12);
    }

    .btn-submit {
      width: 100%;
      padding: 15px;
      background: var(--blue-primary);
      color: var(--white);
      border: none;
      border-radius: var(--radius-sm);
      font-size: 15px;
      font-weight: 700;
      cursor: pointer;
      box-shadow: 0 6px 20px rgba(26,86,219,.35);
      transition: all .2s;
    }
    .btn-submit:hover {
      background: var(--blue-dark);
      transform: translateY(-2px);
    }

    .alert {
      display: flex; gap: 10px; align-items: flex-start;
      background: #FEF2F2; border: 1.5px solid #FECACA; color: #991B1B;
      border-radius: var(--radius-sm); padding: 12px 14px;
      font-size: 12.5px; line-height: 1.5; margin-bottom: 16px;
      text-align: left;
    }
    .alert svg { width: 18px; height: 18px; flex-shrink: 0; margin-top: 1px; }

    .user-info {
      font-size: 12px;
      color: var(--text-light);
      margin-top: 20px;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
    }
  </style>
</head>
<body>

<div class="card">
  <div class="icon-box">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
      <path d="M7 11V7a5 5 0 0110 0v4"></path>
    </svg>
  </div>
  
  <h1>Token Sesi Materi</h1>
  <p>Masukkan token yang diberikan oleh guru PJOK untuk masuk ke kelas dan mengikuti penilaian hari ini.</p>

  <?php if ($err !== ''): ?>
  <div class="alert">
    <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/><path d="M12 8v4M12 16h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
    <span><?= htmlspecialchars($err) ?></span>
  </div>
  <?php endif; ?>

  <form method="post">
    <input type="hidden" name="action" value="join"/>
    <div class="form-group">
      <input type="text" class="form-input" name="token" placeholder="Masukkan Token..." required autocomplete="off" />
    </div>
    <button type="submit" class="btn-submit">Masuk Sesi</button>
  </form>

  <div class="user-info">
    Masuk sebagai: <strong><?= htmlspecialchars($_SESSION['siswa_nama'] ?? '') ?></strong>
  </div>
</div>

</body>
</html>
