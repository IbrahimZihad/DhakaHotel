<?php
/**
 * Search-engine structured data. Builds schema.org nodes for the pages that
 * include site-head.php; every value here is derived from data the visitor can
 * already see, because invented markup is a manual-action risk.
 *
 * Nothing in this file prints. Call seo_json_ld_tag() to get the block.
 */

declare(strict_types=1);

/** Any URL-ish value -> absolute, without doubling the base path. */
function seo_asset_url(string $pathOrUrl): string
{
    if (preg_match('#^https?://#i', $pathOrUrl) === 1) {
        return $pathOrUrl;
    }
    if (str_starts_with($pathOrUrl, '/')) {
        return site_origin() . $pathOrUrl;
    }

    return absolute_url($pathOrUrl);
}

/** Local Bangladeshi mobile numbers to +880E164; anything else is left alone. */
function seo_phone(string $raw): string
{
    $digits = (string) preg_replace('/\D+/', '', $raw);
    if (preg_match('/^0?(1[3-9]\d{8})$/', $digits, $m) === 1) {
        return '+880' . $m[1];
    }

    return trim($raw);
}

/**
 * "Daily, 11:00 AM – 11:00 PM" -> openingHoursSpecification rows.
 * Returns [] unless both a time span and a day set are readable, so a wording
 * change in the admin never produces half-guessed hours.
 */
function seo_opening_hours(): array
{
    $text = trim((string) setting('hours_en', ''));
    if ($text === '') {
        $text = trim((string) setting('hours_bn', ''));
    }
    if ($text === '') {
        return [];
    }

    $pattern = '/(\d{1,2})[:.](\d{2})\s*(AM|PM|am|pm)?\s*\x{2013}\s*(\d{1,2})[:.](\d{2})\s*(AM|PM|am|pm)?/u';
    if (preg_match($pattern, $text, $m) !== 1) {
        return [];
    }

    $to24 = static function (string $hour, string $minute, string $meridiem): string {
        $h = (int) $hour % 12;
        if (strtoupper($meridiem) === 'PM') {
            $h += 12;
        }

        return sprintf('%02d:%02d', $h, (int) $minute);
    };

    $opens = $to24($m[1], $m[2], $m[3] ?? '');
    $closes = $to24($m[4], $m[5], $m[6] ?? '');
    if ($opens === $closes) {
        return [];
    }

    $days = [];
    if (preg_match('/daily|everyday|প্রতিদিন|সারাদিন/iu', $text) === 1) {
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    } else {
        $names = [
            'monday' => 'Monday', 'tuesday' => 'Tuesday', 'wednesday' => 'Wednesday',
            'thursday' => 'Thursday', 'friday' => 'Friday', 'saturday' => 'Saturday', 'sunday' => 'Sunday',
        ];
        foreach ($names as $needle => $canon) {
            if (stripos($text, $needle) !== false) {
                $days[] = $canon;
            }
        }
    }
    if ($days === []) {
        return [];
    }

    return [[
        '@type' => 'OpeningHoursSpecification',
        'dayOfWeek' => $days,
        'opens' => $opens,
        'closes' => $closes,
    ]];
}

/** Budget/mid/luxury indicator from the live menu, so it self-corrects. */
function seo_price_range(): string
{
    $average = (float) fetch_value(
        'SELECT AVG(mi.price) FROM menu_items mi
         JOIN categories c ON c.id = mi.category_id
         WHERE c.is_active = 1 AND mi.is_available = 1',
        [],
        0
    );
    if ($average <= 0.0) {
        return '';
    }

    return match (true) {
        $average < 250 => '$',
        $average < 800 => '$$',
        $average < 2000 => '$$$',
        default => '$$$$',
    };
}

/**
 * Real photos only: featured dishes first, then any priced dish. The SVG
 * placeholder is dropped because scrapers do not render it.
 */
