-- Per-role Backup and Recovery: soft-delete (archive) support for research and extension submissions.
-- Archived rows stay in the table and can be restored by the role that is allowed to manage them.
ALTER TABLE public.research_projects  ADD COLUMN IF NOT EXISTS archived_at TIMESTAMPTZ DEFAULT NULL;
ALTER TABLE public.extension_projects ADD COLUMN IF NOT EXISTS archived_at TIMESTAMPTZ DEFAULT NULL;

CREATE INDEX IF NOT EXISTS idx_research_projects_archived_at  ON public.research_projects (archived_at);
CREATE INDEX IF NOT EXISTS idx_extension_projects_archived_at ON public.extension_projects (archived_at);
