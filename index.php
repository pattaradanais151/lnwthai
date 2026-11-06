<?php
/**
 * Lnwthai — Homepage
 * Path: index.php
 */
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$games = Catalog::games(true) ?: array_slice(Catalog::games(), 0, 8);
$featured = Catalog::featured(8);
$offers = Catalog::offers(4);
$latest = Catalog::latest(8);
$user = Auth::user();

$heroTitle = Settings::get('hero_title', 'บัญชีเกมคุณภาพ ซื้อง่าย ปลอดภัย ตรวจสอบได้');
$heroSub = Settings::get('hero_subtitle', 'ทุกคำสั่งซื้อตรวจสอบการชำระเงินโดยทีมงาน ข้อมูลบัญชีถูกเข้ารหัส และส่งมอบผ่านหน้าบัญชีของคุณเท่านั้น');

$steps = [
    ['เลือกสินค้า', 'ค้นหาและกรองบัญชีเกมตามเกม ราคา และรายละเอียดที่ต้องการ'],
    ['สั่งซื้อ', 'กดซื้อแล้วระบบจะจองบัญชีให้คุณชั่วคราวพร้อมออกเลขคำสั่งซื้อ'],
    ['ชำระเงินและแนบหลักฐาน', 'โอนตามช่องทางที่ร้านกำหนด แล้วแนบหลักฐานในหน้าคำสั่งซื้อ'],
    ['รับบัญชี', 'เมื่อทีมงานยืนยันยอดแล้ว ข้อมูลบัญชีจะแสดงในหน้าบัญชีของคุณ'],
];
$trust = [
    ['shield-check', 'ตรวจยอดกับบัญชีธนาคารจริง', 'ทีมงานยืนยันการชำระเงินจากรายการเดินบัญชี ไม่ใช่จากรูปสลิปเพียงอย่างเดียว'],
    ['lock', 'ข้อมูลบัญชีถูกเข้ารหัส', 'ข้อมูลเข้าสู่ระบบของบัญชีเกมถูกเข้ารหัส และแสดงเฉพาะผู้ซื้อเท่านั้น'],
    ['users', 'ตรวจสอบย้อนหลังได้', 'ทุกการดำเนินการของทีมงานถูกบันทึกพร้อมชื่อผู้ทำรายการและเวลา'],
    ['key-round', 'ส่งมอบผ่านบัญชีของคุณ', 'ไม่ต้องรอแชต ข้อมูลบัญชีอยู่ในหน้า "บัญชีเกมที่ได้รับ" ตลอดเวลา'],
];
$faqs = [
    ['ซื้อแล้วจะได้รับบัญชีอย่างไร?', 'หลังทีมงานยืนยันการชำระเงิน ข้อมูลเข้าสู่ระบบจะแสดงในหน้า "บัญชีเกมที่ได้รับ" ของคุณ ร้านไม่ส่งรหัสผ่านทางแชตหรืออีเมล'],
    ['ตรวจสอบการชำระเงินใช้เวลานานแค่ไหน?', 'ทีมงานตรวจสอบยอดกับบัญชีธนาคารด้วยตนเอง ระยะเวลาขึ้นอยู่กับช่วงเวลาทำการของร้าน คุณติดตามสถานะได้ในหน้าคำสั่งซื้อ'],
    ['ร้านจำหน่ายบัญชีแบบไหน?', 'ร้านจำหน่ายเฉพาะบัญชีที่มีสิทธิ์จำหน่ายและโอนสิทธิ์ได้ตามข้อกำหนดของผู้ให้บริการเกม และไม่รับบัญชีที่ได้มาโดยมิชอบ'],
    ['ได้รับบัญชีแล้วควรทำอะไรก่อน?', 'เปลี่ยนรหัสผ่านและอีเมลกู้คืนเป็นของคุณทันที และเปิดการยืนยันตัวตนสองขั้นตอนหากเกมรองรับ'],
    ['สินค้าที่ขึ้นว่า "สินค้าหมด" จะกลับมาไหม?', 'บัญชีเกมแต่ละรายการมีจำนวนจำกัด เมื่อขายครบแล้วอาจไม่มีรายการเดิมอีก แนะนำให้ดูสินค้าใกล้เคียงในเกมเดียวกัน'],
];

