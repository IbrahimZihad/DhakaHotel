<?php
/**
 * Public page <head>. Expects:
 *   $pageTitle       string  already translated, without site name
 *   $pageDescription string  optional meta description
 *   $bodyClass       string  optional extra body classes
 *   $activeNav       string  optional nav key
 *   $extraCss        array   optional additional stylesheet paths
 */

$pageTitle = $pageTitle ?? '';
$pageDescription = $pageDescription ?? restaurant('description');
$bodyClass = $bodyClass ?? '';
$siteName = restaurant_name();
$fullTitle = $pageTitle !== '' ? $pageTitle . ' · ' . $siteName : $siteName;

$metaDescription = trim(strip_tags($pageDescription));
if (mb_strlen($metaDescription) > 155) {
    $metaDescription = mb_substr($metaDescription, 0, 152) . '…';
}
?><!doctype html>
<html lang="<?= e(current_lang()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($fullTitle) ?></title>
<?php if ($metaDescription !== ''): ?>
<meta name="description" content="<?= e($metaDescription) ?>">
<?php endif; ?>
<meta name="theme-color" content="#f4537d">
<meta name="color-scheme" content="light">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<meta property="og:title" content="<?= e($fullTitle) ?>">
<meta property="og:type" content="website">
<?php if ($metaDescription !== ''): ?>
<meta property="og:description" content="<?= e($metaDescription) ?>">
<?php endif; ?>
<link rel="icon" href="<?= e(asset('assets/images/icons/icon-192.png')) ?>" sizes="192x192">
<link rel="apple-touch-icon" href="<?= e(asset('assets/images/icons/apple-touch-icon.png')) ?>">
<link rel="manifest" href="<?= e(url('manifest.webmanifest')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans+Bengali:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="<?= e(asset('assets/css/style.css')) ?>">
<?php foreach (($extraCss ?? []) as $css): ?>
<link rel="stylesheet" href="<?= e(asset($css)) ?>">
<?php endforeach; ?>
</head>
<body class="<?= e(trim('has-bottom-nav ' . $bodyClass)) ?>">
<a class="skip-link" href="#main"><?= e(__('nav.skip')) ?></a>
<?php if (config('app.demo_mode')): ?>
<div class="demo-banner"><?= e(__('footer.demo_notice')) ?></div>
<?php endif; ?>
