-- Posteingang: vom Versand-Postfach (IMAP) abgeholte Mails (Lieferantenantworten, weitergeleitete Belege).
-- Rein additiv. Anhänge liegen im Dateisystem unter storage/inbox/ (Ordner muss für PHP beschreibbar sein).
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS inbox_messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    imap_uid INT UNSIGNED NOT NULL,
    imap_uidvalidity INT UNSIGNED NOT NULL,
    message_id VARCHAR(255) NOT NULL DEFAULT '',
    received_at DATETIME NOT NULL,
    from_email VARCHAR(255) NOT NULL DEFAULT '',
    from_name VARCHAR(255) NOT NULL DEFAULT '',
    subject VARCHAR(512) NOT NULL DEFAULT '',
    body_text MEDIUMTEXT NULL,
    kind VARCHAR(16) NOT NULL COMMENT 'lieferant|beleg|nachtrag|sonstiges',
    supplier_id INT UNSIGNED NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'neu' COMMENT 'neu|erledigt',
    attachments JSON NULL COMMENT 'Liste {name, file, mime, size}',
    done_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_inbox_uid (imap_uidvalidity, imap_uid),
    INDEX idx_inbox_status (status, kind),
    CONSTRAINT fk_inbox_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
