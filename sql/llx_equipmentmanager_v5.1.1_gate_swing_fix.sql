-- v5.1.1 — Schwenkflügeltor: Öffnungskraftmessung ergänzt
-- S3 wird S4 (Schließkraft), neues S3 = Öffnungskraft

-- Bestehende Schließkraftmessung auf Position 40 verschieben
UPDATE llx_equipmentmanager_checklist_items ci
JOIN llx_equipmentmanager_checklist_sections s ON s.rowid = ci.fk_section
JOIN llx_equipmentmanager_checklist_templates t ON t.rowid = s.fk_template
SET ci.code = 'S4', ci.position = 40
WHERE t.equipment_type_code = 'gate_swing'
  AND s.code = 'sicherheit'
  AND ci.label = 'ItemSchliesskraftmessungN';

-- Öffnungskraftmessung als S3 einfügen
INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'S3', 'ItemOeffnungskraftmessungN', 'number', 30, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON t.rowid = s.fk_template
WHERE t.equipment_type_code = 'gate_swing' AND s.code = 'sicherheit';
