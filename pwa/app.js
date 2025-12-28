/**
 * Main PWA Application
 */

class ServiceReportApp {
    constructor() {
        this.currentView = 'viewInterventions';
        this.currentIntervention = null;
        this.currentEquipment = null;
        this.isOnline = navigator.onLine;
        this.signatureInstance = null;
        this.user = null;

        this.init();
    }

    async init() {
        // Initialize IndexedDB
        try {
            await offlineDB.init();
            console.log('IndexedDB initialized');
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

        // Sync if online
        if (this.isOnline) {
            this.syncData();
        }
    }

    async checkAuth() {
        // If server says we're authenticated, cache the auth data
        if (CONFIG.isAuthenticated && CONFIG.authData) {
            this.user = CONFIG.authData;
            await offlineDB.setMeta('auth', CONFIG.authData);
            console.log('Auth cached for offline use');
            return true;
        }

        // Not authenticated on server - check for cached auth
        const cachedAuth = await offlineDB.getMeta('auth');

        if (cachedAuth && cachedAuth.valid_until > Date.now() / 1000) {
            // Cached auth is still valid
            this.user = cachedAuth;
            console.log('Using cached auth for:', cachedAuth.login);

            if (this.isOnline) {
                // Online but not authenticated - session expired
                this.showAuthError('Sitzung abgelaufen. Bitte neu anmelden.');
                return false;
            }

            // Offline with valid cached auth - allow access
            this.showToast('Offline-Modus: ' + cachedAuth.name);
            return true;
        }

        // No valid auth
        if (this.isOnline) {
            this.showAuthError('Bitte melden Sie sich an.');
        } else {
            this.showAuthError('Offline - Keine gespeicherte Anmeldung vorhanden.');
        }
        return false;
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

        // Detail form submit
        document.getElementById('detailForm').addEventListener('submit', (e) => {
            e.preventDefault();
            this.saveDetail();
        });

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

        // Auto-save on input change (debounced)
        let saveTimeout;
        document.getElementById('detailForm').addEventListener('input', () => {
            clearTimeout(saveTimeout);
            saveTimeout = setTimeout(() => this.autoSaveDetail(), 2000);
        });
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
            case 'viewDetail':
                this.loadEquipment(this.currentIntervention);
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

        console.log('API call:', url);

        if (!this.isOnline) {
            throw new Error('Offline');
        }

        try {
            const response = await fetch(url, {
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    ...options.headers
                },
                ...options
            });

            if (!response.ok) {
                const text = await response.text();
                console.error('API Error:', response.status, text);
                throw new Error(`HTTP ${response.status}`);
            }

            return response.json();
        } catch (err) {
            console.error('API call failed:', endpoint, err);
            throw err;
        }
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
                console.log('API returned', interventions.length, 'interventions');

                // Save to IndexedDB
                await offlineDB.saveInterventions(interventions);
            } else {
                // Load from IndexedDB
                interventions = await offlineDB.getAll('interventions');
            }

            loadingEl.style.display = 'none';

