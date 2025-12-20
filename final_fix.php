<?php
/**
 * Final fix - Delete ALL wrong entries and keep only correct ones
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

echo "=== FINAL FIX - Delete ALL wrong type entries ===\n\n";

// Show current state
echo "Current state:\n";
$sql = "SELECT nom, type, entity FROM " . MAIN_DB_PREFIX . "document_model WHERE nom IN ('soleil', 'equipmentmanager', 'pdf_equipmentmanager') ORDER BY nom, type";
$resql = $db->query($sql);
if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        $marker = ($obj->type == 'fichinter') ? '✓' : '✗';
        echo "  " . $marker . " " . $obj->nom . " => type: '" . $obj->type . "'\n";
    }
}
echo "\n";

// Delete ALL entries with wrong type 'ficheinter' (with 'e')
echo "Deleting ALL entries with WRONG type 'ficheinter' (with 'e')...\n";
$sql = "DELETE FROM " . MAIN_DB_PREFIX . "document_model WHERE type = 'ficheinter'";
$result = $db->query($sql);
if ($result) {
    echo "✓ Deleted " . $db->affected_rows($result) . " wrong entries\n\n";
} else {
    echo "✗ Error: " . $db->lasterror() . "\n\n";
}

// Make sure we have the correct entries
echo "Ensuring correct entries exist...\n";

// Check if soleil exists with correct type
$sql = "SELECT COUNT(*) as cnt FROM " . MAIN_DB_PREFIX . "document_model WHERE nom = 'soleil' AND type = 'fichinter'";
$resql = $db->query($sql);
$obj = $db->fetch_object($resql);
if ($obj->cnt == 0) {
    echo "  Registering soleil...\n";
    $sql = "INSERT INTO " . MAIN_DB_PREFIX . "document_model (nom, type, entity, libelle, description)";
    $sql .= " VALUES ('soleil', 'fichinter', 1, 'Soleil', 'Standard intervention template')";
    $db->query($sql);
}

// Check if equipmentmanager exists with correct type
$sql = "SELECT COUNT(*) as cnt FROM " . MAIN_DB_PREFIX . "document_model WHERE nom = 'equipmentmanager' AND type = 'fichinter'";
$resql = $db->query($sql);
$obj = $db->fetch_object($resql);
if ($obj->cnt == 0) {
    echo "  Registering equipmentmanager...\n";
    $sql = "INSERT INTO " . MAIN_DB_PREFIX . "document_model (nom, type, entity, libelle, description)";
    $sql .= " VALUES ('equipmentmanager', 'fichinter', 1, 'Equipment Manager', 'Service report with equipment details and materials')";
    $db->query($sql);
}

echo "\n=== Final state ===\n";
$sql = "SELECT nom, type, entity, libelle FROM " . MAIN_DB_PREFIX . "document_model WHERE nom IN ('soleil', 'equipmentmanager') ORDER BY nom";
$resql = $db->query($sql);
if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        echo "  ✓ " . $obj->nom . " => type: '" . $obj->type . "', label: '" . $obj->libelle . "'\n";
    }
}

echo "\n=== Testing with getListOfModels() ===\n";
include_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
$models = getListOfModels($db, 'fichinter', 0);
echo "getListOfModels('fichinter') returns " . count($models) . " models:\n";
foreach ($models as $key => $label) {
    echo "  - key: '" . $key . "' => label: '" . $label . "'\n";
}

echo "\n=== Complete ===\n";
echo "Now check the dropdown in Dolibarr!\n";
