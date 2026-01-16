<?php
/* Copyright (C) 2024-2025 Equipment Manager
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file       admin/equipment_types.php
 * \ingroup    equipmentmanager
 * \brief      Admin page for managing equipment types
 */

// Load Dolibarr environment
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
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
    $res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
    $res = @include "../../main.inc.php";
}
if (!$res) {
    die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';

// Load translation files
$langs->loadLangs(array("admin", "equipmentmanager@equipmentmanager"));

// Initialize form object
$form = new Form($db);

// Access control
if (!$user->admin) {
    accessforbidden();
}

// Parameters
$action = GETPOST('action', 'aZ09');
$id = GETPOSTINT('id');
$code = GETPOST('code', 'alpha');
$label = GETPOST('label', 'alphanohtml');
$description = GETPOST('description', 'restricthtml');
$position = GETPOSTINT('position');
$active = GETPOSTINT('active');
$default_duration = GETPOSTINT('default_duration');
$default_interval = GETPOST('default_interval', 'alpha');

/*
 * Actions
 */

// Add new type
if ($action == 'add') {
    $error = 0;

    if (empty($code)) {
        setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentitiesaliased("Code")), null, 'errors');
        $error++;
    }
    if (empty($label)) {
        setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentitiesaliased("Label")), null, 'errors');
        $error++;
    }

    if (!$error) {
        // Check if code already exists
        $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."equipmentmanager_equipment_types";
        $sql .= " WHERE code = '".$db->escape($code)."' AND entity = ".$conf->entity;
        $resql = $db->query($sql);
        if ($resql && $db->num_rows($resql) > 0) {
            setEventMessages($langs->trans("ErrorCodeAlreadyExists"), null, 'errors');
            $error++;
        }
    }

    if (!$error) {
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."equipmentmanager_equipment_types";
        $sql .= " (code, label, description, position, active, default_duration, default_interval, date_creation, fk_user_creat, entity)";
        $sql .= " VALUES (";
        $sql .= "'".$db->escape($code)."',";
        $sql .= "'".$db->escape($label)."',";
        $sql .= "'".$db->escape($description)."',";
        $sql .= (int)$position.",";
        $sql .= "1,";
        $sql .= (int)$default_duration.",";
        $sql .= "'".$db->escape($default_interval ?: 'yearly')."',";
        $sql .= "NOW(),";
        $sql .= (int)$user->id.",";
        $sql .= (int)$conf->entity;
        $sql .= ")";

        $result = $db->query($sql);
        if ($result) {
            setEventMessages($langs->trans("RecordSaved"), null, 'mesgs');
            header("Location: ".$_SERVER["PHP_SELF"]);
            exit;
        } else {
            setEventMessages($db->lasterror(), null, 'errors');
        }
    }
    $action = 'create';
}

// Update type
if ($action == 'update' && $id > 0) {
    $error = 0;

    if (empty($label)) {
        setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentitiesaliased("Label")), null, 'errors');
        $error++;
    }

    if (!$error) {
        $sql = "UPDATE ".MAIN_DB_PREFIX."equipmentmanager_equipment_types SET";
        $sql .= " label = '".$db->escape($label)."',";
        $sql .= " description = '".$db->escape($description)."',";
        $sql .= " position = ".(int)$position.",";
        $sql .= " default_duration = ".(int)$default_duration.",";
        $sql .= " default_interval = '".$db->escape($default_interval ?: 'yearly')."',";
        $sql .= " fk_user_modif = ".(int)$user->id;
        $sql .= " WHERE rowid = ".(int)$id;
        $sql .= " AND entity = ".(int)$conf->entity;

        $result = $db->query($sql);
        if ($result) {
            setEventMessages($langs->trans("RecordSaved"), null, 'mesgs');
            header("Location: ".$_SERVER["PHP_SELF"]);
            exit;
        } else {
            setEventMessages($db->lasterror(), null, 'errors');
        }
    }
    $action = 'edit';
}

