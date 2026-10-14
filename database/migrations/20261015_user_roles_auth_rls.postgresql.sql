-- I-Record role/Auth/RLS cutover. Review the impact and run only after configuring
-- SUPABASE_URL, SUPABASE_ANON_KEY, and SUPABASE_SECRET_KEY on the PHP server.
-- This preserves profile IDs and all related records. It refuses any RIUH or
-- Suspended profiles so those accounts can be reviewed before reassignment.
BEGIN;

DO $$
DECLARE
    affected_riuh BIGINT;
    affected_suspended BIGINT;
    unsupported_roles TEXT[];
    unsupported_statuses TEXT[];
BEGIN
    SELECT COUNT(*) INTO affected_riuh FROM public.users WHERE role::text ILIKE '%RIUH%';
    IF affected_riuh > 0 THEN
        RAISE EXCEPTION 'Migration stopped: % RIUH profile(s) need explicit role reassignment approval.', affected_riuh;
    END IF;

    SELECT COUNT(*) INTO affected_suspended FROM public.users WHERE status::text = 'Suspended';
    IF affected_suspended > 0 THEN
        RAISE EXCEPTION 'Migration stopped: % Suspended profile(s) need review before conversion to Inactive.', affected_suspended;
    END IF;

    SELECT ARRAY_AGG(DISTINCT role::text) INTO unsupported_roles
    FROM public.users
    WHERE role::text NOT IN (
        'Super Admin', 'OVPRE Admin', 'Research Coordinator', 'Extension Coordinator', 'Faculty Extensionist', 'Researcher',
        'Vice President', 'OVPRE Staff', 'Faculty Researcher', 'Evaluator/Reviewer', 'User'
    );
    IF unsupported_roles IS NOT NULL THEN
        RAISE EXCEPTION 'Migration stopped: unsupported role values require review: %', unsupported_roles;
    END IF;

    SELECT ARRAY_AGG(DISTINCT status::text) INTO unsupported_statuses
    FROM public.users
    WHERE status::text NOT IN ('Pending Invitation', 'Pending Approval', 'Active', 'Inactive', 'Pending', 'Rejected', 'Locked');
    IF unsupported_statuses IS NOT NULL THEN
        RAISE EXCEPTION 'Migration stopped: unsupported status values require review: %', unsupported_statuses;
    END IF;
END
$$;

ALTER TABLE public.users ADD COLUMN IF NOT EXISTS auth_user_id UUID;
ALTER TABLE public.users ALTER COLUMN password DROP NOT NULL;
CREATE UNIQUE INDEX IF NOT EXISTS users_auth_user_id_uidx ON public.users (auth_user_id) WHERE auth_user_id IS NOT NULL;

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'users_auth_user_id_fkey' AND conrelid = 'public.users'::regclass) THEN
        ALTER TABLE public.users ADD CONSTRAINT users_auth_user_id_fkey
            FOREIGN KEY (auth_user_id) REFERENCES auth.users(id) ON DELETE SET NULL;
    END IF;
END
$$;

ALTER TABLE public.users ALTER COLUMN role DROP DEFAULT;
ALTER TABLE public.users ALTER COLUMN status DROP DEFAULT;
ALTER TABLE public.users ALTER COLUMN role TYPE TEXT USING role::text;
ALTER TABLE public.users ALTER COLUMN status TYPE TEXT USING status::text;

UPDATE public.users
SET role = CASE role
    WHEN 'Vice President' THEN 'OVPRE Admin'
    WHEN 'OVPRE Staff' THEN 'OVPRE Admin'
    WHEN 'Faculty Researcher' THEN 'Researcher'
    WHEN 'Evaluator/Reviewer' THEN 'Researcher'
    WHEN 'User' THEN 'Researcher'
    ELSE role
END;
UPDATE public.users
SET status = CASE status
    WHEN 'Pending' THEN 'Pending Approval'
    WHEN 'Rejected' THEN 'Inactive'
    WHEN 'Locked' THEN 'Inactive'
    ELSE status
END;

