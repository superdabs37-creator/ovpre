-- READ ONLY: run in the Supabase SQL Editor before applying any role/status migration.
-- Review the result sets, especially RIUH and Suspended accounts, before approving changes.

-- 1. Current profile role/status values and account counts.
SELECT role::text AS current_role, status::text AS current_status, COUNT(*) AS accounts
FROM public.users
GROUP BY role::text, status::text
ORDER BY current_role, current_status;

-- 2. RIUH assignments: keep these records and request explicit reassignment approval.
SELECT id, fullname, email, role::text AS current_role, status::text AS current_status, created_at
FROM public.users
WHERE role::text ILIKE '%RIUH%'
ORDER BY id;

-- 3. Suspended accounts: review these before proposing a conversion to Inactive.
SELECT id, fullname, email, role::text AS current_role, status::text AS current_status, created_at, last_login_at
FROM public.users
WHERE status::text = 'Suspended'
ORDER BY id;

-- 4. Role/status constraints and enum labels on the current users table.
SELECT column_info.column_name, column_info.data_type, column_info.udt_name
FROM information_schema.columns AS column_info
WHERE column_info.table_schema = 'public'
  AND column_info.table_name = 'users'
  AND column_info.column_name IN ('role', 'status')
ORDER BY column_info.column_name;

SELECT constraint_info.conname AS constraint_name,
       pg_get_constraintdef(constraint_info.oid) AS definition
FROM pg_constraint AS constraint_info
JOIN pg_class AS table_info ON table_info.oid = constraint_info.conrelid
JOIN pg_namespace AS schema_info ON schema_info.oid = table_info.relnamespace
WHERE schema_info.nspname = 'public'
  AND table_info.relname = 'users'
  AND constraint_info.contype = 'c'
ORDER BY constraint_info.conname;

SELECT type_info.typname AS enum_type, enum_value.enumlabel AS enum_label
FROM pg_type AS type_info
JOIN pg_enum AS enum_value ON enum_value.enumtypid = type_info.oid
JOIN pg_attribute AS column_info ON column_info.atttypid = type_info.oid
JOIN pg_class AS table_info ON table_info.oid = column_info.attrelid
JOIN pg_namespace AS schema_info ON schema_info.oid = table_info.relnamespace
WHERE schema_info.nspname = 'public'
  AND table_info.relname = 'users'
  AND column_info.attname IN ('role', 'status')
ORDER BY type_info.typname, enum_value.enumsortorder;

-- 5. Existing RLS state and policies. This is a catalog read only.
SELECT table_name, rowsecurity AS rls_enabled, forcerowsecurity AS rls_forced
FROM pg_class AS relation_info
JOIN pg_namespace AS schema_info ON schema_info.oid = relation_info.relnamespace
WHERE schema_info.nspname = 'public'
  AND relation_info.relkind = 'r'
  AND relation_info.relname IN ('users', 'documents', 'research_projects', 'extension_projects', 'accomplishment_reports', 'announcements', 'activities');

SELECT tablename, policyname, roles, cmd, qual, with_check
FROM pg_policies
WHERE schemaname = 'public'
ORDER BY tablename, policyname;

-- 6. Auth account totals for planning the profile-to-auth linking step.
SELECT COUNT(*) AS supabase_auth_users,
       COUNT(*) FILTER (WHERE email_confirmed_at IS NOT NULL) AS email_confirmed_auth_users
FROM auth.users;
