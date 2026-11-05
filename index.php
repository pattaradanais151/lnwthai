<?php
/**
 * Lnwthai — Home (Phase 1 foundation landing; หน้าร้านเต็มรูปแบบใน Phase 2)
 * Path: index.php
 */
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$pageTitle = '';
$activeNav = 'home';
$user = Auth::user();
require LNW_ROOT . '/includes/layout/header.php';
?>
<main id="main">
  <div class="container"><?php require LNW_ROOT . '/includes/layout/flash.php'; ?></div>
  <section class="hero">
    <div class="container hero-inner">
      <span class="eyebrow"><?= icon('gamepad-2') ?> Premium Game Account Store</span>
      <h1 class="hero-title">บัญชีเกมคุณภาพ<br><span class="text-gradient">ซื้อง่าย ปลอดภัย ตรวจสอบได้</span></h1>
      <p class="hero-sub">Lnwthai กำลังเตรียมเปิดร้าน สมัครสมาชิกไว้ล่วงหน้าเพื่อพร้อมสั่งซื้อทันทีเมื่อเปิดให้บริการ</p>
      <div class="hero-actions">
        <?php if ($user): ?>
          <a class="btn btn-primary" href="<?= e(url('account')) ?>">ไปที่บัญชีของฉัน <?= icon('arrow-right') ?></a>
        <?php else: ?>
          <a class="btn btn-primary" href="<?= e(url('register')) ?>">สมัครสมาชิก <?= icon('arrow-right') ?></a>
          <a class="btn btn-secondary" href="<?= e(url('login')) ?>">เข้าสู่ระบบ</a>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <section class="section">
    <div class="container grid-3">
      <article class="card feature">
        <span class="feature-icon"><?= icon('shield-check') ?></span>
        <h2 class="h3">ตรวจสอบการชำระเงินโดยทีมงาน</h2>
        <p class="muted">ทุกยอดโอนถูกตรวจกับบัญชีธนาคารจริงก่อนส่งมอบสินค้า</p>
      </article>
      <article class="card feature">
        <span class="feature-icon"><?= icon('lock') ?></span>
        <h2 class="h3">ข้อมูลบัญชีถูกเข้ารหัส</h2>
        <p class="muted">ข้อมูลเข้าสู่ระบบของบัญชีเกมจะแสดงเฉพาะผู้ซื้อหลังยืนยันการชำระเงินแล้ว</p>
      </article>
      <article class="card feature">
        <span class="feature-icon"><?= icon('users') ?></span>
        <h2 class="h3">ทีมงานตรวจสอบย้อนหลังได้</h2>
        <p class="muted">ทุกการดำเนินการของผู้ดูแลถูกบันทึกและตรวจสอบได้</p>
      </article>
    </div>
  </section>
</main>
<?php require LNW_ROOT . '/includes/layout/footer.php'; ?>