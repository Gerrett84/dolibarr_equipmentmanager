<?php
/**
 * Check how Dolibarr scans for models from modules
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

echo "=== Check Module Scanning for Models ===\n\n";

// Check if EquipmentManager module is enabled
echo "1. Check if EquipmentManager module is enabled:\n";
$sql = "SELECT name, value FROM llx_const WHERE name LIKE '%EQUIPMENT%' OR name LIKE '%FICHINTER%'";
$resql = $db->query($sql);
if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        echo "   {$obj->name} = {$obj->value}\n";
    }
}

// Get list of enabled modules
echo "\n2. Get all enabled modules with 'models' capability:\n";
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';

$modulesdir = dolGetModulesDirs();
echo "   Module directories to scan:\n";
foreach ($modulesdir as $dir) {
    echo "   - $dir\n";
}

// Find all module descriptor files
echo "\n3. Looking for module descriptors with 'models' => 1:\n";
foreach ($modulesdir as $dir) {
    $handle = @opendir($dir);
    if (!$handle) continue;

    while (($file = readdir($handle)) !== false) {
        if (preg_match('/^mod.*\.class\.php$/i', $file)) {
            $classname = preg_replace('/\.class\.php$/i', '', $file);

            if (!class_exists($classname)) {
                require_once $dir . '/' . $file;
            }

            if (class_exists($classname)) {
                try {
                    $module = new $classname($db);

                    // Check if module has models
                    if (!empty($module->module_parts['models'])) {
                        $enabled = isModEnabled(strtolower($module->name));
                        echo "   - {$module->name}: models={$module->module_parts['models']}, ";
                        echo "enabled=" . ($enabled ? 'YES' : 'NO') . "\n";

                        if ($module->name == 'EquipmentManager') {
                            echo "     >>> EquipmentManager found!\n";
                            echo "         Path hint: " . dirname($dir . '/' . $file) . "\n";
                        }
                    }
                } catch (Exception $e) {
                    // Skip
                }
            }
        }
    }
    closedir($handle);
}

// Check what directories ModelePDFFicheinter would scan
echo "\n4. Manually check for fichinter templates in module paths:\n";
foreach ($modulesdir as $basedir) {
    $docdir = $basedir . '/../core/modules/fichinter/doc';
    $docdir = realpath($docdir);
    if ($docdir && is_dir($docdir)) {
        echo "   Found: $docdir\n";
        $files = scandir($docdir);
        foreach ($files as $file) {
            if (preg_match('/\.modules\.php$/i', $file)) {
                echo "      - $file\n";
            }
        }
    }
}

echo "\nDone.\n";
