<?php
/* Copyright (C) 2024 Equipment Manager
 * Equipment Overview by Object Address
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

$form = new Form($db);
$formcompany = new FormCompany($db);

$title = $langs->trans("EquipmentByAddress");
$help_url = '';

llxHeader('', $title, $help_url);

print load_fiche_titre($title, '', 'object_generic');

// Search form
print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';

print '<div class="fichecenter">';
print '<div class="underbanner clearboth"></div>';
print '<table class="border centpercent">';

// Company filter
print '<tr><td class="fieldrequired" style="width: 25%">'.$langs->trans("ThirdParty").'</td><td>';
print $form->select_company($search_company, 'search_company', '', 'SelectThirdParty', 0, 0, null, 0, 'minwidth300');
print ' <input type="submit" class="button" value="'.$langs->trans('Search').'">';
print '</td></tr>';

print '</table>';
print '</div>';

print '</form>';

print '<br>';

// Get data grouped by address
if ($search_company > 0) {
    $sql = "SELECT";
    $sql .= " t.rowid,";
    $sql .= " t.equipment_number,";
    $sql .= " t.equipment_type,";
    $sql .= " t.label,";
    $sql .= " t.manufacturer,";
    $sql .= " t.status,";
    $sql .= " t.fk_address,";
    $sql .= " s.nom as company_name,";
    $sql .= " CONCAT(sp.lastname, ' ', sp.firstname) as address_label,";
    $sql .= " sp.address as address_street,";
    $sql .= " sp.zip as address_zip,";
    $sql .= " sp.town as address_town";
    $sql .= " FROM ".MAIN_DB_PREFIX."equipmentmanager_equipment as t";
    $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON t.fk_soc = s.rowid";
    $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."socpeople as sp ON t.fk_address = sp.rowid";
    $sql .= " WHERE t.fk_soc = ".(int)$search_company;
    $sql .= " AND t.entity IN (".getEntity('equipmentmanager').")";
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
            
            // Type labels
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
            
            // Display grouped equipment
            foreach ($grouped as $address_key => $address_data) {
                print '<div class="div-table-responsive-no-min" style="margin-bottom: 30px;">';
                print '<table class="noborder centpercent">';
                
                // Address header
                print '<tr class="liste_titre" style="background-color: #e3f2fd;">';
                print '<th colspan="5">';
                print '<span class="fa fa-map-marker paddingright" style="color: #1976d2;"></span>';
                print '<strong>'.dol_escape_htmltag($address_data['label']).'</strong>';
                if ($address_data['street'] || $address_data['town']) {
                    print '<br><span class="opacitymedium" style="font-weight: normal;">';
                    if ($address_data['street']) print dol_escape_htmltag($address_data['street']).', ';
                    if ($address_data['zip']) print dol_escape_htmltag($address_data['zip']).' ';
                    if ($address_data['town']) print dol_escape_htmltag($address_data['town']);
                    print '</span>';
                }
                print ' <span class="badge" style="background: #1976d2; color: white; margin-left: 10px;">'.count($address_data['equipment']).' '.$langs->trans('Equipment').'</span>';
                print '</th>';
                print '</tr>';
                
                // Equipment list headers
                print '<tr class="liste_titre">';
                print '<th>'.$langs->trans('EquipmentNumber').'</th>';
                print '<th>'.$langs->trans('Type').'</th>';
                print '<th>'.$langs->trans('Label').'</th>';
                print '<th>'.$langs->trans('Manufacturer').'</th>';
                print '<th class="center">'.$langs->trans('MaintenanceContract').'</th>';
                print '</tr>';
                
                // Equipment items
                foreach ($address_data['equipment'] as $equip) {
                    print '<tr class="oddeven">';
                    
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
                    
                    // Manufacturer
                    print '<td>'.dol_escape_htmltag($equip->manufacturer).'</td>';
                    
                    // Status
                    print '<td class="center">';
                    if ($equip->status == 1) {
                        print '<span class="badge badge-status4 badge-status">'.$langs->trans('ActiveContract').'</span>';
                    } else {
                        print '<span class="badge badge-status8 badge-status">'.$langs->trans('NoContract').'</span>';
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
                
                print '<tr class="liste_titre" style="background-color: #fff3e0;">';
                print '<th colspan="5">';
                print '<span class="fa fa-exclamation-triangle paddingright" style="color: #f57c00;"></span>';
                print '<strong>'.$langs->trans('EquipmentWithoutAddress').'</strong>';
                print ' <span class="badge" style="background: #f57c00; color: white; margin-left: 10px;">'.count($no_address).' '.$langs->trans('Equipment').'</span>';
                print '</th>';
                print '</tr>';
                
                print '<tr class="liste_titre">';
                print '<th>'.$langs->trans('EquipmentNumber').'</th>';
                print '<th>'.$langs->trans('Type').'</th>';
                print '<th>'.$langs->trans('Label').'</th>';
                print '<th>'.$langs->trans('Manufacturer').'</th>';
                print '<th class="center">'.$langs->trans('MaintenanceContract').'</th>';
                print '</tr>';
                
                foreach ($no_address as $equip) {
                    print '<tr class="oddeven">';
                    
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
                    print '<td>'.dol_escape_htmltag($equip->manufacturer).'</td>';
                    
                    print '<td class="center">';
                    if ($equip->status == 1) {
                        print '<span class="badge badge-status4 badge-status">'.$langs->trans('ActiveContract').'</span>';
                    } else {
                        print '<span class="badge badge-status8 badge-status">'.$langs->trans('NoContract').'</span>';
                    }
                    print '</td>';
                    
                    print '</tr>';
                }
                
                print '</table>';
                print '</div>';
            }
            
            // Summary
            print '<div class="info" style="margin-top: 20px;">';
            print '<span class="fa fa-info-circle"></span> ';
            print '<strong>'.$langs->trans('Total').':</strong> '.$num.' '.$langs->trans('Equipment');
            print ' | <strong>'.$langs->trans('Addresses').':</strong> '.count($grouped);
            if (count($no_address) > 0) {
                print ' | <strong>'.$langs->trans('WithoutAddress').':</strong> '.count($no_address);
            }
            print '</div>';
            
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