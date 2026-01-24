-- Equipment Manager v4.0.2 - Cleanup duplicate checklist entries
-- This migration removes duplicate entries that may have been created by running migrations multiple times
-- Execute this SQL manually in phpMyAdmin if you have duplicate checklist items

-- =====================================================
-- STEP 1: Remove duplicate checklist SECTIONS first
-- =====================================================
DELETE s1 FROM llx_equipmentmanager_checklist_sections s1
INNER JOIN llx_equipmentmanager_checklist_sections s2
ON s1.fk_template = s2.fk_template
   AND s1.code = s2.code
   AND s1.rowid > s2.rowid;

-- =====================================================
-- STEP 2: Remove duplicate checklist ITEMS
-- =====================================================
-- Remove items where fk_section, code, and label match (keep lowest rowid)
DELETE i1 FROM llx_equipmentmanager_checklist_items i1
INNER JOIN llx_equipmentmanager_checklist_items i2
ON i1.fk_section = i2.fk_section
   AND i1.code = i2.code
   AND i1.rowid > i2.rowid;

-- =====================================================
-- STEP 3: Clean up orphaned records
-- =====================================================
-- Remove items that reference non-existent sections
DELETE FROM llx_equipmentmanager_checklist_items
WHERE fk_section NOT IN (SELECT rowid FROM llx_equipmentmanager_checklist_sections);

-- Remove item results that reference non-existent items
DELETE FROM llx_equipmentmanager_checklist_item_results
WHERE fk_checklist_item NOT IN (SELECT rowid FROM llx_equipmentmanager_checklist_items);

-- =====================================================
-- VERIFICATION: Check for remaining duplicates
-- =====================================================
-- Run this SELECT to verify no duplicates remain:
-- SELECT fk_section, code, label, COUNT(*) as cnt
-- FROM llx_equipmentmanager_checklist_items
-- GROUP BY fk_section, code, label
-- HAVING cnt > 1;
