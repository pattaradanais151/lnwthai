<?php
/**
 * Lnwthai — High-level media operations (validation + Supabase)
 * Path: includes/MediaService.php
 * ผู้เรียกต้องตรวจสิทธิ์ (Rbac / ความเป็นเจ้าของ) ก่อนเรียกทุกเมธอด
 */
declare(strict_types=1);
defined('LNW_ROOT') || exit;

final class MediaService
{
    public static function available(): bool
    {
        return SupabaseStorage::isConfigured() && SupabaseStorage::transport() !== null;
    }

    /** รูปสินค้า/เกม/แบนเนอร์ → Public bucket */
    public static function uploadPublicImage(mixed $file, string $prefix): array
    {
        $info = FileValidator::image($file, (int) config('upload.max_image_bytes', 5242880));
        try {
            $storage = new SupabaseStorage();
            $bucket = (string) config('supabase.public_bucket');
            $path = SupabaseStorage::makePath($prefix, $info['ext']);
            $storage->upload($bucket, $path, $info['tmp'], $info['mime']);
            return $info + ['bucket' => $bucket, 'path' => $path, 'public_url' => $storage->publicUrl($bucket, $path)];
        } finally {
            if ($info['is_temp_copy']) {
                @unlink($info['tmp']);
            }
        }
    }

    /** หลักฐานการชำระเงิน → Private bucket (ไม่มี public URL) */
    public static function uploadPrivateEvidence(mixed $file, string $prefix): array
    {
        $info = FileValidator::image($file, (int) config('upload.max_evidence_bytes', 5242880));
        try {
            $storage = new SupabaseStorage();
            $bucket = (string) config('supabase.private_bucket');
            $path = SupabaseStorage::makePath($prefix, $info['ext']);
            $storage->upload($bucket, $path, $info['tmp'], $info['mime']);
            return $info + ['bucket' => $bucket, 'path' => $path];
        } finally {
            if ($info['is_temp_copy']) {
                @unlink($info['tmp']);
            }
        }
    }

    public static function privateSignedUrl(string $path, ?int $ttl = null): string
    {
        return (new SupabaseStorage())->signedUrl((string) config('supabase.private_bucket'), $path, $ttl);
    }

    public static function delete(string $bucket, string $path): void
    {
        $allowed = [(string) config('supabase.public_bucket'), (string) config('supabase.private_bucket')];
        if (!in_array($bucket, $allowed, true)) {
            throw new StorageException('ไม่อนุญาตให้ลบไฟล์ใน bucket นี้');
        }
        (new SupabaseStorage())->delete($bucket, [$path]);
    }

    /** ลบแบบไม่ทำให้การทำงานหลักล้ม (บันทึก log แทน) */
    public static function deleteQuietly(string $bucket, ?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }
        try {
            self::delete($bucket, $path);
        } catch (Throwable $e) {
            error_log('Media delete failed (' . $bucket . '/' . $path . '): ' . $e->getMessage());
        }
    }

    /** แปลง public URL ของ bucket สาธารณะกลับเป็น storage path (ใช้ลบไฟล์เก่า) */
    public static function publicPathFromUrl(?string $url): ?string
    {
        if (!is_string($url) || $url === '') {
            return null;
        }
        $prefix = rtrim((string) config('supabase.url', ''), '/') . '/storage/v1/object/public/'
            . (string) config('supabase.public_bucket') . '/';
        if (!str_starts_with($url, $prefix)) {
            return null;
        }
        $path = rawurldecode(substr($url, strlen($prefix)));
        return !str_contains($path, '..') && preg_match('#^[A-Za-z0-9/_.-]{1,255}$#', $path) === 1 ? $path : null;
    }
}