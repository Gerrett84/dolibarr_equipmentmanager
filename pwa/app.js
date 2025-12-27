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
        } else {
            backBtn.style.display = 'block';
            if (title) headerTitle.textContent = title;
        }

        this.currentView = viewId;

        // Initialize signature if needed
        if (viewId === 'viewSignature' && !this.signatureInstance) {
            this.initSignature();
        }
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

        const statusClass = intervention.status === 0 ? 'draft' :
                           intervention.status === 1 ? 'open' :
                           intervention.signed_status > 0 ? 'signed' : 'done';

        const statusText = intervention.status === 0 ? 'Entwurf' :
                          intervention.status === 1 ? 'Offen' :
                          intervention.signed_status > 0 ? 'Unterschrieben' : 'Abgeschlossen';

        card.innerHTML = `
            <div class="card-header">
                <div>
                    <h3 class="card-title">${intervention.ref || 'Intervention'}</h3>
                    <p class="card-subtitle">${intervention.customer?.name || 'Kunde'}</p>
                </div>
                <span class="badge badge-${statusClass}">${statusText}</span>
            </div>
            <div class="card-body">
                <p style="margin:0; font-size:13px; color:#666;">
                    ${intervention.customer?.address || ''}<br>
                    ${intervention.customer?.zip || ''} ${intervention.customer?.town || ''}
                </p>
                ${intervention.date_start ? `<p style="margin:8px 0 0; font-size:12px; color:#999;">📅 ${this.formatDate(intervention.date_start)}</p>` : ''}
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

        // Show signature nav button
        document.getElementById('navSignature').style.display = 'flex';

        try {
            let equipment = [];

            if (this.isOnline) {
                const data = await this.apiCall(`intervention/${intervention.id}/equipment`);
                equipment = data.equipment || [];
                await offlineDB.saveEquipment(intervention.id, equipment);
            } else {
                equipment = await offlineDB.getEquipmentForIntervention(intervention.id);
            }

            loadingEl.style.display = 'none';

            if (equipment.length === 0) {
                listEl.innerHTML = `
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

            equipment.forEach(eq => {
                const item = document.createElement('div');
                item.className = 'equipment-item card-clickable';

                const hasDetail = eq.detail && (eq.detail.work_done || eq.detail.issues_found);
                const iconClass = hasDetail ? 'done' : 'pending';
                const icon = hasDetail ? '✓' : '○';

                item.innerHTML = `
                    <div class="equipment-icon">🚪</div>
                    <div class="equipment-info">
                        <div class="equipment-ref">${eq.ref}</div>
                        <div class="equipment-label">${eq.label || eq.type || ''}</div>
                        ${eq.location ? `<div class="equipment-label">${eq.location}</div>` : ''}
                    </div>
                    <div class="equipment-status ${iconClass}">${icon}</div>
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
        this.showView('viewDetail', equipment.ref);

        document.getElementById('detailEquipmentRef').textContent = `${equipment.ref} - ${equipment.label || ''}`;

        // Try to get from IndexedDB first (for offline edits)
        let detail = await offlineDB.getDetail(this.currentIntervention.id, equipment.id);

        // If no local edits, use server data
        if (!detail && equipment.detail) {
            detail = {
                intervention_id: this.currentIntervention.id,
                equipment_id: equipment.id,
                ...equipment.detail
            };
        }

        // Convert duration to hours and minutes
        const totalMinutes = detail?.work_duration || 0;
        const hours = Math.floor(totalMinutes / 60);
        const minutes = totalMinutes % 60;

        // Populate form
        document.getElementById('workDate').value = detail?.work_date || this.formatDateInput(new Date());
        document.getElementById('workHours').value = hours || '';
        document.getElementById('workMinutes').value = Math.floor(minutes / 15) * 15; // Round to nearest 15
        document.getElementById('workDone').value = detail?.work_done || '';
        document.getElementById('issuesFound').value = detail?.issues_found || '';
        document.getElementById('recommendations').value = detail?.recommendations || '';
        document.getElementById('notes').value = detail?.notes || '';

        // Load and display materials
        this.renderMaterials(equipment.materials || []);
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
        container.innerHTML = '';

        $(container).jSignature({
            color: '#000',
            lineWidth: 2,
            width: '100%',
            height: 200
        });

        this.signatureInstance = $(container);
    }

    clearSignature() {
        if (this.signatureInstance) {
            this.signatureInstance.jSignature('clear');
        }
    }

    async saveSignature() {
        if (!this.signatureInstance) {
            this.showToast('Unterschrift nicht initialisiert');
            return;
        }

        const signerName = document.getElementById('signerName').value.trim();
        if (!signerName) {
            this.showToast('Bitte Name eingeben');
            return;
        }

        // Get signature data
        const signatureData = this.signatureInstance.jSignature('getData', 'base64');

        if (!signatureData || signatureData.length < 2) {
            this.showToast('Bitte unterschreiben');
            return;
        }

        // signatureData is an array: ['image/png;base64', 'base64data']
        const base64 = signatureData[1];

        // Save to IndexedDB
        await offlineDB.saveSignature(this.currentIntervention.id, base64, signerName);

        // Try to sync if online
        if (this.isOnline) {
            try {
                await this.apiCall(`signature/${this.currentIntervention.id}`, {
                    method: 'POST',
                    body: JSON.stringify({
                        signature: base64,
                        signer_name: signerName
                    })
                });
                this.showToast('Unterschrift gespeichert');
                this.currentIntervention.signed_status = 3;
            } catch (err) {
                this.showToast('Offline gespeichert - wird synchronisiert');
            }
        } else {
            this.showToast('Unterschrift offline gespeichert');
        }

        // Go back to equipment list
        this.loadEquipment(this.currentIntervention);
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
}

// Initialize app when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.app = new ServiceReportApp();
});
