-- Lieferanten mit für uns sichtbaren Artikelnummern (für die Prüfung „ohne Art.-Nr.“ in der Datenpflege).
-- Rein additiv. Voreinstellung laut Absprache: Transgourmet, Pütz, HANOS.
SET NAMES utf8mb4;

ALTER TABLE suppliers
    ADD COLUMN has_skus TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Art.-Nr. sichtbar → Datenpflege prüft fehlende Art.-Nr.' AFTER attach_pdf;

UPDATE suppliers SET has_skus = 1 WHERE name IN ('Transgourmet', 'Pütz', 'HANOS');
