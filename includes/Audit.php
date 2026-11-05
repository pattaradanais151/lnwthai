<?php
/**
 * Lnwthai — Append-only audit log (secrets are redacted)
 * Path: includes/Audit.php
 */
declare(strict_types=1);
defined('LNW_ROOT') || exit;

final class Audit
{
    private const SENSITIVE_KEYS = '/pass|secret|token|key|csrf|credential|hash|otp|cookie/i';

    public static function log(
        string $action,
        ?string $targetType = null,
        int|string|null $targetId = null,
        string $description = '',
        array $details = [],
        ?int $actorId = null,
        ?string $context = null
    ): void {
        try {
            $actorId ??= Auth::id();
            $label = null;
            $roles = null;
            if ($actorId !== null) {
                $label = DB::value('SELECT email FROM users WHERE id = ?', [$actorId]);
                $roles = implode(',', array_column(Rbac::roles($actorId), 'slug'));
            }
            $ctx = $context ?? (in_array(LNW_CONTEXT, ['admin', 'customer', 'install'], true) ? LNW_CONTEXT : 'system');
            DB::run(
                'INSERT INTO audit_logs (actor_user_id, actor_label, actor_roles, context, action, target_type, target_id, description, details, ip, user_agent, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $actorId, $label, $roles, $ctx,
                    mb_substr($action, 0, 100),
                    $targetType !== null ? mb_substr($targetType, 0, 50) : null,
                    $targetId !== null ? mb_substr((string) $targetId, 0, 64) : null,
                    mb_substr($description, 0, 500),
                    $details ? json_encode(self::redact($details), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) : null,
                    client_ip(), user_agent(), now(),
                ]
            );
        } catch (Throwable $e) {
            error_log('Audit log failure: ' . $e->getMessage());
        }
    }

    public static function redact(array $data): array
    {
        foreach ($data as $k => $v) {
            if (is_string($k) && preg_match(self::SENSITIVE_KEYS, $k) === 1) {
                $data[$k] = '[REDACTED]';
            } elseif (is_array($v)) {
                $data[$k] = self::redact($v);
            }
        }
        return $data;
    }
}