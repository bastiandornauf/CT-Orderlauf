-- CT-Orderlauf schema (MySQL 8)
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(64) NOT NULL UNIQUE,
    display_name VARCHAR(128) NULL COMMENT 'Anzeigename in Bestell-Mails (Platzhalter {{USER}})',
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
    street VARCHAR(255) NULL,
    city VARCHAR(255) NULL,
    order_type ENUM('mail', 'webshop') NOT NULL DEFAULT 'mail',
    email_template MEDIUMTEXT NULL,
    email_subject_template VARCHAR(512) NULL,
    attach_pdf TINYINT(1) NOT NULL DEFAULT 0,
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
    alias VARCHAR(255) NULL COMMENT 'interner Anzeigename; NULL/leer = name',
    unit VARCHAR(64) NOT NULL DEFAULT '',
    base_unit VARCHAR(8) NULL COMMENT 'kg|l|stk – Vergleichs-/Inventureinheit',
    unit_factor DECIMAL(12,4) NULL COMMENT 'base_unit je 1 unit; NULL = unbekannt',
    location_id INT UNSIGNED NOT NULL,
    category VARCHAR(64) NULL COMMENT 'vorgesehen, noch ohne UI',
    sort_order INT NOT NULL DEFAULT 0,
    min_stock INT NULL,
    max_stock INT NULL,
    valuation_price DECIMAL(10,2) NULL COMMENT 'Bewertungspreis pro Einheit fuer Inventur',
    active TINYINT(1) NOT NULL DEFAULT 1,
    merged_into_id INT UNSIGNED NULL COMMENT 'Artikel wurde in diesen zusammengeführt',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_items_location FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE RESTRICT,
    INDEX idx_items_location (location_id),
    INDEX idx_items_name (name),
    INDEX idx_items_alias (alias)
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

-- Konkreter Artikel beim Lieferanten. Hängt an (item_id, supplier_id), nicht an
-- item_supplier.id – ältere App-Versionen legen item_supplier beim Speichern neu an.
CREATE TABLE IF NOT EXISTS supplier_articles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    item_id INT UNSIGNED NOT NULL,
    supplier_id INT UNSIGNED NOT NULL,
    supplier_label VARCHAR(255) NULL COMMENT 'formelle Bestellbezeichnung; NULL = items.name',
    supplier_sku VARCHAR(64) NULL,
    ean VARCHAR(14) NULL,
    brand VARCHAR(128) NULL,
    pack_size DECIMAL(12,4) NULL,
    pack_unit VARCHAR(8) NULL COMMENT 'kg|g|l|ml|stk',
    order_unit VARCHAR(64) NULL COMMENT 'NULL = items.unit',
    is_default TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'wird für Bestellungen bei diesem Lieferanten genutzt',
    active TINYINT(1) NOT NULL DEFAULT 1,
    note VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_sa_item_supplier (item_id, supplier_id),
    INDEX idx_sa_sku (supplier_id, supplier_sku),
    CONSTRAINT fk_sa_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE RESTRICT,
    CONSTRAINT fk_sa_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Gemeinsame Sammlung neuer Artikel aus Freitext (Bestellung + Inventur)
CREATE TABLE IF NOT EXISTS pending_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    dedupe_key VARCHAR(191) NOT NULL COMMENT 'Bezeichnung normalisiert (klein, Leerraum reduziert)',
    name VARCHAR(255) NOT NULL,
    unit VARCHAR(64) NOT NULL DEFAULT '',
    last_quantity VARCHAR(64) NOT NULL DEFAULT '',
    location_id INT UNSIGNED NULL,
    supplier_id INT UNSIGNED NULL,
    source_order TINYINT(1) NOT NULL DEFAULT 0,
    source_inventory TINYINT(1) NOT NULL DEFAULT 0,
    seen_count INT UNSIGNED NOT NULL DEFAULT 1,
    first_seen_at DATETIME NOT NULL,
    last_seen_at DATETIME NOT NULL,
    last_seen_by INT UNSIGNED NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'open' COMMENT 'open|dismissed',
    dismissed_at DATETIME NULL,
    UNIQUE KEY uq_pending_items_key (dedupe_key),
    INDEX idx_pending_items_status (status),
    CONSTRAINT fk_pending_items_location FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL,
    CONSTRAINT fk_pending_items_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
    CONSTRAINT fk_pending_items_user FOREIGN KEY (last_seen_by) REFERENCES users(id) ON DELETE SET NULL
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
