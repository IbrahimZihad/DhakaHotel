<?php
/**
 * About page — content comes from restaurant settings, nothing invented.
 */

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$pageTitle = __('about.title');
$pageDescription = restaurant('description');
$activeNav = 'about';
require DH_ROOT . '/includes/partials/site-head.php';
require DH_ROOT . '/includes/partials/site-header.php';
require DH_ROOT . '/includes/partials/flash.php';
?>
<main id="main">
  <div class="page-head">
    <div class="container">
      <span class="eyebrow"><?= e(restaurant_name()) ?></span>
      <h1><?= e(__('about.title')) ?></h1>
    </div>
  </div>

  <div class="container section-tight">
    <div class="split">
      <div>
        <p style="font-size:var(--fs-md);color:var(--ink-700)"><?= e(restaurant('description')) ?></p>
        <div class="stack" style="margin-top:22px">
          <div class="feature">
            <span class="icon-tile"><?= icon('cooking-pot') ?></span>
            <h3><?= e(__('about.value_1_title')) ?></h3>
            <p><?= e(__('about.value_1_text')) ?></p>
          </div>
          <div class="feature">
            <span class="icon-tile amber"><?= icon('clock') ?></span>
            <h3><?= e(__('about.value_2_title')) ?></h3>
            <p><?= e(__('about.value_2_text')) ?></p>
          </div>
          <div class="feature">
            <span class="icon-tile green"><?= icon('moped') ?></span>
            <h3><?= e(__('about.value_3_title')) ?></h3>
            <p><?= e(__('about.value_3_text')) ?></p>
          </div>
        </div>
      </div>
      <img src="<?= e(asset('assets/images/menu/kacchi-biryani.svg')) ?>" alt="<?= e(__('home.hero_image_alt')) ?>" width="1200" height="900" loading="lazy">
    </div>

    <div class="grid-2" style="display:grid;gap:18px;margin-top:32px">
      <div class="card card-pad">
        <h2 style="font-size:var(--fs-lg);margin-bottom:10px"><?= e(__('about.hours_title')) ?></h2>
        <p class="muted"><?= e(restaurant('hours') !== '' ? restaurant('hours') : __('common.not_configured')) ?></p>
      </div>
      <div class="card card-pad">
        <h2 style="font-size:var(--fs-lg);margin-bottom:10px"><?= e(__('about.contact_cta')) ?></h2>
        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:10px">
          <a class="btn" href="<?= e(url('contact.php')) ?>"><?= icon('phone') ?><span><?= e(__('nav.contact')) ?></span></a>
          <a class="btn btn-secondary" href="<?= e(url('menu.php')) ?>"><?= e(__('nav.order_now')) ?></a>
        </div>
      </div>
    </div>

    <p class="small muted" style="margin-top:22px"><?= e(__('about.demo_note')) ?></p>
  </div>
</main>
<?php require DH_ROOT . '/includes/partials/site-footer.php'; ?>
