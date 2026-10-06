-- LEGACY MIGRATION
-- Do not run on the current database schema.
-- Kept for historical reference only.
ALTER TABLE user_profile 
ADD COLUMN age INT NULL AFTER weight_kg;