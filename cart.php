<?php
/**
 * Shopping cart. Prices are always re-read from the database; the session only
 * holds menu item IDs and quantities.
 */

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

/* ------------------------------------------------------------------ actions */
if (is_post()) {
    require_csrf();

    $action = (string) ($_POST['action'] ?? '');
    $return = (string) ($_POST['return'] ?? 'cart.php');
    if (!preg_match('#^(index|menu|menu-item|cart|checkout)\.php(\?[A-Za-z0-9_\-=&%.]+)?$#', $return)) {
        $return = 'cart.php';
    }

    switch ($action) {
        case 'add':
            $itemId = parse_int($_POST['item_id'] ?? 0);
            $quantity = max(1, min(99, parse_int($_POST['quantity'] ?? 1)));
            if ($itemId > 0 && cart_add($itemId, $quantity)) {
                flash('success', __('common.added'));
            } else {
                flash('error', __('menu.unavailable'));
            }
            break;

        case 'update':
            $quantities = $_POST['quantity'] ?? [];
            if (is_array($quantities)) {
                foreach ($quantities as $id => $qty) {
                    cart_set((int) $id, max(0, min(99, (int) $qty)));
                }
            }
            flash('success', __('common.saved'));
            break;

        case 'remove':
            $itemId = parse_int($_POST['item_id'] ?? 0);
            if ($itemId > 0) {
                cart_remove($itemId);
                flash('info', __('common.remove'));
            }
            break;

        case 'clear':
            cart_clear();
            flash('info', __('cart.clear'));
            break;
    }

    redirect($return);
}

/* -------------------------------------------------------------------- data */
$cart = cart_summary();
$empty = $cart['items'] === [];
$belowMinimum = !$empty && !$cart['meets_minimum'];
$bodyClass = $empty ? '' : 'has-sticky-bar';

