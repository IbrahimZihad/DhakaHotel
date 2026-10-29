<?php
/**
 * Customer directory. "Spent" counts confirmed payments only, never order totals.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

$search = trim((string) ($_GET['q'] ?? ''));

$where = '';
$params = [];
if ($search !== '') {
    $like = '%' . addcslashes($search, '%_\\') . '%';
    $where = " WHERE c.name LIKE ? ESCAPE '\\\\' OR c.phone LIKE ? ESCAPE '\\\\' OR c.email LIKE ? ESCAPE '\\\\'";
    $params = [$like, $like, $like];
}

$total = (int) fetch_value('SELECT COUNT(*) FROM customers c' . $where, $params, 0);
$pagination = paginate($total, 20, current_page_number());
$customers = fetch_all(
    "SELECT c.*,
        (SELECT COUNT(*) FROM orders o WHERE o.customer_id = c.id) AS orders_count,
        (SELECT COALESCE(SUM(p.amount), 0)
           FROM payments p JOIN orders o2 ON o2.id = p.order_id
          WHERE o2.customer_id = c.id AND p.status = 'confirmed') AS paid_total,
        (SELECT MAX(o.created_at) FROM orders o WHERE o.customer_id = c.id) AS last_order
     FROM customers c" . $where
    . ' ORDER BY c.id DESC LIMIT ' . (int) $pagination['per_page'] . ' OFFSET ' . (int) $pagination['offset'],
    $params
);

$pageTitle = __('admin.customers_title');
$activeNav = 'customers';
require DH_ROOT . '/includes/partials/admin-head.php';
?>
<div class="admin-head">
  <div>
    <h1><?= e(__('admin.customers_title')) ?></h1>
    <p><?= e(__('admin.customers_subtitle')) ?></p>
  </div>
</div>

<div class="panel">
  <form class="filter-bar" method="get" action="<?= e(url('admin/customers.php')) ?>" role="search">
    <div class="field grow">
      <label for="q"><?= e(__('common.search')) ?></label>
      <input class="input" type="search" id="q" name="q" value="<?= e($search) ?>" placeholder="<?= e(__('common.name')) ?> / <?= e(__('common.phone')) ?>">
    </div>
    <button class="btn" type="submit"><?= icon('search') ?><span><?= e(__('common.search')) ?></span></button>
    <a class="btn btn-ghost" href="<?= e(url('admin/customers.php')) ?>"><?= e(__('common.reset')) ?></a>
  </form>

  <div class="table-wrap">
    <?php if ($customers === []): ?>
    <p class="admin-empty"><?= e(__('admin.no_customers')) ?></p>
    <?php else: ?>
    <table class="table responsive">
      <thead>
        <tr>
          <th><?= e(__('common.customer')) ?></th>
          <th><?= e(__('common.phone')) ?></th>
          <th class="num"><?= e(__('admin.orders_count')) ?></th>
          <th class="num"><?= e(__('admin.spent')) ?></th>
          <th><?= e(__('admin.last_order')) ?></th>
          <th><?= e(__('admin.customer_since')) ?></th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($customers as $customer): ?>
        <tr>
          <td data-label="<?= e(__('common.customer')) ?>">
            <strong><?= e($customer['name']) ?></strong>
            <?php if ((int) $customer['is_demo'] === 1): ?><span class="badge badge-demo"><?= e(__('common.demo')) ?></span><?php endif; ?>
            <?php if (!empty($customer['email'])): ?><br><small class="muted"><?= e($customer['email']) ?></small><?php endif; ?>
          </td>
          <td data-label="<?= e(__('common.phone')) ?>"><a href="tel:<?= e($customer['phone']) ?>" class="nowrap"><?= e($customer['phone']) ?></a></td>
          <td data-label="<?= e(__('admin.orders_count')) ?>" class="num"><?= e(is_bn() ? bn_digits((string) (int) $customer['orders_count']) : (string) (int) $customer['orders_count']) ?></td>
          <td data-label="<?= e(__('admin.spent')) ?>" class="num"><?= e(money_from_decimal((string) $customer['paid_total'])) ?></td>
          <td data-label="<?= e(__('admin.last_order')) ?>" class="nowrap"><?= $customer['last_order'] !== null ? e(format_datetime($customer['last_order'])) : '—' ?></td>
          <td data-label="<?= e(__('admin.customer_since')) ?>" class="nowrap"><?= e(format_date($customer['created_at'])) ?></td>
          <td class="actions">
            <a class="btn btn-secondary btn-sm" href="<?= e(url('admin/orders.php?q=' . rawurlencode((string) $customer['phone']))) ?>"><?= e(__('admin.order_history')) ?></a>
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
      <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/customers.php' . query_string_with(['page' => $pagination['page'] - 1]))) ?>"><?= e(__('admin.prev')) ?></a>
      <?php endif; ?>
      <span class="btn btn-ghost btn-sm" aria-disabled="true"><?= e(__('admin.page_of', ['page' => $pagination['page'], 'pages' => $pagination['pages']])) ?></span>
      <?php if ($pagination['page'] < $pagination['pages']): ?>
      <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/customers.php' . query_string_with(['page' => $pagination['page'] + 1]))) ?>"><?= e(__('admin.next')) ?></a>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php require DH_ROOT . '/includes/partials/admin-footer.php'; ?>
