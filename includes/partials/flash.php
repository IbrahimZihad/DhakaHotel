<?php
/**
 * Flash messages. Rendered once near the top of <main>.
 */
$flashMap = [
    'success' => ['icon' => 'check-circle', 'class' => 'alert-success'],
    'error' => ['icon' => 'alert', 'class' => 'alert-danger'],
    'warning' => ['icon' => 'alert', 'class' => 'alert-warning'],
    'info' => ['icon' => 'info', 'class' => 'alert-info'],
];
$messages = flash_messages();
?>
<?php if ($messages !== []): ?>
<div class="container" style="padding-top:16px">
  <div class="stack" role="status" aria-live="polite">
    <?php foreach ($messages as $message):
        $type = (string) ($message['type'] ?? 'info');
        $meta = $flashMap[$type] ?? $flashMap['info'];
    ?>
    <div class="alert <?= e($meta['class']) ?>">
      <?= icon($meta['icon']) ?>
      <div><?= e((string) ($message['message'] ?? '')) ?></div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>