ALTER TABLE public.users ALTER COLUMN role SET NOT NULL;
ALTER TABLE public.users ALTER COLUMN status SET NOT NULL;
ALTER TABLE public.users ALTER COLUMN status SET DEFAULT 'Pending Approval';
ALTER TABLE public.users ADD CONSTRAINT users_role_allowed_check CHECK (role IN (
    'Super Admin', 'OVPRE Admin', 'Research Coordinator', 'Extension Coordinator', 'Faculty Extensionist', 'Researcher'
));
ALTER TABLE public.users ADD CONSTRAINT users_status_allowed_check CHECK (status IN (
    'Pending Invitation', 'Pending Approval', 'Active', 'Inactive'
));

CREATE OR REPLACE FUNCTION public.app_current_profile_id()
RETURNS BIGINT LANGUAGE SQL STABLE SECURITY DEFINER SET search_path = public, auth
AS $$
    SELECT id FROM public.users
    WHERE auth_user_id = auth.uid() AND status = 'Active' AND deleted_at IS NULL
    LIMIT 1
$$;

CREATE OR REPLACE FUNCTION public.app_current_role()
RETURNS TEXT LANGUAGE SQL STABLE SECURITY DEFINER SET search_path = public, auth
AS $$
    SELECT role FROM public.users
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

DROP POLICY IF EXISTS users_select_scoped ON public.users;
CREATE POLICY users_select_scoped ON public.users FOR SELECT TO authenticated
USING (id = public.app_current_profile_id() OR public.app_current_role() IN ('Super Admin', 'OVPRE Admin'));

DROP POLICY IF EXISTS departments_read_active ON public.departments;
CREATE POLICY departments_read_active ON public.departments FOR SELECT TO authenticated
USING (public.app_current_profile_id() IS NOT NULL);
DROP POLICY IF EXISTS departments_manage_admin ON public.departments;
CREATE POLICY departments_manage_admin ON public.departments FOR ALL TO authenticated
USING (public.app_current_role() IN ('Super Admin', 'OVPRE Admin'))
WITH CHECK (public.app_current_role() IN ('Super Admin', 'OVPRE Admin'));

DROP POLICY IF EXISTS categories_read_active ON public.categories;
CREATE POLICY categories_read_active ON public.categories FOR SELECT TO authenticated
USING (public.app_current_profile_id() IS NOT NULL);
DROP POLICY IF EXISTS categories_manage_admin ON public.categories;
CREATE POLICY categories_manage_admin ON public.categories FOR ALL TO authenticated
USING (public.app_current_role() IN ('Super Admin', 'OVPRE Admin'))
WITH CHECK (public.app_current_role() IN ('Super Admin', 'OVPRE Admin'));

DROP POLICY IF EXISTS documents_select_scoped ON public.documents;
CREATE POLICY documents_select_scoped ON public.documents FOR SELECT TO authenticated
USING (
    public.app_current_role() IN ('Super Admin', 'OVPRE Admin')
    OR uploaded_by = public.app_current_profile_id()
    OR (public.app_current_role() IN ('Research Coordinator', 'Extension Coordinator') AND department_id = public.app_current_department_id())
);
DROP POLICY IF EXISTS documents_insert_owned ON public.documents;
CREATE POLICY documents_insert_owned ON public.documents FOR INSERT TO authenticated
WITH CHECK (
    uploaded_by = public.app_current_profile_id()
    AND public.app_current_role() IN ('Super Admin', 'OVPRE Admin', 'Research Coordinator', 'Extension Coordinator', 'Faculty Extensionist', 'Researcher')
    AND (public.app_current_role() IN ('Super Admin', 'OVPRE Admin') OR department_id IS NOT DISTINCT FROM public.app_current_department_id())
);
DROP POLICY IF EXISTS documents_update_managers ON public.documents;
CREATE POLICY documents_update_managers ON public.documents FOR UPDATE TO authenticated
USING (
    public.app_current_role() IN ('Super Admin', 'OVPRE Admin')
    OR (public.app_current_role() IN ('Research Coordinator', 'Extension Coordinator') AND department_id = public.app_current_department_id())
)
WITH CHECK (
    public.app_current_role() IN ('Super Admin', 'OVPRE Admin')
    OR (public.app_current_role() IN ('Research Coordinator', 'Extension Coordinator') AND department_id = public.app_current_department_id())
);
DROP POLICY IF EXISTS documents_delete_super_admin ON public.documents;
CREATE POLICY documents_delete_super_admin ON public.documents FOR DELETE TO authenticated
USING (public.app_current_role() = 'Super Admin');

