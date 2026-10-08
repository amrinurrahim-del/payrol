<?php
declare(strict_types=1);

require_once __DIR__ . '/lib.php';

/* Keluar */
if (isset($_GET['keluar'])) {
    sesi_berakhir();
    redirect('login.php?ok=' . rawurlencode('Anda telah keluar.'));
}

/* Sudah masuk → langsung ke dashboard */
if (sesi_user()) {
    redirect('index.php');
}

$error = (string) ($_GET['err'] ?? '');
if (is_post()) {
    $hasil = login_coba(post('username'), (string) ($_POST['sandi'] ?? ''));
    if ($hasil['ok']) {
        log_admin('login', 'Berhasil masuk');
        redirect('index.php');
    }
    $error = (string) $hasil['error'];
}
$app = conf('app_nama', 'HR & Payroll');
$logo = conf('logo_url', '');
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Masuk — <?= h($app) ?></title>
<link rel="stylesheet" href="<?= h(asset('assets/style.css')) ?>">
</head>
<body>
<div class="auth-wrap">
    <div class="auth-kartu">
        <div class="auth-kepala">
            <?php if ($logo !== ''): ?>
                <img class="auth-logo" src="<?= h($logo) ?>" alt="Logo <?= h(conf('perusahaan_nama')) ?>">
            <?php else: ?>
                <span class="auth-logo" style="display:grid;place-items:center;color:var(--biru-700)"><?= icon('wallet', 30) ?></span>
            <?php endif; ?>
            <div class="auth-perusahaan"><?= h(conf('perusahaan_nama', $app)) ?></div>
            <div class="auth-sub"><?= h($app) ?></div>
        </div>

        <?php if ($error !== ''): ?>
            <div class="flash flash-err"><?= h($error) ?></div>
        <?php endif; ?>
        <?php flash(); ?>

        <form method="post" autocomplete="on">
            <div style="margin-bottom:14px">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required autofocus
                       pattern="[A-Za-z0-9._\-]{3,24}" value="<?= h(post('username')) ?>">
            </div>
            <div style="margin-bottom:20px">
                <label for="sandi">Kata Sandi</label>
                <input type="password" id="sandi" name="sandi" required>
            </div>
            <button class="btn btn-primer" type="submit">Masuk ke Aplikasi</button>
        </form>

        <div class="auth-kaki">
            Aplikasi penggajian karyawan — hak akses hanya untuk Superadmin &amp; HRD.<br>
            Lupa kata sandi? Hubungi Superadmin untuk menyetel ulang.
        </div>
    </div>
</div>
</body>
</html>
