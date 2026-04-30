-- v5.1.0 — Neue Tortypen: Schwenkflügeltor, Schiebetor, Sektionaltor, Schwingtor
-- Norm: DIN EN 12453 (Kraftbetätigte Tore — Sicherheit)

-- ============================================
-- EQUIPMENT TYPES
-- ============================================

INSERT IGNORE INTO llx_equipmentmanager_equipment_types (code, label, position, active, date_creation, entity) VALUES
('gate_swing',      'GateSwing',      80, 1, NOW(), 1),
('gate_sliding',    'GateSliding',    81, 1, NOW(), 1),
('gate_sectional',  'GateSectional',  82, 1, NOW(), 1),
('gate_upandover',  'GateUpAndOver',  83, 1, NOW(), 1);

-- ============================================
-- CHECKLIST TEMPLATES
-- ============================================

INSERT IGNORE INTO llx_equipmentmanager_checklist_templates (equipment_type_code, label, norm_reference, position, active, date_creation, entity) VALUES
('gate_swing',     'ChecklistGateSwing',     'DIN EN 12453', 80, 1, NOW(), 1),
('gate_sliding',   'ChecklistGateSliding',   'DIN EN 12453', 81, 1, NOW(), 1),
('gate_sectional', 'ChecklistGateSectional', 'DIN EN 12453', 82, 1, NOW(), 1),
('gate_upandover', 'ChecklistGateUpAndOver', 'DIN EN 12453', 83, 1, NOW(), 1);

-- ============================================
-- SECTIONS — Schwenkflügeltor
-- ============================================

INSERT INTO llx_equipmentmanager_checklist_sections (fk_template, code, label, position, active, date_creation)
SELECT rowid, 'toranlage', 'SectionToranlage', 10, 1, NOW() FROM llx_equipmentmanager_checklist_templates WHERE equipment_type_code = 'gate_swing';
INSERT INTO llx_equipmentmanager_checklist_sections (fk_template, code, label, position, active, date_creation)
SELECT rowid, 'antrieb', 'SectionAntriebFunktion', 20, 1, NOW() FROM llx_equipmentmanager_checklist_templates WHERE equipment_type_code = 'gate_swing';
INSERT INTO llx_equipmentmanager_checklist_sections (fk_template, code, label, position, active, date_creation)
SELECT rowid, 'sicherheit', 'SectionSicherheit', 30, 1, NOW() FROM llx_equipmentmanager_checklist_templates WHERE equipment_type_code = 'gate_swing';
INSERT INTO llx_equipmentmanager_checklist_sections (fk_template, code, label, position, active, date_creation)
SELECT rowid, 'ergebnis', 'SectionErgebnis', 90, 1, NOW() FROM llx_equipmentmanager_checklist_templates WHERE equipment_type_code = 'gate_swing';

-- ============================================
-- SECTIONS — Schiebetor
-- ============================================

INSERT INTO llx_equipmentmanager_checklist_sections (fk_template, code, label, position, active, date_creation)
SELECT rowid, 'toranlage', 'SectionToranlage', 10, 1, NOW() FROM llx_equipmentmanager_checklist_templates WHERE equipment_type_code = 'gate_sliding';
INSERT INTO llx_equipmentmanager_checklist_sections (fk_template, code, label, position, active, date_creation)
SELECT rowid, 'antrieb', 'SectionAntriebFunktion', 20, 1, NOW() FROM llx_equipmentmanager_checklist_templates WHERE equipment_type_code = 'gate_sliding';
INSERT INTO llx_equipmentmanager_checklist_sections (fk_template, code, label, position, active, date_creation)
SELECT rowid, 'sicherheit', 'SectionSicherheit', 30, 1, NOW() FROM llx_equipmentmanager_checklist_templates WHERE equipment_type_code = 'gate_sliding';
INSERT INTO llx_equipmentmanager_checklist_sections (fk_template, code, label, position, active, date_creation)
SELECT rowid, 'ergebnis', 'SectionErgebnis', 90, 1, NOW() FROM llx_equipmentmanager_checklist_templates WHERE equipment_type_code = 'gate_sliding';

-- ============================================
-- SECTIONS — Sektionaltor
-- ============================================

