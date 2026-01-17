<?php
/* Copyright (C) 2024-2025 Equipment Manager
 * Auto-create Service Orders for due maintenance
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

require_once DOL_DOCUMENT_ROOT.'/fichinter/class/fichinter.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formcompany.class.php';
dol_include_once('/equipmentmanager/class/equipment.class.php');

$langs->loadLangs(array("equipmentmanager@equipmentmanager", "companies", "interventions"));

if (!$user->rights->equipmentmanager->equipment->write) {
    accessforbidden();
}

$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'alpha');
$month = GETPOSTINT('month') ?: (int)date('n');
$year = GETPOSTINT('year') ?: (int)date('Y');

$form = new Form($db);

/*
 * Actions
 */

$created_orders = array();
$errors = array();

if ($action == 'create_orders' && $confirm == 'yes') {
    $db->begin();

    // Get equipment needing service orders
    $sql = "SELECT";
    $sql .= " t.rowid, t.equipment_number, t.label, t.equipment_type,";
    $sql .= " t.fk_soc, t.fk_address, t.fk_contract, t.maintenance_month,";
    $sql .= " s.nom as company_name,";
    $sql .= " CONCAT(sp.lastname, ' ', sp.firstname) as address_label,";
    $sql .= " sp.address, sp.zip, sp.town,";
    $sql .= " c.ref as contract_ref";
    $sql .= " FROM ".MAIN_DB_PREFIX."equipmentmanager_equipment as t";
    $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON t.fk_soc = s.rowid";
    $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."socpeople as sp ON t.fk_address = sp.rowid";
    $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."contrat as c ON t.fk_contract = c.rowid";
    $sql .= " WHERE t.entity IN (".getEntity('equipmentmanager').")";
    $sql .= " AND t.status = 1";
    $sql .= " AND t.fk_contract IS NOT NULL AND t.fk_contract > 0"; // Only with linked contract
    $sql .= " AND t.maintenance_month = ".(int)$month;
    // No open maintenance
    $sql .= " AND NOT EXISTS (";
    $sql .= "   SELECT 1 FROM ".MAIN_DB_PREFIX."equipmentmanager_intervention_link il";
    $sql .= "   INNER JOIN ".MAIN_DB_PREFIX."fichinter f ON il.fk_intervention = f.rowid";
    $sql .= "   WHERE il.fk_equipment = t.rowid";
    $sql .= "   AND il.link_type = 'maintenance'";
    $sql .= "   AND f.fk_statut < 3"; // Not completed
    $sql .= " )";
    // Not completed this year
    $sql .= " AND NOT EXISTS (";
    $sql .= "   SELECT 1 FROM ".MAIN_DB_PREFIX."equipmentmanager_intervention_link il2";
    $sql .= "   INNER JOIN ".MAIN_DB_PREFIX."fichinter f2 ON il2.fk_intervention = f2.rowid";
    $sql .= "   WHERE il2.fk_equipment = t.rowid";
    $sql .= "   AND il2.link_type = 'maintenance'";
    $sql .= "   AND f2.fk_statut = 3";
    $sql .= "   AND YEAR(f2.date_valid) = ".(int)$year;
    $sql .= " )";
    $sql .= " ORDER BY t.fk_soc, t.fk_address, t.equipment_number";

    $resql = $db->query($sql);

    if ($resql) {
        // Group by customer and address
        $grouped = array();
        while ($obj = $db->fetch_object($resql)) {
            $key = $obj->fk_soc.'_'.($obj->fk_address ?: '0');
            if (!isset($grouped[$key])) {
                $grouped[$key] = array(
                    'fk_soc' => $obj->fk_soc,
                    'fk_address' => $obj->fk_address,
                    'company_name' => $obj->company_name,
                    'address_label' => $obj->address_label,
                    'address' => $obj->address,
                    'zip' => $obj->zip,
                    'town' => $obj->town,
                    'equipment' => array()
                );
            }
            $grouped[$key]['equipment'][] = $obj;
        }
        $db->free($resql);

        // Create one service order per customer/address combination
        foreach ($grouped as $key => $data) {
            if (empty($data['fk_soc'])) {
                $errors[] = $langs->trans('EquipmentWithoutCustomer').': '.implode(', ', array_map(function($e) { return $e->equipment_number; }, $data['equipment']));
                continue;
            }

            // Create Fichinter
            $fichinter = new Fichinter($db);
            $fichinter->socid = $data['fk_soc'];
            $fichinter->fk_soc = $data['fk_soc'];
            $fichinter->date = dol_now();
            $fichinter->duree = 0;

            // Get contract from first equipment (all equipment in this group should have same contract)
            $first_contract_id = null;
            foreach ($data['equipment'] as $eq) {
                if ($eq->fk_contract > 0) {
                    $first_contract_id = $eq->fk_contract;
                    break;
                }
            }

            // Build description
            $description = "Jährliche Wartung durchführen\n\n";
            if ($data['address_label']) {
                $description .= $langs->trans('ObjectAddress').': '.$data['address_label'];
                if ($data['town']) {
                    $description .= ', '.$data['town'];
                }
                $description .= "\n";
            }
            $description .= "\n".$langs->trans('Equipment').":\n";
            foreach ($data['equipment'] as $eq) {
                $description .= "- ".$eq->equipment_number." (".$eq->label.")\n";
            }

            $fichinter->description = $description;
            $fichinter->note_private = $langs->trans('AutoCreatedServiceOrder');
            $fichinter->entity = $conf->entity;

            // Set address if available
            if ($data['fk_address'] > 0) {
                $fichinter->fk_address = $data['fk_address'];
            }

            // Link to contract
            if ($first_contract_id > 0) {
                $fichinter->fk_contrat = $first_contract_id;
            }

            $result = $fichinter->create($user);

            if ($result > 0) {
                // Link equipment to intervention
                foreach ($data['equipment'] as $eq) {
                    $sql_link = "INSERT INTO ".MAIN_DB_PREFIX."equipmentmanager_intervention_link";
                    $sql_link .= " (fk_intervention, fk_equipment, link_type, date_creation)";
                    $sql_link .= " VALUES (".(int)$fichinter->id.", ".(int)$eq->rowid.", 'maintenance', NOW())";
                    $db->query($sql_link);
                }

                $created_orders[] = array(
                    'ref' => $fichinter->ref,
                    'id' => $fichinter->id,
                    'company' => $data['company_name'],
                    'address' => $data['address_label'],
                    'equipment_count' => count($data['equipment'])
                );
            } else {
                $errors[] = $langs->trans('ErrorCreatingServiceOrder').': '.$data['company_name'].' - '.$fichinter->error;
            }
        }
    } else {
        $errors[] = $db->lasterror();
    }

    if (count($errors) == 0) {
        $db->commit();
        if (count($created_orders) > 0) {
            setEventMessages($langs->trans('ServiceOrdersCreated', count($created_orders)), null, 'mesgs');
        } else {
            setEventMessages($langs->trans('NoServiceOrdersToCreate'), null, 'warnings');
        }
    } else {
        $db->rollback();
        setEventMessages($errors, null, 'errors');
    }
}

