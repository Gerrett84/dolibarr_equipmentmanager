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

// Status filter: -1=all, 1=open (draft+open), 2=billed, 3=closed
// Note: Dolibarr status 0 (draft) is treated as "Offen" in this view
$status = GETPOST('status', 'int');
if ($status === '') {
    $status = -1;
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
$sql .= " MIN(sp.address) as obj_address, MIN(sp.zip) as obj_zip, MIN(sp.town) as obj_town,";
$sql .= " COUNT(DISTINCT eid.fk_equipment) as nb_equipment,";
$sql .= " GROUP_CONCAT(DISTINCT eq.equipment_type ORDER BY eq.equipment_type SEPARATOR ',') as equipment_types";
$sql .= " FROM ".MAIN_DB_PREFIX."fichinter as f";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON s.rowid = f.fk_soc";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."user as u ON u.rowid = f.fk_user_author";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."equipmentmanager_intervention_detail as eid ON eid.fk_intervention = f.rowid AND eid.fk_equipment IS NOT NULL";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."equipmentmanager_equipment as eq ON eq.rowid = eid.fk_equipment";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."socpeople as sp ON sp.rowid = eq.fk_address";
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
    -1 => array('label' => $langs->trans('ServiceOrderStatusAll'),    'color' => ''),
     1 => array('label' => $langs->trans('ServiceOrderStatusOpen'),   'color' => '#2196F3'),
     2 => array('label' => $langs->trans('ServiceOrderStatusBilled'), 'color' => '#FF9800'),
     3 => array('label' => $langs->trans('ServiceOrderStatusClosed'), 'color' => '#4CAF50'),
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
print '<th class="liste_titre"><a href="?status='.(int)$status.'&sortfield=f.dateo&sortorder='.($sortfield=='f.dateo'&&$sortorder=='ASC'?'DESC':'ASC').'">'.$langs->trans('DateStart').'</a></th>';
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
                    if ($t) {
                        $tagParts[] = '<span style="display:inline-block;padding:1px 6px;border-radius:8px;background:#e8e8e8;font-size:0.82em;margin:1px;">'.dol_escape_htmltag($langs->trans($t)).'</span>';
                    }
                }
                $typeTags = implode(' ', $tagParts);
            }
            print '<td>'.($typeTags ?: '—').'</td>';
        }

        // Date
        print '<td>'.dol_print_date($db->jdate($obj->dateo), 'day').'</td>';

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

llxFooter();
$db->close();
