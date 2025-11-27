<?php
/* Copyright (C) 2024 Equipment Manager */

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
dol_include_once('/equipmentmanager/class/equipment.class.php');

$langs->loadLangs(array("equipmentmanager@equipmentmanager", "companies", "other"));

$id = GETPOST('id', 'int');
$ref = GETPOST('ref', 'alpha');
$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'alpha');
$cancel = GETPOST('cancel', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');

$object = new Equipment($db);
$extrafields = new ExtraFields($db);
$form = new Form($db);
$formcompany = new FormCompany($db);
$formfile = new FormFile($db);

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

if ($action == 'add' && !$cancel && $permissiontoadd) {
    $error = 0;
    
    $object->ref = 'auto';
    $object->equipment_number_mode = GETPOST('equipment_number_mode', 'alpha');
    $object->equipment_number = GETPOST('equipment_number', 'alpha');
    $object->label = GETPOST('label', 'alpha');
    $object->equipment_type = GETPOST('equipment_type', 'alpha');
    $object->manufacturer = GETPOST('manufacturer', 'alpha');
    $object->door_wings = GETPOST('door_wings', 'alpha');
    $object->fk_soc = GETPOST('fk_soc', 'int');
    $object->location_note = GETPOST('location_note', 'restricthtml');
    $object->serial_number = GETPOST('serial_number', 'alpha');
    $object->installation_date = dol_mktime(0, 0, 0, GETPOST('installation_datemonth', 'int'), GETPOST('installation_dateday', 'int'), GETPOST('installation_dateyear', 'int'));
    $object->status = GETPOST('status', 'int');
    
    if (empty($object->label)) {
        setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("Label")), null, 'errors');
        $error++;
    }
    if (empty($object->equipment_type)) {
        setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("Type")), null, 'errors');
        $error++;
    }
    if ($object->equipment_number_mode == 'manual' && empty($object->equipment_number)) {
        setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("EquipmentNumber")), null, 'errors');
        $error++;
    }
    
    if (!$error) {
        $result = $object->create($user);
        if ($result > 0) {
            setEventMessages($langs->trans("EquipmentCreated"), null, 'mesgs');
            header("Location: ".$_SERVER['PHP_SELF']."?id=".$result);
            exit;
        } else {
            setEventMessages($object->error, $object->errors, 'errors');
            $action = 'create';
        }
    } else {
        $action = 'create';
    }
}

if ($action == 'update' && !$cancel && $permissiontoadd) {
    $error = 0;
    
    $object->equipment_number_mode = GETPOST('equipment_number_mode', 'alpha');
    $object->equipment_number = GETPOST('equipment_number', 'alpha');
    $object->label = GETPOST('label', 'alpha');
    $object->equipment_type = GETPOST('equipment_type', 'alpha');
    $object->manufacturer = GETPOST('manufacturer', 'alpha');
    $object->door_wings = GETPOST('door_wings', 'alpha');
    $object->fk_soc = GETPOST('fk_soc', 'int');
    $object->location_note = GETPOST('location_note', 'restricthtml');
    $object->serial_number = GETPOST('serial_number', 'alpha');
    $object->installation_date = dol_mktime(0, 0, 0, GETPOST('installation_datemonth', 'int'), GETPOST('installation_dateday', 'int'), GETPOST('installation_dateyear', 'int'));
    $object->status = GETPOST('status', 'int');
    
    if (!$error) {
        $result = $object->update($user);
        if ($result > 0) {
            setEventMessages($langs->trans("EquipmentUpdated"), null, 'mesgs');
            header("Location: ".$_SERVER['PHP_SELF']."?id=".$object->id);
            exit;
        } else {
            setEventMessages($object->error, $object->errors, 'errors');
            $action = 'edit';
        }
    } else {
        $action = 'edit';
    }
}

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
$help_url = '';
llxHeader('', $title, $help_url);

// JavaScript for auto/manual mode
print '<script type="text/javascript">
function toggleEquipmentNumberMode() {
    var mode = document.getElementById("equipment_number_mode").value;
    var numberField = document.getElementById("equipment_number_field");
    var numberInput = document.getElementById("equipment_number");
    
    if (mode == "auto") {
        numberField.style.display = "none";
        numberInput.value = "";
        numberInput.removeAttribute("required");
    } else {
        numberField.style.display = "table-row";
        numberInput.setAttribute("required", "required");
    }
}
</script>';

