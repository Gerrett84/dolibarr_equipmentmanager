<?php
/* Copyright (C) 2026 Equipment Manager
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file       admin/objectaddress_migrate.php
 * \ingroup    equipmentmanager
 * \brief      Guarded, one-time data migration for the v6 Objektadresse change:
 *             repoints equipmentmanager_equipment.fk_address from a Contact
 *             (llx_socpeople) to that contact's owning company (llx_societe),
 *             flagging the company as an Objektadresse along the way.
 *
 *             Hard pre-flight gate: refuses to run at all if any company has
 *             more than one distinct contact-address in use (see
 *             objectaddress_migration_report.php) - those must be cleaned up
 *             manually first, since a company has only one address and this
 *             would otherwise silently collapse distinct site addresses into one.
 *
 *             Soft gate: once run successfully, a sentinel const blocks a second
 *             run unless the admin explicitly checks "force" - a second run
 *             would misinterpret already-migrated fk_address values (now
 *             societe rowids) as contact rowids again, since both tables have
 *             independent id spaces and collisions are possible.
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

if (!$user->admin) {
    accessforbidden();
}

$page_name = "ObjectAddressMigrate";
$action = GETPOST('action', 'aZ09');
$force = GETPOST('force', 'int') ? 1 : 0;

/**
 * Companies with more than one distinct contact-address in use as fk_address.
 * Same query as objectaddress_migration_report.php - re-checked here as a hard
 * pre-flight gate, not just informational.
 *
 * @param DoliDB $db Database handler
 * @return int Number of flagged companies
 */
function eqmCountFlaggedCompanies($db)
{
    $sql = "SELECT COUNT(*) as nb FROM (";
    $sql .= "  SELECT s.rowid";
    $sql .= "  FROM ".MAIN_DB_PREFIX."equipmentmanager_equipment e";
    $sql .= "  INNER JOIN ".MAIN_DB_PREFIX."socpeople sp ON sp.rowid = e.fk_address";
    $sql .= "  INNER JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid = sp.fk_soc";
    $sql .= "  WHERE e.fk_address IS NOT NULL AND e.fk_address > 0";
    $sql .= "  GROUP BY s.rowid";
    $sql .= "  HAVING COUNT(DISTINCT CONCAT_WS('|', COALESCE(sp.address,''), COALESCE(sp.zip,''), COALESCE(sp.town,''))) > 1";
    $sql .= ") t";

    $resql = $db->query($sql);
    if ($resql) {
        $obj = $db->fetch_object($resql);
        $db->free($resql);
        return (int) $obj->nb;
    }
    return 0;
}

/**
 * Dry-run preview rows: equipment -> old contact/address -> target company.
 *
 * @param DoliDB $db Database handler
 * @return array List of stdClass rows
 */
function eqmPreviewRows($db)
{
    $sql = "SELECT e.rowid AS equipment_id, e.equipment_number, e.fk_address AS old_contact_id,";
    $sql .= " CONCAT(COALESCE(sp.lastname,''), ' ', COALESCE(sp.firstname,'')) AS old_contact_name,";
    $sql .= " sp.fk_soc AS new_company_id, s.nom AS new_company_name";
    $sql .= " FROM ".MAIN_DB_PREFIX."equipmentmanager_equipment e";
    $sql .= " INNER JOIN ".MAIN_DB_PREFIX."socpeople sp ON sp.rowid = e.fk_address";
    $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid = sp.fk_soc";
    $sql .= " WHERE e.fk_address IS NOT NULL AND e.fk_address > 0";
    $sql .= " AND sp.fk_soc IS NOT NULL AND sp.fk_soc > 0";
    $sql .= " ORDER BY e.equipment_number";

    $rows = array();
    $resql = $db->query($sql);
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $rows[] = $obj;
        }
        $db->free($resql);
    }
    return $rows;
}

/**
 * Equipment rows that cannot be migrated automatically (contact has no owning company).
 *
 * @param DoliDB $db Database handler
 * @return int Count
 */
function eqmCountOrphans($db)
{
    $sql = "SELECT COUNT(*) as nb FROM ".MAIN_DB_PREFIX."equipmentmanager_equipment e";
    $sql .= " INNER JOIN ".MAIN_DB_PREFIX."socpeople sp ON sp.rowid = e.fk_address";
    $sql .= " WHERE e.fk_address IS NOT NULL AND e.fk_address > 0";
    $sql .= " AND (sp.fk_soc IS NULL OR sp.fk_soc <= 0)";

    $resql = $db->query($sql);
    if ($resql) {
        $obj = $db->fetch_object($resql);
        $db->free($resql);
        return (int) $obj->nb;
    }
    return 0;
}

$alreadyMigratedAt = getDolGlobalString('EQUIPMENTMANAGER_FK_ADDRESS_MIGRATED');
$flaggedCount = eqmCountFlaggedCompanies($db);
$orphanCount = eqmCountOrphans($db);

$migrationDone = false;
$migrationError = '';

/*
 * Actions
 */
