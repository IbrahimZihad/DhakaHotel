<?php
/**
 * Order persistence, payment recording and the owner notification queue.
 * Every write that touches money runs inside a database transaction.
 */

declare(strict_types=1);

const ORDER_TYPES = ['dine_in', 'takeaway', 'delivery'];
const ORDER_STATUSES = ['pending', 'confirmed', 'preparing', 'ready', 'out_for_delivery', 'delivered', 'cancelled'];
const PAYMENT_STATUSES = ['unpaid', 'partial', 'paid', 'refunded'];
const PAYMENT_METHODS = ['cash', 'cod', 'bkash', 'nagad', 'card', 'other'];

function generate_order_number(): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    for ($attempt = 0; $attempt < 10; $attempt++) {
        $code = '';
        for ($i = 0; $i < 7; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        $number = 'DH-' . $code;

        if (fetch_value('SELECT 1 FROM orders WHERE order_number = ?', [$number]) === null) {
            return $number;
        }
    }

    throw new RuntimeException('Could not allocate a unique order number.');
}

/**
 * Validate raw item input and build authoritative order lines from the DB.
 *
 * @param array $rawItems list of ['id'=>int,'quantity'=>int]
 * @return array{ok: bool, lines: array, errors: array<int, string>, unavailable: array<int, string>}
 */
function build_order_lines(array $rawItems): array
{
    $quantities = [];
    $errors = [];

    foreach ($rawItems as $raw) {
        if (!is_array($raw)) {
            continue;
        }

        $id = parse_int($raw['id'] ?? $raw['menu_item_id'] ?? 0);
        $quantity = parse_int($raw['quantity'] ?? 0);

        if ($id <= 0) {
            $errors[] = 'invalid_item';
            continue;
        }

        if (!v_quantity($quantity)) {
            $errors[] = 'invalid_quantity';
            continue;
        }

        $quantities[$id] = min(99, ($quantities[$id] ?? 0) + $quantity);
    }

    if ($quantities === []) {
        return ['ok' => false, 'lines' => [], 'errors' => ['empty_cart'], 'unavailable' => []];
    }

    $ids = array_keys($quantities);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $rows = fetch_all(
        "SELECT mi.id, mi.name_bn, mi.name_en, mi.price, mi.is_available, c.is_active AS category_active
         FROM menu_items mi
         JOIN categories c ON c.id = mi.category_id
         WHERE mi.id IN ($placeholders)",
        $ids
    );

    $byId = [];
    foreach ($rows as $row) {
        $byId[(int) $row['id']] = $row;
    }

    $lines = [];
    $unavailable = [];

    foreach ($quantities as $id => $quantity) {
        if (!isset($byId[$id]) || (int) $byId[$id]['is_available'] !== 1 || (int) $byId[$id]['category_active'] !== 1) {
            $name = $byId[$id]['name_en'] ?? ('#' . $id);
            $unavailable[] = $name;
            continue;
        }

        $unit = cents_from_decimal($byId[$id]['price']);
        $lines[] = [
            'menu_item_id' => $id,
            'name_bn' => $byId[$id]['name_bn'],
            'name_en' => $byId[$id]['name_en'],
            'unit_price_cents' => $unit,
            'quantity' => $quantity,
            'line_total_cents' => $unit * $quantity,
        ];
    }

    if ($unavailable !== []) {
        return ['ok' => false, 'lines' => $lines, 'errors' => ['unavailable_items'], 'unavailable' => $unavailable];
    }

    return ['ok' => $errors === [], 'lines' => $lines, 'errors' => $errors, 'unavailable' => []];
}

