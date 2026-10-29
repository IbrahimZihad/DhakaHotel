<?php
/**
 * Language switcher. Renders plain links that preserve the current URL.
 * Expects: $class (optional extra class), $id (optional id).
 */
$currentLangCode = current_lang();
$switcherClass = trim('lang-switch ' . ($class ?? ''));
?>
<nav class="<?= e($switcherClass) ?>" <?= isset($id) ? 'id="' . e($id) . '"' : '' ?> aria-label="<?= e(__('common.language')) ?>">
  <a href="<?= e(lang_url('bn')) ?>" hreflang="bn" lang="bn" <?= $currentLangCode === 'bn' ? 'aria-current="true"' : '' ?>>বাংলা</a>
  <a href="<?= e(lang_url('en')) ?>" hreflang="en" lang="en" <?= $currentLangCode === 'en' ? 'aria-current="true"' : '' ?>>English</a>
</nav>
