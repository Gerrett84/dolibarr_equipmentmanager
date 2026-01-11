-- Checklist Items - individual check points within a section
-- Copyright (C) 2024-2025 Equipment Manager Module

CREATE TABLE llx_equipmentmanager_checklist_items (
    rowid integer AUTO_INCREMENT PRIMARY KEY,
    fk_section integer NOT NULL,
    code varchar(50) NOT NULL,
    label varchar(255) NOT NULL,
    description text,
    answer_type varchar(20) NOT NULL DEFAULT 'ok_mangel',
    -- Answer types:
    -- 'ok_mangel' = OK / Mangel
    -- 'ok_mangel_nv' = OK / Mangel / N.V. (nicht vorhanden)
    -- 'ja_nein' = Ja / Nein
    -- 'info' = Free text info field
    required integer DEFAULT 1,
    position integer DEFAULT 0,
    active integer DEFAULT 1,
    date_creation datetime NOT NULL,
    tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_checklist_item_section (fk_section),
    UNIQUE KEY uk_checklist_item_code (fk_section, code)
) ENGINE=innodb;
