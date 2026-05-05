<?php
/* Copyright (C) 2024-2025 Equipment Manager
 * AJAX: update intervention schedule (dateo/datee), bypasses validation lock.
 */

define('NOCSRFCHECK', '1'); // internal AJAX, permission check replaces CSRF guard

ini_set('display_errors', 0);
error_reporting(0);

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
    header('Content-Type: application/json');
    die(json_encode(array('error' => 'Failed to load Dolibarr')));
}

header('Content-Type: application/json');

if (!$user->hasRight('ficheinter', 'creer')) {
    echo json_encode(array('error' => 'Permission denied'));
    exit;
}

$id         = GETPOST('id', 'int');
$date_start = GETPOST('date_start', 'alpha'); // YYYY-MM-DD from <input type="date">
$time_start = GETPOST('time_start', 'alpha'); // HH:MM from <input type="time">
$date_end   = GETPOST('date_end', 'alpha');
$time_end   = GETPOST('time_end', 'alpha');
$allday     = GETPOST('allday', 'int');        // 1 = ganztägig

if (!$id || !$date_start) {
    echo json_encode(array('error' => 'Missing parameters'));
    exit;
}

// Parse date parts (from HTML date/time inputs: always ISO format YYYY-MM-DD and HH:MM)
$pStart = explode('-', $date_start);
if (count($pStart) !== 3) {
    echo json_encode(array('error' => 'Invalid start date format'));
    exit;
}
list($sy, $sm, $sd) = $pStart;

if ($allday || !$time_start) {
    $sh = 0; $smin = 0;
} else {
    $tParts = explode(':', $time_start);
    $sh  = isset($tParts[0]) ? (int)$tParts[0] : 0;
    $smin = isset($tParts[1]) ? (int)$tParts[1] : 0;
}
// Use server timezone (same as what Dolibarr uses for idate/jdate)
$ts_start = mktime($sh, $smin, 0, (int)$sm, (int)$sd, (int)$sy);

$ts_end = null;
if ($date_end) {
    $pEnd = explode('-', $date_end);
    if (count($pEnd) === 3) {
        list($ey, $em, $ed) = $pEnd;
        if ($allday || !$time_end) {
            $eh = 23; $emin = 59;
        } else {
            $tParts2 = explode(':', $time_end);
            $eh   = isset($tParts2[0]) ? (int)$tParts2[0] : 0;
            $emin = isset($tParts2[1]) ? (int)$tParts2[1] : 0;
        }
        $ts_end = mktime($eh, $emin, 0, (int)$em, (int)$ed, (int)$ey);
    }
}

// Direct SQL — bypass Dolibarr's validation lock on fichinter
$sql  = "UPDATE ".MAIN_DB_PREFIX."fichinter";
$sql .= " SET dateo = '".$db->idate($ts_start)."'";
$sql .= ", datee = ".($ts_end ? "'".$db->idate($ts_end)."'" : "NULL");
$sql .= " WHERE rowid = ".(int)$id;

if ($db->query($sql)) {
    echo json_encode(array(
        'success'            => true,
        'date_start_display' => dol_print_date($ts_start, $allday ? 'day' : 'dayhour', 'tzserver'),
        'date_end_display'   => $ts_end ? dol_print_date($ts_end, $allday ? 'day' : 'dayhour', 'tzserver') : '',
    ));
} else {
    echo json_encode(array('error' => $db->lasterror()));
}
