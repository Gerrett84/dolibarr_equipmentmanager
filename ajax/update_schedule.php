<?php
/* Copyright (C) 2024-2025 Equipment Manager
 * AJAX endpoint to update intervention schedule (dateo/datee)
 * Works regardless of intervention validation status.
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

if (!$user->hasRight('ficheinter', 'creer')) {
    echo json_encode(array('error' => 'Permission denied'));
    exit;
}

$id         = GETPOST('id', 'int');
$date_start = GETPOST('date_start', 'alpha'); // YYYY-MM-DD
$time_start = GETPOST('time_start', 'alpha'); // HH:MM
$date_end   = GETPOST('date_end', 'alpha');
$time_end   = GETPOST('time_end', 'alpha');

if (!$id || !$date_start) {
    echo json_encode(array('error' => 'Missing parameters'));
    exit;
}

// Parse start datetime
$start_str = trim($date_start).' '.trim($time_start ?: '00:00');
$ts_start = dol_stringtotime($start_str);
if (!$ts_start) {
    echo json_encode(array('error' => 'Invalid start date'));
    exit;
}

// Parse end datetime
$ts_end = null;
if ($date_end) {
    $end_str = trim($date_end).' '.trim($time_end ?: '00:00');
    $ts_end = dol_stringtotime($end_str);
}

// Update directly — bypass Dolibarr's validation lock
$sql  = "UPDATE ".MAIN_DB_PREFIX."fichinter";
$sql .= " SET dateo = '".$db->idate($ts_start)."'";
$sql .= ", datee = ".($ts_end ? "'".$db->idate($ts_end)."'" : "NULL");
$sql .= " WHERE rowid = ".(int)$id;
$sql .= " AND entity IN (".getEntity('intervention').")";

if ($db->query($sql)) {
    echo json_encode(array(
        'success' => true,
        'date_start_display' => dol_print_date($ts_start, 'dayhour'),
        'date_end_display'   => $ts_end ? dol_print_date($ts_end, 'dayhour') : '',
    ));
} else {
    echo json_encode(array('error' => $db->lasterror()));
}
