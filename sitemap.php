<?php
/**
 * XML sitemap built from live data, so a dish that goes off the menu leaves the
 * index on the next crawl instead of waiting for a hand-edited file.
 * Apache rewrites /sitemap.xml here; see .htaccess.
 *
 * No <priority> or <changefreq>: Google has said for years it ignores both, and
 * stale guesses are worse than nothing.
 */

declare(strict_types=1);

define('DH_MACHINE_READABLE', true);
require __DIR__ . '/includes/bootstrap.php';

/** W3C datetime in the app's timezone, from either a DB timestamp or a file. */
function sitemap_lastmod(?string $dbTime, string $fallbackPath = ''): string
{
    if ($dbTime !== null && $dbTime !== '') {
        $stamp = strtotime($dbTime);
        if ($stamp !== false) {
            return date('c', $stamp);
        }
    }
    if ($fallbackPath !== '' && is_file(DH_ROOT . '/' . $fallbackPath)) {
        return date('c', (int) filemtime(DH_ROOT . '/' . $fallbackPath));
    }

    return date('c');
}

/** The newer of two DB timestamps, tolerating an empty table. */
function sitemap_newer(?string ...$times): ?string
{
    $times = array_values(array_filter($times, static fn (?string $t): bool => $t !== null && $t !== ''));

    return $times === [] ? null : max($times);
}

$menuChanged = fetch_value(
    'SELECT MAX(mi.updated_at) FROM menu_items mi
     JOIN categories c ON c.id = mi.category_id WHERE c.is_active = 1'
);
$categoriesChanged = fetch_value('SELECT MAX(updated_at) FROM categories');
$reviewsChanged = fetch_value("SELECT MAX(created_at) FROM reviews WHERE status = 'approved'");

$entries = [
    // The directory URL, not /index.php: that is what the homepage canonicalises to.
    ['path' => '', 'lastmod' => sitemap_lastmod($menuChanged, 'index.php')],
    ['path' => 'menu.php', 'lastmod' => sitemap_lastmod(
        sitemap_newer($menuChanged, $categoriesChanged),
        'menu.php'
    )],
];

// Each category is a landing page in its own right ("kabab purbachal"), so it
// gets a loc; the sort and search parameters do not, they only duplicate.
foreach (fetch_all('SELECT slug, updated_at FROM categories WHERE is_active = 1 ORDER BY sort_order') as $category) {
    $entries[] = [
        'path' => 'menu.php?category=' . urlencode((string) $category['slug']),
        'lastmod' => sitemap_lastmod($category['updated_at'] ?? null),
    ];
}

$dishes = fetch_all(
    'SELECT mi.id, mi.name_bn, mi.name_en, mi.updated_at, mi.image_path
     FROM menu_items mi
     JOIN categories c ON c.id = mi.category_id
     WHERE c.is_active = 1
     ORDER BY mi.id ASC'
);
foreach ($dishes as $dish) {
    $imageUrl = menu_image_url($dish['image_path'] ?? null);
    $entries[] = [
        'path' => 'menu-item.php?id=' . (int) $dish['id'],
        'lastmod' => sitemap_lastmod($dish['updated_at'] ?? null),
        'image' => str_ends_with(strtolower((string) parse_url($imageUrl, PHP_URL_PATH)), '.svg')
            ? null
            : seo_asset_url($imageUrl),
    ];
}

$entries[] = ['path' => 'about.php', 'lastmod' => sitemap_lastmod(null, 'about.php')];
$entries[] = ['path' => 'reviews.php', 'lastmod' => sitemap_lastmod($reviewsChanged, 'reviews.php')];
$entries[] = ['path' => 'contact.php', 'lastmod' => sitemap_lastmod(null, 'contact.php')];

$languages = array_values(available_languages());

// Built as a string so the XML prolog is guaranteed to be byte zero.
$body = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
$body .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"'
    . ' xmlns:xhtml="http://www.w3.org/1999/xhtml"'
    . ' xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";

foreach ($entries as $entry) {
    $loc = absolute_url($entry['path']);
    $separator = str_contains($loc, '?') ? '&' : '?';
    $body .= '  <url>' . "\n";
    $body .= '    <loc>' . e($loc) . '</loc>' . "\n";
    foreach ($languages as $code) {
        $body .= '    <xhtml:link rel="alternate" hreflang="' . e($code)
            . '" href="' . e($loc . $separator . 'lang=' . $code) . '"/>' . "\n";
    }
    $body .= '    <xhtml:link rel="alternate" hreflang="x-default" href="' . e($loc) . '"/>' . "\n";
    $body .= '    <lastmod>' . e($entry['lastmod']) . '</lastmod>' . "\n";
    if (!empty($entry['image'])) {
        $body .= "    <image:image>\n      <image:loc>" . e($entry['image'])
            . "</image:loc>\n    </image:image>\n";
    }
    $body .= '  </url>' . "\n";
}

$body .= '</urlset>' . "\n";

header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=3600');
echo $body;
