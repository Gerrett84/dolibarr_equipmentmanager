<?php
/**
 * PWA PDF/Document Proxy
 * Serves files from Dolibarr's document root with PWA token authentication.
 * Replaces direct document.php links for the PWA.
 */

define('NOLOGIN', '1');

$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) $res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
if (!$res && file_exists("../../../../main.inc.php")) $res = @include "../../../../main.inc.php";
if (!$res && file_exists("../../../main.inc.php"))    $res = @include "../../../main.inc.php";
if (!$res) { http_response_code(503); exit('Environment not found'); }

// Authenticate via PWA token (query param or header)
dol_include_once('/equipmentmanager/lib/pwa_access.lib.php');
$viewUser = eqmResolveViewRequestUser($db);
if ($viewUser !== null) {
    $user = $viewUser;
}
if ($viewUser === null) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

// Validate and resolve file path — only allow files inside DOL_DATA_ROOT
// Use $_GET directly; security is handled by realpath + DOL_DATA_ROOT prefix check below
$file = isset($_GET['file']) ? $_GET['file'] : '';
if (empty($file)) { http_response_code(400); exit('Missing file parameter'); }

// Map modulepart to its subdirectory under DOL_DATA_ROOT
$modulepart = isset($_GET['modulepart']) ? preg_replace('/[^a-z0-9_]/', '', strtolower($_GET['modulepart'])) : '';
$moduleDirMap = [
    'fichinter'     => 'ficheinter',
    'ficheinter'    => 'ficheinter',
    'equipmentmanager' => 'equipmentmanager',
];
// Default to ficheinter — all documents served by this proxy are from ficheinter
$moduleSubdir = isset($moduleDirMap[$modulepart]) ? $moduleDirMap[$modulepart] : 'ficheinter';

$realDataRoot = realpath(DOL_DATA_ROOT);
$basePath  = $realDataRoot . '/' . $moduleSubdir;
$fullPath  = realpath($basePath . '/' . ltrim($file, '/'));

// The resolved file must live inside the module's own directory (not merely somewhere in
// DOL_DATA_ROOT), otherwise "../mycompany/..." style paths would escape it
if ($fullPath === false || strpos($fullPath, $realDataRoot . '/' . $moduleSubdir . '/') !== 0 || !is_file($fullPath)) {
    http_response_code(404);
    exit('File not found');
}

// Per-order authorization: technician accounts may only open documents of their own orders
// and of the history of the same Objektadresse
dol_include_once('/equipmentmanager/lib/pwa_access.lib.php');
if ($moduleSubdir === 'ficheinter') {
    $relative = ltrim(substr($fullPath, strlen($basePath)), '/');
    $refDir = explode('/', $relative)[0];
    $sqlRef = "SELECT rowid FROM " . MAIN_DB_PREFIX . "fichinter WHERE ref = '" . $db->escape($refDir) . "'";
    $resRef = $db->query($sqlRef);
    $objRef = $resRef ? $db->fetch_object($resRef) : null;
    if ($objRef) {
        $allowed = eqmUserMayViewIntervention($db, $user, (int) $objRef->rowid);
    } else {
        // Not an order folder (e.g. leftovers): only users with the regular backend right
        $allowed = !empty($user->admin) || $user->hasRight('ficheinter', 'lire');
    }
    if (!$allowed) { http_response_code(403); exit('Access denied'); }
} elseif (!eqmUserHasPwaPermission($user)) {
    http_response_code(403);
    exit('Access denied');
}

$attachment = (GETPOST('attachment', 'int') == 1);
$ext  = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
$mime = [
    'pdf'  => 'application/pdf',
    'png'  => 'image/png',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'gif'  => 'image/gif',
][$ext] ?? 'application/octet-stream';

header('Content-Type: ' . $mime);
header('Content-Disposition: ' . ($attachment ? 'attachment' : 'inline') . '; filename="' . basename($fullPath) . '"');
header('Content-Length: ' . filesize($fullPath));
header('Cache-Control: private, max-age=300');
readfile($fullPath);
exit;
