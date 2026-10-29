-- Alias, Vergleichseinheit und Lieferantenartikel (Artikelpflege Phase 1).
-- Rein additiv: alte App-Versionen ignorieren die neuen Spalten/Tabellen.
-- supplier_articles hängt bewusst an (item_id, supplier_id) und NICHT an item_supplier.id,
-- weil ältere Versionen item_supplier beim Speichern löschen und neu anlegen.
--
-- Einmalig in phpMyAdmin ausführen. Zweites Ausführen bricht bei ADD COLUMN ab (ohne Schaden).

SET NAMES utf8mb4;

ALTER TABLE items
    ADD COLUMN alias VARCHAR(255) NULL COMMENT 'interner Anzeigename; NULL/leer = name' AFTER name,
    ADD COLUMN category VARCHAR(64) NULL COMMENT 'vorgesehen, noch ohne UI' AFTER location_id,
    ADD COLUMN base_unit VARCHAR(8) NULL COMMENT 'kg|l|stk – Vergleichs-/Inventureinheit' AFTER unit,
    ADD COLUMN unit_factor DECIMAL(12,4) NULL COMMENT 'base_unit je 1 unit; NULL = unbekannt' AFTER base_unit,
    ADD COLUMN merged_into_id INT UNSIGNED NULL COMMENT 'Artikel wurde in diesen zusammengeführt' AFTER active,
    ADD INDEX idx_items_alias (alias);

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
