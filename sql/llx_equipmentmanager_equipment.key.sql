ALTER TABLE llx_equipmentmanager_equipment ADD INDEX idx_equipment_ref (ref);
ALTER TABLE llx_equipmentmanager_equipment ADD INDEX idx_equipment_number (equipment_number);
ALTER TABLE llx_equipmentmanager_equipment ADD INDEX idx_equipment_fk_soc (fk_soc);
ALTER TABLE llx_equipmentmanager_equipment ADD CONSTRAINT fk_equipment_soc FOREIGN KEY (fk_soc) REFERENCES llx_societe(rowid);
ALTER TABLE llx_equipmentmanager_equipment ADD UNIQUE INDEX uk_equipment_number (equipment_number, entity);
