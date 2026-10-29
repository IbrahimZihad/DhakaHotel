<?php
/**
 * Owner dashboard: today's paid sales versus unpaid totals, kitchen load,
 * a seven-day paid-sales chart, popular items and recent orders.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

$today = date('Y-m-d');

$todayOrders = fetch_one(
    "SELECT COUNT(*) AS total, COALESCE(SUM(grand_total), 0) AS grand
     FROM orders WHERE DATE(created_at) = ? AND status <> 'cancelled'",
    [$today]
);
$paidToday = cents_from_decimal((string) fetch_value(
    "SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'confirmed' AND DATE(confirmed_at) = ?",
    [$today],
    '0.00'
));
$unpaidToday = cents_from_decimal((string) fetch_value(
    "SELECT COALESCE(SUM(grand_total - amount_paid), 0) FROM orders
     WHERE DATE(created_at) = ? AND status <> 'cancelled' AND payment_status IN ('unpaid', 'partial')",
    [$today],
    '0.00'
));
$pendingOrders = (int) fetch_value("SELECT COUNT(*) FROM orders WHERE status = 'pending'", [], 0);
$inKitchen = (int) fetch_value("SELECT COUNT(*) FROM orders WHERE status IN ('confirmed', 'preparing', 'ready')", [], 0);
$customerCount = (int) fetch_value('SELECT COUNT(*) FROM customers', [], 0);

$dailyRows = fetch_all(
    "SELECT DATE(confirmed_at) AS day, SUM(amount) AS total
     FROM payments
     WHERE status = 'confirmed' AND confirmed_at >= (CURDATE() - INTERVAL 6 DAY)
     GROUP BY DATE(confirmed_at)"
);
$dailyPaid = [];
foreach ($dailyRows as $row) {
    $dailyPaid[(string) $row['day']] = cents_from_decimal((string) $row['total']);
}

$chartDays = [];
$chartMax = 1;
for ($offset = 6; $offset >= 0; $offset--) {
    $day = date('Y-m-d', strtotime('-' . $offset . ' days'));
    $value = $dailyPaid[$day] ?? 0;
    $chartMax = max($chartMax, $value);
    $chartDays[] = [
        'day' => $day,
        'label' => date('d/m', strtotime($day)),
        'value' => $value,
        'today' => $offset === 0,
    ];
}

$popular = fetch_all(
    "SELECT oi.name_bn, oi.name_en, SUM(oi.quantity) AS sold
     FROM order_items oi
     JOIN orders o ON o.id = oi.order_id
     WHERE o.status <> 'cancelled' AND o.created_at >= (NOW() - INTERVAL 30 DAY)
     GROUP BY oi.name_bn, oi.name_en
     ORDER BY sold DESC
     LIMIT 5"
);
$popularMax = 1;
foreach ($popular as $row) {
    $popularMax = max($popularMax, (int) $row['sold']);
}

$recentOrders = fetch_all('SELECT * FROM orders ORDER BY id DESC LIMIT 8');

$count = static fn (int $value): string => e(is_bn() ? bn_digits((string) $value) : (string) $value);

$pageTitle = __('admin.dashboard_title');
$activeNav = 'dashboard';
require DH_ROOT . '/includes/partials/admin-head.php';
?>
<div class="admin-head">
  <div>
    <h1><?= e(__('admin.dashboard_title')) ?></h1>
    <p><?= e(__('admin.dashboard_subtitle')) ?></p>
  </div>
  <div class="admin-head-actions">
    <a class="btn btn-secondary" href="<?= e(url('admin/orders.php')) ?>"><?= icon('receipt') ?><span><?= e(__('admin.nav_orders')) ?></span></a>
    <a class="btn" href="<?= e(url('admin/billing.php')) ?>"><?= icon('tablet') ?><span><?= e(__('pos.new_bill')) ?></span></a>
  </div>
</div>

<div class="metric-grid">
  <div class="metric is-accent">
    <div class="metric-top"><span><?= e(__('admin.metric_paid_sales')) ?></span><span class="icon-tile"><?= icon('wallet') ?></span></div>
    <b><?= e(money($paidToday)) ?></b>
    <small><?= e(__('admin.metric_paid_note')) ?></small>
  </div>
  <div class="metric">
    <div class="metric-top"><span><?= e(__('admin.metric_today_orders')) ?></span><span class="icon-tile"><?= icon('receipt') ?></span></div>
    <b><?= $count((int) $todayOrders['total']) ?></b>
    <small><?= e(__('admin.order_total')) ?>: <?= e(money_from_decimal((string) $todayOrders['grand'])) ?></small>
  </div>
  <div class="metric">
    <div class="metric-top"><span><?= e(__('admin.metric_unpaid_today')) ?></span><span class="icon-tile"><?= icon('alert') ?></span></div>
    <b><?= e(money($unpaidToday)) ?></b>
    <small><?= e(__('admin.metric_unpaid_note')) ?></small>
  </div>
  <div class="metric">
    <div class="metric-top"><span><?= e(__('admin.metric_pending_orders')) ?></span><span class="icon-tile"><?= icon('clock') ?></span></div>
    <b><?= $count($pendingOrders) ?></b>
    <small><?= e(__('admin.metric_pending_note')) ?></small>
  </div>
  <div class="metric">
    <div class="metric-top"><span><?= e(__('admin.metric_in_kitchen')) ?></span><span class="icon-tile"><?= icon('cooking-pot') ?></span></div>
    <b><?= $count($inKitchen) ?></b>
    <small><?= e(__('admin.metric_kitchen_note')) ?></small>
  </div>
  <div class="metric">
    <div class="metric-top"><span><?= e(__('admin.metric_customers')) ?></span><span class="icon-tile"><?= icon('users') ?></span></div>
    <b><?= $count($customerCount) ?></b>
    <small><?= e(__('admin.metric_customers')) ?></small>
  </div>
</div>

<div class="grid-main-side">
  <div class="panel">
    <div class="panel-head">
      <div>
        <h2><?= e(__('admin.period_7')) ?></h2>
        <p><?= e(__('admin.revenue_note')) ?></p>
      </div>
      <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/reports.php')) ?>"><?= e(__('admin.nav_reports')) ?></a>
    </div>
    <div class="panel-body">
      <div class="mini-chart" role="img" aria-label="<?= e(__('admin.period_7')) ?>">
        <?php foreach ($chartDays as $point): ?>
        <div class="bar<?= $point['today'] ? ' is-today' : '' ?>">
          <span class="value"><?= e(is_bn() ? bn_digits(number_format($point['value'] / 100)) : number_format($point['value'] / 100)) ?></span>
          <span class="fill" style="height:<?= (int) max(2, round($point['value'] / $chartMax * 100)) ?>%"></span>
          <small><?= e(is_bn() ? bn_digits($point['label']) : $point['label']) ?></small>
        </div>
        <?php endforeach; ?>
      </div>
      <p class="small muted" style="margin-top:10px">
        <?= e(__('admin.metric_paid_note')) ?> · <?= e(money($paidToday)) ?>
      </p>
    </div>
  </div>

  <div class="panel">
    <div class="panel-head">
      <div>
        <h2><?= e(__('admin.popular_30d')) ?></h2>
        <p><?= e(__('admin.popular_items_note')) ?></p>
      </div>
    </div>
    <div class="panel-body">
      <?php if ($popular === []): ?>
      <p class="muted small"><?= e(__('admin.no_data')) ?></p>
      <?php else: ?>
      <div class="bar-list">
        <?php foreach ($popular as $row):
            $sold = (int) $row['sold'];
        ?>
        <div class="bar-list-row">
          <span><?= e(localized($row, 'name')) ?></span>
          <span class="bar-list-track"><span class="bar-list-fill" style="width:<?= (int) max(4, round($sold / $popularMax * 100)) ?>%"></span></span>
          <b><?= $count($sold) ?></b>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="panel" style="margin-top:16px">
  <div class="panel-head">
    <h2><?= e(__('admin.recent_orders')) ?></h2>
    <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/orders.php')) ?>"><?= e(__('admin.view_all')) ?></a>
  </div>
  <div class="table-wrap">
    <?php if ($recentOrders === []): ?>
    <p class="admin-empty"><?= e(__('admin.no_orders')) ?></p>
    <?php else: ?>
    <table class="table responsive">
      <thead>
        <tr>
          <th><?= e(__('admin.order_ref')) ?></th>
          <th><?= e(__('common.customer')) ?></th>
          <th><?= e(__('admin.order_type')) ?></th>
          <th class="num"><?= e(__('admin.order_total')) ?></th>
          <th><?= e(__('admin.payment_status')) ?></th>
          <th><?= e(__('common.status')) ?></th>
          <th><?= e(__('admin.created')) ?></th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recentOrders as $order): ?>
        <tr>
          <td data-label="<?= e(__('admin.order_ref')) ?>"><span class="nowrap" style="font-family:ui-monospace,Menlo,monospace;font-size:0.78rem"><?= e($order['order_number']) ?></span></td>
          <td data-label="<?= e(__('common.customer')) ?>">
            <?= e((string) ($order['customer_name'] ?: __('pos.walk_in'))) ?>
            <?php if (!empty($order['customer_phone'])): ?><br><small class="muted"><?= e($order['customer_phone']) ?></small><?php endif; ?>
          </td>
          <td data-label="<?= e(__('admin.order_type')) ?>"><?= e(order_type_label((string) $order['order_type'])) ?></td>
          <td data-label="<?= e(__('admin.order_total')) ?>" class="num"><?= e(money_from_decimal((string) $order['grand_total'])) ?></td>
          <td data-label="<?= e(__('admin.payment_status')) ?>"><span class="badge <?= e(payment_status_badge_class((string) $order['payment_status'])) ?>"><?= e(payment_status_label((string) $order['payment_status'])) ?></span></td>
          <td data-label="<?= e(__('common.status')) ?>"><span class="badge <?= e(order_status_badge_class((string) $order['status'])) ?>"><?= e(order_status_label((string) $order['status'])) ?></span></td>
          <td data-label="<?= e(__('admin.created')) ?>" class="nowrap"><?= e(format_datetime($order['created_at'])) ?></td>
          <td class="actions"><a class="btn btn-ghost btn-sm" href="<?= e(url('admin/order-details.php?id=' . (int) $order['id'])) ?>"><?= e(__('common.view')) ?></a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>
<?php require DH_ROOT . '/includes/partials/admin-footer.php'; ?>
