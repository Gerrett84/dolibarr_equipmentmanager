-- Equipment Manager v4.6 Update And Fix
-- Copyright (C) 2024-2025 Equipment Manager Module

-- ============================================
-- EQUIPMENT - Add contract link (v4.6.1)
-- ============================================

ALTER TABLE llx_equipmentmanager_equipment
    ADD COLUMN IF NOT EXISTS maintenance_month INT DEFAULT NULL COMMENT 'Maintenance Month';

ALTER TABLE llx_equipmentmanager_equipment
    ADD COLUMN IF NOT EXISTS last_maintenance_date DATE DEFAULT NULL COMMENT 'Last maintenance date';

ALTER TABLE llx_equipmentmanager_equipment
    ADD COLUMN IF NOT EXISTS next_maintenance_date DATE DEFAULT NULL COMMENT 'Nex maintenance date';
