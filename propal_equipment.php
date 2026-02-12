<?php
/* Copyright (C) 2024-2025 Equipment Manager
 *
 * Equipment-Tab on Propal (Angebot) Card
 * v4.4: Link equipment to proposals for automatic transfer to orders/interventions
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

require_once DOL_DOCUMENT_ROOT.'/comm/propal/class/propal.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/propal.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/equipmentmanager/class/equipment.class.php');
dol_include_once('/equipmentmanager/class/documentequipmentlink.class.php');

$langs->loadLangs(array('propal', 'equipmentmanager@equipmentmanager'));

$id = GETPOST('id', 'int');
$ref = GETPOST('ref', 'alpha');
$action = GETPOST('action', 'aZ09');
$equipment_id = GETPOST('equipment_id', 'int');
$link_type = GETPOST('link_type', 'alpha') ?: 'service';

$object = new Propal($db);

if ($id > 0 || !empty($ref)) {
    $result = $object->fetch($id, $ref);
    if ($result <= 0) {
        dol_print_error($db, 'Failed to load proposal');
        exit;
    }
    $object->fetch_thirdparty();
}

$permissiontoread = $user->hasRight('propal', 'lire');
$permissiontoadd = $user->hasRight('propal', 'creer');

if (!$permissiontoread) {
    accessforbidden();
}

/*
 * Actions
 */

// Link equipment
if ($action == 'link' && $permissiontoadd && $equipment_id > 0 && in_array($link_type, array('maintenance', 'service'))) {
    $link = new DocumentEquipmentLink($db, 'propal');
    $link->fk_document = $object->id;
    $link->fk_equipment = $equipment_id;
    $link->link_type = $link_type;

    $result = $link->create($user);
    if ($result > 0) {
        $msg = ($link_type == 'maintenance') ? 'EquipmentLinkedMaintenance' : 'EquipmentLinkedService';
        setEventMessages($langs->trans($msg), null, 'mesgs');
    } else {
        if (strpos($link->error, 'Duplicate') !== false) {
            setEventMessages($langs->trans('EquipmentAlreadyLinked'), null, 'warnings');
        } else {
            setEventMessages($link->error, null, 'errors');
        }
    }

    header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
    exit;
}

