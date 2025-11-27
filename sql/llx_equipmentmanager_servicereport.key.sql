ALTER TABLE llx_equipmentmanager_servicereport ADD INDEX idx_servicereport_ref (ref);
ALTER TABLE llx_equipmentmanager_servicereport ADD INDEX idx_servicereport_equipment (fk_equipment);
ALTER TABLE llx_equipmentmanager_servicereport ADD INDEX idx_servicereport_date (service_date);
ALTER TABLE llx_equipmentmanager_servicereport ADD CONSTRAINT fk_servicereport_equipment FOREIGN KEY (fk_equipment) REFERENCES llx_equipmentmanager_equipment(rowid);
ALTER TABLE llx_equipmentmanager_servicereport ADD UNIQUE INDEX uk_servicereport_ref (ref, entity);