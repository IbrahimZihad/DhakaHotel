<?php
/**
 * Admin top bar. Uses $adminLayout from admin-head.php.
 */
$adminLayout = $adminLayout ?? 'sidebar';
$topbarAdmin = admin_user();
?>
<header class="admin-topbar">
  <?php if ($adminLayout !== 'pos'): ?>
  <button class="sidebar-toggle" type="button" data-sidebar-toggle aria-controls="admin-sidebar" aria-expanded="false">
    <?= icon('menu') ?>
    <span class="sr-only"><?= e(__('nav.menu_open')) ?></span>
  </button>
  <?php endif; ?>

  <a class="brand" href="<?= e(url('admin/index.php')) ?>">
    <span class="brand-mark"><?= brand_mark_svg() ?></span>
    <span class="brand-name"><span><?= e(restaurant_name()) ?></span><small><?= e(__('admin.brand_owner')) ?></small></span>
  </a>

  <span class="spacer"></span>

  <?php if ($adminLayout === 'pos'): ?>
  <a class="topbar-link" href="<?= e(url('admin/index.php')) ?>">
    <?= icon('chevron-left') ?><span><?= e(__('admin.nav_overview')) ?></span>
  </a>
  <?php else: ?>
  <a class="topbar-link" href="<?= e(url('admin/billing.php')) ?>">
    <?= icon('tablet') ?><span><?= e(__('admin.nav_billing')) ?></span>
  </a>
  <a class="topbar-link topbar-site" href="<?= e(url('index.php')) ?>" target="_blank" rel="noopener">
    <?= icon('external') ?><span><?= e(__('admin.view_site')) ?></span>
  </a>
  <?php endif; ?>

  <?php
  $class = '';
  $id = 'lang-admin';
  require DH_ROOT . '/includes/partials/lang-switcher.php';
  ?>

  <div class="topbar-user">
    <?= icon('user') ?>
    <strong><?= e((string) ($topbarAdmin['name'] ?? '')) ?></strong>
  </div>
</header>
