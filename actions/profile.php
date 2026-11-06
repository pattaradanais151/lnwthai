<?php
/** Path: actions/profile.php — แก้ไขโปรไฟล์ลูกค้า (POST + CSRF · แก้ได้เฉพาะบัญชีตัวเอง) */
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

require_post();
$user = Auth::requireCustomer();
Csrf::verify();

$v = new Validator($_POST);
$v->required('display_name', 'ชื่อที่แสดง')->length('display_name', 2, 100, 'ชื่อที่แสดง')->phone('phone');

if ($v->fails()) {
    set_old(['display_name' => $v->value('display_name'), 'phone' => $v->value('phone')]);
    flash('error', (string) $v->first());
    redirect(url('account') . '#profile');
}

DB::run(
    'UPDATE users SET display_name = ?, phone = ? WHERE id = ? AND account_type = ?',
    [$v->value('display_name'), $v->value('phone') !== '' ? $v->value('phone') : null, (int) $user['id'], 'customer']
);

flash('success', 'บันทึกข้อมูลโปรไฟล์เรียบร้อยแล้ว');
redirect(url('account') . '#profile');