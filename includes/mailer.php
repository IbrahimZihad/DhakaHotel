<?php
/**
 * PHPMailer bridge and the notification queue worker helpers.
 * Email is always queued after the payment commits; sending never touches
 * payment state.
 */

declare(strict_types=1);

function mailer_state(): string
{
    $mail = config('app.mail');

    if (trim((string) $mail['host']) === '' || trim((string) $mail['from_address']) === '') {
        return 'unconfigured';
    }

    if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        return 'missing_library';
    }

    return 'ready';
}

/**
 * Attempt one queued notification.
 *
 * @return array{ok: bool, error?: string}
 */
function send_notification(int $id): array
{
    $notification = fetch_one('SELECT * FROM email_notifications WHERE id = ?', [$id]);
    if ($notification === null) {
        return ['ok' => false, 'error' => 'not_found'];
    }

    if ($notification['status'] === 'sent') {
        return ['ok' => true];
    }

    $state = mailer_state();
    if ($state !== 'ready') {
        $message = match ($state) {
            'unconfigured' => 'SMTP is not configured (set MAIL_HOST and MAIL_FROM_ADDRESS).',
            default => 'PHPMailer is not installed (run composer install).',
        };
        mark_notification_failure((int) $notification['id'], (int) $notification['attempts'], $message);

        return ['ok' => false, 'error' => $message];
    }

    require_once DH_ROOT . '/vendor/autoload.php';

    $mail = config('app.mail');

    try {
        $mailer = new PHPMailer\PHPMailer\PHPMailer(true);
        $mailer->isSMTP();
        $mailer->Host = (string) $mail['host'];
        $mailer->Port = (int) $mail['port'];
        $mailer->CharSet = 'UTF-8';
        $mailer->Timeout = 12;

        if (trim((string) $mail['username']) !== '') {
            $mailer->SMTPAuth = true;
            $mailer->Username = (string) $mail['username'];
            $mailer->Password = (string) $mail['password'];
        }

        $encryption = strtolower((string) $mail['encryption']);
        if (in_array($encryption, ['tls', 'ssl'], true)) {
            $mailer->SMTPSecure = $encryption;
        }

        $mailer->setFrom((string) $mail['from_address'], (string) $mail['from_name']);
        $mailer->addAddress((string) $notification['recipient']);
        $mailer->isHTML(true);
        $mailer->Subject = (string) $notification['subject'];
        $mailer->Body = (string) $notification['body'];
        $mailer->AltBody = trim((string) strip_tags((string) $notification['body']));

        $mailer->send();

        q(
            "UPDATE email_notifications SET status = 'sent', sent_at = NOW(), last_error = NULL, attempts = attempts + 1 WHERE id = ?",
            [$id]
        );

        return ['ok' => true];
    } catch (Throwable $exception) {
        $message = mb_substr($exception->getMessage(), 0, 480, 'UTF-8');
        mark_notification_failure((int) $notification['id'], (int) $notification['attempts'], $message);
        storage_log('Email notification failed: ' . $message);

        return ['ok' => false, 'error' => $message];
    }
}

function mark_notification_failure(int $id, int $attempts, string $message): void
{
    $attempts++;
    $status = $attempts >= 5 ? 'failed' : 'queued';
    $nextRetry = date('Y-m-d H:i:s', time() + min(60, $attempts * 5) * 60);

    q(
        'UPDATE email_notifications
         SET attempts = ?, status = ?, last_error = ?, next_retry_at = ?
         WHERE id = ?',
        [$attempts, $status, mb_substr($message, 0, 480, 'UTF-8'), $status === 'failed' ? null : $nextRetry, $id]
    );
}

/**
 * Process the queue (used by scripts/process_email_queue.php).
 *
 * @return array{attempted: int, sent: int, failed: int}
 */
function send_queued_notifications(int $limit = 20): array
{
    $rows = fetch_all(
        "SELECT id FROM email_notifications
         WHERE status = 'queued' AND attempts < 5
           AND (next_retry_at IS NULL OR next_retry_at <= NOW())
         ORDER BY id ASC
         LIMIT " . max(1, $limit)
    );

    $stats = ['attempted' => 0, 'sent' => 0, 'failed' => 0];

    foreach ($rows as $row) {
        $stats['attempted']++;
        $result = send_notification((int) $row['id']);
        if ($result['ok']) {
            $stats['sent']++;
        } else {
            $stats['failed']++;
        }
    }

    return $stats;
}

function retry_notification(int $id): void
{
    q(
        "UPDATE email_notifications
         SET status = 'queued', attempts = 0, last_error = NULL, next_retry_at = NULL
         WHERE id = ? AND status <> 'sent'",
        [$id]
    );
}
