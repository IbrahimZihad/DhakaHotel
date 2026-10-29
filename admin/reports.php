<?php
/**
 * Sales reports over a date range. Revenue is always confirmed payments,
 * kept separate from order totals that are still unpaid.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

$today = date('Y-m-d');
$preset = (string) ($_GET['period'] ?? '7');
$from = v_date((string) ($_GET['from'] ?? '')) ? (string) $_GET['from'] : date('Y-m-d', strtotime('-6 days'));
$to = v_date((string) ($_GET['to'] ?? '')) ? (string) $_GET['to'] : $today;

if ($preset === 'today') {
    $from = $to = $today;
} elseif ($preset === '7') {
    $to = $today;
    $from = date('Y-m-d', strtotime('-6 days'));
} elseif ($preset === '30') {
    $to = $today;
    $from = date('Y-m-d', strtotime('-29 days'));
} elseif ($preset !== 'custom') {
    $preset = 'custom';
}

if ($from > $to) {
    [$from, $to] = [$to, $from];
}

if (strtotime($from) < strtotime($to . ' -366 days')) {
    $from = date('Y-m-d', strtotime($to . ' -365 days'));
}

$range = [$from, $to];

$ordersSummary = fetch_one(
    "SELECT COUNT(*) AS total,
            SUM(CASE WHEN status <> 'cancelled' THEN 1 ELSE 0 END) AS active,
            COALESCE(SUM(CASE WHEN status <> 'cancelled' THEN grand_total ELSE 0 END), 0) AS gross,
            COALESCE(SUM(CASE WHEN status <> 'cancelled' THEN amount_paid ELSE 0 END), 0) AS collected,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled
     FROM orders WHERE DATE(created_at) BETWEEN ? AND ?",
    $range
);
$paidOrders = (int) fetch_value(
    "SELECT COUNT(*) FROM orders WHERE DATE(created_at) BETWEEN ? AND ? AND payment_status = 'paid'",
    $range,
    0
);
$unpaidOrders = (int) fetch_value(
    "SELECT COUNT(*) FROM orders WHERE DATE(created_at) BETWEEN ? AND ? AND payment_status IN ('unpaid', 'partial') AND status <> 'cancelled'",
    $range,
    0
);
$paidRevenue = cents_from_decimal((string) fetch_value(
    "SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'confirmed' AND DATE(confirmed_at) BETWEEN ? AND ?",
    $range,
    '0.00'
));
$unpaidOutstanding = cents_from_decimal((string) fetch_value(
    "SELECT COALESCE(SUM(grand_total - amount_paid), 0) FROM orders
     WHERE DATE(created_at) BETWEEN ? AND ? AND status <> 'cancelled' AND payment_status IN ('unpaid', 'partial')",
    $range,
    '0.00'
));
$deliveryOrders = (int) fetch_value(
    "SELECT COUNT(*) FROM orders WHERE DATE(created_at) BETWEEN ? AND ? AND order_type = 'delivery' AND status <> 'cancelled'",
    $range,
    0
);

$statusRows = fetch_all(
    'SELECT status, COUNT(*) AS total FROM orders WHERE DATE(created_at) BETWEEN ? AND ? GROUP BY status',
    $range
);
$statusCounts = [];
foreach (ORDER_STATUSES as $candidate) {
    $statusCounts[$candidate] = 0;
}
foreach ($statusRows as $row) {
    $statusCounts[(string) $row['status']] = (int) $row['total'];
}
$statusMax = max(1, ...array_values($statusCounts));

$methodRows = fetch_all(
    "SELECT method, COUNT(*) AS payments, COALESCE(SUM(amount), 0) AS total
     FROM payments WHERE status = 'confirmed' AND DATE(confirmed_at) BETWEEN ? AND ?
     GROUP BY method ORDER BY total DESC",
    $range
);
$methodMax = 1;
foreach ($methodRows as $row) {
    $methodMax = max($methodMax, cents_from_decimal((string) $row['total']));
}

$popular = fetch_all(
    "SELECT oi.name_bn, oi.name_en, SUM(oi.quantity) AS sold, COALESCE(SUM(oi.line_total), 0) AS revenue
     FROM order_items oi JOIN orders o ON o.id = oi.order_id
     WHERE DATE(o.created_at) BETWEEN ? AND ? AND o.status <> 'cancelled'
     GROUP BY oi.name_bn, oi.name_en ORDER BY sold DESC LIMIT 8",
    $range
);
$popularMax = 1;
foreach ($popular as $row) {
    $popularMax = max($popularMax, (int) $row['sold']);
}

$dailyGross = fetch_all(
    "SELECT DATE(created_at) AS day, COUNT(*) AS orders, COALESCE(SUM(grand_total), 0) AS gross
     FROM orders WHERE DATE(created_at) BETWEEN ? AND ? AND status <> 'cancelled'
     GROUP BY DATE(created_at) ORDER BY day",
    $range
);
$dailyPaidRows = fetch_all(
    "SELECT DATE(confirmed_at) AS day, COALESCE(SUM(amount), 0) AS paid
     FROM payments WHERE status = 'confirmed' AND DATE(confirmed_at) BETWEEN ? AND ?
     GROUP BY DATE(confirmed_at)",
    $range
);
$paidByDay = [];
foreach ($dailyPaidRows as $row) {
    $paidByDay[(string) $row['day']] = cents_from_decimal((string) $row['paid']);
}

$grossByDay = [];
foreach ($dailyGross as $row) {
    $day = (string) $row['day'];
    $grossByDay[$day] = [
        'orders' => (int) $row['orders'],
        'gross' => (string) $row['gross'],
        'paid' => $paidByDay[$day] ?? 0,
    ];
    unset($paidByDay[$day]);
}
foreach ($paidByDay as $day => $paid) {
    $grossByDay[$day] = ['orders' => 0, 'gross' => '0.00', 'paid' => $paid];
}
ksort($grossByDay);

$orderCount = (int) $ordersSummary['total'];
$activeCount = (int) $ordersSummary['active'];
$avgOrder = $activeCount > 0
    ? (int) round(cents_from_decimal((string) $ordersSummary['gross']) / $activeCount)
    : 0;

$exportQuery = http_build_query(['from' => $from, 'to' => $to]);

$pageTitle = __('admin.reports_title');
$activeNav = 'reports';
require DH_ROOT . '/includes/partials/admin-head.php';
?>
<div class="admin-head">
  <div>
    <h1><?= e(__('admin.reports_title')) ?></h1>
    <p><?= e(__('admin.reports_subtitle')) ?></p>
  </div>
  <div class="admin-head-actions">
    <a class="btn btn-secondary" href="<?= e(url('admin/reports-export.php?' . $exportQuery)) ?>"><?= icon('download') ?><span><?= e(__('admin.export_csv')) ?></span></a>
    <button class="btn" type="button" data-print><?= icon('printer') ?><span><?= e(__('admin.print_report')) ?></span></button>
  </div>
</div>

<div class="panel no-print" style="margin-bottom:16px">
  <form class="filter-bar" method="get" action="<?= e(url('admin/reports.php')) ?>" style="border-bottom:0">
    <div class="field">
      <label for="period"><?= e(__('admin.report_period')) ?></label>
      <select class="select" id="period" name="period">
        <option value="today" <?= $preset === 'today' ? 'selected' : '' ?>><?= e(__('admin.period_today')) ?></option>
        <option value="7" <?= $preset === '7' ? 'selected' : '' ?>><?= e(__('admin.period_7')) ?></option>
        <option value="30" <?= $preset === '30' ? 'selected' : '' ?>><?= e(__('admin.period_30')) ?></option>
        <option value="custom" <?= $preset === 'custom' ? 'selected' : '' ?>><?= e(__('admin.period_custom')) ?></option>
      </select>
    </div>
    <div class="field">
      <label for="from"><?= e(__('admin.filter_from')) ?></label>
      <input class="input" type="date" id="from" name="from" value="<?= e($from) ?>">
    </div>
    <div class="field">
      <label for="to"><?= e(__('admin.filter_to')) ?></label>
      <input class="input" type="date" id="to" name="to" value="<?= e($to) ?>">
    </div>
    <button class="btn" type="submit"><?= icon('filter') ?><span><?= e(__('common.apply')) ?></span></button>
  </form>
</div>

<p class="small muted" style="margin-bottom:16px">
  <?= e(__('admin.date_range')) ?>: <strong><?= e(format_date($from)) ?></strong> — <strong><?= e(format_date($to)) ?></strong>
  · <?= e(__('admin.export_note')) ?>
</p>

<div class="metric-grid">
  <div class="metric is-accent">
    <div class="metric-top"><span><?= e(__('admin.total_paid')) ?></span><span class="icon-tile"><?= icon('wallet') ?></span></div>
    <b><?= e(money($paidRevenue)) ?></b>
    <small><?= e(__('admin.metric_paid_note')) ?></small>
  </div>
  <div class="metric">
    <div class="metric-top"><span><?= e(__('admin.total_unpaid')) ?></span><span class="icon-tile"><?= icon('alert') ?></span></div>
    <b><?= e(money($unpaidOutstanding)) ?></b>
    <small><?= e(__('admin.metric_unpaid_note')) ?></small>
  </div>
  <div class="metric">
    <div class="metric-top"><span><?= e(__('admin.orders_in_range')) ?></span><span class="icon-tile"><?= icon('receipt') ?></span></div>
    <b><?= e(is_bn() ? bn_digits((string) $orderCount) : (string) $orderCount) ?></b>
    <small><?= e(__('admin.orders_total')) ?> · <?= e(money_from_decimal((string) $ordersSummary['gross'])) ?></small>
  </div>
  <div class="metric">
    <div class="metric-top"><span><?= e(__('admin.avg_order')) ?></span><span class="icon-tile"><?= icon('chart') ?></span></div>
    <b><?= e(money($avgOrder)) ?></b>
    <small><?= e(__('admin.revenue_note')) ?></small>
  </div>
</div>

<div class="grid-2">
  <div class="panel">
    <div class="panel-head"><h2><?= e(__('admin.paid_vs_unpaid')) ?></h2></div>
    <div class="panel-body">
      <div class="bar-list">
        <div class="bar-list-row">
          <span><?= e(__('admin.paid_orders')) ?></span>
          <span class="bar-list-track"><span class="bar-list-fill" style="width:<?= max(2, (int) round($paidOrders / max(1, $paidOrders + $unpaidOrders) * 100)) ?>%"></span></span>
          <b><?= e(is_bn() ? bn_digits((string) $paidOrders) : (string) $paidOrders) ?></b>
        </div>
        <div class="bar-list-row">
          <span><?= e(__('admin.unpaid_orders')) ?></span>
          <span class="bar-list-track"><span class="bar-list-fill" style="width:<?= max(2, (int) round($unpaidOrders / max(1, $paidOrders + $unpaidOrders) * 100)) ?>%;background:#e0a800"></span></span>
          <b><?= e(is_bn() ? bn_digits((string) $unpaidOrders) : (string) $unpaidOrders) ?></b>
        </div>
        <div class="bar-list-row">
          <span><?= e(__('admin.cancelled_orders')) ?></span>
          <span class="bar-list-track"><span class="bar-list-fill" style="width:<?= max(2, (int) round((int) $ordersSummary['cancelled'] / max(1, $orderCount) * 100)) ?>%;background:#b3261e"></span></span>
          <b><?= e(is_bn() ? bn_digits((string) (int) $ordersSummary['cancelled']) : (string) (int) $ordersSummary['cancelled']) ?></b>
        </div>
        <div class="bar-list-row">
          <span><?= e(__('admin.delivery_orders')) ?></span>
          <span class="bar-list-track"><span class="bar-list-fill" style="width:<?= max(2, (int) round($deliveryOrders / max(1, $orderCount) * 100)) ?>%"></span></span>
          <b><?= e(is_bn() ? bn_digits((string) $deliveryOrders) : (string) $deliveryOrders) ?></b>
        </div>
      </div>
    </div>
  </div>

  <div class="panel">
    <div class="panel-head"><h2><?= e(__('admin.status_breakdown')) ?></h2></div>
    <div class="panel-body">
      <div class="bar-list">
        <?php foreach ($statusCounts as $statusName => $count): ?>
        <div class="bar-list-row">
          <span><?= e(order_status_label($statusName)) ?></span>
          <span class="bar-list-track"><span class="bar-list-fill" style="width:<?= (int) max(2, round($count / $statusMax * 100)) ?>%"></span></span>
          <b><?= e(is_bn() ? bn_digits((string) $count) : (string) $count) ?></b>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="panel">
    <div class="panel-head"><h2><?= e(__('admin.popular_items')) ?></h2></div>
    <div class="panel-body">
      <?php if ($popular === []): ?>
      <p class="small muted"><?= e(__('admin.no_data_range')) ?></p>
      <?php else: ?>
      <div class="bar-list">
        <?php foreach ($popular as $row): ?>
        <div class="bar-list-row">
          <span><?= e(localized($row, 'name')) ?></span>
          <span class="bar-list-track"><span class="bar-list-fill" style="width:<?= (int) max(4, round((int) $row['sold'] / $popularMax * 100)) ?>%"></span></span>
          <b><?= e(is_bn() ? bn_digits((string) (int) $row['sold']) : (string) (int) $row['sold']) ?> · <?= e(money_from_decimal((string) $row['revenue'])) ?></b>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="panel">
    <div class="panel-head"><h2><?= e(__('admin.payment_methods')) ?></h2></div>
    <div class="panel-body">
      <?php if ($methodRows === []): ?>
      <p class="small muted"><?= e(__('admin.no_data_range')) ?></p>
      <?php else: ?>
      <div class="bar-list">
        <?php foreach ($methodRows as $row): ?>
        <div class="bar-list-row">
          <span><?= e(payment_method_label((string) $row['method'])) ?></span>
          <span class="bar-list-track"><span class="bar-list-fill" style="width:<?= (int) max(4, round(cents_from_decimal((string) $row['total']) / $methodMax * 100)) ?>%"></span></span>
          <b><?= e(money_from_decimal((string) $row['total'])) ?></b>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="panel" style="margin-top:16px">
  <div class="panel-head">
    <div>
      <h2><?= e(__('admin.daily_sales')) ?></h2>
      <p><?= e(__('admin.revenue_note')) ?></p>
    </div>
  </div>
  <div class="table-wrap">
    <?php if ($grossByDay === []): ?>
    <p class="admin-empty"><?= e(__('admin.no_data_range')) ?></p>
    <?php else: ?>
    <table class="table">
      <thead>
        <tr>
          <th><?= e(__('admin.date')) ?></th>
          <th class="num"><?= e(__('admin.orders_total')) ?></th>
          <th class="num"><?= e(__('admin.order_total')) ?></th>
          <th class="num"><?= e(__('admin.total_paid')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($grossByDay as $day => $row): ?>
        <tr>
          <td class="nowrap"><?= e(format_date($day)) ?></td>
          <td class="num"><?= e(is_bn() ? bn_digits((string) $row['orders']) : (string) $row['orders']) ?></td>
          <td class="num"><?= e(money_from_decimal($row['gross'])) ?></td>
          <td class="num"><?= e(money((int) $row['paid'])) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>
<?php require DH_ROOT . '/includes/partials/admin-footer.php'; ?>
