<?php
/* Copyright (C) 2024-2025 Equipment Manager
 * Maintenance Calendar - v4.0
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

// Parameters
$year = GETPOSTINT('year') ?: (int)date('Y');
$month = GETPOSTINT('month') ?: (int)date('n');

// Navigation
$prev_month = $month - 1;
$prev_year = $year;
if ($prev_month < 1) {
    $prev_month = 12;
    $prev_year--;
}

$next_month = $month + 1;
$next_year = $year;
if ($next_month > 12) {
    $next_month = 1;
    $next_year++;
}

$form = new Form($db);

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

$title = $langs->trans("MaintenanceCalendar");
llxHeader('', $title, '');

print load_fiche_titre($title, '', 'fa-calendar');

// Calendar navigation
$month_name = dol_print_date(dol_mktime(0, 0, 0, $month, 1, $year), '%B %Y');

print '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">';
print '<a class="button" href="'.$_SERVER["PHP_SELF"].'?year='.$prev_year.'&month='.$prev_month.'">';
print '<span class="fa fa-chevron-left"></span> '.$langs->trans("Previous");
print '</a>';
print '<h2 style="margin: 0;">'.$month_name.'</h2>';
print '<a class="button" href="'.$_SERVER["PHP_SELF"].'?year='.$next_year.'&month='.$next_month.'">';
print $langs->trans("Next").' <span class="fa fa-chevron-right"></span>';
print '</a>';
print '</div>';

// Quick navigation
print '<div style="text-align: center; margin-bottom: 20px;">';
print '<a class="button smallpaddingimp" href="'.$_SERVER["PHP_SELF"].'?year='.date('Y').'&month='.date('n').'">'.$langs->trans("Today").'</a> ';
for ($m = 1; $m <= 12; $m++) {
    $m_name = dol_print_date(dol_mktime(0, 0, 0, $m, 1, $year), '%b');
    $class = ($m == $month) ? 'button-primary' : 'button';
    print '<a class="button smallpaddingimp'.($m == $month ? ' butActionDelete' : '').'" href="'.$_SERVER["PHP_SELF"].'?year='.$year.'&month='.$m.'">'.$m_name.'</a> ';
}
print '</div>';

// Get maintenance data for this month
// Calculate the "opposite" month for semi-annual check (6 months offset)
$semi_annual_month = $month > 6 ? $month - 6 : $month + 6;

$sql = "SELECT";
$sql .= " t.rowid, t.equipment_number, t.label, t.equipment_type, t.maintenance_month, t.maintenance_interval,";
$sql .= " t.fk_soc, t.fk_address,";
$sql .= " COALESCE(t.planned_duration, et.default_duration, 0) as effective_duration,";
$sql .= " s.nom as company_name,";
$sql .= " CONCAT(sp.lastname, ' ', sp.firstname) as address_label,";
$sql .= " sp.town as address_town,";
$sql .= " (SELECT COUNT(*) FROM ".MAIN_DB_PREFIX."equipmentmanager_intervention_link il";
$sql .= "  INNER JOIN ".MAIN_DB_PREFIX."fichinter f ON il.fk_intervention = f.rowid";
$sql .= "  WHERE il.fk_equipment = t.rowid AND il.link_type = 'maintenance'";
$sql .= "  AND f.fk_statut >= 1 AND f.fk_statut < 3) as has_open_maintenance,";
$sql .= " (SELECT COUNT(*) FROM ".MAIN_DB_PREFIX."equipmentmanager_intervention_link il2";
$sql .= "  INNER JOIN ".MAIN_DB_PREFIX."fichinter f2 ON il2.fk_intervention = f2.rowid";
$sql .= "  WHERE il2.fk_equipment = t.rowid AND il2.link_type = 'maintenance'";
$sql .= "  AND f2.fk_statut = 3 AND YEAR(f2.date_valid) = ".(int)$year;
$sql .= "  AND MONTH(f2.date_valid) = ".(int)$month.") as is_completed";
$sql .= " FROM ".MAIN_DB_PREFIX."equipmentmanager_equipment as t";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON t.fk_soc = s.rowid";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."socpeople as sp ON t.fk_address = sp.rowid";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."equipmentmanager_equipment_types as et ON t.equipment_type = et.code";
$sql .= " WHERE t.entity IN (".getEntity('equipmentmanager').")";
$sql .= " AND t.status = 1";
// Match maintenance month: direct match OR semi-annual with offset month
$sql .= " AND (t.maintenance_month = ".(int)$month;
$sql .= "      OR (t.maintenance_interval = 'semi_annual' AND t.maintenance_month = ".(int)$semi_annual_month."))";
$sql .= " ORDER BY sp.town, sp.lastname, t.equipment_number";

$resql = $db->query($sql);

$type_labels = Equipment::getEquipmentTypesTranslated($db, $langs);

// Statistics
$total_equipment = 0;
$total_duration = 0;
$completed_count = 0;
$in_progress_count = 0;
$pending_count = 0;

// Group by address
$by_address = array();

if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        $total_equipment++;
        $total_duration += (int)$obj->effective_duration;

        if ($obj->is_completed > 0) {
            $completed_count++;
            $obj->status_class = 'completed';
        } elseif ($obj->has_open_maintenance > 0) {
            $in_progress_count++;
            $obj->status_class = 'in_progress';
        } else {
            $pending_count++;
            $obj->status_class = 'pending';
        }

        $address_key = $obj->fk_address ?: 'no_address';
        if (!isset($by_address[$address_key])) {
            $by_address[$address_key] = array(
                'label' => $obj->address_label ?: $langs->trans('NoAddress'),
                'town' => $obj->address_town,
                'company' => $obj->company_name,
                'fk_soc' => $obj->fk_soc,
                'equipment' => array(),
                'duration' => 0
            );
        }
        $by_address[$address_key]['equipment'][] = $obj;
        $by_address[$address_key]['duration'] += (int)$obj->effective_duration;
    }
    $db->free($resql);
}

// Summary cards
print '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; margin-bottom: 20px;">';

print '<div style="background: #2196f3; color: white; padding: 15px; border-radius: 5px; text-align: center;">';
print '<div style="font-size: 28px; font-weight: bold;">'.$total_equipment.'</div>';
print '<div>'.$langs->trans('TotalEquipment').'</div>';
print '<div style="font-size: 0.85em; opacity: 0.9; margin-top: 5px;"><span class="fa fa-clock-o"></span> '.formatDuration($total_duration).'</div>';
print '</div>';

print '<div style="background: #f44336; color: white; padding: 15px; border-radius: 5px; text-align: center;">';
print '<div style="font-size: 28px; font-weight: bold;">'.$pending_count.'</div>';
print '<div>'.$langs->trans('Pending').'</div>';
print '</div>';

print '<div style="background: #ff9800; color: white; padding: 15px; border-radius: 5px; text-align: center;">';
print '<div style="font-size: 28px; font-weight: bold;">'.$in_progress_count.'</div>';
print '<div>'.$langs->trans('InProgress').'</div>';
print '</div>';

print '<div style="background: #4caf50; color: white; padding: 15px; border-radius: 5px; text-align: center;">';
print '<div style="font-size: 28px; font-weight: bold;">'.$completed_count.'</div>';
print '<div>'.$langs->trans('Completed').'</div>';
print '</div>';

print '</div>';

// Calendar view style
print '<style>
.calendar-card {
    border: 1px solid var(--colortext, #ccc);
    border-radius: 8px;
    margin-bottom: 15px;
    overflow: hidden;
}
.calendar-card-header {
    padding: 10px 15px;
    font-weight: bold;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.calendar-card-header.pending { background: rgba(244, 67, 54, 0.1); border-left: 4px solid #f44336; }
.calendar-card-header.in_progress { background: rgba(255, 152, 0, 0.1); border-left: 4px solid #ff9800; }
.calendar-card-header.completed { background: rgba(76, 175, 80, 0.1); border-left: 4px solid #4caf50; }
.calendar-equipment {
    padding: 8px 15px;
    border-top: 1px solid var(--colortext, #eee);
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.calendar-equipment:hover {
    background: rgba(0,0,0,0.03);
}
.calendar-equipment.completed {
    opacity: 0.6;
    text-decoration: line-through;
}
.status-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    display: inline-block;
    margin-right: 8px;
}
.status-dot.pending { background: #f44336; }
.status-dot.in_progress { background: #ff9800; }
.status-dot.completed { background: #4caf50; }
</style>';

// Display by address
if (count($by_address) > 0) {
    foreach ($by_address as $address_key => $address_data) {
        // Determine overall status for card
        $has_pending = false;
        $has_in_progress = false;
        $all_completed = true;
        foreach ($address_data['equipment'] as $eq) {
            if ($eq->status_class == 'pending') $has_pending = true;
            if ($eq->status_class == 'in_progress') $has_in_progress = true;
            if ($eq->status_class != 'completed') $all_completed = false;
        }

        if ($all_completed) {
            $card_status = 'completed';
        } elseif ($has_in_progress) {
            $card_status = 'in_progress';
        } else {
            $card_status = 'pending';
        }

        print '<div class="calendar-card">';
        print '<div class="calendar-card-header '.$card_status.'">';
        print '<div>';
        print '<span class="fa fa-map-marker"></span> ';
        print dol_escape_htmltag($address_data['label']);
        if ($address_data['town']) {
            print ' <span class="opacitymedium">('.dol_escape_htmltag($address_data['town']).')</span>';
        }
        if ($address_data['company']) {
            print ' - '.dol_escape_htmltag($address_data['company']);
        }
        print '</div>';
        print '<div>';
        print '<span class="badge" style="background: #607d8b; color: white;">'.count($address_data['equipment']).' '.$langs->trans('Equipment').'</span> ';
        if ($address_data['duration'] > 0) {
            print '<span class="badge" style="background: #455a64; color: white;"><span class="fa fa-clock-o"></span> '.formatDuration($address_data['duration']).'</span>';
        }
        print '</div>';
        print '</div>';

        foreach ($address_data['equipment'] as $equip) {
            print '<div class="calendar-equipment '.($equip->status_class == 'completed' ? 'completed' : '').'">';
            print '<div>';
            print '<span class="status-dot '.$equip->status_class.'"></span>';
            print '<a href="'.DOL_URL_ROOT.'/custom/equipmentmanager/equipment_view.php?id='.$equip->rowid.'">';
            print '<strong>'.$equip->equipment_number.'</strong>';
            print '</a>';
            print ' - ';
            if (isset($type_labels[$equip->equipment_type])) {
                print $type_labels[$equip->equipment_type];
            } else {
                print dol_escape_htmltag($equip->equipment_type);
            }
            print ' - '.dol_escape_htmltag($equip->label);
            print '</div>';
            print '<div style="text-align: right; min-width: 80px;">';
            if ($equip->effective_duration > 0) {
                print '<span class="opacitymedium">'.formatDuration($equip->effective_duration).'</span>';
            }
            print '</div>';
            print '</div>';
        }

        print '</div>';
    }
} else {
    print '<div class="info" style="padding: 30px; text-align: center;">';
    print '<span class="fa fa-calendar-o" style="font-size: 48px; color: #ccc;"></span>';
    print '<h3>'.$langs->trans('NoMaintenanceThisMonth').'</h3>';
    print '</div>';
}

// Link to dashboard
print '<br>';
print '<div class="center">';
print '<a class="button" href="'.DOL_URL_ROOT.'/custom/equipmentmanager/maintenance_dashboard.php">';
print '<span class="fa fa-list"></span> '.$langs->trans('MaintenanceDashboard');
print '</a>';
print '</div>';

llxFooter();
$db->close();
