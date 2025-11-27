<?php
/* Copyright (C) 2024 Equipment Manager */

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

$langs->loadLangs(array("equipmentmanager@equipmentmanager", "companies"));

if (!$user->rights->equipmentmanager->equipment->read) {
    accessforbidden();
}

$action = GETPOST('action', 'aZ09');
$massaction = GETPOST('massaction', 'alpha');
$confirm = GETPOST('confirm', 'alpha');
$toselect = GETPOST('toselect', 'array');
$contextpage = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : 'equipmentlist';
$backtopage = GETPOST('backtopage', 'alpha');

$sall = trim((GETPOST('search_all', 'alphanohtml') != '') ? GETPOST('search_all', 'alphanohtml') : GETPOST('sall', 'alphanohtml'));
$search_equipment_number = GETPOST('search_equipment_number', 'alpha');
$search_type = GETPOST('search_type', 'alpha');
$search_manufacturer = GETPOST('search_manufacturer', 'alpha');
$search_label = GETPOST('search_label', 'alpha');
$search_location = GETPOST('search_location', 'alpha');
$search_company = GETPOST('search_company', 'alpha');
$search_status = GETPOST('search_status', 'int');

$limit = GETPOST('limit', 'int') ? GETPOST('limit', 'int') : $conf->liste_limit;
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
$page = GETPOSTISSET('pageplusone') ? (GETPOST('pageplusone') - 1) : GETPOST("page", 'int');
if (empty($page) || $page == -1) {
    $page = 0;
}
$offset = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;
if (!$sortfield) {
    $sortfield = "t.equipment_number";
}
if (!$sortorder) {
    $sortorder = "ASC";
}

$object = new stdClass();
$extrafields = new ExtraFields($db);
$form = new Form($db);
$formcompany = new FormCompany($db);

$title = $langs->trans("EquipmentList");
$help_url = '';

llxHeader('', $title, $help_url);

// Build SQL
$sql = "SELECT";
$sql .= " t.rowid,";
$sql .= " t.ref,";
$sql .= " t.equipment_number,";
$sql .= " t.equipment_type,";
$sql .= " t.manufacturer,";
$sql .= " t.label,";
$sql .= " t.fk_soc,";
$sql .= " t.location_note,";
$sql .= " t.status,";
$sql .= " s.nom as company_name";
$sql .= " FROM ".MAIN_DB_PREFIX."equipmentmanager_equipment as t";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON t.fk_soc = s.rowid";
$sql .= " WHERE t.entity IN (".getEntity('equipmentmanager').")";

// Search filters
if ($search_equipment_number) {
    $sql .= " AND t.equipment_number LIKE '%".$db->escape($search_equipment_number)."%'";
}
if ($search_type) {
    $sql .= " AND t.equipment_type = '".$db->escape($search_type)."'";
}
if ($search_manufacturer) {
    $sql .= " AND t.manufacturer LIKE '%".$db->escape($search_manufacturer)."%'";
}
if ($search_label) {
    $sql .= " AND t.label LIKE '%".$db->escape($search_label)."%'";
}
if ($search_location) {
    $sql .= " AND t.location_note LIKE '%".$db->escape($search_location)."%'";
}
if ($search_company) {
    $sql .= " AND s.nom LIKE '%".$db->escape($search_company)."%'";
}
if ($search_status != '' && $search_status >= 0) {
    $sql .= " AND t.status = ".(int)$search_status;
}

// Count total records
$nbtotalofrecords = '';
if (empty($conf->global->MAIN_DISABLE_FULL_SCANLIST)) {
    $resql = $db->query($sql);
    $nbtotalofrecords = $db->num_rows($resql);
    if (($page * $limit) > $nbtotalofrecords) {
        $page = 0;
        $offset = 0;
    }
}

// Sort
$sql .= $db->order($sortfield, $sortorder);

// Limit
$sql .= $db->plimit($limit + 1, $offset);