function upsert_customer(?string $name, ?string $phone, ?string $email): ?int
{
    $phone = $phone !== null ? normalize_bd_phone($phone) : '';
    if ($phone === '') {
        return null;
    }

    $existing = fetch_one('SELECT id, name, email FROM customers WHERE phone = ?', [$phone]);
    if ($existing !== null) {
        $updates = [];
        if ($name !== null && $name !== '' && $name !== $existing['name']) {
            $updates['name'] = $name;
        }
        if ($email !== null && $email !== '' && $email !== $existing['email']) {
            $updates['email'] = $email;
        }
        if ($updates !== []) {
            $updates['updated_at'] = date('Y-m-d H:i:s');
            update_row('customers', (int) $existing['id'], $updates);
        }

        return (int) $existing['id'];
    }

    return insert_row('customers', [
        'name' => $name !== null && $name !== '' ? $name : __('guest.customer'),
        'phone' => $phone,
        'email' => $email !== null && $email !== '' ? $email : null,
    ]);
}

/**
 * Persist a new order with its item snapshots.
 *
 * @param array{
 *   source?: string, order_type: string, table_number?: ?string,
 *   customer_name?: ?string, customer_phone?: ?string, customer_email?: ?string,
 *   delivery_address?: ?string, notes?: ?string, lines: array,
 *   discount_cents?: int, delivery_fee_cents?: int, tax_basis_points?: int,
 *   status?: string, payment_method?: ?string, created_by?: ?int,
 *   idempotency_key?: ?string
 * } $data
 * @return array{ok: bool, errors: array<int,string>, order_id?: int, order_number?: string, totals?: array, created?: bool}
 */
function create_order(array $data): array
{
    if ($data['lines'] === []) {
        return ['ok' => false, 'errors' => ['empty_cart']];
    }

    $key = $data['idempotency_key'] ?? null;
    if (is_string($key) && $key !== '') {
        $existing = fetch_one('SELECT id, order_number FROM orders WHERE idempotency_key = ?', [$key]);
        if ($existing !== null) {
            return [
                'ok' => true,
                'errors' => [],
                'order_id' => (int) $existing['id'],
                'order_number' => $existing['order_number'],
                'created' => false,
            ];
        }
    }

    $totals = compute_bill($data['lines'], [
        'discount_cents' => (int) ($data['discount_cents'] ?? 0),
        'delivery_fee_cents' => (int) ($data['delivery_fee_cents'] ?? 0),
        'tax_basis_points' => (int) ($data['tax_basis_points'] ?? tax_rate_basis_points()),
    ]);

    if (!$totals['ok']) {
        return ['ok' => false, 'errors' => $totals['errors']];
    }

    $orderType = in_array($data['order_type'] ?? '', ORDER_TYPES, true) ? $data['order_type'] : 'takeaway';
    $status = in_array($data['status'] ?? 'pending', ORDER_STATUSES, true) ? ($data['status'] ?? 'pending') : 'pending';
    $method = in_array($data['payment_method'] ?? '', PAYMENT_METHODS, true) ? $data['payment_method'] : null;

    $pdo = db();
    $pdo->beginTransaction();

    try {
        if (is_string($key) && $key !== '') {
            $existing = fetch_one('SELECT id, order_number FROM orders WHERE idempotency_key = ?', [$key]);
            if ($existing !== null) {
                $pdo->commit();
                return ['ok' => true, 'errors' => [], 'order_id' => (int) $existing['id'],
                        'order_number' => $existing['order_number'], 'created' => false];
            }
        }

        $customerId = upsert_customer(
            $data['customer_name'] ?? null,
            $data['customer_phone'] ?? null,
            $data['customer_email'] ?? null
        );

        $orderId = insert_row('orders', [
            'order_number' => generate_order_number(),
            'customer_id' => $customerId,
            'customer_name' => $data['customer_name'] ?? null,
            'customer_phone' => isset($data['customer_phone']) && $data['customer_phone'] !== ''
                ? normalize_bd_phone((string) $data['customer_phone'])
                : null,
            'delivery_address' => $data['delivery_address'] ?? null,
            'order_type' => $orderType,
            'table_number' => $data['table_number'] ?? null,
            'status' => $status,
            'payment_status' => 'unpaid',
            'subtotal' => decimal_from_cents($totals['subtotal']),
            'discount' => decimal_from_cents($totals['discount']),
            'delivery_fee' => decimal_from_cents($totals['delivery_fee']),
            'tax' => decimal_from_cents($totals['tax']),
            'grand_total' => decimal_from_cents($totals['grand_total']),
            'amount_paid' => '0.00',
            'payment_method' => $method,
            'notes' => $data['notes'] ?? null,
            'source' => in_array($data['source'] ?? '', ['web', 'pos'], true) ? $data['source'] : 'web',
            'created_by' => $data['created_by'] ?? null,
            'idempotency_key' => is_string($key) && $key !== '' ? $key : null,
        ]);

        foreach ($data['lines'] as $line) {
            insert_row('order_items', [
                'order_id' => $orderId,
                'menu_item_id' => $line['menu_item_id'],
                'name_bn' => $line['name_bn'],
                'name_en' => $line['name_en'],
                'unit_price' => decimal_from_cents((int) $line['unit_price_cents']),
                'quantity' => (int) $line['quantity'],
                'line_total' => decimal_from_cents((int) $line['line_total_cents']),
            ]);
        }

        insert_row('order_status_history', [
            'order_id' => $orderId,
            'status' => $status,
            'note' => null,
            'admin_id' => $data['created_by'] ?? null,
        ]);

        $orderNumber = (string) fetch_value('SELECT order_number FROM orders WHERE id = ?', [$orderId]);

        $pdo->commit();

        return [
            'ok' => true,
            'errors' => [],
            'order_id' => $orderId,
            'order_number' => $orderNumber,
            'totals' => $totals,
            'created' => true,
        ];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if ($exception instanceof PDOException && (string) $exception->getCode() === '23000' && is_string($key) && $key !== '') {
            $existing = fetch_one('SELECT id, order_number FROM orders WHERE idempotency_key = ?', [$key]);
            if ($existing !== null) {
                return ['ok' => true, 'errors' => [], 'order_id' => (int) $existing['id'],
                        'order_number' => $existing['order_number'], 'created' => false];
            }
        }

        storage_log('create_order failed: ' . $exception->getMessage());
        return ['ok' => false, 'errors' => ['order_failed']];
    }
}

