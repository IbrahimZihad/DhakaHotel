<?php
/**
 * Offline fallback. Intentionally free of database calls so the service
 * worker can cache the rendered HTML and serve it without a connection.
 */

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$lang = current_lang();
$dir = $lang === 'bn' ? 'ltr' : 'ltr';
?><!doctype html>
<html lang="<?= e($lang) ?>" dir="<?= e($dir) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(__('pwa.offline_title')) ?></title>
<meta name="theme-color" content="#f4537d">
<meta name="robots" content="noindex">
<link rel="icon" href="<?= e(asset('assets/images/icons/icon-192.png')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans+Bengali:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="<?= e(asset('assets/css/style.css')) ?>">
<style>
  .offline-wrap { min-height: 100vh; display: grid; place-items: center; padding: 24px; }
  .offline-card { max-width: 460px; text-align: center; padding: clamp(28px, 6vw, 44px); }
  .offline-icon { width: 84px; height: 84px; border-radius: 50%; background: var(--pink-100); color: var(--pink-600); display: grid; place-items: center; margin: 0 auto 18px; }
  .offline-icon svg { width: 40px; height: 40px; }
</style>
</head>
<body>
<main class="offline-wrap">
  <div class="card offline-card">
    <div class="offline-icon">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m2 3 20 18"/><path d="M8.5 15.5a5 5 0 0 1 7 0M5 12a10 10 0 0 1 3-2M19 12a10 10 0 0 0-5.5-2.8M12 19.5h.01"/></svg>
    </div>
    <h1 style="font-size:var(--fs-2xl)"><?= e(__('pwa.offline_title')) ?></h1>
    <p class="muted" style="margin-top:10px"><?= e(__('pwa.offline_text')) ?></p>
    <a class="btn btn-lg" href="<?= e(url('')) ?>" style="margin-top:22px"><?= e(__('pwa.retry')) ?></a>
    <p class="small muted" style="margin-top:18px"><?= e(__('pwa.cached_note')) ?></p>
  </div>
</main>
</body>
</html>
