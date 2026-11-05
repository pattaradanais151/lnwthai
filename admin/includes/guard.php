<?php
/**
 * Lnwthai — Admin entry guard (ทุกไฟล์ใน admin/ ต้อง require ไฟล์นี้ก่อน)
 * Path: admin/includes/guard.php
 */
declare(strict_types=1);

if (count(get_included_files()) === 1) { // ถูกเรียกตรงผ่าน URL
    http_response_code(404);
    exit;
}

defined('LNW_CONTEXT') || define('LNW_CONTEXT', 'admin');
if (LNW_CONTEXT !== 'admin') {
    http_response_code(500);
    exit('Invalid context');
}
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');

/** บังคับ Login หลังบ้าน + ตรวจ Permission จาก Backend ทุกครั้ง */
function admin_require(?string $permission = null): array
{
    $user = Auth::user();
    if ($user === null) {
        flash('info', 'กรุณาเข้าสู่ระบบหลังบ้าน');
        redirect(url('admin/login.php'));
    }
    $uid = (int) $user['id'];
    if (!Rbac::isStaff($uid)) {
        Audit::log('access.not_staff', 'user', $uid, 'บัญชีที่ไม่มีบทบาทผู้ดูแลพยายามเข้าหลังบ้าน');
        Auth::logout();
        render_error(403);
    }
    if ($permission !== null && !Rbac::can($uid, $permission)) {
        Audit::log('access.denied', 'permission', $permission, 'ถูกปฏิเสธการเข้าถึง: ' . $permission, [
            'uri' => mb_substr((string) ($_SERVER['REQUEST_URI'] ?? ''), 0, 255),
        ]);
        render_error(403, 'บัญชีของคุณไม่มีสิทธิ์ใช้งานส่วนนี้');
    }
    return $user;
}

function admin_can(string $permission): bool
{
    $id = Auth::id();
    return $id !== null && Rbac::can($id, $permission);
}