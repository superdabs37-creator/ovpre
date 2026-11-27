-- Migration: Fix Row Level Security (RLS) policies, security loopholes, and role alignments
-- Created: 2026-11-27
-- Description:
-- 1. Updates helper functions (app_current_role) so Research Director and Extension Director are preserved with institutional oversight.
-- 2. Removes dangerous open 'anon' policies on research_projects, extension_projects, activities, and announcements that allowed anyone to read/write/update private proposals.
-- 3. Enables clean authenticated & anon read for public lookups (departments, categories, published announcements, upcoming activities).
-- 4. Enables scoped institutional access for Research Director and Extension Director across projects, reports, documents, and reviews.
-- 5. Enables authenticated users to view active profiles for collaborative lookups while preserving privacy.
-- 6. Configures system_settings RLS policies.

BEGIN;

-- 1. HELPER FUNCTIONS
CREATE OR REPLACE FUNCTION public.app_current_role()
RETURNS TEXT LANGUAGE SQL STABLE SECURITY DEFINER SET search_path = public, auth
AS $$
    SELECT CASE role
        WHEN 'Director for Research and Creative Works' THEN 'Research Director'
        WHEN 'OVPRE Secretariat' THEN 'OVPRE Admin'
        ELSE role
    END
    FROM public.users
    WHERE auth_user_id = auth.uid() AND status = 'Active' AND deleted_at IS NULL
    LIMIT 1
$$;

CREATE OR REPLACE FUNCTION public.app_current_profile_id()
RETURNS BIGINT LANGUAGE SQL STABLE SECURITY DEFINER SET search_path = public, auth
AS $$
    SELECT id FROM public.users
    WHERE auth_user_id = auth.uid() AND status = 'Active' AND deleted_at IS NULL
    LIMIT 1
$$;

CREATE OR REPLACE FUNCTION public.app_current_department_id()
RETURNS BIGINT LANGUAGE SQL STABLE SECURITY DEFINER SET search_path = public, auth
AS $$
    SELECT department_id FROM public.users
    WHERE auth_user_id = auth.uid() AND status = 'Active' AND deleted_at IS NULL
    LIMIT 1
$$;

CREATE OR REPLACE FUNCTION public.app_profile_in_current_department(profile_id BIGINT)
RETURNS BOOLEAN LANGUAGE SQL STABLE SECURITY DEFINER SET search_path = public, auth
AS $$
    SELECT EXISTS (
        SELECT 1 FROM public.users scoped_profile
        WHERE scoped_profile.id = profile_id
          AND scoped_profile.department_id = public.app_current_department_id()
          AND scoped_profile.department_id IS NOT NULL
    )
$$;

-- 2. REMOVE INSECURE ANON OVERRIDES
DROP POLICY IF EXISTS anon_insert_research_projects ON public.research_projects;
DROP POLICY IF EXISTS anon_read_research_projects ON public.research_projects;
DROP POLICY IF EXISTS anon_update_research_projects ON public.research_projects;

DROP POLICY IF EXISTS anon_insert_extension_projects ON public.extension_projects;
DROP POLICY IF EXISTS anon_read_extension_projects ON public.extension_projects;
DROP POLICY IF EXISTS anon_update_extension_projects ON public.extension_projects;

DROP POLICY IF EXISTS anon_insert_activities ON public.activities;
DROP POLICY IF EXISTS anon_update_activities ON public.activities;

DROP POLICY IF EXISTS anon_insert_announcements ON public.announcements;
DROP POLICY IF EXISTS anon_update_announcements ON public.announcements;

-- 3. ENSURE RLS IS ENABLED ON ALL TABLES
ALTER TABLE public.users ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.departments ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.categories ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.documents ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.document_versions ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.archived_documents ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.research_projects ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.extension_projects ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.accomplishment_reports ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.activities ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.announcements ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.notifications ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.audit_logs ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.review_logs ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.login_history ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.research_progress_reports ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.extension_progress_reports ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.system_settings ENABLE ROW LEVEL SECURITY;

