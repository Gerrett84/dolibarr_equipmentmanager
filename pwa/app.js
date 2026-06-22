/**
 * Main PWA Application
 */

class ServiceReportApp {
    constructor() {
        this.currentView = 'viewInterventions';
        this.currentIntervention = null;
        this.currentEquipment = null;
        this.currentEntry = null; // v1.7 - current entry being edited
        this.currentEntries = []; // v1.7 - all entries for current equipment
        this.isOnline = false; // Always start pessimistic — checkConnectivity() confirms
        this.signatureInstance = null;
        this.user = null;
        this.pwaToken = null; // v1.8 - PWA authentication token
        this.currentChecklist = null; // v2.0 - checklist data for maintenance equipment
        this.interventionFilter = 'open'; // v4.1 - current filter: 'open', 'released', 'signed'
        this.signedTimeRange = 30; // v4.1 - time range in days for signed orders (0 = all)
        this.allInterventions = []; // v4.1 - cache all interventions for filtering
        this.defectMaterialMode = 'product'; // v4.3 - 'product' or 'freetext'

        this.equipmentTypeLabels = {
            'door_swing':      'Drehtür',
            'door_sliding':    'Schiebetür',
            'fire_door':       'Brandschutztür',
            'fire_door_fsa':   'Brandschutztür (FSA)',
            'fire_gate':       'Brandschutztor',
            'door_closer':     'Türschließer',
            'hold_open':       'Feststellanlage',
            'rws':             'RWS',
            'rwa':             'RWA',
            'gate_swing':      'Schwenkflügeltor',
            'gate_sliding':    'Schiebetor',
            'gate_sectional':  'Sektionaltor',
            'gate_upandover':  'Schwingtor',
            'other':           'Sonstige'
        };

        this.init();
    }

    async init() {
        // Initialize IndexedDB
        try {
            await offlineDB.init();
            // console.log('IndexedDB initialized');
        } catch (err) {
            console.error('Failed to init IndexedDB:', err);
            this.showToast('Offline-Speicher konnte nicht initialisiert werden');
        }

        // Handle authentication
        const authOk = await this.checkAuth();
        if (!authOk) {
            return; // Auth check shows error message
        }

        // Setup event listeners
        this.setupEventListeners();

        // Update online status
        this.updateOnlineStatus();

        // Load initial data from cache immediately (fast startup)
        await this.loadInterventions();

        // Check connectivity in background — sets isOnline, triggers sync+prefetch if online
        this.checkConnectivity(true);

        // Show pending sync badge if there are unsynced items
        this.updateSyncBadge();
    }

    async checkAuth() {
        // Load saved PWA token into memory on every startup
        const savedToken = await offlineDB.getMeta('pwa_token');
        if (savedToken) {
            this.pwaToken = savedToken;
        }

        // If server says we're authenticated, cache the auth data
        if (CONFIG.isAuthenticated && CONFIG.authData) {
            this.user = CONFIG.authData;
            await offlineDB.setMeta('auth', CONFIG.authData);

            // Show trusted device banner if available
            this.showTrustedDeviceBanner();

            return true;
        }

        // Not authenticated on server - try auto-login via saved PWA token
        if (this.isOnline && this.pwaToken) {
            const loginResult = await this.tryAutoLogin(null, null);
            if (loginResult) {
                this.showToast('Automatisch angemeldet');
                return true;
            }
            console.warn('Auto-login via token failed');
        }

        // Fallback to cached auth (for offline mode or when auto-login failed)
        const cachedAuth = await offlineDB.getMeta('auth');

        // Offline mode - use cached auth if available (even if expired, allow offline access)
        if (!this.isOnline) {
            if (cachedAuth) {
                this.user = cachedAuth;
                this.showToast('Offline-Modus: ' + cachedAuth.name);
                return true;
            } else if (this.pwaToken) {
                // Have token but no cached auth - allow limited offline access
                const savedCredentials = await offlineDB.getMeta('credentials');
                this.user = { name: savedCredentials?.username || 'Offline', offline: true };
                this.showToast('Offline-Modus (begrenzt)');
                return true;
            } else {
                this.showAuthError('Offline - Keine gespeicherte Anmeldung vorhanden.');
                return false;
            }
        }

        // Online but not authenticated and auto-login failed
        const savedCredentials = await offlineDB.getMeta('credentials');
        this.showLoginForm(savedCredentials);
        return false;
    }

    // Show login form - with optional pre-filled credentials
    showLoginForm(savedCredentials = null) {
        document.getElementById('interventionsLoading').style.display = 'none';

        const usernameValue = savedCredentials?.username || '';
        const passwordValue = '';

        document.getElementById('interventionsList').innerHTML = `
            <div class="login-form" style="padding: 20px;">
                <div style="text-align:center;margin-bottom:20px;">
                    <div style="font-size:48px;">🔐</div>
                    <h3 style="margin:10px 0;">${hasCredentials ? 'Sitzung abgelaufen' : 'Anmeldung erforderlich'}</h3>
                    <p style="color:#666;font-size:14px;">
                        ${hasCredentials ? 'Bitte erneut anmelden oder Passwort prüfen.' : 'Bitte speichern Sie Ihre Login-Daten in den Einstellungen.'}
                    </p>
                </div>

                ${!hasCredentials ? `
                <a href="settings.php" class="btn btn-primary" style="display:block;text-align:center;text-decoration:none;padding:14px;font-size:16px;border-radius:8px;background:#1a3f6e;color:white;margin-bottom:20px;">
                    ⚙️ Einstellungen öffnen
                </a>
                ` : ''}

                <form id="pwaLoginForm">
                    <div style="margin-bottom:12px;">
                        <input type="text" id="loginUsername" placeholder="Benutzername" required
                            value="${usernameValue}"
                            style="width:100%;padding:12px;border:1px solid #ddd;border-radius:8px;font-size:16px;">
                    </div>
                    <div style="margin-bottom:12px;">
                        <input type="password" id="loginPassword" placeholder="Passwort" required
                            value="${passwordValue}"
                            style="width:100%;padding:12px;border:1px solid #ddd;border-radius:8px;font-size:16px;">
                    </div>
                    <div style="margin-bottom:12px;">
                        <input type="text" id="login2faCode" placeholder="2FA-Code (falls aktiviert)"
                            style="width:100%;padding:12px;border:1px solid #ddd;border-radius:8px;font-size:16px;text-align:center;letter-spacing:4px;"
                            maxlength="10" inputmode="numeric">
                    </div>
                    <div style="margin-bottom:12px;">
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:14px;">
                            <input type="checkbox" id="loginRemember" checked style="width:18px;height:18px;">
                            <span>Daten speichern (90 Tage)</span>
                        </label>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block" style="padding:12px;font-size:15px;background:#4caf50;border:none;border-radius:8px;color:white;width:100%;cursor:pointer;">
                        ${hasCredentials ? 'Erneut anmelden' : 'Anmelden'}
                    </button>
                    <div id="loginError" style="color:#d32f2f;text-align:center;margin-top:12px;display:none;"></div>
                </form>
            </div>
        `;

        document.getElementById('pwaLoginForm').addEventListener('submit', (e) => {
            e.preventDefault();
            this.handleLogin();
        });
    }

    // Handle login form submission
    async handleLogin() {
        const username = document.getElementById('loginUsername').value;
        const password = document.getElementById('loginPassword').value;
        const totpCode = document.getElementById('login2faCode')?.value || '';
        const remember = document.getElementById('loginRemember').checked;
        const errorEl = document.getElementById('loginError');

        errorEl.style.display = 'none';

        try {
            const formData = new FormData();
            formData.append('pwa_autologin', '1');
            formData.append('username', username);
            formData.append('password', password);
            if (totpCode) {
                formData.append('totp_code', totpCode);
            }

            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });

            if (response.ok) {
                const result = await response.json();
                if (result.status === 'ok') {
                    // Save token (not password) and username for future auto-login
                    if (remember) {
                        if (result.pwa_token) {
                            await offlineDB.setMeta('pwa_token', result.pwa_token);
                            this.pwaToken = result.pwa_token;
                        }
                        await offlineDB.setMeta('credentials', {
                            username: username,
                            saved_at: Date.now()
                        });
                    }

                    // Save auth data
                    this.user = {
                        id: result.user.id,
                        login: result.user.login,
                        name: result.user.name,
                        valid_until: (Date.now() / 1000) + (90 * 24 * 3600)
                    };
                    await offlineDB.setMeta('auth', this.user);

                    // Success! Load interventions directly (no reload needed)
                    this.showToast('Anmeldung erfolgreich');
                    await this.loadInterventions();
                    return;
                }
            }

