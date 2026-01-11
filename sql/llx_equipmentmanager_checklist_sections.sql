-- Checklist Sections - groups of items within a template
-- Copyright (C) 2024-2025 Equipment Manager Module

CREATE TABLE llx_equipmentmanager_checklist_sections (
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