function seo_gallery(): array
{
    $rows = fetch_all(
        "SELECT mi.image_path FROM menu_items mi
         JOIN categories c ON c.id = mi.category_id
         WHERE c.is_active = 1 AND mi.is_available = 1
         ORDER BY mi.is_featured DESC, mi.sort_order ASC, mi.id ASC
         LIMIT 8"
    );

    $urls = [];
    foreach ($rows as $row) {
        $url = menu_image_url($row['image_path'] ?? null);
        if (str_ends_with(strtolower((string) parse_url($url, PHP_URL_PATH)), '.svg')) {
            continue;
        }
        $urls[] = seo_asset_url($url);
    }

    $shareCard = seo_asset_url('assets/images/og-default.jpg');

    return array_values(array_unique(array_merge([$shareCard], $urls)));
}

/**
 * Ratings are withheld while the demo seed is in force. Publishing an
 * aggregateRating built from seeded reviews would advertise a reputation the
 * restaurant has not earned.
 */
function seo_aggregate_rating(): ?array
{
    if (config('app.demo_mode')) {
        return null;
    }

    $row = fetch_one(
        "SELECT COUNT(*) AS review_count, AVG(rating) AS average
         FROM reviews WHERE status = 'approved' AND is_demo = 0"
    );
    $count = (int) ($row['review_count'] ?? 0);
    if ($count < 2) {
        return null;
    }

    return [
        '@type' => 'AggregateRating',
        'ratingValue' => number_format((float) $row['average'], 1, '.', ''),
        'reviewCount' => $count,
        'bestRating' => 5,
        'worstRating' => 1,
    ];
}

function seo_restaurant_id(): string
{
    return rtrim(absolute_url(''), '/') . '/#restaurant';
}

function seo_website_id(): string
{
    return rtrim(absolute_url(''), '/') . '/#website';
}

function seo_restaurant_node(): array
{
    $otherLang = current_lang() === 'bn' ? 'en' : 'bn';
    $name = restaurant_name();
    $alternate = trim(restaurant('name', $otherLang));
    // Mirror the address the visitor can see, so the markup never claims more
    // than the page shows.
    $street = trim(restaurant('address'));

    $node = [
        '@type' => 'Restaurant',
        '@id' => seo_restaurant_id(),
        'name' => $name,
        'url' => rtrim(absolute_url(''), '/') . '/',
        'logo' => seo_asset_url('assets/images/logo.webp'),
        'image' => seo_gallery(),
        'description' => trim(strip_tags(restaurant('description'))),
        'address' => [
            '@type' => 'PostalAddress',
            'streetAddress' => $street,
            'addressLocality' => current_lang() === 'bn' ? 'পূর্বাচল' : 'Purbachal',
            'addressRegion' => current_lang() === 'bn' ? 'ঢাকা' : 'Dhaka',
            'addressCountry' => 'BD',
        ],
        'servesCuisine' => ['Bangladeshi'],
        'priceRange' => seo_price_range(),
        'hasMenu' => seo_asset_url(url('menu.php')),
        'inLanguage' => array_values(available_languages()),
    ];

    if ($alternate !== '' && $alternate !== $name) {
        $node['alternateName'] = $alternate;
    }

    $phones = array_filter([
        seo_phone((string) setting('phone', '')),
        seo_phone((string) setting('phone2', '')),
    ], static fn (string $p): bool => $p !== '');
    if ($phones !== []) {
        $node['telephone'] = array_values($phones);
    }

    $hours = seo_opening_hours();
    if ($hours !== []) {
        $node['openingHoursSpecification'] = $hours;
    }

    $rating = seo_aggregate_rating();
    if ($rating !== null) {
        $node['aggregateRating'] = $rating;
    }

    // A contact point only when the owner has actually supplied an address.
    $email = trim((string) setting('owner_email', ''));
    if ($email !== '') {
        $node['contactPoint'] = [[
            '@type' => 'ContactPoint',
            'contactType' => 'reservations',
            'email' => $email,
            'availableLanguage' => array_values(available_languages()),
        ]];
    }

    return array_filter($node, static fn (mixed $v): bool => $v !== '' && $v !== [] && $v !== null);
}

