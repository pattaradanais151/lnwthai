-- =====================================================================
-- Lnwthai — MySQL / MariaDB Schema (Phase 1 · v1.0.0)
-- Path   : database/schema.sql
-- Target : MySQL 5.7+ / 8.x, MariaDB 10.3+ · InnoDB · utf8mb4
-- Import : phpMyAdmin → เลือกฐานข้อมูล if0_xxx_lnwthai → Import
-- ไม่มี CREATE DATABASE/USE เพราะ InfinityFree กำหนดชื่อ DB ให้
-- ไม่มีบัญชี Owner ในไฟล์นี้ — สร้างผ่าน /install เท่านั้น
-- =====================================================================
SET NAMES utf8mb4;
SET time_zone = '+07:00';
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- RBAC
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS roles (
  id          SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug        VARCHAR(50)  NOT NULL,
  name        VARCHAR(100) NOT NULL,
  name_en     VARCHAR(100) NOT NULL,
  role_level  SMALLINT UNSIGNED NOT NULL,
  is_staff    TINYINT(1) NOT NULL DEFAULT 0,
  is_system   TINYINT(1) NOT NULL DEFAULT 0,
  description VARCHAR(255) NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_roles_slug (slug),
  KEY idx_roles_level (role_level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS permissions (
  id           SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug         VARCHAR(80)  NOT NULL,
  name         VARCHAR(120) NOT NULL,
  name_en      VARCHAR(120) NOT NULL,
  group_key    VARCHAR(50)  NOT NULL,
  is_sensitive TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = มอบได้โดย Owner เท่านั้น',
  description  VARCHAR(255) NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_permissions_slug (slug),
  KEY idx_permissions_group (group_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
  id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  account_type         ENUM('customer','staff') NOT NULL DEFAULT 'customer',
  email                VARCHAR(190) NOT NULL,
  username             VARCHAR(50)  NULL,
  display_name         VARCHAR(100) NOT NULL,
  phone                VARCHAR(30)  NULL,
  password_hash        VARCHAR(255) NOT NULL,
  status               ENUM('active','suspended','disabled') NOT NULL DEFAULT 'active',
  status_reason        VARCHAR(255) NULL,
  owner_lock           TINYINT(1) NULL DEFAULT NULL COMMENT '1 = Owner; UNIQUE ⇒ มี Owner ได้คนเดียว',
  must_change_password TINYINT(1) NOT NULL DEFAULT 0,
  locale               ENUM('th','en') NOT NULL DEFAULT 'th',
  last_login_at        DATETIME NULL,
  last_login_ip        VARCHAR(45) NULL,
  password_changed_at  DATETIME NULL,
  created_by           INT UNSIGNED NULL,
  created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email),
  UNIQUE KEY uq_users_username (username),
  UNIQUE KEY uq_users_owner (owner_lock),
  KEY idx_users_type_status (account_type, status),
  KEY idx_users_created (created_at),
  CONSTRAINT fk_users_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_roles (
  user_id     INT UNSIGNED NOT NULL,
  role_id     SMALLINT UNSIGNED NOT NULL,
  assigned_by INT UNSIGNED NULL,
  assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, role_id),
  KEY idx_user_roles_role (role_id),
  CONSTRAINT fk_ur_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_ur_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE RESTRICT,
  CONSTRAINT fk_ur_assigned_by FOREIGN KEY (assigned_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS role_permissions (
  role_id       SMALLINT UNSIGNED NOT NULL,
  permission_id SMALLINT UNSIGNED NOT NULL,
  granted_by    INT UNSIGNED NULL,
  granted_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (role_id, permission_id),
  KEY idx_rp_permission (permission_id),
  CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE,
  CONSTRAINT fk_rp_permission FOREIGN KEY (permission_id) REFERENCES permissions (id) ON DELETE CASCADE,
  CONSTRAINT fk_rp_granted_by FOREIGN KEY (granted_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_permissions (
  user_id       INT UNSIGNED NOT NULL,
  permission_id SMALLINT UNSIGNED NOT NULL,
  effect        ENUM('allow','deny') NOT NULL,
  granted_by    INT UNSIGNED NULL,
  granted_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, permission_id),
  KEY idx_up_permission (permission_id),
  CONSTRAINT fk_up_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_up_permission FOREIGN KEY (permission_id) REFERENCES permissions (id) ON DELETE CASCADE,
  CONSTRAINT fk_up_granted_by FOREIGN KEY (granted_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_sessions (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id          INT UNSIGNED NOT NULL,
  context          ENUM('customer','admin') NOT NULL,
  token_hash       CHAR(64) NOT NULL,
  ip               VARCHAR(45) NULL,
  user_agent       VARCHAR(255) NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_activity_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at       DATETIME NOT NULL,
  revoked_at       DATETIME NULL,
  revoked_by       INT UNSIGNED NULL,
  revoke_reason    VARCHAR(100) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sessions_token (token_hash),
  KEY idx_sessions_user (user_id, context, revoked_at),
  KEY idx_sessions_expires (expires_at),
  CONSTRAINT fk_sessions_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_sessions_revoked_by FOREIGN KEY (revoked_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  context         ENUM('customer','admin','install','register') NOT NULL,
  identifier_hash CHAR(64) NOT NULL COMMENT 'HMAC-SHA256 ของอีเมล/ชื่อผู้ใช้',
  ip              VARCHAR(45) NOT NULL,
  user_id         INT UNSIGNED NULL,
  success         TINYINT(1) NOT NULL DEFAULT 0,
  reason          VARCHAR(50) NULL,
  user_agent      VARCHAR(255) NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_la_identifier (context, identifier_hash, created_at),
  KEY idx_la_ip (ip, created_at),
  KEY idx_la_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Catalog
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS games (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name              VARCHAR(120) NOT NULL,
  name_en           VARCHAR(120) NULL,
  slug              VARCHAR(140) NOT NULL,
  short_description VARCHAR(255) NULL,
  description       TEXT NULL,
  icon_path         VARCHAR(255) NULL,
  cover_path        VARCHAR(255) NULL,
  is_active         TINYINT(1) NOT NULL DEFAULT 1,
  is_featured       TINYINT(1) NOT NULL DEFAULT 0,
  sort_order        INT NOT NULL DEFAULT 0,
  created_by        INT UNSIGNED NULL,
  updated_by        INT UNSIGNED NULL,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_games_slug (slug),
  KEY idx_games_active (is_active, sort_order),
  KEY idx_games_featured (is_featured, is_active),
  CONSTRAINT fk_games_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_games_updated_by FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categories (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  game_id     INT UNSIGNED NULL,
  name        VARCHAR(120) NOT NULL,
  name_en     VARCHAR(120) NULL,
  slug        VARCHAR(140) NOT NULL,
  description VARCHAR(255) NULL,
  is_active   TINYINT(1) NOT NULL DEFAULT 1,
  sort_order  INT NOT NULL DEFAULT 0,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_categories_game_slug (game_id, slug),
  KEY idx_categories_active (is_active, sort_order),
  CONSTRAINT fk_categories_game FOREIGN KEY (game_id) REFERENCES games (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS products (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  game_id           INT UNSIGNED NOT NULL,
  category_id       INT UNSIGNED NULL,
  sku               VARCHAR(40)  NOT NULL,
  slug              VARCHAR(190) NOT NULL,
  title             VARCHAR(200) NOT NULL,
  title_en          VARCHAR(200) NULL,
  short_description VARCHAR(300) NULL,
  description       TEXT NULL,
  platform          VARCHAR(50)  NULL,
  server_region     VARCHAR(100) NULL,
  level_rank        VARCHAR(100) NULL,
  items_summary     TEXT NULL COMMENT 'Skins / Items / Characters',
  attributes        JSON NULL COMMENT 'Additional details (key/value)',
  price             DECIMAL(12,2) NOT NULL,
  compare_at_price  DECIMAL(12,2) NULL,
  status            ENUM('draft','active','hidden','sold_out','archived') NOT NULL DEFAULT 'draft',
  is_featured       TINYINT(1) NOT NULL DEFAULT 0,
  is_special_offer  TINYINT(1) NOT NULL DEFAULT 0,
  view_count        INT UNSIGNED NOT NULL DEFAULT 0,
  published_at      DATETIME NULL,
  created_by        INT UNSIGNED NULL,
  updated_by        INT UNSIGNED NULL,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_products_sku (sku),
  UNIQUE KEY uq_products_slug (slug),
  KEY idx_products_listing (status, game_id, price),
  KEY idx_products_latest (status, created_at),
  KEY idx_products_featured (is_featured, status),
  KEY idx_products_offer (is_special_offer, status),
  KEY idx_products_category (category_id),
  CONSTRAINT fk_products_game FOREIGN KEY (game_id) REFERENCES games (id) ON DELETE RESTRICT,
  CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE SET NULL,
  CONSTRAINT fk_products_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_products_updated_by FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_images (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id   INT UNSIGNED NOT NULL,
  bucket       VARCHAR(63)  NOT NULL,
  storage_path VARCHAR(255) NOT NULL,
  public_url   VARCHAR(500) NOT NULL,
  alt_text     VARCHAR(200) NULL,
  sort_order   INT NOT NULL DEFAULT 0,
  is_primary   TINYINT(1) NOT NULL DEFAULT 0,
  width        INT UNSIGNED NULL,
  height       INT UNSIGNED NULL,
  size_bytes   INT UNSIGNED NULL,
  mime_type    VARCHAR(50) NULL,
  uploaded_by  INT UNSIGNED NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pi_object (bucket, storage_path),
  KEY idx_pi_product (product_id, sort_order),
  CONSTRAINT fk_pi_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
  CONSTRAINT fk_pi_uploaded_by FOREIGN KEY (uploaded_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Orders & Inventory
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS orders (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_number   VARCHAR(30) NOT NULL,
  user_id        INT UNSIGNED NOT NULL,
  status         ENUM('pending_payment','awaiting_verification','paid','delivered','completed','cancelled','expired','refunded') NOT NULL DEFAULT 'pending_payment',
  payment_status ENUM('pending','submitted','under_review','verified','rejected','refunded') NOT NULL DEFAULT 'pending',
  subtotal       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  discount_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  grand_total    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  currency       CHAR(3) NOT NULL DEFAULT 'THB',
  customer_note  VARCHAR(500) NULL,
  admin_note     TEXT NULL,
  expires_at     DATETIME NULL,
  paid_at        DATETIME NULL,
  delivered_at   DATETIME NULL,
  cancelled_at   DATETIME NULL,
  refunded_at    DATETIME NULL,
  verified_by    INT UNSIGNED NULL,
  ip             VARCHAR(45) NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_orders_number (order_number),
  KEY idx_orders_user (user_id, created_at),
  KEY idx_orders_status (status, created_at),
  KEY idx_orders_payment (payment_status, created_at),
  KEY idx_orders_expires (status, expires_at),
  CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT,
  CONSTRAINT fk_orders_verified_by FOREIGN KEY (verified_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS game_accounts (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id        INT UNSIGNED NOT NULL,
  status            ENUM('available','reserved','sold','disabled') NOT NULL DEFAULT 'available',
  secret_payload    TEXT NOT NULL COMMENT 'AES-256-GCM base64(nonce|tag|ciphertext) ของ JSON {username,password,email,notes}',
  key_version       SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  public_note       VARCHAR(255) NULL COMMENT 'ข้อมูลที่ไม่เป็นความลับเท่านั้น',
  internal_note     VARCHAR(500) NULL,
  reserved_order_id INT UNSIGNED NULL,
  reserved_until    DATETIME NULL,
  sold_order_id     INT UNSIGNED NULL,
  sold_at           DATETIME NULL,
  disabled_reason   VARCHAR(255) NULL,
  created_by        INT UNSIGNED NULL,
  updated_by        INT UNSIGNED NULL,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ga_product_status (product_id, status),
  KEY idx_ga_status_reserved (status, reserved_until),
  KEY idx_ga_reserved_order (reserved_order_id),
  KEY idx_ga_sold_order (sold_order_id),
  CONSTRAINT fk_ga_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE RESTRICT,
  CONSTRAINT fk_ga_reserved_order FOREIGN KEY (reserved_order_id) REFERENCES orders (id) ON DELETE SET NULL,
  CONSTRAINT fk_ga_sold_order FOREIGN KEY (sold_order_id) REFERENCES orders (id) ON DELETE RESTRICT,
  CONSTRAINT fk_ga_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_ga_updated_by FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_items (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id        INT UNSIGNED NOT NULL,
  product_id      INT UNSIGNED NOT NULL,
  game_account_id INT UNSIGNED NULL,
  product_title   VARCHAR(200) NOT NULL COMMENT 'snapshot',
  game_name       VARCHAR(120) NULL COMMENT 'snapshot',
  unit_price      DECIMAL(12,2) NOT NULL,
  quantity        SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  line_total      DECIMAL(12,2) NOT NULL,
  delivery_status ENUM('pending','delivered','revoked') NOT NULL DEFAULT 'pending',
  delivered_at    DATETIME NULL,
  delivered_by    INT UNSIGNED NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_oi_order (order_id),
  KEY idx_oi_product (product_id),
  KEY idx_oi_account (game_account_id),
  CONSTRAINT fk_oi_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
  CONSTRAINT fk_oi_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE RESTRICT,
  CONSTRAINT fk_oi_account FOREIGN KEY (game_account_id) REFERENCES game_accounts (id) ON DELETE RESTRICT,
  CONSTRAINT fk_oi_delivered_by FOREIGN KEY (delivered_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payment_submissions (
  id                     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id               INT UNSIGNED NOT NULL,
  user_id                INT UNSIGNED NOT NULL,
  amount                 DECIMAL(12,2) NOT NULL,
  method                 VARCHAR(50) NOT NULL,
  payer_name             VARCHAR(120) NULL,
  payer_bank             VARCHAR(80) NULL,
  transferred_at         DATETIME NULL,
  reference_no           VARCHAR(100) NULL,
  evidence_bucket        VARCHAR(63) NOT NULL,
  evidence_path          VARCHAR(255) NOT NULL,
  evidence_mime          VARCHAR(50) NOT NULL,
  evidence_size          INT UNSIGNED NOT NULL,
  evidence_sha256        CHAR(64) NOT NULL COMMENT 'ตรวจจับสลิปซ้ำ',
  status                 ENUM('submitted','under_review','verified','rejected') NOT NULL DEFAULT 'submitted',
  bank_statement_checked TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Admin ยืนยันว่าตรวจยอดในบัญชีธนาคารจริงแล้ว',
  reviewed_by            INT UNSIGNED NULL,
  review_started_at      DATETIME NULL,
  reviewed_at            DATETIME NULL,
  review_note            VARCHAR(500) NULL,
  reject_reason          VARCHAR(255) NULL,
  created_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ps_object (evidence_bucket, evidence_path),
  KEY idx_ps_order (order_id, created_at),
  KEY idx_ps_status (status, created_at),
  KEY idx_ps_sha (evidence_sha256),
  KEY idx_ps_reference (reference_no),
  CONSTRAINT fk_ps_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE RESTRICT,
  CONSTRAINT fk_ps_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT,
  CONSTRAINT fk_ps_reviewed_by FOREIGN KEY (reviewed_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS refunds (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id     INT UNSIGNED NOT NULL,
  amount       DECIMAL(12,2) NOT NULL,
  method       VARCHAR(50) NOT NULL,
  reference_no VARCHAR(100) NULL,
  reason       VARCHAR(500) NOT NULL,
  status       ENUM('pending','completed','cancelled') NOT NULL DEFAULT 'pending',
  processed_by INT UNSIGNED NULL,
  processed_at DATETIME NULL,
  created_by   INT UNSIGNED NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_refunds_order (order_id),
  KEY idx_refunds_status (status, created_at),
  CONSTRAINT fk_refunds_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE RESTRICT,
  CONSTRAINT fk_refunds_processed_by FOREIGN KEY (processed_by) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_refunds_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_status_history (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id            INT UNSIGNED NOT NULL,
  from_status         VARCHAR(30) NULL,
  to_status           VARCHAR(30) NOT NULL,
  from_payment_status VARCHAR(30) NULL,
  to_payment_status   VARCHAR(30) NULL,
  note                VARCHAR(500) NULL,
  actor_type          ENUM('customer','admin','system') NOT NULL,
  changed_by          INT UNSIGNED NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_osh_order (order_id, created_at),
  CONSTRAINT fk_osh_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
  CONSTRAINT fk_osh_changed_by FOREIGN KEY (changed_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Content & System
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
  setting_key   VARCHAR(100) NOT NULL,
  setting_value MEDIUMTEXT NULL,
  value_type    ENUM('string','text','html','bool','int','json') NOT NULL DEFAULT 'string',
  group_key     VARCHAR(50) NOT NULL DEFAULT 'general',
  is_public     TINYINT(1) NOT NULL DEFAULT 1,
  updated_by    INT UNSIGNED NULL,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (setting_key),
  KEY idx_settings_group (group_key),
  CONSTRAINT fk_settings_updated_by FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS banners (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title        VARCHAR(200) NOT NULL,
  subtitle     VARCHAR(300) NULL,
  image_bucket VARCHAR(63) NULL,
  image_path   VARCHAR(255) NULL,
  image_url    VARCHAR(500) NULL,
  link_url     VARCHAR(500) NULL,
  placement    ENUM('hero','announcement','promo') NOT NULL DEFAULT 'hero',
  is_active    TINYINT(1) NOT NULL DEFAULT 1,
  starts_at    DATETIME NULL,
  ends_at      DATETIME NULL,
  sort_order   INT NOT NULL DEFAULT 0,
  created_by   INT UNSIGNED NULL,
  updated_by   INT UNSIGNED NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_banners_active (placement, is_active, sort_order),
  CONSTRAINT fk_banners_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_banners_updated_by FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  actor_user_id INT UNSIGNED NULL,
  actor_label   VARCHAR(190) NULL COMMENT 'snapshot อีเมล ณ เวลาที่ทำรายการ',
  actor_roles   VARCHAR(255) NULL,
  context       ENUM('admin','customer','system','install') NOT NULL DEFAULT 'admin',
  action        VARCHAR(100) NOT NULL,
  target_type   VARCHAR(50) NULL,
  target_id     VARCHAR(64) NULL,
  description   VARCHAR(500) NULL,
  details       JSON NULL,
  ip            VARCHAR(45) NULL,
  user_agent    VARCHAR(255) NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_audit_actor (actor_user_id, created_at),
  KEY idx_audit_action (action, created_at),
  KEY idx_audit_target (target_type, target_id),
  KEY idx_audit_created (created_at),
  CONSTRAINT fk_audit_actor FOREIGN KEY (actor_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- SEED DATA (idempotent: INSERT IGNORE)
-- =====================================================================
INSERT IGNORE INTO roles (slug, name, name_en, role_level, is_staff, is_system, description) VALUES
('owner',       'เจ้าของระบบ',          'Owner',       100, 1, 1, 'สิทธิ์สูงสุด มีได้คนเดียว ไม่สามารถถูกแก้ไขโดยผู้อื่น'),
('super_admin', 'ผู้ดูแลระบบระดับสูง', 'Super Admin',  80, 1, 1, 'จัดการระบบและผู้ดูแลระดับต่ำกว่า'),
('admin',       'ผู้ดูแลร้าน',          'Admin',        60, 1, 1, 'จัดการร้านค้าตามสิทธิ์'),
('staff',       'พนักงาน',              'Staff',        40, 1, 1, 'ทำงานเฉพาะส่วนที่ได้รับอนุญาต'),
('customer',    'ลูกค้า',               'Customer',     10, 0, 1, 'ซื้อสินค้าและจัดการบัญชีตนเอง');

INSERT IGNORE INTO permissions (slug, name, name_en, group_key, is_sensitive) VALUES
('dashboard.view',   'ดูแดชบอร์ด',               'View dashboard',         'dashboard', 0),
('product.view',     'ดูสินค้า',                 'View products',          'product',   0),
('product.create',   'เพิ่มสินค้า',              'Create products',        'product',   0),
('product.edit',     'แก้ไขสินค้า',              'Edit products',          'product',   0),
('product.delete',   'ลบสินค้า',                 'Delete products',        'product',   0),
('game.manage',      'จัดการเกม',                'Manage games',           'catalog',   0),
('category.manage',  'จัดการหมวดหมู่',           'Manage categories',      'catalog',   0),
('inventory.view',   'ดูคลังบัญชีเกม',           'View inventory',         'inventory', 0),
('inventory.manage', 'จัดการคลังบัญชีเกม',       'Manage inventory',       'inventory', 0),
('inventory.reveal', 'ดูข้อมูลลับของบัญชีเกม',   'Reveal account secrets', 'inventory', 1),
('order.view',       'ดูคำสั่งซื้อ',             'View orders',            'order',     0),
('order.manage',     'จัดการคำสั่งซื้อ',         'Manage orders',          'order',     0),
('payment.view',     'ดูการชำระเงิน',            'View payments',          'payment',   0),
('payment.verify',   'ยืนยันการชำระเงิน',        'Verify payments',        'payment',   0),
('payment.reject',   'ปฏิเสธการชำระเงิน',        'Reject payments',        'payment',   0),
('payment.refund',   'บันทึกการคืนเงิน',         'Manage refunds',         'payment',   1),
('customer.view',    'ดูข้อมูลลูกค้า',           'View customers',         'customer',  0),
('customer.manage',  'จัดการลูกค้า',             'Manage customers',       'customer',  0),
('admin.view',       'ดูรายชื่อผู้ดูแล',         'View admins',            'admin',     0),
('admin.manage',     'จัดการผู้ดูแล',            'Manage admins',          'admin',     1),
('role.manage',      'จัดการบทบาทและสิทธิ์',     'Manage roles',           'admin',     1),
('session.manage',   'เพิกถอนเซสชันผู้ดูแล',     'Revoke sessions',        'admin',     1),
('settings.manage',  'ตั้งค่าเว็บไซต์',          'Website settings',       'system',    1),
('banner.manage',    'จัดการแบนเนอร์',           'Manage banners',         'system',    0),
('report.view',      'ดูรายงาน',                 'View reports',           'report',    0),
('audit.view',       'ดูบันทึกกิจกรรม',          'View audit logs',        'system',    1),
('system.check',     'ตรวจสอบระบบ',              'System diagnostics',     'system',    1);

-- Owner: ทุกสิทธิ์ (โค้ดให้ Owner ครบทุกสิทธิ์อยู่แล้ว ส่วนนี้เพื่อให้ข้อมูลสอดคล้อง)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p WHERE r.slug = 'owner';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.slug = 'super_admin' AND p.slug NOT IN ('role.manage', 'system.check');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.slug = 'admin' AND p.slug IN (
  'dashboard.view','product.view','product.create','product.edit','product.delete',
  'game.manage','category.manage','inventory.view','inventory.manage',
  'order.view','order.manage','payment.view','payment.verify','payment.reject',
  'customer.view','customer.manage','banner.manage','report.view','admin.view');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.slug = 'staff' AND p.slug IN (
  'dashboard.view','product.view','inventory.view','order.view','payment.view','customer.view');

INSERT IGNORE INTO settings (setting_key, setting_value, value_type, group_key, is_public) VALUES
('store_name',             'Lnwthai',                                   'string', 'general', 1),
('store_tagline',          'Premium Digital Game Account Store',        'string', 'general', 1),
('store_description',      'ร้านจำหน่ายบัญชีเกมออนไลน์ ตรวจสอบได้ ปลอดภัย', 'text', 'general', 1),
('logo_path',              '',                                          'string', 'general', 1),
('contact_email',          '',                                          'string', 'contact', 1),
('contact_phone',          '',                                          'string', 'contact', 1),
('contact_line',           '',                                          'string', 'contact', 1),
('social_facebook',        '',                                          'string', 'social',  1),
('social_tiktok',          '',                                          'string', 'social',  1),
('payment_instructions',   '',                                          'text',   'payment', 1),
('payment_promptpay_id',   '',                                          'string', 'payment', 1),
('payment_bank_name',      '',                                          'string', 'payment', 1),
('payment_account_name',   '',                                          'string', 'payment', 1),
('payment_account_number', '',                                          'string', 'payment', 1),
('order_expiry_minutes',   '30',                                        'int',    'order',   0),
('announcement_enabled',   '0',                                         'bool',   'content', 1),
('announcement_text',      '',                                          'text',   'content', 1),
('terms_of_service',       '',                                          'html',   'legal',   1),
('privacy_policy',         '',                                          'html',   'legal',   1),
('refund_policy',          '',                                          'html',   'legal',   1),
('account_transfer_policy','',                                          'html',   'legal',   1),
('cookie_policy',          '',                                          'html',   'legal',   1),
('maintenance_mode',       '0',                                         'bool',   'system',  0);