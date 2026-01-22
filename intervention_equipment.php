<?php
/* Copyright (C) 2024 Equipment Manager
 *
 * Equipment-Tab auf Intervention Card v1.5.1
 * FIX: Korrekte SQL-Abfragen und Error-Handling
 */

// Load Dolibarr environment
$res = 0;
if (!$res && file_exists("../../main.inc.php")) {
    $res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
    $res = @include "../../../main.inc.php";
}
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
    $res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
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
$link_type = GETPOST('link_type', 'alpha');

$object = new Fichinter($db);

if ($id > 0 || !empty($ref)) {
    $result = $object->fetch($id, $ref);
    if ($result <= 0) {
        dol_print_error($db, 'Failed to load intervention');
        exit;
    }
}

$permissiontoread = $user->hasRight('ficheinter', 'lire');
$permissiontoadd = $user->hasRight('ficheinter', 'creer');

if (!$permissiontoread) {
    accessforbidden();
}

/*
 * Actions
 */

// Link equipment with type (single)
if ($action == 'link' && $permissiontoadd && $equipment_id > 0 && in_array($link_type, array('maintenance', 'service'))) {
    $sql = "INSERT INTO ".MAIN_DB_PREFIX."equipmentmanager_intervention_link";
    $sql .= " (fk_intervention, fk_equipment, link_type, date_creation, fk_user_creat)";
    $sql .= " VALUES (".(int)$object->id.", ".(int)$equipment_id.", '".$db->escape($link_type)."', ";
    $sql .= "'".$db->idate(dol_now())."', ".$user->id.")";

    dol_syslog("Linking equipment ".$equipment_id." to intervention ".$object->id." as ".$link_type, LOG_DEBUG);

    if ($db->query($sql)) {
        $msg = ($link_type == 'maintenance') ? 'EquipmentLinkedMaintenance' : 'EquipmentLinkedService';
        setEventMessages($langs->trans($msg), null, 'mesgs');
    } else {
        if ($db->lasterrno() == 1062) {
            setEventMessages($langs->trans('EquipmentAlreadyLinked'), null, 'warnings');
        } else {
            dol_syslog("Error linking equipment: ".$db->lasterror(), LOG_ERR);
            setEventMessages($db->lasterror(), null, 'errors');
        }
    }

    header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
    exit;
}

// Bulk link equipment (multiple)
$toselect = GETPOST('toselect', 'array');
if ($action == 'bulk_link' && $permissiontoadd && !empty($toselect) && in_array($link_type, array('maintenance', 'service'))) {
    $success_count = 0;
    $skip_count = 0;

    foreach ($toselect as $eq_id) {
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."equipmentmanager_intervention_link";
        $sql .= " (fk_intervention, fk_equipment, link_type, date_creation, fk_user_creat)";
        $sql .= " VALUES (".(int)$object->id.", ".(int)$eq_id.", '".$db->escape($link_type)."', ";
        $sql .= "'".$db->idate(dol_now())."', ".$user->id.")";

        if ($db->query($sql)) {
            $success_count++;
        } else {
            if ($db->lasterrno() == 1062) {
                $skip_count++;
            }
        }
    }

    if ($success_count > 0) {
        $msg = ($link_type == 'maintenance') ? 'EquipmentLinkedMaintenance' : 'EquipmentLinkedService';
        setEventMessages($langs->trans($msg).' ('.$success_count.')', null, 'mesgs');
    }
    if ($skip_count > 0) {
        setEventMessages($langs->trans('EquipmentAlreadyLinked').' ('.$skip_count.')', null, 'warnings');
    }

    header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
    exit;
}

// Unlink equipment
if ($action == 'unlink' && $permissiontoadd && $equipment_id > 0) {
    $sql = "DELETE FROM ".MAIN_DB_PREFIX."equipmentmanager_intervention_link";
    $sql .= " WHERE fk_intervention = ".(int)$object->id;
    $sql .= " AND fk_equipment = ".(int)$equipment_id;

    dol_syslog("Unlinking equipment ".$equipment_id." from intervention ".$object->id, LOG_DEBUG);

    if ($db->query($sql)) {
        setEventMessages($langs->trans('EquipmentUnlinked'), null, 'mesgs');
    } else {
        dol_syslog("Error unlinking equipment: ".$db->lasterror(), LOG_ERR);
        setEventMessages($db->lasterror(), null, 'errors');
    }

    header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
    exit;
}

