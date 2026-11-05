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
    /** รูปสินค้า/แบนเนอร์ → Public bucket */
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
}