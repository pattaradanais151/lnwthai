<?php
/** Path: pages/product.php — Product Details (ไม่แสดงข้อมูลลับของบัญชีเกม) */
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

$slug = is_string($_GET['slug'] ?? null) ? $_GET['slug'] : '';
if (preg_match('/^[a-z0-9-]{1,190}$/', $slug) !== 1) {
    render_error(404);
}
$product = Catalog::findBySlug($slug) ?? render_error(404, 'ไม่พบสินค้านี้ หรือสินค้าถูกนำออกจากร้านแล้ว');

$images = [];
foreach (Catalog::images((int) $product['id']) as $img) {
    $src = Format::imageUrl($img['public_url']);
    if ($src !== null) {
        $images[] = ['src' => $src, 'alt' => (string) ($img['alt_text'] ?: $product['title'])];
    }
}
$related = Catalog::related((int) $product['game_id'], (int) $product['id'], 4);
$buyable = Catalog::isPurchasable($product);
$off = Format::discountPercent($product['price'], $product['compare_at_price']);
$itemList = Format::lines($product['items_summary']);

$attributes = [];
if (is_string($product['attributes']) && $product['attributes'] !== '') {
    $decoded = json_decode($product['attributes'], true);
    if (is_array($decoded)) {
        foreach ($decoded as $k => $v) {
            if ((string) $k !== '' && is_scalar($v)) {
                $attributes[(string) $k] = is_bool($v) ? ($v ? 'มี' : 'ไม่มี') : (string) $v;
            }
        }
    }
}
$specs = array_filter([
    'แพลตฟอร์ม'     => $product['platform'],
    'เซิร์ฟเวอร์ / ภูมิภาค' => $product['server_region'],
    'เลเวล / แรงก์'  => $product['level_rank'],
    'หมวดหมู่'       => $product['category_name'],
], static fn($v): bool => is_string($v) && $v !== '');

