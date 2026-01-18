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
    $object->maintenance_month = GETPOST('maintenance_month', 'int');
    $object->planned_duration = GETPOST('planned_duration', 'int') ?: null;
    $object->fk_contract = GETPOST('fk_contract', 'int') ?: null;
    $object->maintenance_interval = GETPOST('maintenance_interval', 'alpha') ?: null;

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
    $object->maintenance_month = GETPOST('maintenance_month', 'int');
    $object->planned_duration = GETPOST('planned_duration', 'int') ?: null;
    $object->fk_contract = GETPOST('fk_contract', 'int') ?: null;
    $object->maintenance_interval = GETPOST('maintenance_interval', 'alpha') ?: null;

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
?>
<script type="text/javascript">
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

function toggleMaintenanceMonth() {
    var status = document.getElementById("status_select").value;
    var maintenanceRow = document.getElementById("maintenance_month_row");
    var intervalRow = document.getElementById("maintenance_interval_row");
    var durationRow = document.getElementById("planned_duration_row");
    var contractRow = document.getElementById("fk_contract_row");

    if (status == "1") {
        maintenanceRow.style.display = "table-row";
        intervalRow.style.display = "table-row";
        durationRow.style.display = "table-row";
        contractRow.style.display = "table-row";
    } else {
        maintenanceRow.style.display = "none";
        intervalRow.style.display = "none";
        durationRow.style.display = "none";
        contractRow.style.display = "none";
    }
}

jQuery(document).ready(function() {
    // Initial check for maintenance month visibility on page load
    toggleMaintenanceMonth();
    
    jQuery("#fk_soc_select").change(function() {
        var socid = jQuery(this).val();
        var addressSelect = jQuery("#fk_address_select");
        var contractSelect = jQuery("#fk_contract_select");

        if (socid > 0) {
            addressSelect.html("<option value=''>Lädt...</option>");
            contractSelect.html("<option value=''>Lädt...</option>");

            // Load addresses
            jQuery.ajax({
                url: "<?php echo DOL_URL_ROOT; ?>/core/ajax/contacts.php",
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
                        addressSelect.html("<option value=''>---</option>");
                    }
                },
                error: function() {
                    addressSelect.html("<option value=''>Fehler beim Laden</option>");
                }
            });

            // Load contracts
            jQuery.ajax({
                url: "<?php echo DOL_URL_ROOT; ?>/custom/equipmentmanager/ajax/get_contracts.php",
                data: {
                    socid: socid
                },
                type: "GET",
                dataType: "json",
                success: function(data) {
                    contractSelect.html("<option value=''>---</option>");
                    if (data && data.length > 0) {
                        jQuery.each(data, function(i, contract) {
                            contractSelect.append("<option value='" + contract.id + "'>" + contract.label + "</option>");
                        });
                    }
                },
                error: function() {
                    contractSelect.html("<option value=''>---</option>");
                }
            });
        } else {
            addressSelect.html("<option value=''>---</option>");
            contractSelect.html("<option value=''>---</option>");
        }
    });
});
</script>
<?php

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

// Type (dynamic from database)
print '<tr><td class="fieldrequired">'.$langs->trans("Type").'</td><td>';
print '<select name="equipment_type" class="flat minwidth200" required>';
if (!$object->id) print '<option value=""></option>';
$equipmentTypes = Equipment::getEquipmentTypes($db);
foreach ($equipmentTypes as $code => $label) {
    $selected = ($object->equipment_type == $code) ? ' selected' : '';
    print '<option value="'.dol_escape_htmltag($code).'"'.$selected.'>'.$langs->trans($label).'</option>';
}
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
$status_value = isset($object->status) ? $object->status : 1;
print '<tr><td>'.$langs->trans("MaintenanceContract").'</td><td>';
print '<select name="status" id="status_select" class="flat" onchange="toggleMaintenanceMonth()">';
print '<option value="1"'.($status_value == 1 ? ' selected' : '').'>'.$langs->trans('ActiveContract').'</option>';
print '<option value="0"'.($status_value == 0 ? ' selected' : '').'>'.$langs->trans('NoContract').'</option>';
print '</select>';
print '</td></tr>';