            if (interventions.length === 0) {
                listEl.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-icon">📋</div>
                        <p>Keine Interventionen gefunden</p>
                    </div>
                `;
                return;
            }

            // Render interventions
            interventions.forEach(intervention => {
                listEl.appendChild(this.createInterventionCard(intervention));
            });

        } catch (err) {
            console.error('Failed to load interventions:', err);
            loadingEl.style.display = 'none';

            // Try loading from IndexedDB
            const cached = await offlineDB.getAll('interventions');
            if (cached.length > 0) {
                cached.forEach(intervention => {
                    listEl.appendChild(this.createInterventionCard(intervention));
                });
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
            statusClass = 'draft';
            statusText = 'Entwurf';
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
                <div style="margin-top:12px; padding-top:12px; border-top:1px solid #eee;">
                    <p style="margin:0; font-size:12px; color:#263c5c; font-weight:600;">📍 Objektadresse</p>
                    <p style="margin:4px 0 0; font-size:13px; color:#333;">
                        ${addr.name || ''}
                    </p>
                    <p style="margin:2px 0 0; font-size:13px; color:#666;">
                        ${addr.address || ''}<br>
                        ${addr.zip || ''} ${addr.town || ''}
                    </p>
                    ${intervention.object_addresses.length > 1 ? `<p style="margin:4px 0 0; font-size:11px; color:#999;">+ ${intervention.object_addresses.length - 1} weitere Adresse(n)</p>` : ''}
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
                <p style="margin:0; font-size:14px; font-weight:500; color:#333;">
                    ${intervention.customer?.name || 'Kunde'}
                </p>
                <p style="margin:4px 0 0; font-size:13px; color:#666;">
                    ${intervention.customer?.address || ''}<br>
                    ${intervention.customer?.zip || ''} ${intervention.customer?.town || ''}
                </p>
                ${objectAddressHtml}
                ${intervention.date_start ? `<p style="margin:12px 0 0; font-size:12px; color:#999;">📅 ${this.formatDate(intervention.date_start)}</p>` : ''}
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

            if (this.isOnline) {
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
            } else {
                equipment = await offlineDB.getEquipmentForIntervention(intervention.id);
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

            console.log('Equipment loaded, signedStatus:', signedStatus);

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

            // Equipment type labels
            const typeLabels = {
                'door_swing': 'Drehtür',
                'door_sliding': 'Schiebetür',
                'fire_door': 'Brandschutztür',
                'door_closer': 'Türschließer',
                'hold_open': 'Feststellanlage',
                'rws': 'RWS',
                'rwa': 'RWA',
                'other': 'Sonstige'
            };

            equipment.forEach(eq => {
                const item = document.createElement('div');
                item.className = 'equipment-item card-clickable';

                const typeName = typeLabels[eq.type] || eq.type || '';
                const linkTypeBadge = eq.link_type === 'maintenance'
                    ? '<span class="link-type-badge maintenance">Wartung</span>'
                    : '<span class="link-type-badge service">Service</span>';

                item.innerHTML = `
                    <div class="equipment-icon">🚪</div>
                    <div class="equipment-info">
                        <div class="equipment-ref">${eq.ref} - ${typeName}</div>
                        <div class="equipment-label">${eq.manufacturer ? eq.manufacturer + ', ' : ''}${eq.label || ''}</div>
                        ${eq.location ? `<div class="equipment-label" style="color:#888;">${eq.location}</div>` : ''}
                    </div>
                    ${linkTypeBadge}
                `;

                item.addEventListener('click', () => {
                    this.currentEquipment = eq;
                    this.loadDetail(eq);
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

    // Load detail form
    async loadDetail(equipment) {
        try {
            this.showView('viewDetail', equipment.ref);

            document.getElementById('detailEquipmentRef').textContent = `${equipment.ref} - ${equipment.label || ''}`;

            // Try to get from IndexedDB first (for offline edits)
            let detail = null;
            try {
                detail = await offlineDB.getDetail(this.currentIntervention.id, equipment.id);
            } catch (e) {
                console.log('No local detail found');
            }

            // If no local edits, use server data
            if (!detail && equipment.detail) {
                detail = {
                    intervention_id: this.currentIntervention.id,
                    equipment_id: equipment.id,
                    ...equipment.detail
                };
            }

            // Convert duration to hours and minutes
            const totalMinutes = parseInt(detail?.work_duration) || 0;
            const hours = Math.floor(totalMinutes / 60);
            const minutes = totalMinutes % 60;

            // Populate form
            document.getElementById('workDate').value = detail?.work_date || this.formatDateInput(new Date());
            document.getElementById('workHours').value = hours > 0 ? hours : '';
            document.getElementById('workMinutes').value = String(Math.floor(minutes / 15) * 15);
            document.getElementById('workDone').value = detail?.work_done || '';
            document.getElementById('issuesFound').value = detail?.issues_found || '';
            document.getElementById('recommendations').value = detail?.recommendations || '';
            document.getElementById('notes').value = detail?.notes || '';

            // Load and display materials
            const materials = equipment.materials || [];
            this.renderMaterials(materials);
        } catch (err) {
            console.error('Error loading detail:', err);
            this.showToast('Fehler beim Laden');
        }
    }

    // Save detail
    async saveDetail() {
        // Calculate total minutes from hours and minutes
        const hours = parseInt(document.getElementById('workHours').value) || 0;
        const minutes = parseInt(document.getElementById('workMinutes').value) || 0;
        const totalMinutes = (hours * 60) + minutes;

        const detail = {
            intervention_id: this.currentIntervention.id,
            equipment_id: this.currentEquipment.id,
            work_date: document.getElementById('workDate').value,
            work_duration: totalMinutes,
            work_done: document.getElementById('workDone').value,
            issues_found: document.getElementById('issuesFound').value,
            recommendations: document.getElementById('recommendations').value,
            notes: document.getElementById('notes').value
        };

        // Save to IndexedDB
        await offlineDB.saveDetail(detail);

        // Update equipment in memory
        if (this.currentEquipment) {
            this.currentEquipment.detail = detail;
        }

        // Try to sync if online
        if (this.isOnline) {
            try {
                await this.apiCall(`detail/${detail.intervention_id}/${detail.equipment_id}`, {
                    method: 'POST',
                    body: JSON.stringify(detail)
                });
                this.showToast('Gespeichert');
            } catch (err) {
                this.showToast('Offline gespeichert - wird synchronisiert');
            }
        } else {
            this.showToast('Offline gespeichert');
        }
    }

    async autoSaveDetail() {
        await this.saveDetail();
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
        console.log('jSignature initialized');
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
        console.log('saveSignature called, signatureInstance:', this.signatureInstance);

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
            console.log('Signature dataUrl type:', typeof dataUrl, 'length:', dataUrl ? dataUrl.length : 0);

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
                console.log('Native data:', nativeData);
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

        console.log('Final base64 length:', base64 ? base64.length : 0);

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
            // Get sync queue
            const queue = await offlineDB.getSyncQueue();

            if (queue.length === 0) {
                this.showToast('Alles synchronisiert');
                this.updateOnlineStatus();
                return;
            }

            // Build changes array
            const changes = queue.map(item => ({
                type: item.type,
                data: item.data
            }));

            // Send to server
            const result = await this.apiCall('sync', {
                method: 'POST',
                body: JSON.stringify({ changes })
            });

            if (result.status === 'ok' || result.status === 'partial') {
                // Clear queue
                await offlineDB.clearSyncQueue();

                // Mark details as synced
                const details = await offlineDB.getAll('details');
                for (const detail of details) {
                    detail.synced = true;
                    await offlineDB.put('details', detail);
                }

                this.showToast(`${changes.length} Änderungen synchronisiert`);
            }

            // Refresh data from server
            await this.loadInterventions();

        } catch (err) {
            console.error('Sync failed:', err);
            this.showToast('Sync fehlgeschlagen');
        }

        this.updateOnlineStatus();
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

        const listEl = document.getElementById('availableEquipmentList');
        listEl.innerHTML = `
            <div class="loading">
                <div class="spinner"></div>
                <p>Lade verfügbare Anlagen...</p>
            </div>
        `;

        try {
            const data = await this.apiCall(`available-equipment/${this.currentIntervention.id}`);
            const equipment = data.equipment || [];

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

                // Address header
                const header = document.createElement('div');
                header.style.cssText = 'padding:12px;background:#f5f5f5;font-weight:600;font-size:13px;border-bottom:1px solid #ddd;';
                header.innerHTML = `📍 ${group.address?.name || ''} - ${group.address?.zip || ''} ${group.address?.town || ''}`;
                listEl.appendChild(header);

                // Equipment items
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
                            this.linkEquipment(eq.id, btn.dataset.type);
                        });
                    });

                    listEl.appendChild(item);
                });
            });

        } catch (err) {
            console.error('Failed to load available equipment:', err);
            listEl.innerHTML = `
                <div class="empty-state" style="padding: 20px 0;">
                    <p>Fehler beim Laden</p>
                </div>
            `;
        }
    }

    closeEquipmentModal() {
        document.getElementById('equipmentModal').classList.remove('show');
    }

    async linkEquipment(equipmentId, linkType) {
        try {
            await this.apiCall('link-equipment', {
                method: 'POST',
                body: JSON.stringify({
                    intervention_id: this.currentIntervention.id,
                    equipment_id: equipmentId,
                    link_type: linkType
                })
            });

            this.showToast('Anlage hinzugefügt');
            this.closeEquipmentModal();

            // Reload equipment list
            this.loadEquipment(this.currentIntervention);
        } catch (err) {
            console.error('Failed to link equipment:', err);
            this.showToast('Fehler beim Hinzufügen');
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

            console.log('Release result:', result);

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
            const data = await this.apiCall(`intervention/${this.currentIntervention.id}/documents`);
            console.log('Documents response:', data);
            const documents = data.documents || [];

            if (documents.length === 0) {
                let debugInfo = '';
                if (data.doc_path) {
                    debugInfo = `<p style="font-size:10px;color:#999;margin-top:8px;">Pfad: ${data.doc_path}</p>`;
                    debugInfo += `<p style="font-size:10px;color:#999;">Existiert: ${data.doc_dir_exists ? 'Ja' : 'Nein'}</p>`;
                }
                listEl.innerHTML = `
                    <div class="empty-state" style="padding: 20px 0;">
                        <div class="empty-icon">📄</div>
                        <p>Keine Dokumente vorhanden</p>
                        <p style="font-size:12px;color:#666;">Bitte zuerst freigeben um PDF zu erstellen.</p>
                        ${debugInfo}
                    </div>
                `;
                return;
            }

            listEl.innerHTML = '';
            documents.forEach(doc => {
                const item = document.createElement('div');
                item.className = 'document-item';

                // Create preview URL (add &attachment=0 for inline display)
                const previewUrl = doc.url + '&attachment=0';

                item.innerHTML = `
                    <div class="document-icon">${doc.type === 'signature' ? '✍️' : '📄'}</div>
                    <div class="document-info">
                        <div class="document-name">${doc.name}</div>
                        <div class="document-date">${this.formatDate(new Date(doc.date * 1000))}</div>
                    </div>
                    <div class="document-actions">
                        <a href="${previewUrl}" target="_blank" class="doc-action" title="Vorschau">🔍</a>
                        <a href="${doc.url}" target="_blank" class="doc-action" title="Download">⬇️</a>
                    </div>
                `;
                listEl.appendChild(item);
            });
        } catch (err) {
            console.error('Failed to load documents:', err);
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

    formatFileSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }
}

// Initialize app when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.app = new ServiceReportApp();
});
