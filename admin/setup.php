<?php
/* Copyright (C) 2024 Equipment Manager
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
    $res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
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
// Try main.inc.php using relative path
if (!$res && file_exists("../../main.inc.php")) {
    $res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
    $res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
    $res = @include "../../main.inc.php";
}
if (!$res) {
    die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';

// Load translation files
$langs->loadLangs(array("admin", "equipmentmanager@equipmentmanager", "interventions"));

// Initialize form object
$form = new Form($db);

// Access control
if (!$user->admin) {
    accessforbidden();
}

// Parameters
$action = GETPOST('action', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');

/*
 * Actions
 */

// Save configuration
if ($action == 'save') {
    // Add your configuration save logic here if needed
    setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
    header("Location: ".$_SERVER["PHP_SELF"]);
    exit;
}

// Set PDF model for interventions
if ($action == 'setmodel') {
    $value = GETPOST('value', 'alpha');
    $label = GETPOST('label', 'alpha');

    if (!empty($value)) {
        $conf->global->FICHEINTER_ADDON_PDF = $value;
        dolibarr_set_const($db, 'FICHEINTER_ADDON_PDF', $value, 'chaine', 0, '', $conf->entity);
        setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
    }

    header("Location: ".$_SERVER["PHP_SELF"]);
    exit;
}

// Register PDF template
if ($action == 'register_template') {
    // Delete existing entry
    $sql = "DELETE FROM ".MAIN_DB_PREFIX."document_model WHERE nom = 'pdf_equipmentmanager' AND type = 'ficheinter' AND entity = ".$conf->entity;
    $db->query($sql);

    // Insert new entry
    $sql = "INSERT INTO ".MAIN_DB_PREFIX."document_model (nom, type, entity, libelle, description)";
    $sql .= " VALUES ('pdf_equipmentmanager', 'ficheinter', ".$conf->entity.", 'Equipment Manager', 'Service report with equipment details and materials')";
    $result = $db->query($sql);

    if ($result) {
        setEventMessages($langs->trans("PDFTemplateRegistered"), null, 'mesgs');
    } else {
        setEventMessages($langs->trans("Error").': '.$db->lasterror(), null, 'errors');
    }

    header("Location: ".$_SERVER["PHP_SELF"]);
    exit;
}

/*
 * View
 */

$page_name = "EquipmentManagerSetup";
llxHeader('', $langs->trans($page_name));

// Page title
$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans($page_name), $linkback, 'title_setup');

// Configuration form
print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="save">';

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Parameter").'</td>';
print '<td>'.$langs->trans("Value").'</td>';
print "</tr>\n";

// Module information
print '<tr class="oddeven">';
print '<td><strong>'.$langs->trans("ModuleSetup").'</strong></td>';
print '<td>'.$langs->trans("NoConfigurationRequired").'</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>'.$langs->trans("Description").'</td>';
print '<td>'.$langs->trans("ManageEquipmentAndServiceReports").'</td>';
print '</tr>';

print '</table>';
print '</div>';

print '</form>';

// PDF Template Selection
print '<br>';

// Get list of available PDF models from database
$def = array();
$sql = "SELECT nom FROM ".MAIN_DB_PREFIX."document_model";
$sql .= " WHERE type = 'ficheinter'";
$sql .= " AND entity = ".$conf->entity;
$resql = $db->query($sql);
if ($resql) {
    $num = $db->num_rows($resql);
    $i = 0;
    while ($i < $num) {
        $obj = $db->fetch_object($resql);
        array_push($def, $obj->nom);
        $i++;
    }
}

// Check if our template is registered
$template_registered = in_array('pdf_equipmentmanager', $def);

// Show registration status and button if not registered
if (!$template_registered) {
    print '<div class="info">';
    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre">';
    print '<td>'.$langs->trans("PDFTemplateRegistration").'</td>';
    print '<td class="right"></td>';
    print "</tr>\n";
    print '<tr class="oddeven">';
    print '<td>';
    print '<span class="opacitymedium">'.$langs->trans("PDFTemplateNotRegistered").'</span><br>';
    print $langs->trans("PDFTemplateClickToRegister");
    print '</td>';
    print '<td class="right">';
    print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?action=register_template&token='.newToken().'">'.$langs->trans("RegisterPDFTemplate").'</a>';
    print '</td>';
    print '</tr>';
    print '</table>';
    print '</div>';
    print '<br>';
}

