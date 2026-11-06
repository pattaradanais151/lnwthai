<?php
/** Path: admin/catalog.php — จัดการเกม (game.manage) และหมวดหมู่ (category.manage) */
declare(strict_types=1);
require __DIR__ . '/includes/guard.php';

admin_require_any(['game.manage', 'category.manage']);
$canGames = admin_can('game.manage');
$canCats = admin_can('category.manage');
$tab = (($_GET['tab'] ?? '') === 'categories' && $canCats) || !$canGames ? 'categories' : 'games';
$editId = admin_int($_GET['edit'] ?? 0);
$old = admin_take_old();
$mediaOk = MediaService::available();

$games = DB::all(
    'SELECT g.*, (SELECT COUNT(*) FROM products p WHERE p.game_id = g.id) AS product_count
     FROM games g ORDER BY g.sort_order, g.name'
);
$categories = [];
$editing = null;
if ($tab === 'games' && $editId > 0) {
    $editing = DB::one('SELECT * FROM games WHERE id = ?', [$editId]) ?? render_error(404);
}
if ($tab === 'categories') {
    $categories = DB::all(
        'SELECT c.*, g.name AS game_name, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS product_count
         FROM categories c LEFT JOIN games g ON g.id = c.game_id
         ORDER BY g.id IS NULL, g.sort_order, g.name, c.sort_order, c.name'
    );
    if ($editId > 0) {
        $editing = DB::one('SELECT * FROM categories WHERE id = ?', [$editId]) ?? render_error(404);
    }
}

$val = static function (string $k, string $default = '') use ($old, $editing): string {
    if ($old !== null) {
        return is_string($old[$k] ?? null) ? $old[$k] : '';
    }
    return $editing !== null && isset($editing[$k]) ? (string) $editing[$k] : $default;
};
$chk = static function (string $k, bool $default) use ($old, $editing): bool {
    if ($old !== null) {
        return ($old[$k] ?? '') === '1';
    }
    return $editing !== null ? (int) $editing[$k] === 1 : $default;
};

$pageTitle = 'เกมและหมวดหมู่';
$activeNav = 'catalog';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head"><h1>เกมและหมวดหมู่</h1></div>

<nav class="tabs" aria-label="ประเภทข้อมูล">
  <?php if ($canGames): ?><a class="tab<?= $tab === 'games' ? ' is-active' : '' ?>" href="<?= e(url('admin/catalog.php')) ?>"><?= icon('gamepad-2') ?><span>เกม</span></a><?php endif; ?>
  <?php if ($canCats): ?><a class="tab<?= $tab === 'categories' ? ' is-active' : '' ?>" href="<?= e(url('admin/catalog.php') . '?tab=categories') ?>"><?= icon('tag') ?><span>หมวดหมู่</span></a><?php endif; ?>
</nav>

