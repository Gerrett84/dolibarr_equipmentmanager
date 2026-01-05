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
                <div class="card-header">
                    <h3 class="card-title" id="entriesEquipmentRef">Equipment</h3>
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
            <div class="card" style="margin-top:12px;">
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

    <!-- Equipment Modal -->
    <div class="modal" id="equipmentModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Anlage hinzufügen</h3>
                <button type="button" class="modal-close" id="btnCloseEquipment">&times;</button>
            </div>
            <div class="modal-body" id="availableEquipmentList">
                <div class="loading">
                    <div class="spinner"></div>
                    <p>Lade verfügbare Anlagen...</p>
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
