-- Manuelle Reihenfolge der Artikel je Lager (zusätzlich zu alphabetisch nach Name)
ALTER TABLE items
    ADD COLUMN sort_order INT NOT NULL DEFAULT 0 AFTER location_id;
