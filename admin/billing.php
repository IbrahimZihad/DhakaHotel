<?php
/**
 * Single-screen POS. Menu tiles on the left, the live bill on the right.
 * Every write goes through admin/api/pos.php; the client never sets prices.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

$categories = fetch_all(
    'SELECT id, name_bn, name_en FROM categories WHERE is_active = 1 ORDER BY sort_order, id'
);
$menuItems = fetch_all(
    'SELECT mi.*, c.name_bn AS category_bn, c.name_en AS category_en
     FROM menu_items mi
     JOIN categories c ON c.id = mi.category_id
     WHERE mi.is_available = 1 AND c.is_active = 1
     ORDER BY c.sort_order, mi.sort_order, mi.id'
);

$menuById = [];
foreach ($menuItems as $menuItem) {
    $menuById[(int) $menuItem['id']] = $menuItem;
}

$resume = null;
$initialLines = [];
$initialMeta = [
    'order_type' => 'dine_in',
    'table_number' => '',
    'customer_name' => '',
    'customer_phone' => '',
    'delivery_address' => '',
    'notes' => '',
    'discount' => '0',
];
$resumeId = (int) ($_GET['resume'] ?? 0);

if ($resumeId > 0) {
    $candidate = fetch_one("SELECT * FROM orders WHERE id = ? AND source = 'pos'", [$resumeId]);
    $editable = $candidate !== null
        && (string) $candidate['status'] === 'pending'
        && cents_from_decimal((string) $candidate['amount_paid']) === 0;

    if (!$editable) {
        flash('error', __('pos.bill_locked'));
        redirect('admin/billing.php');
    }

    $resume = $candidate;
    $removed = 0;
    foreach (order_items($resumeId) as $line) {
        $menuItemId = (int) ($line['menu_item_id'] ?? 0);
        if (!isset($menuById[$menuItemId])) {
            $removed++;
            continue;
        }
        $initialLines[] = [
            'id' => $menuItemId,
            'name_bn' => $menuById[$menuItemId]['name_bn'],
            'name_en' => $menuById[$menuItemId]['name_en'],
            'price_cents' => cents_from_decimal((string) $menuById[$menuItemId]['price']),
            'quantity' => (int) $line['quantity'],
        ];
    }

    if ($removed > 0) {
        flash('warning', __('pos.error_unavailable'));
    }

    $initialMeta = [
        'order_type' => in_array($resume['order_type'], ORDER_TYPES, true) ? (string) $resume['order_type'] : 'dine_in',
        'table_number' => (string) ($resume['table_number'] ?? ''),
        'customer_name' => (string) ($resume['customer_name'] ?? ''),
        'customer_phone' => (string) ($resume['customer_phone'] ?? ''),
        'delivery_address' => (string) ($resume['delivery_address'] ?? ''),
        'notes' => (string) ($resume['notes'] ?? ''),
        'discount' => decimal_from_cents(cents_from_decimal((string) $resume['discount'])),
    ];
}

$pendingBills = pending_pos_bills(admin_id(), 8);

$posConfig = [
    'endpoint' => url('admin/api/pos.php'),
    'receiptBase' => url('admin/receipt.php'),
    'lang' => current_lang(),
    'taxBasisPoints' => tax_rate_basis_points(),
    'deliveryFeeCents' => delivery_fee_cents(),
    'resumeId' => $resume !== null ? (int) $resume['id'] : 0,
    'resumeNumber' => $resume !== null ? (string) $resume['order_number'] : '',
    'initialLines' => $initialLines,
    'initialMeta' => $initialMeta,
    'receiptPaper' => '80',
    'emptyBill' => __('pos.empty_bill'),
    'labels' => [
        'decrease' => __('pos.decrease_qty'),
        'increase' => __('pos.increase_qty'),
        'remove' => __('common.remove'),
    ],
    'messages' => [
        'empty' => __('pos.error_empty'),
        'amount' => __('pos.error_amount'),
        'reference' => __('pos.error_reference'),
        'generic' => __('pos.error_generic'),
        'csrf' => __('pos.error_csrf'),
        'confirmPayment' => __('admin.confirm_payment_note'),
        'confirmNew' => __('pos.new_bill_confirm'),
        'confirmRefresh' => __('pos.refresh_confirm'),
        'confirmLeave' => __('pos.leave_confirm'),
        'noMatches' => __('admin.no_items'),
        'change' => __('pos.change_due'),
        'remaining' => __('pos.remaining'),
        'walkIn' => __('pos.walk_in'),
        'demo' => __('common.demo'),
    ],
];

$pageTitle = __('pos.title');
$adminLayout = 'pos';
$extraJs = ['assets/js/pos.js'];
require DH_ROOT . '/includes/partials/admin-head.php';
?>
<main class="pos-shell" id="main">
  <section class="pos-catalog" aria-label="<?= e(__('pos.title')) ?>">
    <div class="pos-toolbar">
      <div class="search-box">
        <?= icon('search') ?>
        <label class="sr-only" for="pos-search"><?= e(__('pos.search_items')) ?></label>
        <input class="input" type="search" id="pos-search" placeholder="<?= e(__('pos.search_items')) ?>" autocomplete="off">
      </div>
      <label class="sr-only" for="pos-category"><?= e(__('pos.categories')) ?></label>
      <select class="select" id="pos-category">
        <option value="0"><?= e(__('pos.all_categories')) ?></option>
        <?php foreach ($categories as $category): ?>
        <option value="<?= (int) $category['id'] ?>"><?= e(localized($category, 'name')) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-secondary btn-sm" type="button" id="pos-refresh" title="<?= e(__('pos.refresh_items')) ?>">
        <?= icon('refresh') ?><span><?= e(__('pos.refresh_items')) ?></span>
      </button>
    </div>

    <div class="pos-cats" id="pos-cats" role="group" aria-label="<?= e(__('pos.categories')) ?>">
      <button class="pos-cat is-active" type="button" data-cat="0"><?= e(__('pos.all_categories')) ?></button>
      <?php foreach ($categories as $category): ?>
      <button class="pos-cat" type="button" data-cat="<?= (int) $category['id'] ?>"><?= e(localized($category, 'name')) ?></button>
      <?php endforeach; ?>
    </div>

    <?php if ($pendingBills !== []): ?>
    <div class="pos-pending-strip" aria-label="<?= e(__('pos.pending_bills')) ?>">
      <strong style="white-space:nowrap"><?= e(__('pos.pending_bills')) ?>:</strong>
      <?php foreach ($pendingBills as $pending): ?>
      <a class="pos-pending-item" href="<?= e(url('admin/billing.php?resume=' . (int) $pending['id'])) ?>" title="<?= e(__('pos.reopen')) ?>">
        <?= icon('receipt') ?>
        <span><?= e($pending['order_number']) ?></span>
        <span><?= e(money_from_decimal((string) $pending['grand_total'])) ?></span>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="pos-grid" id="pos-grid">
      <?php foreach ($menuItems as $menuItem):
          $priceCents = cents_from_decimal((string) $menuItem['price']);
      ?>
      <button class="pos-item" type="button"
        data-item-id="<?= (int) $menuItem['id'] ?>"
        data-cat="<?= (int) $menuItem['category_id'] ?>"
        data-price="<?= $priceCents ?>"
        data-name-bn="<?= e($menuItem['name_bn']) ?>"
        data-name-en="<?= e($menuItem['name_en']) ?>"
        data-search="<?= e(mb_strtolower($menuItem['name_bn'] . ' ' . $menuItem['name_en'], 'UTF-8')) ?>"
        aria-label="<?= e(localized($menuItem, 'name') . ' · ' . money($priceCents)) ?>">
        <img src="<?= e(menu_image_url($menuItem['image_path'])) ?>" alt="" width="300" height="225" loading="lazy">
        <span class="pos-item-name"><?= e(localized($menuItem, 'name')) ?></span>
        <span class="pos-item-price">
          <span><?= e(money($priceCents)) ?></span>
          <?php if ((int) $menuItem['is_featured'] === 1): ?><span><?= icon('star') ?></span><?php endif; ?>
        </span>
      </button>
      <?php endforeach; ?>
      <?php if ($menuItems === []): ?>
      <p class="pos-empty" style="grid-column:1/-1"><?= e(__('admin.no_items')) ?></p>
      <?php endif; ?>
    </div>
  </section>

  <aside class="pos-bill" aria-label="<?= e(__('pos.bill')) ?>">
    <div class="pos-bill-head">
      <h1><?= e(__('pos.bill')) ?></h1>
      <span class="badge badge-soft" id="pos-bill-number"><?= $resume !== null ? e($resume['order_number']) : e(__('pos.empty_bill')) ?></span>
    </div>

    <div class="pos-bill-meta">
      <div class="row">
        <div class="field">
          <label for="pos-type"><?= e(__('pos.order_type')) ?></label>
          <select class="select" id="pos-type">
            <?php foreach (ORDER_TYPES as $orderType): ?>
            <option value="<?= e($orderType) ?>" <?= $initialMeta['order_type'] === $orderType ? 'selected' : '' ?>><?= e(order_type_label($orderType)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field" id="pos-table-field">
          <label for="pos-table"><?= e(__('pos.table_number')) ?></label>
          <input class="input" type="text" id="pos-table" maxlength="20" value="<?= e($initialMeta['table_number']) ?>" inputmode="numeric">
        </div>
      </div>
      <div class="row">
        <div class="field">
          <label for="pos-customer"><?= e(__('pos.customer_name')) ?></label>
          <input class="input" type="text" id="pos-customer" maxlength="100" value="<?= e($initialMeta['customer_name']) ?>" autocomplete="off">
        </div>
        <div class="field">
          <label for="pos-phone"><?= e(__('pos.customer_phone')) ?></label>
          <input class="input" type="tel" id="pos-phone" maxlength="20" value="<?= e($initialMeta['customer_phone']) ?>" inputmode="tel" autocomplete="off">
        </div>
      </div>
      <div class="field" id="pos-address-field" hidden>
        <label for="pos-address"><?= e(__('pos.address')) ?></label>
        <input class="input" type="text" id="pos-address" maxlength="500" value="<?= e($initialMeta['delivery_address']) ?>">
      </div>
      <div class="field">
        <label for="pos-notes"><?= e(__('pos.notes')) ?> <span class="muted">(<?= e(__('common.optional')) ?>)</span></label>
        <input class="input" type="text" id="pos-notes" maxlength="500" value="<?= e($initialMeta['notes']) ?>">
      </div>
    </div>

    <div class="pos-lines" id="pos-lines" aria-live="polite">
      <p class="pos-empty" id="pos-empty">
        <?= icon('cart') ?>
        <span style="display:block"><?= e(__('pos.empty_bill_text')) ?></span>
      </p>
    </div>

    <ul class="pos-totals">
      <li><span><?= e(__('pos.subtotal')) ?></span><span id="pos-subtotal">৳0</span></li>
      <li class="discount-row">
        <span><?= e(__('pos.discount')) ?></span>
        <input type="text" id="pos-discount" inputmode="decimal" value="<?= e($initialMeta['discount']) ?>" aria-label="<?= e(__('pos.discount')) ?>">
        <span id="pos-discount-amount">৳0</span>
      </li>
      <li id="pos-delivery-row" hidden><span><?= e(__('pos.delivery')) ?></span><span id="pos-delivery">৳0</span></li>
      <li><span><?= e(__('pos.tax')) ?> (<span id="pos-tax-rate">0</span>%)</span><span id="pos-tax">৳0</span></li>
      <li class="total"><span><?= e(__('pos.total')) ?></span><span id="pos-total">৳0</span></li>
    </ul>

    <div class="pos-pay">
      <div class="row">
        <div class="field">
          <label for="pos-method"><?= e(__('pos.method')) ?></label>
          <select class="select" id="pos-method">
            <?php foreach (PAYMENT_METHODS as $method): ?>
            <option value="<?= e($method) ?>" <?= $method === 'cash' ? 'selected' : '' ?>><?= e(payment_method_label($method)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="pos-received"><?= e(__('pos.amount_received')) ?></label>
          <input class="input" type="text" id="pos-received" inputmode="decimal" autocomplete="off">
        </div>
      </div>
      <div class="field" id="pos-reference-field" hidden>
        <label for="pos-reference"><?= e(__('pos.reference')) ?></label>
        <input class="input" type="text" id="pos-reference" maxlength="120" placeholder="<?= e(__('pos.reference_hint')) ?>">
      </div>
      <div class="pos-change" id="pos-change">
        <span id="pos-change-label"><?= e(__('pos.change_due')) ?></span>
        <span id="pos-change-amount">৳0</span>
      </div>
      <p class="small muted" style="margin:0"><?= e(__('admin.payment_honesty')) ?></p>
    </div>

    <div class="pos-actions">
      <button class="btn btn-primary-pos" type="button" id="pos-complete"><?= icon('wallet') ?><span><?= e(__('pos.complete_payment')) ?></span></button>
      <button class="btn" type="button" id="pos-generate"><?= icon('receipt') ?><span><?= e(__('pos.generate_bill')) ?></span></button>
      <button class="btn btn-ghost" type="button" id="pos-print" disabled><?= icon('printer') ?><span><?= e(__('pos.print_receipt')) ?></span></button>
      <button class="btn btn-ghost" type="button" id="pos-save"><?= icon('clock') ?><span><?= e(__('pos.save_pending')) ?></span></button>
      <button class="btn btn-ghost" type="button" id="pos-new"><?= icon('plus') ?><span><?= e(__('pos.new_bill')) ?></span></button>
    </div>
  </aside>
</main>
<script type="application/json" id="pos-config"><?= json_encode($posConfig, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<?php require DH_ROOT . '/includes/partials/admin-footer.php'; ?>
