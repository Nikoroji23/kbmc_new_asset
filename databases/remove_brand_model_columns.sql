-- Remove brand and model columns from devices table
-- These columns were deprecated and are no longer needed

ALTER TABLE devices DROP COLUMN IF EXISTS brand;
ALTER TABLE devices DROP COLUMN IF EXISTS model;