$pageTitle = '';
$activeNav = 'home';
require LNW_ROOT . '/includes/layout/header.php';
?>
<main id="main">
  <div class="container"><?php require LNW_ROOT . '/includes/layout/flash.php'; ?></div>

  <section class="hero">
    <div class="container hero-inner">
      <span class="eyebrow"><?= icon('gamepad-2') ?> <?= e(Settings::get('store_tagline', 'Premium Digital Game Account Store')) ?></span>
      <h1 class="hero-title"><?= e($heroTitle) ?></h1>
      <p class="hero-sub"><?= e($heroSub) ?></p>
      <form class="hero-search" action="<?= e(url('shop')) ?>" method="get" role="search">
        <input type="search" name="q" placeholder="ค้นหาชื่อเกมหรือสินค้า เช่น แรงก์, สกิน" aria-label="ค้นหาสินค้า" maxlength="100">
        <button class="btn btn-primary" type="submit"><?= icon('search') ?><span>ค้นหา</span></button>
      </form>
      <div class="hero-actions">
        <a class="btn btn-secondary" href="<?= e(url('shop')) ?>">ดูสินค้าทั้งหมด <?= icon('arrow-right') ?></a>
        <?php if (!$user): ?><a class="btn btn-secondary" href="<?= e(url('register')) ?>">สมัครสมาชิก</a><?php endif; ?>
      </div>
    </div>
  </section>

  <?php if ($games): ?>
  <section class="section" aria-labelledby="games-title">
    <div class="container">
      <div class="section-head">
        <div><h2 id="games-title">เกมยอดนิยม</h2><p class="muted">เลือกเกมเพื่อดูบัญชีที่มีจำหน่าย</p></div>
        <a class="link-more" href="<?= e(url('shop')) ?>">ทั้งหมด <?= icon('chevron-right') ?></a>
      </div>
      <div class="game-grid">
        <?php foreach ($games as $g): $gIcon = Format::imageUrl($g['icon_path']); ?>
          <a class="game-tile" href="<?= e(Format::query('shop', ['game' => $g['slug']])) ?>">
            <span class="game-avatar">
              <?php if ($gIcon): ?><img src="<?= e($gIcon) ?>" alt="" loading="lazy"><?php else: ?><?= e(Format::initials($g['name'])) ?><?php endif; ?>
            </span>
            <span><strong><?= e($g['name']) ?></strong><small><?= (int) $g['product_count'] ?> รายการ</small></span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($featured): ?>
  <section class="section section-alt" aria-labelledby="featured-title">
    <div class="container">
      <div class="section-head">
        <div><h2 id="featured-title">สินค้าแนะนำ</h2><p class="muted">คัดสรรโดยทีมงาน</p></div>
        <a class="link-more" href="<?= e(url('shop')) ?>">ดูทั้งหมด <?= icon('chevron-right') ?></a>
      </div>
      <div class="product-grid">
        <?php foreach ($featured as $p) { require LNW_ROOT . '/includes/layout/product-card.php'; } ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($offers): ?>
  <section class="section" aria-labelledby="offers-title">
    <div class="container">
      <div class="section-head">
        <div><h2 id="offers-title"><?= icon('tag') ?> ข้อเสนอพิเศษ</h2><p class="muted">ราคาพิเศษในช่วงเวลาจำกัด</p></div>
      </div>
      <div class="product-grid">
        <?php foreach ($offers as $p) { require LNW_ROOT . '/includes/layout/product-card.php'; } ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($latest): ?>
  <section class="section section-alt" aria-labelledby="latest-title">
    <div class="container">
      <div class="section-head">
        <div><h2 id="latest-title">มาใหม่ล่าสุด</h2><p class="muted">บัญชีที่เพิ่งเข้าร้าน</p></div>
        <a class="link-more" href="<?= e(Format::query('shop', ['sort' => null])) ?>">ดูทั้งหมด <?= icon('chevron-right') ?></a>
      </div>
      <div class="product-grid">
        <?php foreach ($latest as $p) { require LNW_ROOT . '/includes/layout/product-card.php'; } ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <?php if (!$featured && !$latest): ?>
  <section class="section">
    <div class="container">
      <div class="card empty-state"><?= icon('package') ?><h2 class="h3">ยังไม่มีสินค้าในร้าน</h2><p class="muted">ทีมงานกำลังเตรียมสินค้า กรุณากลับมาใหม่เร็ว ๆ นี้</p></div>
    </div>
  </section>
  <?php endif; ?>

  <section class="section" aria-labelledby="howto-title">
    <div class="container">
      <div class="section-head"><div><h2 id="howto-title">ซื้ออย่างไร</h2><p class="muted">4 ขั้นตอนง่าย ๆ</p></div></div>
      <div class="steps-grid">
        <?php foreach ($steps as $i => [$t, $d]): ?>
          <div class="step"><span class="step-num"><?= $i + 1 ?></span><h3><?= e($t) ?></h3><p><?= e($d) ?></p></div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="section section-alt" aria-labelledby="trust-title">
    <div class="container">
      <div class="section-head"><div><h2 id="trust-title">ความปลอดภัยและความน่าเชื่อถือ</h2></div></div>
      <div class="grid-4">
        <?php foreach ($trust as [$ic, $t, $d]): ?>
          <article class="card feature">
            <span class="feature-icon"><?= icon($ic) ?></span>
            <h3><?= e($t) ?></h3>
            <p class="muted small"><?= e($d) ?></p>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="section" aria-labelledby="faq-title">
    <div class="container">
      <div class="section-head"><div><h2 id="faq-title"><?= icon('circle-help') ?> คำถามที่พบบ่อย</h2></div></div>
      <div class="faq">
        <?php foreach ($faqs as $i => [$q, $a]): ?>
          <details<?= $i === 0 ? ' open' : '' ?>><summary><?= e($q) ?></summary><p><?= e($a) ?></p></details>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="section">
    <div class="container">
      <div class="cta-band">
        <h2>พร้อมเลือกบัญชีเกมของคุณแล้วหรือยัง?</h2>
        <p>ดูสินค้าทั้งหมดและกรองตามเกม ราคา และสถานะได้ทันที</p>
        <a class="btn" href="<?= e(url('shop')) ?>">เริ่มเลือกสินค้า <?= icon('arrow-right') ?></a>
      </div>
    </div>
  </section>
</main>
<?php require LNW_ROOT . '/includes/layout/footer.php'; ?>