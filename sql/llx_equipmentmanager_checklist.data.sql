-- Checklist Templates and Items Data
-- Copyright (C) 2024-2025 Equipment Manager Module
-- Version 3.0

-- ============================================
-- TEMPLATES
-- ============================================

INSERT INTO llx_equipmentmanager_checklist_templates (equipment_type_code, label, norm_reference, position, active, date_creation, entity) VALUES
('door_swing', 'ChecklistDoorSwing', 'EN 16005, ASR A1.7', 10, 1, NOW(), 1),
('door_sliding', 'ChecklistDoorSliding', 'EN 16005, AutSchR, ASR A1.7', 20, 1, NOW(), 1),
('fire_door', 'ChecklistFireDoor', 'Zulassungsbescheid', 30, 1, NOW(), 1),
('fire_door_fsa', 'ChecklistFireDoorFSA', 'Zulassungsbescheid / Baumusterprüfung', 35, 1, NOW(), 1),
('fire_gate', 'ChecklistFireGate', 'Zulassungsbescheid', 40, 1, NOW(), 1),
('door_closer', 'ChecklistDoorCloser', 'DIN EN 1154', 50, 1, NOW(), 1),
('rws', 'ChecklistRWS', 'EltVTR', 60, 1, NOW(), 1),
('rwa', 'ChecklistRWA', 'DIN 18232-2, ASR A1.6', 70, 1, NOW(), 1),
('other', 'ChecklistOther', 'Herstellerangabe', 999, 1, NOW(), 1);

-- ============================================
-- SECTIONS
-- ============================================

-- Door Swing Sections
INSERT INTO llx_equipmentmanager_checklist_sections (fk_template, code, label, position, active, date_creation)
SELECT rowid, 'tuerelement', 'SectionTuerelement', 10, 1, NOW() FROM llx_equipmentmanager_checklist_templates WHERE equipment_type_code = 'door_swing';
INSERT INTO llx_equipmentmanager_checklist_sections (fk_template, code, label, position, active, date_creation)
SELECT rowid, 'antrieb', 'SectionAntriebFunktion', 20, 1, NOW() FROM llx_equipmentmanager_checklist_templates WHERE equipment_type_code = 'door_swing';
INSERT INTO llx_equipmentmanager_checklist_sections (fk_template, code, label, position, active, date_creation)
SELECT rowid, 'fsa', 'SectionFSA', 30, 1, NOW() FROM llx_equipmentmanager_checklist_templates WHERE equipment_type_code = 'door_swing';
INSERT INTO llx_equipmentmanager_checklist_sections (fk_template, code, label, position, active, date_creation)
SELECT rowid, 'ergebnis', 'SectionErgebnis', 90, 1, NOW() FROM llx_equipmentmanager_checklist_templates WHERE equipment_type_code = 'door_swing';

-- Door Sliding Sections
INSERT INTO llx_equipmentmanager_checklist_sections (fk_template, code, label, position, active, date_creation)
SELECT rowid, 'tuerelement', 'SectionTuerelement', 10, 1, NOW() FROM llx_equipmentmanager_checklist_templates WHERE equipment_type_code = 'door_sliding';
INSERT INTO llx_equipmentmanager_checklist_sections (fk_template, code, label, position, active, date_creation)
SELECT rowid, 'antrieb', 'SectionAntriebFunktion', 20, 1, NOW() FROM llx_equipmentmanager_checklist_templates WHERE equipment_type_code = 'door_sliding';
INSERT INTO llx_equipmentmanager_checklist_sections (fk_template, code, label, position, active, date_creation)
SELECT rowid, 'fsa', 'SectionFSA', 30, 1, NOW() FROM llx_equipmentmanager_checklist_templates WHERE equipment_type_code = 'door_sliding';
INSERT INTO llx_equipmentmanager_checklist_sections (fk_template, code, label, position, active, date_creation)
SELECT rowid, 'ergebnis', 'SectionErgebnis', 90, 1, NOW() FROM llx_equipmentmanager_checklist_templates WHERE equipment_type_code = 'door_sliding';

-- Fire Door (without FSA) Sections
INSERT INTO llx_equipmentmanager_checklist_sections (fk_template, code, label, position, active, date_creation)
SELECT rowid, 'tuerelement', 'SectionTuerelement', 10, 1, NOW() FROM llx_equipmentmanager_checklist_templates WHERE equipment_type_code = 'fire_door';
INSERT INTO llx_equipmentmanager_checklist_sections (fk_template, code, label, position, active, date_creation)
SELECT rowid, 'ergebnis', 'SectionErgebnis', 90, 1, NOW() FROM llx_equipmentmanager_checklist_templates WHERE equipment_type_code = 'fire_door';

