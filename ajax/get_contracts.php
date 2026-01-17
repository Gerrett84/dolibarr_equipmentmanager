<?php
/* Copyright (C) 2024-2025 Equipment Manager
 * AJAX endpoint to get contracts for a customer
 */

// Avoid displaying errors directly
ini_set('display_errors', 0);

// Load Dolibarr environment
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

// Check permissions
if (!$user->rights->contrat->lire) {
    echo json_encode(array());
    exit;
}

$contracts = array();

$sql = "SELECT c.rowid, c.ref, c.ref_customer, c.date_contrat";
$sql .= " FROM ".MAIN_DB_PREFIX."contrat as c";
$sql .= " WHERE c.fk_soc = ".(int)$socid;
$sql .= " AND c.statut > 0"; // Only active contracts
$sql .= " AND c.entity IN (".getEntity('contrat').")";
$sql .= " ORDER BY c.date_contrat DESC";

$resql = $db->query($sql);
if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        $label = $obj->ref;
        if ($obj->ref_customer) {
            $label .= ' ('.$obj->ref_customer.')';
        }
        $contracts[] = array(
            'id' => $obj->rowid,
            'label' => $label
        );
    }
}

echo json_encode($contracts);
