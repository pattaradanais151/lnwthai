<?php
/** Path: admin/actions/products.php — บันทึก/ลบ สินค้า และจัดการรูปภาพ */
declare(strict_types=1);
require dirname(__DIR__) . '/includes/guard.php';

require_post();
$op = post_str('op');
$id = admin_int($_POST['id'] ?? 0);
$perm = match ($op) {
    'save' => $id > 0 ? 'product.edit' : 'product.create',
    'delete' => 'product.delete',
    'image_upload', 'image_delete', 'image_primary' => 'product.edit',
    default => null,
};
if ($perm === null) {
    render_error(400);
}
$me = admin_action($perm);
$uid = (int) $me['id'];
$editUrl = static fn(int $pid): string => url('admin/product-edit.php') . '?id=' . $pid;
$nz = static fn(string $s): ?string => $s === '' ? null : $s;

// ------------------------------------------------------------------ save
if ($op === 'save') {
    $product = $id > 0 ? (DB::one('SELECT * FROM products WHERE id = ?', [$id]) ?? render_error(404)) : null;
    $in = [
        'game_id' => post_str('game_id'), 'category_id' => post_str('category_id'),
        'sku' => strtoupper(post_str('sku')), 'slug' => strtolower(post_str('slug')),
        'title' => post_str('title'), 'title_en' => post_str('title_en'),
        'short_description' => post_str('short_description'), 'description' => post_str('description'),
        'platform' => post_str('platform'), 'server_region' => post_str('server_region'), 'level_rank' => post_str('level_rank'),
        'items_summary' => post_str('items_summary'), 'attributes_text' => post_str('attributes_text'),
        'price' => post_str('price'), 'compare_at_price' => post_str('compare_at_price'), 'status' => post_str('status'),
        'is_featured' => isset($_POST['is_featured']) ? '1' : '', 'is_special_offer' => isset($_POST['is_special_offer']) ? '1' : '',
    ];
    $back = $id > 0 ? $editUrl($id) : url('admin/product-edit.php');
    $fail = static function (string $msg) use ($in, $back): never {
        set_old($in);
        flash('error', $msg);
        redirect($back);
    };

    $gameId = admin_int($in['game_id']);
    if ($gameId === 0 || DB::value('SELECT id FROM games WHERE id = ?', [$gameId]) === null) { $fail('กรุณาเลือกเกม'); }
    $catId = admin_int($in['category_id']);
    if ($catId > 0) {
        $cat = DB::one('SELECT game_id FROM categories WHERE id = ?', [$catId]);
        if ($cat === null || ($cat['game_id'] !== null && (int) $cat['game_id'] !== $gameId)) { $fail('หมวดหมู่ไม่ตรงกับเกมที่เลือก'); }
    }
    if (preg_match('/^[A-Z0-9_-]{2,40}$/', $in['sku']) !== 1) { $fail('SKU ต้องเป็น A-Z, 0-9, - หรือ _ ยาว 2–40 ตัว'); }
    if (DB::value('SELECT id FROM products WHERE sku = ? AND id <> ?', [$in['sku'], $id]) !== null) { $fail('SKU นี้ถูกใช้แล้ว'); }
    if (mb_strlen($in['title']) < 2 || mb_strlen($in['title']) > 200) { $fail('ชื่อสินค้าต้องยาว 2–200 ตัวอักษร'); }
    $limits = ['title_en' => [200, 'ชื่อภาษาอังกฤษ'], 'short_description' => [300, 'คำอธิบายสั้น'], 'description' => [20000, 'รายละเอียดสินค้า'],
               'platform' => [50, 'แพลตฟอร์ม'], 'server_region' => [100, 'เซิร์ฟเวอร์'], 'level_rank' => [100, 'เลเวล/แรงก์'], 'items_summary' => [5000, 'รายการไอเทม']];
    foreach ($limits as $k => [$max, $label]) {
        if (mb_strlen($in[$k]) > $max) { $fail("{$label}ยาวเกิน {$max} ตัวอักษร"); }
    }
    $price = Str::money($in['price']);
    if ($price === null || (float) $price <= 0) { $fail('ราคาขายไม่ถูกต้อง'); }
    $compare = null;
    if ($in['compare_at_price'] !== '') {
        $compare = Str::money($in['compare_at_price']);
        if ($compare === null || (float) $compare <= (float) $price) { $fail('ราคาก่อนลดต้องมากกว่าราคาขาย (หรือเว้นว่าง)'); }
    }
    if (!array_key_exists($in['status'], Format::options('product'))) { $fail('สถานะไม่ถูกต้อง'); }
    $attrs = Str::parseAttributes($in['attributes_text']);
    if ($attrs === null) { $fail('รายละเอียดเพิ่มเติมต้องเป็น "หัวข้อ: ค่า" บรรทัดละรายการ (สูงสุด 30 บรรทัด)'); }

    $slugBase = Str::slug($in['slug'] !== '' ? $in['slug'] : ($in['title_en'] !== '' ? $in['title_en'] : $in['sku']), 170);
    $slug = Str::uniqueSlug('products', $slugBase, $id ?: null, 'p');

    $data = [
        'game_id' => $gameId, 'category_id' => $catId ?: null, 'sku' => $in['sku'], 'slug' => $slug,
        'title' => $in['title'], 'title_en' => $nz($in['title_en']), 'short_description' => $nz($in['short_description']),
        'description' => $nz($in['description']), 'platform' => $nz($in['platform']), 'server_region' => $nz($in['server_region']),
        'level_rank' => $nz($in['level_rank']), 'items_summary' => $nz($in['items_summary']),
        'attributes' => $attrs ? json_encode($attrs, JSON_UNESCAPED_UNICODE) : null,
        'price' => $price, 'compare_at_price' => $compare, 'status' => $in['status'],
        'is_featured' => $in['is_featured'] === '1' ? 1 : 0, 'is_special_offer' => $in['is_special_offer'] === '1' ? 1 : 0,
    ];

    if ($product === null) {
        $now = now();
        $row = $data + [
            'published_at' => $data['status'] === 'active' ? $now : null,
            'created_by' => $uid, 'updated_by' => $uid, 'created_at' => $now, 'updated_at' => $now,
        ];
        $newId = DB::insert(
            'INSERT INTO products (' . implode(', ', array_keys($row)) . ') VALUES (' . implode(', ', array_fill(0, count($row), '?')) . ')',
            array_values($row)
        );
        Inventory::syncProductStatus($newId);
        Audit::log('product.create', 'product', $newId, 'เพิ่มสินค้า ' . $data['sku'],
            ['title' => $data['title'], 'price' => $price, 'status' => $data['status']]);
        flash('success', 'สร้างสินค้าแล้ว — เพิ่มรูปภาพด้านล่าง และเพิ่มบัญชีเกมในคลังบัญชี');
        redirect($editUrl($newId));
    }

    $changed = [];
    foreach ($data as $k => $v) {
        if ((string) ($product[$k] ?? '') !== (string) ($v ?? '')) {
            $changed[] = $k;
        }
    }
    if ($data['status'] === 'active' && $product['published_at'] === null) {
        $data['published_at'] = now();
    }
    $data['updated_by'] = $uid;
    $sets = implode(', ', array_map(static fn(string $k): string => "{$k} = ?", array_keys($data)));
    DB::run("UPDATE products SET {$sets} WHERE id = ?", [...array_values($data), $id]);
    Inventory::syncProductStatus($id);

    if ($changed) {
        Audit::log('product.update', 'product', $id, 'แก้ไขสินค้า ' . $data['sku'], [
            'changed' => $changed,
            'price_before' => in_array('price', $changed, true) ? $product['price'] : null,
            'price_after' => in_array('price', $changed, true) ? $price : null,
        ]);
    }
    flash('success', $changed ? 'บันทึกการแก้ไขแล้ว' : 'ไม่มีข้อมูลที่เปลี่ยนแปลง');
    if ($in['slug'] !== '' && $slug !== Str::slug($in['slug'], 170)) {
        flash('info', 'Slug ที่ระบุซ้ำกับสินค้าอื่น ระบบใช้ “' . $slug . '” แทน');
    }
    redirect($editUrl($id));
}