// Toggle active status
if ($action == 'activate' && $id > 0) {
    $sql = "UPDATE ".MAIN_DB_PREFIX."equipmentmanager_equipment_types SET active = 1, fk_user_modif = ".(int)$user->id;
    $sql .= " WHERE rowid = ".(int)$id." AND entity = ".(int)$conf->entity;
    $db->query($sql);
    header("Location: ".$_SERVER["PHP_SELF"]);
    exit;
}

if ($action == 'deactivate' && $id > 0) {
    $sql = "UPDATE ".MAIN_DB_PREFIX."equipmentmanager_equipment_types SET active = 0, fk_user_modif = ".(int)$user->id;
    $sql .= " WHERE rowid = ".(int)$id." AND entity = ".(int)$conf->entity;
    $db->query($sql);
    header("Location: ".$_SERVER["PHP_SELF"]);
    exit;
}

// Delete type
if ($action == 'confirm_delete' && $id > 0 && GETPOST('confirm', 'alpha') == 'yes') {
    // Check if type is used
    $sql = "SELECT COUNT(*) as nb FROM ".MAIN_DB_PREFIX."equipmentmanager_equipment";
    $sql .= " WHERE equipment_type = (SELECT code FROM ".MAIN_DB_PREFIX."equipmentmanager_equipment_types WHERE rowid = ".(int)$id.")";
    $resql = $db->query($sql);
    if ($resql) {
        $obj = $db->fetch_object($resql);
        if ($obj->nb > 0) {
            setEventMessages($langs->trans("TypeIsUsedCannotDelete", $obj->nb), null, 'errors');
        } else {
            $sql = "DELETE FROM ".MAIN_DB_PREFIX."equipmentmanager_equipment_types";
            $sql .= " WHERE rowid = ".(int)$id." AND entity = ".(int)$conf->entity;
            $db->query($sql);
            setEventMessages($langs->trans("RecordDeleted"), null, 'mesgs');
        }
    }
    header("Location: ".$_SERVER["PHP_SELF"]);
    exit;
}

// Move up/down
if ($action == 'up' && $id > 0) {
    // Get current position
    $sql = "SELECT position FROM ".MAIN_DB_PREFIX."equipmentmanager_equipment_types WHERE rowid = ".(int)$id;
    $resql = $db->query($sql);
    if ($resql && $obj = $db->fetch_object($resql)) {
        $currentPos = $obj->position;
        // Find previous item
        $sql2 = "SELECT rowid, position FROM ".MAIN_DB_PREFIX."equipmentmanager_equipment_types";
        $sql2 .= " WHERE position < ".(int)$currentPos." AND entity = ".(int)$conf->entity;
        $sql2 .= " ORDER BY position DESC LIMIT 1";
        $resql2 = $db->query($sql2);
        if ($resql2 && $obj2 = $db->fetch_object($resql2)) {
            // Swap positions
            $db->query("UPDATE ".MAIN_DB_PREFIX."equipmentmanager_equipment_types SET position = ".(int)$obj2->position." WHERE rowid = ".(int)$id);
            $db->query("UPDATE ".MAIN_DB_PREFIX."equipmentmanager_equipment_types SET position = ".(int)$currentPos." WHERE rowid = ".(int)$obj2->rowid);
        }
    }
    header("Location: ".$_SERVER["PHP_SELF"]);
    exit;
}