<?php if ($tab === 'games'): ?>
<div class="admin-split">
  <section class="card">
    <h2 class="h3"><?= $editing ? 'แก้ไขเกม' : 'เพิ่มเกม' ?></h2>
    <form method="post" action="<?= e(url('admin/actions/catalog.php')) ?>" class="form" enctype="multipart/form-data">
      <?= Csrf::field() ?>
      <input type="hidden" name="op" value="game_save">
      <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
      <div class="field"><label for="g-name">ชื่อเกม</label><input id="g-name" name="name" required maxlength="120" value="<?= e($val('name')) ?>"></div>
      <div class="field"><label for="g-name-en">ชื่อภาษาอังกฤษ</label><input id="g-name-en" name="name_en" maxlength="120" value="<?= e($val('name_en')) ?>"></div>
      <div class="field"><label for="g-slug">Slug (URL)</label><input id="g-slug" name="slug" maxlength="120" pattern="[a-z0-9-]*" value="<?= e($val('slug')) ?>"><span class="hint">เว้นว่างเพื่อสร้างจากชื่อภาษาอังกฤษอัตโนมัติ</span></div>
      <div class="field"><label for="g-short">คำอธิบายสั้น</label><input id="g-short" name="short_description" maxlength="255" value="<?= e($val('short_description')) ?>"></div>
      <div class="field"><label for="g-sort">ลำดับการแสดง</label><input id="g-sort" name="sort_order" type="number" min="-9999" max="9999" value="<?= e($val('sort_order', '0')) ?>"></div>
      <div class="field">
        <label for="g-icon">ไอคอน (JPG/PNG/WEBP)</label>
        <?php if ($mediaOk): ?>
          <input id="g-icon" name="icon" type="file" accept="image/jpeg,image/png,image/webp">
        <?php else: ?>
          <span class="hint">ยังไม่ได้ตั้งค่า Supabase จึงอัปโหลดไอคอนไม่ได้</span>
        <?php endif; ?>
        <?php if ($editing && Format::imageUrl($editing['icon_path'])): ?>
          <div class="check-row"><img class="thumb" src="<?= e(Format::imageUrl($editing['icon_path'])) ?>" alt=""><label><input type="checkbox" name="remove_icon" value="1"> ลบไอคอนเดิม</label></div>
        <?php endif; ?>
      </div>
      <div class="check-row">
        <label><input type="checkbox" name="is_active" value="1"<?= $chk('is_active', true) ? ' checked' : '' ?>> แสดงในร้าน</label>
        <label><input type="checkbox" name="is_featured" value="1"<?= $chk('is_featured', false) ? ' checked' : '' ?>> เกมยอดนิยม (หน้าแรก)</label>
      </div>
      <div class="form-actions">
        <button class="btn btn-primary" type="submit"><?= $editing ? 'บันทึก' : 'เพิ่มเกม' ?></button>
        <?php if ($editing): ?><a class="btn btn-secondary" href="<?= e(url('admin/catalog.php')) ?>">ยกเลิก</a><?php endif; ?>
      </div>
    </form>
  </section>

  <section class="card">
    <h2 class="h3">เกมทั้งหมด (<?= count($games) ?>)</h2>
    <?php if (!$games): ?>
      <p class="muted">ยังไม่มีเกม เพิ่มเกมแรกได้จากฟอร์มด้านซ้าย</p>
    <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>เกม</th><th>สินค้า</th><th>สถานะ</th><th>ลำดับ</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($games as $g): $gi = Format::imageUrl($g['icon_path']); ?>
          <tr>
            <td><div class="cell-title">
              <?php if ($gi): ?><img class="thumb" src="<?= e($gi) ?>" alt="" loading="lazy"><?php else: ?><span class="thumb-ph"><?= e(Format::initials($g['name'])) ?></span><?php endif; ?>
              <div><strong><?= e($g['name']) ?></strong><span class="muted small mono"><?= e($g['slug']) ?></span></div>
            </div></td>
            <td><?= (int) $g['product_count'] ?></td>
            <td>
              <?= (int) $g['is_active'] === 1 ? '<span class="badge badge-ok">แสดง</span>' : '<span class="badge badge-neutral">ซ่อน</span>' ?>
              <?php if ((int) $g['is_featured'] === 1): ?><span class="badge badge-info">ยอดนิยม</span><?php endif; ?>
            </td>
            <td><?= (int) $g['sort_order'] ?></td>
            <td><div class="actions">
              <a class="btn btn-secondary btn-xs" href="<?= e(url('admin/catalog.php') . '?edit=' . (int) $g['id']) ?>">แก้ไข</a>
              <?php if ((int) $g['product_count'] === 0): ?>
                <form method="post" action="<?= e(url('admin/actions/catalog.php')) ?>" data-confirm="ลบเกม “<?= e($g['name']) ?>” และหมวดหมู่ของเกมนี้?">
                  <?= Csrf::field() ?><input type="hidden" name="op" value="game_delete"><input type="hidden" name="id" value="<?= (int) $g['id'] ?>">
                  <button class="btn btn-ghost-danger btn-xs" type="submit">ลบ</button>
                </form>
              <?php endif; ?>
            </div></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="hint">ลบได้เฉพาะเกมที่ไม่มีสินค้า — ถ้ามีสินค้าให้ซ่อนเกมแทน</p>
    <?php endif; ?>
  </section>
