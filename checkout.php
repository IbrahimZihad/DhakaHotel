<?php
/**
 * Guest checkout. All totals are recalculated here from database prices and
 * restaurant settings; nothing supplied by the browser is trusted.
 */

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$rawCart = cart_raw();
if ($rawCart === []) {
    flash('info', __('checkout.empty_cart'));
    redirect('cart.php');
}

if (empty($_SESSION['checkout_idempotency'])) {
    $_SESSION['checkout_idempotency'] = bin2hex(random_bytes(16));
}
$idempotencyKey = (string) $_SESSION['checkout_idempotency'];

$form = [
    'customer_name' => '',
    'customer_phone' => '',
    'customer_email' => '',
    'delivery_address' => '',
    'notes' => '',
    'delivery_method' => 'delivery',
    'payment_method' => 'cod',
];
$errors = [];

$cartItems = [];
foreach ($rawCart as $id => $quantity) {
    $cartItems[] = ['id' => (int) $id, 'quantity' => (int) $quantity];
}
$lineResult = build_order_lines($cartItems);
$lines = $lineResult['lines'];

$deliveryMethod = 'delivery';
$feeCents = 0;
$bill = compute_bill($lines, ['delivery_fee_cents' => 0]);

/* ------------------------------------------------------------------- submit */
if (is_post()) {
    require_csrf();

    $form['customer_name'] = clean_text($_POST['customer_name'] ?? '', 80);
    $form['customer_phone'] = clean_text($_POST['customer_phone'] ?? '', 20);
    $form['customer_email'] = clean_text($_POST['customer_email'] ?? '', 120);
    $form['delivery_address'] = clean_text($_POST['delivery_address'] ?? '', 300);
    $form['notes'] = clean_text($_POST['notes'] ?? '', 500);
    $form['delivery_method'] = (string) ($_POST['delivery_method'] ?? 'delivery');
    $form['payment_method'] = (string) ($_POST['payment_method'] ?? 'cod');

    if (!in_array($form['delivery_method'], ['delivery', 'pickup'], true)) {
        $errors['delivery_method'] = __('checkout.error_delivery');
        $form['delivery_method'] = 'delivery';
    }

    $deliveryMethod = $form['delivery_method'];
    $feeCents = $deliveryMethod === 'delivery' ? delivery_fee_cents() : 0;
    $bill = compute_bill($lines, [
        'delivery_fee_cents' => $feeCents,
        'tax_basis_points' => tax_rate_basis_points(),
    ]);

    if (!$lineResult['ok']) {
        $errors['items'] = __('checkout.error_items');
    }

    if (!v_min_length($form['customer_name'], 2) || !v_max_length($form['customer_name'], 80)) {
        $errors['customer_name'] = __('checkout.error_name');
    }
    if (!is_valid_bd_phone($form['customer_phone'])) {
        $errors['customer_phone'] = __('checkout.error_phone');
    }
    if ($form['customer_email'] !== '' && !v_email($form['customer_email'])) {
        $errors['customer_email'] = __('checkout.error_email');
    }
    if ($deliveryMethod === 'delivery' && (!v_min_length($form['delivery_address'], 10) || !v_max_length($form['delivery_address'], 300))) {
        $errors['delivery_address'] = __('checkout.error_address');
    }

    $allowedMethods = $deliveryMethod === 'delivery' ? ['cod', 'bkash', 'nagad'] : ['cash', 'bkash', 'nagad'];
    if (!in_array($form['payment_method'], $allowedMethods, true)) {
        $errors['payment_method'] = __('checkout.error_method');
        $form['payment_method'] = $allowedMethods[0];
    }

    if ($bill['subtotal'] < minimum_order_cents()) {
        $errors['items'] = __('checkout.min_order', ['amount' => money(minimum_order_cents())]);
    }

    if ($errors === []) {
        $result = create_order([
            'source' => 'web',
            'order_type' => $deliveryMethod === 'delivery' ? 'delivery' : 'takeaway',
            'customer_name' => $form['customer_name'],
            'customer_phone' => $form['customer_phone'],
            'customer_email' => $form['customer_email'] !== '' ? $form['customer_email'] : null,
            'delivery_address' => $deliveryMethod === 'delivery' ? $form['delivery_address'] : null,
            'notes' => $form['notes'] !== '' ? $form['notes'] : null,
            'lines' => $lines,
            'delivery_fee_cents' => $feeCents,
            'tax_basis_points' => tax_rate_basis_points(),
            'status' => 'pending',
            'payment_method' => $form['payment_method'],
            'idempotency_key' => $idempotencyKey,
        ]);

        if ($result['ok']) {
            cart_clear();
            $_SESSION['last_order_id'] = (int) $result['order_id'];
            unset($_SESSION['checkout_idempotency']);
            redirect('order-success.php');
        }

        $errors['form'] = __('checkout.error_generic');
        storage_log('checkout create_order errors: ' . implode(',', $result['errors']));
    }
} else {
    $bill = compute_bill($lines, [
        'delivery_fee_cents' => delivery_fee_cents(),
        'tax_basis_points' => tax_rate_basis_points(),
    ]);
}

