<?php
/* Copyright (C) 2024-2026 Equipment Manager
 * AJAX endpoint returning the list of companies flagged as "Objektadresse"
 * (equipmentmanager_object_address extrafield on Societe). No longer scoped
 * to a customer (fk_soc) - Objektadresse is now a standalone Thirdparty,
 * reusable across equipment/customers. See Equipment::isObjectAddressMigrated().
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

dol_include_once('/equipmentmanager/class/equipment.class.php');

if (!Equipment::isObjectAddressMigrated()) {
    echo json_encode(array());
    exit;
}

$addresses = array();

$sql = "SELECT s.rowid, s.nom, s.address, s.zip, s.town FROM ".MAIN_DB_PREFIX."societe s";
$sql .= " INNER JOIN ".MAIN_DB_PREFIX."societe_extrafields sef ON sef.fk_object = s.rowid";
$sql .= " WHERE sef.equipmentmanager_object_address = 1";
$sql .= " AND s.entity IN (".getEntity('societe').")";
$sql .= " ORDER BY s.town, s.nom";

$resql = $db->query($sql);
if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        $label = $obj->nom;
        if ($obj->town) $label .= ' - '.$obj->town;
        $addresses[] = array(
            'id'    => (int)$obj->rowid,
            'label' => $label,
            'name'  => $obj->nom,
            'address' => $obj->address,
            'zip'   => $obj->zip,
            'town'  => $obj->town,
        );
    }
}

echo json_encode($addresses);
