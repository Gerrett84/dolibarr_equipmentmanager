<?php
/* Copyright (C) 2024 Equipment Manager
 *
 * Equipment-Tab auf Intervention Card
 * Zeigt: 1) Equipment-Liste des Kunden zur Referenz
 *        2) Verknüpfte Equipments für Tracking
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

// Link equipment
if ($action == 'link' && $permissiontoadd && $equipment_id > 0) {
    $sql = "INSERT INTO ".MAIN_DB_PREFIX."equipmentmanager_intervention_link";
    $sql .= " (fk_intervention, fk_equipment, date_creation, fk_user_creat)";
    $sql .= " VALUES (".(int)$object->id.", ".(int)$equipment_id.", ";
    $sql .= "'".$db->idate(dol_now())."', ".$user->id.")";
    
    if ($db->query($sql)) {
        setEventMessages($langs->trans('EquipmentLinked'), null, 'mesgs');
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
    
    // Get linked equipment
    $sql = "SELECT l.fk_equipment, l.date_creation, l.note";
    $sql .= " FROM ".MAIN_DB_PREFIX."equipmentmanager_intervention_link as l";
    $sql .= " WHERE l.fk_intervention = ".(int)$object->id;
    
    $resql = $db->query($sql);
    $linked_equipment_ids = array();
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $linked_equipment_ids[] = $obj->fk_equipment;
        }
    }
    
    print '<br>';
    
    // Section 1: Gewartete Equipments (verknüpft)
    print '<div class="div-table-responsive-no-min">';
    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre">';
    print '<th colspan="5">'.$langs->trans('ServicedEquipments').'</th>';
    print '</tr>';
    
    print '<tr class="liste_titre">';
    print '<th>'.$langs->trans('EquipmentNumber').'</th>';
    print '<th>'.$langs->trans('Label').'</th>';
    print '<th>'.$langs->trans('Type').'</th>';
    print '<th>'.$langs->trans('ObjectAddress').'</th>';
    print '<th class="center" width="80">'.$langs->trans('Action').'</th>';
    print '</tr>';
    
    if (count($linked_equipment_ids) > 0) {
        foreach ($linked_equipment_ids as $eq_id) {
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
                print '<td>'.$equipment->label.'</td>';
                
                // Type
                print '<td>';
                if ($equipment->equipment_type) {
                    $type_trans = $equipment->equipment_type;
                    print $type_trans;
                }
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
                        print $addr->name;
                        if ($addr->town) print '<br><span class="opacitymedium">'.$addr->town.'</span>';
                    }
                } elseif ($equipment->location_note) {
                    print '<span class="opacitymedium">'.dol_trunc($equipment->location_note, 50).'</span>';
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
    } else {
        print '<tr><td colspan="5" class="opacitymedium center">';
        print $langs->trans('NoLinkedEquipment').'<br>';
        print '<span class="opacitymedium">'.$langs->trans('LinkEquipmentFromListBelow').'</span>';
        print '</td></tr>';
    }
    
    print '</table>';
    print '</div>';
    
    print '<br><br>';
    
    // Section 2: Verfügbare Equipments (zum Verknüpfen)
    if ($object->socid > 0) {
        print '<div class="div-table-responsive-no-min">';
        print '<table class="noborder centpercent">';
        print '<tr class="liste_titre">';
        print '<th colspan="5">'.$langs->trans('AvailableEquipmentsForCustomer').'</th>';
        print '</tr>';
        
        print '<tr class="liste_titre">';
        print '<th>'.$langs->trans('EquipmentNumber').'</th>';
        print '<th>'.$langs->trans('Label').'</th>';
        print '<th>'.$langs->trans('Type').'</th>';
        print '<th>'.$langs->trans('ObjectAddress').'</th>';
        print '<th class="center" width="80">'.$langs->trans('Action').'</th>';
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
                print '<td>'.$equipment->label.'</td>';
                
                // Type
                print '<td>';
                if ($equipment->equipment_type) {
                    print $equipment->equipment_type;
                }
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
                        print $addr->name;
                        if ($addr->town) print '<br><span class="opacitymedium">'.$addr->town.'</span>';
                    }
                } elseif ($equipment->location_note) {
                    print '<span class="opacitymedium">'.dol_trunc($equipment->location_note, 50).'</span>';
                }
                print '</td>';
                
                // Actions
                print '<td class="center">';
                print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=link&equipment_id='.$equipment->id.'&token='.newToken().'">';
                print $langs->trans('Link');
                print '</a>';
                print '</td>';
                
                print '</tr>';
            }
        } else {
            print '<tr><td colspan="5" class="opacitymedium center">';
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