-- Equipment Manager v4.8.1
-- Adds Leistungsdatum extrafield on facture (invoice)

INSERT IGNORE INTO llx_extrafields
    (name, entity, elementtype, label, type, size, fieldunique, fieldrequired, perms, enabled, module, pos, alwayseditable, list, printable, fielddefault, fieldcomputed)
VALUES
    ('leistungsdatum', 1, 'facture', 'Leistungsdatum', 'varchar', '100', 0, 0, '', '1', 'equipmentmanager', 10, 0, '0', 0, '', '');

-- Create the actual column in the extrafields data table
ALTER TABLE llx_facture_extrafields ADD COLUMN IF NOT EXISTS leistungsdatum varchar(100) DEFAULT NULL;
