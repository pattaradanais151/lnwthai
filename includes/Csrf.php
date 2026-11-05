<?php
/**
 * Lnwthai — CSRF protection (synchronizer token + Origin check)
 * Path: includes/Csrf.php
 */
declare(strict_types=1);
defined('LNW_ROOT') || exit;

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function rotate(): void
    {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(self::token()) . '">';
    }

    public static function validate(): bool
    {
        $sent = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!is_string($sent) || $sent === '' || empty($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
            return false;
        }
        return hash_equals($_SESSION['_csrf'], $sent) && self::originAllowed();
    }

    public static function verify(): void
    {
        if (!self::validate()) {
            render_error(419, 'แบบฟอร์มหมดอายุหรือไม่ถูกต้อง กรุณารีเฟรชหน้าแล้วลองใหม่');
        }
    }

    private static function originAllowed(): bool
    {
        $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
        if ($origin === '') {
            return true; // บาง browser ไม่ส่ง Origin — ใช้ token เป็นหลัก
        }
        if ($origin === 'null') {
            return false;
        }
        $expected = (string) parse_url((string) config('app.base_url', ''), PHP_URL_HOST);
        return strcasecmp((string) parse_url($origin, PHP_URL_HOST), $expected) === 0;
    }
}