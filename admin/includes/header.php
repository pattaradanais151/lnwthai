<?php
/** Path: admin/includes/header.php — include หลัง admin_require() เท่านั้น */
defined('LNW_ROOT') || exit;
$__me = Auth::user();
$__uid = (int) $__me['id'];
$__roleNames = implode(', ', array_column(Rbac::roles($__uid), 'name'));
$__active = $activeNav ?? '';
$__menu = array_filter([
    ['dashboard', 'admin/',                 'layout-dashboard', 'แดชบอร์ด',       []],
    ['products',  'admin/products.php',     'package',          'สินค้า',          ['product.view']],
    ['inventory', 'admin/inventory.php',    'key-round',        'คลังบัญชีเกม',    ['inventory.view']],
    ['catalog',   'admin/catalog.php',      'gamepad-2',        'เกมและหมวดหมู่', ['game.manage', 'category.manage']],
    ['vault',     'admin/vault.php',        'lock',             'การเข้ารหัส',     ['system.check']],
    ['system',    'admin/system-check.php', 'activity',         'ตรวจสอบระบบ',     ['system.check']],
], static function (array $item): bool {
    if ($item[4] === []) {
        return true;
    }
    foreach ($item[4] as $perm) {
        if (admin_can($perm)) {
            return true;
        }
    }
    return false;
});
?><!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<meta name="theme-color" content="#ffffff">
<title><?= e(($pageTitle ?? 'แดชบอร์ด') . ' · Lnwthai Admin') ?></title>
<link rel="icon" type="image/svg+xml" href="<?= e(asset('images/logo.svg')) ?>">
<script src="<?= e(asset('js/theme-init.js')) ?>"></script>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/store.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
<script src="<?= e(asset('js/admin.js')) ?>" defer></script>
</head>
<body class="is-admin">
<a class="skip-link" href="#main">ข้ามไปยังเนื้อหา</a>
<header class="nav">
  <div class="container nav-inner">
    <a class="brand" href="<?= e(url('admin/')) ?>" aria-label="Lnwthai Admin">
      <img class="brand-mark" src="<?= e(asset('images/logo.svg')) ?>" alt="" width="28" height="28">
      <span>Lnwthai</span><span class="badge badge-neutral">Admin</span>
    </a>
    <button type="button" class="icon-btn nav-toggle" data-nav-toggle aria-controls="admin-menu" aria-expanded="false" aria-label="เปิดเมนู"><?= icon('menu') ?></button>
    <nav class="nav-links" id="admin-menu" aria-label="บัญชีผู้ดูแล">
      <a class="nav-link" href="<?= e(url('')) ?>" target="_blank" rel="noopener"><?= icon('shopping-bag') ?><span>ดูหน้าร้าน</span></a>
      <span class="user-chip" title="<?= e($__me['email']) ?>">
        <?= icon('user') ?><span><?= e($__me['display_name']) ?></span><span class="muted small">· <?= e($__roleNames) ?></span>
      </span>
      <form method="post" action="<?= e(url('admin/actions/logout.php')) ?>" class="nav-form">
        <?= Csrf::field() ?>
        <button type="submit" class="nav-link nav-button"><?= icon('log-out') ?><span>ออกจากระบบ</span></button>
      </form>
      <button type="button" class="icon-btn" data-theme-toggle aria-label="สลับโหมดสว่าง/มืด" aria-pressed="false">
        <?= icon('moon', 'icon theme-icon-light') ?><?= icon('sun', 'icon theme-icon-dark') ?>
      </button>
    </nav>
  </div>
</header>
<nav class="admin-subnav" aria-label="เมนูผู้ดูแล">
  <div class="container admin-subnav-inner">
    <?php foreach ($__menu as [$__key, $__path, $__icon, $__label]): ?>
      <a class="subnav-link<?= $__active === $__key ? ' is-active' : '' ?>" href="<?= e(url($__path)) ?>"<?= $__active === $__key ? ' aria-current="page"' : '' ?>><?= icon($__icon) ?><span><?= e($__label) ?></span></a>
    <?php endforeach; ?>
  </div>
</nav>
<main id="main" class="section admin-main">
  <div class="container">
    <?php require LNW_ROOT . '/includes/layout/flash.php'; ?>