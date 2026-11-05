<?php
/** Path: includes/layout/header.php */
defined('LNW_ROOT') || exit;
$__title = isset($pageTitle) && $pageTitle !== '' ? $pageTitle . ' · Lnwthai' : 'Lnwthai — ร้านบัญชีเกมออนไลน์พรีเมียม';
$__desc = $pageDescription ?? 'Lnwthai ร้านจำหน่ายบัญชีเกมออนไลน์ ตรวจสอบการชำระเงินโดยทีมงาน ข้อมูลบัญชีเข้ารหัส ปลอดภัย';
$__user = Auth::user();
$__active = $activeNav ?? '';
?><!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($__title) ?></title>
<meta name="description" content="<?= e($__desc) ?>">
<meta name="robots" content="<?= !empty($noindex) ? 'noindex,nofollow' : 'index,follow' ?>">
<meta name="theme-color" content="#ffffff">
<link rel="icon" type="image/svg+xml" href="<?= e(asset('images/logo.svg')) ?>">
<script src="<?= e(asset('js/theme-init.js')) ?>"></script>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</head>
<body>
<a class="skip-link" href="#main">ข้ามไปยังเนื้อหา</a>
<header class="nav">
  <div class="container nav-inner">
    <a class="brand" href="<?= e(url('')) ?>" aria-label="Lnwthai หน้าแรก">
      <img class="brand-mark" src="<?= e(asset('images/logo.svg')) ?>" alt="" width="28" height="28">
      <span>Lnwthai</span>
    </a>
    <button type="button" class="icon-btn nav-toggle" data-nav-toggle aria-controls="site-menu" aria-expanded="false" aria-label="เปิดเมนู"><?= icon('menu') ?></button>
    <nav class="nav-links" id="site-menu" aria-label="เมนูหลัก">
      <a class="nav-link <?= $__active === 'home' ? 'is-active' : '' ?>" href="<?= e(url('')) ?>">หน้าแรก</a>
      <?php if ($__user): ?>
        <a class="nav-link <?= $__active === 'account' ? 'is-active' : '' ?>" href="<?= e(url('account')) ?>"><?= icon('user') ?><span>บัญชีของฉัน</span></a>
        <form method="post" action="<?= e(url('actions/logout.php')) ?>" class="nav-form">
          <?= Csrf::field() ?>
          <button type="submit" class="nav-link nav-button"><?= icon('log-out') ?><span>ออกจากระบบ</span></button>
        </form>
      <?php else: ?>
        <a class="nav-link <?= $__active === 'login' ? 'is-active' : '' ?>" href="<?= e(url('login')) ?>">เข้าสู่ระบบ</a>
        <a class="btn btn-primary btn-sm" href="<?= e(url('register')) ?>">สมัครสมาชิก</a>
      <?php endif; ?>
      <button type="button" class="icon-btn" data-theme-toggle aria-label="สลับโหมดสว่าง/มืด" aria-pressed="false">
        <?= icon('moon', 'icon theme-icon-light') ?><?= icon('sun', 'icon theme-icon-dark') ?>
      </button>
    </nav>
  </div>
</header>