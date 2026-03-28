<?php
/**
 * PDF Embed Wrapper
 * Wraps a PDF URL in a proper HTML page with viewport meta so the PDF
 * scales to fit the device width (especially on iOS Safari PWA).
 */

// Load Dolibarr environment
$res = 0;
if (!$res && file_exists("../../../main.inc.php")) {
    $res = include "../../../main.inc.php";
}
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
            background: #525659;
        }
        embed {
            display: block;
            width: 100%;
            height: 100%;
        }
    </style>
</head>
<body>
    <embed src="<?php echo $safeUrl; ?>" type="application/pdf" width="100%" height="100%">
</body>
</html>
