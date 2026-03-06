-- Equipment Manager v4.6.1 Update
-- Copyright (C) 2026 Equipment Manager Module
--
-- Adds missing columns to llx_equipmentmanager_equipment
-- These columns were used in PHP code but never added via a migration script.

ALTER TABLE llx_equipmentmanager_equipment
    ADD COLUMN IF NOT EXISTS maintenance_month INT DEFAULT NULL COMMENT 'Maintenance month (1-12)';

ALTER TABLE llx_equipmentmanager_equipment
    ADD COLUMN IF NOT EXISTS last_maintenance_date DATE DEFAULT NULL COMMENT 'Last maintenance date';

ALTER TABLE llx_equipmentmanager_equipment
    ADD COLUMN IF NOT EXISTS next_maintenance_date DATE DEFAULT NULL COMMENT 'Next maintenance date';

ALTER TABLE llx_equipmentmanager_equipment
    ADD INDEX IF NOT EXISTS idx_equipment_maintenance_month (maintenance_month);

ALTER TABLE llx_equipmentmanager_equipment
    ADD INDEX IF NOT EXISTS idx_equipment_next_maintenance_date (next_maintenance_date);
