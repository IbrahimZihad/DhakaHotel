<?php
/**
 * Contact page. Only configured details are shown; nothing is invented.
 */

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$phone = trim((string) setting('phone', ''));
$email = trim((string) setting('owner_email', ''));
$address = restaurant('address');
$hours = restaurant('hours');
$waLink = whatsapp_link(__('nav.whatsapp'));

$pageTitle = __('contact.title');
$pageDescription = __('contact.subtitle');
$activeNav = 'contact';
require DH_ROOT . '/includes/partials/site-head.php';
require DH_ROOT . '/includes/partials/site-header.php';
require DH_ROOT . '/includes/partials/flash.php';
?>
<main id="main">
  <div class="page-head">
    <div class="container">
      <span class="eyebrow"><?= icon('phone') ?><?= e(restaurant_name()) ?></span>
      <h1><?= e(__('contact.title')) ?></h1>
      <p><?= e(__('contact.subtitle')) ?></p>
    </div>
  </div>

  <div class="container section-tight">
    <div class="checkout-layout">
      <section class="card card-pad">
        <ul class="contact-list">
          <li>
            <span class="icon-tile"><?= icon('phone') ?></span>
            <div>
              <strong><?= e(__('contact.phone')) ?></strong>
              <?php if ($phone !== ''): ?>
              <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $phone)) ?>"><?= e($phone) ?></a>
              <?php else: ?>
              <span><?= e(__('contact.no_phone')) ?></span>
              <?php endif; ?>
            </div>
          </li>
          <li>
            <span class="icon-tile green"><?= icon('whatsapp') ?></span>
            <div>
              <strong><?= e(__('contact.whatsapp')) ?></strong>
              <?php if ($waLink !== null): ?>
              <a href="<?= e($waLink) ?>" target="_blank" rel="noopener"><?= e(__('contact.whatsapp')) ?> <?= icon('external') ?></a>
              <?php else: ?>
              <span><?= e(__('contact.wa_setup_note')) ?></span>
              <?php endif; ?>
            </div>
          </li>
          <li>
            <span class="icon-tile amber"><?= icon('mail') ?></span>
            <div>
              <strong><?= e(__('contact.email')) ?></strong>
              <?php if ($email !== ''): ?>
              <a href="mailto:<?= e($email) ?>"><?= e($email) ?></a>
              <?php else: ?>
              <span><?= e(__('contact.no_email')) ?></span>
              <?php endif; ?>
            </div>
          </li>
          <li>
            <span class="icon-tile"><?= icon('clock') ?></span>
            <div>
              <strong><?= e(__('contact.hours')) ?></strong>
              <span><?= e($hours !== '' ? $hours : __('common.not_configured')) ?></span>
            </div>
          </li>
          <li>
            <span class="icon-tile"><?= icon('map-pin') ?></span>
            <div>
              <strong><?= e(__('contact.address')) ?></strong>
              <?php if ($address !== ''): ?>
              <span><?= e($address) ?></span>
              <a class="link-arrow" href="https://www.google.com/maps/search/?api=1&amp;query=<?= e(rawurlencode($address)) ?>" target="_blank" rel="noopener" style="margin-top:4px">
                <?= e(__('contact.map')) ?><?= icon('external') ?>
              </a>
              <?php else: ?>
              <span><?= e(__('contact.no_address')) ?></span>
              <?php endif; ?>
            </div>
          </li>
        </ul>
      </section>

      <aside class="checkout-aside">
        <div class="card card-pad">
          <h2 style="font-size:var(--fs-lg);margin-bottom:10px"><?= e(__('home.cta_title')) ?></h2>
          <p class="small muted"><?= e(__('home.cta_text')) ?></p>
          <a class="btn btn-block" href="<?= e(url('menu.php')) ?>" style="margin-top:14px"><?= e(__('home.hero_cta_menu')) ?></a>
          <?php if ($waLink !== null): ?>
          <a class="btn btn-whatsapp btn-block" href="<?= e($waLink) ?>" target="_blank" rel="noopener" style="margin-top:8px"><?= wa_icon() ?><span><?= e(__('contact.whatsapp')) ?></span></a>
          <?php endif; ?>
          <hr class="divider" style="margin:18px 0">
          <h3 style="font-size:var(--fs-sm)"><?= e(__('contact.track_hint')) ?></h3>
          <a class="link-arrow" href="<?= e(url('track-order.php')) ?>"><?= e(__('contact.track_link')) ?><?= icon('arrow-right') ?></a>
        </div>
      </aside>
    </div>
  </div>
</main>
<?php require DH_ROOT . '/includes/partials/site-footer.php'; ?>
