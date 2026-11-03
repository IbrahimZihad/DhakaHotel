<?php
/**
 * Admin authentication, brute-force mitigation and audit logging.
 */

declare(strict_types=1);

const ADMIN_LOGIN_MAX_FAILURES = 5;
const ADMIN_LOGIN_WINDOW_MINUTES = 15;

/**
 * Hash of a throwaway secret, verified when no admin row matches so a missing
 * account costs the same CPU as a wrong password and cannot be listed. Its cost
 * has to track PASSWORD_DEFAULT, which is what every stored hash is rehashed to
 * on login (see attempt_admin_login).
 */
const ADMIN_TIMING_HASH = '$2y$12$1FdT8GKicLduvNOaQz0C4OoJIbTegyxHqfA2BuWrs7fTchGnRJqCy';

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
        'SELECT id, name, email, preferred_language, is_active, last_login_at, auth_version
         FROM admins WHERE id = ? AND is_active = 1',
        [$id]
    );

    // A password change raises auth_version, so sessions minted before it stop
    // authenticating while the one that made the change survives.
    if ($admin === null || (int) ($_SESSION['admin_auth_version'] ?? 1) !== (int) $admin['auth_version']) {
        unset($_SESSION['admin_id']);

        return $cached = null;
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
    $valid = password_verify($password, $admin !== null ? (string) $admin['password_hash'] : ADMIN_TIMING_HASH)
        && $admin !== null;

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
    $_SESSION['admin_auth_version'] = (int) ($admin['auth_version'] ?? 1);
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
