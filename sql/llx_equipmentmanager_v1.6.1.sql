-- Copyright (C) 2024 Equipment Manager
-- SQL Script for v1.6.1 - Register PDF Template

-- Register PDF template for Fichinter
-- Clean up old entries (both wrong names and wrong types)
DELETE FROM llx_document_model WHERE nom IN ('pdf_equipmentmanager', 'equipmentmanager') AND type IN ('ficheinter', 'fichinter');
INSERT INTO llx_document_model (nom, type, entity, libelle, description)
VALUES ('equipmentmanager', 'ficheinter', __ENTITY__, 'Equipment Manager', 'Service report with equipment details and materials');
