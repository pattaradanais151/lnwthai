<?php
/**
 * Path: admin/inventory-reveal.php — เปิดดูข้อมูลลับของบัญชีเกม (inventory.reveal)
 * GET  = แสดงฟอร์มระบุเหตุผล (ไม่มีข้อมูลลับ)
 * POST = ถอดรหัส + บันทึก account_reveals / audit_logs + แสดงผลแบบ no-store
 */
declare(strict_types=1);
require __DIR__ . '/includes/guard.php';

$me = admin_require('inventory.reveal');
$id = admin_int($_GET['id'] ?? ($_POST['id'] ?? 0));
$account = DB::one(
    'SELECT ga.id, ga.status, ga.product_id, ga.public_note, ga.secret_purged_at, p.title, p.sku
     FROM game_accounts ga JOIN products p ON p.id = ga.product_id WHERE ga.id = ?',
    [$id]
) ?? render_error(404, 'ไม่พบบัญชีเกม');

$secret = null;
$error = null;
if (is_post()) {
    Csrf::verify();
    $reason = post_str('reason');
    if (mb_strlen($reason) < 5 || mb_strlen($reason) > 255) {
        $error = 'กรุณาระบุเหตุผล 5–255 ตัวอักษร';
    } else {
        try {
            $secret = Inventory::reveal($id, (int) $me['id'], $reason);
        } catch (DomainException $e) {
            $error = $e->getMessage();
        }
    }
}
header('Cache-Control: no-store, no-cache, must-revalidate, private, max-age=0');
header('Pragma: no-cache');

$history = DB::all(
    'SELECT ar.created_at, ar.reason, ar.ip, ar.context, u.display_name, u.email
     FROM account_reveals ar LEFT JOIN users u ON u.id = ar.user_id
     WHERE ar.game_account_id = ? ORDER BY ar.id DESC LIMIT 10',
    [$id]
);
$back = url('admin/inventory.php') . '?product=' . (int) $account['product_id'];
$fieldLabels = ['username' => 'ชื่อผู้ใช้ / ไอดี', 'password' => 'รหัสผ่าน', 'email' => 'อีเมลของบัญชีเกม', 'notes' => 'ข้อมูลเพิ่มเติม'];

$pageTitle = 'ดูข้อมูลลับ #' . (int) $account['id'];
$activeNav = 'inventory';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <nav class="breadcrumb" aria-label="breadcrumb"><ol>
    <li><a href="<?= e(url('admin/inventory.php')) ?>">คลังบัญชีเกม</a></li>
    <li><a href="<?= e($back) ?>" class="mono"><?= e($account['sku']) ?></a></li>
    <li aria-current="page">บัญชี #<?= (int) $account['id'] ?></li>
  </ol></nav>
  <h1>ข้อมูลลับของบัญชี #<?= (int) $account['id'] ?></h1>
  <p class="muted"><?= e($account['title']) ?> · <?= Format::badge('account', $account['status']) ?></p>
</div>

<?php if ($error): ?><div class="alert alert-error"><?= icon('alert-triangle') ?><span><?= e($error) ?></span></div><?php endif; ?>

<?php if ($secret !== null): ?>
<section class="card">
  <div class="alert alert-warning"><?= icon('eye') ?><span>การเปิดดูครั้งนี้ถูกบันทึกแล้ว · ห้ามคัดลอกไปเก็บนอกระบบ · ปิดหน้านี้ทันทีเมื่อใช้งานเสร็จ</span></div>
  <dl class="secret-grid">
    <?php foreach ($fieldLabels as $k => $label): if (!isset($secret[$k]) || $secret[$k] === '') { continue; } ?>
      <dt><?= e($label) ?></dt><dd><span class="secret-value"><?= e((string) $secret[$k]) ?></span></dd>
    <?php endforeach; ?>
  </dl>
  <div class="form-actions section-sm"><a class="btn btn-primary" href="<?= e($back) ?>">ซ่อนและกลับ</a></div>
</section>
<?php elseif ($account['secret_purged_at'] !== null): ?>
  <div class="card"><p class="muted">ข้อมูลลับของบัญชีนี้ถูกลบตามนโยบายการเก็บรักษาเมื่อ <?= e(format_datetime($account['secret_purged_at'])) ?></p></div>
<?php else: ?>
<section class="card">
  <h2 class="h3 card-title"><?= icon('lock') ?> ระบุเหตุผลก่อนเปิดดู</h2>
  <form method="post" action="<?= e(url('admin/inventory-reveal.php')) ?>" class="form" autocomplete="off">
    <?= Csrf::field() ?>
    <input type="hidden" name="id" value="<?= (int) $account['id'] ?>">
    <div class="field">
      <label for="reason">เหตุผล</label>
      <input id="reason" name="reason" required minlength="5" maxlength="255" placeholder="เช่น ตรวจสอบบัญชีก่อนเปิดขาย / ลูกค้าแจ้งเข้าไม่ได้ คำสั่งซื้อ LNW-...">
      <span class="hint">เหตุผล ผู้เปิดดู เวลา และ IP จะถูกบันทึกใน Audit Log</span>
    </div>
    <div class="form-actions">
      <button class="btn btn-primary" type="submit"><?= icon('eye') ?> ถอดรหัสและแสดง</button>
      <a class="btn btn-secondary" href="<?= e($back) ?>">ยกเลิก</a>
    </div>
  </form>
</section>
<?php endif; ?>

<section class="card section-sm">
  <h2 class="h3 card-title"><?= icon('clock') ?> ประวัติการเปิดดู (10 ครั้งล่าสุด)</h2>
  <?php if ($history): ?>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>เวลา</th><th>ผู้เปิดดู</th><th>ประเภท</th><th>เหตุผล</th><th>IP</th></tr></thead>
      <tbody>
      <?php foreach ($history as $h): ?>
        <tr>
          <td class="nowrap"><?= e(format_datetime($h['created_at'])) ?></td>
          <td><?= e($h['display_name'] ?? '—') ?><br><span class="muted small"><?= e($h['email'] ?? '') ?></span></td>
          <td><?= $h['context'] === 'admin' ? 'ทีมงาน' : 'ลูกค้า' ?></td>
          <td><?= e($h['reason'] ?? '—') ?></td>
          <td class="mono small"><?= e($h['ip'] ?? '') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
    <p class="muted">ยังไม่เคยถูกเปิดดู</p>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>