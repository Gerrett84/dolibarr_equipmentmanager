<?php
/**
 * PWA Manifest for Equipment Manager Service Reports
 */

// Public (the browser/OS fetches the manifest without a session), so no login redirect
define('NOLOGIN', 1);
define('NOCSRFCHECK', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);

// Home screen icons from the company logo (icon.php); module icon if there is no usable logo
$res = 0;
if (!$res && file_exists("../../../main.inc.php")) {
    $res = @include "../../../main.inc.php";
}
if (!$res && file_exists("../../../../main.inc.php")) {
    $res = @include "../../../../main.inc.php";
}
$icons = [
    [
        'src' => '../img/object_equipment.png',
        'sizes' => '32x32',
        'type' => 'image/png'
    ],
];
if ($res) {
    dol_include_once('/equipmentmanager/lib/pwa_theme.lib.php');
    $logo = function_exists('eqmCompanyLogo') ? eqmCompanyLogo(true) : null;
    if ($logo && function_exists('imagecreatefromstring')) {
        $v = substr(md5(filemtime($logo['file']).getDolGlobalString('EQUIPMENTMANAGER_BRAND_COLOR')), 0, 8);
        $icons = [
            ['src' => 'icon.php?size=192&v='.$v, 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
            ['src' => 'icon.php?size=512&v='.$v, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
            ['src' => 'icon.php?size=192&maskable=1&v='.$v, 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'maskable'],
            ['src' => 'icon.php?size=512&maskable=1&v='.$v, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
        ];
    }
}

header('Content-Type: application/manifest+json');
header('Cache-Control: no-cache');

// App name from the backend setting (PDF & design); defaults as before
$appName = ($res && function_exists('getDolGlobalString')) ? trim(getDolGlobalString('EQUIPMENTMANAGER_PWA_APP_NAME')) : '';

$manifest = [
    'name' => $appName !== '' ? $appName : 'Serviceberichte',
    'short_name' => $appName !== '' ? $appName : 'Service',
    'description' => 'Offline Serviceberichte für Techniker',
    'start_url' => './index.php',
    'scope' => './',
    'display' => 'standalone',
    'orientation' => 'portrait',
    'background_color' => '#ffffff',
    'theme_color' => '#263c5c',
    'icons' => $icons,
    'categories' => ['business', 'productivity'],
    'lang' => 'de',
    'dir' => 'ltr'
];

echo json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
