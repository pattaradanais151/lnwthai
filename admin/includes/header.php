<?php
/** Path: admin/includes/header.php — include หลัง admin_require() เท่านั้น */
defined('LNW_ROOT') || exit;
$__me = Auth::user();
$__uid = (int) $__me['id'];
$__roleNames = implode(', ', array_column(Rbac::roles($__uid), 'name'));
$__active = $activeNav ?? '';
$__menu = array_filter([
    ['key' => 'dashboard', 'href' => url('admin/'), 'icon' => 'layout-dashboard', 'label' => 'แดชบอร์ด', 'perm' => null],
    ['key' => 'system', 'href' => url('admin/system-check.php'), 'icon' => 'activity', 'label' => 'ตรวจสอบระบบ', 'perm' => 'system.check'],
], static fn(array $i): bool => $i['perm'] === null || admin_can($i['perm']));
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
    <nav class="nav-links" id="admin-menu" aria-label="เมนูผู้ดูแล">
      <?php foreach ($__menu as $__item): ?>
        <a class="nav-link <?= $__active === $__item['key'] ? 'is-active' : '' ?>" href="<?= e($__item['href']) ?>"><?= icon($__item['icon']) ?><span><?= e($__item['label']) ?></span></a>
      <?php endforeach; ?>
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
<main id="main" class="section admin-main">
  <div class="container">
    <?php require LNW_ROOT . '/includes/layout/flash.php'; ?>