// Generate combined checklists PDF
if ($action == 'pdf_all_checklists' && $permissiontoread) {
    dol_include_once('/equipmentmanager/class/pdf_checklist.class.php');

    $pdf_gen = new pdf_checklist($db);
    $preview = GETPOST('preview', 'int') ? true : false;
    $result = $pdf_gen->write_combined_file($object, $user, $langs, $preview);

    // DEBUG: Show debug info
    if (!empty($pdf_gen->debug_info)) {
        setEventMessages('DEBUG: '.$pdf_gen->debug_info, null, 'warnings');
    }

    if ($result && $result !== 'preview') {
        setEventMessages('PDF erstellt: '.$result, null, 'mesgs');

        // Check if file exists
        if (!file_exists($result)) {
            setEventMessages('ERROR: File does not exist: '.$result, null, 'errors');
        } else {
            setEventMessages('File exists, size: '.filesize($result).' bytes', null, 'mesgs');

            // Add as linked document to the intervention using Dolibarr's standard function
            require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
            $dir = dirname($result);
            $filename = basename($result);

            setEventMessages('Indexing: dir='.$dir.', file='.$filename.', object='.$object->element.'/'.$object->id, null, 'warnings');

            $index_result = addFileIntoDatabaseIndex($dir, $filename, $result, 'generated', 0, $object);

            if ($index_result > 0) {
                setEventMessages('Document indexed with ID: '.$index_result, null, 'mesgs');
            } else {
                setEventMessages('Document index failed, result: '.$index_result, null, 'errors');
            }
        }

        // NO redirect - stay on page to see messages
        // header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id.'&token='.newToken());
        // exit;
    } elseif ($result === 'preview') {
        exit; // Preview mode - PDF already output
    } else {
        setEventMessages($pdf_gen->error, null, 'errors');
    }
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
    $sql = "SELECT l.fk_equipment, l.link_type, l.date_creation";
    $sql .= " FROM ".MAIN_DB_PREFIX."equipmentmanager_intervention_link as l";
    $sql .= " WHERE l.fk_intervention = ".(int)$object->id;
    $sql .= " ORDER BY l.link_type, l.date_creation";
    
    dol_syslog("Getting linked equipment for intervention ".$object->id, LOG_DEBUG);
    
    $resql = $db->query($sql);
    $linked_equipment = array();
    $linked_equipment_ids = array();
    
    if ($resql) {
        $num = $db->num_rows($resql);
        dol_syslog("Found ".$num." linked equipment", LOG_DEBUG);
        
        while ($obj = $db->fetch_object($resql)) {
            $linked_equipment[$obj->fk_equipment] = $obj->link_type;
            $linked_equipment_ids[] = $obj->fk_equipment;
        }
        $db->free($resql);
    } else {
        dol_syslog("Error getting linked equipment: ".$db->lasterror(), LOG_ERR);
    }
    
    print '<br>';
    
    // Type labels (dynamic from database)
    $type_labels = Equipment::getEquipmentTypesTranslated($db, $langs);

    // Section 1: MAINTENANCE
    print '<div class="div-table-responsive-no-min">';
    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre" style="background-color: rgba(76, 175, 80, 0.15);">';
    print '<th colspan="5" style="display:flex;justify-content:space-between;align-items:center;">';
    print '<span>';
    print '<span class="fa fa-wrench paddingright"></span>';
    print '<strong>'.$langs->trans('MaintenanceWork').'</strong>';
    print ' <span class="opacitymedium">('.$langs->trans('MaintenanceWorkDescription').')</span>';
    print '</span>';
    print '<span class="opacitymedium paddingleft">'.$langs->trans('AllChecklistsPDF').':</span> ';
    print '<a class="paddingright paddingleft" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=pdf_all_checklists&preview=1&token='.newToken().'" target="_blank" title="'.$langs->trans('Preview').'">';
    print '<span class="fa fa-eye"></span>';
    print '</a>';
    print '<a href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=pdf_all_checklists&token='.newToken().'" title="'.$langs->trans('Generate').'">';
    print '<span class="fa fa-save"></span>';
    print '</a>';
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
    foreach ($linked_equipment_ids as $eq_id) {
        if ($linked_equipment[$eq_id] != 'maintenance') continue;
        $has_maintenance = true;
        
        $equipment = new Equipment($db);
        if ($equipment->fetch($eq_id) > 0) {
            print '<tr class="oddeven">';
            
            print '<td>';
            print '<a href="'.DOL_URL_ROOT.'/custom/equipmentmanager/equipment_view.php?id='.$equipment->id.'" target="_blank">';
            print img_object('', 'generic', 'class="pictofixedwidth"');
            print '<strong>'.$equipment->equipment_number.'</strong>';
            print '</a>';
            print '</td>';
            
            print '<td>'.dol_escape_htmltag($equipment->label).'</td>';
            
            print '<td>';
            print isset($type_labels[$equipment->equipment_type]) ? $type_labels[$equipment->equipment_type] : dol_escape_htmltag($equipment->equipment_type);
            print '</td>';
            
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
                    $db->free($resql2);
                }
            } elseif ($equipment->location_note) {
                print '<span class="opacitymedium">'.dol_trunc(dol_escape_htmltag($equipment->location_note), 50).'</span>';
            }
            print '</td>';
            
            print '<td class="center">';
            if ($permissiontoadd) {
                print '<a href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=unlink&equipment_id='.$equipment->id.'&token='.newToken().'">';
                print img_delete($langs->trans('Unlink'));
                print '</a>';
            }
            print '</td>';
            
            print '</tr>';
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
    
    // Section 2: SERVICE
    print '<div class="div-table-responsive-no-min">';
    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre" style="background-color: rgba(255, 152, 0, 0.15);">';
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
    foreach ($linked_equipment_ids as $eq_id) {
        if ($linked_equipment[$eq_id] != 'service') continue;
        $has_service = true;
        
        $equipment = new Equipment($db);
        if ($equipment->fetch($eq_id) > 0) {
            print '<tr class="oddeven">';
            
            print '<td>';
            print '<a href="'.DOL_URL_ROOT.'/custom/equipmentmanager/equipment_view.php?id='.$equipment->id.'" target="_blank">';
            print img_object('', 'generic', 'class="pictofixedwidth"');
            print '<strong>'.$equipment->equipment_number.'</strong>';
            print '</a>';
            print '</td>';
            
            print '<td>'.dol_escape_htmltag($equipment->label).'</td>';
            
            print '<td>';
            print isset($type_labels[$equipment->equipment_type]) ? $type_labels[$equipment->equipment_type] : dol_escape_htmltag($equipment->equipment_type);
            print '</td>';
            
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
                    $db->free($resql2);
                }
            } elseif ($equipment->location_note) {
                print '<span class="opacitymedium">'.dol_trunc(dol_escape_htmltag($equipment->location_note), 50).'</span>';
            }
            print '</td>';
            
            print '<td class="center">';
            if ($permissiontoadd) {
                print '<a href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=unlink&equipment_id='.$equipment->id.'&token='.newToken().'">';
                print img_delete($langs->trans('Unlink'));
                print '</a>';
            }
            print '</td>';
            
            print '</tr>';
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
    
    // Section 3: AVAILABLE EQUIPMENT
    if ($object->socid > 0) {
        // Get external contacts linked to this intervention (address)
        $intervention_contacts = $object->liste_contact(-1, 'external');
        $intervention_address_id = 0;
        $intervention_address_name = '';

        if (!empty($intervention_contacts)) {
            foreach ($intervention_contacts as $contact) {
                // Use first external contact as the address filter
                if ($contact['source'] == 'external' && $contact['id'] > 0) {
                    $intervention_address_id = $contact['id'];
                    $intervention_address_name = $contact['lastname'].' '.$contact['firstname'];
                    break;
                }
            }
        }

        // Fetch equipment - only if address is set
        $equipments = array();
        if ($intervention_address_id > 0) {
            // Only fetch equipment for this specific address
            $sql_eq = "SELECT rowid FROM ".MAIN_DB_PREFIX."equipmentmanager_equipment";
            $sql_eq .= " WHERE fk_soc = ".(int)$object->socid;
            $sql_eq .= " AND fk_address = ".(int)$intervention_address_id;
            $sql_eq .= " AND entity IN (".getEntity('equipmentmanager').")";
            $sql_eq .= " ORDER BY equipment_number ASC";

            $resql_eq = $db->query($sql_eq);
            if ($resql_eq) {
                while ($obj_eq = $db->fetch_object($resql_eq)) {
                    $eq = new Equipment($db);
                    if ($eq->fetch($obj_eq->rowid) > 0) {
                        $equipments[] = $eq;
                    }
                }
            }
        }
        // No address linked = no equipment shown

        // Filter out already linked equipment
        $available_equipment = array();
        foreach ($equipments as $equipment) {
            if (!in_array($equipment->id, $linked_equipment_ids)) {
                $available_equipment[] = $equipment;
            }
        }

        // Start form for bulk actions
        print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'" name="bulkform">';
        print '<input type="hidden" name="token" value="'.newToken().'">';
        print '<input type="hidden" name="id" value="'.$object->id.'">';
        print '<input type="hidden" name="action" value="bulk_link">';
        print '<input type="hidden" name="link_type" id="bulk_link_type" value="">';

        print '<div class="div-table-responsive-no-min">';
        print '<table class="noborder centpercent">';
        print '<tr class="liste_titre">';
        print '<th colspan="7">';
        print '<span class="fa fa-list paddingright"></span>';
        if ($intervention_address_id > 0) {
            print $langs->trans('EquipmentForAddress');
            print ': <strong>'.dol_escape_htmltag($intervention_address_name).'</strong>';
            print ' <span class="badge">'.count($available_equipment).'</span>';
        } else {
            print $langs->trans('AvailableEquipmentsForCustomer');
        }
        print '</th>';
        print '</tr>';

        // Bulk action bar
        if ($permissiontoadd && count($available_equipment) > 0) {
            print '<tr class="liste_titre">';
            print '<td colspan="7" class="nobottom" style="padding: 8px;">';
            print '<div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">';

            // Select all / none buttons
            print '<span>';
            print '<a href="#" onclick="selectAllEquipment(); return false;" class="button smallpaddingimp">'.$langs->trans('SelectAll').'</a> ';
            print '<a href="#" onclick="selectNoneEquipment(); return false;" class="button smallpaddingimp">'.$langs->trans('SelectNone').'</a>';
            print '</span>';

            print '<span style="border-left: 1px solid #ccc; height: 25px;"></span>';

            // Bulk action buttons
            print '<span style="display: flex; gap: 8px;">';
            print '<button type="button" onclick="bulkLinkAs(\'maintenance\');" class="button" style="background: #4caf50; color: white;">';
            print '<span class="fa fa-wrench paddingright"></span>'.$langs->trans('LinkAsMaintenance');
            print '</button>';
            print '<button type="button" onclick="bulkLinkAs(\'service\');" class="button" style="background: #ff9800; color: white;">';
            print '<span class="fa fa-cog paddingright"></span>'.$langs->trans('LinkAsService');
            print '</button>';
            print '</span>';

            // Selected count
            print '<span id="selected_count" class="opacitymedium" style="margin-left: auto;"></span>';

            print '</div>';
            print '</td>';
            print '</tr>';
        }

        print '<tr class="liste_titre">';
        if ($permissiontoadd && count($available_equipment) > 0) {
            print '<th class="center" style="width: 30px;"><input type="checkbox" id="select_all_checkbox" onclick="toggleAllEquipment(this.checked);"></th>';
        }
        print '<th>'.$langs->trans('EquipmentNumber').'</th>';
        print '<th>'.$langs->trans('Label').'</th>';
        print '<th>'.$langs->trans('Type').'</th>';
        print '<th>'.$langs->trans('ObjectAddress').'</th>';
        print '<th class="center" width="120">'.$langs->trans('LinkAsMaintenance').'</th>';
        print '<th class="center" width="120">'.$langs->trans('LinkAsService').'</th>';
        print '</tr>';

        if (count($available_equipment) > 0) {
            foreach ($available_equipment as $equipment) {
                print '<tr class="oddeven">';

                // Checkbox
                if ($permissiontoadd) {
                    print '<td class="center">';
                    print '<input type="checkbox" name="toselect[]" value="'.$equipment->id.'" class="equipment-checkbox" onchange="updateSelectedCount();">';
                    print '</td>';
                }

                print '<td>';
                print '<a href="'.DOL_URL_ROOT.'/custom/equipmentmanager/equipment_view.php?id='.$equipment->id.'" target="_blank">';
                print img_object('', 'generic', 'class="pictofixedwidth"');
                print '<strong>'.$equipment->equipment_number.'</strong>';
                print '</a>';
                print '</td>';

                print '<td>'.dol_escape_htmltag($equipment->label).'</td>';

                print '<td>';
                print isset($type_labels[$equipment->equipment_type]) ? $type_labels[$equipment->equipment_type] : dol_escape_htmltag($equipment->equipment_type);
                print '</td>';

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
                        $db->free($resql2);
                    }
                } elseif ($equipment->location_note) {
                    print '<span class="opacitymedium">'.dol_trunc(dol_escape_htmltag($equipment->location_note), 50).'</span>';
                }
                print '</td>';

                print '<td class="center">';
                if ($permissiontoadd) {
                    print '<a class="button smallpaddingimp" style="background: #4caf50; color: white;" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=link&equipment_id='.$equipment->id.'&link_type=maintenance&token='.newToken().'">';
                    print '<span class="fa fa-wrench"></span>';
                    print '</a>';
                }
                print '</td>';

                print '<td class="center">';
                if ($permissiontoadd) {
                    print '<a class="button smallpaddingimp" style="background: #ff9800; color: white;" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=link&equipment_id='.$equipment->id.'&link_type=service&token='.newToken().'">';
                    print '<span class="fa fa-cog"></span>';
                    print '</a>';
                }
                print '</td>';

                print '</tr>';
            }
        } else {
            $colspan = $permissiontoadd ? 7 : 6;
            print '<tr><td colspan="'.$colspan.'" class="opacitymedium center" style="padding: 20px;">';
            if ($intervention_address_id == 0) {
                print '<span class="fa fa-exclamation-triangle" style="color: #f57c00;"></span> ';
                print '<strong>'.$langs->trans('PleaseAddAddressFirst').'</strong><br>';
                print '<span class="opacitymedium">'.$langs->trans('GoToContactTabToAddAddress').'</span>';
            } else {
                print $langs->trans('NoEquipmentForThisAddress');
            }
            print '</td></tr>';
        }

        print '</table>';
        print '</div>';
        print '</form>';

        // JavaScript for bulk selection
        print '<script>
        function selectAllEquipment() {
            document.querySelectorAll(".equipment-checkbox").forEach(function(cb) {
                cb.checked = true;
            });
            document.getElementById("select_all_checkbox").checked = true;
            updateSelectedCount();
        }

        function selectNoneEquipment() {
            document.querySelectorAll(".equipment-checkbox").forEach(function(cb) {
                cb.checked = false;
            });
            document.getElementById("select_all_checkbox").checked = false;
            updateSelectedCount();
        }

        function toggleAllEquipment(checked) {
            document.querySelectorAll(".equipment-checkbox").forEach(function(cb) {
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

        function bulkLinkAs(linkType) {
            var selected = document.querySelectorAll(".equipment-checkbox:checked");
            if (selected.length == 0) {
                alert("'.html_entity_decode($langs->trans('PleaseSelectAtLeastOne')).'");
                return;
            }

            var typeText = (linkType == "maintenance") ? "'.html_entity_decode($langs->trans('Maintenance')).'" : "'.html_entity_decode($langs->trans('Service')).'";

            if (!confirm("'.html_entity_decode($langs->trans('ConfirmBulkLink')).'\n\n" + selected.length + " '.html_entity_decode($langs->trans('Equipment')).' → " + typeText)) {
                return;
            }

            document.getElementById("bulk_link_type").value = linkType;
            document.forms["bulkform"].submit();
        }

        // Initial count
        updateSelectedCount();
        </script>';
    } else {
        print info_admin($langs->trans('PleaseAssignThirdPartyToInterventionFirst'));
    }
}

llxFooter();
$db->close();