<?php
/**
 * POS endpoint. The server recomputes every price from the menu; the client
 * only sends item ids, quantities and bill options.
 *
 * Actions: save_pending | generate_bill | complete_payment
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_admin();

if (!is_post()) {
    json_response(['ok' => false, 'message' => __('error.method_not_allowed')], 405);
}

require_csrf();

if (!request_wants_json()) {
    json_response(['ok' => false, 'message' => __('error.invalid_request')], 400);
}

$body = json_input();
$action = (string) ($body['action'] ?? '');
if (!in_array($action, ['save_pending', 'generate_bill', 'complete_payment'], true)) {
    json_response(['ok' => false, 'message' => __('error.invalid_request')], 400);
}

$built = build_order_lines(is_array($body['items'] ?? null) ? $body['items'] : []);
if (!$built['ok']) {
    $message = __('pos.error_generic');
    if (in_array('empty_cart', $built['errors'], true)) {
        $message = __('pos.error_empty');
    } elseif (in_array('unavailable_items', $built['errors'], true)) {
        $message = __('pos.error_unavailable');
        if ($built['unavailable'] !== []) {
            $message .= ' ' . implode(', ', array_map('strval', $built['unavailable']));
        }
    }

    json_response(['ok' => false, 'message' => $message], 422);
}

$lines = $built['lines'];
$subtotal = 0;
foreach ($lines as $line) {
    $subtotal += (int) $line['line_total_cents'];
}

$discount = 0;
$discountRaw = trim((string) ($body['discount'] ?? ''));
if ($discountRaw !== '' && $discountRaw !== '0') {
    $parsed = parse_money_input($discountRaw);
    if ($parsed === null || $parsed > $subtotal) {
        json_response(['ok' => false, 'message' => __('pos.error_amount')], 422);
    }
    $discount = $parsed;
}

$orderType = in_array($body['order_type'] ?? '', ORDER_TYPES, true) ? (string) $body['order_type'] : 'dine_in';
$deliveryFee = $orderType === 'delivery' ? delivery_fee_cents() : 0;

$totals = compute_bill($lines, [
    'discount_cents' => $discount,
    'delivery_fee_cents' => $deliveryFee,
    'tax_basis_points' => tax_rate_basis_points(),
]);

if (!$totals['ok']) {
    json_response(['ok' => false, 'message' => __('pos.error_amount')], 422);
}

$tableNumber = $orderType === 'dine_in' ? clean_text($body['table_number'] ?? '', 20) : '';
$address = $orderType === 'delivery' ? clean_text($body['delivery_address'] ?? '', 500) : '';
$customerName = clean_text($body['customer_name'] ?? '', 100);
$customerPhone = clean_text($body['customer_phone'] ?? '', 20);
$notes = clean_text($body['notes'] ?? '', 500);
$method = in_array($body['payment_method'] ?? '', PAYMENT_METHODS, true) ? (string) $body['payment_method'] : '';
$reference = clean_text($body['reference'] ?? '', 120);
$resumeId = (int) ($body['resume_id'] ?? 0);
$clientKey = (string) ($body['idempotency_key'] ?? '');
$clientKey = preg_match('/^[a-f0-9]{32}$/', $clientKey) === 1 ? $clientKey : bin2hex(random_bytes(16));

if (in_array($method, ['bkash', 'nagad', 'card', 'other'], true) && $action === 'complete_payment' && $reference === '') {
    json_response(['ok' => false, 'message' => __('pos.error_reference')], 422);
}

// A retry after a lost response must not try to edit a bill that is already
// paid. The payment key is derived from the client's per-bill key.
if ($action === 'complete_payment') {
    $existingPayment = fetch_one(
        'SELECT order_id FROM payments WHERE idempotency_key = ?',
        ['pospay-' . $clientKey]
    );
    if ($existingPayment !== null) {
        $existingOrder = order_by_id((int) $existingPayment['order_id']);
        if ($existingOrder !== null) {
            $existingId = (int) $existingOrder['id'];
            $existingTotal = cents_from_decimal((string) $existingOrder['grand_total']);
            $existingPaid = order_paid_cents($existingId);
            $existingBalance = balance_due($existingTotal, $existingPaid);

            json_response([
                'ok' => true,
                'message' => $existingBalance > 0
                    ? __('pos.partial_payment')
                    : __('pos.payment_done', ['ref' => (string) $existingOrder['order_number']]),
                'paid' => $existingBalance === 0,
                'change_cents' => 0,
                'order' => [
                    'id' => $existingId,
                    'number' => (string) $existingOrder['order_number'],
                    'total_cents' => $existingTotal,
                    'paid_cents' => $existingPaid,
                    'balance_cents' => $existingBalance,
                    'payment_status' => (string) $existingOrder['payment_status'],
                    'status' => (string) $existingOrder['status'],
                ],
                'receipt_url' => url('admin/receipt.php?id=' . $existingId . '&paper=80'),
                'created' => false,
            ]);
        }
    }
}

/**
 * Update an editable POS draft in place. Only pending, fully unpaid POS orders
 * created by a POS session may be edited.
 */