INSERT INTO llx_equipmentmanager_checklist_sections (fk_template, code, label, position, active, date_creation)
SELECT rowid, 'toranlage', 'SectionToranlage', 10, 1, NOW() FROM llx_equipmentmanager_checklist_templates WHERE equipment_type_code = 'gate_sectional';
INSERT INTO llx_equipmentmanager_checklist_sections (fk_template, code, label, position, active, date_creation)
SELECT rowid, 'feder', 'SectionFeder', 20, 1, NOW() FROM llx_equipmentmanager_checklist_templates WHERE equipment_type_code = 'gate_sectional';
INSERT INTO llx_equipmentmanager_checklist_sections (fk_template, code, label, position, active, date_creation)
SELECT rowid, 'antrieb', 'SectionAntriebFunktion', 30, 1, NOW() FROM llx_equipmentmanager_checklist_templates WHERE equipment_type_code = 'gate_sectional';
INSERT INTO llx_equipmentmanager_checklist_sections (fk_template, code, label, position, active, date_creation)
SELECT rowid, 'sicherheit', 'SectionSicherheit', 40, 1, NOW() FROM llx_equipmentmanager_checklist_templates WHERE equipment_type_code = 'gate_sectional';
INSERT INTO llx_equipmentmanager_checklist_sections (fk_template, code, label, position, active, date_creation)
SELECT rowid, 'ergebnis', 'SectionErgebnis', 90, 1, NOW() FROM llx_equipmentmanager_checklist_templates WHERE equipment_type_code = 'gate_sectional';

-- ============================================
-- SECTIONS — Schwingtor
-- ============================================

INSERT INTO llx_equipmentmanager_checklist_sections (fk_template, code, label, position, active, date_creation)
SELECT rowid, 'toranlage', 'SectionToranlage', 10, 1, NOW() FROM llx_equipmentmanager_checklist_templates WHERE equipment_type_code = 'gate_upandover';
INSERT INTO llx_equipmentmanager_checklist_sections (fk_template, code, label, position, active, date_creation)
SELECT rowid, 'feder', 'SectionFeder', 20, 1, NOW() FROM llx_equipmentmanager_checklist_templates WHERE equipment_type_code = 'gate_upandover';
INSERT INTO llx_equipmentmanager_checklist_sections (fk_template, code, label, position, active, date_creation)
SELECT rowid, 'antrieb', 'SectionAntriebFunktion', 30, 1, NOW() FROM llx_equipmentmanager_checklist_templates WHERE equipment_type_code = 'gate_upandover';
INSERT INTO llx_equipmentmanager_checklist_sections (fk_template, code, label, position, active, date_creation)
SELECT rowid, 'sicherheit', 'SectionSicherheit', 40, 1, NOW() FROM llx_equipmentmanager_checklist_templates WHERE equipment_type_code = 'gate_upandover';
INSERT INTO llx_equipmentmanager_checklist_sections (fk_template, code, label, position, active, date_creation)
SELECT rowid, 'ergebnis', 'SectionErgebnis', 90, 1, NOW() FROM llx_equipmentmanager_checklist_templates WHERE equipment_type_code = 'gate_upandover';

-- ============================================
-- ITEMS — Schwenkflügeltor: Toranlage
-- ============================================

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'T1', 'ItemTorblattZargeD', 'ok_mangel', 10, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'gate_swing' AND s.code = 'toranlage';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'T2', 'ItemEndanschlagVerriegelung', 'ok_mangel', 20, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'gate_swing' AND s.code = 'toranlage';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'T3', 'ItemTragarme', 'ok_mangel', 30, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'gate_swing' AND s.code = 'toranlage';

-- Schwenkflügeltor: Antrieb
INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'A1', 'ItemBefestigungAntrieb', 'ok_mangel', 10, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'gate_swing' AND s.code = 'antrieb';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'A2', 'ItemBewegungsablauf', 'ok_mangel', 20, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'gate_swing' AND s.code = 'antrieb';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'A3', 'ItemVerdrahtung', 'ok_mangel', 30, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'gate_swing' AND s.code = 'antrieb';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'A4', 'ItemAnsteuerung', 'ok_mangel', 40, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'gate_swing' AND s.code = 'antrieb';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'A5', 'ItemHandbetaetigung', 'ok_mangel', 50, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'gate_swing' AND s.code = 'antrieb';

