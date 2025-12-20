<?php
/**
 * Add second entry with type 'fichinter' (with 'h') to llx_document_model
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

echo "=== Add fichinter entry to llx_document_model ===\n\n";

// Check current entries
echo "1. Current entries for equipmentmanager:\n";
$sql = "SELECT rowid, nom, type, entity FROM llx_document_model WHERE nom = 'equipmentmanager'";
$resql = $db->query($sql);
if ($resql) {
    $count = $db->num_rows($resql);
    echo "   Found $count entries:\n";
    while ($obj = $db->fetch_object($resql)) {
        echo "   - ID {$obj->rowid}: type='{$obj->type}', entity={$obj->entity}\n";
    }
} else {
    die("Error: " . $db->lasterror() . "\n");
}

// Check if fichinter entry exists
$sql = "SELECT COUNT(*) as cnt FROM llx_document_model WHERE nom = 'equipmentmanager' AND type = 'fichinter'";
$resql = $db->query($sql);
$obj = $db->fetch_object($resql);

if ($obj->cnt == 0) {
    echo "\n2. Adding fichinter entry (with 'h'):\n";

    $sql = "INSERT INTO llx_document_model (nom, type, entity, libelle, description) ";
    $sql .= "VALUES ('equipmentmanager', 'fichinter', 1, 'Equipment Manager', '')";

    if ($db->query($sql)) {
        echo "   ✓ Added successfully\n";
    } else {
        echo "   ✗ Failed: " . $db->lasterror() . "\n";
    }
} else {
    echo "\n2. fichinter entry already exists\n";
}

// Verify
echo "\n3. Final entries:\n";
$sql = "SELECT rowid, nom, type, entity FROM llx_document_model WHERE nom = 'equipmentmanager'";
$resql = $db->query($sql);
if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        echo "   - ID {$obj->rowid}: type='{$obj->type}', entity={$obj->entity}\n";
    }
}

echo "\nDone.\n";
