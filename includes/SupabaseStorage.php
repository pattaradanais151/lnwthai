<?php
/**
 * Lnwthai — Supabase Storage REST client (server-side only)
 * Path: includes/SupabaseStorage.php
 */
declare(strict_types=1);
defined('LNW_ROOT') || exit;

final class SupabaseStorage
{
    private string $url;
    private string $key;
    private int $timeout;

    public function __construct()
    {
        if (!self::isConfigured()) {
            throw new StorageException('ยังไม่ได้ตั้งค่า Supabase (supabase.url / supabase.service_key)');
        }
        if (!extension_loaded('curl')) {
            throw new StorageException('PHP cURL extension ไม่พร้อมใช้งาน');
        }
        $this->url = rtrim((string) config('supabase.url'), '/');
        $this->key = (string) config('supabase.service_key');
        $this->timeout = (int) config('supabase.timeout', 25);
    }

    public static function isConfigured(): bool
    {
        $url = rtrim((string) config('supabase.url', ''), '/');
        return preg_match('#^https://[a-z0-9.-]+$#i', $url) === 1 && strlen((string) config('supabase.service_key', '')) > 20;
    }

    // ------------------------------------------------------------ objects
    public function upload(string $bucket, string $path, string $localFile, string $mime, bool $upsert = false): array
    {
        $this->assertBucket($bucket);
        $this->assertPath($path);
        $body = file_get_contents($localFile);
        if ($body === false) {
            throw new StorageException('อ่านไฟล์ชั่วคราวไม่สำเร็จ');
        }
        $res = $this->request('POST', 'object/' . $bucket . '/' . $this->encodePath($path), $body, [
            'Content-Type: ' . $mime,
            'x-upsert: ' . ($upsert ? 'true' : 'false'),
            'cache-control: 31536000',
        ]);
        $this->expectOk($res, 'อัปโหลดไฟล์');
        return ['bucket' => $bucket, 'path' => $path, 'size' => strlen($body), 'mime' => $mime];
    }

    /** @param string[] $paths */
    public function delete(string $bucket, array $paths): void
    {
        $this->assertBucket($bucket);
        foreach ($paths as $p) {
            $this->assertPath($p);
        }
        $res = $this->requestJson('DELETE', 'object/' . $bucket, ['prefixes' => array_values($paths)]);
        $this->expectOk($res, 'ลบไฟล์');
    }

    /** ดาวน์โหลดเนื้อไฟล์ (ใช้กับ private bucket เมื่อจำเป็นต้อง proxy) */
    public function download(string $bucket, string $path): string
    {
        $this->assertBucket($bucket);
        $this->assertPath($path);
        $res = $this->request('GET', 'object/authenticated/' . $bucket . '/' . $this->encodePath($path));
        $this->expectOk($res, 'ดาวน์โหลดไฟล์');
        return $res['body'];
    }

    public function publicUrl(string $bucket, string $path): string
    {
        $this->assertBucket($bucket);
        $this->assertPath($path);
        return $this->url . '/storage/v1/object/public/' . $bucket . '/' . $this->encodePath($path);
    }

    /** ต้องตรวจสิทธิ์ผู้ใช้ก่อนเรียกเสมอ */
    public function signedUrl(string $bucket, string $path, ?int $ttl = null): string
    {
        $this->assertBucket($bucket);
        $this->assertPath($path);
        $ttl = max(30, min(3600, $ttl ?? (int) config('supabase.signed_url_ttl', 300)));
        $res = $this->requestJson('POST', 'object/sign/' . $bucket . '/' . $this->encodePath($path), ['expiresIn' => $ttl]);
        $this->expectOk($res, 'สร้าง Signed URL');
        $signed = (string) ($res['json']['signedURL'] ?? $res['json']['signedUrl'] ?? '');
        if ($signed === '') {
            throw new StorageException('Supabase ไม่ส่ง Signed URL กลับมา');
        }
        return str_starts_with($signed, 'http') ? $signed : $this->url . '/storage/v1' . $signed;
    }

    // ------------------------------------------------------------ buckets
    public function listBuckets(): array
    {
        $res = $this->request('GET', 'bucket');
        $this->expectOk($res, 'อ่านรายการ bucket');
        return is_array($res['json']) ? $res['json'] : [];
    }

    /** สร้าง/อัปเดต bucket ตาม config: public สำหรับรูปสินค้า, private สำหรับหลักฐานโอนเงิน */
    public function ensureBuckets(): array
    {
        $messages = [];
        $existing = [];
        foreach ($this->listBuckets() as $b) {
            $existing[(string) ($b['id'] ?? '')] = $b;
        }
        $mimes = array_keys(FileValidator::IMAGE_TYPES);
        $spec = [
            [(string) config('supabase.public_bucket'), true, (int) config('upload.max_image_bytes', 5242880)],
            [(string) config('supabase.private_bucket'), false, (int) config('upload.max_evidence_bytes', 5242880)],
        ];
        foreach ($spec as [$id, $public, $limit]) {
            $this->assertBucket($id);
            $body = ['public' => $public, 'file_size_limit' => $limit, 'allowed_mime_types' => $mimes];
            if (isset($existing[$id])) {
                $this->expectOk($this->requestJson('PUT', 'bucket/' . $id, $body), "อัปเดต bucket {$id}");
                $messages[] = "อัปเดต bucket “{$id}” (" . ($public ? 'Public' : 'Private') . ')';
            } else {
                $this->expectOk($this->requestJson('POST', 'bucket', ['id' => $id, 'name' => $id] + $body), "สร้าง bucket {$id}");
                $messages[] = "สร้าง bucket “{$id}” (" . ($public ? 'Public' : 'Private') . ')';
            }
        }
        return $messages;
    }

