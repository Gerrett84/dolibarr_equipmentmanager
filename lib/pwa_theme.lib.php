<?php
/**
 * \file       lib/pwa_theme.lib.php
 * \ingroup    equipmentmanager
 * \brief      Dark mode colors of the PWA (setup -> PDF & design -> dark mode color)
 */

/**
 * Relative luminance (0..1) of an RGB color.
 *
 * @param int[] $rgb [r,g,b] 0-255
 * @return float
 */
function eqmPwaLuminance($rgb)
{
    $lin = array();
    foreach ($rgb as $c) {
        $c = $c / 255;
        $lin[] = ($c <= 0.03928) ? $c / 12.92 : pow(($c + 0.055) / 1.055, 2.4);
    }
    return 0.2126 * $lin[0] + 0.7152 * $lin[1] + 0.0722 * $lin[2];
}

/**
 * Dark mode colors of the PWA. The configured color becomes the header background;
 * the accent (links, buttons, active items) is lightened if needed so it stays
 * readable on the dark card background.
 *
 * @param string $defaultHeader  Header color used when no dark color is configured
 * @param string $defaultPrimary Accent color used when no dark color is configured
 * @return array{header:string,primary:string,primaryLight:string}
 */
function eqmPwaDarkColors($defaultHeader, $defaultPrimary = '#60a5fa')
{
    $hex = getDolGlobalString('EQUIPMENTMANAGER_BRAND_COLOR_DARK');
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $hex)) {
        return array('header' => $defaultHeader, 'primary' => $defaultPrimary, 'primaryLight' => 'rgba(74, 144, 217, 0.2)');
    }

    $rgb = array(hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2)));

    // Raise HSL lightness (keeping hue and saturation) until the accent is readable on dark cards
    list($h, $sat, $l) = eqmPwaRgbToHsl($rgb);
    $accent = $rgb;
    for ($i = 0; $i < 40 && eqmPwaLuminance($accent) < 0.3 && $l < 0.95; $i++) {
        $l += 0.02;
        $accent = eqmPwaHslToRgb($h, $sat, $l);
    }

    return array(
        'header' => $hex,
        'primary' => sprintf('#%02x%02x%02x', $accent[0], $accent[1], $accent[2]),
        'primaryLight' => sprintf('rgba(%d, %d, %d, 0.2)', $accent[0], $accent[1], $accent[2]),
    );
}

/**
 * @param int[] $rgb [r,g,b] 0-255
 * @return float[] [h,s,l] each 0..1
 */
function eqmPwaRgbToHsl($rgb)
{
    list($r, $g, $b) = array($rgb[0] / 255, $rgb[1] / 255, $rgb[2] / 255);
    $max = max($r, $g, $b);
    $min = min($r, $g, $b);
    $l = ($max + $min) / 2;
    if ($max == $min) {
        return array(0.0, 0.0, $l);
    }
    $d = $max - $min;
    $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);
    if ($max == $r) {
        $h = ($g - $b) / $d + ($g < $b ? 6 : 0);
    } elseif ($max == $g) {
        $h = ($b - $r) / $d + 2;
    } else {
        $h = ($r - $g) / $d + 4;
    }
    return array($h / 6, $s, $l);
}

/**
 * @param float $h Hue 0..1
 * @param float $s Saturation 0..1
 * @param float $l Lightness 0..1
 * @return int[] [r,g,b] 0-255
 */
function eqmPwaHslToRgb($h, $s, $l)
{
    if ($s == 0) {
        $v = (int) round($l * 255);
        return array($v, $v, $v);
    }
    $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
    $p = 2 * $l - $q;
    $hue = function ($t) use ($p, $q) {
        if ($t < 0) {
            $t += 1;
        }
        if ($t > 1) {
            $t -= 1;
        }
        if ($t < 1 / 6) {
            return $p + ($q - $p) * 6 * $t;
        }
        if ($t < 1 / 2) {
            return $q;
        }
        if ($t < 2 / 3) {
            return $p + ($q - $p) * (2 / 3 - $t) * 6;
        }
        return $p;
    };
    return array((int) round($hue($h + 1 / 3) * 255), (int) round($hue($h) * 255), (int) round($hue($h - 1 / 3) * 255));
}

/**
 * Whether a logo image is mostly light (white/light glyphs): such a logo must not be placed
 * on a white chip. Samples a 32x32 downscale, weighting by opacity.
 *
 * @param string $file Image file
 * @return bool
 */
function eqmLogoIsLight($file)
{
    if (!function_exists('imagecreatefromstring')) {
        return false;
    }
    $data = @file_get_contents($file);
    $im = $data ? @imagecreatefromstring($data) : false;
    if (!$im) {
        return false;
    }
    $w = imagesx($im);
    $h = imagesy($im);
    $sw = max(1, min(32, $w));
    $sh = max(1, min(32, $h));
    $small = imagecreatetruecolor($sw, $sh);
    imagealphablending($small, false);
    imagesavealpha($small, true);
    imagefill($small, 0, 0, imagecolorallocatealpha($small, 0, 0, 0, 127));
    imagecopyresampled($small, $im, 0, 0, 0, 0, $sw, $sh, $w, $h);

    $sum = 0.0;
    $weight = 0.0;
    for ($y = 0; $y < $sh; $y++) {
        for ($x = 0; $x < $sw; $x++) {
            $c = imagecolorat($small, $x, $y);
            $alpha = ($c >> 24) & 127;
            $opacity = (127 - $alpha) / 127;
            if ($opacity < 0.2) {
                continue;
            }
            $lum = (0.2126 * (($c >> 16) & 255) + 0.7152 * (($c >> 8) & 255) + 0.0722 * ($c & 255)) / 255;
            $sum += $lum * $opacity;
            $weight += $opacity;
        }
    }
    return $weight > 0 && ($sum / $weight) > 0.72;
}

/**
 * The company logo configured in Dolibarr (small thumbnail preferred), only formats browsers
 * can show as an image.
 *
 * @return array|null array(file, mime, light) or null
 */
function eqmCompanyLogo()
{
    global $conf, $mysoc;
    static $cache = false;
    if ($cache !== false) {
        return $cache;
    }
    $cache = null;
    if (empty($mysoc->logo)) {
        return null;
    }

    $mimes = array('png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp', 'avif' => 'image/avif');
    $dir = $conf->mycompany->dir_output.'/logos/';
    $candidates = array();
    if (!empty($mysoc->logo_small)) {
        $candidates[] = $dir.'thumbs/'.basename($mysoc->logo_small);
    }
    $candidates[] = $dir.basename($mysoc->logo);

    foreach ($candidates as $file) {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (isset($mimes[$ext]) && is_file($file)) {
            $cache = array('file' => $file, 'mime' => $mimes[$ext], 'light' => eqmLogoIsLight($file));
            break;
        }
    }
    return $cache;
}

