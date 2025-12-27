<?php
/**
 * PWA Entry Point - Serviceberichte Offline
 */

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

// Check authentication
if (!$user->id) {
    header('Location: ' . DOL_URL_ROOT . '/user/logout.php');
    exit;
}

$title = 'Serviceberichte';
$apiBase = dol_buildpath('/custom/equipmentmanager/api/', 1);
$jSignaturePath = DOL_URL_ROOT . '/includes/jquery/plugins/jSignature/jSignature.min.js';
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

    <link rel="manifest" href="manifest.json.php">
    <link rel="apple-touch-icon" href="../img/object_equipment.png">

    <style>
        * {
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            padding: 0;
            background: #f5f5f5;
            color: #333;
            min-height: 100vh;
        }

        /* Header */
        .header {
            background: #263c5c;
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
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 12px;
            overflow: hidden;
        }

        .card-header {
            padding: 12px 16px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-title {
            font-weight: 600;
            font-size: 16px;
            margin: 0;
        }

        .card-subtitle {
            font-size: 13px;
            color: #666;
            margin-top: 4px;
        }

        .card-body {
            padding: 12px 16px;
        }

        .card-clickable {
            cursor: pointer;
        }

        .card-clickable:active {
            background: #f9f9f9;
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
            color: #666;
            margin-bottom: 6px;
        }

        .form-input, .form-textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
            font-family: inherit;
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
            border: 2px dashed #ccc;
            border-radius: 8px;
            background: white;
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
            background: white;
            border-top: 1px solid #ddd;
            display: flex;
            padding: 8px 0;
            padding-bottom: max(8px, env(safe-area-inset-bottom));
        }

        .nav-item {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 8px;
            color: #666;
            text-decoration: none;
            font-size: 11px;
            cursor: pointer;
            border: none;
            background: none;
        }

        .nav-item.active {
            color: #263c5c;
        }

        .nav-icon {
            font-size: 20px;
            margin-bottom: 4px;
        }

        /* Loading */
        .loading {
            text-align: center;
            padding: 40px;
            color: #666;
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
            color: #666;
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
            border-bottom: 1px solid #eee;
        }

        .equipment-item:last-child {
            border-bottom: none;
        }

        .equipment-icon {
            width: 40px;
            height: 40px;
            background: #e3f2fd;
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
            color: #666;
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
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <button class="header-btn" id="btnBack" style="display:none;">&#8592;</button>
        <h1 id="headerTitle"><?php echo $title; ?></h1>
        <span class="sync-status" id="syncStatus">Offline</span>
        <button class="header-btn" id="btnSync" title="Synchronisieren">&#8635;</button>
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

    <!-- Detail Editor View -->
    <div class="view" id="viewDetail">
        <div class="content">
            <form id="detailForm">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title" id="detailEquipmentRef">Equipment</h3>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label class="form-label">Arbeitsdatum</label>
                            <input type="date" class="form-input" id="workDate">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Arbeitszeit (Minuten)</label>
                            <input type="number" class="form-input" id="workDuration" min="0" step="15">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Durchgeführte Arbeiten</label>
                            <textarea class="form-textarea" id="workDone" rows="4"></textarea>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Festgestellte Mängel</label>
                            <textarea class="form-textarea" id="issuesFound" rows="3"></textarea>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Empfehlungen</label>
                            <textarea class="form-textarea" id="recommendations" rows="3"></textarea>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Notizen</label>
                            <textarea class="form-textarea" id="notes" rows="2"></textarea>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-block">Speichern</button>
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
        <button class="nav-item" data-view="viewSignature" id="navSignature" style="display:none;">
            <span class="nav-icon">✍️</span>
            <span>Unterschrift</span>
        </button>
    </div>

    <!-- Toast -->
    <div class="toast" id="toast"></div>

    <!-- Scripts -->
    <script src="<?php echo DOL_URL_ROOT; ?>/includes/jquery/js/jquery.min.js"></script>
    <script src="<?php echo $jSignaturePath; ?>"></script>
    <script>
        // Configuration
        const CONFIG = {
            apiBase: '<?php echo $apiBase; ?>',
            user: '<?php echo $user->login; ?>',
            userId: <?php echo (int)$user->id; ?>
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
