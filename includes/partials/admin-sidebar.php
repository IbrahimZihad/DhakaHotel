<?php
/**
 * Admin sidebar navigation. Expects $activeNav from admin-head.php.
 */
$activeNav = $activeNav ?? '';
$pendingOrderCount = (int) fetch_value(
    "SELECT COUNT(*) FROM orders WHERE status = 'pending' AND source = 'web'",
    [],
    0
);
$pendingReviewCount = (int) fetch_value("SELECT COUNT(*) FROM reviews WHERE status = 'pending'", [], 0);

$navItem = static function (string $key, string $href, string $icon, string $label, int $badge = 0) use ($activeNav): void {
    $current = $activeNav === $key;
    echo '<a href="' . e(url($href)) . '"' . ($current ? ' aria-current="page"' : '') . '>'
        . icon($icon)
        . '<span>' . e($label) . '</span>'
        . ($badge > 0 ? '<span class="badge badge-warning">' . e(bn_digits((string) $badge)) . '</span>' : '')
        . '</a>';
};
?>
<aside class="admin-sidebar" id="admin-sidebar">
  <nav class="sidebar-nav" aria-label="<?= e(__('admin.brand_owner')) ?>">
    <span class="sidebar-title"><?= e(__('admin.nav_overview')) ?></span>
    <?php
    $navItem('dashboard', 'admin/index.php', 'grid', __('admin.nav_overview'));
    $navItem('pos', 'admin/billing.php', 'tablet', __('admin.nav_billing'));
    ?>

    <span class="sidebar-title"><?= e(__('admin.group_operations')) ?></span>
    <?php
    $navItem('orders', 'admin/orders.php', 'receipt', __('admin.nav_orders'), $pendingOrderCount);
    $navItem('menu', 'admin/menu.php', 'utensils', __('admin.nav_menu'));
    $navItem('categories', 'admin/categories.php', 'tag', __('admin.nav_categories'));
    $navItem('customers', 'admin/customers.php', 'users', __('admin.nav_customers'));
    $navItem('reviews', 'admin/reviews.php', 'star', __('admin.nav_reviews'), $pendingReviewCount);
    $navItem('reports', 'admin/reports.php', 'chart', __('admin.nav_reports'));
    ?>

    <span class="sidebar-title"><?= e(__('admin.group_system')) ?></span>
    <?php
    $navItem('notifications', 'admin/notifications.php', 'bell', __('admin.nav_notifications'));
    $navItem('settings', 'admin/settings.php', 'settings', __('admin.nav_settings'));
    $navItem('profile', 'admin/profile.php', 'user', __('admin.nav_profile'));
    ?>

    <div class="divider" style="height:1px;background:#e2eae2;margin:10px 4px" aria-hidden="true"></div>
    <a href="<?= e(url('index.php')) ?>" target="_blank" rel="noopener"><?= icon('external') ?><span><?= e(__('admin.nav_view_site')) ?></span></a>
    <form method="post" action="<?= e(url('admin/logout.php')) ?>">
      <?= csrf_field() ?>
      <button class="sidebar-nav-link" type="submit" style="display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;font-size:var(--fs-sm);font-weight:600;color:#b3261e;min-height:42px;width:100%">
        <?= icon('logout') ?><span><?= e(__('admin.nav_logout')) ?></span>
      </button>
    </form>
  </nav>
</aside>
<div class="sidebar-backdrop" data-sidebar-backdrop></div>
