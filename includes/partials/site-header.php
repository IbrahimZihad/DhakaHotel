<?php
/**
 * Public site header. Expects: $activeNav (string|null).
 */
$activeNav = $activeNav ?? '';
$navItems = [
    'home' => ['label' => __('nav.home'), 'href' => url(''), 'icon' => 'home'],
    'menu' => ['label' => __('nav.menu'), 'href' => url('menu.php'), 'icon' => 'utensils'],
    'track' => ['label' => __('nav.track'), 'href' => url('track-order.php'), 'icon' => 'receipt'],
    'about' => ['label' => __('nav.about'), 'href' => url('about.php'), 'icon' => 'info'],
    'reviews' => ['label' => __('nav.reviews'), 'href' => url('reviews.php'), 'icon' => 'star'],
    'contact' => ['label' => __('nav.contact'), 'href' => url('contact.php'), 'icon' => 'phone'],
];
$cartQty = cart_count();
$waLink = whatsapp_link(__('nav.whatsapp'));
$tagline = restaurant('tagline');
?>
<header class="site-header">
  <div class="container header-inner">
    <a class="brand" href="<?= e(url('')) ?>">
      <span class="brand-mark"><?= brand_mark_svg() ?></span>
      <span class="brand-name">
        <span><?= e(restaurant_name()) ?></span>
        <?php if ($tagline !== '' && $tagline !== restaurant_name()): ?>
        <small><?= e($tagline) ?></small>
        <?php endif; ?>
      </span>
    </a>

    <nav class="main-nav" aria-label="<?= e(__('nav.menu')) ?>">
      <?php foreach ($navItems as $key => $item): ?>
      <a href="<?= e($item['href']) ?>" <?= $activeNav === $key ? 'aria-current="page"' : '' ?>><?= e($item['label']) ?></a>
      <?php endforeach; ?>
    </nav>

    <div class="header-actions">
      <?php if ($waLink !== null): ?>
      <a class="btn btn-ghost btn-sm header-wa" href="<?= e($waLink) ?>" target="_blank" rel="noopener">
        <?= wa_icon() ?><span><?= e(__('nav.whatsapp')) ?></span>
      </a>
      <?php endif; ?>
      <?php
      $class = 'header-lang';
      $id = 'lang-header';
      require DH_ROOT . '/includes/partials/lang-switcher.php';
      ?>
      <a class="cart-link" href="<?= e(url('cart.php')) ?>" aria-label="<?= e(__('nav.cart')) ?>">
        <?= icon('cart') ?>
        <span class="cart-count" id="cart-count" <?= $cartQty > 0 ? '' : 'hidden' ?>><?= e(is_bn() ? bn_digits((string) $cartQty) : (string) $cartQty) ?></span>
      </a>
      <button class="menu-toggle" type="button" id="menu-toggle" aria-expanded="false" aria-controls="mobile-nav">
        <span class="sr-only"><?= e(__('nav.menu_open')) ?></span>
        <?= icon('menu') ?>
      </button>
    </div>
  </div>

  <div class="mobile-nav" id="mobile-nav">
    <div class="container">
      <ul>
        <?php foreach ($navItems as $key => $item): ?>
        <li>
          <a href="<?= e($item['href']) ?>" <?= $activeNav === $key ? 'aria-current="page"' : '' ?>>
            <?= icon($item['icon']) ?><?= e($item['label']) ?>
          </a>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php
      $class = 'mobile-lang';
      $id = 'lang-mobile';
      require DH_ROOT . '/includes/partials/lang-switcher.php';
      ?>
      <a class="btn btn-block" href="<?= e(url('menu.php')) ?>"><?= e(__('nav.order_now')) ?></a>
    </div>
  </div>
</header>
