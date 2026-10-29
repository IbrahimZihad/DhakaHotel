<?php
/**
 * Create or edit a menu item, including a validated image upload.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

$itemId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$item = $itemId > 0 ? fetch_one('SELECT * FROM menu_items WHERE id = ?', [$itemId]) : null;

if ($itemId > 0 && $item === null) {
    flash('error', __('admin.item_not_found'));
    redirect('admin/menu.php');
}

$categories = fetch_all('SELECT id, name_bn, name_en, is_active FROM categories ORDER BY sort_order, id');
$errors = [];

$form = [
    'category_id' => (int) ($item['category_id'] ?? ($categories[0]['id'] ?? 0)),
    'name_bn' => (string) ($item['name_bn'] ?? ''),
    'name_en' => (string) ($item['name_en'] ?? ''),
    'description_bn' => (string) ($item['description_bn'] ?? ''),
    'description_en' => (string) ($item['description_en'] ?? ''),
    'price' => $item !== null ? (string) $item['price'] : '',
    'sort_order' => (int) ($item['sort_order'] ?? 0),
    'is_available' => $item === null ? 1 : (int) $item['is_available'],
    'is_featured' => (int) ($item['is_featured'] ?? 0),
    'image_path' => $item['image_path'] ?? null,
];

if (is_post()) {
    require_csrf();

    $form['category_id'] = (int) ($_POST['category_id'] ?? 0);
    $form['name_bn'] = clean_text($_POST['name_bn'] ?? '', 120);
    $form['name_en'] = clean_text($_POST['name_en'] ?? '', 120);
    $form['description_bn'] = clean_text($_POST['description_bn'] ?? '', 2000);
    $form['description_en'] = clean_text($_POST['description_en'] ?? '', 2000);
    $form['price'] = trim((string) ($_POST['price'] ?? ''));
    $form['sort_order'] = parse_int($_POST['sort_order'] ?? 0);
    $form['is_available'] = isset($_POST['is_available']) ? 1 : 0;
    $form['is_featured'] = isset($_POST['is_featured']) ? 1 : 0;

    $priceCents = parse_money_input($form['price']);

    if (!fetch_one('SELECT id FROM categories WHERE id = ?', [$form['category_id']])) {
        $errors[] = __('admin.category_error');
    }
    if (mb_strlen($form['name_bn'], 'UTF-8') < 2 || mb_strlen($form['name_en'], 'UTF-8') < 2) {
        $errors[] = __('admin.name_error');
    }
    if ($priceCents === null) {
        $errors[] = __('admin.price_error');
    }

    if (isset($_POST['remove_image']) && $_POST['remove_image'] === '1') {
        remove_upload($form['image_path']);
        $form['image_path'] = null;
    }

    $upload = $_FILES['image'] ?? null;
    if (is_array($upload) && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $stored = store_menu_image($upload, current_lang());
        if ($stored['ok']) {
            remove_upload($form['image_path']);
            $form['image_path'] = $stored['path'];
        } else {
            $errors[] = $stored['error'];
        }
    }

    if ($errors === []) {
        $data = [
            'category_id' => $form['category_id'],
            'name_bn' => $form['name_bn'],
            'name_en' => $form['name_en'],
            'description_bn' => $form['description_bn'] !== '' ? $form['description_bn'] : null,
            'description_en' => $form['description_en'] !== '' ? $form['description_en'] : null,
            'price' => decimal_from_cents((int) $priceCents),
            'image_path' => $form['image_path'],
            'is_available' => $form['is_available'],
            'is_featured' => $form['is_featured'],
            'sort_order' => max(-999, min(999, $form['sort_order'])),
        ];

        if ($item === null) {
            $newId = insert_row('menu_items', $data);
            audit_log('menu.created', 'menu_item', $newId, ['name_en' => $data['name_en']]);
            flash('success', __('admin.item_created'));
        } else {
            update_row('menu_items', $itemId, $data);
            audit_log('menu.updated', 'menu_item', $itemId, ['name_en' => $data['name_en']]);
            flash('success', __('admin.item_updated'));
        }

        redirect('admin/menu.php');
    }
}

$pageTitle = $item === null ? __('admin.add_item') : __('admin.edit_item');
$activeNav = 'menu';
require DH_ROOT . '/includes/partials/admin-head.php';
?>
<div class="admin-head">
  <div>
    <h1><?= e($pageTitle) ?></h1>
    <p><?= e(__('admin.menu_subtitle')) ?></p>
  </div>
  <div class="admin-head-actions">
    <a class="btn btn-secondary" href="<?= e(url('admin/menu.php')) ?>"><?= icon('chevron-left') ?><span><?= e(__('admin.nav_menu')) ?></span></a>
  </div>
</div>

<?php if ($errors !== []): ?>
<div class="alert alert-danger" role="alert">
  <?= icon('alert') ?>
  <div>
    <?php foreach ($errors as $error): ?>
    <div><?= e($error) ?></div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<form method="post" action="<?= e(url('admin/menu-edit.php' . ($item !== null ? '?id=' . $itemId : ''))) ?>" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <?php if ($item !== null): ?><input type="hidden" name="id" value="<?= $itemId ?>"><?php endif; ?>

  <div class="grid-main-side">
    <div class="panel">
      <div class="panel-head"><h2><?= e(__('admin.menu_title')) ?></h2></div>
      <div class="panel-body form-section">
        <div class="form-row two">
          <div class="field">
            <label for="name_bn"><?= e(__('admin.name_bn')) ?> *</label>
            <input class="input" type="text" id="name_bn" name="name_bn" value="<?= e($form['name_bn']) ?>" maxlength="120" required lang="bn">
          </div>
          <div class="field">
            <label for="name_en"><?= e(__('admin.name_en')) ?> *</label>
            <input class="input" type="text" id="name_en" name="name_en" value="<?= e($form['name_en']) ?>" maxlength="120" required lang="en">
          </div>
        </div>

        <div class="form-row two">
          <div class="field">
            <label for="description_bn"><?= e(__('admin.description_bn')) ?></label>
            <textarea class="textarea" id="description_bn" name="description_bn" maxlength="2000" lang="bn"><?= e($form['description_bn']) ?></textarea>
          </div>
          <div class="field">
            <label for="description_en"><?= e(__('admin.description_en')) ?></label>
            <textarea class="textarea" id="description_en" name="description_en" maxlength="2000" lang="en"><?= e($form['description_en']) ?></textarea>
          </div>
        </div>

        <div class="form-row three">
          <div class="field">
            <label for="category_id"><?= e(__('admin.category')) ?> *</label>
            <select class="select" id="category_id" name="category_id" required>
              <?php foreach ($categories as $category): ?>
              <option value="<?= (int) $category['id'] ?>" <?= $form['category_id'] === (int) $category['id'] ? 'selected' : '' ?>>
                <?= e(localized($category, 'name')) ?><?= (int) $category['is_active'] === 1 ? '' : ' — ' . e(__('common.unavailable')) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="price"><?= e(__('admin.price_bdt')) ?> *</label>
            <input class="input" type="text" id="price" name="price" value="<?= e($form['price']) ?>" inputmode="decimal" required>
          </div>
          <div class="field">
            <label for="sort_order"><?= e(__('admin.sort_order')) ?></label>
            <input class="input" type="number" id="sort_order" name="sort_order" value="<?= $form['sort_order'] ?>" min="-999" max="999" step="1">
          </div>
        </div>
      </div>
    </div>

    <div class="stack">
      <div class="panel">
        <div class="panel-head"><h2><?= e(__('admin.image')) ?></h2></div>
        <div class="panel-body stack">
          <img class="image-preview" id="image-preview" src="<?= e(menu_image_url($form['image_path'])) ?>" alt="" width="260" height="195">
          <div class="file-drop">
            <label for="image" style="font-weight:600"><?= e(__('common.upload')) ?></label>
            <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp" data-image-input="image-preview">
            <p class="small muted" style="margin-top:6px"><?= e(__('admin.image_hint')) ?></p>
          </div>
          <?php if (!empty($form['image_path'])): ?>
          <label class="small" style="display:flex;gap:8px;align-items:center">
            <input type="checkbox" name="remove_image" value="1">
            <span><?= e(__('admin.remove_image')) ?></span>
          </label>
          <?php endif; ?>
        </div>
      </div>

      <div class="panel">
        <div class="panel-head"><h2><?= e(__('admin.availability')) ?></h2></div>
        <div class="panel-body stack">
          <label style="display:flex;gap:10px;align-items:center">
            <input type="checkbox" name="is_available" value="1" <?= $form['is_available'] === 1 ? 'checked' : '' ?>>
            <span><?= e(__('admin.visible_public')) ?></span>
          </label>
          <label style="display:flex;gap:10px;align-items:center">
            <input type="checkbox" name="is_featured" value="1" <?= $form['is_featured'] === 1 ? 'checked' : '' ?>>
            <span><?= e(__('admin.featured_item')) ?></span>
          </label>
        </div>
      </div>

      <div style="display:grid;gap:8px">
        <button class="btn btn-lg" type="submit"><?= icon('check') ?><span><?= e($item === null ? __('admin.create') : __('admin.save_changes')) ?></span></button>
        <?php if ($item !== null): ?>
        <a class="btn btn-ghost" href="<?= e(url('menu-item.php?id=' . $itemId)) ?>" target="_blank" rel="noopener"><?= icon('eye') ?><span><?= e(__('admin.menu_preview')) ?></span></a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</form>
<?php require DH_ROOT . '/includes/partials/admin-footer.php'; ?>
