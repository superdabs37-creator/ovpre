-- Migration: Enable Row Level Security (RLS) on progress reports tables
-- Description: Fixes Supabase Security Advisor Critical warnings: "RLS Disabled in Public"
-- Tables: public.research_progress_reports, public.extension_progress_reports

-- 1. Enable Row Level Security
ALTER TABLE public.research_progress_reports ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.extension_progress_reports ENABLE ROW LEVEL SECURITY;

-- 2. Policies for research_progress_reports
DROP POLICY IF EXISTS research_reports_select_scoped ON public.research_progress_reports;
CREATE POLICY research_reports_select_scoped ON public.research_progress_reports FOR SELECT TO authenticated
USING (
    public.app_current_role() IN ('Super Admin', 'OVPRE Admin', 'Research Director')
    OR submitted_by = public.app_current_profile_id()
    OR (public.app_current_role() = 'Research Coordinator' AND public.app_profile_in_current_department(submitted_by))
);

DROP POLICY IF EXISTS research_reports_insert_owned ON public.research_progress_reports;
CREATE POLICY research_reports_insert_owned ON public.research_progress_reports FOR INSERT TO authenticated
WITH CHECK (
    submitted_by = public.app_current_profile_id()
    AND public.app_current_role() IN ('Researcher', 'Research Coordinator', 'Super Admin', 'OVPRE Admin')
);

DROP POLICY IF EXISTS research_reports_update_reviewers ON public.research_progress_reports;
CREATE POLICY research_reports_update_reviewers ON public.research_progress_reports FOR UPDATE TO authenticated
USING (
    public.app_current_role() IN ('Super Admin', 'OVPRE Admin', 'Research Director')
    OR (public.app_current_role() = 'Research Coordinator' AND public.app_profile_in_current_department(submitted_by))
    OR (submitted_by = public.app_current_profile_id() AND status = 'Submitted')
)
WITH CHECK (
    public.app_current_role() IN ('Super Admin', 'OVPRE Admin', 'Research Director')
    OR (public.app_current_role() = 'Research Coordinator' AND EXISTS (
        SELECT 1 FROM public.users owner_profile WHERE owner_profile.id = research_progress_reports.submitted_by
          AND owner_profile.department_id = public.app_current_department_id()
    ))
    OR (submitted_by = public.app_current_profile_id())
);

DROP POLICY IF EXISTS research_reports_delete_admin ON public.research_progress_reports;
CREATE POLICY research_reports_delete_admin ON public.research_progress_reports FOR DELETE TO authenticated
USING (public.app_current_role() IN ('Super Admin', 'OVPRE Admin'));

-- 3. Policies for extension_progress_reports
DROP POLICY IF EXISTS extension_reports_select_scoped ON public.extension_progress_reports;
CREATE POLICY extension_reports_select_scoped ON public.extension_progress_reports FOR SELECT TO authenticated
USING (
    public.app_current_role() IN ('Super Admin', 'OVPRE Admin', 'Extension Director')
    OR submitted_by = public.app_current_profile_id()
    OR (public.app_current_role() = 'Extension Coordinator' AND public.app_profile_in_current_department(submitted_by))
);

DROP POLICY IF EXISTS extension_reports_insert_owned ON public.extension_progress_reports;
CREATE POLICY extension_reports_insert_owned ON public.extension_progress_reports FOR INSERT TO authenticated
WITH CHECK (
    submitted_by = public.app_current_profile_id()
    AND public.app_current_role() IN ('Faculty Extensionist', 'Extension Coordinator', 'Super Admin', 'OVPRE Admin')
);

DROP POLICY IF EXISTS extension_reports_update_reviewers ON public.extension_progress_reports;
CREATE POLICY extension_reports_update_reviewers ON public.extension_progress_reports FOR UPDATE TO authenticated
USING (
    public.app_current_role() IN ('Super Admin', 'OVPRE Admin', 'Extension Director')
    OR (public.app_current_role() = 'Extension Coordinator' AND public.app_profile_in_current_department(submitted_by))
    OR (submitted_by = public.app_current_profile_id() AND status = 'Submitted')
)
WITH CHECK (
    public.app_current_role() IN ('Super Admin', 'OVPRE Admin', 'Extension Director')
    OR (public.app_current_role() = 'Extension Coordinator' AND EXISTS (
        SELECT 1 FROM public.users owner_profile WHERE owner_profile.id = extension_progress_reports.submitted_by
          AND owner_profile.department_id = public.app_current_department_id()
    ))
    OR (submitted_by = public.app_current_profile_id())
);

DROP POLICY IF EXISTS extension_reports_delete_admin ON public.extension_progress_reports;
CREATE POLICY extension_reports_delete_admin ON public.extension_progress_reports FOR DELETE TO authenticated
USING (public.app_current_role() IN ('Super Admin', 'OVPRE Admin'));