// Create mode
if ($action == 'create') {
    print load_fiche_titre($langs->trans("NewEquipment"), '', 'object_generic');
    
    print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
    print '<input type="hidden" name="token" value="'.newToken().'">';
    print '<input type="hidden" name="action" value="add">';
    
    print dol_get_fiche_head();
    
    print '<table class="border centpercent tableforfieldcreate">'."\n";
    
    // Equipment Number Mode
    print '<tr><td class="fieldrequired titlefieldcreate">'.$langs->trans("EquipmentNumberMode").'</td><td>';
    print '<select name="equipment_number_mode" id="equipment_number_mode" class="flat" onchange="toggleEquipmentNumberMode()" required>';
    print '<option value="auto" selected>'.$langs->trans('EquipmentNumberAuto').'</option>';
    print '<option value="manual">'.$langs->trans('EquipmentNumberManual').'</option>';
    print '</select>';
    print '</td></tr>';
    
    // Equipment Number (shown only when manual)
    print '<tr id="equipment_number_field" style="display:none;"><td class="fieldrequired">'.$langs->trans("EquipmentNumber").'</td><td>';
    print '<input type="text" name="equipment_number" id="equipment_number" size="30" placeholder="A000123" value="">';
    print '</td></tr>';
    
    // Label
    print '<tr><td class="fieldrequired">'.$langs->trans("Label").'</td><td>';
    print '<input type="text" name="label" size="30" placeholder="'.$langs->trans('Label').'" value="" required>';
    print '</td></tr>';
    
    // Type
    print '<tr><td class="fieldrequired">'.$langs->trans("Type").'</td><td>';
    print '<select name="equipment_type" class="flat minwidth200" required>';
    print '<option value=""></option>';
    print '<option value="door_swing">'.$langs->trans('DoorSwing').'</option>';
    print '<option value="door_sliding">'.$langs->trans('DoorSliding').'</option>';
    print '<option value="fire_door">'.$langs->trans('FireDoor').'</option>';
    print '<option value="door_closer">'.$langs->trans('DoorCloser').'</option>';
    print '<option value="hold_open">'.$langs->trans('HoldOpen').'</option>';
    print '<option value="rws">'.$langs->trans('RWS').'</option>';
    print '<option value="rwa">'.$langs->trans('RWA').'</option>';
    print '<option value="other">'.$langs->trans('Other').'</option>';
    print '</select>';
    print '</td></tr>';
    
    // Manufacturer
    print '<tr><td>'.$langs->trans("Manufacturer").'</td><td>';
    print '<input type="text" name="manufacturer" size="30" placeholder="'.$langs->trans('Manufacturer').'" value="">';
    print '</td></tr>';
    
    // Door Wings (1-flüglig / 2-flüglig)
    print '<tr><td>'.$langs->trans("DoorWings").'</td><td>';
    print '<select name="door_wings" class="flat">';
    print '<option value=""></option>';
    print '<option value="1">1-flüglig</option>';
    print '<option value="2">2-flüglig</option>';
    print '</select>';
    print '</td></tr>';
    
    // Third Party
    print '<tr><td>'.$langs->trans("ThirdParty").'</td><td>';
    print $form->select_company(0, 'fk_soc', '', 'SelectThirdParty', 0, 0, null, 0, 'minwidth300');
    print '</td></tr>';
    
    // Location / Note
    print '<tr><td class="tdtop">'.$langs->trans("LocationNote").'</td><td>';
    print '<textarea name="location_note" rows="3" class="flat centpercent" placeholder="'.$langs->trans('LocationNote').'"></textarea>';
    print '</td></tr>';
    
    // Serial Number
    print '<tr><td>'.$langs->trans("SerialNumber").'</td><td>';
    print '<input type="text" name="serial_number" size="30" placeholder="SN12938123" value="">';
    print '</td></tr>';
    
    // Installation Date
    print '<tr><td>'.$langs->trans("InstallationDate").'</td><td>';
    print $form->selectDate('', 'installation_date', 0, 0, 1, '', 1, 0);
    print '</td></tr>';
    
    // Maintenance Contract Status
    print '<tr><td>'.$langs->trans("MaintenanceContract").'</td><td>';
    print '<select name="status" class="flat">';
    print '<option value="1" selected>'.$langs->trans('ActiveContract').'</option>';
    print '<option value="0">'.$langs->trans('NoContract').'</option>';
    print '</select>';
    print '</td></tr>';
    
    print '</table>';
    
    print dol_get_fiche_end();
    
    print '<div class="center">';
    print '<input type="submit" class="button button-save" name="save" value="'.$langs->trans("Create").'">';
    print ' &nbsp; ';
    print '<input type="button" class="button button-cancel" name="cancel" value="'.$langs->trans("Cancel").'" onclick="history.back()">';
    print '</div>';
    
    print '</form>';
}

