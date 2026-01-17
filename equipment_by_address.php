<?php
/* Copyright (C) 2024-2025 Equipment Manager
 * Equipment Overview by Object Address with Bulk Edit
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

$langs->loadLangs(array("equipmentmanager@equipmentmanager", "companies"));

if (!$user->rights->equipmentmanager->equipment->read) {
    accessforbidden();
}

$action = GETPOST('action', 'aZ09');
$search_company = GETPOST('search_company', 'alpha');
$search_address = GETPOST('search_address', 'alpha');
$massaction = GETPOST('massaction', 'alpha');
$toselect = GETPOST('toselect', 'array');
$new_maintenance_month = GETPOST('new_maintenance_month', 'int');
$new_planned_duration = GETPOST('new_planned_duration', 'int');
$new_fk_contract = GETPOST('new_fk_contract', 'int');

$form = new Form($db);
$formcompany = new FormCompany($db);

// Get all addresses that have equipment for dropdown
$address_options = array();
$sql_addr = "SELECT DISTINCT sp.rowid, CONCAT(sp.lastname, ' ', sp.firstname) as label, sp.address, sp.zip, sp.town, s.nom as company_name";
$sql_addr .= " FROM ".MAIN_DB_PREFIX."socpeople as sp";
$sql_addr .= " INNER JOIN ".MAIN_DB_PREFIX."equipmentmanager_equipment as e ON e.fk_address = sp.rowid";
$sql_addr .= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON sp.fk_soc = s.rowid";
$sql_addr .= " WHERE e.entity IN (".getEntity('equipmentmanager').")";
$sql_addr .= " ORDER BY sp.town, sp.lastname, sp.firstname";
$resql_addr = $db->query($sql_addr);
if ($resql_addr) {
    while ($obj_addr = $db->fetch_object($resql_addr)) {
        $addr_label = $obj_addr->label;
        if ($obj_addr->town) $addr_label .= ' - '.$obj_addr->town;
        if ($obj_addr->company_name) $addr_label .= ' ('.$obj_addr->company_name.')';
        $address_options[$obj_addr->rowid] = $addr_label;
    }
}

// Handle bulk actions
if ($massaction == 'update_maintenance_month' && !empty($toselect) && $new_maintenance_month >= 0) {
    if (!$user->rights->equipmentmanager->equipment->write) {
        accessforbidden();
    }

    $error = 0;
    $db->begin();

    foreach ($toselect as $equipment_id) {
        $sql = "UPDATE ".MAIN_DB_PREFIX."equipmentmanager_equipment SET";
        $sql .= " maintenance_month = ".($new_maintenance_month > 0 ? (int)$new_maintenance_month : 'NULL').",";
        $sql .= " fk_user_modif = ".$user->id;
        $sql .= " WHERE rowid = ".(int)$equipment_id;
        $sql .= " AND entity IN (".getEntity('equipmentmanager').")";

        $resql = $db->query($sql);
        if (!$resql) {
            $error++;
            break;
        }
    }

    if (!$error) {
        $db->commit();
        setEventMessages($langs->trans('MaintenanceMonthUpdated', count($toselect)), null, 'mesgs');
    } else {
        $db->rollback();
        setEventMessages($langs->trans('Error'), null, 'errors');
    }
}

// Bulk update planned duration
if ($massaction == 'update_planned_duration' && !empty($toselect)) {
    if (!$user->rights->equipmentmanager->equipment->write) {
        accessforbidden();
    }

    $error = 0;
    $db->begin();

    foreach ($toselect as $equipment_id) {
        $sql = "UPDATE ".MAIN_DB_PREFIX."equipmentmanager_equipment SET";
        $sql .= " planned_duration = ".($new_planned_duration > 0 ? (int)$new_planned_duration : 'NULL').",";
        $sql .= " fk_user_modif = ".$user->id;
        $sql .= " WHERE rowid = ".(int)$equipment_id;
        $sql .= " AND entity IN (".getEntity('equipmentmanager').")";

        $resql = $db->query($sql);
        if (!$resql) {
            $error++;
            break;
        }
    }

    if (!$error) {
        $db->commit();
        setEventMessages($langs->trans('Processed').': '.count($toselect).' '.$langs->trans('Equipment'), null, 'mesgs');
    } else {
        $db->rollback();
        setEventMessages($langs->trans('Error'), null, 'errors');
    }
}

// Bulk update contract
if ($massaction == 'update_contract' && !empty($toselect)) {
    if (!$user->rights->equipmentmanager->equipment->write) {
        accessforbidden();
    }

    $error = 0;
    $db->begin();

    foreach ($toselect as $equipment_id) {
        $sql = "UPDATE ".MAIN_DB_PREFIX."equipmentmanager_equipment SET";
        $sql .= " fk_contract = ".($new_fk_contract > 0 ? (int)$new_fk_contract : 'NULL').",";
        $sql .= " fk_user_modif = ".$user->id;
        $sql .= " WHERE rowid = ".(int)$equipment_id;
        $sql .= " AND entity IN (".getEntity('equipmentmanager').")";

        $resql = $db->query($sql);
        if (!$resql) {
            $error++;
            break;
        }
    }

    if (!$error) {
        $db->commit();
        setEventMessages($langs->trans('Processed').': '.count($toselect).' '.$langs->trans('Equipment'), null, 'mesgs');
    } else {
        $db->rollback();
        setEventMessages($langs->trans('Error'), null, 'errors');
    }
}

$title = $langs->trans("EquipmentByAddress");
$help_url = '';

llxHeader('', $title, $help_url);

print load_fiche_titre($title, '', 'object_generic');

// Search form
print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'" name="searchform">';
print '<input type="hidden" name="token" value="'.newToken().'">';

print '<div class="fichecenter">';
print '<div class="underbanner clearboth"></div>';
print '<table class="border centpercent">';

// Company filter
print '<tr><td style="width: 25%">'.$langs->trans("ThirdParty").'</td><td>';
print $form->select_company($search_company, 'search_company', '', 'SelectThirdParty', 0, 0, null, 0, 'minwidth300');
print '</td></tr>';

// Address filter
print '<tr><td>'.$langs->trans("ObjectAddress").'</td><td>';
print '<select name="search_address" id="search_address" class="flat minwidth300">';
print '<option value="">'.$langs->trans("SelectAddress").'</option>';
foreach ($address_options as $addr_id => $addr_label) {
    $selected = ($search_address == $addr_id) ? ' selected' : '';
    print '<option value="'.$addr_id.'"'.$selected.'>'.dol_escape_htmltag($addr_label).'</option>';
}
print '</select>';
print '</td></tr>';

// Search button
print '<tr><td></td><td>';
print '<input type="submit" class="button" value="'.$langs->trans('Search').'">';
if ($search_company || $search_address) {
    print ' <a href="'.$_SERVER["PHP_SELF"].'" class="button">'.$langs->trans('Reset').'</a>';
}
print '</td></tr>';

print '</table>';
print '</div>';

print '</form>';

// JavaScript to clear other field when one is selected
print '<script>
document.getElementById("search_company").addEventListener("change", function() {
    if (this.value) document.getElementById("search_address").value = "";
});
document.getElementById("search_address").addEventListener("change", function() {
    if (this.value) document.getElementById("search_company").value = "";
});
</script>';

print '<br>';

// Month labels
$month_labels = array(
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

// Get data grouped by address
if ($search_company > 0 || $search_address > 0) {
    $sql = "SELECT";
    $sql .= " t.rowid,";
    $sql .= " t.equipment_number,";
    $sql .= " t.equipment_type,";
    $sql .= " t.label,";
    $sql .= " t.manufacturer,";
    $sql .= " t.status,";
    $sql .= " t.maintenance_month,";
    $sql .= " t.planned_duration,";
    $sql .= " t.fk_contract,";
    $sql .= " t.fk_soc,";
    $sql .= " t.fk_address,";
    $sql .= " COALESCE(t.planned_duration, et.default_duration, 0) as effective_duration,";
    $sql .= " s.nom as company_name,";
    $sql .= " CONCAT(sp.lastname, ' ', sp.firstname) as address_label,";
    $sql .= " sp.address as address_street,";
    $sql .= " sp.zip as address_zip,";
    $sql .= " sp.town as address_town,";
    $sql .= " c.ref as contract_ref";
    $sql .= " FROM ".MAIN_DB_PREFIX."equipmentmanager_equipment as t";
    $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON t.fk_soc = s.rowid";
    $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."socpeople as sp ON t.fk_address = sp.rowid";
    $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."equipmentmanager_equipment_types as et ON t.equipment_type = et.code";
    $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."contrat as c ON t.fk_contract = c.rowid";
    $sql .= " WHERE t.entity IN (".getEntity('equipmentmanager').")";
    if ($search_company > 0) {
        $sql .= " AND t.fk_soc = ".(int)$search_company;
    }
    if ($search_address > 0) {
        $sql .= " AND t.fk_address = ".(int)$search_address;
    }
    $sql .= " ORDER BY sp.town, sp.lastname, sp.firstname, t.equipment_number";

    $resql = $db->query($sql);

    if ($resql) {
        $num = $db->num_rows($resql);

        if ($num > 0) {
            // Group equipment by address
            $grouped = array();
            $no_address = array();

            while ($obj = $db->fetch_object($resql)) {
                if ($obj->fk_address > 0) {
                    $address_key = $obj->fk_address;
                    if (!isset($grouped[$address_key])) {
                        $grouped[$address_key] = array(
                            'label' => $obj->address_label,
                            'street' => $obj->address_street,
                            'zip' => $obj->address_zip,
                            'town' => $obj->address_town,
                            'equipment' => array()
                        );
                    }
                    $grouped[$address_key]['equipment'][] = $obj;
                } else {
                    $no_address[] = $obj;
                }
            }

            // Type labels (dynamic from database)
            $type_labels = Equipment::getEquipmentTypesTranslated($db, $langs);

            // Start bulk action form
            print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'" name="bulkform">';
            print '<input type="hidden" name="token" value="'.newToken().'">';
            print '<input type="hidden" name="search_company" value="'.$search_company.'">';
            print '<input type="hidden" name="search_address" value="'.$search_address.'">';
            print '<input type="hidden" name="massaction" value="">';

            // Get contracts for bulk assignment (for selected company or from address)
            $bulk_contracts = array();
            $bulk_socid = $search_company;

            // If searching by address and no company selected, get company from address
            if (empty($bulk_socid) && $search_address > 0) {
                $sql_soc = "SELECT fk_soc FROM ".MAIN_DB_PREFIX."socpeople WHERE rowid = ".(int)$search_address;
                $res_soc = $db->query($sql_soc);
                if ($res_soc && $db->num_rows($res_soc) > 0) {
                    $obj_soc = $db->fetch_object($res_soc);
                    $bulk_socid = $obj_soc->fk_soc;
                }
            }

            if ($bulk_socid > 0) {
                $sql_c = "SELECT c.rowid, c.ref, c.ref_customer FROM ".MAIN_DB_PREFIX."contrat as c";
                $sql_c .= " WHERE c.fk_soc = ".(int)$bulk_socid;
                $sql_c .= " AND c.statut > 0";
                $sql_c .= " ORDER BY c.ref DESC";
                $res_c = $db->query($sql_c);
                if ($res_c) {
                    while ($obj_c = $db->fetch_object($res_c)) {
                        $clabel = $obj_c->ref;
                        if ($obj_c->ref_customer) $clabel .= ' ('.$obj_c->ref_customer.')';
                        $bulk_contracts[$obj_c->rowid] = $clabel;
                    }
                }
            }

            // Bulk action bar
            if ($user->rights->equipmentmanager->equipment->write) {
                print '<div class="div-table-responsive-no-min" style="margin-bottom: 15px;">';
                print '<div class="liste_titre" style="padding: 10px; border-radius: 4px;">';

                // Row 1: Selection buttons
                print '<div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap; margin-bottom: 10px;">';
                print '<span>';
                print '<a href="#" onclick="selectAll(); return false;" class="button smallpaddingimp">'.$langs->trans('SelectAll').'</a> ';
                print '<a href="#" onclick="selectNone(); return false;" class="button smallpaddingimp">'.$langs->trans('SelectNone').'</a>';
                print '</span>';
                print '<span id="selected_count" class="opacitymedium"></span>';
                print '</div>';

                // Row 2: Bulk actions
                print '<div style="display: flex; align-items: center; gap: 20px; flex-wrap: wrap;">';

                // Maintenance month
                print '<span style="display: flex; align-items: center; gap: 5px;">';
                print '<label><strong>'.$langs->trans('MaintenanceMonth').':</strong></label>';
                print '<select name="new_maintenance_month" id="new_maintenance_month" class="flat minwidth100">';
                print '<option value="0">-- '.$langs->trans('NoMaintenance').' --</option>';
                for ($m = 1; $m <= 12; $m++) {
                    print '<option value="'.$m.'">'.$month_labels[$m].'</option>';
                }
                print '</select>';
                print '<button type="button" onclick="applyBulkAction(\'update_maintenance_month\');" class="button smallpaddingimp">'.$langs->trans('Apply').'</button>';
                print '</span>';

                print '<span style="border-left: 1px solid #ccc; height: 25px;"></span>';

                // Planned duration
                print '<span style="display: flex; align-items: center; gap: 5px;">';
                print '<label><strong>'.$langs->trans('PlannedDuration').':</strong></label>';
                print '<input type="number" name="new_planned_duration" id="new_planned_duration" class="flat width50" min="0" step="5" placeholder="min">';
                print '<button type="button" onclick="applyBulkAction(\'update_planned_duration\');" class="button smallpaddingimp">'.$langs->trans('Apply').'</button>';
                print '</span>';

                print '<span style="border-left: 1px solid #ccc; height: 25px;"></span>';

                // Contract
                print '<span style="display: flex; align-items: center; gap: 5px;">';
                print '<label><strong>'.$langs->trans('Contract').':</strong></label>';
                print '<select name="new_fk_contract" id="new_fk_contract" class="flat minwidth150">';
                print '<option value="0">-- '.$langs->trans('NoContractLinked').' --</option>';
                foreach ($bulk_contracts as $cid => $clabel) {
                    print '<option value="'.$cid.'">'.dol_escape_htmltag($clabel).'</option>';
                }
                print '</select>';
                print '<button type="button" onclick="applyBulkAction(\'update_contract\');" class="button smallpaddingimp">'.$langs->trans('Apply').'</button>';
                print '</span>';

                print '</div>';
                print '</div>';
                print '</div>';
            }

            // Display grouped equipment
            foreach ($grouped as $address_key => $address_data) {
                print '<div class="div-table-responsive-no-min" style="margin-bottom: 30px;">';
                print '<table class="noborder centpercent">';

                // Address header
                print '<tr class="liste_titre">';
                print '<th colspan="7">';
                print '<span class="fa fa-map-marker paddingright"></span>';
                print '<strong>'.dol_escape_htmltag($address_data['label']).'</strong>';
                if ($address_data['street'] || $address_data['town']) {
                    print '<br><span class="opacitymedium" style="font-weight: normal;">';
                    if ($address_data['street']) print dol_escape_htmltag($address_data['street']).', ';
                    if ($address_data['zip']) print dol_escape_htmltag($address_data['zip']).' ';
                    if ($address_data['town']) print dol_escape_htmltag($address_data['town']);
                    print '</span>';
                }
                print ' <span class="badge badge-status4" style="margin-left: 10px;">'.count($address_data['equipment']).' '.$langs->trans('Equipment').'</span>';

                // Select all for this address
                if ($user->rights->equipmentmanager->equipment->write) {
                    print ' <a href="#" onclick="selectAddress('.$address_key.'); return false;" class="badge badge-status0" style="margin-left: 5px; cursor: pointer;">'.$langs->trans('SelectAll').'</a>';
                }
                print '</th>';
                print '</tr>';

                // Equipment list headers
                print '<tr class="liste_titre">';
                if ($user->rights->equipmentmanager->equipment->write) {
                    print '<th class="center" style="width: 30px;"><input type="checkbox" class="address-checkbox" data-address="'.$address_key.'" onclick="toggleAddress('.$address_key.', this.checked);"></th>';
                }
                print '<th>'.$langs->trans('EquipmentNumber').'</th>';
                print '<th>'.$langs->trans('Type').'</th>';
                print '<th>'.$langs->trans('Label').'</th>';
                print '<th class="center">'.$langs->trans('MaintenanceMonth').'</th>';
                print '<th class="center">'.$langs->trans('PlannedDuration').'</th>';
                print '<th>'.$langs->trans('Contract').'</th>';
                print '</tr>';

                // Equipment items
                foreach ($address_data['equipment'] as $equip) {
                    print '<tr class="oddeven">';

                    // Checkbox
                    if ($user->rights->equipmentmanager->equipment->write) {
                        print '<td class="center">';
                        print '<input type="checkbox" name="toselect[]" value="'.$equip->rowid.'" class="equipment-checkbox address-'.$address_key.'" onchange="updateSelectedCount();">';
                        print '</td>';
                    }

                    // Equipment Number
                    print '<td>';
                    print '<a href="'.DOL_URL_ROOT.'/custom/equipmentmanager/equipment_view.php?id='.$equip->rowid.'">';
                    print img_object('', 'generic', 'class="pictofixedwidth"');
                    print '<strong>'.$equip->equipment_number.'</strong>';
                    print '</a>';
                    print '</td>';

                    // Type
                    print '<td>';
                    if (isset($type_labels[$equip->equipment_type])) {
                        print $type_labels[$equip->equipment_type];
                    } else {
                        print dol_escape_htmltag($equip->equipment_type);
                    }
                    print '</td>';

                    // Label
                    print '<td>'.dol_escape_htmltag($equip->label).'</td>';

                    // Maintenance Month
                    print '<td class="center">';
                    if ($equip->maintenance_month > 0 && isset($month_labels[$equip->maintenance_month])) {
                        print '<span class="badge badge-status1">'.$month_labels[$equip->maintenance_month].'</span>';
                    } else {
                        print '<span class="opacitymedium">-</span>';
                    }
                    print '</td>';

                    // Planned Duration
                    print '<td class="center">';
                    if ($equip->effective_duration > 0) {
                        if ($equip->effective_duration >= 60) {
                            $hours = floor($equip->effective_duration / 60);
                            $mins = $equip->effective_duration % 60;
                            print $hours.'h'.($mins > 0 ? ' '.$mins.'min' : '');
                        } else {
                            print $equip->effective_duration.' min';
                        }
                        if (empty($equip->planned_duration)) {
                            print ' <span class="opacitymedium">(Std)</span>';
                        }
                    } else {
                        print '<span class="opacitymedium">-</span>';
                    }
                    print '</td>';

                    // Contract
                    print '<td>';
                    if ($equip->contract_ref) {
                        print '<a href="'.DOL_URL_ROOT.'/contrat/card.php?id='.$equip->fk_contract.'">';
                        print dol_escape_htmltag($equip->contract_ref);
                        print '</a>';
                    } else {
                        print '<span class="opacitymedium">-</span>';
                    }
                    print '</td>';

                    print '</tr>';
                }

                print '</table>';
                print '</div>';
            }

            // Equipment without address
            if (count($no_address) > 0) {
                print '<div class="div-table-responsive-no-min" style="margin-bottom: 30px;">';
                print '<table class="noborder centpercent">';

                print '<tr class="liste_titre">';
                print '<th colspan="7">';
                print '<span class="fa fa-exclamation-triangle paddingright warning"></span>';
                print '<strong>'.$langs->trans('EquipmentWithoutAddress').'</strong>';
                print ' <span class="badge badge-status1" style="margin-left: 10px;">'.count($no_address).' '.$langs->trans('Equipment').'</span>';
                if ($user->rights->equipmentmanager->equipment->write) {
                    print ' <a href="#" onclick="selectAddress(0); return false;" class="badge badge-status0" style="margin-left: 5px; cursor: pointer;">'.$langs->trans('SelectAll').'</a>';
                }
                print '</th>';
                print '</tr>';

                print '<tr class="liste_titre">';
                if ($user->rights->equipmentmanager->equipment->write) {
                    print '<th class="center" style="width: 30px;"><input type="checkbox" class="address-checkbox" data-address="0" onclick="toggleAddress(0, this.checked);"></th>';
                }
                print '<th>'.$langs->trans('EquipmentNumber').'</th>';
                print '<th>'.$langs->trans('Type').'</th>';
                print '<th>'.$langs->trans('Label').'</th>';
                print '<th class="center">'.$langs->trans('MaintenanceMonth').'</th>';
                print '<th class="center">'.$langs->trans('PlannedDuration').'</th>';
                print '<th>'.$langs->trans('Contract').'</th>';
                print '</tr>';

                foreach ($no_address as $equip) {
                    print '<tr class="oddeven">';

                    // Checkbox
                    if ($user->rights->equipmentmanager->equipment->write) {
                        print '<td class="center">';
                        print '<input type="checkbox" name="toselect[]" value="'.$equip->rowid.'" class="equipment-checkbox address-0" onchange="updateSelectedCount();">';
                        print '</td>';
                    }

                    print '<td>';
                    print '<a href="'.DOL_URL_ROOT.'/custom/equipmentmanager/equipment_view.php?id='.$equip->rowid.'">';
                    print img_object('', 'generic', 'class="pictofixedwidth"');
                    print '<strong>'.$equip->equipment_number.'</strong>';
                    print '</a>';
                    print '</td>';

                    print '<td>';
                    if (isset($type_labels[$equip->equipment_type])) {
                        print $type_labels[$equip->equipment_type];
                    } else {
                        print dol_escape_htmltag($equip->equipment_type);
                    }
                    print '</td>';

                    print '<td>'.dol_escape_htmltag($equip->label).'</td>';

                    // Maintenance Month
                    print '<td class="center">';
                    if ($equip->maintenance_month > 0 && isset($month_labels[$equip->maintenance_month])) {
                        print '<span class="badge badge-status1">'.$month_labels[$equip->maintenance_month].'</span>';
                    } else {
                        print '<span class="opacitymedium">-</span>';
                    }
                    print '</td>';

                    // Planned Duration
                    print '<td class="center">';
                    if ($equip->effective_duration > 0) {
                        if ($equip->effective_duration >= 60) {
                            $hours = floor($equip->effective_duration / 60);
                            $mins = $equip->effective_duration % 60;
                            print $hours.'h'.($mins > 0 ? ' '.$mins.'min' : '');
                        } else {
                            print $equip->effective_duration.' min';
                        }
                        if (empty($equip->planned_duration)) {
                            print ' <span class="opacitymedium">(Std)</span>';
                        }
                    } else {
                        print '<span class="opacitymedium">-</span>';
                    }
                    print '</td>';

                    // Contract
                    print '<td>';
                    if ($equip->contract_ref) {
                        print '<a href="'.DOL_URL_ROOT.'/contrat/card.php?id='.$equip->fk_contract.'">';
                        print dol_escape_htmltag($equip->contract_ref);
                        print '</a>';
                    } else {
                        print '<span class="opacitymedium">-</span>';
                    }
                    print '</td>';

                    print '</tr>';
                }

                print '</table>';
                print '</div>';
            }

            print '</form>';

            // Summary
            print '<div class="info" style="margin-top: 20px;">';
            print '<span class="fa fa-info-circle"></span> ';
            print '<strong>'.$langs->trans('Total').':</strong> '.$num.' '.$langs->trans('Equipment');
            print ' | <strong>'.$langs->trans('Addresses').':</strong> '.count($grouped);
            if (count($no_address) > 0) {
                print ' | <strong>'.$langs->trans('WithoutAddress').':</strong> '.count($no_address);
            }
            print '</div>';

            // JavaScript for bulk selection
            print '<script>
            function selectAll() {
                document.querySelectorAll(".equipment-checkbox").forEach(function(cb) {
                    cb.checked = true;
                });
                document.querySelectorAll(".address-checkbox").forEach(function(cb) {
                    cb.checked = true;
                });
                updateSelectedCount();
            }

            function selectNone() {
                document.querySelectorAll(".equipment-checkbox").forEach(function(cb) {
                    cb.checked = false;
                });
                document.querySelectorAll(".address-checkbox").forEach(function(cb) {
                    cb.checked = false;
                });
                updateSelectedCount();
            }

            function selectAddress(addressId) {
                document.querySelectorAll(".address-" + addressId).forEach(function(cb) {
                    cb.checked = true;
                });
                updateSelectedCount();
            }

            function toggleAddress(addressId, checked) {
                document.querySelectorAll(".address-" + addressId).forEach(function(cb) {
                    cb.checked = checked;
                });
                updateSelectedCount();
            }

            function updateSelectedCount() {
                var count = document.querySelectorAll(".equipment-checkbox:checked").length;
                var countEl = document.getElementById("selected_count");
                if (countEl) {
                    countEl.innerHTML = count + " '.html_entity_decode($langs->trans('Selected')).'";
                }
            }

            function applyBulkAction(actionType) {
                var selected = document.querySelectorAll(".equipment-checkbox:checked");
                if (selected.length == 0) {
                    alert("'.html_entity_decode($langs->trans('PleaseSelectAtLeastOne')).'");
                    return;
                }

                var confirmMsg = "";
                if (actionType == "update_maintenance_month") {
                    var monthText = document.getElementById("new_maintenance_month").options[document.getElementById("new_maintenance_month").selectedIndex].text;
                    confirmMsg = "'.html_entity_decode($langs->trans('MaintenanceMonth')).': " + monthText;
                } else if (actionType == "update_planned_duration") {
                    var duration = document.getElementById("new_planned_duration").value || "0";
                    confirmMsg = "'.html_entity_decode($langs->trans('PlannedDuration')).': " + duration + " min";
                } else if (actionType == "update_contract") {
                    var contractText = document.getElementById("new_fk_contract").options[document.getElementById("new_fk_contract").selectedIndex].text;
                    confirmMsg = "'.html_entity_decode($langs->trans('Contract')).': " + contractText;
                }

                if (!confirm(selected.length + " '.html_entity_decode($langs->trans('Equipment')).' → " + confirmMsg + "\n\n'.html_entity_decode($langs->trans('ConfirmBulkLink')).'")) {
                    return;
                }

                document.querySelector("input[name=massaction]").value = actionType;
                document.forms["bulkform"].submit();
            }

            // Initial count
            updateSelectedCount();
            </script>';

        } else {
            print info_admin($langs->trans('NoEquipmentForThisThirdParty'));
        }

        $db->free($resql);
    } else {
        dol_print_error($db);
    }
} else {
    print info_admin($langs->trans('PleaseSelectThirdParty'));
}

llxFooter();
$db->close();