function seo_website_node(): array
{
    return [
        '@type' => 'WebSite',
        '@id' => seo_website_id(),
        'url' => rtrim(absolute_url(''), '/') . '/',
        'name' => restaurant_name(),
        'alternateName' => seo_restaurant_node()['alternateName'] ?? null,
        'description' => trim(strip_tags(restaurant('description'))),
        'inLanguage' => array_values(available_languages()),
        'publisher' => ['@id' => seo_restaurant_id()],
        'potentialAction' => [[
            '@type' => 'SearchAction',
            'target' => [
                '@type' => 'EntryPoint',
                'urlTemplate' => seo_asset_url(url('menu.php')) . '?q={search_term_string}',
            ],
            'query-input' => 'required name=search_term_string',
        ]],
    ];
}

/** Trail of ['name' => .., 'path' => ..]; the last entry is the current page. */
function seo_breadcrumb_node(array $trail): array
{
    $items = [];
    foreach (array_values($trail) as $index => $crumb) {
        $name = trim((string) ($crumb['name'] ?? ''));
        if ($name === '') {
            continue;
        }
        $items[] = [
            '@type' => 'ListItem',
            'position' => count($items) + 1,
            'name' => $name,
            'item' => seo_asset_url((string) ($crumb['path'] ?? '')),
        ];
    }
    if (count($items) < 2) {
        return [];
    }

    return ['@type' => 'BreadcrumbList', 'itemListElement' => $items];
}

/** The full menu as Menu > hasMenuSection > hasMenuItem, straight from the DB. */
function seo_menu_node(): array
{
    $categories = fetch_all(
        'SELECT c.id, c.slug, c.name_bn, c.name_en, c.description_bn, c.description_en
         FROM categories c WHERE c.is_active = 1
         ORDER BY c.sort_order ASC, c.name_en ASC'
    );
    if ($categories === []) {
        return [];
    }

    $itemsByCategory = [];
    $rows = fetch_all(
        'SELECT mi.id, mi.category_id, mi.name_bn, mi.name_en, mi.description_bn,
                mi.description_en, mi.price, mi.image_path, mi.is_available
         FROM menu_items mi
         JOIN categories c ON c.id = mi.category_id
         WHERE c.is_active = 1 ORDER BY mi.sort_order ASC, mi.id ASC'
    );
    foreach ($rows as $row) {
        $itemsByCategory[(int) $row['category_id']][] = $row;
    }

    $sections = [];
    foreach ($categories as $category) {
        $items = [];
        foreach ($itemsByCategory[(int) $category['id']] ?? [] as $row) {
            $available = (int) $row['is_available'] === 1;
            $items[] = array_filter([
                '@type' => 'MenuItem',
                'name' => item_name($row),
                'url' => seo_asset_url(url('menu-item.php?id=' . (int) $row['id'])),
                'image' => seo_asset_url(menu_image_url($row['image_path'])),
                'offers' => [
                    '@type' => 'Offer',
                    'price' => number_format((float) $row['price'], 2, '.', ''),
                    'priceCurrency' => (string) setting('currency', 'BDT'),
                    'availability' => $available
                        ? 'https://schema.org/InStock'
                        : 'https://schema.org/OutOfStock',
                    'url' => seo_asset_url(url('menu-item.php?id=' . (int) $row['id'])),
                ],
            ], static fn (mixed $v): bool => $v !== '' && $v !== [] && $v !== null);
        }
        if ($items === []) {
            continue;
        }

        $sections[] = array_filter([
            '@type' => 'MenuSection',
            'name' => item_name($category),
            'description' => item_description($category) !== '' ? item_description($category) : null,
            'url' => seo_asset_url(url('menu.php?category=' . urlencode((string) $category['slug']))),
            'hasMenuItem' => $items,
        ], static fn (mixed $v): bool => $v !== '' && $v !== [] && $v !== null);
    }

    return [
        '@type' => 'Menu',
        '@id' => seo_asset_url(url('menu.php')) . '#menu',
        'name' => __('menu.title'),
        'url' => seo_asset_url(url('menu.php')),
        'inLanguage' => current_lang(),
        'hasMenuSection' => $sections,
    ];
}