$pageTitle = (string) $product['title'];
$pageDescription = (string) ($product['short_description'] ?: mb_strimwidth((string) $product['description'], 0, 160, '…'));
$activeNav = 'shop';
require LNW_ROOT . '/includes/layout/header.php';
?>
<main id="main" class="section">
  <div class="container">
    <?php require LNW_ROOT . '/includes/layout/flash.php'; ?>
    <nav class="breadcrumb" aria-label="breadcrumb">
      <ol>
        <li><a href="<?= e(url('')) ?>">หน้าแรก</a></li>
        <li><a href="<?= e(url('shop')) ?>">สินค้าทั้งหมด</a></li>
        <li><a href="<?= e(Format::query('shop', ['game' => $product['game_slug']])) ?>"><?= e($product['game_name']) ?></a></li>
        <li aria-current="page"><?= e(mb_strimwidth((string) $product['title'], 0, 40, '…')) ?></li>
      </ol>
    </nav>

    <div class="product-detail">
      <div class="gallery" data-gallery>
        <div class="gallery-main">
          <?php if ($images): ?>
            <img src="<?= e($images[0]['src']) ?>" alt="<?= e($images[0]['alt']) ?>" data-gallery-main decoding="async">
          <?php else: ?>
            <div class="media-placeholder" aria-hidden="true"><?= e(Format::initials($product['game_name'])) ?></div>
          <?php endif; ?>
        </div>
        <?php if (count($images) > 1): ?>
          <div class="gallery-thumbs">
            <?php foreach ($images as $i => $img): ?>
              <button type="button" class="gallery-thumb" data-gallery-thumb data-src="<?= e($img['src']) ?>" data-alt="<?= e($img['alt']) ?>" aria-label="ดูรูปที่ <?= $i + 1 ?>" aria-current="<?= $i === 0 ? 'true' : 'false' ?>">
                <img src="<?= e($img['src']) ?>" alt="" loading="lazy" decoding="async">
              </button>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="product-info">
        <a class="product-game" href="<?= e(Format::query('shop', ['game' => $product['game_slug']])) ?>"><?= e($product['game_name']) ?></a>
        <h1><?= e($product['title']) ?></h1>
        <?php if ($product['short_description']): ?><p class="muted"><?= e($product['short_description']) ?></p><?php endif; ?>

        <div class="price-row">
          <span class="price-lg"><?= e(Format::money($product['price'])) ?></span>
          <?php if ($off): ?>
            <s class="price-old"><?= e(Format::money($product['compare_at_price'])) ?></s>
            <span class="badge badge-error">ประหยัด <?= (int) $off ?>%</span>
          <?php endif; ?>
        </div>

        <?php if ($specs): ?>
        <div class="spec-grid">
          <?php foreach ($specs as $k => $v): ?><div class="spec"><span><?= e($k) ?></span><strong><?= e($v) ?></strong></div><?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="buy-box">
          <div>
            <?php if ($buyable): ?>
              <span class="badge badge-ok"><?= icon('check') ?> พร้อมจำหน่าย</span>
            <?php else: ?>
              <span class="badge badge-neutral">สินค้าหมด</span>
            <?php endif; ?>
            <span class="muted small"> · รหัสสินค้า <span class="mono"><?= e($product['sku']) ?></span></span>
          </div>
          <?php if ($buyable): ?>
            <button class="btn btn-primary btn-lg btn-block" type="button" disabled><?= icon('shopping-bag') ?> ซื้อเลย</button>
            <p class="hint">ร้านยังไม่เปิดรับคำสั่งซื้อออนไลน์ — จะเปิดให้สั่งซื้อเร็ว ๆ นี้</p>
          <?php else: ?>
            <button class="btn btn-secondary btn-lg btn-block" type="button" disabled>สินค้าหมด</button>
            <a class="link-more" href="<?= e(Format::query('shop', ['game' => $product['game_slug'], 'stock' => 'in'])) ?>">ดูสินค้าที่พร้อมจำหน่ายในเกมนี้ <?= icon('chevron-right') ?></a>
          <?php endif; ?>
          <ul class="check-list">
            <li><?= icon('check') ?><span>ตรวจสอบการชำระเงินโดยทีมงานก่อนส่งมอบ</span></li>
            <li><?= icon('check') ?><span>ข้อมูลเข้าสู่ระบบแสดงเฉพาะผู้ซื้อหลังยืนยันการชำระเงิน</span></li>
            <li><?= icon('check') ?><span>ควรเปลี่ยนรหัสผ่านและอีเมลกู้คืนทันทีหลังได้รับบัญชี</span></li>
          </ul>
        </div>
      </div>
    </div>

    <div class="grid-2 section-sm">
      <?php if ($itemList): ?>
      <section class="card">
        <h2 class="h3 card-title"><?= icon('star') ?> สกิน / ไอเทม / ตัวละคร</h2>
        <ul class="check-list">
          <?php foreach ($itemList as $it): ?><li><?= icon('check') ?><span><?= e($it) ?></span></li><?php endforeach; ?>
        </ul>
      </section>
      <?php endif; ?>
      <?php if ($attributes): ?>
      <section class="card">
        <h2 class="h3 card-title"><?= icon('info') ?> รายละเอียดเพิ่มเติม</h2>
        <dl class="dl">
          <?php foreach ($attributes as $k => $v): ?><dt><?= e($k) ?></dt><dd><?= e($v) ?></dd><?php endforeach; ?>
        </dl>
      </section>
      <?php endif; ?>
    </div>

    <?php if ($product['description']): ?>
    <section class="card section-sm">
      <h2 class="h3">รายละเอียดสินค้า</h2>
      <p class="prose"><?= e($product['description']) ?></p>
    </section>
    <?php endif; ?>

    <?php if ($related): ?>
    <section class="section" aria-labelledby="related-title">
      <div class="section-head"><div><h2 id="related-title">สินค้าอื่นใน <?= e($product['game_name']) ?></h2></div></div>
      <div class="product-grid">
        <?php foreach ($related as $p) { require LNW_ROOT . '/includes/layout/product-card.php'; } ?>
      </div>
    </section>
    <?php endif; ?>
  </div>
</main>
<?php require LNW_ROOT . '/includes/layout/footer.php'; ?>