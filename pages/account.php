<?php
/** Path: pages/account.php — ข้อมูลบัญชีลูกค้า (แก้ไขโปรไฟล์/คำสั่งซื้อ: Phase 2) */
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

$user = Auth::requireCustomer();
header('Cache-Control: no-store');

$activeSessions = (int) DB::value(
    'SELECT COUNT(*) FROM user_sessions
     WHERE user_id = ? AND context = ? AND revoked_at IS NULL AND expires_at > ?',
    [(int) $user['id'], 'customer', now()]
);

$pageTitle = 'บัญชีของฉัน';
$activeNav = 'account';
$noindex = true;
require LNW_ROOT . '/includes/layout/header.php';
?>
<main id="main" class="section">
  <div class="container narrow">
    <?php require LNW_ROOT . '/includes/layout/flash.php'; ?>
    <div class="page-head">
      <h1>สวัสดี, <?= e($user['display_name']) ?></h1>
      <p class="muted">จัดการข้อมูลบัญชี Lnwthai ของคุณ</p>
    </div>
    <div class="grid-2">
      <section class="card">
        <h2 class="h3 card-title"><?= icon('user') ?> ข้อมูลบัญชี</h2>
        <dl class="dl">
          <dt>ชื่อที่แสดง</dt><dd><?= e($user['display_name']) ?></dd>
          <dt>อีเมล</dt><dd><?= e($user['email']) ?></dd>
          <dt>เบอร์โทรศัพท์</dt><dd><?= e($user['phone'] ?: '—') ?></dd>
          <dt>สมาชิกตั้งแต่</dt><dd><?= e(format_datetime($user['created_at'])) ?></dd>
        </dl>
      </section>
      <section class="card">
        <h2 class="h3 card-title"><?= icon('shield-check') ?> ความปลอดภัย</h2>
        <dl class="dl">
          <dt>เข้าสู่ระบบล่าสุด</dt><dd><?= e(format_datetime($user['last_login_at'])) ?></dd>
          <dt>IP ล่าสุด</dt><dd class="mono"><?= e($user['last_login_ip'] ?: '—') ?></dd>
          <dt>เซสชันที่ใช้งานอยู่</dt><dd><?= $activeSessions ?> อุปกรณ์</dd>
          <dt>เปลี่ยนรหัสผ่านล่าสุด</dt><dd><?= e(format_datetime($user['password_changed_at'])) ?></dd>
        </dl>
      </section>
    </div>
  </div>
</main>
<?php require LNW_ROOT . '/includes/layout/footer.php'; ?>