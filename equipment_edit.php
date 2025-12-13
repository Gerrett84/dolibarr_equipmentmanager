<?php
/* Copyright (C) 2024 Equipment Manager
 * Equipment Edit Page - Erstellen & Bearbeiten
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
dol_include_once('/equipmentmanager/class/equipment.class.php');

$langs->loadLangs(array('equipmentmanager@equipmentmanager', 'companies', 'other'));

$id = GETPOST('id', 'int');
$ref = GETPOST('ref', 'alpha');
$action = GETPOST('action', 'aZ09');
$cancel = GETPOST('cancel', 'aZ09');

$object = new Equipment($db);
$form = new Form($db);
$formcompany = new FormCompany($db);

if ($id > 0 || !empty($ref)) {
    $result = $object->fetch($id, $ref);
    if ($result < 0) {
        setEventMessages($object->error, $object->errors, 'errors');
    }
}

$permissiontoread = $user->rights->equipmentmanager->equipment->read;
$permissiontoadd = $user->rights->equipmentmanager->equipment->write;

if (!$permissiontoadd) {
    accessforbidden();
}

/*
 * Actions
 */

if ($action == 'add' && !$cancel) {
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
            header("Location: ".DOL_URL_ROOT.'/custom/equipmentmanager/equipment_view.php?id='.$result);
            exit;
        } else {
            setEventMessages($object->error, $object->errors, 'errors');
        }
    }
}

if ($action == 'update' && !$cancel) {
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
            header("Location: ".DOL_URL_ROOT.'/custom/equipmentmanager/equipment_view.php?id='.$object->id);
            exit;
        } else {
            setEventMessages($object->error, $object->errors, 'errors');
        }
    }
}

if ($cancel) {
    if ($object->id > 0) {
        header("Location: ".DOL_URL_ROOT.'/custom/equipmentmanager/equipment_view.php?id='.$object->id);
    } else {
        header("Location: ".DOL_URL_ROOT.'/custom/equipmentmanager/equipment_list.php');
    }
    exit;
}

/*
 * View
 */

$title = $object->id > 0 ? $langs->trans("ModifyEquipment") : $langs->trans("NewEquipment");
llxHeader('', $title, '');

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
            // Clear and show loading
            addressSelect.html("<option value=\'\'>Lädt...</option>");
            
            // Load addresses
            jQuery.ajax({
                url: "'.DOL_URL_ROOT.'/core/ajax/contacts.php",
                data: {
                    action: "fetch",
                    htmlname: "fk_address",
                    socid: socid
                },
                type: "GET",
                success: function(data) {
                    if (data) {
                        addressSelect.html(data);
                    } else {
                        addressSelect.html("<option value=\'\'>---</option>");
                    }
                },
                error: function() {
                    addressSelect.html("<option value=\'\'>Fehler beim Laden</option>");
                }
            });
        } else {
            addressSelect.html("<option value=\'\'>---</option>");
        }
    });
});
</script>';

// ============================================================================
// FORM (CREATE & EDIT)
// ============================================================================

$action_form = $object->id > 0 ? 'update' : 'add';

print load_fiche_titre($title, '', 'object_generic');

print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="'.$action_form.'">';
if ($object->id > 0) {
    print '<input type="hidden" name="id" value="'.$object->id.'">';
}

print dol_get_fiche_head();

print '<table class="border centpercent tableforfieldcreate">'."\n";

// Equipment Number Mode
print '<tr><td class="fieldrequired titlefieldcreate">'.$langs->trans("EquipmentNumberMode").'</td><td>';
$current_mode = $object->equipment_number_mode ? $object->equipment_number_mode : 'auto';
print '<select name="equipment_number_mode" id="equipment_number_mode" class="flat" onchange="toggleEquipmentNumberMode()" required>';
print '<option value="auto"'.($current_mode == 'auto' ? ' selected' : '').'>'.$langs->trans('EquipmentNumberAuto').'</option>';
print '<option value="manual"'.($current_mode == 'manual' ? ' selected' : '').'>'.$langs->trans('EquipmentNumberManual').'</option>';
print '</select>';
print '</td></tr>';

// Equipment Number (shown only when manual)
$display = ($current_mode == 'manual') ? 'table-row' : 'none';
print '<tr id="equipment_number_field" style="display:'.$display.';">'; 
print '<td class="fieldrequired">'.$langs->trans("EquipmentNumber").'</td><td>';
print '<input type="text" name="equipment_number" id="equipment_number" size="30" placeholder="A000123" value="'.$object->equipment_number.'"'.($current_mode == 'manual' ? ' required' : '').'>';
print '</td></tr>';

// Label
print '<tr><td class="fieldrequired">'.$langs->trans("Label").'</td><td>';
print '<input type="text" name="label" size="30" placeholder="'.$langs->trans('Label').'" value="'.dol_escape_htmltag($object->label).'" required>';
print '</td></tr>';

// Type
print '<tr><td class="fieldrequired">'.$langs->trans("Type").'</td><td>';
print '<select name="equipment_type" class="flat minwidth200" required>';
if (!$object->id) print '<option value=""></option>';
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
print '<input type="text" name="manufacturer" size="30" placeholder="'.$langs->trans('Manufacturer').'" value="'.dol_escape_htmltag($object->manufacturer).'">';
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
    print '<select name="fk_address" id="fk_address_select" class="flat minwidth300">';
    print '<option value="">---</option>';
    print '</select>';
    print ' <span class="opacitymedium">'.$langs->trans("SelectThirdPartyFirst").'</span>';
}
print '</td></tr>';

// Location / Note
print '<tr><td class="tdtop">'.$langs->trans("LocationNote").'</td><td>';
print '<textarea name="location_note" rows="3" class="flat centpercent" placeholder="'.$langs->trans('LocationNote').'">'.dol_escape_htmltag($object->location_note).'</textarea>';
print '</td></tr>';

// Serial Number
print '<tr><td>'.$langs->trans("SerialNumber").'</td><td>';
print '<input type="text" name="serial_number" size="30" placeholder="SN12938123" value="'.dol_escape_htmltag($object->serial_number).'">';
print '</td></tr>';

// Installation Date
print '<tr><td>'.$langs->trans("InstallationDate").'</td><td>';
print $form->selectDate($object->installation_date, 'installation_date', 0, 0, 1, '', 1, 0);
print '</td></tr>';

// Status
print '<tr><td>'.$langs->trans("MaintenanceContract").'</td><td>';
print '<select name="status" class="flat">';
$status_value = isset($object->status) ? $object->status : 1;
print '<option value="1"'.($status_value == 1 ? ' selected' : '').'>'.$langs->trans('ActiveContract').'</option>';
print '<option value="0"'.($status_value == 0 ? ' selected' : '').'>'.$langs->trans('NoContract').'</option>';
print '</select>';
print '</td></tr>';

print '</table>';

print dol_get_fiche_end();

print '<div class="center">';
print '<input type="submit" class="button button-save" name="save" value="'.$langs->trans($object->id > 0 ? "Save" : "Create").'">';
print ' &nbsp; ';
print '<input type="submit" class="button button-cancel" name="cancel" value="'.$langs->trans("Cancel").'">';
print '</div>';

print '</form>';

llxFooter();
$db->close();