-- Fix Equipment Numbers - A000000 zu A000001+ konvertieren
-- Führe dieses Script aus falls bereits Equipment mit A000000 existiert

-- Prüfe aktuelle Nummern
SELECT equipment_number, rowid FROM llx_equipmentmanager_equipment ORDER BY rowid;

-- Update: Alle A000000 Nummern neu vergeben
UPDATE llx_equipmentmanager_equipment 
SET equipment_number = CONCAT('A', LPAD(rowid, 6, '0'))
WHERE equipment_number LIKE 'A0%';

-- Prüfe Ergebnis
SELECT equipment_number, rowid FROM llx_equipmentmanager_equipment ORDER BY rowid;

-- Sollte jetzt zeigen:
-- A000001 (rowid 1)
-- A000002 (rowid 2)
-- etc.