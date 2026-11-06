<?php
/** Path: admin/actions/catalog.php — บันทึก/ลบ เกมและหมวดหมู่ */
declare(strict_types=1);
require dirname(__DIR__) . '/includes/guard.php';

require_post();
$op = post_str('op');
$perm = match ($op) {
    'game_save', 'game_delete' => 'game.manage',
    'category_save', 'category_delete' => 'category.manage',
    default => null,
};
if ($perm === null) {
    render_error(400);
}
$me = admin_action($perm);
$uid = (int) $me['id'];
$id = admin_int($_POST['id'] ?? 0);
$publicBucket = (string) config('supabase.public_bucket');
$nz = static fn(string $s): ?string => $s === '' ? null : $s;

$sortValue = static function (string $raw): int|false {
    return filter_var($raw === '' ? '0' : $raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => -9999, 'max_range' => 9999]]);
};

// ------------------------------------------------------------------ games
if ($op === 'game_save') {
    $game = $id > 0 ? (DB::one('SELECT * FROM games WHERE id = ?', [$id]) ?? render_error(404)) : null;
    $back = url('admin/catalog.php') . ($id > 0 ? '?edit=' . $id : '');
    $in = [
        'name' => post_str('name'), 'name_en' => post_str('name_en'), 'slug' => strtolower(post_str('slug')),
        'short_description' => post_str('short_description'), 'sort_order' => post_str('sort_order'),
        'is_active' => isset($_POST['is_active']) ? '1' : '', 'is_featured' => isset($_POST['is_featured']) ? '1' : '',
    ];
    $fail = static function (string $msg) use ($in, $back): never {
        set_old($in);
        flash('error', $msg);
        redirect($back);
    };

    if (mb_strlen($in['name']) < 2 || mb_strlen($in['name']) > 120) { $fail('ชื่อเกมต้องยาว 2–120 ตัวอักษร'); }
    if (mb_strlen($in['name_en']) > 120) { $fail('ชื่อภาษาอังกฤษยาวเกิน 120 ตัวอักษร'); }
    if (mb_strlen($in['short_description']) > 255) { $fail('คำอธิบายสั้นยาวเกิน 255 ตัวอักษร'); }
    $sort = $sortValue($in['sort_order']);
    if ($sort === false) { $fail('ลำดับต้องเป็นตัวเลข -9999 ถึง 9999'); }

    $slug = Str::uniqueSlug('games', Str::slug($in['slug'] !== '' ? $in['slug'] : ($in['name_en'] !== '' ? $in['name_en'] : $in['name'])), $id ?: null, 'game');

    $oldIcon = $game['icon_path'] ?? null;
    $iconPath = isset($_POST['remove_icon']) ? null : $oldIcon;
    $iconFile = $_FILES['icon'] ?? null;
    if (is_array($iconFile) && ($iconFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        try {
            $iconPath = MediaService::uploadPublicImage($iconFile, 'games')['public_url'];
        } catch (InvalidArgumentException | StorageException $e) {
            $fail('อัปโหลดไอคอนไม่สำเร็จ: ' . $e->getMessage());
        }
    }

    $data = [
        'name' => $in['name'], 'name_en' => $nz($in['name_en']), 'slug' => $slug,
        'short_description' => $nz($in['short_description']), 'icon_path' => $iconPath,
        'is_active' => $in['is_active'] === '1' ? 1 : 0, 'is_featured' => $in['is_featured'] === '1' ? 1 : 0,
        'sort_order' => $sort, 'updated_by' => $uid,
    ];
    if ($game === null) {
        $data += ['created_by' => $uid, 'created_at' => now(), 'updated_at' => now()];
        $id = DB::insert('INSERT INTO games (' . implode(', ', array_keys($data)) . ') VALUES (' . implode(', ', array_fill(0, count($data), '?')) . ')', array_values($data));
        Audit::log('game.create', 'game', $id, 'เพิ่มเกม ' . $data['name'], ['slug' => $slug]);
        flash('success', 'เพิ่มเกมแล้ว');
    } else {
        $sets = implode(', ', array_map(static fn(string $k): string => "{$k} = ?", array_keys($data)));
        DB::run("UPDATE games SET {$sets} WHERE id = ?", [...array_values($data), $id]);
        Audit::log('game.update', 'game', $id, 'แก้ไขเกม ' . $data['name'], ['slug' => $slug, 'icon_changed' => $oldIcon !== $iconPath]);
        flash('success', 'บันทึกเกมแล้ว');
    }
    if ($oldIcon !== null && $oldIcon !== $iconPath) {
        MediaService::deleteQuietly($publicBucket, MediaService::publicPathFromUrl($oldIcon));
    }
    redirect(url('admin/catalog.php'));
}

if ($op === 'game_delete') {
    $game = DB::one('SELECT id, name, icon_path FROM games WHERE id = ?', [$id]) ?? render_error(404);
    if ((int) DB::value('SELECT COUNT(*) FROM products WHERE game_id = ?', [$id]) > 0) {
        flash('error', 'ลบไม่ได้: ยังมีสินค้าในเกมนี้ ให้ย้ายหรือลบสินค้าก่อน หรือซ่อนเกมแทน');
        redirect(url('admin/catalog.php'));
    }
    DB::run('DELETE FROM games WHERE id = ?', [$id]);
    MediaService::deleteQuietly($publicBucket, MediaService::publicPathFromUrl($game['icon_path']));
    Audit::log('game.delete', 'game', $id, 'ลบเกม ' . $game['name']);
    flash('success', 'ลบเกมแล้ว');
    redirect(url('admin/catalog.php'));
}

// ------------------------------------------------------------------ categories
if ($op === 'category_save') {
    $cat = $id > 0 ? (DB::one('SELECT * FROM categories WHERE id = ?', [$id]) ?? render_error(404)) : null;
    $back = url('admin/catalog.php') . '?tab=categories' . ($id > 0 ? '&edit=' . $id : '');
    $in = [
        'game_id' => post_str('game_id'), 'name' => post_str('name'), 'name_en' => post_str('name_en'),
        'slug' => strtolower(post_str('slug')), 'description' => post_str('description'),
        'sort_order' => post_str('sort_order'), 'is_active' => isset($_POST['is_active']) ? '1' : '',
    ];
    $fail = static function (string $msg) use ($in, $back): never {
        set_old($in);
        flash('error', $msg);
        redirect($back);
    };

    $gameId = admin_int($in['game_id']);
    if ($gameId > 0 && DB::value('SELECT id FROM games WHERE id = ?', [$gameId]) === null) { $fail('ไม่พบเกมที่เลือก'); }
    if (mb_strlen($in['name']) < 2 || mb_strlen($in['name']) > 120) { $fail('ชื่อหมวดหมู่ต้องยาว 2–120 ตัวอักษร'); }
    if (mb_strlen($in['name_en']) > 120 || mb_strlen($in['description']) > 255) { $fail('ข้อมูลยาวเกินกำหนด'); }
    $sort = $sortValue($in['sort_order']);
    if ($sort === false) { $fail('ลำดับต้องเป็นตัวเลข -9999 ถึง 9999'); }

    $base = Str::slug($in['slug'] !== '' ? $in['slug'] : ($in['name_en'] !== '' ? $in['name_en'] : $in['name']));
    if ($base === '') {
        $base = 'cat-' . bin2hex(random_bytes(3));
    }
    $slug = $base;
    for ($n = 2; DB::value('SELECT id FROM categories WHERE slug = ? AND game_id <=> ? AND id <> ? LIMIT 1', [$slug, $gameId ?: null, $id]) !== null; $n++) {
        $slug = $base . '-' . $n;
    }

    $data = [
        'game_id' => $gameId ?: null, 'name' => $in['name'], 'name_en' => $nz($in['name_en']), 'slug' => $slug,
        'description' => $nz($in['description']), 'is_active' => $in['is_active'] === '1' ? 1 : 0, 'sort_order' => $sort,
    ];
    if ($cat === null) {
        $data += ['created_at' => now(), 'updated_at' => now()];
        $id = DB::insert('INSERT INTO categories (' . implode(', ', array_keys($data)) . ') VALUES (' . implode(', ', array_fill(0, count($data), '?')) . ')', array_values($data));
        Audit::log('category.create', 'category', $id, 'เพิ่มหมวดหมู่ ' . $data['name']);
        flash('success', 'เพิ่มหมวดหมู่แล้ว');
    } else {
        $sets = implode(', ', array_map(static fn(string $k): string => "{$k} = ?", array_keys($data)));
        DB::run("UPDATE categories SET {$sets} WHERE id = ?", [...array_values($data), $id]);
        Audit::log('category.update', 'category', $id, 'แก้ไขหมวดหมู่ ' . $data['name']);
        flash('success', 'บันทึกหมวดหมู่แล้ว');
    }
    redirect(url('admin/catalog.php') . '?tab=categories');
}

if ($op === 'category_delete') {
    $cat = DB::one('SELECT id, name FROM categories WHERE id = ?', [$id]) ?? render_error(404);
    $affected = (int) DB::value('SELECT COUNT(*) FROM products WHERE category_id = ?', [$id]);
    DB::run('DELETE FROM categories WHERE id = ?', [$id]); // products.category_id → NULL (FK SET NULL)
    Audit::log('category.delete', 'category', $id, 'ลบหมวดหมู่ ' . $cat['name'], ['products_unassigned' => $affected]);
    flash('success', 'ลบหมวดหมู่แล้ว' . ($affected > 0 ? " (สินค้า {$affected} รายการไม่มีหมวดหมู่แล้ว)" : ''));
    redirect(url('admin/catalog.php') . '?tab=categories');
}