-- Update für Equipment Manager v1.4
-- Fügt link_type Feld zur Unterscheidung zwischen Wartung und Service hinzu

-- Prüfen ob Tabelle existiert
SELECT COUNT(*) FROM information_schema.tables 
WHERE table_schema = DATABASE() 
AND table_name = 'llx_equipmentmanager_intervention_link';

-- Neue Spalte hinzufügen (wenn nicht vorhanden)
ALTER TABLE llx_equipmentmanager_intervention_link 
ADD COLUMN IF NOT EXISTS link_type varchar(20) DEFAULT 'maintenance' AFTER fk_equipment;

-- Index auf link_type (wenn nicht vorhanden)
ALTER TABLE llx_equipmentmanager_intervention_link 
ADD INDEX IF NOT EXISTS idx_link_type (link_type);

-- Bestehende Einträge aktualisieren (alle auf 'maintenance' setzen)
UPDATE llx_equipmentmanager_intervention_link 
SET link_type = 'maintenance' 
WHERE link_type IS NULL OR link_type = '';

-- Prüfen
SELECT fk_intervention, fk_equipment, link_type, date_creation 
FROM llx_equipmentmanager_intervention_link 
ORDER BY date_creation DESC 
LIMIT 10;

-- Statistik anzeigen
SELECT 
    link_type,
    COUNT(*) as anzahl
FROM llx_equipmentmanager_intervention_link
GROUP BY link_type;