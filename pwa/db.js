/**
 * IndexedDB Wrapper for Offline Storage
 */

const DB_NAME = 'equipmentmanager_pwa';
const DB_VERSION = 6; // v6: Add geocache store for map coordinates

class OfflineDB {
    constructor() {
        this.db = null;
    }

    async init() {
        return Promise.race([
          new Promise((_, reject) =>
              setTimeout(() => reject(new Error('IDB_TIMEOUT')), 8000)
          ),
          new Promise((resolve, reject) => {
            const request = indexedDB.open(DB_NAME, DB_VERSION);

            request.onerror = () => reject(request.error);
            request.onsuccess = () => {
                this.db = request.result;
                this.db.onversionchange = () => {
                    this.db.close();
                    this.db = null;
                    window.location.reload();
                };
                resolve(this.db);
            };

            request.onblocked = () => {
                reject(new Error('IDB_BLOCKED'));
            };

            request.onupgradeneeded = (event) => {
                const db = event.target.result;

                // Interventions store
                if (!db.objectStoreNames.contains('interventions')) {
                    const interventions = db.createObjectStore('interventions', { keyPath: 'id' });
                    interventions.createIndex('status', 'status', { unique: false });
                    interventions.createIndex('date_start', 'date_start', { unique: false });
                }

                // Equipment store - v4: composite key to support same equipment in multiple interventions
                if (!db.objectStoreNames.contains('equipment')) {
                    const equipment = db.createObjectStore('equipment', { keyPath: ['intervention_id', 'id'] });
                    equipment.createIndex('intervention_id', 'intervention_id', { unique: false });
                    equipment.createIndex('equipment_id', 'id', { unique: false });
                } else if (event.oldVersion < 4) {
                    // Migrate existing equipment store to composite key
                    db.deleteObjectStore('equipment');
                    const equipment = db.createObjectStore('equipment', { keyPath: ['intervention_id', 'id'] });
                    equipment.createIndex('intervention_id', 'intervention_id', { unique: false });
                    equipment.createIndex('equipment_id', 'id', { unique: false });
                }

                // Details store (service reports per equipment)
                if (!db.objectStoreNames.contains('details')) {
                    const details = db.createObjectStore('details', { keyPath: ['intervention_id', 'equipment_id'] });
                    details.createIndex('intervention_id', 'intervention_id', { unique: false });
                    details.createIndex('modified', 'modified', { unique: false });
                }

                // Materials store
                if (!db.objectStoreNames.contains('materials')) {
                    const materials = db.createObjectStore('materials', { keyPath: 'id', autoIncrement: true });
                    materials.createIndex('intervention_equipment', ['intervention_id', 'equipment_id'], { unique: false });
                }

                // Sync queue for offline changes
                if (!db.objectStoreNames.contains('sync_queue')) {
                    const syncQueue = db.createObjectStore('sync_queue', { keyPath: 'id', autoIncrement: true });
                    syncQueue.createIndex('timestamp', 'timestamp', { unique: false });
                    syncQueue.createIndex('type', 'type', { unique: false });
                }

                // Signatures store
                if (!db.objectStoreNames.contains('signatures')) {
                    const signatures = db.createObjectStore('signatures', { keyPath: 'intervention_id' });
                }

                // Meta store (last sync time, etc.)
                if (!db.objectStoreNames.contains('meta')) {
                    db.createObjectStore('meta', { keyPath: 'key' });
                }

                // v2: Available equipment per intervention (all customer equipment not yet linked)
                if (!db.objectStoreNames.contains('available_equipment')) {
                    const availEquip = db.createObjectStore('available_equipment', { keyPath: 'intervention_id' });
                }

                // v2: Document metadata per intervention
                if (!db.objectStoreNames.contains('documents')) {
                    const docs = db.createObjectStore('documents', { keyPath: 'intervention_id' });
                }

                // v2: Pending document uploads (for offline upload)
                if (!db.objectStoreNames.contains('pending_uploads')) {
                    const uploads = db.createObjectStore('pending_uploads', { keyPath: 'id', autoIncrement: true });
                    uploads.createIndex('intervention_id', 'intervention_id', { unique: false });
                }

                // v3: Checklists per intervention/equipment
                if (!db.objectStoreNames.contains('checklists')) {
                    const checklists = db.createObjectStore('checklists', { keyPath: 'key' });
                    checklists.createIndex('intervention_id', 'intervention_id', { unique: false });
                }

                // v5: Defect materials for offline support (freetext materials)
                if (!db.objectStoreNames.contains('defect_materials')) {
                    const defectMats = db.createObjectStore('defect_materials', { keyPath: 'local_id', autoIncrement: true });
                    defectMats.createIndex('entry_id', 'entry_id', { unique: false });
                    defectMats.createIndex('synced', 'synced', { unique: false });
                }

                // v6: Geocache — stores geocoded coordinates keyed by address string
                if (!db.objectStoreNames.contains('geocache')) {
                    db.createObjectStore('geocache', { keyPath: 'address' });
                }
            };
          })
        ]);
    }

