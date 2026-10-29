<?php
/**
 * Printable receipt for any order. Opening this page never changes payment
 * state; only recorded payments are reflected.
 *
 * Query: id, paper=58|80|a4, rlang=bn|en
 */

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

$orderId = (int) ($_GET['id'] ?? 0);
$order = $orderId > 0 ? order_by_id($orderId) : null;

if ($order === null) {
    http_response_code(404);
    echo '<!doctype html><meta charset="utf-8"><p style="font-family:system-ui;padding:24px">'
        . e(__('admin.bill_not_found')) . '</p>';
    exit;
}

$paper = (string) ($_GET['paper'] ?? '80');
$paper = in_array($paper, ['58', '80', 'a4'], true) ? $paper : '80';

$rlang = (string) ($_GET['rlang'] ?? '');
$rlang = in_array($rlang, available_languages(), true) ? $rlang : current_lang();

$items = order_items($orderId);
$grandCents = cents_from_decimal((string) $order['grand_total']);
$paidCents = order_paid_cents($orderId);
$balanceCents = balance_due($grandCents, $paidCents);

$name = restaurant_name_for($rlang);
$address = setting('address_' . $rlang, '') ?: setting('address_en', '');
$phone = (string) setting('phone', '');
$footer = setting('receipt_footer_' . $rlang, '') ?: setting('receipt_footer_en', '');

$receiptDate = static function (string $value) use ($rlang): string {
    $ts = strtotime($value);
    if ($ts === false) {
        return '—';
    }
    $formatted = date('d/m/Y g:i A', $ts);

    return $rlang === 'bn' ? bn_digits($formatted) : $formatted;
};

$baseQuery = 'admin/receipt.php?id=' . $orderId;
$paperLabels = [
    '58' => trans('receipt.paper_58', $rlang),
    '80' => trans('receipt.paper_80', $rlang),
    'a4' => trans('receipt.paper_a4', $rlang),
];
?><!doctype html>
<html lang="<?= e($rlang) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(trans('receipt.bill', $rlang) . ' ' . $order['order_number']) ?></title>
<meta name="robots" content="noindex, nofollow">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans+Bengali:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="<?= e(asset('assets/css/print.css')) ?>">
</head>
<body>
<div class="receipt-toolbar no-print">
  <button type="button" data-print><?= e(trans('receipt.print_now', $rlang)) ?></button>
  <div class="paper-links" role="group" aria-label="<?= e(trans('receipt.paper', $rlang)) ?>">
    <?php foreach ($paperLabels as $size => $label): ?>
    <a class="<?= $paper === $size ? 'active' : '' ?>" href="<?= e(url($baseQuery . '&paper=' . $size . '&rlang=' . $rlang)) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
  </div>
  <div class="lang-links" role="group" aria-label="<?= e(trans('common.language', $rlang)) ?>">
    <a class="<?= $rlang === 'bn' ? 'active' : '' ?>" href="<?= e(url($baseQuery . '&paper=' . $paper . '&rlang=bn')) ?>" lang="bn">বাংলা</a>
    <a class="<?= $rlang === 'en' ? 'active' : '' ?>" href="<?= e(url($baseQuery . '&paper=' . $paper . '&rlang=en')) ?>" lang="en">English</a>
  </div>
  <a class="secondary" href="<?= e(url('admin/order-details.php?id=' . $orderId)) ?>"><?= e(trans('common.back', $rlang)) ?></a>
</div>

