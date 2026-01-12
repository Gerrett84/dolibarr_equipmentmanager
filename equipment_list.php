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
dol_include_once('/equipmentmanager/class/equipment.class.php');

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
$search_address = GETPOST('search_address', 'alpha');
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
$sql .= " t.fk_address,";
$sql .= " t.location_note,";
$sql .= " t.status,";
$sql .= " s.nom as company_name,";
$sql .= " CONCAT(sp.lastname, ' ', sp.firstname) as address_label,";
$sql .= " sp.town as address_town,";
$sql .= " sp.zip as address_zip";
$sql .= " FROM ".MAIN_DB_PREFIX."equipmentmanager_equipment as t";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON t.fk_soc = s.rowid";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."socpeople as sp ON t.fk_address = sp.rowid";
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
if ($search_address) {
    $sql .= " AND (";
    $sql .= "CONCAT(sp.lastname, ' ', sp.firstname) LIKE '%".$db->escape($search_address)."%'";
    $sql .= " OR sp.town LIKE '%".$db->escape($search_address)."%'";
    $sql .= " OR sp.zip LIKE '%".$db->escape($search_address)."%'";
    $sql .= " OR sp.address LIKE '%".$db->escape($search_address)."%'";
    $sql .= ")";
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
        $newcardbutton = dolGetButtonTitle($langs->trans('NewEquipment'), '', 'fa fa-plus-circle', DOL_URL_ROOT.'/custom/equipmentmanager/equipment_edit.php?action=create', '', 1);
    }
    
    print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
    print '<input type="hidden" name="token" value="'.newToken().'">';
    print '<input type="hidden" name="sortfield" value="'.$sortfield.'">';
    print '<input type="hidden" name="sortorder" value="'.$sortorder.'">';
    
    print_barre_liste($title, $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, '', $num, $nbtotalofrecords, 'object_generic', 0, $newcardbutton, '', $limit, 0, 0, 1);
    
    print '<div class="div-table-responsive">';
    print '<table class="tagtable liste">'."\n";
    
    // Fields title
    print '<tr class="liste_titre">';
    print_liste_field_titre("EquipmentNumber", $_SERVER["PHP_SELF"], "t.equipment_number", "", $param, '', $sortfield, $sortorder);
    print_liste_field_titre("Type", $_SERVER["PHP_SELF"], "t.equipment_type", "", $param, '', $sortfield, $sortorder);
    print_liste_field_titre("Manufacturer", $_SERVER["PHP_SELF"], "t.manufacturer", "", $param, '', $sortfield, $sortorder);
    print_liste_field_titre("Label", $_SERVER["PHP_SELF"], "t.label", "", $param, '', $sortfield, $sortorder);
    print_liste_field_titre("ObjectAddress", $_SERVER["PHP_SELF"], "sp.town", "", $param, '', $sortfield, $sortorder);
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
    // Use dynamic types from database
    $search_types = Equipment::getEquipmentTypesTranslated($db, $langs, false);
    foreach ($search_types as $code => $label) {
        print '<option value="'.$code.'"'.($search_type == $code ? ' selected' : '').'>'.dol_escape_htmltag($label).'</option>';
    }
    print '</select>';
    print '</td>';
    
    // Manufacturer
    print '<td class="liste_titre"><input type="text" class="flat maxwidth75" name="search_manufacturer" value="'.dol_escape_htmltag($search_manufacturer).'"></td>';
    
    // Label
    print '<td class="liste_titre"><input type="text" class="flat maxwidth100" name="search_label" value="'.dol_escape_htmltag($search_label).'"></td>';
    
    // Object Address - NEU: Suchfeld hinzugefügt
    print '<td class="liste_titre"><input type="text" class="flat maxwidth100" name="search_address" value="'.dol_escape_htmltag($search_address).'" placeholder="'.$langs->trans('Name, Town, ZIP').'"></td>';
    
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
    
    // Type labels (dynamic from database)
    $type_labels = Equipment::getEquipmentTypesTranslated($db, $langs);

    // Lines
    $i = 0;
    while ($i < min($num, $limit)) {
        $obj = $db->fetch_object($resql);
        
        print '<tr class="oddeven">';
        
        // Equipment Number (clickable)
        print '<td>';
        print '<a href="'.DOL_URL_ROOT.'/custom/equipmentmanager/equipment_view.php?id='.$obj->rowid.'">';
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
        
        // Object Address
        print '<td>';
        if ($obj->address_label) {
            print '<strong>'.dol_escape_htmltag($obj->address_label).'</strong>';
            if ($obj->address_zip || $obj->address_town) {
                print '<br><span class="opacitymedium">';
                if ($obj->address_zip) print dol_escape_htmltag($obj->address_zip).' ';
                if ($obj->address_town) print dol_escape_htmltag($obj->address_town);
                print '</span>';
            }
        } else {
            print '<span class="opacitymedium">-</span>';
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
        print '<a class="editfielda" href="'.DOL_URL_ROOT.'/custom/equipmentmanager/equipment_edit.php?id='.$obj->rowid.'">';
        print img_edit();
        print '</a>';
        print '</td>';
        
        print '</tr>';
        $i++;
    }
    
    if ($num == 0) {
        $colspan = 7;
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