if ($action == 'down' && $id > 0) {
    // Get current position
    $sql = "SELECT position FROM ".MAIN_DB_PREFIX."equipmentmanager_equipment_types WHERE rowid = ".(int)$id;
    $resql = $db->query($sql);
    if ($resql && $obj = $db->fetch_object($resql)) {
        $currentPos = $obj->position;
        // Find next item
        $sql2 = "SELECT rowid, position FROM ".MAIN_DB_PREFIX."equipmentmanager_equipment_types";
        $sql2 .= " WHERE position > ".(int)$currentPos." AND entity = ".(int)$conf->entity;
        $sql2 .= " ORDER BY position ASC LIMIT 1";
        $resql2 = $db->query($sql2);
        if ($resql2 && $obj2 = $db->fetch_object($resql2)) {
            // Swap positions
            $db->query("UPDATE ".MAIN_DB_PREFIX."equipmentmanager_equipment_types SET position = ".(int)$obj2->position." WHERE rowid = ".(int)$id);
            $db->query("UPDATE ".MAIN_DB_PREFIX."equipmentmanager_equipment_types SET position = ".(int)$currentPos." WHERE rowid = ".(int)$obj2->rowid);
        }
    }
    header("Location: ".$_SERVER["PHP_SELF"]);
    exit;
}

/*
 * View
 */

$page_name = "EquipmentTypesSetup";
llxHeader('', $langs->trans($page_name));

// Page title
$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans($page_name), $linkback, 'title_setup');

// Admin tabs
$head = array();
$head[0][0] = dol_buildpath('/equipmentmanager/admin/setup.php', 1);
$head[0][1] = $langs->trans('ModuleSetup');
$head[0][2] = 'setup';
$head[1][0] = dol_buildpath('/equipmentmanager/admin/equipment_types.php', 1);
$head[1][1] = $langs->trans('EquipmentTypesSetup');
$head[1][2] = 'equipment_types';
$head[2][0] = dol_buildpath('/equipmentmanager/admin/checklists.php', 1);
$head[2][1] = $langs->trans('ChecklistTemplates');
$head[2][2] = 'checklists';

print dol_get_fiche_head($head, 'equipment_types', '', -1);

// Confirm delete dialog
if ($action == 'delete' && $id > 0) {
    $sql = "SELECT code, label FROM ".MAIN_DB_PREFIX."equipmentmanager_equipment_types WHERE rowid = ".(int)$id;
    $resql = $db->query($sql);
    if ($resql && $obj = $db->fetch_object($resql)) {
        print $form->formconfirm(
            $_SERVER["PHP_SELF"].'?id='.$id,
            $langs->trans("DeleteEquipmentType"),
            $langs->trans("ConfirmDeleteEquipmentType", $obj->label),
            'confirm_delete',
            '',
            0,
            1
        );
    }
}

