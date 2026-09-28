<?php
/* Copyright (C) 2026 Equipment Manager
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file       admin/objectaddress_migration_report.php
 * \ingroup    equipmentmanager
 * \brief      Read-only pre-migration report for the v6 Objektadresse (fk_address:
 *             Contact -> Thirdparty) change. Identifies companies whose contacts are
 *             used as more than one distinct Objektadresse across Equipment records -
 *             those would silently collapse into a single company address once
 *             fk_address is repointed from the contact to its owning company, so they
 *             need manual review/cleanup before running objectaddress_migrate.php.
 */

// Load Dolibarr environment
$res = 0;
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
    $i--;
    $j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
    $res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
    $res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
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

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';

$langs->loadLangs(array("admin", "equipmentmanager@equipmentmanager", "companies"));

$form = new Form($db);

// Access control
if (!$user->admin) {
    accessforbidden();
}

$page_name = "ObjectAddressMigrationReport";

/*
 * Main query: companies whose contacts are used as fk_address on more than
 * one distinct address across equipmentmanager_equipment rows.
 */
$sql = "SELECT s.rowid AS company_id, s.nom AS company_name,";
$sql .= " COUNT(DISTINCT CONCAT_WS('|', COALESCE(sp.address,''), COALESCE(sp.zip,''), COALESCE(sp.town,''))) AS distinct_address_count,";
$sql .= " GROUP_CONCAT(DISTINCT CONCAT(sp.rowid, ': ', COALESCE(sp.address,''), ', ', COALESCE(sp.zip,''), ' ', COALESCE(sp.town,'')) SEPARATOR ' || ') AS address_list,";
$sql .= " GROUP_CONCAT(DISTINCT e.rowid ORDER BY e.rowid SEPARATOR ',') AS equipment_ids";
$sql .= " FROM ".MAIN_DB_PREFIX."equipmentmanager_equipment e";
$sql .= " INNER JOIN ".MAIN_DB_PREFIX."socpeople sp ON sp.rowid = e.fk_address";
$sql .= " INNER JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid = sp.fk_soc";
$sql .= " WHERE e.fk_address IS NOT NULL AND e.fk_address > 0";
$sql .= " GROUP BY s.rowid, s.nom";
$sql .= " HAVING distinct_address_count > 1";
$sql .= " ORDER BY distinct_address_count DESC, s.nom";

$flagged = array();
$resql = $db->query($sql);
if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        $flagged[] = $obj;
    }
    $db->free($resql);
} else {
    dol_print_error($db);
}

/*
 * Orphaned contacts: fk_address points to a contact with no owning company,
 * so it cannot be auto-repointed by objectaddress_migrate.php at all.
 */
$sqlOrphans = "SELECT e.rowid AS equipment_id, e.equipment_number, e.fk_address, sp.rowid AS contact_id,";
$sqlOrphans .= " CONCAT(COALESCE(sp.lastname,''), ' ', COALESCE(sp.firstname,'')) AS contact_name";
$sqlOrphans .= " FROM ".MAIN_DB_PREFIX."equipmentmanager_equipment e";
$sqlOrphans .= " INNER JOIN ".MAIN_DB_PREFIX."socpeople sp ON sp.rowid = e.fk_address";
$sqlOrphans .= " WHERE e.fk_address IS NOT NULL AND e.fk_address > 0";
$sqlOrphans .= " AND (sp.fk_soc IS NULL OR sp.fk_soc <= 0)";
$sqlOrphans .= " ORDER BY e.equipment_number";

$orphans = array();
$resqlOrphans = $db->query($sqlOrphans);
if ($resqlOrphans) {
    while ($obj = $db->fetch_object($resqlOrphans)) {
        $orphans[] = $obj;
    }
    $db->free($resqlOrphans);
}

/*
 * Summary counts.
 */
$sqlSummary = "SELECT COUNT(*) as nb_equipment, COUNT(DISTINCT sp.fk_soc) as nb_companies";
$sqlSummary .= " FROM ".MAIN_DB_PREFIX."equipmentmanager_equipment e";
$sqlSummary .= " INNER JOIN ".MAIN_DB_PREFIX."socpeople sp ON sp.rowid = e.fk_address";
$sqlSummary .= " WHERE e.fk_address IS NOT NULL AND e.fk_address > 0";

