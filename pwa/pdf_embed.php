<?php
/**
 * PDF Embed Wrapper
 * Wraps a PDF URL in a proper HTML page with viewport meta so the PDF
 * scales to fit the device width (especially on iOS Safari PWA).
 *
 * Multi-page mode (pages=N): creates N stacked single-page iframes, each loaded
 * via pdf_embed.php?pages=1 with a ?page=N suffix appended to the base URL.
 * This avoids iOS Safari's single-page limitation for PDFs in iframes.
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
$gapColor = $isDark ? '#111' : '#2a2a2a';

// pages=N: 1 = single page (default), N>1 = stacked single-page iframes
$pages = max(1, min(10, (int)($_GET['pages'] ?? 1)));
$isMultiPage = ($pages > 1);

// Safe values for JS
$urlForJs   = json_encode($rawUrl);
$themeForJs = json_encode($theme);
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
        #pdfWrap {
            position: absolute; top: 0; left: 0; right: 0; bottom: 0;
            overflow-x: hidden;
            overflow-y: <?php echo $isMultiPage ? 'auto' : 'hidden'; ?>;
            <?php if ($isMultiPage): ?>-webkit-overflow-scrolling: touch;<?php endif; ?>
        }
        <?php if (!$isMultiPage): ?>
        #pdfFrame { position: absolute; top: 0; left: 0; border: none; display: block; }
        <?php endif; ?>
    </style>
</head>
<body>
    <div id="pdfWrap">
        <?php if (!$isMultiPage): ?>
        <iframe id="pdfFrame" allowfullscreen></iframe>
        <?php endif; ?>
    </div>
    <script>
    (function() {
        var wrap = document.getElementById('pdfWrap');
        var w    = window.innerWidth;
        // iOS WebKit renders PDFs at 1pt = 1px. A4 portrait = 595 × 842 pt.
        var pdfW  = 595;
        var scale = w / pdfW;
        var pages = <?php echo (int)$pages; ?>;

        <?php if ($isMultiPage): ?>
        // Multi-page: one single-page iframe per PDF page, stacked vertically.
        // Each inner iframe uses pdf_embed.php?pages=1 (single-page transform mode).
        // This reliably shows every page because iOS Safari handles 1-page PDFs correctly.
        var baseUrl  = <?php echo $urlForJs; ?>;
        var theme    = <?php echo $themeForJs; ?>;
        var pageH    = Math.round(842 * scale);
        var sep      = '<?php echo htmlspecialchars($gapColor); ?>';
        var hasSep   = baseUrl.indexOf('?') >= 0;

        for (var i = 1; i <= pages; i++) {
            if (i > 1) {
                var div = document.createElement('div');
                div.style.height = '4px';
                div.style.background = sep;
                wrap.appendChild(div);
            }
            var pageUrl = baseUrl + (hasSep ? '&' : '?') + 'page=' + i;
            var fr = document.createElement('iframe');
            fr.style.width   = '100%';
            fr.style.height  = pageH + 'px';
            fr.style.border  = 'none';
            fr.style.display = 'block';
            fr.src = 'pdf_embed.php?url=' + encodeURIComponent(pageUrl)
                   + '&theme=' + encodeURIComponent(theme)
                   + '&pages=1';
            wrap.appendChild(fr);
        }

        <?php else: ?>
        // Single page: scale to fit screen width via CSS transform.
        var frame = document.getElementById('pdfFrame');
        var h     = window.innerHeight;
        frame.style.width           = pdfW + 'px';
        frame.style.height          = Math.ceil(h / scale) + 'px';
        frame.style.transform       = 'scale(' + scale + ')';
        frame.style.transformOrigin = 'top left';
        frame.src = <?php echo $urlForJs; ?>;
        <?php endif; ?>
    })();
    </script>
</body>
</html>
