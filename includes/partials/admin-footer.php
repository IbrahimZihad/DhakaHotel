<?php
/**
 * Admin shell closing tags and scripts. Uses $adminLayout / $extraJs.
 */
$adminLayout = $adminLayout ?? 'sidebar';
$extraJs = $extraJs ?? [];
if ($adminLayout !== 'pos') {
    echo '</main>';
}
?>
</div><!-- /.admin-shell -->
<div class="toast-region" id="toast-region" aria-live="polite" aria-atomic="false"></div>
<script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>
<script src="<?= e(asset('assets/js/admin.js')) ?>" defer></script>
<?php foreach ($extraJs as $js): ?>
<script src="<?= e(asset($js)) ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
