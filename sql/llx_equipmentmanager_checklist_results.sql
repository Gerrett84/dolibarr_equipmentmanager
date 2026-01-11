-- Checklist Results - completed checklists linked to equipment and intervention
-- Copyright (C) 2024-2025 Equipment Manager Module

CREATE TABLE llx_equipmentmanager_checklist_results (
    rowid integer AUTO_INCREMENT PRIMARY KEY,
    ref varchar(128) NOT NULL,
    fk_template integer NOT NULL,
    fk_equipment integer NOT NULL,
    fk_intervention integer,
    fk_equipment_intervention integer,
    status integer DEFAULT 0,
    -- Status: 0 = draft, 1 = completed, 2 = passed, 3 = failed
    passed integer DEFAULT NULL,
    -- NULL = not evaluated, 1 = passed, 0 = failed
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
