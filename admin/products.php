<?php
/** Path: admin/products.php — รายการสินค้า (product.view) */
declare(strict_types=1);
require __DIR__ . '/includes/guard.php';

admin_require('product.view');
$statusOptions = Format::options('product');

$q = is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']), 0, 100) : '';
$gameId = admin_int($_GET['game'] ?? 0);
$status = is_string($_GET['status'] ?? null) && isset($statusOptions[$_GET['status']]) ? $_GET['status'] : '';
$page = admin_int($_GET['page'] ?? 1) ?: 1;
$perPage = 20;

$where = [];
$params = [];
if ($q !== '') {
    $like = '%' . strtr($q, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
    $where[] = "(p.title LIKE ? ESCAPE '!' OR p.sku LIKE ? ESCAPE '!' OR p.slug LIKE ? ESCAPE '!')";
    array_push($params, $like, $like, $like);
}
if ($gameId > 0) {
    $where[] = 'p.game_id = ?';
    $params[] = $gameId;
}
if ($status !== '') {
    $where[] = 'p.status = ?';
    $params[] = $status;
} else {
    $where[] = "p.status <> 'archived'";
}
$sqlWhere = ' WHERE ' . implode(' AND ', $where);

$total = (int) DB::value('SELECT COUNT(*) FROM products p' . $sqlWhere, $params);
$pages = max(1, (int) ceil($total / $perPage));
$page = min($page, $pages);
$items = DB::all(
    "SELECT p.id, p.sku, p.slug, p.title, p.price, p.status, p.is_featured, p.is_special_offer, p.updated_at, g.name AS game_name,
            (SELECT pi.public_url FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.is_primary DESC, pi.sort_order, pi.id LIMIT 1) AS image_url,
            (SELECT COUNT(*) FROM game_accounts ga WHERE ga.product_id = p.id AND ga.status = 'available') AS c_available,
            (SELECT COUNT(*) FROM game_accounts ga WHERE ga.product_id = p.id AND ga.status = 'reserved') AS c_reserved,
            (SELECT COUNT(*) FROM game_accounts ga WHERE ga.product_id = p.id AND ga.status = 'sold') AS c_sold
     FROM products p JOIN games g ON g.id = p.game_id" . $sqlWhere . '
     ORDER BY p.updated_at DESC, p.id DESC LIMIT ? OFFSET ?',
    [...$params, $perPage, ($page - 1) * $perPage]
);
$games = DB::all('SELECT id, name FROM games ORDER BY sort_order, name');

$canEdit = admin_can('product.edit');
$canInventory = admin_can('inventory.view');

$pageTitle = 'สินค้า';
$activeNav = 'products';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head page-head-row">
  <div><h1>สินค้า</h1><p class="muted">พบ <?= number_format($total) ?> รายการ</p></div>
  <?php if (admin_can('product.create')): ?>
    <a class="btn btn-primary" href="<?= e(url('admin/product-edit.php')) ?>"><?= icon('package') ?> เพิ่มสินค้า</a>
  <?php endif; ?>
</div>

<form class="toolbar" method="get" action="<?= e(url('admin/products.php')) ?>">
  <div class="field"><label for="q">ค้นหา</label><input id="q" type="search" name="q" value="<?= e($q) ?>" placeholder="ชื่อ / SKU / slug" maxlength="100"></div>
  <div class="field">
    <label for="game">เกม</label>
    <select id="game" name="game">
      <option value="">ทุกเกม</option>
      <?php foreach ($games as $g): ?><option value="<?= (int) $g['id'] ?>"<?= $gameId === (int) $g['id'] ? ' selected' : '' ?>><?= e($g['name']) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="field">
    <label for="status">สถานะ</label>
    <select id="status" name="status">
      <option value="">ทั้งหมด (ยกเว้นเก็บถาวร)</option>
      <?php foreach ($statusOptions as $k => $label): ?><option value="<?= e($k) ?>"<?= $status === $k ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
    </select>
  </div>
  <button class="btn btn-secondary" type="submit">กรอง</button>
</form>

<?php if ($items): ?>
<section class="card">
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>สินค้า</th><th>เกม</th><th>ราคา</th><th>สถานะ</th><th>สต็อก</th><th>แก้ไขล่าสุด</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($items as $p): $img = Format::imageUrl($p['image_url']); ?>
        <tr>
          <td><div class="cell-title">
            <?php if ($img): ?><img class="thumb" src="<?= e($img) ?>" alt="" loading="lazy"><?php else: ?><span class="thumb-ph"><?= e(Format::initials($p['game_name'])) ?></span><?php endif; ?>
            <div>
              <strong><?= e($p['title']) ?></strong>
              <span class="muted small mono"><?= e($p['sku']) ?></span>
              <?php if ((int) $p['is_featured'] === 1): ?><span class="badge badge-info">แนะนำ</span><?php endif; ?>
              <?php if ((int) $p['is_special_offer'] === 1): ?><span class="badge badge-warn">ข้อเสนอพิเศษ</span><?php endif; ?>
            </div>
          </div></td>
          <td><?= e($p['game_name']) ?></td>
          <td class="nowrap"><?= e(Format::money($p['price'])) ?></td>
          <td><?= Format::badge('product', $p['status']) ?></td>
          <td><div class="stock-pills">
            <span class="badge badge-ok">ว่าง <?= (int) $p['c_available'] ?></span>
            <?php if ((int) $p['c_reserved'] > 0): ?><span class="badge badge-info">จอง <?= (int) $p['c_reserved'] ?></span><?php endif; ?>
            <?php if ((int) $p['c_sold'] > 0): ?><span class="badge badge-neutral">ขาย <?= (int) $p['c_sold'] ?></span><?php endif; ?>
          </div></td>
          <td class="nowrap small"><?= e(format_datetime($p['updated_at'])) ?></td>
          <td><div class="actions">
            <?php if ($canEdit): ?><a class="btn btn-secondary btn-xs" href="<?= e(url('admin/product-edit.php') . '?id=' . (int) $p['id']) ?>">แก้ไข</a><?php endif; ?>
            <?php if ($canInventory): ?><a class="btn btn-secondary btn-xs" href="<?= e(url('admin/inventory.php') . '?product=' . (int) $p['id']) ?>">คลังบัญชี</a><?php endif; ?>
            <?php if (in_array($p['status'], ['active', 'sold_out'], true)): ?><a class="btn btn-secondary btn-xs" href="<?= e(url('product/' . $p['slug'])) ?>" target="_blank" rel="noopener">หน้าร้าน</a><?php endif; ?>
          </div></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php
  $pager = ['page' => $page, 'pages' => $pages];
  $pagerPath = 'admin/products.php';
  $pagerParams = ['q' => $q, 'game' => $gameId ?: null, 'status' => $status];
  require LNW_ROOT . '/includes/layout/pagination.php';
?>
<?php else: ?>
  <div class="card empty-state"><?= icon('package') ?><h2 class="h3">ไม่พบสินค้า</h2><p class="muted">ลองเปลี่ยนตัวกรอง หรือเพิ่มสินค้าใหม่</p></div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>