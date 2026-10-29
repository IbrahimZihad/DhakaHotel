<?php
/**
 * Repeatable demo seeder (CLI only).
 *
 *   php database/seed_runner.php seed
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.' . PHP_EOL);
}

require dirname(__DIR__) . '/includes/bootstrap.php';
require __DIR__ . '/seeders/demo_seeder.php';

if (($argv[1] ?? '') !== 'seed') {
    echo "Usage: php database/seed_runner.php seed\n";
    exit(1);
}

try {
    // Guard against running before migrations.
    $hasTables = fetch_value(
        "SELECT COUNT(*) FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name IN ('menu_items', 'orders', 'restaurant_settings')",
        [],
        0
    );

    if ((int) $hasTables < 3) {
        fwrite(STDERR, "Required tables are missing. Run: php database/migration_runner.php migrate\n");
        exit(1);
    }

    echo "Seeding demo data into database \"" . config('db.name') . "\" ...\n";
    foreach (seed_demo_data() as $line) {
        echo '  - ' . $line . PHP_EOL;
    }
    echo "Done. Demo credentials: admin@dhakahotel.demo / DhakaDemo@2026! (change before deploying)\n";
} catch (Throwable $exception) {
    fwrite(STDERR, 'Seeding failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
