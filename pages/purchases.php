<?php
/** Path: pages/purchases.php — Purchased Accounts (เฉพาะที่ชำระเงินยืนยันแล้วและส่งมอบแล้ว) */
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

$user = Auth::requireCustomer();
header('Cache-Control: no-store');
$items = CustomerOrders::purchases((int) $user['id']);

$pageTitle = 'บัญชีเกมที่ได้รับ';
$activeNav = 'account';
$accountTab = 'purchases';
$noindex = true;
require LNW_ROOT . '/includes/layout/header.php';
?>
<main id="main" class="section">
  <div class="container">
    <?php require LNW_ROOT . '/includes/layout/flash.php'; ?>
    <div class="page-head">
      <h1>บัญชีเกมที่ได้รับ</h1>
      <p class="muted">แสดงเฉพาะรายการที่ทีมงานยืนยันการชำระเงินและส่งมอบแล้ว</p>
    </div>
    <?php require LNW_ROOT . '/includes/layout/account-nav.php'; ?>

    <?php if ($items): ?>
      <div class="purchase-list">
        <?php foreach ($items as $it): ?>
          <article class="card purchase">
            <div>
              <span class="product-game"><?= e($it['game_name'] ?? '') ?></span>
              <h2 class="h3"><?= e($it['product_title']) ?></h2>
              <p class="muted small">
                คำสั่งซื้อ <a class="mono" href="<?= e(url('order/' . $it['order_number'])) ?>"><?= e($it['order_number']) ?></a>
                · ส่งมอบเมื่อ <?= e(format_datetime($it['delivered_at'])) ?>
              </p>
            </div>
            <span class="badge badge-ok">ส่งมอบแล้ว</span>
          </article>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="card empty-state">
        <?= icon('key-round') ?>
        <h2 class="h3">ยังไม่มีบัญชีเกมที่ได้รับ</h2>
        <p class="muted">เมื่อทีมงานยืนยันการชำระเงินและส่งมอบแล้ว รายการจะแสดงที่นี่</p>
        <a class="btn btn-primary" href="<?= e(url('shop')) ?>">ไปที่ร้านค้า</a>
      </div>
    <?php endif; ?>
  </div>
</main>
<?php require LNW_ROOT . '/includes/layout/footer.php'; ?>