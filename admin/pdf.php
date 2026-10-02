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
if (!$res && file_exists("../../../main.inc.php")) {
    $res = @include "../../../main.inc.php";
}
if (!$res) {
    die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/equipmentmanager/lib/equipmentmanager.lib.php');

$langs->loadLangs(array("admin", "equipmentmanager@equipmentmanager"));

$form = new Form($db);

if (!$user->admin) {
    accessforbidden();
}

$action = GETPOST('action', 'aZ09');

/*
 * Actions
 */

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

// Save or reset brand color (PWA primary color, and PDF fallback when no PDF-specific color is set)
if ($action == 'save_brand_color') {
    $brandColor = GETPOSTISSET('reset_btn') ? '' : GETPOST('brand_color', 'alpha');

    if (!empty($brandColor) && !preg_match('/^#[0-9a-fA-F]{6}$/', $brandColor)) {
        setEventMessages($langs->trans("ErrorBrandColorFormat"), null, 'errors');
    } else {
        dolibarr_set_const($db, 'EQUIPMENTMANAGER_BRAND_COLOR', $brandColor, 'chaine', 0, '', $conf->entity);
        setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
    }

    header("Location: ".$_SERVER["PHP_SELF"]);
    exit;
}

// Save or reset the PWA dark mode color
if ($action == 'save_brand_color_dark') {
    $darkColor = GETPOSTISSET('reset_btn') ? '' : GETPOST('brand_color_dark', 'alpha');

    if (!empty($darkColor) && !preg_match('/^#[0-9a-fA-F]{6}$/', $darkColor)) {
        setEventMessages($langs->trans("ErrorBrandColorFormat"), null, 'errors');
    } else {
        dolibarr_set_const($db, 'EQUIPMENTMANAGER_BRAND_COLOR_DARK', $darkColor, 'chaine', 0, '', $conf->entity);
        setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
    }

    header("Location: ".$_SERVER["PHP_SELF"]);
    exit;
}

// Save or reset PDF-only color override (takes priority over the shared brand color, for PDF only)
if ($action == 'save_pdf_color') {
    $pdfColor = GETPOSTISSET('reset_btn') ? '' : GETPOST('pdf_color', 'alpha');

    if (!empty($pdfColor) && !preg_match('/^#[0-9a-fA-F]{6}$/', $pdfColor)) {
        setEventMessages($langs->trans("ErrorBrandColorFormat"), null, 'errors');
    } else {
        dolibarr_set_const($db, 'EQUIPMENTMANAGER_PDF_COLOR', $pdfColor, 'chaine', 0, '', $conf->entity);
        setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
    }

    header("Location: ".$_SERVER["PHP_SELF"]);
    exit;
}

// Register PDF template
if ($action == 'register_template') {
    // Delete existing entries (both with and without pdf_ prefix, both types)
    $sql = "DELETE FROM ".MAIN_DB_PREFIX."document_model WHERE nom IN ('pdf_equipmentmanager', 'equipmentmanager') AND type IN ('fichinter', 'ficheinter') AND entity = ".$conf->entity;
    $db->query($sql);

    // Insert new entry (without pdf_ prefix - this is what Dolibarr expects)
    $sql = "INSERT INTO ".MAIN_DB_PREFIX."document_model (nom, type, entity, libelle, description)";
    $sql .= " VALUES ('equipmentmanager', 'ficheinter', ".$conf->entity.", 'Equipment Manager', '')";
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

llxHeader('', $langs->trans('PdfAndDesign'));

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans('EquipmentManagerSetup'), $linkback, 'title_setup');

print dol_get_fiche_head(equipmentmanagerAdminPrepareHead(), 'pdf', '', -1);

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

// Check if our template is registered (check both with and without pdf_ prefix)
$template_registered = in_array('equipmentmanager', $def) || in_array('pdf_equipmentmanager', $def);

// Check if it's set as default
$current_default = !empty($conf->global->FICHEINTER_ADDON_PDF) ? $conf->global->FICHEINTER_ADDON_PDF : '';
$is_default = ($current_default == 'equipmentmanager' || $current_default == 'pdf_equipmentmanager');

// Show status based on registration AND default setting
$show_warning = !$template_registered || !$is_default;

// Always show registration status
print '<div class="'.($show_warning ? 'warning' : 'info').'">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td colspan="2">'.$langs->trans("PDFTemplateRegistration").'</td>';
print "</tr>\n";
print '<tr class="oddeven">';
print '<td>';

if ($template_registered) {
    print '<span class="ok">✓ '.$langs->trans("PDFTemplateRegistered").'</span><br>';

    if ($is_default) {
        print '<span class="ok">✓ '.$langs->trans("TemplateSetAsDefault").'</span><br>';
        print '<span class="opacitymedium">'.$langs->trans("TemplateIsActiveAndReady").'</span>';
    } else {
        print '<span class="warning">⚠ '.$langs->trans("TemplateNotSetAsDefault").'</span><br>';
        print '<span class="opacitymedium"><strong>'.$langs->trans("PleaseSetAsDefaultInTableBelow").'</strong></span><br>';
        print '<span class="opacitymedium">'.$langs->trans("CurrentDefault").': <code>'.$current_default.'</code></span>';
    }
} else {
    print '<span class="warning">⚠ '.$langs->trans("PDFTemplateNotRegistered").'</span><br>';
    print '<span class="opacitymedium">'.$langs->trans("PDFTemplateClickToRegister").'</span>';
}


print '</td>';
print '<td class="right" style="vertical-align: top;">';
if (!$template_registered) {
    print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?action=register_template&token='.newToken().'">'.$langs->trans("RegisterPDFTemplate").'</a>';
} else {
    print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?action=register_template&token='.newToken().'">'.$langs->trans("ReregisterTemplate").'</a>';
}
print '</td>';
print '</tr>';
print '</table>';
print '</div>';
print '<br>';

// Brand color (used for PWA primary color, and as PDF fallback if no PDF-specific color is set below)
print load_fiche_titre($langs->trans("BrandColor"), '', '');
print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="save_brand_color">';
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="oddeven">';
print '<td>'.$langs->trans("BrandColor").'</td>';
print '<td>';
$currentBrandColor = getDolGlobalString('EQUIPMENTMANAGER_BRAND_COLOR');
print '<input type="color" name="brand_color" value="'.dol_escape_htmltag($currentBrandColor ?: '#1a3f6e').'">';
print ' <span class="opacitymedium">'.$langs->trans("BrandColorHelp").'</span>';
print '</td>';
print '<td class="right nowraponall">';
print '<input type="submit" name="reset_btn" value="'.$langs->trans("ResetToDefault").'" class="button button-cancel" formnovalidate style="margin-right:5px;">';
print '<input type="submit" class="button button-save" value="'.$langs->trans("Save").'">';
print '</td>';
print '</tr>';
print '</table>';
print '</div>';
print '</form>';
print '<br>';

// PWA dark mode color (header background; accent is lightened automatically if needed)
print load_fiche_titre($langs->trans("BrandColorDark"), '', '');
print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="save_brand_color_dark">';
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="oddeven">';
print '<td>'.$langs->trans("BrandColorDark").'</td>';
print '<td>';
$currentBrandColorDark = getDolGlobalString('EQUIPMENTMANAGER_BRAND_COLOR_DARK');
print '<input type="color" name="brand_color_dark" value="'.dol_escape_htmltag($currentBrandColorDark ?: '#1e3a8a').'">';
print ' <span class="opacitymedium">'.$langs->trans("BrandColorDarkHelp").'</span>';
print '</td>';
print '<td class="right nowraponall">';
print '<input type="submit" name="reset_btn" value="'.$langs->trans("ResetToDefault").'" class="button button-cancel" formnovalidate style="margin-right:5px;">';
print '<input type="submit" class="button button-save" value="'.$langs->trans("Save").'">';
print '</td>';
print '</tr>';
print '</table>';
print '</div>';
print '</form>';
print '<br>';

// PDF-only color override (optional - leave empty to use the brand color above for PDF too)
print load_fiche_titre($langs->trans("PdfColor"), '', '');
print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="save_pdf_color">';
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="oddeven">';
print '<td>'.$langs->trans("PdfColor").'</td>';
print '<td>';
$currentPdfColor = getDolGlobalString('EQUIPMENTMANAGER_PDF_COLOR');
print '<input type="color" name="pdf_color" value="'.dol_escape_htmltag($currentPdfColor ?: ($currentBrandColor ?: '#00003c')).'">';
print ' <span class="opacitymedium">'.$langs->trans("PdfColorHelp").'</span>';
print '</td>';
print '<td class="right nowraponall">';
print '<input type="submit" name="reset_btn" value="'.$langs->trans("ResetToDefault").'" class="button button-cancel" formnovalidate style="margin-right:5px;">';
print '<input type="submit" class="button button-save" value="'.$langs->trans("Save").'">';
print '</td>';
print '</tr>';
print '</table>';
print '</div>';
print '</form>';
print '<br>';

// PDF Template table
print load_fiche_titre($langs->trans("PdfTemplates"), '', '');
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
        $modules_found = 0;
        $modules_loaded = 0;
        $errors = array();

        while (($file = readdir($handle)) !== false) {
            if (preg_match('/^(pdf_.*|equipmentmanager)\.modules\.php$/i', $file, $reg)) {
                $modules_found++;
                $name = $reg[1];
                $classname = $name;

                try {
                    dol_include_once('/equipmentmanager/core/modules/fichinter/modules_fichinter.php');
                    require_once $dir.'/'.$file;

                    if (!class_exists($classname)) {
                        throw new Exception("Class $classname not found in file");
                    }

                    $module = new $classname($db);
                    $modules_loaded++;

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
                        print '<span class="badge badge-status4 badge-status">'.img_picto($langs->trans("Default"), 'on').' '.$langs->trans("Default").'</span>';
                    } else {
                        // Highlight our equipment manager template
                        $is_our_template = ($name == 'pdf_equipmentmanager' || $name == 'equipmentmanager');
                        $link_text = $is_our_template ? '<strong>'.$langs->trans("SetAsDefault").'</strong>' : $langs->trans("SetAsDefault");
                        print '<a class="'.($is_our_template ? 'butAction' : 'button').'" href="'.$_SERVER["PHP_SELF"].'?action=setmodel&token='.newToken().'&value='.urlencode($name).'">'.$link_text.'</a>';
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
                    $errors[] = $name.': '.$e->getMessage();
                    dol_syslog('EquipmentManager PDF module error: '.$e->getMessage(), LOG_ERR);
                } catch (Error $e) {
                    $errors[] = $name.': '.$e->getMessage();
                    dol_syslog('EquipmentManager PDF module fatal error: '.$e->getMessage(), LOG_ERR);
                }
            }
        }
        closedir($handle);
    }
}

print '</table>';
print '</div>';

print '</form>';

print dol_get_fiche_end();

llxFooter();
