-- Entscheidungen zu Dubletten-Vorschlägen der Datenpflege (Artikelpflege Phase 4). Rein additiv.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS dedupe_decisions (
    item_a INT UNSIGNED NOT NULL COMMENT 'kleinere ID',
    item_b INT UNSIGNED NOT NULL COMMENT 'größere ID',
    decision VARCHAR(16) NOT NULL COMMENT 'separate = getrennt lassen, later = zurückgestellt',
    decided_by INT UNSIGNED NULL,
    decided_at DATETIME NOT NULL,
    PRIMARY KEY (item_a, item_b)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
