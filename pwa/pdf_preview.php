<?php
/**
 * PDF Preview for PWA
 * Generates and streams the intervention PDF for preview
 */

// Load Dolibarr environment
$res = 0;
if (!$res && file_exists("../../../main.inc.php")) {
    $res = include "../../../main.inc.php";
}
if (!$res && file_exists("../../../../main.inc.php")) {
    $res = include "../../../../main.inc.php";
}
if (!$res) {
    die("Dolibarr environment not found");
}

require_once DOL_DOCUMENT_ROOT.'/fichinter/class/fichinter.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';

// Authenticate: session or PWA token (query param for iframe/tab contexts)
if (!$user->id) {
    $pwaToken = GETPOST('pwa_token', 'alpha') ?: ($_SERVER['HTTP_X_PWA_TOKEN'] ?? '');
    if (!empty($pwaToken)) {
        $hashed = hash('sha256', $pwaToken);
        $sqlTok = "SELECT fk_user FROM ".MAIN_DB_PREFIX."equipmentmanager_pwa_token"
                . " WHERE token = '".$db->escape($hashed)."'"
                . " AND valid_until > '".$db->idate(dol_now())."'";
        $resTok = $db->query($sqlTok);
        if ($resTok && $db->num_rows($resTok)) {
            $tokObj = $db->fetch_object($resTok);
            require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
            $user = new User($db);
            $user->fetch((int)$tokObj->fk_user);
        }
    }
}
if (!$user->id) {
    http_response_code(401);
    die('Not authenticated');
}

// Get intervention ID
$id = GETPOST('id', 'int');
if (!$id) {
    http_response_code(400);
    die('Missing intervention ID');
}

// Load intervention
$fichinter = new Fichinter($db);
$result = $fichinter->fetch($id);
if ($result <= 0) {
    http_response_code(404);
    die('Intervention not found');
}

// Check permission
if (!$user->hasRight('ficheinter', 'lire')) {
    http_response_code(403);
    die('Access denied');
}

// Fetch related data
$fichinter->fetch_thirdparty();
$fichinter->fetch_lines();

// Setup output language
$outputlangs = $langs;
if (!empty($conf->global->MAIN_MULTILANGS) && !empty($fichinter->thirdparty->default_lang)) {
    $outputlangs = new Translate("", $conf);
    $outputlangs->setDefaultLang($fichinter->thirdparty->default_lang);
}
$outputlangs->loadLangs(array("main", "interventions", "companies", "equipmentmanager@equipmentmanager"));

// Use EquipmentManager PDF template
$modele = 'equipmentmanager';

// Generate PDF to temp file
$tempdir = $conf->equipmentmanager->dir_temp ?? sys_get_temp_dir();
if (!is_dir($tempdir)) {
    @mkdir($tempdir, 0755, true);
}

// Generate unique temp filename
$tempfile = $tempdir.'/preview_'.$fichinter->ref.'_'.session_id().'.pdf';

// Temporarily change output directory
$olddir = $conf->ficheinter->dir_output;
$conf->ficheinter->dir_output = $tempdir;

// Generate PDF
$result = $fichinter->generateDocument($modele, $outputlangs);

// Restore output directory
$conf->ficheinter->dir_output = $olddir;

if ($result <= 0) {
    http_response_code(500);
    die('PDF generation failed: '.$fichinter->error);
}

// Find generated file
$pdffile = $tempdir.'/'.dol_sanitizeFileName($fichinter->ref).'/'.dol_sanitizeFileName($fichinter->ref).'.pdf';

if (!file_exists($pdffile)) {
    // Try alternate location
    $pdffile = $tempdir.'/'.dol_sanitizeFileName($fichinter->ref).'.pdf';
}

if (!file_exists($pdffile)) {
    http_response_code(500);
    die('Generated PDF not found');
}

// Stream PDF to browser
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="Vorschau_'.$fichinter->ref.'.pdf"');
header('Content-Length: '.filesize($pdffile));
header('Cache-Control: no-cache, must-revalidate');

readfile($pdffile);

// Clean up temp file
@unlink($pdffile);
@rmdir(dirname($pdffile));

exit;
