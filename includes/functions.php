<?php
/**
 * Shared helper functions: escaping, configuration, URLs, formatting, flash
 * messages, WhatsApp links. No output happens in this file.
 */

declare(strict_types=1);

if (!defined('DH_ROOT')) {
    define('DH_ROOT', dirname(__DIR__));
}

function config(?string $key = null, mixed $default = null): mixed
{
    static $config = null;

    if ($config === null) {
        $config = [
            'app' => require DH_ROOT . '/config/app.php',
            'db' => require DH_ROOT . '/config/database.php',
        ];
    }

    if ($key === null) {
        return $config;
    }

    $value = $config;
    foreach (explode('.', $key) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }

    return $value;
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    return config('app.base_path', '') . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    $path = ltrim($path, '/');
    if (!str_starts_with($path, 'assets/')) {
        $path = 'assets/' . $path;
    }

    return url($path) . '?v=' . config('app.asset_version', '1');
}

function upload_url(?string $relativePath): ?string
{
    if ($relativePath === null || trim($relativePath) === '') {
        return null;
    }

    return url('uploads/' . ltrim($relativePath, '/'));
}

/**
 * Resolve a menu image, preferring owner uploads and falling back to the
 * bundled demo illustrations. Always returns a usable URL.
 */
function menu_image_url(?string $relativePath): string
{
    $relative = ltrim((string) $relativePath, '/');

    if ($relative !== '') {
        if (is_file(DH_ROOT . '/uploads/' . $relative)) {
            return url('uploads/' . $relative);
        }
        if (is_file(DH_ROOT . '/assets/images/' . $relative)) {
            return asset('assets/images/' . $relative);
        }
    }

    return asset('assets/images/menu/placeholder.svg');
}

function redirect(string $path, int $code = 302): never
{
    if (preg_match('#^https?://#i', $path)) {
        $target = $path;
    } else {
        $target = url($path);
    }

    if (!headers_sent()) {
        header('Location: ' . $target, true, $code);
    }
    exit;
}

/**
 * Only allow redirects inside the admin area, so `?next=` can never be used to
 * bounce users to another host.
 */
function safe_admin_next(?string $candidate): string
{
    if (!is_string($candidate) || $candidate === '') {
        return 'admin/index.php';
    }

    $path = parse_url($candidate, PHP_URL_PATH);
    if (!is_string($path) || !str_starts_with($path, '/admin/') || str_contains($path, '//')) {
        return 'admin/index.php';
    }

    return ltrim($path, '/');
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function input(string $key, mixed $default = null): mixed
{
    if (array_key_exists($key, $_POST)) {
        return $_POST[$key];
    }

    return $_GET[$key] ?? $default;
}

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function flash_messages(): array
{
    $messages = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);

    return is_array($messages) ? $messages : [];
}

function pull_flash(string $type): ?string
{
    $messages = flash_messages();
    $found = null;

    foreach ($messages as $message) {
        if ($message['type'] === $type && $found === null) {
            $found = $message['message'];
            continue;
        }
        $_SESSION['_flash'][] = $message;
    }

    return $found;
}

/* -------------------------------------------------------------------------
 * Money: every amount is handled as an integer number of poisha (cents) so
 * arithmetic never suffers float drift. DECIMAL(12,2) is used in MySQL.
 * ---------------------------------------------------------------------- */

function cents_from_decimal(string|int|float|null $value): int
{
    if ($value === null) {
        return 0;
    }

    $raw = trim((string) $value);
    if ($raw === '') {
        return 0;
    }

    if (!preg_match('/^(-?)(\d+)(?:\.(\d{1,2}))?$/', $raw, $m)) {
        return is_numeric($raw) ? (int) round((float) $raw * 100) : 0;
    }

    $cents = (int) $m[2] * 100 + (int) str_pad($m[3] ?? '0', 2, '0');

    return $m[1] === '-' ? -$cents : $cents;
}

function decimal_from_cents(int $cents): string
{
    $sign = $cents < 0 ? '-' : '';
    $abs = abs($cents);

    return $sign . intdiv($abs, 100) . '.' . str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);
}

/** Parse a positive money input (e.g. "220", "220.50"). Returns cents or null. */
function parse_money_input(mixed $value): ?int
{
    if (is_int($value) || is_float($value)) {
        $value = (string) $value;
    }

    if (!is_string($value)) {
        return null;
    }

    $value = trim(latin_digits($value));
    if ($value === '' || !preg_match('/^\d{1,8}(\.\d{1,2})?$/', $value)) {
        return null;
    }

    return cents_from_decimal($value);
}

