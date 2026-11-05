<?php
/**
 * Lnwthai — Application bootstrap
 * Path: includes/bootstrap.php
 */
declare(strict_types=1);

defined('LNW_ROOT') || define('LNW_ROOT', dirname(__DIR__));
defined('LNW_CONTEXT') || define('LNW_CONTEXT', 'customer'); // customer | admin | install

if (PHP_VERSION_ID < 80100) {
    http_response_code(500);
    exit('Lnwthai requires PHP 8.1 or newer.');
}

$__configFile = LNW_ROOT . '/config/config.php';
if (!is_file($__configFile)) {
    http_response_code(503);
    exit('Configuration not found. Copy config/config.sample.php to config/config.php.');
}
$GLOBALS['__lnw_config'] = require $__configFile;
unset($__configFile);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/icons.php';

spl_autoload_register(static function (string $class): void {
    if (preg_match('/^[A-Z][A-Za-z]+$/', $class) === 1) {
        $file = __DIR__ . '/' . $class . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    }
});

date_default_timezone_set((string) config('app.timezone', 'Asia/Bangkok'));
mb_internal_encoding('UTF-8');
ob_start();
if (!headers_sent()) {
    header_remove('X-Powered-By');
}

foreach (['storage/logs', 'storage/sessions', 'storage/tmp'] as $__dir) {
    $__path = LNW_ROOT . '/' . $__dir;
    if (!is_dir($__path)) {
        @mkdir($__path, 0700, true);
    }
}
unset($__dir, $__path);

$__debug = (bool) config('app.debug', false);
error_reporting(E_ALL);
ini_set('display_errors', $__debug ? '1' : '0');
ini_set('log_errors', '1');
if (is_writable(LNW_ROOT . '/storage/logs')) {
    ini_set('error_log', LNW_ROOT . '/storage/logs/php-' . date('Y-m') . '.log');
}

set_exception_handler(static function (Throwable $e) use ($__debug): void {
    error_log(sprintf('[%s] %s @ %s:%d', $e::class, $e->getMessage(), $e->getFile(), $e->getLine()));
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if ($__debug) {
        http_response_code(500);
        echo '<pre style="white-space:pre-wrap">' . e((string) $e) . '</pre>';
        return;
    }
    render_error(500);
});

if (PHP_SAPI !== 'cli' && config('app.force_https', false) && !is_https()) {
    $host = (string) parse_url((string) config('app.base_url', ''), PHP_URL_HOST);
    if ($host !== '') {
        header('Location: https://' . $host . ($_SERVER['REQUEST_URI'] ?? '/'), true, 301);
        exit;
    }
}

send_security_headers();

if (LNW_CONTEXT !== 'install' && strlen((string) config('security.app_key', '')) < 64) {
    render_error(503, 'ระบบยังตั้งค่าไม่ครบ: กรุณากำหนด security.app_key (อย่างน้อย 64 ตัวอักษร)');
}

Session::start(LNW_CONTEXT === 'admin' ? 'admin' : 'customer');