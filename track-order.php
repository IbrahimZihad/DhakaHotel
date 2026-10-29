<?php
/**
 * Public order tracking. Requires both the order reference and the phone
 * number used at checkout; only that order's data is shown.
 */

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$reference = '';
$phone = '';
$errors = [];
$order = null;

if (is_post()) {
    require_csrf();

    $reference = strtoupper(clean_text($_POST['reference'] ?? '', 24));
    $phone = clean_text($_POST['phone'] ?? '', 20);

    if ($reference === '' || !preg_match('/^[A-Z0-9-]{4,24}$/', $reference)) {
        $errors['reference'] = __('track.error_reference');
    }
    if (!is_valid_bd_phone($phone)) {
        $errors['phone'] = __('track.error_phone');
    }

    if ($errors === []) {
        $candidate = order_by_number($reference);
        if ($candidate !== null && normalize_bd_phone((string) ($candidate['customer_phone'] ?? '')) === normalize_bd_phone($phone)) {
            $order = $candidate;
        }

        if ($order === null) {
            $errors['form'] = __('track.not_found');
        }
    }
} else {
    $lastId = (int) ($_SESSION['last_order_id'] ?? 0);
    if ($lastId > 0) {
        $last = order_by_id($lastId);
        $reference = $last !== null ? (string) $last['order_number'] : '';
    }
}

$items = [];
$paidCents = 0;
$timeline = [];
if ($order !== null) {
    $items = order_items((int) $order['id']);
    $paidCents = order_paid_cents((int) $order['id']);

    $history = fetch_all('SELECT status, created_at FROM order_status_history WHERE order_id = ? ORDER BY id ASC', [(int) $order['id']]);
    foreach ($history as $entry) {
        $timeline[(string) $entry['status']] = $entry['created_at'];
    }
}

$timelineSteps = [
    ['status' => 'pending', 'icon' => 'receipt', 'label' => __('status.pending')],
    ['status' => 'confirmed', 'icon' => 'check-circle', 'label' => __('status.confirmed')],
    ['status' => 'preparing', 'icon' => 'cooking-pot', 'label' => __('status.preparing')],
    ['status' => 'ready', 'icon' => 'bag', 'label' => __('status.ready')],
    ['status' => 'out_for_delivery', 'icon' => 'moped', 'label' => __('status.out_for_delivery')],
    ['status' => 'delivered', 'icon' => 'check', 'label' => __('status.delivered')],
];

