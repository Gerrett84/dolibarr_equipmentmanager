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

// Save technician signature
if ($action == 'save_signature') {
    $signatureData = GETPOST('signature_data', 'alpha');
    $technicianName = GETPOST('technician_name', 'alphanohtml');

    // Save technician name
    if (!empty($technicianName)) {
        dolibarr_set_const($db, 'EQUIPMENTMANAGER_TECHNICIAN_NAME_USER_'.$user->id, $technicianName, 'chaine', 0, '', $conf->entity);
    } else {
        dolibarr_del_const($db, 'EQUIPMENTMANAGER_TECHNICIAN_NAME_USER_'.$user->id, $conf->entity);
    }

    if (!empty($signatureData)) {
        // Create signature directory if not exists
        $signature_dir = DOL_DATA_ROOT.'/equipmentmanager/signatures';
        if (!is_dir($signature_dir)) {
            dol_mkdir($signature_dir);
        }

        // Remove data:image/png;base64, prefix
        $signatureData = str_replace('data:image/png;base64,', '', $signatureData);
        $signatureData = str_replace(' ', '+', $signatureData);
        $imageData = base64_decode($signatureData);

        // Save as PNG
        $signature_file = $signature_dir.'/user_'.$user->id.'.png';
        $result = file_put_contents($signature_file, $imageData);

        if ($result !== false) {
            setEventMessages($langs->trans("SignatureSaved"), null, 'mesgs');
        } else {
            setEventMessages($langs->trans("Error").': Could not save signature', null, 'errors');
        }
    } else {
        setEventMessages($langs->trans("SignatureSaved"), null, 'mesgs');
    }

    header("Location: ".$_SERVER["PHP_SELF"]);
    exit;
}