-- 4. POLICIES FOR USERS
DROP POLICY IF EXISTS users_select_scoped ON public.users;
DROP POLICY IF EXISTS users_select_authenticated ON public.users;
CREATE POLICY users_select_authenticated ON public.users FOR SELECT TO authenticated
USING (
    (status = 'Active' AND deleted_at IS NULL)
    OR id = public.app_current_profile_id()
    OR public.app_current_role() IN ('Super Admin', 'OVPRE Admin')
);

DROP POLICY IF EXISTS users_update_self_or_admin ON public.users;
CREATE POLICY users_update_self_or_admin ON public.users FOR UPDATE TO authenticated
USING (id = public.app_current_profile_id() OR public.app_current_role() IN ('Super Admin', 'OVPRE Admin'))
WITH CHECK (id = public.app_current_profile_id() OR public.app_current_role() IN ('Super Admin', 'OVPRE Admin'));

-- 5. POLICIES FOR DEPARTMENTS & CATEGORIES (Global read lookup)
DROP POLICY IF EXISTS departments_read_active ON public.departments;
DROP POLICY IF EXISTS anon_read_departments ON public.departments;
CREATE POLICY departments_read_active ON public.departments FOR SELECT TO authenticated, anon
USING (true);

DROP POLICY IF EXISTS departments_manage_admin ON public.departments;
CREATE POLICY departments_manage_admin ON public.departments FOR ALL TO authenticated
USING (public.app_current_role() IN ('Super Admin', 'OVPRE Admin'))
WITH CHECK (public.app_current_role() IN ('Super Admin', 'OVPRE Admin'));

DROP POLICY IF EXISTS categories_read_active ON public.categories;
DROP POLICY IF EXISTS anon_read_categories ON public.categories;
CREATE POLICY categories_read_active ON public.categories FOR SELECT TO authenticated, anon
USING (true);

DROP POLICY IF EXISTS categories_manage_admin ON public.categories;
CREATE POLICY categories_manage_admin ON public.categories FOR ALL TO authenticated
USING (public.app_current_role() IN ('Super Admin', 'OVPRE Admin'))
WITH CHECK (public.app_current_role() IN ('Super Admin', 'OVPRE Admin'));

-- 6. POLICIES FOR ANNOUNCEMENTS & ACTIVITIES
DROP POLICY IF EXISTS announcements_read_published ON public.announcements;
DROP POLICY IF EXISTS anon_read_announcements ON public.announcements;
CREATE POLICY announcements_read_published ON public.announcements FOR SELECT TO authenticated, anon
USING (status = 'Published' OR public.app_current_role() IN ('Super Admin', 'OVPRE Admin'));

DROP POLICY IF EXISTS announcements_manage_admin ON public.announcements;
CREATE POLICY announcements_manage_admin ON public.announcements FOR ALL TO authenticated
USING (public.app_current_role() IN ('Super Admin', 'OVPRE Admin'))
WITH CHECK (public.app_current_role() IN ('Super Admin', 'OVPRE Admin'));

DROP POLICY IF EXISTS activities_read_active ON public.activities;
DROP POLICY IF EXISTS anon_read_activities ON public.activities;
CREATE POLICY activities_read_active ON public.activities FOR SELECT TO authenticated, anon
USING (archived_at IS NULL OR public.app_current_role() IN ('Super Admin', 'OVPRE Admin'));

DROP POLICY IF EXISTS activities_insert_owned ON public.activities;
CREATE POLICY activities_insert_owned ON public.activities FOR INSERT TO authenticated
WITH CHECK (
    created_by = public.app_current_profile_id()
    AND public.app_current_role() IN ('Research Coordinator', 'Extension Coordinator', 'Research Director', 'Extension Director', 'OVPRE Admin', 'Super Admin')
);

DROP POLICY IF EXISTS activities_update_managers ON public.activities;
CREATE POLICY activities_update_managers ON public.activities FOR UPDATE TO authenticated
USING (
    public.app_current_role() IN ('Research Coordinator', 'Extension Coordinator', 'Research Director', 'Extension Director', 'OVPRE Admin', 'Super Admin')
    AND (created_by = public.app_current_profile_id() OR public.app_current_role() IN ('OVPRE Admin', 'Super Admin', 'Research Director', 'Extension Director'))
)
WITH CHECK (
    public.app_current_role() IN ('Research Coordinator', 'Extension Coordinator', 'Research Director', 'Extension Director', 'OVPRE Admin', 'Super Admin')
);