function order_by_id(int $orderId): ?array
{
    return fetch_one('SELECT * FROM orders WHERE id = ?', [$orderId]);
}

function order_by_number(string $orderNumber): ?array
{
    return fetch_one('SELECT * FROM orders WHERE order_number = ?', [$orderNumber]);
}

function order_items(int $orderId): array
{
    return fetch_all('SELECT * FROM order_items WHERE order_id = ? ORDER BY id', [$orderId]);
}

function order_payments(int $orderId): array
{
    return fetch_all('SELECT * FROM payments WHERE order_id = ? ORDER BY id', [$orderId]);
}

function order_paid_cents(int $orderId): int
{
    $sum = fetch_value(
        "SELECT COALESCE(SUM(amount), 0) FROM payments WHERE order_id = ? AND status = 'confirmed'",
        [$orderId],
        '0.00'
    );

    return cents_from_decimal((string) $sum);
}

function payment_status_for(int $grandTotalCents, int $paidCents): string
{
    if ($paidCents <= 0) {
        return 'unpaid';
    }
    if ($paidCents >= $grandTotalCents) {
        return 'paid';
    }

    return 'partial';
}

function recompute_order_payment_state(int $orderId): string
{
    $order = order_by_id($orderId);
    if ($order === null) {
        return 'unpaid';
    }

    $paid = order_paid_cents($orderId);
    $status = payment_status_for(cents_from_decimal($order['grand_total']), $paid);

    q(
        'UPDATE orders SET amount_paid = ?, payment_status = ? WHERE id = ?',
        [decimal_from_cents($paid), $status, $orderId]
    );

    return $status;
}

