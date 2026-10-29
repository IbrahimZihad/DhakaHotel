<?php
/**
 * Session-backed cart. Only menu item IDs and quantities are stored; every
 * price is re-read from the database whenever the cart is displayed.
 */

declare(strict_types=1);

function cart_raw(): array
{
    $cart = $_SESSION['cart'] ?? [];

    return is_array($cart) ? $cart : [];
}

function cart_set(int $menuItemId, int $quantity): void
{
    if ($menuItemId <= 0) {
        return;
    }

    $quantity = max(0, min(99, $quantity));
    if ($quantity === 0) {
        unset($_SESSION['cart'][$menuItemId]);
        return;
    }

    $_SESSION['cart'][$menuItemId] = $quantity;
}

function cart_add(int $menuItemId, int $quantity = 1): bool
{
    $item = fetch_one('SELECT id, is_available FROM menu_items WHERE id = ?', [$menuItemId]);
    if ($item === null || (int) $item['is_available'] !== 1) {
        return false;
    }

    $current = (int) (cart_raw()[$menuItemId] ?? 0);
    cart_set($menuItemId, $current + max(1, $quantity));

    return true;
}

function cart_remove(int $menuItemId): void
{
    unset($_SESSION['cart'][$menuItemId]);
}

function cart_clear(): void
{
    unset($_SESSION['cart']);
}

/**
 * Cart contents re-validated against the live menu.
 *
 * @return array{items: array<int, array>, removed: int, subtotal: int, quantity_total: int}
 */
function cart_details(): array
{
    $raw = cart_raw();
    if ($raw === []) {
        return ['items' => [], 'removed' => 0, 'subtotal' => 0, 'quantity_total' => 0];
    }

    $ids = array_map('intval', array_keys($raw));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $rows = fetch_all(
        "SELECT mi.id, mi.name_bn, mi.name_en, mi.price, mi.image_path, mi.is_available,
                c.id AS category_id, c.name_bn AS category_bn, c.name_en AS category_en
         FROM menu_items mi
         JOIN categories c ON c.id = mi.category_id
         WHERE mi.id IN ($placeholders) AND c.is_active = 1",
        $ids
    );

    $items = [];
    $subtotal = 0;
    $quantityTotal = 0;
    $removed = 0;
    $seen = [];

    foreach ($rows as $row) {
        $id = (int) $row['id'];
        $quantity = (int) ($raw[$id] ?? 0);
        if ($quantity < 1 || (int) $row['is_available'] !== 1) {
            $removed++;
            unset($raw[$id]);
            continue;
        }

        $unit = cents_from_decimal($row['price']);
        $lineTotal = $unit * $quantity;
        $items[] = $row + [
            'quantity' => $quantity,
            'unit_price_cents' => $unit,
            'line_total_cents' => $lineTotal,
        ];
        $subtotal += $lineTotal;
        $quantityTotal += $quantity;
        $seen[$id] = true;
    }

    // Any ID that no longer exists in the database is dropped silently.
    foreach ($raw as $id => $quantity) {
        if (!isset($seen[(int) $id])) {
            $removed++;
            unset($raw[$id]);
        }
    }

    $_SESSION['cart'] = array_map('intval', $raw);

    return [
        'items' => $items,
        'removed' => $removed,
        'subtotal' => $subtotal,
        'quantity_total' => $quantityTotal,
    ];
}

function cart_count(): int
{
    $count = 0;
    foreach (cart_raw() as $quantity) {
        $count += (int) $quantity;
    }

    return $count;
}

function cart_summary(): array
{
    $details = cart_details();
    $subtotal = $details['subtotal'];
    $fee = delivery_fee_cents();
    $taxBasisPoints = tax_rate_basis_points();
    $tax = (int) round($subtotal * $taxBasisPoints / 10000);

    return [
        'items' => $details['items'],
        'removed' => $details['removed'],
        'subtotal' => $subtotal,
        'delivery_fee' => $fee,
        'tax' => $tax,
        'tax_basis_points' => $taxBasisPoints,
        'grand_total' => $subtotal + $fee + $tax,
        'quantity_total' => $details['quantity_total'],
        'minimum_order' => minimum_order_cents(),
        'meets_minimum' => $details['subtotal'] >= minimum_order_cents(),
    ];
}