DROP POLICY IF EXISTS activities_delete_managers ON public.activities;
CREATE POLICY activities_delete_managers ON public.activities FOR DELETE TO authenticated
USING (
    public.app_current_role() IN ('OVPRE Admin', 'Super Admin')
    OR (created_by = public.app_current_profile_id() AND approval_status <> 'Approved')
);

-- 7. POLICIES FOR RESEARCH PROJECTS
DROP POLICY IF EXISTS research_select_scoped ON public.research_projects;
CREATE POLICY research_select_scoped ON public.research_projects FOR SELECT TO authenticated
USING (
    public.app_current_role() IN ('Super Admin', 'OVPRE Admin', 'Research Director')
    OR submitted_by = public.app_current_profile_id()
    OR (public.app_current_role() = 'Research Coordinator' AND public.app_profile_in_current_department(submitted_by))
);

DROP POLICY IF EXISTS research_insert_owned ON public.research_projects;
CREATE POLICY research_insert_owned ON public.research_projects FOR INSERT TO authenticated
WITH CHECK (
    submitted_by = public.app_current_profile_id()
    AND public.app_current_role() IN ('Researcher', 'Research Coordinator', 'Research Director', 'Super Admin', 'OVPRE Admin')
);

DROP POLICY IF EXISTS research_update_reviewers ON public.research_projects;
CREATE POLICY research_update_reviewers ON public.research_projects FOR UPDATE TO authenticated
USING (
    public.app_current_role() IN ('Super Admin', 'OVPRE Admin', 'Research Director')
    OR (public.app_current_role() = 'Research Coordinator' AND public.app_profile_in_current_department(submitted_by))
    OR (submitted_by = public.app_current_profile_id() AND status IN ('Draft', 'Pending Coordinator Review', 'Needs Revision'))
)
WITH CHECK (
    public.app_current_role() IN ('Super Admin', 'OVPRE Admin', 'Research Director')
    OR (public.app_current_role() = 'Research Coordinator' AND EXISTS (
        SELECT 1 FROM public.users owner_profile WHERE owner_profile.id = research_projects.submitted_by
          AND owner_profile.department_id = public.app_current_department_id()
    ))
    OR (submitted_by = public.app_current_profile_id())
);

DROP POLICY IF EXISTS research_delete_admin ON public.research_projects;
CREATE POLICY research_delete_admin ON public.research_projects FOR DELETE TO authenticated
USING (public.app_current_role() IN ('Super Admin', 'OVPRE Admin'));

-- 8. POLICIES FOR EXTENSION PROJECTS
DROP POLICY IF EXISTS extension_select_scoped ON public.extension_projects;
CREATE POLICY extension_select_scoped ON public.extension_projects FOR SELECT TO authenticated
USING (
    public.app_current_role() IN ('Super Admin', 'OVPRE Admin', 'Extension Director')
    OR submitted_by = public.app_current_profile_id()
    OR (public.app_current_role() = 'Extension Coordinator' AND public.app_profile_in_current_department(submitted_by))
);

DROP POLICY IF EXISTS extension_insert_owned ON public.extension_projects;
CREATE POLICY extension_insert_owned ON public.extension_projects FOR INSERT TO authenticated
WITH CHECK (
    submitted_by = public.app_current_profile_id()
    AND public.app_current_role() IN ('Faculty Extensionist', 'Extension Coordinator', 'Extension Director', 'Super Admin', 'OVPRE Admin')
);

