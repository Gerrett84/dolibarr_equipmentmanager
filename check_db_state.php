<?php
// Quick DB check script
define('NOREQUIRESOC', 1);
define('NOCSRFCHECK', 1);
require '../../main.inc.php';

echo "=== Equipment Manager Database Check ===\n\n";

// 1. Check tables
echo "1. TABLES:\n";
$sql = "SHOW TABLES LIKE '".MAIN_DB_PREFIX."equipmentmanager%'";
$resql = $db->query($sql);
if ($resql) {
    while ($row = $db->fetch_row($resql)) {
        echo "  ✓ {$row[0]}\n";
    }
}

// 2. Check document_model entries
echo "\n2. DOCUMENT MODEL ENTRIES:\n";
$sql = "SELECT nom, type, entity, libelle, description FROM ".MAIN_DB_PREFIX."document_model WHERE nom LIKE '%equipment%' OR type IN ('fichinter', 'ficheinter')";
$resql = $db->query($sql);
if ($resql) {
    $num = $db->num_rows($resql);
    echo "  Found $num entries:\n";
    while ($obj = $db->fetch_object($resql)) {
        echo "  - nom: '{$obj->nom}', type: '{$obj->type}', entity: {$obj->entity}, libelle: '{$obj->libelle}', description: '{$obj->description}'\n";
    }
} else {
    echo "  ERROR: " . $db->lasterror() . "\n";
}

// 3. Check FICHEINTER_ADDON_PDF constant
echo "\n3. PDF TEMPLATE CONFIG:\n";
$sql = "SELECT name, value, entity FROM ".MAIN_DB_PREFIX."const WHERE name = 'FICHEINTER_ADDON_PDF'";
$resql = $db->query($sql);
if ($resql && $db->num_rows($resql) > 0) {
    $obj = $db->fetch_object($resql);
    echo "  FICHEINTER_ADDON_PDF = '{$obj->value}' (entity: {$obj->entity})\n";
} else {
    echo "  ⚠ FICHEINTER_ADDON_PDF not set!\n";
}

// 4. Check if intervention_detail and intervention_material tables exist (v1.6)
echo "\n4. V1.6 TABLES:\n";
$tables_v16 = ['intervention_detail', 'intervention_material'];
foreach ($tables_v16 as $table) {
    $sql = "SHOW TABLES LIKE '".MAIN_DB_PREFIX."equipmentmanager_$table'";
    $resql = $db->query($sql);
    if ($resql && $db->num_rows($resql) > 0) {
        echo "  ✓ llx_equipmentmanager_$table exists\n";
    } else {
        echo "  ✗ llx_equipmentmanager_$table MISSING!\n";
    }
}

echo "\nDone.\n";
