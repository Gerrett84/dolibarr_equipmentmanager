-- Equipment Manager v5.3.0 - Price List Items
-- Stores configured positions for the two price lists

CREATE TABLE IF NOT EXISTS llx_equipmentmanager_pricelist_item (
    rowid         INT            NOT NULL AUTO_INCREMENT,
    list_type     VARCHAR(20)    NOT NULL DEFAULT 'rate',
    position      INT            NOT NULL DEFAULT 0,
    fk_product    INT            NULL,
    label         VARCHAR(255)   NOT NULL DEFAULT '',
    description   TEXT           NULL,
    unit          VARCHAR(50)    NOT NULL DEFAULT '',
    tms           TIMESTAMP      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    fk_user_creat INT            NULL,
    fk_user_modif INT            NULL,
    PRIMARY KEY (rowid),
    INDEX idx_list_type (list_type),
    INDEX idx_fk_product (fk_product)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
