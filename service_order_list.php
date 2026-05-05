<?php
/* Copyright (C) 2024 Equipment Manager
 * Service order list with status tabs and duplicate-free display
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
if (!$res) {
    die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/fichinter/class/fichinter.class.php';

$langs->loadLangs(array("equipmentmanager@equipmentmanager", "interventions", "companies"));

if (!$user->hasRight('ficheinter', 'lire')) {
    accessforbidden();
}
$permissiontoadd = $user->hasRight('ficheinter', 'creer');

// Status filter: -1=all, 1=open (draft+open), 2=billed, 3=closed
// Note: Dolibarr status 0 (draft) is treated as "Offen" in this view
$status = GETPOST('status', 'int');
if ($status === '') {
    $status = 1;
}
$status = (int) $status;

$search_ref      = GETPOST('search_ref', 'alpha');
$search_societe  = GETPOST('search_societe', 'alpha');

$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
if (!$sortfield) {
    $sortfield = 'f.dateo';
}
if (!$sortorder) {
    $sortorder = 'DESC';
}

$limit  = $conf->liste_limit ? $conf->liste_limit : 25;
$page   = GETPOSTISSET('pageplusone') ? (GETPOST('pageplusone', 'int') - 1) : GETPOST('page', 'int');
if ($page < 0) {
    $page = 0;
}
$offset = $limit * $page;

// ─── Status SQL helper ────────────────────────────────────────────────────────
// status=1 (Offen) covers Dolibarr fk_statut 0 (draft) and 1 (validated)
function buildStatusFilter($status, $prefix = 'f')
{
    if ($status == 1) {
        return " AND ".$prefix.".fk_statut IN (0, 1)";
    } elseif ($status == 2 || $status == 3) {
        return " AND ".$prefix.".fk_statut = ".(int)$status;
    }
    return ''; // -1 = all
}

// ─── Column visibility ───────────────────────────────────────────────────────
$showObjAddress  = getDolGlobalString('EQUIPMENTMANAGER_SOL_COL_OBJADDRESS',  '1') != '0';
$showNbAnlagen   = getDolGlobalString('EQUIPMENTMANAGER_SOL_COL_NBANLAGEN',   '0') != '0';
$showTypes       = getDolGlobalString('EQUIPMENTMANAGER_SOL_COL_TYPES',       '0') != '0';
$showDescription = getDolGlobalString('EQUIPMENTMANAGER_SOL_COL_DESCRIPTION', '0') != '0';
$showTech        = getDolGlobalString('EQUIPMENTMANAGER_SOL_COL_TECH',        '1') != '0';

// ─── Build SQL ───────────────────────────────────────────────────────────────
// GROUP BY f.rowid avoids duplicates from multiple equipment/detail rows

$sql  = "SELECT f.rowid, f.ref, f.fk_soc, f.fk_statut, f.dateo, f.datee, f.datec, f.description,";
$sql .= " s.nom as societe_name,";
$sql .= " u.login, u.lastname, u.firstname,";
$sql .= " COALESCE(MIN(sp.address), MIN(sp_obj.address)) as obj_address,";
$sql .= " COALESCE(MIN(sp.zip), MIN(sp_obj.zip)) as obj_zip,";
$sql .= " COALESCE(MIN(sp.town), MIN(sp_obj.town)) as obj_town,";
$sql .= " COUNT(DISTINCT eid.fk_equipment) as nb_equipment,";
$sql .= " GROUP_CONCAT(DISTINCT eq.equipment_type ORDER BY eq.equipment_type SEPARATOR ',') as equipment_types";
$sql .= " FROM ".MAIN_DB_PREFIX."fichinter as f";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON s.rowid = f.fk_soc";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."user as u ON u.rowid = f.fk_user_author";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."equipmentmanager_intervention_detail as eid ON eid.fk_intervention = f.rowid AND eid.fk_equipment IS NOT NULL";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."equipmentmanager_equipment as eq ON eq.rowid = eid.fk_equipment";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."socpeople as sp ON sp.rowid = eq.fk_address";
// Fallback: OBJ-Kontakt direkt am Serviceauftrag (wenn keine Anlage mit Adresse verknüpft)
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."element_contact as ec ON ec.element_id = f.rowid";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."c_type_contact as ctc ON ctc.rowid = ec.fk_c_type_contact AND ctc.code = 'OBJ' AND ctc.element = 'fichinter'";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."socpeople as sp_obj ON sp_obj.rowid = ec.fk_socpeople AND ctc.rowid IS NOT NULL";
$sql .= " WHERE f.entity IN (".getEntity('intervention').")";
$sql .= buildStatusFilter($status);
if ($search_ref) {
    $sql .= " AND f.ref LIKE '%".$db->escape($search_ref)."%'";
}
if ($search_societe) {
    $sql .= " AND s.nom LIKE '%".$db->escape($search_societe)."%'";
}
$sql .= " GROUP BY f.rowid";
// When viewing "Alle": sort by status priority (Offen→Abrechnen→Abgeschlossen) first, then by selected sort
if ($status == -1) {
    $sql .= " ORDER BY CASE f.fk_statut WHEN 0 THEN 1 WHEN 1 THEN 1 WHEN 2 THEN 2 WHEN 3 THEN 3 ELSE 4 END ASC";
    $sql .= ", ".$db->sanitize($sortfield)." ".$db->sanitize($sortorder);
} else {
    $sql .= $db->order($sortfield, $sortorder);
}

// Count total for pagination
$sqlcount  = "SELECT COUNT(DISTINCT f.rowid) as nb";
$sqlcount .= " FROM ".MAIN_DB_PREFIX."fichinter as f";
$sqlcount .= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON s.rowid = f.fk_soc";
$sqlcount .= " WHERE f.entity IN (".getEntity('intervention').")";
$sqlcount .= buildStatusFilter($status);
if ($search_ref) {
    $sqlcount .= " AND f.ref LIKE '%".$db->escape($search_ref)."%'";
}
if ($search_societe) {
    $sqlcount .= " AND s.nom LIKE '%".$db->escape($search_societe)."%'";
}

$rescount = $db->query($sqlcount);
$nbtotalofrecords = 0;
if ($rescount) {
    $objcount = $db->fetch_object($rescount);
    $nbtotalofrecords = $objcount->nb;
}

$sql .= $db->plimit($limit, $offset);
$resql = $db->query($sql);

// ─── Status counts for tab badges ────────────────────────────────────────────
// Tab "Offen" (key=1) = Dolibarr status 0 + 1 combined
$statusCounts = array(-1 => 0, 1 => 0, 2 => 0, 3 => 0);
$sqlcnt = "SELECT f.fk_statut, COUNT(DISTINCT f.rowid) as nb"
        . " FROM ".MAIN_DB_PREFIX."fichinter as f"
        . " WHERE f.entity IN (".getEntity('intervention').")"
        . " GROUP BY f.fk_statut";
$rescnt = $db->query($sqlcnt);
if ($rescnt) {
    while ($obj = $db->fetch_object($rescnt)) {
        $st = (int)$obj->fk_statut;
        $nb = (int)$obj->nb;
        // Draft (0) counts as Offen (1)
        $tabKey = ($st === 0) ? 1 : $st;
        if (isset($statusCounts[$tabKey])) {
            $statusCounts[$tabKey] += $nb;
        }
        $statusCounts[-1] += $nb;
    }
}

// ─── Page output ─────────────────────────────────────────────────────────────
$title = $langs->trans('ServiceOrderList');
llxHeader('', $title);

$form = new Form($db);

// Status tab definitions — no draft tab
$statusDefs = array(
     1 => array('label' => $langs->trans('ServiceOrderStatusOpen'),   'color' => '#2196F3'),
     2 => array('label' => $langs->trans('ServiceOrderStatusBilled'), 'color' => '#FF9800'),
     3 => array('label' => $langs->trans('ServiceOrderStatusClosed'), 'color' => '#4CAF50'),
    -1 => array('label' => $langs->trans('ServiceOrderStatusAll'),    'color' => ''),
);

// ─── Status tabs ─────────────────────────────────────────────────────────────
print '<style>';
print '.em-status-tabs { display:flex; flex-wrap:nowrap; overflow-x:auto; overflow-y:hidden; -webkit-overflow-scrolling:touch; border-bottom:2px solid #ddd; margin-bottom:8px; gap:0; scrollbar-width:none; -ms-overflow-style:none; }';
print '.em-status-tabs::-webkit-scrollbar { display:none; }';
print '.em-status-tabs a { display:inline-block; white-space:nowrap; padding:7px 14px; text-decoration:none; color:#555; border:1px solid transparent; border-bottom:none; margin-bottom:-2px; flex-shrink:0; font-size:0.9em; }';
print '.em-status-tabs a:hover { background:#f5f5f5; color:#333; }';
print '.em-status-tabs a.tabactive { background:#fff; border-color:#ddd; border-bottom-color:#fff; color:#000; font-weight:bold; }';
print '.em-status-tabs .badge { display:inline-block; padding:1px 6px; border-radius:10px; font-size:0.78em; color:#fff; margin-left:4px; vertical-align:middle; }';
print '</style>';
print '<div class="em-status-tabs">';
foreach ($statusDefs as $st => $def) {
    $active = ($status == $st) ? ' tabactive' : '';
    $badge  = $statusCounts[$st] > 0 ? ' <span class="badge" style="background:'.($def['color'] ?: '#666').'">'.$statusCounts[$st].'</span>' : '';
    print '<a href="'.dol_buildpath('/equipmentmanager/service_order_list.php', 1).'?status='.$st.'" class="'.$active.'">';
    print $def['label'].$badge;
    print '</a>';
}
print '</div>';

// ─── Search bar ──────────────────────────────────────────────────────────────
print '<form method="GET" action="'.dol_buildpath('/equipmentmanager/service_order_list.php', 1).'" style="margin:10px 0 8px;">';
print '<input type="hidden" name="status" value="'.(int)$status.'">';
print '<input type="text" name="search_ref" value="'.dol_escape_htmltag($search_ref).'" placeholder="'.$langs->trans('Ref').'" class="flat" style="width:130px; margin-right:6px;">';
print '<input type="text" name="search_societe" value="'.dol_escape_htmltag($search_societe).'" placeholder="'.$langs->trans('Company').'" class="flat" style="width:180px; margin-right:6px;">';
print '<button type="submit" class="button small">'.img_picto('', 'search').'</button>';
if ($search_ref || $search_societe) {
    print ' <a href="'.dol_buildpath('/equipmentmanager/service_order_list.php', 1).'?status='.(int)$status.'" class="button small">'.img_picto('', 'eraser').'</a>';
}
print '</form>';

// ─── Table ───────────────────────────────────────────────────────────────────
print '<div class="div-table-responsive">';
print '<table class="tagtable liste">';

// Header
$colspan = 3; // Ref + Company + Date + Status (always visible) = 4, but we count extras
print '<thead><tr class="liste_titre">';
print '<th class="liste_titre"><a href="?status='.(int)$status.'&sortfield=f.ref&sortorder='.($sortfield=='f.ref'&&$sortorder=='ASC'?'DESC':'ASC').'">'.$langs->trans('Ref').'</a></th>';
print '<th class="liste_titre"><a href="?status='.(int)$status.'&sortfield=s.nom&sortorder='.($sortfield=='s.nom'&&$sortorder=='ASC'?'DESC':'ASC').'">'.$langs->trans('Company').'</a></th>';
if ($showObjAddress)  { print '<th class="liste_titre">'.$langs->trans('ServiceOrderColObjAddress').'</th>'; }
if ($showDescription) { print '<th class="liste_titre">'.$langs->trans('ServiceOrderColDescription').'</th>'; }
if ($showNbAnlagen)   { print '<th class="liste_titre center">'.$langs->trans('ServiceOrderColNbAnlagen').'</th>'; }
if ($showTypes)       { print '<th class="liste_titre">'.$langs->trans('ServiceOrderColTypes').'</th>'; }
print '<th class="liste_titre"><a href="?status='.(int)$status.'&sortfield=f.dateo&sortorder='.($sortfield=='f.dateo'&&$sortorder=='ASC'?'DESC':'ASC').'">'.$langs->trans('Termin').'</a></th>';
if ($showTech)        { print '<th class="liste_titre">'.$langs->trans('ServiceOrderColTech').'</th>'; }
print '<th class="liste_titre right">'.$langs->trans('Status').'</th>';
print '</tr></thead>';
print '<tbody>';

// Status label helper — draft and open both shown as "Offen"
$statusLabels = array(
    0 => '<span style="color:#2196F3;font-weight:bold">'.$langs->trans('ServiceOrderStatusOpen').'</span>',
    1 => '<span style="color:#2196F3;font-weight:bold">'.$langs->trans('ServiceOrderStatusOpen').'</span>',
    2 => '<span style="color:#FF9800;font-weight:bold">'.$langs->trans('ServiceOrderStatusBilled').'</span>',
    3 => '<span style="color:#4CAF50">'.$langs->trans('ServiceOrderStatusClosed').'</span>',
);

if ($resql) {
    $num = $db->num_rows($resql);
    $totalCols = 4 + ($showObjAddress?1:0) + ($showDescription?1:0) + ($showNbAnlagen?1:0) + ($showTypes?1:0) + ($showTech?1:0);
    if ($num == 0) {
        print '<tr><td colspan="'.$totalCols.'" class="opacitymedium center">'.$langs->trans('NoServiceOrders').'</td></tr>';
    }
    $i = 0;
    while ($i < $num && ($limit <= 0 || $i < $limit)) {
        $obj = $db->fetch_object($resql);
        print '<tr class="oddeven">';

        // Ref → link to fichinter card
        $fichinterUrl = DOL_URL_ROOT.'/fichinter/card.php?id='.$obj->rowid;
        print '<td><a href="'.dol_escape_htmltag($fichinterUrl).'">'.dol_escape_htmltag($obj->ref).'</a></td>';

        // Customer
        if ($obj->fk_soc > 0) {
            $socUrl = DOL_URL_ROOT.'/societe/card.php?socid='.(int)$obj->fk_soc;
            print '<td><a href="'.dol_escape_htmltag($socUrl).'">'.dol_escape_htmltag($obj->societe_name).'</a></td>';
        } else {
            print '<td>—</td>';
        }

        // Objektadresse
        if ($showObjAddress) {
            $addrParts = array();
            if (!empty($obj->obj_address)) {
                $addrParts[] = $obj->obj_address;
            }
            $cityPart = trim($obj->obj_zip.' '.$obj->obj_town);
            if ($cityPart) {
                $addrParts[] = $cityPart;
            }
            print '<td>'.dol_escape_htmltag(implode(', ', $addrParts)).'</td>';
        }

        // Beschreibung
        if ($showDescription) {
            $desc = $obj->description ? substr(strip_tags($obj->description), 0, 60) : '';
            if (strlen($obj->description) > 60) {
                $desc .= '…';
            }
            print '<td>'.dol_escape_htmltag($desc).'</td>';
        }

        // Anzahl Anlagen
        if ($showNbAnlagen) {
            print '<td class="center">'.($obj->nb_equipment > 0 ? (int)$obj->nb_equipment : '—').'</td>';
        }

        // Anlagentypen
        if ($showTypes) {
            $typeTags = '';
            if (!empty($obj->equipment_types)) {
                $types = explode(',', $obj->equipment_types);
                $tagParts = array();
                foreach ($types as $t) {
                    $t = trim($t);
                    if (!$t) {
                        continue;
                    }
                    // DB stores snake_case (fire_gate), lang keys are CamelCase (FireGate)
                    $langKey = implode('', array_map('ucfirst', explode('_', $t)));
                    $label = $langs->trans($langKey);
                    // If no translation found, fall back to the raw value
                    if ($label === $langKey) {
                        $label = $t;
                    }
                    $tagParts[] = '<span style="display:inline-block;padding:1px 6px;border-radius:8px;background:#555;color:#fff;font-size:0.82em;margin:1px;">'.dol_escape_htmltag($label).'</span>';
                }
                $typeTags = implode(' ', $tagParts);
            }
            print '<td>'.($typeTags ?: '—').'</td>';
        }

        // Date + edit icon
        $tsStart = $db->jdate($obj->dateo);
        $tsEnd   = $db->jdate($obj->datee);
        // Use date() for input-compatible ISO values (server timezone, same as idate/jdate)
        $dateStartVal = $tsStart ? date('Y-m-d', $tsStart) : '';
        $timeStartVal = $tsStart ? date('H:i', $tsStart)   : '';
        $dateEndVal   = $tsEnd   ? date('Y-m-d', $tsEnd)   : '';
        $timeEndVal   = $tsEnd   ? date('H:i', $tsEnd)     : '';
        $isAllDay     = $tsStart && $timeStartVal === '00:00' && (!$tsEnd || $timeEndVal === '23:59');
        $displayDate  = $tsStart ? dol_print_date($tsStart, $isAllDay ? 'day' : 'dayhour') : '—';
        if ($tsEnd) {
            $displayDate .= ' – '.dol_print_date($tsEnd, $isAllDay ? 'day' : 'dayhour');
        }
        print '<td style="white-space:nowrap;">';
        print '<span id="dateDisplay_'.$obj->rowid.'" style="margin-right:4px;">'.$displayDate.'</span>';
        if ($permissiontoadd) {
            print '<a href="#" class="editScheduleBtn" style="color:#666;"';
            print ' data-id="'.((int)$obj->rowid).'"';
            print ' data-date-start="'.dol_escape_htmltag($dateStartVal).'"';
            print ' data-time-start="'.dol_escape_htmltag($timeStartVal).'"';
            print ' data-date-end="'.dol_escape_htmltag($dateEndVal).'"';
            print ' data-time-end="'.dol_escape_htmltag($timeEndVal).'"';
            print ' title="'.$langs->trans('EditSchedule').'">';
            print img_picto($langs->trans('EditSchedule'), 'edit');
            print '</a>';
        }
        print '</td>';

        // Technician
        if ($showTech) {
            $techname = trim($obj->firstname.' '.$obj->lastname);
            if (!$techname) {
                $techname = $obj->login;
            }
            print '<td>'.dol_escape_htmltag($techname).'</td>';
        }

        // Status
        $stLabel = isset($statusLabels[(int)$obj->fk_statut]) ? $statusLabels[(int)$obj->fk_statut] : $obj->fk_statut;
        print '<td class="right">'.$stLabel.'</td>';

        print '</tr>';
        $i++;
    }
} else {
    dol_print_error($db);
}

print '</tbody></table></div>';

// Pagination
print_barre_liste('', $page, dol_buildpath('/equipmentmanager/service_order_list.php', 1).'?status='.(int)$status.'&search_ref='.urlencode($search_ref).'&search_societe='.urlencode($search_societe).'&sortfield='.$sortfield.'&sortorder='.$sortorder, '', $sortfield, $sortorder, '', $num, $nbtotalofrecords, '', 0, '', '', $limit);

// ─── Schedule edit modal ─────────────────────────────────────────────────────
?>
<div id="scheduleModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:9999; align-items:center; justify-content:center;">
  <div style="background:#fff; border-radius:6px; padding:24px; max-width:360px; width:92%; box-shadow:0 4px 24px rgba(0,0,0,.3);">
    <h3 style="margin:0 0 14px;"><?php print $langs->trans('EditSchedule'); ?></h3>

    <label style="display:flex; align-items:center; gap:8px; margin-bottom:14px; cursor:pointer;">
      <input type="checkbox" id="schedAllDay" style="width:16px; height:16px;">
      <span><?php print $langs->trans('AllDay'); ?></span>
    </label>

    <div style="margin-bottom:12px;">
      <div style="font-size:.82em; color:#888; margin-bottom:3px;"><?php print $langs->trans('DateStart'); ?></div>
      <div style="display:flex; gap:6px;">
        <input type="date" id="schedDateStart" class="flat" style="flex:1; min-width:0;">
        <input type="time" id="schedTimeStart" class="flat" style="width:90px;">
      </div>
    </div>

    <div style="margin-bottom:14px;">
      <div style="font-size:.82em; color:#888; margin-bottom:3px;"><?php print $langs->trans('DateEnd'); ?></div>
      <div style="display:flex; gap:6px;">
        <input type="date" id="schedDateEnd" class="flat" style="flex:1; min-width:0;">
        <input type="time" id="schedTimeEnd" class="flat" style="width:90px;">
      </div>
    </div>

    <div id="schedError" style="color:#c0392b; margin:8px 0; display:none;"></div>
    <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:4px;">
      <button type="button" class="button" onclick="closeScheduleModal()"><?php print $langs->trans('Cancel'); ?></button>
      <button type="button" class="button button-save" onclick="saveSchedule()"><?php print $langs->trans('SaveSchedule'); ?></button>
    </div>
  </div>
</div>
<script>
(function() {
    var currentId = 0;
    var ajaxUrl = '<?php print dol_buildpath('/equipmentmanager/ajax/update_schedule.php', 1); ?>';

    var elAllDay   = document.getElementById('schedAllDay');
    var elDateS    = document.getElementById('schedDateStart');
    var elTimeS    = document.getElementById('schedTimeStart');
    var elDateE    = document.getElementById('schedDateEnd');
    var elTimeE    = document.getElementById('schedTimeEnd');

    // Helpers: date string ↔ ms
    function dateMs(dateStr, timeStr) {
        if (!dateStr) return null;
        var d = new Date(dateStr + 'T' + (timeStr || '00:00') + ':00');
        return isNaN(d) ? null : d.getTime();
    }
    function msToDate(ms) { return new Date(ms).toISOString().slice(0, 10); }
    function msToTime(ms) {
        var d = new Date(ms);
        return String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
    }

    // Toggle all-day: hide/show time inputs
    elAllDay.addEventListener('change', function() {
        var hide = this.checked;
        elTimeS.style.display = hide ? 'none' : '';
        elTimeE.style.display = hide ? 'none' : '';
    });

    // Auto-adjust end date when start date changes (keep same duration)
    elDateS.addEventListener('change', function() {
        var prevStartMs = dateMs(elDateS._prevValue, elTimeS.value);
        var endMs       = dateMs(elDateE.value, elTimeE.value);
        var newStartMs  = dateMs(elDateS.value, elTimeS.value);
        if (prevStartMs && endMs && newStartMs) {
            var delta = endMs - prevStartMs;
            var newEndMs = newStartMs + delta;
            elDateE.value = msToDate(newEndMs);
            elTimeE.value = msToTime(newEndMs);
        }
        elDateS._prevValue = elDateS.value;
    });

    // Also shift end time when start time changes
    elTimeS.addEventListener('change', function() {
        var prevStartMs = dateMs(elDateS.value, elTimeS._prevValue || '00:00');
        var endMs       = dateMs(elDateE.value, elTimeE.value);
        var newStartMs  = dateMs(elDateS.value, elTimeS.value);
        if (prevStartMs && endMs && newStartMs) {
            var delta = endMs - prevStartMs;
            var newEndMs = newStartMs + delta;
            elDateE.value = msToDate(newEndMs);
            elTimeE.value = msToTime(newEndMs);
        }
        elTimeS._prevValue = elTimeS.value;
    });

    document.querySelectorAll('.editScheduleBtn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            currentId = parseInt(this.dataset.id);
            elDateS.value = this.dataset.dateStart || '';
            elTimeS.value = this.dataset.timeStart || '';
            elDateE.value = this.dataset.dateEnd   || '';
            elTimeE.value = this.dataset.timeEnd   || '';
            elDateS._prevValue = elDateS.value;
            elTimeS._prevValue = elTimeS.value;

            // Detect all-day
            var isAllDay = elTimeS.value === '00:00' && (!elTimeE.value || elTimeE.value === '23:59');
            elAllDay.checked = isAllDay;
            elTimeS.style.display = isAllDay ? 'none' : '';
            elTimeE.style.display = isAllDay ? 'none' : '';

            document.getElementById('schedError').style.display = 'none';
            document.getElementById('scheduleModal').style.display = 'flex';
        });
    });

    window.closeScheduleModal = function() {
        document.getElementById('scheduleModal').style.display = 'none';
    };

    document.getElementById('scheduleModal').addEventListener('click', function(e) {
        if (e.target === this) closeScheduleModal();
    });

    window.saveSchedule = function() {
        if (!elDateS.value) {
            showSchedError('<?php print $langs->trans('DateStart'); ?> <?php print $langs->trans('Required'); ?>.');
            return;
        }
        var fd = new FormData();
        fd.append('id',         currentId);
        fd.append('date_start', elDateS.value);
        fd.append('time_start', elAllDay.checked ? '' : elTimeS.value);
        fd.append('date_end',   elDateE.value);
        fd.append('time_end',   elAllDay.checked ? '' : elTimeE.value);
        fd.append('allday',     elAllDay.checked ? '1' : '0');

        fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    var el = document.getElementById('dateDisplay_' + currentId);
                    if (el) {
                        var disp = data.date_start_display;
                        if (data.date_end_display) disp += ' – ' + data.date_end_display;
                        el.textContent = disp;
                    }
                    // Refresh data attributes for next open
                    var btn = document.querySelector('.editScheduleBtn[data-id="' + currentId + '"]');
                    if (btn) {
                        btn.dataset.dateStart = elDateS.value;
                        btn.dataset.timeStart = elAllDay.checked ? '00:00' : (elTimeS.value || '00:00');
                        btn.dataset.dateEnd   = elDateE.value;
                        btn.dataset.timeEnd   = elAllDay.checked ? '23:59' : (elTimeE.value || '');
                    }
                    closeScheduleModal();
                } else {
                    showSchedError(data.error || '<?php print $langs->trans('ScheduleSaveError'); ?>');
                }
            })
            .catch(function(err) {
                showSchedError('<?php print $langs->trans('ScheduleSaveError'); ?>');
            });
    };

    function showSchedError(msg) {
        var el = document.getElementById('schedError');
        el.textContent = msg;
        el.style.display = 'block';
    }
})();
</script>
<?php

llxFooter();
$db->close();
