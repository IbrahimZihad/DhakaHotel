<?php
/**
 * Moderated customer reviews. New submissions are always pending and are not
 * shown publicly until an admin approves them.
 */

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$errors = [];
$form = ['name' => '', 'rating' => '5', 'text' => ''];

if (is_post()) {
    require_csrf();

    if (trim((string) ($_POST['website'] ?? '')) !== '') {
        flash('success', __('reviews.thanks'));
        redirect('reviews.php');
    }

    $form['name'] = clean_text($_POST['display_name'] ?? '', 60);
    $form['rating'] = (string) parse_int($_POST['rating'] ?? 0);
    $form['text'] = clean_text($_POST['review_text'] ?? '', 800);

    if (!v_min_length($form['name'], 2) || !v_max_length($form['name'], 60)) {
        $errors['name'] = __('reviews.error_name');
    }
    if ((int) $form['rating'] < 1 || (int) $form['rating'] > 5) {
        $errors['rating'] = __('reviews.error_rating');
    }
    if (!v_min_length($form['text'], 10) || !v_max_length($form['text'], 800)) {
        $errors['text'] = __('reviews.error_text');
    }

    if ($errors === []) {
        insert_row('reviews', [
            'display_name' => $form['name'],
            'rating' => (int) $form['rating'],
            'review_text' => $form['text'],
            'status' => 'pending',
            'is_demo' => 0,
        ]);
        flash('success', __('reviews.thanks'));
        redirect('reviews.php');
    }
}

$page = current_page_number();
$perPage = 9;
$total = (int) fetch_value('SELECT COUNT(*) FROM reviews WHERE status = ?', ['approved'], 0);
$pageData = paginate($total, $perPage, $page);
$reviews = $total > 0 ? fetch_all(
    'SELECT * FROM reviews WHERE status = ? ORDER BY created_at DESC LIMIT ' . $perPage . ' OFFSET ' . $pageData['offset'],
    ['approved']
) : [];
$average = $total > 0 ? (float) fetch_value('SELECT AVG(rating) FROM reviews WHERE status = ?', ['approved'], 0) : 0.0;

