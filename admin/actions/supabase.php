<?php
/** Path: admin/actions/supabase.php — ตั้งค่า/ทดสอบ Supabase Storage (ต้องมีสิทธิ์ system.check) */
declare(strict_types=1);
require dirname(__DIR__) . '/includes/guard.php';

admin_require('system.check');
require_post();
Csrf::verify();

$op = post_str('op');
if (!in_array($op, ['buckets', 'selftest'], true)) {
    render_error(400);
}

try {
    $storage = new SupabaseStorage();
    $items = $op === 'buckets'
        ? array_map(static fn(string $m): array => ['ok', $m], $storage->ensureBuckets())
        : $storage->selfTest();
} catch (Throwable $e) {
    error_log('Supabase ' . $op . ': ' . $e->getMessage());
    $items = [['error', $e instanceof StorageException ? $e->getMessage() : 'เกิดข้อผิดพลาดที่ไม่คาดคิด ดูรายละเอียดใน storage/logs']];
}

$failed = count(array_filter($items, static fn(array $i): bool => $i[0] !== 'ok'));
$_SESSION['_sb_results'] = ['op' => $op, 'at' => now(), 'items' => $items];
Audit::log('system.supabase_' . $op, 'system', 'supabase',
    $op === 'buckets' ? 'สร้าง/อัปเดต Supabase buckets' : 'ทดสอบ Supabase Storage',
    ['steps' => count($items), 'failed' => $failed]);

redirect(url('admin/system-check.php') . '#supabase');