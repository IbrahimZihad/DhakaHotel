<?php
/**
 * Public page <head>. Expects:
 *   $pageTitle       string  already translated, without site name
 *   $pageDescription string  optional meta description
 *   $bodyClass       string  optional extra body classes
 *   $activeNav       string  optional nav key
 *   $extraCss        array   optional additional stylesheet paths
 *   $ogImage         string  optional URL or repo-relative path, 1.91:1 ideal
 *   $ogImageAlt      string  optional alt text for the share image
 *   $ogType          string  optional Open Graph type, default 'website'
 *   $robots          string  default 'index, follow'
 *   $titleTag        string  optional complete title, bypassing the site suffix
 *   $canonicalKeep   array   whitelist of query params that define this page
 *   $seo             array   structured-data context, see seo_graph()
 */

$pageTitle = $pageTitle ?? '';
$pageDescription = $pageDescription ?? restaurant('description');
$bodyClass = $bodyClass ?? '';
$siteName = restaurant_name();
// The homepage writes its own complete title so the brand can lead it.
$fullTitle = ($titleTag ?? '') !== ''
    ? (string) $titleTag
    : ($pageTitle !== '' ? $pageTitle . ' · ' . $siteName : $siteName);

$metaDescription = trim(strip_tags($pageDescription));
if (mb_strlen($metaDescription) > 155) {
    $metaDescription = mb_substr($metaDescription, 0, 152) . '…';
}

// Canonical: current path and query, minus the language switcher parameter.
$canonicalPath = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$basePath = (string) config('app.base_path', '');
if ($basePath !== '' && str_starts_with($canonicalPath, $basePath)) {
    $canonicalPath = substr($canonicalPath, strlen($basePath));
}
// /index.php and / serve the same page, so the directory form is the canonical
// one and the two URLs cannot be indexed as duplicates.
if (strtolower(basename($canonicalPath)) === 'index.php') {
    $canonicalPath = rtrim(substr($canonicalPath, 0, -strlen('index.php')), '/') . '/';
}
$canonicalQuery = $_GET;
unset($canonicalQuery['lang']);
// A page may whitelist the parameters that actually define it. Sort orders and
// search refinements only produce duplicate views of the same list, so leaving
// them in the canonical invites Google to index each permutation.
$canonicalKeep = $canonicalKeep ?? null;
if (is_array($canonicalKeep)) {
    $canonicalQuery = array_intersect_key($canonicalQuery, array_flip($canonicalKeep));
}
if (($canonicalQuery['page'] ?? '') === '1') {
    unset($canonicalQuery['page']);
}
$canonicalUrl = absolute_url(ltrim($canonicalPath, '/') . ($canonicalQuery !== [] ? '?' . http_build_query($canonicalQuery) : ''));

$ogImage = $ogImage ?? 'assets/images/og-default.jpg';
$ogImageUrl = seo_asset_url($ogImage);
$ogType = $ogType ?? 'website';
$robots = $robots ?? 'index, follow';
$ogImageAlt = $ogImageAlt ?? ($siteName . ' — ' . restaurant('tagline'));

// Share image dimensions are read from the file so the tags never lie.
$ogImageSize = null;
$imagePath = (string) parse_url($ogImageUrl, PHP_URL_PATH);
if ($basePath !== '' && str_starts_with($imagePath, $basePath)) {
    $imagePath = substr($imagePath, strlen($basePath));
}
$ogImageFile = DH_ROOT . '/' . ltrim($imagePath, '/');
if (is_file($ogImageFile)) {
    $dims = @getimagesize($ogImageFile);
    if (is_array($dims) && $dims[0] > 0 && $dims[1] > 0) {
        $ogImageSize = [$dims[0], $dims[1]];
    }
}

$ogLocales = ['bn' => 'bn_BD', 'en' => 'en_US'];
$ogLocale = $ogLocales[current_lang()] ?? 'bn_BD';
$langUrl = static function (string $locale) use ($canonicalPath, $canonicalQuery): string {
    $canonicalQuery['lang'] = $locale;
    return absolute_url(ltrim($canonicalPath, '/') . '?' . http_build_query($canonicalQuery));
};

// Structured data describes an indexable page; a noindex cart step has nothing
// to advertise and the Restaurant node there only duplicates the menu page.
$seoJson = str_contains($robots, 'noindex') ? '' : seo_json_ld_tag($seo ?? []);
?><!doctype html>
<html lang="<?= e(current_lang()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($fullTitle) ?></title>
<?php if ($metaDescription !== ''): ?>
<meta name="description" content="<?= e($metaDescription) ?>">
<?php endif; ?>
<meta name="robots" content="<?= e($robots) ?>">
<meta name="theme-color" content="#211d1a">
<meta name="color-scheme" content="light">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<meta name="application-name" content="<?= e($siteName) ?>">
<meta name="author" content="<?= e($siteName) ?>">
<link rel="canonical" href="<?= e($canonicalUrl) ?>">
<?php foreach (array_keys($ogLocales) as $locale): ?>
<link rel="alternate" hreflang="<?= e($locale) ?>" href="<?= e($langUrl($locale)) ?>">
<?php endforeach; ?>
<link rel="alternate" hreflang="x-default" href="<?= e($canonicalUrl) ?>">
<meta property="og:site_name" content="<?= e($siteName) ?>">
<meta property="og:title" content="<?= e($fullTitle) ?>">
<meta property="og:type" content="<?= e($ogType) ?>">
<meta property="og:url" content="<?= e($canonicalUrl) ?>">
<meta property="og:image" content="<?= e($ogImageUrl) ?>">
<meta property="og:image:alt" content="<?= e($ogImageAlt) ?>">
<?php if ($ogImageSize !== null): ?>
<meta property="og:image:width" content="<?= (int) $ogImageSize[0] ?>">
<meta property="og:image:height" content="<?= (int) $ogImageSize[1] ?>">
<?php endif; ?>
<meta property="og:locale" content="<?= e($ogLocale) ?>">
<?php foreach ($ogLocales as $locale => $value): ?>
<?php if ($value !== $ogLocale): ?>
<meta property="og:locale:alternate" content="<?= e($value) ?>">
<?php endif; ?>
<?php endforeach; ?>
<?php if ($metaDescription !== ''): ?>
<meta property="og:description" content="<?= e($metaDescription) ?>">
<?php endif; ?>
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= e($fullTitle) ?>">
<?php if ($metaDescription !== ''): ?>
<meta name="twitter:description" content="<?= e($metaDescription) ?>">
<?php endif; ?>
<meta name="twitter:image" content="<?= e($ogImageUrl) ?>">
<meta name="twitter:image:alt" content="<?= e($ogImageAlt) ?>">
<?= $seoJson ?>

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
