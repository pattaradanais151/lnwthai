<?php
/**
 * Lnwthai — Display formatting (เงิน สถานะ URL รูปภาพ)
 * Path: includes/Format.php
 */
declare(strict_types=1);
defined('LNW_ROOT') || exit;

final class Format
{
    private const ORDER = [
        'pending_payment'       => ['รอชำระเงิน', 'warn'],
        'awaiting_verification' => ['รอตรวจสอบการชำระเงิน', 'info'],
        'paid'                  => ['ชำระเงินแล้ว', 'ok'],
        'delivered'             => ['ส่งมอบแล้ว', 'ok'],
        'completed'             => ['สำเร็จ', 'ok'],
        'cancelled'             => ['ยกเลิก', 'neutral'],
        'expired'               => ['หมดอายุ', 'neutral'],
        'refunded'              => ['คืนเงินแล้ว', 'neutral'],
    ];
    private const PAYMENT = [
        'pending'      => ['รอชำระเงิน', 'warn'],
        'submitted'    => ['ส่งหลักฐานแล้ว', 'info'],
        'under_review' => ['กำลังตรวจสอบ', 'info'],
        'verified'     => ['ยืนยันแล้ว', 'ok'],
        'rejected'     => ['ถูกปฏิเสธ', 'error'],
        'refunded'     => ['คืนเงินแล้ว', 'neutral'],
    ];
    private const DELIVERY = [
        'pending'   => ['รอส่งมอบ', 'warn'],
        'delivered' => ['ส่งมอบแล้ว', 'ok'],
        'revoked'   => ['ยกเลิกการส่งมอบ', 'error'],
    ];
    private const PRODUCT = [
        'draft'    => ['ฉบับร่าง', 'neutral'],
        'active'   => ['เปิดขาย', 'ok'],
        'hidden'   => ['ซ่อน', 'warn'],
        'sold_out' => ['สินค้าหมด', 'info'],
        'archived' => ['เก็บถาวร', 'neutral'],
    ];
    private const ACCOUNT = [
        'available' => ['พร้อมขาย', 'ok'],
        'reserved'  => ['ถูกจอง', 'info'],
        'sold'      => ['ขายแล้ว', 'neutral'],
        'disabled'  => ['ปิดใช้งาน', 'error'],
    ];

    public static function money(mixed $value): string
    {
        $n = (float) $value;
        return '฿' . number_format($n, abs($n - floor($n)) < 0.005 ? 0 : 2);
    }

    public static function discountPercent(mixed $price, mixed $compareAt): ?int
    {
        $p = (float) $price;
        $c = (float) ($compareAt ?? 0);
        if ($p <= 0 || $c <= $p) {
            return null;
        }
        $pct = (int) round(($c - $p) / $c * 100);
        return $pct >= 1 ? $pct : null;
    }

    public static function label(string $type, ?string $status): string
    {
        return self::map($type)[(string) $status][0] ?? (string) $status;
    }

    public static function badge(string $type, ?string $status): string
    {
        [$label, $tone] = self::map($type)[(string) $status] ?? [(string) $status, 'neutral'];
        return '<span class="badge badge-' . e($tone) . '">' . e($label) . '</span>';
    }

    /** @return array<string,string> status => label (ใช้กับ <select>) */
    public static function options(string $type): array
    {
        return array_map(static fn(array $v): string => $v[0], self::map($type));
    }

    private static function map(string $type): array
    {
        return match ($type) {
            'order'    => self::ORDER,
            'payment'  => self::PAYMENT,
            'delivery' => self::DELIVERY,
            'product'  => self::PRODUCT,
            'account'  => self::ACCOUNT,
            default    => [],
        };
    }

    /** อนุญาตเฉพาะรูปจาก Supabase public bucket หรือ path ภายในเว็บ (สอดคล้องกับ CSP img-src) */
    public static function imageUrl(mixed $url): ?string
    {
        if (!is_string($url) || $url === '') {
            return null;
        }
        $sb = rtrim((string) config('supabase.url', ''), '/');
        if ($sb !== '' && str_starts_with($url, $sb . '/storage/v1/object/public/')) {
            return $url;
        }
        if ($url[0] === '/' && !str_starts_with($url, '//')) {
            return $url;
        }
        return null;
    }

    /** ลิงก์ภายนอก (โซเชียล) — https เท่านั้น */
    public static function externalUrl(string $url): ?string
    {
        $url = trim($url);
        return preg_match('#^https://[^\s<>"\']+$#i', $url) === 1 && filter_var($url, FILTER_VALIDATE_URL) !== false ? $url : null;
    }

    public static function initials(?string $name): string
    {
        $name = trim((string) $name);
        return $name === '' ? '?' : mb_strtoupper(mb_substr($name, 0, 1));
    }

    /** สร้าง URL พร้อม query string โดยตัดค่าว่างออก */
    public static function query(string $path, array $params): string
    {
        $params = array_filter($params, static fn($v): bool => $v !== null && $v !== '');
        $qs = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        return url($path) . ($qs !== '' ? '?' . $qs : '');
    }

    /** แยกรายการ (ขึ้นบรรทัดใหม่หรือจุลภาค) */
    public static function lines(?string $text): array
    {
        $parts = preg_split('/\r\n|\r|\n|,/u', (string) $text) ?: [];
        return array_values(array_filter(array_map('trim', $parts), static fn(string $s): bool => $s !== ''));
    }
}