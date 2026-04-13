-- CT-Orderlauf schema (MySQL 8)
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(64) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(255) NULL,
    role VARCHAR(16) NOT NULL DEFAULT 'editor' COMMENT 'admin|editor|order',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS locations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_locations_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS suppliers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NULL,
    phone VARCHAR(64) NULL,
    fax VARCHAR(64) NULL,
    mobile VARCHAR(64) NULL,
    order_type ENUM('mail', 'webshop') NOT NULL DEFAULT 'mail',
    email_template MEDIUMTEXT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_suppliers_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS supplier_delivery_days (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    supplier_id INT UNSIGNED NOT NULL,
    weekday TINYINT UNSIGNED NOT NULL COMMENT '1=Mon .. 7=Sun',
    CONSTRAINT fk_sdd_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE,
    UNIQUE KEY uq_supplier_weekday (supplier_id, weekday)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    unit VARCHAR(64) NOT NULL DEFAULT '',
    location_id INT UNSIGNED NOT NULL,
    min_stock INT NULL,
    max_stock INT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_items_location FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE RESTRICT,
    INDEX idx_items_location (location_id),
    INDEX idx_items_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS item_supplier (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    item_id INT UNSIGNED NOT NULL,
    supplier_id INT UNSIGNED NOT NULL,
    priority INT NOT NULL DEFAULT 0 COMMENT 'higher = preferred',
    CONSTRAINT fk_is_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    CONSTRAINT fk_is_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE,
    UNIQUE KEY uq_item_supplier (item_id, supplier_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    key_name VARCHAR(128) NOT NULL UNIQUE,
    value TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- Default admin: admin / admin123 (change in production)
INSERT INTO users (username, password_hash, email, role) VALUES (
    'admin',
    '$2y$12$T6uKJd52pBDfB5YvRN9Cf.sdknFBtTbgs6tsJWnshJDr6zL900zEG',
    'admin@example.com',
    'admin'
) ON DUPLICATE KEY UPDATE username = username;

INSERT INTO settings (key_name, value) VALUES
    ('order_cc_email', ''),
    ('order_email_subject_template', 'Bestellung {{COMPANY}} {{TARGET_DATE}}'),
    ('app_name', 'CT-Orderlauf'),
    ('dev_mode', '0'),
    ('dev_email', ''),
    ('company_name', ''),
    ('company_street', ''),
    ('company_city', ''),
    ('company_phone', ''),
    ('company_fax', '')
ON DUPLICATE KEY UPDATE key_name = key_name;