// Edit mode
if (($id || $ref) && $action == 'edit') {
    print load_fiche_titre($langs->trans("Equipment"), '', 'object_generic');
    
    print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
    print '<input type="hidden" name="token" value="'.newToken().'">';
    print '<input type="hidden" name="action" value="update">';
    print '<input type="hidden" name="id" value="'.$object->id.'">';
    
    print dol_get_fiche_head();
    
    print '<table class="border centpercent tableforfieldedit">'."\n";
    
    // Equipment Number Mode
    print '<tr><td class="fieldrequired titlefieldcreate">'.$langs->trans("EquipmentNumberMode").'</td><td>';
    $current_mode = $object->equipment_number_mode ? $object->equipment_number_mode : 'auto';
    print '<select name="equipment_number_mode" id="equipment_number_mode" class="flat" onchange="toggleEquipmentNumberMode()" required>';
    print '<option value="auto"'.($current_mode == 'auto' ? ' selected' : '').'>'.$langs->trans('EquipmentNumberAuto').'</option>';
    print '<option value="manual"'.($current_mode == 'manual' ? ' selected' : '').'>'.$langs->trans('EquipmentNumberManual').'</option>';
    print '</select>';
    print '</td></tr>';
    
    // Equipment Number
    print '<tr id="equipment_number_field" style="display:'.($current_mode == 'manual' ? 'table-row' : 'none').';">'; 
    print '<td class="fieldrequired">'.$langs->trans("EquipmentNumber").'</td><td>';
    print '<input type="text" name="equipment_number" id="equipment_number" size="30" value="'.$object->equipment_number.'"'.($current_mode == 'manual' ? ' required' : '').'>';
    print '</td></tr>';
    
    // Label
    print '<tr><td class="fieldrequired">'.$langs->trans("Label").'</td><td>';
    print '<input type="text" name="label" size="30" value="'.dol_escape_htmltag($object->label).'" required>';
    print '</td></tr>';
    
    // Type
    print '<tr><td class="fieldrequired">'.$langs->trans("Type").'</td><td>';
    print '<select name="equipment_type" class="flat minwidth200" required>';
    print '<option value="door_swing"'.($object->equipment_type == 'door_swing' ? ' selected' : '').'>'.$langs->trans('DoorSwing').'</option>';
    print '<option value="door_sliding"'.($object->equipment_type == 'door_sliding' ? ' selected' : '').'>'.$langs->trans('DoorSliding').'</option>';
    print '<option value="fire_door"'.($object->equipment_type == 'fire_door' ? ' selected' : '').'>'.$langs->trans('FireDoor').'</option>';
    print '<option value="door_closer"'.($object->equipment_type == 'door_closer' ? ' selected' : '').'>'.$langs->trans('DoorCloser').'</option>';
    print '<option value="hold_open"'.($object->equipment_type == 'hold_open' ? ' selected' : '').'>'.$langs->trans('HoldOpen').'</option>';
    print '<option value="rws"'.($object->equipment_type == 'rws' ? ' selected' : '').'>'.$langs->trans('RWS').'</option>';
    print '<option value="rwa"'.($object->equipment_type == 'rwa' ? ' selected' : '').'>'.$langs->trans('RWA').'</option>';
    print '<option value="other"'.($object->equipment_type == 'other' ? ' selected' : '').'>'.$langs->trans('Other').'</option>';
    print '</select>';
    print '</td></tr>';
    
    // Manufacturer
    print '<tr><td>'.$langs->trans("Manufacturer").'</td><td>';
    print '<input type="text" name="manufacturer" size="30" value="'.dol_escape_htmltag($object->manufacturer).'">';
    print '</td></tr>';
    
    // Door Wings
    print '<tr><td>'.$langs->trans("DoorWings").'</td><td>';
    print '<select name="door_wings" class="flat">';
    print '<option value=""></option>';
    print '<option value="1"'.($object->door_wings == '1' ? ' selected' : '').'>1-flüglig</option>';
    print '<option value="2"'.($object->door_wings == '2' ? ' selected' : '').'>2-flüglig</option>';
    print '</select>';
    print '</td></tr>';
    
    // Third Party
    print '<tr><td>'.$langs->trans("ThirdParty").'</td><td>';
    print $form->select_company($object->fk_soc, 'fk_soc', '', 'SelectThirdParty', 0, 0, null, 0, 'minwidth300');
    print '</td></tr>';
    
    // Location / Note
    print '<tr><td class="tdtop">'.$langs->trans("LocationNote").'</td><td>';
    print '<textarea name="location_note" rows="3" class="flat centpercent">'.dol_escape_htmltag($object->location_note).'</textarea>';
    print '</td></tr>';
    
    // Serial Number
    print '<tr><td>'.$langs->trans("SerialNumber").'</td><td>';
    print '<input type="text" name="serial_number" size="30" value="'.dol_escape_htmltag($object->serial_number).'">';
    print '</td></tr>';
    
    // Installation Date
    print '<tr><td>'.$langs->trans("InstallationDate").'</td><td>';
    print $form->selectDate($object->installation_date, 'installation_date', 0, 0, 1, '', 1, 0);
    print '</td></tr>';
    
    // Maintenance Contract Status
    print '<tr><td>'.$langs->trans("MaintenanceContract").'</td><td>';
    print '<select name="status" class="flat">';
    print '<option value="1"'.($object->status == 1 ? ' selected' : '').'>'.$langs->trans('ActiveContract').'</option>';
    print '<option value="0"'.($object->status == 0 ? ' selected' : '').'>'.$langs->trans('NoContract').'</option>';
    print '</select>';
    print '</td></tr>';
    
    print '</table>';
    
    print dol_get_fiche_end();
    
    print '<div class="center">';
    print '<input type="submit" class="button button-save" name="save" value="'.$langs->trans("Save").'">';
    print ' &nbsp; ';
    print '<input type="button" class="button button-cancel" name="cancel" value="'.$langs->trans("Cancel").'" onclick="window.location.href=\''.$_SERVER['PHP_SELF'].'?id='.$object->id.'\'">';
    print '</div>';
    
    print '</form>';
}

