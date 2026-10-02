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
 *             equipmentmanager_equipment.fk_address used to point to a Contact
 *             (llx_socpeople); it now points to a Third party flagged as
 *             Objektadresse. Each distinct contact in use becomes its own new
 *             Objektadresse third party carrying the contact's name and address
 *             (an existing flagged third party with identical name/address is
 *             reused). Contacts without own address data fall back to the
 *             address of their company.
 *
 *             Hard gate: once run, a sentinel const blocks any second run, since
 *             already-migrated fk_address values (now third party ids) would be
 *             misread as contact ids.
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
 * Contacts currently referenced as fk_address, with the data the new Objektadresse
 * will be built from. Address falls back to the company when the contact has none.
 *
 * @param DoliDB $db Database handler
 * @return array List of stdClass rows (one per equipment)
 */
function eqmLoadSourceRows($db)
{
    $sql = "SELECT e.rowid AS equipment_id, e.equipment_number, e.fk_address AS contact_id,";
    $sql .= " sp.lastname, sp.firstname, sp.address AS c_address, sp.zip AS c_zip, sp.town AS c_town,";
    $sql .= " sp.fk_pays AS c_pays, sp.fk_departement AS c_state, sp.phone AS c_phone, sp.email AS c_email,";
    $sql .= " s.nom AS s_name, s.address AS s_address, s.zip AS s_zip, s.town AS s_town,";
    $sql .= " s.fk_pays AS s_pays, s.fk_departement AS s_state";
    $sql .= " FROM ".MAIN_DB_PREFIX."equipmentmanager_equipment e";
    $sql .= " INNER JOIN ".MAIN_DB_PREFIX."socpeople sp ON sp.rowid = e.fk_address";
    $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid = sp.fk_soc";
    $sql .= " WHERE e.fk_address IS NOT NULL AND e.fk_address > 0";
    $sql .= " ORDER BY e.equipment_number";

    $rows = array();
    $resql = $db->query($sql);
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $name = trim(trim((string) $obj->lastname).' '.trim((string) $obj->firstname));
            $hasOwnAddress = (trim((string) $obj->c_address) !== '' || trim((string) $obj->c_zip) !== '' || trim((string) $obj->c_town) !== '');
            $obj->target_name = ($name !== '') ? $name : (string) $obj->s_name;
            $obj->target_address = $hasOwnAddress ? (string) $obj->c_address : (string) $obj->s_address;
            $obj->target_zip = $hasOwnAddress ? (string) $obj->c_zip : (string) $obj->s_zip;
            $obj->target_town = $hasOwnAddress ? (string) $obj->c_town : (string) $obj->s_town;
            $obj->target_pays = $hasOwnAddress ? (int) $obj->c_pays : (int) $obj->s_pays;
            $obj->target_state = $hasOwnAddress ? (int) $obj->c_state : (int) $obj->s_state;
            $obj->target_key = mb_strtolower($obj->target_name.'|'.trim($obj->target_address).'|'.trim($obj->target_zip).'|'.trim($obj->target_town));
            $rows[] = $obj;
        }
        $db->free($resql);
    }
    return $rows;
}

/**
 * Find an already flagged Objektadresse third party with identical name and address.
 *
 * @param DoliDB $db  Database handler
 * @param object $row Row from eqmLoadSourceRows()
 * @return int Third party id or 0
 */
function eqmFindExistingObjectAddress($db, $row)
{
    $sql = "SELECT s.rowid FROM ".MAIN_DB_PREFIX."societe s";
    $sql .= " INNER JOIN ".MAIN_DB_PREFIX."societe_extrafields sef ON sef.fk_object = s.rowid";
    $sql .= " WHERE sef.equipmentmanager_object_address = 1";
    $sql .= " AND s.nom = '".$db->escape($row->target_name)."'";
    $sql .= " AND COALESCE(s.address,'') = '".$db->escape(trim($row->target_address))."'";
    $sql .= " AND COALESCE(s.zip,'') = '".$db->escape(trim($row->target_zip))."'";
    $sql .= " AND COALESCE(s.town,'') = '".$db->escape(trim($row->target_town))."'";
    $sql .= " AND s.entity IN (".getEntity('societe').")";
    $resql = $db->query($sql);
    if ($resql && ($obj = $db->fetch_object($resql))) {
        return (int) $obj->rowid;
    }
    return 0;
}

$alreadyMigratedAt = getDolGlobalString('EQUIPMENTMANAGER_FK_ADDRESS_MIGRATED');
$sourceRows = eqmLoadSourceRows($db);

