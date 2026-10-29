<?php
/**
 * Owner e-mail queue. Sending is handled by scripts/process_email_queue.php;
 * this screen only inspects and re-queues notifications.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

$statuses = ['queued', 'sent', 'failed'];

if (is_post()) {
    require_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $notificationId = (int) ($_POST['id'] ?? 0);

    if ($action === 'retry' && $notificationId > 0) {
        retry_notification($notificationId);
        audit_log('email.retry', 'email_notification', $notificationId);
        flash('success', __('admin.retry_queued'));
    }

    redirect('admin/notifications.php' . (isset($_POST['return_status']) && in_array($_POST['return_status'], $statuses, true)
        ? '?status=' . $_POST['return_status']
        : ''));
}

$status = (string) ($_GET['status'] ?? '');
$status = in_array($status, $statuses, true) ? $status : '';

$where = $status !== '' ? ' WHERE n.status = ?' : '';
$params = $status !== '' ? [$status] : [];
$total = (int) fetch_value('SELECT COUNT(*) FROM email_notifications n' . $where, $params, 0);
$pagination = paginate($total, 20, current_page_number());
$notifications = fetch_all(
    'SELECT n.*, o.order_number FROM email_notifications n
     LEFT JOIN orders o ON o.id = n.order_id' . $where
    . ' ORDER BY n.id DESC LIMIT ' . (int) $pagination['per_page'] . ' OFFSET ' . (int) $pagination['offset'],
    $params
);

$counts = ['all' => (int) fetch_value('SELECT COUNT(*) FROM email_notifications', [], 0)];
foreach ($statuses as $candidate) {
    $counts[$candidate] = (int) fetch_value('SELECT COUNT(*) FROM email_notifications WHERE status = ?', [$candidate], 0);
}

$mailerState = mailer_state();
$stateAlert = [
    'ready' => ['class' => 'alert-success', 'icon' => 'badge-check', 'text' => __('admin.notify_ready')],
    'missing_library' => ['class' => 'alert-warning', 'icon' => 'alert', 'text' => __('admin.smtp_missing_lib')],
    'unconfigured' => ['class' => 'alert-warning', 'icon' => 'alert', 'text' => __('admin.smtp_unconfigured')],
][$mailerState] ?? ['class' => 'alert-info', 'icon' => 'info', 'text' => __('admin.smtp_unconfigured')];

$statusBadge = ['queued' => 'badge-warning', 'sent' => 'badge-success', 'failed' => 'badge-danger'];
$statusLabel = [
    'queued' => __('admin.status_queued'),
    'sent' => __('admin.status_sent'),
    'failed' => __('admin.status_failed'),
];

$pageTitle = __('admin.notifications_title');
$activeNav = 'notifications';
require DH_ROOT . '/includes/partials/admin-head.php';
?>
<div class="admin-head">
  <div>
    <h1><?= e(__('admin.notifications_title')) ?></h1>
    <p><?= e(__('admin.notifications_subtitle')) ?></p>
  </div>
</div>

<div class="alert <?= e($stateAlert['class']) ?>" role="status">
  <?= icon($stateAlert['icon']) ?>
  <div>
    <div><?= e($stateAlert['text']) ?></div>
    <div class="small" style="margin-top:4px"><?= e(__('admin.process_hint')) ?> <code><?= e(__('admin.run_worker')) ?></code></div>
  </div>
</div>

<div class="panel">
  <div class="filter-bar" style="align-items:center">
    <a class="chip<?= $status === '' ? ' is-active' : '' ?>" href="<?= e(url('admin/notifications.php')) ?>"><?= e(__('common.all')) ?> (<?= e(is_bn() ? bn_digits((string) $counts['all']) : (string) $counts['all']) ?>)</a>
    <?php foreach ($statuses as $candidate): ?>
    <a class="chip<?= $status === $candidate ? ' is-active' : '' ?>" href="<?= e(url('admin/notifications.php?status=' . $candidate)) ?>">
      <?= e($statusLabel[$candidate]) ?> (<?= e(is_bn() ? bn_digits((string) $counts[$candidate]) : (string) $counts[$candidate]) ?>)
    </a>
    <?php endforeach; ?>
  </div>

  <div class="table-wrap">
    <?php if ($notifications === []): ?>
    <p class="admin-empty"><?= e(__('admin.empty_queue')) ?></p>
    <?php else: ?>
    <table class="table responsive">
      <thead>
        <tr>
          <th><?= e(__('admin.notification_type')) ?></th>
          <th><?= e(__('admin.order_ref')) ?></th>
          <th><?= e(__('admin.recipient')) ?></th>
          <th><?= e(__('common.status')) ?></th>
          <th><?= e(__('admin.attempts')) ?></th>
          <th><?= e(__('admin.created')) ?></th>
          <th><?= e(__('admin.sent_at')) ?></th>
          <th><?= e(__('admin.last_error')) ?></th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($notifications as $notification): ?>
        <tr>
          <td data-label="<?= e(__('admin.notification_type')) ?>">
            <?= e($notification['notification_type']) ?>
            <br><small class="muted"><?= e(strtoupper((string) $notification['language'])) ?></small>
          </td>
          <td data-label="<?= e(__('admin.order_ref')) ?>">
            <?php if (!empty($notification['order_number'])): ?>
            <a href="<?= e(url('admin/order-details.php?id=' . (int) $notification['order_id'])) ?>" style="font-family:ui-monospace,Menlo,monospace;font-size:0.78rem"><?= e($notification['order_number']) ?></a>
            <?php else: ?>—<?php endif; ?>
          </td>
          <td data-label="<?= e(__('admin.recipient')) ?>" class="span"><?= e($notification['recipient']) ?></td>
          <td data-label="<?= e(__('common.status')) ?>"><span class="badge <?= e($statusBadge[$notification['status']] ?? 'badge-soft') ?>"><?= e($statusLabel[$notification['status']] ?? $notification['status']) ?></span></td>
          <td data-label="<?= e(__('admin.attempts')) ?>"><?= e((string) (int) $notification['attempts']) ?></td>
          <td data-label="<?= e(__('admin.created')) ?>" class="nowrap"><?= e(format_datetime($notification['created_at'])) ?></td>
          <td data-label="<?= e(__('admin.sent_at')) ?>" class="nowrap"><?= $notification['sent_at'] !== null ? e(format_datetime($notification['sent_at'])) : '—' ?></td>
          <td data-label="<?= e(__('admin.last_error')) ?>"><?= $notification['last_error'] !== null ? e(mb_substr((string) $notification['last_error'], 0, 120)) : '—' ?></td>
          <td class="actions">
            <?php if ($notification['status'] !== 'sent'): ?>
            <form method="post" action="<?= e(url('admin/notifications.php')) ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="retry">
              <input type="hidden" name="id" value="<?= (int) $notification['id'] ?>">
              <input type="hidden" name="return_status" value="<?= e($status) ?>">
              <button class="btn btn-ghost btn-sm" type="submit"><?= icon('refresh') ?><span><?= e(__('admin.retry')) ?></span></button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

  <?php if ($pagination['pages'] > 1): ?>
  <div class="panel-head" style="border-top:1px solid var(--a-line);border-bottom:0;justify-content:space-between">
    <span class="small muted"><?= e(__('admin.page_of', ['page' => $pagination['page'], 'pages' => $pagination['pages']])) ?></span>
    <div style="display:flex;gap:6px">
      <?php if ($pagination['page'] > 1): ?>
      <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/notifications.php' . query_string_with(['page' => $pagination['page'] - 1]))) ?>"><?= e(__('admin.prev')) ?></a>
      <?php endif; ?>
      <?php if ($pagination['page'] < $pagination['pages']): ?>
      <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/notifications.php' . query_string_with(['page' => $pagination['page'] + 1]))) ?>"><?= e(__('admin.next')) ?></a>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php require DH_ROOT . '/includes/partials/admin-footer.php'; ?>
