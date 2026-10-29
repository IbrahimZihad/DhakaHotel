<?php
/**
 * Dish detail page with quantity picker and cross-sell suggestions.
 */

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$itemId = parse_int($_GET['id'] ?? 0);
$dish = $itemId > 0 ? fetch_one(
    'SELECT mi.*, c.id AS category_id, c.slug AS category_slug,
            c.name_bn AS category_name_bn, c.name_en AS category_name_en
     FROM menu_items mi
     JOIN categories c ON c.id = mi.category_id
     WHERE mi.id = ? AND c.is_active = 1',
    [$itemId]
) : null;

if ($dish === null) {
    http_response_code(404);
    $pageTitle = __('error.not_found');
    $activeNav = 'menu';
    require DH_ROOT . '/includes/partials/site-head.php';
    require DH_ROOT . '/includes/partials/site-header.php';
    ?>
    <main id="main" class="container section">
      <div class="card card-pad">
        <div class="empty-state">
          <span class="icon-tile"><?= icon('info') ?></span>
          <h1><?= e(__('dish.not_found')) ?></h1>
          <a class="btn" href="<?= e(url('menu.php')) ?>"><?= e(__('dish.back_to_menu')) ?></a>
        </div>
      </div>
    </main>
    <?php
    require DH_ROOT . '/includes/partials/site-footer.php';
    exit;
}

$mainDish = $dish;
$available = (int) $dish['is_available'] === 1;
$unitCents = cents_from_decimal((string) $dish['price']);
$companions = fetch_all(
    "SELECT mi.* FROM menu_items mi
     JOIN categories c ON c.id = mi.category_id
     WHERE c.slug IN ('drinks', 'desserts') AND c.is_active = 1 AND mi.is_available = 1 AND mi.id <> ?
     ORDER BY RAND() LIMIT 3",
    [$itemId]
);
$related = fetch_all(
    'SELECT mi.* FROM menu_items mi
     WHERE mi.category_id = ? AND mi.id <> ? AND mi.is_available = 1
     ORDER BY mi.sort_order ASC LIMIT 4',
    [(int) $dish['category_id'], $itemId]
);

