<?php
/**
 * Homepage: hero, categories, featured and popular dishes, delivery info,
 * approved reviews and contact calls to action.
 */

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$categories = fetch_all(
    'SELECT c.* FROM categories c WHERE c.is_active = 1 ORDER BY c.sort_order ASC, c.name_en ASC'
);

$featured = fetch_all(
    'SELECT mi.*, c.name_bn AS category_name_bn, c.name_en AS category_name_en
     FROM menu_items mi
     JOIN categories c ON c.id = mi.category_id
     WHERE mi.is_featured = 1 AND mi.is_available = 1 AND c.is_active = 1
     ORDER BY mi.sort_order ASC LIMIT 8'
);

$popular = fetch_all(
    "SELECT mi.*, c.name_bn AS category_name_bn, c.name_en AS category_name_en, SUM(oi.quantity) AS sold
     FROM order_items oi
     JOIN orders o ON o.id = oi.order_id
     JOIN menu_items mi ON mi.id = oi.menu_item_id
     JOIN categories c ON c.id = mi.category_id
     WHERE o.status <> 'cancelled' AND mi.is_available = 1 AND c.is_active = 1
       AND o.created_at >= (NOW() - INTERVAL 30 DAY)
     GROUP BY mi.id, c.name_bn, c.name_en
     ORDER BY sold DESC, mi.is_featured DESC
     LIMIT 4"
);

$reviews = fetch_all(
    'SELECT * FROM reviews WHERE status = ? ORDER BY created_at DESC LIMIT 3',
    ['approved']
);

$dishCount = (int) fetch_value('SELECT COUNT(*) FROM menu_items WHERE is_available = 1', [], 0);
$categoryCount = count($categories);

