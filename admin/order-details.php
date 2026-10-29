<?php
/**
 * Single order: status transitions, explicit payment recording, refunds,
 * receipt printing and the owner-notification queue.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

$orderId = (int) ($_GET['id'] ?? $_POST['order_id'] ?? 0);
$order = $orderId > 0 ? order_by_id($orderId) : null;

if ($order === null) {
    flash('error', __('admin.order_not_found'));
    redirect('admin/orders.php');
}

$paymentErrorKeys = [
    'order_cancelled' => 'admin.payment_cancelled_order',
    'amount_exceeds_balance' => 'admin.overpayment',
    'reference_required' => 'admin.invalid_payment',
    'invalid_method' => 'admin.invalid_payment',
    'invalid_amount' => 'admin.invalid_payment',
    'order_not_found' => 'admin.order_not_found',
];

if (is_post()) {
    require_csrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'status') {
        $newStatus = (string) ($_POST['new_status'] ?? '');
        $note = clean_text($_POST['note'] ?? '', 255);
        $result = update_order_status($orderId, $newStatus, admin_id(), $note !== '' ? $note : null);

        if ($result['ok']) {
            flash('success', __('admin.status_updated'));
        } else {
            flash('error', __('admin.invalid_transition'));
        }
        redirect('admin/order-details.php?id=' . $orderId);
    }

    if ($action === 'payment') {
        $method = (string) ($_POST['method'] ?? '');
        $amount = parse_money_input($_POST['amount'] ?? '');
        $reference = clean_text($_POST['reference'] ?? '', 120);
        $key = (string) ($_POST['payment_key'] ?? '');
        $key = preg_match('/^[a-f0-9]{32}$/', $key) === 1 ? 'pay-' . $key : '';

        if ($amount === null || $amount <= 0) {
            flash('error', __('admin.invalid_payment'));
            redirect('admin/order-details.php?id=' . $orderId);
        }

        $result = record_payment($orderId, [
            'method' => $method,
            'amount_cents' => $amount,
            'reference' => $reference,
            'idempotency_key' => $key,
            'admin_id' => admin_id(),
        ]);

        if ($result['ok']) {
            flash('success', !empty($result['duplicate']) ? __('admin.duplicate_payment') : __('admin.payment_recorded'));
        } else {
            $keyName = $paymentErrorKeys[$result['error'] ?? ''] ?? null;
            flash('error', $keyName !== null
                ? __($keyName)
                : __('admin.payment_error', ['reason' => (string) ($result['error'] ?? 'unknown')]));
        }
        redirect('admin/order-details.php?id=' . $orderId);
    }

    if ($action === 'refund') {
        $paymentId = (int) ($_POST['payment_id'] ?? 0);
        $reason = clean_text($_POST['refund_reason'] ?? '', 255);
        $result = refund_payment($paymentId, admin_id(), $reason);
        flash($result['ok'] ? 'success' : 'error', $result['ok'] ? __('admin.refund_recorded') : __('admin.refund_failed'));
        redirect('admin/order-details.php?id=' . $orderId);
    }

    redirect('admin/order-details.php?id=' . $orderId);
}

$order = order_by_id($orderId);
$items = order_items($orderId);
$payments = order_payments($orderId);
$timeline = fetch_all(
    'SELECT h.*, a.name AS admin_name FROM order_status_history h
     LEFT JOIN admins a ON a.id = h.admin_id
     WHERE h.order_id = ? ORDER BY h.id',
    [$orderId]
);
$notifications = fetch_all('SELECT * FROM email_notifications WHERE order_id = ? ORDER BY id DESC', [$orderId]);
$customer = $order['customer_id'] !== null
    ? fetch_one('SELECT * FROM customers WHERE id = ?', [(int) $order['customer_id']])
    : null;

$grandCents = cents_from_decimal((string) $order['grand_total']);
$paidCents = order_paid_cents($orderId);
$balance = balance_due($grandCents, $paidCents);
$transitions = allowed_status_transitions((string) $order['status']);
$paymentKey = bin2hex(random_bytes(16));
$customerPhone = normalize_bd_phone((string) ($order['customer_phone'] ?? ''));
$waCustomer = is_valid_bd_phone($customerPhone) ? 'https://wa.me/88' . substr($customerPhone, 1) : null;

$emailStatusBadge = ['queued' => 'badge-warning', 'sent' => 'badge-success', 'failed' => 'badge-danger'];
$emailStatusLabel = [
    'queued' => __('admin.status_queued'),
    'sent' => __('admin.status_sent'),
    'failed' => __('admin.status_failed'),
];

$pageTitle = $order['order_number'];
$activeNav = 'orders';
require DH_ROOT . '/includes/partials/admin-head.php';
?>
<div class="admin-head">
  <div>
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
      <h1 style="font-family:ui-monospace,Menlo,monospace"><?= e($order['order_number']) ?></h1>
      <span class="badge <?= e(order_status_badge_class((string) $order['status'])) ?>"><?= e(order_status_label((string) $order['status'])) ?></span>
      <span class="badge <?= e(payment_status_badge_class((string) $order['payment_status'])) ?>"><?= e(payment_status_label((string) $order['payment_status'])) ?></span>
      <?php if ((int) $order['is_demo'] === 1): ?><span class="badge badge-demo"><?= e(__('common.demo')) ?></span><?php endif; ?>
    </div>
    <p><?= e(format_datetime($order['created_at'])) ?> · <?= e(order_type_label((string) $order['order_type'])) ?></p>
  </div>
  <div class="admin-head-actions">
    <a class="btn btn-secondary" href="<?= e(url('admin/orders.php')) ?>"><?= icon('chevron-left') ?><span><?= e(__('admin.nav_orders')) ?></span></a>
    <a class="btn" href="<?= e(url('admin/receipt.php?id=' . $orderId . '&paper=80')) ?>" target="_blank" rel="noopener"><?= icon('printer') ?><span><?= e(__('admin.print_receipt')) ?></span></a>
  </div>
</div>

<div class="grid-main-side">
  <div class="stack">
    <div class="panel">
      <div class="panel-head"><h2><?= e(__('admin.items')) ?></h2><span class="badge badge-soft"><?= e((string) count($items)) ?></span></div>
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th><?= e(__('common.item')) ?></th>
              <th class="num"><?= e(__('receipt.qty')) ?></th>
              <th class="num"><?= e(__('common.price')) ?></th>
              <th class="num"><?= e(__('common.total')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($items as $item): ?>
            <tr>
              <td>
                <strong><?= e(localized($item, 'name')) ?></strong>
                <br><small class="muted"><?= e(is_bn() ? $item['name_en'] : $item['name_bn']) ?></small>
              </td>
              <td class="num"><?= e(is_bn() ? bn_digits((string) (int) $item['quantity']) : (string) (int) $item['quantity']) ?></td>
              <td class="num"><?= e(money_from_decimal((string) $item['unit_price'])) ?></td>
              <td class="num"><?= e(money_from_decimal((string) $item['line_total'])) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="panel-body">
        <ul class="pos-totals" style="padding:0;border:0;background:transparent;margin-left:auto;max-width:320px">
          <li><span><?= e(__('common.subtotal')) ?></span><span><?= e(money_from_decimal((string) $order['subtotal'])) ?></span></li>
          <?php if (cents_from_decimal((string) $order['discount']) > 0): ?>
          <li><span><?= e(__('common.discount')) ?></span><span>−<?= e(money_from_decimal((string) $order['discount'])) ?></span></li>
          <?php endif; ?>
          <?php if (cents_from_decimal((string) $order['delivery_fee']) > 0): ?>
          <li><span><?= e(__('common.delivery_fee')) ?></span><span><?= e(money_from_decimal((string) $order['delivery_fee'])) ?></span></li>
          <?php endif; ?>
          <li><span><?= e(__('common.tax')) ?></span><span><?= e(money_from_decimal((string) $order['tax'])) ?></span></li>
          <li class="total"><span><?= e(__('common.total')) ?></span><span><?= e(money_from_decimal((string) $order['grand_total'])) ?></span></li>
          <li><span><?= e(__('admin.paid')) ?></span><span><?= e(money($paidCents)) ?></span></li>
          <li><span><?= e(__('admin.balance')) ?></span><span><?= e(money($balance)) ?></span></li>
        </ul>
      </div>
    </div>

    <div class="panel">
      <div class="panel-head">
        <div>
          <h2><?= e(__('admin.payments')) ?></h2>
          <p><?= e(__('admin.payment_history_note')) ?></p>
        </div>
      </div>
      <div class="table-wrap">
        <?php if ($payments === []): ?>
        <p class="admin-empty"><?= e(__('admin.no_payments')) ?></p>
        <?php else: ?>
        <table class="table responsive">
          <thead>
            <tr>
              <th><?= e(__('common.date')) ?></th>
              <th><?= e(__('admin.payment_method')) ?></th>
              <th class="num"><?= e(__('admin.amount')) ?></th>
              <th><?= e(__('common.status')) ?></th>
              <th><?= e(__('admin.payment_reference')) ?></th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($payments as $payment):
                $recordedBy = $payment['recorded_by'] !== null
                    ? fetch_value('SELECT name FROM admins WHERE id = ?', [(int) $payment['recorded_by']])
                    : null;
            ?>
            <tr>
              <td data-label="<?= e(__('common.date')) ?>" class="nowrap"><?= e(format_datetime($payment['confirmed_at'] ?? $payment['created_at'])) ?></td>
              <td data-label="<?= e(__('admin.payment_method')) ?>"><?= e(payment_method_label((string) $payment['method'])) ?></td>
              <td data-label="<?= e(__('admin.amount')) ?>" class="num"><?= e(money_from_decimal((string) $payment['amount'])) ?></td>
              <td data-label="<?= e(__('common.status')) ?>">
                <?php if ((string) $payment['status'] === 'confirmed'): ?>
                <span class="badge badge-success"><?= e(__('admin.paid')) ?></span>
                <?php elseif ((string) $payment['status'] === 'refunded'): ?>
                <span class="badge badge-danger"><?= e(payment_status_label('refunded')) ?></span>
                <?php else: ?>
                <span class="badge badge-warning"><?= e(__('common.status')) ?></span>
                <?php endif; ?>
                <?php if ($recordedBy !== null): ?><br><small class="muted"><?= e(__('admin.recorded_by')) ?>: <?= e((string) $recordedBy) ?></small><?php endif; ?>
              </td>
              <td data-label="<?= e(__('admin.payment_reference')) ?>">
                <?= $payment['reference'] !== null && $payment['reference'] !== '' ? e($payment['reference']) : '—' ?>
                <?php if (!empty($payment['refund_reason'])): ?><br><small class="muted"><?= e($payment['refund_reason']) ?></small><?php endif; ?>
              </td>
              <td class="actions">
                <?php if ((string) $payment['status'] === 'confirmed'): ?>
                <form method="post" action="<?= e(url('admin/order-details.php?id=' . $orderId)) ?>" data-confirm="<?= e(__('admin.refund_confirm')) ?>" style="display:flex;gap:6px">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="refund">
                  <input type="hidden" name="payment_id" value="<?= (int) $payment['id'] ?>">
                  <input class="input" type="text" name="refund_reason" maxlength="255" placeholder="<?= e(__('admin.refund_reason')) ?>" style="min-height:34px;padding:4px 8px;font-size:var(--fs-xs);width:130px">
                  <button class="btn btn-ghost btn-sm" type="submit"><?= e(__('admin.refund')) ?></button>
                </form>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>

      <?php if ((string) $order['status'] !== 'cancelled' && $balance > 0): ?>
      <div class="panel-body" style="border-top:1px solid var(--a-line)">
        <h3 style="font-size:var(--fs-sm);margin-bottom:10px"><?= e(__('admin.record_payment')) ?></h3>
        <form method="post" action="<?= e(url('admin/order-details.php?id=' . $orderId)) ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="payment">
          <input type="hidden" name="payment_key" value="<?= e($paymentKey) ?>">
          <div class="form-row three">
            <div class="field">
              <label for="method"><?= e(__('admin.payment_method')) ?></label>
              <select class="select" id="method" name="method" data-payment-method required>
                <?php foreach (PAYMENT_METHODS as $method): ?>
                <option value="<?= e($method) ?>" <?= $method === 'cash' ? 'selected' : '' ?>><?= e(payment_method_label($method)) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field">
              <label for="amount"><?= e(__('admin.amount')) ?> (৳)</label>
              <input class="input" type="text" id="amount" name="amount" inputmode="decimal" value="<?= e(decimal_from_cents($balance)) ?>" required>
            </div>
            <div class="field" data-payment-reference hidden>
              <label for="reference"><?= e(__('admin.reference')) ?></label>
              <input class="input" type="text" id="reference" name="reference" maxlength="120" placeholder="<?= e(__('admin.reference_hint')) ?>">
            </div>
          </div>
          <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-top:12px">
            <p class="small muted" style="max-width:56ch"><?= e(__('admin.payment_honesty')) ?></p>
            <button class="btn" type="submit"><?= icon('cash') ?><span><?= e(__('admin.confirm_payment')) ?></span></button>
          </div>
        </form>
      </div>
      <?php endif; ?>
    </div>

    <div class="panel">
      <div class="panel-head"><h2><?= e(__('admin.email_notifications')) ?></h2></div>
      <?php if ($notifications === []): ?>
      <p class="admin-empty"><?= e(__('admin.no_emails')) ?></p>
      <?php else: ?>
      <div class="table-wrap">
        <table class="table responsive">
          <thead>
            <tr>
              <th><?= e(__('common.date')) ?></th>
              <th><?= e(__('admin.recipient')) ?></th>
              <th><?= e(__('common.status')) ?></th>
              <th><?= e(__('admin.attempts')) ?></th>
              <th><?= e(__('admin.last_error')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($notifications as $notification): ?>
            <tr>
              <td data-label="<?= e(__('common.date')) ?>" class="nowrap"><?= e(format_datetime($notification['created_at'])) ?></td>
              <td data-label="<?= e(__('admin.recipient')) ?>"><?= e($notification['recipient']) ?></td>
              <td data-label="<?= e(__('common.status')) ?>"><span class="badge <?= e($emailStatusBadge[$notification['status']] ?? 'badge-soft') ?>"><?= e($emailStatusLabel[$notification['status']] ?? $notification['status']) ?></span></td>
              <td data-label="<?= e(__('admin.attempts')) ?>"><?= e((string) (int) $notification['attempts']) ?></td>
              <td data-label="<?= e(__('admin.last_error')) ?>"><?= $notification['last_error'] !== null ? e((string) $notification['last_error']) : '—' ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="stack">
    <div class="panel">
      <div class="panel-head"><h2><?= e(__('admin.update_status')) ?></h2></div>
      <div class="panel-body">
        <?php if ($transitions === []): ?>
        <p class="small muted"><?= e(__('admin.status_unchanged')) ?></p>
        <?php else: ?>
        <form method="post" action="<?= e(url('admin/order-details.php?id=' . $orderId)) ?>" class="stack">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="status">
          <div class="field">
            <label for="new_status"><?= e(__('admin.next_status')) ?></label>
            <select class="select" id="new_status" name="new_status" required>
              <?php foreach ($transitions as $next): ?>
              <option value="<?= e($next) ?>"><?= e(order_status_label($next)) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="note"><?= e(__('admin.note')) ?> <span class="muted">(<?= e(__('common.optional')) ?>)</span></label>
            <input class="input" type="text" id="note" name="note" maxlength="255">
          </div>
          <button class="btn" type="submit"><?= e(__('admin.update')) ?></button>
        </form>
        <?php endif; ?>
      </div>
    </div>

    <div class="panel">
      <div class="panel-head"><h2><?= e(__('admin.customer_info')) ?></h2></div>
      <div class="panel-body stack">
        <div>
          <strong><?= e((string) ($order['customer_name'] ?: __('pos.walk_in'))) ?></strong>
          <?php if (!empty($order['customer_phone'])): ?>
          <div class="small muted" style="margin-top:4px"><?= e($order['customer_phone']) ?></div>
          <?php endif; ?>
          <?php if ($customer !== null && !empty($customer['email'])): ?>
          <div class="small muted" style="margin-top:4px"><?= e($customer['email']) ?></div>
          <?php endif; ?>
        </div>
        <?php if (!empty($order['customer_phone'])): ?>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          <a class="btn btn-secondary btn-sm" href="tel:<?= e(preg_replace('/[^0-9+]/', '', (string) $order['customer_phone'])) ?>"><?= icon('phone') ?><span><?= e(__('common.phone')) ?></span></a>
          <?php if ($waCustomer !== null): ?>
          <a class="btn btn-whatsapp btn-sm" href="<?= e($waCustomer) ?>" target="_blank" rel="noopener"><?= wa_icon() ?><span><?= e(__('admin.whatsapp_customer')) ?></span></a>
          <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php if ($customer !== null): ?>
        <a class="small" href="<?= e(url('admin/customers.php?q=' . rawurlencode((string) $customer['phone']))) ?>" style="font-weight:600"><?= e(__('admin.orders_for_customer')) ?> →</a>
        <?php endif; ?>
      </div>
    </div>

    <div class="panel">
      <div class="panel-head"><h2><?= e(__('admin.delivery_info')) ?></h2></div>
      <div class="panel-body stack">
        <div class="pay-summary">
          <div><span><?= e(__('admin.order_type')) ?></span><b><?= e(order_type_label((string) $order['order_type'])) ?></b></div>
          <?php if (!empty($order['table_number'])): ?>
          <div><span><?= e(__('pos.table_number')) ?></span><b><?= e($order['table_number']) ?></b></div>
          <?php endif; ?>
          <div><span><?= e(__('admin.payment_method')) ?></span><b><?= $order['payment_method'] !== null ? e(payment_method_label((string) $order['payment_method'])) : '—' ?></b></div>
          <div><span><?= e(__('admin.created')) ?></span><b><?= e(format_datetime($order['created_at'])) ?></b></div>
        </div>
        <?php if (!empty($order['delivery_address'])): ?>
        <div>
          <div class="small muted"><?= e(__('common.address')) ?></div>
          <p class="small"><?= e($order['delivery_address']) ?></p>
        </div>
        <?php endif; ?>
        <?php if (!empty($order['notes'])): ?>
        <div>
          <div class="small muted"><?= e(__('common.notes')) ?></div>
          <p class="small"><?= e($order['notes']) ?></p>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="panel">
      <div class="panel-head"><h2><?= e(__('admin.timeline')) ?></h2></div>
      <div class="panel-body">
        <ol style="display:grid;gap:10px;font-size:var(--fs-sm)">
          <?php foreach ($timeline as $entry): ?>
          <li style="display:grid;gap:2px">
            <strong><?= e(order_status_label((string) $entry['status'])) ?></strong>
            <span class="small muted"><?= e(format_datetime($entry['created_at'])) ?><?= !empty($entry['admin_name']) ? ' · ' . e($entry['admin_name']) : '' ?></span>
            <?php if (!empty($entry['note'])): ?><span class="small"><?= e($entry['note']) ?></span><?php endif; ?>
          </li>
          <?php endforeach; ?>
        </ol>
      </div>
    </div>
  </div>
</div>
<?php require DH_ROOT . '/includes/partials/admin-footer.php'; ?>
