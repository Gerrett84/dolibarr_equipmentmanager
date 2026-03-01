-- Equipment Manager v4.5 - Commissioning and Acceptance Fields
-- Adds fields for Inbetriebnahme (commissioning) and Abnahme (acceptance) tracking
-- Also adds general work support (fk_equipment nullable) and photo field

-- v4.2: Add photo column to intervention details for defect photos
ALTER TABLE llx_equipmentmanager_intervention_detail
ADD COLUMN IF NOT EXISTS photo varchar(255) DEFAULT NULL AFTER notes;

-- v4.2: Allow entries without equipment (general work items / Allgemeine Arbeiten)
ALTER TABLE llx_equipmentmanager_intervention_detail
MODIFY fk_equipment INT NULL;

-- v4.2: Also allow materials without equipment reference
ALTER TABLE llx_equipmentmanager_intervention_material
MODIFY fk_equipment INT NULL;

-- v4.5: Add commissioning (Inbetriebnahme) fields
ALTER TABLE llx_equipmentmanager_intervention_detail
ADD COLUMN IF NOT EXISTS commissioning_done TINYINT(1) DEFAULT 0 AFTER photo;

ALTER TABLE llx_equipmentmanager_intervention_detail
ADD COLUMN IF NOT EXISTS commissioning_date DATE DEFAULT NULL AFTER commissioning_done;

ALTER TABLE llx_equipmentmanager_intervention_detail
ADD COLUMN IF NOT EXISTS commissioning_note TEXT DEFAULT NULL AFTER commissioning_date;

-- v4.5: Add acceptance (Abnahme) fields
ALTER TABLE llx_equipmentmanager_intervention_detail
ADD COLUMN IF NOT EXISTS acceptance_done TINYINT(1) DEFAULT 0 AFTER commissioning_note;

ALTER TABLE llx_equipmentmanager_intervention_detail
ADD COLUMN IF NOT EXISTS acceptance_date DATE DEFAULT NULL AFTER acceptance_done;

ALTER TABLE llx_equipmentmanager_intervention_detail
ADD COLUMN IF NOT EXISTS acceptance_defect_free TINYINT(1) DEFAULT 1 AFTER acceptance_date;

ALTER TABLE llx_equipmentmanager_intervention_detail
ADD COLUMN IF NOT EXISTS acceptance_note TEXT DEFAULT NULL AFTER acceptance_defect_free;

ALTER TABLE llx_equipmentmanager_intervention_detail
ADD COLUMN IF NOT EXISTS instruction_done TINYINT(1) DEFAULT 0 AFTER acceptance_note;

ALTER TABLE llx_equipmentmanager_intervention_detail
ADD COLUMN IF NOT EXISTS testbook_handed TINYINT(1) DEFAULT 0 AFTER instruction_done;

-- v4.5: Add LOCATIONOFJOB contact type for interventions (Einsatzort)
INSERT IGNORE INTO llx_c_type_contact (element, source, code, libelle, active, module, position)
VALUES ('fichinter', 'external', 'LOCATIONOFJOB', 'Einsatzort', 1, NULL, 50);
