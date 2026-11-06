<?php
/**
 * Lnwthai — String helpers (slug, เงิน, รายละเอียดเพิ่มเติม)
 * Path: includes/Str.php
 */
declare(strict_types=1);
defined('LNW_ROOT') || exit;

final class Str
{
    private const SLUG_TABLES = ['products' => 190, 'games' => 140];

    public static function slug(string $text, int $max = 120): string
    {
        $s = (string) preg_replace('/[^a-z0-9]+/', '-', strtolower(trim($text)));
        return trim(substr(trim($s, '-'), 0, $max), '-');
    }

    /** slug ที่ไม่ซ้ำในตาราง (whitelist เท่านั้น) */
    public static function uniqueSlug(string $table, string $base, ?int $excludeId = null, string $fallbackPrefix = 'item'): string
    {
        $max = self::SLUG_TABLES[$table] ?? throw new InvalidArgumentException('Table not allowed');
        $base = trim(substr($base, 0, $max - 10), '-');
        if ($base === '') {
            $base = $fallbackPrefix . '-' . bin2hex(random_bytes(3));
        }
        $slug = $base;
        for ($i = 2; DB::value("SELECT id FROM {$table} WHERE slug = ? AND id <> ? LIMIT 1", [$slug, $excludeId ?? 0]) !== null; $i++) {
            $slug = $i <= 50 ? $base . '-' . $i : $base . '-' . bin2hex(random_bytes(3));
        }
        return $slug;
    }

    /** "1,290.5" → "1290.50" · ไม่ถูกต้อง → null */
    public static function money(string $value): ?string
    {
        $v = trim(str_replace([',', ' ', '฿'], '', $value));
        if (preg_match('/^\d{1,8}(\.\d{1,2})?$/', $v) !== 1) {
            return null;
        }
        return number_format((float) $v, 2, '.', '');
    }

    /** "หัวข้อ: ค่า" บรรทัดละรายการ → array · รูปแบบผิด → null */
    public static function parseAttributes(string $text): ?array
    {
        $out = [];
        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (preg_match('/^(.{1,60}?)\s*[:=]\s*(.{1,200})$/u', $line, $m) !== 1) {
                return null;
            }
            $out[trim($m[1])] = trim($m[2]);
            if (count($out) > 30) {
                return null;
            }
        }
        return $out;
    }

    public static function attributesToText(?string $json): string
    {
        $data = is_string($json) && $json !== '' ? json_decode($json, true) : null;
        if (!is_array($data)) {
            return '';
        }
        $lines = [];
        foreach ($data as $k => $v) {
            if (is_scalar($v)) {
                $lines[] = $k . ': ' . (is_bool($v) ? ($v ? 'true' : 'false') : $v);
            }
        }
        return implode("\n", $lines);
    }
}