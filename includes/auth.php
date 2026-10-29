<?php
/**
 * Admin authentication, brute-force mitigation and audit logging.
 */

declare(strict_types=1);

const ADMIN_LOGIN_MAX_FAILURES = 5;
const ADMIN_LOGIN_WINDOW_MINUTES = 15;

function admin_user(): ?array
{
    static $cached = false;

    if ($cached !== false) {
        return $cached;
    }

    $id = (int) ($_SESSION['admin_id'] ?? 0);
    if ($id <= 0) {
        return $cached = null;
    }

    $admin = fetch_one(
        'SELECT id, name, email, preferred_language, is_active, last_login_at FROM admins WHERE id = ? AND is_active = 1',
        [$id]
    );

    if ($admin === null) {
        unset($_SESSION['admin_id']);
    }

    return $cached = $admin;
}

function admin_check(): bool
{
    return admin_user() !== null;
}

function require_admin(): void
{
    if (admin_check()) {
        return;
    }

    if (request_wants_json()) {
        json_response([
            'ok' => false,
            'error' => 'auth',
            'message' => __('error.session_expired'),
        ], 401);
    }

    $next = $_SERVER['REQUEST_URI'] ?? '';
    redirect('admin/login.php' . ($next !== '' ? '?next=' . rawurlencode($next) : ''));
}

function admin_id(): ?int
{
    $admin = admin_user();

    return $admin === null ? null : (int) $admin['id'];
}

function login_rate_limited(string $email, string $ip): bool
{
    $count = (int) fetch_value(
        'SELECT COUNT(*) FROM admin_login_attempts
         WHERE success = 0 AND attempted_at > (NOW() - INTERVAL ' . ADMIN_LOGIN_WINDOW_MINUTES . ' MINUTE)
           AND (email = ? OR ip_address = ?)',
        [mb_strtolower($email, 'UTF-8'), $ip],
        0
    );

    return $count >= ADMIN_LOGIN_MAX_FAILURES;
}

function log_login_attempt(string $email, string $ip, bool $success): void
{
    q(
        'INSERT INTO admin_login_attempts (email, ip_address, success) VALUES (?, ?, ?)',
        [mb_strtolower($email, 'UTF-8'), $ip, $success ? 1 : 0]
    );
}

/**
 * @return array{ok: bool, error?: string, admin?: array}
 */
function attempt_admin_login(string $email, string $password, string $ip): array
{
    $email = mb_strtolower(trim($email), 'UTF-8');

    if (login_rate_limited($email, $ip)) {
        return ['ok' => false, 'error' => 'rate_limited'];
    }

    $admin = fetch_one('SELECT * FROM admins WHERE email = ? AND is_active = 1', [$email]);
    $valid = $admin !== null && password_verify($password, (string) $admin['password_hash']);

    if (!$valid) {
        log_login_attempt($email, $ip, false);
        return ['ok' => false, 'error' => 'invalid'];
    }

    if (password_needs_rehash((string) $admin['password_hash'], PASSWORD_DEFAULT)) {
        q('UPDATE admins SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $admin['id']]);
    }

    log_login_attempt($email, $ip, true);
    session_regenerate_id(true);

    $_SESSION['admin_id'] = (int) $admin['id'];
    $_SESSION['admin_lang'] = in_array($admin['preferred_language'], ['bn', 'en'], true)
        ? $admin['preferred_language']
        : 'bn';

    q('UPDATE admins SET last_login_at = NOW() WHERE id = ?', [$admin['id']]);
    audit_log('admin.login', 'admin', (int) $admin['id']);

    return ['ok' => true, 'admin' => $admin];
}

function logout_admin(): void
{
    $id = admin_id();
    if ($id !== null) {
        audit_log('admin.logout', 'admin', $id);
    }

    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => (bool) $params['secure'],
            'httponly' => (bool) $params['httponly'],
            'samesite' => $params['samesite'] ?: 'Lax',
        ]);
    }
    session_destroy();
}

function audit_log(string $action, ?string $entity = null, ?int $entityId = null, array $details = []): void
{
    try {
        q(
            'INSERT INTO admin_audit_log (admin_id, action, entity, entity_id, details, ip_address)
             VALUES (?, ?, ?, ?, ?, ?)',
            [
                admin_id(),
                $action,
                $entity,
                $entityId,
                $details === [] ? null : json_encode($details, JSON_UNESCAPED_UNICODE),
                $_SERVER['REMOTE_ADDR'] ?? null,
            ]
        );
    } catch (PDOException $exception) {
        // Audit logging must never break the actual operation.
        storage_log('Audit log failed: ' . $exception->getMessage());
    }
}