// ------------------------------------------------------------------ delete / archive
if ($op === 'delete') {
    $product = DB::one('SELECT id, sku, title FROM products WHERE id = ?', [$id]) ?? render_error(404);
    $images = DB::all('SELECT bucket, storage_path FROM product_images WHERE product_id = ?', [$id]);

    $result = DB::transaction(static function () use ($id, $uid): string {
        DB::one('SELECT id FROM products WHERE id = ? FOR UPDATE', [$id]);
        $locked = DB::value("SELECT 1 FROM game_accounts WHERE product_id = ? AND status IN ('reserved','sold') LIMIT 1 FOR UPDATE", [$id]) !== null;
        $hasOrders = DB::value('SELECT 1 FROM order_items WHERE product_id = ? LIMIT 1', [$id]) !== null;
        if ($locked || $hasOrders) {
            DB::run("UPDATE products SET status = 'archived', is_featured = 0, is_special_offer = 0, updated_by = ? WHERE id = ?", [$uid, $id]);
            return 'archived';
        }
        DB::run("DELETE FROM game_accounts WHERE product_id = ? AND status IN ('available','disabled')", [$id]);
        DB::run('DELETE FROM products WHERE id = ?', [$id]); // product_images: ON DELETE CASCADE
        return 'deleted';
    });

    if ($result === 'archived') {
        Audit::log('product.archive', 'product', $id, 'เก็บถาวรสินค้า ' . $product['sku']);
        flash('info', 'สินค้ามีประวัติคำสั่งซื้อ จึงเก็บถาวรแทนการลบ');
    } else {
        foreach ($images as $img) {
            MediaService::deleteQuietly((string) $img['bucket'], (string) $img['storage_path']);
        }
        Audit::log('product.delete', 'product', $id, 'ลบสินค้า ' . $product['sku'], ['title' => $product['title'], 'images' => count($images)]);
        flash('success', 'ลบสินค้าแล้ว');
    }
    redirect(url('admin/products.php'));
}