/*
 * View
 */

$title = $langs->trans("AutoCreateServiceOrders");
llxHeader('', $title, '');

print load_fiche_titre($title, '', 'fa-magic');

// Month selector
print '<form method="GET" action="'.$_SERVER["PHP_SELF"].'">';
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td colspan="2">'.$langs->trans('SelectMonth').'</td>';
print '</tr>';
print '<tr class="oddeven">';
print '<td>'.$langs->trans('Month').'</td>';
print '<td>';
print '<select name="month" class="minwidth100">';
for ($m = 1; $m <= 12; $m++) {
    $m_name = dol_print_date(dol_mktime(0, 0, 0, $m, 1, $year), '%B');
    print '<option value="'.$m.'"'.($m == $month ? ' selected' : '').'>'.$m_name.'</option>';
}
print '</select>';
print ' <input type="number" name="year" value="'.$year.'" class="width75" min="2020" max="2050">';
print ' <input type="submit" class="button" value="'.$langs->trans('Refresh').'">';
print '</td>';
print '</tr>';
print '</table>';
print '</div>';
print '</form>';

print '<br>';

// Preview equipment that will get service orders (requires linked contract)
$sql = "SELECT";
$sql .= " t.rowid, t.equipment_number, t.label, t.equipment_type,";
$sql .= " t.fk_soc, t.fk_address, t.fk_contract, t.maintenance_month,";
$sql .= " COALESCE(t.planned_duration, et.default_duration, 0) as effective_duration,";
$sql .= " s.nom as company_name,";
$sql .= " CONCAT(sp.lastname, ' ', sp.firstname) as address_label,";
$sql .= " sp.town,";
$sql .= " c.ref as contract_ref";
$sql .= " FROM ".MAIN_DB_PREFIX."equipmentmanager_equipment as t";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON t.fk_soc = s.rowid";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."socpeople as sp ON t.fk_address = sp.rowid";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."equipmentmanager_equipment_types as et ON t.equipment_type = et.code";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."contrat as c ON t.fk_contract = c.rowid";
$sql .= " WHERE t.entity IN (".getEntity('equipmentmanager').")";
$sql .= " AND t.status = 1";
$sql .= " AND t.fk_contract IS NOT NULL AND t.fk_contract > 0"; // Only with linked contract
$sql .= " AND t.maintenance_month = ".(int)$month;
$sql .= " AND NOT EXISTS (";
$sql .= "   SELECT 1 FROM ".MAIN_DB_PREFIX."equipmentmanager_intervention_link il";
$sql .= "   INNER JOIN ".MAIN_DB_PREFIX."fichinter f ON il.fk_intervention = f.rowid";
$sql .= "   WHERE il.fk_equipment = t.rowid";
$sql .= "   AND il.link_type = 'maintenance'";
$sql .= "   AND f.fk_statut < 3";
$sql .= " )";
$sql .= " AND NOT EXISTS (";
$sql .= "   SELECT 1 FROM ".MAIN_DB_PREFIX."equipmentmanager_intervention_link il2";
$sql .= "   INNER JOIN ".MAIN_DB_PREFIX."fichinter f2 ON il2.fk_intervention = f2.rowid";
$sql .= "   WHERE il2.fk_equipment = t.rowid";
$sql .= "   AND il2.link_type = 'maintenance'";
$sql .= "   AND f2.fk_statut = 3";
$sql .= "   AND YEAR(f2.date_valid) = ".(int)$year;
$sql .= " )";
$sql .= " ORDER BY s.nom, sp.town, t.equipment_number";

