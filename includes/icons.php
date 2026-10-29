<?php
/**
 * Inline SVG icon set (stroke style, 24x24). No icon font or external
 * request is involved, so icons work offline and match text colour.
 */

declare(strict_types=1);

function icon(string $name, string $class = 'icon'): string
{
    $paths = [
        'home' => '<path d="M3 10.4 12 3l9 7.4V21H3z"/><path d="M9 21v-6h6v6"/>',
        'menu' => '<path d="M4 6h16M4 12h16M4 18h10"/>',
        'utensils' => '<path d="M6 3v7a2 2 0 0 0 2 2h0a2 2 0 0 0 2-2V3"/><path d="M8 12v9"/><path d="M16 3c-1.7 1.3-2.5 3-2.5 5.5 0 2 .8 3.2 2.5 3.5V21"/>',
        'cart' => '<circle cx="9" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/><path d="M2.5 3h2.2l2.4 11.2a2 2 0 0 0 2 1.6h8.2a2 2 0 0 0 2-1.6L21 7H6"/>',
        'bag' => '<path d="M5 8h14l1 13H4z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/>',
        'receipt' => '<path d="M6 2h12v20l-3-2-3 2-3-2-3 2z"/><path d="M9 7h6M9 11h6M9 15h4"/>',
        'receipt-long' => '<path d="M5 2h14v20l-2.3-1.6L14.4 22 12 20.4 9.6 22 7.3 20.4 5 22z"/><path d="M9 7h6M9 11h6M9 15h3"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.6-3.6"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'minus' => '<path d="M5 12h14"/>',
        'x' => '<path d="M6 6l12 12M18 6 6 18"/>',
        'check' => '<path d="m4 12.5 5 5L20 6.5"/>',
        'check-circle' => '<circle cx="12" cy="12" r="9"/><path d="m8 12.5 3 3 5-6"/>',
        'trash' => '<path d="M3 6h18M8 6V4h8v2M6 6l1 15h10l1-15"/><path d="M10 11v6M14 11v6"/>',
        'edit' => '<path d="M4 20h4L20 8l-4-4L4 16z"/><path d="m14 6 4 4"/>',
        'eye' => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        'phone' => '<path d="M5 3h4l2 5-2.5 1.5a12 12 0 0 0 6 6L16 13l5 2v4a2 2 0 0 1-2.2 2A17 17 0 0 1 3 5.2 2 2 0 0 1 5 3z"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3.5 6.5 8.5 6 8.5-6"/>',
        'map-pin' => '<path d="M12 21s7-5.5 7-11a7 7 0 1 0-14 0c0 5.5 7 11 7 11z"/><circle cx="12" cy="10" r="2.6"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c.8-4 4-6 8-6s7.2 2 8 6"/>',
        'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 21c.6-3.7 3.2-5.5 6.5-5.5s5.9 1.8 6.5 5.5"/><path d="M16 4.6a3.5 3.5 0 0 1 0 6.8M18 15.8c2 .7 3.2 2.4 3.5 5.2"/>',
        'grid' => '<rect x="3" y="3" width="7.5" height="7.5" rx="1.5"/><rect x="13.5" y="3" width="7.5" height="7.5" rx="1.5"/><rect x="3" y="13.5" width="7.5" height="7.5" rx="1.5"/><rect x="13.5" y="13.5" width="7.5" height="7.5" rx="1.5"/>',
        'chart' => '<path d="M4 20V4"/><path d="M4 20h16"/><rect x="7" y="12" width="3" height="5" rx="1"/><rect x="12" y="8" width="3" height="9" rx="1"/><rect x="17" y="5" width="3" height="12" rx="1"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1-1.5 1.7 1.7 0 0 0-1.9.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1 1.7 1.7 0 0 0-.3-1.9l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.9.3h.1a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.9-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.9v.1a1.7 1.7 0 0 0 1.5 1h.2a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
        'bell' => '<path d="M18 8a6 6 0 1 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/>',
        'logout' => '<path d="M14 4h5a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-5"/><path d="M10 8l-4 4 4 4M6 12h10"/>',
        'login' => '<path d="M10 4H5a1 1 0 0 0-1 1v14a1 1 0 0 0 1 1h5"/><path d="m15 8 4 4-4 4M19 12H9"/>',
        'printer' => '<path d="M7 9V3h10v6"/><rect x="3" y="9" width="18" height="8" rx="2"/><path d="M7 14h10v7H7z"/>',
        'truck' => '<path d="M3 6h11v10H3z"/><path d="M14 9h4l3 3v4h-7"/><circle cx="7" cy="18.5" r="1.8"/><circle cx="17.5" cy="18.5" r="1.8"/>',
        'star' => '<path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1-5.4-2.9-5.4 2.9 1-6.1L3.2 9.5l6.1-.9z"/>',
        'heart' => '<path d="M12 20s-7-4.4-7-9.3A4 4 0 0 1 12 7a4 4 0 0 1 7 3.7C19 15.6 12 20 12 20z"/>',
        'flame' => '<path d="M12 22c4 0 6.5-2.7 6.5-6.2 0-3-1.8-4.9-3.5-6.6C13.2 7.4 12 5.4 12 2c0 0-5 3.3-5 8.3 0 1.6.5 2.7 1.2 3.6C7 13.2 6.5 12 6.5 10.5c-.9 1.2-1.5 2.8-1.5 4.7C5 19.3 8 22 12 22z"/>',
        'leaf' => '<path d="M4 20c8 1 16-4 16-16C11 4 3 8 3 16c0 1.5.3 3 1 4z"/><path d="M4 20C7 14 11 10 16 8"/>',
        'sparkles' => '<path d="m12 3 1.7 4.3L18 9l-4.3 1.7L12 15l-1.7-4.3L6 9l4.3-1.7z"/><path d="M19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8z"/>',
        'shield' => '<path d="M12 22s8-3.5 8-10V5l-8-3-8 3v7c0 6.5 8 10 8 10z"/><path d="m8.5 11.5 2.5 2.5 4.5-5"/>',
        'lock' => '<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
        'key' => '<circle cx="8" cy="15" r="4"/><path d="m11 12 9-9M17 6l3 3M14 9l2 2"/>',
        'globe' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.5 3.8 5.6 3.8 9S14.5 18.5 12 21c-2.5-2.5-3.8-5.6-3.8-9S9.5 5.5 12 3z"/>',
        'chevron-right' => '<path d="m9 5 7 7-7 7"/>',
        'chevron-left' => '<path d="m15 5-7 7 7 7"/>',
        'chevron-down' => '<path d="m5 9 7 7 7-7"/>',
        'arrow-right' => '<path d="M4 12h15M13 6l6 6-6 6"/>',
        'arrow-left' => '<path d="M20 12H5M11 18l-6-6 6-6"/>',
        'arrow-up-right' => '<path d="M6 18 18 6M8 6h10v10"/>',
        'external' => '<path d="M14 4h6v6M20 4 11 13"/><path d="M20 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h5"/>',
        'alert' => '<path d="M12 3 2.5 20h19z"/><path d="M12 9v5M12 17.2v.1"/>',
        'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7.5v.1"/>',
        'ban' => '<circle cx="12" cy="12" r="9"/><path d="m5.6 5.6 12.8 12.8"/>',
        'percent' => '<path d="m6 18 12-12"/><circle cx="7.5" cy="7.5" r="2"/><circle cx="16.5" cy="16.5" r="2"/>',
        'tag' => '<path d="M3 12V4a1 1 0 0 1 1-1h8l9 9-9 9z"/><circle cx="7.5" cy="7.5" r="1.3"/>',
        'image' => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9.5" r="1.5"/><path d="m4 17 5-5 4 4 3-3 4 4"/>',
        'download' => '<path d="M12 3v12M7 10l5 5 5-5"/><path d="M4 20h16"/>',
        'upload' => '<path d="M12 16V4M7 9l5-5 5 5"/><path d="M4 20h16"/>',
        'refresh' => '<path d="M20 11a8 8 0 0 0-14.9-4M4 5v5h5"/><path d="M4 13a8 8 0 0 0 14.9 4M20 19v-5h-5"/>',
        'filter' => '<path d="M3 5h18l-7 8v6l-4 2v-8z"/>',
        'copy' => '<rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/>',
        'wallet' => '<rect x="3" y="6" width="18" height="14" rx="2"/><path d="M3 9h18M16 14h2"/>',
        'credit-card' => '<rect x="2.5" y="5" width="19" height="14" rx="2"/><path d="M2.5 10h19M6 15h4"/>',
        'cash' => '<rect x="2.5" y="6" width="19" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 12h.1M18 12h.1"/>',
        'list' => '<path d="M8 6h13M8 12h13M8 18h13"/><path d="M3.5 6h.1M3.5 12h.1M3.5 18h.1"/>',
        'tablet' => '<rect x="4" y="2.5" width="16" height="19" rx="2"/><path d="M11 18.5h2"/>',
        'wifi-off' => '<path d="m2 3 20 18"/><path d="M8.5 15.5a5 5 0 0 1 7 0M5 12a10 10 0 0 1 3-2M19 12a10 10 0 0 0-5.5-2.8M12 19.5h.1"/>',
        'box' => '<path d="m12 3 9 4.5v9L12 21l-9-4.5v-9z"/><path d="M3.5 7.8 12 12l8.5-4.2M12 12v9"/>',
        'bell-ring' => '<path d="M6 8a6 6 0 1 1 12 0c0 7 3 7 3 9H3c0-2 3-2 3-9"/><path d="M10 21h4M2 5l2-2M22 5l-2-2"/>',
        'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
        'qr' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3h-3zM20 14v.1M14 20v.1M20 20h.1M20 17v.1"/>',
        'whatsapp' => '<path d="M20.5 11.7a8.4 8.4 0 0 1-12.4 7.4L3.5 20.5l1.4-4.5a8.4 8.4 0 1 1 15.6-4.3z"/><path d="M9 8.4c-.3.8-.2 2 .8 3.3s2.3 2 3.2 2.2 1.4-.2 1.7-.6c.3-.4.4-1.1.1-1.3l-1.6-1c-.2-.1-.5-.1-.7.1l-.5.6c-.6-.2-1.5-1-1.8-1.6l.5-.6c.2-.2.2-.5.1-.7l-1-1.5c-.2-.3-.5-.3-.8.1z"/>',
        'utensils-crossed' => '<path d="m4 3 16 18M20 3 4 21"/><path d="M7 3v6M5 3v4a2 2 0 0 0 4 0V3M17 3c-1 1-1.5 2.3-1.5 4 0 1.4.5 2.3 1.5 2.5V21"/>',
        'cooking-pot' => '<path d="M4 9h16v6a5 5 0 0 1-5 5H9a5 5 0 0 1-5-5z"/><path d="M7 9V7a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v2M2 12h2M20 12h2"/>',
        'moped' => '<circle cx="5.5" cy="17.5" r="3"/><circle cx="18.5" cy="17.5" r="3"/><path d="M8.5 17.5h6l3-8h-3M14 9.5l-2-3H9"/>',
        'smile' => '<circle cx="12" cy="12" r="9"/><path d="M8.5 14.5c1 1.3 2.1 2 3.5 2s2.5-.7 3.5-2M9 9.5h.1M15 9.5h.1"/>',
        'badge-check' => '<path d="m12 2 2.4 2.1 3.2-.2.6 3.1 2.7 1.7-1.3 2.9 1.3 2.9-2.7 1.7-.6 3.1-3.2-.2L12 22l-2.4-2.1-3.2.2-.6-3.1L3.1 15l1.3-2.9L3.1 9.2l2.7-1.7.6-3.1 3.2.2z"/><path d="m8.5 12 2.5 2.5 4.5-5"/>',
        'split' => '<path d="M4 6h4l8 12h4M4 18h4l8-12h4"/>',
    ];

    $body = $paths[$name] ?? $paths['info'];

    return '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
        . $body . '</svg>';
}

function brand_mark_svg(): string
{
    return '<svg viewBox="0 0 32 32" fill="none" aria-hidden="true" focusable="false">'
        . '<path d="M6 22h20a10 10 0 0 0-20 0z" fill="currentColor" opacity=".95"/>'
        . '<path d="M15 8c0-2 2-2 2-4M10 10c0-1.6 1.4-1.8 1.4-3.2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" opacity=".85"/>'
        . '<circle cx="16" cy="6.5" r="1.6" fill="currentColor"/>'
        . '<path d="M4 26h24" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/>'
        . '</svg>';
}

function stars_html(float|int $rating, string $class = 'stars'): string
{
    $full = max(0, min(5, (int) round($rating)));
    $out = '<span class="' . e($class) . '" aria-label="' . e((string) $full) . ' / 5">';
    for ($i = 1; $i <= 5; $i++) {
        $out .= '<span aria-hidden="true">' . ($i <= $full ? '★' : '☆') . '</span>';
    }
    return $out . '</span>';
}
