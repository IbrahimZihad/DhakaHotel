<?php
/**
 * Public menu with search, category filter, sorting and pagination.
 */

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$categories = fetch_all(
    'SELECT c.id, c.slug, c.name_bn, c.name_en, c.description_bn, c.description_en,
            COUNT(mi.id) AS item_count
     FROM categories c
     LEFT JOIN menu_items mi ON mi.category_id = c.id
     WHERE c.is_active = 1
     GROUP BY c.id, c.slug, c.name_bn, c.name_en, c.description_bn, c.description_en, c.sort_order
     ORDER BY c.sort_order ASC, c.name_en ASC'
);

$categoryBySlug = [];
foreach ($categories as $category) {
    $categoryBySlug[(string) $category['slug']] = $category;
}

$search = trim((string) ($_GET['q'] ?? ''));
if (mb_strlen($search) > 60) {
    $search = mb_substr($search, 0, 60);
}
$categorySlug = (string) ($_GET['category'] ?? '');
$activeCategory = $categoryBySlug[$categorySlug] ?? null;

$sortOptions = [
    'default' => __('menu.sort_default'),
    'price_asc' => __('menu.sort_price_low'),
    'price_desc' => __('menu.sort_price_high'),
    'name' => __('menu.sort_name'),
];
$sort = (string) ($_GET['sort'] ?? 'default');
if (!isset($sortOptions[$sort])) {
    $sort = 'default';
}

$where = ['c.is_active = 1'];
$params = [];
if ($activeCategory !== null) {
    $where[] = 'mi.category_id = ?';
    $params[] = (int) $activeCategory['id'];
}
if ($search !== '') {
    $like = '%' . addcslashes($search, '%_\\') . '%';
    $where[] = '(mi.name_bn LIKE ? ESCAPE \'\\\\\' OR mi.name_en LIKE ? ESCAPE \'\\\\\' OR mi.description_bn LIKE ? ESCAPE \'\\\\\' OR mi.description_en LIKE ? ESCAPE \'\\\\\')';
    array_push($params, $like, $like, $like, $like);
}
$whereSql = implode(' AND ', $where);

$total = (int) fetch_value(
    "SELECT COUNT(*) FROM menu_items mi JOIN categories c ON c.id = mi.category_id WHERE $whereSql",
    $params,
    0
);

$orderSql = match ($sort) {
    'price_asc' => 'mi.price ASC, mi.name_en ASC',
    'price_desc' => 'mi.price DESC, mi.name_en ASC',
    'name' => (current_lang() === 'bn' ? 'mi.name_bn ASC' : 'mi.name_en ASC'),
    default => 'mi.is_featured DESC, mi.sort_order ASC, mi.id ASC',
};

$perPage = 24;
$pageData = paginate($total, $perPage, current_page_number());
$items = $total > 0 ? fetch_all(
    "SELECT mi.*, c.name_bn AS category_name_bn, c.name_en AS category_name_en, c.slug AS category_slug
     FROM menu_items mi
     JOIN categories c ON c.id = mi.category_id
     WHERE $whereSql
     ORDER BY $orderSql
     LIMIT {$perPage} OFFSET {$pageData['offset']}",
    $params
) : [];

