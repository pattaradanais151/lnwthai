<?php
/** Path: includes/layout/header.php */
defined('LNW_ROOT') || exit;
$__store = Settings::get('store_name', 'Lnwthai');
$__tagline = Settings::get('store_tagline', 'Premium Digital Game Account Store');
$__title = isset($pageTitle) && $pageTitle !== '' ? $pageTitle . ' · ' . $__store : $__store . ' — ' . $__tagline;
$__desc = isset($pageDescription) && $pageDescription !== ''
    ? $pageDescription
    : Settings::get('store_description', 'ร้านจำหน่ายบัญชีเกมออนไลน์ ตรวจสอบการชำระเงินโดยทีมงาน ข้อมูลบัญชีเข้ารหัส');
$__user = Auth::user();
$__active = $activeNav ?? '';
$__announce = Settings::bool('announcement_enabled') ? Settings::get('announcement_text') : '';
$__navQ = is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']), 0, 100) : '';
?><!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($__title) ?></title>
<meta name="description" content="<?= e(mb_strimwidth($__desc, 0, 300, '…')) ?>">
<meta name="robots" content="<?= !empty($noindex) ? 'noindex,nofollow' : 'index,follow' ?>">
<meta name="theme-color" content="#ffffff">
<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= e($__store) ?>">
<meta property="og:title" content="<?= e($__title) ?>">
<meta property="og:description" content="<?= e(mb_strimwidth($__desc, 0, 300, '…')) ?>">
<meta property="og:locale" content="th_TH">
<meta property="og:locale:alternate" content="en_US">
<link rel="icon" type="image/svg+xml" href="<?= e(asset('images/logo.svg')) ?>">
<script src="<?= e(asset('js/theme-init.js')) ?>"></script>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/store.css')) ?>">
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
<script src="<?= e(asset('js/store.js')) ?>" defer></script>
</head>
<body>
<a class="skip-link" href="#main">ข้ามไปยังเนื้อหา</a>
<?php if ($__announce !== ''): ?>
<div class="announce" role="note">
  <div class="container announce-inner"><?= icon('megaphone') ?><span><?= e($__announce) ?></span></div>
</div>
<?php endif; ?>
<header class="nav">
  <div class="container nav-inner">
    <a class="brand" href="<?= e(url('')) ?>" aria-label="<?= e($__store) ?> หน้าแรก">
      <img class="brand-mark" src="<?= e(asset('images/logo.svg')) ?>" alt="" width="28" height="28">
      <span><?= e($__store) ?></span>
    </a>
    <button type="button" class="icon-btn nav-toggle" data-nav-toggle aria-controls="site-menu" aria-expanded="false" aria-label="เปิดเมนู"><?= icon('menu') ?></button>
    <nav class="nav-links" id="site-menu" aria-label="เมนูหลัก">
      <form class="nav-search" action="<?= e(url('shop')) ?>" method="get" role="search">
        <?= icon('search') ?>
        <input type="search" name="q" value="<?= e($__navQ) ?>" placeholder="ค้นหาเกมหรือสินค้า" aria-label="ค้นหาสินค้า" maxlength="100">
      </form>
      <a class="nav-link <?= $__active === 'home' ? 'is-active' : '' ?>" href="<?= e(url('')) ?>">หน้าแรก</a>
      <a class="nav-link <?= $__active === 'shop' ? 'is-active' : '' ?>" href="<?= e(url('shop')) ?>">สินค้าทั้งหมด</a>
      <?php if ($__user): ?>
        <a class="nav-link <?= $__active === 'orders' ? 'is-active' : '' ?>" href="<?= e(url('orders')) ?>"><?= icon('receipt') ?><span>คำสั่งซื้อ</span></a>
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