function bn_digits(string $value): string
{
    $map = ['0' => '০', '1' => '১', '2' => '২', '3' => '৩', '4' => '৪',
            '5' => '৫', '6' => '৬', '7' => '৭', '8' => '৮', '9' => '৯'];

    return strtr($value, $map);
}

/** Accept Bangla numerals in money fields typed by staff. */
function latin_digits(string $value): string
{
    $map = ['০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4',
            '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9'];

    return strtr($value, $map);
}

function money(int $cents, bool $symbol = true): string
{
    return money_lang($cents, current_lang(), $symbol);
}

/** Same formatting rules but for an explicit language (receipts). */
function money_lang(int $cents, string $lang, bool $symbol = true): string
{
    $formatted = number_format(intdiv(abs($cents), 100));
    $fraction = abs($cents) % 100;

    if ($fraction > 0) {
        $formatted .= '.' . str_pad((string) $fraction, 2, '0', STR_PAD_LEFT);
    }

    $prefix = $cents < 0 ? '−' : '';
    if ($lang === 'bn') {
        return $prefix . ($symbol ? '৳' : '') . bn_digits($formatted);
    }

    return $prefix . ($symbol ? '৳' : '') . $formatted;
}

/** Format a raw decimal string from the DB (e.g. "220.00") for display. */
function money_from_decimal(string $decimal, bool $symbol = true): string
{
    return money(cents_from_decimal($decimal), $symbol);
}

function order_status_badge_class(string $status): string
{
    return match ($status) {
        'pending' => 'badge-warning',
        'confirmed', 'preparing', 'out_for_delivery' => 'badge-info',
        'ready', 'delivered' => 'badge-success',
        'cancelled' => 'badge-danger',
        default => 'badge-soft',
    };
}

function payment_status_badge_class(string $status): string
{
    return match ($status) {
        'unpaid' => 'badge-warning',
        'partial' => 'badge-info',
        'paid' => 'badge-success',
        'refunded' => 'badge-danger',
        default => 'badge-soft',
    };
}

function format_datetime(int|string|null $value, bool $withTime = true): string
{
    if ($value === null || $value === '') {
        return '—';
    }

    $ts = is_numeric($value) ? (int) $value : strtotime((string) $value);
    if ($ts === false) {
        return '—';
    }

    if (class_exists('IntlDateFormatter')) {
        try {
            $formatter = new IntlDateFormatter(
                current_lang() === 'bn' ? 'bn_BD' : 'en_US',
                IntlDateFormatter::MEDIUM,
                $withTime ? IntlDateFormatter::SHORT : IntlDateFormatter::NONE,
                date_default_timezone_get()
            );
            $result = $formatter->format($ts);
            if ($result !== false) {
                return $result;
            }
        } catch (Throwable) {
            // fall through to the manual format
        }
    }

    $date = date('d M Y', $ts);
    if (!$withTime) {
        return $date;
    }

    return $date . ', ' . date('g:i A', $ts);
}

function format_date(int|string|null $value): string
{
    return format_datetime($value, false);
}

/* -------------------------------------------------------------------------
 * Uploads & misc
 * ---------------------------------------------------------------------- */

function storage_log(string $message): void
{
    $dir = DH_ROOT . '/storage/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    error_log('[' . date('c') . '] ' . $message . PHP_EOL, 3, $dir . '/app.log');
}

function normalize_phone_digits(string $phone): string
{
    return preg_replace('/\D+/', '', $phone) ?? '';
}

/** Normalise a Bangladeshi phone number to canonical local form 01XXXXXXXXX. */
function normalize_bd_phone(string $phone): string
{
    $digits = normalize_phone_digits($phone);

    if (str_starts_with($digits, '880') && strlen($digits) === 13) {
        $digits = '0' . substr($digits, 3);
    } elseif (str_starts_with($digits, '88') && strlen($digits) === 12) {
        $digits = '0' . substr($digits, 2);
    } elseif (strlen($digits) === 10 && str_starts_with($digits, '1')) {
        $digits = '0' . $digits;
    }

    return $digits;
}

function is_valid_bd_phone(string $phone): bool
{
    return (bool) preg_match('/^01[3-9]\d{8}$/', normalize_bd_phone($phone));
}