$resql = $db->query($sql);

$type_labels = Equipment::getEquipmentTypesTranslated($db, $langs);

// Helper function
function formatDuration($minutes) {
    if ($minutes <= 0) return '-';
    if ($minutes >= 60) {
        $hours = floor($minutes / 60);
        $mins = $minutes % 60;
        return $hours.'h'.($mins > 0 ? ' '.$mins.'min' : '');
    }
    return $minutes.' min';
}

if ($resql) {
    $num = $db->num_rows($resql);

    $month_name = dol_print_date(dol_mktime(0, 0, 0, $month, 1, $year), '%B %Y');

    print '<div class="div-table-responsive-no-min">';
    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre">';
    print '<th colspan="6">';
    print $langs->trans('EquipmentNeedingServiceOrder').' - '.$month_name;
    print ' <span class="badge badge-status4" style="margin-left: 10px;">'.$num.' '.$langs->trans('Equipment').'</span>';
    print '</th>';
    print '</tr>';

    if ($num > 0) {
        print '<tr class="liste_titre">';
        print '<th>'.$langs->trans('Customer').'</th>';
        print '<th>'.$langs->trans('ObjectAddress').'</th>';
        print '<th>'.$langs->trans('EquipmentNumber').'</th>';
        print '<th>'.$langs->trans('Type').'</th>';
        print '<th>'.$langs->trans('Label').'</th>';
        print '<th class="center">'.$langs->trans('PlannedDuration').'</th>';
        print '</tr>';

        $total_duration = 0;
        while ($obj = $db->fetch_object($resql)) {
            print '<tr class="oddeven">';
            print '<td>'.dol_escape_htmltag($obj->company_name).'</td>';
            print '<td>'.dol_escape_htmltag($obj->address_label).($obj->town ? ' ('.$obj->town.')' : '').'</td>';
            print '<td><strong>'.dol_escape_htmltag($obj->equipment_number).'</strong></td>';
            print '<td>'.(isset($type_labels[$obj->equipment_type]) ? $type_labels[$obj->equipment_type] : $obj->equipment_type).'</td>';
            print '<td>'.dol_escape_htmltag($obj->label).'</td>';
            print '<td class="center">'.formatDuration($obj->effective_duration).'</td>';
            print '</tr>';
            $total_duration += (int)$obj->effective_duration;
        }

        print '<tr class="liste_total">';
        print '<td colspan="5" class="right"><strong>'.$langs->trans('Total').'</strong></td>';
        print '<td class="center"><strong>'.formatDuration($total_duration).'</strong></td>';
        print '</tr>';

        print '</table>';
        print '</div>';

        // Create button
        print '<br>';
        print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'?month='.$month.'&year='.$year.'">';
        print '<input type="hidden" name="token" value="'.newToken().'">';
        print '<input type="hidden" name="action" value="create_orders">';
        print '<input type="hidden" name="confirm" value="yes">';
        print '<div class="center">';
        print '<input type="submit" class="button button-save" value="'.$langs->trans('CreateServiceOrders').'" ';
        print 'onclick="return confirm(\''.$langs->trans('ConfirmCreateServiceOrders', $num).'\');">';
        print '</div>';
        print '</form>';

    } else {
        print '<tr class="oddeven">';
        print '<td colspan="6" class="center opacitymedium">';
        print '<span class="fa fa-check-circle" style="color: #4caf50; font-size: 24px;"></span><br>';
        print $langs->trans('NoEquipmentNeedsServiceOrder');
        print '</td>';
        print '</tr>';
        print '</table>';
        print '</div>';
    }
    $db->free($resql);
} else {
    dol_print_error($db);
}

