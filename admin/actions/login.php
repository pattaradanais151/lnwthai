<?php
/** Path: admin/actions/login.php */
declare(strict_types=1);
require dirname(__DIR__) . '/includes/guard.php';

require_post();
Csrf::verify();

$identifier = post_str('identifier');
$result = Auth::attempt($identifier, post_raw('password'), 'admin');

if (!$result['ok']) {
    set_old(['identifier' => $identifier]);
    flash('error', $result['error'] ?? 'ข้อมูลเข้าสู่ระบบไม่ถูกต้อง');
    redirect(url('admin/login.php'));
}
flash('success', 'เข้าสู่ระบบหลังบ้านสำเร็จ');
redirect(url('admin/'));