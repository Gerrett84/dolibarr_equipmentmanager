<?php
/* Copyright (C) 2024 Equipment Manager
 * Equipment View Page - Nur Anzeige
 */

// Load Dolibarr environment
$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
    $res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
if (!$res && file_exists("../main.inc.php")) {
    $res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
    $res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
    $res = @include "../../../main.inc.php";
}
if (!$res) {
    die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/core/class/html.formcompany.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/fichinter/class/fichinter.class.php';
dol_include_once('/equipmentmanager/class/equipment.class.php');

$langs->loadLangs(array('equipmentmanager@equipmentmanager', 'companies', 'other', 'interventions'));

$id = GETPOST('id', 'int');
$ref = GETPOST('ref', 'alpha');
$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'alpha');

$object = new Equipment($db);
$form = new Form($db);

if ($id > 0 || !empty($ref)) {
    $result = $object->fetch($id, $ref);
    if ($result < 0) {
        setEventMessages($object->error, $object->errors, 'errors');
    }
}

$permissiontoread = $user->rights->equipmentmanager->equipment->read;
$permissiontoadd = $user->rights->equipmentmanager->equipment->write;
$permissiontodelete = $user->rights->equipmentmanager->equipment->delete;

if (!$permissiontoread) {
    accessforbidden();
}

/*
 * Actions
 */

if ($action == 'confirm_delete' && $confirm == 'yes' && $permissiontodelete) {
    $result = $object->delete($user);
    if ($result > 0) {
        setEventMessages($langs->trans("EquipmentDeleted"), null, 'mesgs');
        header("Location: ".DOL_URL_ROOT.'/custom/equipmentmanager/equipment_list.php');
        exit;
    } else {
        setEventMessages($object->error, $object->errors, 'errors');
    }
}

/*
 * View
 */

$title = $langs->trans("Equipment");
llxHeader('', $title, '');

