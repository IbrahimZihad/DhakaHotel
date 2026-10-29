<?php
/**
 * Public site footer.
 */
$phone = trim((string) setting('phone', ''));
$email = trim((string) setting('owner_email', ''));
$address = restaurant('address');
$hours = restaurant('hours');
$waLink = whatsapp_link(__('nav.whatsapp'));
$year = (int) date('Y');
?>
<footer class="site-footer">
  <div class="container">
    <div class="footer-grid">
      <div>
        <a class="brand" href="<?= e(url('')) ?>">
          <span class="brand-mark"><?= brand_mark_svg() ?></span>
          <span class="brand-name"><span><?= e(restaurant_name()) ?></span></span>
        </a>
        <p style="margin-top:14px;max-width:34ch"><?= e(mb_substr(restaurant('description'), 0, 180)) ?></p>
        <button class="btn btn-secondary btn-sm" type="button" data-install-pwa data-install-page="<?= e(url('install.php')) ?>" style="margin-top:16px">
          <?= icon('download') ?><span><?= e(__('footer.install_app')) ?></span>
        </button>
      </div>

      <div>
        <h3><?= e(__('footer.quick_links')) ?></h3>
        <nav class="footer-links" aria-label="<?= e(__('footer.quick_links')) ?>">
          <a href="<?= e(url('menu.php')) ?>"><?= e(__('nav.menu')) ?></a>
          <a href="<?= e(url('track-order.php')) ?>"><?= e(__('nav.track')) ?></a>
          <a href="<?= e(url('reviews.php')) ?>"><?= e(__('nav.reviews')) ?></a>
          <a href="<?= e(url('about.php')) ?>"><?= e(__('nav.about')) ?></a>
          <a href="<?= e(url('contact.php')) ?>"><?= e(__('nav.contact')) ?></a>
        </nav>
      </div>

      <div>
        <h3><?= e(__('footer.contact_title')) ?></h3>
        <ul class="footer-contact">
          <?php if ($phone !== ''): ?>
          <li><?= icon('phone') ?><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $phone)) ?>"><?= e($phone) ?></a></li>
          <?php else: ?>
          <li><?= icon('phone') ?><span><?= e(__('contact.no_phone')) ?></span></li>
          <?php endif; ?>
          <?php if ($waLink !== null): ?>
          <li><?= icon('whatsapp') ?><a href="<?= e($waLink) ?>" target="_blank" rel="noopener"><?= e(__('contact.whatsapp')) ?></a></li>
          <?php endif; ?>
          <?php if ($email !== ''): ?>
          <li><?= icon('mail') ?><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></li>
          <?php endif; ?>
          <?php if ($address !== ''): ?>
          <li><?= icon('map-pin') ?><span><?= e($address) ?></span></li>
          <?php endif; ?>
          <?php if ($hours !== ''): ?>
          <li><?= icon('clock') ?><span><?= e($hours) ?></span></li>
          <?php endif; ?>
        </ul>
      </div>

      <div>
        <h3><?= e(__('common.language')) ?></h3>
        <?php
        $class = 'footer-lang';
        $id = 'lang-footer';
        require DH_ROOT . '/includes/partials/lang-switcher.php';
        ?>
        <p class="small" style="margin-top:14px"><?= e(__('footer.demo_notice')) ?></p>
      </div>
    </div>

    <div class="footer-bottom">
      <span>© <?= e(is_bn() ? bn_digits((string) $year) : (string) $year) ?> <?= e(restaurant_name()) ?>. <?= e(__('footer.rights')) ?></span>
      <span>
        <a href="<?= e(url('admin/login.php')) ?>"><?= e(__('footer.manage')) ?></a>
      </span>
    </div>
  </div>
</footer>

<?php
$class = '';
$id = '';
require DH_ROOT . '/includes/partials/bottom-nav.php';
?>
<div class="toast-region" id="toast-region" aria-live="polite" aria-atomic="false"></div>
<script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>
<script src="<?= e(asset('assets/js/language.js')) ?>" defer></script>
<script src="<?= e(asset('assets/js/pwa.js')) ?>" defer></script>
