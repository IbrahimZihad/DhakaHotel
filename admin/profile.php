<?php
/**
 * Admin profile: display name, e-mail, preferred language and password.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

$admin = admin_user();
$errors = [];

if (is_post()) {
    require_csrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'account') {
        $name = clean_text($_POST['name'] ?? '', 100);
        $email = strtolower(clean_text($_POST['email'] ?? '', 190));
        $language = (string) ($_POST['preferred_language'] ?? current_lang());

        if (mb_strlen($name, 'UTF-8') < 2) {
            $errors[] = __('admin.name_error');
        }
        if (!v_email($email)) {
            $errors[] = __('admin.email_taken');
        } elseif (fetch_value('SELECT id FROM admins WHERE email = ? AND id <> ?', [$email, (int) $admin['id']]) !== null) {
            $errors[] = __('admin.email_taken');
        }
        if (!in_array($language, available_languages(), true)) {
            $errors[] = __('admin.language_notice');
        }

        if ($errors === []) {
            update_row('admins', (int) $admin['id'], [
                'name' => $name,
                'email' => $email,
                'preferred_language' => $language,
            ]);
            $_SESSION['admin_lang'] = $language;
            audit_log('admin.profile_updated', 'admin', (int) $admin['id']);
            flash('success', __('admin.profile_updated'));
            redirect('admin/profile.php');
        }
    }

    if ($action === 'password') {
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
        $hash = (string) fetch_value('SELECT password_hash FROM admins WHERE id = ?', [(int) $admin['id']], '');

        if (!password_verify($currentPassword, $hash)) {
            $errors[] = __('admin.current_password_wrong');
        }
        if (mb_strlen($newPassword, 'UTF-8') < 8) {
            $errors[] = __('admin.password_short');
        }
        if ($newPassword !== $confirmPassword) {
            $errors[] = __('admin.password_mismatch');
        }

        if ($errors === []) {
            q('UPDATE admins SET password_hash = ? WHERE id = ?', [password_hash($newPassword, PASSWORD_DEFAULT), (int) $admin['id']]);
            audit_log('admin.password_changed', 'admin', (int) $admin['id']);
            flash('success', __('admin.password_updated'));
            redirect('admin/profile.php');
        }
    }
}

$admin = admin_user();
$pageTitle = __('admin.profile_title');
$activeNav = 'profile';
require DH_ROOT . '/includes/partials/admin-head.php';
?>
<div class="admin-head">
  <div>
    <h1><?= e(__('admin.profile_title')) ?></h1>
    <p><?= e(__('admin.profile_subtitle')) ?></p>
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

<div class="grid-2">
  <div class="panel">
    <div class="panel-head"><h2><?= e(__('admin.account_details')) ?></h2></div>
    <div class="panel-body form-section">
      <form method="post" action="<?= e(url('admin/profile.php')) ?>" class="form-section">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="account">
        <div class="field">
          <label for="name"><?= e(__('common.name')) ?></label>
          <input class="input" type="text" id="name" name="name" value="<?= e($admin['name']) ?>" maxlength="100" required>
        </div>
        <div class="field">
          <label for="email"><?= e(__('admin.email')) ?></label>
          <input class="input" type="email" id="email" name="email" value="<?= e($admin['email']) ?>" maxlength="190" required>
        </div>
        <div class="field">
          <label for="preferred_language"><?= e(__('admin.preferred_language')) ?></label>
          <select class="select" id="preferred_language" name="preferred_language">
            <option value="bn" <?= $admin['preferred_language'] === 'bn' ? 'selected' : '' ?>>বাংলা</option>
            <option value="en" <?= $admin['preferred_language'] === 'en' ? 'selected' : '' ?>>English</option>
          </select>
          <p class="hint"><?= e(__('admin.language_pref')) ?></p>
        </div>
        <div>
          <button class="btn" type="submit"><?= icon('check') ?><span><?= e(__('admin.save_changes')) ?></span></button>
        </div>
      </form>

      <div style="border-top:1px solid var(--a-line);padding-top:14px" class="small muted">
        <div><strong><?= e(__('admin.last_login_at', [])) ?></strong></div>
        <div><?= e($admin['last_login_at'] !== null ? format_datetime($admin['last_login_at']) : __('common.never')) ?></div>
      </div>
    </div>
  </div>

  <div class="panel">
    <div class="panel-head"><h2><?= e(__('admin.change_password')) ?></h2></div>
    <div class="panel-body">
      <form method="post" action="<?= e(url('admin/profile.php')) ?>" class="form-section" autocomplete="off">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="password">
        <div class="field">
          <label for="current_password"><?= e(__('admin.current_password')) ?></label>
          <input class="input" type="password" id="current_password" name="current_password" required autocomplete="current-password">
        </div>
        <div class="field">
          <label for="new_password"><?= e(__('admin.new_password')) ?></label>
          <input class="input" type="password" id="new_password" name="new_password" required minlength="8" autocomplete="new-password">
          <p class="hint"><?= e(__('admin.new_password_hint')) ?></p>
        </div>
        <div class="field">
          <label for="confirm_password"><?= e(__('admin.confirm_password')) ?></label>
          <input class="input" type="password" id="confirm_password" name="confirm_password" required minlength="8" autocomplete="new-password">
        </div>
        <div>
          <button class="btn" type="submit"><?= icon('lock') ?><span><?= e(__('admin.change_password')) ?></span></button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php require DH_ROOT . '/includes/partials/admin-footer.php'; ?>