            // Login failed - check for 2FA requirement
            try {
                const result = await response.json();
                if (result.requires_2fa) {
                    errorEl.textContent = '2FA-Code erforderlich. Bitte Code eingeben.';
                    // Highlight 2FA field
                    const tfaInput = document.getElementById('login2faCode');
                    if (tfaInput) {
                        tfaInput.style.borderColor = '#d32f2f';
                        tfaInput.focus();
                    }
                } else {
                    errorEl.textContent = result.message || 'Benutzername oder Passwort falsch';
                }
            } catch (e) {
                errorEl.textContent = 'Benutzername oder Passwort falsch';
            }
            errorEl.style.display = 'block';
        } catch (err) {
            console.error('Login error:', err);
            errorEl.textContent = 'Verbindungsfehler';
            errorEl.style.display = 'block';
        }
    }

    // Try auto-login using saved PWA token
    async tryAutoLogin(username, password) {
        if (!this.pwaToken) return false;
        try {
            const response = await fetch(CONFIG.apiBase + '?route=pwa-token', {
                method: 'GET',
                headers: { 'X-PWA-Token': this.pwaToken }
            });

            if (response.ok) {
                const result = await response.json();
                if (result.status === 'ok' && result.user_id) {
                    this.user = {
                        id: result.user_id,
                        login: result.user_login,
                        name: result.user_login,
                        valid_until: (Date.now() / 1000) + (90 * 24 * 3600)
                    };
                    await offlineDB.setMeta('auth', this.user);
                    return true;
                }
            }
            return false;
        } catch (err) {
            console.error('Token auto-login failed:', err);
            return false;
        }
    }

    // Show trusted device info banner
    showTrustedDeviceBanner() {
        if (!CONFIG.trustedDevice) return;

        const banner = document.getElementById('trustedDeviceBanner');
        const text = document.getElementById('trustedDeviceText');

        if (banner && text) {
            const days = CONFIG.trustedDevice.days_remaining;
            const device = CONFIG.trustedDevice.device_name || 'Dieses Gerät';

            if (days <= 3) {
                banner.style.background = '#fff3e0';
                banner.style.color = '#e65100';
                banner.style.borderColor = '#ffcc80';
            }

            text.innerHTML = `🔒 ${device} ist vertrauenswürdig - 2FA nicht erforderlich für <strong>${days} Tag${days !== 1 ? 'e' : ''}</strong>`;
            banner.style.display = 'block';

            // Hide after 10 seconds
            setTimeout(() => {
                banner.style.display = 'none';
            }, 10000);
        }
    }

    showAuthError(message) {
        document.getElementById('interventionsLoading').style.display = 'none';
        document.getElementById('interventionsList').innerHTML = `
            <div class="empty-state">
                <div class="empty-icon">🔒</div>
                <p>${message}</p>
                <a href="settings.php" class="btn btn-primary" style="margin-top:16px;">Einstellungen öffnen</a>
            </div>
        `;
    }

    setupEventListeners() {
        // Online/Offline events
        window.addEventListener('online', () => {
            // Browser reports network — verify server is actually reachable
            this.checkConnectivity();
        });

        window.addEventListener('offline', () => {
            this.isOnline = false;
            this.updateOnlineStatus();
        });

        // Periodic connectivity check every 15s — runs always to detect both
        // stuck-offline AND stale-online states
        setInterval(() => {
            this.checkConnectivity(true);
        }, 15000);

        // Check connectivity when app comes back to foreground
        // Use 3 retries (× 1.5s) because network may not be immediately available after wakeup
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                this.checkConnectivity(true, 3);
            }
        });

        // Navigation
        document.querySelectorAll('.nav-item').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const view = e.currentTarget.dataset.view;
                if (view) this.showView(view);
            });
        });

        // Back button
        document.getElementById('btnBack').addEventListener('click', () => this.goBack());

        // Sync button — try to reconnect first if currently offline
        document.getElementById('btnSync').addEventListener('click', async () => {
            if (!this.isOnline) {
                const online = await this.checkConnectivity(true, 2, true);
                if (!online) {
                    this.showToast('Keine Verbindung möglich');
                    return;
                }
            }
            await this.syncData();
        });

        // Entry form submit (v1.7)
        document.getElementById('entryForm').addEventListener('submit', (e) => {
            e.preventDefault();
            this.saveEntry();
        });

        // Add entry button (v1.7)
        document.getElementById('btnAddEntry').addEventListener('click', () => this.addNewEntry());

        // Delete entry button (v1.7)
        document.getElementById('btnDeleteEntry').addEventListener('click', () => this.deleteEntry());

        // Save summary button (v1.7)
        document.getElementById('btnSaveSummary').addEventListener('click', () => this.saveSummary());

        // Signature buttons
        document.getElementById('btnClearSignature').addEventListener('click', () => this.clearSignature());
        document.getElementById('btnSaveSignature').addEventListener('click', () => this.saveSignature());

        // Material buttons
        document.getElementById('btnAddMaterial').addEventListener('click', () => this.showMaterialModal());
        document.getElementById('btnCloseMaterial').addEventListener('click', () => this.closeMaterialModal());
        document.getElementById('btnSaveMaterial').addEventListener('click', () => this.saveMaterial());

        // Product search
        let productSearchTimeout;
        document.getElementById('productSearch').addEventListener('input', (e) => {
            clearTimeout(productSearchTimeout);
            productSearchTimeout = setTimeout(() => this.searchProducts(e.target.value), 300);
        });

        // Equipment modal buttons
        document.getElementById('btnCloseEquipment').addEventListener('click', () => this.closeEquipmentModal());

        // Maintenance overview button
        document.getElementById('navMaintenance').addEventListener('click', () => this.showMaintenance());

        // Map button
        document.getElementById('navMap').addEventListener('click', () => this.showMap());

        // Release button
        document.getElementById('navRelease').addEventListener('click', () => this.toggleRelease());

        // Documents button
        document.getElementById('navDocuments').addEventListener('click', () => this.showDocuments());
        document.getElementById('btnCloseDocuments').addEventListener('click', () => this.closeDocumentsModal());
        document.getElementById('btnClosePdfViewer').addEventListener('click', () => this.closePdfViewer());

        // PDF Preview button
        document.getElementById('navPdfPreview').addEventListener('click', () => this.showPdfPreview());

        // Acceptance Protocol button (v4.5)
        document.getElementById('navAcceptanceProtocol').addEventListener('click', () => this.showAcceptanceProtocol());

        // Defect material section visibility (v4.2) - show after saving entry with issues
        document.getElementById('entryIssuesFound').addEventListener('input', () => {
            // Only show if editing existing entry (new entries must be saved first)
            if (this.currentEntry && this.currentEntry.id) {
                this.updateDefectMaterialVisibility();
            }
        });

        // Commissioning/Acceptance checkbox handlers (v4.5)
        document.getElementById('entryCommissioningDone').addEventListener('change', (e) => {
            this.updateCommissioningUI(e.target.checked);
        });
        // Parent checkbox: "Abnahme durchführen"
        document.getElementById('entryDoingAcceptance').addEventListener('change', (e) => {
            this.updateDoingAcceptanceUI(e.target.checked);
        });
        // Success checkbox: "Abnahme erfolgreich"
        document.getElementById('entryAcceptanceDone').addEventListener('change', (e) => {
            this.updateAcceptanceSuccessUI(e.target.checked);
        });
        // Mangelfrei toggle (inside success row)
        document.getElementById('entryAcceptanceDefectFree').addEventListener('change', (e) => {
            this.updateAcceptanceDefectFreeUI(e.target.checked);
        });
    }

    // Toggle commissioning date vs note visibility (v4.5)
    updateCommissioningUI(isDone) {
        const dateRow = document.getElementById('commissioningDateRow');
        const noteRow = document.getElementById('commissioningNoteRow');
        const dateInput = document.getElementById('entryCommissioningDate');

        if (isDone) {
            dateRow.style.display = 'block';
            noteRow.style.display = 'none';
            if (!dateInput.value) {
                dateInput.value = this.formatDateInput(new Date());
            }
        } else {
            dateRow.style.display = 'none';
            noteRow.style.display = 'block';
        }
    }

    // Toggle acceptance section visibility when "Abnahme durchführen" is clicked (v4.5.2)
    updateDoingAcceptanceUI(isDoing) {
        const detailsRow = document.getElementById('acceptanceDetailsRow');
        const successRow = document.getElementById('acceptanceSuccessRow');
        const failedRow = document.getElementById('acceptanceFailedRow');
        const successCheckbox = document.getElementById('entryAcceptanceDone');

        if (isDoing) {
            detailsRow.style.display = 'block';
            // Default: show failed row (defects), hide success row
            // User must explicitly check "Abnahme erfolgreich"
            this.updateAcceptanceSuccessUI(successCheckbox.checked);
        } else {
            detailsRow.style.display = 'none';
            successCheckbox.checked = false;
        }
    }

    // Toggle success vs failed display when "Abnahme erfolgreich" is clicked (v4.5.2)
    updateAcceptanceSuccessUI(isSuccess) {
        const successRow = document.getElementById('acceptanceSuccessRow');
        const failedRow = document.getElementById('acceptanceFailedRow');
        const dateInput = document.getElementById('entryAcceptanceDate');

        if (isSuccess) {
            successRow.style.display = 'block';
            failedRow.style.display = 'none';
            if (!dateInput.value) {
                dateInput.value = this.formatDateInput(new Date());
            }
            // Re-apply mangelfrei state
            const defectFreeChk = document.getElementById('entryAcceptanceDefectFree');
            this.updateAcceptanceDefectFreeUI(defectFreeChk ? defectFreeChk.checked : true);
        } else {
            successRow.style.display = 'none';
            failedRow.style.display = 'block';
        }
    }

    // Toggle between "Mangelfrei" (remark) and "Abnahme mit Mängeln" views
    updateAcceptanceDefectFreeUI(isDefectFree) {
        const remarkRow = document.getElementById('acceptanceRemarkRow');
        const mitMaengelRow = document.getElementById('acceptanceMitMaengelRow');
        if (remarkRow) remarkRow.style.display = isDefectFree ? 'block' : 'none';
        if (mitMaengelRow) mitMaengelRow.style.display = isDefectFree ? 'none' : 'block';
    }

    updateOnlineStatus() {
        const statusEl = document.getElementById('syncStatus');
        if (this.isOnline) {
            statusEl.textContent = 'Online';
            statusEl.className = 'sync-status online';
        } else {
            statusEl.textContent = 'Offline';
            statusEl.className = 'sync-status offline';
        }
    }

    async checkConnectivity(silent = false, maxRetries = 0, skipAutoSync = false) {
        for (let attempt = 0; attempt <= maxRetries; attempt++) {
            if (attempt > 0) {
                await new Promise(r => setTimeout(r, 1500));
            }
            try {
                const controller = new AbortController();
                const tid = setTimeout(() => controller.abort(), 5000);
                const pingHeaders = {};
                if (this.pwaToken) pingHeaders['X-PWA-Token'] = this.pwaToken;
                const response = await fetch(CONFIG.apiBase + '?route=ping&_=' + Date.now(), {
                    credentials: 'same-origin',
                    cache: 'no-store',
                    signal: controller.signal,
                    headers: pingHeaders
                });
                clearTimeout(tid);

                if (response.ok) {
                    // Guard: SW returns fake HTTP 200 with {offline:true} when network is down
                    const data = await response.json().catch(() => ({}));
                    if (data.offline === true) continue; // SW fallback — retry

                    // Real 200 — authenticated and online
                    await this._goOnline(silent, skipAutoSync);
                    return true;
                }

                if (response.status === 401) {
                    // Server reachable but session expired — try auto-login to refresh session
                    const refreshed = await this._trySessionRefresh();
                    if (refreshed) {
                        await this._goOnline(silent, skipAutoSync);
                        return true;
                    }
                    // No credentials / refresh failed — online but can't auth, don't sync
                    if (!this.isOnline) {
                        this.isOnline = true;
                        this.updateOnlineStatus();
                    }
                    return true;
                }
            } catch (e) {
                // Network error — try next attempt
            }
        }

        // All attempts failed — offline
        if (this.isOnline) {
            this.isOnline = false;
            this.updateOnlineStatus();
        }
        return false;
    }

    // Called when we confirm server is reachable and authenticated
    async _goOnline(silent, skipAutoSync) {
        if (!this.isOnline) {
            this.isOnline = true;
            this.updateOnlineStatus();
            if (!silent) this.showToast('Verbindung wiederhergestellt');
            if (!skipAutoSync) {
                await this.syncData();
                await this.loadInterventions();
            }
        }
    }

    // Try to refresh session using saved PWA token
    async _trySessionRefresh() {
        try {
            if (!this.pwaToken) return false;
            return await this.tryAutoLogin(null, null);
        } catch (e) {
            return false;
        }
    }

    showView(viewId, title = null) {
        // Hide all views
        document.querySelectorAll('.view').forEach(v => v.classList.remove('active'));

        // Show target view
        document.getElementById(viewId).classList.add('active');

        // Update nav
        document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
        const navItem = document.querySelector(`[data-view="${viewId}"]`);
        if (navItem) navItem.classList.add('active');

        // Update header
        const backBtn = document.getElementById('btnBack');
        const headerTitle = document.getElementById('headerTitle');

        if (viewId === 'viewInterventions' || viewId === 'viewMap' || viewId === 'viewMaintenance') {
            backBtn.style.display = 'none';
            const titles = { viewMap: 'Karte', viewMaintenance: 'Wartungsübersicht', viewInterventions: 'Serviceberichte' };
            headerTitle.textContent = titles[viewId] || 'Serviceberichte';
            document.getElementById('navRelease').style.display = 'none';
            document.getElementById('navDocuments').style.display = 'none';
            document.getElementById('navPdfPreview').style.display = 'none';
            document.getElementById('navAcceptanceProtocol').style.display = 'none';
            document.getElementById('navSignature').style.display = 'none';
            document.getElementById('navMap').style.display = 'flex';
            document.getElementById('navMaintenance').style.display = 'flex';
            // Set correct nav item active
            document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
            const navIds = { viewMap: 'navMap', viewMaintenance: 'navMaintenance' };
            const activeBtn = navIds[viewId]
                ? document.getElementById(navIds[viewId])
                : document.querySelector('[data-view="viewInterventions"]');
            if (activeBtn) activeBtn.classList.add('active');
        } else {
            backBtn.style.display = 'block';
            if (title) headerTitle.textContent = title;
            document.getElementById('navMap').style.display = 'none';
            document.getElementById('navMaintenance').style.display = 'none';
        }

        this.currentView = viewId;

        // Initialize signature if needed
        if (viewId === 'viewSignature') {
            // Check if released
            const signedStatus = this.currentIntervention?.signed_status || 0;
            const signatureCard = document.querySelector('#viewSignature .card');
            const saveBtn = document.getElementById('btnSaveSignature');

            if (signedStatus < 1) {
                // Not released - show warning and disable signature
                signatureCard.innerHTML = `
                    <div class="card-header">
                        <h3 class="card-title">Kundenunterschrift</h3>
                    </div>
                    <div class="card-body">
                        <div class="empty-state">
                            <div class="empty-icon">⚠️</div>
                            <p>Unterschrift erst nach Freigabe möglich</p>
                            <p style="font-size:12px;color:#666;">Bitte zuerst auf "Freigeben" klicken.</p>
                        </div>
                    </div>
                `;
                saveBtn.style.display = 'none';
            } else if (signedStatus >= 3) {
                // Already signed — show email button
                signatureCard.innerHTML = `
                    <div class="card-header">
                        <h3 class="card-title">Kundenunterschrift</h3>
                    </div>
                    <div class="card-body">
                        <div class="empty-state">
                            <div class="empty-icon">✅</div>
                            <p>Unterschrift bereits vorhanden</p>
                        </div>
                        <button type="button" class="btn btn-primary btn-block" id="btnSendEmailSigned" style="margin-top:12px;">
                            📧 Servicebericht per E-Mail senden
                        </button>
                    </div>
                `;
                saveBtn.style.display = 'none';
                document.getElementById('btnSendEmailSigned').addEventListener('click', () => this.showEmailModal());
            } else {
                // Released - show Online Sign option
                this.showOnlineSignOption(signatureCard, saveBtn);
            }
        }
    }

    async showOnlineSignOption(signatureCard, saveBtn) {
        // Both online and offline signatures now use our own signature form
        // This ensures the signature is placed correctly in the EquipmentManager PDF template
        signatureCard.innerHTML = `
            <div class="card-header">
                <h3 class="card-title">Kundenunterschrift</h3>
            </div>
            <div class="card-body">
                <p style="margin-bottom:16px;color:#666;">Bereit zur Unterschrift:</p>

                <button type="button" class="btn btn-primary btn-block" id="btnStartSign" style="margin-bottom:12px;">
                    ✍️ Jetzt unterschreiben
                </button>
                <p style="font-size:12px;color:#888;margin-top:8px;text-align:center;">
                    ${this.isOnline ? 'PDF wird sofort mit Unterschrift erstellt' : 'Unterschrift wird bei Verbindung synchronisiert'}
                </p>
            </div>
        `;
        saveBtn.style.display = 'none';

        // Start signature button - shows the signature form
        document.getElementById('btnStartSign').addEventListener('click', () => {
            this.resetSignatureView();
            saveBtn.style.display = 'block';
            this.initSignature();
        });
    }

    resetSignatureView() {
        const signatureCard = document.querySelector('#viewSignature .card');
        signatureCard.innerHTML = `
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
        `;
        // Re-attach clear button handler
        document.getElementById('btnClearSignature').addEventListener('click', () => this.clearSignature());
        this.signatureInstance = null;
    }

    goBack() {
        switch (this.currentView) {
            case 'viewEquipment':
                this.showView('viewInterventions');
                this.loadInterventions(); // Refresh list to reflect any status changes
                break;
            case 'viewEntries':
                this.loadEquipment(this.currentIntervention);
                break;
            case 'viewEntry':
                this.loadEntries(this.currentEquipment);
                break;
            case 'viewSignature':
                this.loadEquipment(this.currentIntervention);
                break;
            default:
                this.showView('viewInterventions');
        }
    }

    // API calls with offline fallback
    async apiCall(endpoint, options = {}) {
        // Build URL - handle endpoints with query params
        let url;
        if (endpoint.includes('?')) {
            // Endpoint already has query params
            const [route, params] = endpoint.split('?');
            url = CONFIG.apiBase + '?route=' + encodeURIComponent(route) + '&' + params;
        } else {
            url = CONFIG.apiBase + '?route=' + encodeURIComponent(endpoint);
        }

        if (!this.isOnline) {
            throw new Error('Offline');
        }

        // Build headers - include PWA token if available
        const headers = {
            'Content-Type': 'application/json',
            ...options.headers
        };

        // Add PWA token for persistent authentication
        if (this.pwaToken) {
            headers['X-PWA-Token'] = this.pwaToken;
        }

        const controller = new AbortController();
        const tid = setTimeout(() => controller.abort(), 10000);

        try {
            const response = await fetch(url, {
                credentials: 'same-origin',
                headers,
                signal: controller.signal,
                ...options
            });
            clearTimeout(tid);

            if (!response.ok) {
                const text = await response.text();
                console.error('API Error:', response.status, text);

                // Session expired — try to refresh and retry the request once
                if (response.status === 401 && !options._authRetried) {
                    const refreshed = await this._trySessionRefresh();
                    if (refreshed) {
                        return this.apiCall(endpoint, { ...options, _authRetried: true });
                    }
                }

                throw new Error(`HTTP ${response.status}`);
            }

            return response.json();
        } catch (err) {
            clearTimeout(tid);
            console.error('API call failed:', endpoint, err);
            // Network error or timeout → mark offline and schedule reconnect
            if (err.name === 'TypeError' || err.name === 'AbortError') {
                if (this.isOnline) {
                    this.isOnline = false;
                    this.updateOnlineStatus();
                    setTimeout(() => this.checkConnectivity(true, 2), 3000);
                }
            }
            throw err;
        }
    }

    // Get intervention status category
    // status: 0=Entwurf/Draft, 1=Validiert, 3=Closed
    // signed_status: 0=not released, 1=released for signature, 3=signed
    getInterventionStatus(intervention) {
        const signedStatus = intervention.signed_status || 0;
        const baseStatus = intervention.status || 0;

        // Erledigt: closed in Dolibarr (status=3), OR digitally signed (signed_status>=3) and validated
        if (baseStatus >= 3 || (signedStatus >= 3 && baseStatus >= 1)) return 'signed';

        // Freigegeben: released for signature (1 or 2) but NOT yet signed/closed
        if (signedStatus >= 1 && signedStatus < 3) return 'released';

        // Offen: everything else including:
        // - Drafts (status=0, signed_status=0)
        // - Signed but still draft (status=0, signed_status=3) - needs validation
        return 'open';
    }

    // Check if intervention is within last N days (0 = no limit)
    isWithinDays(intervention, days) {
        if (days === 0) return true; // No limit

        // Use date_intervention or datec (creation date) as fallback
        const dateStr = intervention.date_intervention || intervention.datec || '';
        if (!dateStr) return true; // If no date, include it

        const interventionDate = new Date(dateStr);
        const cutoffDate = new Date();
        cutoffDate.setDate(cutoffDate.getDate() - days);

        return interventionDate >= cutoffDate;
    }

    // Count interventions by status (apply time range to signed)
    countByStatus(interventions) {
        const counts = { open: 0, released: 0, signed: 0 };
        interventions.forEach(i => {
            const status = this.getInterventionStatus(i);
            // Only count signed if within selected time range
            if (status === 'signed' && !this.isWithinDays(i, this.signedTimeRange)) {
                return;
            }
            counts[status]++;
        });
        return counts;
    }

    // Filter interventions based on current filter
    filterInterventions(interventions) {
        return interventions.filter(i => {
            const status = this.getInterventionStatus(i);
            if (status !== this.interventionFilter) return false;

            // For signed: apply time range filter
            if (status === 'signed' && !this.isWithinDays(i, this.signedTimeRange)) {
                return false;
            }

            return true;
        });
    }

    // Get time range label
    getTimeRangeLabel(days) {
        if (days === 0) return 'Alle';
        if (days === 30) return '30 Tage';
        if (days === 90) return '3 Monate';
        if (days === 180) return '6 Monate';
        if (days === 365) return '12 Monate';
        return `${days} Tage`;
    }

    // Render filter tabs - simplified: Offen, Freigegeben, Erledigt
    renderFilterTabs(counts) {
        // Time range options for signed orders
        const timeRangeOptions = this.interventionFilter === 'signed' ? `
            <div class="time-range-selector">
                <span class="time-range-label">Zeitraum:</span>
                <select id="timeRangeSelect" class="time-range-select">
                    <option value="30" ${this.signedTimeRange === 30 ? 'selected' : ''}>30 Tage</option>
                    <option value="90" ${this.signedTimeRange === 90 ? 'selected' : ''}>3 Monate</option>
                    <option value="180" ${this.signedTimeRange === 180 ? 'selected' : ''}>6 Monate</option>
                    <option value="365" ${this.signedTimeRange === 365 ? 'selected' : ''}>12 Monate</option>
                    <option value="0" ${this.signedTimeRange === 0 ? 'selected' : ''}>Alle</option>
                </select>
            </div>
        ` : '';

        return `
            <div class="filter-tabs">
                <button class="filter-tab ${this.interventionFilter === 'open' ? 'active' : ''}" data-filter="open">
                    Offen <span class="filter-count">${counts.open}</span>
                </button>
                <button class="filter-tab ${this.interventionFilter === 'released' ? 'active' : ''}" data-filter="released">
                    Freigegeben <span class="filter-count">${counts.released}</span>
                </button>
                <button class="filter-tab ${this.interventionFilter === 'signed' ? 'active' : ''}" data-filter="signed">
                    Erledigt <span class="filter-count">${counts.signed}</span>
                </button>
            </div>
            ${timeRangeOptions}
        `;
    }

    // Set filter and re-render
    setFilter(filter) {
        this.interventionFilter = filter;
        this.renderInterventionsList();
    }

    // Set time range for signed orders
    setTimeRange(days) {
        this.signedTimeRange = parseInt(days, 10);
        this.renderInterventionsList();
    }

    // Render interventions list (uses cached data)
    renderInterventionsList() {
        const listEl = document.getElementById('interventionsList');
        const interventions = this.allInterventions;

        if (interventions.length === 0) {
            listEl.innerHTML = `
                <div class="empty-state">
                    <div class="empty-icon">📋</div>
                    <p>Keine Aufträge gefunden</p>
                </div>
            `;
            return;
        }

        // Count by status
        const counts = this.countByStatus(interventions);

        // Filter interventions
        const filtered = this.filterInterventions(interventions);

        // Sort: open first, then released, then signed (by date desc within each group)
        filtered.sort((a, b) => {
            const statusA = this.getInterventionStatus(a);
            const statusB = this.getInterventionStatus(b);
            const order = { open: 0, released: 1, signed: 2 };
            if (order[statusA] !== order[statusB]) {
                return order[statusA] - order[statusB];
            }
            // Same status - sort by date desc
            const dateA = a.date_intervention || a.datec || '';
            const dateB = b.date_intervention || b.datec || '';
            return dateB.localeCompare(dateA);
        });

        // Build HTML
        let html = this.renderFilterTabs(counts);

        if (filtered.length === 0) {
            html += `
                <div class="empty-state" style="padding: 40px 20px;">
                    <div class="empty-icon">📭</div>
                    <p>Keine Aufträge in dieser Kategorie</p>
                </div>
            `;
        }

        listEl.innerHTML = html;

        // Add filter tab event listeners
        listEl.querySelectorAll('.filter-tab').forEach(tab => {
            tab.addEventListener('click', () => {
                this.setFilter(tab.dataset.filter);
            });
        });


        // Add time range select listener
        const timeRangeSelect = document.getElementById('timeRangeSelect');
        if (timeRangeSelect) {
            timeRangeSelect.addEventListener('change', (e) => {
                this.setTimeRange(e.target.value);
            });
        }

        // Render intervention cards
        filtered.forEach(intervention => {
            listEl.appendChild(this.createInterventionCard(intervention));
        });

        // Legend link at the bottom
        const legendLink = document.createElement('div');
        legendLink.style.cssText = 'text-align:center;padding:12px 0 4px;';
        legendLink.innerHTML = '<button id="btnStatusLegend" style="background:none;border:none;font-size:13px;color:var(--text-muted,#999);cursor:pointer;padding:4px 8px;">ⓘ Farb-Legende</button>';
        listEl.appendChild(legendLink);
        document.getElementById('btnStatusLegend').addEventListener('click', () => this.showStatusLegend());
    }

    // Load interventions
    async loadInterventions() {
        const loadingEl = document.getElementById('interventionsLoading');
        const listEl = document.getElementById('interventionsList');

        loadingEl.style.display = 'block';
        listEl.innerHTML = '';

        // Helper to load from cache
        const loadFromCache = async () => {
            const cached = await offlineDB.getAll('interventions');
            if (cached.length > 0) {
                this.allInterventions = cached;
                this.renderInterventionsList();
                return true;
            }
            return false;
        };

        try {
            let interventions = [];

            if (this.isOnline) {
                try {
                    // Fetch from API
                    const data = await this.apiCall('interventions?status=all');
                    interventions = data.interventions || [];

                    // Save to IndexedDB
                    await offlineDB.saveInterventions(interventions);

                    loadingEl.style.display = 'none';
                    this.allInterventions = interventions;
                    this.renderInterventionsList();
                } catch (apiErr) {
                    // API failed - might be offline now
                    console.warn('API call failed, falling back to cache:', apiErr);
                    this.isOnline = false;
                    this.updateOnlineStatus();

                    loadingEl.style.display = 'none';
                    if (await loadFromCache()) {
                        this.showToast('Offline-Daten geladen');
                    } else {
                        throw apiErr; // Re-throw if no cache
                    }
                }
            } else {
                // Offline - load from IndexedDB
                loadingEl.style.display = 'none';
                if (await loadFromCache()) {
                    this.showToast('Offline-Modus');
                } else {
                    listEl.innerHTML = `
                        <div class="empty-state">
                            <div class="empty-icon">📴</div>
                            <p>Offline - Keine gespeicherten Daten</p>
                            <p style="font-size:12px;">Bitte verbinden Sie sich mit dem Internet</p>
                        </div>
                    `;
                }
            }
        } catch (err) {
            console.error('Failed to load interventions:', err);
            loadingEl.style.display = 'none';

            listEl.innerHTML = `
                <div class="empty-state">
                    <div class="empty-icon">⚠️</div>
                    <p>Fehler beim Laden</p>
                    <p style="font-size:12px;">${err.message}</p>
                    <button onclick="window.app.loadInterventions()" style="margin-top:12px;padding:10px 20px;border:none;border-radius:6px;background:#1a3f6e;color:white;cursor:pointer;">
                        Erneut versuchen
                    </button>
                </div>
            `;
        }
    }

    createInterventionCard(intervention) {
        const card = document.createElement('div');
        card.className = 'card card-clickable';

        // Determine status based on both status and signed_status
        // signed_status: 0 = not released, 1 = released for signature, 3 = signed
        const signedStatus = intervention.signed_status || 0;

        let statusClass, statusText;
        if (intervention.status === 0) {
            statusClass = 'open';
            statusText = 'Offen';
        } else if (signedStatus >= 3 || intervention.status >= 3) {
            statusClass = 'signed';
            statusText = 'Unterschrieben';
        } else if (signedStatus >= 1) {
            statusClass = 'released';
            statusText = 'Freigegeben';
        } else if (intervention.status === 1) {
            statusClass = 'open';
            statusText = 'Offen';
        } else {
            statusClass = 'done';
            statusText = 'Abgeschlossen';
        }
        card.classList.add(`card-status-${statusClass}`);

        // Format object addresses with clickable maps link
        let objectAddressHtml = '';
        if (intervention.object_addresses && intervention.object_addresses.length > 0) {
            const addr = intervention.object_addresses[0]; // Show first address
            objectAddressHtml = `
                <div class="object-address-divider">
                    <p class="object-address-label">📍 Objektadresse</p>
                    <p class="object-address-name">
                        ${addr.name || ''}
                    </p>
                    <p class="object-address-details">
                        ${this.renderAddressLink(addr.address, addr.zip, addr.town)}
                    </p>
                    ${intervention.object_addresses.length > 1 ? `<p class="info-text-muted" style="margin:4px 0 0; font-size:11px;">+ ${intervention.object_addresses.length - 1} weitere Adresse(n)</p>` : ''}
                </div>
            `;
        }

        // Customer address with clickable maps link
        const customerAddressHtml = intervention.customer?.address || intervention.customer?.zip || intervention.customer?.town
            ? this.renderAddressLink(intervention.customer?.address, intervention.customer?.zip, intervention.customer?.town)
            : '';

        // Type badge + right border
        let typeBadgeHtml = '';
        let rightBorderColor = '';
        if (intervention.primary_type === 'maintenance') {
            const maintColors = {
                overdue: { bg: '#ffcdd2', text: '#c62828' },
                soon:    { bg: '#ffe0b2', text: '#e65100' },
                ok:      { bg: '#c8e6c9', text: '#2e7d32' },
                none:    { bg: '#ffe0b2', text: '#e65100' },
            };
            const c = maintColors[intervention.maintenance_status] || maintColors.none;
            typeBadgeHtml = `<span class="badge" style="background:${c.bg};color:${c.text}">Wartung</span>`;
            rightBorderColor = c.text;
        } else if (intervention.primary_type === 'service') {
            typeBadgeHtml = '<span class="badge" style="background:#bbdefb;color:#1565c0">Service</span>';
            rightBorderColor = '#1565c0';
        }

        card.innerHTML = `
            <div class="card-header">
                <h3 class="card-title">${intervention.ref || 'Intervention'}</h3>
                ${typeBadgeHtml || ''}
            </div>
            <div class="card-body">
                <p class="customer-name">
                    ${intervention.customer?.name || 'Kunde'}
                </p>
                <p class="customer-address">
                    ${customerAddressHtml}
                </p>
                ${objectAddressHtml}
                ${intervention.date_start ? `<p class="date-text">📅 ${this.formatDate(intervention.date_start)}</p>` : ''}
            </div>
        `;

        card.addEventListener('click', () => {
            this.currentIntervention = intervention;
            this.loadEquipment(intervention);
        });

        return card;
    }

    // Load equipment for intervention
    async loadEquipment(intervention) {
        this.showView('viewEquipment', intervention.ref);

        const loadingEl = document.getElementById('equipmentLoading');
        const listEl = document.getElementById('equipmentList');

        loadingEl.style.display = 'block';
        listEl.innerHTML = '';

        try {
            let equipment = [];
            let signedStatus = intervention.signed_status || 0;
            let loadedFromCache = false;

            // Try loading from API first, fall back to cache
            const loadFromCache = async () => {
                equipment = await offlineDB.getEquipmentForIntervention(intervention.id);
                loadedFromCache = true;
                // Also get cached intervention data
                const cachedInterventions = await offlineDB.getAll('interventions');
                const cached = cachedInterventions.find(i => i.id === intervention.id);
                if (cached) {
                    signedStatus = cached.signed_status || 0;
                    this.currentIntervention.signed_status = signedStatus;
                    this.currentIntervention.status = cached.status || 0;
                }
            };

            if (this.isOnline) {
                try {
                    // Fetch full intervention data to get updated signed_status
                    const fullData = await this.apiCall(`intervention/${intervention.id}`);
                    if (fullData.intervention) {
                        signedStatus = fullData.intervention.signed_status || 0;
                        // Update currentIntervention with fresh data
                        this.currentIntervention.signed_status = signedStatus;
                        this.currentIntervention.status = fullData.intervention.status;
                        this.currentIntervention.note_public = fullData.intervention.note_public;
                        this.currentIntervention.note_private = fullData.intervention.note_private;
                        this.currentIntervention.description = fullData.intervention.description;
                    }
                    equipment = fullData.equipment || [];
                    await offlineDB.saveEquipment(intervention.id, equipment);

                    // Also update intervention in IndexedDB
                    try {
                        const interventions = await offlineDB.getAll('interventions');
                        const idx = interventions.findIndex(i => i.id === intervention.id);
                        if (idx >= 0) {
                            interventions[idx].signed_status = signedStatus;
                            interventions[idx].status = this.currentIntervention.status;
                            await offlineDB.saveInterventions(interventions);
                        }
                    } catch (e) {
                        console.error('Failed to update IndexedDB:', e);
                    }
                } catch (apiErr) {
                    console.warn('API call failed, falling back to cache:', apiErr);
                    // Network error - update online status and load from cache
                    this.isOnline = false;
                    this.updateOnlineStatus();
                    await loadFromCache();
                }
            } else {
                await loadFromCache();
            }

            if (loadedFromCache && equipment.length > 0) {
                this.showToast('Offline-Daten geladen');
            }

            loadingEl.style.display = 'none';

            // Show release button and update text based on signed_status
            const releaseBtn = document.getElementById('navRelease');
            const releaseIcon = document.getElementById('releaseIcon');
            const releaseText = document.getElementById('releaseText');
            releaseBtn.style.display = 'flex';

            // Show/hide documents button
            const docsBtn = document.getElementById('navDocuments');
            docsBtn.style.display = 'flex';

            // Show PDF preview button
            document.getElementById('navPdfPreview').style.display = 'flex';

            // console.log('Equipment loaded, signedStatus:', signedStatus);

            if (signedStatus >= 1) {
                // Released or signed - show "Ändern" button
                releaseIcon.textContent = '✏️';
                releaseText.textContent = 'Ändern';
            } else {
                // Not released - show "Freigeben" button
                releaseIcon.textContent = '✅';
                releaseText.textContent = 'Freigeben';
            }

            // Show signature/email button based on signed_status
            const sigBtn = document.getElementById('navSignature');
            if (signedStatus >= 1 && signedStatus < 3) {
                sigBtn.style.display = 'flex';
                sigBtn.querySelector('.nav-icon').textContent = '✍️';
                sigBtn.querySelector('span:last-child').textContent = 'Unterschrift';
                sigBtn.onclick = null;
                sigBtn.setAttribute('data-view', 'viewSignature');
            } else if (signedStatus >= 3) {
                // Already signed — repurpose as direct email button
                sigBtn.style.display = 'flex';
                sigBtn.querySelector('.nav-icon').textContent = '📧';
                sigBtn.querySelector('span:last-child').textContent = 'E-Mail';
                sigBtn.removeAttribute('data-view');
                sigBtn.onclick = (e) => { e.stopPropagation(); this.showEmailModal(); };
            } else {
                // Not released - hide signature button
                sigBtn.style.display = 'none';
                sigBtn.onclick = null;
                sigBtn.setAttribute('data-view', 'viewSignature');
            }

            // Show acceptance protocol button if has acceptance data (v4.5)
            // Available even before signing so user can preview
            const accBtn = document.getElementById('navAcceptanceProtocol');
            const hasAcceptanceData = equipment.some(eq =>
                eq.link_type === 'service' && eq.detail &&
                (eq.detail.commissioning_done || eq.detail.acceptance_done)
            );
            accBtn.style.display = hasAcceptanceData ? 'flex' : 'none';

            // Collapsible info header
            listEl.appendChild(this.buildInfoHeader(intervention));

            // Button container for action buttons
            const btnContainer = document.createElement('div');
            btnContainer.style.cssText = 'display: flex; gap: 8px; margin-bottom: 12px;';

            // Add "Add Equipment" button
            const addBtn = document.createElement('div');
            addBtn.className = 'add-equipment-btn';
            addBtn.style.flex = '1';
            addBtn.innerHTML = '<span>➕</span> Anlage hinzufügen';
            addBtn.addEventListener('click', () => this.showEquipmentModal());
            btnContainer.appendChild(addBtn);

            // Add "General Work" button
            const generalBtn = document.createElement('div');
            generalBtn.className = 'add-equipment-btn';
            generalBtn.style.flex = '1';
            generalBtn.innerHTML = '<span>📝</span> Allgemeine Arbeiten';
            generalBtn.addEventListener('click', () => {
                this.currentEquipment = { id: 0, ref: 'Allgemein', label: 'Allgemeine Arbeiten', link_type: 'service' };
                this.loadEntries(this.currentEquipment);
            });
            btnContainer.appendChild(generalBtn);

            listEl.appendChild(btnContainer);

            if (equipment.length === 0) {
                const emptyState = document.createElement('div');
                emptyState.className = 'empty-state';
                emptyState.innerHTML = '<div class="empty-icon">🔧</div><p>Kein Equipment verknüpft</p>';
                listEl.appendChild(emptyState);
                return;
            }

            // Create equipment list
            const card = document.createElement('div');
            card.className = 'card';

            // Equipment type labels - store as class property for reuse
            this.equipmentTypeLabels = {
                'door_swing':     'Drehtür',
                'door_sliding':   'Schiebetür',
                'fire_door':      'Brandschutztür',
                'fire_door_fsa':  'Brandschutztür (FSA)',
                'fire_gate':      'Brandschutztor',
                'door_closer':    'Türschließer',
                'hold_open':      'Feststellanlage',
                'rws':            'RWS',
                'rwa':            'RWA',
                'gate_swing':     'Schwenkflügeltor',
                'gate_sliding':   'Schiebetor',
                'gate_sectional': 'Sektionaltor',
                'gate_upandover': 'Schwingtor',
                'other':          'Sonstige'
            };
            const typeLabels = this.equipmentTypeLabels;

            equipment.forEach(eq => {
                const item = document.createElement('div');
                item.className = 'equipment-item card-clickable';

                const typeName = typeLabels[eq.type] || eq.type || '';
                const linkTypeBadge = eq.link_type === 'maintenance'
                    ? '<span class="link-type-badge maintenance">Wartung</span>'
                    : '<span class="link-type-badge service">Service</span>';

                // Check if equipment has been processed (has detail with work_done)
                const isProcessed = eq.detail && eq.detail.work_done;
                const statusIcon = isProcessed ? '✅' : '🚪';
                const processedStyle = isProcessed ? 'border-left: 3px solid #4caf50;' : '';

                const hasData = eq.detail && (eq.detail.work_done || eq.detail.issues_found || eq.detail.notes || eq.detail.recommendations);
                const removeBtn = document.createElement('button');
                removeBtn.innerHTML = '🗑️';
                removeBtn.style.cssText = 'background:none;border:none;font-size:18px;padding:4px 8px;flex-shrink:0;';
                if (hasData) {
                    removeBtn.title = 'Nicht löschbar – Anlage hat bereits Einträge';
                    removeBtn.style.opacity = '0.2';
                    removeBtn.style.cursor = 'not-allowed';
                } else {
                    removeBtn.title = 'Anlage entfernen';
                    removeBtn.style.opacity = '0.6';
                    removeBtn.style.cursor = 'pointer';
                    removeBtn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        this.unlinkEquipment(eq);
                    });
                }

                item.innerHTML = `
                    <div class="equipment-icon">${statusIcon}</div>
                    <div class="equipment-info">
                        <div class="equipment-ref">${eq.ref} - ${typeName}</div>
                        <div class="equipment-label">${eq.manufacturer ? eq.manufacturer + ', ' : ''}${eq.label || ''}</div>
                        ${eq.location ? `<div class="equipment-label" style="color:#888;">${eq.location}</div>` : ''}
                    </div>
                    ${linkTypeBadge}
                `;
                item.appendChild(removeBtn);
                if (isProcessed) {
                    item.style.borderLeft = '3px solid #4caf50';
                }

                item.addEventListener('click', () => {
                    this.currentEquipment = eq;
                    this.loadEntries(eq);
                });

                card.appendChild(item);
            });

            listEl.appendChild(card);

        } catch (err) {
            console.error('Failed to load equipment:', err);
            loadingEl.style.display = 'none';
            this.showToast('Fehler beim Laden des Equipments');
        }
    }

    // Load entries list for equipment (v1.7)
    async loadEntries(equipment) {
        try {
            this.showView('viewEntries', equipment.ref);
            this.currentEquipment = equipment;

            // Show equipment ref and label
            document.getElementById('entriesEquipmentRef').textContent = `${equipment.ref} - ${equipment.label || ''}`;

            // Show link type badge (Wartung/Service/Allgemein) on the right
            let linkTypeBadge;
            if (equipment.id === 0) {
                linkTypeBadge = '<span class="link-type-badge service">Allgemein</span>';
            } else if (equipment.link_type === 'maintenance') {
                linkTypeBadge = '<span class="link-type-badge maintenance">Wartung</span>';
            } else {
                linkTypeBadge = '<span class="link-type-badge service">Service</span>';
            }
            document.getElementById('entriesLinkType').innerHTML = linkTypeBadge;

            // Show equipment details (hide for general entries)
            if (equipment.id > 0) {
                this.renderEquipmentDetails(equipment);
                document.getElementById('equipmentDetailsSection').style.display = 'block';
            } else {
                document.getElementById('equipmentDetailsSection').style.display = 'none';
            }

            const listEl = document.getElementById('entriesList');
            listEl.innerHTML = '<div class="loading"><div class="spinner"></div></div>';

            // Fetch entries from API or IndexedDB
            let entriesData = { entries: [], recommendations: '', notes: '', total_duration: 0 };

            if (this.isOnline) {
                try {
                    entriesData = await this.apiCall(`detail/${this.currentIntervention.id}/${equipment.id}`);

                    // Save to IndexedDB for offline use
                    const detail = {
                        intervention_id: this.currentIntervention.id,
                        equipment_id: equipment.id,
                        entries: entriesData.entries || [],
                        recommendations: entriesData.recommendations || '',
                        notes: entriesData.notes || '',
                        total_duration: entriesData.total_duration || 0,
                        materials: equipment.materials || [],
                        synced: true
                    };
                    await offlineDB.put('details', detail);
                } catch (err) {
                    // Try IndexedDB
                    const cachedDetail = await offlineDB.getDetail(this.currentIntervention.id, equipment.id);
                    if (cachedDetail) {
                        entriesData = cachedDetail;
                    }
                }
            } else {
                // Offline - load from IndexedDB
                const cachedDetail = await offlineDB.getDetail(this.currentIntervention.id, equipment.id);
                if (cachedDetail) {
                    entriesData = cachedDetail;
                    this.showToast('Offline-Daten geladen');
                }
            }

            // Store entries
            this.currentEntries = entriesData.entries || [];

            // Populate summary fields
            document.getElementById('summaryRecommendations').value = entriesData.recommendations || '';
            document.getElementById('summaryNotes').value = entriesData.notes || '';

            // Render entries list
            if (this.currentEntries.length === 0) {
                listEl.innerHTML = `
                    <div class="empty-state" style="padding: 20px;">
                        <p style="margin: 0; color: #666;">Keine Einträge vorhanden</p>
                        <p style="font-size: 12px; color: #999;">Klicke auf "Neuer Eintrag"</p>
                    </div>
                `;
            } else {
                listEl.innerHTML = '';

                // Show total duration
                if (entriesData.total_duration > 0) {
                    const hours = Math.floor(entriesData.total_duration / 60);
                    const mins = entriesData.total_duration % 60;
                    const totalDiv = document.createElement('div');
                    totalDiv.className = 'total-duration';
                    totalDiv.textContent = `Gesamtzeit: ${hours} Std. ${mins} min.`;
                    listEl.appendChild(totalDiv);
                }

                // Render each entry
                this.currentEntries.forEach((entry, index) => {
                    const item = document.createElement('div');
                    item.className = 'entry-item';

                    const hours = Math.floor((entry.work_duration || 0) / 60);
                    const mins = (entry.work_duration || 0) % 60;
                    let durationText = '';
                    if (entry.work_start_time && entry.work_end_time) {
                        durationText = `${entry.work_start_time} – ${entry.work_end_time}`;
                        if (hours > 0 || mins > 0) durationText += ` (${hours}h${mins > 0 ? ` ${mins}min` : ''})`;
                    } else if (hours > 0 || mins > 0) {
                        durationText = `${hours} Std. ${mins} min.`;
                    }

                    const summary = entry.work_done ? entry.work_done.substring(0, 50) + (entry.work_done.length > 50 ? '...' : '') : '';

                    item.innerHTML = `
                        <div class="entry-date">${this.formatDate(entry.work_date)}</div>
                        <div class="entry-info">
                            <div class="entry-duration">${durationText}</div>
                            <div class="entry-summary">${this.escapeHtml(summary)}</div>
                        </div>
                        <div class="entry-arrow">›</div>
                    `;

                    item.addEventListener('click', () => this.loadEntry(entry, index));
                    listEl.appendChild(item);
                });
            }

            // Load and display materials (not for general entries)
            if (equipment.id > 0) {
                const materials = equipment.materials || [];
                this.renderMaterials(materials);
                document.getElementById('materialsCard').style.display = 'block';
            } else {
                document.getElementById('materialsCard').style.display = 'none';
            }

            // Load checklist if maintenance equipment (not for general entries)
            const checklistCard = document.getElementById('checklistCard');
            if (equipment.id > 0 && equipment.link_type === 'maintenance') {
                checklistCard.style.display = 'block';
                await this.loadChecklist(this.currentIntervention.id, equipment.id);
            } else {
                checklistCard.style.display = 'none';
                this.currentChecklist = null;
            }

        } catch (err) {
            console.error('Error loading entries:', err);
            this.showToast('Fehler beim Laden');
        }
    }

    // Load single entry for editing (v1.7)
    loadEntry(entry, index) {
        this.currentEntry = { ...entry, index };
        this.showView('viewEntry', 'Eintrag bearbeiten');

        document.getElementById('entryTitle').textContent = 'Eintrag bearbeiten';

        // Populate form
        document.getElementById('entryDate').value = entry.work_date || this.formatDateInput(new Date());

        if (entry.work_start_time && entry.work_end_time) {
            this.setTimeMode('range');
            document.getElementById('entryTimeStart').value = entry.work_start_time;
            document.getElementById('entryTimeEnd').value = entry.work_end_time;
            this.onTimeRangeChange();
        } else {
            this.setTimeMode('duration');
            const hours = Math.floor((entry.work_duration || 0) / 60);
            const minutes = (entry.work_duration || 0) % 60;
            document.getElementById('entryHours').value = hours > 0 ? hours : '';
            document.getElementById('entryMinutes').value = String(Math.floor(minutes / 15) * 15);
        }

        document.getElementById('entryWorkDone').value = entry.work_done || '';
        document.getElementById('entryIssuesFound').value = entry.issues_found || '';

        // Load entry photo if exists
        this.currentEntryPhoto = entry.photo || null;
        this.currentEntryPhotoData = null; // Only set when new photo is captured
        this.updateEntryPhotoUI();

        // Load defect materials (v4.2) - use materials from entry response
        this.currentEntryDefectMaterials = entry.materials || [];
        this.renderDefectMaterials();
        // Show section if issues_found has content
        if (entry.issues_found && entry.issues_found.trim().length > 0) {
            document.getElementById('defectMaterialSection').style.display = 'block';
        } else {
            document.getElementById('defectMaterialSection').style.display = 'none';
        }

        // Commissioning/Acceptance section (v4.5) - only for service entries
        this.loadCommissioningAcceptanceFields(entry);

        // Show delete button for existing entries
        document.getElementById('btnDeleteEntry').style.display = 'block';
    }

    // Load commissioning and acceptance fields (v4.5)
    loadCommissioningAcceptanceFields(entry) {
        const section = document.getElementById('commissioningAcceptanceSection');
        if (!section) return; // Safety check

        // Hide for maintenance entries, show for everything else (service, general)
        if (this.currentEquipment && this.currentEquipment.link_type === 'maintenance') {
            section.style.display = 'none';
            return;
        }

        section.style.display = 'block';

        // Commissioning
        const commDone = document.getElementById('entryCommissioningDone');
        const commDate = document.getElementById('entryCommissioningDate');
        const commNote = document.getElementById('entryCommissioningNote');

        commDone.checked = !!entry?.commissioning_done;
        commDate.value = entry?.commissioning_date || '';
        commNote.value = entry?.commissioning_note || '';
        this.updateCommissioningUI(commDone.checked);

        // Acceptance (v4.5.2 - new logic)
        const doingAcc = document.getElementById('entryDoingAcceptance');
        const accDone = document.getElementById('entryAcceptanceDone');
        const accDate = document.getElementById('entryAcceptanceDate');
        const accDefects = document.getElementById('entryAcceptanceDefects');
        const accNote = document.getElementById('entryAcceptanceNote');

        // Determine acceptance state:
        // - acceptance_done=1, defect_free=1 → successful (mangelfrei)
        // - acceptance_done=1, defect_free=0 → successful with defects (mit Mängeln)
        // - acceptance_done=0 + note → not performed (wesentliche Mängel)
        // - acceptance_done=0, no note → not doing acceptance
        const wasDoingAcceptance = !!entry?.acceptance_done || !!(entry?.acceptance_note);
        const wasSuccessful = !!entry?.acceptance_done;
        const wasDefectFree = entry?.acceptance_defect_free !== 0; // DB default is 1
        const wasMitMaengeln = wasSuccessful && !wasDefectFree;

        doingAcc.checked = wasDoingAcceptance;
        accDone.checked = wasSuccessful;
        accDate.value = entry?.acceptance_date || '';

        const defectFreeChk = document.getElementById('entryAcceptanceDefectFree');
        if (defectFreeChk) defectFreeChk.checked = wasDefectFree;

        accNote.value = (wasSuccessful && wasDefectFree) ? (entry?.acceptance_note || '') : '';
        accDefects.value = (!wasSuccessful && wasDoingAcceptance) ? (entry?.acceptance_note || '') : '';
        const accWithDefects = document.getElementById('entryAcceptanceWithDefects');
        if (accWithDefects) accWithDefects.value = wasMitMaengeln ? (entry?.acceptance_note || '') : '';

        this.updateDoingAcceptanceUI(doingAcc.checked);
        if (doingAcc.checked) {
            this.updateAcceptanceSuccessUI(accDone.checked);
            if (wasSuccessful) this.updateAcceptanceDefectFreeUI(wasDefectFree);
        }

        // Instruction & Testbook
        document.getElementById('entryInstructionDone').checked = !!entry?.instruction_done;
        document.getElementById('entryTestbookHanded').checked = !!entry?.testbook_handed;
    }

    // Add new entry (v1.7)
    addNewEntry() {
        this.currentEntry = null;
        this.showView('viewEntry', 'Neuer Eintrag');

        document.getElementById('entryTitle').textContent = 'Neuer Eintrag';

        // Clear form
        document.getElementById('entryDate').value = this.formatDateInput(new Date());
        this.setTimeMode('duration');
        document.getElementById('entryHours').value = '';
        document.getElementById('entryMinutes').value = '0';
        document.getElementById('entryTimeStart').value = '';
        document.getElementById('entryTimeEnd').value = '';
        document.getElementById('timeRangePreview').textContent = '';
        document.getElementById('entryWorkDone').value = '';
        document.getElementById('entryIssuesFound').value = '';

        // Clear defect materials (v4.2) - hidden for new entries
        this.currentEntryDefectMaterials = [];
        document.getElementById('defectMaterialList').innerHTML = '';
        document.getElementById('defectMaterialSection').style.display = 'none';

        // Clear entry photo
        this.currentEntryPhoto = null;
        this.currentEntryPhotoData = null;
        this.updateEntryPhotoUI();

        // Clear commissioning/acceptance fields (v4.5)
        this.loadCommissioningAcceptanceFields(null);

        // Hide delete button for new entries
        document.getElementById('btnDeleteEntry').style.display = 'none';
    }

    // Time mode toggle
    setTimeMode(mode) {
        const isDuration = (mode === 'duration');
        document.getElementById('timeModeDuration').style.display = isDuration ? 'flex' : 'none';
        document.getElementById('timeModeRange').style.display = isDuration ? 'none' : 'block';
        document.getElementById('btnModeDuration').classList.toggle('active', isDuration);
        document.getElementById('btnModeRange').classList.toggle('active', !isDuration);
        this._timeMode = mode;
    }

    onTimeRangeChange() {
        const s = document.getElementById('entryTimeStart').value;
        const e = document.getElementById('entryTimeEnd').value;
        const el = document.getElementById('timeRangePreview');
        if (!s || !e) { el.textContent = ''; return; }
        const [sh, sm] = s.split(':').map(Number);
        const [eh, em] = e.split(':').map(Number);
        let startMin = sh * 60 + sm, endMin = eh * 60 + em;
        if (endMin < startMin) endMin += 24 * 60;
        const diff = endMin - startMin;
        const h = Math.floor(diff / 60), m = diff % 60;
        el.textContent = `= ${h}h${m > 0 ? ` ${m}min` : ''}`;
    }

    // Save entry (v1.7)
    async saveEntry() {
        const mode = this._timeMode || 'duration';
        let totalMinutes = 0;
        let workStartTime = null, workEndTime = null;

        if (mode === 'range') {
            const s = document.getElementById('entryTimeStart').value;
            const e = document.getElementById('entryTimeEnd').value;
            if (s && e) {
                workStartTime = s;
                workEndTime = e;
                const [sh, sm] = s.split(':').map(Number);
                const [eh, em] = e.split(':').map(Number);
                let startMin = sh * 60 + sm, endMin = eh * 60 + em;
                if (endMin < startMin) endMin += 24 * 60;
                totalMinutes = endMin - startMin;
            }
        } else {
            const hours = parseInt(document.getElementById('entryHours').value) || 0;
            const minutes = parseInt(document.getElementById('entryMinutes').value) || 0;
            totalMinutes = (hours * 60) + minutes;
        }

        const entryData = {
            intervention_id: this.currentIntervention.id,
            equipment_id: this.currentEquipment.id,
            work_date: document.getElementById('entryDate').value,
            work_start_time: workStartTime,
            work_end_time: workEndTime,
            work_duration: totalMinutes,
            work_done: document.getElementById('entryWorkDone').value,
            issues_found: document.getElementById('entryIssuesFound').value
        };

        // Add commissioning/acceptance fields for non-maintenance entries (v4.5.2)
        if (this.currentEquipment?.link_type !== 'maintenance') {
            const commDone = document.getElementById('entryCommissioningDone').checked;
            entryData.commissioning_done = commDone ? 1 : 0;
            entryData.commissioning_date = commDone ? document.getElementById('entryCommissioningDate').value : null;
            entryData.commissioning_note = !commDone ? document.getElementById('entryCommissioningNote').value : null;

            // Acceptance: new logic (v4.5.2)
            const doingAcceptance = document.getElementById('entryDoingAcceptance').checked;
            const accSuccessful = document.getElementById('entryAcceptanceDone').checked;

            if (doingAcceptance && accSuccessful) {
                const defectFree = document.getElementById('entryAcceptanceDefectFree').checked;
                entryData.acceptance_done = 1;
                entryData.acceptance_date = document.getElementById('entryAcceptanceDate').value || null;
                entryData.acceptance_defect_free = defectFree ? 1 : 0;
                if (defectFree) {
                    entryData.acceptance_note = document.getElementById('entryAcceptanceNote').value || null;
                } else {
                    entryData.acceptance_note = document.getElementById('entryAcceptanceWithDefects').value || null;
                }
            } else if (doingAcceptance && !accSuccessful) {
                // Abnahme durchgeführt aber nicht erfolgt (wesentliche Mängel)
                entryData.acceptance_done = 0;
                entryData.acceptance_date = null;
                entryData.acceptance_defect_free = 0;
                entryData.acceptance_note = document.getElementById('entryAcceptanceDefects').value || null;
            } else {
                // Keine Abnahme durchgeführt
                entryData.acceptance_done = 0;
                entryData.acceptance_date = null;
                entryData.acceptance_defect_free = 1;
                entryData.acceptance_note = null;
            }

            entryData.instruction_done = document.getElementById('entryInstructionDone').checked ? 1 : 0;
            entryData.testbook_handed = document.getElementById('entryTestbookHanded').checked ? 1 : 0;
        }

        // Add entry_id if editing existing entry
        if (this.currentEntry && this.currentEntry.id) {
            entryData.entry_id = this.currentEntry.id;
        }

        // Handle photo deletion flag (photo upload is done separately)
        if (this.currentEntryPhoto === null && this.currentEntry?.photo) {
            entryData.delete_photo = true;
        }

        // Try to sync if online
        if (this.isOnline) {
            try {
                // Save entry first
                const response = await this.apiCall(`detail/${entryData.intervention_id}/${entryData.equipment_id}`, {
                    method: 'POST',
                    body: JSON.stringify(entryData)
                });

                // Upload photo separately if new photo was captured
                if (this.currentEntryPhotoData && response.id) {
                    try {
                        await this.apiCall(`entry-photo/${entryData.intervention_id}/${response.id}`, {
                            method: 'POST',
                            body: JSON.stringify({
                                image: this.currentEntryPhotoData
                            })
                        });
                    } catch (photoErr) {
                        console.error('Photo upload failed:', photoErr);
                        this.showToast('Entry gespeichert, aber Foto-Upload fehlgeschlagen');
                    }
                }

                this.showToast('Gespeichert');

                // Go back to entries list and refresh
                this.loadEntries(this.currentEquipment);
            } catch (err) {
                console.error('Save failed:', err);
                this.showToast('Fehler beim Speichern');
            }
        } else {
            this.showToast('Offline - Speichern nicht möglich');
        }
    }

    // Update entry photo UI
    updateEntryPhotoUI() {
        const preview = document.getElementById('entryPhotoPreview');
        const addBtn = document.getElementById('btnAddEntryPhoto');
        const img = document.getElementById('entryPhotoImg');

        if (this.currentEntryPhotoData) {
            // New photo captured (base64)
            preview.style.display = 'block';
            addBtn.style.display = 'none';
            img.src = this.currentEntryPhotoData;
        } else if (this.currentEntryPhoto) {
            // Existing photo from server
            preview.style.display = 'block';
            addBtn.style.display = 'none';
            img.src = this.getEntryPhotoUrl(this.currentEntryPhoto);
        } else {
            // No photo
            preview.style.display = 'none';
            addBtn.style.display = 'flex';
            img.src = '';
        }
    }

    // Get URL for entry photo
    getEntryPhotoUrl(filename) {
        if (!filename || !this.currentIntervention) return '';
        return `${CONFIG.apiBase}/entry-photo/${this.currentIntervention.id}/file/${filename}`;
    }

    // Capture entry photo - let iOS/browser handle source selection
    captureEntryPhoto() {
        const fileInput = document.createElement('input');
        fileInput.type = 'file';
        fileInput.accept = 'image/*';

        fileInput.onchange = async (e) => {
            const file = e.target.files[0];
            if (!file) return;

            try {
                const base64Data = await this.readFileAsBase64(file);
                // Show cropper
                this.showPhotoCropper(base64Data);
            } catch (err) {
                console.error('Failed to read photo:', err);
                this.showToast('Fehler beim Laden des Fotos');
            }
        };

        fileInput.click();
    }

    // Show photo cropper overlay
    showPhotoCropper(imageData) {
        const overlay = document.createElement('div');
        overlay.className = 'crop-overlay';
        overlay.id = 'cropOverlay';

        overlay.innerHTML = `
            <div class="crop-title">Bildausschnitt wählen</div>
            <div class="crop-container" id="cropContainer">
                <img id="cropImage" src="${imageData}" alt="Zuschneiden">
                <div class="crop-box" id="cropBox">
                    <div class="crop-handle crop-handle-se" id="cropHandle"></div>
                </div>
            </div>
            <div class="crop-buttons">
                <button class="crop-btn crop-btn-cancel" onclick="app.cancelCrop()">Abbrechen</button>
                <button class="crop-btn crop-btn-confirm" onclick="app.confirmCrop()">Zuschneiden</button>
            </div>
        `;

        document.body.appendChild(overlay);

        // Wait for image to load then initialize cropper
        const img = document.getElementById('cropImage');
        img.onload = () => this.initCropper();
        if (img.complete) this.initCropper();
    }

    // Initialize cropper box and handlers
    initCropper() {
        const container = document.getElementById('cropContainer');
        const img = document.getElementById('cropImage');
        const box = document.getElementById('cropBox');
        const handle = document.getElementById('cropHandle');

        const imgRect = img.getBoundingClientRect();
        const containerRect = container.getBoundingClientRect();

        // Initial crop box size (60% of smaller dimension, centered)
        const size = Math.min(imgRect.width, imgRect.height) * 0.6;
        const left = (imgRect.width - size) / 2;
        const top = (imgRect.height - size) / 2;

        box.style.width = size + 'px';
        box.style.height = size + 'px';
        box.style.left = left + 'px';
        box.style.top = top + 'px';

        // Store image dimensions for cropping
        this.cropData = {
            imgWidth: img.naturalWidth,
            imgHeight: img.naturalHeight,
            displayWidth: imgRect.width,
            displayHeight: imgRect.height
        };

        // Drag crop box
        let isDragging = false;
        let isResizing = false;
        let startX, startY, startLeft, startTop, startWidth, startHeight;

        const getEventPos = (e) => {
            if (e.touches) return { x: e.touches[0].clientX, y: e.touches[0].clientY };
            return { x: e.clientX, y: e.clientY };
        };

        // Handle move/resize start
        handle.addEventListener('mousedown', (e) => { isResizing = true; e.stopPropagation(); startResize(e); });
        handle.addEventListener('touchstart', (e) => { isResizing = true; e.stopPropagation(); startResize(e); });

        box.addEventListener('mousedown', startDrag);
        box.addEventListener('touchstart', startDrag);

        function startDrag(e) {
            if (isResizing) return;
            isDragging = true;
            const pos = getEventPos(e);
            startX = pos.x;
            startY = pos.y;
            startLeft = box.offsetLeft;
            startTop = box.offsetTop;
            e.preventDefault();
        }

        function startResize(e) {
            const pos = getEventPos(e);
            startX = pos.x;
            startY = pos.y;
            startWidth = box.offsetWidth;
            startHeight = box.offsetHeight;
            e.preventDefault();
        }

        document.addEventListener('mousemove', onMove);
        document.addEventListener('touchmove', onMove);
        document.addEventListener('mouseup', onEnd);
        document.addEventListener('touchend', onEnd);

        function onMove(e) {
            if (!isDragging && !isResizing) return;
            const pos = getEventPos(e);
            const dx = pos.x - startX;
            const dy = pos.y - startY;

            if (isResizing) {
                // Keep square aspect ratio
                const delta = Math.max(dx, dy);
                let newSize = Math.max(50, startWidth + delta);
                newSize = Math.min(newSize, imgRect.width - box.offsetLeft, imgRect.height - box.offsetTop);
                box.style.width = newSize + 'px';
                box.style.height = newSize + 'px';
            } else if (isDragging) {
                let newLeft = Math.max(0, Math.min(startLeft + dx, imgRect.width - box.offsetWidth));
                let newTop = Math.max(0, Math.min(startTop + dy, imgRect.height - box.offsetHeight));
                box.style.left = newLeft + 'px';
                box.style.top = newTop + 'px';
            }
            e.preventDefault();
        }

        function onEnd() {
            isDragging = false;
            isResizing = false;
        }

        // Store cleanup function
        this.cropCleanup = () => {
            document.removeEventListener('mousemove', onMove);
            document.removeEventListener('touchmove', onMove);
            document.removeEventListener('mouseup', onEnd);
            document.removeEventListener('touchend', onEnd);
        };
    }

    // Cancel cropping
    cancelCrop() {
        if (this.cropCleanup) this.cropCleanup();
        document.getElementById('cropOverlay')?.remove();
    }

    // Confirm and apply crop
    confirmCrop() {
        const img = document.getElementById('cropImage');
        const box = document.getElementById('cropBox');

        // Calculate crop coordinates in original image dimensions
        const scaleX = this.cropData.imgWidth / this.cropData.displayWidth;
        const scaleY = this.cropData.imgHeight / this.cropData.displayHeight;

        const cropX = box.offsetLeft * scaleX;
        const cropY = box.offsetTop * scaleY;
        const cropW = box.offsetWidth * scaleX;
        const cropH = box.offsetHeight * scaleY;

        // Create canvas and crop
        const canvas = document.createElement('canvas');
        canvas.width = cropW;
        canvas.height = cropH;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(img, cropX, cropY, cropW, cropH, 0, 0, cropW, cropH);

        // Get cropped image as base64
        const croppedData = canvas.toDataURL('image/jpeg', 0.85);

        // Set as entry photo
        this.currentEntryPhotoData = croppedData;
        this.currentEntryPhoto = 'new';
        this.updateEntryPhotoUI();
        this.showToast('Foto zugeschnitten');

        // Cleanup
        this.cancelCrop();
    }

    // Delete entry photo
    deleteEntryPhoto() {
        if (!confirm('Foto wirklich löschen?')) return;

        this.currentEntryPhoto = null;
        this.currentEntryPhotoData = null;
        this.updateEntryPhotoUI();
        this.showToast('Foto wird beim Speichern gelöscht');
    }

    // View entry photo fullscreen
    viewEntryPhoto() {
        let url;
        if (this.currentEntryPhotoData) {
            url = this.currentEntryPhotoData;
        } else if (this.currentEntryPhoto) {
            url = this.getEntryPhotoUrl(this.currentEntryPhoto);
        }
        if (!url) return;

        const overlay = document.createElement('div');
        overlay.className = 'defect-photo-overlay';
        overlay.innerHTML = `
            <div class="defect-photo-fullscreen">
                <button class="defect-photo-close" onclick="this.parentElement.parentElement.remove()">✕</button>
                <img src="${url}" alt="Mangel-Foto">
            </div>
        `;
        overlay.onclick = (e) => {
            if (e.target === overlay) overlay.remove();
        };
        document.body.appendChild(overlay);
    }

    // ========== DEFECT MATERIALS (v4.2) ==========

    // Show defect material modal
    showDefectMaterialModal() {
        if (!this.currentEntry || !this.currentEntry.id) {
            this.showToast('Bitte zuerst den Eintrag speichern');
            return;
        }
        document.getElementById('defectMaterialModal').classList.add('show');

        // Reset to product mode
        this.defectMaterialMode = 'product';
        this.switchDefectMaterialMode('product');

        document.getElementById('defectProductSearch').value = '';
        document.getElementById('defectProductResults').innerHTML = '';
        document.getElementById('defectProductResults').classList.remove('show');
        document.getElementById('defectSelectedProduct').style.display = 'none';
        document.getElementById('defectProductId').value = '';
        document.getElementById('defectFreetextLabel').value = '';
        document.getElementById('defectMaterialQty').value = '1';

        // Setup search listener
        const searchInput = document.getElementById('defectProductSearch');
        searchInput.oninput = () => this.searchDefectProducts();
        searchInput.focus();
    }

    // v4.3: Switch between product search and freetext mode
    switchDefectMaterialMode(mode) {
        this.defectMaterialMode = mode;

        const productMode = document.getElementById('defectProductMode');
        const freetextMode = document.getElementById('defectFreetextMode');
        const tabProduct = document.getElementById('defectTabProduct');
        const tabFreetext = document.getElementById('defectTabFreetext');

        if (!productMode || !freetextMode || !tabProduct || !tabFreetext) {
            return;
        }

        if (mode === 'product') {
            productMode.style.display = 'block';
            freetextMode.style.display = 'none';
            tabProduct.classList.add('active');
            tabFreetext.classList.remove('active');
            const freetextInput = document.getElementById('defectFreetextLabel');
            if (freetextInput) freetextInput.value = '';
        } else {
            productMode.style.display = 'none';
            freetextMode.style.display = 'block';
            tabProduct.classList.remove('active');
            tabFreetext.classList.add('active');
            const productIdInput = document.getElementById('defectProductId');
            if (productIdInput) productIdInput.value = '';
            document.getElementById('defectSelectedProduct').style.display = 'none';
        }
    }

    // Close defect material modal
    closeDefectMaterialModal() {
        document.getElementById('defectMaterialModal').classList.remove('show');
    }

    // Search products for defect material
    async searchDefectProducts() {
        const search = document.getElementById('defectProductSearch').value.trim();
        const resultsDiv = document.getElementById('defectProductResults');

        if (search.length < 2) {
            resultsDiv.innerHTML = '';
            resultsDiv.classList.remove('show');
            return;
        }

        try {
            const response = await this.apiCall(`products?search=${encodeURIComponent(search)}`);
            const products = response.products || [];
            if (products.length > 0) {
                resultsDiv.innerHTML = products.map(p => `
                    <div class="product-result" onclick="app.selectDefectProduct(${p.id}, '${p.ref.replace(/'/g, "\\'")}', '${p.label.replace(/'/g, "\\'")}')">
                        <span class="product-ref">[${p.ref}]</span>
                        <span class="product-label">${p.label}</span>
                    </div>
                `).join('');
                resultsDiv.classList.add('show');
            } else {
                resultsDiv.innerHTML = '<div class="product-result" style="color: var(--text-secondary);">Keine Produkte gefunden</div>';
                resultsDiv.classList.add('show');
            }
        } catch (err) {
            console.error('Product search failed:', err);
        }
    }

    // Select a product for defect material
    selectDefectProduct(id, ref, label) {
        document.getElementById('defectProductId').value = id;
        document.getElementById('defectProductRef').textContent = '[' + ref + ']';
        document.getElementById('defectProductLabel').textContent = label;
        document.getElementById('defectSelectedProduct').style.display = 'block';
        document.getElementById('defectProductResults').classList.remove('show');
        document.getElementById('defectProductSearch').value = '';
    }

    // Clear selected product
    clearDefectProduct() {
        document.getElementById('defectProductId').value = '';
        document.getElementById('defectSelectedProduct').style.display = 'none';
    }

    // Save defect material (v4.3: supports product or freetext)
    async saveDefectMaterial() {
        const qty = parseInt(document.getElementById('defectMaterialQty').value) || 1;
        let body = { qty: qty };

        // v4.3: Check mode - product or freetext
        if (this.defectMaterialMode === 'freetext') {
            const freetextLabel = document.getElementById('defectFreetextLabel').value.trim();
            if (!freetextLabel) {
                this.showToast('Bitte eine Bezeichnung eingeben');
                return;
            }
            body.freetext_label = freetextLabel;
        } else {
            const productId = document.getElementById('defectProductId').value;
            if (!productId) {
                this.showToast('Bitte ein Produkt auswählen');
                return;
            }
            body.fk_product = parseInt(productId);
        }

        if (!this.currentEntry || !this.currentEntry.id) {
            this.showToast('Eintrag nicht gefunden');
            return;
        }

        // v4.3: Support offline saving for freetext materials
        if (this.isOnline) {
            try {
                const result = await this.apiCall(`defect-material/${this.currentEntry.id}`, {
                    method: 'POST',
                    body: JSON.stringify(body)
                });

                // Add to local list
                if (!this.currentEntryDefectMaterials) {
                    this.currentEntryDefectMaterials = [];
                }
                this.currentEntryDefectMaterials.push(result);

                this.renderDefectMaterials();
                this.closeDefectMaterialModal();
                this.showToast('Material hinzugefügt');
            } catch (err) {
                console.error('Failed to save defect material:', err);
                this.showToast('Fehler beim Speichern');
            }
        } else {
            // Offline mode - save to IndexedDB
            try {
                const savedMaterial = await offlineDB.saveDefectMaterial(this.currentEntry.id, body);

                // Add to local list with offline indicator
                if (!this.currentEntryDefectMaterials) {
                    this.currentEntryDefectMaterials = [];
                }
                this.currentEntryDefectMaterials.push(savedMaterial);

                this.renderDefectMaterials();
                this.closeDefectMaterialModal();
                this.showToast('Material offline gespeichert');
            } catch (err) {
                console.error('Failed to save defect material offline:', err);
                // Check if DB upgrade needed
                if (err.message && err.message.includes('DB upgrade')) {
                    this.showToast('Bitte Browser-Daten löschen und neu laden');
                } else {
                    this.showToast('Fehler beim Offline-Speichern: ' + (err.message || 'Unbekannt'));
                }
            }
        }
    }

    // Delete defect material (v4.3: supports local and server materials)
    async deleteDefectMaterial(materialId, type = 'server') {
        if (!confirm('Material wirklich entfernen?')) return;

        try {
            if (type === 'local') {
                // Delete from IndexedDB
                await offlineDB.deleteDefectMaterial(materialId);
                // Remove from local list by local_id
                this.currentEntryDefectMaterials = this.currentEntryDefectMaterials.filter(m => m.local_id !== materialId);
                this.showToast('Material entfernt');
            } else {
                // Delete from server
                if (this.isOnline) {
                    await this.apiCall(`defect-material/${materialId}`, {
                        method: 'DELETE'
                    });
                    this.showToast('Material entfernt');
                } else {
                    this.showToast('Offline - Löschen nicht möglich');
                    return;
                }
                // Remove from local list by server id
                this.currentEntryDefectMaterials = this.currentEntryDefectMaterials.filter(m => m.id !== materialId);
            }

            this.renderDefectMaterials();
        } catch (err) {
            console.error('Failed to delete defect material:', err);
            this.showToast('Fehler beim Löschen');
        }
    }

    // Render defect materials list (v4.3: with offline indicator)
    renderDefectMaterials() {
        const container = document.getElementById('defectMaterialList');
        const materials = this.currentEntryDefectMaterials || [];

        if (materials.length === 0) {
            container.innerHTML = '<div style="color: var(--text-secondary); font-size: 13px; padding: 8px 0;">Noch kein Material hinzugefügt</div>';
        } else {
            container.innerHTML = materials.map(m => {
                // Check if offline (has local_id but no server id or synced=false)
                const isOffline = m.local_id && !m.synced;
                const deleteId = m.id || m.local_id;
                const deleteType = m.id ? 'server' : 'local';

                return `
                <div class="defect-material-item${isOffline ? ' offline' : ''}">
                    <div class="defect-material-info">
                        <span class="defect-material-ref">[${m.product_ref}]${isOffline ? ' ⏳' : ''}</span>
                        <span class="defect-material-label">${m.product_label}</span>
                    </div>
                    <span class="defect-material-qty">${m.qty}x</span>
                    <button class="defect-material-delete" onclick="app.deleteDefectMaterial(${deleteId}, '${deleteType}')">✕</button>
                </div>
            `}).join('');
        }
    }

    // Load defect materials for entry (v4.3: with offline support)
    async loadDefectMaterials(entryId) {
        let serverMaterials = [];
        let offlineMaterials = [];

        // Try to load from server if online
        if (this.isOnline) {
            try {
                serverMaterials = await this.apiCall(`defect-material/${entryId}`) || [];
            } catch (err) {
                console.error('Failed to load defect materials from server:', err);
            }
        }

        // Always check for offline materials
        try {
            offlineMaterials = await offlineDB.getDefectMaterials(entryId) || [];
            // Filter only unsynced materials (synced ones are already in serverMaterials)
            offlineMaterials = offlineMaterials.filter(m => !m.synced);
        } catch (err) {
            console.error('Failed to load offline defect materials:', err);
        }

        // Combine: server materials + unsynced offline materials
        this.currentEntryDefectMaterials = [...serverMaterials, ...offlineMaterials];
        this.renderDefectMaterials();
    }

    // Show/hide defect material section based on issues_found
    updateDefectMaterialVisibility() {
        const issuesFound = document.getElementById('entryIssuesFound').value.trim();
        const section = document.getElementById('defectMaterialSection');
        if (issuesFound.length > 0 && this.currentEntry && this.currentEntry.id) {
            section.style.display = 'block';
        } else {
            section.style.display = 'none';
        }
    }

    // ========== END DEFECT MATERIALS ==========

    // Save summary (recommendations & notes) (v1.7)
    async saveSummary() {
        const summaryData = {
            intervention_id: this.currentIntervention.id,
            equipment_id: this.currentEquipment.id,
            recommendations: document.getElementById('summaryRecommendations').value,
            notes: document.getElementById('summaryNotes').value,
            save_summary_only: true
        };

        if (this.isOnline) {
            try {
                await this.apiCall(`detail/${summaryData.intervention_id}/${summaryData.equipment_id}`, {
                    method: 'POST',
                    body: JSON.stringify(summaryData)
                });
                this.showToast('Empfehlungen gespeichert');
            } catch (err) {
                console.error('Save summary failed:', err);
                this.showToast('Fehler beim Speichern');
            }
        } else {
            this.showToast('Offline - Speichern nicht möglich');
        }
    }

    // Delete entry (v1.7)
    async deleteEntry() {
        if (!this.currentEntry || !this.currentEntry.id) {
            this.showToast('Eintrag kann nicht gelöscht werden');
            return;
        }

        if (!confirm('Eintrag wirklich löschen?')) {
            return;
        }

        if (this.isOnline) {
            try {
                await this.apiCall(`detail/${this.currentIntervention.id}/${this.currentEquipment.id}?entry_id=${this.currentEntry.id}`, {
                    method: 'DELETE'
                });
                this.showToast('Eintrag gelöscht');

                // Go back to entries list and refresh
                this.loadEntries(this.currentEquipment);
            } catch (err) {
                console.error('Delete failed:', err);
                this.showToast('Fehler beim Löschen');
            }
        } else {
            this.showToast('Offline - Löschen nicht möglich');
        }
    }

    // Signature handling
    initSignature() {
        const container = document.getElementById('signatureCanvas');
        if (!container) {
            console.error('Signature canvas container not found');
            return;
        }

        container.innerHTML = '';

        // Initialize jSignature with explicit settings
        $(container).jSignature({
            color: '#000',
            'background-color': '#fff',
            'decor-color': 'transparent',
            lineWidth: 2,
            width: '100%',
            height: 200,
            cssclass: 'signature-canvas'
        });

        this.signatureInstance = $(container);
        // console.log('jSignature initialized');
    }

    clearSignature() {
        if (this.signatureInstance) {
            try {
                this.signatureInstance.jSignature('reset');
            } catch (e) {
                console.error('Error clearing signature:', e);
            }
        }
    }

    async saveSignature() {
        // console.log('saveSignature called, signatureInstance:', this.signatureInstance);

        if (!this.signatureInstance) {
            this.showToast('Unterschrift nicht initialisiert');
            return;
        }

        // Check if intervention is released
        const signedStatus = this.currentIntervention.signed_status || 0;
        if (signedStatus < 1) {
            this.showToast('Bitte zuerst freigeben');
            return;
        }

        const signerName = document.getElementById('signerName').value.trim();
        if (!signerName) {
            this.showToast('Bitte Name eingeben');
            return;
        }

        // Get signature data - try different methods
        let base64 = '';
        try {
            // Method 1: Try getData with 'image' format (returns data URL)
            const dataUrl = this.signatureInstance.jSignature('getData', 'image');
            // console.log('Signature dataUrl type:', typeof dataUrl, 'length:', dataUrl ? dataUrl.length : 0);

            if (dataUrl && typeof dataUrl === 'string' && dataUrl.includes('base64,')) {
                base64 = dataUrl.split('base64,')[1];
            } else if (Array.isArray(dataUrl) && dataUrl.length >= 2) {
                // Format: ['data:image/png;base64', 'actualdata']
                base64 = dataUrl[1];
            }
        } catch (e) {
            console.error('Error getting signature data (image):', e);
        }

        // Method 2: Fallback to base30 format
        if (!base64 || base64.length < 100) {
            try {
                const nativeData = this.signatureInstance.jSignature('getData', 'native');
                // console.log('Native data:', nativeData);
                if (nativeData && nativeData.length > 0) {
                    // There are strokes - try to get as base64 again
                    const b64Data = this.signatureInstance.jSignature('getData', 'base64');
                    if (Array.isArray(b64Data) && b64Data.length >= 2) {
                        base64 = b64Data[1];
                    }
                }
            } catch (e2) {
                console.error('Error getting native data:', e2);
            }
        }

        // console.log('Final base64 length:', base64 ? base64.length : 0);

        // Check if signature actually has content
        if (!base64 || base64.length < 100) {
            this.showToast('Bitte unterschreiben');
            return;
        }

        // Save to IndexedDB
        await offlineDB.saveSignature(this.currentIntervention.id, base64, signerName);

        // Try to sync if online
        if (this.isOnline) {
            try {
                const result = await this.apiCall(`signature/${this.currentIntervention.id}`, {
                    method: 'POST',
                    body: JSON.stringify({
                        signature: base64,
                        signer_name: signerName
                    })
                });
                this.currentIntervention.signed_status = 3;
                this.currentIntervention.status = 3; // Closed
                await this.loadInterventions();

                // Offer to send email (if auto-open enabled in settings, default: true)
                this.showToast('Unterschrift gespeichert – Auftrag abgeschlossen');
                this.showView('viewInterventions');
                if (localStorage.getItem('pwa_email_auto_open') !== 'false') {
                    setTimeout(() => this.showEmailModal(), 600);
                }
            } catch (err) {
                // Sync failed — saved in queue, show persistent warning
                this.showToast('⚠️ Offline gespeichert – Sync ausstehend!', 6000);
                this.updateSyncBadge();
                this.showView('viewInterventions');
            }
        } else {
            this.showToast('⚠️ Offline gespeichert – Sync ausstehend!', 6000);
            this.updateSyncBadge();
            this.showView('viewInterventions');
        }
    }

    // Sync data with server
    async syncData() {
        if (!this.isOnline) {
            this.showToast('Offline - Sync nicht möglich');
            return;
        }

        const statusEl = document.getElementById('syncStatus');
        statusEl.textContent = 'Sync...';
        statusEl.className = 'sync-status syncing';

        try {
            let syncedCount = 0;

            // 1. Get sync queue (details, signatures, link-equipment)
            const queue = await offlineDB.getSyncQueue();

            if (queue.length > 0) {
                // Separate different change types
                const linkEquipmentChanges = queue.filter(item => item.type === 'link-equipment');
                const defectMaterialChanges = queue.filter(item => item.type === 'defect_material');
                const otherChanges = queue.filter(item => item.type !== 'link-equipment' && item.type !== 'defect_material');

                // Sync link-equipment separately (direct API calls)
                for (const item of linkEquipmentChanges) {
                    try {
                        await this.apiCall('link-equipment', {
                            method: 'POST',
                            body: JSON.stringify(item.data)
                        });
                        syncedCount++;
                    } catch (err) {
                        console.warn('Failed to sync link-equipment:', err);
                    }
                }

                // v4.3: Sync defect materials separately
                for (const item of defectMaterialChanges) {
                    try {
                        const result = await this.apiCall(`defect-material/${item.data.entry_id}`, {
                            method: 'POST',
                            body: JSON.stringify({
                                fk_product: item.data.fk_product,
                                freetext_label: item.data.freetext_label,
                                qty: item.data.qty
                            })
                        });
                        // Mark local material as synced
                        if (item.data.local_id && result.id) {
                            await offlineDB.markDefectMaterialSynced(item.data.local_id, result.id);
                        }
                        syncedCount++;
                    } catch (err) {
                        console.warn('Failed to sync defect material:', err);
                    }
                }

                // Sync other changes via batch sync endpoint
                if (otherChanges.length > 0) {
                    const changes = otherChanges.map(item => ({
                        type: item.type,
                        data: item.data
                    }));

                    const result = await this.apiCall('sync', {
                        method: 'POST',
                        body: JSON.stringify({ changes })
                    });

                    if (result.status === 'ok' || result.status === 'partial') {
                        syncedCount += changes.length;
                    }
                }

                // Clear queue
                await offlineDB.clearSyncQueue();

                // Mark details as synced
                const details = await offlineDB.getAll('details');
                for (const detail of details) {
                    detail.synced = true;
                    await offlineDB.put('details', detail);
                }
            }

            // 2. Sync pending file uploads
            const pendingUploads = await offlineDB.getAllPendingUploads();
            if (pendingUploads.length > 0) {
                statusEl.textContent = 'Uploads...';

                for (const upload of pendingUploads) {
                    try {
                        // Convert base64 back to blob
                        const response = await fetch(upload.file_data);
                        const blob = await response.blob();
                        const file = new File([blob], upload.file_name, { type: upload.file_type });

                        const formData = new FormData();
                        formData.append('file', file);

                        const url = CONFIG.apiBase + '?route=' + encodeURIComponent(`intervention/${upload.intervention_id}/documents`);

                        const headers = {};
                        if (this.pwaToken) {
                            headers['X-PWA-Token'] = this.pwaToken;
                        }

                        const uploadResponse = await fetch(url, {
                            method: 'POST',
                            body: formData,
                            credentials: 'same-origin',
                            headers
                        });

                        if (uploadResponse.ok) {
                            // Remove from pending
                            await offlineDB.removePendingUpload(upload.id);
                            syncedCount++;
                        }
                    } catch (err) {
                        console.warn('Failed to upload file:', upload.file_name, err);
                    }
                }
            }

            if (syncedCount > 0) {
                this.showToast(`${syncedCount} Änderungen synchronisiert`);
            }

            // 3. Prefetch ALL data for offline use
            await this.prefetchAllData();

            this.showToast('Alle Daten synchronisiert');

        } catch (err) {
            console.error('Sync failed:', err);
            this.showToast('Sync fehlgeschlagen');
        }

        this.updateOnlineStatus();
        await this.updateSyncBadge();
    }

    // Prefetch all data for offline use
    async prefetchAllData() {
        if (!this.isOnline) return;

        const statusEl = document.getElementById('syncStatus');
        statusEl.textContent = 'Lade...';
        statusEl.className = 'sync-status syncing';

        try {
            // 1. Fetch all interventions
            const data = await this.apiCall('interventions?status=all');
            const interventions = data.interventions || [];
            await offlineDB.saveInterventions(interventions);

            // 2. For each intervention, fetch equipment, entries, available equipment, and documents
            const total = interventions.length;
            let current = 0;

            for (const intervention of interventions) {
                current++;
                statusEl.textContent = `Sync ${current}/${total}`;

                try {
                    // Fetch full intervention data with equipment
                    const fullData = await this.apiCall(`intervention/${intervention.id}`);
                    const equipment = fullData.equipment || [];

                    // Save equipment
                    await offlineDB.saveEquipment(intervention.id, equipment);

                    // 3. For each equipment, fetch entries/details
                    for (const eq of equipment) {
                        try {
                            const detailData = await this.apiCall(`detail/${intervention.id}/${eq.id}`);

                            // Save detail to IndexedDB
                            const detail = {
                                intervention_id: intervention.id,
                                equipment_id: eq.id,
                                entries: detailData.entries || [],
                                recommendations: detailData.recommendations || '',
                                notes: detailData.notes || '',
                                total_duration: detailData.total_duration || 0,
                                materials: eq.materials || [],
                                synced: true
                            };
                            await offlineDB.put('details', detail);
                        } catch (detailErr) {
                            console.warn(`Failed to fetch detail for equipment ${eq.id}:`, detailErr);
                        }
                    }

                    // 3b. Also fetch general entries (Allgemeine Arbeiten, equipment_id=0)
                    try {
                        const generalData = await this.apiCall(`detail/${intervention.id}/0`);
                        const generalDetail = {
                            intervention_id: intervention.id,
                            equipment_id: 0,
                            entries: generalData.entries || [],
                            recommendations: generalData.recommendations || '',
                            notes: generalData.notes || '',
                            total_duration: generalData.total_duration || 0,
                            materials: [],
                            synced: true
                        };
                        await offlineDB.put('details', generalDetail);
                    } catch (generalErr) {
                        console.warn(`Failed to fetch general entries for intervention ${intervention.id}:`, generalErr);
                    }

                    // 4. Fetch available equipment (all customer equipment not yet linked)
                    try {
                        const availData = await this.apiCall(`available-equipment/${intervention.id}`);
                        await offlineDB.saveAvailableEquipment(intervention.id, availData.equipment || []);
                    } catch (availErr) {
                        console.warn(`Failed to fetch available equipment for intervention ${intervention.id}:`, availErr);
                    }

                    // 5. Fetch document metadata
                    try {
                        const docsData = await this.apiCall(`intervention/${intervention.id}/documents`);
                        await offlineDB.saveDocuments(intervention.id, docsData.documents || []);
                    } catch (docsErr) {
                        console.warn(`Failed to fetch documents for intervention ${intervention.id}:`, docsErr);
                    }

                } catch (intErr) {
                    console.warn(`Failed to fetch data for intervention ${intervention.id}:`, intErr);
                }
            }

            // Save last sync time
            await offlineDB.setMeta('lastSync', Date.now());

        } catch (err) {
            console.error('Prefetch failed:', err);
            throw err;
        } finally {
            // Always update status when prefetch completes or fails
            this.updateOnlineStatus();
        }
    }

    // Utility functions
    formatDate(dateStr) {
        if (!dateStr) return '';
        const date = new Date(dateStr);
        return date.toLocaleDateString('de-DE', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric'
        });
    }

    // Format schedule for display (two lines: start / end)
    formatScheduleDisplay(dateStart, dateEnd) {
        if (!dateStart) return '<span style="color:var(--text-muted);font-style:italic;">Kein Termin</span>';
        const fmtOpts = { day: '2-digit', month: '2-digit', year: 'numeric' };
        const fmtTime = { hour: '2-digit', minute: '2-digit' };
        const ds = new Date(dateStart);
        const isAllDay = ds.getHours() === 0 && ds.getMinutes() === 0 &&
                         (!dateEnd || (new Date(dateEnd).getHours() === 23 && new Date(dateEnd).getMinutes() === 59));
        const startStr = isAllDay
            ? ds.toLocaleDateString('de-DE', fmtOpts)
            : ds.toLocaleDateString('de-DE', fmtOpts) + ' ' + ds.toLocaleTimeString('de-DE', fmtTime) + ' Uhr';
        if (!dateEnd) return startStr;
        const de = new Date(dateEnd);
        const endStr = isAllDay
            ? de.toLocaleDateString('de-DE', fmtOpts)
            : de.toLocaleDateString('de-DE', fmtOpts) + ' ' + de.toLocaleTimeString('de-DE', fmtTime) + ' Uhr';
        return startStr + '<br><span style="color:var(--text-muted);">' + endStr + '</span>';
    }

    // Parse DB date string (YYYY-MM-DD HH:MM:SS) into local date/time input values
    parseDateForInput(dbStr) {
        if (!dbStr) return { date: '', time: '' };
        // DB format: "2026-05-05 09:00:00"
        const parts = dbStr.replace('T', ' ').split(' ');
        return { date: parts[0] || '', time: (parts[1] || '').slice(0, 5) };
    }

    showScheduleModal(intervention) {
        const sStart = this.parseDateForInput(intervention.date_start);
        const sEnd   = this.parseDateForInput(intervention.date_end);
        const isAllDay = sStart.time === '00:00' && (!sEnd.time || sEnd.time === '23:59');

        // Build modal
        const overlay = document.createElement('div');
        overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;display:flex;align-items:flex-end;justify-content:center;';

        const sheet = document.createElement('div');
        sheet.style.cssText = 'background:var(--card-bg,#fff);border-radius:16px 16px 0 0;padding:20px;width:100%;max-width:480px;box-shadow:0 -4px 24px rgba(0,0,0,.2);';

        const title = document.createElement('h3');
        title.style.cssText = 'margin:0 0 16px;font-size:18px;';
        title.textContent = 'Termin bearbeiten';

        // All-day toggle
        const allDayLabel = document.createElement('label');
        allDayLabel.style.cssText = 'display:flex;align-items:center;gap:10px;margin-bottom:16px;font-size:15px;cursor:pointer;';
        const allDayChk = document.createElement('input');
        allDayChk.type = 'checkbox';
        allDayChk.style.cssText = 'width:18px;height:18px;';
        allDayChk.checked = isAllDay;
        allDayLabel.appendChild(allDayChk);
        allDayLabel.appendChild(document.createTextNode('Ganztägig'));

        // Start fields
        const startGroup = document.createElement('div');
        startGroup.style.marginBottom = '12px';
        const startLbl = document.createElement('div');
        startLbl.style.cssText = 'font-size:12px;color:var(--text-muted);margin-bottom:4px;';
        startLbl.textContent = 'Start';
        const startRow = document.createElement('div');
        startRow.style.cssText = 'display:flex;gap:8px;';
        const dateStartInp = document.createElement('input');
        dateStartInp.type = 'date';
        dateStartInp.value = sStart.date;
        dateStartInp.style.cssText = 'flex:1;padding:10px;border:1px solid #ddd;border-radius:8px;font-size:15px;background:var(--input-bg,#fff);color:var(--text,#000);';
        const timeStartInp = document.createElement('input');
        timeStartInp.type = 'time';
        timeStartInp.value = sStart.time || '08:00';
        timeStartInp.style.cssText = 'width:100px;padding:10px;border:1px solid #ddd;border-radius:8px;font-size:15px;background:var(--input-bg,#fff);color:var(--text,#000);';
        timeStartInp.style.display = isAllDay ? 'none' : '';
        startRow.appendChild(dateStartInp);
        startRow.appendChild(timeStartInp);
        startGroup.appendChild(startLbl);
        startGroup.appendChild(startRow);

        // End fields
        const endGroup = document.createElement('div');
        endGroup.style.marginBottom = '16px';
        const endLbl = document.createElement('div');
        endLbl.style.cssText = 'font-size:12px;color:var(--text-muted);margin-bottom:4px;';
        endLbl.textContent = 'Ende';
        const endRow = document.createElement('div');
        endRow.style.cssText = 'display:flex;gap:8px;';
        const dateEndInp = document.createElement('input');
        dateEndInp.type = 'date';
        dateEndInp.value = sEnd.date;
        dateEndInp.style.cssText = dateStartInp.style.cssText;
        const timeEndInp = document.createElement('input');
        timeEndInp.type = 'time';
        timeEndInp.value = sEnd.time || '17:00';
        timeEndInp.style.cssText = timeStartInp.style.cssText;
        timeEndInp.style.display = isAllDay ? 'none' : '';
        endRow.appendChild(dateEndInp);
        endRow.appendChild(timeEndInp);
        endGroup.appendChild(endLbl);
        endGroup.appendChild(endRow);

        // Auto-adjust end date when start date changes
        let prevStartMs = dateStartInp.value ? new Date(dateStartInp.value + 'T' + (timeStartInp.value || '00:00')).getTime() : null;
        dateStartInp.addEventListener('change', () => {
            const endMs = dateEndInp.value ? new Date(dateEndInp.value + 'T' + (timeEndInp.value || '00:00')).getTime() : null;
            const newStartMs = new Date(dateStartInp.value + 'T' + (timeStartInp.value || '00:00')).getTime();
            if (prevStartMs && endMs && newStartMs) {
                const delta = endMs - prevStartMs;
                const newEndMs = newStartMs + delta;
                const ned = new Date(newEndMs);
                dateEndInp.value = ned.getFullYear() + '-' + String(ned.getMonth()+1).padStart(2,'0') + '-' + String(ned.getDate()).padStart(2,'0');
                timeEndInp.value = String(ned.getHours()).padStart(2,'0') + ':' + String(ned.getMinutes()).padStart(2,'0');
            }
            prevStartMs = newStartMs;
        });

        // Toggle all-day
        allDayChk.addEventListener('change', () => {
            const hide = allDayChk.checked;
            timeStartInp.style.display = hide ? 'none' : '';
            timeEndInp.style.display   = hide ? 'none' : '';
        });

        // Error message
        const errEl = document.createElement('div');
        errEl.style.cssText = 'color:#e53935;font-size:13px;margin-bottom:8px;display:none;';

        // Buttons
        const btnRow = document.createElement('div');
        btnRow.style.cssText = 'display:flex;gap:10px;';
        const cancelBtn = document.createElement('button');
        cancelBtn.textContent = 'Abbrechen';
        cancelBtn.style.cssText = 'flex:1;padding:12px;border:1px solid #ddd;border-radius:8px;background:transparent;font-size:15px;cursor:pointer;';
        const saveBtn = document.createElement('button');
        saveBtn.textContent = 'Speichern';
        saveBtn.style.cssText = 'flex:1;padding:12px;border:none;border-radius:8px;background:var(--primary,#2196F3);color:#fff;font-size:15px;font-weight:600;cursor:pointer;';

        const closeModal = () => overlay.remove();
        cancelBtn.addEventListener('click', closeModal);
        overlay.addEventListener('click', (e) => { if (e.target === overlay) closeModal(); });

        saveBtn.addEventListener('click', async () => {
            if (!dateStartInp.value) {
                errEl.textContent = 'Startdatum ist erforderlich.';
                errEl.style.display = 'block';
                return;
            }
            saveBtn.disabled = true;
            saveBtn.textContent = '…';
            try {
                const payload = {
                    date_start: dateStartInp.value,
                    time_start: allDayChk.checked ? '' : timeStartInp.value,
                    date_end:   dateEndInp.value,
                    time_end:   allDayChk.checked ? '' : timeEndInp.value,
                    allday:     allDayChk.checked,
                };
                const result = await this.apiCall('schedule/' + intervention.id, {
                    method: 'POST',
                    body: JSON.stringify(payload),
                });
                if (result.status === 'ok') {
                    // Update in-memory intervention
                    intervention.date_start = result.date_start;
                    intervention.date_end   = result.date_end;
                    // Update display in info header
                    const textEl = document.getElementById('infoTerminText_' + intervention.id);
                    if (textEl) textEl.innerHTML = this.formatScheduleDisplay(result.date_start, result.date_end);
                    // Also update the card in the intervention list
                    const idx = this.allInterventions.findIndex(i => i.id === intervention.id);
                    if (idx >= 0) {
                        this.allInterventions[idx].date_start = result.date_start;
                        this.allInterventions[idx].date_end   = result.date_end;
                    }
                    closeModal();
                    this.showToast('Termin gespeichert');
                } else {
                    throw new Error(result.error || 'Fehler');
                }
            } catch (err) {
                errEl.textContent = 'Fehler: ' + err.message;
                errEl.style.display = 'block';
                saveBtn.disabled = false;
                saveBtn.textContent = 'Speichern';
            }
        });

        btnRow.appendChild(cancelBtn);
        btnRow.appendChild(saveBtn);
        sheet.appendChild(title);
        sheet.appendChild(allDayLabel);
        sheet.appendChild(startGroup);
        sheet.appendChild(endGroup);
        sheet.appendChild(errEl);
        sheet.appendChild(btnRow);
        overlay.appendChild(sheet);
        document.body.appendChild(overlay);
    }

    formatDateInput(date) {
        return date.toISOString().split('T')[0];
    }

    /**
     * Generate a maps URL from address components
     * Opens in Apple Maps on iOS, Google Maps on Android/Desktop
     */
    getMapsUrl(address, zip, town) {
        const parts = [];
        if (address) parts.push(address);
        if (zip) parts.push(zip);
        if (town) parts.push(town);

        if (parts.length === 0) return null;

        const query = encodeURIComponent(parts.join(', '));
        // Universal link that works on iOS (Apple Maps) and Android/Desktop (Google Maps)
        return `https://maps.apple.com/?q=${query}`;
    }

    /**
     * Create a clickable address link
     */
    renderAddressLink(address, zip, town, additionalClasses = '') {
        const mapsUrl = this.getMapsUrl(address, zip, town);
        const addressText = `${address || ''}<br>${zip || ''} ${town || ''}`.trim();

        if (!mapsUrl || !addressText) return addressText;

        return `<a href="${mapsUrl}" target="_blank" rel="noopener" onclick="event.stopPropagation();" class="address-link ${additionalClasses}" title="In Karten öffnen">${addressText}</a>`;
    }

    showToast(message, duration = 3000) {
        const toast = document.getElementById('toast');
        toast.textContent = message;
        toast.classList.add('show');

        setTimeout(() => {
            toast.classList.remove('show');
        }, duration);
    }

    async updateSyncBadge() {
        try {
            const queue = await offlineDB.getSyncQueue();
            const statusEl = document.getElementById('syncStatus');
            if (queue.length > 0) {
                statusEl.textContent = `⚠️ ${queue.length} ausstehend`;
                statusEl.className = 'sync-status pending';
            } else {
                this.updateOnlineStatus();
            }
        } catch (e) {
            // ignore
        }
    }

    // Material management
    renderMaterials(materials) {
        const container = document.getElementById('materialsList');

        if (!materials || materials.length === 0) {
            container.innerHTML = `
                <div class="empty-state" style="padding: 20px 0;">
                    <p style="margin: 0; color: #666;">Kein Material erfasst</p>
                </div>
            `;
            return;
        }

        container.innerHTML = '';
        materials.forEach((material, index) => {
            const item = document.createElement('div');
            item.className = 'material-item';
            item.innerHTML = `
                <div class="material-info">
                    <div class="material-name">${material.name}</div>
                    <div class="material-details">
                        ${material.quantity} ${material.unit}
                        ${material.description ? ' - ' + material.description : ''}
                    </div>
                </div>
                <div class="material-price">${this.formatPrice(material.total_price || (material.quantity * material.unit_price))} €</div>
                <button type="button" class="material-delete" data-index="${index}" title="Löschen">🗑</button>
            `;

            item.querySelector('.material-delete').addEventListener('click', (e) => {
                e.stopPropagation();
                this.deleteMaterial(index);
            });

            container.appendChild(item);
        });
    }

    showMaterialModal() {
        // Reset form
        document.getElementById('productSearch').value = '';
        document.getElementById('productResults').classList.remove('show');
        document.getElementById('productResults').innerHTML = '';
        document.getElementById('materialName').value = '';
        document.getElementById('materialDescription').value = '';
        document.getElementById('materialQty').value = '1';
        document.getElementById('materialUnit').value = 'Stk';
        document.getElementById('materialPrice').value = '';
        document.getElementById('materialSerial').value = '';
        document.getElementById('materialNotes').value = '';

        document.getElementById('materialModal').classList.add('show');
    }

    closeMaterialModal() {
        document.getElementById('materialModal').classList.remove('show');
    }

    async saveMaterial() {
        const name = document.getElementById('materialName').value.trim();
        if (!name) {
            this.showToast('Bitte Bezeichnung eingeben');
            return;
        }

        const quantity = parseFloat(document.getElementById('materialQty').value) || 1;
        const unitPrice = parseFloat(document.getElementById('materialPrice').value) || 0;

        const material = {
            intervention_id: this.currentIntervention.id,
            equipment_id: this.currentEquipment.id,
            material_name: name,
            material_description: document.getElementById('materialDescription').value.trim(),
            quantity: quantity,
            unit: document.getElementById('materialUnit').value,
            unit_price: unitPrice,
            total_price: quantity * unitPrice,
            serial_number: document.getElementById('materialSerial').value.trim(),
            notes: document.getElementById('materialNotes').value.trim()
        };

        // Add to current equipment's materials
        if (!this.currentEquipment.materials) {
            this.currentEquipment.materials = [];
        }

        // Convert for display
        const displayMaterial = {
            name: material.material_name,
            description: material.material_description,
            quantity: material.quantity,
            unit: material.unit,
            unit_price: material.unit_price,
            total_price: material.total_price,
            serial_number: material.serial_number,
            notes: material.notes
        };

        this.currentEquipment.materials.push(displayMaterial);
        this.renderMaterials(this.currentEquipment.materials);
        this.closeMaterialModal();

        // Save to server if online
        if (this.isOnline) {
            try {
                await this.apiCall('material', {
                    method: 'POST',
                    body: JSON.stringify(material)
                });
                this.showToast('Material gespeichert');
            } catch (err) {
                this.showToast('Material offline gespeichert');
            }
        } else {
            this.showToast('Material offline gespeichert');
        }
    }

    async deleteMaterial(index) {
        if (!confirm('Material wirklich löschen?')) {
            return;
        }

        const material = this.currentEquipment.materials[index];
        this.currentEquipment.materials.splice(index, 1);
        this.renderMaterials(this.currentEquipment.materials);

        // Delete on server if has ID and online
        if (material.id && this.isOnline) {
            try {
                await this.apiCall(`material/${material.id}`, {
                    method: 'DELETE'
                });
                this.showToast('Material gelöscht');
            } catch (err) {
                this.showToast('Fehler beim Löschen');
            }
        }
    }

    formatPrice(value) {
        return parseFloat(value || 0).toFixed(2).replace('.', ',');
    }

    // Product search
    async searchProducts(query) {
        const resultsEl = document.getElementById('productResults');

        if (!query || query.length < 2) {
            resultsEl.classList.remove('show');
            resultsEl.innerHTML = '';
            return;
        }

        try {
            const data = await this.apiCall(`products?search=${encodeURIComponent(query)}`);
            const products = data.products || [];

            if (products.length === 0) {
                resultsEl.innerHTML = '<div class="product-item"><em>Keine Produkte gefunden</em></div>';
            } else {
                resultsEl.innerHTML = products.map(p => `
                    <div class="product-item" data-id="${p.id}" data-ref="${p.ref}" data-label="${p.label}" data-price="${p.price}">
                        <div class="product-ref">${p.ref}</div>
                        <div class="product-label">${p.label}</div>
                        <div class="product-price">${this.formatPrice(p.price)} €</div>
                    </div>
                `).join('');

                // Add click handlers
                resultsEl.querySelectorAll('.product-item').forEach(item => {
                    item.addEventListener('click', () => this.selectProduct(item));
                });
            }

            resultsEl.classList.add('show');
        } catch (err) {
            console.error('Product search failed:', err);
            resultsEl.innerHTML = '<div class="product-item"><em>Fehler bei der Suche</em></div>';
            resultsEl.classList.add('show');
        }
    }

    selectProduct(item) {
        const ref = item.dataset.ref;
        const label = item.dataset.label;
        const price = item.dataset.price;

        document.getElementById('materialName').value = label;
        document.getElementById('materialPrice').value = price;
        document.getElementById('productSearch').value = ref + ' - ' + label;
        document.getElementById('productResults').classList.remove('show');
    }

    // Equipment modal
    async showEquipmentModal() {
        document.getElementById('equipmentModal').classList.add('show');
        document.getElementById('equipmentModalFooter').style.display = 'none';
        this.selectedEquipment = []; // Reset selection
        this.availableEquipmentData = []; // Store equipment data for multi-select

        const listEl = document.getElementById('availableEquipmentList');
        listEl.innerHTML = `
            <div class="loading">
                <div class="spinner"></div>
                <p>Lade verfügbare Anlagen...</p>
            </div>
        `;

        try {
            let equipment = [];
            let loadedFromCache = false;

            if (this.isOnline) {
                try {
                    const data = await this.apiCall(`available-equipment/${this.currentIntervention.id}`);
                    equipment = data.equipment || [];
                    // Cache for offline use
                    await offlineDB.saveAvailableEquipment(this.currentIntervention.id, equipment);
                } catch (apiErr) {
                    console.warn('API call failed, falling back to cache:', apiErr);
                    this.isOnline = false;
                    this.updateOnlineStatus();
                    equipment = await offlineDB.getAvailableEquipment(this.currentIntervention.id);
                    loadedFromCache = true;
                }
            } else {
                // Load from cache when offline
                equipment = await offlineDB.getAvailableEquipment(this.currentIntervention.id);
                loadedFromCache = true;
            }

            this.availableEquipmentData = equipment; // Store for multi-select

            if (loadedFromCache && equipment.length > 0) {
                this.showToast('Offline-Daten geladen');
            }

            if (equipment.length === 0) {
                listEl.innerHTML = `
                    <div class="empty-state" style="padding: 20px 0;">
                        <p>Keine weiteren Anlagen verfügbar</p>
                    </div>
                `;
                return;
            }

            // Group by address
            const byAddress = {};
            equipment.forEach(eq => {
                const addrKey = eq.address?.town || 'Ohne Adresse';
                if (!byAddress[addrKey]) {
                    byAddress[addrKey] = {
                        address: eq.address,
                        equipment: []
                    };
                }
                byAddress[addrKey].equipment.push(eq);
            });

            listEl.innerHTML = '';

            // Show offline indicator if offline
            if (!this.isOnline) {
                const offlineNote = document.createElement('div');
                offlineNote.className = 'offline-note';
                offlineNote.textContent = '📴 Offline - Verknüpfung wird bei Verbindung synchronisiert';
                listEl.appendChild(offlineNote);
            }

            Object.keys(byAddress).forEach(addrKey => {
                const group = byAddress[addrKey];

                // Address header with select all checkbox
                const header = document.createElement('div');
                header.className = 'address-header';
                header.style.display = 'flex';
                header.style.alignItems = 'center';
                header.style.gap = '8px';
                const addressIds = group.equipment.map(eq => eq.id);
                const mapsUrl = this.getMapsUrl(group.address?.address, group.address?.zip, group.address?.town);
                const addressText = `${group.address?.name || ''} - ${group.address?.zip || ''} ${group.address?.town || ''}`;
                header.innerHTML = `
                    <input type="checkbox" class="address-select-all" data-address="${addrKey}" style="width:18px;height:18px;">
                    ${mapsUrl
                        ? `<a href="${mapsUrl}" target="_blank" rel="noopener" onclick="event.stopPropagation();" class="address-link" title="In Karten öffnen">📍 ${addressText}</a>`
                        : `<span>📍 ${addressText}</span>`
                    }
                `;
                header.querySelector('.address-select-all').addEventListener('change', (e) => {
                    const checked = e.target.checked;
                    addressIds.forEach(id => {
                        const checkbox = document.querySelector(`.equipment-checkbox[data-id="${id}"]`);
                        if (checkbox) checkbox.checked = checked;
                    });
                    this.updateEquipmentSelection();
                });
                listEl.appendChild(header);

                // Equipment items
                group.equipment.forEach(eq => {
                    const item = document.createElement('div');
                    item.className = 'equipment-item';
                    item.style.cursor = 'pointer';
                    item.innerHTML = `
                        <input type="checkbox" class="equipment-checkbox" data-id="${eq.id}" style="width:18px;height:18px;margin-right:8px;">
                        <div class="equipment-icon">🚪</div>
                        <div class="equipment-info" style="flex:1;">
                            <div class="equipment-ref">${eq.ref}</div>
                            <div class="equipment-label">${eq.label || eq.type || ''}</div>
                            ${eq.location ? `<div class="equipment-label">${eq.location}</div>` : ''}
                        </div>
                        <div style="display:flex;gap:8px;">
                            <button class="btn btn-primary" style="padding:6px 10px;font-size:12px;" data-type="service">S</button>
                            <button class="btn" style="padding:6px 10px;font-size:12px;background:#4caf50;color:white;" data-type="maintenance">W</button>
                        </div>
                    `;

                    // Checkbox handling
                    const checkbox = item.querySelector('.equipment-checkbox');
                    checkbox.addEventListener('click', (e) => e.stopPropagation());
                    checkbox.addEventListener('change', () => this.updateEquipmentSelection());

                    // Click on row toggles checkbox
                    item.addEventListener('click', (e) => {
                        if (e.target.tagName !== 'BUTTON' && e.target.tagName !== 'INPUT') {
                            checkbox.checked = !checkbox.checked;
                            this.updateEquipmentSelection();
                        }
                    });

                    item.querySelectorAll('button').forEach(btn => {
                        btn.addEventListener('click', (e) => {
                            e.stopPropagation();
                            this.linkEquipment(eq.id, btn.dataset.type, eq);
                        });
                    });

                    listEl.appendChild(item);
                });
            });

        } catch (err) {
            console.error('Failed to load available equipment:', err);
            // Try to load from cache on error
            try {
                const cachedEquipment = await offlineDB.getAvailableEquipment(this.currentIntervention.id);
                if (cachedEquipment.length > 0) {
                    this.showToast('Offline-Daten geladen');
                    // Recursively render with cached data
                    return this.renderAvailableEquipmentList(cachedEquipment, listEl);
                }
            } catch (cacheErr) {
                console.error('Cache load also failed:', cacheErr);
            }
            listEl.innerHTML = `
                <div class="empty-state" style="padding: 20px 0;">
                    <p>Fehler beim Laden</p>
                </div>
            `;
        }
    }

    renderAvailableEquipmentList(equipment, listEl) {
        if (equipment.length === 0) {
            listEl.innerHTML = `
                <div class="empty-state" style="padding: 20px 0;">
                    <p>Keine weiteren Anlagen verfügbar</p>
                </div>
            `;
            return;
        }

        // Group by address
        const byAddress = {};
        equipment.forEach(eq => {
            const addrKey = eq.address?.town || 'Ohne Adresse';
            if (!byAddress[addrKey]) {
                byAddress[addrKey] = {
                    address: eq.address,
                    equipment: []
                };
            }
            byAddress[addrKey].equipment.push(eq);
        });

        listEl.innerHTML = '';

        Object.keys(byAddress).forEach(addrKey => {
            const group = byAddress[addrKey];

            const header = document.createElement('div');
            header.style.cssText = 'padding:12px;background:#f5f5f5;font-weight:600;font-size:13px;border-bottom:1px solid #ddd;';
            const mapsUrl = this.getMapsUrl(group.address?.address, group.address?.zip, group.address?.town);
            const addressText = `${group.address?.name || ''} - ${group.address?.zip || ''} ${group.address?.town || ''}`;
            header.innerHTML = mapsUrl
                ? `<a href="${mapsUrl}" target="_blank" rel="noopener" class="address-link" title="In Karten öffnen">📍 ${addressText}</a>`
                : `📍 ${addressText}`;
            listEl.appendChild(header);

            group.equipment.forEach(eq => {
                const item = document.createElement('div');
                item.className = 'equipment-item';
                item.style.cursor = 'pointer';
                item.innerHTML = `
                    <div class="equipment-icon">🚪</div>
                    <div class="equipment-info">
                        <div class="equipment-ref">${eq.ref}</div>
                        <div class="equipment-label">${eq.label || eq.type || ''}</div>
                        ${eq.location ? `<div class="equipment-label">${eq.location}</div>` : ''}
                    </div>
                    <div style="display:flex;gap:8px;">
                        <button class="btn btn-primary" style="padding:6px 10px;font-size:12px;" data-type="service">Service</button>
                        <button class="btn" style="padding:6px 10px;font-size:12px;background:#4caf50;color:white;" data-type="maintenance">Wartung</button>
                    </div>
                `;

                item.querySelectorAll('button').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        this.linkEquipment(eq.id, btn.dataset.type, eq);
                    });
                });

                listEl.appendChild(item);
            });
        });
    }

    closeEquipmentModal() {
        document.getElementById('equipmentModal').classList.remove('show');
        document.getElementById('equipmentModalFooter').style.display = 'none';
        this.selectedEquipment = [];
    }

    // Update selection state and show/hide footer
    updateEquipmentSelection() {
        const checkboxes = document.querySelectorAll('.equipment-checkbox:checked');
        this.selectedEquipment = Array.from(checkboxes).map(cb => parseInt(cb.dataset.id));

        const footer = document.getElementById('equipmentModalFooter');
        const countEl = document.getElementById('selectedCount');

        if (this.selectedEquipment.length > 0) {
            footer.style.display = 'flex';
            countEl.textContent = `${this.selectedEquipment.length} ausgewählt`;
        } else {
            footer.style.display = 'none';
        }
    }

    // Link all selected equipment with the given type
    async linkSelectedEquipment(linkType) {
        if (!this.selectedEquipment || this.selectedEquipment.length === 0) {
            this.showToast('Keine Anlagen ausgewählt');
            return;
        }

        const count = this.selectedEquipment.length;
        const linkTypeName = linkType === 'maintenance' ? 'Wartung' : 'Service';

        // Link each selected equipment (batch mode - don't close/reload for each)
        for (const equipmentId of this.selectedEquipment) {
            const equipmentData = this.availableEquipmentData?.find(eq => eq.id === equipmentId);
            await this.linkEquipment(equipmentId, linkType, equipmentData, true);
        }

        this.showToast(`${count} Anlagen als ${linkTypeName} hinzugefügt`);
        this.closeEquipmentModal();
        this.loadEquipment(this.currentIntervention);
    }

    async linkEquipment(equipmentId, linkType, equipmentData = null, batchMode = false) {
        if (this.isOnline) {
            try {
                await this.apiCall('link-equipment', {
                    method: 'POST',
                    body: JSON.stringify({
                        intervention_id: this.currentIntervention.id,
                        equipment_id: equipmentId,
                        link_type: linkType
                    })
                });

                // Skip toast/close/reload in batch mode (handled by linkSelectedEquipment)
                if (!batchMode) {
                    this.showToast('Anlage hinzugefügt');
                    this.closeEquipmentModal();
                    this.loadEquipment(this.currentIntervention);
                }
            } catch (err) {
                console.error('Failed to link equipment:', err);
                if (!batchMode) {
                    this.showToast('Fehler beim Hinzufügen');
                }
            }
        } else {
            // Offline: Queue the link operation and update local cache
            try {
                // Add to sync queue
                await offlineDB.addToSyncQueue('link-equipment', {
                    intervention_id: this.currentIntervention.id,
                    equipment_id: equipmentId,
                    link_type: linkType
                });

                // Update local cache: add equipment to intervention's equipment list
                if (equipmentData) {
                    const currentEquipment = await offlineDB.getEquipmentForIntervention(this.currentIntervention.id);
                    const newEquipment = {
                        id: equipmentData.id,
                        ref: equipmentData.ref,
                        label: equipmentData.label,
                        type: equipmentData.type,
                        location: equipmentData.location,
                        link_type: linkType,
                        detail: null,
                        materials: [],
                        _pendingSync: true
                    };
                    currentEquipment.push(newEquipment);
                    await offlineDB.saveEquipment(this.currentIntervention.id, currentEquipment);

                    // Remove from available equipment
                    const availableEquipment = await offlineDB.getAvailableEquipment(this.currentIntervention.id);
                    const filteredAvailable = availableEquipment.filter(eq => eq.id !== equipmentId);
                    await offlineDB.saveAvailableEquipment(this.currentIntervention.id, filteredAvailable);
                }

                // Skip toast/close/reload in batch mode (handled by linkSelectedEquipment)
                if (!batchMode) {
                    this.showToast('Offline gespeichert - wird synchronisiert');
                    this.closeEquipmentModal();
                    this.loadEquipment(this.currentIntervention);
                }
            } catch (err) {
                console.error('Failed to queue link operation:', err);
                if (!batchMode) {
                    this.showToast('Fehler beim Speichern');
                }
            }
        }
    }

    // Toggle release/unreleased intervention
    async toggleRelease() {
        const signedStatus = this.currentIntervention.signed_status || 0;
        const isReleased = signedStatus >= 1;
        const action = isReleased ? 'unreleased' : 'release';
        const confirmMsg = isReleased
            ? 'Auftrag zur Bearbeitung wieder öffnen?'
            : 'Auftrag zur Unterschrift freigeben? Dies generiert auch die PDF.';

        if (!confirm(confirmMsg)) {
            return;
        }

        try {
            const result = await this.apiCall(`intervention/${this.currentIntervention.id}/${action}`, {
                method: 'POST'
            });

            // console.log('Release result:', result);

            if (result.status === 'ok') {
                // Update local signed_status
                this.currentIntervention.signed_status = result.signed_status;

                // Also update in IndexedDB
                try {
                    const interventions = await offlineDB.getAll('interventions');
                    const idx = interventions.findIndex(i => i.id === this.currentIntervention.id);
                    if (idx >= 0) {
                        interventions[idx].signed_status = result.signed_status;
                        await offlineDB.saveInterventions(interventions);
                    }
                } catch (e) {
                    console.error('Failed to update IndexedDB:', e);
                }

                // Update button
                const releaseIcon = document.getElementById('releaseIcon');
                const releaseText = document.getElementById('releaseText');
                const sigBtn = document.getElementById('navSignature');

                if (result.signed_status >= 1) {
                    releaseIcon.textContent = '✏️';
                    releaseText.textContent = 'Ändern';
                    sigBtn.style.display = 'flex';
                    this.showToast('Auftrag freigegeben' + (result.pdf_generated ? ' - PDF erstellt' : ''));
                } else {
                    releaseIcon.textContent = '✅';
                    releaseText.textContent = 'Freigeben';
                    sigBtn.style.display = 'none';
                    this.showToast('Auftrag zur Bearbeitung geöffnet');
                }

                // Update current intervention status
                this.currentIntervention.status = result.signed_status >= 1 ? 1 : 0;

                // Reload interventions list in background to update overview
                this.loadInterventions();

                // Reload equipment to ensure UI is in sync
                await this.loadEquipment(this.currentIntervention);
            } else {
                this.showToast('Fehler: ' + (result.error || 'Unbekannt'));
            }
        } catch (err) {
            console.error('Failed to toggle release:', err);
            this.showToast('Fehler: ' + (err.message || 'Unbekannt'));
        }
    }

    // Show documents modal
    async showDocuments() {
        document.getElementById('documentsModal').classList.add('show');

        const listEl = document.getElementById('documentsList');
        listEl.innerHTML = `
            <div class="loading">
                <div class="spinner"></div>
                <p>Lade Dokumente...</p>
            </div>
        `;

        try {
            let documents = [];

            if (this.isOnline) {
                const data = await this.apiCall(`intervention/${this.currentIntervention.id}/documents`);
                documents = data.documents || [];
                // Cache for offline use
                await offlineDB.saveDocuments(this.currentIntervention.id, documents);
            } else {
                // Load from cache when offline
                documents = await offlineDB.getDocuments(this.currentIntervention.id);
            }

            // Get pending uploads for this intervention
            const pendingUploads = await offlineDB.getPendingUploads(this.currentIntervention.id);

            // Start building the list
            listEl.innerHTML = '';

            // Always show upload button
            const uploadSection = document.createElement('div');
            uploadSection.className = 'upload-section';
            uploadSection.style.cssText = 'margin-bottom:16px;padding-bottom:16px;border-bottom:1px solid #eee;';
            uploadSection.innerHTML = `
                <input type="file" id="fileUpload" accept="image/*,.pdf" style="display:none;" multiple>
                <button type="button" class="btn btn-primary btn-block" id="btnUpload">
                    📷 Foto/Datei hochladen
                </button>
            `;
            listEl.appendChild(uploadSection);

            // Add upload handlers
            document.getElementById('btnUpload').addEventListener('click', () => {
                document.getElementById('fileUpload').click();
            });
            document.getElementById('fileUpload').addEventListener('change', (e) => this.uploadFiles(e.target.files));

            // Show offline indicator
            if (!this.isOnline) {
                const offlineNote = document.createElement('div');
                offlineNote.style.cssText = 'padding:8px 12px;background:#fff3e0;color:#e65100;font-size:12px;margin-bottom:12px;border-radius:4px;';
                offlineNote.textContent = '📴 Offline - Dokumente werden bei Verbindung synchronisiert';
                listEl.appendChild(offlineNote);
            }

            // Show pending uploads first
            if (pendingUploads.length > 0) {
                const pendingHeader = document.createElement('div');
                pendingHeader.style.cssText = 'padding:8px 12px;background:#e3f2fd;font-weight:600;font-size:13px;margin-bottom:8px;border-radius:4px;';
                pendingHeader.textContent = `⏳ Ausstehende Uploads (${pendingUploads.length})`;
                listEl.appendChild(pendingHeader);

                pendingUploads.forEach(upload => {
                    const item = document.createElement('div');
                    item.className = 'document-item';
                    item.style.opacity = '0.7';
                    item.innerHTML = `
                        <div class="document-icon">⏳</div>
                        <div class="document-info">
                            <div class="document-name">${upload.file_name}</div>
                            <div class="document-date" style="color:#1976d2;">Wartet auf Upload...</div>
                        </div>
                        <div class="document-actions">
                            <button type="button" class="doc-action doc-remove-pending" data-id="${upload.id}" title="Entfernen">❌</button>
                        </div>
                    `;
                    listEl.appendChild(item);
                });

                // Add remove handlers for pending
                listEl.querySelectorAll('.doc-remove-pending').forEach(btn => {
                    btn.addEventListener('click', async (e) => {
                        const id = parseInt(e.target.dataset.id);
                        await offlineDB.removePendingUpload(id);
                        this.showToast('Ausstehender Upload entfernt');
                        this.showDocuments(); // Refresh
                    });
                });
            }

            // Show server documents
            if (documents.length === 0 && pendingUploads.length === 0) {
                const emptyState = document.createElement('div');
                emptyState.className = 'empty-state';
                emptyState.style.padding = '20px 0';
                emptyState.innerHTML = `
                    <div class="empty-icon">📄</div>
                    <p>Keine Dokumente vorhanden</p>
                    <p style="font-size:12px;color:#666;">Bitte zuerst freigeben um PDF zu erstellen.</p>
                `;
                listEl.appendChild(emptyState);
                return;
            }

            // Render server documents
            documents.forEach(doc => {
                const item = document.createElement('div');
                item.className = 'document-item';

                // Create preview URL (add &attachment=0 for inline display)
                const previewUrl = doc.url + '&attachment=0';

                // Determine icon and filename for delete
                let icon = '📄';
                let deleteFilename = doc.name;
                if (doc.type === 'signature') {
                    icon = '✍️';
                    deleteFilename = 'signatures/' + doc.name.replace('Unterschrift: ', '');
                } else if (doc.type === 'image') {
                    icon = '🖼️';
                }

                // Offline: show document info but disable actions
                if (this.isOnline) {
                    item.innerHTML = `
                        <div class="document-icon">${icon}</div>
                        <a href="${doc.url}" class="document-info" target="_blank" title="Download">
                            <div class="document-name">${doc.name}</div>
                            <div class="document-date">${this.formatDate(new Date(doc.date * 1000))}</div>
                        </a>
                        <div class="document-actions">
                            <button type="button" class="doc-action" title="Vorschau" onclick="app.openPdfViewer('${previewUrl.replace(/'/g, "\\'")}', '${doc.name.replace(/'/g, "\\'")}')">🔍</button>
                            <button type="button" class="doc-action doc-delete" data-filename="${encodeURIComponent(deleteFilename)}" title="Löschen">🗑️</button>
                        </div>
                    `;
                } else {
                    // Offline: just show document name without clickable actions
                    item.innerHTML = `
                        <div class="document-icon">${icon}</div>
                        <div class="document-info">
                            <div class="document-name">${doc.name}</div>
                            <div class="document-date">${this.formatDate(new Date(doc.date * 1000))}</div>
                        </div>
                        <div class="document-actions" style="color:#999;">
                            <span title="Offline nicht verfügbar">📴</span>
                        </div>
                    `;
                }
                listEl.appendChild(item);
            });

            // Add delete event handlers (only when online)
            if (this.isOnline) {
                listEl.querySelectorAll('.doc-delete').forEach(btn => {
                    btn.addEventListener('click', (e) => this.deleteDocument(e.target.dataset.filename));
                });
            }
        } catch (err) {
            console.error('Failed to load documents:', err);
            // Try to show cached documents on error
            try {
                const cachedDocs = await offlineDB.getDocuments(this.currentIntervention.id);
                const pendingUploads = await offlineDB.getPendingUploads(this.currentIntervention.id);
                if (cachedDocs.length > 0 || pendingUploads.length > 0) {
                    this.showToast('Offline-Daten geladen');
                    // Reload with offline flag
                    this.isOnline = false;
                    return this.showDocuments();
                }
            } catch (cacheErr) {
                console.error('Cache load also failed:', cacheErr);
            }
            listEl.innerHTML = `
                <div class="empty-state" style="padding: 20px 0;">
                    <p>Fehler beim Laden der Dokumente</p>
                </div>
            `;
        }
    }

    closeDocumentsModal() {
        document.getElementById('documentsModal').classList.remove('show');
    }

    // Open PDF in in-app viewer overlay (no new tab needed)
    openPdfViewer(url, title = 'Dokument') {
        const overlay = document.getElementById('pdfViewerOverlay');
        document.getElementById('pdfViewerTitle').textContent = title;
        // Wrap in pdf_embed.php so the PDF scales to device width on iOS
        const storedTheme = localStorage.getItem('pwa_theme') || 'auto';
        const isDark = storedTheme === 'dark' ||
            (storedTheme === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches);
        const theme = isDark ? 'dark' : 'light';
        document.getElementById('pdfViewerFrame').src = `pdf_embed.php?url=${encodeURIComponent(url)}&theme=${theme}`;
        overlay.classList.add('show');
    }

    closePdfViewer() {
        const overlay = document.getElementById('pdfViewerOverlay');
        overlay.classList.remove('show');
        // Clear iframe to stop loading
        document.getElementById('pdfViewerFrame').src = 'about:blank';
    }

    // Show PDF preview in in-app viewer
    showPdfPreview() {
        if (!this.currentIntervention) {
            this.showToast('Keine Intervention ausgewählt');
            return;
        }

        if (!this.isOnline) {
            this.showToast('PDF-Vorschau nur online verfügbar');
            return;
        }

        const previewUrl = `pdf_preview.php?id=${this.currentIntervention.id}`;
        this.openPdfViewer(previewUrl, 'Servicebericht');
    }

    // Show acceptance protocol PDF in new tab (v4.5)
    showAcceptanceProtocol() {
        if (!this.currentIntervention) {
            this.showToast('Keine Intervention ausgewählt');
            return;
        }

        if (!this.isOnline) {
            this.showToast('Abnahmeprotokoll nur online verfügbar');
            return;
        }

        // Pass current equipment ID so only that one appears in the protocol
        let protocolUrl = `acceptance_protocol.php?id=${this.currentIntervention.id}`;
        if (this.currentEquipment && this.currentEquipment.id) {
            protocolUrl += `&equipment_id=${this.currentEquipment.id}`;
        }
        this.openPdfViewer(protocolUrl, 'Abnahmeprotokoll');
    }

    buildInfoHeader(intervention) {
        const card = document.createElement('div');
        card.className = 'info-collapse-card';

        // Summary row (always visible)
        const summary = document.createElement('div');
        summary.className = 'info-collapse-summary';

        const textWrap = document.createElement('div');
        textWrap.style.cssText = 'flex:1;min-width:0;';

        const primaryLabel = document.createElement('div');
        primaryLabel.className = 'info-collapse-customer';

        const addrPreview = document.createElement('div');
        addrPreview.className = 'info-collapse-addr-preview';

        const firstObj = intervention.object_addresses?.[0];
        if (firstObj) {
            // Primary: object name, secondary: object address + phone/email
            primaryLabel.textContent = firstObj.name || '—';
            const addrParts = [firstObj.address, [firstObj.zip, firstObj.town].filter(Boolean).join(' ')].filter(Boolean);
            addrPreview.textContent = addrParts.join(', ');
        } else {
            // Fallback: customer name + customer address
            primaryLabel.textContent = intervention.customer?.name || '—';
            if (intervention.customer?.address) {
                const addrParts = [intervention.customer.address, [intervention.customer.zip, intervention.customer.town].filter(Boolean).join(' ')].filter(Boolean);
                addrPreview.textContent = addrParts.join(', ');
            }
        }

        textWrap.appendChild(primaryLabel);
        if (addrPreview.textContent) textWrap.appendChild(addrPreview);

        // Phone only in summary (email only in expanded body)
        if (firstObj?.phone) {
            const contactRow = document.createElement('div');
            contactRow.className = 'info-collapse-addr-preview';
            contactRow.style.marginTop = '2px';
            const telLink = document.createElement('a');
            telLink.href = `tel:${firstObj.phone.replace(/\s/g, '')}`;
            telLink.textContent = `📞 ${firstObj.phone}`;
            telLink.style.cssText = 'color:inherit;text-decoration:none;';
            telLink.addEventListener('click', e => e.stopPropagation());
            contactRow.appendChild(telLink);
            textWrap.appendChild(contactRow);
        }


        const chevron = document.createElement('span');
        chevron.className = 'info-collapse-chevron';
        chevron.textContent = '▾';

        summary.appendChild(textWrap);
        summary.appendChild(chevron);

        // Detail body (hidden by default)
        const body = document.createElement('div');
        body.className = 'info-collapse-body';

        // Kunde
        if (intervention.customer) {
            const sec = document.createElement('div');
            sec.className = 'info-collapse-section';
            const lbl = document.createElement('div');
            lbl.className = 'info-collapse-label';
            lbl.textContent = 'Kunde';
            const val = document.createElement('div');
            val.className = 'info-collapse-value';
            const mapsUrl = this.getMapsUrl(intervention.customer.address, intervention.customer.zip, intervention.customer.town);
            let addrHtml = '';
            if (intervention.customer.address) addrHtml += this.escapeHtml(intervention.customer.address) + '<br>';
            if (intervention.customer.zip || intervention.customer.town) {
                addrHtml += this.escapeHtml((intervention.customer.zip || '') + ' ' + (intervention.customer.town || '')).trim();
            }
            if (mapsUrl && addrHtml) {
                val.innerHTML = `<a href="${mapsUrl}" target="_blank" rel="noopener" class="address-link">${addrHtml}</a>`;
            } else {
                val.innerHTML = addrHtml || '—';
            }
            sec.appendChild(lbl);
            sec.appendChild(val);
            body.appendChild(sec);
        }

        // Objektadresse(n)
        const objSec = document.createElement('div');
        objSec.className = 'info-collapse-section';
        const objLbl = document.createElement('div');
        objLbl.className = 'info-collapse-label';
        objLbl.textContent = 'Objektadresse';
        objSec.appendChild(objLbl);
        if (intervention.object_addresses?.length > 0) {
            intervention.object_addresses.forEach((addr, i) => {
                const val = document.createElement('div');
                val.className = 'info-collapse-value';
                if (i > 0) val.style.marginTop = '8px';
                const mapsUrl = this.getMapsUrl(addr.address, addr.zip, addr.town);
                let html = addr.name ? `<strong>${this.escapeHtml(addr.name)}</strong><br>` : '';
                let addrHtml = '';
                if (addr.address) addrHtml += this.escapeHtml(addr.address) + '<br>';
                if (addr.zip || addr.town) addrHtml += this.escapeHtml((addr.zip || '') + ' ' + (addr.town || '')).trim();
                if (mapsUrl && addrHtml) {
                    html += `<a href="${mapsUrl}" target="_blank" rel="noopener" class="address-link">${addrHtml}</a>`;
                } else {
                    html += addrHtml;
                }
                if (addr.phone) html += `<br><a href="tel:${this.escapeHtml(addr.phone.replace(/\s/g,''))}" class="address-link">📞 ${this.escapeHtml(addr.phone)}</a>`;
                if (addr.email) html += `<br><a href="mailto:${this.escapeHtml(addr.email)}" class="address-link">✉ ${this.escapeHtml(addr.email)}</a>`;
                if (addr.note) html += `<br><span style="color:var(--text-muted);font-style:italic;">${this.escapeHtml(addr.note).replace(/\n/g, '<br>')}</span>`;
                val.innerHTML = html;
                objSec.appendChild(val);
            });
        } else {
            const val = document.createElement('div');
            val.className = 'info-collapse-value';
            val.style.color = 'var(--text-muted)';
            val.style.fontStyle = 'italic';
            val.textContent = 'Keine Objektadresse hinterlegt';
            objSec.appendChild(val);
        }
        body.appendChild(objSec);

        // Termin
        const terminSec = document.createElement('div');
        terminSec.className = 'info-collapse-section';
        const terminLbl = document.createElement('div');
        terminLbl.className = 'info-collapse-label';
        terminLbl.textContent = 'Termin';
        const terminVal = document.createElement('div');
        terminVal.className = 'info-collapse-value';
        terminVal.id = 'infoTerminVal_' + intervention.id;
        terminVal.style.display = 'flex';
        terminVal.style.alignItems = 'flex-start';
        terminVal.style.gap = '8px';
        const terminText = document.createElement('div');
        terminText.id = 'infoTerminText_' + intervention.id;
        terminText.style.flex = '1';
        terminText.innerHTML = this.formatScheduleDisplay(intervention.date_start, intervention.date_end);
        const terminEditBtn = document.createElement('button');
        terminEditBtn.innerHTML = '✏️';
        terminEditBtn.style.cssText = 'background:none;border:none;cursor:pointer;padding:0;font-size:16px;line-height:1;flex-shrink:0;';
        terminEditBtn.title = 'Termin bearbeiten';
        terminEditBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            this.showScheduleModal(intervention);
        });
        terminVal.appendChild(terminText);
        terminVal.appendChild(terminEditBtn);
        terminSec.appendChild(terminLbl);
        terminSec.appendChild(terminVal);
        body.appendChild(terminSec);

        // Beschreibung
        if (intervention.description) {
            const sec = document.createElement('div');
            sec.className = 'info-collapse-section';
            const lbl = document.createElement('div');
            lbl.className = 'info-collapse-label';
            lbl.textContent = 'Auftragsbeschreibung';
            const val = document.createElement('div');
            val.className = 'info-collapse-value';
            val.innerHTML = this.escapeHtml(intervention.description).replace(/\n/g, '<br>');
            sec.appendChild(lbl);
            sec.appendChild(val);
            body.appendChild(sec);
        }

        // Öffentliche Anmerkung
        if (intervention.note_public) {
            const sec = document.createElement('div');
            sec.className = 'info-collapse-section';
            const lbl = document.createElement('div');
            lbl.className = 'info-collapse-label';
            lbl.textContent = 'Öffentliche Anmerkung';
            const val = document.createElement('div');
            val.className = 'info-collapse-value';
            val.innerHTML = this.escapeHtml(intervention.note_public).replace(/\n/g, '<br>');
            sec.appendChild(lbl);
            sec.appendChild(val);
            body.appendChild(sec);
        }

        // Private Anmerkung
        if (intervention.note_private) {
            const sec = document.createElement('div');
            sec.className = 'info-collapse-section';
            const lbl = document.createElement('div');
            lbl.className = 'info-collapse-label';
            lbl.textContent = 'Private Anmerkung';
            const val = document.createElement('div');
            val.className = 'info-collapse-value';
            val.innerHTML = this.escapeHtml(intervention.note_private).replace(/\n/g, '<br>');
            sec.appendChild(lbl);
            sec.appendChild(val);
            body.appendChild(sec);
        }

        // Historie-Button (nur wenn Objekt vorhanden)
        const histBtn = document.createElement('button');
        histBtn.textContent = '🕒 Objekt-Historie';
        histBtn.style.cssText = 'margin-top:12px;width:100%;padding:10px;border:1px solid var(--border,#ddd);border-radius:8px;background:transparent;color:var(--text,#000);font-size:14px;cursor:pointer;text-align:left;';
        histBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            this.showHistoryModal(intervention);
        });
        body.appendChild(histBtn);

        // Toggle logic
        summary.addEventListener('click', () => {
            const isOpen = body.classList.toggle('open');
            chevron.classList.toggle('open', isOpen);
        });

        card.appendChild(summary);
        card.appendChild(body);
        return card;
    }

    async showHistoryModal(intervention) {
        const overlay = document.createElement('div');
        overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;display:flex;align-items:flex-end;justify-content:center;';

        const sheet = document.createElement('div');
        sheet.style.cssText = 'background:var(--card-bg,#fff);border-radius:16px 16px 0 0;padding:20px;width:100%;max-width:480px;max-height:75vh;display:flex;flex-direction:column;box-shadow:0 -4px 24px rgba(0,0,0,.2);';

        const titleRow = document.createElement('div');
        titleRow.style.cssText = 'display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;flex-shrink:0;';
        const title = document.createElement('h3');
        title.style.cssText = 'margin:0;font-size:18px;';
        title.textContent = '🕒 Objekt-Historie';
        const closeBtn = document.createElement('button');
        closeBtn.textContent = '✕';
        closeBtn.style.cssText = 'background:none;border:none;font-size:20px;cursor:pointer;color:var(--text-muted,#888);padding:4px;';
        closeBtn.addEventListener('click', () => overlay.remove());
        overlay.addEventListener('click', (e) => { if (e.target === overlay) overlay.remove(); });
        titleRow.appendChild(title);
        titleRow.appendChild(closeBtn);

        const objName = intervention.object_addresses?.[0]?.name || intervention.customer?.name || '';
        const subtitle = document.createElement('div');
        subtitle.style.cssText = 'font-size:13px;color:var(--text-muted,#888);margin-bottom:14px;flex-shrink:0;';
        subtitle.textContent = objName ? `Objekt: ${objName}` : 'Alle Serviceaufträge an dieser Liegenschaft';

        const listWrap = document.createElement('div');
        listWrap.style.cssText = 'overflow-y:auto;flex:1;';
        listWrap.innerHTML = '<div style="text-align:center;padding:20px;color:var(--text-muted,#888);">Wird geladen…</div>';

        sheet.appendChild(titleRow);
        sheet.appendChild(subtitle);
        sheet.appendChild(listWrap);
        overlay.appendChild(sheet);
        document.body.appendChild(overlay);

        const statusLabel = (s, ss) => {
            if (ss >= 3) return { text: 'Unterschrieben', color: '#4caf50' };
            if (s === 3)  return { text: 'Abgeschlossen',  color: '#4caf50' };
            if (s === 1)  return { text: 'Freigegeben',    color: '#2196f3' };
            return { text: 'Entwurf', color: '#9e9e9e' };
        };

        try {
            const data = await this.apiCall(`intervention/${intervention.id}/history`);
            const history = data.history || [];

            if (history.length === 0) {
                listWrap.innerHTML = data.no_obj_contact
                    ? '<div style="text-align:center;padding:20px;color:var(--text-muted,#888);">Kein Objekt (OBJ-Kontakt) hinterlegt</div>'
                    : '<div style="text-align:center;padding:20px;color:var(--text-muted,#888);">Keine früheren Aufträge für dieses Objekt</div>';
                return;
            }

            listWrap.innerHTML = '';
            history.forEach(item => {
                const row = document.createElement('div');
                row.style.cssText = 'padding:12px 0;border-bottom:1px solid var(--border,#eee);cursor:pointer;display:flex;align-items:flex-start;gap:10px;';

                const sl = statusLabel(item.status, item.signed_status);
                const badge = document.createElement('span');
                badge.textContent = sl.text;
                badge.style.cssText = `flex-shrink:0;font-size:11px;font-weight:600;color:#fff;background:${sl.color};border-radius:4px;padding:2px 6px;margin-top:2px;`;

                const info = document.createElement('div');
                info.style.flex = '1';

                const refLine = document.createElement('div');
                refLine.style.cssText = 'font-weight:600;font-size:15px;';
                refLine.textContent = item.ref;

                const dateLine = document.createElement('div');
                dateLine.style.cssText = 'font-size:13px;color:var(--text-muted,#888);margin-top:2px;';
                dateLine.textContent = item.date_start ? this.formatDate(item.date_start) : '—';
                if (item.technician) dateLine.textContent += ' · ' + item.technician;

                if (item.description) {
                    const desc = document.createElement('div');
                    desc.style.cssText = 'font-size:13px;color:var(--text-muted,#888);margin-top:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:240px;';
                    desc.textContent = item.description;
                    info.appendChild(refLine);
                    info.appendChild(dateLine);
                    info.appendChild(desc);
                } else {
                    info.appendChild(refLine);
                    info.appendChild(dateLine);
                }

                row.appendChild(badge);
                row.appendChild(info);

                row.addEventListener('click', async () => {
                    overlay.remove();
                    this.currentIntervention = item;
                    await this.loadEquipment(item);
                });

                listWrap.appendChild(row);
            });
        } catch (err) {
            listWrap.innerHTML = `<div style="text-align:center;padding:20px;color:#f44336;">Fehler: ${this.escapeHtml(err.message)}</div>`;
        }
    }

    showStatusLegend() {
        const overlay = document.createElement('div');
        overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;display:flex;align-items:flex-end;justify-content:center;';
        overlay.addEventListener('click', (e) => { if (e.target === overlay) overlay.remove(); });

        const sheet = document.createElement('div');
        sheet.style.cssText = 'background:var(--card-bg,#fff);border-radius:16px 16px 0 0;padding:20px;width:100%;max-width:480px;box-shadow:0 -4px 24px rgba(0,0,0,.2);';

        const title = document.createElement('h3');
        title.style.cssText = 'margin:0 0 16px;font-size:18px;';
        title.textContent = 'Status-Legende';

        const items = [
            { color: '#bbdefb', label: 'Offen',          desc: 'Auftrag noch nicht freigegeben' },
            { color: '#e65100', label: 'Freigegeben',     desc: 'Zur Unterschrift bereit' },
            { color: '#c8e6c9', label: 'Unterschrieben',  desc: 'Vom Kunden unterschrieben' },
            { color: '#c8e6c9', label: 'Abgeschlossen',   desc: 'Auftrag vollständig erledigt' },
        ];

        const list = document.createElement('div');
        list.style.cssText = 'display:flex;flex-direction:column;gap:12px;';

        items.forEach(item => {
            const row = document.createElement('div');
            row.style.cssText = 'display:flex;align-items:center;gap:12px;';

            const swatch = document.createElement('div');
            swatch.style.cssText = `width:4px;height:36px;border-radius:2px;background:${item.color};flex-shrink:0;`;

            const text = document.createElement('div');
            const labelEl = document.createElement('div');
            labelEl.style.cssText = 'font-weight:600;font-size:15px;';
            labelEl.textContent = item.label;
            const descEl = document.createElement('div');
            descEl.style.cssText = 'font-size:13px;color:var(--text-muted,#888);';
            descEl.textContent = item.desc;
            text.appendChild(labelEl);
            text.appendChild(descEl);

            row.appendChild(swatch);
            row.appendChild(text);
            list.appendChild(row);
        });

        const closeBtn = document.createElement('button');
        closeBtn.textContent = 'Schließen';
        closeBtn.style.cssText = 'margin-top:20px;width:100%;padding:12px;border:none;border-radius:8px;background:var(--primary-color,#1a3f6e);color:#fff;font-size:15px;font-weight:600;cursor:pointer;';
        closeBtn.addEventListener('click', () => overlay.remove());

        sheet.appendChild(title);
        sheet.appendChild(list);
        sheet.appendChild(closeBtn);
        overlay.appendChild(sheet);
        document.body.appendChild(overlay);
    }

    closeInfoModal() {
        // kept for compatibility, no longer used
        return;
    }

    makeMapMarkerIcon(type, intervention) {
        let color;
        if (type === 'maintenance') {
            const statusColors = { overdue: '#f44336', soon: '#ff9800', ok: '#4caf50', none: '#ff9800' };
            color = statusColors[intervention && intervention.maintenance_status] || '#ff9800';
        } else {
            color = '#2196f3';
        }
        const svg = '<svg xmlns="http://www.w3.org/2000/svg" width="25" height="41" viewBox="0 0 25 41">' +
            '<path d="M12.5 0C5.6 0 0 5.6 0 12.5C0 22 12.5 41 12.5 41S25 22 25 12.5C25 5.6 19.4 0 12.5 0Z" fill="' + color + '" stroke="rgba(0,0,0,0.3)" stroke-width="1"/>' +
            '<circle cx="12.5" cy="12.5" r="5" fill="white"/>' +
            '</svg>';
        const icon = L.divIcon({ html: svg, className: '', iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [0, -41] });
        icon._color = color;
        return icon;
    }

    async showMap() {
        this.showView('viewMap');

        // Init Leaflet map only once
        if (!this.leafletMap) {
            this.leafletMap = L.map('interventionMap').setView([51.1657, 10.4515], 6);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
                maxZoom: 19
            }).addTo(this.leafletMap);
        }

        // Clear existing markers
        if (this.mapMarkers) {
            this.mapMarkers.forEach(m => m.remove());
        }
        this.mapMarkers = [];

        // Only open interventions (status 0 or 1)
        const interventions = (this.allInterventions || []).filter(i => i.status === 0 || i.status === 1);

        if (interventions.length === 0) {
            this.showToast('Keine offenen Aufträge');
            setTimeout(() => this.leafletMap.invalidateSize(), 100);
            return;
        }

        // Collect all needed address strings for cache cleanup
        const neededAddresses = [];
        const bounds = [];

        for (const intervention of interventions) {
            const addr = intervention.object_addresses?.[0];
            const street = addr?.address || intervention.customer?.address;
            const zip    = addr?.zip    || intervention.customer?.zip;
            const town   = addr?.town   || intervention.customer?.town;

            if (!street && !zip && !town) continue;

            const query = [street, zip, town].filter(Boolean).join(', ');
            neededAddresses.push(query);

            try {
                // Check geocache first
                let cached = await offlineDB.getGeoCache(query);
                let lat, lon;

                if (cached) {
                    lat = cached.lat;
                    lon = cached.lon;
                } else {
                    const geo = await this.geocodeAddress(query);
                    if (!geo) continue;
                    lat = parseFloat(geo.lat);
                    lon = parseFloat(geo.lon);
                    await offlineDB.setGeoCache(query, lat, lon);
                    // Respect Nominatim rate limit only when actually geocoding
                    await new Promise(r => setTimeout(r, 1100));
                }

                const addrLine = [street, [zip, town].filter(Boolean).join(' ')].filter(Boolean).join(', ');
                const icon = this.makeMapMarkerIcon(intervention.primary_type, intervention);
                const markerColor = icon._color;
                const typeLabel = intervention.primary_type === 'maintenance' ? 'Wartung' : 'Service';

                const marker = L.marker([lat, lon], { icon }).addTo(this.leafletMap);
                marker.bindPopup(
                    '<div class="map-popup-ref">' + this.escapeHtml(intervention.ref) +
                    ' <span style="font-size:10px;color:' + markerColor + '">' + typeLabel + '</span></div>' +
                    '<div class="map-popup-customer">' + this.escapeHtml(intervention.customer?.name || '') + '</div>' +
                    '<div class="map-popup-addr">' + this.escapeHtml(addrLine) + '</div>' +
                    '<a class="map-popup-link" onclick="app.openInterventionFromMap(' + intervention.id + ')">Auftrag öffnen →</a>'
                );

                this.mapMarkers.push(marker);
                bounds.push([lat, lon]);
            } catch (e) {
                console.warn('Geocoding failed for', query, e);
            }
        }

        // Clean up cache entries no longer needed
        try { await offlineDB.cleanGeoCache(neededAddresses); } catch (e) {}

        if (bounds.length > 0) {
            this.leafletMap.fitBounds(bounds, { padding: [40, 40] });
        }

        setTimeout(() => this.leafletMap.invalidateSize(), 100);
    }

    async showMaintenance() {
        this.showView('viewMaintenance');

        const loadingEl = document.getElementById('maintenanceLoading');
        const listEl = document.getElementById('maintenanceList');

        // Init filter state: default +3 months
        if (this.maintenanceMonthsAhead === undefined) this.maintenanceMonthsAhead = 3;

        loadingEl.style.display = 'flex';
        listEl.innerHTML = '';

        try {
            const data = await this.apiCall('maintenance-overview');
            loadingEl.style.display = 'none';

            if (!data || !data.groups || data.groups.length === 0) {
                listEl.innerHTML = '<div class="empty-state"><div class="empty-icon">📅</div><p>Keine Anlagen mit Wartungsplan gefunden.</p></div>';
                return;
            }

            this.maintenanceData = data.groups;
            this.renderMaintenanceView(listEl);
        } catch (e) {
            loadingEl.style.display = 'none';
            listEl.innerHTML = '<div class="empty-state"><div class="empty-icon">⚠️</div><p>Fehler beim Laden der Wartungsübersicht.</p></div>';
            console.error('Maintenance overview error:', e);
        }
    }

    renderMaintenanceView(container) {
        const groups = this.maintenanceData || [];
        const monthsAhead = this.maintenanceMonthsAhead !== undefined ? this.maintenanceMonthsAhead : 3;
        container = container || document.getElementById('maintenanceList');
        container.innerHTML = '';

        const statusColors = {
            overdue: '#f44336',
            due:     '#ff9800',
            soon:    '#ffd54f',
            future:  '#4caf50',
            done:    '#4caf50',
            none:    '#9e9e9e'
        };
        const statusLabels = {
            overdue: 'Überfällig',
            due:     'Fällig',
            soon:    'Nächster Monat',
            future:  'Geplant',
            done:    'Erledigt',
            none:    'Kein Monat'
        };

        // Filter dropdown — same style as signed time-range selector
        const filterWrap = document.createElement('div');
        filterWrap.className = 'time-range-selector';
        filterWrap.innerHTML =
            '<span class="time-range-label">Zusätzlich fällig in:</span>' +
            '<select class="time-range-select" id="maintenanceRangeSelect">' +
            '<option value="3"'  + (monthsAhead === 3  ? ' selected' : '') + '>3 Monate</option>' +
            '<option value="6"'  + (monthsAhead === 6  ? ' selected' : '') + '>6 Monate</option>' +
            '<option value="9"'  + (monthsAhead === 9  ? ' selected' : '') + '>9 Monate</option>' +
            '<option value="12"' + (monthsAhead === 12 ? ' selected' : '') + '>12 Monate</option>' +
            '</select>';
        container.appendChild(filterWrap);
        filterWrap.querySelector('select').addEventListener('change', (e) => {
            this.maintenanceMonthsAhead = parseInt(e.target.value, 10);
            this.renderMaintenanceView();
        });

        // Filter based on maintenance_month — same logic as backend maintenance dashboard
        const today = new Date();
        const currentMonth = today.getMonth() + 1; // 1-12

        const filteredGroups = groups.map(group => {
            const equipment = group.equipment.filter(eq => {
                const s = eq.maint_status;
                if (s === 'done') return false; // done this year — hide
                if (s === 'overdue' || s === 'due') return true; // always show
                if (s === 'none') return true; // no month set — always show
                // 'soon' or 'future': show if maintenance_month falls within filter range
                if (!eq.maintenance_month) return true;
                let monthsUntil = eq.maintenance_month - currentMonth;
                if (monthsUntil <= 0) monthsUntil += 12; // wrap to next calendar year
                return monthsUntil <= monthsAhead;
            });
            return { ...group, equipment };
        }).filter(g => g.equipment.length > 0);

        if (filteredGroups.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'empty-state';
            empty.innerHTML = '<div class="empty-icon">✅</div><p>Keine Anlagen in diesem Zeitraum fällig.</p>';
            container.appendChild(empty);
            return;
        }

        const monthNames = ['Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'];
        const statusRank = { overdue: 1, due: 2, soon: 3, future: 4, none: 5, done: 6 };

        // Group filtered equipment by maintenance_month across all address groups
        const byMonth = {}; // month (0=none) → { month, addressGroups: { key → {label, equipment[]} } }
        filteredGroups.forEach(group => {
            group.equipment.forEach(eq => {
                const m = eq.maintenance_month || 0;
                if (!byMonth[m]) byMonth[m] = { month: m, addressGroups: {} };
                if (!byMonth[m].addressGroups[group.key]) {
                    byMonth[m].addressGroups[group.key] = { key: group.key, label: group.label, equipment: [] };
                }
                byMonth[m].addressGroups[group.key].equipment.push(eq);
            });
        });

        // Sort months: 1-12 ascending (overdue months naturally first), 0 (none) last
        const sortedMonths = Object.values(byMonth).sort((a, b) => {
            if (a.month === 0) return 1;
            if (b.month === 0) return -1;
            return a.month - b.month;
        });

        sortedMonths.forEach(monthData => {
            // Worst status across all equipment in this month
            let worstStatus = 'none';
            Object.values(monthData.addressGroups).forEach(ag => {
                ag.equipment.forEach(eq => {
                    if ((statusRank[eq.maint_status] || 5) < (statusRank[worstStatus] || 5)) {
                        worstStatus = eq.maint_status;
                    }
                });
            });
            const worstColor = statusColors[worstStatus] || '#9e9e9e';

            // Month header
            const monthHeader = document.createElement('div');
            monthHeader.className = 'maint-month-header';
            const monthLabel = monthData.month ? monthNames[monthData.month - 1] : 'Kein Monat';
            monthHeader.innerHTML =
                '<div class="maint-status-dot" style="background:' + worstColor + ';width:10px;height:10px;"></div>' +
                '<span>' + monthLabel + '</span>';
            container.appendChild(monthHeader);

            // Address groups within this month
            Object.values(monthData.addressGroups).forEach(group => {
                const groupWorst = group.equipment.reduce((w, eq) =>
                    (statusRank[eq.maint_status] || 5) < (statusRank[w] || 5) ? eq.maint_status : w, 'none');
                const groupColor = statusColors[groupWorst] || '#9e9e9e';

                const groupEl = document.createElement('div');
                groupEl.className = 'maint-group';

                const headerEl = document.createElement('div');
                headerEl.className = 'maint-group-header';
                headerEl.innerHTML =
                    '<div class="maint-status-dot" style="background:' + groupColor + '"></div>' +
                    '<div style="flex:1;min-width:0;">' +
                      '<div class="maint-group-label">' + this.escapeHtml(group.label) + '</div>' +
                    '</div>' +
                    '<span class="maint-group-count">' + group.equipment.length + ' Anlage' + (group.equipment.length !== 1 ? 'n' : '') + '</span>' +
                    '<span class="maint-group-chevron">▼</span>';
                headerEl.addEventListener('click', () => groupEl.classList.toggle('open'));

                const bodyEl = document.createElement('div');
                bodyEl.className = 'maint-group-body';

                group.equipment.forEach(eq => {
                    const color = statusColors[eq.maint_status] || '#9e9e9e';
                    const typeLabel = (this.equipmentTypeLabels || {})[eq.type] || eq.type || '';
                    const statusLabel = statusLabels[eq.maint_status] || '';

                    const itemEl = document.createElement('div');
                    itemEl.className = 'maint-eq-item';
                    itemEl.innerHTML =
                        '<div class="maint-status-dot" style="background:' + color + ';flex-shrink:0;"></div>' +
                        '<div class="maint-eq-info">' +
                          '<div class="maint-eq-label">' + this.escapeHtml(eq.label || eq.ref) + '</div>' +
                          (typeLabel ? '<div class="maint-eq-date">' + this.escapeHtml(typeLabel) + '</div>' : '') +
                          '<div class="maint-eq-date">' + this.escapeHtml(statusLabel) + '</div>' +
                        '</div>';

                    if (eq.open_intervention_id) {
                        const linkEl = document.createElement('a');
                        linkEl.className = 'maint-eq-link';
                        linkEl.textContent = eq.open_intervention_ref + ' →';
                        linkEl.href = '#';
                        linkEl.addEventListener('click', (e) => {
                            e.preventDefault();
                            const intervention = (this.allInterventions || []).find(i => i.id === eq.open_intervention_id);
                            if (intervention) {
                                this.currentIntervention = intervention;
                                this.loadEquipment(intervention);
                            } else {
                                this.showToast('Auftrag nicht in der Liste — bitte synchronisieren');
                            }
                        });
                        itemEl.appendChild(linkEl);
                    }

                    bodyEl.appendChild(itemEl);
                });

                groupEl.appendChild(headerEl);
                groupEl.appendChild(bodyEl);
                container.appendChild(groupEl);
            });
        });
    }

    async geocodeAddress(query) {
        const url = `https://nominatim.openstreetmap.org/search?format=json&limit=1&q=${encodeURIComponent(query)}`;
        const res = await fetch(url, { headers: { 'Accept-Language': 'de' } });
        const data = await res.json();
        return data?.[0] || null;
    }

    openInterventionFromMap(interventionId) {
        const intervention = (this.allInterventions || []).find(i => i.id === interventionId);
        if (intervention) {
            this.leafletMap.closePopup();
            this.currentIntervention = intervention;
            this.loadEquipment(intervention);
        }
    }

    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Render equipment details in entries view
    renderEquipmentDetails(equipment) {
        // Get type label from typeLabels map
        const typeLabels = this.equipmentTypeLabels || {};
        const typeLabel = typeLabels[equipment.type] || equipment.type || '-';

        document.getElementById('eqDetailLabel').textContent = equipment.label || '-';
        document.getElementById('eqDetailLocation').textContent = equipment.location || '-';
        document.getElementById('eqDetailType').textContent = typeLabel;
        document.getElementById('eqDetailManufacturer').textContent = equipment.manufacturer || '-';

        // Show serial number (editable, so always show row)
        const serialRow = document.getElementById('eqDetailSerialRow');
        const serialEl = document.getElementById('eqDetailSerial');
        serialEl.textContent = equipment.serial_number || '-';
        serialRow.style.display = 'block';

        // Add click handlers for editable fields
        const labelEl = document.getElementById('eqDetailLabel');
        const locationEl = document.getElementById('eqDetailLocation');
        const manufacturerEl = document.getElementById('eqDetailManufacturer');

        // Style editable fields
        [labelEl, locationEl, manufacturerEl, serialEl].forEach(el => {
            el.style.background = 'var(--input-bg)';
            el.style.border = '1px dashed var(--border-color)';
        });

        // Click handlers
        labelEl.onclick = () => this.editEquipmentField('label', 'Bezeichnung', equipment.label || '');
        locationEl.onclick = () => this.editEquipmentField('location_note', 'Standort', equipment.location || '');
        manufacturerEl.onclick = () => this.editEquipmentField('manufacturer', 'Hersteller', equipment.manufacturer || '');
        serialEl.onclick = () => this.editEquipmentField('serial_number', 'Seriennummer', equipment.serial_number || '');

        // Helper: format month/year
        const monthNames = ['Jan','Feb','Mär','Apr','Mai','Jun','Jul','Aug','Sep','Okt','Nov','Dez'];
        const formatMonthYear = (month, year) => {
            if (!year) return '-';
            const mName = (month >= 1 && month <= 12) ? monthNames[month - 1] + ' ' : '';
            return mName + year;
        };

        const type = equipment.type;
        const fireProtActive = (equipment.fire_protection == 1);
        const showBattery  = (type === 'door_sliding');
        const showFireProt = (type === 'door_swing');
        const showSmoke    = (type === 'hold_open' || type === 'fire_gate') || (type === 'door_swing' && fireProtActive);

        // Battery rows
        const batteryRow      = document.getElementById('eqDetailBatteryRow');
        const batteryCycleRow = document.getElementById('eqDetailBatteryCycleRow');
        const batteryDateEl   = document.getElementById('eqDetailBatteryDate');
        const batteryCycleEl  = document.getElementById('eqDetailBatteryCycle');
        if (showBattery) {
            batteryDateEl.textContent = formatMonthYear(equipment.battery_install_month, equipment.battery_install_year);
            batteryCycleEl.textContent = equipment.battery_replacement_cycle ? equipment.battery_replacement_cycle + ' J.' : '-';
            batteryRow.style.display = '';
            batteryCycleRow.style.display = '';
            batteryDateEl.style.background = 'var(--input-bg)';
            batteryDateEl.style.border = '1px dashed var(--border-color)';
            batteryDateEl.onclick = () => this.editEquipmentInstallDate('battery', equipment);
        } else {
            batteryRow.style.display = 'none';
            batteryCycleRow.style.display = 'none';
        }

        // Brandschutz row
        const fireProtRow = document.getElementById('eqDetailFireProtRow');
        const fireProtEl  = document.getElementById('eqDetailFireProt');
        if (showFireProt) {
            fireProtEl.textContent = fireProtActive ? 'Ja' : 'Nein';
            fireProtRow.style.display = '';
        } else {
            fireProtRow.style.display = 'none';
        }

        // Smoke detector rows
        const smokeRow      = document.getElementById('eqDetailSmokeRow');
        const smokeCycleRow = document.getElementById('eqDetailSmokeCycleRow');
        const smokeDateEl   = document.getElementById('eqDetailSmokeDate');
        const smokeCycleEl  = document.getElementById('eqDetailSmokeCycle');
        if (showSmoke) {
            smokeDateEl.textContent = formatMonthYear(equipment.smoke_detector_install_month, equipment.smoke_detector_install_year);
            smokeCycleEl.textContent = equipment.smoke_detector_replacement_cycle ? equipment.smoke_detector_replacement_cycle + ' J.' : '-';
            smokeRow.style.display = '';
            smokeCycleRow.style.display = '';
            smokeDateEl.style.background = 'var(--input-bg)';
            smokeDateEl.style.border = '1px dashed var(--border-color)';
            smokeDateEl.onclick = () => this.editEquipmentInstallDate('smoke_detector', equipment);
        } else {
            smokeRow.style.display = 'none';
            smokeCycleRow.style.display = 'none';
        }
    }

    // Edit install date (month+year) for battery or smoke_detector
    async editEquipmentInstallDate(prefix, equipment) {
        const label = prefix === 'battery' ? 'Einbaujahr Akku' : 'Einbaujahr Rauchmelder';
        const monthField = prefix + '_install_month';
        const yearField  = prefix + '_install_year';
        const currentMonth = equipment[monthField] || '';
        const currentYear  = equipment[yearField] || '';
        const currentVal   = currentMonth && currentYear ? currentMonth + '/' + currentYear
                           : currentYear ? currentYear : '';

        const input = prompt(label + ' (MM/JJJJ oder JJJJ):', currentVal);
        if (input === null) return;

        let month = null, year = null;
        const trimmed = input.trim();
        if (trimmed) {
            const parts = trimmed.split('/');
            if (parts.length === 2) {
                month = parseInt(parts[0], 10) || null;
                year  = parseInt(parts[1], 10) || null;
            } else {
                year = parseInt(trimmed, 10) || null;
            }
            if (month !== null && (month < 1 || month > 12)) month = null;
        }

        try {
            const body = {};
            body[monthField] = month;
            body[yearField]  = year;
            const result = await this.apiCall(`equipment/${this.currentEquipment.id}`, {
                method: 'PUT',
                body: JSON.stringify(body)
            });
            if (result.status === 'ok') {
                this.currentEquipment[monthField] = month;
                this.currentEquipment[yearField]  = year;
                this.renderEquipmentDetails(this.currentEquipment);
                this.showToast(label + ' aktualisiert');
            } else {
                this.showToast('Fehler beim Speichern');
            }
        } catch (err) {
            console.error('Failed to update install date:', err);
            this.showToast('Fehler: ' + err.message);
        }
    }

    // Edit equipment field via prompt
    async editEquipmentField(field, label, currentValue) {
        const newValue = prompt(`${label}:`, currentValue);

        if (newValue === null) return; // Cancelled

        try {
            const result = await this.apiCall(`equipment/${this.currentEquipment.id}`, {
                method: 'PUT',
                body: JSON.stringify({ [field]: newValue })
            });

            if (result.status === 'ok') {
                // Update local data
                if (field === 'label') {
                    this.currentEquipment.label = newValue;
                } else if (field === 'location_note') {
                    this.currentEquipment.location = newValue;
                } else if (field === 'manufacturer') {
                    this.currentEquipment.manufacturer = newValue;
                } else if (field === 'serial_number') {
                    this.currentEquipment.serial_number = newValue;
                }

                // Re-render details
                this.renderEquipmentDetails(this.currentEquipment);
                this.showToast(`${label} aktualisiert`);
            } else {
                this.showToast('Fehler beim Speichern');
            }
        } catch (err) {
            console.error('Failed to update equipment:', err);
            this.showToast('Fehler: ' + err.message);
        }
    }

    // Show send-email modal, pre-filled with recipient + subject from API
    async showEmailModal() {
        const modal       = document.getElementById('emailModal');
        const recipientEl = document.getElementById('emailModalRecipient');
        const ccEl        = document.getElementById('emailModalCC');
        const bccEl       = document.getElementById('emailModalBCC');
        const subjectEl   = document.getElementById('emailModalSubject');
        const bodyRow     = document.getElementById('emailModalBodyRow');
        const bodyEl      = document.getElementById('emailModalBody');
        const attachNote  = document.getElementById('emailModalAttachNote');
        const sendBtn     = document.getElementById('btnEmailModalSend');
        const cancelBtn   = document.getElementById('btnEmailModalCancel');

        const showBody = localStorage.getItem('pwa_email_show_body') === 'true';
        bodyRow.style.display = showBody ? 'block' : 'none';

        // Show modal immediately with loading state
        modal.style.display = 'flex';
        recipientEl.value = '';
        ccEl.value = '';
        bccEl.value = '';
        subjectEl.value = 'Lädt…';
        bodyEl.value = '';
        sendBtn.disabled = true;

        try {
            const info = await this.apiCall(`intervention/${this.currentIntervention.id}/email-info`);
            recipientEl.value = info.email || '';
            subjectEl.value   = info.subject || this.currentIntervention.ref || '';
            bodyEl.value      = info.body || '';
            bccEl.value       = info.bcc || '';
            attachNote.textContent = '📎 PDF wird automatisch angehängt';
        } catch (err) {
            subjectEl.value = this.currentIntervention.ref || '';
            attachNote.textContent = '';
        }
        sendBtn.disabled = false;

        cancelBtn.onclick = () => { modal.style.display = 'none'; };
        sendBtn.onclick   = () => this.sendEmailReport(
            recipientEl.value.trim(),
            subjectEl.value.trim(),
            ccEl.value.trim(),
            showBody ? bodyEl.value.trim() : '',
            bccEl.value.trim()
        );
    }

    async sendEmailReport(email, subject, cc = '', body = '', bcc = '') {
        if (!email) {
            this.showToast('Bitte E-Mail-Adresse eingeben');
            return;
        }

        const sendBtn = document.getElementById('btnEmailModalSend');
        sendBtn.disabled = true;
        sendBtn.textContent = 'Sende…';

        try {
            const payload = { email, subject };
            if (cc)   payload.cc   = cc;
            if (bcc)  payload.bcc  = bcc;
            if (body) payload.body = body;
            const result = await this.apiCall(`intervention/${this.currentIntervention.id}/send-email`, {
                method: 'POST',
                body: JSON.stringify(payload)
            });

            document.getElementById('emailModal').style.display = 'none';
            if (result.status === 'ok') {
                this.showToast(result.attached ? '📧 E-Mail mit PDF gesendet' : '📧 E-Mail gesendet');
            } else {
                this.showToast('Fehler beim Senden');
            }
        } catch (err) {
            this.showToast('Fehler: ' + err.message);
        } finally {
            sendBtn.disabled = false;
            sendBtn.textContent = 'Senden';
        }
    }

    // Remove equipment link from current intervention
    async unlinkEquipment(eq) {
        if (!confirm(`Anlage "${eq.ref} – ${eq.label || ''}" aus diesem Serviceauftrag entfernen?`)) {
            return;
        }

        try {
            const result = await this.apiCall('link-equipment', {
                method: 'DELETE',
                body: JSON.stringify({
                    intervention_id: this.currentIntervention.id,
                    equipment_id: eq.id
                })
            });

            if (result.status === 'ok') {
                this.showToast('Anlage entfernt');
                // Reload equipment list
                this.loadEquipment(this.currentIntervention);
            } else {
                this.showToast('Fehler beim Entfernen');
            }
        } catch (err) {
            console.error('Failed to unlink equipment:', err);
            this.showToast('Fehler: ' + err.message);
        }
    }

    async deleteDocument(encodedFilename) {
        const filename = decodeURIComponent(encodedFilename);

        if (!confirm(`Dokument "${filename}" wirklich löschen?`)) {
            return;
        }

        try {
            // Use decoded filename - apiCall will encode the route
            const result = await this.apiCall(
                `intervention/${this.currentIntervention.id}/documents/${filename}`,
                { method: 'DELETE' }
            );

            if (result.status === 'ok') {
                this.showToast('Dokument gelöscht');
                // Refresh the documents list
                this.showDocuments();
            } else {
                this.showToast('Löschen fehlgeschlagen');
            }
        } catch (err) {
            console.error('Failed to delete document:', err);
            this.showToast('Fehler beim Löschen');
        }
    }

    /**
     * Compress an image file to reduce upload size
     * @param {File} file - The image file to compress
     * @param {number} maxWidth - Maximum width (default 1920)
     * @param {number} quality - JPEG quality 0-1 (default 0.8)
     * @returns {Promise<File>} - Compressed file
     */
    async compressImage(file, maxWidth = 1920, quality = 0.8) {
        // Only compress images
        if (!file.type.startsWith('image/')) {
            return file;
        }

        // Don't compress small files (< 500KB)
        if (file.size < 500 * 1024) {
            return file;
        }

        return new Promise((resolve, reject) => {
            const img = new Image();
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');

            img.onload = () => {
                let width = img.width;
                let height = img.height;

                // Calculate new dimensions
                if (width > maxWidth) {
                    height = Math.round((height * maxWidth) / width);
                    width = maxWidth;
                }

                canvas.width = width;
                canvas.height = height;

                // Draw and compress
                ctx.drawImage(img, 0, 0, width, height);

                canvas.toBlob(
                    (blob) => {
                        if (blob) {
                            // Create new file with same name
                            const compressedFile = new File([blob], file.name, {
                                type: 'image/jpeg',
                                lastModified: Date.now()
                            });
                            console.log(`Compressed ${file.name}: ${(file.size/1024).toFixed(0)}KB -> ${(compressedFile.size/1024).toFixed(0)}KB`);
                            resolve(compressedFile);
                        } else {
                            resolve(file); // Fallback to original
                        }
                    },
                    'image/jpeg',
                    quality
                );
            };

            img.onerror = () => {
                console.warn('Failed to load image for compression, using original');
                resolve(file);
            };

            // Load image from file
            const reader = new FileReader();
            reader.onload = (e) => {
                img.src = e.target.result;
            };
            reader.onerror = () => resolve(file);
            reader.readAsDataURL(file);
        });
    }

    async uploadFiles(files) {
        if (!files || files.length === 0) return;

        let successCount = 0;
        let errorCount = 0;

        if (this.isOnline) {
            this.showToast('Lade hoch...');

            for (let file of files) {
                try {
                    // Compress images before upload
                    if (file.type.startsWith('image/')) {
                        this.showToast('Komprimiere Bild...');
                        file = await this.compressImage(file);
                    }

                    const formData = new FormData();
                    formData.append('file', file);

                    // Use same URL format as apiCall (query parameter style)
                    const url = CONFIG.apiBase + '?route=' + encodeURIComponent(`intervention/${this.currentIntervention.id}/documents`);

                    // Include PWA token if available
                    const headers = {};
                    if (this.pwaToken) {
                        headers['X-PWA-Token'] = this.pwaToken;
                    }

                    const response = await fetch(url, {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin',
                        headers
                    });

                    if (!response.ok) {
                        const text = await response.text();
                        console.error('Upload response:', response.status, text);
                        // Show more detailed error
                        if (response.status === 413) {
                            this.showToast('Datei zu groß für Server');
                        }
                        errorCount++;
                        continue;
                    }

                    const result = await response.json();

                    if (result.status === 'ok') {
                        successCount++;
                    } else {
                        errorCount++;
                        console.error('Upload failed:', result.error);
                        this.showToast('Fehler: ' + (result.error || 'Unbekannt'));
                    }
                } catch (err) {
                    errorCount++;
                    console.error('Upload error:', err);
                }
            }

            if (successCount > 0) {
                this.showToast(`${successCount} Datei(en) hochgeladen`);
                // Refresh the documents list
                this.showDocuments();
            }
            if (errorCount > 0) {
                this.showToast(`${errorCount} Fehler beim Hochladen`);
            }
        } else {
            // Offline: Store files for later upload
            this.showToast('Speichere offline...');

            for (const file of files) {
                try {
                    // Read file as base64
                    const fileData = await this.fileToBase64(file);

                    // Store in IndexedDB
                    await offlineDB.addPendingUpload(
                        this.currentIntervention.id,
                        fileData,
                        file.name,
                        file.type
                    );
                    successCount++;
                } catch (err) {
                    errorCount++;
                    console.error('Offline save error:', err);
                }
            }

            if (successCount > 0) {
                this.showToast(`${successCount} Datei(en) offline gespeichert`);
                // Refresh the documents list to show pending uploads
                this.showDocuments();
            }
            if (errorCount > 0) {
                this.showToast(`${errorCount} Fehler beim Speichern`);
            }
        }
    }

    // Helper to convert file to base64
    fileToBase64(file) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.readAsDataURL(file);
            reader.onload = () => resolve(reader.result);
            reader.onerror = error => reject(error);
        });
    }

    formatFileSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }

    // ===== Checklist Methods (v2.0) =====

    // Load checklist for equipment
    async loadChecklist(interventionId, equipmentId) {
        const contentEl = document.getElementById('checklistContent');
        const titleEl = document.getElementById('checklistTitle');

        contentEl.innerHTML = `
            <div class="loading">
                <div class="spinner"></div>
                <p>Lade Checkliste...</p>
            </div>
        `;

        try {
            let checklistData = null;

            if (this.isOnline) {
                try {
                    checklistData = await this.apiCall(`checklist/${interventionId}/${equipmentId}`);
                    // Cache for offline use
                    await offlineDB.put('checklists', {
                        key: `${interventionId}_${equipmentId}`,
                        intervention_id: interventionId,
                        equipment_id: equipmentId,
                        data: checklistData
                    });
                } catch (err) {
                    console.warn('API call failed, trying cache:', err);
                    const cached = await offlineDB.get('checklists', `${interventionId}_${equipmentId}`);
                    if (cached) {
                        checklistData = cached.data;
                    }
                }
            } else {
                // Offline - load from cache
                const cached = await offlineDB.get('checklists', `${interventionId}_${equipmentId}`);
                if (cached) {
                    checklistData = cached.data;
                    this.showToast('Offline-Daten geladen');
                }
            }

            // Handle case where no checklist exists yet but templates are available
            if (!checklistData || (!checklistData.has_checklist && (!checklistData.available_templates || checklistData.available_templates.length === 0))) {
                contentEl.innerHTML = `
                    <div class="empty-state" style="padding: 20px 0;">
                        <p>Keine Checkliste verfügbar</p>
                        <p style="font-size: 12px; color: #999;">Für diesen Anlagentyp (${checklistData?.equipment_type || 'unbekannt'}) ist keine Vorlage hinterlegt.</p>
                    </div>
                `;
                return;
            }

            // If no checklist exists but templates are available, show start button
            if (!checklistData.has_checklist && checklistData.available_templates && checklistData.available_templates.length > 0) {
                this.renderChecklistStart(checklistData.available_templates, contentEl);
                return;
            }

            this.currentChecklist = checklistData;
            // Use template name without "Checkliste" prefix if it already starts with it
            let templateName = checklistData.template.label || 'Wartung';
            if (templateName.toLowerCase().startsWith('checkliste ')) {
                templateName = templateName.substring(11); // Remove "Checkliste " prefix
            }
            titleEl.textContent = `Checkliste: ${templateName}`;

            this.renderChecklist(checklistData);

        } catch (err) {
            console.error('Error loading checklist:', err);
            contentEl.innerHTML = `
                <div class="empty-state" style="padding: 20px 0;">
                    <p>Fehler beim Laden der Checkliste</p>
                </div>
            `;
        }
    }

    // Render checklist with sections and items
    renderChecklist(data) {
        const contentEl = document.getElementById('checklistContent');
        const sections = data.template.sections || [];
        const results = data.results || {};
        const checklist = data.checklist || {};
        const isCompleted = checklist.status === 1;
        // Check if intervention is still draft (status 0) - then checklist is editable even if completed
        const interventionStatus = this.currentIntervention?.status ?? 1;
        const canEditChecklist = !isCompleted || (isCompleted && interventionStatus === 0);

        let html = '';

        // Show completion status if completed
        if (isCompleted) {
            html += `
                <div class="checklist-status completed">
                    <span>✅</span>
                    <span>Checkliste abgeschlossen${checklist.date_completion ? ' am ' + this.formatDate(checklist.date_completion) : ''}</span>
                    ${canEditChecklist ? '<span style="margin-left:8px;font-size:12px;color:#666;">(bearbeitbar)</span>' : ''}
                </div>
            `;
        }

        // Render sections
        sections.forEach(section => {
            const sectionCode = section.code;
            const isErgebnisSection = sectionCode === 'ergebnis';

            html += `<div class="checklist-section" data-section="${sectionCode}">`;

            // Section header with "Alle OK" button (except for Ergebnis section)
            html += `<div class="checklist-section-header">`;
            html += `<span>${this.escapeHtml(section.label)}</span>`;
            if (!isErgebnisSection && canEditChecklist) {
                html += `<button type="button" class="btn-all-ok" onclick="app.setAllOK('${sectionCode}')">Alle OK</button>`;
            }
            html += `</div>`;

            // Section items
            const items = section.items || [];
            items.forEach(item => {
                const itemId = item.id;
                const itemCode = item.code;
                const result = results[itemId] || {};
                const currentAnswer = result.answer || '';
                const currentNote = result.note || '';
                const answerType = item.answer_type || 'ok_mangel';

                html += `<div class="checklist-item" data-item="${itemId}" data-code="${itemCode}">`;
                html += `<div class="checklist-item-header">`;
                html += `<span class="checklist-item-label">${this.escapeHtml(item.label)}</span>`;

                // Answer select
                const selectClass = this.getAnswerClass(currentAnswer);
                html += `<select class="checklist-item-select ${selectClass}"
                            data-item="${itemId}"
                            data-section="${sectionCode}"
                            ${!canEditChecklist ? 'disabled' : ''}
                            onchange="app.onChecklistAnswerChange(this)">`;

                // Options based on answer type
                html += `<option value="">-</option>`;

                if (answerType === 'ok_mangel' || answerType === 'ok_mangel_nv') {
                    html += `<option value="ok" ${currentAnswer === 'ok' ? 'selected' : ''}>OK</option>`;
                    html += `<option value="mangel" ${currentAnswer === 'mangel' ? 'selected' : ''}>Mangel</option>`;
                    if (answerType === 'ok_mangel_nv' || !isErgebnisSection) {
                        html += `<option value="nv" ${currentAnswer === 'nv' ? 'selected' : ''}>n.V.</option>`;
                    }
                } else if (answerType === 'ergebnis') {
                    html += `<option value="ok" ${currentAnswer === 'ok' ? 'selected' : ''}>OK</option>`;
                    html += `<option value="bedingt_ok" ${currentAnswer === 'bedingt_ok' ? 'selected' : ''}>Bedingt OK</option>`;
                    html += `<option value="nicht_ok" ${currentAnswer === 'nicht_ok' ? 'selected' : ''}>Nicht OK</option>`;
                } else if (answerType === 'ja_nein') {
                    html += `<option value="ja" ${currentAnswer === 'ja' ? 'selected' : ''}>Ja</option>`;
                    html += `<option value="nein" ${currentAnswer === 'nein' ? 'selected' : ''}>Nein</option>`;
                } else if (answerType === 'number') {
                    // Number input — rendered separately below, select is hidden
                    html += `<option value="${this.escapeHtml(currentAnswer)}" selected>${this.escapeHtml(currentAnswer) || '-'}</option>`;
                } else {
                    // Default fallback to ok_mangel
                    html += `<option value="ok" ${currentAnswer === 'ok' ? 'selected' : ''}>OK</option>`;
                    html += `<option value="mangel" ${currentAnswer === 'mangel' ? 'selected' : ''}>Mangel</option>`;
                    html += `<option value="nv" ${currentAnswer === 'nv' ? 'selected' : ''}>n.V.</option>`;
                }

                html += `</select>`;

                // Number input field for 'number' answer type (replaces select)
                if (answerType === 'number') {
                    const thresholdMax = item.threshold_max != null ? parseFloat(item.threshold_max) : null;
                    const numVal = currentAnswer !== '' ? parseFloat(currentAnswer) : NaN;
                    let numClass = '';
                    let badgeHtml = '';
                    if (!isNaN(numVal) && thresholdMax !== null) {
                        if (numVal <= thresholdMax) {
                            numClass = 'threshold-ok';
                            badgeHtml = `<span class="number-threshold-badge ok">✓ OK</span>`;
                        } else {
                            numClass = 'threshold-nok';
                            badgeHtml = `<span class="number-threshold-badge nok">✗ >${thresholdMax} N</span>`;
                        }
                    }
                    const thresholdAttr = thresholdMax !== null ? `data-threshold-max="${thresholdMax}"` : '';
                    html += `<input type="number" class="checklist-item-number ${numClass}"
                                placeholder="N"
                                data-item="${itemId}"
                                data-section="${sectionCode}"
                                value="${this.escapeHtml(currentAnswer)}"
                                step="any"
                                ${thresholdAttr}
                                ${!canEditChecklist ? 'disabled' : ''}
                                onchange="app.onChecklistNumberChange(this)"
                                style="display:none">`;
                    html += `<span class="number-unit" style="display:none">N</span>`;
                    html += badgeHtml ? `<span class="number-threshold-display" style="display:none">${badgeHtml}</span>` : `<span class="number-threshold-display" style="display:none"></span>`;
                }

                html += `</div>`;

                // Note field
                html += `<input type="text" class="checklist-item-note"
                            placeholder="Anmerkung..."
                            data-item="${itemId}"
                            value="${this.escapeHtml(currentNote)}"
                            ${!canEditChecklist ? 'disabled' : ''}
                            onchange="app.onChecklistNoteChange(this)">`;

                // Defect photo section (shown when answer is 'mangel')
                const currentPhoto = result.photo || '';
                const showPhotoSection = currentAnswer === 'mangel';
                html += `<div class="defect-photo-section" data-item="${itemId}" style="display:${showPhotoSection ? 'block' : 'none'}">`;

                if (currentPhoto) {
                    // Show photo thumbnail
                    html += `<div class="defect-photo-preview" data-item="${itemId}">
                        <img src="${this.getDefectPhotoUrl(currentPhoto)}" alt="Mangel-Foto" onclick="app.viewDefectPhoto('${currentPhoto}')">
                        ${canEditChecklist ? `<button type="button" class="defect-photo-delete" onclick="app.deleteDefectPhoto(${itemId})" title="Foto löschen">✕</button>` : ''}
                    </div>`;
                } else if (canEditChecklist) {
                    // Show add photo button
                    html += `<button type="button" class="btn-defect-photo" onclick="app.captureDefectPhoto(${itemId})">
                        📷 Foto hinzufügen
                    </button>`;
                }

                html += `</div>`;

                html += `</div>`;
            });

            html += `</div>`;
        });

        // Action buttons
        html += '<div class="checklist-actions" style="margin-top:16px;">';

        // PDF Preview button (always available)
        html += `
            <button type="button" class="btn btn-secondary btn-block" style="margin-bottom:8px;" onclick="app.openChecklistPdf(true)">
                PDF Vorschau
            </button>
        `;

        // Complete/Update button (if editable)
        if (canEditChecklist) {
            const buttonLabel = isCompleted ? 'Checkliste aktualisieren' : 'Checkliste abschließen';
            html += `
                <button type="button" class="btn btn-success btn-block" onclick="app.completeChecklist()">
                    ${buttonLabel}
                </button>
            `;
        }

        html += '</div>';

        contentEl.innerHTML = html;

        // For 'number' answer type: hide the select, show the number input + unit + badge
        contentEl.querySelectorAll('.checklist-item-number').forEach(numEl => {
            const header = numEl.closest('.checklist-item-header');
            const selectEl = header.querySelector('.checklist-item-select');
            if (selectEl) selectEl.style.display = 'none';
            numEl.style.display = 'inline-block';
            const unitEl = header.querySelector('.number-unit');
            if (unitEl) unitEl.style.display = 'inline';
            const badgeContainer = header.querySelector('.number-threshold-display');
            if (badgeContainer) badgeContainer.style.display = 'inline';
        });
    }

    // Render checklist start view when no checklist exists yet
    renderChecklistStart(templates, contentEl) {
        let html = '<div class="empty-state" style="padding: 20px 0;">';
        html += '<p style="margin-bottom:16px;">Noch keine Checkliste gestartet</p>';

        if (templates.length === 1) {
            // Single template - show simple start button
            const template = templates[0];
            let templateName = template.label || 'Wartung';
            if (templateName.toLowerCase().startsWith('checkliste ')) {
                templateName = templateName.substring(11);
            }
            html += `
                <button type="button" class="btn btn-success btn-block"
                    onclick="app.createChecklist('${template.equipment_type_code}')">
                    Checkliste starten (${this.escapeHtml(templateName)})
                </button>
            `;
        } else {
            // Multiple templates - show selection
            html += '<p style="margin-bottom:12px;font-size:13px;color:#666;">Vorlage auswählen:</p>';
            templates.forEach(template => {
                let templateName = template.label || template.equipment_type_code;
                if (templateName.toLowerCase().startsWith('checkliste ')) {
                    templateName = templateName.substring(11);
                }
                html += `
                    <button type="button" class="btn btn-success btn-block" style="margin-bottom:8px;"
                        onclick="app.createChecklist('${template.equipment_type_code}')">
                        ${this.escapeHtml(templateName)}
                    </button>
                `;
            });
        }

        html += '</div>';
        contentEl.innerHTML = html;
    }

    // Create a new checklist from template
    async createChecklist(templateType) {
        if (!this.currentIntervention || !this.currentEquipment) {
            this.showToast('Fehler: Keine Intervention/Anlage');
            return;
        }

        const contentEl = document.getElementById('checklistContent');
        contentEl.innerHTML = `
            <div class="loading">
                <div class="spinner"></div>
                <p>Erstelle Checkliste...</p>
            </div>
        `;

        try {
            const result = await this.apiCall(`checklist/${this.currentIntervention.id}/${this.currentEquipment.id}`, {
                method: 'POST',
                body: JSON.stringify({ template_type: templateType })
            });

            // Reload checklist
            await this.loadChecklist(this.currentIntervention.id, this.currentEquipment.id);
        } catch (err) {
            console.error('Failed to create checklist:', err);
            this.showToast('Fehler beim Erstellen der Checkliste');
            contentEl.innerHTML = `
                <div class="empty-state" style="padding: 20px 0;">
                    <p>Fehler beim Erstellen</p>
                    <p style="font-size: 12px; color: #999;">${err.message || 'Unbekannter Fehler'}</p>
                </div>
            `;
        }
    }

    // Get CSS class for answer styling
    getAnswerClass(answer) {
        switch (answer) {
            case 'ok':
            case 'bedingt_ok':
            case 'ja':
                return 'answer-ok';
            case 'mangel':
            case 'nicht_ok':
            case 'nein':
                return 'answer-mangel';
            case 'nv':
                return 'answer-nv';
            default:
                return '';
        }
    }

    // Set all items in a section to OK
    setAllOK(sectionCode) {
        const section = document.querySelector(`.checklist-section[data-section="${sectionCode}"]`);
        if (!section) return;

        const selects = section.querySelectorAll('.checklist-item-select');
        selects.forEach(select => {
            // Find and select the "ok" option
            for (let i = 0; i < select.options.length; i++) {
                if (select.options[i].value === 'ok') {
                    select.value = 'ok';
                    select.className = 'checklist-item-select answer-ok';
                    // Trigger change to save
                    this.onChecklistAnswerChange(select, true);
                    break;
                }
            }
        });

        this.showToast('Alle auf OK gesetzt');
    }

    // Handle answer change
    async onChecklistAnswerChange(selectEl, skipToast = false) {
        const itemCode = selectEl.dataset.item;
        const answer = selectEl.value;

        // Update styling
        selectEl.className = 'checklist-item-select ' + this.getAnswerClass(answer);

        // Get note value
        const noteEl = selectEl.closest('.checklist-item').querySelector('.checklist-item-note');
        const note = noteEl ? noteEl.value : '';

        // Show/hide defect photo section based on answer
        const photoSection = selectEl.closest('.checklist-item').querySelector('.defect-photo-section');
        if (photoSection) {
            photoSection.style.display = answer === 'mangel' ? 'block' : 'none';
        }

        await this.saveChecklistItem(itemCode, answer, note, skipToast);
    }

    // Handle note change
    async onChecklistNoteChange(inputEl) {
        const itemCode = inputEl.dataset.item;
        const note = inputEl.value;

        // Get answer value — prefer number input if present
        const numberEl = inputEl.closest('.checklist-item').querySelector('.checklist-item-number');
        const selectEl = inputEl.closest('.checklist-item').querySelector('.checklist-item-select');
        const answer = numberEl ? numberEl.value : (selectEl ? selectEl.value : '');

        await this.saveChecklistItem(itemCode, answer, note);
    }

    // Handle number measurement input change
    async onChecklistNumberChange(inputEl) {
        const itemCode = inputEl.dataset.item;
        const answer = inputEl.value;

        // Update threshold badge color
        const thresholdMax = inputEl.dataset.thresholdMax != null ? parseFloat(inputEl.dataset.thresholdMax) : null;
        const numVal = answer !== '' ? parseFloat(answer) : NaN;
        const header = inputEl.closest('.checklist-item-header');
        const badgeContainer = header ? header.querySelector('.number-threshold-display') : null;
        inputEl.classList.remove('threshold-ok', 'threshold-nok');
        if (badgeContainer) {
            if (!isNaN(numVal) && thresholdMax !== null) {
                if (numVal <= thresholdMax) {
                    inputEl.classList.add('threshold-ok');
                    badgeContainer.innerHTML = `<span class="number-threshold-badge ok">✓ OK</span>`;
                } else {
                    inputEl.classList.add('threshold-nok');
                    badgeContainer.innerHTML = `<span class="number-threshold-badge nok">✗ >${thresholdMax} N</span>`;
                }
            } else {
                badgeContainer.innerHTML = '';
            }
        }

        // Update hidden select to keep value in sync
        const selectEl = inputEl.closest('.checklist-item').querySelector('.checklist-item-select');
        if (selectEl) {
            selectEl.options[0].value = answer;
            selectEl.options[0].text = answer || '-';
            selectEl.value = answer;
        }

        const noteEl = inputEl.closest('.checklist-item').querySelector('.checklist-item-note');
        const note = noteEl ? noteEl.value : '';

        await this.saveChecklistItem(itemCode, answer, note);
    }

    // Get URL for defect photo
    getDefectPhotoUrl(filename) {
        if (!filename) return '';
        const checklistId = this.currentChecklist?.checklist?.id;
        if (!checklistId) return '';
        // Photos are served via API endpoint with filename
        return `${CONFIG.apiBase}/defect-photo/${checklistId}/file/${filename}`;
    }

    // Capture defect photo for an item
    async captureDefectPhoto(itemId) {
        if (!this.currentIntervention || !this.currentEquipment || !this.currentChecklist?.checklist?.id) {
            this.showToast('Fehler: Checkliste nicht geladen');
            return;
        }

        // Create file input for camera capture
        const fileInput = document.createElement('input');
        fileInput.type = 'file';
        fileInput.accept = 'image/*';
        fileInput.capture = 'environment'; // Use back camera on mobile

        fileInput.onchange = async (e) => {
            const file = e.target.files[0];
            if (!file) return;

            // Show loading indicator
            const photoSection = document.querySelector(`.defect-photo-section[data-item="${itemId}"]`);
            if (photoSection) {
                photoSection.innerHTML = '<div class="defect-photo-loading">📷 Wird hochgeladen...</div>';
            }

            try {
                // Read file as base64
                const base64Data = await this.readFileAsBase64(file);

                // Upload via API: defect-photo/{checklist_id}/{item_id}
                const response = await this.apiCall(`defect-photo/${this.currentChecklist.checklist.id}/${itemId}`, {
                    method: 'POST',
                    body: JSON.stringify({
                        image: base64Data,
                        filename: file.name
                    })
                });

                if (response.status === 'ok' && response.filename) {
                    // Update UI with new photo
                    if (photoSection) {
                        photoSection.innerHTML = `
                            <div class="defect-photo-preview" data-item="${itemId}">
                                <img src="${this.getDefectPhotoUrl(response.filename)}" alt="Mangel-Foto" onclick="app.viewDefectPhoto('${response.filename}')">
                                <button type="button" class="defect-photo-delete" onclick="app.deleteDefectPhoto(${itemId})" title="Foto löschen">✕</button>
                            </div>`;
                    }

                    // Update local checklist data
                    if (this.currentChecklist?.results?.[itemId]) {
                        this.currentChecklist.results[itemId].photo = response.filename;
                    }

                    this.showToast('Foto gespeichert');
                } else {
                    throw new Error(response.error || 'Upload fehlgeschlagen');
                }
            } catch (err) {
                console.error('Failed to upload defect photo:', err);
                this.showToast('Fehler beim Hochladen');

                // Restore add button
                if (photoSection) {
                    photoSection.innerHTML = `
                        <button type="button" class="btn-defect-photo" onclick="app.captureDefectPhoto(${itemId})">
                            📷 Foto hinzufügen
                        </button>`;
                }
            }
        };

        fileInput.click();
    }

    // Read file as base64
    readFileAsBase64(file) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = () => resolve(reader.result);
            reader.onerror = reject;
            reader.readAsDataURL(file);
        });
    }

    // Delete defect photo
    async deleteDefectPhoto(itemId) {
        if (!this.currentIntervention || !this.currentChecklist?.checklist?.id) {
            this.showToast('Fehler: Checkliste nicht geladen');
            return;
        }

        if (!confirm('Foto wirklich löschen?')) {
            return;
        }

        const photoSection = document.querySelector(`.defect-photo-section[data-item="${itemId}"]`);

        try {
            await this.apiCall(`defect-photo/${this.currentChecklist.checklist.id}/${itemId}`, {
                method: 'DELETE'
            });

            // Update UI - show add button again
            if (photoSection) {
                photoSection.innerHTML = `
                    <button type="button" class="btn-defect-photo" onclick="app.captureDefectPhoto(${itemId})">
                        📷 Foto hinzufügen
                    </button>`;
            }

            // Update local checklist data
            if (this.currentChecklist?.results?.[itemId]) {
                this.currentChecklist.results[itemId].photo = '';
            }

            this.showToast('Foto gelöscht');
        } catch (err) {
            console.error('Failed to delete defect photo:', err);
            this.showToast('Fehler beim Löschen');
        }
    }

    // View defect photo in fullscreen
    viewDefectPhoto(filename) {
        const url = this.getDefectPhotoUrl(filename);
        if (!url) return;

        // Create fullscreen overlay
        const overlay = document.createElement('div');
        overlay.className = 'defect-photo-overlay';
        overlay.innerHTML = `
            <div class="defect-photo-fullscreen">
                <button class="defect-photo-close" onclick="this.parentElement.parentElement.remove()">✕</button>
                <img src="${url}" alt="Mangel-Foto">
            </div>
        `;
        overlay.onclick = (e) => {
            if (e.target === overlay) {
                overlay.remove();
            }
        };
        document.body.appendChild(overlay);
    }

    // Save a single checklist item
    async saveChecklistItem(itemId, answer, note, skipToast = false) {
        if (!this.currentIntervention || !this.currentEquipment) return;

        // Build items object with item ID as key (API expects this format)
        const items = {};
        items[itemId] = {
            answer: answer,
            note: note
        };

        const data = { items };

        if (this.isOnline) {
            try {
                await this.apiCall(`checklist/${this.currentIntervention.id}/${this.currentEquipment.id}`, {
                    method: 'POST',
                    body: JSON.stringify(data)
                });
                if (!skipToast) {
                    // Don't show toast for individual saves to avoid spam
                }
            } catch (err) {
                console.error('Failed to save checklist item:', err);
                this.showToast('Fehler beim Speichern');
            }
        } else {
            // Queue for offline sync
            await offlineDB.addToSyncQueue({
                type: 'checklist-item',
                data: {
                    intervention_id: this.currentIntervention.id,
                    equipment_id: this.currentEquipment.id,
                    ...data
                }
            });
            if (!skipToast) {
                this.showToast('Offline gespeichert');
            }
        }
    }

    // Complete the checklist
    async completeChecklist() {
        if (!this.currentIntervention || !this.currentEquipment || !this.currentChecklist) {
            this.showToast('Fehler: Keine Checkliste geladen');
            return;
        }

        const checklistId = this.currentChecklist.checklist?.id;
        if (!checklistId) {
            this.showToast('Fehler: Checkliste nicht gefunden');
            return;
        }

        // Validate Ergebnis section is filled
        const ergebnisSection = this.currentChecklist.template.sections?.find(s => s.code === 'ergebnis');
        if (ergebnisSection) {
            const ergebnisItems = ergebnisSection.items || [];
            for (const item of ergebnisItems) {
                const selectEl = document.querySelector(`.checklist-item-select[data-item="${item.id}"]`);
                if (!selectEl || !selectEl.value) {
                    this.showToast('Bitte Ergebnis ausfüllen');
                    // Scroll to ergebnis section
                    const ergebnisSectionEl = document.querySelector('.checklist-section[data-section="ergebnis"]');
                    if (ergebnisSectionEl) {
                        ergebnisSectionEl.scrollIntoView({ behavior: 'smooth' });
                    }
                    return;
                }
            }
        }

        if (!confirm('Checkliste wirklich abschließen? Danach sind keine Änderungen mehr möglich.')) {
            return;
        }

        if (this.isOnline) {
            try {
                // Gather all current item values
                const items = {};
                document.querySelectorAll('.checklist-item-select').forEach(select => {
                    const itemId = select.dataset.item;
                    const noteEl = select.closest('.checklist-item').querySelector('.checklist-item-note');
                    items[itemId] = {
                        answer: select.value,
                        note: noteEl ? noteEl.value : ''
                    };
                });

                const response = await this.apiCall(`checklist/${this.currentIntervention.id}/${this.currentEquipment.id}/complete`, {
                    method: 'POST',
                    body: JSON.stringify({
                        checklist_id: checklistId,
                        items: items
                    })
                });

                // Show feedback about completion and PDF generation
                if (response.pdf_generated) {
                    this.showToast('Checkliste abgeschlossen & PDF erstellt');
                } else {
                    this.showToast('Checkliste abgeschlossen');
                    if (response.pdf_error) {
                        console.error('PDF generation error:', response.pdf_error);
                    }
                }

                // Reload checklist to show completion status
                await this.loadChecklist(this.currentIntervention.id, this.currentEquipment.id);
            } catch (err) {
                console.error('Failed to complete checklist:', err);
                this.showToast('Fehler beim Abschließen');
            }
        } else {
            this.showToast('Offline - Abschließen nicht möglich');
        }
    }

    // Open checklist PDF in new tab (preview = true for preview only, not saved)
    openChecklistPdf(preview = false) {
        if (!this.currentIntervention || !this.currentEquipment || !this.currentChecklist) {
            this.showToast('Fehler: Keine Checkliste verfügbar');
            return;
        }

        const checklistId = this.currentChecklist.checklist?.id;
        if (!checklistId) {
            this.showToast('Fehler: Checkliste nicht gefunden');
            return;
        }

        if (!this.isOnline) {
            this.showToast('Offline - PDF nicht verfügbar');
            return;
        }

        // Build URL to generate PDF using module URL from config
        // preview=1 means PDF is just displayed, not saved to documents
        const previewParam = preview ? '&preview=1' : '';
        const pdfUrl = `${CONFIG.moduleUrl}intervention_equipment_details.php?id=${this.currentIntervention.id}&equipment_id=${this.currentEquipment.id}&action=pdf_checklist&checklist_id=${checklistId}${previewParam}`;

        this.openPdfViewer(pdfUrl, 'Checkliste');
    }
}

// Initialize app when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.app = new ServiceReportApp();
});
