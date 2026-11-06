<?php
/**
 * Path: admin/inventory.php — คลังบัญชีเกม (inventory.view)
 * ไม่มีการ SELECT secret_payload ในหน้านี้ · การดูข้อมูลลับต้องผ่าน inventory-reveal.php
 */
declare(strict_types=1);
require __DIR__ . '/includes/guard.php';

admin_require('inventory.view');
$productId = admin_int($_GET['product'] ?? 0);
$canManage = admin_can('inventory.manage');
$canReveal = admin_can('inventory.reveal');
$vaultReady = Vault::ready();
$statusOptions = Format::options('account');
$page = admin_int($_GET['page'] ?? 1) ?: 1;

// ================================================================== ภาพรวมทุกสินค้า
if ($productId === 0) {
    $q = is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']), 0, 100) : '';
    $where = "p.status <> 'archived'";
    $params = [];
    if ($q !== '') {
        $like = '%' . strtr($q, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
        $where .= " AND (p.title LIKE ? ESCAPE '!' OR p.sku LIKE ? ESCAPE '!')";
        array_push($params, $like, $like);
    }
    $perPage = 25;
    $total = (int) DB::value("SELECT COUNT(*) FROM products p WHERE {$where}", $params);
    $pages = max(1, (int) ceil($total / $perPage));
    $page = min($page, $pages);
    $rows = DB::all(
        "SELECT p.id, p.sku, p.title, p.status, g.name AS game_name,
                (SELECT COUNT(*) FROM game_accounts ga WHERE ga.product_id = p.id AND ga.status = 'available') AS c_available,
                (SELECT COUNT(*) FROM game_accounts ga WHERE ga.product_id = p.id AND ga.status = 'reserved') AS c_reserved,
                (SELECT COUNT(*) FROM game_accounts ga WHERE ga.product_id = p.id AND ga.status = 'sold') AS c_sold,
                (SELECT COUNT(*) FROM game_accounts ga WHERE ga.product_id = p.id AND ga.status = 'disabled') AS c_disabled
         FROM products p JOIN games g ON g.id = p.game_id WHERE {$where}
         ORDER BY p.updated_at DESC, p.id DESC LIMIT ? OFFSET ?",
        [...$params, $perPage, ($page - 1) * $perPage]
    );

    $pageTitle = 'คลังบัญชีเกม';
    $activeNav = 'inventory';
    require __DIR__ . '/includes/header.php';
    ?>
    <div class="page-head"><h1>คลังบัญชีเกม</h1><p class="muted">เลือกสินค้าเพื่อจัดการบัญชีเกม</p></div>
    <?php if (!$vaultReady): ?>
      <div class="alert alert-warning"><?= icon('lock') ?><span>ระบบเข้ารหัสยังไม่พร้อม: <?= e(Vault::error()) ?><?= admin_can('system.check') ? ' — ' : '' ?><?php if (admin_can('system.check')): ?><a href="<?= e(url('admin/vault.php')) ?>">ตั้งค่า</a><?php endif; ?></span></div>
    <?php endif; ?>
    <form class="toolbar" method="get" action="<?= e(url('admin/inventory.php')) ?>">
      <div class="field"><label for="q">ค้นหาสินค้า</label><input id="q" type="search" name="q" value="<?= e($q) ?>" maxlength="100" placeholder="ชื่อ / SKU"></div>
      <button class="btn btn-secondary" type="submit">ค้นหา</button>
    </form>
    <?php if ($rows): ?>
    <section class="card">
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>สินค้า</th><th>เกม</th><th>สถานะสินค้า</th><th>พร้อมขาย</th><th>ถูกจอง</th><th>ขายแล้ว</th><th>ปิดใช้งาน</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><strong><?= e($r['title']) ?></strong><br><span class="muted small mono"><?= e($r['sku']) ?></span></td>
              <td><?= e($r['game_name']) ?></td>
              <td><?= Format::badge('product', $r['status']) ?></td>
              <td><?= (int) $r['c_available'] ?></td>
              <td><?= (int) $r['c_reserved'] ?></td>
              <td><?= (int) $r['c_sold'] ?></td>
              <td><?= (int) $r['c_disabled'] ?></td>
              <td><a class="btn btn-secondary btn-xs" href="<?= e(url('admin/inventory.php') . '?product=' . (int) $r['id']) ?>">จัดการ</a></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
    <?php
      $pager = ['page' => $page, 'pages' => $pages];
      $pagerPath = 'admin/inventory.php';
      $pagerParams = ['q' => $q];
      require LNW_ROOT . '/includes/layout/pagination.php';
    ?>
    <?php else: ?>
      <div class="card empty-state"><?= icon('key-round') ?><h2 class="h3">ไม่พบสินค้า</h2></div>
    <?php endif; ?>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

// ================================================================== รายละเอียดสินค้าเดียว
$product = DB::one(
    'SELECT p.id, p.sku, p.title, p.status, g.name AS game_name FROM products p JOIN games g ON g.id = p.game_id WHERE p.id = ?',
    [$productId]
) ?? render_error(404, 'ไม่พบสินค้า');
$counts = Inventory::counts($productId);
$status = is_string($_GET['status'] ?? null) && isset($statusOptions[$_GET['status']]) ? $_GET['status'] : '';
$perPage = 30;

$where = 'ga.product_id = ?';
$params = [$productId];
if ($status !== '') {
    $where .= ' AND ga.status = ?';
    $params[] = $status;
}
$total = (int) DB::value("SELECT COUNT(*) FROM game_accounts ga WHERE {$where}", $params);
$pages = max(1, (int) ceil($total / $perPage));
$page = min($page, $pages);
$accounts = DB::all(
    "SELECT ga.id, ga.status, ga.key_version, ga.public_note, ga.internal_note, ga.reserved_until, ga.sold_at,
            ga.disabled_reason, ga.secret_purged_at, ga.created_at, ga.updated_at,
            ro.order_number AS reserved_order, so.order_number AS sold_order
     FROM game_accounts ga
     LEFT JOIN orders ro ON ro.id = ga.reserved_order_id
     LEFT JOIN orders so ON so.id = ga.sold_order_id
     WHERE {$where} ORDER BY ga.id DESC LIMIT ? OFFSET ?",
    [...$params, $perPage, ($page - 1) * $perPage]
);

$editId = admin_int($_GET['edit'] ?? 0);
$editing = $canManage && $editId > 0
    ? DB::one('SELECT id, status, public_note, internal_note FROM game_accounts WHERE id = ? AND product_id = ?', [$editId, $productId])
    : null;
$base = url('admin/inventory.php') . '?product=' . $productId;

$pageTitle = 'คลังบัญชี · ' . $product['sku'];
$activeNav = 'inventory';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head page-head-row">
  <div>
    <nav class="breadcrumb" aria-label="breadcrumb"><ol>
      <li><a href="<?= e(url('admin/inventory.php')) ?>">คลังบัญชีเกม</a></li>
      <li aria-current="page" class="mono"><?= e($product['sku']) ?></li>
    </ol></nav>
    <h1><?= e($product['title']) ?></h1>
    <p class="muted"><?= e($product['game_name']) ?> · <?= Format::badge('product', $product['status']) ?></p>
  </div>
  <?php if (admin_can('product.edit')): ?>
    <a class="btn btn-secondary btn-sm" href="<?= e(url('admin/product-edit.php') . '?id=' . $productId) ?>">แก้ไขสินค้า</a>
  <?php endif; ?>
</div>

<div class="stat-row">
  <?php foreach ($statusOptions as $k => $label): ?>
    <div class="stat-mini"><span><?= e($label) ?></span><strong><?= number_format($counts[$k]) ?></strong></div>
  <?php endforeach; ?>
</div>

<?php if (!$vaultReady): ?>
  <div class="alert alert-warning"><?= icon('lock') ?><span>ระบบเข้ารหัสยังไม่พร้อม (<?= e(Vault::error()) ?>) — เพิ่ม/แก้ไข/ดูข้อมูลลับไม่ได้จนกว่าจะตั้งค่า<?php if (admin_can('system.check')): ?> · <a href="<?= e(url('admin/vault.php')) ?>">ไปที่หน้าการเข้ารหัส</a><?php endif; ?></span></div>
<?php endif; ?>

<?php if ($editing): $loginEditable = in_array($editing['status'], Inventory::MANAGEABLE, true); ?>
<section class="card section-sm">
  <h2 class="h3 card-title"><?= icon('key-round') ?> แก้ไขบัญชี #<?= (int) $editing['id'] ?> <?= Format::badge('account', $editing['status']) ?></h2>
  <form method="post" action="<?= e(url('admin/actions/inventory.php')) ?>" class="form" autocomplete="off">
    <?= Csrf::field() ?>
    <input type="hidden" name="op" value="update">
    <input type="hidden" name="product_id" value="<?= $productId ?>">
    <input type="hidden" name="account_id" value="<?= (int) $editing['id'] ?>">
    <?php if ($loginEditable && $vaultReady): ?>
      <p class="hint">ข้อมูลเข้าสู่ระบบ: เว้นว่างช่องที่ไม่ต้องการเปลี่ยน (ระบบจะไม่แสดงค่าเดิม)</p>
      <div class="form-grid">
        <div class="field"><label for="e-user">ชื่อผู้ใช้/ไอดีใหม่</label><input id="e-user" name="username" maxlength="190" autocomplete="off"></div>
        <div class="field"><label for="e-pass">รหัสผ่านใหม่</label><input id="e-pass" name="password" type="password" maxlength="200" autocomplete="new-password"></div>
        <div class="field"><label for="e-email">อีเมลของบัญชีเกมใหม่</label><input id="e-email" name="email" type="email" maxlength="190" autocomplete="off"></div>
        <div class="field"><label for="e-notes">ข้อมูลเพิ่มเติมสำหรับผู้ซื้อใหม่</label><input id="e-notes" name="notes" maxlength="2000" autocomplete="off"></div>
      </div>
      <div class="check-row"><label><input type="checkbox" name="clear_optional" value="1"> ล้างอีเมลและข้อมูลเพิ่มเติมเดิม</label></div>
    <?php elseif (!$loginEditable): ?>
      <p class="hint">บัญชีที่ถูกจองหรือขายแล้ว แก้ไขได้เฉพาะหมายเหตุ</p>
    <?php endif; ?>
    <div class="form-grid">
      <div class="field"><label for="e-public">หมายเหตุสาธารณะ</label><input id="e-public" name="public_note" maxlength="255" value="<?= e($editing['public_note'] ?? '') ?>"></div>
      <div class="field"><label for="e-internal">หมายเหตุภายใน (ทีมงานเห็นเท่านั้น)</label><input id="e-internal" name="internal_note" maxlength="500" value="<?= e($editing['internal_note'] ?? '') ?>"></div>
    </div>
    <div class="form-actions">
      <button class="btn btn-primary" type="submit">บันทึก</button>
      <a class="btn btn-secondary" href="<?= e($base) ?>">ยกเลิก</a>
    </div>
  </form>
</section>
<?php endif; ?>

<?php if ($canManage && $vaultReady && !$editing): ?>
<div class="grid-2 section-sm">
  <section class="card">
    <h2 class="h3 card-title"><?= icon('key-round') ?> เพิ่มบัญชีเกม</h2>
    <form method="post" action="<?= e(url('admin/actions/inventory.php')) ?>" class="form" autocomplete="off">
      <?= Csrf::field() ?>
      <input type="hidden" name="op" value="add">
      <input type="hidden" name="product_id" value="<?= $productId ?>">
      <div class="field"><label for="a-user">ชื่อผู้ใช้ / ไอดี</label><input id="a-user" name="username" required maxlength="190" autocomplete="off"></div>
      <div class="field">
        <label for="a-pass">รหัสผ่าน</label>
        <div class="input-group">
          <input id="a-pass" name="password" type="password" required maxlength="200" autocomplete="new-password">
          <button type="button" class="input-addon" data-toggle-password="a-pass" aria-label="แสดงหรือซ่อนรหัสผ่าน" aria-pressed="false"><?= icon('eye', 'icon icon-eye') ?><?= icon('eye-off', 'icon icon-eye-off') ?></button>
        </div>
      </div>
      <div class="field"><label for="a-email">อีเมลที่ผูกกับบัญชีเกม <span class="muted">(ไม่บังคับ)</span></label><input id="a-email" name="email" type="email" maxlength="190" autocomplete="off"></div>
      <div class="field"><label for="a-notes">ข้อมูลเพิ่มเติมสำหรับผู้ซื้อ <span class="muted">(เข้ารหัส)</span></label><textarea id="a-notes" name="notes" maxlength="2000" rows="3" placeholder="เช่น รหัสกู้คืน, คำถามความปลอดภัย"></textarea></div>
      <div class="field"><label for="a-public">หมายเหตุสาธารณะ <span class="muted">(ไม่เข้ารหัส ห้ามใส่ข้อมูลลับ)</span></label><input id="a-public" name="public_note" maxlength="255"></div>
      <div class="field"><label for="a-internal">หมายเหตุภายใน</label><input id="a-internal" name="internal_note" maxlength="500" placeholder="เช่น แหล่งที่มา/หลักฐานสิทธิ์จำหน่าย"></div>
      <button class="btn btn-primary" type="submit">เข้ารหัสและบันทึก</button>
    </form>
  </section>
  <section class="card">
    <h2 class="h3 card-title"><?= icon('upload') ?> เพิ่มหลายบัญชี</h2>
    <form method="post" action="<?= e(url('admin/actions/inventory.php')) ?>" class="form" autocomplete="off">
      <?= Csrf::field() ?>
      <input type="hidden" name="op" value="bulk_add">
      <input type="hidden" name="product_id" value="<?= $productId ?>">
      <div class="field">
        <label for="b-bulk">บรรทัดละ 1 บัญชี (สูงสุด 100)</label>
        <textarea id="b-bulk" name="bulk" class="mono" rows="9" required spellcheck="false" autocomplete="off" placeholder="ชื่อผู้ใช้ | รหัสผ่าน | อีเมล(ไม่บังคับ) | ข้อมูลเพิ่มเติม(ไม่บังคับ)"></textarea>
        <span class="hint">คั่นด้วย | · ช่องว่างหน้า-หลังแต่ละช่องจะถูกตัด · ถ้ารหัสผ่านมี | หรือขึ้นต้น/ลงท้ายด้วยช่องว่าง ให้เพิ่มทีละบัญชี · ถ้ามีบรรทัดผิดจะไม่บันทึกเลยทั้งชุด</span>
      </div>
      <div class="field"><label for="b-internal">หมายเหตุภายใน (ใช้กับทุกบัญชี)</label><input id="b-internal" name="internal_note" maxlength="500"></div>
      <button class="btn btn-primary" type="submit">เข้ารหัสและบันทึกทั้งหมด</button>
    </form>
  </section>
</div>
<?php endif; ?>

<section class="card section-sm">
  <div class="page-head-row">
    <h2 class="h3">บัญชีเกม (<?= number_format($total) ?>)</h2>
    <div class="chip-row">
      <a class="chip-link<?= $status === '' ? ' is-active' : '' ?>" href="<?= e($base) ?>">ทั้งหมด</a>
      <?php foreach ($statusOptions as $k => $label): ?>
        <a class="chip-link<?= $status === $k ? ' is-active' : '' ?>" href="<?= e($base . '&status=' . $k) ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php if ($accounts): ?>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>#</th><th>สถานะ</th><th>คำสั่งซื้อ</th><th>หมายเหตุ</th><th>เพิ่มเมื่อ</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($accounts as $a): $manageable = in_array($a['status'], Inventory::MANAGEABLE, true); ?>
        <tr>
          <td class="mono"><?= (int) $a['id'] ?></td>
          <td>
            <?= Format::badge('account', $a['status']) ?>
            <?php if ($a['secret_purged_at']): ?><br><span class="muted small">ลบข้อมูลลับแล้ว</span><?php endif; ?>
            <?php if ($a['status'] === 'disabled' && $a['disabled_reason']): ?><br><span class="muted small"><?= e($a['disabled_reason']) ?></span><?php endif; ?>
          </td>
          <td class="mono small">
            <?php if ($a['reserved_order']): ?>จอง: <?= e($a['reserved_order']) ?><br><span class="muted">ถึง <?= e(format_datetime($a['reserved_until'])) ?></span><?php endif; ?>
            <?php if ($a['sold_order']): ?>ขาย: <?= e($a['sold_order']) ?><br><span class="muted"><?= e(format_datetime($a['sold_at'])) ?></span><?php endif; ?>
            <?php if (!$a['reserved_order'] && !$a['sold_order']): ?>—<?php endif; ?>
          </td>
          <td class="small">
            <?php if ($a['public_note']): ?><span><?= e($a['public_note']) ?></span><br><?php endif; ?>
            <?php if ($a['internal_note']): ?><span class="muted"><?= e($a['internal_note']) ?></span><?php endif; ?>
          </td>
          <td class="nowrap small"><?= e(format_datetime($a['created_at'])) ?></td>
          <td><div class="actions">
            <?php if ($canReveal && $vaultReady && !$a['secret_purged_at']): ?>
              <a class="btn btn-secondary btn-xs" href="<?= e(url('admin/inventory-reveal.php') . '?id=' . (int) $a['id']) ?>"><?= icon('eye') ?> ดูข้อมูลลับ</a>
            <?php endif; ?>
            <?php if ($canManage): ?>
              <a class="btn btn-secondary btn-xs" href="<?= e($base . '&edit=' . (int) $a['id']) ?>">แก้ไข</a>
              <?php if ($a['status'] === 'available'): ?>
                <form method="post" action="<?= e(url('admin/actions/inventory.php')) ?>" data-confirm="ปิดใช้งานบัญชี #<?= (int) $a['id'] ?>?">
                  <?= Csrf::field() ?><input type="hidden" name="op" value="disable"><input type="hidden" name="product_id" value="<?= $productId ?>"><input type="hidden" name="account_id" value="<?= (int) $a['id'] ?>">
                  <button class="btn btn-secondary btn-xs" type="submit">ปิดใช้งาน</button>
                </form>
              <?php elseif ($a['status'] === 'disabled'): ?>
                <form method="post" action="<?= e(url('admin/actions/inventory.php')) ?>">
                  <?= Csrf::field() ?><input type="hidden" name="op" value="enable"><input type="hidden" name="product_id" value="<?= $productId ?>"><input type="hidden" name="account_id" value="<?= (int) $a['id'] ?>">
                  <button class="btn btn-secondary btn-xs" type="submit">เปิดขาย</button>
                </form>
              <?php endif; ?>
              <?php if ($manageable): ?>
                <form method="post" action="<?= e(url('admin/actions/inventory.php')) ?>" data-confirm="ลบบัญชี #<?= (int) $a['id'] ?> ถาวร?">
                  <?= Csrf::field() ?><input type="hidden" name="op" value="delete"><input type="hidden" name="product_id" value="<?= $productId ?>"><input type="hidden" name="account_id" value="<?= (int) $a['id'] ?>">
                  <button class="btn btn-ghost-danger btn-xs" type="submit">ลบ</button>
                </form>
              <?php endif; ?>
            <?php endif; ?>
          </div></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
    <p class="muted">ยังไม่มีบัญชีเกม<?= $status !== '' ? 'ในสถานะนี้' : '' ?></p>
  <?php endif; ?>
</section>
<?php
  $pager = ['page' => $page, 'pages' => $pages];
  $pagerPath = 'admin/inventory.php';
  $pagerParams = ['product' => $productId, 'status' => $status];
  require LNW_ROOT . '/includes/layout/pagination.php';
?>
<?php require __DIR__ . '/includes/footer.php'; ?>