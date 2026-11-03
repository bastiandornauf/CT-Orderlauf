-- Artikel-Vorschläge können Lieferschein-/Rechnungsdaten tragen (aus Pflegepaketen, Zeile „suggest“).
-- Beim Übernehmen in die Stammdaten werden daraus Bestelldaten und Einkaufspreis. Rein additiv.
SET NAMES utf8mb4;

ALTER TABLE pending_items
    ADD COLUMN receipt JSON NULL COMMENT 'Lieferschein-/Rechnungsdaten aus Pflegepaket: Lieferant, Bezeichnung, Art.-Nr., Gebinde, Preis' AFTER dismissed_at;
