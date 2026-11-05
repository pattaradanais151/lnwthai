<?php
/** Path: pages/login.php */
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

if (Auth::check()) {
    redirect(url('account'));
}
$next = safe_redirect_target($_GET['next'] ?? null, '');
$loggedOut = isset($_GET['logged_out']);
$pageTitle = 'เข้าสู่ระบบ';
$activeNav = 'login';
$noindex = true;
require LNW_ROOT . '/includes/layout/header.php';
?>
<main id="main" class="auth">
  <div class="auth-card">
    <div class="auth-head">
      <span class="auth-icon"><?= icon('log-in') ?></span>
      <h1>เข้าสู่ระบบ</h1>
      <p class="muted">ยินดีต้อนรับกลับสู่ Lnwthai</p>
    </div>
    <?php if ($loggedOut): ?>
      <div class="alert alert-success"><?= icon('circle-check') ?><span>ออกจากระบบเรียบร้อยแล้ว</span></div>
    <?php endif; ?>
    <?php require LNW_ROOT . '/includes/layout/flash.php'; ?>
    <form method="post" action="<?= e(url('actions/login.php')) ?>" class="form">
      <?= Csrf::field() ?>
      <input type="hidden" name="next" value="<?= e($next) ?>">
      <div class="field">
        <label for="email">อีเมล</label>
        <input id="email" name="email" type="email" autocomplete="email" required maxlength="190" value="<?= e(old('email')) ?>">
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
    <p class="auth-foot">ยังไม่มีบัญชี? <a href="<?= e(url('register')) ?>">สมัครสมาชิก</a></p>
  </div>
</main>
<?php require LNW_ROOT . '/includes/layout/footer.php'; ?>