// Bulk link equipment
$toselect = GETPOST('toselect', 'array');
if ($action == 'bulk_link' && $permissiontoadd && !empty($toselect) && in_array($link_type, array('maintenance', 'service'))) {
    $success_count = 0;
    $skip_count = 0;

    foreach ($toselect as $eq_id) {
        $link = new DocumentEquipmentLink($db, 'propal');
        $link->fk_document = $object->id;
        $link->fk_equipment = $eq_id;
        $link->link_type = $link_type;

        if ($link->create($user) > 0) {
            $success_count++;
        } else {
            $skip_count++;
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
    $link = new DocumentEquipmentLink($db, 'propal');
    $result = $link->deleteByDocumentAndEquipment($object->id, $equipment_id);

    if ($result > 0) {
        setEventMessages($langs->trans('EquipmentUnlinked'), null, 'mesgs');
    } else {
        setEventMessages($link->error, null, 'errors');
    }

    header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
    exit;
}

// Update link type
if ($action == 'update_link_type' && $permissiontoadd && $equipment_id > 0) {
    $new_type = GETPOST('new_link_type', 'alpha');
    if (in_array($new_type, array('maintenance', 'service'))) {
        $sql = "UPDATE ".MAIN_DB_PREFIX."equipmentmanager_propal_equipment";
        $sql .= " SET link_type = '".$db->escape($new_type)."'";
        $sql .= " WHERE fk_propal = ".(int)$object->id;
        $sql .= " AND fk_equipment = ".(int)$equipment_id;

        if ($db->query($sql)) {
            setEventMessages($langs->trans('LinkTypeUpdated'), null, 'mesgs');
        }
    }

    header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
    exit;
}

/*
 * View
 */

$form = new Form($db);

$title = $langs->trans('Proposal').' - '.$langs->trans('Equipment');
llxHeader('', $title);

if ($object->id > 0) {
    $head = propal_prepare_head($object);

    print dol_get_fiche_head($head, 'equipmentmanager', $langs->trans('Proposal'), -1, 'propal');

    // Banner
    $linkback = '<a href="'.DOL_URL_ROOT.'/comm/propal/list.php?restore_lastsearch_values=1">'.$langs->trans("BackToList").'</a>';

    $morehtmlref = '<div class="refidno">';
    $morehtmlref .= $object->thirdparty->getNomUrl(1, 'customer');
    $morehtmlref .= '</div>';

    dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref);

    print '<div class="fichecenter">';
    print '<div class="underbanner clearboth"></div>';
    print '</div>';

    print dol_get_fiche_end();

    // Get customer's equipment
    $customer_id = $object->socid;
    $equipment_list = array();

    $sql = "SELECT e.rowid, e.equipment_number, e.label, e.equipment_type, e.location_note,";
    $sql .= " a.address, a.zip, a.town";
    $sql .= " FROM ".MAIN_DB_PREFIX."equipmentmanager_equipment as e";
    $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."socpeople as a ON a.rowid = e.fk_address";
    $sql .= " WHERE e.fk_soc = ".(int)$customer_id;
    $sql .= " ORDER BY e.equipment_number ASC";

    $resql = $db->query($sql);
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $equipment_list[$obj->rowid] = $obj;
        }
        $db->free($resql);
    }

    // Get already linked equipment
    $linkHelper = new DocumentEquipmentLink($db, 'propal');
    $linked_equipment = $linkHelper->fetchAllByDocument($object->id);
    $linked_ids = array();
    foreach ($linked_equipment as $link) {
        $linked_ids[$link->fk_equipment] = $link;
    }

    // Equipment type labels
    $type_labels = Equipment::getEquipmentTypesTranslated($db, $langs);

    print '<br>';

    // ========================================================================
    // SECTION 1: Linked Equipment (with copy button)
    // ========================================================================

    print '<div class="div-table-responsive-no-min">';
    print '<table class="noborder centpercent">';

    print '<tr class="liste_titre">';
    print '<th colspan="6">';
    print '<span class="fa fa-link paddingright"></span>'.$langs->trans('LinkedEquipment');
    if (count($linked_equipment) > 0) {
        print ' <button type="button" class="button small" onclick="copyAllEquipment()" title="'.$langs->trans('CopyAllToClipboard').'">';
        print '<span class="fa fa-clipboard"></span> '.$langs->trans('CopyAll');
        print '</button>';
    }
    print '</th>';
    print '</tr>';

    if (count($linked_equipment) > 0) {
        print '<tr class="liste_titre">';
        print '<th>'.$langs->trans('EquipmentNumber').'</th>';
        print '<th>'.$langs->trans('Label').'</th>';
        print '<th>'.$langs->trans('Type').'</th>';
        print '<th>'.$langs->trans('LocationNote').'</th>';
        print '<th class="center">'.$langs->trans('LinkType').'</th>';
        print '<th class="center" width="120">'.$langs->trans('Actions').'</th>';
        print '</tr>';

        foreach ($linked_equipment as $link) {
            print '<tr class="oddeven">';

            // Equipment number with copy button
            print '<td>';
            print '<strong>'.$link->equipment_number.'</strong>';
            print ' <button type="button" class="button-clipboard" onclick="copyEquipment(\''.$link->equipment_number.'\', \''.addslashes($link->equipment_label).'\', \''.addslashes($link->location_note).'\')" title="'.$langs->trans('CopyToClipboard').'">';
            print '<span class="fa fa-clipboard"></span>';
            print '</button>';
            print '</td>';

            // Label
            print '<td>'.dol_escape_htmltag($link->equipment_label).'</td>';

            // Type
            print '<td>';
            print isset($type_labels[$link->equipment_type]) ? $type_labels[$link->equipment_type] : $link->equipment_type;
            print '</td>';

            // Location
            print '<td>'.dol_escape_htmltag($link->location_note).'</td>';

            // Link type with toggle
            print '<td class="center">';
            if ($link->link_type == 'maintenance') {
                print '<span class="badge badge-status4" style="background:#e8f5e9;color:#2e7d32;padding:3px 8px;border-radius:4px;">'.$langs->trans('MaintenanceWork').'</span>';
            } else {
                print '<span class="badge badge-status1" style="background:#fff3e0;color:#e65100;padding:3px 8px;border-radius:4px;">'.$langs->trans('ServiceWork').'</span>';
            }
            if ($permissiontoadd) {
                $new_type = ($link->link_type == 'maintenance') ? 'service' : 'maintenance';
                print ' <a href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=update_link_type&equipment_id='.$link->fk_equipment.'&new_link_type='.$new_type.'&token='.newToken().'" title="'.$langs->trans('ToggleLinkType').'">';
                print '<span class="fa fa-exchange"></span>';
                print '</a>';
            }
            print '</td>';

            // Actions
            print '<td class="center nowraponall">';
            if ($permissiontoadd) {
                print '<a href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=unlink&equipment_id='.$link->fk_equipment.'&token='.newToken().'" onclick="return confirm(\''.$langs->trans('ConfirmUnlinkEquipment').'\');">';
                print img_delete();
                print '</a>';
            }
            print '</td>';

            print '</tr>';
        }
    } else {
        print '<tr><td colspan="6" class="opacitymedium center" style="padding: 20px;">';
        print $langs->trans('NoEquipmentLinked');
        print '</td></tr>';
    }

    print '</table>';
    print '</div>';

    print '<br>';

    // ========================================================================
    // SECTION 2: Available Equipment to Link
    // ========================================================================

    // Filter out already linked equipment
    $available_equipment = array();
    foreach ($equipment_list as $eq_id => $eq) {
        if (!isset($linked_ids[$eq_id])) {
            $available_equipment[$eq_id] = $eq;
        }
    }

    if (count($available_equipment) > 0 && $permissiontoadd) {
        print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
        print '<input type="hidden" name="token" value="'.newToken().'">';
        print '<input type="hidden" name="action" value="bulk_link">';
        print '<input type="hidden" name="id" value="'.$object->id.'">';

        print '<div class="div-table-responsive-no-min">';
        print '<table class="noborder centpercent">';

        print '<tr class="liste_titre">';
        print '<th colspan="6">';
        print '<span class="fa fa-cubes paddingright"></span>'.$langs->trans('AvailableEquipment');
        print ' <span class="opacitymedium">('.$object->thirdparty->name.')</span>';
        print '</th>';
        print '</tr>';

        print '<tr class="liste_titre">';
        print '<th class="center" width="40"><input type="checkbox" id="checkall" onclick="toggleCheckAll()"></th>';
        print '<th>'.$langs->trans('EquipmentNumber').'</th>';
        print '<th>'.$langs->trans('Label').'</th>';
        print '<th>'.$langs->trans('Type').'</th>';
        print '<th>'.$langs->trans('LocationNote').'</th>';
        print '<th class="center" width="150">'.$langs->trans('Link').'</th>';
        print '</tr>';

        foreach ($available_equipment as $eq_id => $eq) {
            print '<tr class="oddeven">';

            // Checkbox
            print '<td class="center"><input type="checkbox" name="toselect[]" value="'.$eq_id.'" class="equipment-checkbox"></td>';

            // Equipment number
            print '<td><strong>'.$eq->equipment_number.'</strong></td>';

            // Label
            print '<td>'.dol_escape_htmltag($eq->label).'</td>';

            // Type
            print '<td>';
            print isset($type_labels[$eq->equipment_type]) ? $type_labels[$eq->equipment_type] : $eq->equipment_type;
            print '</td>';

            // Location
            print '<td>'.dol_escape_htmltag($eq->location_note).'</td>';

            // Link buttons
            print '<td class="center nowraponall">';
            print '<a class="button buttongen" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=link&equipment_id='.$eq_id.'&link_type=maintenance&token='.newToken().'" title="'.$langs->trans('LinkAsMaintenance').'">';
            print '<span class="fa fa-wrench"></span> '.$langs->trans('MaintenanceShort');
            print '</a> ';
            print '<a class="button buttongen" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=link&equipment_id='.$eq_id.'&link_type=service&token='.newToken().'" title="'.$langs->trans('LinkAsService').'">';
            print '<span class="fa fa-cog"></span> '.$langs->trans('ServiceShort');
            print '</a>';
            print '</td>';

            print '</tr>';
        }

        print '</table>';
        print '</div>';

        // Bulk action buttons
        print '<div class="center" style="margin-top: 10px;">';
        print '<select name="link_type" class="flat">';
        print '<option value="maintenance">'.$langs->trans('MaintenanceWork').'</option>';
        print '<option value="service" selected>'.$langs->trans('ServiceWork').'</option>';
        print '</select> ';
        print '<input type="submit" class="button" value="'.$langs->trans('LinkSelected').'">';
        print '</div>';

        print '</form>';
    } elseif (count($equipment_list) == 0) {
        print '<div class="info">';
        print $langs->trans('NoEquipmentForThisCustomer');
        print '</div>';
    }
}

