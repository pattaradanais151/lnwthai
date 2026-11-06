<?php
/** Path: actions/password.php — เปลี่ยนรหัสผ่านลูกค้า (POST + CSRF + rate limit) */
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

require_post();
$user = Auth::requireCustomer();
Csrf::verify();

$uid = (int) $user['id'];
$email = (string) $user['email'];
$ip = client_ip();
$back = url('account') . '#security';

if (RateLimiter::isLocked('customer', $email, $ip)) {
    flash('error', 'มีการใส่รหัสผ่านผิดหลายครั้ง กรุณารอสักครู่แล้วลองใหม่');
    redirect($back);
}

$hash = (string) DB::value('SELECT password_hash FROM users WHERE id = ?', [$uid]);
$current = post_raw('current_password');
if ($current === '' || strlen($current) > 4096 || !password_verify($current, $hash)) {
    RateLimiter::record('customer', $email, $ip, false, $uid, 'pw_change_bad');
    flash('error', 'รหัสผ่านปัจจุบันไม่ถูกต้อง');
    redirect($back);
}

$v = new Validator($_POST);
$v->password('new_password')->same('new_password', 'new_password_confirmation', 'รหัสผ่านยืนยันไม่ตรงกัน');
if ($v->fails()) {
    flash('error', (string) $v->first());
    redirect($back);
}
if (password_verify($v->raw('new_password'), $hash)) {
    flash('error', 'รหัสผ่านใหม่ต้องไม่ซ้ำกับรหัสผ่านเดิม');
    redirect($back);
}

DB::run('UPDATE users SET password_hash = ?, password_changed_at = ? WHERE id = ?', [Auth::hashPassword($v->raw('new_password')), now(), $uid]);
$revoked = Auth::revokeSessions($uid, (int) $user['session_id'], $uid, 'password_changed');
Session::regenerate();
Csrf::rotate();
Audit::log('customer.password_changed', 'user', $uid, 'ลูกค้าเปลี่ยนรหัสผ่าน', ['other_sessions_revoked' => $revoked], $uid, 'customer');

flash('success', 'เปลี่ยนรหัสผ่านเรียบร้อยแล้ว' . ($revoked > 0 ? " และออกจากระบบอุปกรณ์อื่น {$revoked} เครื่อง" : ''));
redirect($back);