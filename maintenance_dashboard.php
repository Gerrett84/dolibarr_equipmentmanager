<?php
/* Copyright (C) 2024 Equipment Manager
 * Maintenance Dashboard - v1.5.1
 * FIXES:
 * - Serviceauftrags-Link verwendet jetzt ref statt rowid
 * - Tabellenfarben dunkelmodus-kompatibel (rgba mit Transparenz)
 * - Status "In Bearbeitung" bereits ab Validierung (Status 1+)
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

$action = GETPOST('action', 'aZ09');
$equipment_id = GETPOST('equipment_id', 'int');
$confirm = GETPOST('confirm', 'alpha');

if (!$user->rights->equipmentmanager->equipment->read) {
    accessforbidden();
}

/*
 * Actions
 */

// Manuelles "Als erledigt markieren"
if ($action == 'mark_completed' && $confirm == 'yes' && $equipment_id > 0) {
    if ($user->rights->equipmentmanager->equipment->write) {
        $sql_update = "UPDATE ".MAIN_DB_PREFIX."equipmentmanager_equipment";
        $sql_update .= " SET last_maintenance_date = NOW()";
        $sql_update .= " WHERE rowid = ".(int)$equipment_id;
        
        if ($db->query($sql_update)) {
            setEventMessages($langs->trans('MaintenanceMarkedCompleted'), null, 'mesgs');
        } else {
            setEventMessages($db->lasterror(), null, 'errors');
        }
    }
    
    header("Location: ".$_SERVER["PHP_SELF"]);
    exit;
}

$form = new Form($db);

// Helper function to format duration
function formatDuration($minutes) {
    if ($minutes <= 0) return '-';
    if ($minutes >= 60) {
        $hours = floor($minutes / 60);
        $mins = $minutes % 60;
        return $hours.'h'.($mins > 0 ? ' '.$mins.'min' : '');
    }
    return $minutes.' min';
}

$title = $langs->trans("MaintenanceDashboard");
$help_url = '';

llxHeader('', $title, $help_url);

// Dark Mode Styles
print '<style>
/* Dark Mode für Wartungs-Dashboard */
@media (prefers-color-scheme: dark) {
    .maintenance-summary {
        background: #2d2d2d !important;
        border-color: #404040 !important;
    }
    .maintenance-legend {
        background: #2d2d2d !important;
        border-color: #404040 !important;
        color: #e0e0e0 !important;
    }
    .maintenance-legend li {
        color: #e0e0e0 !important;
    }
}
/* Dolibarr Dark Mode (eldy_menu class check) */
body.class-colorblind-b .maintenance-summary,
body[class*="dark"] .maintenance-summary,
.bodywebsite[style*="background-color: rgb(28, 28, 28)"] .maintenance-summary {
    background: #2d2d2d !important;
    border-color: #404040 !important;
}
body.class-colorblind-b .maintenance-legend,
body[class*="dark"] .maintenance-legend,
.bodywebsite[style*="background-color: rgb(28, 28, 28)"] .maintenance-legend {
    background: #2d2d2d !important;
    border-color: #404040 !important;
    color: #e0e0e0 !important;
}
</style>';

$linkback = '<a class="button" href="'.DOL_URL_ROOT.'/custom/equipmentmanager/maintenance_calendar.php"><span class="fa fa-calendar"></span> '.$langs->trans("MaintenanceCalendar").'</a>';
$linkback .= ' <a class="button" href="'.DOL_URL_ROOT.'/custom/equipmentmanager/maintenance_map.php"><span class="fa fa-map-marker"></span> '.$langs->trans("MaintenanceMap").'</a>';
$linkback .= ' <a class="button" href="'.DOL_URL_ROOT.'/custom/equipmentmanager/maintenance_auto_create.php"><span class="fa fa-magic"></span> '.$langs->trans("AutoCreateServiceOrders").'</a>';
print load_fiche_titre($title, $linkback, 'fa-wrench');

// Aktueller Monat und nächster Monat
$current_month = (int)date('n');
$current_year = (int)date('Y');
$next_month = $current_month + 1;
$next_year = $current_year;
$prev_year = $current_year;

if ($next_month > 12) {
    $next_month = 1;
    $next_year++;
}

