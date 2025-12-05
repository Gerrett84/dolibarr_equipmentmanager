-- Update existing equipment table to new structure
-- Run this if you already installed the module

-- Add new field for equipment number mode
ALTER TABLE llx_equipmentmanager_equipment ADD COLUMN IF NOT EXISTS equipment_number_mode varchar(10) DEFAULT 'auto' AFTER equipment_number;

-- Rename installation_location to location_note
ALTER TABLE llx_equipmentmanager_equipment CHANGE COLUMN installation_location location_note text;

-- Add serial_number field
ALTER TABLE llx_equipmentmanager_equipment ADD COLUMN IF NOT EXISTS serial_number varchar(255) AFTER location_note;

-- Add manufacturer field
ALTER TABLE llx_equipmentmanager_equipment ADD COLUMN IF NOT EXISTS manufacturer varchar(255) AFTER equipment_type;

-- Add door_wings field (1-flüglig / 2-flüglig)
ALTER TABLE llx_equipmentmanager_equipment ADD COLUMN IF NOT EXISTS door_wings varchar(20) AFTER manufacturer;

-- Add object address (Lieferadresse) field
ALTER TABLE llx_equipmentmanager_equipment ADD COLUMN IF NOT EXISTS fk_address INT AFTER fk_soc;
ALTER TABLE llx_equipmentmanager_equipment ADD INDEX IF NOT EXISTS idx_equipment_fk_address (fk_address);

-- Remove description field (we use location_note instead)
ALTER TABLE llx_equipmentmanager_equipment DROP COLUMN IF EXISTS description;

-- Fix duplicate key issue - drop old unique constraint on ref
ALTER TABLE llx_equipmentmanager_equipment DROP INDEX IF EXISTS uk_equipment_ref;

-- Add new unique constraint on equipment_number instead
ALTER TABLE llx_equipmentmanager_equipment ADD UNIQUE INDEX IF NOT EXISTS uk_equipment_number (equipment_number, entity);

-- Fix any existing entries with 'auto' or 'manual' as ref
UPDATE llx_equipmentmanager_equipment 
SET ref = CONCAT('EQU-', LPAD(rowid, 4, '0'))
WHERE ref IN ('auto', 'manual', 'auto-1', 'manual-1');