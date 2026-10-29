<?php
/**
 * Bilingual categories: create, rename, reorder, deactivate and delete.
 * A category that still holds menu items can never be deleted (FK RESTRICT).
 */

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

$editing = null;
$editId = (int) ($_GET['edit'] ?? 0);
if ($editId > 0) {
    $editing = fetch_one('SELECT * FROM categories WHERE id = ?', [$editId]);
}

$uniqueSlug = static function (string $base, int $ignoreId): string {
    $slug = slugify($base, 'category');
    $candidate = $slug;
    $suffix = 2;
    while (fetch_value('SELECT id FROM categories WHERE slug = ? AND id <> ?', [$candidate, $ignoreId]) !== null) {
        $candidate = $slug . '-' . $suffix;
        $suffix++;
    }

    return $candidate;
};

if (is_post()) {
    require_csrf();
    $action = (string) ($_POST['action'] ?? 'save');
    $categoryId = (int) ($_POST['id'] ?? 0);
    $category = $categoryId > 0 ? fetch_one('SELECT * FROM categories WHERE id = ?', [$categoryId]) : null;

    if ($action === 'toggle' && $category !== null) {
        q('UPDATE categories SET is_active = ? WHERE id = ?', [(int) $category['is_active'] === 1 ? 0 : 1, $categoryId]);
        audit_log('category.toggle', 'category', $categoryId);
        flash('success', __('admin.category_updated'));
        redirect('admin/categories.php');
    }

    if ($action === 'delete' && $category !== null) {
        $itemCount = (int) fetch_value('SELECT COUNT(*) FROM menu_items WHERE category_id = ?', [$categoryId], 0);
        if ($itemCount > 0) {
            flash('error', __('admin.category_in_use'));
        } else {
            q('DELETE FROM categories WHERE id = ?', [$categoryId]);
            audit_log('category.deleted', 'category', $categoryId, ['name_en' => $category['name_en']]);
            flash('success', __('admin.category_deleted'));
        }
        redirect('admin/categories.php');
    }

    if ($action === 'save') {
        $nameBn = clean_text($_POST['name_bn'] ?? '', 120);
        $nameEn = clean_text($_POST['name_en'] ?? '', 120);
        $descBn = clean_text($_POST['description_bn'] ?? '', 255);
        $descEn = clean_text($_POST['description_en'] ?? '', 255);
        $sortOrder = max(-999, min(999, parse_int($_POST['sort_order'] ?? 0)));
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if (mb_strlen($nameBn, 'UTF-8') < 2 || mb_strlen($nameEn, 'UTF-8') < 2) {
            flash('error', __('admin.name_error'));
            redirect('admin/categories.php' . ($category !== null ? '?edit=' . $categoryId : ''));
        }

        $data = [
            'name_bn' => $nameBn,
            'name_en' => $nameEn,
            'description_bn' => $descBn !== '' ? $descBn : null,
            'description_en' => $descEn !== '' ? $descEn : null,
            'slug' => $uniqueSlug($nameEn, $categoryId),
            'is_active' => $isActive,
            'sort_order' => $sortOrder,
        ];

        if ($category === null) {
            $newId = insert_row('categories', $data);
            audit_log('category.created', 'category', $newId, ['name_en' => $nameEn]);
            flash('success', __('admin.category_created'));
        } else {
            update_row('categories', $categoryId, $data);
            audit_log('category.updated', 'category', $categoryId, ['name_en' => $nameEn]);
            flash('success', __('admin.category_updated'));
        }

        redirect('admin/categories.php');
    }
}

$categories = fetch_all(
    'SELECT c.*, (SELECT COUNT(*) FROM menu_items mi WHERE mi.category_id = c.id) AS item_count
     FROM categories c ORDER BY c.sort_order, c.id'
);

$form = [
    'id' => (int) ($editing['id'] ?? 0),
    'name_bn' => (string) ($editing['name_bn'] ?? ''),
    'name_en' => (string) ($editing['name_en'] ?? ''),
    'description_bn' => (string) ($editing['description_bn'] ?? ''),
    'description_en' => (string) ($editing['description_en'] ?? ''),
    'sort_order' => (int) ($editing['sort_order'] ?? 0),
    'is_active' => $editing === null ? 1 : (int) $editing['is_active'],
];

$pageTitle = __('admin.categories_title');
$activeNav = 'categories';
require DH_ROOT . '/includes/partials/admin-head.php';
?>
<div class="admin-head">
  <div>
    <h1><?= e(__('admin.categories_title')) ?></h1>
    <p><?= e(__('admin.categories_subtitle')) ?></p>
  </div>
</div>