// Add/Edit form
if ($action == 'create' || $action == 'edit') {
    $editType = null;
    if ($action == 'edit' && $id > 0) {
        $sql = "SELECT * FROM ".MAIN_DB_PREFIX."equipmentmanager_equipment_types WHERE rowid = ".(int)$id;
        $resql = $db->query($sql);
        if ($resql) {
            $editType = $db->fetch_object($resql);
        }
    }

    print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
    print '<input type="hidden" name="token" value="'.newToken().'">';
    print '<input type="hidden" name="action" value="'.($action == 'edit' ? 'update' : 'add').'">';
    if ($editType) {
        print '<input type="hidden" name="id" value="'.$editType->rowid.'">';
    }

    print '<div class="div-table-responsive-no-min">';
    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre">';
    print '<td colspan="2">'.$langs->trans($action == 'edit' ? "EditEquipmentType" : "AddEquipmentType").'</td>';
    print '</tr>';

    // Code (only for new entries)
    if ($action == 'create') {
        print '<tr class="oddeven">';
        print '<td class="fieldrequired">'.$langs->trans("Code").'</td>';
        print '<td><input type="text" name="code" value="'.dol_escape_htmltag(GETPOST('code', 'alpha')).'" class="minwidth200" maxlength="50" required></td>';
        print '</tr>';
    } else {
        print '<tr class="oddeven">';
        print '<td>'.$langs->trans("Code").'</td>';
        print '<td><strong>'.dol_escape_htmltag($editType->code).'</strong> <span class="opacitymedium">('.$langs->trans("NotEditable").')</span></td>';
        print '</tr>';
    }

    // Label
    print '<tr class="oddeven">';
    print '<td class="fieldrequired">'.$langs->trans("Label").'</td>';
    print '<td><input type="text" name="label" value="'.dol_escape_htmltag($editType ? $editType->label : GETPOST('label', 'alphanohtml')).'" class="minwidth300" required>';
    print '<br><span class="opacitymedium">'.$langs->trans("EquipmentTypeLabelHelp").'</span></td>';
    print '</tr>';

    // Description
    print '<tr class="oddeven">';
    print '<td>'.$langs->trans("Description").'</td>';
    print '<td><textarea name="description" class="minwidth300" rows="2">'.dol_escape_htmltag($editType ? $editType->description : GETPOST('description', 'restricthtml')).'</textarea></td>';
    print '</tr>';

    // Position
    print '<tr class="oddeven">';
    print '<td>'.$langs->trans("Position").'</td>';
    print '<td><input type="number" name="position" value="'.($editType ? $editType->position : (GETPOSTINT('position') ?: 100)).'" class="width100"></td>';
    print '</tr>';

    // Default Duration
    print '<tr class="oddeven">';
    print '<td>'.$langs->trans("DefaultDuration").'</td>';
    print '<td><input type="number" name="default_duration" value="'.($editType ? $editType->default_duration : GETPOSTINT('default_duration')).'" class="width100" min="0"> '.$langs->trans("Minutes");
    print '<br><span class="opacitymedium">'.$langs->trans("DefaultDurationHelp").'</span></td>';
    print '</tr>';

    // Default Interval
    print '<tr class="oddeven">';
    print '<td>'.$langs->trans("DefaultInterval").'</td>';
    print '<td>';
    $currentInterval = $editType ? $editType->default_interval : (GETPOST('default_interval', 'alpha') ?: 'yearly');
    print '<select name="default_interval" class="minwidth200">';
    print '<option value="yearly"'.($currentInterval == 'yearly' ? ' selected' : '').'>'.$langs->trans("IntervalYearly").'</option>';
    print '<option value="semi_annual"'.($currentInterval == 'semi_annual' ? ' selected' : '').'>'.$langs->trans("IntervalSemiAnnual").'</option>';
    print '</select>';
    print '<br><span class="opacitymedium">'.$langs->trans("DefaultIntervalHelp").'</span></td>';
    print '</tr>';

    print '</table>';
    print '</div>';

    print '<div class="center">';
    print '<input type="submit" class="button button-save" value="'.$langs->trans("Save").'">';
    print ' &nbsp; <a class="button button-cancel" href="'.$_SERVER["PHP_SELF"].'">'.$langs->trans("Cancel").'</a>';
    print '</div>';

    print '</form>';
    print '<br>';
}

// Add button
if ($action != 'create' && $action != 'edit') {
    print '<div class="tabsAction">';
    print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?action=create">'.$langs->trans("AddEquipmentType").'</a>';
    print '</div>';
}

// List of types
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Code").'</td>';
print '<td>'.$langs->trans("Label").'</td>';
print '<td>'.$langs->trans("TranslatedLabel").'</td>';
print '<td class="center">'.$langs->trans("Duration").'</td>';
print '<td class="center">'.$langs->trans("Interval").'</td>';
print '<td class="center">'.$langs->trans("Position").'</td>';
print '<td class="center">'.$langs->trans("Status").'</td>';
print '<td class="center">'.$langs->trans("Action").'</td>';
print '</tr>';

$sql = "SELECT * FROM ".MAIN_DB_PREFIX."equipmentmanager_equipment_types";
$sql .= " WHERE entity = ".(int)$conf->entity;
$sql .= " ORDER BY position ASC, code ASC";