if ($action == 'migrate' && $user->admin) {
    if ($flaggedCount > 0) {
        $migrationError = $langs->trans("ObjectAddressMigrateBlockedDirty");
    } elseif (!empty($alreadyMigratedAt) && !$force) {
        $migrationError = $langs->trans("ObjectAddressMigrateBlockedAlreadyDone", $alreadyMigratedAt);
    } else {
        $db->begin();

        $sqlFlag = "INSERT INTO ".MAIN_DB_PREFIX."societe_extrafields (fk_object, equipmentmanager_object_address)";
        $sqlFlag .= " SELECT DISTINCT sp.fk_soc, 1";
        $sqlFlag .= " FROM ".MAIN_DB_PREFIX."equipmentmanager_equipment e";
        $sqlFlag .= " INNER JOIN ".MAIN_DB_PREFIX."socpeople sp ON sp.rowid = e.fk_address";
        $sqlFlag .= " WHERE e.fk_address IS NOT NULL AND e.fk_address > 0";
        $sqlFlag .= " AND sp.fk_soc IS NOT NULL AND sp.fk_soc > 0";
        $sqlFlag .= " ON DUPLICATE KEY UPDATE equipmentmanager_object_address = 1";

        $sqlRepoint = "UPDATE ".MAIN_DB_PREFIX."equipmentmanager_equipment e";
        $sqlRepoint .= " INNER JOIN ".MAIN_DB_PREFIX."socpeople sp ON sp.rowid = e.fk_address";
        $sqlRepoint .= " SET e.fk_address = sp.fk_soc";
        $sqlRepoint .= " WHERE e.fk_address IS NOT NULL AND e.fk_address > 0";
        $sqlRepoint .= " AND sp.fk_soc IS NOT NULL AND sp.fk_soc > 0";

        $ok = true;
        if (!$db->query($sqlFlag)) {
            $ok = false;
            $migrationError = $db->lasterror();
        }
        if ($ok && !$db->query($sqlRepoint)) {
            $ok = false;
            $migrationError = $db->lasterror();
        }

        if ($ok) {
            $db->commit();
            dolibarr_set_const($db, 'EQUIPMENTMANAGER_FK_ADDRESS_MIGRATED', dol_print_date(dol_now(), 'dayhourlog'), 'chaine', 0, '', $conf->entity);
            $migrationDone = true;
            $alreadyMigratedAt = getDolGlobalString('EQUIPMENTMANAGER_FK_ADDRESS_MIGRATED');
        } else {
            $db->rollback();
        }
    }
}

$previewRows = ($flaggedCount == 0) ? eqmPreviewRows($db) : array();

/*
 * View
 */
llxHeader('', $langs->trans($page_name));

$linkback = '<a href="'.dol_buildpath('/equipmentmanager/admin/setup.php', 1).'">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans($page_name), $linkback, 'title_setup');

print '<div class="info">'.$langs->trans("ObjectAddressMigrateHelp").'</div>';

if ($migrationDone) {
    print '<div class="ok">'.$langs->trans("ObjectAddressMigrateSuccess", count($previewRows)).'</div>';
} elseif ($migrationError) {
    print '<div class="error">'.dol_escape_htmltag($migrationError).'</div>';
}

// Gate 1: dirty companies (hard block)
if ($flaggedCount > 0) {
    print '<div class="error">';
    print $langs->trans("ObjectAddressMigrateBlockedDirty").' ';
    print '<a href="'.dol_buildpath('/equipmentmanager/admin/objectaddress_migration_report.php', 1).'">'.$langs->trans("ObjectAddressMigrationReport").'</a>';
    print '</div>';
    llxFooter();
    $db->close();
    exit;
}

// Gate 2: already migrated (soft block, overridable)
if (!empty($alreadyMigratedAt) && !$migrationDone) {
    print '<div class="warning">'.$langs->trans("ObjectAddressAlreadyMigrated", $alreadyMigratedAt).'</div>';
}

if ($orphanCount > 0) {
    print '<div class="warning">'.$langs->trans("ObjectAddressOrphansWillBeSkipped", $orphanCount).' ';
    print '<a href="'.dol_buildpath('/equipmentmanager/admin/objectaddress_migration_report.php', 1).'">'.$langs->trans("ObjectAddressMigrationReport").'</a>';
    print '</div>';
}

if (!$migrationDone) {
    print '<br>';
    print load_fiche_titre($langs->trans("ObjectAddressMigratePreview"), '', '');

    if (count($previewRows) > 0) {
        print '<div class="div-table-responsive-no-min">';
        print '<table class="noborder centpercent">';
        print '<tr class="liste_titre">';
        print '<th>'.$langs->trans("Equipment").'</th>';
        print '<th>'.$langs->trans("ObjectAddressOldContact").'</th>';
        print '<th>'.$langs->trans("ObjectAddressNewCompany").'</th>';
        print '</tr>';
        foreach ($previewRows as $row) {
            print '<tr class="oddeven">';
            print '<td><a href="'.dol_buildpath('/equipmentmanager/equipment_view.php', 1).'?id='.((int) $row->equipment_id).'" target="_blank">'.dol_escape_htmltag($row->equipment_number).'</a></td>';
            print '<td>'.dol_escape_htmltag(trim($row->old_contact_name)).'</td>';
            print '<td>'.dol_escape_htmltag($row->new_company_name).'</td>';
            print '</tr>';
        }
        print '</table>';
        print '</div>';

        print '<br>';
        print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
        print '<input type="hidden" name="token" value="'.newToken().'">';
        print '<input type="hidden" name="action" value="migrate">';
        if (!empty($alreadyMigratedAt)) {
            print '<label><input type="checkbox" name="force" value="1"> '.$langs->trans("ObjectAddressForceRerun").'</label><br><br>';
        }
        print '<button type="submit" class="butAction" onclick="return confirm(\''.dol_escape_js($langs->trans("ObjectAddressConfirmMigrate")).'\');">'.$langs->trans("ObjectAddressRunMigration").'</button>';
        print '</form>';
    } else {
        print '<div class="info">'.$langs->trans("ObjectAddressNothingToMigrate").'</div>';
    }
}

llxFooter();
$db->close();
