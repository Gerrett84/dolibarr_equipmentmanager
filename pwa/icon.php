<?php
/**
 * PWA app icon (home screen icon) built from the company logo: a square PNG with the logo
 * centered on a fitting background. Falls back to the module icon when no usable logo exists.
 *
 * Parameters: size (120|152|167|180|192|256|384|512), maskable=1 (extra safe-zone padding).
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

$size = isset($_GET['size']) ? (int) $_GET['size'] : 180;
if (!in_array($size, array(120, 152, 167, 180, 192, 256, 384, 512), true)) {
    $size = 180;
}
$maskable = !empty($_GET['maskable']);

$logo = eqmCompanyLogo(true);
if (!$logo || !function_exists('imagecreatefromstring')) {
    header('Location: ../img/object_equipment.png');
    exit;
}
$data = @file_get_contents($logo['file']);
$src = $data ? @imagecreatefromstring($data) : false;
if (!$src) {
    header('Location: ../img/object_equipment.png');
    exit;
}
$w = imagesx($src);
$h = imagesy($src);

// Background: the logo's own color if it brings an opaque background, the brand color behind a
// light logo, white behind a dark one (iOS needs an opaque icon)
$brand = getDolGlobalString('EQUIPMENTMANAGER_BRAND_COLOR');
if (!preg_match('/^#[0-9a-fA-F]{6}$/', $brand)) {
    $brand = '#263c5c';
}
$bg = array(255, 255, 255);
if ($logo['opaque']) {
    $px = imagecolorat($src, 0, 0);
    $bg = array(($px >> 16) & 255, ($px >> 8) & 255, $px & 255);
} elseif ($logo['light']) {
    $bg = array(hexdec(substr($brand, 1, 2)), hexdec(substr($brand, 3, 2)), hexdec(substr($brand, 5, 2)));
}

$etag = '"'.md5($logo['file'].filemtime($logo['file']).$size.($maskable ? 'm' : 'n').implode(',', $bg)).'"';
header('ETag: '.$etag);
header('Cache-Control: public, max-age=86400');
if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
    http_response_code(304);
    exit;
}

$canvas = imagecreatetruecolor($size, $size);
imagefill($canvas, 0, 0, imagecolorallocate($canvas, $bg[0], $bg[1], $bg[2]));
$box = $size * ($maskable ? 0.56 : 0.74);
$scale = min($box / $w, $box / $h);
$dw = max(1, (int) round($w * $scale));
$dh = max(1, (int) round($h * $scale));
imagealphablending($canvas, true);
imagecopyresampled($canvas, $src, (int) round(($size - $dw) / 2), (int) round(($size - $dh) / 2), 0, 0, $dw, $dh, $w, $h);

header('Content-Type: image/png');
imagepng($canvas);
