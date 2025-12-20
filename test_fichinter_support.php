<?php
/**
 * Test if Fichinter supports PDF templates
 */

// Load Dolibarr environment
$res = 0;
$paths = array(
    "../../main.inc.php",
    "../../../main.inc.php",
    "/usr/share/dolibarr/htdocs/main.inc.php",
    __DIR__ . "/../../main.inc.php",
    __DIR__ . "/../../../main.inc.php"
);

foreach ($paths as $path) {
    if (file_exists($path)) {
        $res = @include $path;
        if ($res) break;
    }
}

if (!$res) {
    die("Error: Could not load Dolibarr environment\n");
}

echo "=== Check if Fichinter supports PDF templates ===\n\n";

// Load Fichinter class
require_once DOL_DOCUMENT_ROOT.'/fichinter/class/fichinter.class.php';

$ficheinter = new Fichinter($db);

// Check if it has generateDocument method
echo "Methods available:\n";
$methods = get_class_methods($ficheinter);
$pdf_methods = array_filter($methods, function($m) {
    return stripos($m, 'pdf') !== false || stripos($m, 'document') !== false || stripos($m, 'generate') !== false;
});
foreach ($pdf_methods as $method) {
    echo "  - $method\n";
}

echo "\n=== Check model_pdf property ===\n";
if (property_exists($ficheinter, 'model_pdf')) {
    echo "model_pdf property exists: " . var_export($ficheinter->model_pdf, true) . "\n";
} else {
    echo "model_pdf property does NOT exist\n";
}

echo "\n=== Try to generate a PDF (if method exists) ===\n";
if (method_exists($ficheinter, 'generateDocument')) {
    echo "generateDocument() method exists ✓\n";
} else {
    echo "generateDocument() method does NOT exist ✗\n";
}

echo "\n=== Check Dolibarr fichinter doc directory ===\n";
$doc_dir = DOL_DOCUMENT_ROOT.'/core/modules/fichinter/doc';
if (is_dir($doc_dir)) {
    echo "Directory exists: $doc_dir\n";
    $files = scandir($doc_dir);
    echo "Files found:\n";
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            echo "  - $file\n";
        }
    }
} else {
    echo "Directory NOT found: $doc_dir\n";
}
