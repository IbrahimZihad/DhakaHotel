<?php
/**
 * Review moderation. Only approved reviews are shown on the public site.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

$statuses = ['pending', 'approved', 'rejected'];

if (is_post()) {
    require_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $reviewId = (int) ($_POST['id'] ?? 0);
    $review = $reviewId > 0 ? fetch_one('SELECT * FROM reviews WHERE id = ?', [$reviewId]) : null;

    if ($review === null) {
        flash('error', __('admin.no_reviews'));
        redirect('admin/reviews.php');
    }

    if ($action === 'approve' || $action === 'reject') {
        $newStatus = $action === 'approve' ? 'approved' : 'rejected';
        q('UPDATE reviews SET status = ? WHERE id = ?', [$newStatus, $reviewId]);
        audit_log('review.' . $newStatus, 'review', $reviewId);
        flash('success', $action === 'approve' ? __('admin.review_approved') : __('admin.review_rejected'));
    } elseif ($action === 'delete') {
        q('DELETE FROM reviews WHERE id = ?', [$reviewId]);
        audit_log('review.deleted', 'review', $reviewId);
        flash('success', __('admin.review_deleted'));
    }

    redirect('admin/reviews.php' . (isset($_POST['return_status']) && in_array($_POST['return_status'], $statuses, true)
        ? '?status=' . $_POST['return_status']
        : ''));
}

$status = (string) ($_GET['status'] ?? '');
$status = in_array($status, $statuses, true) ? $status : '';

$where = $status !== '' ? ' WHERE status = ?' : '';
$params = $status !== '' ? [$status] : [];
$total = (int) fetch_value('SELECT COUNT(*) FROM reviews' . $where, $params, 0);
$pagination = paginate($total, 20, current_page_number());
$reviews = fetch_all(
    'SELECT * FROM reviews' . $where . ' ORDER BY created_at DESC, id DESC LIMIT '
    . (int) $pagination['per_page'] . ' OFFSET ' . (int) $pagination['offset'],
    $params
);

$counts = ['all' => (int) fetch_value('SELECT COUNT(*) FROM reviews', [], 0)];
foreach ($statuses as $candidate) {
    $counts[$candidate] = (int) fetch_value('SELECT COUNT(*) FROM reviews WHERE status = ?', [$candidate], 0);
}

$statusBadge = ['pending' => 'badge-warning', 'approved' => 'badge-success', 'rejected' => 'badge-danger'];
$statusLabel = [
    'pending' => __('admin.pending'),
    'approved' => __('admin.approved'),
    'rejected' => __('admin.rejected'),
];

$pageTitle = __('admin.reviews_title');
$activeNav = 'reviews';
require DH_ROOT . '/includes/partials/admin-head.php';
?>
<div class="admin-head">
  <div>
    <h1><?= e(__('admin.reviews_title')) ?></h1>
    <p><?= e(__('admin.reviews_subtitle')) ?></p>
  </div>
</div>

<div class="panel">
  <div class="filter-bar" style="align-items:center">
    <a class="chip<?= $status === '' ? ' is-active' : '' ?>" href="<?= e(url('admin/reviews.php')) ?>"><?= e(__('common.all')) ?> (<?= e(is_bn() ? bn_digits((string) $counts['all']) : (string) $counts['all']) ?>)</a>
    <?php foreach ($statuses as $candidate): ?>
    <a class="chip<?= $status === $candidate ? ' is-active' : '' ?>" href="<?= e(url('admin/reviews.php?status=' . $candidate)) ?>">
      <?= e($statusLabel[$candidate]) ?> (<?= e(is_bn() ? bn_digits((string) $counts[$candidate]) : (string) $counts[$candidate]) ?>)
    </a>
    <?php endforeach; ?>
  </div>

  <?php if ($reviews === []): ?>
  <p class="admin-empty"><?= e(__('admin.no_reviews')) ?></p>
  <?php else: ?>
  <div class="stack" style="padding:16px">
    <?php foreach ($reviews as $review): ?>
    <article class="panel" style="box-shadow:none">
      <div class="panel-body stack">
        <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:flex-start">
          <div>
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
              <strong><?= e($review['display_name']) ?></strong>
              <span class="badge <?= e($statusBadge[$review['status']] ?? 'badge-soft') ?>"><?= e($statusLabel[$review['status']] ?? $review['status']) ?></span>
              <?php if ((int) $review['is_demo'] === 1): ?><span class="badge badge-demo"><?= e(__('common.demo')) ?></span><?php endif; ?>
            </div>
            <div class="stars" aria-label="<?= e(__('admin.rating')) ?> <?= (int) $review['rating'] ?>/5"><?= stars_html((int) $review['rating']) ?></div>
          </div>
          <span class="small muted"><?= e(format_datetime($review['created_at'])) ?></span>
        </div>

        <p style="font-size:var(--fs-sm)"><?= nl2br(e($review['review_text'])) ?></p>

        <div class="actions" style="display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end">
          <?php if ($review['status'] !== 'approved'): ?>
          <form method="post" action="<?= e(url('admin/reviews.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="approve">
            <input type="hidden" name="id" value="<?= (int) $review['id'] ?>">
            <input type="hidden" name="return_status" value="<?= e($status) ?>">
            <button class="btn btn-success btn-sm" type="submit"><?= icon('check') ?><span><?= e(__('admin.approve')) ?></span></button>
          </form>
          <?php endif; ?>
          <?php if ($review['status'] !== 'rejected'): ?>
          <form method="post" action="<?= e(url('admin/reviews.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="reject">
            <input type="hidden" name="id" value="<?= (int) $review['id'] ?>">
            <input type="hidden" name="return_status" value="<?= e($status) ?>">
            <button class="btn btn-ghost btn-sm" type="submit"><?= icon('ban') ?><span><?= e(__('admin.reject')) ?></span></button>
          </form>
          <?php endif; ?>
          <form method="post" action="<?= e(url('admin/reviews.php')) ?>" data-confirm="<?= e(__('admin.confirm_delete_review')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int) $review['id'] ?>">
            <input type="hidden" name="return_status" value="<?= e($status) ?>">
            <button class="btn btn-ghost btn-sm" type="submit"><?= icon('trash') ?><span><?= e(__('common.delete')) ?></span></button>
          </form>
        </div>
      </div>
    </article>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if ($pagination['pages'] > 1): ?>
  <div class="panel-head" style="border-top:1px solid var(--a-line);border-bottom:0;justify-content:space-between">
    <span class="small muted"><?= e(__('admin.page_of', ['page' => $pagination['page'], 'pages' => $pagination['pages']])) ?></span>
    <div style="display:flex;gap:6px">
      <?php if ($pagination['page'] > 1): ?>
      <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/reviews.php' . query_string_with(['page' => $pagination['page'] - 1]))) ?>"><?= e(__('admin.prev')) ?></a>
      <?php endif; ?>
      <?php if ($pagination['page'] < $pagination['pages']): ?>
      <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/reviews.php' . query_string_with(['page' => $pagination['page'] + 1]))) ?>"><?= e(__('admin.next')) ?></a>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php require DH_ROOT . '/includes/partials/admin-footer.php'; ?>
