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

// Status filter: -1=all, 0=draft, 1=open, 2=billed, 3=closed
$status  = GETPOST('status', 'int');
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

// ─── Build SQL ───────────────────────────────────────────────────────────────
// GROUP BY f.rowid avoids duplicates caused by multiple fichinterdet rows
// (Dolibarr creates one row per source-document line when creating from order/proposal)

$sql  = "SELECT f.rowid, f.ref, f.fk_soc, f.fk_statut, f.dateo, f.datee, f.datec,";
$sql .= " s.nom as societe_name,";
$sql .= " u.login, u.lastname, u.firstname";
$sql .= " FROM ".MAIN_DB_PREFIX."fichinter as f";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON s.rowid = f.fk_soc";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."user as u ON u.rowid = f.fk_user_author";
$sql .= " WHERE f.entity IN (".getEntity('intervention').")";

if ($status >= 0) {
    $sql .= " AND f.fk_statut = ".(int)$status;
}
if ($search_ref) {
    $sql .= " AND f.ref LIKE '%".$db->escape($search_ref)."%'";
}
if ($search_societe) {
    $sql .= " AND s.nom LIKE '%".$db->escape($search_societe)."%'";
}

$sql .= " GROUP BY f.rowid";
$sql .= $db->order($sortfield, $sortorder);

// Count total for pagination
$sqlcount  = "SELECT COUNT(DISTINCT f.rowid) as nb";
$sqlcount .= " FROM ".MAIN_DB_PREFIX."fichinter as f";
$sqlcount .= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON s.rowid = f.fk_soc";
$sqlcount .= " WHERE f.entity IN (".getEntity('intervention').")";
if ($status >= 0) {
    $sqlcount .= " AND f.fk_statut = ".(int)$status;
}
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
$statusCounts = array(-1 => 0, 0 => 0, 1 => 0, 2 => 0, 3 => 0);
$sqlcnt = "SELECT f.fk_statut, COUNT(DISTINCT f.rowid) as nb"
        . " FROM ".MAIN_DB_PREFIX."fichinter as f"
        . " WHERE f.entity IN (".getEntity('intervention').")"
        . " GROUP BY f.fk_statut";
$rescnt = $db->query($sqlcnt);
if ($rescnt) {
    while ($obj = $db->fetch_object($rescnt)) {
        $statusCounts[(int)$obj->fk_statut] = (int)$obj->nb;
        $statusCounts[-1] += (int)$obj->nb;
    }
}

// ─── Page output ─────────────────────────────────────────────────────────────
$title = $langs->trans('ServiceOrderList');
llxHeader('', $title);

$form = new Form($db);

// Status tab definitions
$statusDefs = array(
    -1 => array('label' => $langs->trans('ServiceOrderStatusAll'),    'color' => ''),
     0 => array('label' => $langs->trans('ServiceOrderStatusDraft'),  'color' => '#888'),
     1 => array('label' => $langs->trans('ServiceOrderStatusOpen'),   'color' => '#2196F3'),
     2 => array('label' => $langs->trans('ServiceOrderStatusBilled'), 'color' => '#FF9800'),
     3 => array('label' => $langs->trans('ServiceOrderStatusClosed'), 'color' => '#4CAF50'),
);

// ─── Status tabs ─────────────────────────────────────────────────────────────
print '<div class="tabsAction" style="margin-bottom:0;">';
print '<div class="tabs" data-role="controlgroup" data-type="horizontal">';
foreach ($statusDefs as $st => $def) {
    $active = ($status == $st) ? ' tabactive' : '';
    $badge  = $statusCounts[$st] > 0 ? ' <span class="badge" style="background:'.($def['color'] ?: '#666').'">'.$statusCounts[$st].'</span>' : '';
    print '<a href="'.dol_buildpath('/equipmentmanager/service_order_list.php', 1).'?status='.$st.'" class="tab'.$active.'">';
    print $def['label'].$badge;
    print '</a>';
}
print '</div>';
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
print '<thead><tr class="liste_titre">';
print '<th class="liste_titre"><a href="?status='.(int)$status.'&sortfield=f.ref&sortorder='.($sortfield=='f.ref'&&$sortorder=='ASC'?'DESC':'ASC').'">'.$langs->trans('Ref').'</a></th>';
print '<th class="liste_titre"><a href="?status='.(int)$status.'&sortfield=s.nom&sortorder='.($sortfield=='s.nom'&&$sortorder=='ASC'?'DESC':'ASC').'">'.$langs->trans('Company').'</a></th>';
print '<th class="liste_titre"><a href="?status='.(int)$status.'&sortfield=f.dateo&sortorder='.($sortfield=='f.dateo'&&$sortorder=='ASC'?'DESC':'ASC').'">'.$langs->trans('DateStart').'</a></th>';
print '<th class="liste_titre">'.$langs->trans('ServiceOrderTechName').'</th>';
print '<th class="liste_titre right">'.$langs->trans('Status').'</th>';
print '</tr></thead>';
print '<tbody>';

// Status label helper
$statusLabels = array(
    0 => '<span style="color:#888">'.$langs->trans('ServiceOrderStatusDraft').'</span>',
    1 => '<span style="color:#2196F3;font-weight:bold">'.$langs->trans('ServiceOrderStatusOpen').'</span>',
    2 => '<span style="color:#FF9800;font-weight:bold">'.$langs->trans('ServiceOrderStatusBilled').'</span>',
    3 => '<span style="color:#4CAF50">'.$langs->trans('ServiceOrderStatusClosed').'</span>',
);

if ($resql) {
    $num = $db->num_rows($resql);
    if ($num == 0) {
        print '<tr><td colspan="5" class="opacitymedium center">'.$langs->trans('NoServiceOrders').'</td></tr>';
    }
    $i = 0;
    while ($i < $num && ($limit <= 0 || $i < $limit)) {
        $obj = $db->fetch_object($resql);
        $rowclass = ($i % 2 == 0) ? 'oddeven' : 'oddeven';
        print '<tr class="'.$rowclass.'">';

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

        // Date
        print '<td>'.dol_print_date($db->jdate($obj->dateo), 'day').'</td>';

        // Technician
        $techname = trim($obj->firstname.' '.$obj->lastname);
        if (!$techname) {
            $techname = $obj->login;
        }
        print '<td>'.dol_escape_htmltag($techname).'</td>';

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
