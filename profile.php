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
dol_include_once('/equipmentmanager/lib/equipmentmanager.lib.php');

$langs->loadLangs(array("admin", "equipmentmanager@equipmentmanager"));

$action = GETPOST('action', 'aZ09');

/*
 * Actions (per user - every logged in backend user edits only their own profile)
 */

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

equipmentmanagerSettingsHeader('MyProfile');

print '<p class="opacitymedium">'.$langs->trans("AlsoAvailableInPwa").'</p>';

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

    print '<img src="'.$dataUrl.'" style="border: 1px solid var(--colortext, #ccc); max-width: 400px; background: var(--inputbackgroundcolor, #fff); padding: 10px;" alt="Signature"><br><br>';
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
print '<div style="border: 2px solid var(--colortext, #ccc); display: inline-block; background: var(--inputbackgroundcolor, #fff);">';
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


llxFooter();
