<?php
/**
 * Versioned migration runner (CLI only).
 *
 *   php database/migration_runner.php migrate
 *   php database/migration_runner.php status
 *   php database/migration_runner.php rollback
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.' . PHP_EOL);
}

require dirname(__DIR__) . '/includes/bootstrap.php';

$command = $argv[1] ?? 'help';

function migrations_dir(): string
{
    return __DIR__ . '/migrations';
}

/** @return array<int, array{version: string, name: string, path: string}> */
function load_migrations(): array
{
    $files = glob(migrations_dir() . '/*.sql') ?: [];
    sort($files, SORT_NATURAL);

    $migrations = [];
    foreach ($files as $file) {
        $base = basename($file);
        if (str_ends_with($base, '.down.sql')) {
            continue;
        }
        if (!preg_match('/^(\d{3})_([a-z0-9_]+)\.sql$/i', $base, $matches)) {
            continue;
        }
        $migrations[] = [
            'version' => $matches[1],
            'name' => $base,
            'path' => $file,
        ];
    }

    return $migrations;
}

function ensure_tracking_table(): void
{
    db()->exec(
        "CREATE TABLE IF NOT EXISTS schema_migrations (
            version VARCHAR(160) NOT NULL,
            filename VARCHAR(190) NOT NULL,
            batch INT NOT NULL DEFAULT 1,
            applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (version)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

/** @return array<string, array> keyed by filename */
function applied_migrations(): array
{
    $applied = [];
    foreach (fetch_all('SELECT * FROM schema_migrations ORDER BY filename') as $row) {
        $applied[$row['filename']] = $row;
    }

    return $applied;
}

/**
 * Split a SQL file into statements, respecting quotes and comments.
 *
 * @return array<int, string>
 */
function split_sql(string $sql): array
{
    $statements = [];
    $buffer = '';
    $length = strlen($sql);
    $inSingle = false;
    $inDouble = false;
    $inBacktick = false;
    $inLineComment = false;
    $inBlockComment = false;

    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $next = $i + 1 < $length ? $sql[$i + 1] : '';

        if ($inLineComment) {
            if ($char === "\n") {
                $inLineComment = false;
                $buffer .= "\n";
            }
            continue;
        }

        if ($inBlockComment) {
            if ($char === '*' && $next === '/') {
                $inBlockComment = false;
                $i++;
            }
            continue;
        }

        if ($inSingle) {
            $buffer .= $char;
            if ($char === '\\' && $next !== '') {
                $buffer .= $next;
                $i++;
                continue;
            }
            if ($char === "'") {
                if ($next === "'") {
                    $buffer .= $next;
                    $i++;
                    continue;
                }
                $inSingle = false;
            }
            continue;
        }

        if ($inDouble) {
            $buffer .= $char;
            if ($char === '\\' && $next !== '') {
                $buffer .= $next;
                $i++;
                continue;
            }
            if ($char === '"') {
                $inDouble = false;
            }
            continue;
        }

        if ($inBacktick) {
            $buffer .= $char;
            if ($char === '`') {
                $inBacktick = false;
            }
            continue;
        }

        if ($char === '-' && $next === '-') {
            $inLineComment = true;
            $i++;
            continue;
        }

        if ($char === '#') {
            $inLineComment = true;
            continue;
        }

        if ($char === '/' && $next === '*') {
            $inBlockComment = true;
            $i++;
            continue;
        }

        if ($char === "'") {
            $inSingle = true;
            $buffer .= $char;
            continue;
        }

        if ($char === '"') {
            $inDouble = true;
            $buffer .= $char;
            continue;
        }

        if ($char === '`') {
            $inBacktick = true;
            $buffer .= $char;
            continue;
        }

        if ($char === ';') {
            $statement = trim($buffer);
            if ($statement !== '') {
                $statements[] = $statement;
            }
            $buffer = '';
            continue;
        }

        $buffer .= $char;
    }

    $statement = trim($buffer);
    if ($statement !== '') {
        $statements[] = $statement;
    }

    return $statements;
}

function run_sql_file(string $path): void
{
    $sql = file_get_contents($path);
    if ($sql === false) {
        throw new RuntimeException('Could not read ' . basename($path));
    }

    foreach (split_sql($sql) as $statement) {
        db()->exec($statement);
    }
}

function next_batch_number(): int
{
    $max = fetch_value('SELECT COALESCE(MAX(batch), 0) FROM schema_migrations', [], 0);

    return (int) $max + 1;
}

function command_status(): void
{
    ensure_tracking_table();
    $applied = applied_migrations();
    $migrations = load_migrations();

    echo "Migration status\n";
    echo str_repeat('-', 72) . "\n";
    printf("%-40s %-10s %s\n", 'File', 'State', 'Applied at');

    foreach ($migrations as $migration) {
        $row = $applied[$migration['name']] ?? null;
        printf(
            "%-40s %-10s %s\n",
            $migration['name'],
            $row === null ? 'PENDING' : 'applied',
            $row['applied_at'] ?? '—'
        );
    }

    echo str_repeat('-', 72) . "\n";
    $pending = count(array_filter($migrations, static fn ($m) => !isset($applied[$m['name']])));
    echo count($migrations) . " migration file(s), {$pending} pending.\n";
}

function command_migrate(): void
{
    ensure_tracking_table();
    $applied = applied_migrations();
    $batch = next_batch_number();
    $ran = 0;

    foreach (load_migrations() as $migration) {
        if (isset($applied[$migration['name']])) {
            continue;
        }

        echo "Applying {$migration['name']} ... ";
        try {
            run_sql_file($migration['path']);
            q(
                'INSERT INTO schema_migrations (version, filename, batch) VALUES (?, ?, ?)',
                [$migration['version'], $migration['name'], $batch]
            );
            echo "done\n";
            $ran++;
        } catch (Throwable $exception) {
            echo "FAILED\n";
            fwrite(STDERR, 'Migration error: ' . $exception->getMessage() . PHP_EOL);
            fwrite(STDERR, "Applied migrations before the failure remain recorded. Fix the file and re-run migrate.\n");
            // MySQL DDL commits implicitly, so a failed statement may leave partial DDL behind.
            exit(1);
        }
    }

    echo $ran === 0 ? "Nothing to migrate — database is up to date.\n" : "Applied {$ran} migration(s).\n";
}

function command_rollback(): void
{
    ensure_tracking_table();
    $batch = fetch_value('SELECT MAX(batch) FROM schema_migrations', [], null);

    if ($batch === null) {
        echo "Nothing to roll back.\n";
        return;
    }

    $rows = fetch_all('SELECT * FROM schema_migrations WHERE batch = ? ORDER BY filename DESC', [$batch]);
    if ($rows === []) {
        echo "Nothing to roll back.\n";
        return;
    }

    // Refuse the whole batch up-front if any migration lacks a down file.
    foreach ($rows as $row) {
        $down = migrations_dir() . '/' . preg_replace('/\.sql$/', '.down.sql', $row['filename']);
        if (!is_file($down)) {
            fwrite(STDERR, "Cannot roll back safely: missing " . basename($down) . ".\n");
            fwrite(STDERR, "This batch is not reversible. No changes were made.\n");
            exit(1);
        }
    }

    foreach ($rows as $row) {
        $down = migrations_dir() . '/' . preg_replace('/\.sql$/', '.down.sql', $row['filename']);
        echo "Rolling back {$row['filename']} ... ";
        try {
            run_sql_file($down);
            q('DELETE FROM schema_migrations WHERE version = ?', [$row['version']]);
            echo "done\n";
        } catch (Throwable $exception) {
            echo "FAILED\n";
            fwrite(STDERR, 'Rollback error: ' . $exception->getMessage() . PHP_EOL);
            exit(1);
        }
    }

    echo "Rolled back batch {$batch}.\n";
}

try {
    match ($command) {
        'migrate' => command_migrate(),
        'status' => command_status(),
        'rollback' => command_rollback(),
        default => print("Usage:\n  php database/migration_runner.php migrate\n  php database/migration_runner.php status\n  php database/migration_runner.php rollback\n"),
    };
} catch (Throwable $exception) {
    fwrite(STDERR, 'Error: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