$resql = $db->query($sql);
if ($resql) {
    $num = $db->num_rows($resql);
    if ($num > 0) {
        $i = 0;
        while ($obj = $db->fetch_object($resql)) {
            print '<tr class="oddeven">';

            // Code
            print '<td><code>'.dol_escape_htmltag($obj->code).'</code></td>';

            // Label (key for translation)
            print '<td>'.dol_escape_htmltag($obj->label).'</td>';

            // Translated label
            $translated = $langs->trans($obj->label);
            if ($translated == $obj->label) {
                // No translation found, show label as-is
                print '<td><span class="opacitymedium">'.dol_escape_htmltag($obj->label).'</span></td>';
            } else {
                print '<td>'.dol_escape_htmltag($translated).'</td>';
            }

            // Duration
            print '<td class="center">';
            if ($obj->default_duration > 0) {
                if ($obj->default_duration >= 60) {
                    $hours = floor($obj->default_duration / 60);
                    $mins = $obj->default_duration % 60;
                    print $hours.'h'.($mins > 0 ? ' '.$mins.'min' : '');
                } else {
                    print $obj->default_duration.' min';
                }
            } else {
                print '<span class="opacitymedium">-</span>';
            }
            print '</td>';

            // Interval
            print '<td class="center">';
            if ($obj->default_interval == 'semi_annual') {
                print '<span class="badge badge-status1">'.$langs->trans("IntervalSemiAnnual").'</span>';
            } else {
                print '<span class="badge badge-status4">'.$langs->trans("IntervalYearly").'</span>';
            }
            print '</td>';

            // Position
            print '<td class="center">'.$obj->position.'</td>';

            // Status
            print '<td class="center">';
            if ($obj->active) {
                print '<a href="'.$_SERVER["PHP_SELF"].'?action=deactivate&id='.$obj->rowid.'&token='.newToken().'">'.img_picto($langs->trans("Enabled"), 'switch_on').'</a>';
            } else {
                print '<a href="'.$_SERVER["PHP_SELF"].'?action=activate&id='.$obj->rowid.'&token='.newToken().'">'.img_picto($langs->trans("Disabled"), 'switch_off').'</a>';
            }
            print '</td>';

            // Actions
            print '<td class="center nowraponall">';
            // Move up/down
            if ($i > 0) {
                print '<a href="'.$_SERVER["PHP_SELF"].'?action=up&id='.$obj->rowid.'&token='.newToken().'">'.img_picto($langs->trans("Up"), 'chevron-up', 'class="pictofixedwidth"').'</a>';
            } else {
                print img_picto('', 'chevron-up', 'class="pictofixedwidth opacitymedium"');
            }
            if ($i < $num - 1) {
                print '<a href="'.$_SERVER["PHP_SELF"].'?action=down&id='.$obj->rowid.'&token='.newToken().'">'.img_picto($langs->trans("Down"), 'chevron-down', 'class="pictofixedwidth"').'</a>';
            } else {
                print img_picto('', 'chevron-down', 'class="pictofixedwidth opacitymedium"');
            }
            print ' &nbsp; ';
            // Edit
            print '<a href="'.$_SERVER["PHP_SELF"].'?action=edit&id='.$obj->rowid.'">'.img_picto($langs->trans("Edit"), 'edit').'</a>';
            print ' &nbsp; ';
            // Delete
            print '<a href="'.$_SERVER["PHP_SELF"].'?action=delete&id='.$obj->rowid.'&token='.newToken().'">'.img_picto($langs->trans("Delete"), 'delete').'</a>';
            print '</td>';

            print '</tr>';
            $i++;
        }
    } else {
        print '<tr class="oddeven"><td colspan="8" class="opacitymedium">'.$langs->trans("NoRecordFound").'</td></tr>';
    }
} else {
    dol_print_error($db);
}

print '</table>';
print '</div>';

// Info box
print '<br>';
print '<div class="info">';
print '<strong>'.$langs->trans("EquipmentTypesInfo").'</strong><br>';
print $langs->trans("EquipmentTypesInfoDesc");
print '</div>';

print dol_get_fiche_end();

// End of page
llxFooter();
$db->close();
