-- Checklist Item Results - individual answers for each check point
-- Copyright (C) 2024-2025 Equipment Manager Module

CREATE TABLE llx_equipmentmanager_checklist_item_results (
    rowid integer AUTO_INCREMENT PRIMARY KEY,
    fk_checklist_result integer NOT NULL,
    fk_checklist_item integer NOT NULL,
    answer varchar(20),
    -- Possible values depending on answer_type:
    -- 'ok', 'mangel', 'nv' (nicht vorhanden)
    -- 'ja', 'nein'
    -- For 'info' type: stored in answer_text
    answer_text text,
    note text,
    date_creation datetime NOT NULL,
    tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_item_result_checklist (fk_checklist_result),
    INDEX idx_item_result_item (fk_checklist_item),
    UNIQUE KEY uk_item_result (fk_checklist_result, fk_checklist_item)
) ENGINE=innodb;
