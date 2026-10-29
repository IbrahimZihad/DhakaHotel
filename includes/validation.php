<?php
/**
 * Server-side input validation primitives (in addition to CSRF).
 */

declare(strict_types=1);

function v_required(mixed $value): bool
{
    return is_string($value) ? trim($value) !== '' : $value !== null;
}

function v_max_length(string $value, int $max): bool
{
    return mb_strlen(trim($value), 'UTF-8') <= $max;
}

function v_min_length(string $value, int $min): bool
{
    return mb_strlen(trim($value), 'UTF-8') >= $min;
}

function v_email(string $value): bool
{
    return filter_var($value, FILTER_VALIDATE_EMAIL) !== false && strlen($value) <= 190;
}

function v_quantity(mixed $value): bool
{
    if (is_int($value)) {
        return $value >= 1 && $value <= 99;
    }

    return is_string($value) && preg_match('/^\d{1,2}$/', $value) === 1
        && (int) $value >= 1 && (int) $value <= 99;
}

function parse_int(mixed $value, int $default = 0): int
{
    if (is_int($value)) {
        return $value;
    }

    if (is_string($value) && preg_match('/^-?\d+$/', trim($value))) {
        return (int) $value;
    }

    return $default;
}

function v_date(string $value): bool
{
    $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);

    return $date !== false && $date->format('Y-m-d') === $value;
}

function v_enum(mixed $value, array $allowed): bool
{
    return is_string($value) && in_array($value, $allowed, true);
}

/** Strip control characters and trim; keeps Bangla intact. */
function clean_text(mixed $value, int $max = 500): string
{
    if (!is_string($value)) {
        return '';
    }

    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
    $value = trim($value);

    if (mb_strlen($value, 'UTF-8') > $max) {
        $value = mb_substr($value, 0, $max, 'UTF-8');
    }

    return $value;
}

function slugify(string $text, string $fallback = 'item'): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    $text = trim($text, '-');

    return $text !== '' ? $text : $fallback;
}

/**
 * Validate and store an uploaded image. Content is inspected with
 * getimagesize()/finfo, never trusted from the client's MIME header.
 *
 * @return array{ok: bool, path?: string, error?: string}
 */
function store_menu_image(array $file, string $lang = 'en'): array
{
    $errors = [
        'bn' => [
            'upload' => 'ছবি আপলোড করা যায়নি। আবার চেষ্টা করুন।',
            'type' => 'শুধু JPG, PNG অথবা WebP ছবি আপলোড করা যাবে।',
            'size' => 'ছবির আকার ৩ মেগাবাইটের বেশি হতে পারবে না।',
            'invalid' => 'ফাইলটি সঠিক ছবি নয়।',
        ],
        'en' => [
            'upload' => 'The image could not be uploaded. Please try again.',
            'type' => 'Only JPG, PNG or WebP images are allowed.',
            'size' => 'Images must be 3 MB or smaller.',
            'invalid' => 'That file is not a valid image.',
        ],
    ];
    $t = $errors[$lang === 'bn' ? 'bn' : 'en'];

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'error' => 'no_file'];
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) {
        return ['ok' => false, 'error' => $t['upload']];
    }

    if (($file['size'] ?? 0) > 3 * 1024 * 1024) {
        return ['ok' => false, 'error' => $t['size']];
    }

    $info = @getimagesize($file['tmp_name']);
    if ($info === false) {
        return ['ok' => false, 'error' => $t['invalid']];
    }

    $allowed = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_WEBP => 'webp',
    ];

    if (!isset($allowed[$info[2]])) {
        return ['ok' => false, 'error' => $t['type']];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']) ?? '';
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        return ['ok' => false, 'error' => $t['type']];
    }

    $extension = $allowed[$info[2]];
    $filename = bin2hex(random_bytes(10)) . '.' . $extension;
    $directory = DH_ROOT . '/uploads/menu';

    if (!is_dir($directory) && !@mkdir($directory, 0775, true)) {
        return ['ok' => false, 'error' => $t['upload']];
    }

    $destination = $directory . '/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return ['ok' => false, 'error' => $t['upload']];
    }

    // Hard requirement: never leave anything executable in uploads/.
    @file_put_contents($directory . '/.htaccess', "php_flag engine off\nOptions -ExecCGI -Indexes\n<FilesMatch \"\\.(php|phtml|phar|cgi|pl)$\">\nRequire all denied\n</FilesMatch>\n");

    return ['ok' => true, 'path' => 'menu/' . $filename];
}

function remove_upload(?string $relativePath): void
{
    if ($relativePath === null || $relativePath === '') {
        return;
    }

    $path = realpath(DH_ROOT . '/uploads/' . ltrim($relativePath, '/'));
    $root = realpath(DH_ROOT . '/uploads');

    if ($path !== false && $root !== false && str_starts_with($path, $root) && is_file($path)) {
        @unlink($path);
    }
}

/**
 * Read a JSON request body for the POS/cart endpoints.
 */
function json_input(): array
{
    static $data = null;

    if ($data === null) {
        $raw = file_get_contents('php://input');
        $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
        $data = is_array($decoded) ? $decoded : [];
    }

    return $data;
}
