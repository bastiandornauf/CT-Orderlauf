-- Noilly Prat: Schreibweise und Gebinde vereinheitlichen, eine Position, zwei Lieferanten.
SET NAMES utf8mb4;

UPDATE items SET name = 'Noilly Prat Dry 18 %', unit = 'Flasche (1 l)' WHERE name = 'Nolly Prait' AND unit = 'Flasche';

-- Nur-Stück-Artikel ohne separate Flasche-Zeile → auf Flasche (1 l) (MySQL: kein NOT EXISTS auf gleiche Tabelle)
UPDATE items i1
LEFT JOIN items i2 ON i2.name = 'Noilly Prat Dry 18 %' AND i2.unit = 'Flasche (1 l)' AND i2.id <> i1.id
SET i1.name = 'Noilly Prat Dry 18 %', i1.unit = 'Flasche (1 l)'
WHERE i1.name = 'Noilly Prat' AND i1.unit = 'Stk' AND i2.id IS NULL;

-- Transgourmet-Zuordnung von der alten Stk-Zeile auf die Flasche-Zeile übernehmen
INSERT IGNORE INTO item_supplier (item_id, supplier_id, priority)
SELECT lf.id, isup.supplier_id, isup.priority
FROM items ls
JOIN item_supplier isup ON isup.item_id = ls.id
JOIN items lf ON lf.name = 'Noilly Prat Dry 18 %' AND lf.unit = 'Flasche (1 l)'
WHERE ls.name = 'Noilly Prat' AND ls.unit = 'Stk' AND lf.id <> ls.id;

DELETE isup FROM item_supplier isup
 INNER JOIN items i ON i.id = isup.item_id
 WHERE i.name = 'Noilly Prat' AND i.unit = 'Stk';

DELETE FROM items WHERE name = 'Noilly Prat' AND unit = 'Stk';

-- Legacy-Zeile noch „Noilly Prat“ + Flasche (ohne Migration 20260412)
UPDATE items
 SET name = 'Noilly Prat Dry 18 %',
     unit = 'Flasche (1 l)',
     min_stock = COALESCE(min_stock, 1),
     max_stock = COALESCE(max_stock, 2)
 WHERE name = 'Noilly Prat' AND unit = 'Flasche';

UPDATE items
 SET min_stock = COALESCE(min_stock, 1),
     max_stock = COALESCE(max_stock, 2)
 WHERE name = 'Noilly Prat Dry 18 %' AND unit = 'Flasche (1 l)';
