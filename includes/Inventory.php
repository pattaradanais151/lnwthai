<?php
/**
 * Lnwthai — Inventory service (บัญชีเกม)
 * Path: includes/Inventory.php
 * - ข้อมูลลับเข้ารหัสด้วย Vault เสมอ · ไม่มีการเก็บ plaintext ใน DB, session หรือ log
 * - เปลี่ยนสถานะด้วย SELECT ... FOR UPDATE เพื่อกันชนกับ Checkout (3B)
 * - ผู้เรียกต้องตรวจ Permission ก่อน (inventory.manage / inventory.reveal)
 */
declare(strict_types=1);
defined('LNW_ROOT') || exit;

final class Inventory
{
    public const MANAGEABLE = ['available', 'disabled'];

    /** ตรวจและจัดรูปข้อมูลลับ · $partial = true ⇒ ช่องว่างหมายถึง "คงค่าเดิม" */
    public static function secretFromInput(array $in, bool $partial = false): array
    {
        $get = static fn(string $k): string => is_string($in[$k] ?? null) ? $in[$k] : '';
        $username = trim($get('username'));
        $password = $get('password'); // ไม่ trim: ช่องว่างอาจเป็นส่วนหนึ่งของรหัสผ่าน
        $email = trim($get('email'));
        $notes = trim(str_replace(["\r\n", "\r"], "\n", $get('notes')));

        if (!$partial || $username !== '') {
            if ($username === '' || mb_strlen($username) > 190) {
                throw new DomainException('ชื่อผู้ใช้/ไอดีของบัญชีเกมต้องยาว 1–190 ตัวอักษร');
            }
        }
        if (!$partial || $password !== '') {
            if ($password === '' || mb_strlen($password) > 200 || preg_match('/[\x00-\x1F\x7F]/', $password) === 1) {
                throw new DomainException('รหัสผ่านของบัญชีเกมต้องยาว 1–200 ตัวอักษร และไม่มีอักขระควบคุม');
            }
        }
        if ($email !== '' && (mb_strlen($email) > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false)) {
            throw new DomainException('อีเมลของบัญชีเกมไม่ถูกต้อง');
        }
        if (mb_strlen($notes) > 2000) {
            throw new DomainException('ข้อมูลเพิ่มเติมสำหรับผู้ซื้อยาวเกิน 2,000 ตัวอักษร');
        }
        return array_filter(
            ['username' => $username, 'password' => $password, 'email' => $email, 'notes' => $notes],
            static fn(string $v): bool => $v !== ''
        );
    }

    private static function assertVault(): void
    {
        if (!Vault::ready()) {
            throw new DomainException('ระบบเข้ารหัสยังไม่พร้อม: ' . Vault::error());
        }
    }

    private static function assertNotes(string $public, string $internal): void
    {
        if (mb_strlen($public) > 255) {
            throw new DomainException('หมายเหตุสาธารณะยาวเกิน 255 ตัวอักษร');
        }
        if (mb_strlen($internal) > 500) {
            throw new DomainException('หมายเหตุภายในยาวเกิน 500 ตัวอักษร');
        }
    }

    /** @return int[] id ของบัญชีที่เพิ่ม */
    public static function addMany(int $productId, array $secrets, string $internalNote, int $actorId, string $publicNote = ''): array
    {
        self::assertVault();
        self::assertNotes($publicNote, $internalNote);
        if ($secrets === []) {
            throw new DomainException('ไม่มีข้อมูลบัญชีเกม');
        }

        $ids = DB::transaction(static function () use ($productId, $secrets, $internalNote, $publicNote, $actorId): array {
            $ids = [];
            $now = now();
            foreach ($secrets as $secret) {
                $id = DB::insert(
                    "INSERT INTO game_accounts (product_id, status, secret_payload, key_version, public_note, internal_note,
                                                created_by, updated_by, created_at, updated_at)
                     VALUES (?, 'available', '', 1, ?, ?, ?, ?, ?, ?)",
                    [$productId, $publicNote !== '' ? $publicNote : null, $internalNote !== '' ? $internalNote : null,
                     $actorId, $actorId, $now, $now]
                );
                $enc = Vault::encrypt($secret, Vault::aad($id));
                DB::run('UPDATE game_accounts SET secret_payload = ?, key_version = ? WHERE id = ?', [$enc['payload'], $enc['version'], $id]);
                $ids[] = $id;
            }
            return $ids;
        });

