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
        echo json_encode(['status' => 'error', 'message' => 'Benutzer nicht gefunden']);
        exit;
    }

    // Check 2FA if enabled
    $requires_2fa = false;
    $totp2fa_verified = false;

    if (!empty($conf->totp2fa->enabled)) {
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
    if (!empty($conf->totp2fa->enabled)) {
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

    // Success!
    echo json_encode([
        'status' => 'ok',
        'message' => 'Login erfolgreich',
        'user' => [
            'id' => (int)$tmpuser->id,
            'login' => $tmpuser->login,
            'name' => $tmpuser->getFullName($langs)
        ],
        'trusted_device' => $trustedInfo
    ]);
    exit;
}

$title = 'PWA Einstellungen';

// Get trusted device info
$trustedDeviceInfo = null;
if (!empty($conf->totp2fa->enabled)) {
    // We need to get any logged-in user's trusted device - check saved credentials
    // Since this is a no-login page, we can only show this after login test is successful
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#263c5c">
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
            --header-bg: #263c5c;
            --input-bg: #ffffff;
            --input-border: #dddddd;
        }
        [data-theme="dark"] {
            --bg-primary: #1a1a1a;
            --bg-card: #2d2d2d;
            --text-primary: #e0e0e0;
            --text-secondary: #b0b0b0;
            --text-muted: #808080;
            --border-color: #404040;
            --header-bg: #1e2d3d;
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
        .header {
            background: var(--header-bg);
            color: white;
            padding: 16px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 20px;
            font-weight: 500;
        }
        .content {
            padding: 16px;
            max-width: 400px;
            margin: 0 auto;
        }
        .card {
            background: var(--bg-card);
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 16px;
            transition: background-color 0.3s;
        }
        .card h2 {
            margin: 0 0 16px 0;
            font-size: 18px;
            color: var(--text-primary);
        }
        .form-group {
            margin-bottom: 16px;
        }
        .form-label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            font-size: 14px;
        }
        .form-input {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--input-border);
            border-radius: 8px;
            font-size: 16px;
            font-family: inherit;
            background: var(--input-bg);
            color: var(--text-primary);
            transition: background-color 0.3s, border-color 0.3s;
        }
        .form-input:focus {
            outline: none;
            border-color: #263c5c;
        }
        .btn {
            display: block;
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            margin-bottom: 10px;
        }
        .btn-primary {
            background: #263c5c;
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
            text-align: center;
            padding: 16px;
            color: var(--text-secondary);
            font-size: 14px;
        }
        .status-icon {
            font-size: 48px;
            margin-bottom: 8px;
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
            padding: 12px 8px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            background: var(--bg-card);
            cursor: pointer;
            text-align: center;
            transition: all 0.2s;
        }
        .theme-option:hover {
            border-color: #263c5c;
        }
        .theme-option.active {
            border-color: #263c5c;
            background: rgba(38, 60, 92, 0.1);
        }
        .theme-option-icon {
            font-size: 24px;
            margin-bottom: 4px;
        }
        .theme-option-label {
            font-size: 12px;
            font-weight: 500;
        }
        .back-link {
            display: block;
            text-align: center;
            color: #263c5c;
            text-decoration: none;
            padding: 12px;
            font-weight: 500;
        }
        .help-text {
            font-size: 13px;
            color: #666;
            margin-top: 8px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>PWA Einstellungen</h1>
    </div>

    <div class="content">
        <div id="messageArea"></div>

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

        <a href="index.php" class="back-link">← Zurück zur PWA</a>
    </div>

    <script src="db.js"></script>
    <script>
        let savedCredentials = null;

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
        document.addEventListener('DOMContentLoaded', async () => {
            await offlineDB.init();
            await loadStatus();
            initTheme();

            document.getElementById('settingsForm').addEventListener('submit', handleSubmit);
        });

        async function loadStatus() {
            const statusEl = document.getElementById('statusContent');

            try {
                savedCredentials = await offlineDB.getMeta('credentials');

                if (savedCredentials && savedCredentials.username) {
                    const savedAt = new Date(savedCredentials.saved_at);
                    statusEl.innerHTML = `
                        <div class="status-icon">✅</div>
                        <p><strong>${savedCredentials.username}</strong></p>
                        <p style="font-size:12px;color:#999;">
                            Gespeichert: ${savedAt.toLocaleDateString('de-DE')} ${savedAt.toLocaleTimeString('de-DE')}
                        </p>
                        <button type="button" class="btn btn-danger" onclick="deleteCredentials()" style="margin-top:12px;">
                            Löschen
                        </button>
                    `;

                    // Pre-fill username
                    document.getElementById('username').value = savedCredentials.username;
                } else {
                    statusEl.innerHTML = `
                        <div class="status-icon">❌</div>
                        <p>Keine Daten gespeichert</p>
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
                    // Save credentials
                    await offlineDB.setMeta('credentials', {
                        username: username,
                        password: password,
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
    </script>
</body>
</html>
