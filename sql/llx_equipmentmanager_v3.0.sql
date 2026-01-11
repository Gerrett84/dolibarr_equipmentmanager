-- Equipment Manager v3.0 Update - Checklist System
-- Copyright (C) 2024-2025 Equipment Manager Module

-- ============================================
-- CHECKLIST TABLES
-- ============================================

-- Checklist Templates - one per equipment type
CREATE TABLE IF NOT EXISTS llx_equipmentmanager_checklist_templates (
    rowid integer AUTO_INCREMENT PRIMARY KEY,
    equipment_type_code varchar(50) NOT NULL,
    label varchar(255) NOT NULL,
    description text,
    norm_reference varchar(255),
    active integer DEFAULT 1,
    position integer DEFAULT 0,
    date_creation datetime NOT NULL,
    tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    fk_user_creat integer,
    fk_user_modif integer,
    entity integer DEFAULT 1,
    UNIQUE KEY uk_checklist_template_type (equipment_type_code, entity)
) ENGINE=innodb;

-- Checklist Sections - groups of items within a template
CREATE TABLE IF NOT EXISTS llx_equipmentmanager_checklist_sections (
    rowid integer AUTO_INCREMENT PRIMARY KEY,
    fk_template integer NOT NULL,
    code varchar(50) NOT NULL,
    label varchar(255) NOT NULL,
    description text,
    position integer DEFAULT 0,
    active integer DEFAULT 1,
    date_creation datetime NOT NULL,
    tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_checklist_section_template (fk_template),
    UNIQUE KEY uk_checklist_section_code (fk_template, code)
) ENGINE=innodb;

-- Checklist Items - individual check points within a section
CREATE TABLE IF NOT EXISTS llx_equipmentmanager_checklist_items (
    rowid integer AUTO_INCREMENT PRIMARY KEY,
    fk_section integer NOT NULL,
    code varchar(50) NOT NULL,
    label varchar(255) NOT NULL,
    description text,
    answer_type varchar(20) NOT NULL DEFAULT 'ok_mangel',
    required integer DEFAULT 1,
    position integer DEFAULT 0,
    active integer DEFAULT 1,
    date_creation datetime NOT NULL,
    tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_checklist_item_section (fk_section),
    UNIQUE KEY uk_checklist_item_code (fk_section, code)
) ENGINE=innodb;

-- Checklist Results - completed checklists linked to equipment and intervention
CREATE TABLE IF NOT EXISTS llx_equipmentmanager_checklist_results (
    rowid integer AUTO_INCREMENT PRIMARY KEY,
    ref varchar(128) NOT NULL,
    fk_template integer NOT NULL,
    fk_equipment integer NOT NULL,
    fk_intervention integer,
    fk_equipment_intervention integer,
    status integer DEFAULT 0,
    passed integer DEFAULT NULL,
    work_date date,
    note_public text,
    note_private text,
    date_creation datetime NOT NULL,
    date_completion datetime,
    tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    fk_user_creat integer,
    fk_user_modif integer,
    fk_user_completion integer,
    entity integer DEFAULT 1,
    INDEX idx_checklist_result_equipment (fk_equipment),
    INDEX idx_checklist_result_intervention (fk_intervention),
    INDEX idx_checklist_result_template (fk_template)
) ENGINE=innodb;

-- Checklist Item Results - individual answers for each check point
CREATE TABLE IF NOT EXISTS llx_equipmentmanager_checklist_item_results (
    rowid integer AUTO_INCREMENT PRIMARY KEY,
    fk_checklist_result integer NOT NULL,
    fk_checklist_item integer NOT NULL,
    answer varchar(20),
    answer_text text,
    note text,
    date_creation datetime NOT NULL,
    tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_item_result_checklist (fk_checklist_result),
    INDEX idx_item_result_item (fk_checklist_item),
    UNIQUE KEY uk_item_result (fk_checklist_result, fk_checklist_item)
) ENGINE=innodb;

-- ============================================
-- INSERT DEFAULT TEMPLATES (only if empty)
-- ============================================

INSERT IGNORE INTO llx_equipmentmanager_checklist_templates (equipment_type_code, label, norm_reference, position, active, date_creation, entity) VALUES
('door_swing', 'ChecklistDoorSwing', 'EN 16005, ASR A1.7', 10, 1, NOW(), 1),
('door_sliding', 'ChecklistDoorSliding', 'EN 16005, AutSchR, ASR A1.7', 20, 1, NOW(), 1),
('fire_door', 'ChecklistFireDoor', 'Zulassungsbescheid', 30, 1, NOW(), 1),
('fire_door_fsa', 'ChecklistFireDoorFSA', 'Zulassungsbescheid / Baumusterprüfung', 35, 1, NOW(), 1),
('fire_gate', 'ChecklistFireGate', 'Zulassungsbescheid', 40, 1, NOW(), 1),
('door_closer', 'ChecklistDoorCloser', 'DIN EN 1154', 50, 1, NOW(), 1),
('rws', 'ChecklistRWS', 'EltVTR', 60, 1, NOW(), 1),
('rwa', 'ChecklistRWA', 'DIN 18232-2, ASR A1.6', 70, 1, NOW(), 1),
('other', 'ChecklistOther', 'Herstellerangabe', 999, 1, NOW(), 1);
