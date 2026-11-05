<?php
/**
 * Lnwthai — Hardened PHP session (separate customer / admin cookies)
 * Path: includes/Session.php
 */
declare(strict_types=1);
defined('LNW_ROOT') || exit;

final class Session
{
    private static string $context = 'customer';

    public static function start(string $context): void
    {
        self::$context = $context === 'admin' ? 'admin' : 'customer';
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $isAdmin = self::$context === 'admin';
        $idle = (int) config($isAdmin ? 'session.admin_idle' : 'session.customer_idle', 7200);

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.gc_maxlifetime', (string) max(1800, $idle));
        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '100');

        $savePath = LNW_ROOT . '/storage/sessions';
        if (is_dir($savePath) && is_writable($savePath)) {
            session_save_path($savePath);
        }

        session_name((string) config($isAdmin ? 'session.admin_name' : 'session.customer_name', $isAdmin ? 'LNWADM' : 'LNWSID'));
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => $isAdmin ? base_path() . '/admin/' : base_path() . '/',
            'secure'   => is_https(),
            'httponly' => true,
            'samesite' => $isAdmin ? 'Strict' : 'Lax',
        ]);
        session_start();

        $now = time();
        if (!isset($_SESSION['_started'])) {
            $_SESSION['_started'] = $now;
            $_SESSION['_regen'] = $now;
        }
        if ($now - (int) ($_SESSION['_regen'] ?? 0) > 900) {
            self::regenerate();
        }
    }

    public static function context(): string
    {
        return self::$context;
    }

    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
            $_SESSION['_regen'] = time();
        }
    }

    public static function destroy(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        $_SESSION = [];
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $p['path'],
            'secure'   => $p['secure'],
            'httponly' => true,
            'samesite' => $p['samesite'] ?: 'Lax',
        ]);
        session_destroy();
    }
}