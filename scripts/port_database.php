<?php
/**
 * Copies content tables between the local XAMPP database and the one `.env`
 * points at, so a menu built and curated locally can move to a managed server
 * without rewriting it.
 *
 *   E:/xampp/php/php.exe scripts/port_database.php --source=local
 *   E:/xampp/php/php.exe scripts/port_database.php --source=env
 *
 * `.env` is always the destination when --source=local, and the source when
 * --source=env. The script refuses to run unless every destination table is
 * empty, so it can never overwrite rows that are already on the server; load an
 * edited menu into a clean database.
 *
 * It deliberately says nothing about schema: run
 * `php database/migration_runner.php migrate` against the destination first.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

require dirname(__DIR__) . '/includes/bootstrap.php';

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

// Root with no password is the XAMPP default; override for any other setup.
$localConfig = [
    'host' => (string) env('LOCAL_DB_HOST', '127.0.0.1'),
    'port' => (int) env('LOCAL_DB_PORT', 3306),
    'name' => (string) env('LOCAL_DB_NAME', 'dhaka_hotel_demo'),
    'user' => (string) env('LOCAL_DB_USER', 'root'),
    'password' => (string) env('LOCAL_DB_PASSWORD', ''),
    'charset' => (string) env('LOCAL_DB_CHARSET', 'utf8mb4'),
];

$sourceMode = '';
$tableList = '';
foreach (array_slice($argv, 1) as $argument) {
    if (preg_match('/^--source=(local|env)$/', $argument, $m) === 1) {
        $sourceMode = $m[1];
    } elseif (preg_match('/^--tables=(.+)$/', $argument, $m) === 1) {
        $tableList = $m[1];
    } else {
        fwrite(STDERR, "Unrecognised argument: $argument\n");
        exit(1);
    }
}

if ($sourceMode === '') {
    fwrite(STDERR, "Usage: php scripts/port_database.php --source=local|env [--tables=a,b,c]\n");
    exit(1);
}

$tables = $tableList === ''
    ? [
        'admins', 'restaurant_settings', 'categories', 'menu_items', 'customers',
        'orders', 'order_items', 'order_status_history', 'payments', 'reviews',
        'email_notifications',
    ]
    : array_values(array_filter(array_map('trim', explode(',', $tableList)), static fn (string $t): bool => $t !== ''));

foreach ($tables as $table) {
    if (preg_match('/^[a-z][a-z0-9_]{1,62}$/', $table) !== 1) {
        fwrite(STDERR, "Refusing to touch an unexpected table name: $table\n");
        exit(1);
    }
}

function connect(array $cfg, array $options): PDO
{
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $cfg['host'],
        $cfg['port'],
        $cfg['name'],
        $cfg['charset']
    );
    $pdo = new PDO($dsn, (string) $cfg['user'], (string) $cfg['password'], $options + db_ssl_options($cfg));
    $pdo->exec("SET time_zone = '+06:00'");
    return $pdo;
}

$from = $sourceMode === 'local' ? $localConfig : config('db');
$to = $sourceMode === 'local' ? config('db') : $localConfig;

try {
    $source = connect($from, $options);
    $target = connect($to, $options);
} catch (PDOException $exception) {
    fwrite(STDERR, 'Connection failed: ' . $exception->getMessage() . "\n");
    exit(1);
}

$sourceName = (string) $source->query('SELECT DATABASE()')->fetchColumn();
$targetName = (string) $target->query('SELECT DATABASE()')->fetchColumn();
printf("source: %s @ %s\ntarget: %s @ %s\n\n", $sourceName, $from['host'], $targetName, $to['host']);

if ($sourceName === $targetName && (string) $from['host'] === (string) $to['host']) {
    fwrite(STDERR, "Source and target are the same database; nothing to do.\n");
    exit(1);
}

$notEmpty = [];
$missing = [];
foreach ($tables as $table) {
    try {
        if ((int) $target->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn() > 0) {
            $notEmpty[] = $table;
        }
    } catch (PDOException $exception) {
        $missing[] = $table;
    }
}
if ($missing !== []) {
    fwrite(STDERR, 'Destination is missing table(s): ' . implode(', ', $missing) . ". Run migration_runner.php migrate first.\n");
    exit(1);
}
if ($notEmpty !== []) {
    fwrite(STDERR, 'Destination already has rows in: ' . implode(', ', $notEmpty) . "\nRefusing to overwrite. Port into a clean database.\n");
    exit(1);
}

$failures = [];
$target->exec('SET FOREIGN_KEY_CHECKS = 0');

foreach ($tables as $table) {
    $rows = $source->query('SELECT * FROM `' . $table . '`')->fetchAll();
    if ($rows === []) {
        printf("%-24s   0 rows (nothing to copy)\n", $table);
        continue;
    }

    $columns = array_keys($rows[0]);
    $statement = $target->prepare(
        'INSERT INTO `' . $table . '` (`' . implode('`, `', $columns) . '`) VALUES ('
        . implode(', ', array_fill(0, count($columns), '?')) . ')'
    );

    try {
        $target->beginTransaction();
        foreach ($rows as $row) {
            $bound = [];
            foreach ($columns as $column) {
                $value = $row[$column];
                $bound[] = is_bool($value) ? (int) $value : $value;
            }
            $statement->execute($bound);
        }
        $target->commit();
        printf("%-24s  %d rows copied\n", $table, count($rows));
    } catch (PDOException $exception) {
        if ($target->inTransaction()) {
            $target->rollBack();
        }
        $failures[] = $table;
        printf("%-24s  FAILED: %s\n", $table, $exception->getMessage());
    }
}

$target->exec('SET FOREIGN_KEY_CHECKS = 1');

echo "\nRow counts after the copy:\n";
foreach ($tables as $table) {
    $from_count = (int) $source->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
    $to_count = (int) $target->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
    printf(
        "%-24s source=%-5d target=%-5d %s\n",
        $table,
        $from_count,
        $to_count,
        $from_count === $to_count ? 'OK' : 'MISMATCH'
    );
}

if ($failures !== []) {
    fwrite(STDERR, "\nFailed tables: " . implode(', ', $failures) . "\n");
    exit(1);
}

echo "\nDone. If this database is new, also run: php database/seed_runner.php seed\n";