-- Fire Door with FSA (Feststellanlage) Sections
INSERT INTO llx_equipmentmanager_checklist_sections (fk_template, code, label, position, active, date_creation)
SELECT rowid, 'tuerelement', 'SectionTuerelement', 10, 1, NOW() FROM llx_equipmentmanager_checklist_templates WHERE equipment_type_code = 'fire_door_fsa';
INSERT INTO llx_equipmentmanager_checklist_sections (fk_template, code, label, position, active, date_creation)
SELECT rowid, 'fsa', 'SectionFSA', 30, 1, NOW() FROM llx_equipmentmanager_checklist_templates WHERE equipment_type_code = 'fire_door_fsa';
INSERT INTO llx_equipmentmanager_checklist_sections (fk_template, code, label, position, active, date_creation)
SELECT rowid, 'ergebnis', 'SectionErgebnis', 90, 1, NOW() FROM llx_equipmentmanager_checklist_templates WHERE equipment_type_code = 'fire_door_fsa';

-- Fire Gate Sections
INSERT INTO llx_equipmentmanager_checklist_sections (fk_template, code, label, position, active, date_creation)
SELECT rowid, 'torelement', 'SectionTorelement', 10, 1, NOW() FROM llx_equipmentmanager_checklist_templates WHERE equipment_type_code = 'fire_gate';
INSERT INTO llx_equipmentmanager_checklist_sections (fk_template, code, label, position, active, date_creation)
SELECT rowid, 'fsa', 'SectionFSA', 30, 1, NOW() FROM llx_equipmentmanager_checklist_templates WHERE equipment_type_code = 'fire_gate';
INSERT INTO llx_equipmentmanager_checklist_sections (fk_template, code, label, position, active, date_creation)
SELECT rowid, 'ergebnis', 'SectionErgebnis', 90, 1, NOW() FROM llx_equipmentmanager_checklist_templates WHERE equipment_type_code = 'fire_gate';

-- Door Closer Sections
INSERT INTO llx_equipmentmanager_checklist_sections (fk_template, code, label, position, active, date_creation)
SELECT rowid, 'tuerelement', 'SectionTuerelement', 10, 1, NOW() FROM llx_equipmentmanager_checklist_templates WHERE equipment_type_code = 'door_closer';
INSERT INTO llx_equipmentmanager_checklist_sections (fk_template, code, label, position, active, date_creation)
SELECT rowid, 'tuerschliesser', 'SectionTuerschliesser', 20, 1, NOW() FROM llx_equipmentmanager_checklist_templates WHERE equipment_type_code = 'door_closer';
INSERT INTO llx_equipmentmanager_checklist_sections (fk_template, code, label, position, active, date_creation)
SELECT rowid, 'ergebnis', 'SectionErgebnis', 90, 1, NOW() FROM llx_equipmentmanager_checklist_templates WHERE equipment_type_code = 'door_closer';

-- RWS Sections
INSERT INTO llx_equipmentmanager_checklist_sections (fk_template, code, label, position, active, date_creation)
SELECT rowid, 'tuerelement', 'SectionTuerelement', 10, 1, NOW() FROM llx_equipmentmanager_checklist_templates WHERE equipment_type_code = 'rws';
INSERT INTO llx_equipmentmanager_checklist_sections (fk_template, code, label, position, active, date_creation)
SELECT rowid, 'rws_terminal', 'SectionRWSTerminal', 20, 1, NOW() FROM llx_equipmentmanager_checklist_templates WHERE equipment_type_code = 'rws';
INSERT INTO llx_equipmentmanager_checklist_sections (fk_template, code, label, position, active, date_creation)
SELECT rowid, 'ergebnis', 'SectionErgebnis', 90, 1, NOW() FROM llx_equipmentmanager_checklist_templates WHERE equipment_type_code = 'rws';