// Delete technician signature
if ($action == 'delete_signature') {
    $signature_file = DOL_DATA_ROOT.'/equipmentmanager/signatures/user_'.$user->id.'.png';

    if (file_exists($signature_file)) {
        if (unlink($signature_file)) {
            setEventMessages($langs->trans("SignatureDeleted"), null, 'mesgs');
        } else {
            setEventMessages($langs->trans("Error").': Could not delete signature', null, 'errors');
        }
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

// Admin tabs
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

print dol_get_fiche_head($head, 'setup', '', -1);

// Quick Links
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td colspan="2">'.$langs->trans("Configuration").'</td>';
print "</tr>\n";

// Equipment Types Link
print '<tr class="oddeven">';
print '<td><span class="fa fa-cogs paddingright"></span><strong>'.$langs->trans("ManageEquipmentTypes").'</strong></td>';
print '<td><a class="butAction" href="'.dol_buildpath('/equipmentmanager/admin/equipment_types.php', 1).'">'.$langs->trans("EquipmentTypesSetup").'</a></td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>'.$langs->trans("Description").'</td>';
print '<td>'.$langs->trans("ManageEquipmentAndServiceReports").'</td>';
print '</tr>';

print '</table>';
print '</div>';
print '<br>';

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

// Debug: Show directory path
print '<tr class="liste_titre">';
print '<td colspan="6">';
print '<details><summary style="cursor:pointer;"><small>Debug: Module Loading</small></summary>';
print '<ul style="margin:5px 0; font-family: monospace; font-size: 11px;">';
print '<li>Directory: <code>'.$dir.'</code></li>';
print '<li>Directory exists: '.(is_dir($dir) ? 'YES ✓' : 'NO ✗').'</li>';

if (is_dir($dir)) {
    $handle = opendir($dir);
    if (is_resource($handle)) {
        print '<li>Files found:</li>';
        print '<ul>';

        $var = false;
        $modules_found = 0;
        $modules_loaded = 0;
        $errors = array();

        while (($file = readdir($handle)) !== false) {
            if (preg_match('/^(pdf_.*|equipmentmanager)\.modules\.php$/i', $file, $reg)) {
                $modules_found++;
                print '<li>Found: <code>'.$file.'</code></li>';

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
                    print '</ul></details></td></tr>';

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

                    // Re-open debug section for next module
                    print '<tr class="liste_titre"><td colspan="6"><details><summary style="cursor:pointer;"><small>Debug: Module Loading</small></summary><ul style="margin:5px 0; font-family: monospace; font-size: 11px;">';

                } catch (Exception $e) {
                    $errors[] = $name.': '.$e->getMessage();
                    print '<li style="color:red;">ERROR loading <code>'.$name.'</code>: '.$e->getMessage().'</li>';
                } catch (Error $e) {
                    $errors[] = $name.': '.$e->getMessage();
                    print '<li style="color:red;">FATAL ERROR loading <code>'.$name.'</code>: '.$e->getMessage().'</li>';
                }
            }
        }
        closedir($handle);

        print '</ul>';
        print '<li>Total modules found: '.$modules_found.'</li>';
        print '<li>Successfully loaded: '.$modules_loaded.'</li>';
        if (count($errors) > 0) {
            print '<li style="color:red;">Errors: '.count($errors).'</li>';
        }
        print '</ul></details></td></tr>';
    } else {
        print '<li style="color:red;">Cannot open directory</li>';
        print '</ul></details></td></tr>';
    }
} else {
    print '</ul></details></td></tr>';
}

print '</table>';
print '</div>';

print '</form>';

// Technician Signature Section
print '<br><br>';
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td colspan="2">';
print '<span class="fa fa-pencil paddingright"></span>'.$langs->trans("TechnicianSignature");
print '</td>';
print '</tr>';

// Get current technician name
$technicianName = getDolGlobalString('EQUIPMENTMANAGER_TECHNICIAN_NAME_USER_'.$user->id, $user->getFullName($langs));

// Check if user has signature
$signature_file = DOL_DATA_ROOT.'/equipmentmanager/signatures/user_'.$user->id.'.png';
$has_signature = file_exists($signature_file);

print '<tr class="oddeven">';
print '<td colspan="2">';

if ($has_signature) {
    print '<div class="info">';
    print '<strong>'.$langs->trans("SignatureExists").'</strong><br>';

    // Load signature as base64 data URL
    $imageData = file_get_contents($signature_file);
    $base64 = base64_encode($imageData);
    $dataUrl = 'data:image/png;base64,'.$base64;

    print '<img src="'.$dataUrl.'" style="border: 1px solid #ccc; max-width: 400px; background: white; padding: 10px;" alt="Signature"><br><br>';
    print '<a class="button butActionDelete" href="'.$_SERVER["PHP_SELF"].'?action=delete_signature&token='.newToken().'" onclick="return confirm(\''.$langs->trans("ConfirmDeleteSignature").'\');">';
    print $langs->trans("DeleteSignature");
    print '</a>';
    print '</div>';
} else {
    print '<div class="warning">';
    print $langs->trans("NoSignatureYet").'<br>';
    print $langs->trans("DrawYourSignatureBelow");
    print '</div>';
}

print '<br>';

// Technician name input field
print '<div style="margin-bottom: 15px;">';
print '<label for="technician_name" style="display: block; margin-bottom: 5px; font-weight: bold;">'.$langs->trans("TechnicianName").':</label>';
print '<input type="text" id="technician_name" name="technician_name" value="'.dol_escape_htmltag($technicianName).'" style="width: 400px; padding: 8px;" placeholder="'.$langs->trans("NameForSignature").'">';
print '<br><small class="opacitymedium">'.$langs->trans("TechnicianNameHelp").'</small>';
print '</div>';

// Signature Pad Canvas
print '<div style="border: 2px solid #ccc; display: inline-block; background: white;">';
print '<canvas id="signature-pad" width="400" height="200" style="touch-action: none; cursor: crosshair;"></canvas>';
print '</div><br><br>';

print '<button type="button" class="button" onclick="clearSignature()">'.$langs->trans("Clear").'</button> ';
print '<button type="button" class="button button-save" onclick="saveSignature()">'.$langs->trans("SaveSignature").'</button>';

print '</td>';
print '</tr>';
print '</table>';
print '</div>';

// Hidden form for signature submission
print '<form id="signature-form" method="POST" action="'.$_SERVER["PHP_SELF"].'" style="display:none;">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="save_signature">';
print '<input type="hidden" name="signature_data" id="signature_data">';
print '<input type="hidden" name="technician_name" id="technician_name_hidden">';
print '</form>';

// Signature Pad JavaScript (inline to avoid external dependencies)
?>
<script>
// Signature Pad Library (MIT License) - Simplified inline version
(function() {
    var canvas = document.getElementById('signature-pad');
    var ctx = canvas.getContext('2d');
    var drawing = false;
    var lastX = 0;
    var lastY = 0;

    ctx.strokeStyle = '#000';
    ctx.lineWidth = 2;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';

    // Mouse events
    canvas.addEventListener('mousedown', startDrawing);
    canvas.addEventListener('mousemove', draw);
    canvas.addEventListener('mouseup', stopDrawing);
    canvas.addEventListener('mouseout', stopDrawing);

    // Touch events
    canvas.addEventListener('touchstart', function(e) {
        e.preventDefault();
        var touch = e.touches[0];
        var rect = canvas.getBoundingClientRect();
        lastX = touch.clientX - rect.left;
        lastY = touch.clientY - rect.top;
        drawing = true;
    });

    canvas.addEventListener('touchmove', function(e) {
        e.preventDefault();
        if (!drawing) return;
        var touch = e.touches[0];
        var rect = canvas.getBoundingClientRect();
        var x = touch.clientX - rect.left;
        var y = touch.clientY - rect.top;

        ctx.beginPath();
        ctx.moveTo(lastX, lastY);
        ctx.lineTo(x, y);
        ctx.stroke();

        lastX = x;
        lastY = y;
    });

    canvas.addEventListener('touchend', function(e) {
        e.preventDefault();
        drawing = false;
    });

    function startDrawing(e) {
        drawing = true;
        var rect = canvas.getBoundingClientRect();
        lastX = e.clientX - rect.left;
        lastY = e.clientY - rect.top;
    }

    function draw(e) {
        if (!drawing) return;
        var rect = canvas.getBoundingClientRect();
        var x = e.clientX - rect.left;
        var y = e.clientY - rect.top;

        ctx.beginPath();
        ctx.moveTo(lastX, lastY);
        ctx.lineTo(x, y);
        ctx.stroke();

        lastX = x;
        lastY = y;
    }

    function stopDrawing() {
        drawing = false;
    }

    window.clearSignature = function() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
    };

    window.saveSignature = function() {
        // Get technician name
        var technicianName = document.getElementById('technician_name').value;
        document.getElementById('technician_name_hidden').value = technicianName;

        // Check if canvas is empty
        var imgData = ctx.getImageData(0, 0, canvas.width, canvas.height);
        var isEmpty = !imgData.data.some(channel => channel !== 0);

        // Allow saving just the name without a new signature
        if (isEmpty) {
            // If no signature drawn, still allow saving the name
            document.getElementById('signature_data').value = '';
        } else {
            // Get image data as base64
            var dataURL = canvas.toDataURL('image/png');
            document.getElementById('signature_data').value = dataURL;
        }

        document.getElementById('signature-form').submit();
    };
})();
</script>
<?php

print dol_get_fiche_end();

// End of page
llxFooter();
$db->close();