// ------------------------------------------------------------------ images
$product = DB::one('SELECT id, sku, title FROM products WHERE id = ?', [$id]) ?? render_error(404);

if ($op === 'image_upload') {
    $files = admin_files($_FILES['images'] ?? null);
    if ($files === []) {
        flash('error', 'กรุณาเลือกรูปภาพ');
        redirect($editUrl($id));
    }
    $room = 10 - (int) DB::value('SELECT COUNT(*) FROM product_images WHERE product_id = ?', [$id]);
    if ($room <= 0) {
        flash('error', 'สินค้านี้มีรูปครบ 10 รูปแล้ว');
        redirect($editUrl($id));
    }
    $files = array_slice($files, 0, min($room, 5));
    $ok = 0;
    $errors = [];
    $sort = (int) DB::value('SELECT COALESCE(MAX(sort_order), 0) FROM product_images WHERE product_id = ?', [$id]);
    foreach ($files as $i => $file) {
        try {
            $up = MediaService::uploadPublicImage($file, 'products/' . $id);
        } catch (InvalidArgumentException | StorageException $e) {
            $errors[] = 'รูปที่ ' . ($i + 1) . ': ' . $e->getMessage();
            continue;
        }
        try {
            $isPrimary = (int) DB::value('SELECT COUNT(*) FROM product_images WHERE product_id = ? AND is_primary = 1', [$id]) === 0 ? 1 : 0;
            DB::insert(
                'INSERT INTO product_images (product_id, bucket, storage_path, public_url, alt_text, sort_order, is_primary,
                                             width, height, size_bytes, mime_type, uploaded_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$id, $up['bucket'], $up['path'], $up['public_url'], mb_substr((string) $product['title'], 0, 200), ++$sort, $isPrimary,
                 $up['width'], $up['height'], $up['size'], $up['mime'], $uid, now()]
            );
            $ok++;
        } catch (Throwable $e) {
            error_log('Product image insert failed: ' . $e->getMessage());
            MediaService::deleteQuietly($up['bucket'], $up['path']);
            $errors[] = 'รูปที่ ' . ($i + 1) . ': บันทึกข้อมูลไม่สำเร็จ';
        }
    }
    if ($ok > 0) {
        Audit::log('product.image_upload', 'product', $id, "อัปโหลดรูปสินค้า {$ok} รูป", ['sku' => $product['sku']]);
        flash('success', "อัปโหลดรูปสำเร็จ {$ok} รูป");
    }
    foreach ($errors as $err) {
        flash('error', $err);
    }
    redirect($editUrl($id));
}

$imageId = admin_int($_POST['image_id'] ?? 0);
$img = DB::one('SELECT id, bucket, storage_path, is_primary FROM product_images WHERE id = ? AND product_id = ?', [$imageId, $id])
    ?? render_error(404);

if ($op === 'image_primary') {
    DB::transaction(static function () use ($id, $imageId): void {
        DB::run('UPDATE product_images SET is_primary = 0 WHERE product_id = ?', [$id]);
        DB::run('UPDATE product_images SET is_primary = 1 WHERE id = ?', [$imageId]);
    });
    Audit::log('product.image_primary', 'product', $id, 'ตั้งรูปหลักของสินค้า', ['image_id' => $imageId]);
    flash('success', 'ตั้งเป็นรูปหลักแล้ว');
    redirect($editUrl($id));
}

if ($op === 'image_delete') {
    DB::transaction(static function () use ($id, $imageId, $img): void {
        DB::run('DELETE FROM product_images WHERE id = ?', [$imageId]);
        if ((int) $img['is_primary'] === 1) {
            DB::run('UPDATE product_images SET is_primary = 1 WHERE product_id = ? ORDER BY sort_order, id LIMIT 1', [$id]);
        }
    });
    MediaService::deleteQuietly((string) $img['bucket'], (string) $img['storage_path']);
    Audit::log('product.image_delete', 'product', $id, 'ลบรูปสินค้า', ['image_id' => $imageId]);
    flash('success', 'ลบรูปแล้ว');
    redirect($editUrl($id));
}

render_error(400);