-- RWA Sections
INSERT INTO llx_equipmentmanager_checklist_sections (fk_template, code, label, position, active, date_creation)
SELECT rowid, 'rwa', 'SectionRWA', 10, 1, NOW() FROM llx_equipmentmanager_checklist_templates WHERE equipment_type_code = 'rwa';
INSERT INTO llx_equipmentmanager_checklist_sections (fk_template, code, label, position, active, date_creation)
SELECT rowid, 'ausloesung', 'SectionAusloesung', 20, 1, NOW() FROM llx_equipmentmanager_checklist_templates WHERE equipment_type_code = 'rwa';
INSERT INTO llx_equipmentmanager_checklist_sections (fk_template, code, label, position, active, date_creation)
SELECT rowid, 'elektrik', 'SectionElektrik', 30, 1, NOW() FROM llx_equipmentmanager_checklist_templates WHERE equipment_type_code = 'rwa';
INSERT INTO llx_equipmentmanager_checklist_sections (fk_template, code, label, position, active, date_creation)
SELECT rowid, 'ergebnis', 'SectionErgebnis', 90, 1, NOW() FROM llx_equipmentmanager_checklist_templates WHERE equipment_type_code = 'rwa';

-- Other Sections
INSERT INTO llx_equipmentmanager_checklist_sections (fk_template, code, label, position, active, date_creation)
SELECT rowid, 'allgemein', 'SectionAllgemein', 10, 1, NOW() FROM llx_equipmentmanager_checklist_templates WHERE equipment_type_code = 'other';
INSERT INTO llx_equipmentmanager_checklist_sections (fk_template, code, label, position, active, date_creation)
SELECT rowid, 'ergebnis', 'SectionErgebnis', 90, 1, NOW() FROM llx_equipmentmanager_checklist_templates WHERE equipment_type_code = 'other';

-- ============================================
-- ITEMS - Door Swing (Drehtürantrieb)
-- ============================================

-- Türelement
INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'T1', 'ItemTuerblattZargeDichtungGlas', 'ok_mangel', 10, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'door_swing' AND s.code = 'tuerelement';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'T2', 'ItemPanikschloss', 'ok_mangel_nv', 20, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'door_swing' AND s.code = 'tuerelement';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'T3', 'ItemSchliessfolgereglerBei2Flg', 'ok_mangel_nv', 30, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'door_swing' AND s.code = 'tuerelement';

-- Antrieb/Funktion
INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'A1', 'ItemBefestigungAntrieb', 'ok_mangel', 10, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'door_swing' AND s.code = 'antrieb';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'A2', 'ItemBewegungsablauf', 'ok_mangel', 20, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'door_swing' AND s.code = 'antrieb';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'A3', 'ItemSicherheitssensorikFunktionErfassung', 'ok_mangel', 30, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'door_swing' AND s.code = 'antrieb';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'A4', 'ItemEinklemmschutz', 'ok_mangel', 40, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'door_swing' AND s.code = 'antrieb';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'A5', 'ItemVerdrahtung', 'ok_mangel', 50, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'door_swing' AND s.code = 'antrieb';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'A6', 'ItemAnsteuerung', 'ok_mangel', 60, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'door_swing' AND s.code = 'antrieb';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'A7', 'ItemBetriebsarten', 'ok_mangel', 70, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'door_swing' AND s.code = 'antrieb';

-- FSA (falls vorhanden)
INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'F1', 'ItemAusloesungRauchmelder', 'ok_mangel_nv', 10, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'door_swing' AND s.code = 'fsa';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'F2', 'ItemSelbstschliessung', 'ok_mangel_nv', 20, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'door_swing' AND s.code = 'fsa';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'F3', 'ItemZulassungsschild', 'ok_mangel_nv', 30, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'door_swing' AND s.code = 'fsa';

-- Ergebnis
INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'E1', 'ItemPruefungHerstellervorgabe', 'ja_nein', 10, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'door_swing' AND s.code = 'ergebnis';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'E2', 'ItemEntsprichtEN16005ASRA17', 'ja_nein', 20, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'door_swing' AND s.code = 'ergebnis';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'E3', 'ItemPruefungBestanden', 'ja_nein', 30, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'door_swing' AND s.code = 'ergebnis';

-- ============================================
-- ITEMS - Door Sliding (Schiebetürantrieb)
-- ============================================

-- Türelement
INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'T1', 'ItemFahrfluegelRahmenGlas', 'ok_mangel', 10, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'door_sliding' AND s.code = 'tuerelement';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'T2', 'ItemLaufschieneF', 'ok_mangel', 20, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'door_sliding' AND s.code = 'tuerelement';

