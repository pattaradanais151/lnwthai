-- =====================================================================
-- Lnwthai — Migration 002 (Phase 2 · Storefront)
-- Path: database/migrations/002_phase2_storefront.sql
-- Import หลัง schema.sql · รันซ้ำได้ (INSERT IGNORE) · ไม่เปลี่ยนโครงสร้างตาราง
-- =====================================================================
SET NAMES utf8mb4;

INSERT IGNORE INTO settings (setting_key, setting_value, value_type, group_key, is_public) VALUES
('hero_title',    'บัญชีเกมคุณภาพ ซื้อง่าย ปลอดภัย ตรวจสอบได้', 'string', 'content', 1),
('hero_subtitle', 'ทุกคำสั่งซื้อตรวจสอบการชำระเงินโดยทีมงาน ข้อมูลบัญชีถูกเข้ารหัส และส่งมอบผ่านหน้าบัญชีของคุณเท่านั้น', 'text', 'content', 1),
('shop_per_page', '12', 'int', 'general', 0);