// Vorjahr für Dezember-Überfällige (im Januar)
if ($current_month == 1) {
    $prev_year = $current_year - 1;
}

// SQL: Equipment mit fälligen Wartungen
$sql = "SELECT";
$sql .= " t.rowid,";
$sql .= " t.equipment_number,";
$sql .= " t.label,";
$sql .= " t.equipment_type,";
$sql .= " t.manufacturer,";
$sql .= " t.maintenance_month,";
$sql .= " t.last_maintenance_date,";
$sql .= " t.next_maintenance_date,";
$sql .= " t.fk_soc,";
$sql .= " t.fk_address,";
$sql .= " t.planned_duration as equipment_duration,";
$sql .= " et.default_duration as type_duration,";
$sql .= " COALESCE(t.planned_duration, et.default_duration, 0) as effective_duration,";
$sql .= " s.nom as company_name,";
$sql .= " CONCAT(sp.lastname, ' ', sp.firstname) as address_label,";
$sql .= " sp.address as address_street,";
$sql .= " sp.zip as address_zip,";
$sql .= " sp.town as address_town,";
// FIX: Prüfe bereits ab Status 1 (Validiert)
$sql .= " (SELECT COUNT(*) FROM ".MAIN_DB_PREFIX."equipmentmanager_intervention_link il";
$sql .= "  INNER JOIN ".MAIN_DB_PREFIX."fichinter f ON il.fk_intervention = f.rowid";
$sql .= "  WHERE il.fk_equipment = t.rowid";
$sql .= "  AND il.link_type = 'maintenance'";
$sql .= "  AND f.fk_statut >= 1 AND f.fk_statut < 3) as has_open_maintenance";
$sql .= " FROM ".MAIN_DB_PREFIX."equipmentmanager_equipment as t";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON t.fk_soc = s.rowid";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."socpeople as sp ON t.fk_address = sp.rowid";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."equipmentmanager_equipment_types as et ON t.equipment_type = et.code";
$sql .= " WHERE t.entity IN (".getEntity('equipmentmanager').")";
$sql .= " AND t.status = 1";
$sql .= " AND t.maintenance_month IS NOT NULL";
// Zeige: aktueller Monat, nächster Monat, UND alle überfälligen (Monat < aktueller Monat)
$sql .= " AND (t.maintenance_month = ".$current_month;
$sql .= " OR t.maintenance_month = ".$next_month;
$sql .= " OR t.maintenance_month < ".$current_month;
// Im Januar auch Dezember des Vorjahres (maintenance_month = 12)
if ($current_month == 1) {
    $sql .= " OR t.maintenance_month = 12";
}
$sql .= ")";
// Blende bereits erledigte aus
// Für Dezember-Wartungen im Januar: prüfe Vorjahr ODER aktuelles Jahr
$sql .= " AND NOT EXISTS (";
$sql .= "   SELECT 1 FROM ".MAIN_DB_PREFIX."equipmentmanager_intervention_link il2";
$sql .= "   INNER JOIN ".MAIN_DB_PREFIX."fichinter f2 ON il2.fk_intervention = f2.rowid";
$sql .= "   WHERE il2.fk_equipment = t.rowid";
$sql .= "   AND il2.link_type = 'maintenance'";
$sql .= "   AND f2.fk_statut = 3";
$sql .= "   AND (";
// Normale Wartungen: im aktuellen Jahr erledigt
$sql .= "     (YEAR(f2.date_valid) = ".$current_year." AND (";
$sql .= "       MONTH(f2.date_valid) = t.maintenance_month";
$sql .= "       OR MONTH(f2.date_valid) = t.maintenance_month - 1";
$sql .= "       OR (t.maintenance_month = 1 AND MONTH(f2.date_valid) = 12)";
$sql .= "     ))";
// Dezember-Wartungen: auch Vorjahr prüfen
if ($current_month <= 2) {
    $sql .= "     OR (t.maintenance_month = 12 AND YEAR(f2.date_valid) = ".($current_year - 1)." AND MONTH(f2.date_valid) = 12)";
}
$sql .= "   )";
$sql .= " )";
$sql .= " AND NOT (";
$sql .= "   t.last_maintenance_date IS NOT NULL";
$sql .= "   AND (";
// Normale Wartungen: im aktuellen Jahr erledigt
$sql .= "     (YEAR(t.last_maintenance_date) = ".$current_year." AND (";
$sql .= "       MONTH(t.last_maintenance_date) = t.maintenance_month";
$sql .= "       OR MONTH(t.last_maintenance_date) = t.maintenance_month - 1";
$sql .= "       OR (t.maintenance_month = 1 AND MONTH(t.last_maintenance_date) = 12)";
$sql .= "     ))";
// Dezember-Wartungen: auch Vorjahr prüfen
if ($current_month <= 2) {
    $sql .= "     OR (t.maintenance_month = 12 AND YEAR(t.last_maintenance_date) = ".($current_year - 1)." AND MONTH(t.last_maintenance_date) = 12)";
}
$sql .= "   )";
$sql .= " )";
$sql .= " ORDER BY t.maintenance_month, sp.town, sp.lastname, t.equipment_number";