-- Antrieb/Funktion
INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'A1', 'ItemBefestigungAntrieb', 'ok_mangel', 10, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'door_sliding' AND s.code = 'antrieb';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'A2', 'ItemBewegungsablauf', 'ok_mangel', 20, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'door_sliding' AND s.code = 'antrieb';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'A3', 'ItemSicherheitssensorikFunktionErfassung', 'ok_mangel', 30, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'door_sliding' AND s.code = 'antrieb';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'A4', 'ItemNebenschliesskante', 'ok_mangel', 40, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'door_sliding' AND s.code = 'antrieb';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'A5', 'ItemVerdrahtung', 'ok_mangel', 50, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'door_sliding' AND s.code = 'antrieb';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'A6', 'ItemAnsteuerung', 'ok_mangel', 60, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'door_sliding' AND s.code = 'antrieb';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'A7', 'ItemBetriebsartenInklEinweg', 'ok_mangel', 70, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'door_sliding' AND s.code = 'antrieb';

-- FSA (falls vorhanden)
INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'F1', 'ItemAusloesungRauchmelder', 'ok_mangel_nv', 10, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'door_sliding' AND s.code = 'fsa';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'F2', 'ItemSelbstschliessung', 'ok_mangel_nv', 20, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'door_sliding' AND s.code = 'fsa';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'F3', 'ItemZulassungsschild', 'ok_mangel_nv', 30, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'door_sliding' AND s.code = 'fsa';

-- Ergebnis
INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'E1', 'ItemPruefungHerstellervorgabe', 'ja_nein', 10, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'door_sliding' AND s.code = 'ergebnis';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'E2', 'ItemEntsprichtEN16005AutSchRASRA17', 'ja_nein', 20, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'door_sliding' AND s.code = 'ergebnis';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'E3', 'ItemPruefungBestanden', 'ja_nein', 30, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'door_sliding' AND s.code = 'ergebnis';

-- ============================================
-- ITEMS - Fire Door (Brandschutztür ohne FSA)
-- ============================================

-- Türelement
INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'T1', 'ItemTuerblattZargeDichtungGlas', 'ok_mangel', 10, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'fire_door' AND s.code = 'tuerelement';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'T2', 'ItemPanikschloss', 'ok_mangel_nv', 20, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'fire_door' AND s.code = 'tuerelement';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'T3', 'ItemSchliessfolgereglerBei2Flg', 'ok_mangel_nv', 30, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'fire_door' AND s.code = 'tuerelement';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'T4', 'ItemTuerschliesser', 'ok_mangel', 40, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'fire_door' AND s.code = 'tuerelement';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'T5', 'ItemSelbstschliessung', 'ok_mangel', 50, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'fire_door' AND s.code = 'tuerelement';

-- Ergebnis
INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'E1', 'ItemPruefungHerstellervorgabe', 'ja_nein', 10, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'fire_door' AND s.code = 'ergebnis';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'E2', 'ItemEntsprichtZulassungsbescheid', 'ja_nein', 20, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'fire_door' AND s.code = 'ergebnis';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'E3', 'ItemPruefungBestanden', 'ja_nein', 30, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'fire_door' AND s.code = 'ergebnis';

-- ============================================
-- ITEMS - Fire Door with FSA (Feststellanlage)
-- ============================================

-- Türelement
INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'T1', 'ItemTuerblattZargeDichtungGlas', 'ok_mangel', 10, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'fire_door_fsa' AND s.code = 'tuerelement';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'T2', 'ItemPanikschloss', 'ok_mangel_nv', 20, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'fire_door_fsa' AND s.code = 'tuerelement';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'T3', 'ItemSchliessfolgereglerBei2Flg', 'ok_mangel_nv', 30, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'fire_door_fsa' AND s.code = 'tuerelement';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'T4', 'ItemTuerschliesser', 'ok_mangel', 40, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'fire_door_fsa' AND s.code = 'tuerelement';

-- FSA
INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'F1', 'ItemFeststellvorrichtung', 'ok_mangel', 10, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'fire_door_fsa' AND s.code = 'fsa';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'F2', 'ItemAusloesungRauchmelder', 'ok_mangel', 20, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'fire_door_fsa' AND s.code = 'fsa';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'F3', 'ItemHandausloesung', 'ok_mangel', 30, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'fire_door_fsa' AND s.code = 'fsa';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'F4', 'ItemSelbstschliessung', 'ok_mangel', 40, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'fire_door_fsa' AND s.code = 'fsa';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'F5', 'ItemRauchmelderSichtpruefung', 'ok_mangel', 50, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'fire_door_fsa' AND s.code = 'fsa';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'F6', 'ItemZulassungsschild', 'ok_mangel', 60, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'fire_door_fsa' AND s.code = 'fsa';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'F7', 'ItemVerdrahtung', 'ok_mangel', 70, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'fire_door_fsa' AND s.code = 'fsa';

