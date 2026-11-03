<?php
/**
 * robots.txt, generated rather than committed so the rules carry the real
 * install prefix and the Sitemap line can never name a domain the site does
 * not live on. Apache rewrites /robots.txt here; see .htaccess.
 */

declare(strict_types=1);

define('DH_MACHINE_READABLE', true);
require __DIR__ . '/includes/bootstrap.php';

/*
 * Cart, checkout and tracking are deliberately NOT disallowed: they carry a
 * noindex meta tag, and a blocked URL cannot be crawled far enough for that
 * instruction to be read. Only paths with no indexing value at all are closed.
 */
$closed = [
    'admin/',
    'install.php',
    'scripts/',
    'storage/',
    'offline.php',
    'service-worker.js',
];

$body = "User-agent: *\n";
foreach ($closed as $path) {
    $body .= 'Disallow: ' . url($path) . "\n";
}
$body .= "\nSitemap: " . absolute_url('sitemap.xml') . "\n";

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: public, max-age=3600');
echo $body;
