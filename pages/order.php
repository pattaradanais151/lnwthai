<?php
/** Path: pages/order.php — Order detail (กัน IDOR: order_number + user_id) */
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

$user = Auth::requireCustomer();
header('Cache-Control: no-store');

$no = is_string($_GET['no'] ?? null) ? $_GET['no'] : '';
if (preg_match('/^[A-Za-z0-9-]{1,30}$/', $no) !== 1) {
    render_error(404);
}
$order = CustomerOrders::find((int) $user['id'], $no) ?? render_error(404, 'ไม่พบคำสั่งซื้อนี้ในบัญชีของคุณ');
$items = CustomerOrders::items((int) $order['id']);
$history = CustomerOrders::history((int) $order['id']);
$payments = CustomerOrders::payments((int) $order['id']);

$next = match ($order['status']) {
    'pending_payment'       => ['warning', 'คำสั่งซื้อนี้รอการชำระเงิน' . ($order['expires_at'] ? ' ภายใน ' . format_datetime($order['expires_at']) : '')],
    'awaiting_verification' => ['info', 'ทีมงานกำลังตรวจสอบการชำระเงินของคุณ'],
    'paid', 'delivered', 'completed' => ['success', 'ชำระเงินเรียบร้อย ดูข้อมูลบัญชีเกมได้ที่หน้า "บัญชีเกมที่ได้รับ" เมื่อส่งมอบแล้ว'],
    'refunded'              => ['info', 'คำสั่งซื้อนี้ได้รับการคืนเงินแล้ว'],
    default                 => ['info', 'คำสั่งซื้อนี้ถูกปิดแล้ว'],
};
$nextIcon = ['warning' => 'clock', 'info' => 'info', 'success' => 'circle-check'][$next[0]];

$pageTitle = 'คำสั่งซื้อ ' . $order['order_number'];
$activeNav = 'orders';
$accountTab = 'orders';
$noindex = true;
require LNW_ROOT . '/includes/layout/header.php';
?>
<main id="main" class="section">
  <div class="container">
    <?php require LNW_ROOT . '/includes/layout/flash.php'; ?>
    <nav class="breadcrumb" aria-label="breadcrumb">
      <ol><li><a href="<?= e(url('orders')) ?>">คำสั่งซื้อของฉัน</a></li><li aria-current="page" class="mono"><?= e($order['order_number']) ?></li></ol>
    </nav>
    <div class="page-head">
      <h1>คำสั่งซื้อ <span class="mono"><?= e($order['order_number']) ?></span></h1>
      <p class="muted">สั่งซื้อเมื่อ <?= e(format_datetime($order['created_at'])) ?> · <?= Format::badge('order', $order['status']) ?> <?= Format::badge('payment', $order['payment_status']) ?></p>
    </div>

    <div class="alert alert-<?= e($next[0]) ?>"><?= icon($nextIcon) ?><span><?= e($next[1]) ?></span></div>

    <div class="order-layout">
      <section class="card">
        <h2 class="h3 card-title"><?= icon('package') ?> รายการสินค้า</h2>
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>สินค้า</th><th>ราคา</th><th>การส่งมอบ</th></tr></thead>
            <tbody>
            <?php foreach ($items as $it): ?>
              <tr>
                <td>
                  <?php if ($it['product_slug']): ?><a href="<?= e(url('product/' . $it['product_slug'])) ?>"><?= e($it['product_title']) ?></a><?php else: ?><?= e($it['product_title']) ?><?php endif; ?>
                  <?php if ($it['game_name']): ?><br><span class="muted small"><?= e($it['game_name']) ?></span><?php endif; ?>
                </td>
                <td class="nowrap"><?= e(Format::money($it['line_total'])) ?></td>
                <td><?= Format::badge('delivery', $it['delivery_status']) ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="totals">
          <div><span class="muted">ยอดสินค้า</span><span><?= e(Format::money($order['subtotal'])) ?></span></div>
          <?php if ((float) $order['discount_total'] > 0): ?><div><span class="muted">ส่วนลด</span><span>-<?= e(Format::money($order['discount_total'])) ?></span></div><?php endif; ?>
          <div class="grand"><span>ยอดชำระทั้งหมด</span><span><?= e(Format::money($order['grand_total'])) ?></span></div>
        </div>
      </section>

      <div>
        <section class="card">
          <h2 class="h3 card-title"><?= icon('credit-card') ?> การชำระเงิน</h2>
          <?php if ($payments): ?>
            <ul class="timeline">
              <?php foreach ($payments as $pm): ?>
                <li>
                  <?= Format::badge('payment', $pm['status']) ?> <strong><?= e(Format::money($pm['amount'])) ?></strong>
                  <br><span class="muted small">ส่งเมื่อ <?= e(format_datetime($pm['created_at'])) ?><?= $pm['reviewed_at'] ? ' · ตรวจเมื่อ ' . e(format_datetime($pm['reviewed_at'])) : '' ?></span>
                  <?php if ($pm['status'] === 'rejected' && $pm['reject_reason']): ?><br><span class="text-danger small">เหตุผล: <?= e($pm['reject_reason']) ?></span><?php endif; ?>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <p class="muted">ยังไม่มีการส่งหลักฐานการชำระเงิน</p>
          <?php endif; ?>
        </section>

        <section class="card section-sm">
          <h2 class="h3 card-title"><?= icon('clock') ?> ประวัติสถานะ</h2>
          <?php if ($history): ?>
            <ul class="timeline">
              <?php foreach ($history as $h): ?>
                <li><?= e(Format::label('order', $h['to_status'])) ?><br><span class="muted small"><?= e(format_datetime($h['created_at'])) ?></span></li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <p class="muted">ยังไม่มีประวัติการเปลี่ยนสถานะ</p>
          <?php endif; ?>
        </section>
      </div>
    </div>
  </div>
</main>
<?php require LNW_ROOT . '/includes/layout/footer.php'; ?>