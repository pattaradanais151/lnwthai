<?php
/**
 * Lnwthai — Store settings (โหลดครั้งเดียวต่อ request)
 * Path: includes/Settings.php
 */
declare(strict_types=1);
defined('LNW_ROOT') || exit;

final class Settings
{
    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache === null) {
            try {
                self::$cache = array_column(DB::all('SELECT setting_key, setting_value FROM settings'), 'setting_value', 'setting_key');
            } catch (Throwable $e) {
                error_log('Settings load failed: ' . $e->getMessage());
                self::$cache = [];
            }
        }
        return self::$cache;
    }

    public static function get(string $key, string $default = ''): string
    {
        $v = self::all()[$key] ?? null;
        return is_string($v) && trim($v) !== '' ? $v : $default;
    }

    public static function bool(string $key): bool
    {
        return in_array(strtolower(self::get($key, '0')), ['1', 'true', 'yes', 'on'], true);
    }

    public static function int(string $key, int $default): int
    {
        $v = self::get($key);
        return preg_match('/^-?\d{1,9}$/', $v) === 1 ? (int) $v : $default;
    }
}