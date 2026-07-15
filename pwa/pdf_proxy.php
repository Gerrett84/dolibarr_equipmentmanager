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
$pwaToken = GETPOST('pwa_token', 'alpha') ?: ($_SERVER['HTTP_X_PWA_TOKEN'] ?? '');
if (empty($pwaToken) || !validateProxyPwaToken($pwaToken, $db)) {
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

if ($fullPath === false || strpos($fullPath, $realDataRoot) !== 0 || !is_file($fullPath)) {
    http_response_code(404);
    exit('File not found');
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

function validateProxyPwaToken($token, $db) {
    global $user;
    $hashed = hash('sha256', $token);
    $sql    = "SELECT fk_user FROM " . MAIN_DB_PREFIX . "equipmentmanager_pwa_token"
            . " WHERE token = '" . $db->escape($hashed) . "'"
            . " AND valid_until > '" . $db->idate(dol_now()) . "'";
    $res = $db->query($sql);
    if (!$res || !$db->num_rows($res)) return false;
    $obj = $db->fetch_object($res);
    require_once DOL_DOCUMENT_ROOT . '/user/class/user.class.php';
    $user = new User($db);
    return $user->fetch((int)$obj->fk_user) > 0;
}
