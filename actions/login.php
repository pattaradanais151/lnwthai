<?php
/** Path: actions/login.php — Customer login (POST only) */
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

require_post();
Csrf::verify();

$email = mb_strtolower(post_str('email'));
$next = safe_redirect_target(post_str('next'), '');
$result = Auth::attempt($email, post_raw('password'), 'customer');

if (!$result['ok']) {
    set_old(['email' => $email]);
    flash('error', $result['error'] ?? 'เข้าสู่ระบบไม่สำเร็จ');
    redirect(url('login') . ($next !== '' ? '?next=' . rawurlencode($next) : ''));
}

flash('success', 'เข้าสู่ระบบสำเร็จ');
redirect($next !== '' ? $next : url('account'));