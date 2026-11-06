<?php
/** Path: pages/shop.php — Shop & Product Catalog */
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

$f = Catalog::filters($_GET);
$perPage = max(4, min(48, Settings::int('shop_per_page', 12)));
$result = Catalog::search($f, $perPage);
$games = Catalog::games();
$params = Catalog::params($f);

$gameName = '';
foreach ($games as $g) {
    if ($g['slug'] === $f['game']) {
        $gameName = (string) $g['name'];
    }
}
$heading = $f['q'] !== '' ? 'ผลการค้นหา “' . $f['q'] . '”' : ($gameName !== '' ? $gameName : 'สินค้าทั้งหมด');

$chips = [];
if ($f['q'] !== '') { $chips['q'] = 'ค้นหา: ' . $f['q']; }
if ($gameName !== '') { $chips['game'] = 'เกม: ' . $gameName; }
if ($f['min'] !== null) { $chips['min'] = 'ตั้งแต่ ' . Format::money($f['min']); }
if ($f['max'] !== null) { $chips['max'] = 'ไม่เกิน ' . Format::money($f['max']); }
if ($f['stock'] !== '') { $chips['stock'] = Catalog::STOCK_LABELS[$f['stock']]; }

$pageTitle = $heading;
$pageDescription = 'เลือกซื้อบัญชีเกม' . ($gameName !== '' ? ' ' . $gameName : '') . ' ค้นหาและกรองตามราคาและสถานะสินค้า';
$activeNav = 'shop';
$noindex = $chips !== [];
require LNW_ROOT . '/includes/layout/header.php';
?>
<main id="main" class="section">
  <div class="container">
    <?php require LNW_ROOT . '/includes/layout/flash.php'; ?>
    <nav class="breadcrumb" aria-label="breadcrumb">
      <ol><li><a href="<?= e(url('')) ?>">หน้าแรก</a></li><li aria-current="page">สินค้าทั้งหมด</li></ol>
    </nav>
    <div class="page-head"><h1><?= e($heading) ?></h1></div>

    <?php if ($games): ?>
    <div class="chip-row" aria-label="เลือกเกม">
      <a class="chip-link<?= $f['game'] === '' ? ' is-active' : '' ?>" href="<?= e(Format::query('shop', array_merge($params, ['game' => null]))) ?>">ทุกเกม</a>
      <?php foreach ($games as $g): ?>
        <a class="chip-link<?= $f['game'] === $g['slug'] ? ' is-active' : '' ?>" href="<?= e(Format::query('shop', array_merge($params, ['game' => $g['slug']]))) ?>"><?= e($g['name']) ?></a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="shop-layout">
      <aside>
        <form id="shop-filter" class="card shop-filters" method="get" action="<?= e(url('shop')) ?>" aria-label="ตัวกรองสินค้า">
          <div class="filter-group">
            <label class="filter-title" for="f-q">ค้นหา</label>
            <input id="f-q" type="search" name="q" value="<?= e($f['q']) ?>" maxlength="100" placeholder="ชื่อเกม / สินค้า / รหัส">
          </div>
          <div class="filter-group">
            <label class="filter-title" for="f-game">เกม</label>
            <select id="f-game" name="game">
              <option value="">ทุกเกม</option>
              <?php foreach ($games as $g): ?>
                <option value="<?= e($g['slug']) ?>"<?= $f['game'] === $g['slug'] ? ' selected' : '' ?>><?= e($g['name']) ?> (<?= (int) $g['product_count'] ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <fieldset class="filter-group">
            <legend class="filter-title">ช่วงราคา (บาท)</legend>
            <div class="price-range">
              <input type="number" name="min" min="0" step="1" inputmode="numeric" placeholder="ต่ำสุด" aria-label="ราคาต่ำสุด" value="<?= e($f['min'] ?? '') ?>">
              <span class="muted">–</span>
              <input type="number" name="max" min="0" step="1" inputmode="numeric" placeholder="สูงสุด" aria-label="ราคาสูงสุด" value="<?= e($f['max'] ?? '') ?>">
            </div>
          </fieldset>
          <fieldset class="filter-group">
            <legend class="filter-title">สถานะสินค้า</legend>
            <div class="radio-list">
              <?php foreach (Catalog::STOCK_LABELS as $val => $label): ?>
                <label><input type="radio" name="stock" value="<?= e($val) ?>"<?= $f['stock'] === $val ? ' checked' : '' ?>> <?= e($label) ?></label>
              <?php endforeach; ?>
            </div>
          </fieldset>
          <div class="filter-actions">
            <button class="btn btn-primary btn-sm" type="submit">ใช้ตัวกรอง</button>
            <a class="btn btn-secondary btn-sm" href="<?= e(url('shop')) ?>">ล้างทั้งหมด</a>
          </div>
        </form>
      </aside>

      <div>
        <div class="results-bar">
          <p class="muted">พบ <strong><?= number_format($result['total']) ?></strong> รายการ</p>
          <div class="results-tools">
            <button type="button" class="btn btn-secondary btn-sm filter-toggle" data-filter-toggle aria-controls="shop-filter" aria-expanded="false"><?= icon('sliders-horizontal') ?><span>ตัวกรอง</span></button>
            <label class="sr-only" for="f-sort">เรียงลำดับ</label>
            <select id="f-sort" name="sort" form="shop-filter" data-autosubmit>
              <?php foreach (Catalog::SORT_LABELS as $val => $label): ?>
                <option value="<?= e($val) ?>"<?= $f['sort'] === $val ? ' selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <?php if ($chips): ?>
        <div class="active-filters" aria-label="ตัวกรองที่ใช้อยู่">
          <?php foreach ($chips as $key => $label): ?>
            <a class="chip-link" href="<?= e(Format::query('shop', array_merge($params, [$key => null]))) ?>" aria-label="ลบตัวกรอง <?= e($label) ?>"><?= e($label) ?> <?= icon('x') ?></a>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($result['items']): ?>
          <div class="product-grid product-grid-3">
            <?php foreach ($result['items'] as $p) { require LNW_ROOT . '/includes/layout/product-card.php'; } ?>
          </div>
          <?php $pager = $result; $pagerPath = 'shop'; $pagerParams = $params; require LNW_ROOT . '/includes/layout/pagination.php'; ?>
        <?php else: ?>
          <div class="card empty-state">
            <?= icon('search') ?>
            <h2 class="h3">ไม่พบสินค้าที่ตรงกับเงื่อนไข</h2>
            <p class="muted">ลองเปลี่ยนคำค้นหาหรือล้างตัวกรองบางรายการ</p>
            <a class="btn btn-secondary" href="<?= e(url('shop')) ?>">ดูสินค้าทั้งหมด</a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</main>
<?php require LNW_ROOT . '/includes/layout/footer.php'; ?>