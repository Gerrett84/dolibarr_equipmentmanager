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

$dir = $conf->mycompany->dir_output.'/logos/';
$candidates = array();
if (!empty($mysoc->logo_small)) {
    $candidates[] = $dir.'thumbs/'.basename($mysoc->logo_small);
}
if (!empty($mysoc->logo)) {
    $candidates[] = $dir.basename($mysoc->logo);
}

foreach ($candidates as $file) {
    if (is_file($file)) {
        $mimes = array('png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp');
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (!isset($mimes[$ext])) {
            continue;
        }
        header('Content-Type: '.$mimes[$ext]);
        header('Content-Length: '.filesize($file));
        header('Cache-Control: public, max-age=86400');
        readfile($file);
        exit;
    }
}

http_response_code(404);
