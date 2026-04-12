-- Rückmeldung Betrieb: Lachs nach Karton (5 kg); zweite Grana-Variante gehobelt; Rest unverändert.
SET NAMES utf8mb4;

UPDATE items SET unit = 'Karton (5 kg)'
WHERE name = 'Lachsportion ohne Haut 150 g (TK, Gebinde ca. 5 kg)' AND unit = 'kg';

INSERT INTO items (name, unit, location_id, min_stock, max_stock, active)
SELECT 'Grana Padano gehobelt DOP', 'Schale (500 g)', l.id, 4, 12, 1
FROM locations l
WHERE l.name = 'Kühlhaus 2'
  AND NOT EXISTS (
    SELECT 1 FROM items i WHERE i.name = 'Grana Padano gehobelt DOP' AND i.unit = 'Schale (500 g)'
  )
LIMIT 1;

INSERT IGNORE INTO item_supplier (item_id, supplier_id, priority)
SELECT i.id, s.id, 0 FROM items i, suppliers s
WHERE i.name = 'Grana Padano gehobelt DOP' AND i.unit = 'Schale (500 g)' AND s.name = 'Transgourmet';