<div class="grid-main-side">
  <div class="panel">
    <div class="panel-head"><h2><?= e(__('admin.categories_title')) ?></h2></div>
    <div class="table-wrap">
      <?php if ($categories === []): ?>
      <p class="admin-empty"><?= e(__('admin.no_categories')) ?></p>
      <?php else: ?>
      <table class="table responsive">
        <thead>
          <tr>
            <th><?= e(__('admin.category')) ?></th>
            <th><?= e(__('admin.slug')) ?></th>
            <th><?= e(__('admin.items_in_category')) ?></th>
            <th><?= e(__('admin.active')) ?></th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($categories as $category): ?>
          <tr>
            <td data-label="<?= e(__('admin.category')) ?>">
              <strong><?= e(localized($category, 'name')) ?></strong>
              <br><small class="muted"><?= e(is_bn() ? $category['name_en'] : $category['name_bn']) ?></small>
            </td>
            <td data-label="<?= e(__('admin.slug')) ?>"><code style="font-size:0.75rem"><?= e($category['slug']) ?></code></td>
            <td data-label="<?= e(__('admin.items_in_category')) ?>"><?= e(is_bn() ? bn_digits((string) (int) $category['item_count']) : (string) (int) $category['item_count']) ?></td>
            <td data-label="<?= e(__('admin.active')) ?>">
              <form method="post" action="<?= e(url('admin/categories.php')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="id" value="<?= (int) $category['id'] ?>">
                <button type="submit" class="badge <?= (int) $category['is_active'] === 1 ? 'badge-success' : 'badge-danger' ?>" style="border:0;cursor:pointer">
                  <?= e((int) $category['is_active'] === 1 ? __('common.yes') : __('common.no')) ?>
                </button>
              </form>
            </td>
            <td class="actions">
              <a class="btn btn-secondary btn-sm" href="<?= e(url('admin/categories.php?edit=' . (int) $category['id'])) ?>"><?= e(__('common.edit')) ?></a>
              <form method="post" action="<?= e(url('admin/categories.php')) ?>" data-confirm="<?= e(__('admin.confirm_delete_category')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int) $category['id'] ?>">
                <button class="btn btn-ghost btn-sm" type="submit"><?= e(__('common.delete')) ?></button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>

  <div class="panel">
    <div class="panel-head">
      <h2><?= e($editing === null ? __('admin.add_category') : __('admin.edit_category')) ?></h2>
      <?php if ($editing !== null): ?>
      <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/categories.php')) ?>"><?= e(__('common.cancel')) ?></a>
      <?php endif; ?>
    </div>
    <div class="panel-body">
      <form method="post" action="<?= e(url('admin/categories.php')) ?>" class="form-section">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <?php if ($form['id'] > 0): ?><input type="hidden" name="id" value="<?= $form['id'] ?>"><?php endif; ?>

        <div class="field">
          <label for="name_bn"><?= e(__('admin.category_name_bn')) ?> *</label>
          <input class="input" type="text" id="name_bn" name="name_bn" value="<?= e($form['name_bn']) ?>" maxlength="120" required lang="bn">
        </div>
        <div class="field">
          <label for="name_en"><?= e(__('admin.category_name_en')) ?> *</label>
          <input class="input" type="text" id="name_en" name="name_en" value="<?= e($form['name_en']) ?>" maxlength="120" required lang="en">
        </div>
        <div class="field">
          <label for="description_bn"><?= e(__('admin.description_bn')) ?></label>
          <input class="input" type="text" id="description_bn" name="description_bn" value="<?= e($form['description_bn']) ?>" maxlength="255" lang="bn">
        </div>
        <div class="field">
          <label for="description_en"><?= e(__('admin.description_en')) ?></label>
          <input class="input" type="text" id="description_en" name="description_en" value="<?= e($form['description_en']) ?>" maxlength="255" lang="en">
        </div>
        <div class="form-row two">
          <div class="field">
            <label for="sort_order"><?= e(__('admin.sort_order')) ?></label>
            <input class="input" type="number" id="sort_order" name="sort_order" value="<?= $form['sort_order'] ?>" min="-999" max="999" step="1">
          </div>
          <div class="field">
            <label><?= e(__('admin.active')) ?></label>
            <label style="display:flex;gap:10px;align-items:center;min-height:44px">
              <input type="checkbox" name="is_active" value="1" <?= $form['is_active'] === 1 ? 'checked' : '' ?>>
              <span><?= e(__('admin.visible_public')) ?></span>
            </label>
          </div>
        </div>
        <button class="btn" type="submit"><?= icon('check') ?><span><?= e($editing === null ? __('admin.create') : __('admin.save_changes')) ?></span></button>
      </form>
    </div>
  </div>
</div>
<?php require DH_ROOT . '/includes/partials/admin-footer.php'; ?>
