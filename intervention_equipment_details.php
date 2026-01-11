<?php
/* Copyright (C) 2024 Equipment Manager
 * Equipment Details Tab - v1.7
 * - Multiple report entries per equipment
 * - Dropdown navigation
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
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/equipmentmanager/class/equipment.class.php');
dol_include_once('/equipmentmanager/class/interventiondetail.class.php');
dol_include_once('/equipmentmanager/class/interventionmaterial.class.php');
dol_include_once('/equipmentmanager/class/checklisttemplate.class.php');
dol_include_once('/equipmentmanager/class/checklistresult.class.php');

$langs->loadLangs(array('interventions', 'equipmentmanager@equipmentmanager', 'products'));

$id = GETPOST('id', 'int');
$ref = GETPOST('ref', 'alpha');
$action = GETPOST('action', 'aZ09');
$equipment_id = GETPOST('equipment_id', 'int');
$material_id = GETPOST('material_id', 'int');
$entry_id = GETPOST('entry_id', 'int');

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

// Save entry (new or edit)
if ($action == 'save_entry' && $permissiontoadd && $equipment_id > 0) {
    $detail = new InterventionDetail($db);

    // Check if editing existing entry
    if ($entry_id > 0) {
        $detail->fetch($entry_id);
    }

    $detail->fk_intervention = $object->id;
    $detail->fk_equipment = $equipment_id;
    $detail->work_done = GETPOST('work_done', 'restricthtml');
    $detail->issues_found = GETPOST('issues_found', 'restricthtml');

    // Datum
    $detail->work_date = dol_mktime(0, 0, 0, GETPOST('work_datemonth', 'int'), GETPOST('work_dateday', 'int'), GETPOST('work_dateyear', 'int'));

    // Arbeitszeit (in Minuten)
    $hours = GETPOST('work_hours', 'int');
    $minutes = GETPOST('work_minutes', 'int');
    $detail->work_duration = ($hours * 60) + $minutes;

    $result = $detail->createOrUpdate($user);

    if ($result > 0) {
        // Auto-create Fichinter line if none exists (enables validation/freigabe)
        if ($object->status == Fichinter::STATUS_DRAFT) {
            $sql = "SELECT COUNT(*) as nb FROM ".MAIN_DB_PREFIX."fichinterdet WHERE fk_fichinter = ".(int)$object->id;
            $resql = $db->query($sql);
            $has_lines = false;
            if ($resql) {
                $obj = $db->fetch_object($resql);
                $has_lines = ($obj->nb > 0);
            }

            if (!$has_lines) {
                require_once DOL_DOCUMENT_ROOT.'/fichinter/class/fichinterligne.class.php';
                $desc = "Service durchgeführt";
                $intervention_date = $detail->work_date ? $detail->work_date : $object->dateo;
                $duration_seconds = $detail->work_duration * 60;
                $line_result = $object->addline($user, $object->id, $desc, $intervention_date, $duration_seconds);
            }
        }

        setEventMessages($langs->trans('DetailSaved'), null, 'mesgs');
    } else {
        setEventMessages($detail->error, $detail->errors, 'errors');
    }

    header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id."&equipment_id=".$equipment_id);
    exit;
}

// Delete entry
if ($action == 'delete_entry' && $permissiontoadd && $entry_id > 0) {
    $detail = new InterventionDetail($db);
    if ($detail->fetch($entry_id) > 0) {
        $result = $detail->delete($user);
        if ($result > 0) {
            setEventMessages($langs->trans('EntryDeleted'), null, 'mesgs');
        } else {
            setEventMessages($detail->error, $detail->errors, 'errors');
        }
    }

    header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id."&equipment_id=".$equipment_id);
    exit;
}

// Save summary (recommendations, notes)
if ($action == 'save_summary' && $permissiontoadd && $equipment_id > 0) {
    // Get first entry or create one if none exists
    $detail = new InterventionDetail($db);
    $entries = $detail->fetchAllByInterventionEquipment($object->id, $equipment_id);

    if (count($entries) > 0) {
        // Update recommendations/notes on first entry
        $first = $entries[count($entries) - 1]; // Get oldest entry (entry_number = 1)
        $first->recommendations = GETPOST('recommendations', 'restricthtml');
        $first->notes = GETPOST('notes', 'restricthtml');
        $first->update($user);
    } else {
        // Create a new entry with just recommendations/notes
        $detail->fk_intervention = $object->id;
        $detail->fk_equipment = $equipment_id;
        $detail->recommendations = GETPOST('recommendations', 'restricthtml');
        $detail->notes = GETPOST('notes', 'restricthtml');
        $detail->work_date = dol_now();
        $detail->create($user);
    }

    setEventMessages($langs->trans('DetailSaved'), null, 'mesgs');
    header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id."&equipment_id=".$equipment_id);
    exit;
}

// Add material
if ($action == 'add_material' && $permissiontoadd && $equipment_id > 0) {
    $material = new InterventionMaterial($db);
    $material->fk_intervention = $object->id;
    $material->fk_equipment = $equipment_id;
    $material->fk_product = GETPOST('fk_product', 'int') > 0 ? GETPOST('fk_product', 'int') : null;
    $material->material_name = GETPOST('material_name', 'alpha');
    $material->material_description = GETPOST('material_description', 'restricthtml');
    $material->quantity = price2num(GETPOST('quantity', 'alpha'));
    $material->unit = GETPOST('unit', 'alpha');
    $material->unit_price = price2num(GETPOST('unit_price', 'alpha'));
    $material->serial_number = GETPOST('serial_number', 'alpha');
    $material->notes = GETPOST('material_notes', 'restricthtml');

    $result = $material->create($user);

    if ($result > 0) {
        setEventMessages($langs->trans('MaterialAdded'), null, 'mesgs');
    } else {
        setEventMessages($material->error, $material->errors, 'errors');
    }

    header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id."&equipment_id=".$equipment_id);
    exit;
}

// Delete material
if ($action == 'delete_material' && $permissiontoadd && $material_id > 0) {
    $material = new InterventionMaterial($db);
    if ($material->fetch($material_id) > 0) {
        $result = $material->delete($user);

        if ($result > 0) {
            setEventMessages($langs->trans('MaterialDeleted'), null, 'mesgs');
        } else {
            setEventMessages($material->error, $material->errors, 'errors');
        }
    }

    header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id."&equipment_id=".$equipment_id);
    exit;
}

// Start checklist
if ($action == 'start_checklist' && $permissiontoadd && $equipment_id > 0) {
    $equipment_temp = new Equipment($db);
    $equipment_temp->fetch($equipment_id);

    // Get equipment intervention link ID
    $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."equipmentmanager_intervention_link";
    $sql .= " WHERE fk_intervention = ".(int)$object->id;
    $sql .= " AND fk_equipment = ".(int)$equipment_id;
    $resql = $db->query($sql);
    $eq_inter_id = 0;
    if ($resql && $db->num_rows($resql)) {
        $obj = $db->fetch_object($resql);
        $eq_inter_id = $obj->rowid;
    }

    // Get template for equipment type
    $template = new ChecklistTemplate($db);
    $template_type = $equipment_temp->equipment_type;

    // Check if FSA variant should be used (for fire_door with FSA)
    $has_fsa = GETPOST('has_fsa', 'int');
    if ($equipment_temp->equipment_type == 'fire_door' && $has_fsa) {
        $template_type = 'fire_door_fsa';
    }

    if ($template->fetchByEquipmentType($template_type) > 0) {
        $checklist = new ChecklistResult($db);
        $checklist->fk_template = $template->id;
        $checklist->fk_equipment = $equipment_id;
        $checklist->fk_intervention = $object->id;
        $checklist->fk_equipment_intervention = $eq_inter_id;
        $checklist->status = 0;
        $checklist->work_date = dol_now();

        $result = $checklist->create($user);
        if ($result > 0) {
            setEventMessages($langs->trans('ChecklistCreated'), null, 'mesgs');
        } else {
            setEventMessages($checklist->error, $checklist->errors, 'errors');
        }
    } else {
        setEventMessages('No checklist template found for equipment type: '.$template_type, null, 'errors');
    }

    header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id."&equipment_id=".$equipment_id);
    exit;
}

// Save checklist item
if ($action == 'save_checklist_item' && $permissiontoadd) {
    $checklist_id = GETPOST('checklist_id', 'int');
    $item_id = GETPOST('item_id', 'int');
    $answer = GETPOST('answer', 'alpha');
    $answer_text = GETPOST('answer_text', 'restricthtml');
    $item_note = GETPOST('item_note', 'restricthtml');

    $checklist = new ChecklistResult($db);
    if ($checklist->fetch($checklist_id) > 0) {
        $checklist->saveItemResult($item_id, $answer, $answer_text, $item_note);
    }

    header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id."&equipment_id=".$equipment_id);
    exit;
}

// Save all checklist items at once
if ($action == 'save_checklist' && $permissiontoadd) {
    $checklist_id = GETPOST('checklist_id', 'int');

    $checklist = new ChecklistResult($db);
    if ($checklist->fetch($checklist_id) > 0) {
        // Get all posted items
        foreach ($_POST as $key => $value) {
            if (preg_match('/^answer_(\d+)$/', $key, $matches)) {
                $item_id = $matches[1];
                $answer = GETPOST('answer_'.$item_id, 'alpha');
                $answer_text = GETPOST('answer_text_'.$item_id, 'restricthtml');
                $item_note = GETPOST('note_'.$item_id, 'restricthtml');
                $checklist->saveItemResult($item_id, $answer, $answer_text, $item_note);
            }
        }
        setEventMessages($langs->trans('ChecklistUpdated'), null, 'mesgs');
    }

    header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id."&equipment_id=".$equipment_id);
    exit;
}

// Complete checklist
if ($action == 'complete_checklist' && $permissiontoadd) {
    $checklist_id = GETPOST('checklist_id', 'int');

    $checklist = new ChecklistResult($db);
    if ($checklist->fetch($checklist_id) > 0) {
        // First save all items
        foreach ($_POST as $key => $value) {
            if (preg_match('/^answer_(\d+)$/', $key, $matches)) {
                $item_id = $matches[1];
                $answer = GETPOST('answer_'.$item_id, 'alpha');
                $answer_text = GETPOST('answer_text_'.$item_id, 'restricthtml');
                $item_note = GETPOST('note_'.$item_id, 'restricthtml');
                $checklist->saveItemResult($item_id, $answer, $answer_text, $item_note);
            }
        }

        // Then complete
        $result = $checklist->complete($user);
        if ($result > 0) {
            setEventMessages($langs->trans('ChecklistCompleted'), null, 'mesgs');
        } else {
            setEventMessages($checklist->error, $checklist->errors, 'errors');
        }
    }

    header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id."&equipment_id=".$equipment_id);
    exit;
}

// Delete checklist
if ($action == 'delete_checklist' && $permissiontoadd) {
    $checklist_id = GETPOST('checklist_id', 'int');

    $checklist = new ChecklistResult($db);
    if ($checklist->fetch($checklist_id) > 0) {
        $result = $checklist->delete($user);
        if ($result > 0) {
            setEventMessages($langs->trans('ChecklistDeleted'), null, 'mesgs');
        } else {
            setEventMessages($checklist->error, $checklist->errors, 'errors');
        }
    }

    header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id."&equipment_id=".$equipment_id);
    exit;
}

/*
 * View
 */

