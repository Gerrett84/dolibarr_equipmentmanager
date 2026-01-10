-- Equipment Types table for dynamic type management
-- Copyright (C) 2024-2025 Equipment Manager Module

CREATE TABLE llx_equipmentmanager_equipment_types (
    rowid integer AUTO_INCREMENT PRIMARY KEY,
    code varchar(50) NOT NULL,
    label varchar(255) NOT NULL,
    description text,
    position integer DEFAULT 0,
    active integer DEFAULT 1,
    date_creation datetime NOT NULL,
    tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    fk_user_creat integer,
    fk_user_modif integer,
    entity integer DEFAULT 1,
    UNIQUE KEY uk_equipment_type_code (code, entity)
) ENGINE=innodb;
