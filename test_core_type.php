<?php
/**
 * Test what type Dolibarr Core actually uses for fichinter
 */

// Load Dolibarr environment
$res = 0;
$paths = array(
    "../../main.inc.php",
    "../../../main.inc.php",
    "/usr/share/dolibarr/htdocs/main.inc.php",
    __DIR__ . "/../../main.inc.php",
    __DIR__ . "/../../../main.inc.php"
);

foreach ($paths as $path) {
    if (file_exists($path)) {
        $res = @include $path;
        if ($res) break;
    }
}

if (!$res) {
    die("ERROR: Cannot load Dolibarr environment\n");
}

echo "=== Testing Dolibarr Core Fichinter Type ===\n\n";

// Method 1: Check what's in llx_const for FICHEINTER_ADDON_PDF
echo "1. Check FICHEINTER_ADDON_PDF constant:\n";
$sql = "SELECT name, value, entity FROM " . MAIN_DB_PREFIX . "const WHERE name = 'FICHEINTER_ADDON_PDF'";
$resql = $db->query($sql);
if ($resql && $db->num_rows($resql) > 0) {
    $obj = $db->fetch_object($resql);
    echo "   FICHEINTER_ADDON_PDF = '" . $obj->value . "' (entity " . $obj->entity . ")\n";
} else {
    echo "   NOT SET\n";
}
echo "\n";

// Method 2: Load Fichinter class and check
echo "2. Load Fichinter class:\n";
require_once DOL_DOCUMENT_ROOT.'/fichinter/class/fichinter.class.php';
$fichinter = new Fichinter($db);
echo "   Fichinter class loaded ✓\n";
echo "   Table name: " . $fichinter->table_element . "\n";
echo "\n";

// Method 3: Check Core modFichinter.class.php
echo "3. Load Fichinter module descriptor:\n";
$modfile = DOL_DOCUMENT_ROOT.'/fichinter/core/modules/modFichinter.class.php';
if (file_exists($modfile)) {
    echo "   File exists: " . $modfile . "\n";
    require_once $modfile;
    $modFichinter = new modFichinter($db);
    echo "   Module loaded ✓\n";

    // Check if module registers templates
    echo "   Checking init() method...\n";

    // Read the file to see if it registers templates
    $content = file_get_contents($modfile);
    if (strpos($content, 'document_model') !== false) {
        echo "   ✓ Module DOES register document_model entries\n";

        // Try to extract the type used
        if (preg_match("/type\\s*=\\s*['\"]([^'\"]+)['\"]/", $content, $matches)) {
            echo "   Type found in code: '" . $matches[1] . "'\n";
        }
        if (preg_match("/INSERT.*document_model.*VALUES.*['\"]([^'\"]+)['\"].*['\"](fichinter|ficheinter)['\"]/", $content, $matches)) {
            echo "   INSERT statement type: '" . $matches[2] . "'\n";
        }
    } else {
        echo "   Module does NOT register document_model entries\n";
    }
} else {
    echo "   File NOT found: " . $modfile . "\n";
}
echo "\n";

// Method 4: Check what soleil uses in the database
echo "4. Check existing soleil entry:\n";
$sql = "SELECT nom, type, entity FROM " . MAIN_DB_PREFIX . "document_model WHERE nom = 'soleil'";
$resql = $db->query($sql);
if ($resql) {
    $num = $db->num_rows($resql);
    if ($num > 0) {
        while ($obj = $db->fetch_object($resql)) {
            echo "   soleil => type: '" . $obj->type . "' (entity " . $obj->entity . ")\n";
        }
    } else {
        echo "   soleil NOT FOUND in database\n";
    }
}
echo "\n";

// Method 5: Try both types with ModelePDFFicheinter
echo "5. Test ModelePDFFicheinter::liste_modeles():\n";
require_once DOL_DOCUMENT_ROOT.'/core/modules/fichinter/modules_fichinter.php';
if (class_exists('ModelePDFFicheinter')) {
    echo "   Class exists ✓\n";
    $liste = ModelePDFFicheinter::liste_modeles($db);
    echo "   liste_modeles() returns:\n";
    foreach ($liste as $key => $value) {
        echo "     - key: '" . $key . "' => '" . $value . "'\n";
    }
} else {
    echo "   Class ModelePDFFicheinter NOT FOUND\n";
}
echo "\n";

// Method 6: Check Dolibarr's functions2.lib.php directly
echo "6. Direct call to getListOfModels():\n";
include_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';

// Test with 'fichinter' (no e)
$models1 = getListOfModels($db, 'fichinter', 0);
echo "   getListOfModels(db, 'fichinter'):\n";
foreach ($models1 as $key => $value) {
    echo "     - key: '" . $key . "' => '" . $value . "'\n";
}

// Test with 'ficheinter' (with e)
$models2 = getListOfModels($db, 'ficheinter', 0);
echo "   getListOfModels(db, 'ficheinter'):\n";
foreach ($models2 as $key => $value) {
    echo "     - key: '" . $key . "' => '" . $value . "'\n";
}

echo "\n=== CONCLUSION ===\n";
if (count($models1) > 0 && count($models2) == 0) {
    echo "CORRECT type is: 'fichinter' (NO 'e')\n";
} elseif (count($models2) > 0 && count($models1) == 0) {
    echo "CORRECT type is: 'ficheinter' (WITH 'e')\n";
} else {
    echo "BOTH types work or NEITHER works - needs investigation\n";
}
