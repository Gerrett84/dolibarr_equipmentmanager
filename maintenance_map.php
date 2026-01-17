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

// Get addresses with equipment (using object address from socpeople)
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
    // Same logic as dashboard: show equipment with maintenance_month set
    $sql .= " AND t.maintenance_month IS NOT NULL";
    if ($month > 0) {
        $sql .= " AND t.maintenance_month = ".(int)$month;
    }
}
$sql .= " ORDER BY sp.town, sp.lastname";

$resql = $db->query($sql);

$locations = array();
$locations_to_geocode = array();

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
            $sql2 .= " AND t.maintenance_month IS NOT NULL";
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
                // Add to geocoding queue - will be geocoded via JavaScript
                $locations_to_geocode[] = $location;
            }
        }
    }
    $db->free($resql);
}

// Statistics
$total_locations = count($locations) + count($locations_to_geocode);
$total_equipment = 0;
$total_pending = 0;
$total_in_progress = 0;
$total_completed = 0;

foreach (array_merge($locations, $locations_to_geocode) as $loc) {
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

// Map container - show if we have any locations (with or without coords)
if (count($locations) > 0 || count($locations_to_geocode) > 0) {
    print '<div id="maintenance-map" style="height: 500px; border: 1px solid #ccc; border-radius: 8px; margin-bottom: 20px;"></div>';
    print '<div id="geocoding-status" style="text-align: center; padding: 10px; display: none;"><span class="fa fa-spinner fa-spin"></span> '.$langs->trans('GeocodingAddresses').'...</div>';

    // Leaflet CSS & JS
    print '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />';
    print '<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>';

    // Prepare locations data for JavaScript
    $js_locations = array();
    $js_locations_to_geocode = array();
    $type_labels = Equipment::getEquipmentTypesTranslated($db, $langs);

    // Helper function to build popup HTML
    function buildPopupHtml($loc, $type_labels, $langs) {
        global $db;
        $popup_html = '<div style="min-width: 250px;">';
        $popup_html .= '<strong>'.dol_escape_htmltag($loc['address_label']).'</strong><br>';
        $popup_html .= '<span style="color: #666;">'.dol_escape_htmltag($loc['address']).', '.dol_escape_htmltag($loc['zip']).' '.dol_escape_htmltag($loc['town']).'</span><br>';
        if ($loc['company_name']) {
            $popup_html .= '<em>'.dol_escape_htmltag($loc['company_name']).'</em><br>';
        }
        $popup_html .= '<hr style="margin: 5px 0;">';
        $popup_html .= '<strong>'.count($loc['equipment']).' '.$langs->trans('Equipment').'</strong>';
        $popup_html .= ' <span style="color: #607d8b;">('.formatDuration($loc['total_duration']).')</span><br>';

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
        return $popup_html;
    }

    // Helper function to get marker color
    function getMarkerColor($loc) {
        if ($loc['pending'] > 0) return 'red';
        if ($loc['in_progress'] > 0) return 'orange';
        return 'green';
    }

    // Locations with coordinates
    foreach ($locations as $loc) {
        $js_locations[] = array(
            'lat' => (float)$loc['lat'],
            'lng' => (float)$loc['lng'],
            'popup' => buildPopupHtml($loc, $type_labels, $langs),
            'color' => getMarkerColor($loc),
            'count' => count($loc['equipment'])
        );
    }

    // Locations that need geocoding
    foreach ($locations_to_geocode as $loc) {
        $js_locations_to_geocode[] = array(
            'address' => $loc['address'].', '.$loc['zip'].' '.$loc['town'].', Germany',
            'popup' => buildPopupHtml($loc, $type_labels, $langs),
            'color' => getMarkerColor($loc),
            'count' => count($loc['equipment'])
        );
    }

    print '<script>
    document.addEventListener("DOMContentLoaded", function() {
        var locations = '.json_encode($js_locations).';
        var locationsToGeocode = '.json_encode($js_locations_to_geocode).';
        var bounds = [];
        var map;
        var geocodeIndex = 0;
        var geocodedCount = 0;
        var geocodeFailedCount = 0;

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

        // Add marker to map
        function addMarker(lat, lng, popup, color, count) {
            var marker = L.marker([lat, lng], {
                icon: getMarkerIcon(color, count)
            }).addTo(map);
            marker.bindPopup(popup, { maxWidth: 350 });
            bounds.push([lat, lng]);
        }

        // Initialize map
        var defaultCenter = [51.1657, 10.4515]; // Germany center
        map = L.map("maintenance-map").setView(defaultCenter, 6);

        // Add OpenStreetMap tiles
        L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
            attribution: "&copy; <a href=\\"https://www.openstreetmap.org/copyright\\">OpenStreetMap</a> contributors"
        }).addTo(map);

        // Add pre-geocoded locations
        locations.forEach(function(loc) {
            addMarker(loc.lat, loc.lng, loc.popup, loc.color, loc.count);
        });

        // Geocode function using Nominatim
        function geocodeNext() {
            if (geocodeIndex >= locationsToGeocode.length) {
                // Done geocoding
                document.getElementById("geocoding-status").style.display = "none";
                if (bounds.length > 0) {
                    map.fitBounds(bounds, { padding: [30, 30] });
                }
                return;
            }

            var loc = locationsToGeocode[geocodeIndex];
            var url = "https://nominatim.openstreetmap.org/search?format=json&q=" + encodeURIComponent(loc.address);

            fetch(url, {
                headers: { "Accept-Language": "de" }
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data && data.length > 0) {
                    var lat = parseFloat(data[0].lat);
                    var lng = parseFloat(data[0].lon);
                    addMarker(lat, lng, loc.popup, loc.color, loc.count);
                    geocodedCount++;
                } else {
                    geocodeFailedCount++;
                    console.log("Geocoding failed for: " + loc.address);
                }
                geocodeIndex++;
                // Delay to respect Nominatim rate limit (1 request per second)
                setTimeout(geocodeNext, 1100);
            })
            .catch(function(err) {
                console.error("Geocoding error:", err);
                geocodeFailedCount++;
                geocodeIndex++;
                setTimeout(geocodeNext, 1100);
            });
        }

        // Start geocoding if needed
        if (locationsToGeocode.length > 0) {
            document.getElementById("geocoding-status").style.display = "block";
            geocodeNext();
        } else if (bounds.length > 0) {
            map.fitBounds(bounds, { padding: [30, 30] });
        }
    });
    </script>';
} else {
    print '<div class="info" style="padding: 30px; text-align: center;">';
    print '<span class="fa fa-map-marker" style="font-size: 48px; color: #ccc;"></span>';
    print '<h3>'.$langs->trans('NoLocationsFound').'</h3>';
    print '</div>';
}

// Info about geocoding
if (count($locations_to_geocode) > 0) {
    print '<br>';
    print '<div class="info">';
    print '<span class="fa fa-info-circle"></span> ';
    print $langs->trans('GeocodingInfo', count($locations_to_geocode));
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
