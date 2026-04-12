-- Bereinigt Dubletten / einheitliche Benennung (Artikel + item_supplier).
-- Idempotent-sicher: mehrfaches Ausführen schadet nicht (0 Zeilen betroffen nach dem ersten Lauf).
-- Ausführung z. B.: mysql -u USER -p DBNAME < database/migrations/20260413_cleanup_duplicate_items.sql

SET NAMES utf8mb4;

-- Umbenennungen (IDs bleiben gleich, Zuordnungen bleiben gültig)
UPDATE items SET name = 'Rinderhüfte (Stück)' WHERE name = 'Rinderhüfte' AND unit = 'Stück';
UPDATE items SET name = 'Rinderhüfte (kg)' WHERE name = 'Rinderhüfte' AND unit = 'kg';

UPDATE items SET name = 'Obstsalat (10 l)' WHERE name = 'Obstsalat 10 L' AND unit = 'Eimer';
UPDATE items SET name = 'Obstsalat (Standard)' WHERE name = 'Obstsalat' AND unit = 'Eimer';

UPDATE items SET unit = 'Dose' WHERE name = 'Rote Beete (Streifen)' AND unit IN ('Dose!!!!', 'Dose');

UPDATE items SET name = 'Vanilleschote (Packung, mehrere Stück)'
 WHERE name IN ('Vanilleschote (Wo mehrere drin sind)', 'Vanilleschote(Wo mehrere drin sind)')
   AND unit = 'Dose';

-- Dubletten: Zuordnungen löschen, dann Artikel
DELETE isup FROM item_supplier isup
 INNER JOIN items i ON i.id = isup.item_id
 WHERE i.name = 'Brokolli' AND i.unit = 'Stück';
DELETE FROM items WHERE name = 'Brokolli' AND unit = 'Stück';

DELETE isup FROM item_supplier isup
 INNER JOIN items i ON i.id = isup.item_id
 WHERE i.name = 'Peperonie' AND i.unit = 'Eimer';
DELETE FROM items WHERE name = 'Peperonie' AND unit = 'Eimer';

DELETE isup FROM item_supplier isup
 INNER JOIN items i ON i.id = isup.item_id
 WHERE i.name = 'Rote Beete in Streifen' AND i.unit = 'Dose';
DELETE FROM items WHERE name = 'Rote Beete in Streifen' AND unit = 'Dose';

DELETE isup FROM item_supplier isup
 INNER JOIN items i ON i.id = isup.item_id
 WHERE i.name = 'Vanilleschote(Wo mehrere drin sind)' AND i.unit = 'Glas';
DELETE FROM items WHERE name = 'Vanilleschote(Wo mehrere drin sind)' AND unit = 'Glas';