// Show created orders
if (count($created_orders) > 0) {
    print '<br><br>';
    print '<div class="div-table-responsive-no-min">';
    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre">';
    print '<th colspan="4">'.$langs->trans('CreatedServiceOrders').'</th>';
    print '</tr>';
    print '<tr class="liste_titre">';
    print '<th>'.$langs->trans('Ref').'</th>';
    print '<th>'.$langs->trans('Customer').'</th>';
    print '<th>'.$langs->trans('ObjectAddress').'</th>';
    print '<th class="center">'.$langs->trans('Equipment').'</th>';
    print '</tr>';

    foreach ($created_orders as $order) {
        print '<tr class="oddeven">';
        print '<td><a href="'.DOL_URL_ROOT.'/fichinter/card.php?id='.$order['id'].'">'.$order['ref'].'</a></td>';
        print '<td>'.dol_escape_htmltag($order['company']).'</td>';
        print '<td>'.dol_escape_htmltag($order['address']).'</td>';
        print '<td class="center">'.$order['equipment_count'].'</td>';
        print '</tr>';
    }

    print '</table>';
    print '</div>';
}

// Links
print '<br>';
print '<div class="center">';
print '<a class="button" href="'.DOL_URL_ROOT.'/custom/equipmentmanager/maintenance_dashboard.php">';
print '<span class="fa fa-list"></span> '.$langs->trans('MaintenanceDashboard');
print '</a> ';
print '<a class="button" href="'.DOL_URL_ROOT.'/custom/equipmentmanager/maintenance_calendar.php">';
print '<span class="fa fa-calendar"></span> '.$langs->trans('MaintenanceCalendar');
print '</a>';
print '</div>';

llxFooter();
$db->close();
