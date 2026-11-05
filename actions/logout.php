<?php
/** Path: actions/logout.php — Customer logout (POST + CSRF) */
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

require_post();
if (!Csrf::validate()) {
    if (!Auth::check()) {
        redirect(url(''));
    }
    render_error(419, 'แบบฟอร์มหมดอายุ กรุณารีเฟรชหน้าแล้วลองใหม่');
}
Auth::logout();
redirect(url('login') . '?logged_out=1');