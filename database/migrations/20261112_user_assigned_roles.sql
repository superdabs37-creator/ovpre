-- Migration: Add assigned_roles column to users table for multi-role portal switching
-- Works for both MySQL and PostgreSQL

-- PostgreSQL:
ALTER TABLE public.users ADD COLUMN IF NOT EXISTS assigned_roles TEXT DEFAULT NULL;

-- MySQL:
-- ALTER TABLE users ADD COLUMN IF NOT EXISTS assigned_roles TEXT DEFAULT NULL AFTER role;
