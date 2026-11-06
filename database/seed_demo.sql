-- =====================================================================
-- Lnwthai — DEMO DATA สำหรับทดสอบหน้าร้าน (ห้ามใช้บน Production จริง)
-- Path: database/seed_demo.sql
-- ทุกแถวขึ้นต้นด้วย demo-/DEMO- · game_accounts ใช้ข้อความ placeholder ไม่มีข้อมูลจริง
-- ลบทิ้ง: ดู CLEANUP ท้ายไฟล์
-- =====================================================================
SET NAMES utf8mb4;
SET time_zone = '+07:00';

INSERT IGNORE INTO games (name, name_en, slug, short_description, is_active, is_featured, sort_order) VALUES
('Demo Legends', 'Demo Legends', 'demo-legends', 'เกม MOBA ตัวอย่างสำหรับทดสอบระบบ', 1, 1, 1),
('Demo Quest',   'Demo Quest',   'demo-quest',   'เกม RPG โลกเปิดตัวอย่าง',          1, 1, 2),
('Demo Shooter', 'Demo Shooter', 'demo-shooter', 'เกมยิงแท็กติกตัวอย่าง',            1, 1, 3),
('Demo Racing',  'Demo Racing',  'demo-racing',  'เกมแข่งรถตัวอย่าง',                1, 0, 4);

INSERT IGNORE INTO products
(game_id, sku, slug, title, short_description, description, platform, server_region, level_rank, items_summary, attributes,
 price, compare_at_price, status, is_featured, is_special_offer, published_at, created_at)
VALUES
((SELECT id FROM games WHERE slug='demo-legends'), 'DEMO-LG-001', 'demo-legends-mythic-124-skins',
 'Demo Legends แรงก์ Mythic สกิน 124 ชิ้น', 'ฮีโร่ครบเกือบทุกตัว สกินลิมิเต็ดหลายชิ้น',
 'บัญชีตัวอย่างสำหรับทดสอบหน้าร้าน\nผูกอีเมลที่โอนสิทธิ์ได้\nไม่มีประวัติการแบน',
 'Mobile', 'TH', 'Mythic', 'ฮีโร่ 98 ตัว\nสกิน 124 ชิ้น\nสกินลิมิเต็ด 9 ชิ้น',
 JSON_OBJECT('ผูกอีเมล', 'โอนได้', 'ประวัติการแบน', 'ไม่มี', 'เปลี่ยนชื่อได้', 'ได้'),
 2590.00, 3200.00, 'active', 1, 1, NOW(), DATE_SUB(NOW(), INTERVAL 1 DAY)),
((SELECT id FROM games WHERE slug='demo-legends'), 'DEMO-LG-002', 'demo-legends-diamond-starter',
 'Demo Legends แรงก์ Diamond เริ่มต้นเล่น', 'เหมาะสำหรับผู้เริ่มต้น',
 'บัญชีตัวอย่างสำหรับทดสอบ', 'Mobile', 'TH', 'Diamond', 'ฮีโร่ 40 ตัว, สกิน 22 ชิ้น',
 JSON_OBJECT('ผูกอีเมล', 'โอนได้'), 690.00, NULL, 'active', 1, 0, NOW(), DATE_SUB(NOW(), INTERVAL 3 DAY)),
((SELECT id FROM games WHERE slug='demo-legends'), 'DEMO-LG-003', 'demo-legends-limited-15',
 'Demo Legends สกินลิมิเต็ด 15 ชิ้น', 'สายสะสมสกิน',
 'บัญชีตัวอย่างสำหรับทดสอบ', 'Mobile', 'TH', 'Legend', 'สกินลิมิเต็ด 15 ชิ้น',
 NULL, 1890.00, NULL, 'active', 0, 0, NOW(), DATE_SUB(NOW(), INTERVAL 6 DAY)),
((SELECT id FROM games WHERE slug='demo-quest'), 'DEMO-QS-001', 'demo-quest-ar60-12-five-star',
 'Demo Quest AR 60 ตัวละคร 5 ดาว 12 ตัว', 'บัญชีสายเก็บตัวละคร',
 'บัญชีตัวอย่างสำหรับทดสอบ\nอาวุธ 5 ดาว 6 ชิ้น', 'PC / Mobile', 'Asia', 'AR 60',
 'ตัวละคร 5 ดาว 12 ตัว\nอาวุธ 5 ดาว 6 ชิ้น',
 JSON_OBJECT('ผูกอีเมล', 'โอนได้', 'Primogem', '12,000'),
 3490.00, 3990.00, 'active', 1, 1, NOW(), DATE_SUB(NOW(), INTERVAL 2 DAY)),
