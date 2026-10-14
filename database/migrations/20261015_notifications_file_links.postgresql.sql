-- Add links to notifications and alert all active accounts when new files arrive.
ALTER TABLE notifications ADD COLUMN IF NOT EXISTS link VARCHAR(500);
