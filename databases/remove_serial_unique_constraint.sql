-- Remove UNIQUE constraint from serial_number to allow N/A duplicates
-- This allows multiple devices to have 'N/A' as serial number

ALTER TABLE devices DROP INDEX serial_number;
