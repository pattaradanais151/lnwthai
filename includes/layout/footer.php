<?php
/** Path: includes/layout/footer.php */
defined('LNW_ROOT') || exit;
$__fStore = Settings::get('store_name', 'Lnwthai');
$__fEmail = Settings::get('contact_email');
$__fEmail = filter_var($__fEmail, FILTER_VALIDATE_EMAIL) !== false ? $__fEmail : '';
$__fPhone = Settings::get('contact_phone');
$__fTel = (string) preg_replace('/[^0-9+]/', '', $__fPhone);
$__fLine = Settings::get('contact_line');
$__fSocial = array_filter([
    'Facebook' => Format::externalUrl(Settings::get('social_facebook')),
    'TikTok'   => Format::externalUrl(Settings::get('social_tiktok')),
]);
$__fGames = array_slice(Catalog::games(), 0, 4);
$__fUser = Auth::user();
?>
<footer class="footer">
  <div class="container">
    <div class="footer-grid">
      <div class="footer-col">
        <div class="brand brand-sm">
          <img class="brand-mark" src="<?= e(asset('images/logo.svg')) ?>" alt="" width="22" height="22">
          <span><?= e($__fStore) ?></span>
        </div>
        <p class="muted small"><?= e(Settings::get('store_description', 'ร้านจำหน่ายบัญชีเกมออนไลน์ ตรวจสอบได้ ปลอดภัย')) ?></p>
      </div>
      <div class="footer-col">
        <h2>ร้านค้า</h2>
        <ul>
          <li><a href="<?= e(url('shop')) ?>">สินค้าทั้งหมด</a></li>
          <?php foreach ($__fGames as $__g): ?>
            <li><a href="<?= e(Format::query('shop', ['game' => $__g['slug']])) ?>"><?= e($__g['name']) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <div class="footer-col">
        <h2>บัญชี</h2>
        <ul>
          <?php if ($__fUser): ?>
            <li><a href="<?= e(url('account')) ?>">บัญชีของฉัน</a></li>
            <li><a href="<?= e(url('orders')) ?>">คำสั่งซื้อ</a></li>
            <li><a href="<?= e(url('purchases')) ?>">บัญชีเกมที่ได้รับ</a></li>
          <?php else: ?>
            <li><a href="<?= e(url('login')) ?>">เข้าสู่ระบบ</a></li>
            <li><a href="<?= e(url('register')) ?>">สมัครสมาชิก</a></li>
          <?php endif; ?>
        </ul>
      </div>
      <div class="footer-col">
        <h2>ติดต่อเรา</h2>
        <ul>
          <?php if ($__fEmail !== ''): ?><li><?= icon('mail') ?><a href="mailto:<?= e($__fEmail) ?>"><?= e($__fEmail) ?></a></li><?php endif; ?>
          <?php if ($__fTel !== ''): ?><li><?= icon('phone') ?><a href="tel:<?= e($__fTel) ?>"><?= e($__fPhone) ?></a></li><?php endif; ?>
          <?php if ($__fLine !== ''): ?><li><?= icon('message-circle') ?><span>LINE: <?= e($__fLine) ?></span></li><?php endif; ?>
          <?php foreach ($__fSocial as $__name => $__href): ?>
            <li><?= icon('link') ?><a href="<?= e($__href) ?>" rel="noopener noreferrer" target="_blank"><?= e($__name) ?></a></li>
          <?php endforeach; ?>
          <?php if ($__fEmail === '' && $__fTel === '' && $__fLine === '' && !$__fSocial): ?>
            <li class="muted">ยังไม่ได้ระบุช่องทางติดต่อ</li>
          <?php endif; ?>
        </ul>
      </div>
    </div>
    <div class="footer-bottom">
      <p class="muted small">© <?= date('Y') ?> <?= e($__fStore) ?> · จำหน่ายเฉพาะบัญชีที่โอนสิทธิ์ได้ตามข้อกำหนดของผู้ให้บริการเกม</p>
    </div>
  </div>
</footer>
</body>
</html>