<?php
/**
 * Menu item list with search, category filter, availability toggle and delete.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

if (is_post()) {
    require_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $itemId = (int) ($_POST['id'] ?? 0);
    $item = $itemId > 0 ? fetch_one('SELECT * FROM menu_items WHERE id = ?', [$itemId]) : null;

    if ($item === null) {
        flash('error', __('admin.item_not_found'));
        redirect('admin/menu.php');
    }

    if ($action === 'toggle') {
        q('UPDATE menu_items SET is_available = ? WHERE id = ?', [(int) $item['is_available'] === 1 ? 0 : 1, $itemId]);
        audit_log('menu.toggle', 'menu_item', $itemId, ['is_available' => (int) $item['is_available'] === 1 ? 0 : 1]);
        flash('success', __('admin.item_updated'));
    } elseif ($action === 'delete') {
        remove_upload($item['image_path']);
        q('DELETE FROM menu_items WHERE id = ?', [$itemId]);
        audit_log('menu.deleted', 'menu_item', $itemId, ['name_en' => $item['name_en']]);
        flash('success', __('admin.item_deleted'));
    }

    redirect('admin/menu.php');
}

$search = trim((string) ($_GET['q'] ?? ''));
$categoryId = (int) ($_GET['category'] ?? 0);
$availability = (string) ($_GET['available'] ?? '');

$where = [];
$params = [];
if ($search !== '') {
    $like = '%' . addcslashes($search, '%_\\') . '%';
    $where[] = "(mi.name_bn LIKE ? ESCAPE '\\\\' OR mi.name_en LIKE ? ESCAPE '\\\\')";
    $params[] = $like;
    $params[] = $like;
}
if ($categoryId > 0) {
    $where[] = 'mi.category_id = ?';
    $params[] = $categoryId;
}
if ($availability === 'yes') {
    $where[] = 'mi.is_available = 1';
} elseif ($availability === 'no') {
    $where[] = 'mi.is_available = 0';
}
$whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

$categories = fetch_all('SELECT id, name_bn, name_en FROM categories ORDER BY sort_order, id');
$total = (int) fetch_value('SELECT COUNT(*) FROM menu_items mi' . $whereSql, $params, 0);
$pagination = paginate($total, 20, current_page_number());
$items = fetch_all(
    'SELECT mi.*, c.name_bn AS category_bn, c.name_en AS category_en
     FROM menu_items mi
     JOIN categories c ON c.id = mi.category_id' . $whereSql
    . ' ORDER BY c.sort_order, mi.sort_order, mi.id LIMIT ' . (int) $pagination['per_page'] . ' OFFSET ' . (int) $pagination['offset'],
    $params
);

$pageTitle = __('admin.menu_title');
$activeNav = 'menu';
require DH_ROOT . '/includes/partials/admin-head.php';
?>
<div class="admin-head">
  <div>
    <h1><?= e(__('admin.menu_title')) ?></h1>
    <p><?= e(__('admin.menu_subtitle')) ?></p>
  </div>
  <div class="admin-head-actions">
    <a class="btn" href="<?= e(url('admin/menu-edit.php')) ?>"><?= icon('plus') ?><span><?= e(__('admin.add_item')) ?></span></a>
  </div>
</div>

<div class="panel">
  <form class="filter-bar" method="get" action="<?= e(url('admin/menu.php')) ?>" role="search">
    <div class="field grow">
      <label for="q"><?= e(__('common.search')) ?></label>
      <input class="input" type="search" id="q" name="q" value="<?= e($search) ?>" placeholder="<?= e(__('admin.search_items')) ?>">
    </div>
    <div class="field">
      <label for="category"><?= e(__('admin.category')) ?></label>
      <select class="select" id="category" name="category">
        <option value="0"><?= e(__('admin.all_categories')) ?></option>
        <?php foreach ($categories as $category): ?>
        <option value="<?= (int) $category['id'] ?>" <?= $categoryId === (int) $category['id'] ? 'selected' : '' ?>><?= e(localized($category, 'name')) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="available"><?= e(__('admin.availability')) ?></label>
      <select class="select" id="available" name="available">
        <option value=""><?= e(__('common.all')) ?></option>
        <option value="yes" <?= $availability === 'yes' ? 'selected' : '' ?>><?= e(__('common.available')) ?></option>
        <option value="no" <?= $availability === 'no' ? 'selected' : '' ?>><?= e(__('common.unavailable')) ?></option>
      </select>
    </div>
    <button class="btn" type="submit"><?= icon('filter') ?><span><?= e(__('common.filter')) ?></span></button>
    <a class="btn btn-ghost" href="<?= e(url('admin/menu.php')) ?>"><?= e(__('common.reset')) ?></a>
  </form>

  <div class="table-wrap">
    <?php if ($items === []): ?>
    <p class="admin-empty"><?= e(__('admin.no_items')) ?></p>
    <?php else: ?>
    <table class="table responsive">
      <thead>
        <tr>
          <th><?= e(__('common.item')) ?></th>
          <th><?= e(__('admin.category')) ?></th>
          <th class="num"><?= e(__('admin.price')) ?></th>
          <th><?= e(__('admin.availability')) ?></th>
          <th><?= e(__('admin.featured')) ?></th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($items as $item): ?>
        <tr>
          <td data-label="<?= e(__('common.item')) ?>">
            <div class="cell-main">
              <img class="thumb" src="<?= e(menu_image_url($item['image_path'])) ?>" alt="" width="44" height="44" loading="lazy">
              <div>
                <strong><?= e(localized($item, 'name')) ?></strong>
                <small><?= e(is_bn() ? $item['name_en'] : $item['name_bn']) ?><?= (int) $item['is_demo'] === 1 ? ' · ' . e(__('common.demo')) : '' ?></small>
              </div>
            </div>
          </td>
          <td data-label="<?= e(__('admin.category')) ?>"><?= e(localized(['name_bn' => $item['category_bn'], 'name_en' => $item['category_en']], 'name')) ?></td>
          <td data-label="<?= e(__('admin.price')) ?>" class="num"><?= e(money_from_decimal((string) $item['price'])) ?></td>
          <td data-label="<?= e(__('admin.availability')) ?>">
            <form method="post" action="<?= e(url('admin/menu.php')) ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
              <button class="badge <?= (int) $item['is_available'] === 1 ? 'badge-success' : 'badge-danger' ?>" type="submit" style="border:0;cursor:pointer">
                <?= e((int) $item['is_available'] === 1 ? __('common.available') : __('common.unavailable')) ?>
              </button>
            </form>
          </td>
          <td data-label="<?= e(__('admin.featured')) ?>"><?= (int) $item['is_featured'] === 1 ? icon('star') : '—' ?></td>
          <td class="actions">
            <a class="btn btn-secondary btn-sm" href="<?= e(url('admin/menu-edit.php?id=' . (int) $item['id'])) ?>"><?= e(__('common.edit')) ?></a>
            <form method="post" action="<?= e(url('admin/menu.php')) ?>" data-confirm="<?= e(__('admin.confirm_delete_item')) ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
              <button class="btn btn-ghost btn-sm" type="submit"><?= e(__('common.delete')) ?></button>
            </form>
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
      <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/menu.php' . query_string_with(['page' => $pagination['page'] - 1]))) ?>"><?= e(__('admin.prev')) ?></a>
      <?php endif; ?>
      <span class="btn btn-ghost btn-sm" aria-disabled="true"><?= e(__('admin.page_of', ['page' => $pagination['page'], 'pages' => $pagination['pages']])) ?></span>
      <?php if ($pagination['page'] < $pagination['pages']): ?>
      <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/menu.php' . query_string_with(['page' => $pagination['page'] + 1]))) ?>"><?= e(__('admin.next')) ?></a>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php require DH_ROOT . '/includes/partials/admin-footer.php'; ?>