$pageTitle = '';
$pageDescription = restaurant('description');
$activeNav = 'home';
require DH_ROOT . '/includes/partials/site-head.php';
require DH_ROOT . '/includes/partials/site-header.php';
require DH_ROOT . '/includes/partials/flash.php';
?>
<main id="main">
  <section class="hero">
    <div class="container">
      <div class="hero-card">
        <div class="hero-copy">
          <span class="eyebrow"><?= icon('sparkles') ?><?= e(__('home.hero_eyebrow')) ?></span>
          <h1><?= e(__('home.hero_title')) ?></h1>
          <p><?= e(__('home.hero_text')) ?></p>
          <div class="hero-actions">
            <a class="btn btn-lg" href="<?= e(url('menu.php')) ?>"><?= icon('utensils') ?><span><?= e(__('home.hero_cta_menu')) ?></span></a>
            <a class="btn btn-secondary btn-lg" href="<?= e(url('track-order.php')) ?>"><?= icon('receipt') ?><span><?= e(__('nav.track')) ?></span></a>
          </div>
          <div class="hero-meta">
            <span class="badge badge-success"><?= icon('clock') ?><?= e(__('home.stat_delivery')) ?>: <?= e(__('home.stat_delivery_value')) ?></span>
            <span class="badge badge-soft"><?= e(__('home.min_order', ['amount' => money(minimum_order_cents())])) ?></span>
          </div>
        </div>
        <div class="hero-visual">
          <img src="<?= e(asset('assets/images/hero.svg')) ?>" alt="<?= e(__('home.hero_image_alt')) ?>" width="1200" height="900" fetchpriority="high">
          <div class="hero-float">
            <?= icon('moped') ?>
            <span><?= e(__('home.delivery_fee', ['amount' => money(delivery_fee_cents())])) ?></span>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="section-tight">
    <div class="container">
      <div class="section-head">
        <div>
          <h2><?= e(__('home.categories_title')) ?></h2>
          <p><?= e(__('home.categories_text')) ?></p>
        </div>
        <a class="link-arrow" href="<?= e(url('menu.php')) ?>"><?= e(__('common.view_all')) ?><?= icon('arrow-right') ?></a>
      </div>
      <div class="chip-row">
        <?php foreach ($categories as $category): ?>
        <a class="chip" href="<?= e(url('menu.php') . '?category=' . urlencode((string) $category['slug'])) ?>">
          <?= icon('utensils') ?><?= e(item_name($category)) ?>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <?php if ($featured !== []): ?>
  <section class="section-tight">
    <div class="container">
      <div class="section-head">
        <div>
          <h2><?= e(__('home.featured_title')) ?></h2>
          <p><?= e(__('home.featured_text')) ?></p>
        </div>
        <a class="link-arrow" href="<?= e(url('menu.php')) ?>"><?= e(__('common.view_all')) ?><?= icon('arrow-right') ?></a>
      </div>
      <div class="dish-grid">
        <?php foreach ($featured as $dish):
            $showCategory = true;
            require DH_ROOT . '/includes/partials/dish-card.php';
        endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($popular !== []): ?>
  <section class="section-tight">
    <div class="container">
      <div class="section-head">
        <div>
          <h2><?= e(__('home.popular_title')) ?></h2>
          <p><?= e(__('admin.popular_items_note')) ?></p>
        </div>
      </div>
      <div class="dish-grid">
        <?php foreach ($popular as $dish):
            $showCategory = true;
            require DH_ROOT . '/includes/partials/dish-card.php';
        endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <section class="section">
    <div class="container">
      <div class="split">
        <div>
          <span class="eyebrow"><?= icon('flame') ?><?= e(__('home.intro_title')) ?></span>
          <h2 style="font-size:var(--fs-2xl);margin-block:10px 12px"><?= e(restaurant_name()) ?></h2>
          <p class="muted"><?= e(restaurant('description')) ?></p>
          <div class="stat-row" style="margin-top:22px">
            <div class="stat"><b><?= e(is_bn() ? bn_digits((string) $dishCount) : (string) $dishCount) ?></b><span><?= e(__('home.stat_dishes')) ?></span></div>
            <div class="stat"><b><?= e(is_bn() ? bn_digits((string) $categoryCount) : (string) $categoryCount) ?></b><span><?= e(__('home.stat_categories')) ?></span></div>
            <div class="stat"><b><?= e(__('home.stat_delivery_value')) ?></b><span><?= e(__('home.stat_delivery')) ?></span></div>
          </div>
        </div>
        <img src="<?= e(asset('assets/images/menu/chicken-biryani.svg')) ?>" alt="<?= e(__('home.hero_image_alt')) ?>" width="1200" height="900" loading="lazy">
      </div>
    </div>
  </section>

  <section class="section-tight">
    <div class="container">
      <div class="section-head">
        <h2><?= e(__('home.how_title')) ?></h2>
      </div>
      <div class="feature-grid">
        <div class="feature">
          <span class="icon-tile"><?= icon('utensils') ?></span>
          <h3><?= e(__('home.how_1_title')) ?></h3>
          <p><?= e(__('home.how_1_text')) ?></p>
        </div>
        <div class="feature">
          <span class="icon-tile amber"><?= icon('map-pin') ?></span>
          <h3><?= e(__('home.how_2_title')) ?></h3>
          <p><?= e(__('home.how_2_text')) ?></p>
        </div>
        <div class="feature">
          <span class="icon-tile green"><?= icon('moped') ?></span>
          <h3><?= e(__('home.how_3_title')) ?></h3>
          <p><?= e(__('home.how_3_text')) ?></p>
        </div>
      </div>
    </div>
  </section>

  <section class="section-tight">
    <div class="container">
      <div class="grid-2" style="display:grid;gap:20px">
        <div class="card card-pad">
          <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px">
            <span class="icon-tile"><?= icon('clock') ?></span>
            <h2 style="font-size:var(--fs-lg)"><?= e(__('home.hours_title')) ?></h2>
          </div>
          <p class="muted" style="font-size:var(--fs-sm)"><?= e(restaurant('hours') !== '' ? restaurant('hours') : __('common.not_configured')) ?></p>
        </div>
        <div class="card card-pad">
          <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px">
            <span class="icon-tile green"><?= icon('truck') ?></span>
            <h2 style="font-size:var(--fs-lg)"><?= e(__('home.delivery_title')) ?></h2>
          </div>
          <ul class="hours-list">
            <li><strong><?= e(__('home.min_order', ['amount' => money(minimum_order_cents())])) ?></strong></li>
            <li><strong><?= e(__('home.delivery_fee', ['amount' => money(delivery_fee_cents())])) ?></strong></li>
            <li><span><?= e(__('checkout.delivery_only_note')) ?></span></li>
          </ul>
        </div>
      </div>
    </div>
  </section>

  <?php if ($reviews !== []): ?>
  <section class="section-tight">
    <div class="container">
      <div class="section-head">
        <div>
          <h2><?= e(__('home.reviews_title')) ?></h2>
          <p><?= e(__('home.reviews_text')) ?></p>
        </div>
        <a class="link-arrow" href="<?= e(url('reviews.php')) ?>"><?= e(__('common.view_all')) ?><?= icon('arrow-right') ?></a>
      </div>
      <div class="review-grid">
        <?php foreach ($reviews as $review): ?>
        <article class="review-card">
          <div style="display:flex;justify-content:space-between;gap:8px;align-items:center">
            <?= stars_html((float) $review['rating']) ?>
            <?php if ((int) $review['is_demo'] === 1): ?><span class="badge badge-demo"><?= e(__('reviews.demo_badge')) ?></span><?php endif; ?>
          </div>
          <p><?= e((string) $review['review_text']) ?></p>
          <footer>
            <span class="avatar"><?= e(mb_strtoupper(mb_substr((string) $review['display_name'], 0, 1))) ?></span>
            <span><?= e((string) $review['display_name']) ?> · <?= e(format_date($review['created_at'])) ?></span>
          </footer>
        </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <section class="section">
    <div class="container">
      <div class="hero-card" style="text-align:center;display:block">
        <h2 style="font-size:var(--fs-2xl)"><?= e(__('home.cta_title')) ?></h2>
        <p class="muted" style="margin-top:8px"><?= e(__('home.cta_text')) ?></p>
        <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;margin-top:20px">
          <a class="btn btn-lg" href="<?= e(url('menu.php')) ?>"><?= e(__('home.hero_cta_order')) ?><?= icon('arrow-right') ?></a>
          <?php $waLink = whatsapp_link(__('nav.whatsapp')); ?>
          <?php if ($waLink !== null): ?>
          <a class="btn btn-whatsapp btn-lg" href="<?= e($waLink) ?>" target="_blank" rel="noopener"><?= wa_icon() ?><span><?= e(__('contact.whatsapp')) ?></span></a>
          <?php else: ?>
          <span class="small muted" style="align-self:center"><?= e(__('home.no_whatsapp')) ?></span>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>
</main>
<?php require DH_ROOT . '/includes/partials/site-footer.php'; ?>
