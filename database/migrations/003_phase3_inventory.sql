-- =====================================================================
-- Lnwthai — Migration 003 (Phase 3 · Inventory, Vault, Anti double-sell)
-- Path: database/migrations/003_phase3_inventory.sql
-- เขียนสำหรับ MariaDB 10.3+ (รองรับ IF NOT EXISTS ใน ALTER) — รันซ้ำได้
-- MySQL 8: ลบคำว่า "IF NOT EXISTS" ใน ALTER TABLE ออกแล้วรันครั้งเดียว
-- =====================================================================
SET NAMES utf8mb4;
SET time_zone = '+07:00';

-- 1) กันขายบัญชีซ้ำระดับฐานข้อมูล (ใช้ใน 3B):
--    account_lock = game_account_id ขณะคำสั่งซื้อยังมีผล, NULL เมื่อปล่อยคืน · UNIQUE ⇒ บัญชีหนึ่งอยู่ได้แค่คำสั่งซื้อเดียว
ALTER TABLE order_items
  ADD COLUMN IF NOT EXISTS account_lock INT UNSIGNED NULL DEFAULT NULL
  COMMENT '= game_account_id ขณะคำสั่งซื้อยังมีผล; NULL เมื่อปล่อยคืน' AFTER game_account_id;
ALTER TABLE order_items ADD UNIQUE INDEX IF NOT EXISTS uq_oi_account_lock (account_lock);

-- 2) บันทึกเวลาที่ลบข้อมูลลับตามนโยบายการเก็บรักษา
ALTER TABLE game_accounts
  ADD COLUMN IF NOT EXISTS secret_purged_at DATETIME NULL DEFAULT NULL AFTER key_version;

-- 3) ประวัติการเปิดดูข้อมูลลับ (Admin และลูกค้า)
CREATE TABLE IF NOT EXISTS account_reveals (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  game_account_id INT UNSIGNED NOT NULL,
  order_id        INT UNSIGNED NULL,
  user_id         INT UNSIGNED NULL,
  context         ENUM('admin','customer') NOT NULL,
  reason          VARCHAR(255) NULL,
  ip              VARCHAR(45) NULL,
  user_agent      VARCHAR(255) NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ar_account (game_account_id, created_at),
  KEY idx_ar_user (user_id, created_at),
  CONSTRAINT fk_ar_account FOREIGN KEY (game_account_id) REFERENCES game_accounts (id) ON DELETE CASCADE,
  CONSTRAINT fk_ar_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE SET NULL,
  CONSTRAINT fk_ar_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4) ค่าตั้งต้นสำหรับ 3B
INSERT IGNORE INTO settings (setting_key, setting_value, value_type, group_key, is_public) VALUES
('vault_retention_days', '30', 'int', 'order', 0);

-- 5) ข้อมูล Demo จาก Phase 2 ไม่ได้เข้ารหัส → ปิดไว้ไม่ให้ขาย
UPDATE game_accounts SET status = 'disabled', disabled_reason = 'DEMO placeholder — ไม่ได้เข้ารหัส'
WHERE secret_payload = 'DEMO-PLACEHOLDER' AND status = 'available';
UPDATE products p SET p.status = 'sold_out'
WHERE p.sku LIKE 'DEMO-%' AND p.status = 'active'
  AND NOT EXISTS (SELECT 1 FROM game_accounts ga WHERE ga.product_id = p.id AND ga.status = 'available');