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
dol_include_once('/equipmentmanager/class/equipment.class.php');
dol_include_once('/equipmentmanager/lib/equipmentmanager.lib.php');

// Load translation files
$langs->loadLangs(array("admin", "equipmentmanager@equipmentmanager"));

// Access control
if (!$user->admin) {
    accessforbidden();
}

$action = GETPOST('action', 'aZ09');

/*
 * Actions
 */

// Technician user group (PWA access without backend access to service orders/equipment)
$technicianGroupName = 'Techniker (PWA)';
if ($action == 'create_technician_group') {
    require_once DOL_DOCUMENT_ROOT.'/user/class/usergroup.class.php';

    $group = new UserGroup($db);
    $resGroup = $db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."usergroup WHERE nom = '".$db->escape($technicianGroupName)."' AND entity IN (".getEntity('usergroup').")");
    $objGroup = $resGroup ? $db->fetch_object($resGroup) : null;
    if ($objGroup) {
        $group->fetch((int) $objGroup->rowid);
    } else {
        $group->name = $technicianGroupName;
        $group->nom = $technicianGroupName;
        $group->note = 'Equipment Manager: Techniker-Zugang (PWA)';
        $group->create();
    }

    $wantedRights = array(
        array('equipmentmanager', 'pwa', 'use'),
        array('user', 'self', 'creer'),
        array('user', 'self', 'password'),
        array('agenda', 'myactions', 'read'),
        array('agenda', 'myactions', 'create'),
    );
    $added = 0;
    if ($group->id > 0) {
        foreach ($wantedRights as $wr) {
            $sqlR = "SELECT id FROM ".MAIN_DB_PREFIX."rights_def WHERE module = '".$db->escape($wr[0])."' AND perms = '".$db->escape($wr[1])."' AND subperms = '".$db->escape($wr[2])."' AND entity = ".(int) $conf->entity;
            $resR = $db->query($sqlR);
            if ($resR && ($objR = $db->fetch_object($resR))) {
                $group->addrights((int) $objR->id, '', '', $conf->entity, 1);
                $added++;
            }
        }
        setEventMessages($langs->trans('TechnicianGroupReadyMsg', $technicianGroupName, $added, count($wantedRights)), null, $added == count($wantedRights) ? 'mesgs' : 'warnings');
    } else {
        setEventMessages($group->error ?: 'Error', null, 'errors');
    }
    header('Location: '.$_SERVER['PHP_SELF']);
    exit;
}

// Cleanup duplicate checklist entries
if ($action == 'cleanup_duplicates') {
    $errors = array();
    $deleted_sections = 0;
    $deleted_items = 0;
    $deleted_orphans = 0;
    $deleted_label_dupes = 0;

    // Step 1: Remove duplicate sections
    $sql = "DELETE s1 FROM ".MAIN_DB_PREFIX."equipmentmanager_checklist_sections s1 ";
    $sql .= "INNER JOIN ".MAIN_DB_PREFIX."equipmentmanager_checklist_sections s2 ";
    $sql .= "ON s1.fk_template = s2.fk_template AND s1.code = s2.code AND s1.rowid > s2.rowid";
    if ($db->query($sql)) {
        $deleted_sections = $db->affected_rows;
    } else {
        $errors[] = 'Sections: '.$db->lasterror();
    }

    // Step 2: Remove duplicate items (same section, same code)
    $sql = "DELETE i1 FROM ".MAIN_DB_PREFIX."equipmentmanager_checklist_items i1 ";
    $sql .= "INNER JOIN ".MAIN_DB_PREFIX."equipmentmanager_checklist_items i2 ";
    $sql .= "ON i1.fk_section = i2.fk_section AND i1.code = i2.code AND i1.rowid > i2.rowid";
    if ($db->query($sql)) {
        $deleted_items = $db->affected_rows;
    } else {
        $errors[] = 'Items: '.$db->lasterror();
    }

    // Step 2b: Remove duplicate items with same LABEL but different code (keep lowest rowid)
    $sql = "DELETE i1 FROM ".MAIN_DB_PREFIX."equipmentmanager_checklist_items i1 ";
    $sql .= "INNER JOIN ".MAIN_DB_PREFIX."equipmentmanager_checklist_items i2 ";
    $sql .= "ON i1.fk_section = i2.fk_section AND i1.label = i2.label AND i1.rowid > i2.rowid";
    if ($db->query($sql)) {
        $deleted_label_dupes = $db->affected_rows;
    } else {
        $errors[] = 'Label dupes: '.$db->lasterror();
    }

    // Step 3: Remove orphaned items (items without valid section)
    $sql = "DELETE FROM ".MAIN_DB_PREFIX."equipmentmanager_checklist_items ";
    $sql .= "WHERE fk_section NOT IN (SELECT rowid FROM ".MAIN_DB_PREFIX."equipmentmanager_checklist_sections)";
    if ($db->query($sql)) {
        $deleted_orphans += $db->affected_rows;
    } else {
        $errors[] = 'Orphan items: '.$db->lasterror();
    }

    // Step 4: Remove orphaned item results
    $sql = "DELETE FROM ".MAIN_DB_PREFIX."equipmentmanager_checklist_item_results ";
    $sql .= "WHERE fk_checklist_item NOT IN (SELECT rowid FROM ".MAIN_DB_PREFIX."equipmentmanager_checklist_items)";
    if ($db->query($sql)) {
        $deleted_orphans += $db->affected_rows;
    } else {
        $errors[] = 'Orphan results: '.$db->lasterror();
    }

    if (empty($errors)) {
        $msg = "Bereinigung abgeschlossen: $deleted_sections Sections, $deleted_items Items (Code-Duplikate), $deleted_label_dupes Items (Label-Duplikate), $deleted_orphans verwaiste Einträge entfernt.";
        setEventMessages($msg, null, 'mesgs');
    } else {
        setEventMessages(implode('<br>', $errors), null, 'errors');
    }

    header("Location: ".$_SERVER["PHP_SELF"]);
    exit;
}

