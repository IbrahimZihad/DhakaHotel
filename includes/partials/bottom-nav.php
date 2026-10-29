<?php
/**
 * Mobile bottom navigation (customer app style).
 */
$script = basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');
$isActive = static fn (string $file): string => $script === $file ? 'aria-current="page"' : '';
$navCartQty = cart_count();
?>
<nav class="bottom-nav" aria-label="<?= e(__('nav.menu')) ?>">
  <a href="<?= e(url('')) ?>" <?= $isActive('index.php') ?>>
    <?= icon('home') ?><span><?= e(__('nav.home')) ?></span>
  </a>
  <a href="<?= e(url('menu.php')) ?>" <?= $isActive('menu.php') ?>>
    <?= icon('utensils') ?><span><?= e(__('nav.menu')) ?></span>
  </a>
  <a href="<?= e(url('cart.php')) ?>" <?= $isActive('cart.php') ?>>
    <?= icon('cart') ?><span><?= e(__('nav.cart')) ?></span>
    <span class="cart-count" data-cart-count <?= $navCartQty > 0 ? '' : 'hidden' ?>><?= e(is_bn() ? bn_digits((string) $navCartQty) : (string) $navCartQty) ?></span>
  </a>
  <a href="<?= e(url('track-order.php')) ?>" <?= $isActive('track-order.php') ?>>
    <?= icon('receipt') ?><span><?= e(__('nav.track')) ?></span>
  </a>
</nav>