$nbEquipmentTotal = 0;
$nbCompaniesTotal = 0;
$resqlSummary = $db->query($sqlSummary);
if ($resqlSummary) {
    $objSummary = $db->fetch_object($resqlSummary);
    $nbEquipmentTotal = (int) $objSummary->nb_equipment;
    $nbCompaniesTotal = (int) $objSummary->nb_companies;
    $db->free($resqlSummary);
}

/*
 * View
 */
llxHeader('', $langs->trans($page_name));

$linkback = '<a href="'.dol_buildpath('/equipmentmanager/admin/setup.php', 1).'">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans($page_name), $linkback, 'title_setup');

print '<div class="info">'.$langs->trans("ObjectAddressMigrationReportHelp").'</div>';

// Summary
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans("Summary").'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans("EquipmentWithObjectAddress").'</td><td>'.$nbEquipmentTotal.'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans("CompaniesUsedAsObjectAddress").'</td><td>'.$nbCompaniesTotal.'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans("CompaniesWithMultipleAddresses").'</td><td>';
if (count($flagged) > 0) {
    print '<span class="badge badge-status8" style="background:#f44336;">'.count($flagged).'</span>';
} else {
    print '<span class="badge badge-status4" style="background:#4caf50;">0</span> '.$langs->trans("ObjectAddressReportClean");
}
print '</td></tr>';
print '</table>';
print '</div>';

print '<br>';

// Flagged companies (need manual review)
print load_fiche_titre($langs->trans("CompaniesWithMultipleAddresses"), '', '');
if (count($flagged) > 0) {
    print '<div class="div-table-responsive-no-min">';
    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre">';
    print '<th>'.$langs->trans("Company").'</th>';
    print '<th class="center">'.$langs->trans("ObjectAddressDistinctCount").'</th>';
    print '<th>'.$langs->trans("Addresses").'</th>';
    print '<th>'.$langs->trans("Equipment").'</th>';
    print '</tr>';

    foreach ($flagged as $row) {
        $societe = new Societe($db);
        $societe->fetch($row->company_id);

        print '<tr class="oddeven">';
        print '<td>'.$societe->getNomUrl(1).'</td>';
        print '<td class="center"><span class="badge badge-status8" style="background:#f44336;">'.$row->distinct_address_count.'</span></td>';
        print '<td>'.dol_escape_htmltag($row->address_list).'</td>';
        print '<td>';
        $eqIds = explode(',', $row->equipment_ids);
        foreach ($eqIds as $eqId) {
            print '<a href="'.dol_buildpath('/equipmentmanager/equipment_view.php', 1).'?id='.((int) $eqId).'" target="_blank">#'.(int) $eqId.'</a> ';
        }
        print '</td>';
        print '</tr>';
    }
    print '</table>';
    print '</div>';
} else {
    print '<div class="info">'.$langs->trans("ObjectAddressReportClean").'</div>';
}

// Orphaned contacts
if (count($orphans) > 0) {
    print '<br>';
    print load_fiche_titre($langs->trans("OrphanedObjectAddressContacts"), '', '');
    print '<div class="info">'.$langs->trans("OrphanedObjectAddressContactsHelp").'</div>';
    print '<div class="div-table-responsive-no-min">';
    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre">';
    print '<th>'.$langs->trans("Equipment").'</th>';
    print '<th>'.$langs->trans("ObjectAddress").'</th>';
    print '</tr>';
    foreach ($orphans as $row) {
        print '<tr class="oddeven">';
        print '<td><a href="'.dol_buildpath('/equipmentmanager/equipment_view.php', 1).'?id='.((int) $row->equipment_id).'" target="_blank">'.dol_escape_htmltag($row->equipment_number).'</a></td>';
        print '<td>'.dol_escape_htmltag(trim($row->contact_name)).' (id '.(int) $row->contact_id.')</td>';
        print '</tr>';
    }
    print '</table>';
    print '</div>';
}

llxFooter();
$db->close();