DROP POLICY IF EXISTS extension_update_reviewers ON public.extension_projects;
CREATE POLICY extension_update_reviewers ON public.extension_projects FOR UPDATE TO authenticated
USING (
    public.app_current_role() IN ('Super Admin', 'OVPRE Admin', 'Extension Director')
    OR (public.app_current_role() = 'Extension Coordinator' AND public.app_profile_in_current_department(submitted_by))
    OR (submitted_by = public.app_current_profile_id() AND status IN ('Draft', 'Pending Coordinator Review', 'Needs Revision'))
)
WITH CHECK (
    public.app_current_role() IN ('Super Admin', 'OVPRE Admin', 'Extension Director')
    OR (public.app_current_role() = 'Extension Coordinator' AND EXISTS (
        SELECT 1 FROM public.users owner_profile WHERE owner_profile.id = extension_projects.submitted_by
          AND owner_profile.department_id = public.app_current_department_id()
    ))
    OR (submitted_by = public.app_current_profile_id())
);

DROP POLICY IF EXISTS extension_delete_admin ON public.extension_projects;
CREATE POLICY extension_delete_admin ON public.extension_projects FOR DELETE TO authenticated
USING (public.app_current_role() IN ('Super Admin', 'OVPRE Admin'));

-- 9. POLICIES FOR ACCOMPLISHMENT REPORTS
DROP POLICY IF EXISTS accomplishments_select_scoped ON public.accomplishment_reports;
CREATE POLICY accomplishments_select_scoped ON public.accomplishment_reports FOR SELECT TO authenticated
USING (
    public.app_current_role() IN ('Super Admin', 'OVPRE Admin', 'Extension Director')
    OR submitted_by = public.app_current_profile_id()
    OR (public.app_current_role() = 'Extension Coordinator' AND EXISTS (
        SELECT 1 FROM public.users owner_profile WHERE owner_profile.id = accomplishment_reports.submitted_by
          AND owner_profile.department_id = public.app_current_department_id()
    ))
);

DROP POLICY IF EXISTS accomplishments_insert_owned ON public.accomplishment_reports;
CREATE POLICY accomplishments_insert_owned ON public.accomplishment_reports FOR INSERT TO authenticated
WITH CHECK (
    submitted_by = public.app_current_profile_id()
    AND public.app_current_role() IN ('Faculty Extensionist', 'Extension Coordinator', 'Extension Director', 'Super Admin', 'OVPRE Admin')
);

DROP POLICY IF EXISTS accomplishments_update_scoped ON public.accomplishment_reports;
CREATE POLICY accomplishments_update_scoped ON public.accomplishment_reports FOR UPDATE TO authenticated
USING (
    public.app_current_role() IN ('Super Admin', 'OVPRE Admin', 'Extension Director')
    OR (public.app_current_role() = 'Extension Coordinator' AND public.app_profile_in_current_department(submitted_by))
)
WITH CHECK (
    public.app_current_role() IN ('Super Admin', 'OVPRE Admin', 'Extension Director')
    OR (public.app_current_role() = 'Extension Coordinator' AND public.app_profile_in_current_department(submitted_by))
);

DROP POLICY IF EXISTS accomplishments_delete_admin ON public.accomplishment_reports;
CREATE POLICY accomplishments_delete_admin ON public.accomplishment_reports FOR DELETE TO authenticated
USING (public.app_current_role() IN ('Super Admin', 'OVPRE Admin'));

-- 10. POLICIES FOR DOCUMENTS
DROP POLICY IF EXISTS documents_select_scoped ON public.documents;
CREATE POLICY documents_select_scoped ON public.documents FOR SELECT TO authenticated
USING (
    public.app_current_role() IN ('Super Admin', 'OVPRE Admin', 'Research Director', 'Extension Director')
    OR uploaded_by = public.app_current_profile_id()
    OR (public.app_current_role() IN ('Research Coordinator', 'Extension Coordinator') AND department_id = public.app_current_department_id())
);

DROP POLICY IF EXISTS documents_insert_owned ON public.documents;
CREATE POLICY documents_insert_owned ON public.documents FOR INSERT TO authenticated
WITH CHECK (
    uploaded_by = public.app_current_profile_id()
    AND public.app_current_role() IN ('Super Admin', 'OVPRE Admin', 'Research Director', 'Extension Director', 'Research Coordinator', 'Extension Coordinator', 'Faculty Extensionist', 'Researcher')
    AND (public.app_current_role() IN ('Super Admin', 'OVPRE Admin', 'Research Director', 'Extension Director') OR department_id IS NOT DISTINCT FROM public.app_current_department_id())
);