$form = new Form($db);

llxHeader('', $langs->trans('Intervention'));

if ($object->id > 0) {
    $head = fichinter_prepare_head($object);

    print dol_get_fiche_head($head, 'equipmentmanager_equipment_details', $langs->trans('Intervention'), -1, 'intervention');

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
    $sql = "SELECT l.fk_equipment, l.link_type";
    $sql .= " FROM ".MAIN_DB_PREFIX."equipmentmanager_intervention_link as l";
    $sql .= " WHERE l.fk_intervention = ".(int)$object->id;
    $sql .= " ORDER BY l.link_type, l.date_creation";

    $resql = $db->query($sql);
    $linked_equipment = array();

    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $linked_equipment[] = $obj->fk_equipment;
        }
        $db->free($resql);
    }

    if (count($linked_equipment) == 0) {
        print '<br>';
        print '<div class="info">';
        print '<span class="fa fa-info-circle"></span> ';
        print $langs->trans('NoEquipmentLinked').'<br>';
        print $langs->trans('PleaseGoToEquipmentTabFirst');
        print '</div>';
        llxFooter();
        $db->close();
        exit;
    }

    // If no equipment selected, select first one
    if (!$equipment_id) {
        $equipment_id = $linked_equipment[0];
    }

    // Load current equipment
    $equipment = new Equipment($db);
    $equipment->fetch($equipment_id);

    // Load all entries for this equipment
    $detailHelper = new InterventionDetail($db);
    $entries = $detailHelper->fetchAllByInterventionEquipment($object->id, $equipment_id);
    $totalDuration = $detailHelper->getTotalDuration($object->id, $equipment_id);

    // Get recommendations/notes from first entry (if exists)
    $recommendations = '';
    $notes = '';
    if (count($entries) > 0) {
        foreach ($entries as $e) {
            if (!empty($e->recommendations)) $recommendations = $e->recommendations;
            if (!empty($e->notes)) $notes = $e->notes;
        }
    }

    // Check if we're editing an entry
    $editEntry = null;
    if ($action == 'edit_entry' && $entry_id > 0) {
        foreach ($entries as $e) {
            if ($e->id == $entry_id) {
                $editEntry = $e;
                break;
            }
        }
    }

    print '<br>';

    // ========================================================================
    // DROPDOWN NAVIGATION
    // ========================================================================

    // Get equipment with entries (processed)
    $sql_processed = "SELECT DISTINCT fk_equipment FROM ".MAIN_DB_PREFIX."equipmentmanager_intervention_detail";
    $sql_processed .= " WHERE fk_intervention = ".(int)$object->id;
    $resql_processed = $db->query($sql_processed);
    $processed_equipment = array();
    if ($resql_processed) {
        while ($obj_proc = $db->fetch_object($resql_processed)) {
            $processed_equipment[] = $obj_proc->fk_equipment;
        }
    }

    print '<div class="fichecenter" style="margin-bottom: 20px;">';
    print '<table class="border centpercent">';
    print '<tr>';
    print '<td class="titlefield">'.$langs->trans('SelectEquipment').'</td>';
    print '<td>';

    print '<select name="equipment_selector" class="flat minwidth400" onchange="window.location.href=\''.$_SERVER["PHP_SELF"].'?id='.$object->id.'&equipment_id=\'+this.value">';
    foreach ($linked_equipment as $eq_id) {
        $eq_temp = new Equipment($db);
        $eq_temp->fetch($eq_id);

        $selected = ($eq_id == $equipment_id) ? ' selected' : '';
        $is_processed = in_array($eq_id, $processed_equipment);

        print '<option value="'.$eq_id.'"'.$selected.'>';
        if ($is_processed) {
            print '✓ ';
        } else {
            print '○ ';
        }
        print $eq_temp->equipment_number.' - '.$eq_temp->label;
        print '</option>';
    }
    print '</select>';

    // Legend
    print ' <span class="opacitymedium" style="margin-left: 15px;">';
    print '✓ = '.$langs->trans('Processed').' | ○ = '.$langs->trans('Pending');
    print '</span>';

    print '</td>';
    print '</tr>';
    print '</table>';
    print '</div>';

    // ========================================================================
    // SECTION 1: EQUIPMENT INFO + SUMMARY
    // ========================================================================

    print '<div class="fichecenter">';
    print '<div class="fichehalfleft">';
    print '<div class="underbanner clearboth"></div>';
    print '<table class="border centpercent tableforfield">';

    print '<tr class="liste_titre">';
    print '<th colspan="2"><span class="fa fa-wrench paddingright"></span>'.$langs->trans('EquipmentInformation').'</th>';
    print '</tr>';

    print '<tr><td class="titlefield">'.$langs->trans("EquipmentNumber").'</td><td>';
    print '<strong>'.$equipment->equipment_number.'</strong>';
    print '</td></tr>';

    print '<tr><td>'.$langs->trans("Label").'</td><td>';
    print dol_escape_htmltag($equipment->label);
    print '</td></tr>';

    print '<tr><td>'.$langs->trans("Type").'</td><td>';
    $type_labels = Equipment::getEquipmentTypesTranslated($db, $langs);
    print isset($type_labels[$equipment->equipment_type]) ? $type_labels[$equipment->equipment_type] : dol_escape_htmltag($equipment->equipment_type);
    print '</td></tr>';

    if ($equipment->location_note) {
        print '<tr><td class="tdtop">'.$langs->trans("LocationNote").'</td><td>';
        print nl2br(dol_escape_htmltag($equipment->location_note));
        print '</td></tr>';
    }

    print '</table>';
    print '</div>';

    print '<div class="fichehalfright">';
    print '<div class="underbanner clearboth"></div>';

    // Summary
    $materials = InterventionMaterial::fetchAllForEquipment($db, $object->id, $equipment_id);
    $material_total = InterventionMaterial::getTotalForEquipment($db, $object->id, $equipment_id);

    print '<table class="border centpercent tableforfield">';
    print '<tr class="liste_titre">';
    print '<th colspan="2"><span class="fa fa-info-circle paddingright"></span>'.$langs->trans('Summary').'</th>';
    print '</tr>';

    print '<tr><td class="titlefield">'.$langs->trans("Entries").'</td><td>';
    print '<strong>'.count($entries).'</strong>';
    print '</td></tr>';

    // Gesamt-Arbeitszeit
    if ($totalDuration > 0) {
        $hours = floor($totalDuration / 60);
        $minutes = $totalDuration % 60;

        print '<tr><td>'.$langs->trans("TotalDuration").'</td><td>';
        print '<strong>'.$hours.'h '.$minutes.'min</strong>';
        print '</td></tr>';
    }

    print '<tr><td>'.$langs->trans("MaterialItems").'</td><td>';
    print '<strong>'.count($materials).'</strong>';
    print '</td></tr>';

    print '<tr><td>'.$langs->trans("TotalCost").'</td><td>';
    print '<strong>'.price($material_total, 0, '', 1, -1, -1, $conf->currency).'</strong>';
    print '</td></tr>';

    print '</table>';
    print '</div>';
    print '</div>';

    print '<div class="clearboth"></div>';
    print '<br>';

    // ========================================================================
    // SECTION 2: REPORT ENTRIES
    // ========================================================================

    print '<div class="div-table-responsive-no-min">';
    print '<table class="noborder centpercent">';

    print '<tr class="liste_titre">';
    print '<th colspan="5">';
    print '<span class="fa fa-file-text-o paddingright"></span>'.$langs->trans('WorkEntries');
    if ($permissiontoadd) {
        print ' <a class="button buttongen button-add" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&equipment_id='.$equipment_id.'&action=new_entry">';
        print '<span class="fa fa-plus"></span> '.$langs->trans('AddEntry');
        print '</a>';
    }
    print '</th>';
    print '</tr>';

    // Entry form (for new or edit)
    if (($action == 'new_entry' || $action == 'edit_entry') && $permissiontoadd) {
        print '<tr id="entry_form">';
        print '<td colspan="5">';
        print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
        print '<input type="hidden" name="token" value="'.newToken().'">';
        print '<input type="hidden" name="action" value="save_entry">';
        print '<input type="hidden" name="id" value="'.$object->id.'">';
        print '<input type="hidden" name="equipment_id" value="'.$equipment_id.'">';
        if ($editEntry) {
            print '<input type="hidden" name="entry_id" value="'.$editEntry->id.'">';
        }

        print '<table class="border centpercent">';

        // Datum
        print '<tr><td class="titlefield">'.$langs->trans('WorkDate').' <span class="star">*</span></td><td>';
        $work_date = $editEntry ? $editEntry->work_date : dol_now();
        print $form->selectDate($work_date, 'work_date', 0, 0, 0, '', 1, 0);
        print '</td></tr>';

        // Arbeitszeit
        print '<tr><td>'.$langs->trans('WorkDuration').'</td><td>';
        $hours = $editEntry ? floor($editEntry->work_duration / 60) : 0;
        $minutes = $editEntry ? $editEntry->work_duration % 60 : 0;

        print '<input type="number" name="work_hours" value="'.$hours.'" min="0" max="24" class="flat" style="width: 60px;"> ';
        print $langs->trans('Hours');
        print ' <input type="number" name="work_minutes" value="'.$minutes.'" min="0" max="59" class="flat" style="width: 60px;"> ';
        print $langs->trans('Minutes');
        print '</td></tr>';

        // Work done
        print '<tr><td class="tdtop">'.$langs->trans('WorkDone').'</td><td>';
        print '<textarea name="work_done" rows="4" class="flat centpercent">';
        print $editEntry ? dol_escape_htmltag($editEntry->work_done) : '';
        print '</textarea>';
        print '</td></tr>';

        // Issues found
        print '<tr><td class="tdtop">'.$langs->trans('IssuesFound').'</td><td>';
        print '<textarea name="issues_found" rows="2" class="flat centpercent">';
        print $editEntry ? dol_escape_htmltag($editEntry->issues_found) : '';
        print '</textarea>';
        print '</td></tr>';

        print '<tr>';
        print '<td colspan="2" class="center">';
        print '<input type="submit" class="button button-save" value="'.$langs->trans('Save').'">';
        print ' <a class="button button-cancel" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&equipment_id='.$equipment_id.'">'.$langs->trans('Cancel').'</a>';
        print '</td>';
        print '</tr>';

        print '</table>';
        print '</form>';
        print '</td>';
        print '</tr>';
    }

    // Entry list header
    print '<tr class="liste_titre">';
    print '<th>'.$langs->trans('Date').'</th>';
    print '<th class="center">'.$langs->trans('Duration').'</th>';
    print '<th>'.$langs->trans('WorkDone').'</th>';
    print '<th>'.$langs->trans('IssuesFound').'</th>';
    print '<th class="center" width="80">'.$langs->trans('Actions').'</th>';
    print '</tr>';

    // Entry items
    if (count($entries) > 0) {
        foreach ($entries as $entry) {
            print '<tr class="oddeven">';

            // Date
            print '<td><strong>'.dol_print_date($entry->work_date, 'day').'</strong></td>';

            // Duration
            print '<td class="center">';
            if ($entry->work_duration > 0) {
                $h = floor($entry->work_duration / 60);
                $m = $entry->work_duration % 60;
                print $h.'h '.($m > 0 ? $m.'min' : '');
            } else {
                print '-';
            }
            print '</td>';

            // Work done (truncated)
            print '<td>';
            if ($entry->work_done) {
                $text = dol_trunc(dol_escape_htmltag($entry->work_done), 80);
                print $text;
            }
            print '</td>';

            // Issues found (truncated)
            print '<td>';
            if ($entry->issues_found) {
                $text = dol_trunc(dol_escape_htmltag($entry->issues_found), 50);
                print '<span class="warning">'.$text.'</span>';
            }
            print '</td>';

            // Actions
            print '<td class="center nowraponall">';
            if ($permissiontoadd) {
                print '<a href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&equipment_id='.$equipment_id.'&action=edit_entry&entry_id='.$entry->id.'">';
                print img_edit();
                print '</a> ';
                print '<a href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&equipment_id='.$equipment_id.'&action=delete_entry&entry_id='.$entry->id.'&token='.newToken().'" onclick="return confirm(\''.$langs->trans('ConfirmDelete').'\');">';
                print img_delete();
                print '</a>';
            }
            print '</td>';

            print '</tr>';
        }
    } else {
        print '<tr><td colspan="5" class="opacitymedium center">';
        print $langs->trans('NoEntries');
        if ($permissiontoadd) {
            print ' - <a href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&equipment_id='.$equipment_id.'&action=new_entry">'.$langs->trans('AddEntry').'</a>';
        }
        print '</td></tr>';
    }

    print '</table>';
    print '</div>';

    print '<br>';

    // ========================================================================
    // SECTION 3: CHECKLIST
    // ========================================================================

    // Get equipment intervention link ID
    $sql_eq_inter = "SELECT rowid FROM ".MAIN_DB_PREFIX."equipmentmanager_intervention_link";
    $sql_eq_inter .= " WHERE fk_intervention = ".(int)$object->id;
    $sql_eq_inter .= " AND fk_equipment = ".(int)$equipment_id;
    $resql_eq_inter = $db->query($sql_eq_inter);
    $eq_inter_id = 0;
    if ($resql_eq_inter && $db->num_rows($resql_eq_inter)) {
        $obj_eq_inter = $db->fetch_object($resql_eq_inter);
        $eq_inter_id = $obj_eq_inter->rowid;
    }

    // Check if checklist exists for this equipment/intervention
    $checklistResult = new ChecklistResult($db);
    $hasChecklist = ($checklistResult->fetchByEquipmentIntervention($equipment_id, $eq_inter_id) > 0);

    print '<div class="div-table-responsive-no-min">';
    print '<table class="noborder centpercent">';

    print '<tr class="liste_titre">';
    print '<th colspan="4">';
    print '<span class="fa fa-check-square-o paddingright"></span>'.$langs->trans('Checklist');

    if (!$hasChecklist && $permissiontoadd) {
        // Show start checklist button
        // For fire_door, show option to select with/without FSA
        if ($equipment->equipment_type == 'fire_door') {
            print ' <a class="button buttongen" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&equipment_id='.$equipment_id.'&action=start_checklist&has_fsa=0&token='.newToken().'">';
            print '<span class="fa fa-play"></span> '.$langs->trans('StartChecklist').' ('.$langs->trans('ChecklistFireDoor').')';
            print '</a>';
            print ' <a class="button buttongen" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&equipment_id='.$equipment_id.'&action=start_checklist&has_fsa=1&token='.newToken().'">';
            print '<span class="fa fa-play"></span> '.$langs->trans('StartChecklist').' ('.$langs->trans('HasFSA').')';
            print '</a>';
        } else {
            print ' <a class="button buttongen" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&equipment_id='.$equipment_id.'&action=start_checklist&token='.newToken().'">';
            print '<span class="fa fa-play"></span> '.$langs->trans('StartChecklist');
            print '</a>';
        }
    } elseif ($hasChecklist) {
        print ' '.$checklistResult->getLibStatut(0);
    }
    print '</th>';
    print '</tr>';

    if ($hasChecklist) {
        // Load template and sections
        $template = new ChecklistTemplate($db);
        $template->fetch($checklistResult->fk_template);
        $template->fetchSectionsWithItems();

        // Load existing answers
        $checklistResult->fetchItemResults();

        // Show norm reference
        if ($template->norm_reference) {
            print '<tr><td colspan="4" class="opacitymedium" style="padding: 5px 10px;">';
            print '<strong>'.$langs->trans('Norm').':</strong> '.dol_escape_htmltag($template->norm_reference);
            print '</td></tr>';
        }

        // Only show form if not completed
        if ($checklistResult->status == 0) {
            print '<tr><td colspan="4">';
            print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'" id="checklist_form">';
            print '<input type="hidden" name="token" value="'.newToken().'">';
            print '<input type="hidden" name="action" value="save_checklist">';
            print '<input type="hidden" name="id" value="'.$object->id.'">';
            print '<input type="hidden" name="equipment_id" value="'.$equipment_id.'">';
            print '<input type="hidden" name="checklist_id" value="'.$checklistResult->id.'">';
            print '</td></tr>';
        }

        // Display sections and items
        foreach ($template->sections as $section) {
            // Section header
            print '<tr class="liste_titre">';
            print '<th colspan="4" style="background-color: #f0f0f0;">';
            print $langs->trans($section->label);
            print '</th>';
            print '</tr>';

            // Items header
            print '<tr class="liste_titre">';
            print '<th style="width: 50%;">'.$langs->trans('CheckPoint').'</th>';
            print '<th class="center" style="width: 30%;">'.$langs->trans('Result').'</th>';
            print '<th style="width: 20%;">'.$langs->trans('Notes').'</th>';
            print '</tr>';

            foreach ($section->items as $item) {
                $current_answer = isset($checklistResult->item_results[$item->id]) ? $checklistResult->item_results[$item->id]['answer'] : '';
                $current_text = isset($checklistResult->item_results[$item->id]) ? $checklistResult->item_results[$item->id]['answer_text'] : '';
                $current_note = isset($checklistResult->item_results[$item->id]) ? $checklistResult->item_results[$item->id]['note'] : '';

                print '<tr class="oddeven">';

                // Item label
                print '<td>'.$langs->trans($item->label).'</td>';

                // Answer input
                print '<td class="center">';
                if ($checklistResult->status == 0 && $permissiontoadd) {
                    // Editable
                    if ($item->answer_type == 'ok_mangel') {
                        print '<select name="answer_'.$item->id.'" class="flat">';
                        print '<option value="">--</option>';
                        print '<option value="ok"'.($current_answer == 'ok' ? ' selected' : '').'>'.$langs->trans('AnswerOK').'</option>';
                        print '<option value="mangel"'.($current_answer == 'mangel' ? ' selected' : '').'>'.$langs->trans('AnswerMangel').'</option>';
                        print '</select>';
                    } elseif ($item->answer_type == 'ok_mangel_nv') {
                        print '<select name="answer_'.$item->id.'" class="flat">';
                        print '<option value="">--</option>';
                        print '<option value="ok"'.($current_answer == 'ok' ? ' selected' : '').'>'.$langs->trans('AnswerOK').'</option>';
                        print '<option value="mangel"'.($current_answer == 'mangel' ? ' selected' : '').'>'.$langs->trans('AnswerMangel').'</option>';
                        print '<option value="nv"'.($current_answer == 'nv' ? ' selected' : '').'>'.$langs->trans('AnswerNV').'</option>';
                        print '</select>';
                    } elseif ($item->answer_type == 'ja_nein') {
                        print '<select name="answer_'.$item->id.'" class="flat">';
                        print '<option value="">--</option>';
                        print '<option value="ja"'.($current_answer == 'ja' ? ' selected' : '').'>'.$langs->trans('AnswerJa').'</option>';
                        print '<option value="nein"'.($current_answer == 'nein' ? ' selected' : '').'>'.$langs->trans('AnswerNein').'</option>';
                        print '</select>';
                    } elseif ($item->answer_type == 'info') {
                        print '<input type="text" name="answer_text_'.$item->id.'" value="'.dol_escape_htmltag($current_text).'" class="flat minwidth200">';
                        print '<input type="hidden" name="answer_'.$item->id.'" value="info">';
                    }
                } else {
                    // Read-only display
                    if ($item->answer_type == 'info') {
                        print dol_escape_htmltag($current_text);
                    } else {
                        $answer_class = '';
                        if ($current_answer == 'ok' || $current_answer == 'ja') {
                            $answer_class = 'badge badge-status4';
                        } elseif ($current_answer == 'mangel' || $current_answer == 'nein') {
                            $answer_class = 'badge badge-status8';
                        } elseif ($current_answer == 'nv') {
                            $answer_class = 'badge badge-status0';
                        }
                        if ($current_answer) {
                            print '<span class="'.$answer_class.'">'.$langs->trans('Answer'.ucfirst($current_answer)).'</span>';
                        }
                    }
                }
                print '</td>';

                // Note
                print '<td>';
                if ($checklistResult->status == 0 && $permissiontoadd) {
                    print '<input type="text" name="note_'.$item->id.'" value="'.dol_escape_htmltag($current_note).'" class="flat" style="width: 100%;">';
                } else {
                    print dol_escape_htmltag($current_note);
                }
                print '</td>';

                print '</tr>';
            }
        }

        // Buttons
        if ($checklistResult->status == 0 && $permissiontoadd) {
            print '<tr><td colspan="4" class="center" style="padding: 15px;">';
            print '<input type="submit" class="button" value="'.$langs->trans('Save').'">';
            print ' <input type="submit" class="button button-save" name="action" value="'.$langs->trans('CompleteChecklist').'" onclick="jQuery(\'#checklist_form input[name=action]\').val(\'complete_checklist\'); return true;">';
            print ' <a class="button button-cancel" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&equipment_id='.$equipment_id.'&action=delete_checklist&checklist_id='.$checklistResult->id.'&token='.newToken().'" onclick="return confirm(\''.$langs->trans('ConfirmDeleteChecklist').'\');">'.$langs->trans('Delete').'</a>';
            print '</td></tr>';
            print '</form>';
        }
    } else {
        print '<tr><td colspan="4" class="opacitymedium center" style="padding: 20px;">';
        print $langs->trans('NoChecklistYet');
        print '</td></tr>';
    }

    print '</table>';
    print '</div>';

    print '<br>';

    // ========================================================================
    // SECTION 4: RECOMMENDATIONS & NOTES
    // ========================================================================

    print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
    print '<input type="hidden" name="token" value="'.newToken().'">';
    print '<input type="hidden" name="action" value="save_summary">';
    print '<input type="hidden" name="id" value="'.$object->id.'">';
    print '<input type="hidden" name="equipment_id" value="'.$equipment_id.'">';

    print '<div class="div-table-responsive-no-min">';
    print '<table class="noborder centpercent">';

    print '<tr class="liste_titre">';
    print '<th colspan="2"><span class="fa fa-lightbulb-o paddingright"></span>'.$langs->trans('RecommendationsAndNotes').'</th>';
    print '</tr>';

    // Recommendations
    print '<tr><td class="titlefield tdtop">'.$langs->trans('Recommendations').'</td><td>';
    print '<textarea name="recommendations" rows="3" class="flat centpercent">';
    print dol_escape_htmltag($recommendations);
    print '</textarea>';
    print '</td></tr>';

    // Notes
    print '<tr><td class="tdtop">'.$langs->trans('Notes').'</td><td>';
    print '<textarea name="notes" rows="2" class="flat centpercent">';
    print dol_escape_htmltag($notes);
    print '</textarea>';
    print '</td></tr>';

    print '</table>';
    print '</div>';

    print '<div class="center" style="margin-top: 10px;">';
    if ($permissiontoadd) {
        print '<input type="submit" class="button button-save" value="'.$langs->trans('Save').'">';
    }
    print '</div>';

    print '</form>';

    print '<br>';

    // ========================================================================
    // SECTION 4: MATERIAL
    // ========================================================================

    print '<div class="div-table-responsive-no-min">';
    print '<table class="noborder centpercent">';

    print '<tr class="liste_titre">';
    print '<th colspan="6">';
    print '<span class="fa fa-cubes paddingright"></span>'.$langs->trans('MaterialAndParts');
    if ($permissiontoadd) {
        print ' <a class="button buttongen button-add" href="#" onclick="jQuery(\'#add_material_form\').toggle(); return false;">';
        print '<span class="fa fa-plus"></span> '.$langs->trans('AddMaterial');
        print '</a>';
    }
    print '</th>';
    print '</tr>';

    // Add material form (hidden by default)
    print '<tr id="add_material_form" style="display:none;">';
    print '<td colspan="6">';
    print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
    print '<input type="hidden" name="token" value="'.newToken().'">';
    print '<input type="hidden" name="action" value="add_material">';
    print '<input type="hidden" name="id" value="'.$object->id.'">';
    print '<input type="hidden" name="equipment_id" value="'.$equipment_id.'">';

    print '<table class="border centpercent">';

    // Product selector
    print '<tr>';
    print '<td class="titlefield">'.$langs->trans('Product').'</td>';
    print '<td>';

    // Load products (only material, not services)
    $sql = "SELECT p.rowid, p.ref, p.label, p.price, p.description";
    $sql .= " FROM ".MAIN_DB_PREFIX."product as p";
    $sql .= " WHERE p.entity IN (".getEntity('product').")";
    $sql .= " AND p.tosell = 1";
    $sql .= " AND p.fk_product_type = 0";
    $sql .= " ORDER BY p.ref ASC";

    $resql = $db->query($sql);
    $products = array();
    if ($resql) {
        $num = $db->num_rows($resql);
        $i = 0;
        while ($i < $num) {
            $obj = $db->fetch_object($resql);
            $formatted_price = price2num($obj->price, 2);
            $products[$obj->rowid] = array(
                'ref' => $obj->ref,
                'label' => $obj->label,
                'price' => $formatted_price,
                'description' => $obj->description
            );
            $i++;
        }
        $db->free($resql);
    }

    print '<select name="fk_product" id="product_selector" class="flat minwidth300" onchange="fillProductData()">';
    print '<option value="0">-- '.$langs->trans('SelectProduct').' ('.$langs->trans('Optional').') --</option>';
    foreach ($products as $prod_id => $prod) {
        print '<option value="'.$prod_id.'" data-label="'.dol_escape_htmltag($prod['label']).'" data-price="'.$prod['price'].'" data-description="'.dol_escape_htmltag($prod['description']).'">';
        print $prod['ref'].' - '.$prod['label'];
        print '</option>';
    }
    print '</select>';
    print ' <span class="opacitymedium">'.$langs->trans('OrEnterManually').'</span>';
    print '</td>';
    print '</tr>';

    print '<tr>';
    print '<td class="titlefield">'.$langs->trans('MaterialName').'</td>';
    print '<td><input type="text" name="material_name" id="material_name" class="flat minwidth200" required></td>';
    print '</tr>';
    print '<tr>';
    print '<td>'.$langs->trans('Quantity').'</td>';
    print '<td><input type="text" name="quantity" id="material_quantity" class="flat" value="1" required></td>';
    print '</tr>';
    print '<tr>';
    print '<td>'.$langs->trans('Unit').'</td>';
    print '<td><input type="text" name="unit" id="material_unit" class="flat" value="Stk"></td>';
    print '</tr>';
    print '<tr>';
    print '<td>'.$langs->trans('UnitPrice').'</td>';
    print '<td><input type="text" name="unit_price" id="material_price" class="flat" value="0"></td>';
    print '</tr>';
    print '<tr>';
    print '<td colspan="2" class="center">';
    print '<input type="submit" class="button button-save" value="'.$langs->trans('Add').'">';
    print ' <input type="button" class="button button-cancel" value="'.$langs->trans('Cancel').'" onclick="jQuery(\'#add_material_form\').hide();">';
    print '</td>';
    print '</tr>';
    print '</table>';

    print '</form>';
    print '</td>';
    print '</tr>';

    // Material list header
    print '<tr class="liste_titre">';
    print '<th>'.$langs->trans('MaterialName').'</th>';
    print '<th class="right">'.$langs->trans('Quantity').'</th>';
    print '<th>'.$langs->trans('Unit').'</th>';
    print '<th class="right">'.$langs->trans('UnitPrice').'</th>';
    print '<th class="right">'.$langs->trans('TotalPrice').'</th>';
    print '<th class="center" width="80">'.$langs->trans('Actions').'</th>';
    print '</tr>';

    // Material items
    if (count($materials) > 0) {
        foreach ($materials as $material) {
            print '<tr class="oddeven">';
            print '<td><strong>'.dol_escape_htmltag($material->material_name).'</strong></td>';
            print '<td class="right">'.price($material->quantity, 0, '', 0, 0).'</td>';
            print '<td>'.dol_escape_htmltag($material->unit).'</td>';
            print '<td class="right">'.price($material->unit_price, 0, '', 1, -1, -1, $conf->currency).'</td>';
            print '<td class="right"><strong>'.price($material->total_price, 0, '', 1, -1, -1, $conf->currency).'</strong></td>';
            print '<td class="center">';
            if ($permissiontoadd) {
                print '<a href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&equipment_id='.$equipment_id.'&action=delete_material&material_id='.$material->id.'&token='.newToken().'" onclick="return confirm(\''.$langs->trans('ConfirmDeleteMaterial').'\');">';
                print img_delete();
                print '</a>';
            }
            print '</td>';
            print '</tr>';
        }

        // Total row
        print '<tr class="liste_total">';
        print '<td colspan="4" class="right"><strong>'.$langs->trans('Total').'</strong></td>';
        print '<td class="right"><strong>'.price($material_total, 0, '', 1, -1, -1, $conf->currency).'</strong></td>';
        print '<td></td>';
        print '</tr>';
    } else {
        print '<tr><td colspan="6" class="opacitymedium center">';
        print $langs->trans('NoMaterialAdded');
        print '</td></tr>';
    }

    print '</table>';
    print '</div>';
}

// JavaScript for product auto-fill
?>
<script type="text/javascript">
function fillProductData() {
    var selector = document.getElementById('product_selector');
    var selectedOption = selector.options[selector.selectedIndex];

    if (selectedOption.value != '0') {
        var label = selectedOption.getAttribute('data-label');
        if (label) {
            document.getElementById('material_name').value = label;
        }

        var price = selectedOption.getAttribute('data-price');
        if (price) {
            document.getElementById('material_price').value = price;
        }
    } else {
        document.getElementById('material_name').value = '';
        document.getElementById('material_price').value = '0';
    }
}
</script>
<?php

llxFooter();
$db->close();