-- Schwenkflügeltor: Sicherheit
INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'S1', 'ItemSicherheitskontakleiste', 'ok_mangel', 10, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'gate_swing' AND s.code = 'sicherheit';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'S2', 'ItemLichtschranke', 'ok_mangel', 20, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'gate_swing' AND s.code = 'sicherheit';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'S3', 'ItemOeffnungskraftmessungN', 'number', 30, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'gate_swing' AND s.code = 'sicherheit';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'S4', 'ItemSchliesskraftmessungN', 'number', 40, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'gate_swing' AND s.code = 'sicherheit';

-- Schwenkflügeltor: Ergebnis
INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'E1', 'ItemPruefungHerstellervorgabe', 'ja_nein', 10, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'gate_swing' AND s.code = 'ergebnis';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'E2', 'ItemEntsprichtDINEN12453', 'ja_nein', 20, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'gate_swing' AND s.code = 'ergebnis';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'E3', 'ItemPruefungBestanden', 'ja_nein', 30, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'gate_swing' AND s.code = 'ergebnis';

-- ============================================
-- ITEMS — Schiebetor: Toranlage
-- ============================================

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'T1', 'ItemTorblattZargeD', 'ok_mangel', 10, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'gate_sliding' AND s.code = 'toranlage';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'T2', 'ItemFahrschieneFuehrung', 'ok_mangel', 20, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'gate_sliding' AND s.code = 'toranlage';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'T3', 'ItemLaufrollen', 'ok_mangel', 30, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'gate_sliding' AND s.code = 'toranlage';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'T4', 'ItemZahnstangeKette', 'ok_mangel', 40, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'gate_sliding' AND s.code = 'toranlage';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'T5', 'ItemEndanschlagVerriegelung', 'ok_mangel', 50, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'gate_sliding' AND s.code = 'toranlage';

-- Schiebetor: Antrieb
INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'A1', 'ItemBefestigungAntrieb', 'ok_mangel', 10, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'gate_sliding' AND s.code = 'antrieb';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'A2', 'ItemBewegungsablauf', 'ok_mangel', 20, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'gate_sliding' AND s.code = 'antrieb';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'A3', 'ItemVerdrahtung', 'ok_mangel', 30, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'gate_sliding' AND s.code = 'antrieb';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'A4', 'ItemAnsteuerung', 'ok_mangel', 40, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'gate_sliding' AND s.code = 'antrieb';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'A5', 'ItemHandbetaetigung', 'ok_mangel', 50, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'gate_sliding' AND s.code = 'antrieb';

-- Schiebetor: Sicherheit
INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'S1', 'ItemSicherheitskontakleiste', 'ok_mangel', 10, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'gate_sliding' AND s.code = 'sicherheit';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'S2', 'ItemLichtschranke', 'ok_mangel', 20, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'gate_sliding' AND s.code = 'sicherheit';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'S3', 'ItemSchliesskraftmessungN', 'number', 30, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'gate_sliding' AND s.code = 'sicherheit';

-- Schiebetor: Ergebnis
INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'E1', 'ItemPruefungHerstellervorgabe', 'ja_nein', 10, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'gate_sliding' AND s.code = 'ergebnis';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'E2', 'ItemEntsprichtDINEN12453', 'ja_nein', 20, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'gate_sliding' AND s.code = 'ergebnis';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'E3', 'ItemPruefungBestanden', 'ja_nein', 30, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'gate_sliding' AND s.code = 'ergebnis';

-- ============================================
-- ITEMS — Sektionaltor: Toranlage
-- ============================================

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'T1', 'ItemTorblattZargeD', 'ok_mangel', 10, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'gate_sectional' AND s.code = 'toranlage';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'T2', 'ItemScharniereGelenkePaneele', 'ok_mangel', 20, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'gate_sectional' AND s.code = 'toranlage';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'T3', 'ItemFuehrungsschienen', 'ok_mangel', 30, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'gate_sectional' AND s.code = 'toranlage';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'T4', 'ItemAufwicklungWelle', 'ok_mangel', 40, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'gate_sectional' AND s.code = 'toranlage';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'T5', 'ItemAbsturzsicherung', 'ok_mangel_nv', 50, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'gate_sectional' AND s.code = 'toranlage';

-- Sektionaltor: Feder / Gegengewicht
INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'F1', 'ItemTorsionsfederSpannung', 'ok_mangel', 10, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'gate_sectional' AND s.code = 'feder';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'F2', 'ItemDrahtseil', 'ok_mangel', 20, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'gate_sectional' AND s.code = 'feder';