/** Return the owner WhatsApp number as international digits, or '' if unset. */
function whatsapp_number(): string
{
    $number = trim((string) setting('whatsapp_number', ''));
    if ($number === '') {
        $number = (string) config('app.owner.whatsapp', '');
    }

    $digits = normalize_phone_digits($number);
    if ($digits === '') {
        return '';
    }

    if (str_starts_with($digits, '880')) {
        return $digits;
    }
    if (str_starts_with($digits, '0')) {
        return '880' . substr($digits, 1);
    }
    if (str_starts_with($digits, '1') && strlen($digits) === 10) {
        return '880' . $digits;
    }

    return $digits;
}

function whatsapp_link(string $message = ''): ?string
{
    $number = whatsapp_number();
    if ($number === '') {
        return null;
    }

    $link = 'https://wa.me/' . $number;
    if ($message !== '') {
        $link .= '?text=' . rawurlencode($message);
    }

    return $link;
}

function whatsapp_order_message(?string $orderNumber, string $lang): string
{
    if ($lang === 'bn') {
        $message = 'আসসালামু আলাইকুম, আমি আমার অর্ডার সম্পর্কে জানতে চাই।';
        if ($orderNumber !== null) {
            $message .= ' অর্ডার নম্বর: ' . $orderNumber;
        }

        return $message;
    }

    $message = 'Hello, I would like to ask about my order.';
    if ($orderNumber !== null) {
        $message .= ' Order reference: ' . $orderNumber;
    }

    return $message;
}

function whatsapp_button_markup(?string $orderNumber = null, string $variant = 'ghost'): string
{
    $link = whatsapp_link(whatsapp_order_message($orderNumber, current_lang()));
    if ($link === null) {
        return '';
    }

    $label = $orderNumber === null ? __('nav.whatsapp') : __('order.ask_whatsapp');

    return '<a class="btn btn--' . e($variant) . ' btn--wa" href="' . e($link) . '" target="_blank" rel="noopener">'
        . wa_icon() . '<span>' . e($label) . '</span></a>';
}

function wa_icon(): string
{
    return '<svg class="icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">'
        . '<path d="M12.04 2C6.6 2 2.2 6.4 2.2 11.84c0 1.74.46 3.44 1.32 4.94L2 22l5.36-1.4c1.44.78 3.06 1.2 4.68 1.2h.01c5.43 0 9.84-4.4 9.84-9.84C21.89 6.4 17.47 2 12.04 2Zm0 17.96h-.01a8.1 8.1 0 0 1-4.13-1.13l-.3-.18-3.06.8.82-3-.2-.3a8.07 8.07 0 0 1-1.24-4.3c0-4.46 3.63-8.09 8.1-8.09a8.1 8.1 0 0 1 8.08 8.1c0 4.47-3.63 8.1-8.06 8.1Zm4.44-6.06c-.24-.12-1.44-.71-1.66-.79-.22-.08-.38-.12-.54.12-.16.24-.62.79-.76.95-.14.16-.28.18-.52.06-.24-.12-1.02-.38-1.94-1.2-.72-.64-1.2-1.42-1.34-1.66-.14-.24-.02-.37.1-.49.11-.11.24-.28.36-.42.12-.14.16-.24.24-.4.08-.16.04-.3-.02-.42-.06-.12-.54-1.3-.74-1.78-.2-.47-.39-.4-.54-.41h-.46c-.16 0-.42.06-.64.3-.22.24-.84.82-.84 2 0 1.18.86 2.32.98 2.48.12.16 1.68 2.67 4.08 3.65.57.24 1.02.39 1.37.5.58.18 1.1.16 1.51.1.46-.07 1.44-.59 1.64-1.16.2-.57.2-1.06.14-1.16-.06-.1-.22-.16-.46-.28Z"/>'
        . '</svg>';
}

/* -------------------------------------------------------------------------
 * Pagination
 * ---------------------------------------------------------------------- */

function current_page_number(): int
{
    $page = (int) ($_GET['page'] ?? 1);

    return max(1, $page);
}

function paginate(int $total, int $perPage, int $page): array
{
    $perPage = max(1, $perPage);
    $pages = max(1, (int) ceil($total / $perPage));
    $page = min(max(1, $page), $pages);

    return [
        'page' => $page,
        'pages' => $pages,
        'per_page' => $perPage,
        'offset' => ($page - 1) * $perPage,
        'total' => $total,
    ];
}

function query_string_with(array $overrides): string
{
    $query = $_GET;
    foreach ($overrides as $key => $value) {
        if ($value === null || $value === '') {
            unset($query[$key]);
        } else {
            $query[$key] = $value;
        }
    }

    $query = array_filter($query, static fn ($value) => $value !== null && $value !== '');
    $string = http_build_query($query);

    return $string === '' ? '' : '?' . $string;
}
