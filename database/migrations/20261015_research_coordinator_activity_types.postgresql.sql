-- Adds research-specific activity types to the existing activity_type enum.
-- Run this migration against the Supabase/PostgreSQL database before submitting
-- schedule requests that use the new options.
DO $$
DECLARE
    activity_type_schema TEXT;
    activity_type_name TEXT;
    activity_type_value TEXT;
BEGIN
    SELECT type_namespace.nspname, activity_type.typname
    INTO activity_type_schema, activity_type_name
    FROM pg_attribute column_info
    JOIN pg_class activity_table ON activity_table.oid = column_info.attrelid
    JOIN pg_namespace table_namespace ON table_namespace.oid = activity_table.relnamespace
    JOIN pg_type activity_type ON activity_type.oid = column_info.atttypid
    JOIN pg_namespace type_namespace ON type_namespace.oid = activity_type.typnamespace
    WHERE table_namespace.nspname = current_schema()
      AND activity_table.relname = 'activities'
      AND column_info.attname = 'activity_type'
      AND column_info.attnum > 0
      AND NOT column_info.attisdropped
      AND activity_type.typtype = 'e'
    LIMIT 1;

    IF activity_type_name IS NOT NULL THEN
        FOREACH activity_type_value IN ARRAY ARRAY[
            'Research Proposal',
            'Research Colloquium',
            'Research Completion and Final Presentation',
            'Research Publication',
            'Research Seminar',
            'Research Monitoring and Evaluation'
        ] LOOP
            EXECUTE format(
                'ALTER TYPE %I.%I ADD VALUE IF NOT EXISTS %L',
                activity_type_schema,
                activity_type_name,
                activity_type_value
            );
        END LOOP;
    END IF;
END
$$;
