<?php
/* Copyright (C) 2024-2025 Equipment Manager
 * WebCal / ICS calendar feed for service order appointments.
 * Subscribe via webcal:// URL in iOS Calendar or any calendar app.
 *
 * Authentication: ?token=EQUIPMENTMANAGER_CAL_SECRET constant value
 * Token is shown in Equipment Manager admin setup page.
 */

ob_start(); // catch any stray PHP output so it can't corrupt the ICS response
error_reporting(0);
ini_set('display_errors', 0);

// No Dolibarr session needed — token-based auth only
define('NOLOGIN', '1');
define('NOCSRFCHECK', '1');
define('NOREQUIREHTML', '1');
define('NOTOKENRENEWAL', '1');

$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
    $res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
    $i--;
    $j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
    $res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && file_exists("../main.inc.php")) {
    $res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
    $res = @include "../../main.inc.php";
}
if (!$res) {
    header('HTTP/1.1 500 Internal Server Error');
    exit('Dolibarr not found');
}

// Token validation
$token = isset($_GET['token']) ? trim($_GET['token']) : '';
$secret = getDolGlobalString('EQUIPMENTMANAGER_CAL_SECRET');

if (!$secret || !$token || !hash_equals($secret, $token)) {
    header('HTTP/1.1 403 Forbidden');
    header('Content-Type: text/plain; charset=UTF-8');
    exit('Access denied. Invalid or missing token.');
}

// Query open service orders with dates
$sql  = "SELECT f.rowid, f.ref, f.dateo, f.datee, f.description,";
$sql .= " s.nom as societe_name,";
$sql .= " u.firstname as tech_firstname, u.lastname as tech_lastname,";
$sql .= " COALESCE(MIN(sp.address), MIN(sp_obj.address)) as obj_address,";
$sql .= " COALESCE(MIN(sp.zip), MIN(sp_obj.zip)) as obj_zip,";
$sql .= " COALESCE(MIN(sp.town), MIN(sp_obj.town)) as obj_town,";
$sql .= " TRIM(CONCAT(COALESCE(MIN(sp.firstname), MIN(sp_obj.firstname), ''), ' ', COALESCE(MIN(sp.lastname), MIN(sp_obj.lastname), ''))) as obj_contact_name";
$sql .= " FROM ".MAIN_DB_PREFIX."fichinter as f";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON s.rowid = f.fk_soc";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."user as u ON u.rowid = f.fk_user_author";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."equipmentmanager_intervention_detail as eid ON eid.fk_intervention = f.rowid AND eid.fk_equipment IS NOT NULL";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."equipmentmanager_equipment as eq ON eq.rowid = eid.fk_equipment";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."socpeople as sp ON sp.rowid = eq.fk_address";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."element_contact as ec ON ec.element_id = f.rowid";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."c_type_contact as ctc ON ctc.rowid = ec.fk_c_type_contact AND ctc.code = 'OBJ' AND ctc.element = 'fichinter'";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."socpeople as sp_obj ON sp_obj.rowid = ec.fk_socpeople AND ctc.rowid IS NOT NULL";
$sql .= " WHERE f.entity IN (".getEntity('intervention').")";
$sql .= " AND f.fk_statut IN (0, 1)";   // open service orders only
$sql .= " AND f.dateo IS NOT NULL";
$sql .= " GROUP BY f.rowid";
$sql .= " ORDER BY f.dateo ASC";

$resql = $db->query($sql);

// ICS helper: format timestamp as UTC
function icsDate($ts) {
    if (!$ts) {
        return null;
    }
    return gmdate('Ymd\THis\Z', $ts);
}

// ICS helper: fold long lines per RFC 5545 (max 75 octets)
function icsFold($str) {
    $out = '';
    while (strlen($str) > 75) {
        $out .= substr($str, 0, 75)."\r\n ";
        $str = substr($str, 75);
    }
    $out .= $str;
    return $out;
}

// ICS helper: escape text values
function icsEscape($str) {
    $str = str_replace('\\', '\\\\', $str);
    $str = str_replace(';', '\;', $str);
    $str = str_replace(',', '\,', $str);
    $str = str_replace("\n", '\n', $str);
    $str = str_replace("\r", '', $str);
    return $str;
}

ob_end_clean(); // discard any stray output (PHP warnings etc.) before ICS headers
header('Content-Type: text/calendar; charset=UTF-8');
header('Content-Disposition: inline; filename="serviceorders.ics"');
header('Cache-Control: no-cache, no-store');