$resql = $db->query($sql);
if ($resql) {
    $num = $db->num_rows($resql);
    
    $param = '';
    if (!empty($contextpage) && $contextpage != $_SERVER["PHP_SELF"]) {
        $param .= '&contextpage='.urlencode($contextpage);
    }
    if ($limit > 0 && $limit != $conf->liste_limit) {
        $param .= '&limit='.urlencode($limit);
    }
    
    $newcardbutton = '';
    if ($user->rights->equipmentmanager->equipment->write) {
        $newcardbutton = dolGetButtonTitle($langs->trans('NewEquipment'), '', 'fa fa-plus-circle', DOL_URL_ROOT.'/custom/equipmentmanager/equipment_card.php?action=create', '', 1);
    }
    
    print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
    print '<input type="hidden" name="token" value="'.newToken().'">';
    print '<input type="hidden" name="sortfield" value="'.$sortfield.'">';
    print '<input type="hidden" name="sortorder" value="'.$sortorder.'">';
    
    print_barre_liste($title, $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, '', $num, $nbtotalofrecords, 'object_generic', 0, $newcardbutton, '', $limit, 0, 0, 1);
    
    print '<div class="div-table-responsive">';
    print '<table class="tagtable liste">'."\n";
    
    // Fields title - Neue Reihenfolge: Anlagennummer, Typ, Hersteller, Bezeichnung, Standort, Kunde, Wartungsvertrag
    print '<tr class="liste_titre">';
    print_liste_field_titre("EquipmentNumber", $_SERVER["PHP_SELF"], "t.equipment_number", "", $param, '', $sortfield, $sortorder);
    print_liste_field_titre("Type", $_SERVER["PHP_SELF"], "t.equipment_type", "", $param, '', $sortfield, $sortorder);
    print_liste_field_titre("Manufacturer", $_SERVER["PHP_SELF"], "t.manufacturer", "", $param, '', $sortfield, $sortorder);
    print_liste_field_titre("Label", $_SERVER["PHP_SELF"], "t.label", "", $param, '', $sortfield, $sortorder);
    print_liste_field_titre("LocationNote", $_SERVER["PHP_SELF"], "t.location_note", "", $param, '', $sortfield, $sortorder);
    print_liste_field_titre("ThirdParty", $_SERVER["PHP_SELF"], "s.nom", "", $param, '', $sortfield, $sortorder);
    print_liste_field_titre("MaintenanceContract", $_SERVER["PHP_SELF"], "t.status", "", $param, '', $sortfield, $sortorder, 'center ');
    print_liste_field_titre('', $_SERVER["PHP_SELF"], "", '', '', '', $sortfield, $sortorder, 'center maxwidthsearch ');
    print "</tr>\n";
    
    // Fields title search
    print '<tr class="liste_titre">';
    
    // Equipment Number
    print '<td class="liste_titre"><input type="text" class="flat maxwidth75" name="search_equipment_number" value="'.dol_escape_htmltag($search_equipment_number).'"></td>';
    
    // Type
    print '<td class="liste_titre">';
    print '<select class="flat maxwidth100" name="search_type">';
    print '<option value=""></option>';
    print '<option value="door_swing"'.($search_type == 'door_swing' ? ' selected' : '').'>'.$langs->trans('DoorSwing').'</option>';
    print '<option value="door_sliding"'.($search_type == 'door_sliding' ? ' selected' : '').'>'.$langs->trans('DoorSliding').'</option>';
    print '<option value="fire_door"'.($search_type == 'fire_door' ? ' selected' : '').'>'.$langs->trans('FireDoor').'</option>';
    print '<option value="door_closer"'.($search_type == 'door_closer' ? ' selected' : '').'>'.$langs->trans('DoorCloser').'</option>';
    print '<option value="hold_open"'.($search_type == 'hold_open' ? ' selected' : '').'>'.$langs->trans('HoldOpen').'</option>';
    print '<option value="rws"'.($search_type == 'rws' ? ' selected' : '').'>'.$langs->trans('RWS').'</option>';
    print '<option value="rwa"'.($search_type == 'rwa' ? ' selected' : '').'>'.$langs->trans('RWA').'</option>';
    print '<option value="other"'.($search_type == 'other' ? ' selected' : '').'>'.$langs->trans('Other').'</option>';
    print '</select>';
    print '</td>';
    
    // Manufacturer
    print '<td class="liste_titre"><input type="text" class="flat maxwidth75" name="search_manufacturer" value="'.dol_escape_htmltag($search_manufacturer).'"></td>';
    
    // Label
    print '<td class="liste_titre"><input type="text" class="flat maxwidth100" name="search_label" value="'.dol_escape_htmltag($search_label).'"></td>';
    
    // Location
    print '<td class="liste_titre"><input type="text" class="flat maxwidth75" name="search_location" value="'.dol_escape_htmltag($search_location).'"></td>';
    
    // Company
    print '<td class="liste_titre"><input type="text" class="flat maxwidth75" name="search_company" value="'.dol_escape_htmltag($search_company).'"></td>';
    
    // Status
    print '<td class="liste_titre center">';
    print '<select class="flat maxwidth75" name="search_status">';
    print '<option value=""></option>';
    print '<option value="1"'.($search_status === '1' ? ' selected' : '').'>'.$langs->trans('ActiveContract').'</option>';
    print '<option value="0"'.($search_status === '0' ? ' selected' : '').'>'.$langs->trans('NoContract').'</option>';
    print '</select>';
    print '</td>';
    
    print '<td class="liste_titre center">';
    $searchpicto = $form->showFilterButtons();
    print $searchpicto;
    print '</td>';
    print '</tr>';
    
    // Type labels array
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
    
    // Lines
    $i = 0;
    while ($i < min($num, $limit)) {
        $obj = $db->fetch_object($resql);
        
        print '<tr class="oddeven">';
        
        // Equipment Number (clickable)
        print '<td>';
        print '<a href="'.DOL_URL_ROOT.'/custom/equipmentmanager/equipment_card.php?id='.$obj->rowid.'">';
        print '<strong>'.$obj->equipment_number.'</strong>';
        print '</a>';
        print '</td>';
        
        // Type
        print '<td>';
        if (isset($type_labels[$obj->equipment_type])) {
            print $type_labels[$obj->equipment_type];
        } else {
            print dol_escape_htmltag($obj->equipment_type);
        }
        print '</td>';
        
        // Manufacturer
        print '<td>'.dol_escape_htmltag($obj->manufacturer).'</td>';
        
        // Label
        print '<td>'.dol_escape_htmltag($obj->label).'</td>';
        
        // Location
        print '<td>';
        $location_short = dol_trunc($obj->location_note, 50);
        print dol_escape_htmltag($location_short);
        print '</td>';
        
        // Company
        print '<td>';
        if ($obj->fk_soc > 0) {
            $companystatic = new Societe($db);
            $companystatic->id = $obj->fk_soc;
            $companystatic->name = $obj->company_name;
            print $companystatic->getNomUrl(1);
        }
        print '</td>';
        
        // Maintenance Contract Status
        print '<td class="center">';
        if ($obj->status == 1) {
            print '<span class="badge badge-status4 badge-status">'.$langs->trans('ActiveContract').'</span>';
        } else {
            print '<span class="badge badge-status8 badge-status">'.$langs->trans('NoContract').'</span>';
        }
        print '</td>';
        
        // Actions
        print '<td class="center">';
        print '<a class="editfielda" href="'.DOL_URL_ROOT.'/custom/equipmentmanager/equipment_card.php?id='.$obj->rowid.'&action=edit">';
        print img_edit();
        print '</a>';
        print '</td>';
        
        print '</tr>';
        $i++;
    }
    
    if ($num == 0) {
        $colspan = 8;
        print '<tr><td colspan="'.$colspan.'"><span class="opacitymedium">'.$langs->trans("NoRecordFound").'</span></td></tr>';
    }
    
    print "</table>";
    print '</div>';
    
    print '</form>';
    
    $db->free($resql);
} else {
    dol_print_error($db);
}

llxFooter();
$db->close();