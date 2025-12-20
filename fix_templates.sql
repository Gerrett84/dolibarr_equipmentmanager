-- Fix PDF templates registration with correct type 'fichinter'
-- Run this in your Dolibarr database

-- First, clean up ALL old entries (both wrong and right type)
DELETE FROM llx_document_model WHERE type IN ('ficheinter', 'fichinter');

-- Re-register soleil template (Dolibarr standard)
INSERT INTO llx_document_model (nom, type, entity, libelle, description)
VALUES ('soleil', 'fichinter', 1, 'Soleil', 'Standard intervention template');

-- Register our Equipment Manager template
INSERT INTO llx_document_model (nom, type, entity, libelle, description)
VALUES ('equipmentmanager', 'fichinter', 1, 'Equipment Manager', 'Service report with equipment details and materials');

-- Verify the registration
SELECT nom, type, entity, libelle FROM llx_document_model WHERE type = 'fichinter';
