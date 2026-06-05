-- Bewertungspreis pro Artikel (Inventur-Export)
ALTER TABLE items
  ADD COLUMN valuation_price DECIMAL(10,2) NULL
  COMMENT 'Bewertungspreis pro Einheit fuer Inventur' AFTER max_stock;