/** One dish as a purchasable Product, mirroring the card on the page. */
function seo_product_node(array $dish): array
{
    $price = number_format((float) ($dish['price'] ?? 0), 2, '.', '');
    $available = (int) ($dish['is_available'] ?? 0) === 1;
    $image = menu_image_url($dish['image_path'] ?? null);
    $node = [
        '@type' => 'Product',
        '@id' => seo_asset_url(url('menu-item.php?id=' . (int) $dish['id'])) . '#product',
        'name' => item_name($dish),
        'category' => isset($dish['category_slug'])
            ? localized($dish, 'category_name')
            : null,
        'image' => seo_asset_url($image),
        'description' => trim(strip_tags(item_description($dish))),
        'brand' => ['@type' => 'Brand', 'name' => restaurant_name()],
        'offers' => [
            '@type' => 'Offer',
            'price' => $price,
            'priceCurrency' => (string) setting('currency', 'BDT'),
            'availability' => $available
                ? 'https://schema.org/InStock'
                : 'https://schema.org/OutOfStock',
            'url' => seo_asset_url(url('menu-item.php?id=' . (int) $dish['id'])),
            'eligibleQuantity' => [
                '@type' => 'QuantitativeValue',
                'minValue' => 1,
                'unitText' => 'portion',
            ],
        ],
    ];

    if (str_ends_with(strtolower((string) parse_url($image, PHP_URL_PATH)), '.svg')) {
        $node['image'] = seo_gallery();
    }

    return array_filter($node, static fn (mixed $v): bool => $v !== '' && $v !== [] && $v !== null);
}

/**
 * Assemble the page's @graph. $page accepts:
 *   'menu'        => true to attach the full Menu node
 *   'item'        => dish row, adds the Product node
 *   'breadcrumbs' => trail for seo_breadcrumb_node()
 *   'sections'    => [['name' => .., 'text' => ..], ...] article headings
 *   'title'/'description'/'url' => current page metadata
 */
function seo_graph(array $page = []): array
{
    $restaurant = seo_restaurant_node();
    $nodes = ['restaurant' => $restaurant, 'website' => seo_website_node()];

    $breadcrumbs = seo_breadcrumb_node($page['breadcrumbs'] ?? []);
    if ($breadcrumbs !== []) {
        $nodes['breadcrumb'] = $breadcrumbs;
    }

    if (!empty($page['menu'])) {
        $menu = seo_menu_node();
        if ($menu !== []) {
            $nodes['menu'] = $menu;
            $restaurant['hasMenu'] = ['@id' => $menu['@id']];
            $nodes['restaurant'] = $restaurant;
        }
    }

    if (isset($page['item']) && is_array($page['item'])) {
        $nodes['product'] = seo_product_node($page['item']);
    }

    $sections = [];
    foreach (($page['sections'] ?? []) as $section) {
        $text = trim(strip_tags((string) ($section['text'] ?? '')));
        $name = trim((string) ($section['name'] ?? ''));
        if ($text === '' || $name === '') {
            continue;
        }
        $sections[] = ['@type' => 'WebPageElement', 'name' => $name, 'text' => $text];
    }
    if ($sections !== []) {
        $pageUrl = seo_asset_url((string) ($page['url'] ?? ''));
        $nodes['webpage'] = array_filter([
            '@type' => 'WebPage',
            '@id' => $pageUrl . '#webpage',
            'url' => $pageUrl,
            'name' => (string) ($page['title'] ?? restaurant_name()),
            'description' => (string) ($page['description'] ?? trim(strip_tags(restaurant('description')))),
            'inLanguage' => current_lang(),
            'isPartOf' => ['@id' => seo_website_id()],
            'about' => ['@id' => seo_restaurant_id()],
            'hasPart' => $sections,
        ], static fn (mixed $v): bool => $v !== '' && $v !== [] && $v !== null);
    }

    return array_values($nodes);
}

/** The <script> block, or '' when there is nothing to say. */
function seo_json_ld_tag(array $page = []): string
{
    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
    $json = json_encode(
        ['@context' => 'https://schema.org', '@graph' => seo_graph($page)],
        $flags
    );
    if ($json === false) {
        return '';
    }

    return '<script type="application/ld+json">' . $json . '</script>';
}