((SELECT id FROM games WHERE slug='demo-quest'), 'DEMO-QS-002', 'demo-quest-ar45-starter',
 'Demo Quest AR 45 เริ่มต้น', 'ราคาประหยัด',
 'บัญชีตัวอย่างสำหรับทดสอบ', 'PC / Mobile', 'Asia', 'AR 45', 'ตัวละคร 5 ดาว 3 ตัว',
 NULL, 890.00, NULL, 'active', 0, 0, NOW(), DATE_SUB(NOW(), INTERVAL 8 DAY)),
((SELECT id FROM games WHERE slug='demo-shooter'), 'DEMO-SH-001', 'demo-shooter-immortal-30-skins',
 'Demo Shooter แรงก์ Immortal สกินอาวุธ 30', 'บัญชีสายแข่งขัน',
 'บัญชีตัวอย่างสำหรับทดสอบ', 'PC', 'AP', 'Immortal', 'สกินอาวุธ 30 ชิ้น\nเอเจนต์ครบ',
 JSON_OBJECT('ผูกอีเมล', 'โอนได้'), 4200.00, NULL, 'active', 1, 0, NOW(), DATE_SUB(NOW(), INTERVAL 4 DAY)),
((SELECT id FROM games WHERE slug='demo-shooter'), 'DEMO-SH-002', 'demo-shooter-lv80',
 'Demo Shooter บัญชี Lv.80', 'พร้อมลงแรงก์',
 'บัญชีตัวอย่างสำหรับทดสอบ', 'PC', 'AP', 'Lv.80', 'สกินอาวุธ 4 ชิ้น',
 NULL, 590.00, 790.00, 'active', 0, 1, NOW(), DATE_SUB(NOW(), INTERVAL 10 DAY)),
((SELECT id FROM games WHERE slug='demo-racing'), 'DEMO-RC-001', 'demo-racing-s-class-25',
 'Demo Racing รถ S-Class 25 คัน', 'สายสะสมรถ',
 'บัญชีตัวอย่างสำหรับทดสอบ', 'Mobile', 'Global', 'Lv.120', 'รถ S-Class 25 คัน',
 NULL, 1290.00, NULL, 'active', 0, 0, NOW(), DATE_SUB(NOW(), INTERVAL 12 DAY)),
((SELECT id FROM games WHERE slug='demo-racing'), 'DEMO-RC-002', 'demo-racing-full-collection',
 'Demo Racing บัญชีสะสมครบ', 'ขายแล้ว — ใช้ทดสอบตัวกรองสินค้าหมด',
 'บัญชีตัวอย่างสำหรับทดสอบ', 'Mobile', 'Global', 'Lv.200', 'รถครบทุกคัน',
 NULL, 2990.00, NULL, 'sold_out', 0, 0, NOW(), DATE_SUB(NOW(), INTERVAL 15 DAY));

-- สต็อกทดสอบ 2 ชิ้นต่อสินค้า (ยกเว้น DEMO-RC-002) · payload เป็น placeholder ไม่ใช่ข้อมูลจริง
INSERT INTO game_accounts (product_id, status, secret_payload, internal_note)
SELECT p.id, 'available', 'DEMO-PLACEHOLDER', 'DEMO — ไม่มีข้อมูลจริง ห้ามขาย'
FROM products p
CROSS JOIN (SELECT 1 AS n UNION ALL SELECT 2) x
WHERE p.sku LIKE 'DEMO-%' AND p.sku <> 'DEMO-RC-002'
  AND NOT EXISTS (SELECT 1 FROM game_accounts ga WHERE ga.product_id = p.id);

-- =====================================================================
-- CLEANUP (รันเมื่อต้องการลบข้อมูลทดสอบทั้งหมด — ต้องรันก่อนมีคำสั่งซื้อจริง)
-- DELETE ga FROM game_accounts ga JOIN products p ON p.id = ga.product_id WHERE p.sku LIKE 'DEMO-%';
-- DELETE FROM products WHERE sku LIKE 'DEMO-%';
-- DELETE FROM games WHERE slug LIKE 'demo-%';
-- =====================================================================