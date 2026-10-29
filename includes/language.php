<?php
/**
 * Bilingual (Bangla / English) translation layer.
 *
 * - Public visitors default to Bangla and may switch (cookie `dh_lang`).
 * - Admins keep an independent preference in the session and in `admins`.
 * - Missing keys fall back to English, then to the key itself.
 */

declare(strict_types=1);

function available_languages(): array
{
    return ['bn', 'en'];
}

function dictionary(string $lang): array
{
    static $cache = [];

    $lang = in_array($lang, available_languages(), true) ? $lang : 'en';
    if (!isset($cache[$lang])) {
        $cache[$lang] = require DH_ROOT . '/includes/languages/' . $lang . '.php';
    }

    return $cache[$lang];
}

function current_lang(): string
{
    static $lang = null;

    if ($lang !== null) {
        return $lang;
    }

    $default = (string) config('app.default_lang', 'bn');

    if (defined('DH_ADMIN_CONTEXT') && DH_ADMIN_CONTEXT) {
        $session = $_SESSION['admin_lang'] ?? null;
        if (is_string($session) && in_array($session, available_languages(), true)) {
            return $lang = $session;
        }

        return $lang = $default;
    }

    $cookie = $_COOKIE['dh_lang'] ?? null;
    if (is_string($cookie) && in_array($cookie, available_languages(), true)) {
        return $lang = $cookie;
    }

    return $lang = $default;
}

function is_bn(?string $lang = null): bool
{
    return ($lang ?? current_lang()) === 'bn';
}

/** Persist a language choice for the current context. Returns true when accepted. */
function set_language(string $lang, bool $persistAdmin = true): bool
{
    if (!in_array($lang, available_languages(), true)) {
        return false;
    }

    if (defined('DH_ADMIN_CONTEXT') && DH_ADMIN_CONTEXT) {
        $_SESSION['admin_lang'] = $lang;
        if ($persistAdmin && admin_check()) {
            q('UPDATE admins SET preferred_language = ? WHERE id = ?', [$lang, admin_id()]);
        }

        return true;
    }

    setcookie('dh_lang', $lang, [
        'expires' => time() + 60 * 60 * 24 * 365,
        'path' => config('app.base_path', '') !== '' ? config('app.base_path') : '/',
        'secure' => is_https(),
        'httponly' => false,
        'samesite' => 'Lax',
    ]);
    $_COOKIE['dh_lang'] = $lang;

    return true;
}

function trans(string $key, string $lang, array $params = []): string
{
    $dict = dictionary($lang);
    $value = $dict[$key] ?? null;

    if ($value === null) {
        $fallback = dictionary('en');
        $value = $fallback[$key] ?? $key;
    }

    if ($params !== []) {
        $replacements = [];
        foreach ($params as $name => $replacement) {
            $replacements[':' . $name] = (string) $replacement;
            $replacements['{' . $name . '}'] = (string) $replacement;
        }
        $value = strtr($value, $replacements);
    }

    return $value;
}

function trans_lang(string $key, string $lang, array $params = []): string
{
    return trans($key, $lang, $params);
}

function __(string $key, array $params = []): string
{
    return trans($key, current_lang(), $params);
}

function lang_url(string $lang): string
{
    $path = strtok($_SERVER['REQUEST_URI'] ?? '/', '?') ?: '/';
    $basePath = (string) config('app.base_path', '');
    if ($basePath !== '' && str_starts_with($path, $basePath)) {
        $path = substr($path, strlen($basePath));
    }

    parse_str((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY), $query);
    $query['lang'] = $lang;

    return url(ltrim($path, '/')) . '?' . http_build_query($query);
}

function order_status_label(string $status, ?string $lang = null): string
{
    return trans('status.' . $status, $lang ?? current_lang());
}

function payment_status_label(string $status, ?string $lang = null): string
{
    return trans('payment_status.' . $status, $lang ?? current_lang());
}

function order_type_label(string $type, ?string $lang = null): string
{
    return trans('order_type.' . $type, $lang ?? current_lang());
}

function payment_method_label(string $method, ?string $lang = null): string
{
    return trans('payment_method.' . $method, $lang ?? current_lang());
}

/** Pick the localized column from a bilingual DB row. */
function localized(array $row, string $field, ?string $lang = null): string
{
    $lang = $lang ?? current_lang();
    $value = $row[$field . '_' . $lang] ?? '';

    if (is_string($value) && trim($value) !== '') {
        return $value;
    }

    $other = $lang === 'bn' ? 'en' : 'bn';
    $fallback = $row[$field . '_' . $other] ?? '';

    return is_string($fallback) ? $fallback : '';
}

function item_name(array $row, ?string $lang = null): string
{
    return localized($row, 'name', $lang);
}

function item_description(array $row, ?string $lang = null): string
{
    return localized($row, 'description', $lang);
}

/** Data injected into pages for assets/js/language.js and pos.js. */
function i18n_payload(): array
{
    return [
        'lang' => current_lang(),
        'dict' => [
            'bn' => dictionary('bn'),
            'en' => dictionary('en'),
        ],
        'languages' => available_languages(),
    ];
}

function is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }

    return (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}