$pageTitle = __('reviews.title');
$pageDescription = __('reviews.subtitle');
$activeNav = 'reviews';
require DH_ROOT . '/includes/partials/site-head.php';
require DH_ROOT . '/includes/partials/site-header.php';
require DH_ROOT . '/includes/partials/flash.php';
?>
<main id="main">
  <div class="page-head">
    <div class="container">
      <span class="eyebrow"><?= icon('star') ?><?= e(__('home.reviews_title')) ?></span>
      <h1><?= e(__('reviews.title')) ?></h1>
      <p><?= e(__('reviews.subtitle')) ?></p>
      <?php if ($total > 0): ?>
      <div style="display:flex;align-items:center;gap:10px;margin-top:12px;flex-wrap:wrap">
        <?= stars_html(round($average * 2) / 2) ?>
        <span class="small muted"><?= e(__('reviews.stars', ['rating' => number_format($average, 1)])) ?> · <?= e(is_bn() ? bn_digits((string) $total) : (string) $total) ?></span>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="container section-tight">
    <div class="checkout-layout">
      <section>
        <?php if ($reviews === []): ?>
        <div class="card card-pad">
          <div class="empty-state">
            <span class="icon-tile"><?= icon('star') ?></span>
            <h2><?= e(__('home.reviews_empty')) ?></h2>
            <p><?= e(__('reviews.empty')) ?></p>
          </div>
        </div>
        <?php else: ?>
        <div class="review-grid">
          <?php foreach ($reviews as $review): ?>
          <article class="review-card">
            <div style="display:flex;justify-content:space-between;gap:8px;align-items:center;flex-wrap:wrap">
              <?= stars_html((float) $review['rating']) ?>
              <div style="display:flex;gap:6px">
                <?php if ((int) $review['is_demo'] === 1): ?>
                <span class="badge badge-demo"><?= e(__('reviews.demo_badge')) ?></span>
                <?php endif; ?>
                <span class="badge badge-success"><?= e(__('admin.approved')) ?></span>
              </div>
            </div>
            <p><?= e((string) $review['review_text']) ?></p>
            <footer>
              <span class="avatar"><?= e(mb_strtoupper(mb_substr((string) $review['display_name'], 0, 1))) ?></span>
              <span><?= e((string) $review['display_name']) ?> · <?= e(format_date($review['created_at'])) ?></span>
            </footer>
          </article>
          <?php endforeach; ?>
        </div>

        <?php if ($pageData['pages'] > 1): ?>
        <nav class="pagination" aria-label="<?= e(__('reviews.title')) ?>">
          <?php for ($i = 1; $i <= $pageData['pages']; $i++): ?>
            <?php if ($i === $pageData['page']): ?>
            <span class="is-current" aria-current="page"><?= e(is_bn() ? bn_digits((string) $i) : (string) $i) ?></span>
            <?php else: ?>
            <a href="<?= e(url('reviews.php') . query_string_with(['page' => $i])) ?>"><?= e(is_bn() ? bn_digits((string) $i) : (string) $i) ?></a>
            <?php endif; ?>
          <?php endfor; ?>
        </nav>
        <?php endif; ?>
        <?php endif; ?>
      </section>

      <aside class="checkout-aside">
        <div class="card card-pad">
          <h2 style="font-size:var(--fs-lg);margin-bottom:6px"><?= e(__('reviews.form_title')) ?></h2>
          <p class="small muted" style="margin-bottom:16px"><?= e(__('reviews.pending_note')) ?></p>

          <form method="post" action="<?= e(url('reviews.php')) ?>" class="form-grid" novalidate>
            <?= csrf_field() ?>
            <div class="field">
              <label for="display_name"><?= e(__('reviews.name')) ?></label>
              <input class="input" id="display_name" name="display_name" value="<?= e($form['name']) ?>" maxlength="60" required autocomplete="name"
                     <?= isset($errors['name']) ? 'aria-invalid="true"' : '' ?>>
              <?php if (isset($errors['name'])): ?><span class="field-error"><?= e($errors['name']) ?></span><?php endif; ?>
            </div>

            <div class="field">
              <span class="label-text"><?= e(__('reviews.rating')) ?></span>
              <div class="rating-input" role="radiogroup" aria-label="<?= e(__('reviews.rating')) ?>">
                <?php for ($star = 5; $star >= 1; $star--): ?>
                <input type="radio" id="rating-<?= $star ?>" name="rating" value="<?= $star ?>" <?= (string) $star === $form['rating'] ? 'checked' : '' ?>>
                <label for="rating-<?= $star ?>" title="<?= e(__('reviews.stars', ['rating' => $star])) ?>">★</label>
                <?php endfor; ?>
              </div>
              <?php if (isset($errors['rating'])): ?><span class="field-error"><?= e($errors['rating']) ?></span><?php endif; ?>
            </div>

            <div class="field">
              <label for="review_text"><?= e(__('reviews.text')) ?></label>
              <textarea class="textarea" id="review_text" name="review_text" maxlength="800" required
                        aria-describedby="review-hint" <?= isset($errors['text']) ? 'aria-invalid="true"' : '' ?>><?= e($form['text']) ?></textarea>
              <span class="hint" id="review-hint"><?= e(__('reviews.text_hint')) ?></span>
              <?php if (isset($errors['text'])): ?><span class="field-error"><?= e($errors['text']) ?></span><?php endif; ?>
            </div>

            <div class="field" aria-hidden="true" style="position:absolute;left:-9999px;top:-9999px">
              <label for="website">Website</label>
              <input class="input" id="website" name="website" tabindex="-1" autocomplete="off">
            </div>

            <button class="btn btn-block" type="submit"><?= icon('star') ?><span><?= e(__('reviews.submit')) ?></span></button>
          </form>
        </div>
      </aside>
    </div>
  </div>
</main>
<?php require DH_ROOT . '/includes/partials/site-footer.php'; ?>
