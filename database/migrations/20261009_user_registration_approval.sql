-- Select the target database in phpMyAdmin before importing this migration.

-- Existing Active / Inactive / Locked values are retained during this migration.
UPDATE users SET status = 'Active' WHERE status IS NULL;

ALTER TABLE users
  MODIFY COLUMN status ENUM('Pending','Active','Rejected','Suspended','Inactive','Locked') NOT NULL DEFAULT 'Pending',
  ADD COLUMN IF NOT EXISTS approved_at DATETIME NULL AFTER status,
  ADD COLUMN IF NOT EXISTS approved_by INT NULL AFTER approved_at,
  ADD COLUMN IF NOT EXISTS rejected_at DATETIME NULL AFTER approved_by,
  ADD COLUMN IF NOT EXISTS rejected_by INT NULL AFTER rejected_at,
  ADD COLUMN IF NOT EXISTS rejection_reason TEXT NULL AFTER rejected_by,
  ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at,
  ADD INDEX IF NOT EXISTS idx_users_approved_by (approved_by),
  ADD INDEX IF NOT EXISTS idx_users_rejected_by (rejected_by),
  ADD INDEX IF NOT EXISTS idx_users_status_created (status, created_at);

SET @has_approved_by_fk = (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND CONSTRAINT_NAME = 'fk_users_approved_by'
);
SET @add_approved_by_fk = IF(@has_approved_by_fk = 0,
  'ALTER TABLE users ADD CONSTRAINT fk_users_approved_by FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL',
  'SELECT 1'
);
PREPARE approval_fk_stmt FROM @add_approved_by_fk;
EXECUTE approval_fk_stmt;
DEALLOCATE PREPARE approval_fk_stmt;

SET @has_rejected_by_fk = (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND CONSTRAINT_NAME = 'fk_users_rejected_by'
);
SET @add_rejected_by_fk = IF(@has_rejected_by_fk = 0,
  'ALTER TABLE users ADD CONSTRAINT fk_users_rejected_by FOREIGN KEY (rejected_by) REFERENCES users(id) ON DELETE SET NULL',
  'SELECT 1'
);
PREPARE rejection_fk_stmt FROM @add_rejected_by_fk;
EXECUTE rejection_fk_stmt;
DEALLOCATE PREPARE rejection_fk_stmt;
