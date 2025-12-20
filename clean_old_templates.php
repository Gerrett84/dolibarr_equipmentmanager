<?php
/**
 * Clean up old template entries with wrong type 'ficheinter'
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

echo "=== Cleaning Old Template Entries ===\n\n";

// Show what will be deleted
$sql = "SELECT nom, type, entity FROM " . MAIN_DB_PREFIX . "document_model WHERE type = 'ficheinter'";
$resql = $db->query($sql);

if ($resql) {
    $num = $db->num_rows($resql);
    echo "Found " . $num . " entries with WRONG type 'ficheinter' (with 'e'):\n";
    while ($obj = $db->fetch_object($resql)) {
        echo "  - " . $obj->nom . " (entity " . $obj->entity . ")\n";
    }
} else {
    echo "SQL ERROR: " . $db->lasterror() . "\n";
}

echo "\n";

// Delete them
$sql = "DELETE FROM " . MAIN_DB_PREFIX . "document_model WHERE type = 'ficheinter'";
echo "Executing: " . $sql . "\n";
$result = $db->query($sql);

if ($result) {
    $affected = $db->affected_rows($result);
    echo "✓ Successfully deleted " . $affected . " old entries!\n\n";
} else {
    echo "✗ Failed to delete: " . $db->lasterror() . "\n\n";
}

// Now re-register soleil with correct type
echo "Re-registering soleil template with correct type 'fichinter'...\n";
$sql = "INSERT INTO " . MAIN_DB_PREFIX . "document_model (nom, type, entity, libelle, description)";
$sql .= " VALUES ('soleil', 'fichinter', 1, 'Soleil', 'Standard intervention template')";

$result = $db->query($sql);
if ($result) {
    echo "✓ Soleil registered successfully!\n\n";
} else {
    echo "✗ Failed to register soleil: " . $db->lasterror() . "\n\n";
}

// Verify final state
echo "=== Final State ===\n";
$sql = "SELECT nom, type, entity FROM " . MAIN_DB_PREFIX . "document_model WHERE type = 'fichinter' ORDER BY nom";
$resql = $db->query($sql);

if ($resql) {
    $num = $db->num_rows($resql);
    echo "Templates with CORRECT type 'fichinter': " . $num . "\n";
    while ($obj = $db->fetch_object($resql)) {
        echo "  ✓ " . $obj->nom . " (entity " . $obj->entity . ")\n";
    }
} else {
    echo "SQL ERROR: " . $db->lasterror() . "\n";
}

echo "\n=== Cleanup Complete ===\n";
echo "Please refresh your Dolibarr page and check the dropdown!\n";
