-- v5.3.0: Add threshold_max to checklist items for number-type force measurements
ALTER TABLE llx_equipmentmanager_checklist_items
    ADD COLUMN threshold_max DECIMAL(10,2) NULL AFTER answer_type;

-- Set 400 N (DIN EN 12453 dynamic force limit) for all existing force measurement items
UPDATE llx_equipmentmanager_checklist_items
    SET threshold_max = 400
    WHERE answer_type = 'number';