// JavaScript for copy functionality and checkbox handling
?>
<style>
.button-clipboard {
    background: none;
    border: 1px solid #ccc;
    border-radius: 3px;
    padding: 2px 6px;
    cursor: pointer;
    margin-left: 5px;
    font-size: 11px;
}
.button-clipboard:hover {
    background: #f0f0f0;
}
</style>

<script type="text/javascript">
function copyEquipment(number, label, location) {
    var text = 'Anlage ' + number;
    if (label) text += ' (' + label + ')';
    if (location) text += ' - Standort: ' + location;

    copyToClipboard(text);
}

function copyAllEquipment() {
    var rows = document.querySelectorAll('table tr.oddeven');
    var texts = [];

    // Only get from linked equipment section (first table)
    var linkedTable = document.querySelector('.div-table-responsive-no-min table');
    if (linkedTable) {
        linkedTable.querySelectorAll('tr.oddeven').forEach(function(row) {
            var cells = row.querySelectorAll('td');
            if (cells.length >= 4) {
                var number = cells[0].querySelector('strong')?.textContent || '';
                var label = cells[1].textContent.trim();
                var location = cells[3].textContent.trim();

                var text = 'Anlage ' + number;
                if (label) text += ' (' + label + ')';
                if (location) text += ' - Standort: ' + location;
                texts.push(text);
            }
        });
    }

    if (texts.length > 0) {
        copyToClipboard(texts.join('\n'));
    }
}

function copyToClipboard(text) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(function() {
            showCopyFeedback('<?php echo $langs->trans("CopiedToClipboard"); ?>');
        });
    } else {
        // Fallback for older browsers
        var textarea = document.createElement('textarea');
        textarea.value = text;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        showCopyFeedback('<?php echo $langs->trans("CopiedToClipboard"); ?>');
    }
}

function showCopyFeedback(msg) {
    // Create temporary tooltip
    var tooltip = document.createElement('div');
    tooltip.textContent = msg;
    tooltip.style.cssText = 'position:fixed;top:20px;right:20px;background:#4caf50;color:white;padding:10px 20px;border-radius:4px;z-index:9999;';
    document.body.appendChild(tooltip);
    setTimeout(function() { tooltip.remove(); }, 2000);
}

function toggleCheckAll() {
    var checkAll = document.getElementById('checkall');
    var checkboxes = document.querySelectorAll('.equipment-checkbox');
    checkboxes.forEach(function(cb) {
        cb.checked = checkAll.checked;
    });
}
</script>
<?php

llxFooter();
$db->close();
