<?php
/** Path: admin/login.php — ไม่มีระบบสมัคร Admin สาธารณะ */
declare(strict_types=1);
require __DIR__ . '/includes/guard.php';

if (Auth::check() && Rbac::isStaff((int) Auth::id())) {
    redirect(url('admin/'));
}
$loggedOut = isset($_GET['logged_out']);
?><!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<meta name="theme-color" content="#ffffff">
<title>เข้าสู่ระบบหลังบ้าน · Lnwthai Admin</title>
<link rel="icon" type="image/svg+xml" href="<?= e(asset('images/logo.svg')) ?>">
<script src="<?= e(asset('js/theme-init.js')) ?>"></script>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</head>
<body>
<main id="main" class="auth auth-full">
  <div class="auth-card">
    <div class="auth-head">
      <span class="auth-icon"><?= icon('shield-check') ?></span>
      <h1>Lnwthai Admin</h1>
      <p class="muted">สำหรับผู้ดูแลที่ได้รับอนุญาตเท่านั้น</p>
    </div>
    <?php if ($loggedOut): ?>
      <div class="alert alert-success"><?= icon('circle-check') ?><span>ออกจากระบบเรียบร้อยแล้ว</span></div>
    <?php endif; ?>
    <?php require LNW_ROOT . '/includes/layout/flash.php'; ?>
    <form method="post" action="<?= e(url('admin/actions/login.php')) ?>" class="form">
      <?= Csrf::field() ?>
      <div class="field">
        <label for="identifier">อีเมลหรือชื่อผู้ใช้</label>
        <input id="identifier" name="identifier" type="text" autocomplete="username" required maxlength="190" value="<?= e(old('identifier')) ?>">
      </div>
      <div class="field">
        <label for="password">รหัสผ่าน</label>
        <div class="input-group">
          <input id="password" name="password" type="password" autocomplete="current-password" required maxlength="200">
          <button type="button" class="input-addon" data-toggle-password="password" aria-label="แสดงหรือซ่อนรหัสผ่าน" aria-pressed="false">
            <?= icon('eye', 'icon icon-eye') ?><?= icon('eye-off', 'icon icon-eye-off') ?>
          </button>
        </div>
      </div>
      <button class="btn btn-primary btn-block" type="submit">เข้าสู่ระบบ</button>
    </form>
    <p class="auth-foot muted small">บัญชีผู้ดูแลสร้างได้โดย Owner หรือผู้ที่ได้รับสิทธิ์เท่านั้น</p>
    <div class="auth-tools">
      <button type="button" class="icon-btn" data-theme-toggle aria-label="สลับโหมดสว่าง/มืด" aria-pressed="false">
        <?= icon('moon', 'icon theme-icon-light') ?><?= icon('sun', 'icon theme-icon-dark') ?>
      </button>
    </div>
  </div>
</main>
</body>
</html>