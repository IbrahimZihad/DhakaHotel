<?php
/**
 * PWA installation instructions (Android/Chrome and iPhone/Safari).
 */

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$pageTitle = __('pwa.install_title');
$pageDescription = __('pwa.install_text');
$activeNav = '';
require DH_ROOT . '/includes/partials/site-head.php';
require DH_ROOT . '/includes/partials/site-header.php';
?>
<main id="main">
  <div class="page-head">
    <div class="container">
      <span class="eyebrow"><?= icon('download') ?><?= e(__('pwa.install')) ?></span>
      <h1><?= e(__('pwa.install_title')) ?></h1>
      <p><?= e(__('pwa.install_text')) ?></p>
    </div>
  </div>

  <div class="container section-tight">
    <div class="checkout-layout">
      <section class="card card-pad">
        <h2 style="font-size:var(--fs-lg);margin-bottom:14px"><?= e(__('pwa.steps_title')) ?></h2>
        <div class="feature-grid">
          <div class="feature">
            <span class="icon-tile"><?= icon('tablet') ?></span>
            <h3><?= e(__('pwa.android_title')) ?></h3>
            <p><?= e(__('pwa.android_steps')) ?></p>
          </div>
          <div class="feature">
            <span class="icon-tile amber"><?= icon('download') ?></span>
            <h3><?= e(__('pwa.ios_title')) ?></h3>
            <p><?= e(__('pwa.ios_steps')) ?></p>
          </div>
        </div>
        <div class="alert alert-info" style="margin-top:18px">
          <?= icon('info') ?>
          <div><?= e(__('pwa.cached_note')) ?></div>
        </div>
      </section>

      <aside class="checkout-aside">
        <div class="card card-pad">
          <h2 style="font-size:var(--fs-lg);margin-bottom:10px"><?= e(__('pwa.install_title')) ?></h2>
          <p class="small muted"><?= e(__('pwa.not_supported')) ?></p>
          <button class="btn btn-block" type="button" data-install-pwa data-install-page="<?= e(url('install.php')) ?>" style="margin-top:14px">
            <?= icon('download') ?><span><?= e(__('pwa.install_now')) ?></span>
          </button>
          <a class="btn btn-ghost btn-block" href="<?= e(url('menu.php')) ?>" style="margin-top:8px"><?= e(__('nav.menu')) ?></a>
        </div>
      </aside>
    </div>
  </div>
</main>
<?php require DH_ROOT . '/includes/partials/site-footer.php'; ?>