DROP POLICY IF EXISTS documents_update_managers ON public.documents;
CREATE POLICY documents_update_managers ON public.documents FOR UPDATE TO authenticated
USING (
    public.app_current_role() IN ('Super Admin', 'OVPRE Admin', 'Research Director', 'Extension Director')
    OR (public.app_current_role() IN ('Research Coordinator', 'Extension Coordinator') AND department_id = public.app_current_department_id())
)
WITH CHECK (
    public.app_current_role() IN ('Super Admin', 'OVPRE Admin', 'Research Director', 'Extension Director')
    OR (public.app_current_role() IN ('Research Coordinator', 'Extension Coordinator') AND department_id = public.app_current_department_id())
);

DROP POLICY IF EXISTS documents_delete_super_admin ON public.documents;
CREATE POLICY documents_delete_super_admin ON public.documents FOR DELETE TO authenticated
USING (public.app_current_role() = 'Super Admin');

-- 11. POLICIES FOR REVIEW LOGS
DROP POLICY IF EXISTS review_logs_select_authorized ON public.review_logs;
CREATE POLICY review_logs_select_authorized ON public.review_logs FOR SELECT TO authenticated
USING (
    public.app_current_role() IN ('Super Admin', 'OVPRE Admin', 'Research Director', 'Extension Director')
    OR (public.app_current_role() = 'Research Coordinator' AND entity_type = 'research' AND EXISTS (
        SELECT 1 FROM public.research_projects scoped_research WHERE scoped_research.id = review_logs.entity_id AND public.app_profile_in_current_department(scoped_research.submitted_by)
    ))
    OR (public.app_current_role() = 'Extension Coordinator' AND entity_type = 'extension' AND EXISTS (
        SELECT 1 FROM public.extension_projects scoped_extension WHERE scoped_extension.id = review_logs.entity_id AND public.app_profile_in_current_department(scoped_extension.submitted_by)
    ))
    OR user_id = public.app_current_profile_id()
);

-- 12. POLICIES FOR SYSTEM SETTINGS
DROP POLICY IF EXISTS system_settings_select_authenticated ON public.system_settings;
CREATE POLICY system_settings_select_authenticated ON public.system_settings FOR SELECT TO authenticated
USING (true);

DROP POLICY IF EXISTS system_settings_manage_admin ON public.system_settings;
CREATE POLICY system_settings_manage_admin ON public.system_settings FOR ALL TO authenticated
USING (public.app_current_role() IN ('Super Admin', 'OVPRE Admin'))
WITH CHECK (public.app_current_role() IN ('Super Admin', 'OVPRE Admin'));

-- 13. POLICIES FOR STORAGE OBJECTS (Private Bucket)
DROP POLICY IF EXISTS record_files_insert_owner ON storage.objects;
CREATE POLICY record_files_insert_owner ON storage.objects FOR INSERT TO authenticated
WITH CHECK (bucket_id = 'irecord-records' AND owner_id = auth.uid()::text AND public.app_current_role() IN ('Super Admin', 'OVPRE Admin', 'Research Director', 'Extension Director', 'Research Coordinator', 'Extension Coordinator', 'Faculty Extensionist', 'Researcher'));

DROP POLICY IF EXISTS record_files_read_authorized ON storage.objects;
CREATE POLICY record_files_read_authorized ON storage.objects FOR SELECT TO authenticated
USING (
    bucket_id = 'irecord-records'
    AND (
        public.app_current_role() IN ('Super Admin', 'OVPRE Admin', 'Research Director', 'Extension Director')
        OR EXISTS (
            SELECT 1 FROM public.documents d
            WHERE d.filename = 'storage:' || name
              AND (d.uploaded_by = public.app_current_profile_id()
                   OR (public.app_current_role() IN ('Research Coordinator', 'Extension Coordinator') AND d.department_id = public.app_current_department_id()))
        )
    )
);

DROP POLICY IF EXISTS record_files_delete_admin ON storage.objects;
CREATE POLICY record_files_delete_admin ON storage.objects FOR DELETE TO authenticated
USING (bucket_id = 'irecord-records' AND public.app_current_role() = 'Super Admin');

COMMIT;
