<?php
/**
 * PWA company logo (public, so the login view can show it).
 * Serves only the logo configured under Setup -> Company, small thumbnail preferred.
 */

define('NOLOGIN', 1);
define('NOCSRFCHECK', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);

$res = 0;
if (!$res && file_exists("../../../main.inc.php")) {
    $res = include "../../../main.inc.php";
}
if (!$res && file_exists("../../../../main.inc.php")) {
    $res = include "../../../../main.inc.php";
}
if (!$res) {
    http_response_code(500);
    exit;
}

dol_include_once('/equipmentmanager/lib/pwa_theme.lib.php');
$logo = eqmCompanyLogo();
if ($logo) {
    header('Content-Type: '.$logo['mime']);
    header('Content-Length: '.filesize($logo['file']));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: public, max-age=86400');
    readfile($logo['file']);
    exit;
}

http_response_code(404);
