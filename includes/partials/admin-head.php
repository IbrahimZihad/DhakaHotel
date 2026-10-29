<?php
/**
 * Admin <head> and shell opening. Expects:
 *   $pageTitle    string  already translated
 *   $activeNav    string  sidebar key
 *   $adminLayout  string  'sidebar' (default) or 'pos'
 *   $bodyClass    string  optional extra body classes
 *   $extraCss     array   optional additional stylesheet paths
 *   $extraJs      array   optional additional script paths
 */
$pageTitle = $pageTitle ?? '';
$activeNav = $activeNav ?? '';
$adminLayout = $adminLayout ?? 'sidebar';
$bodyClass = $bodyClass ?? '';
$extraCss = $extraCss ?? [];
$extraJs = $extraJs ?? [];
$siteName = restaurant_name();
$fullTitle = ($pageTitle !== '' ? $pageTitle . ' · ' : '') . $siteName;
?><!doctype html>
<html lang="<?= e(current_lang()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($fullTitle) ?></title>
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#3d8542">
<meta name="color-scheme" content="light">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<link rel="icon" href="<?= e(asset('assets/images/icons/icon-192.png')) ?>" sizes="192x192">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans+Bengali:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="<?= e(asset('assets/css/style.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('assets/css/admin.css')) ?>">
<?php foreach ($extraCss as $css): ?>
<link rel="stylesheet" href="<?= e(asset($css)) ?>">
<?php endforeach; ?>
</head>
<body class="<?= e(trim('admin-body ' . ($adminLayout === 'pos' ? 'pos-body ' : '') . $bodyClass)) ?>">
<div class="admin-shell">
<?php require DH_ROOT . '/includes/partials/admin-topbar.php'; ?>
<?php if ($adminLayout !== 'pos'): ?>
<?php require DH_ROOT . '/includes/partials/admin-sidebar.php'; ?>
<main class="admin-main" id="main">
<?php endif; ?>
<?php
$adminFlashMap = [
    'success' => ['icon' => 'check-circle', 'class' => 'alert-success'],
    'error' => ['icon' => 'alert', 'class' => 'alert-danger'],
    'warning' => ['icon' => 'alert', 'class' => 'alert-warning'],
    'info' => ['icon' => 'info', 'class' => 'alert-info'],
];
$adminFlash = flash_messages();
?>
<?php if ($adminFlash !== []): ?>
<div class="<?= $adminLayout === 'pos' ? 'stack pos-flash' : 'stack' ?>"<?= $adminLayout !== 'pos' ? ' style="margin-bottom:18px"' : '' ?> role="status" aria-live="polite">
  <?php foreach ($adminFlash as $adminMessage):
      $adminType = (string) ($adminMessage['type'] ?? 'info');
      $adminMeta = $adminFlashMap[$adminType] ?? $adminFlashMap['info'];
  ?>
  <div class="alert <?= e($adminMeta['class']) ?>">
    <?= icon($adminMeta['icon']) ?>
    <div><?= e((string) ($adminMessage['message'] ?? '')) ?></div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
