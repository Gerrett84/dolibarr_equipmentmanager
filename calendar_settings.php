<?php
/* Copyright (C) 2024 Equipment Manager
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
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
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
    $res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
// Try main.inc.php using relative path
if (!$res && file_exists("../../main.inc.php")) {
    $res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
    $res = @include "../../../main.inc.php";
}
if (!$res) {
    die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
dol_include_once('/equipmentmanager/lib/equipmentmanager.lib.php');

$langs->loadLangs(array("admin", "equipmentmanager@equipmentmanager"));

$action = GETPOST('action', 'aZ09');

/*
 * Actions
 */

// Generate new calendar token (first-time creation: anyone, regenerating invalidates every subscription: admin only)
if ($action == 'generate_cal_token' && (empty(getDolGlobalString('EQUIPMENTMANAGER_CAL_SECRET')) || $user->admin)) {
    $newToken = bin2hex(random_bytes(24));
    dolibarr_set_const($db, 'EQUIPMENTMANAGER_CAL_SECRET', $newToken, 'chaine', 0, '', $conf->entity);
    setEventMessages($langs->trans("CalendarTokenGenerated"), null, 'mesgs');
    header("Location: ".$_SERVER["PHP_SELF"]);
    exit;
}

/*
 * View
 */

equipmentmanagerSettingsHeader('CalendarFeed');

print '<p class="opacitymedium">'.$langs->trans("AlsoAvailableInPwa").'</p>';
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td colspan="2"><span class="fa fa-calendar paddingright"></span>'.$langs->trans("CalendarFeed").'</td>';
print "</tr>\n";
print '<tr class="oddeven">';
print '<td>';
dol_include_once('/equipmentmanager/lib/pwa_access.lib.php');
$calSecret = getDolGlobalString('EQUIPMENTMANAGER_CAL_SECRET');
if ($calSecret) {
    // Personal feed: only the orders assigned to the logged-in user
    $calUrl = DOL_MAIN_URL_ROOT.'/custom/equipmentmanager/calendar.php?token='.urlencode(eqmCalendarToken($db, $user->id));
    $webcalUrl = str_replace(array('https://', 'http://'), 'webcal://', $calUrl);
    print '<strong>'.$langs->trans("CalendarFeedUrl").'</strong><br>';
    print '<code style="word-break:break-all;">'.$calUrl.'</code>';
    print '<br><small class="opacitymedium">'.$langs->trans("CalendarFeedHelp").'</small>';
    print '<br><br>';
    print '<a href="'.dol_escape_htmltag($webcalUrl).'" class="button smallpaddingimp"><span class="fa fa-calendar"></span> '.$langs->trans("SubscribeInCalendar").'</a>';
} else {
    print $langs->trans("CalendarFeedNoToken");
}
print '</td>';
print '<td class="right" style="vertical-align:top; white-space:nowrap;">';
if ($calSecret) {
    if ($user->admin) print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?action=generate_cal_token&token='.newToken().'" onclick="return confirm(\''.$langs->trans("ConfirmRegenerateToken").'\');">'.$langs->trans("RegenerateToken").'</a>';
} else {
    print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?action=generate_cal_token&token='.newToken().'">'.$langs->trans("GenerateToken").'</a>';
}
print '</td>';
print '</tr>';
print '</table>';
print '</div>';

llxFooter();
