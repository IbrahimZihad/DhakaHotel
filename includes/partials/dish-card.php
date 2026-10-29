<?php
/**
 * Dish card. Expects $dish array; optional $showCategory = true.
 */
$dish = $dish ?? [];
$dishId = (int) ($dish['id'] ?? 0);
$dishName = item_name($dish);
$dishDesc = item_description($dish);
$available = (int) ($dish['is_available'] ?? 0) === 1;
$image = menu_image_url($dish['image_path'] ?? null);
$detailUrl = url('menu-item.php?id=' . $dishId);
$showCategory = $showCategory ?? false;
$categoryName = '';
if ($showCategory && isset($dish['category_name_bn'])) {
    $categoryName = localized($dish, 'category_name');
}
?>
<article class="dish-card<?= $available ? '' : ' is-unavailable' ?>">
  <a class="dish-media" href="<?= e($detailUrl) ?>" tabindex="-1" aria-hidden="true">
    <img src="<?= e($image) ?>" alt="<?= e($dishName) ?>" width="600" height="450" loading="lazy" decoding="async">
    <?php if (!empty($dish['is_featured'])): ?>
    <span class="badge"><?= e(__('common.featured')) ?></span>
    <?php endif; ?>
    <?php if (!$available): ?>
    <span class="badge badge-danger"><?= e(__('menu.unavailable')) ?></span>
    <?php endif; ?>
  </a>
  <div class="dish-body">
    <?php if ($categoryName !== ''): ?>
    <span class="small muted"><?= e($categoryName) ?></span>
    <?php endif; ?>
    <h3><a href="<?= e($detailUrl) ?>"><?= e($dishName) ?></a></h3>
    <?php if ($dishDesc !== ''): ?>
    <p class="dish-desc"><?= e($dishDesc) ?></p>
    <?php endif; ?>
    <div class="dish-foot">
      <span class="price"><?= money_from_decimal((string) $dish['price']) ?></span>
      <?php if ($available): ?>
      <form method="post" action="<?= e(url('cart.php')) ?>" data-cart-add data-item-id="<?= $dishId ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="item_id" value="<?= $dishId ?>">
        <input type="hidden" name="quantity" value="1">
        <input type="hidden" name="return" value="<?= e(basename($_SERVER['SCRIPT_NAME'] ?? 'menu.php') . (isset($_GET['category']) ? '?category=' . urlencode((string) $_GET['category']) : '')) ?>">
        <button class="add-btn" type="submit" aria-label="<?= e(__('common.add_to_cart') . ': ' . $dishName) ?>">
          <?= icon('plus') ?>
        </button>
      </form>
      <?php else: ?>
      <button class="add-btn" type="button" disabled aria-label="<?= e(__('menu.unavailable')) ?>"><?= icon('plus') ?></button>
      <?php endif; ?>
    </div>
  </div>
</article>
