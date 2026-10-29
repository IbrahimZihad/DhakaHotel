<?php
/**
 * CLI worker for the owner email notification queue.
 *
 *   php scripts/process_email_queue.php
 *   php scripts/process_email_queue.php --limit=50
 *
 * Intended for cron/scheduled tasks; never required by the web request path.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.' . PHP_EOL);
}

require dirname(__DIR__) . '/includes/bootstrap.php';

$limit = 20;
foreach ($argv as $argument) {
    if (preg_match('/^--limit=(\d+)$/', $argument, $matches)) {
        $limit = max(1, min(200, (int) $matches[1]));
    }
}

$state = mailer_state();
if ($state !== 'ready') {
    echo "Mailer state: {$state}\n";
    echo $state === 'unconfigured'
        ? "Set MAIL_HOST, MAIL_PORT and MAIL_FROM_ADDRESS in .env before sending.\n"
        : "Install PHPMailer with: composer install\n";
    echo "Queued notifications were left untouched.\n";
    exit(2);
}

try {
    $stats = send_queued_notifications($limit);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Queue processing failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

echo sprintf(
    "Processed %d notification(s): %d sent, %d failed.\n",
    $stats['attempted'],
    $stats['sent'],
    $stats['failed']
);

exit($stats['failed'] > 0 ? 1 : 0);
