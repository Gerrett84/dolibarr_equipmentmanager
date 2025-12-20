<?php
/**
 * Trace exactly what happens in getListOfModels()
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

echo "=== Trace getListOfModels() for fichinter ===\n\n";

// Manually do what getListOfModels does
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';

$type = 'fichinter';
$maxfilenamelength = 28;

echo "1. Get directory paths to scan:\n";
$dirs = array();
$dirs[] = DOL_DOCUMENT_ROOT.'/core/modules/'.strtolower($type).'/doc';
$dirs[] = DOL_DOCUMENT_ROOT.'/custom';

foreach ($dirs as $dir) {
    echo "   - $dir\n";
}

echo "\n2. Scan for models:\n";
$liste = array();

foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        echo "   Skip $dir (not a directory)\n";
        continue;
    }

    echo "   Scanning: $dir\n";

    // Get all .modules.php files
    $handle = @opendir($dir);
    if (!$handle) {
        echo "   Cannot open directory\n";
        continue;
    }

    $var = true;
    while (($file = readdir($handle)) !== false) {
        if (preg_match('/\.modules\.php$/i', $file)) {
            $classname = preg_replace('/\.modules\.php$/i', '', $file);

            echo "   Found file: $file\n";
            echo "      Classname: $classname\n";

            // Try to load the class
            $filepath = $dir.'/'.$file;

            // Check if already loaded
            if (!class_exists($classname)) {
                echo "      Loading from: $filepath\n";
                require_once $filepath;
            } else {
                echo "      Already loaded\n";
            }

            if (class_exists($classname)) {
                echo "      Class exists: YES\n";

                try {
                    $module = new $classname($db);

                    echo "      Instantiated: YES\n";
                    echo "      name property: " . var_export($module->name ?? 'NOT SET', true) . "\n";
                    echo "      description: " . var_export($module->description ?? 'NOT SET', true) . "\n";

                    // This is what getListOfModels does
                    if (isset($module->name)) {
                        $liste[$module->name] = $module->name . (isset($module->description) ? ': ' . $module->description : '');
                        echo "      Added to list as: [{$module->name}] => '{$liste[$module->name]}'\n";
                    } else {
                        echo "      ERROR: name property not set!\n";
                    }
                } catch (Exception $e) {
                    echo "      ERROR instantiating: " . $e->getMessage() . "\n";
                }
            } else {
                echo "      Class exists: NO\n";
            }
            echo "\n";
        }
    }
    closedir($handle);
}

echo "3. Final list:\n";
foreach ($liste as $key => $value) {
    echo "   [$key] => '$value'\n";
}

echo "\n4. Compare with actual getListOfModels():\n";
$actual = getListOfModels($db, 'fichinter');
foreach ($actual as $key => $value) {
    echo "   [$key] => '$value'\n";
}

echo "\nDone.\n";