if ($object->id > 0) {
    $head = array();
    $head[0][0] = DOL_URL_ROOT.'/custom/equipmentmanager/equipment_view.php?id='.$object->id;
    $head[0][1] = $langs->trans("Card");
    $head[0][2] = 'card';
    
    print dol_get_fiche_head($head, 'card', $langs->trans("Equipment"), -1, 'generic');
    
    if ($action == 'delete') {
        $formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('DeleteEquipment'), $langs->trans('ConfirmDeleteEquipment'), 'confirm_delete', '', 0, 1);
        print $formconfirm;
    }
    
    $linkback = '<a href="'.DOL_URL_ROOT.'/custom/equipmentmanager/equipment_list.php?restore_lastsearch_values=1">'.$langs->trans("BackToList").'</a>';
    
    // Banner: Equipment Number as ref
    $object->ref = $object->equipment_number;
    
    dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', '', '', 0, '', '', 1);
    
    print '<div class="fichecenter">';
    print '<div class="fichehalfleft">';
    print '<div class="underbanner clearboth"></div>';
    print '<table class="border centpercent tableforfield">'."\n";
    
    // Equipment Number
    print '<tr><td class="titlefield">'.$langs->trans("EquipmentNumber").'</td><td>';
    print '<strong>'.dol_escape_htmltag($object->equipment_number).'</strong>';
    print '</td></tr>';
    
    // Label
    print '<tr><td>'.$langs->trans("Label").'</td><td>';
    print dol_escape_htmltag($object->label);
    print '</td></tr>';
    
    // Type
    print '<tr><td>'.$langs->trans("Type").'</td><td>';
    $type_labels = array(
        'door_swing' => $langs->trans('DoorSwing'),
        'door_sliding' => $langs->trans('DoorSliding'),
        'fire_door' => $langs->trans('FireDoor'),
        'door_closer' => $langs->trans('DoorCloser'),
        'hold_open' => $langs->trans('HoldOpen'),
        'rws' => $langs->trans('RWS'),
        'rwa' => $langs->trans('RWA'),
        'other' => $langs->trans('Other')
    );
    print isset($type_labels[$object->equipment_type]) ? $type_labels[$object->equipment_type] : dol_escape_htmltag($object->equipment_type);
    print '</td></tr>';
    
    // Manufacturer
    print '<tr><td>'.$langs->trans("Manufacturer").'</td><td>';
    print $object->manufacturer ? dol_escape_htmltag($object->manufacturer) : '<span class="opacitymedium">-</span>';
    print '</td></tr>';
    
    // Door Wings
    print '<tr><td>'.$langs->trans("DoorWings").'</td><td>';
    if ($object->door_wings == '1') {
        print '1-flüglig';
    } elseif ($object->door_wings == '2') {
        print '2-flüglig';
    } else {
        print '<span class="opacitymedium">-</span>';
    }
    print '</td></tr>';
    
    print '</table>';
    print '</div>';
    
    print '<div class="fichehalfright">';
    print '<div class="underbanner clearboth"></div>';
    print '<table class="border centpercent tableforfield">'."\n";
    
    // Third Party
    print '<tr><td class="titlefield">'.$langs->trans("ThirdParty").'</td><td>';
    if ($object->fk_soc > 0) {
        $companystatic = new Societe($db);
        $companystatic->fetch($object->fk_soc);
        print $companystatic->getNomUrl(1);
    } else {
        print '<span class="opacitymedium">-</span>';
    }
    print '</td></tr>';
    
    // Object Address
    print '<tr><td>'.$langs->trans("ObjectAddress").'</td><td>';
    if ($object->fk_address > 0) {
        $sql = "SELECT CONCAT(lastname, ' ', firstname) as name, address, zip, town FROM ".MAIN_DB_PREFIX."socpeople";
        $sql .= " WHERE rowid = ".(int)$object->fk_address;
        $resql = $db->query($sql);
        if ($resql && $db->num_rows($resql)) {
            $addr = $db->fetch_object($resql);
            print '<strong>'.dol_escape_htmltag($addr->name).'</strong><br>';
            if ($addr->address) print dol_escape_htmltag($addr->address).'<br>';
            if ($addr->zip || $addr->town) print dol_escape_htmltag($addr->zip.' '.$addr->town);
        }
    } else {
        print '<span class="opacitymedium">-</span>';
    }
    print '</td></tr>';
    
    // Location / Note
    print '<tr><td class="tdtop">'.$langs->trans("LocationNote").'</td><td>';
    print $object->location_note ? nl2br(dol_escape_htmltag($object->location_note)) : '<span class="opacitymedium">-</span>';
    print '</td></tr>';
    
    // Serial Number
    print '<tr><td>'.$langs->trans("SerialNumber").'</td><td>';
    print $object->serial_number ? dol_escape_htmltag($object->serial_number) : '<span class="opacitymedium">-</span>';
    print '</td></tr>';
    
    // Installation Date
    print '<tr><td>'.$langs->trans("InstallationDate").'</td><td>';
    print $object->installation_date ? dol_print_date($object->installation_date, 'day') : '<span class="opacitymedium">-</span>';
    print '</td></tr>';
    
    // Status
    print '<tr><td>'.$langs->trans("MaintenanceContract").'</td><td>';
    if ($object->status == 1) {
        print '<span class="badge badge-status4 badge-status">'.$langs->trans('ActiveContract').'</span>';
    } else {
        print '<span class="badge badge-status8 badge-status">'.$langs->trans('NoContract').'</span>';
    }
    print '</td></tr>';
    
    print '</table>';
    print '</div>';
    print '</div>';
    
    print '<div class="clearboth"></div>';
    
    print dol_get_fiche_end();
    
    // Actions buttons
    print '<div class="tabsAction">'."\n";
    
    if ($permissiontoadd) {
        print '<a class="butAction" href="'.DOL_URL_ROOT.'/custom/equipmentmanager/equipment_edit.php?id='.$object->id.'">'.$langs->trans("Modify").'</a>'."\n";
    }
    
    if ($permissiontodelete) {
        print '<a class="butActionDelete" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=delete&token='.newToken().'">'.$langs->trans("Delete").'</a>'."\n";
    }
    
    print '</div>'."\n";
    
    // ========================================================================
    // HISTORY SECTION
    // ========================================================================
    
    print '<br><br>';
    
    // Section 1: Serviceaufträge
    print '<div class="div-table-responsive-no-min">';
    print '<table class="noborder centpercent">';
    
    print '<tr class="liste_titre">';
    print '<th colspan="5"><span class="fa fa-history paddingright"></span>'.$langs->trans('EquipmentHistory').'</th>';
    print '</tr>';
    
    print '<tr class="liste_titre">';
    print '<th>'.$langs->trans('Ref').'</th>';
    print '<th>'.$langs->trans('Type').'</th>';
    print '<th>'.$langs->trans('Date').'</th>';
    print '<th>'.$langs->trans('Customer').'</th>';
    print '<th>'.$langs->trans('Status').'</th>';
    print '</tr>';
    
    // Get linked interventions
    $sql = "SELECT l.fk_intervention, l.link_type, l.date_creation";
    $sql .= " FROM ".MAIN_DB_PREFIX."equipmentmanager_intervention_link as l";
    $sql .= " WHERE l.fk_equipment = ".(int)$object->id;
    $sql .= " ORDER BY l.date_creation DESC";
    
    $resql = $db->query($sql);
    
    if ($resql && $db->num_rows($resql) > 0) {
        while ($obj = $db->fetch_object($resql)) {
            $intervention = new Fichinter($db);
            if ($intervention->fetch($obj->fk_intervention) > 0) {
                $intervention->fetch_thirdparty();
                
                print '<tr class="oddeven">';
                print '<td>'.$intervention->getNomUrl(1).'</td>';
                
                // Type Badge
                print '<td>';
                if ($obj->link_type == 'maintenance') {
                    print '<span class="badge" style="background: #4caf50; color: white;">'.ucfirst($obj->link_type).'</span>';
                } else {
                    print '<span class="badge" style="background: #ff9800; color: white;">'.ucfirst($obj->link_type).'</span>';
                }
                print '</td>';
                
                print '<td>'.dol_print_date($intervention->datec, 'day').'</td>';
                print '<td>';
                if ($intervention->thirdparty) {
                    print $intervention->thirdparty->getNomUrl(1);
                }
                print '</td>';
                print '<td>'.$intervention->getLibStatut(5).'</td>';
                print '</tr>';
            }
        }
    } else {
        print '<tr><td colspan="5" class="opacitymedium center">';
        print $langs->trans('NoInterventionsLinked');
        print '</td></tr>';
    }
    
    print '</table>';
    print '</div>';
    
    print '<br>';
    
    // Section 2: Zugehörige Dokumente (Platzhalter)
    print '<div class="div-table-responsive-no-min">';
    print '<table class="noborder centpercent">';
    
    print '<tr class="liste_titre">';
    print '<th colspan="4"><span class="fa fa-file-text-o paddingright"></span>'.$langs->trans('RelatedDocuments').'</th>';
    print '</tr>';
    
    print '<tr><td colspan="4" class="opacitymedium center" style="padding: 20px;">';
    print '<em>'.$langs->trans('ComingSoon').': '.$langs->trans('Proposals').', '.$langs->trans('Orders').', '.$langs->trans('Contracts').'</em>';
    print '</td></tr>';
    
    print '</table>';
    print '</div>';
}

llxFooter();
$db->close();