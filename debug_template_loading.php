<?php
/**
 * Debug why template shows "Keine" in intervention dropdown
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
    die("Error: Could not load Dolibarr environment\n");
}

echo "=== Debug Template Loading ===\n\n";

// Try to load our template directly
$template_path = DOL_DOCUMENT_ROOT.'/custom/equipmentmanager/core/modules/fichinter/doc/equipmentmanager.modules.php';
echo "1. Check template file:\n";
echo "   Path: $template_path\n";
echo "   Exists: " . (file_exists($template_path) ? "YES" : "NO") . "\n";
echo "   Readable: " . (is_readable($template_path) ? "YES" : "NO") . "\n";

if (file_exists($template_path)) {
    // Try to include it
    echo "\n2. Try to load template class:\n";
    try {
        require_once $template_path;
        echo "   Include: SUCCESS\n";

        // Check if class exists
        if (class_exists('equipmentmanager')) {
            echo "   Class 'equipmentmanager': EXISTS\n";

            // Instantiate it
            $template = new equipmentmanager($db);
            echo "   Instantiated: SUCCESS\n";
            echo "   name property: " . var_export($template->name, true) . "\n";
            echo "   description property: " . var_export($template->description, true) . "\n";

            // Check if write_file method exists
            if (method_exists($template, 'write_file')) {
                echo "   write_file() method: EXISTS\n";
            } else {
                echo "   write_file() method: MISSING!\n";
            }
        } else {
            echo "   Class 'equipmentmanager': NOT FOUND\n";
        }
    } catch (Exception $e) {
        echo "   Error: " . $e->getMessage() . "\n";
    }
}

echo "\n3. Check llx_document_model table:\n";
$sql = "SELECT rowid, nom, type, entity FROM llx_document_model WHERE type = 'fichinter' OR type = 'ficheinter' ORDER BY nom";
$resql = $db->query($sql);
if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        echo "   - {$obj->nom} (type: {$obj->type}, entity: {$obj->entity})\n";
    }
} else {
    echo "   Error: " . $db->lasterror() . "\n";
}

echo "\n4. Call ModelePDFFicheinter::liste_modeles():\n";
require_once DOL_DOCUMENT_ROOT.'/core/modules/fichinter/modules_fichinter.php';
$liste = ModelePDFFicheinter::liste_modeles($db);
foreach ($liste as $key => $value) {
    echo "   [$key] => '$value'\n";
}

echo "\n5. Check what getListOfModels() returns:\n";
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
$models = getListOfModels($db, 'fichinter');
foreach ($models as $key => $value) {
    echo "   [$key] => '$value'\n";
}

echo "\nDone.\n";
