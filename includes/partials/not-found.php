<?php
/**
 * Shared "not here" page. Include it after deciding the URL is missing; it sets
 * the status, renders and stops, so a soft 404 can never slip out as a 200.
 */
http_response_code(404);
$pageTitle = __('error.not_found');
$robots = 'noindex, nofollow';
$activeNav = 'menu';
require DH_ROOT . '/includes/partials/site-head.php';
require DH_ROOT . '/includes/partials/site-header.php';
?>
<main id="main" class="container section">
  <div class="card card-pad">
    <div class="empty-state">
      <span class="icon-tile"><?= icon('info') ?></span>
      <h1><?= e(__('dish.not_found')) ?></h1>
      <a class="btn" href="<?= e(url('menu.php')) ?>"><?= e(__('dish.back_to_menu')) ?></a>
    </div>
  </div>
</main>
<?php
require DH_ROOT . '/includes/partials/site-footer.php';
exit;
