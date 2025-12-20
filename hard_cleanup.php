<?php
/**
 * HARD cleanup - Delete ALL fichinter/ficheinter templates and re-register correctly
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

echo "=== HARD Cleanup - Deleting ALL fichinter templates ===\n\n";

// First, check what Dolibarr's core uses
echo "Checking Dolibarr's expected type...\n";
include_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
$models = getListOfModels($db, 'fichinter', 0);
echo "getListOfModels() for 'fichinter' returns: " . count($models) . " models\n";
foreach ($models as $key => $label) {
    echo "  - " . $key . " => " . $label . "\n";
}
echo "\n";

// Show current state
$sql = "SELECT nom, type, entity FROM " . MAIN_DB_PREFIX . "document_model WHERE type LIKE '%fichinter%' OR type LIKE '%ficheinter%'";
$resql = $db->query($sql);

if ($resql) {
    $num = $db->num_rows($resql);
    echo "Current entries for fichinter/ficheinter: " . $num . "\n";
    while ($obj = $db->fetch_object($resql)) {
        echo "  - nom: '" . $obj->nom . "', type: '" . $obj->type . "'\n";
    }
}
echo "\n";

// Delete BOTH types (fichinter AND ficheinter)
echo "Deleting ALL fichinter-related entries...\n";

$sql1 = "DELETE FROM " . MAIN_DB_PREFIX . "document_model WHERE type = 'fichinter'";
echo "SQL 1: " . $sql1 . "\n";
$result1 = $db->query($sql1);
if ($result1) {
    echo "  ✓ Deleted " . $db->affected_rows($result1) . " entries with type='fichinter'\n";
} else {
    echo "  ✗ Error: " . $db->lasterror() . "\n";
}

$sql2 = "DELETE FROM " . MAIN_DB_PREFIX . "document_model WHERE type = 'ficheinter'";
echo "SQL 2: " . $sql2 . "\n";
$result2 = $db->query($sql2);
if ($result2) {
    echo "  ✓ Deleted " . $db->affected_rows($result2) . " entries with type='ficheinter'\n";
} else {
    echo "  ✗ Error: " . $db->lasterror() . "\n";
}

echo "\n";

// Now check what getListOfModels uses as query
echo "Testing which type getListOfModels() uses...\n";
$sql_test1 = "SELECT nom FROM " . MAIN_DB_PREFIX . "document_model WHERE type = 'fichinter'";
$sql_test2 = "SELECT nom FROM " . MAIN_DB_PREFIX . "document_model WHERE type = 'ficheinter'";

// Insert test entry with 'fichinter'
$db->query("INSERT INTO " . MAIN_DB_PREFIX . "document_model (nom, type, entity) VALUES ('__test1__', 'fichinter', 1)");
$models1 = getListOfModels($db, 'fichinter', 0);
$found1 = isset($models1['__test1__']);
$db->query("DELETE FROM " . MAIN_DB_PREFIX . "document_model WHERE nom = '__test1__'");

// Insert test entry with 'ficheinter'
$db->query("INSERT INTO " . MAIN_DB_PREFIX . "document_model (nom, type, entity) VALUES ('__test2__', 'ficheinter', 1)");
$models2 = getListOfModels($db, 'fichinter', 0);
$found2 = isset($models2['__test2__']);
$db->query("DELETE FROM " . MAIN_DB_PREFIX . "document_model WHERE nom = '__test2__'");

echo "  - type='fichinter' (no e): " . ($found1 ? "FOUND ✓" : "NOT FOUND") . "\n";
echo "  - type='ficheinter' (with e): " . ($found2 ? "FOUND ✓" : "NOT FOUND") . "\n";
echo "\n";

$correct_type = $found2 ? 'ficheinter' : 'fichinter';
echo "CORRECT type to use: '" . $correct_type . "'\n\n";

// Re-register with correct type
echo "Re-registering templates with correct type '" . $correct_type . "'...\n";

// Register soleil
$sql = "INSERT INTO " . MAIN_DB_PREFIX . "document_model (nom, type, entity, libelle, description)";
$sql .= " VALUES ('soleil', '" . $correct_type . "', 1, 'Soleil', 'Standard intervention template')";
echo "  " . $sql . "\n";
$result = $db->query($sql);
if ($result) {
    echo "  ✓ soleil registered\n";
} else {
    echo "  ✗ Error: " . $db->lasterror() . "\n";
}

// Register equipmentmanager
$sql = "INSERT INTO " . MAIN_DB_PREFIX . "document_model (nom, type, entity, libelle, description)";
$sql .= " VALUES ('equipmentmanager', '" . $correct_type . "', 1, 'Equipment Manager', 'Service report with equipment details and materials')";
echo "  " . $sql . "\n";
$result = $db->query($sql);
if ($result) {
    echo "  ✓ equipmentmanager registered\n";
} else {
    echo "  ✗ Error: " . $db->lasterror() . "\n";
}

echo "\n=== Final verification ===\n";
$models = getListOfModels($db, 'fichinter', 0);
echo "getListOfModels() now returns:\n";
foreach ($models as $key => $label) {
    echo "  - " . $key . " => " . $label . "\n";
}

echo "\n=== Cleanup Complete ===\n";
