<?php
/** Path: pages/register.php */
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

if (Auth::check()) {
    redirect(url('account'));
}
$pageTitle = 'สมัครสมาชิก';
$activeNav = 'register';
$noindex = true;
require LNW_ROOT . '/includes/layout/header.php';
?>
<main id="main" class="auth">
  <div class="auth-card">
    <div class="auth-head">
      <span class="auth-icon"><?= icon('user-plus') ?></span>
      <h1>สร้างบัญชี Lnwthai</h1>
      <p class="muted">ใช้เวลาไม่ถึงหนึ่งนาที</p>
    </div>
    <?php require LNW_ROOT . '/includes/layout/flash.php'; ?>
    <form method="post" action="<?= e(url('actions/register.php')) ?>" class="form">
      <?= Csrf::field() ?>
            <div class="hp" aria-hidden="true">
        <label for="website">Website</label>
        <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
      </div>
      <div class="field">
        <label for="display_name">ชื่อที่แสดง</label>
        <input id="display_name" name="display_name" type="text" autocomplete="nickname" required minlength="2" maxlength="100" value="<?= e(old('display_name')) ?>">
      </div>
      <div class="field">
        <label for="email">อีเมล</label>
        <input id="email" name="email" type="email" autocomplete="email" required maxlength="190" value="<?= e(old('email')) ?>">
      </div>
      <div class="field">
        <label for="phone">เบอร์โทรศัพท์ <span class="muted">(ไม่บังคับ)</span></label>
        <input id="phone" name="phone" type="tel" autocomplete="tel" maxlength="20" value="<?= e(old('phone')) ?>">
      </div>
      <div class="field">
        <label for="password">รหัสผ่าน</label>
        <div class="input-group">
          <input id="password" name="password" type="password" autocomplete="new-password" required minlength="10" maxlength="72">
          <button type="button" class="input-addon" data-toggle-password="password" aria-label="แสดงหรือซ่อนรหัสผ่าน" aria-pressed="false">
            <?= icon('eye', 'icon icon-eye') ?><?= icon('eye-off', 'icon icon-eye-off') ?>
          </button>
        </div>
        <span class="hint">อย่างน้อย 10 ตัว มีทั้งตัวอักษรภาษาอังกฤษและตัวเลข</span>
      </div>
      <div class="field">
        <label for="password_confirmation">ยืนยันรหัสผ่าน</label>
        <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required maxlength="72">
      </div>
      <button class="btn btn-primary btn-block" type="submit">สมัครสมาชิก</button>
    </form>
    <p class="auth-foot">มีบัญชีอยู่แล้ว? <a href="<?= e(url('login')) ?>">เข้าสู่ระบบ</a></p>
  </div>
</main>
<?php require LNW_ROOT . '/includes/layout/footer.php'; ?>