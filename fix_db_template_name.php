<?php
/**
 * Fix database entry for template name
 * Change from 'pdf_equipmentmanager' to 'equipmentmanager' to match file name
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

echo "=== Fix Template Name in Database ===\n\n";

// Check current value
$sql = "SELECT name, value, entity FROM llx_const WHERE name = 'FICHEINTER_ADDON_PDF'";
$resql = $db->query($sql);
if ($resql) {
    $obj = $db->fetch_object($resql);
    echo "Current value: {$obj->value} (entity {$obj->entity})\n";

    if ($obj->value === 'pdf_equipmentmanager') {
        echo "Changing to 'equipmentmanager' to match filename...\n";

        $sql = "UPDATE llx_const SET value = 'equipmentmanager' WHERE name = 'FICHEINTER_ADDON_PDF'";
        $result = $db->query($sql);

        if ($result) {
            echo "✓ Updated successfully\n";

            // Verify
            $sql = "SELECT value FROM llx_const WHERE name = 'FICHEINTER_ADDON_PDF'";
            $resql = $db->query($sql);
            $obj = $db->fetch_object($resql);
            echo "New value: {$obj->value}\n";
        } else {
            echo "✗ Failed to update: " . $db->lasterror() . "\n";
        }
    } else if ($obj->value === 'equipmentmanager') {
        echo "Already correct! No change needed.\n";
    } else {
        echo "Warning: Unexpected value '{$obj->value}'\n";
    }
} else {
    echo "Error: Could not query database\n";
}

echo "\nDone.\n";