$pageTitle = __('cart.title');
$activeNav = 'cart';
require DH_ROOT . '/includes/partials/site-head.php';
require DH_ROOT . '/includes/partials/site-header.php';
require DH_ROOT . '/includes/partials/flash.php';
?>
<main id="main">
  <div class="page-head">
    <div class="container">
      <span class="eyebrow"><?= e(restaurant_name()) ?></span>
      <h1><?= e(__('cart.title')) ?></h1>
      <?php if (!$empty): ?>
      <p><?= e(__('cart.delivery_note')) ?></p>
      <?php endif; ?>
    </div>
  </div>

  <div class="container section-tight">
    <?php if ($empty): ?>
    <div class="card card-pad">
      <div class="empty-state">
        <span class="icon-tile"><?= icon('cart') ?></span>
        <h2><?= e(__('cart.empty_title')) ?></h2>
        <p><?= e(__('cart.empty_text')) ?></p>
        <a class="btn" href="<?= e(url('menu.php')) ?>"><?= icon('utensils') ?><span><?= e(__('cart.browse_menu')) ?></span></a>
      </div>
    </div>
    <?php else: ?>
    <?php if ($cart['removed'] > 0): ?>
    <div class="alert alert-warning" style="margin-bottom:16px"><?= icon('alert') ?><div><?= e(__('cart.removed_notice')) ?></div></div>
    <?php endif; ?>

    <div class="cart-layout">
      <section class="card card-pad" aria-label="<?= e(__('cart.title')) ?>">
        <?php foreach ($cart['items'] as $item): ?>
        <div class="cart-line">
          <img src="<?= e(menu_image_url($item['image_path'])) ?>" alt="<?= e(item_name($item)) ?>" width="72" height="72" loading="lazy">
          <div>
            <h3><?= e(item_name($item)) ?></h3>
            <div class="line-price"><?= money($item['unit_price_cents']) ?> <?= e(__('dish.price_each')) ?></div>
            <form method="post" action="<?= e(url('cart.php')) ?>" class="cart-line-actions" data-auto-submit>
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="update">
              <div class="qty">
                <button type="button" data-qty="down" aria-label="<?= e(__('common.quantity')) ?> −"><?= icon('minus') ?></button>
                <input type="number" name="quantity[<?= (int) $item['id'] ?>]" value="<?= (int) $item['quantity'] ?>" min="1" max="99" inputmode="numeric" aria-label="<?= e(__('common.quantity') . ': ' . item_name($item)) ?>">
                <button type="button" data-qty="up" aria-label="<?= e(__('common.quantity')) ?> +"><?= icon('plus') ?></button>
              </div>
              <noscript><button class="btn btn-secondary btn-sm" type="submit"><?= e(__('common.saved')) ?></button></noscript>
            </form>
          </div>
          <div style="text-align:right">
            <div class="line-total"><?= money($item['line_total_cents']) ?></div>
            <form method="post" action="<?= e(url('cart.php')) ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="remove">
              <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
              <button class="remove-link" type="submit"><?= e(__('common.remove')) ?></button>
            </form>
          </div>
        </div>
        <?php endforeach; ?>

        <div style="display:flex;gap:10px;flex-wrap:wrap;justify-content:space-between;align-items:center;margin-top:18px">
          <a class="btn btn-ghost" href="<?= e(url('menu.php')) ?>"><?= icon('arrow-left') ?><span><?= e(__('cart.continue')) ?></span></a>
          <form method="post" action="<?= e(url('cart.php')) ?>" data-confirm="<?= e(__('cart.clear_confirm')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="clear">
            <button class="btn btn-ghost btn-sm" type="submit"><?= icon('trash') ?><span><?= e(__('cart.clear')) ?></span></button>
          </form>
        </div>
      </section>

      <aside class="cart-aside">
        <div class="card card-pad">
          <h2 style="font-size:var(--fs-lg);margin-bottom:14px"><?= e(__('cart.summary')) ?></h2>
          <ul class="summary-list">
            <li><span><?= e(__('common.items')) ?> (<?= e(is_bn() ? bn_digits((string) $cart['quantity_total']) : (string) $cart['quantity_total']) ?>)</span><span><?= money($cart['subtotal']) ?></span></li>
            <li><span><?= e(__('common.delivery_fee')) ?></span><span><?= money($cart['delivery_fee']) ?></span></li>
            <?php if ($cart['tax'] > 0): ?>
            <li><span><?= e(__('common.tax')) ?></span><span><?= money($cart['tax']) ?></span></li>
            <?php endif; ?>
            <li class="total"><span><?= e(__('common.total')) ?></span><span><?= money($cart['grand_total']) ?></span></li>
          </ul>

          <?php if ($belowMinimum): ?>
          <div class="alert alert-warning" style="margin-top:14px">
            <?= icon('info') ?>
            <div><?= e(__('cart.min_order', ['amount' => money($cart['minimum_order']), 'remaining' => money($cart['minimum_order'] - $cart['subtotal'])])) ?></div>
          </div>
          <?php elseif ($cart['minimum_order'] > 0): ?>
          <p class="summary-note" style="color:var(--success)"><?= e(__('cart.min_order_ok')) ?></p>
          <?php endif; ?>

          <div class="stack" style="margin-top:16px">
            <a class="btn btn-lg btn-block cart-checkout-desktop" href="<?= e(url('checkout.php')) ?>" <?= $belowMinimum ? 'aria-disabled="true" style="pointer-events:none;opacity:.55"' : '' ?>>
              <?= e(__('cart.checkout')) ?><?= icon('arrow-right') ?>
            </a>
            <?php $waLink = whatsapp_link(__('nav.whatsapp')); ?>
            <?php if ($waLink !== null): ?>
            <a class="btn btn-whatsapp btn-block" href="<?= e($waLink) ?>" target="_blank" rel="noopener">
              <?= wa_icon() ?><span><?= e(__('nav.whatsapp')) ?></span>
            </a>
            <?php endif; ?>
          </div>
          <p class="summary-note"><?= e(__('cart.delivery_note')) ?></p>
        </div>
      </aside>
    </div>

    <div class="sticky-bar">
      <div class="sticky-total">
        <span><?= e(__('common.total')) ?></span>
        <b class="price"><?= money($cart['grand_total']) ?></b>
      </div>
      <a class="btn" href="<?= e(url('checkout.php')) ?>" <?= $belowMinimum ? 'aria-disabled="true" style="pointer-events:none;opacity:.55"' : '' ?>>
        <?= e(__('cart.checkout')) ?><?= icon('arrow-right') ?>
      </a>
    </div>
    <?php endif; ?>
  </div>
</main>
<?php require DH_ROOT . '/includes/partials/site-footer.php'; ?>
