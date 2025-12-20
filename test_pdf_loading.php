<?php
/**
 * Test script to check if PDF template can be loaded
 * Run this from Dolibarr root: php htdocs/custom/equipmentmanager/test_pdf_loading.php
 */

// Load Dolibarr environment
$res = 0;

// Try different paths to find main.inc.php
$paths = array(
    "../../main.inc.php",
    "../../../main.inc.php",
    "/usr/share/dolibarr/htdocs/main.inc.php",
    __DIR__ . "/../../main.inc.php",
    __DIR__ . "/../../../main.inc.php"
);

foreach ($paths as $path) {
    if (file_exists($path)) {
        echo "Found main.inc.php at: $path\n";
        $res = @include $path;
        if ($res) break;
    }
}

if (!$res) {
    echo "ERROR: Cannot load Dolibarr environment\n";
    echo "Searched in:\n";
    foreach ($paths as $path) {
        echo "  - $path: " . (file_exists($path) ? "exists" : "not found") . "\n";
    }
    echo "\nCurrent directory: " . getcwd() . "\n";
    echo "Script location: " . __DIR__ . "\n";
    die();
}

echo "=== PDF Template Loading Test ===\n\n";

// Test 1: Check if file exists
$filepath = dol_buildpath('/equipmentmanager/core/modules/fichinter/doc/equipmentmanager.modules.php', 0);
echo "1. File path: " . $filepath . "\n";
echo "   File exists: " . (file_exists($filepath) ? "YES ✓" : "NO ✗") . "\n\n";

// Test 2: Try to load the parent class
echo "2. Loading parent class...\n";
try {
    dol_include_once('/equipmentmanager/core/modules/fichinter/modules_fichinter.php');
    echo "   ModelePDFFicheinter class exists: " . (class_exists('ModelePDFFicheinter') ? "YES ✓" : "NO ✗") . "\n\n";
} catch (Exception $e) {
    echo "   ERROR: " . $e->getMessage() . "\n\n";
}

// Test 3: Try to load the PDF module
echo "3. Loading PDF module class...\n";
try {
    require_once $filepath;
    echo "   File loaded successfully ✓\n";
    echo "   equipmentmanager class exists: " . (class_exists('equipmentmanager') ? "YES ✓" : "NO ✗") . "\n\n";
} catch (Exception $e) {
    echo "   ERROR: " . $e->getMessage() . "\n\n";
}

// Test 4: Try to instantiate the class
echo "4. Instantiating class...\n";
try {
    if (class_exists('equipmentmanager')) {
        $module = new equipmentmanager($db);
        echo "   Instance created ✓\n";
        echo "   Module name: " . $module->name . "\n";
        echo "   Module description: " . $module->description . "\n";
        echo "   Module type: " . $module->type . "\n\n";
    } else {
        echo "   ERROR: Class does not exist\n\n";
    }
} catch (Exception $e) {
    echo "   ERROR: " . $e->getMessage() . "\n\n";
}

// Test 5: Check database registration
echo "5. Database registration...\n";
echo "   Current entity: " . $conf->entity . "\n";

// First check ALL entities
$sql = "SELECT nom, libelle, description, entity FROM " . MAIN_DB_PREFIX . "document_model";
$sql .= " WHERE type = 'fichinter'";
$resql = $db->query($sql);
if ($resql) {
    $num = $db->num_rows($resql);
    echo "   Found " . $num . " template(s) in ALL entities:\n";
    while ($obj = $db->fetch_object($resql)) {
        echo "   - " . $obj->nom . " (" . $obj->libelle . ") [entity=" . $obj->entity . "]";
        if ($obj->entity == $conf->entity) {
            echo " ← CURRENT ENTITY";
        }
        echo "\n";
        if ($obj->nom == 'equipmentmanager' && $obj->entity == $conf->entity) {
            echo "     ✓ Our template is registered in current entity!\n";
        }
    }
} else {
    echo "   ERROR: " . $db->lasterror() . "\n";
}

// Now check current entity only
$sql = "SELECT nom, libelle, description FROM " . MAIN_DB_PREFIX . "document_model";
$sql .= " WHERE type = 'fichinter' AND entity = " . $conf->entity;
$resql = $db->query($sql);
if ($resql) {
    $num = $db->num_rows($resql);
    echo "   Found " . $num . " template(s) in CURRENT entity (" . $conf->entity . "):\n";
    while ($obj = $db->fetch_object($resql)) {
        echo "   - " . $obj->nom . " (" . $obj->libelle . ")\n";
    }
}
echo "\n";

// Test 6: Check what getListOfModels returns
echo "6. Testing getListOfModels()...\n";
include_once DOL_DOCUMENT_ROOT . '/core/lib/functions2.lib.php';
$models = getListOfModels($db, 'fichinter', 0);
echo "   Found " . count($models) . " model(s):\n";
foreach ($models as $key => $model) {
    echo "   - Key: " . $key . ", Label: " . $model . "\n";
    if ($key == 'equipmentmanager') {
        echo "     ✓ Our template is in the list!\n";
    }
}
echo "\n";

// Test 7: Check default model setting
echo "7. Default model configuration...\n";
$default = !empty($conf->global->FICHEINTER_ADDON_PDF) ? $conf->global->FICHEINTER_ADDON_PDF : 'NOT SET';
echo "   FICHEINTER_ADDON_PDF = " . $default . "\n";
if ($default == 'equipmentmanager') {
    echo "   ✓ Our template is set as default!\n";
}
echo "\n";

echo "=== Test Complete ===\n";
