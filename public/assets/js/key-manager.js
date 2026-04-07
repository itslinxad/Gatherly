/**
 * Gatherly E2EE Key Manager
 * Handles key storage, caching, and retrieval
 * Uses sessionStorage for temporary key caching and IndexedDB for conversation keys
 */

const GatherlyKeyManager = (function() {
    'use strict';

    // Constants
    const SESSION_STORAGE_KEYS = {
        MASTER_KEY: 'gatherly_master_key',
        PRIVATE_KEY: 'gatherly_private_key',
        PUBLIC_KEY: 'gatherly_public_key',
        USER_ID: 'gatherly_user_id',
        KEY_VERSION: 'gatherly_key_version',
        SALT: 'gatherly_salt'
    };

    const INDEXEDDB_NAME = 'GatherlyE2EE';
    const INDEXEDDB_VERSION = 1;
    const STORE_NAMES = {
        CONVERSATION_KEYS: 'conversationKeys',
        PUBLIC_KEYS: 'publicKeys',
        CACHED_DATA: 'cachedData'
    };

    let db = null;

    /**
     * Initialize IndexedDB
     * @returns {Promise<IDBDatabase>}
     */
    async function initIndexedDB() {
        if (db) return db;

        return new Promise((resolve, reject) => {
            const request = indexedDB.open(INDEXEDDB_NAME, INDEXEDDB_VERSION);

            request.onerror = () => {
                console.error('IndexedDB failed to open:', request.error);
                reject(request.error);
            };

            request.onsuccess = () => {
                db = request.result;
                resolve(db);
            };

            request.onupgradeneeded = (event) => {
                const database = event.target.result;

                // Conversation keys store
                if (!database.objectStoreNames.contains(STORE_NAMES.CONVERSATION_KEYS)) {
                    const conversationStore = database.createObjectStore(
                        STORE_NAMES.CONVERSATION_KEYS,
                        { keyPath: 'conversationId' }
                    );
                    conversationStore.createIndex('userId', 'userId', { unique: false });
                    conversationStore.createIndex('lastUsed', 'lastUsed', { unique: false });
                }

                // Public keys store (cache other users' public keys)
                if (!database.objectStoreNames.contains(STORE_NAMES.PUBLIC_KEYS)) {
                    const publicKeysStore = database.createObjectStore(
                        STORE_NAMES.PUBLIC_KEYS,
                        { keyPath: 'userId' }
                    );
                    publicKeysStore.createIndex('cachedAt', 'cachedAt', { unique: false });
                }

                // Generic cached data store
                if (!database.objectStoreNames.contains(STORE_NAMES.CACHED_DATA)) {
                    const cachedDataStore = database.createObjectStore(
                        STORE_NAMES.CACHED_DATA,
                        { keyPath: 'key' }
                    );
                    cachedDataStore.createIndex('expiresAt', 'expiresAt', { unique: false });
                }
            };
        });
    }

    /**
     * Session Storage Management
     */
    const SessionStorage = {
        /**
         * Store master key in session storage
         * @param {CryptoKey} masterKey - The derived master key
         * @returns {Promise<void>}
         */
        async setMasterKey(masterKey) {
            try {
                const exported = await crypto.subtle.exportKey('raw', masterKey);
                const base64 = btoa(String.fromCharCode(...new Uint8Array(exported)));
                sessionStorage.setItem(SESSION_STORAGE_KEYS.MASTER_KEY, base64);
            } catch (error) {
                console.error('Failed to store master key:', error);
                throw new Error('Failed to store master key in session');
            }
        },

        /**
         * Retrieve master key from session storage
         * @returns {Promise<CryptoKey|null>}
         */
        async getMasterKey() {
            try {
                const base64 = sessionStorage.getItem(SESSION_STORAGE_KEYS.MASTER_KEY);
                if (!base64) return null;

                const bytes = Uint8Array.from(atob(base64), c => c.charCodeAt(0));
                return await crypto.subtle.importKey(
                    'raw',
                    bytes,
                    { name: 'AES-GCM' },
                    false,
                    ['encrypt', 'decrypt']
                );
            } catch (error) {
                console.error('Failed to retrieve master key:', error);
                return null;
            }
        },

        /**
         * Store private key in session storage
         * @param {CryptoKey} privateKey - The RSA private key
         * @returns {Promise<void>}
         */
        async setPrivateKey(privateKey) {
            try {
                const exported = await crypto.subtle.exportKey('pkcs8', privateKey);
                const base64 = btoa(String.fromCharCode(...new Uint8Array(exported)));
                sessionStorage.setItem(SESSION_STORAGE_KEYS.PRIVATE_KEY, base64);
            } catch (error) {
                console.error('Failed to store private key:', error);
                throw new Error('Failed to store private key in session');
            }
        },

        /**
         * Retrieve private key from session storage
         * @returns {Promise<CryptoKey|null>}
         */
        async getPrivateKey() {
            try {
                const base64 = sessionStorage.getItem(SESSION_STORAGE_KEYS.PRIVATE_KEY);
                if (!base64) return null;

                const bytes = Uint8Array.from(atob(base64), c => c.charCodeAt(0));
                return await crypto.subtle.importKey(
                    'pkcs8',
                    bytes,
                    {
                        name: 'RSA-OAEP',
                        hash: 'SHA-256'
                    },
                    false,
                    ['decrypt']
                );
            } catch (error) {
                console.error('Failed to retrieve private key:', error);
                return null;
            }
        },

        /**
         * Store public key in session storage
         * @param {string} publicKeyPem - The public key in PEM format
         */
        setPublicKey(publicKeyPem) {
            sessionStorage.setItem(SESSION_STORAGE_KEYS.PUBLIC_KEY, publicKeyPem);
        },

        /**
         * Retrieve public key from session storage
         * @returns {string|null}
         */
        getPublicKey() {
            return sessionStorage.getItem(SESSION_STORAGE_KEYS.PUBLIC_KEY);
        },

        /**
         * Store user ID
         * @param {number} userId
         */
        setUserId(userId) {
            sessionStorage.setItem(SESSION_STORAGE_KEYS.USER_ID, userId.toString());
        },

        /**
         * Get user ID
         * @returns {number|null}
         */
        getUserId() {
            const id = sessionStorage.getItem(SESSION_STORAGE_KEYS.USER_ID);
            return id ? parseInt(id, 10) : null;
        },

        /**
         * Store key version
         * @param {number} version
         */
        setKeyVersion(version) {
            sessionStorage.setItem(SESSION_STORAGE_KEYS.KEY_VERSION, version.toString());
        },

        /**
         * Get key version
         * @returns {number|null}
         */
        getKeyVersion() {
            const version = sessionStorage.getItem(SESSION_STORAGE_KEYS.KEY_VERSION);
            return version ? parseInt(version, 10) : null;
        },

        /**
         * Store salt
         * @param {string} salt - Base64 encoded salt
         */
        setSalt(salt) {
            sessionStorage.setItem(SESSION_STORAGE_KEYS.SALT, salt);
        },

        /**
         * Get salt
         * @returns {string|null}
         */
        getSalt() {
            return sessionStorage.getItem(SESSION_STORAGE_KEYS.SALT);
        },

        /**
         * Check if user is authenticated (has keys in session)
         * @returns {boolean}
         */
        isAuthenticated() {
            return sessionStorage.getItem(SESSION_STORAGE_KEYS.PRIVATE_KEY) !== null &&
                   sessionStorage.getItem(SESSION_STORAGE_KEYS.MASTER_KEY) !== null;
        },

        /**
         * Clear all session storage
         */
        clear() {
            Object.values(SESSION_STORAGE_KEYS).forEach(key => {
                sessionStorage.removeItem(key);
            });
        }
    };

    /**
     * IndexedDB Management
     */
    const IndexedDBStorage = {
        /**
         * Store conversation key
         * @param {string} conversationId - The conversation ID
         * @param {string} encryptedSessionKey - Base64 encoded encrypted session key
         * @param {number} keyVersion - Key version number
         * @param {string} iv - Base64 encoded IV
         * @returns {Promise<void>}
         */
        async setConversationKey(conversationId, encryptedSessionKey, keyVersion, iv) {
            await initIndexedDB();
            
            return new Promise((resolve, reject) => {
                const transaction = db.transaction([STORE_NAMES.CONVERSATION_KEYS], 'readwrite');
                const store = transaction.objectStore(STORE_NAMES.CONVERSATION_KEYS);

                const data = {
                    conversationId,
                    userId: SessionStorage.getUserId(),
                    encryptedSessionKey,
                    keyVersion,
                    iv,
                    lastUsed: Date.now(),
                    createdAt: Date.now()
                };

                const request = store.put(data);

                request.onsuccess = () => resolve();
                request.onerror = () => reject(request.error);
            });
        },

        /**
         * Get conversation key
         * @param {string} conversationId
         * @returns {Promise<Object|null>}
         */
        async getConversationKey(conversationId) {
            await initIndexedDB();

            return new Promise((resolve, reject) => {
                const transaction = db.transaction([STORE_NAMES.CONVERSATION_KEYS], 'readonly');
                const store = transaction.objectStore(STORE_NAMES.CONVERSATION_KEYS);
                const request = store.get(conversationId);

                request.onsuccess = () => {
                    const result = request.result;
                    
                    // Update last used timestamp
                    if (result) {
                        result.lastUsed = Date.now();
                        const updateTransaction = db.transaction([STORE_NAMES.CONVERSATION_KEYS], 'readwrite');
                        const updateStore = updateTransaction.objectStore(STORE_NAMES.CONVERSATION_KEYS);
                        updateStore.put(result);
                    }
                    
                    resolve(result || null);
                };
                request.onerror = () => reject(request.error);
            });
        },

        /**
         * Delete conversation key
         * @param {string} conversationId
         * @returns {Promise<void>}
         */
        async deleteConversationKey(conversationId) {
            await initIndexedDB();

            return new Promise((resolve, reject) => {
                const transaction = db.transaction([STORE_NAMES.CONVERSATION_KEYS], 'readwrite');
                const store = transaction.objectStore(STORE_NAMES.CONVERSATION_KEYS);
                const request = store.delete(conversationId);

                request.onsuccess = () => resolve();
                request.onerror = () => reject(request.error);
            });
        },

        /**
         * Cache public key for another user
         * @param {number} userId
         * @param {string} publicKeyPem
         * @param {number} keyVersion
         * @returns {Promise<void>}
         */
        async cachePublicKey(userId, publicKeyPem, keyVersion) {
            await initIndexedDB();

            return new Promise((resolve, reject) => {
                const transaction = db.transaction([STORE_NAMES.PUBLIC_KEYS], 'readwrite');
                const store = transaction.objectStore(STORE_NAMES.PUBLIC_KEYS);

                const data = {
                    userId,
                    publicKey: publicKeyPem,
                    keyVersion,
                    cachedAt: Date.now()
                };

                const request = store.put(data);

                request.onsuccess = () => resolve();
                request.onerror = () => reject(request.error);
            });
        },

        /**
         * Get cached public key
         * @param {number} userId
         * @returns {Promise<Object|null>}
         */
        async getCachedPublicKey(userId) {
            await initIndexedDB();

            return new Promise((resolve, reject) => {
                const transaction = db.transaction([STORE_NAMES.PUBLIC_KEYS], 'readonly');
                const store = transaction.objectStore(STORE_NAMES.PUBLIC_KEYS);
                const request = store.get(userId);

                request.onsuccess = () => resolve(request.result || null);
                request.onerror = () => reject(request.error);
            });
        },

        /**
         * Store generic cached data with expiration
         * @param {string} key
         * @param {any} value
         * @param {number} ttlMinutes - Time to live in minutes
         * @returns {Promise<void>}
         */
        async setCachedData(key, value, ttlMinutes = 60) {
            await initIndexedDB();

            return new Promise((resolve, reject) => {
                const transaction = db.transaction([STORE_NAMES.CACHED_DATA], 'readwrite');
                const store = transaction.objectStore(STORE_NAMES.CACHED_DATA);

                const data = {
                    key,
                    value,
                    cachedAt: Date.now(),
                    expiresAt: Date.now() + (ttlMinutes * 60 * 1000)
                };

                const request = store.put(data);

                request.onsuccess = () => resolve();
                request.onerror = () => reject(request.error);
            });
        },

        /**
         * Get cached data if not expired
         * @param {string} key
         * @returns {Promise<any|null>}
         */
        async getCachedData(key) {
            await initIndexedDB();

            return new Promise((resolve, reject) => {
                const transaction = db.transaction([STORE_NAMES.CACHED_DATA], 'readonly');
                const store = transaction.objectStore(STORE_NAMES.CACHED_DATA);
                const request = store.get(key);

                request.onsuccess = () => {
                    const result = request.result;
                    
                    // Check if expired
                    if (result && result.expiresAt < Date.now()) {
                        // Delete expired entry
                        const deleteTransaction = db.transaction([STORE_NAMES.CACHED_DATA], 'readwrite');
                        const deleteStore = deleteTransaction.objectStore(STORE_NAMES.CACHED_DATA);
                        deleteStore.delete(key);
                        resolve(null);
                    } else {
                        resolve(result ? result.value : null);
                    }
                };
                request.onerror = () => reject(request.error);
            });
        },

        /**
         * Clear all conversation keys for current user
         * @returns {Promise<void>}
         */
        async clearUserConversationKeys() {
            await initIndexedDB();
            const userId = SessionStorage.getUserId();
            if (!userId) return;

            return new Promise((resolve, reject) => {
                const transaction = db.transaction([STORE_NAMES.CONVERSATION_KEYS], 'readwrite');
                const store = transaction.objectStore(STORE_NAMES.CONVERSATION_KEYS);
                const index = store.index('userId');
                const request = index.openCursor(IDBKeyRange.only(userId));

                request.onsuccess = (event) => {
                    const cursor = event.target.result;
                    if (cursor) {
                        cursor.delete();
                        cursor.continue();
                    } else {
                        resolve();
                    }
                };

                request.onerror = () => reject(request.error);
            });
        },

        /**
         * Clear all cached public keys
         * @returns {Promise<void>}
         */
        async clearPublicKeyCache() {
            await initIndexedDB();

            return new Promise((resolve, reject) => {
                const transaction = db.transaction([STORE_NAMES.PUBLIC_KEYS], 'readwrite');
                const store = transaction.objectStore(STORE_NAMES.PUBLIC_KEYS);
                const request = store.clear();

                request.onsuccess = () => resolve();
                request.onerror = () => reject(request.error);
            });
        },

        /**
         * Clear expired cached data
         * @returns {Promise<void>}
         */
        async cleanExpiredCache() {
            await initIndexedDB();

            return new Promise((resolve, reject) => {
                const transaction = db.transaction([STORE_NAMES.CACHED_DATA], 'readwrite');
                const store = transaction.objectStore(STORE_NAMES.CACHED_DATA);
                const index = store.index('expiresAt');
                const request = index.openCursor(IDBKeyRange.upperBound(Date.now()));

                request.onsuccess = (event) => {
                    const cursor = event.target.result;
                    if (cursor) {
                        cursor.delete();
                        cursor.continue();
                    } else {
                        resolve();
                    }
                };

                request.onerror = () => reject(request.error);
            });
        },

        /**
         * Clear all IndexedDB data
         * @returns {Promise<void>}
         */
        async clearAll() {
            await initIndexedDB();

            const stores = Object.values(STORE_NAMES);
            const promises = stores.map(storeName => {
                return new Promise((resolve, reject) => {
                    const transaction = db.transaction([storeName], 'readwrite');
                    const store = transaction.objectStore(storeName);
                    const request = store.clear();

                    request.onsuccess = () => resolve();
                    request.onerror = () => reject(request.error);
                });
            });

            return Promise.all(promises);
        }
    };

    /**
     * High-level key management functions
     */
    const KeyManager = {
        /**
         * Initialize key manager (called on app start)
         * @returns {Promise<void>}
         */
        async initialize() {
            await initIndexedDB();
            // Clean up expired cache on init
            await IndexedDBStorage.cleanExpiredCache();
        },

        /**
         * Setup user keys after login
         * @param {Object} params
         * @param {number} params.userId
         * @param {CryptoKey} params.masterKey
         * @param {CryptoKey} params.privateKey
         * @param {string} params.publicKeyPem
         * @param {number} params.keyVersion
         * @param {string} params.salt
         * @returns {Promise<void>}
         */
        async setupUserKeys({ userId, masterKey, privateKey, publicKeyPem, keyVersion, salt }) {
            await SessionStorage.setMasterKey(masterKey);
            await SessionStorage.setPrivateKey(privateKey);
            SessionStorage.setPublicKey(publicKeyPem);
            SessionStorage.setUserId(userId);
            SessionStorage.setKeyVersion(keyVersion);
            SessionStorage.setSalt(salt);
        },

        /**
         * Check if user has active session
         * @returns {boolean}
         */
        hasActiveSession() {
            return SessionStorage.isAuthenticated();
        },

        /**
         * Logout and clear all keys
         * @returns {Promise<void>}
         */
        async logout() {
            SessionStorage.clear();
            await IndexedDBStorage.clearUserConversationKeys();
        },

        /**
         * Complete wipe (for key rotation or account deletion)
         * @returns {Promise<void>}
         */
        async completeWipe() {
            SessionStorage.clear();
            await IndexedDBStorage.clearAll();
        },

        /**
         * Get conversation session key (decrypt if needed)
         * @param {string} conversationId
         * @returns {Promise<CryptoKey|null>}
         */
        async getConversationSessionKey(conversationId) {
            // Check IndexedDB for stored conversation key
            const stored = await IndexedDBStorage.getConversationKey(conversationId);
            if (!stored) return null;

            // Decrypt session key using private key
            const privateKey = await SessionStorage.getPrivateKey();
            if (!privateKey) throw new Error('Private key not available in session');

            try {
                const sessionKey = await GatherlyE2EE.decryptSessionKey(
                    stored.encryptedSessionKey,
                    privateKey
                );
                return sessionKey;
            } catch (error) {
                console.error('Failed to decrypt session key:', error);
                return null;
            }
        },

        /**
         * Store conversation session key
         * @param {string} conversationId
         * @param {string} encryptedSessionKey
         * @param {number} keyVersion
         * @param {string} iv
         * @returns {Promise<void>}
         */
        async storeConversationSessionKey(conversationId, encryptedSessionKey, keyVersion, iv) {
            await IndexedDBStorage.setConversationKey(conversationId, encryptedSessionKey, keyVersion, iv);
        },

        /**
         * Get user's own keys from session
         * @returns {Promise<Object>}
         */
        async getOwnKeys() {
            return {
                masterKey: await SessionStorage.getMasterKey(),
                privateKey: await SessionStorage.getPrivateKey(),
                publicKey: SessionStorage.getPublicKey(),
                keyVersion: SessionStorage.getKeyVersion(),
                salt: SessionStorage.getSalt()
            };
        }
    };

    // Public API
    return {
        initialize: KeyManager.initialize,
        setupUserKeys: KeyManager.setupUserKeys,
        hasActiveSession: KeyManager.hasActiveSession,
        logout: KeyManager.logout,
        completeWipe: KeyManager.completeWipe,
        getConversationSessionKey: KeyManager.getConversationSessionKey,
        storeConversationSessionKey: KeyManager.storeConversationSessionKey,
        getOwnKeys: KeyManager.getOwnKeys,
        
        // Direct access to storage layers
        session: SessionStorage,
        indexedDB: IndexedDBStorage
    };
})();

// Auto-initialize on page load
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        GatherlyKeyManager.initialize().catch(console.error);
    });
} else {
    GatherlyKeyManager.initialize().catch(console.error);
}
