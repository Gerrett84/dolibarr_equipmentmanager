-- Equipment Manager v4.0 Update - Maintenance Planner
-- Copyright (C) 2024-2025 Equipment Manager Module

-- ============================================
-- EQUIPMENT TYPES - Add default duration and interval
-- ============================================

ALTER TABLE llx_equipmentmanager_equipment_types
    ADD COLUMN IF NOT EXISTS default_duration INT DEFAULT 0 COMMENT 'Default planned duration in minutes',
    ADD COLUMN IF NOT EXISTS default_interval VARCHAR(20) DEFAULT 'yearly' COMMENT 'Default maintenance interval: yearly or semi_annual';

-- ============================================
-- EQUIPMENT - Add individual duration and interval
-- ============================================

ALTER TABLE llx_equipmentmanager_equipment
    ADD COLUMN IF NOT EXISTS planned_duration INT DEFAULT NULL COMMENT 'Individual planned duration in minutes (NULL = use type default)',
    ADD COLUMN IF NOT EXISTS maintenance_interval VARCHAR(20) DEFAULT NULL COMMENT 'Individual maintenance interval (NULL = use type default)';

-- ============================================
-- SET DEFAULT DURATIONS FOR EXISTING TYPES
-- ============================================

-- Schiebetür: 60 min
UPDATE llx_equipmentmanager_equipment_types
SET default_duration = 60, default_interval = 'yearly'
WHERE code = 'door_sliding';

-- Drehtür: 30 min
UPDATE llx_equipmentmanager_equipment_types
SET default_duration = 30, default_interval = 'yearly'
WHERE code = 'door_swing';

-- Feststellanlage: 15 min
UPDATE llx_equipmentmanager_equipment_types
SET default_duration = 15, default_interval = 'yearly'
WHERE code = 'hold_open';

-- RWA: 45 min
UPDATE llx_equipmentmanager_equipment_types
SET default_duration = 45, default_interval = 'yearly'
WHERE code = 'rwa';

-- RWS: 20 min
UPDATE llx_equipmentmanager_equipment_types
SET default_duration = 20, default_interval = 'yearly'
WHERE code = 'rws';

-- Türschließer: 10 min
UPDATE llx_equipmentmanager_equipment_types
SET default_duration = 10, default_interval = 'yearly'
WHERE code = 'door_closer';

-- Brandschutztor: 30 min
UPDATE llx_equipmentmanager_equipment_types
SET default_duration = 30, default_interval = 'yearly'
WHERE code = 'fire_gate';

-- Brandschutztür: 30 min (same as fire_gate)
UPDATE llx_equipmentmanager_equipment_types
SET default_duration = 30, default_interval = 'yearly'
WHERE code = 'fire_door';

-- Sonstige: 0 min
UPDATE llx_equipmentmanager_equipment_types
SET default_duration = 0, default_interval = 'yearly'
WHERE code = 'other';

-- ============================================
-- EQUIPMENT - Add contract link (v4.0.1)
-- ============================================

ALTER TABLE llx_equipmentmanager_equipment
    ADD COLUMN IF NOT EXISTS fk_contract INT DEFAULT NULL COMMENT 'Link to maintenance contract (llx_contrat)';

ALTER TABLE llx_equipmentmanager_equipment
    ADD INDEX IF NOT EXISTS idx_equipment_fk_contract (fk_contract);
