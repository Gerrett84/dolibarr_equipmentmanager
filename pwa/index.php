<?php
/**
 * PWA Entry Point - Serviceberichte Offline
 */

// Prevent Dolibarr login redirect - PWA handles its own auth display
define('NOLOGIN', 1);
define('NOCSRFCHECK', 1);

// Load Dolibarr environment
$res = 0;
if (!$res && file_exists("../../../main.inc.php")) {
    $res = include "../../../main.inc.php";
}
if (!$res && file_exists("../../../../main.inc.php")) {
    $res = include "../../../../main.inc.php";
}
if (!$res) {
    die("Dolibarr environment not found");
}

// Since we use NOLOGIN, we need to manually check session and load user
$isAuthenticated = false;
$authData = null;

if (!empty($_SESSION['dol_login'])) {
    require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
    $tmpuser = new User($db);
    $result = $tmpuser->fetch('', $_SESSION['dol_login']);
    if ($result > 0 && $tmpuser->id > 0) {
        $user = $tmpuser;
        $isAuthenticated = true;
        $authData = [
            'id' => (int)$user->id,
            'login' => $user->login,
            'name' => $user->getFullName($langs),
            'timestamp' => time(),
            'valid_until' => time() + (90 * 24 * 3600)
        ];
    }
}

// Handle auto-login via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['pwa_autologin'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $totp_code = $_POST['totp_code'] ?? '';

    if ($username && $password) {
        require_once DOL_DOCUMENT_ROOT.'/core/lib/security2.lib.php';

        $login = checkLoginPassEntity($username, $password, 1, array('dolibarr'));

        if ($login && $login !== '--bad-login-validity--') {
            require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
            $tmpuser = new User($db);
            $tmpuser->fetch('', $login);

            if ($tmpuser->id > 0) {
                // Check if TOTP 2FA is enabled and verify code
                $totp2fa_required = false;
                $totp2fa_verified = false;

                if (!empty($conf->totp2fa->enabled)) {
                    dol_include_once('/totp2fa/class/user2fa.class.php');

                    if (class_exists('User2FA')) {
                        $user2fa = new User2FA($db);
                        $result = $user2fa->fetch($tmpuser->id);

                        if ($result > 0 && $user2fa->is_enabled) {
                            $totp2fa_required = true;

                            // Check if device is trusted
                            $trustedEnabled = getDolGlobalInt('TOTP2FA_TRUSTED_DEVICE_ENABLED', 0);
                            if ($trustedEnabled) {
                                $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
                                $acceptLang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
                                $deviceHash = hash('sha256', $userAgent . '|' . $acceptLang);

                                $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."totp2fa_trusted_devices";
                                $sql .= " WHERE fk_user = ".(int)$tmpuser->id;
                                $sql .= " AND device_hash = '".$db->escape($deviceHash)."'";
                                $sql .= " AND trusted_until > NOW()";

                                $resql = $db->query($sql);
                                if ($resql && $db->num_rows($resql) > 0) {
                                    $totp2fa_verified = true;
                                    $obj = $db->fetch_object($resql);
                                    $db->query("UPDATE ".MAIN_DB_PREFIX."totp2fa_trusted_devices SET date_last_use = NOW() WHERE rowid = ".(int)$obj->rowid);
                                }
                            }

                            if (!$totp2fa_verified && !empty($totp_code)) {
                                $totp2fa_verified = $user2fa->verifyCode($totp_code);
                                if (!$totp2fa_verified && strpos($totp_code, '-') !== false) {
                                    $totp2fa_verified = $user2fa->verifyBackupCode($totp_code);
                                }
                            }
                        }
                    }
                }

                if ($totp2fa_required && !$totp2fa_verified) {
                    header('Content-Type: application/json');
                    http_response_code(401);
                    echo json_encode(['status' => 'error', 'message' => '2FA-Code erforderlich', 'requires_2fa' => true]);
                    exit;
                }

                // Set Dolibarr session
                $_SESSION['dol_login'] = $tmpuser->login;
                $_SESSION['dol_authmode'] = 'dolibarr';
                $_SESSION['dol_tz'] = $_POST['tz'] ?? '';
                $_SESSION['dol_entity'] = 1;

                if ($totp2fa_required) {
                    $_SESSION['totp2fa_verified'] = $tmpuser->id;
                }

                header('Content-Type: application/json');
                echo json_encode([
                    'status' => 'ok',
                    'message' => 'Login successful',
                    'user' => [
                        'id' => (int)$tmpuser->id,
                        'login' => $tmpuser->login,
                        'name' => $tmpuser->getFullName($langs)
                    ]
                ]);
                exit;
            }
        }

        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Login fehlgeschlagen']);
        exit;
    }
}

// $authData is already set above when session is valid

// Get trusted device info for current user
$trustedDeviceInfo = null;
if ($isAuthenticated && !empty($conf->totp2fa->enabled)) {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $acceptLang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
    $deviceHash = hash('sha256', $userAgent . '|' . $acceptLang);

    $sql = "SELECT trusted_until, device_name, DATEDIFF(trusted_until, NOW()) as days_left FROM ".MAIN_DB_PREFIX."totp2fa_trusted_devices";
    $sql .= " WHERE fk_user = ".(int)$user->id;
    $sql .= " AND device_hash = '".$db->escape($deviceHash)."'";
    $sql .= " AND trusted_until > NOW()";

    $resql = $db->query($sql);
    if ($resql && $db->num_rows($resql) > 0) {
        $obj = $db->fetch_object($resql);
        $trustedDeviceInfo = [
            'device_name' => $obj->device_name,
            'trusted_until' => $obj->trusted_until,
            'days_remaining' => max(1, (int)$obj->days_left) // MySQL berechnet direkt
        ];
    }
}

