<?php
/**
 * Lnwthai — Login / registration rate limiting (DB-backed, no cron needed)
 * Path: includes/RateLimiter.php
 */
declare(strict_types=1);
defined('LNW_ROOT') || exit;

final class RateLimiter
{
    public static function identifierHash(string $identifier): string
    {
        return hash_hmac('sha256', mb_strtolower(trim($identifier)), (string) config('security.app_key', ''));
    }

    public static function isLocked(string $context, string $identifier, string $ip): bool
    {
        $window = (int) config('security.login_window', 900);
        $since = date('Y-m-d H:i:s', time() - $window);
        $hash = self::identifierHash($identifier);

        $failsForIdentifier = (int) DB::value(
            "SELECT COUNT(*) FROM login_attempts
             WHERE context = ? AND identifier_hash = ? AND success = 0
               AND created_at > GREATEST(?, COALESCE((
                   SELECT MAX(created_at) FROM login_attempts
                   WHERE context = ? AND identifier_hash = ? AND success = 1), '1970-01-01'))",
            [$context, $hash, $since, $context, $hash]
        );
        $failsForIp = (int) DB::value(
            'SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND success = 0 AND created_at > ?',
            [$ip, $since]
        );

        return $failsForIdentifier >= (int) config('security.login_max_attempts', 5)
            || $failsForIp >= (int) config('security.ip_max_attempts', 30);
    }

    /** นับทุกครั้ง (ใช้กับการสมัครสมาชิก / ตัวติดตั้ง) */
    public static function tooManyActions(string $context, string $ip, int $max, int $windowSeconds): bool
    {
        return (int) DB::value(
            'SELECT COUNT(*) FROM login_attempts WHERE context = ? AND ip = ? AND created_at > ?',
            [$context, $ip, date('Y-m-d H:i:s', time() - $windowSeconds)]
        ) >= $max;
    }

    public static function record(string $context, string $identifier, string $ip, bool $success, ?int $userId, string $reason = ''): void
    {
        DB::run(
            'INSERT INTO login_attempts (context, identifier_hash, ip, user_id, success, reason, user_agent, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$context, self::identifierHash($identifier), $ip, $userId, $success ? 1 : 0, mb_substr($reason, 0, 50), user_agent(), now()]
        );
        if (random_int(1, 100) === 1) { // lazy cleanup (ไม่มี cron บน InfinityFree)
            DB::run('DELETE FROM login_attempts WHERE created_at < ?', [date('Y-m-d H:i:s', time() - 90 * 86400)]);
            DB::run('DELETE FROM user_sessions WHERE expires_at < ?', [date('Y-m-d H:i:s', time() - 30 * 86400)]);
        }
    }
}