-- Betreff-Vorlage für Bestellmails (optional; leer = Code-Default)
INSERT INTO settings (key_name, value)
VALUES ('order_email_subject_template', 'Bestellung {{COMPANY}} {{TARGET_DATE}}')
ON DUPLICATE KEY UPDATE key_name = key_name;