$pageTitle = item_name($dish);
$pageDescription = item_description($dish);
$activeNav = 'menu';
$bodyClass = 'has-sticky-bar';
require DH_ROOT . '/includes/partials/site-head.php';
require DH_ROOT . '/includes/partials/site-header.php';
require DH_ROOT . '/includes/partials/flash.php';
?>
<main id="main">
  <div class="container">
    <nav class="breadcrumb" aria-label="Breadcrumb">
      <a href="<?= e(url('menu.php')) ?>"><?= e(__('nav.menu')) ?></a>
      <?= icon('chevron-right') ?>
      <a href="<?= e(url('menu.php') . '?category=' . urlencode((string) $dish['category_slug'])) ?>"><?= e(localized($dish, 'category_name')) ?></a>
      <?= icon('chevron-right') ?>
      <span aria-current="page"><?= e(item_name($dish)) ?></span>
    </nav>

    <div class="product-layout">
      <div class="product-media">
        <img src="<?= e(menu_image_url($dish['image_path'])) ?>" alt="<?= e(item_name($dish)) ?>" width="800" height="600" fetchpriority="high">
      </div>

      <div class="product-info">
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          <span class="badge badge-soft"><?= icon('tag') ?><?= e(localized($dish, 'category_name')) ?></span>
          <?php if (!empty($dish['is_featured'])): ?>
          <span class="badge"><?= e(__('common.featured')) ?></span>
          <?php endif; ?>
          <span class="badge <?= $available ? 'badge-success' : 'badge-danger' ?>">
            <?= e($available ? __('dish.available_now') : __('menu.unavailable')) ?>
          </span>
        </div>

        <h1><?= e(item_name($dish)) ?></h1>
        <p><?= e(item_description($dish)) ?></p>

        <div class="product-price-row">
          <span class="product-price"><?= money($unitCents) ?></span>
          <span class="muted small"><?= e(__('dish.price_each')) ?></span>
        </div>

        <?php if ($available): ?>
        <form class="product-form" id="order-form" method="post" action="<?= e(url('cart.php')) ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="add">
          <input type="hidden" name="item_id" value="<?= (int) $dish['id'] ?>">

          <div class="field">
            <span class="label-text"><?= e(__('dish.quantity')) ?></span>
            <div class="qty">
              <button type="button" data-qty="down" aria-label="<?= e(__('common.quantity')) ?> −"><?= icon('minus') ?></button>
              <input type="number" name="quantity" value="1" min="1" max="99" inputmode="numeric"
                     data-qty-input data-unit-price="<?= $unitCents ?>"
                     aria-label="<?= e(__('dish.quantity')) ?>">
              <button type="button" data-qty="up" aria-label="<?= e(__('common.quantity')) ?> +"><?= icon('plus') ?></button>
            </div>
          </div>

          <div class="product-actions">
            <button class="btn" type="submit" name="return" value="menu-item.php?id=<?= (int) $mainDish['id'] ?>">
              <?= icon('cart') ?><span><?= e(__('common.add_to_cart')) ?></span>
            </button>
            <button class="btn btn-dark" type="submit" name="return" value="checkout.php">
              <?= e(__('dish.buy_now')) ?><?= icon('arrow-right') ?>
            </button>
          </div>
        </form>
        <?php else: ?>
        <div class="alert alert-warning"><?= icon('alert') ?><div><?= e(__('dish.unavailable_note')) ?></div></div>
        <?php endif; ?>

        <div class="product-note" style="margin-top:16px">
          <?= icon('truck') ?>
          <span><?= e(__('checkout.delivery_only_note')) ?></span>
        </div>

        <?php $waLink = whatsapp_link(__('nav.whatsapp')); ?>
        <?php if ($waLink !== null): ?>
        <a class="btn btn-whatsapp" style="margin-top:12px" href="<?= e($waLink) ?>" target="_blank" rel="noopener">
          <?= wa_icon() ?><span><?= e(__('nav.whatsapp')) ?></span>
        </a>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($companions !== []): ?>
    <section class="companion" aria-labelledby="companion-title">
      <h2 id="companion-title" style="font-size:var(--fs-lg)"><?= e(__('dish.companion_title')) ?></h2>
      <p class="small muted"><?= e(__('dish.companion_text')) ?></p>
      <div class="companion-list">
        <?php foreach ($companions as $extra): ?>
        <div class="companion-item">
          <img src="<?= e(menu_image_url($extra['image_path'])) ?>" alt="<?= e(item_name($extra)) ?>" width="52" height="52" loading="lazy">
          <div class="ci-body">
            <strong><?= e(item_name($extra)) ?></strong>
            <span><?= money_from_decimal((string) $extra['price']) ?></span>
          </div>
          <form method="post" action="<?= e(url('cart.php')) ?>" data-cart-add data-item-id="<?= (int) $extra['id'] ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="item_id" value="<?= (int) $extra['id'] ?>">
            <input type="hidden" name="quantity" value="1">
            <input type="hidden" name="return" value="menu-item.php?id=<?= (int) $mainDish['id'] ?>">
            <button class="add-btn" type="submit" aria-label="<?= e(__('common.add_to_cart') . ': ' . item_name($extra)) ?>"><?= icon('plus') ?></button>
          </form>
        </div>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <?php if ($related !== []): ?>
    <section class="section-tight" aria-labelledby="related-title">
      <div class="section-head"><h2 id="related-title" style="font-size:var(--fs-xl)"><?= e(__('dish.related_title')) ?></h2></div>
      <div class="dish-grid">
        <?php foreach ($related as $relatedDish):
            $dish = $relatedDish;
            $showCategory = false;
            require DH_ROOT . '/includes/partials/dish-card.php';
        endforeach; ?>
      </div>
    </section>
    <?php endif; ?>
  </div>
</main>

<?php if ($available): ?>
<div class="detail-sticky">
  <span class="price" data-detail-total><?= money($unitCents) ?></span>
  <button class="btn" type="submit" form="order-form" name="return" value="menu-item.php?id=<?= (int) $mainDish['id'] ?>">
    <?= e(__('common.add_to_cart')) ?>
  </button>
  <button class="btn btn-dark" type="submit" form="order-form" name="return" value="checkout.php">
    <?= e(__('dish.buy_now')) ?>
  </button>
</div>
<?php endif; ?>
<?php require DH_ROOT . '/includes/partials/site-footer.php'; ?>