$pageTitle = __('track.title');
$activeNav = 'track';
require DH_ROOT . '/includes/partials/site-head.php';
require DH_ROOT . '/includes/partials/site-header.php';
require DH_ROOT . '/includes/partials/flash.php';
?>
<main id="main">
  <div class="page-head">
    <div class="container">
      <span class="eyebrow"><?= icon('receipt') ?><?= e(__('success.reference')) ?></span>
      <h1><?= e(__('track.title')) ?></h1>
      <p><?= e(__('track.subtitle')) ?></p>
    </div>
  </div>

  <div class="container section-tight">
    <div class="checkout-layout">
      <div class="stack" style="display:grid;gap:16px">
        <?php if ($order === null): ?>
        <section class="card card-pad">
          <?php if ($errors !== []): ?>
          <div class="alert alert-danger" style="margin-bottom:16px" role="alert">
            <?= icon('alert') ?>
            <div><?= e($errors['form'] ?? reset($errors)) ?></div>
          </div>
          <?php endif; ?>
          <form method="post" action="<?= e(url('track-order.php')) ?>" class="form-grid">
            <?= csrf_field() ?>
            <div class="field">
              <label for="reference"><?= e(__('track.reference')) ?></label>
              <input class="input" id="reference" name="reference" value="<?= e($reference) ?>" placeholder="DH-XXXXXXX" required autocomplete="off"
                     <?= isset($errors['reference']) ? 'aria-invalid="true"' : '' ?>>
              <?php if (isset($errors['reference'])): ?><span class="field-error"><?= e($errors['reference']) ?></span><?php endif; ?>
            </div>
            <div class="field">
              <label for="phone"><?= e(__('track.phone')) ?></label>
              <input class="input" id="phone" name="phone" value="<?= e($phone) ?>" inputmode="tel" placeholder="01XXXXXXXXX" required autocomplete="tel"
                     <?= isset($errors['phone']) ? 'aria-invalid="true"' : '' ?>>
              <?php if (isset($errors['phone'])): ?><span class="field-error"><?= e($errors['phone']) ?></span><?php endif; ?>
            </div>
            <div>
              <button class="btn btn-lg" type="submit"><?= icon('search') ?><span><?= e(__('track.submit')) ?></span></button>
            </div>
          </form>
        </section>
        <?php endif; ?>

        <?php if ($order !== null): ?>
        <section class="card card-pad">
          <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:center">
            <div>
              <p class="small muted"><?= e(__('success.reference')) ?></p>
              <h2 style="font-size:var(--fs-xl);letter-spacing:.04em"><?= e((string) $order['order_number']) ?></h2>
            </div>
            <div style="display:flex;gap:6px;flex-wrap:wrap">
              <span class="badge badge-info badge-dot"><?= e(order_status_label((string) $order['status'])) ?></span>
              <span class="badge <?= $order['payment_status'] === 'paid' ? 'badge-success' : ($order['payment_status'] === 'partial' ? 'badge-warning' : 'badge-soft') ?> badge-dot">
                <?= e(payment_status_label((string) $order['payment_status'])) ?>
              </span>
            </div>
          </div>

          <?php if ((string) $order['status'] === 'cancelled'): ?>
          <div class="alert alert-danger" style="margin-top:16px"><?= icon('ban') ?><div><?= e(__('track.cancelled_note')) ?></div></div>
          <?php endif; ?>

          <h3 style="font-size:var(--fs-base);margin-top:20px;margin-bottom:10px"><?= e(__('track.timeline')) ?></h3>
          <ul class="timeline">
            <?php
            $currentIndex = array_search((string) $order['status'], array_column($timelineSteps, 'status'), true);
            if ($currentIndex === false) {
                $currentIndex = -1;
            }
            foreach ($timelineSteps as $index => $step):
                $done = isset($timeline[$step['status']]);
                $isCurrent = $step['status'] === $order['status'];
            ?>
            <li class="<?= $done ? 'is-done' : '' ?> <?= $isCurrent ? 'is-current' : '' ?>">
              <span class="dot"><?= icon($done ? 'check' : $step['icon']) ?></span>
              <div>
                <strong><?= e($step['label']) ?></strong>
                <?php if (isset($timeline[$step['status']])): ?>
                <time datetime="<?= e(date('c', strtotime((string) $timeline[$step['status']]))) ?>"><?= e(format_datetime($timeline[$step['status']])) ?></time>
                <?php endif; ?>
              </div>
            </li>
            <?php endforeach; ?>
          </ul>
        </section>

        <section class="card card-pad">
          <h2 style="font-size:var(--fs-lg);margin-bottom:14px"><?= e(__('track.details')) ?></h2>
          <ul class="summary-list">
            <li><span><?= e(__('track.placed_at')) ?></span><span><?= e(format_datetime($order['created_at'])) ?></span></li>
            <li><span><?= e(__('admin.order_type')) ?></span><span><?= e(order_type_label((string) $order['order_type'])) ?></span></li>
            <li><span><?= e(__('track.payment_method')) ?></span><span><?= e($order['payment_method'] ? payment_method_label((string) $order['payment_method']) : __('common.none')) ?></span></li>
            <li><span><?= e(__('admin.amount_paid')) ?></span><span><?= money($paidCents) ?></span></li>
            <?php if ($paidCents < cents_from_decimal((string) $order['grand_total'])): ?>
            <li><span><?= e(__('admin.balance')) ?></span><span><?= money(cents_from_decimal((string) $order['grand_total']) - $paidCents) ?></span></li>
            <?php endif; ?>
            <li class="total"><span><?= e(__('common.total')) ?></span><span><?= money_from_decimal((string) $order['grand_total']) ?></span></li>
          </ul>
          <?php if ((string) $order['order_type'] === 'delivery'): ?>
          <p class="summary-note"><strong><?= e(__('track.delivery_to')) ?>:</strong> <?= e((string) $order['delivery_address']) ?></p>
          <?php endif; ?>

          <?php if ($order['payment_status'] === 'unpaid'): ?>
          <div class="alert alert-warning" style="margin-top:14px"><?= icon('info') ?><div><?= e(__('track.unpaid_note')) ?></div></div>
          <?php elseif ($order['payment_status'] === 'partial'): ?>
          <div class="alert alert-info" style="margin-top:14px"><?= icon('info') ?><div><?= e(__('track.partial_note')) ?></div></div>
          <?php elseif ($order['payment_status'] === 'paid'): ?>
          <div class="alert alert-success" style="margin-top:14px"><?= icon('check-circle') ?><div><?= e(__('track.paid_note')) ?></div></div>
          <?php endif; ?>
        </section>
        <?php endif; ?>
      </div>

      <aside class="checkout-aside">
        <div class="card card-pad">
          <h2 style="font-size:var(--fs-lg);margin-bottom:12px"><?= e(__('track.help')) ?></h2>
          <?php $waLink = whatsapp_link(whatsapp_order_message($order !== null ? (string) $order['order_number'] : ($reference !== '' ? $reference : null), current_lang())); ?>
          <?php if ($waLink !== null): ?>
          <a class="btn btn-whatsapp btn-block" href="<?= e($waLink) ?>" target="_blank" rel="noopener"><?= wa_icon() ?><span><?= e(__('nav.whatsapp')) ?></span></a>
          <?php else: ?>
          <p class="small muted"><?= e(__('home.no_whatsapp')) ?></p>
          <?php endif; ?>
          <?php $phoneSetting = trim((string) setting('phone', '')); ?>
          <?php if ($phoneSetting !== ''): ?>
          <a class="btn btn-ghost btn-block" href="tel:<?= e(preg_replace('/[^0-9+]/', '', $phoneSetting)) ?>" style="margin-top:8px"><?= icon('phone') ?><span><?= e(__('contact.call_now')) ?></span></a>
          <?php endif; ?>
          <hr class="divider" style="margin:16px 0">
          <p class="small muted"><?= e(__('success.track_hint')) ?></p>
          <a class="link-arrow" href="<?= e(url('menu.php')) ?>" style="margin-top:8px"><?= e(__('success.continue')) ?><?= icon('arrow-right') ?></a>
        </div>
      </aside>
    </div>
  </div>
</main>
<?php require DH_ROOT . '/includes/partials/site-footer.php'; ?>
