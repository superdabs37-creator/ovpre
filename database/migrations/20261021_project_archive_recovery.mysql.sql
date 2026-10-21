-- Per-role Backup and Recovery: soft-delete (archive) support for research and extension submissions.
-- Run each statement once; skip a statement if the column already exists.
ALTER TABLE research_projects  ADD COLUMN archived_at DATETIME DEFAULT NULL;
ALTER TABLE extension_projects ADD COLUMN archived_at DATETIME DEFAULT NULL;
