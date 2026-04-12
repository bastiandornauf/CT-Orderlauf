-- Add address fields and attach_pdf flag to suppliers (idempotent via stored procedure)
DROP PROCEDURE IF EXISTS ct_add_supplier_columns;
DELIMITER //
CREATE PROCEDURE ct_add_supplier_columns()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'suppliers' AND COLUMN_NAME = 'street') THEN
        ALTER TABLE suppliers ADD COLUMN street VARCHAR(255) NULL AFTER mobile;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'suppliers' AND COLUMN_NAME = 'city') THEN
        ALTER TABLE suppliers ADD COLUMN city VARCHAR(255) NULL AFTER street;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'suppliers' AND COLUMN_NAME = 'attach_pdf') THEN
        ALTER TABLE suppliers ADD COLUMN attach_pdf TINYINT(1) NOT NULL DEFAULT 0 AFTER city;
    END IF;
END //
DELIMITER ;
CALL ct_add_supplier_columns();
DROP PROCEDURE IF EXISTS ct_add_supplier_columns;
