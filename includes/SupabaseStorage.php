<?php
/**
 * Lnwthai — Supabase Storage REST client (server-side only)
 * Path: includes/SupabaseStorage.php
 * Transport: PHP cURL (หลัก) → PHP HTTPS stream (สำรองเมื่อไม่มี cURL)
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
        if (self::transport() === null) {
            throw new StorageException('ไม่มีช่องทาง HTTPS: ต้องเปิด PHP cURL หรือ allow_url_fopen + openssl');
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

    /** @return 'curl'|'stream'|null */
    public static function transport(): ?string
    {
        if (extension_loaded('curl') && function_exists('curl_init')) {
            return 'curl';
        }
        if (filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOL)
            && extension_loaded('openssl')
            && in_array('https', stream_get_wrappers(), true)) {
            return 'stream';
        }
        return null;
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
        $messages[] = 'ช่องทางเชื่อมต่อ: ' . (self::transport() === 'curl' ? 'PHP cURL' : 'PHP HTTPS stream');
        return $messages;
    }

    /** ทดสอบครบวงจร: upload → public/private access → signed URL → delete */
    public function selfTest(): array
    {
        $out = [['ok', 'ช่องทางเชื่อมต่อ: ' . (self::transport() === 'curl' ? 'PHP cURL' : 'PHP HTTPS stream')]];
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
    private function authHeaders(array $extra = []): array
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
        $res = $this->send($method, $this->url . '/storage/v1/' . ltrim($path, '/'), $body, $this->authHeaders($headers), $this->timeout);
        $res['json'] = json_decode($res['body'], true);
        return $res;
    }

    /** ใช้ตรวจ URL สาธารณะ/Signed URL โดยไม่ส่ง key */
    private function httpStatus(string $url): int
    {
        return $this->send('GET', $url, null, [], 15)['status'];
    }

    /** @return array{status:int, body:string} */
    private function send(string $method, string $url, ?string $body, array $headers, int $timeout): array
    {
        if (!str_starts_with($url, 'https://')) {
            throw new StorageException('อนุญาตเฉพาะ HTTPS');
        }
        return self::transport() === 'curl'
            ? $this->sendCurl($method, $url, $body, $headers, $timeout)
            : $this->sendStream($method, $url, $body, $headers, $timeout);
    }

    private function sendCurl(string $method, string $url, ?string $body, array $headers, int $timeout): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => $timeout,
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
        unset($ch); // PHP 8.5: curl_close() deprecated
        if ($response === false) {
            throw new StorageException('เชื่อมต่อ Supabase ไม่สำเร็จ (cURL): ' . $this->scrub($error));
        }
        return ['status' => $status, 'body' => (string) $response];
    }

    private function sendStream(string $method, string $url, ?string $body, array $headers, int $timeout): array
    {
        $headers[] = 'Connection: close';
        $http = [
            'method'           => $method,
            'header'           => implode("\r\n", $headers),
            'timeout'          => $timeout,
            'ignore_errors'    => true,   // อ่าน body ของ 4xx/5xx ได้
            'follow_location'  => 0,
            'max_redirects'    => 0,
            'protocol_version' => 1.1,
        ];
        if ($body !== null) {
            $http['content'] = $body;
        }
        $ctx = stream_context_create([
            'http' => $http,
            'ssl'  => ['verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false],
        ]);

        $fp = @fopen($url, 'rb', false, $ctx);
        if ($fp === false) {
            $err = error_get_last()['message'] ?? 'unknown error';
            throw new StorageException('เชื่อมต่อ Supabase ไม่สำเร็จ (stream): ' . $this->scrub($err));
        }
        $meta = stream_get_meta_data($fp);
        $response = stream_get_contents($fp);
        fclose($fp);

        $status = 0;
        foreach ((array) ($meta['wrapper_data'] ?? []) as $line) {
            if (is_string($line) && preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m) === 1) {
                $status = (int) $m[1];
            }
        }
        return ['status' => $status, 'body' => $response === false ? '' : $response];
    }

    /** ตัด query string (เช่น token ของ Signed URL) ออกจากข้อความ error */
    private function scrub(string $message): string
    {
        return (string) preg_replace('/\?[^\s"\']*/', '?…', $message);
    }

    private function expectOk(array $res, string $operation): void
    {
        if ($res['status'] >= 200 && $res['status'] < 300) {
            return;
        }
        $msg = is_array($res['json'] ?? null) ? (string) ($res['json']['message'] ?? $res['json']['error'] ?? '') : '';
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