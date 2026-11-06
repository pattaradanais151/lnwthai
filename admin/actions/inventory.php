<?php
/**
 * Path: admin/actions/inventory.php — เพิ่ม/แก้ไข/ปิด/ลบ บัญชีเกม (inventory.manage)
 * ไม่ใช้ set_old() กับฟอร์มนี้ เพื่อไม่ให้ข้อมูลลับถูกเก็บลง session
 */
declare(strict_types=1);
require dirname(__DIR__) . '/includes/guard.php';

$me = admin_action('inventory.manage');
$uid = (int) $me['id'];
$op = post_str('op');
$productId = admin_int($_POST['product_id'] ?? 0);
DB::value('SELECT id FROM products WHERE id = ?', [$productId]) ?? render_error(404, 'ไม่พบสินค้า');
$accountId = admin_int($_POST['account_id'] ?? 0);
$back = url('admin/inventory.php') . '?product=' . $productId;

try {
    switch ($op) {
        case 'add':
            Inventory::addMany($productId, [Inventory::secretFromInput($_POST)], post_str('internal_note'), $uid, post_str('public_note'));
            flash('success', 'เพิ่มบัญชีเกม 1 รายการแล้ว (เข้ารหัสเรียบร้อย)');
            break;

        case 'bulk_add':
            $lines = array_values(array_filter(
                array_map('trim', preg_split('/\r\n|\r|\n/', post_raw('bulk')) ?: []),
                static fn(string $l): bool => $l !== ''
            ));
            if ($lines === []) {
                throw new DomainException('กรุณากรอกอย่างน้อย 1 บรรทัด');
            }
            if (count($lines) > 100) {
                throw new DomainException('เพิ่มได้ครั้งละไม่เกิน 100 บรรทัด');
            }
            $secrets = [];
            foreach ($lines as $i => $line) {
                $parts = array_map('trim', explode('|', $line));
                if (count($parts) < 2 || count($parts) > 4) {
                    throw new DomainException('บรรทัดที่ ' . ($i + 1) . ': ต้องเป็น ชื่อผู้ใช้ | รหัสผ่าน | อีเมล | ข้อมูลเพิ่มเติม');
                }
                try {
                    $secrets[] = Inventory::secretFromInput([
                        'username' => $parts[0], 'password' => $parts[1], 'email' => $parts[2] ?? '', 'notes' => $parts[3] ?? '',
                    ]);
                } catch (DomainException $e) {
                    throw new DomainException('บรรทัดที่ ' . ($i + 1) . ': ' . $e->getMessage());
                }
            }
            $ids = Inventory::addMany($productId, $secrets, post_str('internal_note'), $uid);
            flash('success', 'เพิ่มบัญชีเกม ' . count($ids) . ' รายการแล้ว (เข้ารหัสเรียบร้อย)');
            break;

        case 'update':
            Inventory::update(
                $accountId, $productId, Inventory::secretFromInput($_POST, true), isset($_POST['clear_optional']),
                post_str('public_note'), post_str('internal_note'), $uid
            );
            flash('success', 'บันทึกบัญชี #' . $accountId . ' แล้ว');
            break;

        case 'disable':
            Inventory::setStatus($accountId, $productId, 'disabled', post_str('reason'), $uid);
            flash('success', 'ปิดใช้งานบัญชี #' . $accountId . ' แล้ว');
            break;

        case 'enable':
            Inventory::setStatus($accountId, $productId, 'available', '', $uid);
            flash('success', 'เปิดขายบัญชี #' . $accountId . ' แล้ว');
            break;

        case 'delete':
            Inventory::delete($accountId, $productId, $uid);
            flash('success', 'ลบบัญชี #' . $accountId . ' แล้ว');
            break;

        default:
            render_error(400);
    }
} catch (DomainException $e) {
    flash('error', $e->getMessage());
    if ($op === 'update' && $accountId > 0) {
        redirect($back . '&edit=' . $accountId);
    }
}
redirect($back);