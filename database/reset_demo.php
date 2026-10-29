<?php
/**
 * Destructive demo reset (CLI only, explicit flag required).
 *
 *   php database/reset_demo.php --confirm
 *
 * Refuses to run against anything that is not clearly a local demo database.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.' . PHP_EOL);
}

require dirname(__DIR__) . '/includes/bootstrap.php';
require __DIR__ . '/seeders/demo_seeder.php';

$confirm = in_array('--confirm', $argv, true);

echo "Dhaka Hotel demo reset\n";
echo str_repeat('-', 60) . "\n";

if (!$confirm) {
    fwrite(STDERR, "Refusing to run: add the exact --confirm flag.\n");
    fwrite(STDERR, "This would DELETE all demo data in the configured database.\n");
    exit(1);
}

$appEnv = (string) config('app.env');
$databaseName = (string) config('db.name');
$allowed = (bool) config('app.demo_reset_allowed');

if (!in_array($appEnv, ['local', 'development', 'testing'], true)) {
    fwrite(STDERR, "Refusing to run: APP_ENV is '{$appEnv}', not a local/demo environment.\n");
    exit(1);
}

if ($databaseName !== 'dhaka_hotel_demo') {
    fwrite(STDERR, "Refusing to run: database name '{$databaseName}' is not the demo database 'dhaka_hotel_demo'.\n");
    exit(1);
}

if (str_contains(strtolower($databaseName), 'prod')) {
    fwrite(STDERR, "Refusing to run: the database name looks like production.\n");
    exit(1);
}

if (!$allowed) {
    fwrite(STDERR, "Refusing to run: DEMO_RESET_ALLOWED is not true in .env.\n");
    exit(1);
}

// Verify connectivity before printing the warning so failures stay clear.
$currentDb = fetch_value('SELECT DATABASE()', [], '');
if ($currentDb !== 'dhaka_hotel_demo') {
    fwrite(STDERR, "Refusing to run: connected database is '{$currentDb}', expected 'dhaka_hotel_demo'.\n");
    exit(1);
}

echo "WARNING: this deletes and recreates all demo content in '{$databaseName}':\n";
echo "  email_notifications, payments, order_status_history, order_items, orders,\n";
echo "  reviews, menu_items, categories, customers, admin_audit_log,\n";
echo "  admin_login_attempts, admins, restaurant_settings\n";

try {
    db()->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach ([
        'email_notifications', 'payments', 'order_status_history', 'order_items', 'orders',
        'reviews', 'menu_items', 'categories', 'customers', 'admin_audit_log',
        'admin_login_attempts', 'admins', 'restaurant_settings',
    ] as $table) {
        db()->exec('DELETE FROM `' . $table . '`');
        echo "  cleared {$table}\n";
    }
    db()->exec('SET FOREIGN_KEY_CHECKS = 1');

    echo "\nRe-seeding demo data ...\n";
    foreach (seed_demo_data() as $line) {
        echo '  - ' . $line . PHP_EOL;
    }

    echo "\nDemo reset complete. Log in with admin@dhakahotel.demo / DhakaDemo@2026!\n";
} catch (Throwable $exception) {
    db()->exec('SET FOREIGN_KEY_CHECKS = 1');
    fwrite(STDERR, 'Reset failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
