<?php
/** Path: admin/actions/logout.php */
declare(strict_types=1);
require dirname(__DIR__) . '/includes/guard.php';

require_post();
if (!Csrf::validate()) {
    if (!Auth::check()) {
        redirect(url('admin/login.php'));
    }
    render_error(419, 'แบบฟอร์มหมดอายุ กรุณารีเฟรชหน้าแล้วลองใหม่');
}
Auth::logout();
redirect(url('admin/login.php') . '?logged_out=1');