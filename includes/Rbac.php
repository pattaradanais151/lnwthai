<?php
/**
 * Lnwthai — Role-Based Access Control + anti privilege-escalation rules
 * Path: includes/Rbac.php
 */
declare(strict_types=1);
defined('LNW_ROOT') || exit;

final class Rbac
{
    private static array $roleCache = [];
    private static array $permCache = [];
    private static array $ownerCache = [];
    private static ?array $allPermissions = null;

    public static function roles(int $userId): array
    {
        return self::$roleCache[$userId] ??= DB::all(
            'SELECT r.id, r.slug, r.name, r.name_en, r.role_level, r.is_staff
             FROM user_roles ur JOIN roles r ON r.id = ur.role_id
             WHERE ur.user_id = ? ORDER BY r.role_level DESC',
            [$userId]
        );
    }

    /** ระดับสูงสุดของผู้ใช้ — บทบาท owner นับเฉพาะเมื่อเป็น Owner ที่ถูกล็อกจริง */
    public static function level(int $userId): int
    {
        $max = 0;
        foreach (self::roles($userId) as $role) {
            if ($role['slug'] === 'owner' && !self::isOwner($userId)) {
                continue;
            }
            $max = max($max, (int) $role['role_level']);
        }
        return $max;
    }

    public static function isOwner(int $userId): bool
    {
        if (!isset(self::$ownerCache[$userId])) {
            $row = DB::one('SELECT owner_lock, account_type FROM users WHERE id = ?', [$userId]);
            self::$ownerCache[$userId] = $row !== null
                && (int) $row['owner_lock'] === 1
                && $row['account_type'] === 'staff'
                && in_array('owner', array_column(self::roles($userId), 'slug'), true);
        }
        return self::$ownerCache[$userId];
    }

    public static function isStaff(int $userId): bool
    {
        if (DB::value('SELECT account_type FROM users WHERE id = ?', [$userId]) !== 'staff') {
            return false;
        }
        foreach (self::roles($userId) as $role) {
            if ((int) $role['is_staff'] === 1) {
                return true;
            }
        }
        return false;
    }

    public static function allPermissions(): array
    {
        return self::$allPermissions ??= DB::all(
            'SELECT id, slug, name, name_en, group_key, is_sensitive FROM permissions ORDER BY group_key, slug'
        );
    }

    /**
     * Effective permissions = role perms ∪ user allow − user deny
     * Owner (owner_lock = 1) = ทั้งหมด · บทบาท owner ที่ไม่ถูกล็อกจะไม่ให้สิทธิ์ใด ๆ
     */
    public static function permissions(int $userId): array
    {
        if (isset(self::$permCache[$userId])) {
            return self::$permCache[$userId];
        }
        if (!self::isStaff($userId)) {
            return self::$permCache[$userId] = [];
        }
        if (self::isOwner($userId)) {
            return self::$permCache[$userId] = array_fill_keys(array_column(self::allPermissions(), 'slug'), true);
        }

        $rows = DB::all(
            "SELECT DISTINCT p.slug FROM user_roles ur
             JOIN roles r ON r.id = ur.role_id AND r.is_staff = 1 AND r.slug <> 'owner'
             JOIN role_permissions rp ON rp.role_id = ur.role_id
             JOIN permissions p ON p.id = rp.permission_id
             WHERE ur.user_id = ?",
            [$userId]
        );
        $set = array_fill_keys(array_column($rows, 'slug'), true);

        $overrides = DB::all(
            'SELECT p.slug, up.effect FROM user_permissions up JOIN permissions p ON p.id = up.permission_id WHERE up.user_id = ?',
            [$userId]
        );
        foreach ($overrides as $o) {
            if ($o['effect'] === 'allow') {
                $set[$o['slug']] = true;
            }
        }
        foreach ($overrides as $o) {
            if ($o['effect'] === 'deny') {
                unset($set[$o['slug']]);
            }
        }
        return self::$permCache[$userId] = $set;
    }

    public static function can(int $userId, string $permission): bool
    {
        return isset(self::permissions($userId)[$permission]);
    }

    /** จัดการบัญชีผู้อื่น (สถานะ/บทบาท/สิทธิ์) */
    public static function canManageUser(int $actorId, int $targetId): bool
    {
        if ($actorId === $targetId || self::isOwner($targetId)) {
            return false;
        }
        $type = DB::value('SELECT account_type FROM users WHERE id = ?', [$targetId]);
        if ($type === 'customer') {
            return self::can($actorId, 'customer.manage');
        }
        if ($type !== 'staff') {
            return false;
        }
        if (self::isOwner($actorId)) {
            return true;
        }
        return self::can($actorId, 'admin.manage') && self::level($actorId) > self::level($targetId);
    }

    public static function canAssignRole(int $actorId, array $role): bool
    {
        if (($role['slug'] ?? '') === 'owner' || (int) ($role['is_staff'] ?? 0) !== 1) {
            return false;
        }
        if (self::isOwner($actorId)) {
            return true;
        }
        return self::can($actorId, 'admin.manage') && (int) $role['role_level'] < self::level($actorId);
    }

    public static function canGrantPermission(int $actorId, array $permission): bool
    {
        if (self::isOwner($actorId)) {
            return true;
        }
        if ((int) ($permission['is_sensitive'] ?? 1) === 1) {
            return false;
        }
        return self::can($actorId, 'admin.manage') && self::can($actorId, (string) $permission['slug']);
    }

    public static function canEditRole(int $actorId, array $role): bool
    {
        if (($role['slug'] ?? '') === 'owner') {
            return false;
        }
        if (self::isOwner($actorId)) {
            return true;
        }
        return self::can($actorId, 'role.manage') && (int) $role['role_level'] < self::level($actorId);
    }

    public static function flush(?int $userId = null): void
    {
        if ($userId === null) {
            self::$roleCache = self::$permCache = self::$ownerCache = [];
            self::$allPermissions = null;
            return;
        }
        unset(self::$roleCache[$userId], self::$permCache[$userId], self::$ownerCache[$userId]);
    }
}