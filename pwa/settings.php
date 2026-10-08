<?php
/**
 * PWA Settings Page - No authentication required
 * Allows saving credentials for auto-login
 */

// No login required for this page
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

// Handle login test via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['test_login'])) {
    header('Content-Type: application/json');

    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $totp_code = $_POST['totp_code'] ?? '';

    if (empty($username) || empty($password)) {
        echo json_encode(['status' => 'error', 'message' => 'Benutzername und Passwort erforderlich']);
        exit;
    }

    require_once DOL_DOCUMENT_ROOT.'/core/lib/security2.lib.php';

    $login = checkLoginPassEntity($username, $password, 1, array('dolibarr'));

    if (!$login || $login === '--bad-login-validity--') {
        echo json_encode(['status' => 'error', 'message' => 'Benutzername oder Passwort falsch']);
        exit;
    }

    // Get user
    require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
    $tmpuser = new User($db);
    $tmpuser->fetch('', $login);

    if ($tmpuser->id <= 0) {
        // Same message as wrong password to avoid username enumeration
        echo json_encode(['status' => 'error', 'message' => 'Benutzername oder Passwort falsch']);
        exit;
    }

    // Check 2FA if enabled
    $requires_2fa = false;
    $totp2fa_verified = false;

    if (isModEnabled('totp2fa')) {
        dol_include_once('/totp2fa/class/user2fa.class.php');

        if (class_exists('User2FA')) {
            $user2fa = new User2FA($db);
            $result = $user2fa->fetch($tmpuser->id);

            if ($result > 0 && $user2fa->is_enabled) {
                $requires_2fa = true;

                // Check trusted device
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
                    }
                }

                // Verify TOTP code if provided
                if (!$totp2fa_verified && !empty($totp_code)) {
                    $totp2fa_verified = $user2fa->verifyCode($totp_code);
                    if (!$totp2fa_verified && strpos($totp_code, '-') !== false) {
                        $totp2fa_verified = $user2fa->verifyBackupCode($totp_code);
                    }
                }
            }
        }
    }

    if ($requires_2fa && !$totp2fa_verified) {
        echo json_encode([
            'status' => 'error',
            'message' => '2FA-Code erforderlich',
            'requires_2fa' => true
        ]);
        exit;
    }

    // Get trusted device info
    $trustedInfo = null;
    if (isModEnabled('totp2fa')) {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $acceptLang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        $deviceHash = hash('sha256', $userAgent . '|' . $acceptLang);

        $sql = "SELECT trusted_until, device_name, DATEDIFF(trusted_until, NOW()) as days_left FROM ".MAIN_DB_PREFIX."totp2fa_trusted_devices";
        $sql .= " WHERE fk_user = ".(int)$tmpuser->id;
        $sql .= " AND device_hash = '".$db->escape($deviceHash)."'";
        $sql .= " AND trusted_until > NOW()";

        $resql = $db->query($sql);
        if ($resql && $db->num_rows($resql) > 0) {
            $obj = $db->fetch_object($resql);
            $trustedInfo = [
                'device_name' => $obj->device_name,
                'trusted_until' => $obj->trusted_until,
                'days_remaining' => max(1, (int)$obj->days_left)
            ];
        }
    }

    // Generate PWA token so client can store token instead of password
    $pwaToken = null;
    $plainToken = bin2hex(random_bytes(32));
    $validUntil = dol_now() + (90 * 24 * 3600);

    $sqlDel = "DELETE FROM ".MAIN_DB_PREFIX."equipmentmanager_pwa_token WHERE fk_user = ".(int)$tmpuser->id;
    $db->query($sqlDel);
    $sqlIns = "INSERT INTO ".MAIN_DB_PREFIX."equipmentmanager_pwa_token"
        ." (fk_user, token, valid_until, date_creation, last_use)"
        ." VALUES (".(int)$tmpuser->id.",'".$db->escape(hash('sha256', $plainToken))."',"
        ."'".$db->idate($validUntil)."','".$db->idate(dol_now())."','".$db->idate(dol_now())."')";
    if ($db->query($sqlIns)) {
        $pwaToken = $plainToken;
    }

    // Success!
    echo json_encode([
        'status' => 'ok',
        'message' => 'Login erfolgreich',
        'user' => [
            'id' => (int)$tmpuser->id,
            'login' => $tmpuser->login,
            'name' => $tmpuser->getFullName($langs)
        ],
        'pwa_token' => $pwaToken,
        'trusted_device' => $trustedInfo
    ]);
    exit;
}

$title = 'Einstellungen';
$dolibarrUrl = dol_buildpath('/', 1);
$apiBase = dol_buildpath('/custom/equipmentmanager/api/index.php', 1);

// Brand color (Setup -> Equipment Manager -> Brand color). Empty by default,
// so this changes nothing until an admin picks a color. Mirrors index.php's
// handling, but keeps this page's own original color as the fallback default.
$pwaBrandColor = '#263c5c'; // previous hardcoded header/theme-color default, kept as fallback
$brandColorSetting = getDolGlobalString('EQUIPMENTMANAGER_BRAND_COLOR');
if (preg_match('/^#[0-9a-fA-F]{6}$/', $brandColorSetting)) {
    $pwaBrandColor = $brandColorSetting;
}
$pwaBrandColorRgb = sprintf('%d, %d, %d', hexdec(substr($pwaBrandColor, 1, 2)), hexdec(substr($pwaBrandColor, 3, 2)), hexdec(substr($pwaBrandColor, 5, 2)));
dol_include_once('/equipmentmanager/lib/pwa_theme.lib.php');
$pwaDark = eqmPwaDarkColors('#1e2d3d');