$pageTitle = __('checkout.title');
$activeNav = 'cart';
$bodyClass = 'has-sticky-bar';
require DH_ROOT . '/includes/partials/site-head.php';
require DH_ROOT . '/includes/partials/site-header.php';
require DH_ROOT . '/includes/partials/flash.php';
?>
<main id="main">
  <div class="page-head">
    <div class="container">
      <span class="eyebrow"><?= e(__('home.stat_delivery_value')) ?></span>
      <h1><?= e(__('checkout.title')) ?></h1>
      <p><?= e(__('checkout.subtitle')) ?></p>
    </div>
  </div>

  <div class="container section-tight">
    <?php if ($errors !== []): ?>
    <div class="alert alert-danger" style="margin-bottom:16px" role="alert">
      <?= icon('alert') ?>
      <div>
        <?php foreach ($errors as $message): ?>
        <div><?= e($message) ?></div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('checkout.php')) ?>" id="checkout-form" novalidate>
      <?= csrf_field() ?>

      <div class="checkout-layout">
        <div class="stack" style="display:grid;gap:16px">
          <section class="card card-pad" aria-labelledby="customer-title">
            <h2 id="customer-title" style="font-size:var(--fs-lg);margin-bottom:16px"><?= e(__('checkout.customer_title')) ?></h2>
            <div class="form-grid two">
              <div class="field">
                <label for="customer_name"><?= e(__('checkout.name')) ?> <span aria-hidden="true">*</span></label>
                <input class="input" id="customer_name" name="customer_name" value="<?= e($form['customer_name']) ?>" required minlength="2" maxlength="80" autocomplete="name"
                       <?= isset($errors['customer_name']) ? 'aria-invalid="true"' : '' ?>>
                <?php if (isset($errors['customer_name'])): ?><span class="field-error"><?= e($errors['customer_name']) ?></span><?php endif; ?>
              </div>
              <div class="field">
                <label for="customer_phone"><?= e(__('checkout.phone')) ?> <span aria-hidden="true">*</span></label>
                <input class="input" id="customer_phone" name="customer_phone" value="<?= e($form['customer_phone']) ?>" required inputmode="tel" autocomplete="tel"
                       placeholder="01XXXXXXXXX" aria-describedby="phone-hint"
                       <?= isset($errors['customer_phone']) ? 'aria-invalid="true"' : '' ?>>
                <span class="hint" id="phone-hint"><?= e(__('checkout.phone_hint')) ?> · <?= e(__('checkout.contact_note')) ?></span>
                <?php if (isset($errors['customer_phone'])): ?><span class="field-error"><?= e($errors['customer_phone']) ?></span><?php endif; ?>
              </div>
              <div class="field span-2">
                <label for="customer_email"><?= e(__('checkout.email')) ?> <span class="hint">(<?= e(__('common.optional')) ?>)</span></label>
                <input class="input" id="customer_email" name="customer_email" type="email" value="<?= e($form['customer_email']) ?>" autocomplete="email"
                       <?= isset($errors['customer_email']) ? 'aria-invalid="true"' : '' ?>>
                <?php if (isset($errors['customer_email'])): ?><span class="field-error"><?= e($errors['customer_email']) ?></span><?php endif; ?>
              </div>
            </div>
          </section>

          <section class="card card-pad" aria-labelledby="delivery-title">
            <h2 id="delivery-title" style="font-size:var(--fs-lg);margin-bottom:16px"><?= e(__('checkout.delivery_title')) ?></h2>
            <div class="choice-grid two" role="radiogroup" aria-label="<?= e(__('checkout.delivery_method')) ?>">
              <label class="choice">
                <input type="radio" name="delivery_method" value="delivery" <?= $form['delivery_method'] === 'delivery' ? 'checked' : '' ?>>
                <?= icon('moped') ?>
                <span><strong><?= e(__('checkout.method_delivery')) ?></strong><span><?= e(__('checkout.method_delivery_hint')) ?></span></span>
              </label>
              <label class="choice">
                <input type="radio" name="delivery_method" value="pickup" <?= $form['delivery_method'] === 'pickup' ? 'checked' : '' ?>>
                <?= icon('bag') ?>
                <span><strong><?= e(__('checkout.method_pickup')) ?></strong><span><?= e(__('checkout.method_pickup_hint')) ?></span></span>
              </label>
            </div>
            <?php if (isset($errors['delivery_method'])): ?><span class="field-error"><?= e($errors['delivery_method']) ?></span><?php endif; ?>

            <div class="field" style="margin-top:16px">
              <label for="delivery_address"><?= e(__('checkout.address')) ?></label>
              <textarea class="textarea" id="delivery_address" name="delivery_address" maxlength="300"
                        placeholder="<?= e(__('checkout.address')) ?>" autocomplete="street-address"
                        <?= isset($errors['delivery_address']) ? 'aria-invalid="true"' : '' ?>><?= e($form['delivery_address']) ?></textarea>
              <span class="hint"><?= e(__('checkout.delivery_only_note')) ?></span>
              <?php if (isset($errors['delivery_address'])): ?><span class="field-error"><?= e($errors['delivery_address']) ?></span><?php endif; ?>
            </div>

            <div class="field" style="margin-top:16px">
              <label for="notes"><?= e(__('checkout.notes')) ?> <span class="hint">(<?= e(__('common.optional')) ?>)</span></label>
              <textarea class="textarea" id="notes" name="notes" maxlength="500" placeholder="<?= e(__('checkout.notes_hint')) ?>"><?= e($form['notes']) ?></textarea>
            </div>
          </section>

          <section class="card card-pad" aria-labelledby="payment-title">
            <h2 id="payment-title" style="font-size:var(--fs-lg);margin-bottom:16px"><?= e(__('checkout.payment_title')) ?></h2>
            <div class="choice-grid two" role="radiogroup" aria-label="<?= e(__('checkout.payment_title')) ?>">
              <label class="choice">
                <input type="radio" name="payment_method" value="cod" <?= $form['payment_method'] === 'cod' ? 'checked' : '' ?>>
                <?= icon('cash') ?>
                <span><strong><?= e(__('checkout.method_cod')) ?></strong><span><?= e(__('checkout.method_cod_hint')) ?></span></span>
              </label>
              <label class="choice">
                <input type="radio" name="payment_method" value="cash" <?= $form['payment_method'] === 'cash' ? 'checked' : '' ?>>
                <?= icon('cash') ?>
                <span><strong><?= e(__('checkout.method_cash')) ?></strong><span><?= e(__('checkout.method_cash_hint')) ?></span></span>
              </label>
              <label class="choice">
                <input type="radio" name="payment_method" value="bkash" <?= $form['payment_method'] === 'bkash' ? 'checked' : '' ?>>
                <?= icon('wallet') ?>
                <span><strong><?= e(__('checkout.method_bkash')) ?></strong><span><?= e(__('checkout.method_bkash_hint')) ?></span></span>
              </label>
              <label class="choice">
                <input type="radio" name="payment_method" value="nagad" <?= $form['payment_method'] === 'nagad' ? 'checked' : '' ?>>
                <?= icon('wallet') ?>
                <span><strong><?= e(__('checkout.method_nagad')) ?></strong><span><?= e(__('checkout.method_nagad_hint')) ?></span></span>
              </label>
            </div>
            <p class="summary-note"><?= e(__('checkout.payment_note')) ?></p>
            <?php if (isset($errors['payment_method'])): ?><span class="field-error"><?= e($errors['payment_method']) ?></span><?php endif; ?>
          </section>
        </div>

        <aside class="checkout-aside">
          <div class="card card-pad">
            <h2 style="font-size:var(--fs-lg);margin-bottom:14px"><?= e(__('checkout.summary')) ?></h2>
            <ul class="order-items-mini">
              <?php foreach ($lines as $line): ?>
              <li>
                <span><span class="q"><?= e(is_bn() ? bn_digits((string) $line['quantity']) : (string) $line['quantity']) ?>×</span> <?= e(localized($line, 'name')) ?></span>
                <span><?= money($line['line_total_cents']) ?></span>
              </li>
              <?php endforeach; ?>
            </ul>

            <hr class="divider" style="margin:14px 0">

            <ul class="summary-list">
              <li><span><?= e(__('common.subtotal')) ?></span><span><?= money($bill['subtotal']) ?></span></li>
              <li><span><?= e(__('common.delivery_fee')) ?></span><span><?= money($bill['delivery_fee']) ?></span></li>
              <?php if ($bill['tax'] > 0): ?>
              <li><span><?= e(__('common.tax')) ?></span><span><?= money($bill['tax']) ?></span></li>
              <?php endif; ?>
              <li class="total"><span><?= e(__('common.total')) ?></span><span><?= money($bill['grand_total']) ?></span></li>
            </ul>

            <button class="btn btn-lg btn-block checkout-submit-desktop" type="submit" style="margin-top:16px" id="place-order-btn">
              <?= icon('check') ?><span><?= e(__('checkout.place_order')) ?></span>
            </button>
            <p class="summary-note"><?= e(__('checkout.delivery_only_note')) ?></p>
            <a class="link-arrow" href="<?= e(url('cart.php')) ?>" style="margin-top:8px;display:inline-flex"><?= icon('arrow-left') ?><?= e(__('common.back')) ?></a>
          </div>
        </aside>
      </div>
    </form>

    <div class="sticky-bar">
      <div class="sticky-total">
        <span><?= e(__('common.total')) ?></span>
        <b class="price"><?= money($bill['grand_total']) ?></b>
      </div>
      <button class="btn btn-lg" type="submit" form="checkout-form">
        <?= icon('check') ?><span><?= e(__('checkout.place_order')) ?></span>
      </button>
    </div>
  </div>
</main>
<?php require DH_ROOT . '/includes/partials/site-footer.php'; ?>