-- Ergebnis
INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'E1', 'ItemPruefungHerstellervorgabe', 'ja_nein', 10, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'fire_door_fsa' AND s.code = 'ergebnis';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'E2', 'ItemEntsprichtZulassungsbescheid', 'ja_nein', 20, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'fire_door_fsa' AND s.code = 'ergebnis';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'E3', 'ItemPruefungBestanden', 'ja_nein', 30, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'fire_door_fsa' AND s.code = 'ergebnis';

-- ============================================
-- ITEMS - Fire Gate (Brandschutztor)
-- ============================================

-- Torelement
INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'T1', 'ItemTorblattZargeDichtung', 'ok_mangel', 10, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'fire_gate' AND s.code = 'torelement';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'T2', 'ItemFuehrungsschienen', 'ok_mangel', 20, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'fire_gate' AND s.code = 'torelement';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'T3', 'ItemDrahtseil', 'ok_mangel', 30, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'fire_gate' AND s.code = 'torelement';

-- FSA
INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'F1', 'ItemFeststellvorrichtung', 'ok_mangel', 10, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'fire_gate' AND s.code = 'fsa';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'F2', 'ItemAusloesungRauchmelder', 'ok_mangel', 20, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'fire_gate' AND s.code = 'fsa';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'F3', 'ItemHandausloesung', 'ok_mangel', 30, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'fire_gate' AND s.code = 'fsa';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'F4', 'ItemSelbstschliessung', 'ok_mangel', 40, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'fire_gate' AND s.code = 'fsa';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'F5', 'ItemRauchmelderSichtpruefung', 'ok_mangel', 50, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'fire_gate' AND s.code = 'fsa';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'F6', 'ItemZulassungsschild', 'ok_mangel', 60, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'fire_gate' AND s.code = 'fsa';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'F7', 'ItemVerdrahtung', 'ok_mangel', 70, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'fire_gate' AND s.code = 'fsa';

-- Ergebnis
INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'E1', 'ItemPruefungHerstellervorgabe', 'ja_nein', 10, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'fire_gate' AND s.code = 'ergebnis';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'E2', 'ItemEntsprichtZulassungsbescheid', 'ja_nein', 20, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'fire_gate' AND s.code = 'ergebnis';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'E3', 'ItemPruefungBestanden', 'ja_nein', 30, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'fire_gate' AND s.code = 'ergebnis';

-- ============================================
-- ITEMS - Door Closer (Türschließer)
-- ============================================

-- Türelement
INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'T1', 'ItemTuerblattZargeDichtungGlas', 'ok_mangel', 10, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'door_closer' AND s.code = 'tuerelement';

-- Türschließer
INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'S1', 'ItemSchliesserklasseGroesse', 'info', 10, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'door_closer' AND s.code = 'tuerschliesser';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'S2', 'ItemBefestigung', 'ok_mangel', 20, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'door_closer' AND s.code = 'tuerschliesser';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'S3', 'ItemGestaengeGleitschiene', 'ok_mangel', 30, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'door_closer' AND s.code = 'tuerschliesser';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'S4', 'ItemSchliessgeschwindigkeit', 'ok_mangel', 40, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'door_closer' AND s.code = 'tuerschliesser';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'S5', 'ItemEndschlag', 'ok_mangel', 50, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'door_closer' AND s.code = 'tuerschliesser';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'S6', 'ItemOeffnungsdaempfung', 'ok_mangel_nv', 60, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'door_closer' AND s.code = 'tuerschliesser';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'S7', 'ItemSelbstschliessung', 'ok_mangel', 70, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'door_closer' AND s.code = 'tuerschliesser';

-- Ergebnis
INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'E1', 'ItemPruefungHerstellervorgabe', 'ja_nein', 10, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'door_closer' AND s.code = 'ergebnis';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'E2', 'ItemEntsprichtDINEN1154', 'ja_nein', 20, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'door_closer' AND s.code = 'ergebnis';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'E3', 'ItemPruefungBestanden', 'ja_nein', 30, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'door_closer' AND s.code = 'ergebnis';

-- ============================================
-- ITEMS - RWS (Rettungswegsystem/Fluchttürterminal)
-- ============================================

