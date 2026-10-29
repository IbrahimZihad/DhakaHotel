<?php
/**
 * Post-checkout confirmation. Access is limited to the session that created
 * the order; other visitors are sent to the tracking page.
 */

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$orderId = (int) ($_SESSION['last_order_id'] ?? 0);
$order = $orderId > 0 ? order_by_id($orderId) : null;

if ($order === null) {
    flash('info', __('success.track_hint'));
    redirect('track-order.php');
}

$items = order_items($orderId);
$payments = order_payments($orderId);
$paidCents = order_paid_cents($orderId);
$grandCents = cents_from_decimal((string) $order['grand_total']);
$paymentStatus = (string) $order['payment_status'];
$fullyPaid = $paymentStatus === 'paid' && $paidCents >= $grandCents;
$waLink = whatsapp_link(whatsapp_order_message((string) $order['order_number'], current_lang()));

$pageTitle = __('success.title');
$activeNav = 'track';
require DH_ROOT . '/includes/partials/site-head.php';
require DH_ROOT . '/includes/partials/site-header.php';
?>
<main id="main">
  <div class="container section-tight">
    <div class="success-hero">
      <div class="success-check"><?= icon('check') ?></div>
      <h1><?= e(__('success.title')) ?></h1>
      <p class="muted"><?= e(__('success.subtitle')) ?></p>

      <div class="ref-box">
        <span><?= e(__('success.reference')) ?>:</span>
        <strong id="order-ref"><?= e((string) $order['order_number']) ?></strong>
        <button class="btn btn-ghost btn-sm" type="button" data-copy="<?= e((string) $order['order_number']) ?>" data-copied="<?= e(__('common.copied')) ?>">
          <?= icon('copy') ?><span><?= e(__('common.copy')) ?></span>
        </button>
      </div>

      <div style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap">
        <span class="badge badge-info badge-dot"><?= e(order_status_label((string) $order['status'])) ?></span>
        <span class="badge <?= $fullyPaid ? 'badge-success' : ($paymentStatus === 'partial' ? 'badge-warning' : 'badge-soft') ?> badge-dot">
          <?= e(payment_status_label($paymentStatus)) ?>
        </span>
        <?php if ((int) $order['is_demo'] === 1): ?><span class="badge badge-demo"><?= e(__('success.demo')) ?></span><?php endif; ?>
      </div>
    </div>

    <div class="checkout-layout" style="margin-top:8px">
      <section class="card card-pad">
        <h2 style="font-size:var(--fs-lg);margin-bottom:14px"><?= e(__('success.summary')) ?></h2>
        <ul class="order-items-mini">
          <?php foreach ($items as $item): ?>
          <li>
            <span><span class="q"><?= e(is_bn() ? bn_digits((string) $item['quantity']) : (string) $item['quantity']) ?>×</span> <?= e(localized($item, 'name')) ?></span>
            <span><?= money_from_decimal((string) $item['line_total']) ?></span>
          </li>
          <?php endforeach; ?>
        </ul>
        <hr class="divider" style="margin:14px 0">
        <ul class="summary-list">
          <li><span><?= e(__('common.subtotal')) ?></span><span><?= money_from_decimal((string) $order['subtotal']) ?></span></li>
          <?php if (cents_from_decimal((string) $order['discount']) > 0): ?>
          <li><span><?= e(__('common.discount')) ?></span><span>−<?= money_from_decimal((string) $order['discount']) ?></span></li>
          <?php endif; ?>
          <?php if (cents_from_decimal((string) $order['delivery_fee']) > 0): ?>
          <li><span><?= e(__('common.delivery_fee')) ?></span><span><?= money_from_decimal((string) $order['delivery_fee']) ?></span></li>
          <?php endif; ?>
          <?php if (cents_from_decimal((string) $order['tax']) > 0): ?>
          <li><span><?= e(__('common.tax')) ?></span><span><?= money_from_decimal((string) $order['tax']) ?></span></li>
          <?php endif; ?>
          <li class="total"><span><?= e(__('common.total')) ?></span><span><?= money_from_decimal((string) $order['grand_total']) ?></span></li>
        </ul>

        <?php if (!$fullyPaid): ?>
        <div class="alert alert-warning" style="margin-top:14px"><?= icon('info') ?><div><?= e(__('success.pay_later')) ?></div></div>
        <?php endif; ?>

        <div style="margin-top:16px;display:flex;gap:10px;flex-wrap:wrap">
          <a class="btn" href="<?= e(url('track-order.php')) ?>"><?= icon('receipt') ?><span><?= e(__('success.track')) ?></span></a>
          <?php if ($waLink !== null): ?>
          <a class="btn btn-whatsapp" href="<?= e($waLink) ?>" target="_blank" rel="noopener"><?= wa_icon() ?><span><?= e(__('success.whatsapp')) ?></span></a>
          <?php endif; ?>
          <a class="btn btn-ghost" href="<?= e(url('menu.php')) ?>"><?= e(__('success.continue')) ?></a>
        </div>
      </section>

      <aside class="checkout-aside">
        <div class="card card-pad">
          <h2 style="font-size:var(--fs-lg);margin-bottom:12px"><?= e(__('track.details')) ?></h2>
          <ul class="summary-list">
            <li><span><?= e(__('common.date')) ?></span><span><?= e(format_datetime($order['created_at'])) ?></span></li>
            <li><span><?= e(__('admin.order_type')) ?></span><span><?= e(order_type_label((string) $order['order_type'])) ?></span></li>
            <li><span><?= e(__('track.payment_method')) ?></span><span><?= e($order['payment_method'] ? payment_method_label((string) $order['payment_method']) : __('common.none')) ?></span></li>
            <li><span><?= e(__('admin.amount_paid')) ?></span><span><?= money($paidCents) ?></span></li>
            <?php if ($paidCents < $grandCents): ?>
            <li><span><?= e(__('admin.balance')) ?></span><span><?= money($grandCents - $paidCents) ?></span></li>
            <?php endif; ?>
          </ul>
          <?php if ((string) $order['order_type'] === 'delivery' && (string) $order['delivery_address'] !== ''): ?>
          <p class="summary-note" style="margin-top:12px"><strong><?= e(__('track.delivery_to')) ?>:</strong> <?= e((string) $order['delivery_address']) ?></p>
          <?php endif; ?>
          <p class="summary-note"><?= e(__('success.track_hint')) ?></p>
        </div>
      </aside>
    </div>
  </div>
</main>
<?php require DH_ROOT . '/includes/partials/site-footer.php'; ?>