$title = 'Serviceberichte';
$apiBase = dol_buildpath('/custom/equipmentmanager/api/index.php', 1);
$jSignaturePath = DOL_URL_ROOT . '/includes/jquery/plugins/jSignature/jSignature.min.js';
$dolibarrUrl = dol_buildpath('/', 1); // Absolute URL to Dolibarr root
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="<?php echo $title; ?>">
    <meta name="theme-color" content="#263c5c">

    <title><?php echo $title; ?></title>

    <!-- Theme initialization (prevent flash) -->
    <script>
        (function() {
            const stored = localStorage.getItem('pwa_theme');
            let theme = stored || 'auto';
            if (theme === 'auto') {
                theme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            }
            if (theme === 'dark') {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        })();
    </script>

    <link rel="manifest" href="manifest.json.php">
    <link rel="apple-touch-icon" href="../img/object_equipment.png">

    <style>
        /* Theme Variables */
        :root {
            --bg-primary: #f5f5f5;
            --bg-secondary: #ffffff;
            --bg-card: #ffffff;
            --text-primary: #333333;
            --text-secondary: #666666;
            --text-muted: #999999;
            --border-color: #e0e0e0;
            --header-bg: #263c5c;
            --shadow: 0 1px 3px rgba(0,0,0,0.1);
            --input-bg: #ffffff;
            --input-border: #dddddd;
            --primary-color: #263c5c;
            --primary-light: rgba(38, 60, 92, 0.1);
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
        }

        [data-theme="dark"] {
            --bg-primary: #1a1a1a;
            --bg-secondary: #2d2d2d;
            --bg-card: #2d2d2d;
            --text-primary: #e0e0e0;
            --text-secondary: #b0b0b0;
            --text-muted: #808080;
            --border-color: #404040;
            --header-bg: #1e2d3d;
            --shadow: 0 1px 3px rgba(0,0,0,0.3);
            --input-bg: #3d3d3d;
            --input-border: #505050;
            --primary-color: #4a90d9;
            --primary-light: rgba(74, 144, 217, 0.2);
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
        }

        * {
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            padding: 0;
            background: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
            transition: background-color 0.3s, color 0.3s;
        }

        /* Header */
        .header {
            background: var(--header-bg);
            color: white;
            padding: 12px 16px;
            position: sticky;
            top: 0;
            z-index: 100;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .header h1 {
            margin: 0;
            font-size: 18px;
            font-weight: 500;
            flex: 1;
        }

        .header-btn {
            background: none;
            border: none;
            color: white;
            font-size: 20px;
            padding: 8px;
            cursor: pointer;
            border-radius: 50%;
        }

        .header-btn:active {
            background: rgba(255,255,255,0.2);
        }

        /* Sync Status */
        .sync-status {
            font-size: 12px;
            padding: 4px 8px;
            border-radius: 12px;
            background: rgba(255,255,255,0.2);
        }

        .sync-status.online { background: #4caf50; }
        .sync-status.offline { background: #f44336; }
        .sync-status.syncing { background: #ff9800; }

        /* Main Content */
        .content {
            padding: 16px;
            padding-bottom: 80px;
        }

        /* Cards */
        .card {
            background: var(--bg-card);
            border-radius: 8px;
            box-shadow: var(--shadow);
            margin-bottom: 12px;
            overflow: hidden;
            transition: background-color 0.3s;
        }

        .card-header {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-title {
            font-weight: 600;
            font-size: 16px;
            margin: 0;
            color: #263c5c;
        }

        [data-theme="dark"] .card-title {
            color: #6fa8dc;
        }

        .card-subtitle {
            font-size: 13px;
            color: var(--text-secondary);
            margin-top: 4px;
        }

        .card-body {
            padding: 12px 16px;
        }

        .card-clickable {
            cursor: pointer;
        }

        .card-clickable:active {
            background: var(--bg-secondary);
        }

        /* Status Badge */
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 500;
            text-transform: uppercase;
        }

        .badge-draft { background: #e0e0e0; color: #666; }
        .badge-open { background: #bbdefb; color: #1565c0; }
        .badge-released { background: #fff3e0; color: #e65100; }
        .badge-done { background: #c8e6c9; color: #2e7d32; }
        .badge-signed { background: #a5d6a7; color: #1b5e20; }

        /* Filter Tabs */
        .filter-tabs {
            display: flex;
            gap: 6px;
            padding: 12px 16px;
            overflow-x: auto;
            background: var(--bg-primary);
            border-bottom: 1px solid var(--border-color);
            margin: -16px -16px 16px -16px;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .filter-tab {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: 20px;
            background: var(--bg-secondary);
            color: var(--text-secondary);
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            white-space: nowrap;
            transition: all 0.2s;
        }

        .filter-tab:hover {
            background: var(--bg-primary);
            border-color: #263c5c;
        }

        .filter-tab.active {
            background: #263c5c;
            color: white;
            border-color: #263c5c;
        }

        .filter-count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 20px;
            height: 20px;
            padding: 0 6px;
            border-radius: 10px;
            background: rgba(0,0,0,0.1);
            font-size: 11px;
            font-weight: 600;
        }

        .filter-tab.active .filter-count {
            background: rgba(255,255,255,0.2);
        }

        [data-theme="dark"] .filter-tab {
            background: var(--bg-secondary);
            border-color: var(--border-color);
        }

        [data-theme="dark"] .filter-tab.active {
            background: #4a6fa5;
            border-color: #4a6fa5;
        }

        .time-range-selector {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 16px;
            background: var(--bg-secondary);
            border-bottom: 1px solid var(--border-color);
            margin: -16px -16px 16px -16px;
        }

        .time-range-label {
            font-size: 13px;
            color: var(--text-secondary);
        }

        .time-range-select {
            padding: 6px 12px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            background: var(--bg-primary);
            color: var(--text-primary);
            font-size: 13px;
            cursor: pointer;
        }

        .time-range-select:focus {
            outline: none;
            border-color: #263c5c;
        }

        /* Form Elements */
        .form-group {
            margin-bottom: 16px;
        }

        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: var(--text-secondary);
            margin-bottom: 6px;
        }

        .form-input, .form-textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--input-border);
            border-radius: 6px;
            font-size: 16px;
            font-family: inherit;
            background: var(--input-bg);
            color: var(--text-primary);
            transition: background-color 0.3s, border-color 0.3s;
        }

        .form-textarea {
            min-height: 100px;
            resize: vertical;
        }

        .form-input:focus, .form-textarea:focus {
            outline: none;
            border-color: #263c5c;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-primary {
            background: #263c5c;
            color: white;
        }

        .btn-primary:active {
            background: #1a2a40;
        }

        .btn-success {
            background: #4caf50;
            color: white;
        }

        .btn-danger {
            background: #f44336;
            color: white;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:active {
            background: #545b62;
        }

        [data-theme="dark"] .btn-secondary {
            background: #5a6268;
            color: #e0e0e0;
        }

        .btn-block {
            width: 100%;
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Signature Canvas */
        .signature-container {
            border: 2px dashed var(--border-color);
            border-radius: 8px;
            background: var(--input-bg);
            margin-bottom: 12px;
        }

        .signature-container canvas {
            width: 100%;
            height: 200px;
            touch-action: none;
        }

        /* Bottom Navigation */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: var(--bg-card);
            border-top: 1px solid var(--border-color);
            display: flex;
            padding: 8px 0;
            padding-bottom: max(8px, env(safe-area-inset-bottom));
            transition: background-color 0.3s;
        }

        .nav-item {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 4px 2px;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 8px;
            cursor: pointer;
            border: none;
            background: none;
            min-width: 0;
            white-space: nowrap;
            overflow: hidden;
        }

        .nav-item span:not(.nav-icon) {
            display: none;
        }

        @media (min-width: 400px) {
            .nav-item span:not(.nav-icon) {
                display: block;
            }
        }

        .nav-item.active {
            color: #263c5c;
        }

        [data-theme="dark"] .nav-item.active {
            color: #ffffff;
        }

        .nav-icon {
            font-size: 18px;
            margin-bottom: 1px;
        }

        /* Loading */
        .loading {
            text-align: center;
            padding: 40px;
            color: var(--text-secondary);
        }

        .spinner {
            display: inline-block;
            width: 30px;
            height: 30px;
            border: 3px solid #ddd;
            border-top-color: #263c5c;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-secondary);
        }

        .empty-icon {
            font-size: 48px;
            margin-bottom: 16px;
        }

        /* Views */
        .view {
            display: none;
        }

        .view.active {
            display: block;
        }

        /* Equipment List Item */
        .equipment-item {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            border-bottom: 1px solid var(--border-color);
        }

        .equipment-item:last-child {
            border-bottom: none;
        }

        .equipment-icon {
            width: 40px;
            height: 40px;
            background: var(--bg-secondary);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            font-size: 18px;
        }

        .equipment-info {
            flex: 1;
        }

        .equipment-ref {
            font-weight: 600;
            font-size: 14px;
        }

        .equipment-label {
            font-size: 13px;
            color: var(--text-secondary);
        }

        .equipment-status {
            font-size: 20px;
        }

        .equipment-status.done { color: #4caf50; }
        .equipment-status.pending { color: #ccc; }

        /* Toast */
        .toast {
            position: fixed;
            bottom: 80px;
            left: 16px;
            right: 16px;
            background: #333;
            color: white;
            padding: 12px 16px;
            border-radius: 8px;
            text-align: center;
            z-index: 1000;
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.3s;
        }

        .toast.show {
            opacity: 1;
            transform: translateY(0);
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: var(--bg-card);
            border-radius: 12px;
            width: 95%;
            max-width: 500px;
            max-height: 90vh;
            color: var(--text-primary);
            overflow-y: auto;
        }

        .modal-header {
            padding: 16px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 18px;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--text-secondary);
            padding: 0;
            line-height: 1;
        }

        .modal-body {
            padding: 16px;
        }

        .modal-footer {
            padding: 16px;
            border-top: 1px solid var(--border-color);
        }

        /* Material Item */
        .material-item {
            display: flex;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .material-item:last-child {
            border-bottom: none;
        }

        .material-info {
            flex: 1;
        }

        .material-name {
            font-weight: 600;
            font-size: 14px;
        }

        .material-details {
            font-size: 13px;
            color: var(--text-secondary);
        }

        .material-price {
            font-weight: 600;
            color: #263c5c;
            margin-left: 12px;
        }

        .material-delete {
            background: none;
            border: none;
            color: #f44336;
            font-size: 18px;
            cursor: pointer;
            padding: 8px;
            margin-left: 8px;
        }

        /* Product Search Results */
        .product-results {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 6px;
            margin-top: 4px;
            display: none;
        }

        .product-results.show {
            display: block;
        }

        .product-item {
            padding: 10px 12px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
        }

        .product-item:last-child {
            border-bottom: none;
        }

        .product-item:hover, .product-item:active {
            background: #f5f5f5;
        }

        .product-result {
            padding: 10px 12px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .product-result:last-child {
            border-bottom: none;
        }

        .product-result:hover, .product-result:active {
            background: #f5f5f5;
        }

        [data-theme="dark"] .product-result:hover,
        [data-theme="dark"] .product-result:active {
            background: var(--bg-secondary);
        }

        /* v4.3: Toggle Tabs for Product/Freetext */
        .toggle-tabs {
            display: flex;
            gap: 0;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            overflow: hidden;
        }

        .toggle-tab {
            flex: 1;
            padding: 10px 16px;
            border: none;
            background: var(--bg-secondary);
            color: var(--text-secondary);
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .toggle-tab.active {
            background: var(--primary-color);
            color: white;
        }

        .toggle-tab:not(.active):hover {
            background: var(--bg-tertiary, #e9ecef);
        }

        .product-ref {
            font-weight: 600;
            font-size: 13px;
            color: #263c5c;
        }

        .product-label {
            font-size: 14px;
        }

        .product-price {
            font-size: 12px;
            color: var(--text-secondary);
        }

        /* Add Equipment Button */
        .add-equipment-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 16px;
            background: #e8f5e9;
            border: 2px dashed #4caf50;
            border-radius: 8px;
            color: #2e7d32;
            cursor: pointer;
            margin-bottom: 12px;
        }

        .add-equipment-btn:active {
            background: #c8e6c9;
        }

        /* Link Type Badge */
        .link-type-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            margin-left: 8px;
        }

        .link-type-badge.service {
            background: #fff3e0;
            color: #e65100;
        }

        .link-type-badge.maintenance {
            background: #e8f5e9;
            color: #2e7d32;
        }

        [data-theme="dark"] .link-type-badge.service {
            background: #3d2a00;
            color: #ffb74d;
        }

        [data-theme="dark"] .link-type-badge.maintenance {
            background: #1b3d1b;
            color: #81c784;
        }

        /* Document Item */
        .document-item {
            display: flex;
            align-items: center;
            padding: 12px;
            border-bottom: 1px solid #eee;
            text-decoration: none;
            color: inherit;
        }

        .document-item:last-child {
            border-bottom: none;
        }

        .document-item:active {
            background: #f5f5f5;
        }

        .document-icon {
            font-size: 24px;
            margin-right: 12px;
        }

        .document-info {
            flex: 1;
        }

        .document-name {
            font-weight: 600;
            font-size: 14px;
            color: var(--text-primary);
        }

        .document-date {
            font-size: 12px;
            color: var(--text-secondary);
        }

        .document-size {
            font-size: 12px;
            color: var(--text-muted);
        }

        .document-actions {
            display: flex;
            gap: 8px;
        }

        .document-info {
            text-decoration: none;
            color: inherit;
        }

        .document-info:active .document-name {
            color: #263c5c;
        }

        .doc-action {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--bg-secondary);
            text-decoration: none;
            font-size: 16px;
            border: none;
            cursor: pointer;
        }

        .doc-action:active {
            background: var(--border-color);
        }

        .doc-delete:hover, .doc-delete:active {
            background: rgba(244, 67, 54, 0.15);
        }

        /* Entry Item (v1.7) */
        .entry-item {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            border-bottom: 1px solid var(--border-color);
            cursor: pointer;
        }

        .entry-item:last-child {
            border-bottom: none;
        }

        .entry-item:active {
            background: var(--bg-secondary);
        }

        .entry-date {
            font-weight: 600;
            font-size: 14px;
            color: var(--text-primary);
            min-width: 90px;
        }

        .entry-info {
            flex: 1;
            margin-left: 12px;
        }

        .entry-duration {
            font-size: 12px;
            color: var(--text-secondary);
        }

        .entry-summary {
            font-size: 13px;
            color: var(--text-primary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 200px;
        }

        .entry-arrow {
            color: var(--text-muted);
            font-size: 18px;
        }

        .total-duration {
            background: var(--bg-secondary);
            padding: 8px 16px;
            font-size: 13px;
            color: var(--text-secondary);
            font-weight: 500;
            border-top: 1px solid var(--border-color);
        }

        /* Info Section Styles for Dark Mode */
        .info-heading {
            margin: 0 0 8px 0;
            color: #263c5c;
            font-size: 14px;
            font-weight: 600;
        }

        [data-theme="dark"] .info-heading {
            color: #6fa8dc;
        }

        .info-section-divider {
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid var(--border-color);
        }

        .info-text {
            color: var(--text-primary);
            font-size: 14px;
            line-height: 1.5;
        }

        .info-text-secondary {
            color: var(--text-secondary);
        }

        .info-text-muted {
            color: var(--text-muted);
            font-style: italic;
        }

        /* Card Content Styles for Dark Mode */
        .customer-name {
            margin: 0;
            font-size: 14px;
            font-weight: 500;
            color: var(--text-primary);
        }

        .customer-address {
            margin: 4px 0 0;
            font-size: 13px;
            color: var(--text-secondary);
        }

        .object-address-label {
            margin: 0;
            font-size: 12px;
            color: var(--text-primary);
            font-weight: 600;
        }

        .object-address-name {
            margin: 4px 0 0;
            font-size: 13px;
            color: var(--text-primary);
        }

        .object-address-details {
            margin: 2px 0 0;
            font-size: 13px;
            color: var(--text-secondary);
        }

        /* Clickable address links */
        .address-link {
            color: var(--primary-color);
            text-decoration: none;
            display: inline-block;
        }

        .address-link:hover,
        .address-link:active {
            text-decoration: underline;
        }

        .customer-address .address-link,
        .object-address-details .address-link {
            color: inherit;
        }

        .customer-address .address-link:hover,
        .object-address-details .address-link:hover {
            color: var(--primary-color);
        }

        .address-header .address-link {
            color: inherit;
            font-weight: inherit;
        }

        .object-address-divider {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid var(--border-color);
        }

        .date-text {
            margin: 12px 0 0;
            font-size: 12px;
            color: var(--text-muted);
        }

        /* Equipment Modal Address Header */
        .address-header {
            padding: 12px;
            background: var(--bg-secondary);
            font-weight: 600;
            font-size: 13px;
            border-bottom: 1px solid var(--border-color);
            color: #263c5c;
        }

        [data-theme="dark"] .address-header {
            color: #6fa8dc;
        }

        /* Offline Note */
        .offline-note {
            padding: 8px 12px;
            background: #fff3e0;
            color: #e65100;
            font-size: 12px;
            border-bottom: 1px solid var(--border-color);
        }

        [data-theme="dark"] .offline-note {
            background: #3d2a00;
            color: #ffb74d;
        }

        /* Checklist Styles */
        .checklist-section {
            margin-bottom: 16px;
        }

        .checklist-section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 12px;
            background: var(--bg-secondary);
            border-bottom: 1px solid var(--border-color);
            font-weight: 600;
            font-size: 14px;
            color: #263c5c;
        }

        [data-theme="dark"] .checklist-section-header {
            color: #6fa8dc;
        }

        .checklist-section-header .btn-all-ok {
            padding: 4px 10px;
            font-size: 11px;
            background: #4caf50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .checklist-section-header .btn-all-ok:active {
            background: #388e3c;
        }

        .checklist-item {
            display: flex;
            flex-direction: column;
            padding: 10px 12px;
            border-bottom: 1px solid var(--border-color);
        }

        .checklist-item:last-child {
            border-bottom: none;
        }

        .checklist-item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
        }

        .checklist-item-label {
            font-size: 14px;
            color: var(--text-primary);
            flex: 1;
        }

        .checklist-item-select {
            padding: 6px 10px;
            border: 1px solid var(--input-border);
            border-radius: 4px;
            font-size: 13px;
            background: var(--input-bg);
            color: var(--text-primary);
            min-width: 80px;
        }

        .checklist-item-select.answer-ok {
            background: #e8f5e9;
            border-color: #4caf50;
        }

        .checklist-item-select.answer-mangel {
            background: #ffebee;
            border-color: #f44336;
        }

        .checklist-item-select.answer-nv {
            background: #f5f5f5;
            border-color: #9e9e9e;
        }

        [data-theme="dark"] .checklist-item-select.answer-ok {
            background: #1b3d1b;
            border-color: #4caf50;
        }

        [data-theme="dark"] .checklist-item-select.answer-mangel {
            background: #3d1b1b;
            border-color: #f44336;
        }

        [data-theme="dark"] .checklist-item-select.answer-nv {
            background: #2d2d2d;
            border-color: #666;
        }

        .checklist-item-note {
            width: 100%;
            padding: 6px 10px;
            border: 1px solid var(--input-border);
            border-radius: 4px;
            font-size: 13px;
            background: var(--input-bg);
            color: var(--text-primary);
            margin-top: 6px;
        }

        .checklist-item-note::placeholder {
            color: var(--text-muted);
        }

        /* Defect photo styles */
        .defect-photo-section {
            margin-top: 8px;
        }

        .btn-defect-photo {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 12px;
            background: var(--bg-light);
            border: 1px dashed var(--border-color);
            border-radius: 6px;
            font-size: 13px;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-defect-photo:hover {
            background: var(--primary-light);
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        .defect-photo-preview {
            position: relative;
            display: inline-block;
            border-radius: 6px;
            overflow: hidden;
            border: 1px solid var(--border-color);
        }

        .defect-photo-preview img {
            display: block;
            max-width: 120px;
            max-height: 90px;
            object-fit: cover;
            cursor: pointer;
        }

        .defect-photo-delete {
            position: absolute;
            top: 4px;
            right: 4px;
            width: 22px;
            height: 22px;
            background: rgba(0, 0, 0, 0.6);
            color: white;
            border: none;
            border-radius: 50%;
            font-size: 12px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .defect-photo-delete:hover {
            background: var(--danger-color);
        }

        .defect-photo-loading {
            padding: 8px 12px;
            background: var(--bg-light);
            border-radius: 6px;
            font-size: 13px;
            color: var(--text-secondary);
        }

        .defect-photo-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.9);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .defect-photo-fullscreen {
            position: relative;
            max-width: 100%;
            max-height: 100%;
        }

        .defect-photo-fullscreen img {
            max-width: 100%;
            max-height: calc(100vh - 40px);
            object-fit: contain;
            border-radius: 4px;
        }

        .defect-photo-close {
            position: absolute;
            top: -40px;
            right: 0;
            width: 36px;
            height: 36px;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: none;
            border-radius: 50%;
            font-size: 18px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .defect-photo-close:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        /* Entry photo styles (Servicebericht Mängel-Foto) */
        .entry-photo-section {
            margin-top: 8px;
        }

        .btn-entry-photo {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 10px 16px;
            background: var(--bg-light);
            border: 1px dashed var(--border-color);
            border-radius: 6px;
            font-size: 14px;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.2s ease;
            width: 100%;
            justify-content: center;
        }

        .btn-entry-photo:hover {
            background: var(--primary-light);
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        .entry-photo-preview {
            position: relative;
            display: inline-block;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid var(--border-color);
            margin-bottom: 8px;
        }

        .entry-photo-preview img {
            display: block;
            max-width: 100%;
            max-height: 200px;
            object-fit: contain;
            cursor: pointer;
        }

        .entry-photo-delete {
            position: absolute;
            top: 8px;
            right: 8px;
            width: 28px;
            height: 28px;
            background: rgba(0, 0, 0, 0.6);
            color: white;
            border: none;
            border-radius: 50%;
            font-size: 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .entry-photo-delete:hover {
            background: var(--danger-color);
        }

        /* Defect Material List (v4.2) */
        .defect-material-list {
            margin-bottom: 12px;
        }
        .defect-material-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 12px;
            background: var(--bg-secondary);
            border-radius: 6px;
            margin-bottom: 6px;
            font-size: 14px;
        }
        .defect-material-info {
            display: flex;
            flex-direction: column;
            gap: 2px;
            flex: 1;
            min-width: 0;
            overflow: hidden;
        }
        .defect-material-ref {
            font-weight: 600;
            color: var(--primary-color);
            font-size: 12px;
        }
        .defect-material-label {
            color: var(--text-primary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .defect-material-qty {
            color: var(--text-secondary);
            font-size: 13px;
            margin-right: 12px;
            min-width: 35px;
            text-align: right;
            flex-shrink: 0;
        }
        .defect-material-delete {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: rgba(244, 67, 54, 0.1);
            border: none;
            color: var(--danger-color);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }
        .defect-material-delete:hover {
            background: var(--danger-color);
            color: white;
        }
        .defect-material-item.offline {
            border-left: 3px solid var(--warning-color);
            background: rgba(255, 193, 7, 0.1);
        }
        .btn-add-material {
            width: 100%;
            padding: 10px;
            background: var(--bg-secondary);
            border: 2px dashed var(--border-color);
            border-radius: 8px;
            color: var(--text-secondary);
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-add-material:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
        }
        .selected-product-info {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px;
            background: var(--bg-secondary);
            border-radius: 6px;
        }
        .selected-product-info .product-ref {
            font-weight: 600;
            color: var(--primary-color);
        }
        .selected-product-info .product-label {
            flex: 1;
        }
        .btn-clear-product {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: rgba(244, 67, 54, 0.1);
            border: none;
            color: var(--danger-color);
            cursor: pointer;
            font-size: 14px;
        }

        .checklist-complete-btn {
            margin-top: 12px;
        }

        .checklist-status {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px;
            background: #e8f5e9;
            border-radius: 6px;
            margin-bottom: 12px;
            font-size: 14px;
            color: #2e7d32;
        }

        .checklist-status.completed {
            background: #c8e6c9;
        }

        [data-theme="dark"] .checklist-status {
            background: #1b3d1b;
            color: #81c784;
        }

        /* Photo Cropper */
        .crop-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.95);
            z-index: 10000;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 10px;
        }

        .crop-container {
            position: relative;
            max-width: 100%;
            max-height: calc(100vh - 120px);
            overflow: hidden;
            touch-action: none;
        }

        .crop-container img {
            max-width: 100%;
            max-height: calc(100vh - 120px);
            display: block;
        }

        .crop-box {
            position: absolute;
            border: 3px solid #fff;
            box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.6);
            cursor: move;
            touch-action: none;
            min-width: 50px;
            min-height: 50px;
        }

        .crop-handle {
            position: absolute;
            width: 24px;
            height: 24px;
            background: #fff;
            border-radius: 50%;
            border: 2px solid var(--primary);
        }

        .crop-handle-se {
            bottom: -12px;
            right: -12px;
            cursor: se-resize;
        }

        .crop-buttons {
            display: flex;
            gap: 15px;
            margin-top: 15px;
        }

        .crop-btn {
            padding: 14px 35px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
        }

        .crop-btn-cancel {
            background: #666;
            color: #fff;
        }

        .crop-btn-confirm {
            background: var(--primary);
            color: #fff;
        }

        .crop-title {
            color: #fff;
            font-size: 16px;
            margin-bottom: 10px;
        }

        /* Photo Choice Modal */
        .photo-choice-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            z-index: 10000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .photo-choice-modal {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 20px;
            width: 100%;
            max-width: 280px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .photo-choice-title {
            font-size: 18px;
            font-weight: 600;
            text-align: center;
            margin-bottom: 8px;
            color: var(--text);
        }

        .photo-choice-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            padding: 16px 20px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            background: var(--primary);
            color: #fff;
        }

        .photo-choice-btn:active {
            opacity: 0.8;
        }

        .photo-choice-icon {
            font-size: 24px;
        }

        .photo-choice-cancel {
            background: var(--border);
            color: var(--text);
            margin-top: 4px;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <button class="header-btn" id="btnBack" style="display:none;">&#8592;</button>
        <h1 id="headerTitle"><?php echo $title; ?></h1>
        <span class="sync-status" id="syncStatus">Offline</span>
        <a href="settings.php" class="header-btn" id="btnSettings" title="Einstellungen" style="text-decoration:none;color:white;">&#9881;</a>
        <a href="<?php echo $dolibarrUrl; ?>" class="header-btn" id="btnDolibarr" title="Dolibarr öffnen" style="text-decoration:none;color:white;">&#127968;</a>
        <button class="header-btn" id="btnSync" title="Synchronisieren">&#8635;</button>
    </div>

    <!-- Trusted Device Info Banner -->
    <div id="trustedDeviceBanner" style="display:none;background:#e8f5e9;padding:8px 16px;font-size:13px;color:#2e7d32;border-bottom:1px solid #c8e6c9;">
        <span id="trustedDeviceText"></span>
    </div>

    <!-- Interventions List View -->
    <div class="view active" id="viewInterventions">
        <div class="content">
            <div class="loading" id="interventionsLoading">
                <div class="spinner"></div>
                <p>Lade Interventionen...</p>
            </div>
            <div id="interventionsList"></div>
        </div>
    </div>

    <!-- Equipment List View -->
    <div class="view" id="viewEquipment">
        <div class="content">
            <div class="loading" id="equipmentLoading" style="display:none;">
                <div class="spinner"></div>
                <p>Lade Equipment...</p>
            </div>
            <div id="equipmentList"></div>
        </div>
    </div>

    <!-- Entries List View (v1.7) -->
    <div class="view" id="viewEntries">
        <div class="content">
            <div class="card">
                <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
                    <h3 class="card-title" id="entriesEquipmentRef" style="margin:0;">Equipment</h3>
                    <span id="entriesLinkType"></span>
                </div>
                <!-- Equipment Details Section -->
                <div id="equipmentDetailsSection" class="card-body" style="padding:12px;border-bottom:1px solid var(--border-color);background:var(--bg-secondary);">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:13px;">
                        <div style="grid-column:span 2;">
                            <span style="color:var(--text-muted);">Bezeichnung:</span>
                            <div id="eqDetailLabel" class="eq-detail-value" style="cursor:pointer;padding:4px;border-radius:4px;">-</div>
                        </div>
                        <div style="grid-column:span 2;">
                            <span style="color:var(--text-muted);">Standort:</span>
                            <div id="eqDetailLocation" class="eq-detail-value" style="cursor:pointer;padding:4px;border-radius:4px;">-</div>
                        </div>
                        <div>
                            <span style="color:var(--text-muted);">Typ:</span>
                            <div id="eqDetailType" class="eq-detail-value" style="padding:4px;">-</div>
                        </div>
                        <div>
                            <span style="color:var(--text-muted);">Hersteller:</span>
                            <div id="eqDetailManufacturer" class="eq-detail-value" style="cursor:pointer;padding:4px;border-radius:4px;">-</div>
                        </div>
                        <div id="eqDetailSerialRow" style="display:none;">
                            <span style="color:var(--text-muted);">Seriennummer:</span>
                            <div id="eqDetailSerial" class="eq-detail-value" style="cursor:pointer;padding:4px;border-radius:4px;">-</div>
                        </div>
                    </div>
                </div>
                <div class="card-body" style="padding:0;">
                    <!-- Add Entry Button -->
                    <div class="add-equipment-btn" id="btnAddEntry" style="margin:12px;border-radius:6px;">
                        <span>➕</span> Neuer Eintrag
                    </div>
                    <!-- Entries List -->
                    <div id="entriesList"></div>
                </div>
            </div>

            <!-- Recommendations & Notes (Summary) -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Empfehlungen & Notizen</h3>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">Empfehlungen</label>
                        <textarea class="form-textarea" id="summaryRecommendations" rows="2"></textarea>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">Notizen</label>
                        <textarea class="form-textarea" id="summaryNotes" rows="2"></textarea>
                    </div>
                </div>
            </div>
            <button type="button" class="btn btn-primary btn-block" id="btnSaveSummary">Empfehlungen speichern</button>

            <!-- Materials Section -->
            <div class="card" style="margin-top:12px;" id="materialsCard">
                <div class="card-header">
                    <h3 class="card-title">Material</h3>
                    <button type="button" class="btn btn-primary" id="btnAddMaterial" style="padding: 6px 12px; font-size: 14px;">+ Hinzufügen</button>
                </div>
                <div class="card-body" id="materialsList">
                    <div class="empty-state" style="padding: 20px 0;">
                        <p style="margin: 0; color: #666;">Kein Material erfasst</p>
                    </div>
                </div>
            </div>

            <!-- Checklist Section (only for maintenance) -->
            <div class="card" style="margin-top:12px;display:none;" id="checklistCard">
                <div class="card-header">
                    <h3 class="card-title" id="checklistTitle">Checkliste</h3>
                </div>
                <div class="card-body" id="checklistContent">
                    <div class="loading">
                        <div class="spinner"></div>
                        <p>Lade Checkliste...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Entry Editor View (v1.7) -->
    <div class="view" id="viewEntry">
        <div class="content">
            <form id="entryForm">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title" id="entryTitle">Neuer Eintrag</h3>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label class="form-label">Arbeitsdatum</label>
                            <input type="date" class="form-input" id="entryDate">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Arbeitszeit</label>
                            <div style="display: flex; gap: 12px;">
                                <div style="flex: 1;">
                                    <input type="number" class="form-input" id="entryHours" min="0" max="24" placeholder="Std">
                                    <span style="font-size: 12px; color: #666;">Stunden</span>
                                </div>
                                <div style="flex: 1;">
                                    <select class="form-input" id="entryMinutes">
                                        <option value="0">0 min</option>
                                        <option value="15">15 min</option>
                                        <option value="30">30 min</option>
                                        <option value="45">45 min</option>
                                    </select>
                                    <span style="font-size: 12px; color: #666;">Minuten</span>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Durchgeführte Arbeiten</label>
                            <textarea class="form-textarea" id="entryWorkDone" rows="4"></textarea>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Festgestellte Mängel</label>
                            <textarea class="form-textarea" id="entryIssuesFound" rows="3"></textarea>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Mangel-Foto</label>
                            <div id="entryPhotoSection" class="entry-photo-section">
                                <div id="entryPhotoPreview" class="entry-photo-preview" style="display:none;">
                                    <img id="entryPhotoImg" src="" alt="Mangel-Foto" onclick="app.viewEntryPhoto()">
                                    <button type="button" class="entry-photo-delete" onclick="app.deleteEntryPhoto()" title="Foto löschen">✕</button>
                                </div>
                                <button type="button" id="btnAddEntryPhoto" class="btn-entry-photo" onclick="app.captureEntryPhoto()">
                                    📷 Foto hinzufügen
                                </button>
                            </div>
                        </div>

                        <!-- Defect Materials Section (v4.2) -->
                        <div class="form-group" id="defectMaterialSection" style="display:none;">
                            <label class="form-label">Material für Mängelbeseitigung</label>
                            <div id="defectMaterialList" class="defect-material-list"></div>
                            <button type="button" class="btn-add-material" onclick="app.showDefectMaterialModal()">
                                + Material hinzufügen
                            </button>
                        </div>

                        <!-- Commissioning & Acceptance Section (v4.5) - hidden for maintenance entries -->
                        <div id="commissioningAcceptanceSection" style="margin-top:16px; padding:12px; background:var(--bg-secondary); border-radius:8px;">
                            <h4 style="margin:0 0 12px 0; font-size:14px; color:var(--text-muted);">Inbetriebnahme & Abnahme</h4>

                            <!-- Commissioning (Inbetriebnahme) -->
                            <div class="form-group" style="margin-bottom:16px; padding-bottom:12px; border-bottom:1px solid var(--border-color);">
                                <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-weight:bold;">
                                    <input type="checkbox" id="entryCommissioningDone" style="width:20px; height:20px;">
                                    <span>Inbetriebnahme erfolgt</span>
                                </label>
                                <div id="commissioningDateRow" style="display:none; margin-top:8px;">
                                    <label class="form-label" style="font-size:12px;">Erfolgt am:</label>
                                    <input type="date" class="form-input" id="entryCommissioningDate" style="width:100%;">
                                </div>
                                <div id="commissioningNoteRow" style="display:none; margin-top:8px;">
                                    <label class="form-label" style="font-size:12px;">Bemerkung:</label>
                                    <textarea class="form-textarea" id="entryCommissioningNote" rows="2" placeholder="Grund warum nicht durchgeführt"></textarea>
                                </div>
                            </div>

                            <!-- Acceptance (Abnahme) -->
                            <div class="form-group" style="margin-bottom:16px; padding-bottom:12px; border-bottom:1px solid var(--border-color);">
                                <!-- Parent: Doing acceptance at all? -->
                                <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-weight:bold;">
                                    <input type="checkbox" id="entryDoingAcceptance" style="width:20px; height:20px;">
                                    <span>Abnahme durchführen</span>
                                </label>

                                <!-- Sub-section: Acceptance details (shown when doing acceptance) -->
                                <div id="acceptanceDetailsRow" style="display:none; margin-top:12px; margin-left:28px; padding:10px; background:var(--bg-tertiary); border-radius:6px;">
                                    <!-- Success checkbox -->
                                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-weight:bold; color:#2e7d32;">
                                        <input type="checkbox" id="entryAcceptanceDone" style="width:20px; height:20px;">
                                        <span>Abnahme erfolgreich</span>
                                    </label>

                                    <!-- Success: Date + optional note -->
                                    <div id="acceptanceSuccessRow" style="display:none; margin-top:10px;">
                                        <label class="form-label" style="font-size:12px;">Abnahme am:</label>
                                        <input type="date" class="form-input" id="entryAcceptanceDate" style="width:100%; margin-bottom:8px;">
                                        <label class="form-label" style="font-size:12px;">Bemerkung (optional):</label>
                                        <textarea class="form-textarea" id="entryAcceptanceNote" rows="2" placeholder="Zusätzliche Bemerkungen"></textarea>
                                    </div>

                                    <!-- Failed: Mandatory defect description -->
                                    <div id="acceptanceFailedRow" style="margin-top:10px;">
                                        <label class="form-label" style="font-size:12px; color:#d32f2f; font-weight:bold;">
                                            Abnahme konnte nicht erfolgen - wesentliche Mängel:
                                        </label>
                                        <textarea class="form-textarea" id="entryAcceptanceDefects" rows="3" placeholder="Mängelbeschreibung (Pflichtfeld bei nicht erfolgter Abnahme)" style="border-color:#d32f2f;"></textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- Instruction & Testbook -->
                            <div class="form-group" style="margin-bottom:0;">
                                <label style="display:flex; align-items:center; gap:8px; cursor:pointer; margin-bottom:8px;">
                                    <input type="checkbox" id="entryInstructionDone" style="width:20px; height:20px;">
                                    <span>Einweisung erfolgt</span>
                                </label>
                                <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                                    <input type="checkbox" id="entryTestbookHanded" style="width:20px; height:20px;">
                                    <span>Prüfbuch übergeben</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-block">Speichern</button>
                <button type="button" class="btn btn-danger btn-block" id="btnDeleteEntry" style="margin-top:8px;display:none;">Eintrag löschen</button>
            </form>
        </div>
    </div>

    <!-- Signature View -->
    <div class="view" id="viewSignature">
        <div class="content">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Kundenunterschrift</h3>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">Name des Unterzeichners</label>
                        <input type="text" class="form-input" id="signerName" placeholder="Vor- und Nachname">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Unterschrift</label>
                        <div class="signature-container">
                            <div id="signatureCanvas"></div>
                        </div>
                        <button type="button" class="btn btn-danger" id="btnClearSignature">Löschen</button>
                    </div>
                </div>
            </div>

            <button type="button" class="btn btn-success btn-block" id="btnSaveSignature">
                Unterschrift speichern
            </button>
        </div>
    </div>

    <!-- Bottom Navigation -->
    <div class="bottom-nav">
        <button class="nav-item active" data-view="viewInterventions">
            <span class="nav-icon">📋</span>
            <span>Aufträge</span>
        </button>
        <button class="nav-item" id="navRelease" style="display:none;">
            <span class="nav-icon" id="releaseIcon">✅</span>
            <span id="releaseText">Freigeben</span>
        </button>
        <button class="nav-item" id="navInfo" style="display:none;">
            <span class="nav-icon">ℹ️</span>
            <span>Info</span>
        </button>
        <button class="nav-item" id="navDocuments" style="display:none;">
            <span class="nav-icon">📄</span>
            <span>Dokumente</span>
        </button>
        <button class="nav-item" id="navPdfPreview" style="display:none;">
            <span class="nav-icon">👁️</span>
            <span>Vorschau</span>
        </button>
        <button class="nav-item" id="navAcceptanceProtocol" style="display:none;">
            <span class="nav-icon">📋</span>
            <span>Abnahme</span>
        </button>
        <button class="nav-item" data-view="viewSignature" id="navSignature" style="display:none;">
            <span class="nav-icon">✍️</span>
            <span>Unterschrift</span>
        </button>
    </div>

    <!-- Toast -->
    <div class="toast" id="toast"></div>

    <!-- Material Modal -->
    <div class="modal" id="materialModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Material hinzufügen</h3>
                <button type="button" class="modal-close" id="btnCloseMaterial">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Produkt suchen</label>
                    <input type="text" class="form-input" id="productSearch" placeholder="Artikelnr. oder Name...">
                    <div id="productResults" class="product-results"></div>
                </div>
                <div class="form-group">
                    <label class="form-label">Bezeichnung *</label>
                    <input type="text" class="form-input" id="materialName" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Beschreibung</label>
                    <input type="text" class="form-input" id="materialDescription">
                </div>
                <div style="display: flex; gap: 12px;">
                    <div class="form-group" style="flex: 1;">
                        <label class="form-label">Menge</label>
                        <input type="number" class="form-input" id="materialQty" value="1" min="0" step="0.01">
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label class="form-label">Einheit</label>
                        <select class="form-input" id="materialUnit">
                            <option value="Stk">Stk</option>
                            <option value="m">m</option>
                            <option value="kg">kg</option>
                            <option value="l">l</option>
                            <option value="Set">Set</option>
                        </select>
                    </div>
                </div>
                <div style="display: flex; gap: 12px;">
                    <div class="form-group" style="flex: 1;">
                        <label class="form-label">Einzelpreis (€)</label>
                        <input type="number" class="form-input" id="materialPrice" min="0" step="0.01">
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label class="form-label">Seriennummer</label>
                        <input type="text" class="form-input" id="materialSerial">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Notizen</label>
                    <input type="text" class="form-input" id="materialNotes">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-success btn-block" id="btnSaveMaterial">Speichern</button>
            </div>
        </div>
    </div>

    <!-- Defect Material Modal (v4.2, v4.3: Freitext) -->
    <div class="modal" id="defectMaterialModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Material für Mängelbeseitigung</h3>
                <button type="button" class="modal-close" onclick="app.closeDefectMaterialModal()">&times;</button>
            </div>
            <div class="modal-body">
                <!-- v4.3: Toggle Produkt/Freitext -->
                <div class="form-group">
                    <div class="toggle-tabs">
                        <button type="button" class="toggle-tab active" id="defectTabProduct" onclick="app.switchDefectMaterialMode('product')">📦 Produkt</button>
                        <button type="button" class="toggle-tab" id="defectTabFreetext" onclick="app.switchDefectMaterialMode('freetext')">✏️ Freitext</button>
                    </div>
                </div>
                <!-- Produkt-Suche -->
                <div id="defectProductMode">
                    <div class="form-group">
                        <label class="form-label">Produkt suchen</label>
                        <input type="text" class="form-input" id="defectProductSearch" placeholder="Artikelnr. oder Name..." autocomplete="off">
                        <div id="defectProductResults" class="product-results"></div>
                    </div>
                    <div class="form-group" id="defectSelectedProduct" style="display:none;">
                        <label class="form-label">Ausgewähltes Produkt</label>
                        <div class="selected-product-info">
                            <span id="defectProductRef" class="product-ref"></span>
                            <span id="defectProductLabel" class="product-label"></span>
                            <button type="button" class="btn-clear-product" onclick="app.clearDefectProduct()">✕</button>
                        </div>
                        <input type="hidden" id="defectProductId">
                    </div>
                </div>
                <!-- Freitext-Eingabe -->
                <div id="defectFreetextMode" style="display:none;">
                    <div class="form-group">
                        <label class="form-label">Material-Bezeichnung *</label>
                        <input type="text" class="form-input" id="defectFreetextLabel" placeholder="z.B. Türschließer TS 3000">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Menge</label>
                    <input type="number" class="form-input" id="defectMaterialQty" value="1" min="1" step="1">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-success btn-block" onclick="app.saveDefectMaterial()">Hinzufügen</button>
            </div>
        </div>
    </div>

    <!-- Equipment Modal -->
    <div class="modal" id="equipmentModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Anlage hinzufügen</h3>
                <button type="button" class="modal-close" id="btnCloseEquipment">&times;</button>
            </div>
            <div class="modal-body" id="availableEquipmentList" style="padding-bottom:70px;">
                <div class="loading">
                    <div class="spinner"></div>
                    <p>Lade verfügbare Anlagen...</p>
                </div>
            </div>
            <div class="modal-footer" id="equipmentModalFooter" style="display:none;position:absolute;bottom:0;left:0;right:0;background:var(--bg-card);border-top:1px solid var(--border-color);padding:12px;gap:8px;">
                <div style="display:flex;justify-content:space-between;align-items:center;width:100%;">
                    <span id="selectedCount" style="font-size:13px;">0 ausgewählt</span>
                    <div style="display:flex;gap:8px;">
                        <button type="button" class="btn btn-primary" style="padding:8px 12px;font-size:13px;" onclick="app.linkSelectedEquipment('service')">Service</button>
                        <button type="button" class="btn btn-success" style="padding:8px 12px;font-size:13px;" onclick="app.linkSelectedEquipment('maintenance')">Wartung</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Documents Modal -->
    <div class="modal" id="documentsModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Dokumente</h3>
                <button type="button" class="modal-close" id="btnCloseDocuments">&times;</button>
            </div>
            <div class="modal-body" id="documentsList">
                <div class="loading">
                    <div class="spinner"></div>
                    <p>Lade Dokumente...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Info Modal -->
    <div class="modal" id="infoModal">
        <div class="modal-content" style="max-height:85vh;">
            <div class="modal-header">
                <h3>Auftragsdetails</h3>
                <button type="button" class="modal-close" id="btnCloseInfo">&times;</button>
            </div>
            <div class="modal-body" id="infoContent" style="overflow-y:auto;">
                <div class="loading">
                    <div class="spinner"></div>
                    <p>Lade...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="<?php echo DOL_URL_ROOT; ?>/includes/jquery/js/jquery.min.js"></script>
    <script src="<?php echo $jSignaturePath; ?>"></script>
    <script>
        // Configuration
        const CONFIG = {
            apiBase: '<?php echo $apiBase; ?>',
            moduleUrl: '<?php echo dol_buildpath('/custom/equipmentmanager/', 1); ?>',
            isAuthenticated: <?php echo $isAuthenticated ? 'true' : 'false'; ?>,
            authData: <?php echo $authData ? json_encode($authData) : 'null'; ?>,
            trustedDevice: <?php echo $trustedDeviceInfo ? json_encode($trustedDeviceInfo) : 'null'; ?>
        };
    </script>
    <script src="db.js"></script>
    <script src="app.js"></script>

    <?php if (file_exists('sw.js')): ?>
    <script>
        // Register Service Worker
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('sw.js')
                .then(reg => console.log('SW registered:', reg.scope))
                .catch(err => console.error('SW registration failed:', err));
        }
    </script>
    <?php endif; ?>
</body>
</html>
