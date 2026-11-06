<?php
/** Path: pages/orders.php — My Orders / Order History (เฉพาะของตัวเอง) */
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

$user = Auth::requireCustomer();
header('Cache-Control: no-store');

$status = is_string($_GET['status'] ?? null) && isset(CustomerOrders::GROUPS[$_GET['status']]) ? $_GET['status'] : 'all';
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100000]]) ?: 1;
$result = CustomerOrders::paginate((int) $user['id'], $status, $page, 10);

$pageTitle = 'คำสั่งซื้อของฉัน';
$activeNav = 'orders';
$accountTab = 'orders';
$noindex = true;
require LNW_ROOT . '/includes/layout/header.php';
?>
<main id="main" class="section">
  <div class="container">
    <?php require LNW_ROOT . '/includes/layout/flash.php'; ?>
    <div class="page-head"><h1>คำสั่งซื้อของฉัน</h1><p class="muted">ติดตามสถานะคำสั่งซื้อและการชำระเงิน</p></div>
    <?php require LNW_ROOT . '/includes/layout/account-nav.php'; ?>

    <div class="chip-row" aria-label="กรองตามสถานะ">
      <?php foreach (CustomerOrders::GROUPS as $key => $g): ?>
        <a class="chip-link<?= $status === $key ? ' is-active' : '' ?>" href="<?= e(Format::query('orders', ['status' => $key !== 'all' ? $key : null])) ?>"><?= e($g['label']) ?></a>
      <?php endforeach; ?>
    </div>

    <?php if ($result['items']): ?>
      <section class="card">
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>เลขคำสั่งซื้อ</th><th>วันที่</th><th>สินค้า</th><th>ยอดรวม</th><th>สถานะ</th><th>การชำระเงิน</th></tr></thead>
            <tbody>
            <?php foreach ($result['items'] as $o): ?>
              <tr>
                <td class="nowrap"><a class="mono" href="<?= e(url('order/' . $o['order_number'])) ?>"><?= e($o['order_number']) ?></a></td>
                <td class="nowrap"><?= e(format_datetime($o['created_at'])) ?></td>
                <td><?= e($o['first_item'] ?? '—') ?><?php if ((int) $o['item_count'] > 1): ?> <span class="muted small">+<?= (int) $o['item_count'] - 1 ?> รายการ</span><?php endif; ?></td>
                <td class="nowrap"><?= e(Format::money($o['grand_total'])) ?></td>
                <td><?= Format::badge('order', $o['status']) ?></td>
                <td><?= Format::badge('payment', $o['payment_status']) ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
      <?php $pager = $result; $pagerPath = 'orders'; $pagerParams = ['status' => $status !== 'all' ? $status : null]; require LNW_ROOT . '/includes/layout/pagination.php'; ?>
    <?php else: ?>
      <div class="card empty-state">
        <?= icon('inbox') ?>
        <h2 class="h3"><?= $status === 'all' ? 'ยังไม่มีคำสั่งซื้อ' : 'ไม่มีคำสั่งซื้อในสถานะนี้' ?></h2>
        <p class="muted">เลือกบัญชีเกมที่ต้องการได้จากหน้าร้าน</p>
        <a class="btn btn-primary" href="<?= e(url('shop')) ?>">ไปที่ร้านค้า</a>
      </div>
    <?php endif; ?>
  </div>
</main>
<?php require LNW_ROOT . '/includes/layout/footer.php'; ?>