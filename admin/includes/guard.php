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

/** ต้องมีอย่างน้อยหนึ่งสิทธิ์ในรายการ */
function admin_require_any(array $permissions): array
{
    $user = admin_require();
    foreach ($permissions as $p) {
        if (Rbac::can((int) $user['id'], $p)) {
            return $user;
        }
    }
    Audit::log('access.denied', 'permission', implode('|', $permissions), 'ถูกปฏิเสธการเข้าถึง', [
        'uri' => mb_substr((string) ($_SERVER['REQUEST_URI'] ?? ''), 0, 255),
    ]);
    render_error(403, 'บัญชีของคุณไม่มีสิทธิ์ใช้งานส่วนนี้');
}

function admin_can(string $permission): bool
{
    $id = Auth::id();
    return $id !== null && Rbac::can($id, $permission);
}

/** สำหรับไฟล์ใน admin/actions/: POST + Login + Permission + CSRF */
function admin_action(?string $permission): array
{
    require_post();
    $user = admin_require($permission);
    Csrf::verify();
    return $user;
}

/** จำนวนเต็มบวก หรือ 0 ถ้าไม่ถูกต้อง */
function admin_int(mixed $value): int
{
    $i = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]);
    return $i === false ? 0 : (int) $i;
}

/** แปลง $_FILES แบบ multiple (name="x[]") เป็นรายการไฟล์ทีละชิ้น */
function admin_files(mixed $f): array
{
    if (!is_array($f) || !isset($f['name'])) {
        return [];
    }
    if (!is_array($f['name'])) {
        return ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE ? [] : [$f];
    }
    $out = [];
    foreach (array_keys($f['name']) as $i) {
        if (($f['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $out[] = [
            'name' => $f['name'][$i], 'type' => $f['type'][$i] ?? '', 'tmp_name' => $f['tmp_name'][$i] ?? '',
            'error' => $f['error'][$i] ?? UPLOAD_ERR_NO_FILE, 'size' => $f['size'][$i] ?? 0,
        ];
    }
    return $out;
}

/** อ่านค่าฟอร์มเดิมหลัง validation ไม่ผ่าน (ใช้ครั้งเดียว) */
function admin_take_old(): ?array
{
    $old = is_array($_SESSION['_old'] ?? null) ? $_SESSION['_old'] : null;
    unset($_SESSION['_old']);
    return $old;
}