<?php
/** Path: admin/product-edit.php — เพิ่ม (product.create) / แก้ไข (product.edit) สินค้า + จัดการรูปภาพ */
declare(strict_types=1);
require __DIR__ . '/includes/guard.php';

$id = admin_int($_GET['id'] ?? 0);
admin_require($id > 0 ? 'product.edit' : 'product.create');

$product = $id > 0 ? (DB::one('SELECT * FROM products WHERE id = ?', [$id]) ?? render_error(404, 'ไม่พบสินค้า')) : null;
$games = DB::all('SELECT id, name, is_active FROM games ORDER BY sort_order, name');
$categories = DB::all(
    'SELECT c.id, c.name, c.game_id, g.name AS game_name FROM categories c LEFT JOIN games g ON g.id = c.game_id
     ORDER BY g.id IS NULL, g.sort_order, g.name, c.sort_order, c.name'
);
$images = $product ? DB::all('SELECT id, public_url, alt_text, is_primary FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, sort_order, id', [$id]) : [];
$counts = $product ? Inventory::counts($id) : null;
$hasOrders = $product !== null && DB::value('SELECT 1 FROM order_items WHERE product_id = ? LIMIT 1', [$id]) !== null;
$mediaOk = MediaService::available();
$old = admin_take_old();

$val = static function (string $k, string $default = '') use ($old, $product): string {
    if ($old !== null) {
        return is_string($old[$k] ?? null) ? $old[$k] : '';
    }
    return $product !== null && isset($product[$k]) ? (string) $product[$k] : $default;
};
$chk = static function (string $k) use ($old, $product): bool {
    if ($old !== null) {
        return ($old[$k] ?? '') === '1';
    }
    return $product !== null && (int) $product[$k] === 1;
};
$attrText = $old !== null ? (string) ($old['attributes_text'] ?? '') : Str::attributesToText($product['attributes'] ?? null);
$catGroups = [];
foreach ($categories as $c) {
    $catGroups[$c['game_name'] ?? 'ใช้ได้ทุกเกม'][] = $c;
}

$pageTitle = $product ? 'แก้ไขสินค้า' : 'เพิ่มสินค้า';
$activeNav = 'products';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head page-head-row">
  <div>
    <nav class="breadcrumb" aria-label="breadcrumb"><ol>
      <li><a href="<?= e(url('admin/products.php')) ?>">สินค้า</a></li>
      <li aria-current="page"><?= $product ? e($product['sku']) : 'เพิ่มสินค้าใหม่' ?></li>
    </ol></nav>
    <h1><?= $product ? e($product['title']) : 'เพิ่มสินค้าใหม่' ?></h1>
  </div>
  <?php if ($product): ?>
    <div class="actions">
      <?= Format::badge('product', $product['status']) ?>
      <?php if (admin_can('inventory.view')): ?>
        <a class="btn btn-secondary btn-sm" href="<?= e(url('admin/inventory.php') . '?product=' . $id) ?>"><?= icon('key-round') ?> คลังบัญชี (ว่าง <?= (int) $counts['available'] ?>)</a>
      <?php endif; ?>
      <?php if (in_array($product['status'], ['active', 'sold_out'], true)): ?>
        <a class="btn btn-secondary btn-sm" href="<?= e(url('product/' . $product['slug'])) ?>" target="_blank" rel="noopener">ดูหน้าร้าน</a>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<?php if (!$games): ?>
  <div class="alert alert-warning"><?= icon('alert-triangle') ?><span>ยังไม่มีเกมในระบบ — <a href="<?= e(url('admin/catalog.php')) ?>">เพิ่มเกมก่อน</a></span></div>
<?php endif; ?>