$pageTitle = __('menu.title');
$pageDescription = __('menu.subtitle');
$activeNav = 'menu';
require DH_ROOT . '/includes/partials/site-head.php';
require DH_ROOT . '/includes/partials/site-header.php';
require DH_ROOT . '/includes/partials/flash.php';
?>
<main id="main">
  <div class="page-head">
    <div class="container">
      <span class="eyebrow"><?= e(restaurant_name()) ?></span>
      <h1><?= e(__('menu.title')) ?></h1>
      <p><?= e(__('menu.subtitle')) ?></p>
    </div>
  </div>

  <div class="container section-tight">
    <div class="chip-row" style="margin-bottom:16px" role="group" aria-label="<?= e(__('menu.category_label')) ?>">
      <a class="chip" href="<?= e(url('menu.php') . query_string_with(['category' => null, 'page' => null])) ?>" <?= $activeCategory === null ? 'aria-pressed="true"' : '' ?>>
        <?= e(__('common.all')) ?>
      </a>
      <?php foreach ($categories as $category): ?>
      <a class="chip" href="<?= e(url('menu.php') . query_string_with(['category' => $category['slug'], 'page' => null])) ?>"
         <?= $activeCategory !== null && $activeCategory['slug'] === $category['slug'] ? 'aria-pressed="true"' : '' ?>>
        <?= e(item_name($category)) ?>
      </a>
      <?php endforeach; ?>
    </div>

    <form class="toolbar" method="get" action="<?= e(url('menu.php')) ?>" role="search">
      <div class="search-box">
        <?= icon('search') ?>
        <label class="sr-only" for="menu-search"><?= e(__('menu.search_label')) ?></label>
        <input class="input" id="menu-search" type="search" name="q" value="<?= e($search) ?>" placeholder="<?= e(__('menu.search_placeholder')) ?>" autocomplete="off">
      </div>
      <div class="toolbar-row">
        <label class="sr-only" for="menu-category"><?= e(__('menu.category_label')) ?></label>
        <select class="select" id="menu-category" name="category">
          <option value=""><?= e(__('common.all')) ?></option>
          <?php foreach ($categories as $category): ?>
          <option value="<?= e($category['slug']) ?>" <?= $activeCategory !== null && $activeCategory['slug'] === $category['slug'] ? 'selected' : '' ?>><?= e(item_name($category)) ?></option>
          <?php endforeach; ?>
        </select>
        <label class="sr-only" for="menu-sort"><?= e(__('menu.sort_label')) ?></label>
        <select class="select" id="menu-sort" name="sort">
          <?php foreach ($sortOptions as $value => $label): ?>
          <option value="<?= e($value) ?>" <?= $sort === $value ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
        <button class="btn" type="submit"><?= icon('search') ?><span><?= e(__('common.apply')) ?></span></button>
        <?php if ($search !== '' || $activeCategory !== null || $sort !== 'default'): ?>
        <a class="btn btn-ghost" href="<?= e(url('menu.php')) ?>"><?= e(__('common.reset')) ?></a>
        <?php endif; ?>
      </div>
    </form>

    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin:18px 0 12px">
      <p class="small muted" aria-live="polite"><?= e(__('menu.results', ['count' => is_bn() ? bn_digits((string) $total) : (string) $total])) ?></p>
      <span class="badge badge-demo"><?= e(__('menu.demo_note')) ?></span>
    </div>

    <?php if ($items === []): ?>
    <div class="card card-pad">
      <div class="empty-state">
        <span class="icon-tile"><?= icon('search') ?></span>
        <h2><?= e(__('menu.empty_title')) ?></h2>
        <p><?= e(__('menu.empty_text')) ?></p>
        <a class="btn" href="<?= e(url('menu.php')) ?>"><?= e(__('menu.clear_filters')) ?></a>
      </div>
    </div>
    <?php else: ?>
    <div class="dish-grid">
      <?php foreach ($items as $dish):
          $showCategory = $activeCategory === null;
          require DH_ROOT . '/includes/partials/dish-card.php';
      endforeach; ?>
    </div>

    <?php if ($pageData['pages'] > 1): ?>
    <nav class="pagination" aria-label="<?= e(__('menu.title')) ?>">
      <?php if ($pageData['page'] > 1): ?>
      <a href="<?= e(url('menu.php') . query_string_with(['page' => $pageData['page'] - 1])) ?>" rel="prev" aria-label="<?= e(__('common.previous_page')) ?>"><?= icon('chevron-left') ?></a>
      <?php endif; ?>
      <?php for ($i = 1; $i <= $pageData['pages']; $i++): ?>
        <?php if ($i === $pageData['page']): ?>
        <span class="is-current" aria-current="page"><?= e(is_bn() ? bn_digits((string) $i) : (string) $i) ?></span>
        <?php else: ?>
        <a href="<?= e(url('menu.php') . query_string_with(['page' => $i])) ?>"><?= e(is_bn() ? bn_digits((string) $i) : (string) $i) ?></a>
        <?php endif; ?>
      <?php endfor; ?>
      <?php if ($pageData['page'] < $pageData['pages']): ?>
      <a href="<?= e(url('menu.php') . query_string_with(['page' => $pageData['page'] + 1])) ?>" rel="next" aria-label="<?= e(__('common.next_page')) ?>"><?= icon('chevron-right') ?></a>
      <?php endif; ?>
    </nav>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</main>
<?php require DH_ROOT . '/includes/partials/site-footer.php'; ?>
