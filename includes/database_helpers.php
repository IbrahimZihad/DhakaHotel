<?php
/**
 * PDO connection, small query helpers and the restaurant settings cache.
 */

declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $cfg = config('db');
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $cfg['host'],
        $cfg['port'],
        $cfg['name'],
        $cfg['charset']
    );

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_STRINGIFY_FETCHES => false,
    ];

    if (!empty($cfg['ssl_ca'])) {
        $options[PDO::MYSQL_ATTR_SSL_CA] = $cfg['ssl_ca'];
    }

    try {
        $pdo = new PDO($dsn, $cfg['user'], $cfg['password'], $options);
        $pdo->exec("SET time_zone = '+06:00'");
    } catch (PDOException $exception) {
        storage_log('Database connection failed: ' . $exception->getMessage());

        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, "Database connection failed. Check your .env values.\n");
            exit(1);
        }

        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
        $hint = config('app.debug')
            ? '<p style="font-family:system-ui;color:#8a4b5c">' . e($exception->getMessage()) . '</p>'
            : '';
        echo '<!doctype html><html lang="bn"><meta charset="utf-8"><title>Database unavailable</title>'
            . '<div style="font-family:system-ui;max-width:560px;margin:12vh auto;padding:24px;border:1px solid #eee;border-radius:16px">'
            . '<h1 style="font-size:20px;margin:0 0 8px">Service temporarily unavailable / সেবা সাময়িকভাবে বন্ধ</h1>'
            . '<p style="color:#666">The restaurant database could not be reached. Please try again shortly.</p>'
            . $hint . '</div></html>';
        exit;
    }

    return $pdo;
}

function q(string $sql, array $params = []): PDOStatement
{
    $statement = db()->prepare($sql);
    $statement->execute($params);

    return $statement;
}

function fetch_one(string $sql, array $params = []): ?array
{
    $row = q($sql, $params)->fetch();

    return $row === false ? null : $row;
}

function fetch_all(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

function fetch_value(string $sql, array $params = [], mixed $default = null): mixed
{
    $value = q($sql, $params)->fetchColumn();

    return $value === false ? $default : $value;
}

function insert_row(string $table, array $data): int
{
    $columns = array_keys($data);
    $placeholders = array_map(static fn ($column) => ':' . $column, $columns);
    $sql = sprintf(
        'INSERT INTO `%s` (`%s`) VALUES (%s)',
        $table,
        implode('`, `', $columns),
        implode(', ', $placeholders)
    );

    q($sql, $data);

    return (int) db()->lastInsertId();
}

function update_row(string $table, int $id, array $data): void
{
    if ($data === []) {
        return;
    }

    $assignments = [];
    foreach (array_keys($data) as $column) {
        $assignments[] = sprintf('`%s` = :%s', $column, $column);
    }

    $data['__id'] = $id;
    $sql = sprintf('UPDATE `%s` SET %s WHERE id = :__id', $table, implode(', ', $assignments));
    q($sql, $data);
}

/* -------------------------------------------------------------------------
 * Restaurant settings (cached per request)
 * ---------------------------------------------------------------------- */

function settings(): array
{
    if (!isset($GLOBALS['_dh_settings'])) {
        $cache = [];
        try {
            foreach (db()->query('SELECT setting_key, setting_value FROM restaurant_settings') as $row) {
                $cache[$row['setting_key']] = $row['setting_value'];
            }
        } catch (PDOException $exception) {
            storage_log('Settings load failed: ' . $exception->getMessage());
        }
        $GLOBALS['_dh_settings'] = $cache;
    }

    return $GLOBALS['_dh_settings'];
}

function setting(string $key, ?string $default = null): ?string
{
    $all = settings();
    if (!array_key_exists($key, $all)) {
        return $default;
    }

    return $all[$key] === '' ? $default : $all[$key];
}

function set_setting(string $key, string $value): void
{
    q(
        'INSERT INTO restaurant_settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
        [$key, $value]
    );

    if (!isset($GLOBALS['_dh_settings'])) {
        settings();
    }
    $GLOBALS['_dh_settings'][$key] = $value;
}

function restaurant(string $field, string $lang = ''): string
{
    $lang = $lang !== '' ? $lang : current_lang();
    $value = setting($field . '_' . $lang, '');
    if ($value === '') {
        $value = setting($field . '_en', '');
    }
    if ($value === '') {
        $value = setting($field . '_bn', '');
    }

    return (string) ($value ?? '');
}

function restaurant_name(): string
{
    $name = restaurant('name');

    return $name !== '' ? $name : 'Dhaka Hotel';
}

function tax_rate_basis_points(): int
{
    return max(0, cents_from_decimal(setting('tax_percent', '0') ?? '0'));
}

function delivery_fee_cents(): int
{
    return max(0, cents_from_decimal(setting('delivery_fee', '0') ?? '0'));
}

function minimum_order_cents(): int
{
    return max(0, cents_from_decimal(setting('minimum_order', '0') ?? '0'));
}
