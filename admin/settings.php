<?php
/**
 * Restaurant settings: bilingual identity, contact details, delivery, tax and
 * receipt text. No credentials or mail secrets are stored here.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

$current = settings();
$errors = [];

$form = [
    'name_bn' => (string) ($current['name_bn'] ?? ''),
    'name_en' => (string) ($current['name_en'] ?? ''),
    'tagline_bn' => (string) ($current['tagline_bn'] ?? ''),
    'tagline_en' => (string) ($current['tagline_en'] ?? ''),
    'description_bn' => (string) ($current['description_bn'] ?? ''),
    'description_en' => (string) ($current['description_en'] ?? ''),
    'address_bn' => (string) ($current['address_bn'] ?? ''),
    'address_en' => (string) ($current['address_en'] ?? ''),
    'phone' => (string) ($current['phone'] ?? ''),
    'whatsapp_number' => (string) ($current['whatsapp_number'] ?? ''),
    'owner_email' => (string) ($current['owner_email'] ?? ''),
    'hours_bn' => (string) ($current['hours_bn'] ?? ''),
    'hours_en' => (string) ($current['hours_en'] ?? ''),
    'delivery_fee' => decimal_from_cents(delivery_fee_cents()),
    'minimum_order' => decimal_from_cents(minimum_order_cents()),
    'tax_percent' => number_format(tax_rate_basis_points() / 100, 2, '.', ''),
    'notification_language' => (string) ($current['notification_language'] ?? 'bn'),
    'receipt_footer_bn' => (string) ($current['receipt_footer_bn'] ?? ''),
    'receipt_footer_en' => (string) ($current['receipt_footer_en'] ?? ''),
];

if (is_post()) {
    require_csrf();

    foreach (['name_bn', 'name_en', 'tagline_bn', 'tagline_en', 'address_bn', 'address_en', 'hours_bn', 'hours_en', 'receipt_footer_bn', 'receipt_footer_en'] as $textKey) {
        $form[$textKey] = clean_text($_POST[$textKey] ?? '', 255);
    }
    $form['description_bn'] = clean_text($_POST['description_bn'] ?? '', 1000);
    $form['description_en'] = clean_text($_POST['description_en'] ?? '', 1000);
    $form['phone'] = clean_text($_POST['phone'] ?? '', 20);
    $form['whatsapp_number'] = clean_text($_POST['whatsapp_number'] ?? '', 20);
    $form['owner_email'] = clean_text($_POST['owner_email'] ?? '', 190);
    $form['delivery_fee'] = trim((string) ($_POST['delivery_fee'] ?? ''));
    $form['minimum_order'] = trim((string) ($_POST['minimum_order'] ?? ''));
    $form['tax_percent'] = trim((string) ($_POST['tax_percent'] ?? ''));
    $form['notification_language'] = (string) ($_POST['notification_language'] ?? 'bn');

    if (mb_strlen($form['name_bn'], 'UTF-8') < 2 || mb_strlen($form['name_en'], 'UTF-8') < 2) {
        $errors[] = __('admin.name_error');
    }
    if ($form['phone'] !== '' && preg_match('/^[0-9+\-\s()]{6,20}$/', $form['phone']) !== 1) {
        $errors[] = __('admin.invalid_number');
    }
    if ($form['whatsapp_number'] !== '' && !is_valid_bd_phone($form['whatsapp_number'])) {
        $errors[] = __('admin.invalid_number');
    }
    if ($form['owner_email'] !== '' && !v_email($form['owner_email'])) {
        $errors[] = __('admin.owner_email_hint');
    }

    $feeCents = parse_money_input($form['delivery_fee']);
    $minimumCents = parse_money_input($form['minimum_order']);
    $taxBasisPoints = parse_money_input($form['tax_percent']);

    if ($feeCents === null) {
        $errors[] = __('admin.invalid_number');
    }
    if ($minimumCents === null) {
        $errors[] = __('admin.invalid_number');
    }
    if ($taxBasisPoints === null || $taxBasisPoints > 10000) {
        $errors[] = __('admin.invalid_tax');
    }
    if (!in_array($form['notification_language'], available_languages(), true)) {
        $errors[] = __('admin.language_notice');
    }

    if ($errors === []) {
        $stored = [
            'name_bn' => $form['name_bn'],
            'name_en' => $form['name_en'],
            'tagline_bn' => $form['tagline_bn'],
            'tagline_en' => $form['tagline_en'],
            'description_bn' => $form['description_bn'],
            'description_en' => $form['description_en'],
            'address_bn' => $form['address_bn'],
            'address_en' => $form['address_en'],
            'phone' => $form['phone'],
            'whatsapp_number' => $form['whatsapp_number'],
            'owner_email' => $form['owner_email'],
            'hours_bn' => $form['hours_bn'],
            'hours_en' => $form['hours_en'],
            'delivery_fee' => decimal_from_cents((int) $feeCents),
            'minimum_order' => decimal_from_cents((int) $minimumCents),
            'tax_percent' => decimal_from_cents((int) $taxBasisPoints),
            'notification_language' => $form['notification_language'],
            'receipt_footer_bn' => $form['receipt_footer_bn'],
            'receipt_footer_en' => $form['receipt_footer_en'],
            'currency' => 'BDT',
        ];

        foreach ($stored as $key => $value) {
            set_setting($key, $value);
        }

        audit_log('settings.updated', 'settings', null, ['keys' => array_keys($stored)]);
        flash('success', __('admin.settings_saved'));
        redirect('admin/settings.php');
    }
}

$pageTitle = __('admin.settings_title');
$activeNav = 'settings';
require DH_ROOT . '/includes/partials/admin-head.php';
?>
<div class="admin-head">
  <div>
    <h1><?= e(__('admin.settings_title')) ?></h1>
    <p><?= e(__('admin.settings_subtitle')) ?></p>
  </div>
</div>

<?php if ($errors !== []): ?>
<div class="alert alert-danger" role="alert">
  <?= icon('alert') ?>
  <div>
    <?php foreach (array_unique($errors) as $error): ?>
    <div><?= e($error) ?></div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php if (config('app.demo_mode')): ?>
<div class="alert alert-info" role="status">
  <?= icon('info') ?>
  <div><?= e(__('admin.demo_reset_hint')) ?></div>
</div>
<?php endif; ?>

<form method="post" action="<?= e(url('admin/settings.php')) ?>">
  <?= csrf_field() ?>

  <div class="grid-2">
    <div class="panel">
      <div class="panel-head"><h2><?= e(__('admin.general')) ?></h2></div>
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
            <label for="tagline_bn"><?= e(__('admin.tagline')) ?> (বাংলা)</label>
            <input class="input" type="text" id="tagline_bn" name="tagline_bn" value="<?= e($form['tagline_bn']) ?>" maxlength="160" lang="bn">
          </div>
          <div class="field">
            <label for="tagline_en"><?= e(__('admin.tagline')) ?> (English)</label>
            <input class="input" type="text" id="tagline_en" name="tagline_en" value="<?= e($form['tagline_en']) ?>" maxlength="160" lang="en">
          </div>
        </div>
        <div class="form-row two">
          <div class="field">
            <label for="description_bn"><?= e(__('admin.description_bn')) ?></label>
            <textarea class="textarea" id="description_bn" name="description_bn" maxlength="1000" lang="bn"><?= e($form['description_bn']) ?></textarea>
          </div>
          <div class="field">
            <label for="description_en"><?= e(__('admin.description_en')) ?></label>
            <textarea class="textarea" id="description_en" name="description_en" maxlength="1000" lang="en"><?= e($form['description_en']) ?></textarea>
          </div>
        </div>
        <div class="field">
          <label for="currency"><?= e(__('admin.currency')) ?></label>
          <input class="input" type="text" id="currency" value="BDT (৳)" readonly disabled>
          <p class="hint"><?= e(__('admin.currency_note')) ?></p>
        </div>
      </div>
    </div>

    <div class="panel">
      <div class="panel-head"><h2><?= e(__('admin.contact')) ?></h2></div>
      <div class="panel-body form-section">
        <div class="form-row two">
          <div class="field">
            <label for="phone"><?= e(__('admin.phone')) ?></label>
            <input class="input" type="tel" id="phone" name="phone" value="<?= e($form['phone']) ?>" maxlength="20" inputmode="tel">
          </div>
          <div class="field">
            <label for="whatsapp_number"><?= e(__('admin.whatsapp')) ?></label>
            <input class="input" type="tel" id="whatsapp_number" name="whatsapp_number" value="<?= e($form['whatsapp_number']) ?>" maxlength="20" inputmode="tel" placeholder="01XXXXXXXXX">
            <p class="hint"><?= e(__('admin.whatsapp_hint_long')) ?></p>
          </div>
        </div>
        <div class="field">
          <label for="owner_email"><?= e(__('admin.owner_email')) ?></label>
          <input class="input" type="email" id="owner_email" name="owner_email" value="<?= e($form['owner_email']) ?>" maxlength="190" inputmode="email">
          <p class="hint"><?= e(__('admin.owner_email_hint')) ?></p>
        </div>
        <div class="form-row two">
          <div class="field">
            <label for="address_bn"><?= e(__('admin.address_bn')) ?></label>
            <input class="input" type="text" id="address_bn" name="address_bn" value="<?= e($form['address_bn']) ?>" maxlength="255" lang="bn">
          </div>
          <div class="field">
            <label for="address_en"><?= e(__('admin.address_en')) ?></label>
            <input class="input" type="text" id="address_en" name="address_en" value="<?= e($form['address_en']) ?>" maxlength="255" lang="en">
          </div>
        </div>
        <div class="form-row two">
          <div class="field">
            <label for="hours_bn"><?= e(__('admin.hours')) ?> (বাংলা)</label>
            <input class="input" type="text" id="hours_bn" name="hours_bn" value="<?= e($form['hours_bn']) ?>" maxlength="160" lang="bn">
          </div>
          <div class="field">
            <label for="hours_en"><?= e(__('admin.hours')) ?> (English)</label>
            <input class="input" type="text" id="hours_en" name="hours_en" value="<?= e($form['hours_en']) ?>" maxlength="160" lang="en">
          </div>
        </div>
      </div>
    </div>

    <div class="panel">
      <div class="panel-head"><h2><?= e(__('admin.delivery')) ?></h2></div>
      <div class="panel-body form-section">
        <div class="form-row three">
          <div class="field">
            <label for="delivery_fee"><?= e(__('admin.delivery_fee')) ?> (৳)</label>
            <input class="input" type="text" id="delivery_fee" name="delivery_fee" value="<?= e($form['delivery_fee']) ?>" inputmode="decimal" required>
          </div>
          <div class="field">
            <label for="minimum_order"><?= e(__('admin.minimum_order')) ?> (৳)</label>
            <input class="input" type="text" id="minimum_order" name="minimum_order" value="<?= e($form['minimum_order']) ?>" inputmode="decimal" required>
          </div>
          <div class="field">
            <label for="tax_percent"><?= e(__('admin.tax_percent')) ?> (%)</label>
            <input class="input" type="text" id="tax_percent" name="tax_percent" value="<?= e($form['tax_percent']) ?>" inputmode="decimal" required>
            <p class="hint"><?= e(__('admin.tax_hint')) ?></p>
          </div>
        </div>
        <p class="small muted"><?= e(__('admin.percent')) ?>: 0–100 · <?= e(__('admin.currency_note')) ?></p>
      </div>
    </div>

    <div class="panel">
      <div class="panel-head"><h2><?= e(__('admin.notifications_title')) ?></h2></div>
      <div class="panel-body form-section">
        <div class="field">
          <label for="notification_language"><?= e(__('admin.notification_language')) ?></label>
          <select class="select" id="notification_language" name="notification_language">
            <option value="bn" <?= $form['notification_language'] === 'bn' ? 'selected' : '' ?>>বাংলা</option>
            <option value="en" <?= $form['notification_language'] === 'en' ? 'selected' : '' ?>>English</option>
          </select>
          <p class="hint"><?= e(__('admin.notification_lang')) ?></p>
        </div>
        <div class="form-row two">
          <div class="field">
            <label for="receipt_footer_bn"><?= e(__('admin.receipt_footer')) ?> (বাংলা)</label>
            <input class="input" type="text" id="receipt_footer_bn" name="receipt_footer_bn" value="<?= e($form['receipt_footer_bn']) ?>" maxlength="160" lang="bn">
          </div>
          <div class="field">
            <label for="receipt_footer_en"><?= e(__('admin.receipt_footer')) ?> (English)</label>
            <input class="input" type="text" id="receipt_footer_en" name="receipt_footer_en" value="<?= e($form['receipt_footer_en']) ?>" maxlength="160" lang="en">
          </div>
        </div>
      </div>
    </div>
  </div>

  <div style="display:flex;justify-content:flex-end;margin-top:16px">
    <button class="btn btn-lg" type="submit"><?= icon('check') ?><span><?= e(__('admin.save_changes')) ?></span></button>
  </div>
</form>
<?php require DH_ROOT . '/includes/partials/admin-footer.php'; ?>
