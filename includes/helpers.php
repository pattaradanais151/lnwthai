<?php
/**
 * Lnwthai — Global helpers
 * Path: includes/helpers.php
 */
declare(strict_types=1);
defined('LNW_ROOT') || exit;

function config(string $key, mixed $default = null): mixed
{
    $value = $GLOBALS['__lnw_config'] ?? [];
    foreach (explode('.', $key) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }
    return $value;
}

function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

function base_path(): string
{
    static $path = null;
    if ($path === null) {
        $path = rtrim((string) parse_url((string) config('app.base_url', ''), PHP_URL_PATH), '/');
    }
    return $path;
}

function url(string $path = ''): string
{
    return base_path() . '/' . ltrim($path, '/');
}

function absolute_url(string $path = ''): string
{
    return rtrim((string) config('app.base_url', ''), '/') . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    $path = ltrim($path, '/');
    $file = LNW_ROOT . '/assets/' . $path;
    $version = is_file($file) ? (string) filemtime($file) : '1';
    return url('assets/' . $path) . '?v=' . $version;
}

function redirect(string $location, int $status = 303): never
{
    if ($location === '' || $location[0] !== '/' || str_starts_with($location, '//')) {
        $location = url($location);
    }
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Location: ' . $location, true, $status);
    exit;
}

/** อนุญาตเฉพาะ path ภายในเว็บ ป้องกัน Open Redirect */
function safe_redirect_target(mixed $target, string $fallback): string
{
    if (!is_string($target) || $target === '' || strlen($target) > 2000) {
        return $fallback;
    }
    if ($target[0] !== '/' || str_starts_with($target, '//') || str_contains($target, '\\')
        || preg_match('/[\x00-\x1F\x7F]/', $target) === 1) {
        return $fallback;
    }
    return $target;
}

function is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443) {
        return true;
    }
    return (bool) config('security.trust_forwarded_proto', false)
        && strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function require_post(): void
{
    if (!is_post()) {
        header('Allow: POST');
        render_error(405);
    }
}

function post_str(string $key): string
{
    $v = $_POST[$key] ?? '';
    return is_string($v) ? trim($v) : '';
}

function post_raw(string $key): string
{
    $v = $_POST[$key] ?? '';
    return is_string($v) ? $v : '';
}

function client_ip(): string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    $header = (string) config('security.client_ip_header', '');
    if ($header !== '' && !empty($_SERVER[$header])) {
        $candidate = trim(explode(',', (string) $_SERVER[$header])[0]);
        if (filter_var($candidate, FILTER_VALIDATE_IP)) {
            $ip = $candidate;
        }
    }
    return substr($ip, 0, 45);
}

function user_agent(): string
{
    return mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function format_datetime(?string $value): string
{
    if (!$value) {
        return '—';
    }
    $ts = strtotime($value);
    return $ts ? date('d/m/Y H:i', $ts) : '—';
}

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $items = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return is_array($items) ? $items : [];
}

function set_old(array $input): void
{
    $_SESSION['_old'] = array_filter($input, 'is_string');
}

function old(string $key): string
{
    static $old = null;
    if ($old === null) {
        $old = is_array($_SESSION['_old'] ?? null) ? $_SESSION['_old'] : [];
        unset($_SESSION['_old']);
    }
    $v = $old[$key] ?? '';
    return is_string($v) ? $v : '';
}

function send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }
    $img = ["'self'", 'data:'];
    $sb = rtrim((string) config('supabase.url', ''), '/');
    if (preg_match('#^https://[a-z0-9.-]+$#i', $sb) === 1) {
        $img[] = $sb;
    }
    header('Content-Security-Policy: ' . implode('; ', [
        "default-src 'self'",
        'img-src ' . implode(' ', $img),
        "style-src 'self'",
        "script-src 'self'",
        "font-src 'self'",
        "connect-src 'self'",
        "object-src 'none'",
        "base-uri 'self'",
        "form-action 'self'",
        "frame-ancestors 'none'",
    ]));
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    if (is_https()) {
        header('Strict-Transport-Security: max-age=31536000');
    }
}

function render_error(int $code, string $message = ''): never
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code($code);
        header('Cache-Control: no-store');
    }
    $titles = [
        400 => 'คำขอไม่ถูกต้อง', 403 => 'ไม่มีสิทธิ์เข้าถึง', 404 => 'ไม่พบหน้าที่ต้องการ',
        405 => 'ไม่รองรับวิธีการนี้', 419 => 'เซสชันหมดอายุ', 429 => 'ทำรายการบ่อยเกินไป',
        500 => 'เกิดข้อผิดพลาดภายในระบบ', 503 => 'ระบบยังไม่พร้อมให้บริการ',
    ];
    $title = $titles[$code] ?? 'เกิดข้อผิดพลาด';
    $message = $message !== '' ? $message : 'กรุณาลองใหม่อีกครั้ง หรือกลับไปยังหน้าแรก';
    require LNW_ROOT . '/includes/layout/error.php';
    exit;
}