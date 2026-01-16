<?php
/* Copyright (C) 2024-2025 Equipment Manager
 * Maintenance Map View - v4.0
 * Uses OpenStreetMap + Leaflet.js
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
$month = GETPOSTINT('month') ?: (int)date('n');
$year = GETPOSTINT('year') ?: (int)date('Y');
$show_all = GETPOST('show_all', 'int');

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

$title = $langs->trans("MaintenanceMap");
llxHeader('', $title, '');

print load_fiche_titre($title, '', 'fa-map-marker');

// Filters
print '<form method="GET" action="'.$_SERVER["PHP_SELF"].'">';
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td colspan="4">'.$langs->trans('Filter').'</td>';
print '</tr>';
print '<tr class="oddeven">';
print '<td>'.$langs->trans('Month').'</td>';
print '<td>';
print '<select name="month" class="minwidth100">';
print '<option value="0"'.(!$month ? ' selected' : '').'>'.$langs->trans('AllMonths').'</option>';
for ($m = 1; $m <= 12; $m++) {
    $m_name = dol_print_date(dol_mktime(0, 0, 0, $m, 1, $year), '%B');
    print '<option value="'.$m.'"'.($m == $month ? ' selected' : '').'>'.$m_name.'</option>';
}
print '</select>';
print ' <input type="number" name="year" value="'.$year.'" class="width75" min="2020" max="2050">';
print '</td>';
print '<td>';
print '<label><input type="checkbox" name="show_all" value="1"'.($show_all ? ' checked' : '').'> '.$langs->trans('ShowAllEquipment').'</label>';
print '</td>';
print '<td>';
print '<input type="submit" class="button" value="'.$langs->trans('Refresh').'">';
print '</td>';
print '</tr>';
print '</table>';
print '</div>';
print '</form>';

print '<br>';

// Get addresses with equipment
$sql = "SELECT DISTINCT";
$sql .= " sp.rowid as address_id,";
$sql .= " CONCAT(sp.lastname, ' ', sp.firstname) as address_label,";
$sql .= " sp.address, sp.zip, sp.town, sp.fk_pays,";
$sql .= " sp.latitude, sp.longitude,";
$sql .= " s.nom as company_name,";
$sql .= " s.rowid as company_id";
$sql .= " FROM ".MAIN_DB_PREFIX."equipmentmanager_equipment as t";
$sql .= " INNER JOIN ".MAIN_DB_PREFIX."socpeople as sp ON t.fk_address = sp.rowid";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON t.fk_soc = s.rowid";
$sql .= " WHERE t.entity IN (".getEntity('equipmentmanager').")";
$sql .= " AND t.status = 1";
$sql .= " AND t.fk_address IS NOT NULL AND t.fk_address > 0";
if (!$show_all) {
    $sql .= " AND t.maintenance_contract = 1";
    if ($month > 0) {
        $sql .= " AND t.maintenance_month = ".(int)$month;
    }
}
$sql .= " ORDER BY sp.town, sp.lastname";

$resql = $db->query($sql);

$locations = array();
$addresses_without_coords = array();

if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        // Get equipment count and details for this address
        $sql2 = "SELECT";
        $sql2 .= " t.rowid, t.equipment_number, t.label, t.equipment_type, t.maintenance_month,";
        $sql2 .= " COALESCE(t.planned_duration, et.default_duration, 0) as effective_duration,";
        $sql2 .= " (SELECT COUNT(*) FROM ".MAIN_DB_PREFIX."equipmentmanager_intervention_link il";
        $sql2 .= "  INNER JOIN ".MAIN_DB_PREFIX."fichinter f ON il.fk_intervention = f.rowid";
        $sql2 .= "  WHERE il.fk_equipment = t.rowid AND il.link_type = 'maintenance'";
        $sql2 .= "  AND f.fk_statut >= 1 AND f.fk_statut < 3) as has_open_maintenance,";
        $sql2 .= " (SELECT COUNT(*) FROM ".MAIN_DB_PREFIX."equipmentmanager_intervention_link il2";
        $sql2 .= "  INNER JOIN ".MAIN_DB_PREFIX."fichinter f2 ON il2.fk_intervention = f2.rowid";
        $sql2 .= "  WHERE il2.fk_equipment = t.rowid AND il2.link_type = 'maintenance'";
        $sql2 .= "  AND f2.fk_statut = 3 AND YEAR(f2.date_valid) = ".(int)$year.") as is_completed";
        $sql2 .= " FROM ".MAIN_DB_PREFIX."equipmentmanager_equipment as t";
        $sql2 .= " LEFT JOIN ".MAIN_DB_PREFIX."equipmentmanager_equipment_types as et ON t.equipment_type = et.code";
        $sql2 .= " WHERE t.fk_address = ".(int)$obj->address_id;
        $sql2 .= " AND t.status = 1";
        if (!$show_all) {
            $sql2 .= " AND t.maintenance_contract = 1";
            if ($month > 0) {
                $sql2 .= " AND t.maintenance_month = ".(int)$month;
            }
        }
        $sql2 .= " ORDER BY t.equipment_number";

        $resql2 = $db->query($sql2);
        $equipment = array();
        $total_duration = 0;
        $pending_count = 0;
        $in_progress_count = 0;
        $completed_count = 0;

        if ($resql2) {
            while ($eq = $db->fetch_object($resql2)) {
                $equipment[] = $eq;
                $total_duration += (int)$eq->effective_duration;
                if ($eq->is_completed > 0) {
                    $completed_count++;
                } elseif ($eq->has_open_maintenance > 0) {
                    $in_progress_count++;
                } else {
                    $pending_count++;
                }
            }
            $db->free($resql2);
        }

        if (count($equipment) > 0) {
            $location = array(
                'address_id' => $obj->address_id,
                'address_label' => $obj->address_label,
                'address' => $obj->address,
                'zip' => $obj->zip,
                'town' => $obj->town,
                'company_name' => $obj->company_name,
                'company_id' => $obj->company_id,
                'lat' => $obj->latitude,
                'lng' => $obj->longitude,
                'equipment' => $equipment,
                'total_duration' => $total_duration,
                'pending' => $pending_count,
                'in_progress' => $in_progress_count,
                'completed' => $completed_count
            );

            // Check if we have coordinates
            if (!empty($obj->latitude) && !empty($obj->longitude)) {
                $locations[] = $location;
            } else {
                $addresses_without_coords[] = $location;
            }
        }
    }
    $db->free($resql);
}

// Statistics
$total_locations = count($locations) + count($addresses_without_coords);
$total_equipment = 0;
$total_pending = 0;
$total_in_progress = 0;
$total_completed = 0;

foreach (array_merge($locations, $addresses_without_coords) as $loc) {
    $total_equipment += count($loc['equipment']);
    $total_pending += $loc['pending'];
    $total_in_progress += $loc['in_progress'];
    $total_completed += $loc['completed'];
}

// Summary
print '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 10px; margin-bottom: 20px;">';

print '<div style="background: #2196f3; color: white; padding: 10px; border-radius: 5px; text-align: center;">';
print '<div style="font-size: 20px; font-weight: bold;">'.$total_locations.'</div>';
print '<div style="font-size: 0.85em;">'.$langs->trans('Locations').'</div>';
print '</div>';

print '<div style="background: #9c27b0; color: white; padding: 10px; border-radius: 5px; text-align: center;">';
print '<div style="font-size: 20px; font-weight: bold;">'.$total_equipment.'</div>';
print '<div style="font-size: 0.85em;">'.$langs->trans('Equipment').'</div>';
print '</div>';

print '<div style="background: #f44336; color: white; padding: 10px; border-radius: 5px; text-align: center;">';
print '<div style="font-size: 20px; font-weight: bold;">'.$total_pending.'</div>';
print '<div style="font-size: 0.85em;">'.$langs->trans('Pending').'</div>';
print '</div>';

print '<div style="background: #ff9800; color: white; padding: 10px; border-radius: 5px; text-align: center;">';
print '<div style="font-size: 20px; font-weight: bold;">'.$total_in_progress.'</div>';
print '<div style="font-size: 0.85em;">'.$langs->trans('InProgress').'</div>';
print '</div>';

print '<div style="background: #4caf50; color: white; padding: 10px; border-radius: 5px; text-align: center;">';
print '<div style="font-size: 20px; font-weight: bold;">'.$total_completed.'</div>';
print '<div style="font-size: 0.85em;">'.$langs->trans('Completed').'</div>';
print '</div>';

print '</div>';

// Map container
if (count($locations) > 0) {
    print '<div id="maintenance-map" style="height: 500px; border: 1px solid #ccc; border-radius: 8px; margin-bottom: 20px;"></div>';

    // Leaflet CSS & JS
    print '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />';
    print '<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>';

    // Prepare locations data for JavaScript
    $js_locations = array();
    $type_labels = Equipment::getEquipmentTypesTranslated($db, $langs);

    foreach ($locations as $loc) {
        $popup_html = '<div style="min-width: 250px;">';
        $popup_html .= '<strong>'.dol_escape_htmltag($loc['address_label']).'</strong><br>';
        $popup_html .= '<span style="color: #666;">'.dol_escape_htmltag($loc['address']).', '.dol_escape_htmltag($loc['zip']).' '.dol_escape_htmltag($loc['town']).'</span><br>';
        if ($loc['company_name']) {
            $popup_html .= '<em>'.dol_escape_htmltag($loc['company_name']).'</em><br>';
        }
        $popup_html .= '<hr style="margin: 5px 0;">';
        $popup_html .= '<strong>'.count($loc['equipment']).' '.$langs->trans('Equipment').'</strong>';
        $popup_html .= ' <span style="color: #607d8b;">('.formatDuration($loc['total_duration']).')</span><br>';

        // Status badges
        if ($loc['pending'] > 0) {
            $popup_html .= '<span style="background: #f44336; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px; margin-right: 3px;">'.$loc['pending'].' '.$langs->trans('Pending').'</span>';
        }
        if ($loc['in_progress'] > 0) {
            $popup_html .= '<span style="background: #ff9800; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px; margin-right: 3px;">'.$loc['in_progress'].' '.$langs->trans('InProgress').'</span>';
        }
        if ($loc['completed'] > 0) {
            $popup_html .= '<span style="background: #4caf50; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px;">'.$loc['completed'].' '.$langs->trans('Completed').'</span>';
        }

        $popup_html .= '<ul style="margin: 5px 0; padding-left: 15px; max-height: 150px; overflow-y: auto;">';
        foreach ($loc['equipment'] as $eq) {
            $type_label = isset($type_labels[$eq->equipment_type]) ? $type_labels[$eq->equipment_type] : $eq->equipment_type;
            $popup_html .= '<li style="font-size: 12px;">';
            $popup_html .= '<a href="'.DOL_URL_ROOT.'/custom/equipmentmanager/equipment_view.php?id='.$eq->rowid.'" target="_blank">';
            $popup_html .= dol_escape_htmltag($eq->equipment_number);
            $popup_html .= '</a>';
            $popup_html .= ' - '.dol_escape_htmltag($type_label);
            $popup_html .= '</li>';
        }
        $popup_html .= '</ul>';
        $popup_html .= '</div>';

        // Determine marker color based on status
        if ($loc['pending'] > 0) {
            $color = 'red';
        } elseif ($loc['in_progress'] > 0) {
            $color = 'orange';
        } else {
            $color = 'green';
        }

        $js_locations[] = array(
            'lat' => (float)$loc['lat'],
            'lng' => (float)$loc['lng'],
            'popup' => $popup_html,
            'color' => $color,
            'count' => count($loc['equipment'])
        );
    }

    print '<script>
    document.addEventListener("DOMContentLoaded", function() {
        var locations = '.json_encode($js_locations).';

        if (locations.length === 0) return;

        // Calculate center
        var sumLat = 0, sumLng = 0;
        locations.forEach(function(loc) {
            sumLat += loc.lat;
            sumLng += loc.lng;
        });
        var centerLat = sumLat / locations.length;
        var centerLng = sumLng / locations.length;

        // Initialize map
        var map = L.map("maintenance-map").setView([centerLat, centerLng], 10);

        // Add OpenStreetMap tiles
        L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
            attribution: "&copy; <a href=\"https://www.openstreetmap.org/copyright\">OpenStreetMap</a> contributors"
        }).addTo(map);

        // Custom marker icons
        function getMarkerIcon(color, count) {
            var colors = {
                red: "#f44336",
                orange: "#ff9800",
                green: "#4caf50"
            };
            var bgColor = colors[color] || "#2196f3";

            return L.divIcon({
                className: "custom-marker",
                html: \'<div style="background: \' + bgColor + \'; color: white; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 12px; border: 2px solid white; box-shadow: 0 2px 5px rgba(0,0,0,0.3);">\' + count + \'</div>\',
                iconSize: [30, 30],
                iconAnchor: [15, 15],
                popupAnchor: [0, -15]
            });
        }

        // Add markers
        var bounds = [];
        locations.forEach(function(loc) {
            var marker = L.marker([loc.lat, loc.lng], {
                icon: getMarkerIcon(loc.color, loc.count)
            }).addTo(map);
            marker.bindPopup(loc.popup, { maxWidth: 350 });
            bounds.push([loc.lat, loc.lng]);
        });

        // Fit bounds
        if (bounds.length > 1) {
            map.fitBounds(bounds, { padding: [30, 30] });
        }
    });
    </script>';
} else {
    print '<div class="info" style="padding: 30px; text-align: center;">';
    print '<span class="fa fa-map-marker" style="font-size: 48px; color: #ccc;"></span>';
    print '<h3>'.$langs->trans('NoLocationsWithCoordinates').'</h3>';
    print '<p class="opacitymedium">'.$langs->trans('NoLocationsWithCoordinatesDesc').'</p>';
    print '</div>';
}

// Addresses without coordinates
if (count($addresses_without_coords) > 0) {
    print '<br>';
    print '<div class="div-table-responsive-no-min">';
    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre">';
    print '<th colspan="5">';
    print '<span class="fa fa-exclamation-triangle" style="color: #ff9800;"></span> ';
    print $langs->trans('AddressesWithoutCoordinates');
    print ' <span class="badge" style="background: #ff9800; color: white;">'.count($addresses_without_coords).'</span>';
    print '</th>';
    print '</tr>';
    print '<tr class="liste_titre">';
    print '<th>'.$langs->trans('Address').'</th>';
    print '<th>'.$langs->trans('Town').'</th>';
    print '<th>'.$langs->trans('Customer').'</th>';
    print '<th class="center">'.$langs->trans('Equipment').'</th>';
    print '<th class="center">'.$langs->trans('PlannedDuration').'</th>';
    print '</tr>';

    foreach ($addresses_without_coords as $loc) {
        print '<tr class="oddeven">';
        print '<td>'.dol_escape_htmltag($loc['address_label']).'<br><span class="opacitymedium">'.dol_escape_htmltag($loc['address']).'</span></td>';
        print '<td>'.dol_escape_htmltag($loc['zip']).' '.dol_escape_htmltag($loc['town']).'</td>';
        print '<td>'.dol_escape_htmltag($loc['company_name']).'</td>';
        print '<td class="center">'.count($loc['equipment']).'</td>';
        print '<td class="center">'.formatDuration($loc['total_duration']).'</td>';
        print '</tr>';
    }

    print '</table>';
    print '</div>';

    print '<div class="info" style="margin-top: 10px;">';
    print '<span class="fa fa-info-circle"></span> ';
    print $langs->trans('AddCoordinatesHint');
    print '</div>';
}

// Legend
print '<br>';
print '<div class="info">';
print '<h4><span class="fa fa-info-circle"></span> '.$langs->trans('Legend').'</h4>';
print '<ul style="list-style: none; padding: 0; margin: 0;">';
print '<li><span style="display: inline-block; width: 20px; height: 20px; background: #f44336; border-radius: 50%; margin-right: 10px; vertical-align: middle;"></span> '.$langs->trans('HasPendingMaintenance').'</li>';
print '<li><span style="display: inline-block; width: 20px; height: 20px; background: #ff9800; border-radius: 50%; margin-right: 10px; vertical-align: middle;"></span> '.$langs->trans('HasInProgressMaintenance').'</li>';
print '<li><span style="display: inline-block; width: 20px; height: 20px; background: #4caf50; border-radius: 50%; margin-right: 10px; vertical-align: middle;"></span> '.$langs->trans('AllMaintenanceCompleted').'</li>';
print '</ul>';
print '</div>';

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