$resql = $db->query($sql);

if ($resql) {
    $num = $db->num_rows($resql);
    
    // Zähle erledigte Wartungen
    $sql_completed = "SELECT COUNT(DISTINCT t.rowid) as completed_count";
    $sql_completed .= " FROM ".MAIN_DB_PREFIX."equipmentmanager_equipment t";
    $sql_completed .= " WHERE t.maintenance_month = ".$current_month;
    $sql_completed .= " AND t.status = 1";
    $sql_completed .= " AND (";
    $sql_completed .= "   EXISTS (";
    $sql_completed .= "     SELECT 1 FROM ".MAIN_DB_PREFIX."equipmentmanager_intervention_link il";
    $sql_completed .= "     INNER JOIN ".MAIN_DB_PREFIX."fichinter f ON il.fk_intervention = f.rowid";
    $sql_completed .= "     WHERE il.fk_equipment = t.rowid";
    $sql_completed .= "     AND il.link_type = 'maintenance'";
    $sql_completed .= "     AND f.fk_statut = 3";
    $sql_completed .= "     AND YEAR(f.date_valid) = ".$current_year;
    $sql_completed .= "     AND (";
    $sql_completed .= "       MONTH(f.date_valid) = t.maintenance_month";
    $sql_completed .= "       OR MONTH(f.date_valid) = t.maintenance_month - 1";
    $sql_completed .= "       OR (t.maintenance_month = 1 AND MONTH(f.date_valid) = 12)";
    $sql_completed .= "     )";
    $sql_completed .= "   )";
    $sql_completed .= "   OR (";
    $sql_completed .= "     t.last_maintenance_date IS NOT NULL";
    $sql_completed .= "     AND YEAR(t.last_maintenance_date) = ".$current_year;
    $sql_completed .= "     AND (";
    $sql_completed .= "       MONTH(t.last_maintenance_date) = t.maintenance_month";
    $sql_completed .= "       OR MONTH(t.last_maintenance_date) = t.maintenance_month - 1";
    $sql_completed .= "       OR (t.maintenance_month = 1 AND MONTH(t.last_maintenance_date) = 12)";
    $sql_completed .= "     )";
    $sql_completed .= "   )";
    $sql_completed .= " )";
    
    $resql_completed = $db->query($sql_completed);
    $completed_count = 0;
    if ($resql_completed) {
        $obj_completed = $db->fetch_object($resql_completed);
        $completed_count = $obj_completed->completed_count;
    }
    
    if ($num > 0) {
        // Gruppierung - dynamisch für alle gefundenen Monate
        $grouped = array();

        $stats = array(
            'total' => 0,
            'current_month' => 0,
            'next_month' => 0,
            'overdue' => 0,
            'open_maintenance' => 0,
            'completed' => 0,
            'without_address' => 0,
            'total_duration' => 0,
            'current_month_duration' => 0,
            'next_month_duration' => 0,
            'overdue_duration' => 0
        );

        while ($obj = $db->fetch_object($resql)) {
            $month = (int)$obj->maintenance_month;

            // Initialisiere Monat wenn noch nicht vorhanden
            if (!isset($grouped[$month])) {
                $grouped[$month] = array();
            }

            $duration = (int)$obj->effective_duration;
            $stats['total']++;
            $stats['total_duration'] += $duration;
            if ($month == $current_month) {
                $stats['current_month']++;
                $stats['current_month_duration'] += $duration;
            } elseif ($month == $next_month) {
                $stats['next_month']++;
                $stats['next_month_duration'] += $duration;
            } else {
                // Überfällig (Vormonat oder älter)
                $stats['overdue']++;
                $stats['overdue_duration'] += $duration;
            }
            if ($obj->has_open_maintenance > 0) $stats['open_maintenance']++;
            if (!$obj->fk_address) $stats['without_address']++;
            
            if ($obj->fk_address > 0) {
                $address_key = $obj->fk_address;
                $address_name = $obj->address_label;
                $address_full = '';
                if ($obj->address_street) $address_full .= $obj->address_street.', ';
                if ($obj->address_zip) $address_full .= $obj->address_zip.' ';
                if ($obj->address_town) $address_full .= $obj->address_town;
            } else {
                $address_key = 'no_address';
                $address_name = $langs->trans('NoAddress');
                $address_full = '';
            }
            
            if (!isset($grouped[$month][$address_key])) {
                $grouped[$month][$address_key] = array(
                    'label' => $address_name,
                    'full_address' => $address_full,
                    'company_name' => $obj->company_name,
                    'fk_soc' => $obj->fk_soc,
                    'equipment' => array(),
                    'total_duration' => 0
                );
            }

            $grouped[$month][$address_key]['equipment'][] = $obj;
            $grouped[$month][$address_key]['total_duration'] += $duration;
        }
        
        // Zusammenfassung
        print '<div class="info maintenance-summary" style="margin-bottom: 20px; padding: 10px;">';
        print '<h3 style="margin-top: 0; margin-bottom: 10px;"><span class="fa fa-info-circle"></span> '.$langs->trans('Summary').'</h3>';
        print '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px;">';
        
        print '<div style="background: #2196f3; color: white; padding: 10px; border-radius: 5px; text-align: center;">';
        print '<div style="font-size: 24px; font-weight: bold;">'.$stats['total'].'</div>';
        print '<div style="font-size: 0.85em;">'.$langs->trans('TotalMaintenanceDue').'</div>';
        print '<div style="font-size: 0.75em; margin-top: 5px; opacity: 0.9;"><span class="fa fa-clock-o"></span> '.formatDuration($stats['total_duration']).'</div>';
        print '</div>';

        // Überfällig (rot, dunkel)
        if ($stats['overdue'] > 0) {
            print '<div style="background: #b71c1c; color: white; padding: 10px; border-radius: 5px; text-align: center;">';
            print '<div style="font-size: 24px; font-weight: bold;">'.$stats['overdue'].'</div>';
            print '<div style="font-size: 0.85em;">'.$langs->trans('Overdue').'</div>';
            print '<div style="font-size: 0.75em; margin-top: 5px; opacity: 0.9;"><span class="fa fa-clock-o"></span> '.formatDuration($stats['overdue_duration']).'</div>';
            print '</div>';
        }

        $month_name = dol_print_date(dol_mktime(0, 0, 0, $current_month, 1, $current_year), '%B');
        print '<div style="background: #f44336; color: white; padding: 10px; border-radius: 5px; text-align: center;">';
        print '<div style="font-size: 24px; font-weight: bold;">'.$stats['current_month'].'</div>';
        print '<div style="font-size: 0.85em;">'.$month_name.'</div>';
        print '<div style="font-size: 0.75em; margin-top: 5px; opacity: 0.9;"><span class="fa fa-clock-o"></span> '.formatDuration($stats['current_month_duration']).'</div>';
        print '</div>';

        $month_name = dol_print_date(dol_mktime(0, 0, 0, $next_month, 1, $next_year), '%B');
        print '<div style="background: #ff9800; color: white; padding: 10px; border-radius: 5px; text-align: center;">';
        print '<div style="font-size: 24px; font-weight: bold;">'.$stats['next_month'].'</div>';
        print '<div style="font-size: 0.85em;">'.$month_name.'</div>';
        print '<div style="font-size: 0.75em; margin-top: 5px; opacity: 0.9;"><span class="fa fa-clock-o"></span> '.formatDuration($stats['next_month_duration']).'</div>';
        print '</div>';

        print '<div style="background: #4caf50; color: white; padding: 10px; border-radius: 5px; text-align: center;">';
        print '<div style="font-size: 24px; font-weight: bold;">'.$stats['open_maintenance'].'</div>';
        print '<div style="font-size: 0.85em;">'.$langs->trans('InProgress').'</div>';
        print '</div>';

        print '<div style="background: #00bcd4; color: white; padding: 10px; border-radius: 5px; text-align: center;">';
        print '<div style="font-size: 24px; font-weight: bold;">'.$completed_count.'</div>';
        print '<div style="font-size: 0.85em;">'.$langs->trans('CompletedThisMonth').'</div>';
        print '</div>';
        
        print '</div>';
        print '</div>';
        
        $type_labels = Equipment::getEquipmentTypesTranslated($db, $langs);

        // Zeige nach Monat gruppiert - sortiert: überfällige zuerst, dann aktuell, dann nächster
        // Sortiere Monate: überfällige aufsteigend, dann current, dann next
        $display_months = array_keys($grouped);
        usort($display_months, function($a, $b) use ($current_month, $next_month) {
            // Überfällige zuerst (aufsteigend), dann current, dann next
            $a_overdue = ($a != $current_month && $a != $next_month);
            $b_overdue = ($b != $current_month && $b != $next_month);
            if ($a_overdue && !$b_overdue) return -1;
            if (!$a_overdue && $b_overdue) return 1;
            if ($a == $current_month) return ($b == $next_month) ? -1 : 1;
            if ($b == $current_month) return ($a == $next_month) ? 1 : -1;
            return $a - $b;
        });

        foreach ($display_months as $month) {
            if (empty($grouped[$month])) continue;

            // Bestimme Jahr für Monatsanzeige
            $display_year = $current_year;
            if ($month == $next_month && $next_month == 1) {
                $display_year = $next_year;
            } elseif ($month == 12 && $current_month == 1) {
                // Dezember im Januar = Vorjahr
                $display_year = $current_year - 1;
            }

            $month_name = dol_print_date(dol_mktime(0, 0, 0, $month, 1, $display_year), '%B %Y');
            $is_current = ($month == $current_month);
            $is_next = ($month == $next_month);
            $is_overdue = (!$is_current && !$is_next);

            // Farbe je nach Status
            if ($is_overdue) {
                $icon_color = '#b71c1c'; // Dunkelrot für überfällig
            } elseif ($is_current) {
                $icon_color = '#f44336'; // Rot für aktuell
            } else {
                $icon_color = '#ff9800'; // Orange für nächsten Monat
            }
            
            print '<h2 style="margin-top: 30px; color: '.$icon_color.';">';
            print '<span class="fa fa-calendar"></span> '.$month_name;
            if ($is_overdue) {
                print ' <span class="badge" style="background: #b71c1c; color: white; font-size: 14px; margin-left: 10px;">'.$langs->trans('Overdue').'</span>';
            } elseif ($is_current) {
                print ' <span class="badge" style="background: #f44336; color: white; font-size: 14px; margin-left: 10px;">'.$langs->trans('CurrentMonth').'</span>';
            }
            print '</h2>';
            
            foreach ($grouped[$month] as $address_key => $address_data) {
                $equipment_count = count($address_data['equipment']);
                
                print '<div class="div-table-responsive-no-min" style="margin-bottom: 20px;">';
                print '<table class="noborder centpercent">';
                
                // FIX: Dunkelmodus-kompatible Farben mit rgba und Transparenz
                // Farben für Überfällig/Aktuell/Nächster Monat
                if ($is_overdue) {
                    $header_bg = 'rgba(183, 28, 28, 0.15)'; // Dunkelrot
                    $header_border = 'rgba(183, 28, 28, 0.3)';
                } elseif ($is_current) {
                    $header_bg = 'rgba(244, 67, 54, 0.15)';
                    $header_border = 'rgba(244, 67, 54, 0.3)';
                } else {
                    $header_bg = 'rgba(255, 152, 0, 0.15)';
                    $header_border = 'rgba(255, 152, 0, 0.3)';
                }
                
                print '<tr class="liste_titre" style="background-color: '.$header_bg.'; border-left: 4px solid '.$icon_color.';">';
                print '<th colspan="8">';
                
                print '<span class="fa fa-map-marker paddingright" style="color: '.$icon_color.';"></span>';
                print '<strong>'.dol_escape_htmltag($address_data['label']).'</strong>';
                
                if ($address_data['company_name']) {
                    print ' <span class="opacitymedium">- ';
                    if ($address_data['fk_soc'] > 0) {
                        $companystatic = new Societe($db);
                        $companystatic->fetch($address_data['fk_soc']);
                        print $companystatic->getNomUrl(1);
                    } else {
                        print dol_escape_htmltag($address_data['company_name']);
                    }
                    print '</span>';
                }
                
                if ($address_data['full_address']) {
                    print '<br><span class="opacitymedium" style="font-weight: normal; font-size: 0.9em;">';
                    print dol_escape_htmltag($address_data['full_address']);
                    print '</span>';
                }
                
                print ' <span class="badge" style="background: '.$icon_color.'; color: white; margin-left: 10px;">';
                print $equipment_count.' '.$langs->trans('Equipment');
                print '</span>';

                if ($address_data['total_duration'] > 0) {
                    print ' <span class="badge" style="background: #607d8b; color: white; margin-left: 5px;">';
                    print '<span class="fa fa-clock-o"></span> '.formatDuration($address_data['total_duration']);
                    print '</span>';
                }

                print '</th>';
                print '</tr>';
                
                print '<tr class="liste_titre">';
                print '<th>'.$langs->trans('EquipmentNumber').'</th>';
                print '<th>'.$langs->trans('Type').'</th>';
                print '<th>'.$langs->trans('Label').'</th>';
                print '<th>'.$langs->trans('Manufacturer').'</th>';
                print '<th class="center">'.$langs->trans('PlannedDuration').'</th>';
                print '<th class="center">'.$langs->trans('LastMaintenance').'</th>';
                print '<th class="center">'.$langs->trans('Status').'</th>';
                print '<th class="center">'.$langs->trans('Actions').'</th>';
                print '</tr>';
                
                foreach ($address_data['equipment'] as $equip) {
                    print '<tr class="oddeven">';
                    
                    print '<td>';
                    print '<a href="'.DOL_URL_ROOT.'/custom/equipmentmanager/equipment_view.php?id='.$equip->rowid.'" target="_blank">';
                    print img_object('', 'generic', 'class="pictofixedwidth"');
                    print '<strong>'.$equip->equipment_number.'</strong>';
                    print '</a>';
                    print '</td>';
                    
                    print '<td>';
                    if (isset($type_labels[$equip->equipment_type])) {
                        print $type_labels[$equip->equipment_type];
                    } else {
                        print dol_escape_htmltag($equip->equipment_type);
                    }
                    print '</td>';
                    
                    print '<td>'.dol_escape_htmltag($equip->label).'</td>';
                    print '<td>'.dol_escape_htmltag($equip->manufacturer).'</td>';

                    // Planned duration
                    print '<td class="center nowrap">';
                    $equip_duration = (int)$equip->effective_duration;
                    if ($equip_duration > 0) {
                        print formatDuration($equip_duration);
                    } else {
                        print '<span class="opacitymedium">-</span>';
                    }
                    print '</td>';

                    print '<td class="center">';
                    if ($equip->last_maintenance_date) {
                        print dol_print_date($db->jdate($equip->last_maintenance_date), 'day');
                    } else {
                        print '<span class="opacitymedium">-</span>';
                    }
                    print '</td>';
                    
                    print '<td class="center nowrap">';
                    if ($equip->has_open_maintenance > 0) {
                        // Suche den verknüpften Serviceauftrag
                        $sql_int = "SELECT f.rowid, f.ref FROM ".MAIN_DB_PREFIX."fichinter f";
                        $sql_int .= " INNER JOIN ".MAIN_DB_PREFIX."equipmentmanager_intervention_link il";
                        $sql_int .= " ON f.rowid = il.fk_intervention";
                        $sql_int .= " WHERE il.fk_equipment = ".(int)$equip->rowid;
                        $sql_int .= " AND il.link_type = 'maintenance'";
                        $sql_int .= " AND f.fk_statut >= 1 AND f.fk_statut < 3";
                        $sql_int .= " ORDER BY f.datec DESC LIMIT 1";
                        
                        $resql_int = $db->query($sql_int);
                        
                        if ($resql_int) {
                            $num_int = $db->num_rows($resql_int);
                            
                            if ($num_int > 0) {
                                $int_obj = $db->fetch_object($resql_int);
                                
                                print '<span class="badge badge-status4" style="background: #4caf50;">';
                                print '<span class="fa fa-cog fa-spin"></span> '.$langs->trans('InProgress');
                                print '</span><br>';
                                print '<a href="'.DOL_URL_ROOT.'/fichinter/card.php?ref='.urlencode($int_obj->ref).'" target="_blank" style="font-size: 0.85em; white-space: nowrap;">';
                                print '<span class="fa fa-external-link"></span> '.dol_escape_htmltag($int_obj->ref);
                                print '</a>';
                            } else {
                                // Status sagt "In Bearbeitung" aber kein Link gefunden
                                print '<span class="badge badge-status4" style="background: #4caf50;">';
                                print '<span class="fa fa-cog fa-spin"></span> '.$langs->trans('InProgress');
                                print '</span>';
                            }
                            $db->free($resql_int);
                        } else {
                            // SQL Fehler
                            print '<span class="badge badge-status4" style="background: #4caf50;">';
                            print '<span class="fa fa-cog fa-spin"></span> '.$langs->trans('InProgress');
                            print '</span><br>';
                            print '<span class="opacitymedium" style="font-size: 0.8em;">Error: '.$db->lasterror().'</span>';
                        }
                    } else {
                        print '<span class="badge badge-status8" style="background: #f44336;">';
                        print '<span class="fa fa-exclamation-triangle"></span> '.$langs->trans('Pending');
                        print '</span>';
                    }
                    print '</td>';
                    
                    print '<td class="center nowrap">';
                    if ($user->rights->equipmentmanager->equipment->write) {
                        print '<a class="button smallpaddingimp" style="padding: 3px 8px; font-size: 0.9em;" href="'.$_SERVER["PHP_SELF"].'?action=mark_completed&equipment_id='.$equip->rowid.'&confirm=yes&token='.newToken().'" ';
                        print 'onclick="return confirm(\''.$langs->trans('ConfirmMarkCompleted').'\');" ';
                        print 'title="'.$langs->trans('MarkAsCompletedManually').'">';
                        print '<span class="fa fa-check"></span>';
                        print '</a>';
                    }
                    print '</td>';
                    
                    print '</tr>';
                }
                
                print '</table>';
                print '</div>';
            }
        }
        
    } else {
        print '<div class="info maintenance-summary" style="padding: 30px; text-align: center;">';
        print '<span class="fa fa-check-circle" style="font-size: 48px; color: #4caf50;"></span>';
        print '<h3>'.$langs->trans('NoMaintenanceDue').'</h3>';
        print '<p class="opacitymedium">'.$langs->trans('NoMaintenanceDueDescription').'</p>';
        print '</div>';
    }
    
    $db->free($resql);
} else {
    dol_print_error($db);
}

// Legende
print '<br><br>';
print '<div class="info maintenance-legend">';
print '<h4><span class="fa fa-info-circle"></span> '.$langs->trans('Legend').'</h4>';
print '<ul>';
print '<li><span class="badge badge-status8" style="background: #f44336;"><span class="fa fa-exclamation-triangle"></span> '.$langs->trans('Pending').'</span> - '.$langs->trans('MaintenancePendingDescription').'</li>';
print '<li><span class="badge badge-status4" style="background: #4caf50;"><span class="fa fa-cog fa-spin"></span> '.$langs->trans('InProgress').'</span> - '.$langs->trans('MaintenanceInProgressDescription').'</li>';
print '</ul>';
print '<p class="opacitymedium">'.$langs->trans('MaintenanceDashboardHelp').'</p>';
print '</div>';

llxFooter();
$db->close();