/**
 * Record an explicitly confirmed payment against an order.
 * Idempotent: the same idempotency key never creates a second payment.
 *
 * @return array{ok: bool, error?: string, payment_id?: int, payment_status?: string, amount_paid?: int, balance?: int}
 */
function record_payment(int $orderId, array $payment): array
{
    $method = $payment['method'] ?? '';
    $amount = (int) ($payment['amount_cents'] ?? 0);
    $reference = trim((string) ($payment['reference'] ?? ''));
    $key = trim((string) ($payment['idempotency_key'] ?? ''));
    $adminId = isset($payment['admin_id']) ? (int) $payment['admin_id'] : null;

    if (!in_array($method, PAYMENT_METHODS, true)) {
        return ['ok' => false, 'error' => 'invalid_method'];
    }

    if ($amount <= 0) {
        return ['ok' => false, 'error' => 'invalid_amount'];
    }

    if (in_array($method, ['bkash', 'nagad', 'card', 'other'], true) && $reference === '') {
        return ['ok' => false, 'error' => 'reference_required'];
    }

    if ($key === '') {
        $key = 'pay-' . bin2hex(random_bytes(12));
    }

    $pdo = db();
    $pdo->beginTransaction();

    try {
        $order = fetch_one('SELECT * FROM orders WHERE id = ? FOR UPDATE', [$orderId]);
        if ($order === null) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'order_not_found'];
        }

        $duplicate = fetch_one('SELECT id FROM payments WHERE idempotency_key = ?', [$key]);
        if ($duplicate !== null) {
            $pdo->rollBack();
            return [
                'ok' => true,
                'payment_id' => (int) $duplicate['id'],
                'payment_status' => $order['payment_status'] === 'refunded' ? 'unpaid' : $order['payment_status'],
                'amount_paid' => cents_from_decimal($order['amount_paid']),
                'balance' => balance_due(cents_from_decimal($order['grand_total']), cents_from_decimal($order['amount_paid'])),
                'duplicate' => true,
            ];
        }

        if ($order['status'] === 'cancelled') {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'order_cancelled'];
        }

        $grandTotal = cents_from_decimal($order['grand_total']);
        $alreadyPaid = order_paid_cents($orderId);
        $outstanding = balance_due($grandTotal, $alreadyPaid);

        if ($amount > $outstanding) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'amount_exceeds_balance'];
        }

        $paymentId = insert_row('payments', [
            'order_id' => $orderId,
            'method' => $method,
            'amount' => decimal_from_cents($amount),
            'status' => 'confirmed',
            'reference' => $reference !== '' ? $reference : null,
            'recorded_by' => $adminId,
            'idempotency_key' => $key,
            'confirmed_at' => date('Y-m-d H:i:s'),
        ]);

        $newPaid = $alreadyPaid + $amount;
        $newStatus = payment_status_for($grandTotal, $newPaid);

        q(
            'UPDATE orders SET amount_paid = ?, payment_status = ?, payment_method = COALESCE(payment_method, ?) WHERE id = ?',
            [decimal_from_cents($newPaid), $newStatus, $method, $orderId]
        );

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        storage_log('record_payment failed: ' . $exception->getMessage());
        return ['ok' => false, 'error' => 'payment_failed'];
    }

    audit_log('payment.recorded', 'order', $orderId, ['payment_id' => $paymentId, 'amount' => decimal_from_cents($amount), 'method' => $method]);

    if ($newStatus === 'paid') {
        queue_payment_notification($orderId, $paymentId);
    }

    return [
        'ok' => true,
        'payment_id' => $paymentId,
        'payment_status' => $newStatus,
        'amount_paid' => $newPaid,
        'balance' => balance_due($grandTotal, $newPaid),
    ];
}