    // Generic CRUD operations
    async put(storeName, data) {
        if (!this.db) return null;
        return new Promise((resolve, reject) => {
            const tx = this.db.transaction(storeName, 'readwrite');
            const store = tx.objectStore(storeName);
            const request = store.put(data);
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    async get(storeName, key) {
        if (!this.db) return undefined;
        return new Promise((resolve, reject) => {
            const tx = this.db.transaction(storeName, 'readonly');
            const store = tx.objectStore(storeName);
            const request = store.get(key);
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    async getAll(storeName) {
        if (!this.db) return [];
        return new Promise((resolve, reject) => {
            const tx = this.db.transaction(storeName, 'readonly');
            const store = tx.objectStore(storeName);
            const request = store.getAll();
            request.onsuccess = () => resolve(request.result || []);
            request.onerror = () => reject(request.error);
        });
    }

    async delete(storeName, key) {
        if (!this.db) return;
        return new Promise((resolve, reject) => {
            const tx = this.db.transaction(storeName, 'readwrite');
            const store = tx.objectStore(storeName);
            const request = store.delete(key);
            request.onsuccess = () => resolve();
            request.onerror = () => reject(request.error);
        });
    }

    async clear(storeName) {
        if (!this.db) return;
        return new Promise((resolve, reject) => {
            const tx = this.db.transaction(storeName, 'readwrite');
            const store = tx.objectStore(storeName);
            const request = store.clear();
            request.onsuccess = () => resolve();
            request.onerror = () => reject(request.error);
        });
    }

    async getByIndex(storeName, indexName, value) {
        if (!this.db) return [];
        return new Promise((resolve, reject) => {
            const tx = this.db.transaction(storeName, 'readonly');
            const store = tx.objectStore(storeName);
            const index = store.index(indexName);
            const request = index.getAll(value);
            request.onsuccess = () => resolve(request.result || []);
            request.onerror = () => reject(request.error);
        });
    }

    // Specific methods

    // Save interventions from API
    async saveInterventions(interventions) {
        for (const intervention of interventions) {
            await this.put('interventions', intervention);
        }
    }

    // Save equipment for an intervention (v4: uses composite key [intervention_id, id])
    async saveEquipment(interventionId, equipmentList) {
        for (const eq of equipmentList) {
            // Clone to avoid modifying original object
            const eqCopy = { ...eq };
            eqCopy.intervention_id = parseInt(interventionId);
            eqCopy.id = parseInt(eqCopy.id);
            await this.put('equipment', eqCopy);
        }
    }

    // Delete all equipment for an intervention (for refresh)
    async clearEquipmentForIntervention(interventionId) {
        const equipment = await this.getEquipmentForIntervention(interventionId);
        for (const eq of equipment) {
            await this.delete('equipment', [eq.intervention_id, eq.id]);
        }
    }

    // Save or update a detail (service report)
    async saveDetail(detail) {
        detail.modified = Date.now();
        detail.synced = false;
        await this.put('details', detail);

        // Add to sync queue
        await this.addToSyncQueue('detail', detail);
    }

    // Get detail for specific equipment in intervention
    async getDetail(interventionId, equipmentId) {
        return await this.get('details', [interventionId, equipmentId]);
    }

    // Get all equipment for an intervention
    async getEquipmentForIntervention(interventionId) {
        return await this.getByIndex('equipment', 'intervention_id', interventionId);
    }

    // Sync queue management
    async addToSyncQueue(type, data) {
        await this.put('sync_queue', {
            type: type,
            data: data,
            timestamp: Date.now(),
            attempts: 0
        });
    }

    async getSyncQueue() {
        return await this.getAll('sync_queue');
    }

    async clearSyncQueue() {
        await this.clear('sync_queue');
    }

    async removeSyncItem(id) {
        await this.delete('sync_queue', id);
    }

    // Signature storage
    async saveSignature(interventionId, signatureData, signerName) {
        await this.put('signatures', {
            intervention_id: interventionId,
            signature: signatureData,
            signer_name: signerName,
            timestamp: Date.now(),
            synced: false
        });

        // Add to sync queue
        await this.addToSyncQueue('signature', {
            intervention_id: interventionId,
            signature: signatureData,
            signer_name: signerName
        });
    }

    async getSignature(interventionId) {
        return await this.get('signatures', interventionId);
    }

    // Meta data
    async setMeta(key, value) {
        await this.put('meta', { key, value });
    }

    async getMeta(key) {
        const result = await this.get('meta', key);
        return result ? result.value : null;
    }

    // Get pending sync count
    async getPendingSyncCount() {
        const queue = await this.getSyncQueue();
        return queue.length;
    }

    // v2: Save available equipment for intervention
    async saveAvailableEquipment(interventionId, equipment) {
        await this.put('available_equipment', {
            intervention_id: interventionId,
            equipment: equipment,
            timestamp: Date.now()
        });
    }

    // v2: Get available equipment for intervention
    async getAvailableEquipment(interventionId) {
        const data = await this.get('available_equipment', interventionId);
        return data ? data.equipment : [];
    }

    // v2: Save document metadata for intervention
    async saveDocuments(interventionId, documents) {
        await this.put('documents', {
            intervention_id: interventionId,
            documents: documents,
            timestamp: Date.now()
        });
    }

    // v2: Get document metadata for intervention
    async getDocuments(interventionId) {
        const data = await this.get('documents', interventionId);
        return data ? data.documents : [];
    }

    // v2: Add pending upload
    async addPendingUpload(interventionId, fileData, fileName, fileType) {
        await this.put('pending_uploads', {
            intervention_id: interventionId,
            file_data: fileData,
            file_name: fileName,
            file_type: fileType,
            timestamp: Date.now()
        });
    }

    // v2: Get pending uploads for intervention
    async getPendingUploads(interventionId) {
        return await this.getByIndex('pending_uploads', 'intervention_id', interventionId);
    }

    // v2: Get all pending uploads
    async getAllPendingUploads() {
        return await this.getAll('pending_uploads');
    }

    // v2: Remove pending upload
    async removePendingUpload(id) {
        await this.delete('pending_uploads', id);
    }

    // v5: Save defect material (offline support)
    async saveDefectMaterial(entryId, material) {
        // Check if store exists
        if (!this.db.objectStoreNames.contains('defect_materials')) {
            throw new Error('DB upgrade needed - please clear browser data and reload');
        }

        const data = {
            entry_id: parseInt(entryId),
            fk_product: material.fk_product || null,
            freetext_label: material.freetext_label || '',
            product_ref: material.product_ref || (material.freetext_label ? 'FREI' : ''),
            product_label: material.product_label || material.freetext_label || '',
            qty: material.qty || 1,
            synced: false,
            timestamp: Date.now()
        };

        const localId = await this.put('defect_materials', data);
        data.local_id = localId;

        // Add to sync queue
        await this.addToSyncQueue('defect_material', {
            entry_id: parseInt(entryId),
            fk_product: data.fk_product,
            freetext_label: data.freetext_label,
            qty: data.qty,
            local_id: localId
        });

        return data;
    }

    // v5: Get defect materials for entry (including unsynced)
    async getDefectMaterials(entryId) {
        return await this.getByIndex('defect_materials', 'entry_id', entryId);
    }

    // v5: Mark defect material as synced
    async markDefectMaterialSynced(localId, serverId) {
        const mat = await this.get('defect_materials', localId);
        if (mat) {
            mat.id = serverId;
            mat.synced = true;
            await this.put('defect_materials', mat);
        }
    }

    // v5: Delete defect material
    async deleteDefectMaterial(localId) {
        await this.delete('defect_materials', localId);
    }

    // v5: Get unsynced defect materials
    async getUnsyncedDefectMaterials() {
        return await this.getByIndex('defect_materials', 'synced', false);
    }

    // v6: Geocache
    async getGeoCache(address) {
        return await this.get('geocache', address);
    }

    async setGeoCache(address, lat, lon) {
        await this.put('geocache', { address, lat, lon, cached_at: Date.now() });
    }

    // Remove geocache entries whose address is no longer needed
    async cleanGeoCache(neededAddresses) {
        const all = await this.getAll('geocache');
        for (const entry of all) {
            if (!neededAddresses.includes(entry.address)) {
                await this.delete('geocache', entry.address);
            }
        }
    }
}

// Global instance
const offlineDB = new OfflineDB();
