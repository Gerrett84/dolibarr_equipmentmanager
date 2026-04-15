-- v5.0.1 Fix: Remove duplicate ItemVerdrahtung F6 entries
-- In fire_door_fsa and fire_gate the FSA section had both F6 and F7 (ItemVerdrahtung).
-- F6 is the old entry, F7 is the correct current one. Remove F6.

DELETE ci FROM llx_equipmentmanager_checklist_items ci
JOIN llx_equipmentmanager_checklist_sections s ON s.rowid = ci.fk_section
JOIN llx_equipmentmanager_checklist_templates t ON t.rowid = s.fk_template
WHERE ci.code = 'F6'
  AND ci.label = 'ItemVerdrahtung'
  AND s.code = 'fsa'
  AND t.equipment_type_code IN ('fire_door_fsa', 'fire_gate');