$updateDraft = static function (int $orderId) use ($totals, $lines, $orderType, $tableNumber, $address, $customerName, $customerPhone, $notes): ?string {
    $order = order_by_id($orderId);
    if ($order === null
        || (string) $order['source'] !== 'pos'
        || (string) $order['status'] !== 'pending'
        || cents_from_decimal((string) $order['amount_paid']) > 0) {
        return __('pos.bill_locked');
    }

    $pdo = db();
    $pdo->beginTransaction();

    try {
        $linkedCustomer = upsert_customer(
            $customerName !== '' ? $customerName : null,
            $customerPhone !== '' ? normalize_bd_phone($customerPhone) : null,
            null
        );

        q('DELETE FROM order_items WHERE order_id = ?', [$orderId]);
        foreach ($lines as $line) {
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

        update_row('orders', $orderId, [
            'customer_id' => $linkedCustomer,
            'customer_name' => $customerName !== '' ? $customerName : null,
            'customer_phone' => $customerPhone !== '' ? normalize_bd_phone($customerPhone) : null,
            'delivery_address' => $address !== '' ? $address : null,
            'order_type' => $orderType,
            'table_number' => $tableNumber !== '' ? $tableNumber : null,
            'notes' => $notes !== '' ? $notes : null,
            'subtotal' => decimal_from_cents((int) $totals['subtotal']),
            'discount' => decimal_from_cents((int) $totals['discount']),
            'delivery_fee' => decimal_from_cents((int) $totals['delivery_fee']),
            'tax' => decimal_from_cents((int) $totals['tax']),
            'grand_total' => decimal_from_cents((int) $totals['grand_total']),
        ]);

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        storage_log('POS draft update failed: ' . $exception->getMessage());

        return __('pos.error_generic');
    }

    return null;
};

$orderId = 0;
$orderNumber = '';
$created = false;

if ($resumeId > 0) {
    $draftError = $updateDraft($resumeId);
    if ($draftError !== null) {
        json_response(['ok' => false, 'message' => $draftError], 409);
    }
    $orderId = $resumeId;
    $orderNumber = (string) fetch_value('SELECT order_number FROM orders WHERE id = ?', [$orderId], '');
} else {
    // Always start unpaid and pending; confirmation happens only after the
    // payment is recorded (or the bill is handed over as cash on delivery).
    $result = create_order([
        'source' => 'pos',
        'order_type' => $orderType,
        'table_number' => $tableNumber !== '' ? $tableNumber : null,
        'customer_name' => $customerName !== '' ? $customerName : null,
        'customer_phone' => $customerPhone !== '' ? $customerPhone : null,
        'delivery_address' => $address !== '' ? $address : null,
        'notes' => $notes !== '' ? $notes : null,
        'lines' => $lines,
        'discount_cents' => (int) $totals['discount'],
        'delivery_fee_cents' => (int) $totals['delivery_fee'],
        'tax_basis_points' => tax_rate_basis_points(),
        'status' => 'pending',
        'payment_method' => $action === 'complete_payment' && $method !== '' ? $method : null,
        'created_by' => admin_id(),
        'idempotency_key' => $clientKey,
    ]);

    if (!$result['ok']) {
        json_response(['ok' => false, 'message' => __('pos.error_generic')], 422);
    }

    $orderId = (int) $result['order_id'];
    $orderNumber = (string) $result['order_number'];
    $created = (bool) ($result['created'] ?? true);
}

if ($action === 'complete_payment') {
    /** Advance a pending POS bill to confirmed once money is handled. */
    $confirmIfPending = static function (int $id): string {
        $current = order_by_id($id);
        if ($current !== null && (string) $current['status'] === 'pending') {
            update_order_status($id, 'confirmed', admin_id(), 'POS payment');
            $current = order_by_id($id);
        }

        return (string) ($current['status'] ?? 'pending');
    };

    if ($method === 'cod') {
        q('UPDATE orders SET payment_method = ? WHERE id = ?', ['cod', $orderId]);
        audit_log('pos.cod_saved', 'order', $orderId);

        json_response([
            'ok' => true,
            'message' => __('pos.cod_note'),
            'paid' => false,
            'order' => [
                'id' => $orderId,
                'number' => $orderNumber,
                'total_cents' => (int) $totals['grand_total'],
                'paid_cents' => 0,
                'balance_cents' => (int) $totals['grand_total'],
                'payment_status' => 'unpaid',
                'status' => $confirmIfPending($orderId),
            ],
            'receipt_url' => url('admin/receipt.php?id=' . $orderId . '&paper=80'),
            'created' => $created,
        ]);
    }

    $receivedRaw = trim((string) ($body['amount_received'] ?? ''));
    $received = $receivedRaw === '' ? (int) $totals['grand_total'] : parse_money_input($receivedRaw);

    if ($received === null || $received <= 0) {
        json_response([
            'ok' => false,
            'message' => __('pos.error_amount'),
            'order' => ['id' => $orderId, 'number' => $orderNumber],
            'receipt_url' => url('admin/receipt.php?id=' . $orderId . '&paper=80'),
        ], 422);
    }

    $charge = in_array($method, ['bkash', 'nagad', 'card', 'other'], true)
        ? (int) $totals['grand_total']
        : min($received, (int) $totals['grand_total']);

    $payment = record_payment($orderId, [
        'method' => $method !== '' ? $method : 'cash',
        'amount_cents' => $charge,
        'reference' => $reference,
        'idempotency_key' => 'pospay-' . $clientKey,
        'admin_id' => admin_id(),
    ]);

    if (!$payment['ok']) {
        json_response([
            'ok' => false,
            'message' => __('pos.error_generic'),
            'order' => ['id' => $orderId, 'number' => $orderNumber],
            'receipt_url' => url('admin/receipt.php?id=' . $orderId . '&paper=80'),
        ], 422);
    }

    $paidCents = (int) ($payment['amount_paid'] ?? 0);
    $balance = (int) ($payment['balance'] ?? 0);
    $paymentStatus = (string) ($payment['payment_status'] ?? 'unpaid');
    $orderStatus = $confirmIfPending($orderId);

    json_response([
        'ok' => true,
        'message' => $balance > 0
            ? __('pos.partial_payment')
            : __('pos.payment_done', ['ref' => $orderNumber]),
        'paid' => $balance === 0,
        'change_cents' => $method === 'cash' || $method === ''
            ? change_due((int) $totals['grand_total'], $received)
            : 0,
        'order' => [
            'id' => $orderId,
            'number' => $orderNumber,
            'total_cents' => (int) $totals['grand_total'],
            'paid_cents' => $paidCents,
            'balance_cents' => $balance,
            'payment_status' => $paymentStatus,
            'status' => $orderStatus,
        ],
        'receipt_url' => url('admin/receipt.php?id=' . $orderId . '&paper=80'),
        'created' => $created,
    ]);
}

$message = $action === 'save_pending'
    ? __('pos.pending_saved', ['ref' => $orderNumber])
    : __('pos.bill_generated', ['ref' => $orderNumber]);
audit_log('pos.' . $action, 'order', $orderId);

json_response([
    'ok' => true,
    'message' => $message,
    'paid' => false,
    'order' => [
        'id' => $orderId,
        'number' => $orderNumber,
        'total_cents' => (int) $totals['grand_total'],
        'paid_cents' => 0,
        'balance_cents' => (int) $totals['grand_total'],
        'payment_status' => 'unpaid',
        'status' => 'pending',
    ],
    'receipt_url' => url('admin/receipt.php?id=' . $orderId . '&paper=80'),
    'created' => $created,
]);