<form class="card form" method="post" action="<?= e(url('admin/actions/products.php')) ?>">
  <?= Csrf::field() ?>
  <input type="hidden" name="op" value="save">
  <input type="hidden" name="id" value="<?= $id ?>">
  <div class="form-grid">
    <div class="field">
      <label for="p-game">เกม</label>
      <select id="p-game" name="game_id" required>
        <option value="">— เลือกเกม —</option>
        <?php foreach ($games as $g): ?>
          <option value="<?= (int) $g['id'] ?>"<?= $val('game_id') === (string) $g['id'] ? ' selected' : '' ?>><?= e($g['name']) ?><?= (int) $g['is_active'] === 1 ? '' : ' (ซ่อน)' ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="p-cat">หมวดหมู่ <span class="muted">(ไม่บังคับ)</span></label>
      <select id="p-cat" name="category_id">
        <option value="">— ไม่มี —</option>
        <?php foreach ($catGroups as $group => $cats): ?>
          <optgroup label="<?= e($group) ?>">
            <?php foreach ($cats as $c): ?><option value="<?= (int) $c['id'] ?>"<?= $val('category_id') === (string) $c['id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
          </optgroup>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field span-2"><label for="p-title">ชื่อสินค้า</label><input id="p-title" name="title" required minlength="2" maxlength="200" value="<?= e($val('title')) ?>"></div>
    <div class="field"><label for="p-title-en">ชื่อภาษาอังกฤษ</label><input id="p-title-en" name="title_en" maxlength="200" value="<?= e($val('title_en')) ?>"></div>
    <div class="field"><label for="p-sku">รหัสสินค้า (SKU)</label><input id="p-sku" name="sku" required maxlength="40" pattern="[A-Za-z0-9_\-]{2,40}" value="<?= e($val('sku')) ?>"></div>
    <div class="field"><label for="p-slug">Slug (URL)</label><input id="p-slug" name="slug" maxlength="180" pattern="[a-z0-9\-]*" value="<?= e($val('slug')) ?>"><span class="hint">เว้นว่างเพื่อสร้างจากชื่อภาษาอังกฤษหรือ SKU</span></div>
    <div class="field"><label for="p-price">ราคาขาย (บาท)</label><input id="p-price" name="price" required inputmode="decimal" maxlength="14" value="<?= e($val('price')) ?>"></div>
    <div class="field"><label for="p-compare">ราคาก่อนลด <span class="muted">(ไม่บังคับ)</span></label><input id="p-compare" name="compare_at_price" inputmode="decimal" maxlength="14" value="<?= e($val('compare_at_price')) ?>"></div>
    <div class="field"><label for="p-platform">แพลตฟอร์ม</label><input id="p-platform" name="platform" maxlength="50" placeholder="Mobile / PC / Console" value="<?= e($val('platform')) ?>"></div>
    <div class="field"><label for="p-region">เซิร์ฟเวอร์ / ภูมิภาค</label><input id="p-region" name="server_region" maxlength="100" value="<?= e($val('server_region')) ?>"></div>
    <div class="field"><label for="p-level">เลเวล / แรงก์</label><input id="p-level" name="level_rank" maxlength="100" value="<?= e($val('level_rank')) ?>"></div>
    <div class="field span-2"><label for="p-short">คำอธิบายสั้น</label><input id="p-short" name="short_description" maxlength="300" value="<?= e($val('short_description')) ?>"></div>
    <div class="field span-2"><label for="p-desc">รายละเอียดสินค้า</label><textarea id="p-desc" name="description" maxlength="20000" rows="6"><?= e($val('description')) ?></textarea></div>
    <div class="field span-2"><label for="p-items">สกิน / ไอเทม / ตัวละคร</label><textarea id="p-items" name="items_summary" maxlength="5000" rows="4"><?= e($val('items_summary')) ?></textarea><span class="hint">บรรทัดละรายการ</span></div>
    <div class="field span-2"><label for="p-attrs">รายละเอียดเพิ่มเติม</label><textarea id="p-attrs" name="attributes_text" class="mono" rows="4" placeholder="ผูกอีเมล: โอนได้&#10;ประวัติการแบน: ไม่มี"><?= e($attrText) ?></textarea><span class="hint">รูปแบบ "หัวข้อ: ค่า" บรรทัดละรายการ สูงสุด 30 บรรทัด · ห้ามใส่รหัสผ่านหรือข้อมูลลับ</span></div>
    <div class="field">
      <label for="p-status">สถานะ</label>
      <select id="p-status" name="status">
        <?php foreach (Format::options('product') as $k => $label): ?><option value="<?= e($k) ?>"<?= $val('status', 'draft') === $k ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
      </select>
      <span class="hint">ระบบสลับ "เปิดขาย ↔ สินค้าหมด" อัตโนมัติตามจำนวนบัญชีที่พร้อมขาย</span>
    </div>
    <div class="field">
      <span class="filter-title">การแสดงผล</span>
      <div class="check-row">
        <label><input type="checkbox" name="is_featured" value="1"<?= $chk('is_featured') ? ' checked' : '' ?>> สินค้าแนะนำ</label>
        <label><input type="checkbox" name="is_special_offer" value="1"<?= $chk('is_special_offer') ? ' checked' : '' ?>> ข้อเสนอพิเศษ</label>
      </div>
    </div>
  </div>
  <div class="form-actions">
    <button class="btn btn-primary" type="submit"><?= $product ? 'บันทึกการแก้ไข' : 'สร้างสินค้า' ?></button>
    <a class="btn btn-secondary" href="<?= e(url('admin/products.php')) ?>">กลับ</a>
  </div>
</form>

<?php if ($product): ?>
<section class="card section-sm">
  <h2 class="h3 card-title"><?= icon('upload') ?> รูปภาพสินค้า (<?= count($images) ?>/10)</h2>
  <?php if ($images): ?>
    <div class="image-grid">
      <?php foreach ($images as $img): $src = Format::imageUrl($img['public_url']); if ($src === null) { continue; } ?>
        <div class="image-item">
          <img src="<?= e($src) ?>" alt="<?= e($img['alt_text'] ?? '') ?>" loading="lazy">
          <div class="actions">
            <?php if ((int) $img['is_primary'] === 1): ?>
              <span class="badge badge-ok">รูปหลัก</span>
            <?php else: ?>
              <form method="post" action="<?= e(url('admin/actions/products.php')) ?>">
                <?= Csrf::field() ?><input type="hidden" name="op" value="image_primary"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="image_id" value="<?= (int) $img['id'] ?>">
                <button class="btn btn-secondary btn-xs" type="submit">ตั้งเป็นรูปหลัก</button>
              </form>
            <?php endif; ?>
            <form method="post" action="<?= e(url('admin/actions/products.php')) ?>" data-confirm="ลบรูปนี้?">
              <?= Csrf::field() ?><input type="hidden" name="op" value="image_delete"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="image_id" value="<?= (int) $img['id'] ?>">
              <button class="btn btn-ghost-danger btn-xs" type="submit">ลบ</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <?php if (!$mediaOk): ?>
    <p class="muted">ยังไม่ได้ตั้งค่า Supabase จึงอัปโหลดรูปไม่ได้</p>
  <?php elseif (count($images) < 10): ?>
    <form method="post" action="<?= e(url('admin/actions/products.php')) ?>" enctype="multipart/form-data" class="form">
      <?= Csrf::field() ?><input type="hidden" name="op" value="image_upload"><input type="hidden" name="id" value="<?= $id ?>">
      <div class="field">
        <label for="p-images">เพิ่มรูปภาพ (ครั้งละไม่เกิน 5 รูป · JPG/PNG/WEBP · รูปละไม่เกิน <?= round((int) config('upload.max_image_bytes', 5242880) / 1048576, 1) ?> MB)</label>
        <input id="p-images" name="images[]" type="file" accept="image/jpeg,image/png,image/webp" multiple required>
        <span class="hint">ห้ามใช้รูปที่เห็นชื่อผู้ใช้ อีเมล หรือข้อมูลส่วนตัวของบัญชี</span>
      </div>
      <div class="form-actions"><button class="btn btn-secondary" type="submit">อัปโหลด</button></div>
    </form>
  <?php endif; ?>
</section>

<?php if (admin_can('product.delete')): ?>
<section class="card section-sm danger-zone">
  <h2 class="h3 card-title"><?= icon('alert-triangle') ?> ลบสินค้า</h2>
  <p class="muted small">
    <?= $hasOrders || $counts['reserved'] + $counts['sold'] > 0
        ? 'สินค้านี้มีประวัติคำสั่งซื้อ ระบบจะ "เก็บถาวร" แทนการลบ เพื่อให้ตรวจสอบย้อนหลังได้'
        : 'จะลบสินค้า รูปภาพ และบัญชีเกมที่ยังไม่ถูกขายทั้งหมดของสินค้านี้ถาวร' ?>
  </p>
  <form method="post" action="<?= e(url('admin/actions/products.php')) ?>" data-confirm="ยืนยันการลบ/เก็บถาวรสินค้า <?= e($product['sku']) ?>?">
    <?= Csrf::field() ?><input type="hidden" name="op" value="delete"><input type="hidden" name="id" value="<?= $id ?>">
    <button class="btn btn-danger" type="submit"><?= $hasOrders ? 'เก็บถาวร' : 'ลบสินค้า' ?></button>
  </form>
</section>
<?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>