        self::syncProductStatus($productId);
        Audit::log('inventory.add', 'product', $productId, 'เพิ่มบัญชีเกม ' . count($ids) . ' รายการ', ['account_ids' => $ids], $actorId);
        return $ids;
    }

    public static function update(int $accountId, int $productId, array $partialSecret, bool $clearOptional,
                                  string $publicNote, string $internalNote, int $actorId): void
    {
        self::assertNotes($publicNote, $internalNote);
        $loginChanged = $partialSecret !== [] || $clearOptional;
        if ($loginChanged) {
            self::assertVault();
        }

        DB::transaction(static function () use ($accountId, $productId, $partialSecret, $clearOptional, $publicNote, $internalNote, $actorId, $loginChanged): void {
            $row = DB::one(
                'SELECT id, status, secret_payload, key_version FROM game_accounts WHERE id = ? AND product_id = ? FOR UPDATE',
                [$accountId, $productId]
            ) ?? throw new DomainException('ไม่พบบัญชีเกม');

            $sets = ['public_note = ?', 'internal_note = ?', 'updated_by = ?'];
            $params = [$publicNote !== '' ? $publicNote : null, $internalNote !== '' ? $internalNote : null, $actorId];

            if ($loginChanged) {
                if (!in_array($row['status'], self::MANAGEABLE, true)) {
                    throw new DomainException('แก้ไขข้อมูลเข้าสู่ระบบได้เฉพาะบัญชีที่ยังไม่ถูกจองหรือขาย');
                }
                try {
                    $current = Vault::decrypt((string) $row['secret_payload'], (int) $row['key_version'], Vault::aad($accountId));
                } catch (RuntimeException) {
                    if (!isset($partialSecret['username'], $partialSecret['password'])) {
                        throw new DomainException('ข้อมูลเดิมถอดรหัสไม่ได้ กรุณากรอกทั้งชื่อผู้ใช้และรหัสผ่านใหม่');
                    }
                    $current = [];
                }
                if ($clearOptional) {
                    unset($current['email'], $current['notes']);
                }
                $enc = Vault::encrypt(array_merge($current, $partialSecret), Vault::aad($accountId));
                array_push($sets, 'secret_payload = ?', 'key_version = ?', 'secret_purged_at = NULL');
                array_push($params, $enc['payload'], $enc['version']);
            }
            DB::run('UPDATE game_accounts SET ' . implode(', ', $sets) . ' WHERE id = ?', [...$params, $accountId]);
        });

        Audit::log('inventory.update', 'game_account', $accountId, 'แก้ไขบัญชีเกม', [
            'product_id' => $productId,
            'login_changed' => $loginChanged,
            'fields' => array_keys($partialSecret),
            'cleared_optional' => $clearOptional,
        ], $actorId);
    }

    public static function setStatus(int $accountId, int $productId, string $to, string $reason, int $actorId): void
    {
        if (!in_array($to, self::MANAGEABLE, true)) {
            throw new DomainException('สถานะไม่ถูกต้อง');
        }
        if (mb_strlen($reason) > 255) {
            throw new DomainException('เหตุผลยาวเกิน 255 ตัวอักษร');
        }
        $from = DB::transaction(static function () use ($accountId, $productId, $to, $reason, $actorId): string {
            $row = DB::one('SELECT status FROM game_accounts WHERE id = ? AND product_id = ? FOR UPDATE', [$accountId, $productId])
                ?? throw new DomainException('ไม่พบบัญชีเกม');
            if (!in_array($row['status'], self::MANAGEABLE, true)) {
                throw new DomainException('บัญชีที่ถูกจองหรือขายแล้วเปลี่ยนสถานะจากหน้านี้ไม่ได้');
            }
            if ($row['status'] !== $to) {
                DB::run(
                    'UPDATE game_accounts SET status = ?, disabled_reason = ?, updated_by = ? WHERE id = ?',
                    [$to, $to === 'disabled' && $reason !== '' ? $reason : null, $actorId, $accountId]
                );
            }
            return (string) $row['status'];
        });
        self::syncProductStatus($productId);
        Audit::log('inventory.status', 'game_account', $accountId, "เปลี่ยนสถานะบัญชีเกม {$from} → {$to}",
            ['product_id' => $productId, 'reason' => $reason], $actorId);
    }

    public static function delete(int $accountId, int $productId, int $actorId): void
    {
        DB::transaction(static function () use ($accountId, $productId): void {
            $row = DB::one('SELECT status FROM game_accounts WHERE id = ? AND product_id = ? FOR UPDATE', [$accountId, $productId])
                ?? throw new DomainException('ไม่พบบัญชีเกม');
            if (!in_array($row['status'], self::MANAGEABLE, true)) {
                throw new DomainException('ลบไม่ได้: บัญชีถูกจองหรือขายแล้ว');
            }
            if (DB::value('SELECT 1 FROM order_items WHERE game_account_id = ? LIMIT 1', [$accountId]) !== null) {
                throw new DomainException('ลบไม่ได้: บัญชีนี้มีประวัติในคำสั่งซื้อ ให้ปิดใช้งานแทน');
            }
            DB::run('DELETE FROM game_accounts WHERE id = ?', [$accountId]);
        });
        self::syncProductStatus($productId);
        Audit::log('inventory.delete', 'game_account', $accountId, 'ลบบัญชีเกม', ['product_id' => $productId], $actorId);
    }

    /** ถอดรหัสให้ Admin ดู — บันทึกทั้ง account_reveals และ audit_logs */
    public static function reveal(int $accountId, int $actorId, string $reason): array
    {
        self::assertVault();
        $row = DB::one(
            'SELECT id, product_id, secret_payload, key_version, secret_purged_at FROM game_accounts WHERE id = ?',
            [$accountId]
        ) ?? throw new DomainException('ไม่พบบัญชีเกม');
        if ($row['secret_purged_at'] !== null || $row['secret_payload'] === '') {
            throw new DomainException('ข้อมูลลับของบัญชีนี้ถูกลบตามนโยบายการเก็บรักษาแล้ว');
        }
        try {
            $secret = Vault::decrypt((string) $row['secret_payload'], (int) $row['key_version'], Vault::aad($accountId));
        } catch (RuntimeException $e) {
            error_log('Reveal failed #' . $accountId . ': ' . $e->getMessage());
            Audit::log('inventory.reveal_failed', 'game_account', $accountId, 'ถอดรหัสบัญชีเกมไม่สำเร็จ', ['product_id' => (int) $row['product_id']], $actorId);
            throw new DomainException('ถอดรหัสไม่สำเร็จ (key ไม่ตรง หรือข้อมูลไม่ได้เข้ารหัส/ถูกแก้ไข)');
        }
        DB::run(
            'INSERT INTO account_reveals (game_account_id, order_id, user_id, context, reason, ip, user_agent, created_at)
             VALUES (?, NULL, ?, ?, ?, ?, ?, ?)',
            [$accountId, $actorId, 'admin', mb_substr($reason, 0, 255), client_ip(), user_agent(), now()]
        );
        Audit::log('inventory.reveal', 'game_account', $accountId, 'เปิดดูข้อมูลลับของบัญชีเกม',
            ['product_id' => (int) $row['product_id'], 'reason' => $reason], $actorId);
        return $secret;
    }

    public static function counts(int $productId): array
    {
        $out = array_fill_keys(['available', 'reserved', 'sold', 'disabled'], 0);
        foreach (DB::all('SELECT status, COUNT(*) AS n FROM game_accounts WHERE product_id = ? GROUP BY status', [$productId]) as $r) {
            $out[$r['status']] = (int) $r['n'];
        }
        return $out;
    }

    /** สลับ active ↔ sold_out ตามสต็อก (ไม่แตะ draft / hidden / archived) */
    public static function syncProductStatus(int $productId): void
    {
        $available = (int) DB::value("SELECT COUNT(*) FROM game_accounts WHERE product_id = ? AND status = 'available'", [$productId]);
        if ($available === 0) {
            DB::run("UPDATE products SET status = 'sold_out' WHERE id = ? AND status = 'active'", [$productId]);
        } else {
            DB::run("UPDATE products SET status = 'active' WHERE id = ? AND status = 'sold_out'", [$productId]);
        }
    }
}