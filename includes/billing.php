<?php
/**
 * Single source of truth for bill arithmetic. All money is expressed in
 * integer poisha (aka cents) and never as floats.
 */

declare(strict_types=1);

/**
 * @param array<int, array{quantity: int, unit_price_cents: int}> $lines
 * @param array{discount_cents?: int, delivery_fee_cents?: int, tax_basis_points?: int} $options
 * @return array{ok: bool, errors: array<int, string>, subtotal: int, discount: int,
 *               delivery_fee: int, tax: int, grand_total: int, item_count: int}
 */
function compute_bill(array $lines, array $options = []): array
{
    $errors = [];
    $subtotal = 0;
    $itemCount = 0;

    if ($lines === []) {
        $errors[] = 'empty';
    }

    foreach ($lines as $line) {
        $quantity = (int) ($line['quantity'] ?? 0);
        $unit = (int) ($line['unit_price_cents'] ?? 0);

        if ($quantity < 1 || $quantity > 99) {
            $errors[] = 'quantity';
            continue;
        }

        if ($unit < 0) {
            $errors[] = 'price';
            continue;
        }

        $subtotal += $unit * $quantity;
        $itemCount += $quantity;
    }

    $discount = max(0, (int) ($options['discount_cents'] ?? 0));
    $delivery = max(0, (int) ($options['delivery_fee_cents'] ?? 0));
    $taxBasisPoints = max(0, (int) ($options['tax_basis_points'] ?? 0));

    if ($discount > $subtotal) {
        $errors[] = 'discount_exceeds_subtotal';
        $discount = $subtotal;
    }

    if ($taxBasisPoints > 10000) {
        $errors[] = 'tax_invalid';
        $taxBasisPoints = 0;
    }

    $taxable = $subtotal - $discount;
    $tax = (int) round($taxable * $taxBasisPoints / 10000);
    $grandTotal = $taxable + $delivery + $tax;

    return [
        'ok' => $errors === [],
        'errors' => array_values(array_unique($errors)),
        'subtotal' => $subtotal,
        'discount' => $discount,
        'delivery_fee' => $delivery,
        'tax' => $tax,
        'grand_total' => $grandTotal,
        'item_count' => $itemCount,
    ];
}

/** Amount still owed on a bill. */
function balance_due(int $grandTotal, int $paid): int
{
    return max(0, $grandTotal - $paid);
}

/** Change handed back to the customer; only positive when overpaid. */
function change_due(int $grandTotal, int $received): int
{
    return max(0, $received - $grandTotal);
}