DROP POLICY IF EXISTS research_select_scoped ON public.research_projects;
CREATE POLICY research_select_scoped ON public.research_projects FOR SELECT TO authenticated
USING (
    public.app_current_role() IN ('Super Admin', 'OVPRE Admin')
    OR submitted_by = public.app_current_profile_id()
    OR (public.app_current_role() = 'Research Coordinator' AND public.app_profile_in_current_department(submitted_by))
);
DROP POLICY IF EXISTS research_insert_owned ON public.research_projects;
CREATE POLICY research_insert_owned ON public.research_projects FOR INSERT TO authenticated
WITH CHECK (submitted_by = public.app_current_profile_id() AND public.app_current_role() IN ('Researcher', 'Research Coordinator', 'Super Admin', 'OVPRE Admin'));
DROP POLICY IF EXISTS research_update_reviewers ON public.research_projects;
CREATE POLICY research_update_reviewers ON public.research_projects FOR UPDATE TO authenticated
USING (
    public.app_current_role() IN ('Super Admin', 'OVPRE Admin')
    OR (public.app_current_role() = 'Research Coordinator' AND public.app_profile_in_current_department(submitted_by))
)
WITH CHECK (
    public.app_current_role() IN ('Super Admin', 'OVPRE Admin')
    OR (public.app_current_role() = 'Research Coordinator' AND EXISTS (
        SELECT 1 FROM public.users owner_profile WHERE owner_profile.id = submitted_by
          AND owner_profile.department_id = public.app_current_department_id()
    ))
);
DROP POLICY IF EXISTS research_delete_admin ON public.research_projects;
CREATE POLICY research_delete_admin ON public.research_projects FOR DELETE TO authenticated
USING (public.app_current_role() IN ('Super Admin', 'OVPRE Admin'));

DROP POLICY IF EXISTS extension_select_scoped ON public.extension_projects;
CREATE POLICY extension_select_scoped ON public.extension_projects FOR SELECT TO authenticated
USING (
    public.app_current_role() IN ('Super Admin', 'OVPRE Admin')
    OR submitted_by = public.app_current_profile_id()
    OR (public.app_current_role() = 'Extension Coordinator' AND public.app_profile_in_current_department(submitted_by))
);
DROP POLICY IF EXISTS extension_insert_owned ON public.extension_projects;
CREATE POLICY extension_insert_owned ON public.extension_projects FOR INSERT TO authenticated
WITH CHECK (submitted_by = public.app_current_profile_id() AND public.app_current_role() IN ('Faculty Extensionist', 'Extension Coordinator', 'Super Admin', 'OVPRE Admin'));
DROP POLICY IF EXISTS extension_update_reviewers ON public.extension_projects;
CREATE POLICY extension_update_reviewers ON public.extension_projects FOR UPDATE TO authenticated
USING (
    public.app_current_role() IN ('Super Admin', 'OVPRE Admin')
    OR (public.app_current_role() = 'Extension Coordinator' AND public.app_profile_in_current_department(submitted_by))
)
WITH CHECK (
    public.app_current_role() IN ('Super Admin', 'OVPRE Admin')
    OR (public.app_current_role() = 'Extension Coordinator' AND EXISTS (
        SELECT 1 FROM public.users owner_profile WHERE owner_profile.id = submitted_by
          AND owner_profile.department_id = public.app_current_department_id()
    ))
);
DROP POLICY IF EXISTS extension_delete_admin ON public.extension_projects;
CREATE POLICY extension_delete_admin ON public.extension_projects FOR DELETE TO authenticated
USING (public.app_current_role() IN ('Super Admin', 'OVPRE Admin'));

