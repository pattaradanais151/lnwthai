<?php
/**
 * Lnwthai — Vault: AES-256-GCM สำหรับข้อมูลลับของบัญชีเกม
 * Path: includes/Vault.php
 * - Key อยู่ในไฟล์แยก (ไม่อยู่ใน DB และไม่อยู่ใน config.php)
 * - Payload = base64( nonce[12] | tag[16] | ciphertext )
 * - AAD ผูกกับ id ของแถว ⇒ สลับ ciphertext ข้ามแถวแล้วถอดรหัสไม่ได้
 * - รองรับหลาย key version เพื่อการหมุนเวียน key
 */
declare(strict_types=1);
defined('LNW_ROOT') || exit;

final class Vault
{
    private const CIPHER = 'aes-256-gcm';
    private const NONCE_LEN = 12;
    private const TAG_LEN = 16;

    private static ?array $keys = null;
    private static ?string $error = null;
    private static bool $loaded = false;

    public static function enabled(): bool
    {
        return config('vault.enabled', false) === true;
    }

    public static function keyFile(): string
    {
        $path = (string) config('vault.key_file', 'config/vault.key.php');
        return preg_match('#^([A-Za-z]:)?[\\\\/]#', $path) === 1 ? $path : LNW_ROOT . '/' . ltrim($path, '/');
    }

    private static function load(): ?array
    {
        if (self::$loaded) {
            return self::$keys;
        }
        self::$loaded = true;

        if (!self::enabled()) {
            self::$error = 'ยังไม่เปิดใช้งาน (vault.enabled = false)';
            return null;
        }
        if (!function_exists('openssl_encrypt') || !in_array(self::CIPHER, openssl_get_cipher_methods(), true)) {
            self::$error = 'เซิร์ฟเวอร์ไม่รองรับ AES-256-GCM';
            return null;
        }
        $file = self::keyFile();
        if (!is_file($file) || !is_readable($file)) {
            self::$error = 'ไม่พบไฟล์ key หรืออ่านไม่ได้';
            return null;
        }
        $data = (static fn(string $f): mixed => require $f)($file);
        if (!is_array($data) || !is_array($data['keys'] ?? null) || !is_int($data['current'] ?? null)) {
            self::$error = 'รูปแบบไฟล์ key ไม่ถูกต้อง';
            return null;
        }
        $keys = [];
        foreach ($data['keys'] as $version => $encoded) {
            $raw = is_string($encoded) ? base64_decode($encoded, true) : false;
            if (!is_int($version) || $version < 1 || $version > 65535 || $raw === false || strlen($raw) !== 32) {
                self::$error = 'key เวอร์ชัน ' . $version . ' ไม่ถูกต้อง (ต้องเป็น base64 ของข้อมูล 32 ไบต์)';
                return null;
            }
            $keys[$version] = $raw;
        }
        if (!isset($keys[$data['current']])) {
            self::$error = 'ไม่พบ key ของเวอร์ชันปัจจุบัน (current)';
            return null;
        }
        self::$error = null;
        return self::$keys = ['current' => $data['current'], 'keys' => $keys];
    }

    public static function ready(): bool
    {
        return self::load() !== null;
    }

    public static function error(): ?string
    {
        self::load();
        return self::$error;
    }

    public static function currentVersion(): ?int
    {
        return self::load()['current'] ?? null;
    }

    /** @return int[] */
    public static function versions(): array
    {
        return array_keys(self::load()['keys'] ?? []);
    }

    public static function aad(int $accountId): string
    {
        return 'lnwthai:game_account:' . $accountId;
    }

    /** @return array{payload: string, version: int} */
    public static function encrypt(array $data, string $aad): array
    {
        $k = self::load() ?? throw new RuntimeException('Vault not ready: ' . self::$error);
        $plain = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $nonce = random_bytes(self::NONCE_LEN);
        $tag = '';
        $cipher = openssl_encrypt($plain, self::CIPHER, $k['keys'][$k['current']], OPENSSL_RAW_DATA, $nonce, $tag, $aad, self::TAG_LEN);
        if ($cipher === false || strlen($tag) !== self::TAG_LEN) {
            throw new RuntimeException('Encryption failed');
        }
        return ['payload' => base64_encode($nonce . $tag . $cipher), 'version' => $k['current']];
    }

    public static function decrypt(string $payload, int $version, string $aad): array
    {
        $k = self::load() ?? throw new RuntimeException('Vault not ready: ' . self::$error);
        if (!isset($k['keys'][$version])) {
            throw new RuntimeException('Unknown key version ' . $version);
        }
        $raw = base64_decode($payload, true);
        if ($raw === false || strlen($raw) <= self::NONCE_LEN + self::TAG_LEN) {
            throw new RuntimeException('Malformed payload');
        }
        $plain = openssl_decrypt(
            substr($raw, self::NONCE_LEN + self::TAG_LEN),
            self::CIPHER,
            $k['keys'][$version],
            OPENSSL_RAW_DATA,
            substr($raw, 0, self::NONCE_LEN),
            substr($raw, self::NONCE_LEN, self::TAG_LEN),
            $aad
        );
        if ($plain === false) {
            throw new RuntimeException('Decryption failed (wrong key, AAD or tampered data)');
        }
        $data = json_decode($plain, true);
        if (!is_array($data)) {
            throw new RuntimeException('Invalid decrypted data');
        }
        return $data;
    }

    /** เข้ารหัส → ถอดรหัส → ตรวจว่า AAD ผิดต้องถอดไม่ได้ */
    public static function selfTest(): bool
    {
        if (!self::ready()) {
            return false;
        }
        try {
            $enc = self::encrypt(['probe' => 'ok'], 'lnwthai:selftest:1');
            if ((self::decrypt($enc['payload'], $enc['version'], 'lnwthai:selftest:1')['probe'] ?? '') !== 'ok') {
                return false;
            }
            try {
                self::decrypt($enc['payload'], $enc['version'], 'lnwthai:selftest:2');
                return false;
            } catch (RuntimeException) {
                return true;
            }
        } catch (Throwable $e) {
            error_log('Vault self-test: ' . $e->getMessage());
            return false;
        }
    }

    public static function keyFileTemplate(string $base64Key): string
    {
        return "<?php\n"
            . "// Lnwthai vault key — ห้าม commit / ห้ามแชร์ / สำรองแยกจากฐานข้อมูล\n"
            . "// ถ้าไฟล์นี้หาย ข้อมูลบัญชีเกมที่เข้ารหัสไว้จะกู้คืนไม่ได้\n"
            . "defined('LNW_ROOT') || exit;\n\n"
            . "return [\n"
            . "    'current' => 1,\n"
            . "    'keys' => [\n"
            . "        1 => '" . $base64Key . "',\n"
            . "    ],\n"
            . "];\n";
    }
}