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

// Only allow relative URLs within this module (prevent open redirect + XSS)
$rawUrl = isset($_GET['url']) ? $_GET['url'] : '';
if (empty($rawUrl) || preg_match('/^https?:\/\//i', $rawUrl) || strpos($rawUrl, '..') !== false) {
    http_response_code(400);
    exit('Invalid URL');
}

$theme = GETPOST('theme', 'alpha');
$isDark = ($theme === 'dark');
$bgColor = $isDark ? '#1a1a1a' : '#525659';

// Safe URL for JS string (json_encode handles all escaping)
$urlForJs = json_encode($rawUrl);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title>PDF</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { width: 100%; height: 100%; overflow: hidden; background: <?php echo $bgColor; ?>; }
        #pdfWrap { position: absolute; top: 0; left: 0; right: 0; bottom: 0; overflow: hidden; }
        #pdfFrame { position: absolute; top: 0; left: 0; border: none; display: block; }
    </style>
</head>
<body>
    <div id="pdfWrap">
        <iframe id="pdfFrame" allowfullscreen></iframe>
    </div>
    <script>
    (function() {
        var frame = document.getElementById('pdfFrame');
        var w = window.innerWidth;
        var h = window.innerHeight;
        // iOS WebKit renders PDFs at 1pt = 1px. A4 portrait = 595 × 842 pt.
        var pdfW = 595;
        var scale = w / pdfW;
        frame.style.width  = pdfW + 'px';
        frame.style.height = Math.ceil(h / scale) + 'px';
        frame.style.transform = 'scale(' + scale + ')';
        frame.style.transformOrigin = 'top left';
        frame.src = <?php echo $urlForJs; ?>;
    })();
    </script>
</body>
</html>