DROP POLICY IF EXISTS accomplishments_select_scoped ON public.accomplishment_reports;
CREATE POLICY accomplishments_select_scoped ON public.accomplishment_reports FOR SELECT TO authenticated
USING (
    public.app_current_role() IN ('Super Admin', 'OVPRE Admin')
    OR submitted_by = public.app_current_profile_id()
    OR (public.app_current_role() = 'Extension Coordinator' AND EXISTS (
        SELECT 1 FROM public.users owner_profile WHERE owner_profile.id = submitted_by
          AND owner_profile.department_id = public.app_current_department_id()
    ))
);
DROP POLICY IF EXISTS accomplishments_insert_owned ON public.accomplishment_reports;
CREATE POLICY accomplishments_insert_owned ON public.accomplishment_reports FOR INSERT TO authenticated
WITH CHECK (submitted_by = public.app_current_profile_id() AND public.app_current_role() IN ('Faculty Extensionist', 'Super Admin', 'OVPRE Admin'));
DROP POLICY IF EXISTS accomplishments_manage_admin ON public.accomplishment_reports;
CREATE POLICY accomplishments_update_scoped ON public.accomplishment_reports FOR UPDATE TO authenticated
USING (public.app_current_role() IN ('Super Admin', 'OVPRE Admin') OR (public.app_current_role() = 'Extension Coordinator' AND public.app_profile_in_current_department(submitted_by)))
WITH CHECK (public.app_current_role() IN ('Super Admin', 'OVPRE Admin') OR (public.app_current_role() = 'Extension Coordinator' AND public.app_profile_in_current_department(submitted_by)));
CREATE POLICY accomplishments_delete_admin ON public.accomplishment_reports FOR DELETE TO authenticated
USING (public.app_current_role() IN ('Super Admin', 'OVPRE Admin'));

DROP POLICY IF EXISTS activities_read_active ON public.activities;
CREATE POLICY activities_read_active ON public.activities FOR SELECT TO authenticated
USING (public.app_current_profile_id() IS NOT NULL AND archived_at IS NULL);
DROP POLICY IF EXISTS activities_insert_owned ON public.activities;
CREATE POLICY activities_insert_owned ON public.activities FOR INSERT TO authenticated
WITH CHECK (created_by = public.app_current_profile_id() AND public.app_current_role() IN ('Research Coordinator', 'Extension Coordinator', 'OVPRE Admin', 'Super Admin'));
DROP POLICY IF EXISTS activities_update_managers ON public.activities;
CREATE POLICY activities_update_managers ON public.activities FOR UPDATE TO authenticated
USING (public.app_current_role() IN ('Research Coordinator', 'Extension Coordinator', 'OVPRE Admin', 'Super Admin') AND (created_by = public.app_current_profile_id() OR public.app_current_role() IN ('OVPRE Admin', 'Super Admin')))
WITH CHECK (public.app_current_role() IN ('Research Coordinator', 'Extension Coordinator', 'OVPRE Admin', 'Super Admin'));
DROP POLICY IF EXISTS activities_delete_managers ON public.activities;
CREATE POLICY activities_delete_managers ON public.activities FOR DELETE TO authenticated
USING (public.app_current_role() IN ('OVPRE Admin', 'Super Admin') OR (created_by = public.app_current_profile_id() AND approval_status <> 'Approved'));

DROP POLICY IF EXISTS announcements_read_published ON public.announcements;
CREATE POLICY announcements_read_published ON public.announcements FOR SELECT TO authenticated
USING (status = 'Published' OR public.app_current_role() IN ('Super Admin', 'OVPRE Admin'));
DROP POLICY IF EXISTS announcements_manage_admin ON public.announcements;
CREATE POLICY announcements_manage_admin ON public.announcements FOR ALL TO authenticated
USING (public.app_current_role() IN ('Super Admin', 'OVPRE Admin'))
WITH CHECK (public.app_current_role() IN ('Super Admin', 'OVPRE Admin'));

DROP POLICY IF EXISTS notifications_read_own ON public.notifications;
CREATE POLICY notifications_read_own ON public.notifications FOR SELECT TO authenticated
USING (user_id = public.app_current_profile_id());
DROP POLICY IF EXISTS notifications_update_own ON public.notifications;
CREATE POLICY notifications_update_own ON public.notifications FOR UPDATE TO authenticated
USING (user_id = public.app_current_profile_id())
WITH CHECK (user_id = public.app_current_profile_id());

