<?php
/* Copyright (C) 2024 Equipment Manager
 * Equipment Details Tab - v1.6 Improved
 * - Dropdown statt Pagination
 * - Datum + Arbeitszeit
 * - Bessere UX
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

$langs->loadLangs(array('interventions', 'equipmentmanager@equipmentmanager', 'products'));

$id = GETPOST('id', 'int');
$ref = GETPOST('ref', 'alpha');
$action = GETPOST('action', 'aZ09');
$equipment_id = GETPOST('equipment_id', 'int');
$material_id = GETPOST('material_id', 'int');

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

// Save detail
if ($action == 'save_detail' && $permissiontoadd && $equipment_id > 0) {
    $detail = new InterventionDetail($db);
    $detail->fk_intervention = $object->id;
    $detail->fk_equipment = $equipment_id;
    $detail->work_done = GETPOST('work_done', 'restricthtml');
    $detail->issues_found = GETPOST('issues_found', 'restricthtml');
    $detail->recommendations = GETPOST('recommendations', 'restricthtml');
    $detail->notes = GETPOST('notes', 'restricthtml');
    
    // Datum
    $detail->work_date = dol_mktime(0, 0, 0, GETPOST('work_datemonth', 'int'), GETPOST('work_dateday', 'int'), GETPOST('work_dateyear', 'int'));
    
    // Arbeitszeit (in Minuten)
    $hours = GETPOST('work_hours', 'int');
    $minutes = GETPOST('work_minutes', 'int');
    $detail->work_duration = ($hours * 60) + $minutes;
    
    $result = $detail->createOrUpdate($user);
    
    if ($result > 0) {
        setEventMessages($langs->trans('DetailSaved'), null, 'mesgs');
    } else {
        setEventMessages($detail->error, $detail->errors, 'errors');
    }
    
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
    
    // Load detail
    $detail = new InterventionDetail($db);
    $detail->fetchByInterventionEquipment($object->id, $equipment_id);
    
    print '<br>';
    
    // ========================================================================
    // DROPDOWN NAVIGATION
    // ========================================================================
    
    print '<div class="fichecenter" style="margin-bottom: 20px;">';
    print '<table class="border centpercent">';
    print '<tr>';
    print '<td class="titlefield">'.$langs->trans('SelectEquipment').'</td>';
    print '<td>';
    
    // Dropdown mit allen Equipment
    print '<select name="equipment_selector" class="flat minwidth300" onchange="window.location.href=\''.$_SERVER["PHP_SELF"].'?id='.$object->id.'&equipment_id=\'+this.value">';
    foreach ($linked_equipment as $eq_id) {
        $eq_temp = new Equipment($db);
        $eq_temp->fetch($eq_id);
        
        $selected = ($eq_id == $equipment_id) ? ' selected' : '';
        print '<option value="'.$eq_id.'"'.$selected.'>';
        print $eq_temp->equipment_number.' - '.$eq_temp->label;
        print '</option>';
    }
    print '</select>';
    
    // Quick info
    print ' <span class="opacitymedium">';
    print '('.count($linked_equipment).' '.$langs->trans('Equipment').')';
    print '</span>';
    
    print '</td>';
    print '</tr>';
    print '</table>';
    print '</div>';
    
    // ========================================================================
    // SECTION 1: EQUIPMENT INFO
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
    
    // Material summary
    $materials = InterventionMaterial::fetchAllForEquipment($db, $object->id, $equipment_id);
    $material_total = InterventionMaterial::getTotalForEquipment($db, $object->id, $equipment_id);
    
    print '<table class="border centpercent tableforfield">';
    print '<tr class="liste_titre">';
    print '<th colspan="2"><span class="fa fa-shopping-cart paddingright"></span>'.$langs->trans('MaterialSummary').'</th>';
    print '</tr>';
    
    print '<tr><td class="titlefield">'.$langs->trans("MaterialItems").'</td><td>';
    print '<strong>'.count($materials).'</strong>';
    print '</td></tr>';
    
    print '<tr><td>'.$langs->trans("TotalCost").'</td><td>';
    print '<strong>'.price($material_total, 0, '', 1, -1, -1, $conf->currency).'</strong>';
    print '</td></tr>';
    
    // Arbeitszeit anzeigen
    if ($detail->work_duration > 0) {
        $hours = floor($detail->work_duration / 60);
        $minutes = $detail->work_duration % 60;
        
        print '<tr><td>'.$langs->trans("WorkDuration").'</td><td>';
        print '<strong>'.$hours.'h '.$minutes.'min</strong>';
        print '</td></tr>';
    }
    
    print '</table>';
    print '</div>';
    print '</div>';
    
    print '<div class="clearboth"></div>';
    print '<br>';
    
    // ========================================================================
    // SECTION 2: REPORT TEXT
    // ========================================================================
    
    print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
    print '<input type="hidden" name="token" value="'.newToken().'">';
    print '<input type="hidden" name="action" value="save_detail">';
    print '<input type="hidden" name="id" value="'.$object->id.'">';
    print '<input type="hidden" name="equipment_id" value="'.$equipment_id.'">';
    
    print '<div class="div-table-responsive-no-min">';
    print '<table class="noborder centpercent">';
    
    print '<tr class="liste_titre">';
    print '<th colspan="2"><span class="fa fa-file-text-o paddingright"></span>'.$langs->trans('ServiceReport').'</th>';
    print '</tr>';
    
    // Datum
    print '<tr><td class="titlefield">'.$langs->trans('WorkDate').'</td><td>';
    $work_date = $detail->work_date ? $detail->work_date : dol_now();
    print $form->selectDate($work_date, 'work_date', 0, 0, 0, '', 1, 0);
    print '</td></tr>';
    
    // Arbeitszeit
    print '<tr><td>'.$langs->trans('WorkDuration').'</td><td>';
    $hours = floor($detail->work_duration / 60);
    $minutes = $detail->work_duration % 60;
    
    print '<input type="number" name="work_hours" value="'.$hours.'" min="0" max="24" class="flat" style="width: 60px;"> ';
    print $langs->trans('Hours');
    print ' <input type="number" name="work_minutes" value="'.$minutes.'" min="0" max="59" class="flat" style="width: 60px;"> ';
    print $langs->trans('Minutes');
    print '</td></tr>';
    
    // Work done
    print '<tr><td class="tdtop">'.$langs->trans('WorkDone').'</td><td>';
    print '<textarea name="work_done" rows="5" class="flat centpercent">';
    print dol_escape_htmltag($detail->work_done);
    print '</textarea>';
    print '</td></tr>';
    
    // Issues found
    print '<tr><td class="tdtop">'.$langs->trans('IssuesFound').'</td><td>';
    print '<textarea name="issues_found" rows="3" class="flat centpercent">';
    print dol_escape_htmltag($detail->issues_found);
    print '</textarea>';
    print '</td></tr>';
    
    // Recommendations
    print '<tr><td class="tdtop">'.$langs->trans('Recommendations').'</td><td>';
    print '<textarea name="recommendations" rows="3" class="flat centpercent">';
    print dol_escape_htmltag($detail->recommendations);
    print '</textarea>';
    print '</td></tr>';
    
    // Notes
    print '<tr><td class="tdtop">'.$langs->trans('Notes').'</td><td>';
    print '<textarea name="notes" rows="2" class="flat centpercent">';
    print dol_escape_htmltag($detail->notes);
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
    // SECTION 3: MATERIAL
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
    $sql .= " AND p.fini = 0"; // 0 = Product (Material), 1 = Service
    $sql .= " ORDER BY p.ref ASC";

    $resql = $db->query($sql);
    $products = array();
    if ($resql) {
        $num = $db->num_rows($resql);
        $i = 0;
        while ($i < $num) {
            $obj = $db->fetch_object($resql);
            // Format price to 2 decimal places
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
        // Fill material name from product label
        var label = selectedOption.getAttribute('data-label');
        if (label) {
            document.getElementById('material_name').value = label;
        }

        // Fill price
        var price = selectedOption.getAttribute('data-price');
        if (price) {
            document.getElementById('material_price').value = price;
        }

        // Keep default quantity and unit
    } else {
        // Reset fields if "no product" is selected
        document.getElementById('material_name').value = '';
        document.getElementById('material_price').value = '0';
    }
}
</script>
<?php

llxFooter();
$db->close();