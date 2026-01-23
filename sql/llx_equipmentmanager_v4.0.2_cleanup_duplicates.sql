-- Equipment Manager v4.0.2 - Cleanup duplicate checklist items
-- This migration removes duplicate entries that may have been created by running migrations multiple times

-- Remove duplicate checklist items (keep only the first one based on lowest rowid)
-- This query finds items with same fk_section, code, label and keeps only the one with the lowest rowid

DELETE i1 FROM llx_equipmentmanager_checklist_items i1
INNER JOIN llx_equipmentmanager_checklist_items i2
ON i1.fk_section = i2.fk_section
   AND i1.code = i2.code
   AND i1.label = i2.label
   AND i1.rowid > i2.rowid;

-- Also clean up any orphaned item results that reference deleted items
DELETE FROM llx_equipmentmanager_checklist_item_results
WHERE fk_item NOT IN (SELECT rowid FROM llx_equipmentmanager_checklist_items);
