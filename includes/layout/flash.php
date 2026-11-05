<?php
/** Path: includes/layout/flash.php */
defined('LNW_ROOT') || exit;
$__flashes = take_flashes();
$__icons = ['success' => 'circle-check', 'error' => 'alert-triangle', 'warning' => 'alert-triangle', 'info' => 'info'];
if ($__flashes): ?>
<div class="flash-stack" role="status" aria-live="polite">
  <?php foreach ($__flashes as $__f):
      $__t = isset($__icons[$__f['type'] ?? '']) ? $__f['type'] : 'info'; ?>
    <div class="alert alert-<?= e($__t) ?>">
      <?= icon($__icons[$__t]) ?>
      <span><?= e($__f['message'] ?? '') ?></span>
      <button type="button" class="alert-close" data-dismiss aria-label="ปิด"><?= icon('x') ?></button>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>