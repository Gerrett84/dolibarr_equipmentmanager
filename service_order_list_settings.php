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

if (!$user->admin) {
    accessforbidden();
}

$action = GETPOST('action', 'aZ09');

/*
 * Actions
 */

// Save service order list column settings
if ($action == 'save_sol_columns') {
    $cols = array(
        'EQUIPMENTMANAGER_SOL_COL_TERMIN',
        'EQUIPMENTMANAGER_SOL_COL_OBJADDRESS',
        'EQUIPMENTMANAGER_SOL_COL_NBANLAGEN',
        'EQUIPMENTMANAGER_SOL_COL_TYPES',
        'EQUIPMENTMANAGER_SOL_COL_DESCRIPTION',
        'EQUIPMENTMANAGER_SOL_COL_TECH',
    );
    foreach ($cols as $key) {
        $val = GETPOST($key, 'int') ? '1' : '0';
        dolibarr_set_const($db, $key, $val, 'chaine', 0, '', $conf->entity);
    }
    setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
    header("Location: ".$_SERVER["PHP_SELF"]);
    exit;
}

/*
 * View
 */

equipmentmanagerSettingsHeader('ServiceOrderListColumns');

print '<p class="opacitymedium">'.$langs->trans("ServiceOrderListColumnsHelp").'</p>';

print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="save_sol_columns">';
print '<table class="noborder centpercent">';

$solCols = array(
    'EQUIPMENTMANAGER_SOL_COL_TERMIN'      => array('label' => $langs->trans('Termin'),                     'default' => '1'),
    'EQUIPMENTMANAGER_SOL_COL_OBJADDRESS'  => array('label' => $langs->trans('ServiceOrderColObjAddress'),  'default' => '1'),
    'EQUIPMENTMANAGER_SOL_COL_NBANLAGEN'   => array('label' => $langs->trans('ServiceOrderColNbAnlagen'),   'default' => '0'),
    'EQUIPMENTMANAGER_SOL_COL_TYPES'       => array('label' => $langs->trans('ServiceOrderColTypes'),       'default' => '0'),
    'EQUIPMENTMANAGER_SOL_COL_DESCRIPTION' => array('label' => $langs->trans('ServiceOrderColDescription'), 'default' => '0'),
    'EQUIPMENTMANAGER_SOL_COL_TECH'        => array('label' => $langs->trans('ServiceOrderColTech'),        'default' => '1'),
);
foreach ($solCols as $key => $def) {
    $checked = getDolGlobalString($key, $def['default']) != '0' ? ' checked' : '';
    print '<tr class="oddeven">';
    print '<td>'.$def['label'].'</td>';
    print '<td><input type="checkbox" name="'.$key.'" value="1"'.$checked.'></td>';
    print '</tr>';
}

print '<tr class="oddeven">';
print '<td colspan="2" class="right"><input type="submit" class="button button-save" value="'.$langs->trans("Save").'"></td>';
print '</tr>';
print '</table>';
print '</form>';

llxFooter();
