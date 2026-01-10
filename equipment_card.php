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
require_once DOL_DOCUMENT_ROOT.'/fichinter/class/fichinter.class.php';
dol_include_once('/equipmentmanager/class/equipment.class.php');

$langs->loadLangs(array('equipmentmanager@equipmentmanager', 'companies', 'other', 'interventions'));

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
    $object->fk_address = GETPOST('fk_address', 'int');
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
    $object->fk_address = GETPOST('fk_address', 'int');
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

// JavaScript for auto/manual mode and address loading
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

// Load addresses when customer changes
jQuery(document).ready(function() {
    jQuery("#fk_soc_select").change(function() {
        var socid = jQuery(this).val();
        var addressSelect = jQuery("#fk_address_select");
        
        if (socid > 0) {
            // Load addresses via AJAX
            jQuery.ajax({
                url: "'.DOL_URL_ROOT.'/core/ajax/contacts.php?action=fetch&htmlname=fk_address&socid=" + socid,
                type: "GET",
                success: function(data) {
                    addressSelect.html(data);
                }
            });
        } else {
            addressSelect.html("<option value=\'\'>---</option>");
        }
    });
});
</script>';

// ============================================================================
// CREATE MODE
// ============================================================================
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
    
    // Type (dynamic from database)
    print '<tr><td class="fieldrequired">'.$langs->trans("Type").'</td><td>';
    print '<select name="equipment_type" class="flat minwidth200" required>';
    print '<option value=""></option>';
    $equipmentTypes = Equipment::getEquipmentTypes($db);
    foreach ($equipmentTypes as $code => $label) {
        print '<option value="'.dol_escape_htmltag($code).'">'.$langs->trans($label).'</option>';
    }
    print '</select>';
    print '</td></tr>';

    // Manufacturer
    print '<tr><td>'.$langs->trans("Manufacturer").'</td><td>';
    print '<input type="text" name="manufacturer" size="30" placeholder="'.$langs->trans('Manufacturer').'" value="">';
    print '</td></tr>';
    
    // Door Wings
    print '<tr><td>'.$langs->trans("DoorWings").'</td><td>';
    print '<select name="door_wings" class="flat">';
    print '<option value=""></option>';
    print '<option value="1">1-flüglig</option>';
    print '<option value="2">2-flüglig</option>';
    print '</select>';
    print '</td></tr>';
    
    // Third Party
    print '<tr><td class="fieldrequired">'.$langs->trans("ThirdParty").'</td><td>';
    print $form->select_company(0, 'fk_soc', '', 'SelectThirdParty', 0, 0, null, 0, 'minwidth300', 0, '', 0, 'fk_soc_select');
    print '</td></tr>';
    
    // Object Address
    print '<tr><td>'.$langs->trans("ObjectAddress").'</td><td>';
    print '<select name="fk_address" id="fk_address_select" class="flat minwidth300">';
    print '<option value="">---</option>';
    print '</select>';
    print ' <span class="opacitymedium">'.$langs->trans("SelectThirdPartyFirst").'</span>';
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
    
    // Status
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

// ============================================================================
// EDIT MODE
// ============================================================================
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
    
    // Type (dynamic from database)
    print '<tr><td class="fieldrequired">'.$langs->trans("Type").'</td><td>';
    print '<select name="equipment_type" class="flat minwidth200" required>';
    $equipmentTypes = Equipment::getEquipmentTypes($db);
    foreach ($equipmentTypes as $code => $label) {
        $selected = ($object->equipment_type == $code) ? ' selected' : '';
        print '<option value="'.dol_escape_htmltag($code).'"'.$selected.'>'.$langs->trans($label).'</option>';
    }
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
    print '<tr><td class="fieldrequired">'.$langs->trans("ThirdParty").'</td><td>';
    print $form->select_company($object->fk_soc, 'fk_soc', '', 'SelectThirdParty', 0, 0, null, 0, 'minwidth300', 0, '', 0, 'fk_soc_select');
    print '</td></tr>';
    
    // Object Address
    print '<tr><td>'.$langs->trans("ObjectAddress").'</td><td>';
    if ($object->fk_soc > 0) {
        print '<select name="fk_address" id="fk_address_select" class="flat minwidth300">';
        print '<option value="">---</option>';
        
        // Load contacts (addresses) of the third party
        $sql = "SELECT rowid, CONCAT(lastname, ' ', firstname) as name, address, zip, town FROM ".MAIN_DB_PREFIX."socpeople";
        $sql .= " WHERE fk_soc = ".(int)$object->fk_soc;
        $sql .= " ORDER BY lastname, firstname";
        
        $resql = $db->query($sql);
        if ($resql) {
            while ($addr = $db->fetch_object($resql)) {
                $selected = ($object->fk_address == $addr->rowid) ? ' selected' : '';
                $address_display = $addr->name;
                if ($addr->town) $address_display .= ' - '.$addr->town;
                
                print '<option value="'.$addr->rowid.'"'.$selected.'>';
                print dol_escape_htmltag($address_display);
                print '</option>';
            }
        }
        print '</select>';
    } else {
        print '<span class="opacitymedium">'.$langs->trans("SelectThirdPartyFirst").'</span>';
    }
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
    
    // Status
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

// ============================================================================
// VIEW MODE
// ============================================================================
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
    
    // ============================================================================
    // HISTORY SECTION - Anlagen-Historie (untereinander)
    // ============================================================================
    
    print '<br><br>';
    
    // SECTION 1: Serviceaufträge
    print '<div class="div-table-responsive-no-min">';
    print '<table class="noborder centpercent">';
    
    print '<tr class="liste_titre">';
    print '<th colspan="4"><span class="fa fa-history paddingright"></span>'.$langs->trans('EquipmentHistory').'</th>';
    print '</tr>';
    
    print '<tr class="liste_titre">';
    print '<th>'.$langs->trans('Ref').'</th>';
    print '<th>'.$langs->trans('Date').'</th>';
    print '<th>'.$langs->trans('Customer').'</th>';
    print '<th>'.$langs->trans('Status').'</th>';
    print '</tr>';
    
    // Get linked interventions
    $sql = "SELECT l.fk_intervention, l.date_creation";
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
        print '<tr><td colspan="4" class="opacitymedium center">';
        print $langs->trans('NoInterventionsLinked');
        print '</td></tr>';
    }
    
    print '</table>';
    print '</div>';
    
    print '<br>';
    
    // SECTION 2: Zugehörige Dokumente (Platzhalter)
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