/** Explicitly reverse a recorded payment. Never invents a provider refund. */
function refund_payment(int $paymentId, ?int $adminId, string $reason): array
{
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $payment = fetch_one('SELECT * FROM payments WHERE id = ? FOR UPDATE', [$paymentId]);
        if ($payment === null) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'payment_not_found'];
        }

        if ($payment['status'] !== 'confirmed') {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'not_refundable'];
        }

        q(
            "UPDATE payments SET status = 'refunded', refund_reason = ? WHERE id = ?",
            [$reason !== '' ? $reason : null, $paymentId]
        );

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        storage_log('refund_payment failed: ' . $exception->getMessage());
        return ['ok' => false, 'error' => 'refund_failed'];
    }

    $status = recompute_order_payment_state((int) $payment['order_id']);
    audit_log('payment.refunded', 'payment', $paymentId, ['order_id' => (int) $payment['order_id'], 'reason' => $reason]);

    return ['ok' => true, 'order_id' => (int) $payment['order_id'], 'payment_status' => $status];
}

function allowed_status_transitions(string $status): array
{
    return match ($status) {
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['preparing', 'cancelled'],
        'preparing' => ['ready', 'cancelled'],
        'ready' => ['out_for_delivery', 'delivered', 'cancelled'],
        'out_for_delivery' => ['delivered', 'cancelled'],
        default => [],
    };
}

function update_order_status(int $orderId, string $newStatus, ?int $adminId, ?string $note = null): array
{
    $order = order_by_id($orderId);
    if ($order === null) {
        return ['ok' => false, 'error' => 'order_not_found'];
    }

    if (!in_array($newStatus, ORDER_STATUSES, true)) {
        return ['ok' => false, 'error' => 'invalid_status'];
    }

    if (!in_array($newStatus, allowed_status_transitions((string) $order['status']), true)) {
        return ['ok' => false, 'error' => 'invalid_transition'];
    }

    q('UPDATE orders SET status = ? WHERE id = ?', [$newStatus, $orderId]);
    insert_row('order_status_history', [
        'order_id' => $orderId,
        'status' => $newStatus,
        'note' => $note,
        'admin_id' => $adminId,
    ]);
    audit_log('order.status', 'order', $orderId, ['from' => $order['status'], 'to' => $newStatus]);

    return ['ok' => true];
}

/**
 * Queue the owner's "payment completed" email. Runs only after the payment
 * transaction has committed, and only once per payment event.
 */
