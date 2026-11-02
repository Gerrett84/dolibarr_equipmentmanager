-- Objektadresse of a service order (v6.1.8). Chosen in the backend, fixed for the PWA;
-- equipment can only be added from this address. One row per intervention.
CREATE TABLE llx_equipmentmanager_intervention_address (
    fk_intervention integer NOT NULL PRIMARY KEY,
    fk_address      integer NOT NULL,
    date_creation   datetime NOT NULL,
    fk_user_creat   integer,
    tms             timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=innodb;
