<?php
/**
 * PDF Embed Wrapper
 * Wraps a PDF URL in a proper HTML page with viewport meta so the PDF
 * scales to fit the device width (especially on iOS Safari PWA).
 */

// No session needed — the embedded URL carries its own auth (pwa_token).
define('NOLOGIN', '1');

$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) $res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
if (!$res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if (!$res) {
    http_response_code(503);
    exit('Environment not found');
}

// Only allow relative URLs within this module (prevent open redirect)
$url = GETPOST('url', 'alpha');
if (empty($url) || preg_match('/^https?:\/\//i', $url)) {
    http_response_code(400);
    exit('Invalid URL');
}
$safeUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');

$theme = GETPOST('theme', 'alpha');
$isDark = ($theme === 'dark');
$bgColor   = $isDark ? '#1a1a1a' : '#525659';
$textColor = $isDark ? '#e0e0e0' : '#ffffff';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title>PDF</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html, body {
            width: 100%; height: 100%;
            overflow: hidden;
            background: <?php echo $bgColor; ?>;
            color: <?php echo $textColor; ?>;
        }
        embed {
            display: block;
            width: 100%;
            height: 100%;
        }
        /* Fallback message if embed not supported */
        .fallback {
            display: none;
            padding: 20px;
            text-align: center;
            font-family: sans-serif;
            font-size: 15px;
        }
        embed:not([src]) + .fallback { display: block; }
    </style>
</head>
<body>
    <embed src="<?php echo $safeUrl; ?>" type="application/pdf" width="100%" height="100%">
    <div class="fallback">PDF kann nicht angezeigt werden.<br>
        <a href="<?php echo $safeUrl; ?>" style="color:<?php echo $textColor; ?>">PDF herunterladen</a>
    </div>
</body>
</html>
