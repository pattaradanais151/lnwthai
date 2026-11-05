<?php
/**
 * Lnwthai — Authentication (customer & admin contexts, DB-tracked sessions)
 * Path: includes/Auth.php
 */
declare(strict_types=1);
defined('LNW_ROOT') || exit;

final class Auth
{
    private static ?array $user = null;
    private static bool $resolved = false;
    private static ?string $dummyHash = null;

    public static function hashPassword(string $plain): string
    {
        return password_hash($plain, PASSWORD_DEFAULT);
    }

    private static function dummyHash(): string
    {
        return self::$dummyHash ??= password_hash('lnwthai-timing-equalizer', PASSWORD_DEFAULT);
    }

    /** @return array{ok: bool, error?: string} */
    public static function attempt(string $identifier, string $password, string $context): array
    {
        $context = $context === 'admin' ? 'admin' : 'customer';
        $identifier = trim($identifier);
        $ip = client_ip();
        $generic = $context === 'admin' ? 'ข้อมูลเข้าสู่ระบบไม่ถูกต้อง' : 'อีเมลหรือรหัสผ่านไม่ถูกต้อง';

        if ($identifier === '' || $password === '' || strlen($password) > 4096 || mb_strlen($identifier) > 190) {
            return ['ok' => false, 'error' => $generic];
        }
        if (RateLimiter::isLocked($context, $identifier, $ip)) {
            $minutes = (int) ceil((int) config('security.login_window', 900) / 60);
            return ['ok' => false, 'error' => "มีการพยายามเข้าสู่ระบบผิดหลายครั้ง กรุณารอประมาณ {$minutes} นาทีแล้วลองใหม่"];
        }

        $byEmail = str_contains($identifier, '@');
        $user = null;
        if ($byEmail || $context === 'admin') {
            $user = DB::one(
                'SELECT * FROM users WHERE ' . ($byEmail ? 'email' : 'username') . ' = ? AND account_type = ? LIMIT 1',
                [$byEmail ? mb_strtolower($identifier) : $identifier, $context === 'admin' ? 'staff' : 'customer']
            );
        }

        if ($user === null) {
            password_verify($password, self::dummyHash());
            RateLimiter::record($context, $identifier, $ip, false, null, 'unknown_user');
            return ['ok' => false, 'error' => $generic];
        }

        $uid = (int) $user['id'];
        if (!password_verify($password, (string) $user['password_hash'])) {
            RateLimiter::record($context, $identifier, $ip, false, $uid, 'bad_password');
            if ($context === 'admin') {
                Audit::log('auth.login_failed', 'user', $uid, 'เข้าสู่ระบบหลังบ้านไม่สำเร็จ: รหัสผ่านไม่ถูกต้อง', [], null, 'admin');
            }
            return ['ok' => false, 'error' => $generic];
        }
        if ($user['status'] !== 'active') {
            RateLimiter::record($context, $identifier, $ip, false, $uid, 'inactive');
            if ($context === 'admin') {
                Audit::log('auth.login_blocked', 'user', $uid, 'บัญชีถูกระงับพยายามเข้าสู่ระบบ', [], null, 'admin');
            }
            return ['ok' => false, 'error' => 'บัญชีนี้ถูกระงับการใช้งาน กรุณาติดต่อผู้ดูแลระบบ'];
        }
        if ($context === 'admin' && !Rbac::isStaff($uid)) {
            RateLimiter::record($context, $identifier, $ip, false, $uid, 'no_staff_role');
            return ['ok' => false, 'error' => $generic];
        }

        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
            DB::run('UPDATE users SET password_hash = ? WHERE id = ?', [self::hashPassword($password), $uid]);
        }
        RateLimiter::record($context, $identifier, $ip, true, $uid, 'ok');
        self::startSession($user, $context);
        return ['ok' => true];
    }

    public static function startSession(array $user, string $context): void
    {
        Session::regenerate();
        Csrf::rotate();
        $token = bin2hex(random_bytes(32));
        $now = now();
        $absolute = (int) config($context === 'admin' ? 'session.admin_absolute' : 'session.customer_absolute', 86400);

        DB::run(
            'INSERT INTO user_sessions (user_id, context, token_hash, ip, user_agent, created_at, last_activity_at, expires_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [(int) $user['id'], $context, hash('sha256', $token), client_ip(), user_agent(), $now, $now, date('Y-m-d H:i:s', time() + $absolute)]
        );
        DB::run('UPDATE users SET last_login_at = ?, last_login_ip = ? WHERE id = ?', [$now, client_ip(), (int) $user['id']]);

        $_SESSION['auth'] = ['uid' => (int) $user['id'], 'token' => $token, 'ctx' => $context];
        self::$user = null;
        self::$resolved = false;

        if ($context === 'admin') {
            Audit::log('auth.login', 'user', (int) $user['id'], 'เข้าสู่ระบบหลังบ้าน', [], (int) $user['id'], 'admin');
        }
    }

    public static function user(): ?array
    {
        if (self::$resolved) {
            return self::$user;
        }
        self::$resolved = true;

        $auth = $_SESSION['auth'] ?? null;
        if (!is_array($auth) || empty($auth['token']) || !is_string($auth['token']) || ($auth['ctx'] ?? '') !== Session::context()) {
            return null;
        }

        $row = DB::one(
            'SELECT s.id AS session_id, s.last_activity_at, s.expires_at, s.revoked_at, u.*
             FROM user_sessions s JOIN users u ON u.id = s.user_id
             WHERE s.token_hash = ? AND s.context = ? LIMIT 1',
            [hash('sha256', $auth['token']), Session::context()]
        );

        $idle = (int) config(Session::context() === 'admin' ? 'session.admin_idle' : 'session.customer_idle', 7200);
        $invalid = $row === null
            || $row['revoked_at'] !== null
            || (int) $row['id'] !== (int) ($auth['uid'] ?? 0)
            || $row['status'] !== 'active'
            || strtotime((string) $row['expires_at']) < time()
            || strtotime((string) $row['last_activity_at']) < time() - $idle;

        if ($invalid) {
            if ($row !== null && $row['revoked_at'] === null) {
                DB::run('UPDATE user_sessions SET revoked_at = ?, revoke_reason = ? WHERE id = ?', [now(), 'expired', (int) $row['session_id']]);
            }
            unset($_SESSION['auth']);
            flash('warning', 'เซสชันหมดอายุหรือถูกยกเลิก กรุณาเข้าสู่ระบบอีกครั้ง');
            return null;
        }

        if (time() - strtotime((string) $row['last_activity_at']) > 60) {
            DB::run('UPDATE user_sessions SET last_activity_at = ? WHERE id = ?', [now(), (int) $row['session_id']]);
        }
        unset($row['password_hash']);
        return self::$user = $row;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function id(): ?int
    {
        $u = self::user();
        return $u ? (int) $u['id'] : null;
    }

    public static function logout(): void
    {
        $u = self::user();
        if ($u !== null) {
            DB::run('UPDATE user_sessions SET revoked_at = ?, revoke_reason = ? WHERE id = ? AND revoked_at IS NULL', [now(), 'logout', (int) $u['session_id']]);
            if (Session::context() === 'admin') {
                Audit::log('auth.logout', 'user', (int) $u['id'], 'ออกจากระบบหลังบ้าน');
            }
        }
        Session::destroy();
        self::$user = null;
        self::$resolved = true;
    }

    /** ใช้ใน Phase 3: Owner/Admin เพิกถอนเซสชัน */
    public static function revokeSessions(int $userId, ?int $exceptSessionId, ?int $byUserId, string $reason): int
    {
        $sql = 'UPDATE user_sessions SET revoked_at = ?, revoked_by = ?, revoke_reason = ? WHERE user_id = ? AND revoked_at IS NULL';
        $params = [now(), $byUserId, mb_substr($reason, 0, 100), $userId];
        if ($exceptSessionId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $exceptSessionId;
        }
        return DB::run($sql, $params)->rowCount();
    }

    public static function requireCustomer(): array
    {
        $u = self::user();
        if ($u === null) {
            flash('info', 'กรุณาเข้าสู่ระบบก่อนดำเนินการต่อ');
            redirect(url('login') . '?next=' . rawurlencode((string) ($_SERVER['REQUEST_URI'] ?? '')));
        }
        return $u;
    }

    /** @throws DomainException email_taken */
    public static function registerCustomer(string $email, string $displayName, string $password, ?string $phone): int
    {
        $email = mb_strtolower(trim($email));
        try {
            return (int) DB::transaction(static function () use ($email, $displayName, $password, $phone): int {
                if (DB::value('SELECT id FROM users WHERE email = ? LIMIT 1', [$email]) !== null) {
                    throw new DomainException('email_taken');
                }
                $now = now();
                $id = DB::insert(
                    'INSERT INTO users (account_type, email, display_name, phone, password_hash, status, password_changed_at, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    ['customer', $email, $displayName, $phone ?: null, self::hashPassword($password), 'active', $now, $now, $now]
                );
                $roleId = (int) DB::value('SELECT id FROM roles WHERE slug = ?', ['customer']);
                if ($roleId === 0) {
                    throw new RuntimeException('Customer role missing — import schema.sql');
                }
                DB::run('INSERT INTO user_roles (user_id, role_id, assigned_at) VALUES (?, ?, ?)', [$id, $roleId, $now]);
                return $id;
            });
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                throw new DomainException('email_taken');
            }
            throw $e;
        }
    }
}