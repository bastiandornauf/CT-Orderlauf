-- Anzeigename für Bestell-Mails (Platzhalter {{USER}})
ALTER TABLE users
    ADD COLUMN display_name VARCHAR(128) NULL AFTER username;
