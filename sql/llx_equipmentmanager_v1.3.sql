CREATE TABLE llx_equipmentmanager_intervention_link
(
    rowid           integer AUTO_INCREMENT PRIMARY KEY,
    fk_intervention integer NOT NULL,
    fk_equipment    integer NOT NULL,
    date_creation   datetime NOT NULL,
    fk_user_creat   integer,
    note            text,
    
    INDEX idx_intervention (fk_intervention),
    INDEX idx_equipment (fk_equipment),
    UNIQUE KEY uk_intervention_equipment (fk_intervention, fk_equipment)
) ENGINE=InnoDB;

-- Prüfen
SHOW TABLES LIKE '%intervention_link%';

exit