    /** ทดสอบครบวงจร: upload → public/private access → signed URL → delete */
    public function selfTest(): array
    {
        $out = [];
        $pub = (string) config('supabase.public_bucket');
        $priv = (string) config('supabase.private_bucket');
        $name = '_healthcheck/' . bin2hex(random_bytes(8)) . '.png';
        $tmp = tempnam(LNW_ROOT . '/storage/tmp', 'hc');
        if ($tmp === false) {
            throw new StorageException('สร้างไฟล์ชั่วคราวไม่สำเร็จ (storage/tmp)');
        }
        file_put_contents($tmp, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='));
        try {
            $this->upload($pub, $name, $tmp, 'image/png');
            $out[] = ['ok', "Upload ไป Public bucket “{$pub}” สำเร็จ"];
            $code = $this->httpStatus($this->publicUrl($pub, $name));
            $out[] = [$code === 200 ? 'ok' : 'error', "Public URL ตอบกลับ HTTP {$code}"];

            $this->upload($priv, $name, $tmp, 'image/png');
            $out[] = ['ok', "Upload ไป Private bucket “{$priv}” สำเร็จ"];
            $code = $this->httpStatus($this->publicUrl($priv, $name));
            $out[] = [$code !== 200 ? 'ok' : 'error', "Private bucket ต้องเข้าถึงแบบ Public ไม่ได้ (HTTP {$code})"];

            $code = $this->httpStatus($this->signedUrl($priv, $name, 60));
            $out[] = [$code === 200 ? 'ok' : 'error', "Signed URL (60 วินาที) ตอบกลับ HTTP {$code}"];
        } finally {
            try {
                $this->delete($pub, [$name]);
                $this->delete($priv, [$name]);
                $out[] = ['ok', 'ลบไฟล์ทดสอบเรียบร้อย'];
            } catch (Throwable $e) {
                $out[] = ['error', 'ลบไฟล์ทดสอบไม่สำเร็จ: ' . $e->getMessage()];
            }
            @unlink($tmp);
        }
        return $out;
    }

    public static function makePath(string $prefix, string $ext): string
    {
        $prefix = trim(preg_replace('/[^a-z0-9\/_-]/', '', strtolower($prefix)) ?? '', '/');
        return ($prefix !== '' ? $prefix . '/' : '') . date('Y/m') . '/' . bin2hex(random_bytes(16)) . '.' . $ext;
    }

    // ------------------------------------------------------------ internals
    private function headers(array $extra = []): array
    {
        $h = ['apikey: ' . $this->key];
        if (str_starts_with($this->key, 'eyJ')) { // legacy JWT service_role key
            $h[] = 'Authorization: Bearer ' . $this->key;
        }
        return array_merge($h, $extra);
    }

    private function requestJson(string $method, string $path, array $data): array
    {
        return $this->request($method, $path, json_encode($data, JSON_UNESCAPED_SLASHES), ['Content-Type: application/json']);
    }

    private function request(string $method, string $path, ?string $body = null, array $headers = []): array
    {
        $ch = curl_init($this->url . '/storage/v1/' . ltrim($path, '/'));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $this->headers($headers),
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        unset($ch);
        if ($response === false) {
            throw new StorageException('เชื่อมต่อ Supabase ไม่สำเร็จ: ' . $error);
        }
        return ['status' => $status, 'body' => (string) $response, 'json' => json_decode((string) $response, true)];
    }

    private function httpStatus(string $url): int
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        ]);
        curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        unset($ch);
        return $code;
    }

    private function expectOk(array $res, string $operation): void
    {
        if ($res['status'] >= 200 && $res['status'] < 300) {
            return;
        }
        $msg = is_array($res['json']) ? (string) ($res['json']['message'] ?? $res['json']['error'] ?? '') : '';
        throw new StorageException("{$operation} ไม่สำเร็จ (HTTP {$res['status']})" . ($msg !== '' ? ": {$msg}" : ''));
    }

    private function assertBucket(string $bucket): void
    {
        if (preg_match('/^[a-z0-9][a-z0-9_-]{2,62}$/', $bucket) !== 1) {
            throw new StorageException('ชื่อ bucket ไม่ถูกต้อง');
        }
    }

    private function assertPath(string $path): void
    {
        if ($path === '' || strlen($path) > 255 || str_contains($path, '..') || preg_match('#^[A-Za-z0-9/_.-]+$#', $path) !== 1) {
            throw new StorageException('Path ของไฟล์ไม่ถูกต้อง');
        }
    }

    private function encodePath(string $path): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $path)));
    }
}