function queue_payment_notification(int $orderId, int $paymentId): void
{
    try {
        $order = order_by_id($orderId);
        $payment = fetch_one('SELECT * FROM payments WHERE id = ?', [$paymentId]);
        if ($order === null || $payment === null) {
            return;
        }

        $recipient = trim((string) setting('owner_email', ''));
        if ($recipient === '') {
            $recipient = (string) config('app.owner.email', '');
        }

        if ($recipient === '' || !v_email($recipient)) {
            storage_log('Payment notification skipped: no owner email configured (order ' . $order['order_number'] . ').');
            return;
        }

        $lang = setting('notification_language', 'bn');
        $lang = in_array($lang, ['bn', 'en'], true) ? $lang : 'bn';

        $subject = trans_lang('email.payment_subject', $lang, ['ref' => $order['order_number']]);
        $body = build_payment_email_body($order, $payment, $lang);

        q(
            'INSERT IGNORE INTO email_notifications
                (notification_type, order_id, payment_id, recipient, language, subject, body, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            ['payment_completed', $orderId, $paymentId, $recipient, $lang, $subject, $body, 'queued']
        );
    } catch (Throwable $exception) {
        // A notification failure must never roll back or hide a real payment.
        storage_log('queue_payment_notification failed: ' . $exception->getMessage());
    }
}

function build_payment_email_body(array $order, array $payment, string $lang): string
{
    $items = order_items((int) $order['id']);
    $rows = '';
    foreach ($items as $item) {
        $name = $lang === 'bn' ? $item['name_bn'] : $item['name_en'];
        $rows .= '<tr>'
            . '<td style="padding:6px 0">' . e($name) . ' × ' . (int) $item['quantity'] . '</td>'
            . '<td style="padding:6px 0;text-align:right">' . e(money_from_decimal($item['line_total'])) . '</td>'
            . '</tr>';
    }

    $labels = [
        'bn' => [
            'title' => 'পেমেন্ট সম্পন্ন হয়েছে',
            'ref' => 'অর্ডার নম্বর',
            'total' => 'মোট বিল',
            'paid' => 'পরিশোধিত',
            'method' => 'পেমেন্ট মাধ্যম',
            'time' => 'সময়',
            'items' => 'আইটেম',
            'footer' => 'এটি স্বয়ংক্রিয়ভাবে পাঠানো একটি বিজ্ঞপ্তি।',
        ],
        'en' => [
            'title' => 'Payment completed',
            'ref' => 'Order reference',
            'total' => 'Bill total',
            'paid' => 'Amount paid',
            'method' => 'Payment method',
            'time' => 'Recorded at',
            'items' => 'Items',
            'footer' => 'This is an automated notification.',
        ],
    ];
    $l = $labels[$lang === 'bn' ? 'bn' : 'en'];
    $method = payment_method_label((string) $payment['method'], $lang);

    return '<div style="font-family:Arial,Helvetica,sans-serif;max-width:560px;margin:0 auto;color:#222">'
        . '<h2 style="margin:0 0 4px">' . e(restaurant_name_for($lang)) . '</h2>'
        . '<p style="margin:0 0 16px;color:#666">' . e($l['title']) . '</p>'
        . '<table style="width:100%;border-collapse:collapse;font-size:14px">'
        . '<tr><td style="padding:6px 0;color:#666">' . e($l['ref']) . '</td>'
        . '<td style="padding:6px 0;text-align:right;font-weight:bold">' . e($order['order_number']) . '</td></tr>'
        . '<tr><td style="padding:6px 0;color:#666">' . e($l['total']) . '</td>'
        . '<td style="padding:6px 0;text-align:right">' . e(money_from_decimal($order['grand_total'])) . '</td></tr>'
        . '<tr><td style="padding:6px 0;color:#666">' . e($l['paid']) . '</td>'
        . '<td style="padding:6px 0;text-align:right">' . e(money_from_decimal($order['amount_paid'])) . '</td></tr>'
        . '<tr><td style="padding:6px 0;color:#666">' . e($l['method']) . '</td>'
        . '<td style="padding:6px 0;text-align:right">' . e($method) . '</td></tr>'
        . '<tr><td style="padding:6px 0;color:#666">' . e($l['time']) . '</td>'
        . '<td style="padding:6px 0;text-align:right">' . e($payment['confirmed_at'] ?? $payment['created_at']) . '</td></tr>'
        . '</table>'
        . '<h3 style="font-size:14px;margin:20px 0 6px">' . e($l['items']) . '</h3>'
        . '<table style="width:100%;border-collapse:collapse;font-size:14px;border-top:1px solid #eee">' . $rows . '</table>'
        . '<p style="margin-top:20px;color:#999;font-size:12px">' . e($l['footer']) . '</p>'
        . '</div>';
}

function restaurant_name_for(string $lang): string
{
    $name = setting('name_' . $lang, '');

    return $name !== '' ? $name : restaurant_name();
}

function pending_pos_bills(?int $adminId = null, int $limit = 20): array
{
    $sql = "SELECT o.*, COUNT(oi.id) AS item_lines
            FROM orders o
            LEFT JOIN order_items oi ON oi.order_id = o.id
            WHERE o.source = 'pos' AND o.status = 'pending' AND o.payment_status = 'unpaid'";
    $params = [];

    if ($adminId !== null) {
        $sql .= ' AND o.created_by = ?';
        $params[] = $adminId;
    }

    $sql .= ' GROUP BY o.id ORDER BY o.updated_at DESC, o.id DESC LIMIT ' . max(1, $limit);

    return fetch_all($sql, $params);
}
