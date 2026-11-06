<?php
/** Path: pages/account.php — ภาพรวมบัญชี + แก้ไขโปรไฟล์ + เปลี่ยนรหัสผ่าน */
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

$user = Auth::requireCustomer();
header('Cache-Control: no-store');
$uid = (int) $user['id'];
$summary = CustomerOrders::summary($uid);
$activeSessions = (int) DB::value(
    'SELECT COUNT(*) FROM user_sessions WHERE user_id = ? AND context = ? AND revoked_at IS NULL AND expires_at > ?',
    [$uid, 'customer', now()]
);
$oldName = old('display_name');
$oldPhone = old('phone');

$pageTitle = 'บัญชีของฉัน';
$activeNav = 'account';
$accountTab = 'account';
$noindex = true;
require LNW_ROOT . '/includes/layout/header.php';
?>
<main id="main" class="section">
  <div class="container">
    <?php require LNW_ROOT . '/includes/layout/flash.php'; ?>
    <div class="page-head">
      <h1>สวัสดี, <?= e($user['display_name']) ?></h1>
      <p class="muted">จัดการข้อมูลบัญชีและติดตามคำสั่งซื้อของคุณ</p>
    </div>
    <?php require LNW_ROOT . '/includes/layout/account-nav.php'; ?>

    <section class="grid-4" aria-label="สรุปบัญชี">
      <a class="card stat stat-link" href="<?= e(url('orders')) ?>"><span class="stat-label"><?= icon('receipt') ?> คำสั่งซื้อทั้งหมด</span><span class="stat-value"><?= number_format($summary['total']) ?></span></a>
      <a class="card stat stat-link" href="<?= e(Format::query('orders', ['status' => 'pending'])) ?>"><span class="stat-label"><?= icon('clock') ?> รอชำระเงิน</span><span class="stat-value"><?= number_format($summary['pending']) ?></span></a>
      <a class="card stat stat-link" href="<?= e(Format::query('orders', ['status' => 'review'])) ?>"><span class="stat-label"><?= icon('shield-check') ?> รอตรวจสอบ</span><span class="stat-value"><?= number_format($summary['review']) ?></span></a>
      <a class="card stat stat-link" href="<?= e(url('purchases')) ?>"><span class="stat-label"><?= icon('key-round') ?> บัญชีเกมที่ได้รับ</span><span class="stat-value"><?= number_format($summary['purchases']) ?></span></a>
    </section>

    <div class="grid-2 section-sm">
      <section class="card" id="profile">
        <h2 class="h3 card-title"><?= icon('user') ?> ข้อมูลโปรไฟล์</h2>
        <form method="post" action="<?= e(url('actions/profile.php')) ?>" class="form">
          <?= Csrf::field() ?>
          <div class="field">
            <label for="email">อีเมล</label>
            <input id="email" type="email" value="<?= e($user['email']) ?>" disabled>
            <span class="hint">หากต้องการเปลี่ยนอีเมล กรุณาติดต่อร้าน</span>
          </div>
          <div class="field">
            <label for="display_name">ชื่อที่แสดง</label>
            <input id="display_name" name="display_name" type="text" required minlength="2" maxlength="100" autocomplete="nickname" value="<?= e($oldName !== '' ? $oldName : $user['display_name']) ?>">
          </div>
          <div class="field">
            <label for="phone">เบอร์โทรศัพท์ <span class="muted">(ไม่บังคับ)</span></label>
            <input id="phone" name="phone" type="tel" maxlength="20" autocomplete="tel" value="<?= e($oldPhone !== '' ? $oldPhone : ($user['phone'] ?? '')) ?>">
          </div>
          <button class="btn btn-primary" type="submit">บันทึกโปรไฟล์</button>
        </form>
      </section>

      <section class="card" id="security">
        <h2 class="h3 card-title"><?= icon('lock') ?> เปลี่ยนรหัสผ่าน</h2>
        <form method="post" action="<?= e(url('actions/password.php')) ?>" class="form" autocomplete="off">
          <?= Csrf::field() ?>
          <input type="text" name="username" value="<?= e($user['email']) ?>" autocomplete="username" class="hp" tabindex="-1" aria-hidden="true">
          <div class="field">
            <label for="current_password">รหัสผ่านปัจจุบัน</label>
            <input id="current_password" name="current_password" type="password" required maxlength="200" autocomplete="current-password">
          </div>
          <div class="field">
            <label for="new_password">รหัสผ่านใหม่</label>
            <div class="input-group">
              <input id="new_password" name="new_password" type="password" required minlength="10" maxlength="72" autocomplete="new-password">
              <button type="button" class="input-addon" data-toggle-password="new_password" aria-label="แสดงหรือซ่อนรหัสผ่าน" aria-pressed="false">
                <?= icon('eye', 'icon icon-eye') ?><?= icon('eye-off', 'icon icon-eye-off') ?>
              </button>
            </div>
            <span class="hint">อย่างน้อย 10 ตัว มีทั้งตัวอักษรภาษาอังกฤษและตัวเลข</span>
          </div>
          <div class="field">
            <label for="new_password_confirmation">ยืนยันรหัสผ่านใหม่</label>
            <input id="new_password_confirmation" name="new_password_confirmation" type="password" required maxlength="72" autocomplete="new-password">
          </div>
          <p class="hint">เมื่อเปลี่ยนรหัสผ่าน อุปกรณ์อื่นที่เข้าสู่ระบบอยู่จะถูกออกจากระบบทันที</p>
          <button class="btn btn-primary" type="submit">เปลี่ยนรหัสผ่าน</button>
        </form>
      </section>
    </div>

    <section class="card section-sm">
      <h2 class="h3 card-title"><?= icon('shield-check') ?> ความปลอดภัยของบัญชี</h2>
      <dl class="dl">
        <dt>สมาชิกตั้งแต่</dt><dd><?= e(format_datetime($user['created_at'])) ?></dd>
        <dt>เข้าสู่ระบบล่าสุด</dt><dd><?= e(format_datetime($user['last_login_at'])) ?></dd>
        <dt>IP ล่าสุด</dt><dd class="mono"><?= e($user['last_login_ip'] ?: '—') ?></dd>
        <dt>อุปกรณ์ที่เข้าสู่ระบบอยู่</dt><dd><?= $activeSessions ?> อุปกรณ์</dd>
        <dt>เปลี่ยนรหัสผ่านล่าสุด</dt><dd><?= e(format_datetime($user['password_changed_at'])) ?></dd>
      </dl>
    </section>
  </div>
</main>
<?php require LNW_ROOT . '/includes/layout/footer.php'; ?>