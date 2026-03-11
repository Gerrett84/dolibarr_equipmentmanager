<?php
/* Copyright (C) 2024-2025 Equipment Manager
 * AJAX endpoint to get contact addresses for a customer
 */

ini_set('display_errors', 0);

$res = 0;
if (!$res && file_exists("../../main.inc.php")) {
    $res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
    $res = @include "../../../main.inc.php";
}
if (!$res && file_exists("../../../../main.inc.php")) {
    $res = @include "../../../../main.inc.php";
}
if (!$res) {
    die(json_encode(array('error' => 'Failed to load Dolibarr')));
}

header('Content-Type: application/json');

$socid = GETPOST('socid', 'int');

if (empty($socid) || $socid <= 0) {
    echo json_encode(array());
    exit;
}

$addresses = array();

$sql = "SELECT rowid, lastname, firstname, address, zip, town";
$sql .= " FROM ".MAIN_DB_PREFIX."socpeople";
$sql .= " WHERE fk_soc = ".(int)$socid;
$sql .= " ORDER BY lastname, firstname";

$resql = $db->query($sql);
if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        $name = trim($obj->lastname.' '.$obj->firstname);
        $label = $name;
        if ($obj->town) $label .= ' - '.$obj->town;
        $addresses[] = array(
            'id'    => (int)$obj->rowid,
            'label' => $label,
            'name'  => $name,
            'address' => $obj->address,
            'zip'   => $obj->zip,
            'town'  => $obj->town,
        );
    }
}

echo json_encode($addresses);
