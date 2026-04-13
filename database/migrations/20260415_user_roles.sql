-- Rollen: admin | editor | order (idempotent: auch wenn schema.sql die Spalte schon angelegt hat)
SET @dbname = DATABASE();
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'users' AND COLUMN_NAME = 'role') > 0,
    'SELECT 1',
    'ALTER TABLE users ADD COLUMN role VARCHAR(16) NOT NULL DEFAULT ''editor'' AFTER email'
));
PREPARE alterUserRole FROM @preparedStatement;
EXECUTE alterUserRole;
DEALLOCATE PREPARE alterUserRole;
UPDATE users SET role = 'admin' WHERE username = 'admin';