// Show duplicate diagnosis
if ($action == 'show_duplicates') {
    $duplicates = array();

    // Find items with same label in same section
    $sql = "SELECT s.code as section_code, t.equipment_type_code, i.rowid, i.code, i.label, i.position ";
    $sql .= "FROM ".MAIN_DB_PREFIX."equipmentmanager_checklist_items i ";
    $sql .= "JOIN ".MAIN_DB_PREFIX."equipmentmanager_checklist_sections s ON i.fk_section = s.rowid ";
    $sql .= "JOIN ".MAIN_DB_PREFIX."equipmentmanager_checklist_templates t ON s.fk_template = t.rowid ";
    $sql .= "WHERE i.label IN (";
    $sql .= "  SELECT label FROM ".MAIN_DB_PREFIX."equipmentmanager_checklist_items ";
    $sql .= "  GROUP BY fk_section, label HAVING COUNT(*) > 1";
    $sql .= ") ";
    $sql .= "ORDER BY t.equipment_type_code, s.code, i.label, i.rowid";

    $resql = $db->query($sql);
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $duplicates[] = $obj;
        }
    }

    $_SESSION['checklist_duplicates'] = $duplicates;
}

/*
 * View
 */

llxHeader('', $langs->trans("EquipmentManagerSetup"));

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans("EquipmentManagerSetup"), $linkback, 'title_setup');

print dol_get_fiche_head(equipmentmanagerAdminPrepareHead(), 'setup', '', -1);

// ─── Einstellungen in der Seitenleiste ────────────────────────────────────────
print load_fiche_titre($langs->trans("SettingsInSidebar"), '', '');
print '<p class="opacitymedium">'.$langs->trans("SettingsInSidebarHelp").'</p>';

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
$sidebarPages = array(
    array('fa-list',     'ServiceOrderListColumns', 'ServiceOrderListColumnsHelp', '/equipmentmanager/service_order_list_settings.php'),
    array('fa-pencil',   'MyProfile',               'MyProfileHelp',               '/equipmentmanager/profile.php'),
    array('fa-calendar', 'CalendarFeed',            'CalendarFeedHelpShort',       '/equipmentmanager/calendar_settings.php'),
);
foreach ($sidebarPages as $p) {
    print '<tr class="oddeven">';
    print '<td><span class="fa '.$p[0].' paddingright"></span><strong>'.$langs->trans($p[1]).'</strong><br><span class="opacitymedium">'.$langs->trans($p[2]).'</span></td>';
    print '<td class="right"><a class="butAction" href="'.dol_buildpath($p[3], 1).'">'.$langs->trans("Open").'</a></td>';
    print '</tr>';
}
print '</table>';
print '</div>';
print '<br>';

// v6.0 Objektadresse migration - one-time setup step, hidden once already run so
// the page doesn't stay cluttered with a tool nobody needs again afterwards.
if (!Equipment::isObjectAddressMigrated()) {
    print load_fiche_titre($langs->trans("ObjectAddressMigrate"), '', '');

    print '<div class="div-table-responsive-no-min">';
    print '<table class="noborder centpercent">';

    print '<tr class="oddeven">';
    print '<td><span class="fa fa-exchange-alt paddingright"></span><strong>'.$langs->trans("ObjectAddressMigrate").'</strong></td>';
    print '<td><a class="butAction" href="'.dol_buildpath('/equipmentmanager/admin/objectaddress_migrate.php', 1).'">'.$langs->trans("ObjectAddressMigrate").'</a></td>';
    print '</tr>';

    print '</table>';
    print '</div>';
    print '<br>';
}

