-- Gemeinsame Sammlung neuer Artikel aus Freitext (Bestellung + Inventur).
-- Liegt serverseitig, damit alle Nutzer in denselben Topf einzahlen.
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
