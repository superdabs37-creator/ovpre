-- Run once on existing MySQL installations to enable clickable file notifications.
ALTER TABLE notifications ADD COLUMN link VARCHAR(500) NULL AFTER message;