// PDF Template table
print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="setmodel">';

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Name").'</td>';
print '<td>'.$langs->trans("Description").'</td>';
print '<td class="center">'.$langs->trans("Status").'</td>';
print '<td class="center">'.$langs->trans("Default").'</td>';
print '<td class="center">'.$langs->trans("ShortInfo").'</td>';
print '<td class="center">'.$langs->trans("Preview").'</td>';
print "</tr>\n";

// Include PDF module
clearstatcache();
$dir = dol_buildpath('/equipmentmanager/core/modules/fichinter/doc', 0);
if (is_dir($dir)) {
    $handle = opendir($dir);
    if (is_resource($handle)) {
        $var = false;
        while (($file = readdir($handle)) !== false) {
            if (preg_match('/^(pdf_.*)\.modules\.php$/i', $file, $reg)) {
                $name = $reg[1];
                $classname = $name;

                try {
                    dol_include_once('/equipmentmanager/core/modules/fichinter/modules_fichinter.php');
                    require_once $dir.'/'.$file;
                    $module = new $classname($db);

                    $var = !$var;
                    print '<tr class="oddeven">';
                    print '<td width="100">';
                    print $module->name;
                    print "</td><td>\n";
                    print $module->description;
                    print '</td>';

                    // Active
                    if (in_array($name, $def)) {
                        print '<td class="center">'."\n";
                        print img_picto($langs->trans("Enabled"), 'switch_on');
                        print '</td>';
                    } else {
                        print '<td class="center">'."\n";
                        print img_picto($langs->trans("Disabled"), 'switch_off');
                        print '</td>';
                    }

                    // Default
                    print '<td class="center">';
                    $current_model = !empty($conf->global->FICHEINTER_ADDON_PDF) ? $conf->global->FICHEINTER_ADDON_PDF : '';
                    if ($current_model == $name) {
                        print img_picto($langs->trans("Default"), 'on');
                    } else {
                        print '<a href="'.$_SERVER["PHP_SELF"].'?action=setmodel&token='.newToken().'&value='.urlencode($name).'" alt="'.$langs->trans("Default").'">'.img_picto($langs->trans("Disabled"), 'off').'</a>';
                    }
                    print '</td>';

                    // Info
                    $htmltooltip = ''.$langs->trans("Name").': '.$module->name;
                    $htmltooltip .= '<br>'.$langs->trans("Type").': '.($module->type ? $module->type : $langs->trans("Unknown"));
                    if (isset($module->page_largeur) && isset($module->page_hauteur)) {
                        $htmltooltip .= '<br>'.$langs->trans("Width").'/'.$langs->trans("Height").': '.$module->page_largeur.'/'.$module->page_hauteur;
                    }
                    $htmltooltip .= '<br><br><u>'.$langs->trans("FeaturesSupported").':</u>';
                    $htmltooltip .= '<br>'.$langs->trans("Logo").': '.yn($module->option_logo, 1, 1);
                    $htmltooltip .= '<br>'.$langs->trans("MultiLanguage").': '.yn($module->option_multilang, 1, 1);

                    print '<td class="center">';
                    print $form->textwithpicto('', $htmltooltip, 1, 0);
                    print '</td>';

                    // Preview
                    print '<td class="center">';
                    if ($module->type == 'pdf') {
                        print '<a href="'.$_SERVER["PHP_SELF"].'?action=specimen&module='.$name.'">'.img_object($langs->trans("Preview"), 'bill').'</a>';
                    } else {
                        print img_object($langs->trans("PreviewNotAvailable"), 'generic');
                    }
                    print '</td>';

                    print "</tr>\n";
                } catch (Exception $e) {
                    // If module loading fails, show error row
                    print '<tr class="oddeven">';
                    print '<td width="100">'.$name.'</td>';
                    print '<td colspan="5"><span class="error">'.$langs->trans("Error").': '.$e->getMessage().'</span></td>';
                    print "</tr>\n";
                }
            }
        }
        closedir($handle);
    }
}

print '</table>';
print '</div>';

print '</form>';

// End of page
llxFooter();
$db->close();