$prodId = '-//EquipmentManager//ServiceOrders//DE';
$calName = 'Serviceauftraege';

echo "BEGIN:VCALENDAR\r\n";
echo "VERSION:2.0\r\n";
echo icsFold("PRODID:".$prodId)."\r\n";
echo "CALSCALE:GREGORIAN\r\n";
echo "METHOD:PUBLISH\r\n";
echo icsFold("X-WR-CALNAME:".$calName)."\r\n";
echo "X-WR-TIMEZONE:Europe/Berlin\r\n";
echo "X-PUBLISHED-TTL:PT1H\r\n";

if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        // Raw string extraction for TZ-independent all-day detection
        $dateoRaw = !empty($obj->dateo) ? (string)$obj->dateo : '';
        $dateeRaw = !empty($obj->datee) ? (string)$obj->datee : '';
        $timeStartVal = (strlen($dateoRaw) >= 16) ? substr($dateoRaw, 11, 5) : '';
        $timeEndVal   = (strlen($dateeRaw) >= 16) ? substr($dateeRaw, 11, 5) : '';
        $dateStartStr = (strlen($dateoRaw) >= 10) ? str_replace('-', '', substr($dateoRaw, 0, 10)) : '';
        $dateEndStr   = (strlen($dateeRaw) >= 10) ? str_replace('-', '', substr($dateeRaw, 0, 10)) : '';

        $isAllDay = !empty($dateStartStr) && $timeStartVal === '00:00'
                    && (empty($dateeRaw) || $timeEndVal === '00:00' || $timeEndVal === '23:59');

        $ts_start = $db->jdate($dateoRaw);
        $ts_end   = $dateeRaw ? $db->jdate($dateeRaw) : ($ts_start + 3600);

        // Summary: "INT-0001 – Objektname" (fallback: Adresse, then Kundenname)
        $summary = $obj->ref;
        $objName = trim($obj->obj_contact_name);
        if ($objName) {
            $summary .= ' - '.$objName;
        } elseif ($obj->obj_address) {
            $addrShort = trim($obj->obj_address.($obj->obj_town ? ', '.$obj->obj_town : ''));
            $summary .= ' - '.$addrShort;
        } elseif ($obj->societe_name) {
            $summary .= ' - '.$obj->societe_name;
        }

        // Location from address
        $locParts = array();
        if ($obj->obj_address) {
            $locParts[] = $obj->obj_address;
        }
        $cityPart = trim($obj->obj_zip.' '.$obj->obj_town);
        if ($cityPart) {
            $locParts[] = $cityPart;
        }
        $location = implode(', ', $locParts);

        // Description
        $description = $obj->description ? strip_tags($obj->description) : '';

        $uid = 'serviceorder-'.$obj->rowid.'@equipmentmanager';
        $dtStamp = icsDate(time());

        echo "BEGIN:VEVENT\r\n";
        echo icsFold("UID:".$uid)."\r\n";
        echo "DTSTAMP:".$dtStamp."\r\n";

        if ($isAllDay) {
            // RFC 5545: all-day events use VALUE=DATE (date only, no time)
            // DTEND is exclusive: must be the day AFTER the last day of the event
            echo "DTSTART;VALUE=DATE:".$dateStartStr."\r\n";
            if ($timeEndVal === '23:59') {
                $dtEndAllDay = date('Ymd', strtotime(substr($dateeRaw, 0, 10).' +1 day'));
            } elseif ($timeEndVal === '00:00' && $dateEndStr > $dateStartStr) {
                $dtEndAllDay = $dateEndStr; // already midnight of next day → exclusive end
            } else {
                $dtEndAllDay = date('Ymd', strtotime(substr($dateoRaw, 0, 10).' +1 day'));
            }
            echo "DTEND;VALUE=DATE:".$dtEndAllDay."\r\n";
        } else {
            echo "DTSTART:".icsDate($ts_start)."\r\n";
            echo "DTEND:".icsDate($ts_end)."\r\n";
        }

        echo icsFold("SUMMARY:".icsEscape($summary))."\r\n";
        if ($location) {
            echo icsFold("LOCATION:".icsEscape($location))."\r\n";
        }
        if ($description) {
            echo icsFold("DESCRIPTION:".icsEscape($description))."\r\n";
        }
        echo "STATUS:CONFIRMED\r\n";
        echo "END:VEVENT\r\n";
    }
}

echo "END:VCALENDAR\r\n";
$db->close();
