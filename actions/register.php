<?php
/** Path: actions/register.php — Customer registration (POST only) */
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

require_post();
Csrf::verify();

// Honeypot: บอตกรอกช่องที่ซ่อนอยู่ → ตอบเงียบ ๆ
if (post_str('website') !== '') {
    redirect(url('register'));
}

$ip = client_ip();
if (RateLimiter::tooManyActions('register', $ip, (int) config('security.register_max_per_hour', 5), 3600)) {
    flash('error', 'มีการสมัครสมาชิกจากเครือข่ายนี้บ่อยเกินไป กรุณาลองใหม่ภายหลัง');
    redirect(url('register'));
}

$v = new Validator($_POST);
$v->required('display_name', 'ชื่อที่แสดง')->length('display_name', 2, 100, 'ชื่อที่แสดง')
  ->required('email', 'อีเมล')->email('email')
  ->phone('phone')
  ->password('password')
  ->same('password', 'password_confirmation', 'รหัสผ่านยืนยันไม่ตรงกัน');

$email = mb_strtolower($v->value('email'));
$old = ['display_name' => $v->value('display_name'), 'email' => $email, 'phone' => $v->value('phone')];

if ($v->fails()) {
    set_old($old);
    flash('error', (string) $v->first());
    redirect(url('register'));
}

try {
    $userId = Auth::registerCustomer($email, $old['display_name'], $v->raw('password'), $old['phone'] ?: null);
} catch (DomainException) {
    RateLimiter::record('register', $email, $ip, false, null, 'email_taken');
    set_old($old);
    flash('error', 'อีเมลนี้ถูกใช้งานแล้ว หากเป็นของคุณกรุณาเข้าสู่ระบบ');
    redirect(url('register'));
}

RateLimiter::record('register', $email, $ip, true, $userId, 'ok');
$user = DB::one('SELECT * FROM users WHERE id = ?', [$userId]);
Auth::startSession($user, 'customer');

flash('success', 'สมัครสมาชิกสำเร็จ ยินดีต้อนรับสู่ Lnwthai');
redirect(url('account'));