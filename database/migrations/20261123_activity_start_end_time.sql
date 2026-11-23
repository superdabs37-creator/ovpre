-- Migration: Add date_to, start_time, and end_time to activities table
-- Supports both MySQL and PostgreSQL

-- MySQL:
-- ALTER TABLE activities ADD COLUMN IF NOT EXISTS date_to DATE DEFAULT NULL AFTER date;
-- ALTER TABLE activities ADD COLUMN IF NOT EXISTS start_time TIME DEFAULT NULL AFTER date_to;
-- ALTER TABLE activities ADD COLUMN IF NOT EXISTS end_time TIME DEFAULT NULL AFTER start_time;

-- PostgreSQL:
ALTER TABLE activities ADD COLUMN IF NOT EXISTS date_to DATE DEFAULT NULL;
ALTER TABLE activities ADD COLUMN IF NOT EXISTS start_time TIME DEFAULT NULL;
ALTER TABLE activities ADD COLUMN IF NOT EXISTS end_time TIME DEFAULT NULL;