// View mode
if ($object->id > 0 && (empty($action) || ($action != 'edit' && $action != 'create'))) {
    $head = array();
    $head[0][0] = DOL_URL_ROOT.'/custom/equipmentmanager/equipment_card.php?id='.$object->id;
    $head[0][1] = $langs->trans("Card");
    $head[0][2] = 'card';
    
    print dol_get_fiche_head($head, 'card', $langs->trans("Equipment"), -1, 'generic');
    
    if ($action == 'delete') {
        $formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('DeleteEquipment'), $langs->trans('ConfirmDeleteEquipment'), 'confirm_delete', '', 0, 1);
        print $formconfirm;
    }
    
    $linkback = '<a href="'.DOL_URL_ROOT.'/custom/equipmentmanager/equipment_list.php?restore_lastsearch_values=1">'.$langs->trans("BackToList").'</a>';
    
    // Banner-Titel ist die Equipment-Nummer
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
    
    // Maintenance Contract Status
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
    
    if ($action != 'edit') {
        print '<div class="tabsAction">'."\n";
        
        if ($permissiontoadd) {
            print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=edit&token='.newToken().'">'.$langs->trans("Modify").'</a>'."\n";
        }
        
        if ($permissiontodelete) {
            print '<a class="butActionDelete" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=delete&token='.newToken().'">'.$langs->trans("Delete").'</a>'."\n";
        }
        
        print '</div>'."\n";
    }
}

llxFooter();
$db->close();