// Get trusted device info
$trustedDeviceInfo = null;
if (isModEnabled('totp2fa')) {
    // We need to get any logged-in user's trusted device - check saved credentials
    // Since this is a no-login page, we can only show this after login test is successful
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="<?php echo $pwaBrandColor; ?>" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="<?php echo $pwaDark['header']; ?>" media="(prefers-color-scheme: dark)">
    <title><?php echo $title; ?></title>

    <!-- Theme initialization -->
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

    <style>
        :root {
            --bg-primary: #f5f5f5;
            --bg-card: #ffffff;
            --text-primary: #333333;
            --text-secondary: #666666;
            --text-muted: #999999;
            --border-color: #dddddd;
            --header-bg: <?php echo $pwaBrandColor; ?>;
            --input-bg: #ffffff;
            --input-border: #dddddd;
            --primary-color: <?php echo $pwaBrandColor; ?>;
            --primary-light: rgba(<?php echo $pwaBrandColorRgb; ?>, 0.1);
        }
        [data-theme="dark"] {
            --bg-primary: #1a1a1a;
            --bg-card: #2d2d2d;
            --text-primary: #e0e0e0;
            --text-secondary: #b0b0b0;
            --text-muted: #808080;
            --border-color: #404040;
            --header-bg: <?php echo $pwaDark['header']; ?>;
            --input-bg: #3d3d3d;
            --input-border: #505050;
            --primary-color: <?php echo $pwaDark['primary']; ?>;
            --primary-light: <?php echo $pwaDark['primaryLight']; ?>;
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
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .header-btn:active {
            background: rgba(255,255,255,0.2);
        }
        .content {
            padding: 12px;
            max-width: 400px;
            margin: 0 auto;
        }
        .card {
            background: var(--bg-card);
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            padding: 12px 14px;
            margin-bottom: 12px;
            transition: background-color 0.3s;
        }
        .card h2 {
            margin: 0 0 10px 0;
            font-size: 16px;
            color: var(--text-primary);
        }
        .section-title {
            margin: 20px 0 8px 4px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: var(--text-muted);
        }
        .section-title:first-of-type {
            margin-top: 0;
        }
        .form-group {
            margin-bottom: 10px;
        }
        .form-label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            font-size: 14px;
        }
        .form-input {
            width: 100%;
            padding: 9px 12px;
            border: 1px solid var(--input-border);
            border-radius: 8px;
            font-size: 15px;
            font-family: inherit;
            background: var(--input-bg);
            color: var(--text-primary);
            transition: background-color 0.3s, border-color 0.3s;
        }
        .form-input:focus {
            outline: none;
            border-color: var(--primary-color);
        }
        .btn {
            display: block;
            width: 100%;
            padding: 10px;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            margin-bottom: 8px;
        }
        .btn-primary {
            background: var(--primary-color);
            color: white;
        }
        .btn-success {
            background: #4caf50;
            color: white;
        }
        .btn-danger {
            background: #f44336;
            color: white;
        }
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .message {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 16px;
            text-align: center;
        }
        .message.success {
            background: #e8f5e9;
            color: #2e7d32;
        }
        .message.error {
            background: #ffebee;
            color: #c62828;
        }
        .message.info {
            background: #e3f2fd;
            color: #1565c0;
        }
        .status {
            color: var(--text-secondary);
            font-size: 14px;
        }
        .help-text {
            font-size: 13px;
            color: var(--text-muted);
            margin-top: 8px;
        }
        /* Theme Switcher */
        .theme-switcher {
            display: flex;
            gap: 8px;
        }
        .theme-option {
            flex: 1;
            padding: 8px 6px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            background: var(--bg-card);
            cursor: pointer;
            text-align: center;
            transition: all 0.2s;
        }
        .theme-option:hover {
            border-color: var(--primary-color);
        }
        .theme-option.active {
            border-color: var(--primary-color);
            background: var(--primary-light);
        }
        .theme-option-icon {
            font-size: 18px;
            margin-bottom: 2px;
        }
        .theme-option-label {
            font-size: 11px;
            font-weight: 500;
            color: var(--text-primary);
        }
        .back-link {
            display: block;
            text-align: center;
            color: var(--primary-color);
            text-decoration: none;
            padding: 12px;
            font-weight: 500;
        }
        .help-text {
            font-size: 13px;
            color: #666;
            margin-top: 8px;
        }
        .sig-pad-wrap {
            border: 2px solid var(--input-border);
            border-radius: 8px;
            display: block;
            background: #fff;
            touch-action: none;
        }
        .sig-pad-wrap canvas {
            display: block;
            width: 100%;
            height: 150px;
            cursor: crosshair;
        }
        .sig-preview {
            border: 1px solid var(--input-border);
            border-radius: 8px;
            max-width: 100%;
            background: #fff;
            padding: 8px;
            margin-bottom: 10px;
        }
        .btn-row {
            display: flex;
            gap: 8px;
        }
        .btn-row .btn {
            margin-bottom: 0;
        }
        .btn-secondary {
            background: var(--border-color);
            color: var(--text-primary);
        }
    </style>
</head>
<body>
    <div class="header">
        <a href="index.php" class="header-btn" title="Zurück">&#8592;</a>
        <h1><?php echo $title; ?></h1>
        <button class="header-btn" title="Dolibarr Backend" onclick="if(confirm('Zum Dolibarr-Backend wechseln?')) window.location.href='<?php echo $dolibarrUrl; ?>';">&#127968;</button>
    </div>

    <div class="content">
        <div id="messageArea"></div>

        <div class="section-title">Konto</div>

        <div class="card">
            <h2>Login-Daten speichern</h2>
            <p class="help-text" style="margin-top:0;">
                Speichern Sie Ihre Login-Daten, um sich automatisch in der PWA anzumelden.
            </p>

            <form id="settingsForm">
                <div class="form-group">
                    <label class="form-label">Benutzername</label>
                    <input type="text" id="username" class="form-input" required autocomplete="username">
                </div>

                <div class="form-group">
                    <label class="form-label">Passwort</label>
                    <input type="password" id="password" class="form-input" required autocomplete="current-password">
                </div>

                <div class="form-group" id="totpGroup" style="display:none;">
                    <label class="form-label">2FA-Code (falls aktiviert)</label>
                    <input type="text" id="totp_code" class="form-input"
                        placeholder="6-stelliger Code" maxlength="10"
                        inputmode="numeric" autocomplete="one-time-code"
                        style="text-align:center;letter-spacing:4px;">
                </div>

                <button type="submit" class="btn btn-primary" id="btnTest">
                    Testen & Speichern
                </button>
            </form>
        </div>

        <div class="card" id="statusCard">
            <h2>Gespeicherte Daten</h2>
            <div id="statusContent" class="status">
                <div class="status-icon">⏳</div>
                <p>Lade...</p>
            </div>
        </div>

        <div class="card" id="trustedDeviceCard" style="display:none;">
            <h2>🔒 Vertrauenswürdiges Gerät</h2>
            <div id="trustedDeviceContent" class="status"></div>
        </div>

        <div class="card" id="passwordCard" style="display:none;">
            <h2>🔑 Passwort ändern</h2>
            <p class="help-text" style="margin-top:0;">
                Ändert Ihr Passwort für Dolibarr und die PWA. <span id="pwPolicyHint"></span>
            </p>
            <form id="passwordForm">
                <input type="text" autocomplete="username" style="display:none;" tabindex="-1" aria-hidden="true">
                <div class="form-group">
                    <label class="form-label">Aktuelles Passwort</label>
                    <input type="password" id="pw_current" class="form-input" required autocomplete="current-password">
                </div>
                <div class="form-group">
                    <label class="form-label">Neues Passwort</label>
                    <input type="password" id="pw_new" class="form-input" required autocomplete="new-password">
                </div>
                <div class="form-group">
                    <label class="form-label">Neues Passwort wiederholen</label>
                    <input type="password" id="pw_new2" class="form-input" required autocomplete="new-password">
                </div>
                <p class="help-text" id="pwStatus"></p>
                <button type="submit" class="btn btn-primary" id="btnPwChange">Passwort ändern</button>
            </form>
        </div>

        <div class="card" id="totpCard" style="display:none;">
            <h2>🛡️ Zwei-Faktor-Authentifizierung</h2>
            <div id="totpContent"></div>
        </div>

        <div class="section-title">Profil</div>

        <div class="card" id="signatureCard" style="display:none;">
            <h2>✍️ Techniker-Unterschrift</h2>
            <p class="help-text" style="margin-top:0;">
                Wird beim Kunden-Unterschreiben im Servicebericht als Ihre Unterschrift verwendet.
            </p>

            <div class="form-group">
                <label class="form-label">Name für die Unterschrift</label>
                <input type="text" id="technician_name" class="form-input" placeholder="Ihr Name">
            </div>

            <div id="sigExisting" style="display:none;">
                <img id="sigExistingImg" class="sig-preview" alt="Aktuelle Unterschrift">
            </div>

            <div id="sigPadWrap" class="sig-pad-wrap">
                <canvas id="signaturePad"></canvas>
            </div>

            <p class="help-text" id="sigStatus"></p>

            <div class="btn-row" style="margin-top:10px;">
                <button type="button" class="btn btn-secondary" id="btnSigClear">Löschen (Zeichnung)</button>
                <button type="button" class="btn btn-primary" id="btnSigSave">Speichern</button>
            </div>
            <button type="button" class="btn btn-danger" id="btnSigDelete" style="margin-top:8px;display:none;">
                Unterschrift entfernen
            </button>
        </div>

        <div class="section-title">Kalender</div>

        <div class="card" id="calendarCard" style="display:none;">
            <h2>📅 Kalender-Abo</h2>
            <p class="help-text" style="margin-top:0;">
                Alle offenen Serviceaufträge als Termine in Ihrer Kalender-App abonnieren (z.B. iPhone-Kalender, Google Calendar).
            </p>
            <div id="calendarContent" class="status">Lade...</div>
        </div>

        <div class="section-title">Darstellung</div>

        <div class="card">
            <h2>🎨 Design</h2>
            <div class="theme-switcher">
                <div class="theme-option" data-theme="light" onclick="setTheme('light')">
                    <div class="theme-option-icon">☀️</div>
                    <div class="theme-option-label">Hell</div>
                </div>
                <div class="theme-option" data-theme="dark" onclick="setTheme('dark')">
                    <div class="theme-option-icon">🌙</div>
                    <div class="theme-option-label">Dunkel</div>
                </div>
                <div class="theme-option" data-theme="auto" onclick="setTheme('auto')">
                    <div class="theme-option-icon">⚙️</div>
                    <div class="theme-option-label">Auto</div>
                </div>
            </div>
            <p class="help-text" style="margin-top:12px;text-align:center;">
                Auto verwendet die Systemeinstellung
            </p>
        </div>

        <div class="section-title">Benachrichtigungen</div>

        <div class="card">
            <h2>📧 E-Mail</h2>
            <div id="emailSettingsList"></div>
        </div>

        <div class="section-title">Daten</div>

        <div class="card">
            <h2>Offline-Daten</h2>
            <p class="help-text" style="margin-top:0;">
                Löscht alle lokal gespeicherten Daten (Aufträge, Anlagen, Einträge). Login-Daten bleiben erhalten.
            </p>
            <button type="button" class="btn btn-danger" id="btnClearCache" style="margin-bottom:8px;">
                Cache leeren
            </button>
            <p class="help-text" style="color:#d32f2f;display:none;" id="cacheCleared">
                ✅ Cache wurde geleert. Bitte PWA neu laden.
            </p>
        </div>

        <div class="card">
            <h2>Abmelden &amp; alles zurücksetzen</h2>
            <p class="help-text" style="margin-top:0;">
                Meldet dich auf dem Server ab und löscht <strong>alles</strong> auf diesem Gerät: Login-Daten, Offline-Daten, Cache, Einstellungen und App-Zwischenspeicher. Danach startet die PWA wie neu auf der Anmeldeseite. Nicht synchronisierte Änderungen gehen verloren.
            </p>
            <button type="button" class="btn btn-danger" id="btnHardReset">
                Abmelden &amp; alles zurücksetzen
            </button>
        </div>

    </div>

    <script src="db.js"></script>
    <script>
        const CONFIG = { apiBase: '<?php echo $apiBase; ?>' };
        let savedCredentials = null;
        let pwaToken = null;

        async function apiCall(route, options = {}) {
            const url = CONFIG.apiBase + '?route=' + encodeURIComponent(route);
            const headers = { 'Content-Type': 'application/json', ...options.headers };
            if (pwaToken) headers['X-PWA-Token'] = pwaToken;

            const response = await fetch(url, { headers, ...options });
            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                throw new Error(data.error || ('HTTP ' + response.status));
            }
            return data;
        }

        // Theme functions
        function setTheme(theme) {
            localStorage.setItem('pwa_theme', theme);

            let effectiveTheme = theme;
            if (theme === 'auto') {
                effectiveTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            }

            if (effectiveTheme === 'dark') {
                document.documentElement.setAttribute('data-theme', 'dark');
            } else {
                document.documentElement.removeAttribute('data-theme');
            }

            updateThemeUI(theme);
        }

        function updateThemeUI(activeTheme) {
            document.querySelectorAll('.theme-option').forEach(el => {
                el.classList.toggle('active', el.dataset.theme === activeTheme);
            });
        }

        function initTheme() {
            const stored = localStorage.getItem('pwa_theme') || 'auto';
            updateThemeUI(stored);

            // Listen for system theme changes
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
                const current = localStorage.getItem('pwa_theme');
                if (current === 'auto') {
                    setTheme('auto');
                }
            });
        }

        // Initialize
        // Generic toggle row builder — stored in localStorage, default true unless defaultVal=false
        function buildToggleRow(key, label, helpText, defaultVal = true) {
            const on = localStorage.getItem(key) === null ? defaultVal : localStorage.getItem(key) !== 'false';
            const row = document.createElement('div');
            row.style.cssText = 'display:flex;align-items:center;justify-content:space-between;gap:16px;padding:8px 0;border-top:1px solid var(--border-color, #eee);';
            row.innerHTML = `
                <div>
                    <div style="font-weight:500;font-size:14px;">${label}</div>
                    ${helpText ? `<div class="help-text" style="margin-top:2px;">${helpText}</div>` : ''}
                </div>
                <div data-key="${key}" data-on="${on ? '1' : '0'}"
                    style="position:relative;width:44px;height:24px;flex-shrink:0;cursor:pointer;"
                    onclick="toggleSetting(this)">
                    <div style="position:absolute;inset:0;border-radius:24px;background:${on ? 'var(--primary-color)' : '#ccc'};transition:.2s;" class="tog-track"></div>
                    <div style="position:absolute;top:3px;left:${on ? '23px' : '3px'};width:18px;height:18px;border-radius:50%;background:white;transition:.2s;" class="tog-thumb"></div>
                </div>`;
            return row;
        }

        function toggleSetting(el) {
            const key = el.dataset.key;
            const nowOn = el.dataset.on !== '1';
            el.dataset.on = nowOn ? '1' : '0';
            localStorage.setItem(key, nowOn ? 'true' : 'false');
            el.querySelector('.tog-track').style.background = nowOn ? 'var(--primary-color)' : '#ccc';
            el.querySelector('.tog-thumb').style.left = nowOn ? '23px' : '3px';
        }

        function initEmailSettings() {
            const list = document.getElementById('emailSettingsList');
            list.appendChild(buildToggleRow(
                'pwa_email_auto_open',
                'E-Mail nach Unterschrift',
                'Sendeformular automatisch öffnen',
                true
            ));
            list.appendChild(buildToggleRow(
                'pwa_email_show_body',
                'E-Mail Inhalt anzeigen',
                'Vorlage im Modal anzeigen und bearbeiten',
                false
            ));
        }

        document.addEventListener('DOMContentLoaded', async () => {
            await offlineDB.init();
            await loadStatus();
            initTheme();
            initEmailSettings();

            pwaToken = await offlineDB.getMeta('pwa_token');
            if (pwaToken) {
                initSignature();
                initPasswordChange();
                initTotp2fa();
                initCalendarSubscription();
            }

            document.getElementById('settingsForm').addEventListener('submit', handleSubmit);
        });

        async function loadStatus() {
            const statusEl = document.getElementById('statusContent');

            try {
                savedCredentials = await offlineDB.getMeta('credentials');

                if (savedCredentials && savedCredentials.username) {
                    const savedAt = new Date(savedCredentials.saved_at);
                    statusEl.innerHTML = `
                        <div style="display:flex;align-items:center;gap:10px;">
                            <span style="font-size:22px;">✅</span>
                            <div style="flex:1;min-width:0;">
                                <div style="font-weight:600;font-size:14px;">${savedCredentials.username}</div>
                                <div style="font-size:12px;color:var(--text-muted);">Gespeichert: ${savedAt.toLocaleDateString('de-DE')} ${savedAt.toLocaleTimeString('de-DE', {hour:'2-digit',minute:'2-digit'})}</div>
                            </div>
                            <button type="button" onclick="deleteCredentials()" style="padding:5px 10px;background:#f44336;color:white;border:none;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;flex-shrink:0;">Löschen</button>
                        </div>
                    `;

                    // Pre-fill username
                    document.getElementById('username').value = savedCredentials.username;
                } else {
                    statusEl.innerHTML = `
                        <div style="display:flex;align-items:center;gap:8px;color:var(--text-muted);">
                            <span>❌</span>
                            <span style="font-size:14px;">Keine Daten gespeichert</span>
                        </div>
                    `;
                }
            } catch (err) {
                console.error('Error loading status:', err);
                statusEl.innerHTML = `
                    <div class="status-icon">⚠️</div>
                    <p>Fehler beim Laden</p>
                `;
            }
        }

        async function handleSubmit(e) {
            e.preventDefault();

            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;
            const totp_code = document.getElementById('totp_code').value;
            const btn = document.getElementById('btnTest');
            const messageArea = document.getElementById('messageArea');

            btn.disabled = true;
            btn.textContent = 'Teste...';
            messageArea.innerHTML = '';

            try {
                const formData = new FormData();
                formData.append('test_login', '1');
                formData.append('username', username);
                formData.append('password', password);
                if (totp_code) {
                    formData.append('totp_code', totp_code);
                }

                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.status === 'ok') {
                    // Save token (not password) for future auto-login
                    if (result.pwa_token) {
                        await offlineDB.setMeta('pwa_token', result.pwa_token);
                        pwaToken = result.pwa_token;
                        initSignature();
                        initPasswordChange();
                        initCalendarSubscription();
                    }
                    // Keep username for display purposes only (no password)
                    await offlineDB.setMeta('credentials', {
                        username: username,
                        saved_at: Date.now()
                    });

                    // Save auth data
                    await offlineDB.setMeta('auth', {
                        id: result.user.id,
                        login: result.user.login,
                        name: result.user.name,
                        valid_until: (Date.now() / 1000) + (90 * 24 * 3600)
                    });

                    messageArea.innerHTML = `
                        <div class="message success">
                            ✅ Login erfolgreich! Daten wurden gespeichert.
                        </div>
                    `;

                    // Show trusted device info if available
                    if (result.trusted_device) {
                        showTrustedDeviceInfo(result.trusted_device);
                    } else {
                        document.getElementById('trustedDeviceCard').style.display = 'none';
                    }

                    // Clear password field for security
                    document.getElementById('password').value = '';
                    document.getElementById('totp_code').value = '';

                    // Reload status
                    await loadStatus();
                } else {
                    if (result.requires_2fa) {
                        // Show 2FA field
                        document.getElementById('totpGroup').style.display = 'block';
                        document.getElementById('totp_code').focus();
                        messageArea.innerHTML = `
                            <div class="message info">
                                🔐 2FA-Code erforderlich. Bitte Code eingeben.
                            </div>
                        `;
                    } else {
                        messageArea.innerHTML = `
                            <div class="message error">
                                ❌ ${result.message || 'Login fehlgeschlagen'}
                            </div>
                        `;
                    }
                }
            } catch (err) {
                console.error('Test error:', err);
                messageArea.innerHTML = `
                    <div class="message error">
                        ❌ Verbindungsfehler
                    </div>
                `;
            }

            btn.disabled = false;
            btn.textContent = 'Testen & Speichern';
        }

        async function deleteCredentials() {
            if (!confirm('Login-Daten wirklich löschen?')) {
                return;
            }

            try {
                await offlineDB.setMeta('credentials', null);
                await offlineDB.setMeta('auth', null);

                document.getElementById('messageArea').innerHTML = `
                    <div class="message success">
                        ✅ Daten gelöscht
                    </div>
                `;

                document.getElementById('username').value = '';
                document.getElementById('password').value = '';
                document.getElementById('trustedDeviceCard').style.display = 'none';

                await loadStatus();
            } catch (err) {
                console.error('Delete error:', err);
            }
        }

        document.getElementById('btnClearCache').addEventListener('click', async () => {
            if (!confirm('Alle Offline-Daten löschen? Login-Daten bleiben erhalten.')) return;

            const btn = document.getElementById('btnClearCache');
            btn.disabled = true;
            btn.textContent = 'Lösche...';

            try {
                // 1. Clear service worker caches
                if ('caches' in window) {
                    const keys = await caches.keys();
                    await Promise.all(keys.map(k => caches.delete(k)));
                }

                // 2. Clear IndexedDB data stores (keep meta = credentials/auth/token)
                await offlineDB.init();
                const storesToClear = ['interventions', 'equipment', 'details', 'sync_queue',
                                       'checklist_results', 'defect_materials', 'pending_uploads'];
                for (const store of storesToClear) {
                    try {
                        const tx = offlineDB.db.transaction(store, 'readwrite');
                        await new Promise((res, rej) => {
                            const req = tx.objectStore(store).clear();
                            req.onsuccess = res;
                            req.onerror = rej;
                        });
                    } catch (e) { /* store might not exist */ }
                }

                document.getElementById('cacheCleared').style.display = 'block';
                btn.textContent = 'Cache leeren';
                btn.disabled = false;
            } catch (err) {
                console.error('Clear cache error:', err);
                btn.textContent = 'Fehler – bitte erneut versuchen';
                btn.disabled = false;
            }
        });

        document.getElementById('btnHardReset').addEventListener('click', async () => {
            const btn = document.getElementById('btnHardReset');

            // Count changes that were not synced yet - they would be lost
            let pending = 0;
            try {
                await offlineDB.init();
                for (const store of ['sync_queue', 'pending_uploads']) {
                    pending += await new Promise(resolve => {
                        try {
                            const req = offlineDB.db.transaction(store).objectStore(store).count();
                            req.onsuccess = () => resolve(req.result || 0);
                            req.onerror = () => resolve(0);
                        } catch (e) { resolve(0); }
                    });
                }
            } catch (e) { /* DB unavailable - nothing to lose */ }

            let message = 'Wirklich abmelden und ALLES auf diesem Gerät löschen?\n\nDanach musst du dich neu anmelden.';
            if (pending > 0) {
                message = '⚠️ ' + pending + ' Änderung(en) sind noch NICHT synchronisiert und gehen verloren!\n\n' + message;
            }
            if (!confirm(message)) return;

            btn.disabled = true;
            btn.textContent = 'Setze zurück...';

            // 1. Server: revoke this device's token and destroy the session
            let token = null;
            try { token = await offlineDB.getMeta('pwa_token'); } catch (e) { /* ignore */ }
            let serverDone = false;
            try {
                const res = await fetch(CONFIG.apiBase + '?route=pwa-logout', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: token ? { 'X-PWA-Token': token } : {}
                });
                serverDone = res.ok;
            } catch (e) { /* offline */ }
            if (!serverDone && !confirm('Der Server ist nicht erreichbar, die Server-Sitzung bleibt deshalb bestehen. Trotzdem alles auf diesem Gerät löschen?')) {
                btn.disabled = false;
                btn.textContent = 'Abmelden & alles zurücksetzen';
                return;
            }

            try {
                // 2. Service workers
                if ('serviceWorker' in navigator) {
                    const regs = await navigator.serviceWorker.getRegistrations();
                    await Promise.all(regs.map(r => r.unregister()));
                }
                // 3. Cache storage
                if ('caches' in window) {
                    const keys = await caches.keys();
                    await Promise.all(keys.map(k => caches.delete(k)));
                }
                // 4. All IndexedDB databases
                try { if (offlineDB.db) offlineDB.db.close(); } catch (e) { /* ignore */ }
                let dbNames = [DB_NAME];
                if (indexedDB.databases) {
                    dbNames = Array.from(new Set(dbNames.concat((await indexedDB.databases()).map(d => d.name).filter(Boolean))));
                }
                await Promise.all(dbNames.map(name => new Promise(resolve => {
                    const req = indexedDB.deleteDatabase(name);
                    req.onsuccess = req.onerror = req.onblocked = () => resolve();
                })));
                // 5. Web storage and cookies readable by the page
                try { localStorage.clear(); } catch (e) { /* ignore */ }
                try { sessionStorage.clear(); } catch (e) { /* ignore */ }
                document.cookie.split(';').forEach(c => {
                    const name = c.split('=')[0].trim();
                    if (name) document.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/';
                });
            } catch (err) {
                console.error('Hard reset error:', err);
            }

            // 6. Start over on the login view
            window.location.replace('index.php?_=' + Date.now());
        });

        function showTrustedDeviceInfo(trusted) {
            const card = document.getElementById('trustedDeviceCard');
            const content = document.getElementById('trustedDeviceContent');

            const days = trusted.days_remaining;
            const device = trusted.device_name || 'Dieses Gerät';
            const until = new Date(trusted.trusted_until).toLocaleDateString('de-DE');

            let bgColor = '#e8f5e9';
            let textColor = '#2e7d32';

            if (days <= 3) {
                bgColor = '#fff3e0';
                textColor = '#e65100';
            }

            content.innerHTML = `
                <div style="padding:8px;">
                    <p style="margin:0 0 8px 0;"><strong>${device}</strong></p>
                    <p style="margin:0;color:${textColor};">
                        2FA nicht erforderlich für <strong>${days} Tag${days !== 1 ? 'e' : ''}</strong>
                    </p>
                    <p style="margin:8px 0 0 0;font-size:12px;color:#999;">
                        Gültig bis: ${until}
                    </p>
                </div>
            `;

            card.style.display = 'block';
        }

        // ─── Technician Signature ───────────────────────────────────────────
        let sigCtx = null;
        let sigDrawing = false;
        let sigLastX = 0, sigLastY = 0;
        let sigHasStrokes = false;

        function setupSignatureCanvas() {
            const canvas = document.getElementById('signaturePad');
            const wrap = document.getElementById('sigPadWrap');
            const rect = wrap.getBoundingClientRect();
            const dpr = window.devicePixelRatio || 1;
            canvas.width = rect.width * dpr;
            canvas.height = 150 * dpr;
            sigCtx = canvas.getContext('2d');
            sigCtx.scale(dpr, dpr);
            sigCtx.strokeStyle = '#000';
            sigCtx.lineWidth = 2;
            sigCtx.lineCap = 'round';
            sigCtx.lineJoin = 'round';

            const getPos = (e) => {
                const r = canvas.getBoundingClientRect();
                if (e.touches && e.touches.length) {
                    return { x: e.touches[0].clientX - r.left, y: e.touches[0].clientY - r.top };
                }
                return { x: e.clientX - r.left, y: e.clientY - r.top };
            };

            const start = (e) => {
                e.preventDefault();
                sigDrawing = true;
                const p = getPos(e);
                sigLastX = p.x;
                sigLastY = p.y;
            };
            const move = (e) => {
                if (!sigDrawing) return;
                e.preventDefault();
                const p = getPos(e);
                sigCtx.beginPath();
                sigCtx.moveTo(sigLastX, sigLastY);
                sigCtx.lineTo(p.x, p.y);
                sigCtx.stroke();
                sigLastX = p.x;
                sigLastY = p.y;
                sigHasStrokes = true;
            };
            const end = () => { sigDrawing = false; };

            canvas.addEventListener('mousedown', start);
            canvas.addEventListener('mousemove', move);
            canvas.addEventListener('mouseup', end);
            canvas.addEventListener('mouseout', end);
            canvas.addEventListener('touchstart', start, { passive: false });
            canvas.addEventListener('touchmove', move, { passive: false });
            canvas.addEventListener('touchend', end);
        }

        function clearSignaturePad() {
            const canvas = document.getElementById('signaturePad');
            if (sigCtx) sigCtx.clearRect(0, 0, canvas.width, canvas.height);
            sigHasStrokes = false;
        }

        async function initSignature() {
            const card = document.getElementById('signatureCard');
            card.style.display = 'block';
            setupSignatureCanvas();

            try {
                const data = await apiCall('technician-signature');
                document.getElementById('technician_name').value = data.technician_name || '';

                const existingWrap = document.getElementById('sigExisting');
                const existingImg = document.getElementById('sigExistingImg');
                const deleteBtn = document.getElementById('btnSigDelete');
                if (data.has_signature && data.signature_data_url) {
                    existingImg.src = data.signature_data_url;
                    existingWrap.style.display = 'block';
                    deleteBtn.style.display = 'block';
                    document.getElementById('sigStatus').textContent = 'Zum Ändern unten neu zeichnen und speichern.';
                } else {
                    existingWrap.style.display = 'none';
                    deleteBtn.style.display = 'none';
                    document.getElementById('sigStatus').textContent = 'Noch keine Unterschrift hinterlegt – unten zeichnen.';
                }
            } catch (err) {
                console.error('Signature load error:', err);
                document.getElementById('sigStatus').textContent = 'Fehler beim Laden.';
            }
        }

        document.getElementById('btnSigClear').addEventListener('click', clearSignaturePad);

        document.getElementById('btnSigSave').addEventListener('click', async () => {
            const btn = document.getElementById('btnSigSave');
            const name = document.getElementById('technician_name').value.trim();
            const canvas = document.getElementById('signaturePad');

            btn.disabled = true;
            btn.textContent = 'Speichere...';

            try {
                const signatureData = sigHasStrokes ? canvas.toDataURL('image/png') : '';
                await apiCall('technician-signature', {
                    method: 'POST',
                    body: JSON.stringify({ technician_name: name, signature_data: signatureData })
                });
                document.getElementById('sigStatus').textContent = '✅ Gespeichert.';
                await initSignature();
            } catch (err) {
                console.error('Signature save error:', err);
                document.getElementById('sigStatus').textContent = '❌ Fehler beim Speichern.';
            }

            btn.disabled = false;
            btn.textContent = 'Speichern';
        });

        document.getElementById('btnSigDelete').addEventListener('click', async () => {
            if (!confirm('Unterschrift wirklich entfernen?')) return;
            try {
                await apiCall('technician-signature', { method: 'DELETE' });
                await initSignature();
            } catch (err) {
                console.error('Signature delete error:', err);
            }
        });

        // ─── Password change ────────────────────────────────────────────────
        async function initPasswordChange() {
            document.getElementById('passwordCard').style.display = 'block';
            try {
                const policy = await apiCall('change-password');
                if (policy.hint) document.getElementById('pwPolicyHint').textContent = policy.hint;
                if (policy.min_length) {
                    document.getElementById('pw_new').minLength = policy.min_length;
                    document.getElementById('pw_new2').minLength = policy.min_length;
                }
            } catch (e) { /* offline: server validates anyway */ }
        }

        document.getElementById('passwordForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const status = document.getElementById('pwStatus');
            const btn = document.getElementById('btnPwChange');
            const current = document.getElementById('pw_current').value;
            const next = document.getElementById('pw_new').value;
            const next2 = document.getElementById('pw_new2').value;

            if (next !== next2) {
                status.textContent = '❌ Die neuen Passwörter stimmen nicht überein.';
                return;
            }

            btn.disabled = true;
            btn.textContent = 'Ändere...';
            status.textContent = '';

            try {
                await apiCall('change-password', {
                    method: 'POST',
                    body: JSON.stringify({ current_password: current, new_password: next })
                });
                status.textContent = '✅ Passwort wurde geändert.';
                document.getElementById('passwordForm').reset();
            } catch (err) {
                status.textContent = '❌ ' + err.message;
            }

            btn.disabled = false;
            btn.textContent = 'Passwort ändern';
        });

        // ─── Two-factor authentication (only if the totp2fa module is active) ──
        function escHtml(str) {
            return String(str).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
        }

        async function initTotp2fa() {
            const card = document.getElementById('totpCard');
            try {
                const info = await apiCall('totp2fa');
                if (!info.available) { card.style.display = 'none'; return; }
                card.style.display = 'block';
                renderTotp(info.enabled);
            } catch (e) {
                card.style.display = 'none';
            }
        }

        function renderTotp(enabled) {
            const el = document.getElementById('totpContent');
            if (enabled) {
                el.innerHTML = `
                    <p class="help-text" style="margin-top:0;">✅ 2FA ist aktiviert. Beim Anmelden wird zusätzlich ein Code aus Ihrer Authenticator-App abgefragt.</p>
                    <button type="button" class="btn btn-danger" id="btnTotpDisableStart">2FA deaktivieren</button>
                    <div id="totpDisableBox" style="display:none;">
                        <div class="form-group">
                            <label class="form-label">Passwort</label>
                            <input type="password" id="totp_dis_pw" class="form-input" autocomplete="current-password">
                        </div>
                        <div class="form-group">
                            <label class="form-label">2FA-Code oder Backup-Code</label>
                            <input type="text" id="totp_dis_code" class="form-input" inputmode="numeric" autocomplete="one-time-code" maxlength="10" style="text-align:center;letter-spacing:4px;">
                        </div>
                        <button type="button" class="btn btn-danger" id="btnTotpDisable">Jetzt deaktivieren</button>
                    </div>
                    <p class="help-text" id="totpStatus"></p>`;
                document.getElementById('btnTotpDisableStart').onclick = () => {
                    document.getElementById('btnTotpDisableStart').style.display = 'none';
                    document.getElementById('totpDisableBox').style.display = 'block';
                };
                document.getElementById('btnTotpDisable').onclick = totpDisable;
            } else {
                el.innerHTML = `
                    <p class="help-text" style="margin-top:0;">Schützt Ihren Zugang zusätzlich mit einem Code aus einer Authenticator-App (z. B. Google Authenticator, Microsoft Authenticator, Aegis).</p>
                    <button type="button" class="btn btn-primary" id="btnTotpStart">2FA einrichten</button>
                    <p class="help-text" id="totpStatus"></p>`;
                document.getElementById('btnTotpStart').onclick = totpStart;
            }
        }

        async function totpStart() {
            const status = document.getElementById('totpStatus');
            const btn = document.getElementById('btnTotpStart');
            btn.disabled = true;
            try {
                const d = await apiCall('totp2fa', { method: 'POST', body: JSON.stringify({ action: 'start' }) });
                document.getElementById('totpContent').innerHTML = `
                    <p class="help-text" style="margin-top:0;"><b>1.</b> Authenticator-App öffnen und das Konto hinzufügen:</p>
                    <a class="btn btn-primary" style="text-decoration:none;text-align:center;" href="${escHtml(d.uri)}">In Authenticator-App öffnen</a>
                    <p class="help-text">Funktioniert das nicht (oder richten Sie die App auf einem anderen Gerät ein), den QR-Code scannen oder das Geheimnis manuell eingeben:</p>
                    <div style="text-align:center;background:#fff;padding:8px;border-radius:8px;max-width:240px;margin:0 auto 10px;">${d.qr_svg || ''}</div>
                    <div style="text-align:center;font-family:monospace;font-size:15px;word-break:break-all;margin-bottom:12px;user-select:all;">${escHtml(d.secret)}</div>
                    <p class="help-text"><b>2.</b> Den 6-stelligen Code aus der App eingeben:</p>
                    <div class="form-group">
                        <input type="text" id="totp_verify_code" class="form-input" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="000000" style="text-align:center;letter-spacing:4px;">
                    </div>
                    <button type="button" class="btn btn-success" id="btnTotpVerify">Bestätigen und aktivieren</button>
                    <button type="button" class="btn btn-secondary" id="btnTotpCancel">Abbrechen</button>
                    <p class="help-text" id="totpStatus"></p>`;
                document.getElementById('btnTotpVerify').onclick = totpVerify;
                document.getElementById('btnTotpCancel').onclick = () => renderTotp(false);
            } catch (err) {
                status.textContent = '❌ ' + err.message;
                btn.disabled = false;
            }
        }

        async function totpVerify() {
            const status = document.getElementById('totpStatus');
            const btn = document.getElementById('btnTotpVerify');
            const code = document.getElementById('totp_verify_code').value.trim();
            btn.disabled = true;
            status.textContent = '';
            try {
                const d = await apiCall('totp2fa', { method: 'POST', body: JSON.stringify({ action: 'verify', code }) });
                document.getElementById('totpContent').innerHTML = `
                    <p class="help-text" style="margin-top:0;">✅ 2FA ist jetzt aktiviert.</p>
                    <p class="help-text"><b>Backup-Codes</b> – jeder Code gilt einmalig, falls das Handy nicht verfügbar ist. Jetzt sicher notieren, sie werden nicht erneut angezeigt:</p>
                    <div style="text-align:center;font-family:monospace;font-size:16px;line-height:1.8;user-select:all;margin-bottom:12px;">${d.backup_codes.map(escHtml).join('<br>')}</div>
                    <button type="button" class="btn btn-primary" id="btnTotpDone">Codes gesichert – fertig</button>`;
                document.getElementById('btnTotpDone').onclick = () => renderTotp(true);
            } catch (err) {
                status.textContent = '❌ ' + err.message;
                btn.disabled = false;
            }
        }

        async function totpDisable() {
            const status = document.getElementById('totpStatus');
            const btn = document.getElementById('btnTotpDisable');
            btn.disabled = true;
            status.textContent = '';
            try {
                await apiCall('totp2fa', { method: 'POST', body: JSON.stringify({
                    action: 'disable',
                    password: document.getElementById('totp_dis_pw').value,
                    code: document.getElementById('totp_dis_code').value.trim()
                }) });
                renderTotp(false);
                document.getElementById('totpStatus').textContent = '✅ 2FA wurde deaktiviert.';
            } catch (err) {
                status.textContent = '❌ ' + err.message;
                btn.disabled = false;
            }
        }

        // ─── Calendar Subscription ──────────────────────────────────────────
        async function initCalendarSubscription() {
            const card = document.getElementById('calendarCard');
            card.style.display = 'block';
            const content = document.getElementById('calendarContent');

            try {
                const data = await apiCall('calendar-subscription');
                content.innerHTML = `
                    <a href="${data.webcal_url}" class="btn btn-primary" style="text-decoration:none;text-align:center;">📅 Im Kalender abonnieren</a>
                    <p class="help-text" style="word-break:break-all;">${data.url}</p>
                    <button type="button" class="btn btn-secondary" id="btnCopyCalUrl">Link kopieren</button>
                `;
                document.getElementById('btnCopyCalUrl').addEventListener('click', async () => {
                    const copyBtn = document.getElementById('btnCopyCalUrl');
                    try {
                        await navigator.clipboard.writeText(data.url);
                        copyBtn.textContent = '✅ Kopiert';
                        setTimeout(() => { copyBtn.textContent = 'Link kopieren'; }, 2000);
                    } catch (e) { /* clipboard may be unavailable */ }
                });
            } catch (err) {
                console.error('Calendar subscription load error:', err);
                content.innerHTML = '<p class="help-text">Fehler beim Laden.</p>';
            }
        }
    </script>
</body>
</html>