// ─── Techniker-Zugang ─────────────────────────────────────────────────────────
print load_fiche_titre($langs->trans("TechnicianAccess"), '', '');
print '<p class="opacitymedium">'.$langs->trans("TechnicianAccessHelp").'</p>';

$techGroupId = 0;
$techMembers = 0;
$techRights = 0;
$resTg = $db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."usergroup WHERE nom = '".$db->escape($technicianGroupName)."' AND entity IN (".getEntity('usergroup').")");
if ($resTg && ($objTg = $db->fetch_object($resTg))) {
    $techGroupId = (int) $objTg->rowid;
    $resM = $db->query("SELECT COUNT(*) as nb FROM ".MAIN_DB_PREFIX."usergroup_user WHERE fk_usergroup = ".$techGroupId);
    $techMembers = $resM ? (int) $db->fetch_object($resM)->nb : 0;
    $resRt = $db->query("SELECT COUNT(*) as nb FROM ".MAIN_DB_PREFIX."usergroup_rights WHERE fk_usergroup = ".$techGroupId);
    $techRights = $resRt ? (int) $db->fetch_object($resRt)->nb : 0;
}
$resPr = $db->query("SELECT id FROM ".MAIN_DB_PREFIX."rights_def WHERE module = 'equipmentmanager' AND perms = 'pwa' AND subperms = 'use'");
$hasPwaRight = ($resPr && $db->num_rows($resPr) > 0);

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="oddeven"><td><span class="fa fa-user-cog paddingright"></span><strong>'.dol_escape_htmltag($technicianGroupName).'</strong><br><span class="opacitymedium">';
if (!$hasPwaRight) {
    print $langs->trans("TechnicianNeedReactivate");
} elseif ($techGroupId) {
    print $langs->trans("TechnicianGroupStatus", $techRights, $techMembers);
} else {
    print $langs->trans("TechnicianGroupMissing");
}
print '</span></td><td class="right">';
if ($hasPwaRight) {
    print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'" style="display:inline;">';
    print '<input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="create_technician_group">';
    print '<input type="submit" class="butAction" value="'.dol_escape_htmltag($langs->trans($techGroupId ? "TechnicianGroupUpdate" : "TechnicianGroupCreate")).'">';
    print '</form> ';
    if ($techGroupId) {
        print '<a class="butAction" href="'.DOL_URL_ROOT.'/user/group/card.php?id='.$techGroupId.'">'.$langs->trans("Open").'</a> ';
    }
    print '<a class="butAction" href="'.DOL_URL_ROOT.'/user/card.php?action=create">'.$langs->trans("TechnicianNewUser").'</a>';
}
print '</td></tr>';
print '</table>';
print '</div>';
print '<br>';

// ─── Wartung ──────────────────────────────────────────────────────────────────
print load_fiche_titre($langs->trans("DatabaseMaintenance"), '', '');

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="oddeven">';
print '<td>';
print '<strong>'.$langs->trans("CleanupDuplicates").'</strong><br>';
print '<span class="opacitymedium">'.$langs->trans("CleanupDuplicatesDesc").'</span>';
print '</td>';
print '<td class="right">';
print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?action=show_duplicates&token='.newToken().'">'.$langs->trans("ShowDuplicates").'</a> ';
print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?action=cleanup_duplicates&token='.newToken().'" onclick="return confirm(\'Duplikate wirklich bereinigen?\');">'.$langs->trans("RunCleanup").'</a>';
print '</td>';
print '</tr>';

// Show duplicate diagnosis if available
if (!empty($_SESSION['checklist_duplicates'])) {
    $duplicates = $_SESSION['checklist_duplicates'];
    unset($_SESSION['checklist_duplicates']);

    print '<tr class="oddeven">';
    print '<td colspan="2">';
    if (empty($duplicates)) {
        print '<div class="info">Keine Duplikate gefunden.</div>';
    } else {
        print '<div class="warning">';
        print '<strong>Gefundene Duplikate ('.count($duplicates).' Einträge):</strong><br><br>';
        print '<table class="noborder" style="width: auto;">';
        print '<tr class="liste_titre"><th>Template</th><th>Section</th><th>ID</th><th>Code</th><th>Label</th><th>Position</th></tr>';
        foreach ($duplicates as $dup) {
            print '<tr class="oddeven">';
            print '<td>'.$dup->equipment_type_code.'</td>';
            print '<td>'.$dup->section_code.'</td>';
            print '<td>'.$dup->rowid.'</td>';
            print '<td>'.$dup->code.'</td>';
            print '<td><strong>'.$dup->label.'</strong></td>';
            print '<td>'.$dup->position.'</td>';
            print '</tr>';
        }
        print '</table>';
        print '</div>';
    }
    print '</td>';
    print '</tr>';
}

print '</table>';
print '</div>';

print dol_get_fiche_end();

llxFooter();
