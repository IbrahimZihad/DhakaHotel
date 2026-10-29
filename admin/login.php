<?php
/**
 * Owner / staff sign-in. Rate limited by email + IP (see includes/auth.php).
 */

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';

if (admin_check()) {
    redirect('admin/index.php');
}

$error = '';
$email = '';

if (is_post()) {
    require_csrf();
    $email = clean_text($_POST['email'] ?? '', 190);
    $password = (string) ($_POST['password'] ?? '');
    $result = attempt_admin_login($email, $password, $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');

    if ($result['ok']) {
        redirect(safe_admin_next($_GET['next'] ?? null));
    }

    $error = $result['error'] === 'rate_limited'
        ? __('admin.rate_limited')
        : __('admin.invalid_credentials');
}

$siteName = restaurant_name();
?><!doctype html>
<html lang="<?= e(current_lang()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e(__('admin.login_title') . ' · ' . $siteName) ?></title>
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#3d8542">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<link rel="icon" href="<?= e(asset('assets/images/icons/icon-192.png')) ?>" sizes="192x192">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans+Bengali:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="<?= e(asset('assets/css/style.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('assets/css/admin.css')) ?>">
</head>
<body class="admin-body admin-login-body">
<main class="login-card">
  <div class="login-brand">
    <span class="brand-mark"><?= brand_mark_svg() ?></span>
    <div>
      <h1><?= e(__('admin.login_title')) ?></h1>
      <p><?= e(__('admin.login_subtitle')) ?></p>
    </div>
  </div>

  <div style="display:flex;justify-content:flex-end;margin-bottom:8px">
    <?php
    $class = '';
    $id = 'lang-login';
    require DH_ROOT . '/includes/partials/lang-switcher.php';
    ?>
  </div>

  <?php if ($error !== ''): ?>
  <div class="alert alert-danger" role="alert"><?= icon('alert') ?><div><?= e($error) ?></div></div>
  <?php endif; ?>

  <form method="post" action="<?= e(url('admin/login.php' . (isset($_GET['next']) ? '?next=' . rawurlencode((string) $_GET['next']) : ''))) ?>" class="stack" style="margin-top:12px" autocomplete="on">
    <?= csrf_field() ?>
    <div class="field">
      <label for="email"><?= e(__('admin.email')) ?></label>
      <input class="input" type="email" id="email" name="email" value="<?= e($email) ?>" required autocomplete="username" inputmode="email">
    </div>
    <div class="field">
      <label for="password"><?= e(__('admin.password')) ?></label>
      <input class="input" type="password" id="password" name="password" required autocomplete="current-password">
    </div>
    <button class="btn btn-block btn-lg" type="submit"><?= icon('login') ?><span><?= e(__('admin.sign_in')) ?></span></button>
  </form>

  <?php if (config('app.demo_mode')): ?>
  <div class="alert alert-warning" style="margin-top:18px;display:block">
    <?= icon('info') ?>
    <div>
      <strong><?= e(__('admin.demo_credentials')) ?></strong>
      <p class="small" style="margin-top:6px"><?= e(__('admin.demo_warning')) ?></p>
    </div>
  </div>
  <?php endif; ?>

  <div class="login-foot">
    <a href="<?= e(url('index.php')) ?>"><?= icon('chevron-left') ?> <?= e(__('nav.home')) ?></a>
    <span><?= e(__('admin.login_intro')) ?></span>
  </div>
</main>
</body>
</html>
