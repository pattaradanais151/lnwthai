<?php
/**
 * Lnwthai — Upload validation (MIME sniffing + dimension guard + GD re-encode)
 * Path: includes/FileValidator.php
 */
declare(strict_types=1);
defined('LNW_ROOT') || exit;

final class FileValidator
{
    public const IMAGE_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    private const UPLOAD_ERRORS = [
        UPLOAD_ERR_INI_SIZE   => 'ไฟล์มีขนาดใหญ่เกินที่เซิร์ฟเวอร์รองรับ',
        UPLOAD_ERR_FORM_SIZE  => 'ไฟล์มีขนาดใหญ่เกินกำหนด',
        UPLOAD_ERR_PARTIAL    => 'อัปโหลดไฟล์ไม่สมบูรณ์',
        UPLOAD_ERR_NO_FILE    => 'กรุณาเลือกไฟล์',
        UPLOAD_ERR_NO_TMP_DIR => 'เซิร์ฟเวอร์ไม่มีโฟลเดอร์ชั่วคราว',
        UPLOAD_ERR_CANT_WRITE => 'บันทึกไฟล์ชั่วคราวไม่สำเร็จ',
        UPLOAD_ERR_EXTENSION  => 'การอัปโหลดถูกปฏิเสธโดยเซิร์ฟเวอร์',
    ];

    /** @return array{tmp:string,is_temp_copy:bool,mime:string,ext:string,size:int,width:int,height:int,sha256:string} */
    public static function image(mixed $file, int $maxBytes, ?int $maxDimension = null): array
    {
        $maxDimension ??= (int) config('upload.max_dimension', 4096);
        if (!is_array($file) || !isset($file['error'], $file['tmp_name']) || is_array($file['error'])) {
            throw new InvalidArgumentException('ไม่พบไฟล์ที่อัปโหลด');
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException(self::UPLOAD_ERRORS[$file['error']] ?? 'อัปโหลดไฟล์ไม่สำเร็จ');
        }
        $tmp = (string) $file['tmp_name'];
        if (!is_uploaded_file($tmp)) {
            throw new InvalidArgumentException('ไฟล์ไม่ถูกต้อง');
        }
        $size = (int) filesize($tmp);
        if ($size <= 0 || $size > $maxBytes) {
            throw new InvalidArgumentException('ไฟล์ต้องมีขนาดไม่เกิน ' . round($maxBytes / 1048576, 1) . ' MB');
        }
        $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
        if (!isset(self::IMAGE_TYPES[$mime])) {
            throw new InvalidArgumentException('รองรับเฉพาะไฟล์ JPG, PNG หรือ WEBP');
        }
        $dim = @getimagesize($tmp);
        if ($dim === false || ($dim['mime'] ?? '') !== $mime || $dim[0] < 1 || $dim[1] < 1
            || $dim[0] > $maxDimension || $dim[1] > $maxDimension) {
            throw new InvalidArgumentException("รูปภาพไม่ถูกต้อง หรือมีขนาดเกิน {$maxDimension}px");
        }

        $sha = (string) hash_file('sha256', $tmp);
        $clean = ($dim[0] * $dim[1] <= 8_000_000) ? self::reencode($tmp, $mime) : null;
        $final = $clean ?? $tmp;

        return [
            'tmp' => $final, 'is_temp_copy' => $clean !== null, 'mime' => $mime,
            'ext' => self::IMAGE_TYPES[$mime], 'size' => (int) filesize($final),
            'width' => (int) $dim[0], 'height' => (int) $dim[1], 'sha256' => $sha,
        ];
    }

    /** Re-encode ด้วย GD เพื่อตัด metadata / payload แฝง (ถ้ามี GD) */
    private static function reencode(string $path, string $mime): ?string
    {
        if (!extension_loaded('gd')) {
            return null;
        }
        $loader = ['image/jpeg' => 'imagecreatefromjpeg', 'image/png' => 'imagecreatefrompng', 'image/webp' => 'imagecreatefromwebp'][$mime];
        if (!function_exists($loader)) {
            return null;
        }
        $img = @$loader($path);
        if ($img === false) {
            throw new InvalidArgumentException('ไฟล์รูปภาพเสียหาย');
        }
        $out = tempnam(LNW_ROOT . '/storage/tmp', 'img');
        if ($out === false) {
            return null;
        }
        $ok = match ($mime) {
            'image/jpeg' => imagejpeg($img, $out, 85),
            'image/png'  => imagesavealpha($img, true) && imagepng($img, $out, 6),
            'image/webp' => function_exists('imagewebp') && imagewebp($img, $out, 85),
        };
        unset($img);
        if (!$ok) {
            @unlink($out);
            return null;
        }
        return $out;
    }
}