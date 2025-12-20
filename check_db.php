<?php
/**
 * Quick database check for PDF templates
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

echo "=== PDF Template Database Check ===\n\n";

// Check what's in the database
$sql = "SELECT nom, type, entity, libelle FROM " . MAIN_DB_PREFIX . "document_model ORDER BY type, nom";
$resql = $db->query($sql);

if ($resql) {
    $num = $db->num_rows($resql);
    echo "Total templates in database: " . $num . "\n\n";

    echo "All templates:\n";
    while ($obj = $db->fetch_object($resql)) {
        echo "  - nom: '" . $obj->nom . "', type: '" . $obj->type . "', entity: " . $obj->entity . ", label: '" . $obj->libelle . "'\n";
    }
} else {
    echo "SQL ERROR: " . $db->lasterror() . "\n";
}

echo "\n";

// Check specifically for fichinter templates
$sql = "SELECT nom, type, entity, libelle FROM " . MAIN_DB_PREFIX . "document_model WHERE type = 'fichinter'";
$resql = $db->query($sql);

if ($resql) {
    $num = $db->num_rows($resql);
    echo "Templates with type='fichinter': " . $num . "\n";

    while ($obj = $db->fetch_object($resql)) {
        echo "  - " . $obj->nom . " (entity " . $obj->entity . ")\n";
    }
} else {
    echo "SQL ERROR: " . $db->lasterror() . "\n";
}

echo "\n";

// Check if our template exists
$sql = "SELECT * FROM " . MAIN_DB_PREFIX . "document_model WHERE nom = 'equipmentmanager'";
$resql = $db->query($sql);

if ($resql) {
    $num = $db->num_rows($resql);
    if ($num > 0) {
        echo "equipmentmanager template FOUND in database:\n";
        $obj = $db->fetch_object($resql);
        echo "  - nom: '" . $obj->nom . "'\n";
        echo "  - type: '" . $obj->type . "'\n";
        echo "  - entity: " . $obj->entity . "\n";
        echo "  - libelle: '" . $obj->libelle . "'\n";
        echo "  - description: '" . $obj->description . "'\n";
    } else {
        echo "equipmentmanager template NOT FOUND in database!\n";
        echo "\nAttempting to register...\n";

        // Try to register it
        $sql_insert = "INSERT INTO " . MAIN_DB_PREFIX . "document_model (nom, type, entity, libelle, description)";
        $sql_insert .= " VALUES ('equipmentmanager', 'fichinter', " . $conf->entity . ", 'Equipment Manager', 'Service report with equipment details and materials')";

        echo "SQL: " . $sql_insert . "\n";

        $result = $db->query($sql_insert);
        if ($result) {
            echo "✓ Successfully registered!\n";
        } else {
            echo "✗ Failed to register: " . $db->lasterror() . "\n";
        }
    }
}

echo "\n=== End ===\n";
