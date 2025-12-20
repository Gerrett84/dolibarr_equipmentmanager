-- Copyright (C) 2024 Equipment Manager
-- SQL Script for v1.6.1 - Register PDF Template

-- Register PDF template for Fichinter
DELETE FROM llx_document_model WHERE nom = 'pdf_equipmentmanager' AND type = 'ficheinter';
INSERT INTO llx_document_model (nom, type, entity, libelle, description) 
VALUES ('pdf_equipmentmanager', 'ficheinter', __ENTITY__, 'Equipment Manager', 'Service report with equipment details and materials');