-- Türelement
INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'T1', 'ItemTuerblattZargeDichtungGlas', 'ok_mangel', 10, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'rws' AND s.code = 'tuerelement';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'T2', 'ItemPanikschlossPanikstange', 'ok_mangel', 20, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'rws' AND s.code = 'tuerelement';

-- RWS Terminal
INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'R1', 'ItemTerminalgehaeuse', 'ok_mangel', 10, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'rws' AND s.code = 'rws_terminal';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'R2', 'ItemBedienelemente', 'ok_mangel', 20, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'rws' AND s.code = 'rws_terminal';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'R3', 'ItemAnzeigenSignalisierung', 'ok_mangel', 30, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'rws' AND s.code = 'rws_terminal';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'R4', 'ItemFreigabeUeberAnsteuerung', 'ok_mangel', 40, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'rws' AND s.code = 'rws_terminal';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'R5', 'ItemVerdrahtung', 'ok_mangel', 50, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'rws' AND s.code = 'rws_terminal';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'R6', 'ItemAlarmfunktion', 'ok_mangel', 60, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'rws' AND s.code = 'rws_terminal';

-- Ergebnis
INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'E1', 'ItemPruefungHerstellervorgabe', 'ja_nein', 10, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'rws' AND s.code = 'ergebnis';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'E2', 'ItemEntsprichtEltVTR', 'ja_nein', 20, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'rws' AND s.code = 'ergebnis';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'E3', 'ItemPruefungBestanden', 'ja_nein', 30, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'rws' AND s.code = 'ergebnis';

-- ============================================
-- ITEMS - RWA (Rauch- und Wärmeabzugsanlage)
-- ============================================

-- RWA
INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'R1', 'ItemKlappeHaube', 'ok_mangel', 10, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'rwa' AND s.code = 'rwa';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'R2', 'ItemMechanikAntrieb', 'ok_mangel', 20, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'rwa' AND s.code = 'rwa';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'R3', 'ItemHubOeffnungswinkel', 'ok_mangel', 30, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'rwa' AND s.code = 'rwa';

-- Auslösung
INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'A1', 'ItemHandausloesung', 'ok_mangel', 10, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'rwa' AND s.code = 'ausloesung';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'A2', 'ItemAutomatischeAusloesung', 'ok_mangel_nv', 20, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'rwa' AND s.code = 'ausloesung';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'A3', 'ItemRueckstellung', 'ok_mangel', 30, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'rwa' AND s.code = 'ausloesung';

-- Elektrik
INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'K1', 'ItemVerdrahtung', 'ok_mangel', 10, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'rwa' AND s.code = 'elektrik';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'K2', 'ItemSteuerzentrale', 'ok_mangel', 20, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'rwa' AND s.code = 'elektrik';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'K3', 'ItemAkkuNotstromversorgung', 'ok_mangel', 30, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'rwa' AND s.code = 'elektrik';

-- Ergebnis
INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'E1', 'ItemPruefungHerstellervorgabe', 'ja_nein', 10, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'rwa' AND s.code = 'ergebnis';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'E2', 'ItemEntsprichtDIN18232ASRA16', 'ja_nein', 20, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'rwa' AND s.code = 'ergebnis';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'E3', 'ItemPruefungBestanden', 'ja_nein', 30, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'rwa' AND s.code = 'ergebnis';

-- ============================================
-- ITEMS - Other (Sonstiges)
-- ============================================

-- Allgemein
INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'A1', 'ItemAllgemeinerZustand', 'ok_mangel', 10, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'other' AND s.code = 'allgemein';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'A2', 'ItemFunktion', 'ok_mangel', 20, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'other' AND s.code = 'allgemein';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'A3', 'ItemSicherheit', 'ok_mangel', 30, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'other' AND s.code = 'allgemein';

-- Ergebnis
INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'E1', 'ItemPruefungHerstellervorgabe', 'ja_nein', 10, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'other' AND s.code = 'ergebnis';

INSERT INTO llx_equipmentmanager_checklist_items (fk_section, code, label, answer_type, position, active, date_creation)
SELECT s.rowid, 'E2', 'ItemPruefungBestanden', 'ja_nein', 20, 1, NOW()
FROM llx_equipmentmanager_checklist_sections s
JOIN llx_equipmentmanager_checklist_templates t ON s.fk_template = t.rowid
WHERE t.equipment_type_code = 'other' AND s.code = 'ergebnis';
