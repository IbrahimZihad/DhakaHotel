<?php
/**
 * Cart API for the public site. Quantities only — all prices are recalculated
 * from the database before anything is shown to the customer.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';

if (!is_post()) {
    json_response(['ok' => false, 'message' => __('error.method_not_allowed')], 405);
}

require_csrf();

$action = (string) ($_POST['action'] ?? '');
$itemId = parse_int($_POST['item_id'] ?? 0);
$quantity = parse_int($_POST['quantity'] ?? 1);

switch ($action) {
    case 'add':
        if ($itemId <= 0 || !cart_add($itemId, max(1, min(99, $quantity)))) {
            json_response(['ok' => false, 'message' => __('menu.unavailable')], 422);
        }
        $message = __('common.added');
        break;

    case 'set':
        if ($itemId <= 0) {
            json_response(['ok' => false, 'message' => __('error.invalid_request')], 422);
        }
        cart_set($itemId, max(0, min(99, $quantity)));
        $message = __('common.saved');
        break;

    case 'remove':
        if ($itemId > 0) {
            cart_remove($itemId);
        }
        $message = __('common.remove');
        break;

    case 'clear':
        cart_clear();
        $message = __('cart.clear');
        break;

    default:
        json_response(['ok' => false, 'message' => __('error.invalid_request')], 422);
}

$summary = cart_summary();

json_response([
    'ok' => true,
    'message' => $message,
    'cart' => [
        'count' => cart_count(),
        'subtotal' => $summary['subtotal'],
        'subtotal_formatted' => money($summary['subtotal']),
        'grand_total' => $summary['grand_total'],
        'grand_total_formatted' => money($summary['grand_total']),
        'items' => array_map(static fn (array $item): array => [
            'id' => (int) $item['id'],
            'quantity' => (int) $item['quantity'],
            'line_total' => (int) $item['line_total_cents'],
        ], $summary['items']),
    ],
]);