<article class="receipt" data-paper="<?= e($paper) ?>">
  <div class="r-center">
    <div class="r-brand"><?= e($name) ?></div>
    <?php if ($address !== ''): ?><div class="r-sub"><?= e($address) ?></div><?php endif; ?>
    <?php if ($phone !== ''): ?><div class="r-sub"><?= e($phone) ?></div><?php endif; ?>
  </div>

  <hr class="solid">

  <div class="r-row r-meta"><span><?= e(trans('receipt.bill', $rlang)) ?></span><span><?= e($order['order_number']) ?></span></div>
  <div class="r-row r-meta"><span><?= e(trans('receipt.date', $rlang)) ?></span><span><?= e($receiptDate((string) $order['created_at'])) ?></span></div>
  <div class="r-row r-meta"><span><?= e(trans('receipt.order_type', $rlang)) ?></span><span><?= e(trans('order_type.' . $order['order_type'], $rlang)) ?></span></div>
  <?php if (!empty($order['table_number'])): ?>
  <div class="r-row r-meta"><span><?= e(trans('receipt.table', $rlang)) ?></span><span><?= e($order['table_number']) ?></span></div>
  <?php endif; ?>
  <?php if (!empty($order['customer_name'])): ?>
  <div class="r-row r-meta"><span><?= e(trans('receipt.customer', $rlang)) ?></span><span><?= e($order['customer_name']) ?></span></div>
  <?php endif; ?>
  <?php if (!empty($order['customer_phone'])): ?>
  <div class="r-row r-meta"><span><?= e(trans('receipt.phone', $rlang)) ?></span><span><?= e($order['customer_phone']) ?></span></div>
  <?php endif; ?>
  <?php if (!empty($order['delivery_address'])): ?>
  <div class="r-row r-meta"><span><?= e(trans('receipt.address', $rlang)) ?></span><span><?= e($order['delivery_address']) ?></span></div>
  <?php endif; ?>

  <hr>

  <table class="r-line-items">
    <thead>
      <tr>
        <th><?= e(trans('receipt.item', $rlang)) ?></th>
        <th class="r-qty"><?= e(trans('receipt.qty', $rlang)) ?></th>
        <th class="r-amt"><?= e(trans('receipt.line_total', $rlang)) ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($items as $item): ?>
      <tr>
        <td class="r-item-name"><?= e(localized($item, 'name', $rlang)) ?></td>
        <td class="r-qty"><?= e($rlang === 'bn' ? bn_digits((string) (int) $item['quantity']) : (string) (int) $item['quantity']) ?></td>
        <td class="r-amt"><?= e(money_lang(cents_from_decimal((string) $item['line_total']), $rlang)) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <hr>

  <div class="r-row"><span><?= e(trans('receipt.subtotal', $rlang)) ?></span><span><?= e(money_lang(cents_from_decimal((string) $order['subtotal']), $rlang)) ?></span></div>
  <?php if (cents_from_decimal((string) $order['discount']) > 0): ?>
  <div class="r-row"><span><?= e(trans('receipt.discount', $rlang)) ?></span><span>−<?= e(money_lang(cents_from_decimal((string) $order['discount']), $rlang)) ?></span></div>
  <?php endif; ?>
  <?php if (cents_from_decimal((string) $order['delivery_fee']) > 0): ?>
  <div class="r-row"><span><?= e(trans('receipt.delivery', $rlang)) ?></span><span><?= e(money_lang(cents_from_decimal((string) $order['delivery_fee']), $rlang)) ?></span></div>
  <?php endif; ?>
  <?php if (cents_from_decimal((string) $order['tax']) > 0): ?>
  <div class="r-row"><span><?= e(trans('receipt.tax', $rlang)) ?></span><span><?= e(money_lang(cents_from_decimal((string) $order['tax']), $rlang)) ?></span></div>
  <?php endif; ?>

  <table>
    <tbody>
      <tr class="r-total-row">
        <td><?= e(trans('receipt.total', $rlang)) ?></td>
        <td class="r-amt"><?= e(money_lang($grandCents, $rlang)) ?></td>
      </tr>
    </tbody>
  </table>

  <hr>

  <div class="r-row"><span><?= e(trans('receipt.paid', $rlang)) ?></span><span><?= e(money_lang($paidCents, $rlang)) ?></span></div>
  <div class="r-row"><span><?= e(trans('receipt.balance', $rlang)) ?></span><span><?= e(money_lang($balanceCents, $rlang)) ?></span></div>
  <?php if ($order['payment_method'] !== null): ?>
  <div class="r-row"><span><?= e(trans('receipt.method', $rlang)) ?></span><span><?= e(trans('payment_method.' . $order['payment_method'], $rlang)) ?></span></div>
  <?php endif; ?>
  <div class="r-row"><span><?= e(trans('receipt.payment_status', $rlang)) ?></span><span class="r-status"><?= e(trans('payment_status.' . $order['payment_status'], $rlang)) ?></span></div>

  <?php if ($balanceCents > 0): ?>
  <p class="r-note r-center"><?= e($paidCents > 0 ? trans('receipt.partial_note', $rlang) : trans('receipt.unpaid_note', $rlang)) ?></p>
  <?php endif; ?>

  <hr>

  <p class="r-thanks"><?= e(trans('receipt.thanks', $rlang)) ?></p>
  <?php if ($footer !== ''): ?><p class="r-note r-center r-muted"><?= e($footer) ?></p><?php endif; ?>
  <?php if ((int) $order['is_demo'] === 1 || config('app.demo_mode')): ?>
  <p class="r-demo"><?= e(trans('receipt.demo', $rlang)) ?></p>
  <?php endif; ?>
  <p class="r-note r-center r-muted"><?= e(trans('receipt.bangla_note', $rlang)) ?></p>
</article>
<script src="<?= e(asset('assets/js/admin.js')) ?>" defer></script>
</body>
</html>
