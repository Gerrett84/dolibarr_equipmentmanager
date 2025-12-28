-- Equipment Manager v1.7 - Multiple Report Entries per Equipment
-- Allows multiple work entries per equipment per intervention

-- Step 1: Add entry_number column (default 1 for existing records)
ALTER TABLE llx_equipmentmanager_intervention_detail
    ADD COLUMN entry_number INT NOT NULL DEFAULT 1 AFTER fk_equipment;

-- Step 2: Drop the old unique key
ALTER TABLE llx_equipmentmanager_intervention_detail
    DROP INDEX uk_intervention_equipment;

-- Step 3: Create new unique key including entry_number
ALTER TABLE llx_equipmentmanager_intervention_detail
    ADD UNIQUE KEY uk_intervention_equipment_entry (fk_intervention, fk_equipment, entry_number);

-- Step 4: Add index for faster entry lookups
ALTER TABLE llx_equipmentmanager_intervention_detail
    ADD INDEX idx_intervention_equipment (fk_intervention, fk_equipment);