DROP POLICY IF EXISTS audit_logs_select_admin ON public.audit_logs;
CREATE POLICY audit_logs_select_admin ON public.audit_logs FOR SELECT TO authenticated
USING (public.app_current_role() = 'Super Admin');
DROP POLICY IF EXISTS audit_logs_insert_self ON public.audit_logs;
CREATE POLICY audit_logs_insert_self ON public.audit_logs FOR INSERT TO authenticated
WITH CHECK (user_id = public.app_current_profile_id());
DROP POLICY IF EXISTS review_logs_select_authorized ON public.review_logs;
CREATE POLICY review_logs_select_authorized ON public.review_logs FOR SELECT TO authenticated
USING (
    public.app_current_role() IN ('Super Admin', 'OVPRE Admin')
    OR (public.app_current_role() = 'Research Coordinator' AND entity_type = 'research' AND EXISTS (
        SELECT 1 FROM public.research_projects scoped_research WHERE scoped_research.id = entity_id AND public.app_profile_in_current_department(scoped_research.submitted_by)
    ))
    OR (public.app_current_role() = 'Extension Coordinator' AND entity_type = 'extension' AND EXISTS (
        SELECT 1 FROM public.extension_projects scoped_extension WHERE scoped_extension.id = entity_id AND public.app_profile_in_current_department(scoped_extension.submitted_by)
    ))
);
DROP POLICY IF EXISTS review_logs_insert_self ON public.review_logs;
CREATE POLICY review_logs_insert_self ON public.review_logs FOR INSERT TO authenticated
WITH CHECK (user_id = public.app_current_profile_id());
DROP POLICY IF EXISTS login_history_select_admin ON public.login_history;
CREATE POLICY login_history_select_admin ON public.login_history FOR SELECT TO authenticated
USING (public.app_current_role() = 'Super Admin');

DROP POLICY IF EXISTS document_versions_read_scoped ON public.document_versions;
CREATE POLICY document_versions_read_scoped ON public.document_versions FOR SELECT TO authenticated
USING (EXISTS (SELECT 1 FROM public.documents d WHERE d.id = document_id));
DROP POLICY IF EXISTS document_versions_insert_self ON public.document_versions;
CREATE POLICY document_versions_insert_self ON public.document_versions FOR INSERT TO authenticated
WITH CHECK (modified_by = public.app_current_profile_id() AND public.app_current_role() IN ('Super Admin', 'OVPRE Admin', 'Research Coordinator', 'Extension Coordinator', 'Faculty Extensionist', 'Researcher'));
DROP POLICY IF EXISTS archived_documents_read_scoped ON public.archived_documents;
CREATE POLICY archived_documents_read_scoped ON public.archived_documents FOR SELECT TO authenticated
USING (EXISTS (SELECT 1 FROM public.documents d WHERE d.id = document_id));
DROP POLICY IF EXISTS archived_documents_insert_admin ON public.archived_documents;
CREATE POLICY archived_documents_insert_admin ON public.archived_documents FOR INSERT TO authenticated
WITH CHECK (archived_by = public.app_current_profile_id() AND public.app_current_role() IN ('Super Admin', 'OVPRE Admin'));

DROP POLICY IF EXISTS record_files_insert_owner ON storage.objects;
CREATE POLICY record_files_insert_owner ON storage.objects FOR INSERT TO authenticated
WITH CHECK (bucket_id = 'irecord-records' AND owner_id = auth.uid()::text AND public.app_current_role() IN ('Super Admin', 'OVPRE Admin', 'Research Coordinator', 'Extension Coordinator', 'Faculty Extensionist', 'Researcher'));
DROP POLICY IF EXISTS record_files_read_authorized ON storage.objects;
CREATE POLICY record_files_read_authorized ON storage.objects FOR SELECT TO authenticated
USING (
    bucket_id = 'irecord-records'
    AND EXISTS (
        SELECT 1 FROM public.documents d
        WHERE d.filename = 'storage:' || name
          AND (public.app_current_role() IN ('Super Admin', 'OVPRE Admin')
               OR d.uploaded_by = public.app_current_profile_id()
               OR (public.app_current_role() IN ('Research Coordinator', 'Extension Coordinator') AND d.department_id = public.app_current_department_id()))
    )
);
DROP POLICY IF EXISTS record_files_delete_admin ON storage.objects;
CREATE POLICY record_files_delete_admin ON storage.objects FOR DELETE TO authenticated
USING (bucket_id = 'irecord-records' AND public.app_current_role() = 'Super Admin');

INSERT INTO storage.buckets (id, name, public)
VALUES ('irecord-records', 'irecord-records', FALSE)
ON CONFLICT (id) DO UPDATE SET public = FALSE;

COMMIT;
