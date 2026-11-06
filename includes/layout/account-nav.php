<?php
/** Path: includes/layout/account-nav.php — ต้องมี $accountTab */
defined('LNW_ROOT') || exit;
$__tabs = [
    'account'   => ['path' => 'account',   'label' => 'ภาพรวมบัญชี',        'icon' => 'user'],
    'orders'    => ['path' => 'orders',    'label' => 'คำสั่งซื้อของฉัน',  'icon' => 'receipt'],
    'purchases' => ['path' => 'purchases', 'label' => 'บัญชีเกมที่ได้รับ', 'icon' => 'key-round'],
];
?>
<nav class="tabs" aria-label="เมนูบัญชี">
  <?php foreach ($__tabs as $__key => $__t): ?>
    <a class="tab<?= ($accountTab ?? '') === $__key ? ' is-active' : '' ?>" href="<?= e(url($__t['path'])) ?>"<?= ($accountTab ?? '') === $__key ? ' aria-current="page"' : '' ?>>
      <?= icon($__t['icon']) ?><span><?= e($__t['label']) ?></span>
    </a>
  <?php endforeach; ?>
</nav>