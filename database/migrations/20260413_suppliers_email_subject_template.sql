-- Optional per-supplier mail subject template (overrides global when set)
DROP PROCEDURE IF EXISTS ct_add_supplier_email_subject;
DELIMITER //
CREATE PROCEDURE ct_add_supplier_email_subject()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'suppliers' AND COLUMN_NAME = 'email_subject_template') THEN
        ALTER TABLE suppliers ADD COLUMN email_subject_template VARCHAR(512) NULL AFTER email_template;
    END IF;
END //
DELIMITER ;
CALL ct_add_supplier_email_subject();
DROP PROCEDURE IF EXISTS ct_add_supplier_email_subject;
