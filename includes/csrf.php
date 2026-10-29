<?php
/**
 * CSRF token helpers. Tokens live in the session and are compared with
 * hash_equals() on every state-changing request.
 */

declare(strict_types=1);

function csrf_token(): string
{
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_valid(?string $token): bool
{
    $expected = $_SESSION['_csrf_token'] ?? '';

    return is_string($token) && $token !== '' && $expected !== '' && hash_equals($expected, $token);
}

function csrf_from_request(): ?string
{
    $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

    return is_string($token) ? $token : null;
}

function require_csrf(): void
{
    if (csrf_valid(csrf_from_request())) {
        return;
    }

    if (request_wants_json()) {
        json_response(['ok' => false, 'error' => 'csrf'], 419);
    }

    http_response_code(419);
    echo '<!doctype html><meta charset="utf-8"><p style="font-family:system-ui;padding:24px">'
        . e(__('error.session_expired')) . '</p>';
    exit;
}

function request_wants_json(): bool
{
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    return str_contains($accept, 'application/json')
        || strtolower($requestedWith) === 'xmlhttprequest'
        || str_contains($contentType, 'application/json');
}
