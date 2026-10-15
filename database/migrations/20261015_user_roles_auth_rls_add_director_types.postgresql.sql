BEGIN;

ALTER TABLE public.users DROP CONSTRAINT IF EXISTS users_role_allowed_check;
ALTER TABLE public.users ADD CONSTRAINT users_role_allowed_check CHECK (role IN (
    'Super Admin', 'OVPRE Admin', 'Research Coordinator', 'Extension Coordinator', 'Faculty Extensionist', 'Researcher',
    'Research Director', 'Extension Director', 'Director for Research and Creative Works', 'OVPRE Secretariat'
));

CREATE OR REPLACE FUNCTION public.app_current_role()
RETURNS TEXT LANGUAGE SQL STABLE SECURITY DEFINER SET search_path = public, auth
AS $$
    SELECT CASE role
        WHEN 'Research Director' THEN 'Research Coordinator'
        WHEN 'Director for Research and Creative Works' THEN 'Research Coordinator'
        WHEN 'Extension Director' THEN 'Extension Coordinator'
        WHEN 'OVPRE Secretariat' THEN 'OVPRE Admin'
        ELSE role
    END
    FROM public.users
    WHERE auth_user_id = auth.uid() AND status = 'Active' AND deleted_at IS NULL
    LIMIT 1
$$;

COMMIT;
