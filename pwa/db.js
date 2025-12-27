/**
 * IndexedDB Wrapper for Offline Storage
 */

const DB_NAME = 'equipmentmanager_pwa';
const DB_VERSION = 1;

class OfflineDB {
    constructor() {
        this.db = null;
    }

    async init() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open(DB_NAME, DB_VERSION);

            request.onerror = () => reject(request.error);
            request.onsuccess = () => {
                this.db = request.result;
                resolve(this.db);
            };

            request.onupgradeneeded = (event) => {
                const db = event.target.result;

                // Interventions store
                if (!db.objectStoreNames.contains('interventions')) {
                    const interventions = db.createObjectStore('interventions', { keyPath: 'id' });
                    interventions.createIndex('status', 'status', { unique: false });
                    interventions.createIndex('date_start', 'date_start', { unique: false });
                }

                // Equipment store
                if (!db.objectStoreNames.contains('equipment')) {
                    const equipment = db.createObjectStore('equipment', { keyPath: 'id' });
                    equipment.createIndex('intervention_id', 'intervention_id', { unique: false });
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
            };
        });
    }

    // Generic CRUD operations
    async put(storeName, data) {
        return new Promise((resolve, reject) => {
            const tx = this.db.transaction(storeName, 'readwrite');
            const store = tx.objectStore(storeName);
            const request = store.put(data);
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    async get(storeName, key) {
        return new Promise((resolve, reject) => {
            const tx = this.db.transaction(storeName, 'readonly');
            const store = tx.objectStore(storeName);
            const request = store.get(key);
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    async getAll(storeName) {
        return new Promise((resolve, reject) => {
            const tx = this.db.transaction(storeName, 'readonly');
            const store = tx.objectStore(storeName);
            const request = store.getAll();
            request.onsuccess = () => resolve(request.result || []);
            request.onerror = () => reject(request.error);
        });
    }

    async delete(storeName, key) {
        return new Promise((resolve, reject) => {
            const tx = this.db.transaction(storeName, 'readwrite');
            const store = tx.objectStore(storeName);
            const request = store.delete(key);
            request.onsuccess = () => resolve();
            request.onerror = () => reject(request.error);
        });
    }

    async clear(storeName) {
        return new Promise((resolve, reject) => {
            const tx = this.db.transaction(storeName, 'readwrite');
            const store = tx.objectStore(storeName);
            const request = store.clear();
            request.onsuccess = () => resolve();
            request.onerror = () => reject(request.error);
        });
    }

    async getByIndex(storeName, indexName, value) {
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

    // Save equipment for an intervention
    async saveEquipment(interventionId, equipmentList) {
        for (const eq of equipmentList) {
            eq.intervention_id = interventionId;
            await this.put('equipment', eq);
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
}

// Global instance
const offlineDB = new OfflineDB();
