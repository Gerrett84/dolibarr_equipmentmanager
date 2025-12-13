<?php
/* Copyright (C) 2024 Equipment Manager
 *
 * Equipment-Tab auf Intervention Card v1.4
 * Zeigt: 1) Equipment-Liste des Kunden zur Referenz
 *        2) Verknüpfte Equipments mit Typ (Wartung/Service)
 */

// Load Dolibarr environment
$res = 0;
if (!$res && file_exists("../../main.inc.php")) {
    $res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
    $res = @include "../../../main.inc.php";
}
if (!$res) {
    die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/fichinter/class/fichinter.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/fichinter.lib.php';
dol_include_once('/equipmentmanager/class/equipment.class.php');

$langs->loadLangs(array('interventions', 'equipmentmanager@equipmentmanager'));

$id = GETPOST('id', 'int');
$ref = GETPOST('ref', 'alpha');
$action = GETPOST('action', 'aZ09');
$equipment_id = GETPOST('equipment_id', 'int');
$link_type = GETPOST('link_type', 'alpha'); // 'maintenance' or 'service'

$object = new Fichinter($db);

if ($id > 0 || !empty($ref)) {
    $object->fetch($id, $ref);
}

$permissiontoread = $user->hasRight('ficheinter', 'lire');
$permissiontoadd = $user->hasRight('ficheinter', 'creer');

if (!$permissiontoread) {
    accessforbidden();
}

/*
 * Actions
 */

// Link equipment with type
if ($action == 'link' && $permissiontoadd && $equipment_id > 0 && in_array($link_type, array('maintenance', 'service'))) {
    $sql = "INSERT INTO ".MAIN_DB_PREFIX."equipmentmanager_intervention_link";
    $sql .= " (fk_intervention, fk_equipment, link_type, date_creation, fk_user_creat)";
    $sql .= " VALUES (".(int)$object->id.", ".(int)$equipment_id.", '".$db->escape($link_type)."', ";
    $sql .= "'".$db->idate(dol_now())."', ".$user->id.")";
    
    if ($db->query($sql)) {
        $msg = ($link_type == 'maintenance') ? 'EquipmentLinkedMaintenance' : 'EquipmentLinkedService';
        setEventMessages($langs->trans($msg), null, 'mesgs');
    } else {
        if ($db->lasterrno() == 1062) { // Duplicate
            setEventMessages($langs->trans('EquipmentAlreadyLinked'), null, 'warnings');
        } else {
            setEventMessages($db->lasterror(), null, 'errors');
        }
    }
    
    header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
    exit;
}

// Unlink equipment
if ($action == 'unlink' && $permissiontoadd && $equipment_id > 0) {
    $sql = "DELETE FROM ".MAIN_DB_PREFIX."equipmentmanager_intervention_link";
    $sql .= " WHERE fk_intervention = ".(int)$object->id;
    $sql .= " AND fk_equipment = ".(int)$equipment_id;
    
    if ($db->query($sql)) {
        setEventMessages($langs->trans('EquipmentUnlinked'), null, 'mesgs');
    }
    
    header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
    exit;
}

/*
 * View
 */

$form = new Form($db);

llxHeader('', $langs->trans('Intervention'));

if ($object->id > 0) {
    $head = fichinter_prepare_head($object);
    
    print dol_get_fiche_head($head, 'equipmentmanager_equipment', $langs->trans('Intervention'), -1, 'intervention');
    
    // Banner
    $linkback = '<a href="'.DOL_URL_ROOT.'/fichinter/list.php?restore_lastsearch_values=1">'.$langs->trans("BackToList").'</a>';
    
    $morehtmlref = '<div class="refidno">';
    if ($object->ref_client) {
        $morehtmlref .= $langs->trans('RefCustomer').': '.$object->ref_client;
    }
    $morehtmlref .= '</div>';
    
    dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref);
    
    print '<div class="fichecenter">';
    print '<div class="underbanner clearboth"></div>';
    print '</div>';
    
    print dol_get_fiche_end();
    
    // Get linked equipment with types
    $sql = "SELECT l.fk_equipment, l.link_type, l.date_creation, l.note";
    $sql .= " FROM ".MAIN_DB_PREFIX."equipmentmanager_intervention_link as l";
    $sql .= " WHERE l.fk_intervention = ".(int)$object->id;
    $sql .= " ORDER BY l.link_type, l.date_creation";
    
    $resql = $db->query($sql);
    $linked_equipment = array();
    $linked_equipment_ids = array();
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $linked_equipment[$obj->fk_equipment] = $obj->link_type;
            $linked_equipment_ids[] = $obj->fk_equipment;
        }
    }
    
    print '<br>';
    
    // ========================================================================
    // Section 1: GEWARTETE EQUIPMENTS (Wartungsarbeiten)
    // ========================================================================
    print '<div class="div-table-responsive-no-min">';
    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre" style="background-color: #e8f5e9;">';
    print '<th colspan="5">';
    print '<span class="fa fa-wrench paddingright"></span>';
    print '<strong>'.$langs->trans('MaintenanceWork').'</strong>';
    print ' <span class="opacitymedium">('.$langs->trans('MaintenanceWorkDescription').')</span>';
    print '</th>';
    print '</tr>';
    
    print '<tr class="liste_titre">';
    print '<th>'.$langs->trans('EquipmentNumber').'</th>';
    print '<th>'.$langs->trans('Label').'</th>';
    print '<th>'.$langs->trans('Type').'</th>';
    print '<th>'.$langs->trans('ObjectAddress').'</th>';
    print '<th class="center" width="80">'.$langs->trans('Action').'</th>';
    print '</tr>';
    
    $has_maintenance = false;
    if (count($linked_equipment_ids) > 0) {
        foreach ($linked_equipment_ids as $eq_id) {
            if ($linked_equipment[$eq_id] != 'maintenance') continue;
            $has_maintenance = true;
            
            $equipment = new Equipment($db);
            if ($equipment->fetch($eq_id) > 0) {
                print '<tr class="oddeven">';
                
                // Equipment Number
                print '<td>';
                print '<a href="'.DOL_URL_ROOT.'/custom/equipmentmanager/equipment_card.php?id='.$equipment->id.'" target="_blank">';
                print img_object('', 'generic', 'class="pictofixedwidth"');
                print '<strong>'.$equipment->equipment_number.'</strong>';
                print '</a>';
                print '</td>';
                
                // Label
                print '<td>'.dol_escape_htmltag($equipment->label).'</td>';
                
                // Type
                print '<td>';
                $type_labels = array(
                    'door_swing' => 'Drehtürantrieb',
                    'door_sliding' => 'Schiebetürantrieb',
                    'fire_door' => 'Brandschutztür',
                    'door_closer' => 'Türschließer',
                    'hold_open' => 'Feststellanlage',
                    'rws' => 'RWS',
                    'rwa' => 'RWA',
                    'other' => 'Sonstiges'
                );
                print isset($type_labels[$equipment->equipment_type]) ? $type_labels[$equipment->equipment_type] : $equipment->equipment_type;
                print '</td>';
                
                // Address
                print '<td>';
                if ($equipment->fk_address > 0) {
                    $sql2 = "SELECT CONCAT(lastname, ' ', firstname) as name, town";
                    $sql2 .= " FROM ".MAIN_DB_PREFIX."socpeople";
                    $sql2 .= " WHERE rowid = ".(int)$equipment->fk_address;
                    $resql2 = $db->query($sql2);
                    if ($resql2 && $db->num_rows($resql2)) {
                        $addr = $db->fetch_object($resql2);
                        print dol_escape_htmltag($addr->name);
                        if ($addr->town) print '<br><span class="opacitymedium">'.dol_escape_htmltag($addr->town).'</span>';
                    }
                } elseif ($equipment->location_note) {
                    print '<span class="opacitymedium">'.dol_trunc(dol_escape_htmltag($equipment->location_note), 50).'</span>';
                }
                print '</td>';
                
                // Actions
                print '<td class="center">';
                print '<a href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=unlink&equipment_id='.$equipment->id.'&token='.newToken().'">';
                print img_delete($langs->trans('Unlink'));
                print '</a>';
                print '</td>';
                
                print '</tr>';
            }
        }
    }
    
    if (!$has_maintenance) {
        print '<tr><td colspan="5" class="opacitymedium center">';
        print $langs->trans('NoMaintenanceEquipment').'<br>';
        print '<span class="opacitymedium">'.$langs->trans('LinkMaintenanceEquipmentFromListBelow').'</span>';
        print '</td></tr>';
    }
    
    print '</table>';
    print '</div>';
    
    print '<br>';
    
    // ========================================================================
    // Section 2: BEARBEITETE EQUIPMENTS (Service/Reparatur)
    // ========================================================================
    print '<div class="div-table-responsive-no-min">';
    print '<table class="noborder centpercent">';
    print '<tr class="liste_titel" style="background-color: #fff3e0;">';
    print '<th colspan="5">';
    print '<span class="fa fa-cog paddingright"></span>';
    print '<strong>'.$langs->trans('ServiceWork').'</strong>';
    print ' <span class="opacitymedium">('.$langs->trans('ServiceWorkDescription').')</span>';
    print '</th>';
    print '</tr>';
    
    print '<tr class="liste_titre">';
    print '<th>'.$langs->trans('EquipmentNumber').'</th>';
    print '<th>'.$langs->trans('Label').'</th>';
    print '<th>'.$langs->trans('Type').'</th>';
    print '<th>'.$langs->trans('ObjectAddress').'</th>';
    print '<th class="center" width="80">'.$langs->trans('Action').'</th>';
    print '</tr>';
    
    $has_service = false;
    if (count($linked_equipment_ids) > 0) {
        foreach ($linked_equipment_ids as $eq_id) {
            if ($linked_equipment[$eq_id] != 'service') continue;
            $has_service = true;
            
            $equipment = new Equipment($db);
            if ($equipment->fetch($eq_id) > 0) {
                print '<tr class="oddeven">';
                
                // Equipment Number
                print '<td>';
                print '<a href="'.DOL_URL_ROOT.'/custom/equipmentmanager/equipment_card.php?id='.$equipment->id.'" target="_blank">';
                print img_object('', 'generic', 'class="pictofixedwidth"');
                print '<strong>'.$equipment->equipment_number.'</strong>';
                print '</a>';
                print '</td>';
                
                // Label
                print '<td>'.dol_escape_htmltag($equipment->label).'</td>';
                
                // Type
                print '<td>';
                $type_labels = array(
                    'door_swing' => 'Drehtürantrieb',
                    'door_sliding' => 'Schiebetürantrieb',
                    'fire_door' => 'Brandschutztür',
                    'door_closer' => 'Türschließer',
                    'hold_open' => 'Feststellanlage',
                    'rws' => 'RWS',
                    'rwa' => 'RWA',
                    'other' => 'Sonstiges'
                );
                print isset($type_labels[$equipment->equipment_type]) ? $type_labels[$equipment->equipment_type] : $equipment->equipment_type;
                print '</td>';
                
                // Address
                print '<td>';
                if ($equipment->fk_address > 0) {
                    $sql2 = "SELECT CONCAT(lastname, ' ', firstname) as name, town";
                    $sql2 .= " FROM ".MAIN_DB_PREFIX."socpeople";
                    $sql2 .= " WHERE rowid = ".(int)$equipment->fk_address;
                    $resql2 = $db->query($sql2);
                    if ($resql2 && $db->num_rows($resql2)) {
                        $addr = $db->fetch_object($resql2);
                        print dol_escape_htmltag($addr->name);
                        if ($addr->town) print '<br><span class="opacitymedium">'.dol_escape_htmltag($addr->town).'</span>';
                    }
                } elseif ($equipment->location_note) {
                    print '<span class="opacitymedium">'.dol_trunc(dol_escape_htmltag($equipment->location_note), 50).'</span>';
                }
                print '</td>';
                
                // Actions
                print '<td class="center">';
                print '<a href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=unlink&equipment_id='.$equipment->id.'&token='.newToken().'">';
                print img_delete($langs->trans('Unlink'));
                print '</a>';
                print '</td>';
                
                print '</tr>';
            }
        }
    }
    
    if (!$has_service) {
        print '<tr><td colspan="5" class="opacitymedium center">';
        print $langs->trans('NoServiceEquipment').'<br>';
        print '<span class="opacitymedium">'.$langs->trans('LinkServiceEquipmentFromListBelow').'</span>';
        print '</td></tr>';
    }
    
    print '</table>';
    print '</div>';
    
    print '<br><br>';
    
    // ========================================================================
    // Section 3: VERFÜGBARE EQUIPMENTS (zum Verknüpfen)
    // ========================================================================
    if ($object->socid > 0) {
        print '<div class="div-table-responsive-no-min">';
        print '<table class="noborder centpercent">';
        print '<tr class="liste_titre">';
        print '<th colspan="6">';
        print '<span class="fa fa-list paddingright"></span>';
        print $langs->trans('AvailableEquipmentsForCustomer');
        print '</th>';
        print '</tr>';
        
        print '<tr class="liste_titre">';
        print '<th>'.$langs->trans('EquipmentNumber').'</th>';
        print '<th>'.$langs->trans('Label').'</th>';
        print '<th>'.$langs->trans('Type').'</th>';
        print '<th>'.$langs->trans('ObjectAddress').'</th>';
        print '<th class="center" width="100">'.$langs->trans('LinkAsMaintenance').'</th>';
        print '<th class="center" width="100">'.$langs->trans('LinkAsService').'</th>';
        print '</tr>';
        
        $equipments = Equipment::fetchAllBySoc($db, $object->socid);
        
        if (count($equipments) > 0) {
            foreach ($equipments as $equipment) {
                // Skip if already linked
                if (in_array($equipment->id, $linked_equipment_ids)) {
                    continue;
                }
                
                print '<tr class="oddeven">';
                
                // Equipment Number
                print '<td>';
                print '<a href="'.DOL_URL_ROOT.'/custom/equipmentmanager/equipment_card.php?id='.$equipment->id.'" target="_blank">';
                print img_object('', 'generic', 'class="pictofixedwidth"');
                print '<strong>'.$equipment->equipment_number.'</strong>';
                print '</a>';
                print '</td>';
                
                // Label
                print '<td>'.dol_escape_htmltag($equipment->label).'</td>';
                
                // Type
                print '<td>';
                $type_labels = array(
                    'door_swing' => 'Drehtürantrieb',
                    'door_sliding' => 'Schiebetürantrieb',
                    'fire_door' => 'Brandschutztür',
                    'door_closer' => 'Türschließer',
                    'hold_open' => 'Feststellanlage',
                    'rws' => 'RWS',
                    'rwa' => 'RWA',
                    'other' => 'Sonstiges'
                );
                print isset($type_labels[$equipment->equipment_type]) ? $type_labels[$equipment->equipment_type] : $equipment->equipment_type;
                print '</td>';
                
                // Address
                print '<td>';
                if ($equipment->fk_address > 0) {
                    $sql2 = "SELECT CONCAT(lastname, ' ', firstname) as name, town";
                    $sql2 .= " FROM ".MAIN_DB_PREFIX."socpeople";
                    $sql2 .= " WHERE rowid = ".(int)$equipment->fk_address;
                    $resql2 = $db->query($sql2);
                    if ($resql2 && $db->num_rows($resql2)) {
                        $addr = $db->fetch_object($resql2);
                        print dol_escape_htmltag($addr->name);
                        if ($addr->town) print '<br><span class="opacitymedium">'.dol_escape_htmltag($addr->town).'</span>';
                    }
                } elseif ($equipment->location_note) {
                    print '<span class="opacitymedium">'.dol_trunc(dol_escape_htmltag($equipment->location_note), 50).'</span>';
                }
                print '</td>';
                
                // Actions - Maintenance
                print '<td class="center">';
                print '<a class="button smallpaddingimp" style="background: #4caf50; color: white;" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=link&equipment_id='.$equipment->id.'&link_type=maintenance&token='.newToken().'">';
                print '<span class="fa fa-wrench"></span> '.$langs->trans('Maintenance');
                print '</a>';
                print '</td>';
                
                // Actions - Service
                print '<td class="center">';
                print '<a class="button smallpaddingimp" style="background: #ff9800; color: white;" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=link&equipment_id='.$equipment->id.'&link_type=service&token='.newToken().'">';
                print '<span class="fa fa-cog"></span> '.$langs->trans('Service');
                print '</a>';
                print '</td>';
                
                print '</tr>';
            }
        } else {
            print '<tr><td colspan="6" class="opacitymedium center">';
            print $langs->trans('NoEquipmentForThisThirdParty');
            print '</td></tr>';
        }
        
        print '</table>';
        print '</div>';
    } else {
        print info_admin($langs->trans('PleaseAssignThirdPartyToInterventionFirst'));
    }
}

llxFooter();
$db->close();