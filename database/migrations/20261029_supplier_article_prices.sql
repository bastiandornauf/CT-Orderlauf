-- Preise je Lieferantenartikel mit Historie + Protokoll für Pflegeaktionen (Artikelpflege Phase 2).
-- Rein additiv. Nach 20261029_items_alias_supplier_articles.sql ausführen.

SET NAMES utf8mb4;

-- Jeder Preis ist ein eigener datierter Datensatz; alte Preise bleiben stehen.
-- price_type: known = bekannter Lieferantenpreis (Shop, Liste, manuell), purchase = bestätigter Einkauf.
-- dedupe_key verhindert, dass derselbe Preis/Beleg doppelt eingetragen wird.
CREATE TABLE IF NOT EXISTS supplier_article_prices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    supplier_article_id INT UNSIGNED NOT NULL,
    price_type VARCHAR(16) NOT NULL COMMENT 'known|purchase',
    amount DECIMAL(12,4) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'EUR',
    is_net TINYINT(1) NOT NULL DEFAULT 1,
    per_qty DECIMAL(12,4) NOT NULL DEFAULT 1,
    per_unit VARCHAR(16) NOT NULL COMMENT 'order = je Bestelleinheit, sonst kg|g|l|ml|stk',
    price_date DATE NOT NULL,
    source VARCHAR(16) NOT NULL COMMENT 'manual|shop|pricelist|invoice|import',
    document_ref VARCHAR(128) NULL,
    note VARCHAR(255) NULL,
    dedupe_key CHAR(40) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT UNSIGNED NULL,
    UNIQUE KEY uq_sap_dedupe (dedupe_key),
    INDEX idx_sap_lookup (supplier_article_id, price_type, price_date),
    CONSTRAINT fk_sap_article FOREIGN KEY (supplier_article_id) REFERENCES supplier_articles(id) ON DELETE RESTRICT,
    CONSTRAINT fk_sap_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Nachvollziehbarkeit von Pflegeaktionen (Preis gelöscht, Artikel zusammengeführt, …)
CREATE TABLE IF NOT EXISTS maintenance_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    action VARCHAR(32) NOT NULL,
    item_id INT UNSIGNED NULL,
    detail_json MEDIUMTEXT NULL,
    user_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_ml_item (item_id),
    INDEX idx_ml_action (action, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
