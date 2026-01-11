<?php
/* Copyright (C) 2024-2025 Equipment Manager
 *
 * Checklist Templates Administration
 */

// Load Dolibarr environment
$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
    $res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
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
if (!$res) {
    die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/equipmentmanager/class/checklisttemplate.class.php');

// Load translation files
$langs->loadLangs(array("admin", "equipmentmanager@equipmentmanager"));

// Access control
if (!$user->admin) {
    accessforbidden();
}

$action = GETPOST('action', 'aZ09');

/*
 * Actions
 */

// Initialize checklist templates from SQL data
if ($action == 'init_templates') {
    // Check if templates table is empty
    $sql = "SELECT COUNT(*) as nb FROM ".MAIN_DB_PREFIX."equipmentmanager_checklist_templates WHERE entity = ".$conf->entity;
    $resql = $db->query($sql);
    $obj = $db->fetch_object($resql);

    if ($obj->nb == 0) {
        // Run the data SQL file
        $sqlfile = DOL_DOCUMENT_ROOT.'/custom/equipmentmanager/sql/llx_equipmentmanager_checklist.data.sql';
        if (file_exists($sqlfile)) {
            $result = run_sql($sqlfile, 1, $conf->entity, 1, '', 0, 'default', 1);
            if ($result > 0) {
                setEventMessages($langs->trans('ChecklistTemplatesInitialized'), null, 'mesgs');
            } else {
                setEventMessages('Error initializing templates', null, 'errors');
            }
        }
    } else {
        setEventMessages('Templates already exist', null, 'warnings');
    }

    header("Location: ".$_SERVER["PHP_SELF"]);
    exit;
}

/*
 * View
 */

$form = new Form($db);

llxHeader('', $langs->trans('ChecklistTemplates'));

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';

print load_fiche_titre($langs->trans('ChecklistTemplates'), $linkback, 'title_setup');

// Setup tabs
$head = array();
$head[0][0] = dol_buildpath('/equipmentmanager/admin/setup.php', 1);
$head[0][1] = $langs->trans('ModuleSetup');
$head[0][2] = 'setup';

$head[1][0] = dol_buildpath('/equipmentmanager/admin/equipment_types.php', 1);
$head[1][1] = $langs->trans('EquipmentTypesSetup');
$head[1][2] = 'equipment_types';

$head[2][0] = dol_buildpath('/equipmentmanager/admin/checklists.php', 1);
$head[2][1] = $langs->trans('ChecklistTemplates');
$head[2][2] = 'checklists';

print dol_get_fiche_head($head, 'checklists', '', -1);

// Check if tables exist
$sql = "SHOW TABLES LIKE '".MAIN_DB_PREFIX."equipmentmanager_checklist_templates'";
$resql = $db->query($sql);
$tablesExist = ($resql && $db->num_rows($resql) > 0);

if (!$tablesExist) {
    print '<div class="warning">';
    print $langs->trans('ChecklistTablesNotInstalled');
    print '<br><br>';
    print $langs->trans('PleaseRunModuleUpdate');
    print '</div>';
} else {
    // Load templates
    $templateObj = new ChecklistTemplate($db);
    $templates = $templateObj->fetchAll($conf->entity, 0);

    if (count($templates) == 0) {
        print '<div class="info">';
        print $langs->trans('NoChecklistTemplatesFound');
        print '<br><br>';
        print '<a class="button" href="'.$_SERVER["PHP_SELF"].'?action=init_templates&token='.newToken().'">';
        print $langs->trans('InitializeChecklistTemplates');
        print '</a>';
        print '</div>';
    } else {
        print '<div class="info opacitymedium" style="margin-bottom: 15px;">';
        print $langs->trans('ChecklistTemplatesInfo');
        print '</div>';

        // Templates table
        print '<div class="div-table-responsive-no-min">';
        print '<table class="noborder centpercent">';

        print '<tr class="liste_titre">';
        print '<th>'.$langs->trans('EquipmentType').'</th>';
        print '<th>'.$langs->trans('Label').'</th>';
        print '<th>'.$langs->trans('Norm').'</th>';
        print '<th class="center">'.$langs->trans('Sections').'</th>';
        print '<th class="center">'.$langs->trans('Items').'</th>';
        print '<th class="center">'.$langs->trans('Status').'</th>';
        print '</tr>';

        foreach ($templates as $template) {
            // Count sections and items
            $sql_sections = "SELECT COUNT(*) as nb FROM ".MAIN_DB_PREFIX."equipmentmanager_checklist_sections WHERE fk_template = ".(int)$template->id;
            $resql_sections = $db->query($sql_sections);
            $sections_count = 0;
            if ($resql_sections) {
                $obj = $db->fetch_object($resql_sections);
                $sections_count = $obj->nb;
            }

            $sql_items = "SELECT COUNT(*) as nb FROM ".MAIN_DB_PREFIX."equipmentmanager_checklist_items i";
            $sql_items .= " JOIN ".MAIN_DB_PREFIX."equipmentmanager_checklist_sections s ON i.fk_section = s.rowid";
            $sql_items .= " WHERE s.fk_template = ".(int)$template->id;
            $resql_items = $db->query($sql_items);
            $items_count = 0;
            if ($resql_items) {
                $obj = $db->fetch_object($resql_items);
                $items_count = $obj->nb;
            }

            print '<tr class="oddeven">';

            // Equipment type
            print '<td><strong>'.dol_escape_htmltag($template->equipment_type_code).'</strong></td>';

            // Label
            print '<td>'.$langs->trans($template->label).'</td>';

            // Norm
            print '<td>'.dol_escape_htmltag($template->norm_reference).'</td>';

            // Sections count
            print '<td class="center">'.$sections_count.'</td>';

            // Items count
            print '<td class="center">'.$items_count.'</td>';

            // Status
            print '<td class="center">';
            if ($template->active) {
                print '<span class="badge badge-status4">'.$langs->trans('Active').'</span>';
            } else {
                print '<span class="badge badge-status5">'.$langs->trans('Inactive').'</span>';
            }
            print '</td>';

            print '</tr>';

            // Show sections collapsed
            if ($sections_count > 0) {
                $tplObj = new ChecklistTemplate($db);
                $tplObj->fetch($template->id);
                $tplObj->fetchSectionsWithItems();

                foreach ($tplObj->sections as $section) {
                    print '<tr class="oddeven" style="background-color: #f8f8f8;">';
                    print '<td style="padding-left: 30px;">↳ <em>'.$langs->trans($section->label).'</em></td>';
                    print '<td colspan="3" class="opacitymedium" style="font-size: 0.9em;">';
                    $item_labels = array();
                    foreach ($section->items as $item) {
                        $item_labels[] = $langs->trans($item->label);
                    }
                    print implode(', ', $item_labels);
                    print '</td>';
                    print '<td class="center">'.count($section->items).'</td>';
                    print '<td></td>';
                    print '</tr>';
                }
            }
        }

        print '</table>';
        print '</div>';
    }
}

print dol_get_fiche_end();

llxFooter();
$db->close();
