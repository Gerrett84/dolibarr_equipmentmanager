-- Equipment Manager v4.0.1 - Remove duplicate Rauchmelder checklist item
-- The item "Rauchmelder" (ItemRauchmelderSichtpruefung) was confusing
-- since "Auslösung durch Rauchmelder" (ItemAusloesungRauchmelder) already covers this

-- Delete the redundant ItemRauchmelderSichtpruefung items
DELETE FROM llx_equipmentmanager_checklist_items
WHERE code = 'F5'
AND label = 'ItemRauchmelderSichtpruefung';

-- Update positions of following items (F6 -> F5, F7 -> F6, etc.)
UPDATE llx_equipmentmanager_checklist_items i
JOIN llx_equipmentmanager_checklist_sections s ON i.fk_section = s.rowid
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
SET i.code = CONCAT('F', CAST(SUBSTRING(i.code, 2) AS UNSIGNED) - 1),
    i.position = i.position - 10
WHERE s.code = 'fsa'
AND t.equipment_type_code IN ('fire_door_fsa', 'fire_gate')
AND i.code REGEXP '^F[6-9]$'
AND i.position >= 60;
