<?php
/**
 * Order list with status/payment/date filters and pagination.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

$search = trim((string) ($_GET['q'] ?? ''));
$status = (string) ($_GET['status'] ?? '');
$payment = (string) ($_GET['payment'] ?? '');
$type = (string) ($_GET['type'] ?? '');
$from = (string) ($_GET['from'] ?? '');
$to = (string) ($_GET['to'] ?? '');

$status = in_array($status, ORDER_STATUSES, true) ? $status : '';
$payment = in_array($payment, PAYMENT_STATUSES, true) ? $payment : '';
$type = in_array($type, ORDER_TYPES, true) ? $type : '';
$from = v_date($from) ? $from : '';
$to = v_date($to) ? $to : '';

$where = [];
$params = [];

if ($search !== '') {
    $like = '%' . addcslashes($search, '%_\\') . '%';
    $where[] = "(order_number LIKE ? ESCAPE '\\\\' OR customer_name LIKE ? ESCAPE '\\\\' OR customer_phone LIKE ? ESCAPE '\\\\')";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
if ($status !== '') {
    $where[] = 'status = ?';
    $params[] = $status;
}
if ($payment !== '') {
    $where[] = 'payment_status = ?';
    $params[] = $payment;
}
if ($type !== '') {
    $where[] = 'order_type = ?';
    $params[] = $type;
}
if ($from !== '') {
    $where[] = 'DATE(created_at) >= ?';
    $params[] = $from;
}
if ($to !== '') {
    $where[] = 'DATE(created_at) <= ?';
    $params[] = $to;
}

$whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
$total = (int) fetch_value('SELECT COUNT(*) FROM orders' . $whereSql, $params, 0);
$pagination = paginate($total, 20, current_page_number());
$orders = fetch_all(
    'SELECT * FROM orders' . $whereSql . ' ORDER BY id DESC LIMIT ' . (int) $pagination['per_page'] . ' OFFSET ' . (int) $pagination['offset'],
    $params
);

$pageTitle = __('admin.orders_title');
$activeNav = 'orders';
require DH_ROOT . '/includes/partials/admin-head.php';
?>
<div class="admin-head">
  <div>
    <h1><?= e(__('admin.orders_title')) ?></h1>
    <p><?= e(__('admin.orders_subtitle')) ?></p>
  </div>
  <div class="admin-head-actions">
    <a class="btn" href="<?= e(url('admin/billing.php')) ?>"><?= icon('tablet') ?><span><?= e(__('pos.new_bill')) ?></span></a>
  </div>
</div>

<div class="panel">
  <form class="filter-bar" method="get" action="<?= e(url('admin/orders.php')) ?>" role="search">
    <div class="field grow">
      <label for="q"><?= e(__('admin.filter_search')) ?></label>
      <input class="input" type="search" id="q" name="q" value="<?= e($search) ?>" placeholder="<?= e(__('admin.order_ref')) ?> / <?= e(__('common.phone')) ?>">
    </div>
    <div class="field">
      <label for="status"><?= e(__('admin.filter_status')) ?></label>
      <select class="select" id="status" name="status">
        <option value=""><?= e(__('admin.all_statuses')) ?></option>
        <?php foreach (ORDER_STATUSES as $option): ?>
        <option value="<?= e($option) ?>" <?= $status === $option ? 'selected' : '' ?>><?= e(order_status_label($option)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="payment"><?= e(__('admin.filter_payment')) ?></label>
      <select class="select" id="payment" name="payment">
        <option value=""><?= e(__('admin.all_payments')) ?></option>
        <?php foreach (PAYMENT_STATUSES as $option): ?>
        <option value="<?= e($option) ?>" <?= $payment === $option ? 'selected' : '' ?>><?= e(payment_status_label($option)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="type"><?= e(__('admin.order_type')) ?></label>
      <select class="select" id="type" name="type">
        <option value=""><?= e(__('admin.all_types')) ?></option>
        <?php foreach (ORDER_TYPES as $option): ?>
        <option value="<?= e($option) ?>" <?= $type === $option ? 'selected' : '' ?>><?= e(order_type_label($option)) ?></option>
        <?php endforeach; ?>
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
    <button class="btn" type="submit"><?= icon('filter') ?><span><?= e(__('admin.apply_filters')) ?></span></button>
    <a class="btn btn-ghost" href="<?= e(url('admin/orders.php')) ?>"><?= e(__('common.reset')) ?></a>
  </form>

  <div class="table-wrap">
    <?php if ($orders === []): ?>
    <p class="admin-empty"><?= e(__('admin.no_orders')) ?></p>
    <?php else: ?>
    <table class="table responsive">
      <thead>
        <tr>
          <th><?= e(__('admin.order_ref')) ?></th>
          <th><?= e(__('common.customer')) ?></th>
          <th><?= e(__('admin.order_type')) ?></th>
          <th class="num"><?= e(__('admin.order_total')) ?></th>
          <th class="num"><?= e(__('admin.amount_paid')) ?></th>
          <th><?= e(__('admin.payment_status')) ?></th>
          <th><?= e(__('common.status')) ?></th>
          <th><?= e(__('admin.created')) ?></th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($orders as $order): ?>
        <tr>
          <td data-label="<?= e(__('admin.order_ref')) ?>">
            <span class="nowrap" style="font-family:ui-monospace,Menlo,monospace;font-size:0.78rem"><?= e($order['order_number']) ?></span>
            <?php if ((int) $order['is_demo'] === 1): ?><span class="badge badge-demo"><?= e(__('common.demo')) ?></span><?php endif; ?>
          </td>
          <td data-label="<?= e(__('common.customer')) ?>">
            <?= e((string) ($order['customer_name'] ?: __('pos.walk_in'))) ?>
            <?php if (!empty($order['customer_phone'])): ?><br><small class="muted"><?= e($order['customer_phone']) ?></small><?php endif; ?>
          </td>
          <td data-label="<?= e(__('admin.order_type')) ?>">
            <?= e(order_type_label((string) $order['order_type'])) ?>
            <?php if ((string) $order['source'] === 'pos'): ?><br><small class="muted">POS</small><?php endif; ?>
          </td>
          <td data-label="<?= e(__('admin.order_total')) ?>" class="num"><?= e(money_from_decimal((string) $order['grand_total'])) ?></td>
          <td data-label="<?= e(__('admin.amount_paid')) ?>" class="num"><?= e(money_from_decimal((string) $order['amount_paid'])) ?></td>
          <td data-label="<?= e(__('admin.payment_status')) ?>"><span class="badge <?= e(payment_status_badge_class((string) $order['payment_status'])) ?>"><?= e(payment_status_label((string) $order['payment_status'])) ?></span></td>
          <td data-label="<?= e(__('common.status')) ?>"><span class="badge <?= e(order_status_badge_class((string) $order['status'])) ?>"><?= e(order_status_label((string) $order['status'])) ?></span></td>
          <td data-label="<?= e(__('admin.created')) ?>" class="nowrap"><?= e(format_datetime($order['created_at'])) ?></td>
          <td class="actions">
            <a class="btn btn-secondary btn-sm" href="<?= e(url('admin/order-details.php?id=' . (int) $order['id'])) ?>"><?= e(__('common.view')) ?></a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

  <?php if ($pagination['pages'] > 1): ?>
  <div class="panel-head" style="border-top:1px solid var(--a-line);border-bottom:0;justify-content:space-between">
    <span class="small muted"><?= e(__('admin.showing_results', [
        'from' => is_bn() ? bn_digits((string) ($pagination['offset'] + 1)) : (string) ($pagination['offset'] + 1),
        'to' => is_bn() ? bn_digits((string) min($total, $pagination['offset'] + $pagination['per_page'])) : (string) min($total, $pagination['offset'] + $pagination['per_page']),
        'total' => is_bn() ? bn_digits((string) $total) : (string) $total,
    ])) ?></span>
    <div style="display:flex;gap:6px">
      <?php if ($pagination['page'] > 1): ?>
      <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/orders.php' . query_string_with(['page' => $pagination['page'] - 1]))) ?>"><?= e(__('admin.prev')) ?></a>
      <?php endif; ?>
      <span class="btn btn-ghost btn-sm" aria-disabled="true"><?= e(__('admin.page_of', ['{page}' => $pagination['page'], '{pages}' => $pagination['pages']])) ?></span>
      <?php if ($pagination['page'] < $pagination['pages']): ?>
      <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/orders.php' . query_string_with(['page' => $pagination['page'] + 1]))) ?>"><?= e(__('admin.next')) ?></a>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php require DH_ROOT . '/includes/partials/admin-footer.php'; ?>