-- Sektionaltor: Antrieb
INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'A1', 'ItemBefestigungAntrieb', 'ok_mangel', 10, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'gate_sectional' AND s.code = 'antrieb';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'A2', 'ItemBewegungsablauf', 'ok_mangel', 20, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'gate_sectional' AND s.code = 'antrieb';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'A3', 'ItemVerdrahtung', 'ok_mangel', 30, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'gate_sectional' AND s.code = 'antrieb';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'A4', 'ItemAnsteuerung', 'ok_mangel', 40, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'gate_sectional' AND s.code = 'antrieb';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'A5', 'ItemHandbetaetigung', 'ok_mangel', 50, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'gate_sectional' AND s.code = 'antrieb';

-- Sektionaltor: Sicherheit
INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'S1', 'ItemSicherheitskontakleiste', 'ok_mangel', 10, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'gate_sectional' AND s.code = 'sicherheit';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'S2', 'ItemLichtschranke', 'ok_mangel', 20, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'gate_sectional' AND s.code = 'sicherheit';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'S3', 'ItemSchliesskraftmessungN', 'number', 30, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'gate_sectional' AND s.code = 'sicherheit';

-- Sektionaltor: Ergebnis
INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'E1', 'ItemPruefungHerstellervorgabe', 'ja_nein', 10, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'gate_sectional' AND s.code = 'ergebnis';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'E2', 'ItemEntsprichtDINEN12453', 'ja_nein', 20, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'gate_sectional' AND s.code = 'ergebnis';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'E3', 'ItemPruefungBestanden', 'ja_nein', 30, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'gate_sectional' AND s.code = 'ergebnis';

-- ============================================
-- ITEMS — Schwingtor: Toranlage
-- ============================================

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'T1', 'ItemTorblattZargeD', 'ok_mangel', 10, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'gate_upandover' AND s.code = 'toranlage';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'T2', 'ItemTragarme', 'ok_mangel', 20, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'gate_upandover' AND s.code = 'toranlage';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'T3', 'ItemFuehrungsschienen', 'ok_mangel', 30, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'gate_upandover' AND s.code = 'toranlage';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'T4', 'ItemEndanschlagVerriegelung', 'ok_mangel', 40, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'gate_upandover' AND s.code = 'toranlage';

-- Schwingtor: Feder / Gegengewicht
INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'F1', 'ItemGegengewichtAusgleich', 'ok_mangel', 10, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'gate_upandover' AND s.code = 'feder';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'F2', 'ItemDrahtseil', 'ok_mangel', 20, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'gate_upandover' AND s.code = 'feder';

-- Schwingtor: Antrieb
INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'A1', 'ItemBefestigungAntrieb', 'ok_mangel', 10, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'gate_upandover' AND s.code = 'antrieb';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'A2', 'ItemBewegungsablauf', 'ok_mangel', 20, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'gate_upandover' AND s.code = 'antrieb';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'A3', 'ItemVerdrahtung', 'ok_mangel', 30, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'gate_upandover' AND s.code = 'antrieb';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'A4', 'ItemAnsteuerung', 'ok_mangel', 40, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'gate_upandover' AND s.code = 'antrieb';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'A5', 'ItemHandbetaetigung', 'ok_mangel', 50, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'gate_upandover' AND s.code = 'antrieb';

-- Schwingtor: Sicherheit
INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'S1', 'ItemSicherheitskontakleiste', 'ok_mangel', 10, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'gate_upandover' AND s.code = 'sicherheit';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'S2', 'ItemLichtschranke', 'ok_mangel_nv', 20, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'gate_upandover' AND s.code = 'sicherheit';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'S3', 'ItemSchliesskraftmessungN', 'number', 30, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'gate_upandover' AND s.code = 'sicherheit';

-- Schwingtor: Ergebnis
INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'E1', 'ItemPruefungHerstellervorgabe', 'ja_nein', 10, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'gate_upandover' AND s.code = 'ergebnis';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'E2', 'ItemEntsprichtDINEN12453', 'ja_nein', 20, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'gate_upandover' AND s.code = 'ergebnis';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'E3', 'ItemPruefungBestanden', 'ja_nein', 30, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'gate_upandover' AND s.code = 'ergebnis';
