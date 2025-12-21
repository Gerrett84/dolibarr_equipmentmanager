<?php
/**
 * Fix FICHEINTER_ADDON_PDF configuration
 * Problem: Value is 'pdf_equipmentmanager' but should be 'equipmentmanager'
 * The 'pdf_' prefix belongs in the FILENAME only, not in the config value!
 */

define('NOREQUIRESOC', 1);
define('NOCSRFCHECK', 1);
require '../../main.inc.php';

echo "=== FIX: FICHEINTER_ADDON_PDF Configuration ===\n\n";

// 1. Show current state
echo "1. CURRENT STATE:\n";
$sql = "SELECT name, value, entity FROM ".MAIN_DB_PREFIX."const WHERE name = 'FICHEINTER_ADDON_PDF'";
$resql = $db->query($sql);
if ($resql && $db->num_rows($resql) > 0) {
    $obj = $db->fetch_object($resql);
    echo "   FICHEINTER_ADDON_PDF = '{$obj->value}' (entity: {$obj->entity})\n";
    $current_value = $obj->value;
} else {
    echo "   ⚠ FICHEINTER_ADDON_PDF not set!\n";
    $current_value = null;
}

// 2. Check if fix needed
echo "\n2. ANALYSIS:\n";
if ($current_value === 'pdf_equipmentmanager') {
    echo "   ❌ WRONG! Value has 'pdf_' prefix\n";
    echo "   → Dolibarr will look for: pdf_pdf_equipmentmanager.modules.php\n";
    echo "   → But file is named: pdf_equipmentmanager.modules.php\n";
    echo "   → Config should be: 'equipmentmanager' (without prefix)\n";
    $needs_fix = true;
} elseif ($current_value === 'equipmentmanager') {
    echo "   ✅ CORRECT! Value is 'equipmentmanager'\n";
    echo "   → No fix needed\n";
    $needs_fix = false;
} else {
    echo "   ⚠ UNEXPECTED VALUE: '$current_value'\n";
    echo "   → Will set to 'equipmentmanager'\n";
    $needs_fix = true;
}

// 3. Apply fix if needed
if ($needs_fix) {
    echo "\n3. APPLYING FIX:\n";

    // Delete old entries
    $sql = "DELETE FROM ".MAIN_DB_PREFIX."const WHERE name = 'FICHEINTER_ADDON_PDF' AND entity = ".$conf->entity;
    $result = $db->query($sql);
    if ($result) {
        echo "   ✓ Deleted old config entry\n";
    } else {
        echo "   ✗ Error deleting: " . $db->lasterror() . "\n";
        exit(1);
    }

    // Insert correct value
    $sql = "INSERT INTO ".MAIN_DB_PREFIX."const (name, value, type, entity, visible) ";
    $sql .= "VALUES ('FICHEINTER_ADDON_PDF', 'equipmentmanager', 'chaine', ".$conf->entity.", 0)";
    $result = $db->query($sql);
    if ($result) {
        echo "   ✓ Set FICHEINTER_ADDON_PDF = 'equipmentmanager'\n";
    } else {
        echo "   ✗ Error inserting: " . $db->lasterror() . "\n";
        exit(1);
    }

    // 4. Verify
    echo "\n4. VERIFICATION:\n";
    $sql = "SELECT name, value, entity FROM ".MAIN_DB_PREFIX."const WHERE name = 'FICHEINTER_ADDON_PDF'";
    $resql = $db->query($sql);
    if ($resql && $db->num_rows($resql) > 0) {
        $obj = $db->fetch_object($resql);
        echo "   FICHEINTER_ADDON_PDF = '{$obj->value}' (entity: {$obj->entity})\n";
        if ($obj->value === 'equipmentmanager') {
            echo "   ✅ SUCCESS! Configuration fixed!\n";
        } else {
            echo "   ⚠ WARNING: Value is not 'equipmentmanager'\n";
        }
    }
} else {
    echo "\n✅ No fix needed - configuration is already correct!\n";
}

echo "\n=== NEXT STEPS ===\n";
echo "1. Go to Setup → Module → Interventions → PDF Templates\n";
echo "2. Check if 'Equipment Manager' is now GREEN\n";
echo "3. Open a Serviceauftrag (Fichinter)\n";
echo "4. Check PDF dropdown - should show 'Equipment Manager'\n";
echo "5. Try generating a PDF\n";
echo "\nDone!\n";
