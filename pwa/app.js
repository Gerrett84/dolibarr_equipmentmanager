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
        this.isOnline = navigator.onLine;
        this.signatureInstance = null;
        this.user = null;
        this.pwaToken = null; // v1.8 - PWA authentication token
        this.currentChecklist = null; // v2.0 - checklist data for maintenance equipment
        this.interventionFilter = 'open'; // v4.1 - current filter: 'open', 'released', 'signed'
        this.allInterventions = []; // v4.1 - cache all interventions for filtering

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

        // Load initial data
        await this.loadInterventions();

        // Prefetch all data for offline use when online
        if (this.isOnline) {
            // Run prefetch in background (don't await - let user interact)
            // Status update is handled in prefetchAllData's finally block
            this.prefetchAllData().catch(err => {
                console.warn('Background prefetch failed:', err);
            });
        }
    }

    async checkAuth() {
        // If server says we're authenticated, cache the auth data
        if (CONFIG.isAuthenticated && CONFIG.authData) {
            this.user = CONFIG.authData;
            await offlineDB.setMeta('auth', CONFIG.authData);

            // Show trusted device banner if available
            this.showTrustedDeviceBanner();

            return true;
        }

        // Not authenticated on server - try auto-login with saved credentials
        if (this.isOnline) {
            const savedCredentials = await offlineDB.getMeta('credentials');
            if (savedCredentials) {
                const loginResult = await this.tryAutoLogin(savedCredentials.username, savedCredentials.password);
                if (loginResult) {
                    this.showToast('Automatisch angemeldet');
                    return true;
                }
            }
        }

        // Fallback to cached auth (for offline mode)
        const cachedAuth = await offlineDB.getMeta('auth');
        if (cachedAuth && cachedAuth.valid_until > Date.now() / 1000) {
            this.user = cachedAuth;

            if (this.isOnline) {
                // Online but no valid session - show login form
                this.showLoginForm();
                return false;
            }

            // Offline with valid cached auth - allow access
            this.showToast('Offline-Modus: ' + cachedAuth.name);
            return true;
        }

        // No valid auth - show login form
        if (this.isOnline) {
            this.showLoginForm();
        } else {
            this.showAuthError('Offline - Keine gespeicherte Anmeldung vorhanden.');
        }
        return false;
    }

    // Show login form - redirects to settings page for credential storage
    showLoginForm() {
        document.getElementById('interventionsLoading').style.display = 'none';
        document.getElementById('interventionsList').innerHTML = `
            <div class="login-form" style="padding: 20px;">
                <div style="text-align:center;margin-bottom:20px;">
                    <div style="font-size:48px;">🔐</div>
                    <h3 style="margin:10px 0;">Anmeldung erforderlich</h3>
                    <p style="color:#666;font-size:14px;">
                        Bitte speichern Sie Ihre Login-Daten in den Einstellungen.
                    </p>
                </div>

                <a href="settings.php" class="btn btn-primary" style="display:block;text-align:center;text-decoration:none;padding:14px;font-size:16px;border-radius:8px;background:#263c5c;color:white;">
                    ⚙️ Einstellungen öffnen
                </a>

                <div style="margin-top:20px;padding-top:20px;border-top:1px solid #eee;">
                    <p style="color:#666;font-size:13px;text-align:center;margin:0 0 12px 0;">
                        Oder melden Sie sich direkt an:
                    </p>
                    <form id="pwaLoginForm">
                        <div style="margin-bottom:12px;">
                            <input type="text" id="loginUsername" placeholder="Benutzername" required
                                style="width:100%;padding:12px;border:1px solid #ddd;border-radius:8px;font-size:16px;">
                        </div>
                        <div style="margin-bottom:12px;">
                            <input type="password" id="loginPassword" placeholder="Passwort" required
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
                            Anmelden
                        </button>
                        <div id="loginError" style="color:#d32f2f;text-align:center;margin-top:12px;display:none;"></div>
                    </form>
                </div>
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
                    // Save credentials if "remember" is checked
                    if (remember) {
                        await offlineDB.setMeta('credentials', {
                            username: username,
                            password: password,
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

    // Try auto-login with saved credentials
    async tryAutoLogin(username, password) {
        try {
            const formData = new FormData();
            formData.append('pwa_autologin', '1');
            formData.append('username', username);
            formData.append('password', password);

            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });

            if (response.ok) {
                const result = await response.json();
                if (result.status === 'ok') {
                    // Update auth data - don't reload, just continue
                    this.user = {
                        id: result.user.id,
                        login: result.user.login,
                        name: result.user.name,
                        valid_until: (Date.now() / 1000) + (90 * 24 * 3600)
                    };
                    await offlineDB.setMeta('auth', this.user);
                    return true; // No reload - session is set, API calls will work
                }
            }
            return false;
        } catch (err) {
            console.error('Auto-login failed:', err);
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
                <a href="../../../user/card.php" class="btn btn-primary" style="margin-top:16px;">Anmelden</a>
            </div>
        `;
    }

    setupEventListeners() {
        // Online/Offline events
        window.addEventListener('online', () => {
            this.isOnline = true;
            this.updateOnlineStatus();
            this.syncData();
        });

        window.addEventListener('offline', () => {
            this.isOnline = false;
            this.updateOnlineStatus();
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

        // Sync button
        document.getElementById('btnSync').addEventListener('click', () => this.syncData());

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

        // Release button
        document.getElementById('navRelease').addEventListener('click', () => this.toggleRelease());

        // Documents button
        document.getElementById('navDocuments').addEventListener('click', () => this.showDocuments());
        document.getElementById('btnCloseDocuments').addEventListener('click', () => this.closeDocumentsModal());

        // Info button
        document.getElementById('navInfo').addEventListener('click', () => this.showInfo());
        document.getElementById('btnCloseInfo').addEventListener('click', () => this.closeInfoModal());
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

        if (viewId === 'viewInterventions') {
            backBtn.style.display = 'none';
            headerTitle.textContent = 'Serviceberichte';
            document.getElementById('navRelease').style.display = 'none';
            document.getElementById('navInfo').style.display = 'none';
            document.getElementById('navDocuments').style.display = 'none';
            document.getElementById('navSignature').style.display = 'none';
        } else {
            backBtn.style.display = 'block';
            if (title) headerTitle.textContent = title;
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
                // Already signed
                signatureCard.innerHTML = `
                    <div class="card-header">
                        <h3 class="card-title">Kundenunterschrift</h3>
                    </div>
                    <div class="card-body">
                        <div class="empty-state">
                            <div class="empty-icon">✅</div>
                            <p>Unterschrift bereits vorhanden</p>
                        </div>
                    </div>
                `;
                saveBtn.style.display = 'none';
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

        try {
            const response = await fetch(url, {
                credentials: 'same-origin',
                headers,
                ...options
            });

            if (!response.ok) {
                const text = await response.text();
                console.error('API Error:', response.status, text);

                // If unauthorized and we have a PWA token, it might be expired
                if (response.status === 401 && this.pwaToken) {
                    this.pwaToken = null;
                    await offlineDB.setMeta('pwaToken', null);
                }

                throw new Error(`HTTP ${response.status}`);
            }

            return response.json();
        } catch (err) {
            console.error('API call failed:', endpoint, err);
            throw err;
        }
    }

    // Get intervention status category
    // status: 0=Entwurf/Draft, 1=Validiert, 3=Closed
    // signed_status: 0=not released, 1=released for signature, 3=signed
    getInterventionStatus(intervention) {
        const signedStatus = intervention.signed_status || 0;

        // Signed = completely done
        if (signedStatus >= 3) return 'signed';
        // Released for signature (regardless of base status)
        if (signedStatus >= 1) return 'released';
        // Everything else is open (including drafts)
        return 'open';
    }

    // Count interventions by status
    countByStatus(interventions) {
        const counts = { open: 0, released: 0, signed: 0 };
        interventions.forEach(i => {
            const status = this.getInterventionStatus(i);
            counts[status]++;
        });
        return counts;
    }

    // Filter interventions based on current filter
    filterInterventions(interventions) {
        return interventions.filter(i => this.getInterventionStatus(i) === this.interventionFilter);
    }

    // Render filter tabs - simplified: Offen, Freigegeben, Erledigt
    renderFilterTabs(counts) {
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
        `;
    }

    // Set filter and re-render
    setFilter(filter) {
        this.interventionFilter = filter;
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

        // Render intervention cards
        filtered.forEach(intervention => {
            listEl.appendChild(this.createInterventionCard(intervention));
        });
    }

    // Load interventions
    async loadInterventions() {
        const loadingEl = document.getElementById('interventionsLoading');
        const listEl = document.getElementById('interventionsList');

        loadingEl.style.display = 'block';
        listEl.innerHTML = '';

        try {
            let interventions = [];

            if (this.isOnline) {
                // Fetch from API
                // Fetch all interventions (including closed ones for now)
                const data = await this.apiCall('interventions?status=all');
                interventions = data.interventions || [];
                // console.log('API returned', interventions.length, 'interventions');

                // Save to IndexedDB
                await offlineDB.saveInterventions(interventions);
            } else {
                // Load from IndexedDB
                interventions = await offlineDB.getAll('interventions');
            }

            loadingEl.style.display = 'none';

            // Cache interventions for filtering
            this.allInterventions = interventions;

            // Render with current filter
            this.renderInterventionsList();

        } catch (err) {
            console.error('Failed to load interventions:', err);
            loadingEl.style.display = 'none';

            // Try loading from IndexedDB
            const cached = await offlineDB.getAll('interventions');
            if (cached.length > 0) {
                this.allInterventions = cached;
                this.renderInterventionsList();
                this.showToast('Offline-Daten geladen');
            } else {
                listEl.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-icon">⚠️</div>
                        <p>Fehler beim Laden</p>
                        <p style="font-size:12px;">${err.message}</p>
                    </div>
                `;
            }
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
        } else if (signedStatus >= 3) {
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

        // Format object addresses
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
                        ${addr.address || ''}<br>
                        ${addr.zip || ''} ${addr.town || ''}
                    </p>
                    ${intervention.object_addresses.length > 1 ? `<p class="info-text-muted" style="margin:4px 0 0; font-size:11px;">+ ${intervention.object_addresses.length - 1} weitere Adresse(n)</p>` : ''}
                </div>
            `;
        }

        card.innerHTML = `
            <div class="card-header">
                <div>
                    <h3 class="card-title">${intervention.ref || 'Intervention'}</h3>
                </div>
                <span class="badge badge-${statusClass}">${statusText}</span>
            </div>
            <div class="card-body">
                <p class="customer-name">
                    ${intervention.customer?.name || 'Kunde'}
                </p>
                <p class="customer-address">
                    ${intervention.customer?.address || ''}<br>
                    ${intervention.customer?.zip || ''} ${intervention.customer?.town || ''}
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

            if (this.isOnline) {
                try {
                    // Fetch full intervention data to get updated signed_status
                    const fullData = await this.apiCall(`intervention/${intervention.id}`);
                    if (fullData.intervention) {
                        signedStatus = fullData.intervention.signed_status || 0;
                        // Update currentIntervention with fresh data
                        this.currentIntervention.signed_status = signedStatus;
                        this.currentIntervention.status = fullData.intervention.status;
                    }
                    equipment = fullData.equipment || [];
                    await offlineDB.saveEquipment(intervention.id, equipment);

                    // Also update intervention in IndexedDB
                    try {
                        const interventions = await offlineDB.getAll('interventions');
                        const idx = interventions.findIndex(i => i.id === intervention.id);
                        if (idx >= 0) {
                            interventions[idx].signed_status = signedStatus;
                            await offlineDB.saveInterventions(interventions);
                        }
                    } catch (e) {
                        console.error('Failed to update IndexedDB:', e);
                    }
                } catch (apiErr) {
                    console.warn('API call failed, falling back to cache:', apiErr);
                    equipment = await offlineDB.getEquipmentForIntervention(intervention.id);
                    loadedFromCache = true;
                }
            } else {
                equipment = await offlineDB.getEquipmentForIntervention(intervention.id);
                loadedFromCache = true;
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

            // Show info button
            document.getElementById('navInfo').style.display = 'flex';

            // Show/hide documents button
            const docsBtn = document.getElementById('navDocuments');
            docsBtn.style.display = 'flex';

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

            // Only show signature button if released (signed_status >= 1)
            const sigBtn = document.getElementById('navSignature');
            if (signedStatus >= 1 && signedStatus < 3) {
                sigBtn.style.display = 'flex';
            } else if (signedStatus >= 3) {
                // Already signed - hide signature button
                sigBtn.style.display = 'none';
            } else {
                // Not released - hide signature button
                sigBtn.style.display = 'none';
            }

            // Add "Add Equipment" button
            const addBtn = document.createElement('div');
            addBtn.className = 'add-equipment-btn';
            addBtn.innerHTML = '<span>➕</span> Anlage hinzufügen';
            addBtn.addEventListener('click', () => this.showEquipmentModal());
            listEl.appendChild(addBtn);

            if (equipment.length === 0) {
                listEl.innerHTML += `
                    <div class="empty-state">
                        <div class="empty-icon">🔧</div>
                        <p>Kein Equipment verknüpft</p>
                    </div>
                `;
                return;
            }

            // Create equipment list
            const card = document.createElement('div');
            card.className = 'card';

            // Equipment type labels - store as class property for reuse
            this.equipmentTypeLabels = {
                'door_swing': 'Drehtür',
                'door_sliding': 'Schiebetür',
                'fire_door': 'Brandschutztür',
                'fire_door_fsa': 'Brandschutztür (FSA)',
                'fire_gate': 'Brandschutztor',
                'door_closer': 'Türschließer',
                'hold_open': 'Feststellanlage',
                'rws': 'RWS',
                'rwa': 'RWA',
                'other': 'Sonstige'
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

                item.innerHTML = `
                    <div class="equipment-icon">${statusIcon}</div>
                    <div class="equipment-info">
                        <div class="equipment-ref">${eq.ref} - ${typeName}</div>
                        <div class="equipment-label">${eq.manufacturer ? eq.manufacturer + ', ' : ''}${eq.label || ''}</div>
                        ${eq.location ? `<div class="equipment-label" style="color:#888;">${eq.location}</div>` : ''}
                    </div>
                    ${linkTypeBadge}
                `;
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

            // Show link type badge (Wartung/Service) on the right
            const linkTypeBadge = equipment.link_type === 'maintenance'
                ? '<span class="link-type-badge maintenance">Wartung</span>'
                : '<span class="link-type-badge service">Service</span>';
            document.getElementById('entriesLinkType').innerHTML = linkTypeBadge;

            // Show equipment details
            this.renderEquipmentDetails(equipment);

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
                    const durationText = hours > 0 || mins > 0 ? `${hours} Std. ${mins} min.` : '';

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

            // Load and display materials
            const materials = equipment.materials || [];
            this.renderMaterials(materials);

            // Load checklist if maintenance equipment
            const checklistCard = document.getElementById('checklistCard');
            if (equipment.link_type === 'maintenance') {
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

        const hours = Math.floor((entry.work_duration || 0) / 60);
        const minutes = (entry.work_duration || 0) % 60;
        document.getElementById('entryHours').value = hours > 0 ? hours : '';
        document.getElementById('entryMinutes').value = String(Math.floor(minutes / 15) * 15);

        document.getElementById('entryWorkDone').value = entry.work_done || '';
        document.getElementById('entryIssuesFound').value = entry.issues_found || '';

        // Show delete button for existing entries
        document.getElementById('btnDeleteEntry').style.display = 'block';
    }

    // Add new entry (v1.7)
    addNewEntry() {
        this.currentEntry = null;
        this.showView('viewEntry', 'Neuer Eintrag');

        document.getElementById('entryTitle').textContent = 'Neuer Eintrag';

        // Clear form
        document.getElementById('entryDate').value = this.formatDateInput(new Date());
        document.getElementById('entryHours').value = '';
        document.getElementById('entryMinutes').value = '0';
        document.getElementById('entryWorkDone').value = '';
        document.getElementById('entryIssuesFound').value = '';

        // Hide delete button for new entries
        document.getElementById('btnDeleteEntry').style.display = 'none';
    }

    // Save entry (v1.7)
    async saveEntry() {
        const hours = parseInt(document.getElementById('entryHours').value) || 0;
        const minutes = parseInt(document.getElementById('entryMinutes').value) || 0;
        const totalMinutes = (hours * 60) + minutes;

        const entryData = {
            intervention_id: this.currentIntervention.id,
            equipment_id: this.currentEquipment.id,
            work_date: document.getElementById('entryDate').value,
            work_duration: totalMinutes,
            work_done: document.getElementById('entryWorkDone').value,
            issues_found: document.getElementById('entryIssuesFound').value
        };

        // Add entry_id if editing existing entry
        if (this.currentEntry && this.currentEntry.id) {
            entryData.entry_id = this.currentEntry.id;
        }

        // Try to sync if online
        if (this.isOnline) {
            try {
                await this.apiCall(`detail/${entryData.intervention_id}/${entryData.equipment_id}`, {
                    method: 'POST',
                    body: JSON.stringify(entryData)
                });
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
                this.showToast('Unterschrift gespeichert - Auftrag abgeschlossen');
                this.currentIntervention.signed_status = 3;
                this.currentIntervention.status = 3; // Closed

                // Reload interventions list to reflect new status
                await this.loadInterventions();
            } catch (err) {
                this.showToast('Offline gespeichert - wird synchronisiert');
            }
        } else {
            this.showToast('Unterschrift offline gespeichert');
        }

        // Go back to interventions list
        this.showView('viewInterventions');
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
                // Separate link-equipment from other changes
                const linkEquipmentChanges = queue.filter(item => item.type === 'link-equipment');
                const otherChanges = queue.filter(item => item.type !== 'link-equipment');

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
            for (const intervention of interventions) {
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

    formatDateInput(date) {
        return date.toISOString().split('T')[0];
    }

    showToast(message) {
        const toast = document.getElementById('toast');
        toast.textContent = message;
        toast.classList.add('show');

        setTimeout(() => {
            toast.classList.remove('show');
        }, 3000);
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

            if (this.isOnline) {
                const data = await this.apiCall(`available-equipment/${this.currentIntervention.id}`);
                equipment = data.equipment || [];
                // Cache for offline use
                await offlineDB.saveAvailableEquipment(this.currentIntervention.id, equipment);
            } else {
                // Load from cache when offline
                equipment = await offlineDB.getAvailableEquipment(this.currentIntervention.id);
            }

            this.availableEquipmentData = equipment; // Store for multi-select

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
                header.innerHTML = `
                    <input type="checkbox" class="address-select-all" data-address="${addrKey}" style="width:18px;height:18px;">
                    <span>📍 ${group.address?.name || ''} - ${group.address?.zip || ''} ${group.address?.town || ''}</span>
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
            header.innerHTML = `📍 ${group.address?.name || ''} - ${group.address?.zip || ''} ${group.address?.town || ''}`;
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
                await offlineDB.addToSyncQueue({
                    type: 'link-equipment',
                    data: {
                        intervention_id: this.currentIntervention.id,
                        equipment_id: equipmentId,
                        link_type: linkType
                    }
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
                            <a href="${previewUrl}" target="_blank" class="doc-action" title="Vorschau">🔍</a>
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

    async showInfo() {
        document.getElementById('infoModal').classList.add('show');

        const contentEl = document.getElementById('infoContent');

        if (!this.currentIntervention) {
            contentEl.innerHTML = '<p>Keine Intervention ausgewählt</p>';
            return;
        }

        const intervention = this.currentIntervention;

        // Build info content
        let html = '';

        // Customer info first (most important)
        if (intervention.customer) {
            html += '<div class="info-section">';
            html += '<h4 class="info-heading">Kunde</h4>';
            html += `<div class="info-text">`;
            html += `<strong>${this.escapeHtml(intervention.customer.name)}</strong><br>`;
            if (intervention.customer.address) {
                html += `${this.escapeHtml(intervention.customer.address)}<br>`;
            }
            if (intervention.customer.zip || intervention.customer.town) {
                html += `${this.escapeHtml(intervention.customer.zip || '')} ${this.escapeHtml(intervention.customer.town || '')}`;
            }
            html += `</div>`;
            html += '</div>';
        }

        // Object addresses (from socpeople linked to equipment)
        html += '<div class="info-section info-section-divider">';
        html += '<h4 class="info-heading">Objektadresse</h4>';

        // Use object_addresses from intervention data (linked via equipment -> socpeople)
        if (intervention.object_addresses && intervention.object_addresses.length > 0) {
            intervention.object_addresses.forEach(addr => {
                html += `<div class="info-text" style="margin-bottom:8px;">`;
                if (addr.name) {
                    html += `<strong>${this.escapeHtml(addr.name)}</strong><br>`;
                }
                if (addr.address) {
                    html += `${this.escapeHtml(addr.address)}<br>`;
                }
                if (addr.zip || addr.town) {
                    html += `${this.escapeHtml(addr.zip || '')} ${this.escapeHtml(addr.town || '')}`;
                }
                html += `</div>`;
            });
        } else {
            html += '<p class="info-text-muted">Keine Objektadresse hinterlegt</p>';
        }
        html += '</div>';

        // Description (Auftragsbeschreibung)
        html += '<div class="info-section info-section-divider">';
        html += '<h4 class="info-heading">Auftragsbeschreibung</h4>';
        if (intervention.description) {
            html += `<div class="info-text">${this.escapeHtml(intervention.description).replace(/\n/g, '<br>')}</div>`;
        } else {
            html += '<p class="info-text-muted">Keine Beschreibung vorhanden</p>';
        }
        html += '</div>';

        // Public Note
        if (intervention.note_public) {
            html += '<div class="info-section info-section-divider">';
            html += '<h4 class="info-heading">Öffentliche Anmerkung</h4>';
            html += `<div class="info-text">${this.escapeHtml(intervention.note_public).replace(/\n/g, '<br>')}</div>`;
            html += '</div>';
        }

        // Private Note
        if (intervention.note_private) {
            html += '<div class="info-section info-section-divider">';
            html += '<h4 class="info-heading">Private Anmerkung</h4>';
            html += `<div class="info-text">${this.escapeHtml(intervention.note_private).replace(/\n/g, '<br>')}</div>`;
            html += '</div>';
        }

        contentEl.innerHTML = html;
    }

    closeInfoModal() {
        document.getElementById('infoModal').classList.remove('show');
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

        // Add click handlers for editable fields
        const labelEl = document.getElementById('eqDetailLabel');
        const locationEl = document.getElementById('eqDetailLocation');
        const manufacturerEl = document.getElementById('eqDetailManufacturer');

        // Style editable fields
        [labelEl, locationEl, manufacturerEl].forEach(el => {
            el.style.background = 'var(--input-bg)';
            el.style.border = '1px dashed var(--border-color)';
        });

        // Click handlers
        labelEl.onclick = () => this.editEquipmentField('label', 'Bezeichnung', equipment.label || '');
        locationEl.onclick = () => this.editEquipmentField('location_note', 'Standort', equipment.location || '');
        manufacturerEl.onclick = () => this.editEquipmentField('manufacturer', 'Hersteller', equipment.manufacturer || '');
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
                } else {
                    // Default fallback to ok_mangel
                    html += `<option value="ok" ${currentAnswer === 'ok' ? 'selected' : ''}>OK</option>`;
                    html += `<option value="mangel" ${currentAnswer === 'mangel' ? 'selected' : ''}>Mangel</option>`;
                    html += `<option value="nv" ${currentAnswer === 'nv' ? 'selected' : ''}>n.V.</option>`;
                }

                html += `</select>`;
                html += `</div>`;

                // Note field
                html += `<input type="text" class="checklist-item-note"
                            placeholder="Anmerkung..."
                            data-item="${itemId}"
                            value="${this.escapeHtml(currentNote)}"
                            ${!canEditChecklist ? 'disabled' : ''}
                            onchange="app.onChecklistNoteChange(this)">`;

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

        await this.saveChecklistItem(itemCode, answer, note, skipToast);
    }

    // Handle note change
    async onChecklistNoteChange(inputEl) {
        const itemCode = inputEl.dataset.item;
        const note = inputEl.value;

        // Get answer value
        const selectEl = inputEl.closest('.checklist-item').querySelector('.checklist-item-select');
        const answer = selectEl ? selectEl.value : '';

        await this.saveChecklistItem(itemCode, answer, note);
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

        window.open(pdfUrl, '_blank');
    }
}

// Initialize app when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.app = new ServiceReportApp();
});
