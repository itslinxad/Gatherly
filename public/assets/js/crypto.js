/**
 * Gatherly E2EE Crypto Library
 * 
 * Provides end-to-end encryption functionality using Web Crypto API
 * - RSA-2048 for key exchange
 * - AES-256-GCM for message encryption
 * - PBKDF2 (310,000 iterations) for password-derived keys
 * 
 * @version 1.0.0
 * @date 2026-04-06
 */

const GatherlyE2EE = (function() {
    'use strict';

    // Constants
    const PBKDF2_ITERATIONS = 310000; // OWASP 2023 recommendation
    const RSA_KEY_SIZE = 2048;
    const AES_KEY_SIZE = 256;
    const SALT_LENGTH = 32;
    const IV_LENGTH = 12; // 96 bits for GCM

    /**
     * Generate a cryptographically secure random salt
     * @param {number} length - Length in bytes
     * @returns {Uint8Array}
     */
    function generateSalt(length = SALT_LENGTH) {
        return crypto.getRandomValues(new Uint8Array(length));
    }

    /**
     * Convert Uint8Array to Base64 string
     * @param {Uint8Array} buffer
     * @returns {string}
     */
    function bufferToBase64(buffer) {
        const bytes = new Uint8Array(buffer);
        let binary = '';
        for (let i = 0; i < bytes.byteLength; i++) {
            binary += String.fromCharCode(bytes[i]);
        }
        return btoa(binary);
    }

    /**
     * Convert Base64 string to Uint8Array
     * @param {string} base64
     * @returns {Uint8Array}
     */
    function base64ToBuffer(base64) {
        const binary = atob(base64);
        const bytes = new Uint8Array(binary.length);
        for (let i = 0; i < binary.length; i++) {
            bytes[i] = binary.charCodeAt(i);
        }
        return bytes;
    }

    /**
     * Derive master key from password using PBKDF2
     * @param {string} password - User's password
     * @param {Uint8Array|string} salt - Salt (Uint8Array or base64 string)
     * @param {number} iterations - PBKDF2 iterations (default: 310,000)
     * @returns {Promise<CryptoKey>}
     */
    async function deriveMasterKey(password, salt, iterations = PBKDF2_ITERATIONS) {
        try {
            // Convert salt to Uint8Array if it's a base64 string
            const saltBuffer = typeof salt === 'string' ? base64ToBuffer(salt) : salt;

            // Import password as key material
            const keyMaterial = await crypto.subtle.importKey(
                'raw',
                new TextEncoder().encode(password),
                'PBKDF2',
                false,
                ['deriveBits', 'deriveKey']
            );

            // Derive AES-256 key from password
            return await crypto.subtle.deriveKey(
                {
                    name: 'PBKDF2',
                    salt: saltBuffer,
                    iterations: iterations,
                    hash: 'SHA-256'
                },
                keyMaterial,
                { name: 'AES-GCM', length: AES_KEY_SIZE },
                true,
                ['encrypt', 'decrypt']
            );
        } catch (error) {
            console.error('Error deriving master key:', error);
            throw new Error('Failed to derive master key from password');
        }
    }

    /**
     * Generate RSA-2048 key pair for asymmetric encryption
     * @returns {Promise<{publicKey: CryptoKey, privateKey: CryptoKey}>}
     */
    async function generateRSAKeyPair() {
        try {
            return await crypto.subtle.generateKey(
                {
                    name: 'RSA-OAEP',
                    modulusLength: RSA_KEY_SIZE,
                    publicExponent: new Uint8Array([1, 0, 1]), // 65537
                    hash: 'SHA-256'
                },
                true,
                ['encrypt', 'decrypt']
            );
        } catch (error) {
            console.error('Error generating RSA key pair:', error);
            throw new Error('Failed to generate RSA key pair');
        }
    }

    /**
     * Export public key to base64 string (PEM-like format)
     * @param {CryptoKey} publicKey
     * @returns {Promise<string>}
     */
    async function exportPublicKey(publicKey) {
        try {
            const exported = await crypto.subtle.exportKey('spki', publicKey);
            return bufferToBase64(exported);
        } catch (error) {
            console.error('Error exporting public key:', error);
            throw new Error('Failed to export public key');
        }
    }

    /**
     * Import public key from base64 string
     * @param {string} publicKeyBase64
     * @returns {Promise<CryptoKey>}
     */
    async function importPublicKey(publicKeyBase64) {
        try {
            const keyBuffer = base64ToBuffer(publicKeyBase64);
            return await crypto.subtle.importKey(
                'spki',
                keyBuffer,
                {
                    name: 'RSA-OAEP',
                    hash: 'SHA-256'
                },
                true,
                ['encrypt']
            );
        } catch (error) {
            console.error('Error importing public key:', error);
            throw new Error('Failed to import public key');
        }
    }

    /**
     * Export private key to base64 string
     * @param {CryptoKey} privateKey
     * @returns {Promise<string>}
     */
    async function exportPrivateKey(privateKey) {
        try {
            const exported = await crypto.subtle.exportKey('pkcs8', privateKey);
            return bufferToBase64(exported);
        } catch (error) {
            console.error('Error exporting private key:', error);
            throw new Error('Failed to export private key');
        }
    }

    /**
     * Import private key from base64 string
     * @param {string} privateKeyBase64
     * @returns {Promise<CryptoKey>}
     */
    async function importPrivateKey(privateKeyBase64) {
        try {
            const keyBuffer = base64ToBuffer(privateKeyBase64);
            return await crypto.subtle.importKey(
                'pkcs8',
                keyBuffer,
                {
                    name: 'RSA-OAEP',
                    hash: 'SHA-256'
                },
                true,
                ['decrypt']
            );
        } catch (error) {
            console.error('Error importing private key:', error);
            throw new Error('Failed to import private key');
        }
    }

    /**
     * Encrypt private key with master key (AES-GCM)
     * @param {CryptoKey} privateKey - RSA private key
     * @param {CryptoKey} masterKey - AES master key
     * @returns {Promise<{ciphertext: string, iv: string}>}
     */
    async function encryptPrivateKey(privateKey, masterKey) {
        try {
            const privateKeyData = await exportPrivateKey(privateKey);
            const iv = crypto.getRandomValues(new Uint8Array(IV_LENGTH));
            
            const encrypted = await crypto.subtle.encrypt(
                {
                    name: 'AES-GCM',
                    iv: iv
                },
                masterKey,
                new TextEncoder().encode(privateKeyData)
            );

            return {
                ciphertext: bufferToBase64(encrypted),
                iv: bufferToBase64(iv)
            };
        } catch (error) {
            console.error('Error encrypting private key:', error);
            throw new Error('Failed to encrypt private key');
        }
    }

    /**
     * Decrypt private key with master key (AES-GCM)
     * @param {string} encryptedPrivateKey - Base64 encrypted private key (with IV in format "ciphertext_iv")
     * @param {CryptoKey} masterKey - AES master key
     * @returns {Promise<CryptoKey>}
     */
    async function decryptPrivateKey(encryptedPrivateKeyWithIv, masterKey) {
        try {
            let ciphertext, iv;
            
            // Parse format: "ciphertext_iv" or try to detect old format (just ciphertext)
            if (encryptedPrivateKeyWithIv.includes('::')) {
                const parts = encryptedPrivateKeyWithIv.split('::');
                ciphertext = base64ToBuffer(parts[0]);
                iv = base64ToBuffer(parts[1]);
            } else {
                // Old format without IV - try to use a default IV or error
                console.warn('Old encrypted private key format detected, attempting decryption...');
                ciphertext = base64ToBuffer(encryptedPrivateKeyWithIv);
                // Use a zero IV for old format (this will likely fail - user needs to reset keys)
                iv = new Uint8Array(12);
            }

            const decrypted = await crypto.subtle.decrypt(
                {
                    name: 'AES-GCM',
                    iv: iv
                },
                masterKey,
                ciphertext
            );

            const privateKeyData = new TextDecoder().decode(decrypted);
            return await importPrivateKey(privateKeyData);
        } catch (error) {
            console.error('Error decrypting private key:', error);
            throw new Error('Failed to decrypt private key');
        }
    }

    /**
     * Generate AES-256-GCM session key for conversation
     * @returns {Promise<CryptoKey>}
     */
    async function generateAESSessionKey() {
        try {
            return await crypto.subtle.generateKey(
                {
                    name: 'AES-GCM',
                    length: AES_KEY_SIZE
                },
                true,
                ['encrypt', 'decrypt']
            );
        } catch (error) {
            console.error('Error generating AES session key:', error);
            throw new Error('Failed to generate session key');
        }
    }

    /**
     * Export AES session key to base64 string
     * @param {CryptoKey} sessionKey
     * @returns {Promise<string>}
     */
    async function exportSessionKey(sessionKey) {
        try {
            const exported = await crypto.subtle.exportKey('raw', sessionKey);
            return bufferToBase64(exported);
        } catch (error) {
            console.error('Error exporting session key:', error);
            throw new Error('Failed to export session key');
        }
    }

    /**
     * Import AES session key from base64 string
     * @param {string} sessionKeyBase64
     * @returns {Promise<CryptoKey>}
     */
    async function importSessionKey(sessionKeyBase64) {
        try {
            const keyBuffer = base64ToBuffer(sessionKeyBase64);
            return await crypto.subtle.importKey(
                'raw',
                keyBuffer,
                { name: 'AES-GCM' },
                true,
                ['encrypt', 'decrypt']
            );
        } catch (error) {
            console.error('Error importing session key:', error);
            throw new Error('Failed to import session key');
        }
    }

    /**
     * Encrypt session key with recipient's RSA public key
     * @param {CryptoKey} sessionKey - AES session key
     * @param {CryptoKey} recipientPublicKey - RSA public key
     * @returns {Promise<string>} - Base64 encrypted session key
     */
    async function encryptSessionKeyRSA(sessionKey, recipientPublicKey) {
        try {
            const sessionKeyData = await exportSessionKey(sessionKey);
            const encrypted = await crypto.subtle.encrypt(
                {
                    name: 'RSA-OAEP'
                },
                recipientPublicKey,
                new TextEncoder().encode(sessionKeyData)
            );
            return bufferToBase64(encrypted);
        } catch (error) {
            console.error('Error encrypting session key with RSA:', error);
            throw new Error('Failed to encrypt session key');
        }
    }

    /**
     * Decrypt session key with own RSA private key
     * @param {string} encryptedSessionKey - Base64 encrypted session key
     * @param {CryptoKey} privateKey - RSA private key
     * @returns {Promise<CryptoKey>} - AES session key
     */
    async function decryptSessionKeyRSA(encryptedSessionKey, privateKey) {
        try {
            const ciphertext = base64ToBuffer(encryptedSessionKey);
            const decrypted = await crypto.subtle.decrypt(
                {
                    name: 'RSA-OAEP'
                },
                privateKey,
                ciphertext
            );
            const sessionKeyData = new TextDecoder().decode(decrypted);
            return await importSessionKey(sessionKeyData);
        } catch (error) {
            console.error('Error decrypting session key with RSA:', error);
            throw new Error('Failed to decrypt session key');
        }
    }

    /**
     * Encrypt message with AES-GCM
     * @param {string} plaintext - Message to encrypt
     * @param {CryptoKey} sessionKey - AES session key
     * @returns {Promise<{ciphertext: string, iv: string, authTag: string}>}
     */
    async function encryptMessage(plaintext, sessionKey) {
        try {
            const iv = crypto.getRandomValues(new Uint8Array(IV_LENGTH));
            const encodedText = new TextEncoder().encode(plaintext);

            const encrypted = await crypto.subtle.encrypt(
                {
                    name: 'AES-GCM',
                    iv: iv,
                    tagLength: 128 // 128-bit authentication tag
                },
                sessionKey,
                encodedText
            );

            // GCM mode includes auth tag in the ciphertext (last 16 bytes)
            const encryptedArray = new Uint8Array(encrypted);
            const ciphertext = encryptedArray.slice(0, -16);
            const authTag = encryptedArray.slice(-16);

            return {
                ciphertext: bufferToBase64(ciphertext),
                iv: bufferToBase64(iv),
                authTag: bufferToBase64(authTag)
            };
        } catch (error) {
            console.error('Error encrypting message:', error);
            throw new Error('Failed to encrypt message');
        }
    }

    /**
     * Decrypt message with AES-GCM
     * @param {string} ciphertextBase64 - Base64 encrypted message
     * @param {string} ivBase64 - Base64 IV
     * @param {string} authTagBase64 - Base64 authentication tag
     * @param {CryptoKey} sessionKey - AES session key
     * @returns {Promise<string>} - Decrypted plaintext
     */
    async function decryptMessage(ciphertextBase64, ivBase64, authTagBase64, sessionKey) {
        try {
            const ciphertext = base64ToBuffer(ciphertextBase64);
            const iv = base64ToBuffer(ivBase64);
            const authTag = base64ToBuffer(authTagBase64);

            // Combine ciphertext and auth tag
            const combined = new Uint8Array(ciphertext.length + authTag.length);
            combined.set(ciphertext);
            combined.set(authTag, ciphertext.length);

            const decrypted = await crypto.subtle.decrypt(
                {
                    name: 'AES-GCM',
                    iv: iv,
                    tagLength: 128
                },
                sessionKey,
                combined
            );

            return new TextDecoder().decode(decrypted);
        } catch (error) {
            console.error('Error decrypting message:', error);
            throw new Error('Failed to decrypt message - possibly corrupted or tampered');
        }
    }

    /**
     * Generate SHA-256 hash of string
     * @param {string} message
     * @returns {Promise<string>} - Hex string
     */
    async function sha256(message) {
        try {
            const msgBuffer = new TextEncoder().encode(message);
            const hashBuffer = await crypto.subtle.digest('SHA-256', msgBuffer);
            const hashArray = Array.from(new Uint8Array(hashBuffer));
            return hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
        } catch (error) {
            console.error('Error computing SHA-256:', error);
            throw new Error('Failed to compute hash');
        }
    }

    /**
     * Generate 24-word BIP39-style recovery mnemonic
     * @returns {string} - Space-separated mnemonic
     */
    function generateRecoveryMnemonic() {
        // Simplified word list (in production, use full BIP39 wordlist)
        const wordlist = [
            'abandon', 'ability', 'able', 'about', 'above', 'absent', 'absorb', 'abstract',
            'absurd', 'abuse', 'access', 'accident', 'account', 'accuse', 'achieve', 'acid',
            'acoustic', 'acquire', 'across', 'act', 'action', 'actor', 'actress', 'actual',
            'adapt', 'add', 'addict', 'address', 'adjust', 'admit', 'adult', 'advance',
            'advice', 'aerobic', 'affair', 'afford', 'afraid', 'again', 'age', 'agent',
            'agree', 'ahead', 'aim', 'air', 'airport', 'aisle', 'alarm', 'album',
            'alcohol', 'alert', 'alien', 'all', 'alley', 'allow', 'almost', 'alone',
            'alpha', 'already', 'also', 'alter', 'always', 'amateur', 'amazing', 'among',
            'amount', 'amused', 'analyst', 'anchor', 'ancient', 'anger', 'angle', 'angry',
            'animal', 'ankle', 'announce', 'annual', 'another', 'answer', 'antenna', 'antique',
            'anxiety', 'any', 'apart', 'apology', 'appear', 'apple', 'approve', 'april',
            'arch', 'arctic', 'area', 'arena', 'argue', 'arm', 'armed', 'armor',
            'army', 'around', 'arrange', 'arrest', 'arrive', 'arrow', 'art', 'artefact',
            'artist', 'artwork', 'ask', 'aspect', 'assault', 'asset', 'assist', 'assume'
        ];

        const words = [];
        for (let i = 0; i < 24; i++) {
            const randomIndex = Math.floor(Math.random() * wordlist.length);
            words.push(wordlist[randomIndex]);
        }
        return words.join(' ');
    }

    /**
     * Derive recovery key from mnemonic
     * @param {string} mnemonic - 24-word mnemonic
     * @returns {Promise<CryptoKey>} - AES-256 key
     */
    async function mnemonicToKey(mnemonic) {
        try {
            const mnemonicBuffer = new TextEncoder().encode(mnemonic);
            const hashBuffer = await crypto.subtle.digest('SHA-256', mnemonicBuffer);
            
            return await crypto.subtle.importKey(
                'raw',
                hashBuffer,
                { name: 'AES-GCM', length: AES_KEY_SIZE },
                true,
                ['encrypt', 'decrypt']
            );
        } catch (error) {
            console.error('Error deriving key from mnemonic:', error);
            throw new Error('Failed to derive key from recovery phrase');
        }
    }

    // Public API
    return {
        // Salt and encoding utilities
        generateSalt,
        bufferToBase64,
        base64ToBuffer,
        
        // Master key derivation
        deriveMasterKey,
        
        // RSA key pair management
        generateRSAKeyPair,
        generateKeyPair: generateRSAKeyPair, // Alias
        exportPublicKey,
        importPublicKey,
        exportPrivateKey,
        importPrivateKey,
        encryptPrivateKey,
        decryptPrivateKey,
        
        // Session key management
        generateAESSessionKey,
        generateSessionKey: generateAESSessionKey,
        exportSessionKey,
        importSessionKey,
        encryptSessionKeyRSA,
        encryptSessionKey: encryptSessionKeyRSA,
        decryptSessionKeyRSA,
        decryptSessionKey: decryptSessionKeyRSA,
        
        // Message encryption/decryption
        encryptMessage,
        decryptMessage,
        encrypt: encryptMessage, // Alias
        decrypt: decryptMessage, // Alias
        
        // Recovery keys
        generateRecoveryMnemonic,
        mnemonicToKey,
        
        // Utilities
        sha256,
        
        // Constants
        PBKDF2_ITERATIONS,
        RSA_KEY_SIZE,
        AES_KEY_SIZE
    };
})();

// Make available globally
if (typeof window !== 'undefined') {
    window.GatherlyE2EE = GatherlyE2EE;
}

console.log('✅ Gatherly E2EE Crypto Library loaded');
