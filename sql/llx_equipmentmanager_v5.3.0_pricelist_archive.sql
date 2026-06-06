-- Equipment Manager v5.3.0 - Price List Archive
-- Stores archived PDF snapshots of price lists

CREATE TABLE IF NOT EXISTS llx_equipmentmanager_pricelist_archive (
    rowid         INT            NOT NULL AUTO_INCREMENT,
    list_type     VARCHAR(20)    NOT NULL DEFAULT 'rate',
    fk_soc        INT            NULL,
    discount      DECIMAL(5,2)   NOT NULL DEFAULT 0,
    saved_date    DATE           NOT NULL,
    pdf_filename  VARCHAR(255)   NOT NULL DEFAULT '',
    fk_user_creat INT            NULL,
    tms           TIMESTAMP      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (rowid),
    INDEX idx_archive_list_type (list_type),
    INDEX idx_archive_saved_date (saved_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
