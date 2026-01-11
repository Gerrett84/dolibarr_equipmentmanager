-- Checklist Templates - one per equipment type
-- Copyright (C) 2024-2025 Equipment Manager Module

CREATE TABLE llx_equipmentmanager_checklist_templates (
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