$migrationDone = false;
$migrationError = '';
$createdCount = 0;
$reusedCount = 0;

/*
 * Actions
 */
if ($action == 'migrate' && $user->admin) {
    if (!empty($alreadyMigratedAt)) {
        $migrationError = $langs->trans("ObjectAddressMigrateBlockedAlreadyDone", $alreadyMigratedAt);
    } else {
        $db->begin();
        $ok = true;
        $map = array(); // target_key => third party id
        $assign = array(); // equipment id => third party id

        foreach ($sourceRows as $row) {
            if (!isset($map[$row->target_key])) {
                $socid = eqmFindExistingObjectAddress($db, $row);
                if ($socid > 0) {
                    $reusedCount++;
                } else {
                    $soc = new Societe($db);
                    $soc->name = $row->target_name;
                    $soc->address = trim($row->target_address);
                    $soc->zip = trim($row->target_zip);
                    $soc->town = trim($row->target_town);
                    $soc->country_id = $row->target_pays;
                    $soc->state_id = $row->target_state;
                    $soc->phone = (string) $row->c_phone;
                    $soc->email = (string) $row->c_email;
                    $soc->client = 0;
                    $soc->fournisseur = 0;
                    $soc->status = 1;
                    $soc->array_options['options_equipmentmanager_object_address'] = 1;
                    $socid = $soc->create($user);
                    if ($socid <= 0) {
                        $ok = false;
                        $migrationError = $soc->error ? $soc->error : implode(', ', $soc->errors);
                        break;
                    }
                    $createdCount++;
                }
                $map[$row->target_key] = (int) $socid;
            }
            $assign[(int) $row->equipment_id] = $map[$row->target_key];
        }

        if ($ok) {
            foreach ($assign as $equipmentId => $socid) {
                $sqlUpdate = "UPDATE ".MAIN_DB_PREFIX."equipmentmanager_equipment SET fk_address = ".((int) $socid)." WHERE rowid = ".((int) $equipmentId);
                if (!$db->query($sqlUpdate)) {
                    $ok = false;
                    $migrationError = $db->lasterror();
                    break;
                }
            }
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

$previewRows = $migrationDone ? array() : $sourceRows;

/*
 * View
 */
llxHeader('', $langs->trans($page_name));

$linkback = '<a href="'.dol_buildpath('/equipmentmanager/admin/setup.php', 1).'">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans($page_name), $linkback, 'title_setup');

print '<div class="info">'.$langs->trans("ObjectAddressMigrateHelp").'</div>';

if ($migrationDone) {
    print '<div class="ok">'.$langs->trans("ObjectAddressMigrateSuccess", count($assign), $createdCount, $reusedCount).'</div>';
} elseif ($migrationError) {
    print '<div class="error">'.dol_escape_htmltag($migrationError).'</div>';
}

if (!empty($alreadyMigratedAt) && !$migrationDone) {
    print '<div class="warning">'.$langs->trans("ObjectAddressAlreadyMigrated", $alreadyMigratedAt).'</div>';
}

if (!$migrationDone && empty($alreadyMigratedAt)) {
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
            $existing = eqmFindExistingObjectAddress($db, $row);
            print '<tr class="oddeven">';
            print '<td><a href="'.dol_buildpath('/equipmentmanager/equipment_view.php', 1).'?id='.((int) $row->equipment_id).'" target="_blank">'.dol_escape_htmltag($row->equipment_number).'</a></td>';
            print '<td>'.dol_escape_htmltag(trim($row->lastname.' '.$row->firstname)).'</td>';
            print '<td>'.dol_escape_htmltag($row->target_name).', '.dol_escape_htmltag(trim($row->target_address.', '.$row->target_zip.' '.$row->target_town, ' ,'));
            print ' <span class="opacitymedium">('.$langs->trans($existing > 0 ? "ObjectAddressWillReuse" : "ObjectAddressWillCreate").')</span></td>';
            print '</tr>';
        }
        print '</table>';
        print '</div>';

        print '<br>';
        print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
        print '<input type="hidden" name="token" value="'.newToken().'">';
        print '<input type="hidden" name="action" value="migrate">';
        print '<button type="submit" class="butAction" onclick="return confirm(\''.dol_escape_js($langs->trans("ObjectAddressConfirmMigrate")).'\');">'.$langs->trans("ObjectAddressRunMigration").'</button>';
        print '</form>';
    } else {
        print '<div class="info">'.$langs->trans("ObjectAddressNothingToMigrate").'</div>';
    }
}

llxFooter();
$db->close();
