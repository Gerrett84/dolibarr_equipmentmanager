CREATE TABLE llx_equipmentmanager_servicereport (
    rowid integer AUTO_INCREMENT PRIMARY KEY,
    ref varchar(128) NOT NULL,
    fk_equipment integer NOT NULL,
    service_date date NOT NULL,
    technician_name varchar(255),
    report_text text NOT NULL,
    status integer DEFAULT 1,
    hours_spent decimal(10,2),
    next_service_date date,
    note_public text,
    note_private text,
    date_creation datetime NOT NULL,
    tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    fk_user_creat integer,
    fk_user_modif integer,
    import_key varchar(14),
    entity integer DEFAULT 1
) ENGINE=innodb;