// Wartungsmonat (nur wenn Vertrag aktiv) - NEU in v1.5
$display_maintenance = ($status_value == 1) ? 'table-row' : 'none';
print '<tr id="maintenance_month_row" style="display:'.$display_maintenance.';">';
print '<td>'.$langs->trans("MaintenanceMonth").'</td><td>';
print '<select name="maintenance_month" id="maintenance_month_select" class="flat">';
print '<option value="">---</option>';
$months = array(
    1 => $langs->trans('January'),
    2 => $langs->trans('February'),
    3 => $langs->trans('March'),
    4 => $langs->trans('April'),
    5 => $langs->trans('May'),
    6 => $langs->trans('June'),
    7 => $langs->trans('July'),
    8 => $langs->trans('August'),
    9 => $langs->trans('September'),
    10 => $langs->trans('October'),
    11 => $langs->trans('November'),
    12 => $langs->trans('December')
);
$current_month = isset($object->maintenance_month) ? (int)$object->maintenance_month : 0;
foreach ($months as $num => $name) {
    $selected = ($current_month == $num) ? ' selected' : '';
    print '<option value="'.$num.'"'.$selected.'>'.$name.'</option>';
}
print '</select>';
print ' <span class="opacitymedium">'.$langs->trans('MaintenanceMonthHelp').'</span>';
print '</td></tr>';

// Wartungsintervall (v4.0) - nur wenn Vertrag aktiv
print '<tr id="maintenance_interval_row" style="display:'.$display_maintenance.';">';
print '<td>'.$langs->trans("MaintenanceInterval").'</td><td>';
$current_interval = isset($object->maintenance_interval) ? $object->maintenance_interval : '';
print '<select name="maintenance_interval" id="maintenance_interval_select" class="flat">';
print '<option value="">'.$langs->trans('UseTypeDefault').' ('.$langs->trans('IntervalYearly').')</option>';
print '<option value="yearly"'.($current_interval == 'yearly' ? ' selected' : '').'>'.$langs->trans('IntervalYearly').'</option>';
print '<option value="semi_annual"'.($current_interval == 'semi_annual' ? ' selected' : '').'>'.$langs->trans('IntervalSemiAnnual').'</option>';
print '</select>';
print '</td></tr>';

// Planzeit (v4.0) - nur wenn Vertrag aktiv
print '<tr id="planned_duration_row" style="display:'.$display_maintenance.';">';
print '<td>'.$langs->trans("PlannedDuration").'</td><td>';
$current_duration = isset($object->planned_duration) ? (int)$object->planned_duration : '';
print '<input type="number" name="planned_duration" id="planned_duration" class="width75" min="0" step="5" value="'.$current_duration.'" placeholder="'.$langs->trans('UseTypeDefault').'">';
print ' <span class="opacitymedium">min</span>';
print ' <span class="opacitymedium" style="margin-left: 10px;">('.$langs->trans('UseTypeDefault').' = leer lassen)</span>';
print '</td></tr>';

// Vertrag (v4.0) - nur wenn Vertrag aktiv
print '<tr id="fk_contract_row" style="display:'.$display_maintenance.';">';
print '<td>'.$langs->trans("Contract").'</td><td>';
print '<select name="fk_contract" id="fk_contract_select" class="flat minwidth300">';
print '<option value="">---</option>';
// Load contracts for this customer
if ($object->fk_soc > 0) {
    $sql_contracts = "SELECT c.rowid, c.ref, c.ref_customer, c.date_contrat";
    $sql_contracts .= " FROM ".MAIN_DB_PREFIX."contrat as c";
    $sql_contracts .= " WHERE c.fk_soc = ".(int)$object->fk_soc;
    $sql_contracts .= " AND c.statut > 0"; // Only active contracts
    $sql_contracts .= " ORDER BY c.date_contrat DESC";

    $resql_c = $db->query($sql_contracts);
    if ($resql_c) {
        while ($contract = $db->fetch_object($resql_c)) {
            $selected = ($object->fk_contract == $contract->rowid) ? ' selected' : '';
            $contract_label = $contract->ref;
            if ($contract->ref_customer) $contract_label .= ' ('.$contract->ref_customer.')';
            print '<option value="'.$contract->rowid.'"'.$selected.'>';
            print dol_escape_htmltag($contract_label);
            print '</option>';
        }
    }
}
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