</div>

<?php else: ?>
<div class="admin-split">
  <section class="card">
    <h2 class="h3"><?= $editing ? 'แก้ไขหมวดหมู่' : 'เพิ่มหมวดหมู่' ?></h2>
    <form method="post" action="<?= e(url('admin/actions/catalog.php')) ?>" class="form">
      <?= Csrf::field() ?>
      <input type="hidden" name="op" value="category_save">
      <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
      <div class="field">
        <label for="c-game">เกม</label>
        <select id="c-game" name="game_id">
          <option value="">ใช้ได้ทุกเกม</option>
          <?php foreach ($games as $g): ?>
            <option value="<?= (int) $g['id'] ?>"<?= $val('game_id') === (string) $g['id'] ? ' selected' : '' ?>><?= e($g['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field"><label for="c-name">ชื่อหมวดหมู่</label><input id="c-name" name="name" required maxlength="120" value="<?= e($val('name')) ?>"></div>
      <div class="field"><label for="c-name-en">ชื่อภาษาอังกฤษ</label><input id="c-name-en" name="name_en" maxlength="120" value="<?= e($val('name_en')) ?>"></div>
      <div class="field"><label for="c-slug">Slug</label><input id="c-slug" name="slug" maxlength="120" pattern="[a-z0-9-]*" value="<?= e($val('slug')) ?>"></div>
      <div class="field"><label for="c-desc">คำอธิบาย</label><input id="c-desc" name="description" maxlength="255" value="<?= e($val('description')) ?>"></div>
      <div class="field"><label for="c-sort">ลำดับ</label><input id="c-sort" name="sort_order" type="number" min="-9999" max="9999" value="<?= e($val('sort_order', '0')) ?>"></div>
      <div class="check-row"><label><input type="checkbox" name="is_active" value="1"<?= $chk('is_active', true) ? ' checked' : '' ?>> ใช้งาน</label></div>
      <div class="form-actions">
        <button class="btn btn-primary" type="submit"><?= $editing ? 'บันทึก' : 'เพิ่มหมวดหมู่' ?></button>
        <?php if ($editing): ?><a class="btn btn-secondary" href="<?= e(url('admin/catalog.php') . '?tab=categories') ?>">ยกเลิก</a><?php endif; ?>
      </div>
    </form>
  </section>

  <section class="card">
    <h2 class="h3">หมวดหมู่ทั้งหมด (<?= count($categories) ?>)</h2>
    <?php if (!$categories): ?>
      <p class="muted">ยังไม่มีหมวดหมู่</p>
    <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>หมวดหมู่</th><th>เกม</th><th>สินค้า</th><th>สถานะ</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($categories as $c): ?>
          <tr>
            <td><strong><?= e($c['name']) ?></strong><br><span class="muted small mono"><?= e($c['slug']) ?></span></td>
            <td><?= e($c['game_name'] ?? 'ทุกเกม') ?></td>
            <td><?= (int) $c['product_count'] ?></td>
            <td><?= (int) $c['is_active'] === 1 ? '<span class="badge badge-ok">ใช้งาน</span>' : '<span class="badge badge-neutral">ปิด</span>' ?></td>
            <td><div class="actions">
              <a class="btn btn-secondary btn-xs" href="<?= e(url('admin/catalog.php') . '?tab=categories&edit=' . (int) $c['id']) ?>">แก้ไข</a>
              <form method="post" action="<?= e(url('admin/actions/catalog.php')) ?>" data-confirm="ลบหมวดหมู่ “<?= e($c['name']) ?>”? สินค้าในหมวดนี้จะไม่มีหมวดหมู่">
                <?= Csrf::field() ?><input type="hidden" name="op" value="category_delete"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                <button class="btn btn-ghost-danger btn-xs" type="submit">ลบ</button>
              </form>
            </div></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </section>
</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>