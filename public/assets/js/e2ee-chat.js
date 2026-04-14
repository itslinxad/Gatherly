/**
 * Gatherly E2EE Chat Handler
 * Handles encryption/decryption of chat messages with E2EE
 * Works alongside the legacy encryption system during transition period
 */

const GatherlyE2EEChat = (function() {
    'use strict';

    /**
     * Generate conversation ID from two user IDs (deterministic)
     * @param {number} userId1
     * @param {number} userId2
     * @returns {string}
     */
    function generateConversationId(userId1, userId2) {
        // Always use the same order for consistent conversation IDs
        const ids = [userId1, userId2].sort((a, b) => a - b);
        return `conv_${ids[0]}_${ids[1]}`;
    }

    /**
     * Get or create session key for a conversation
     * @param {string} conversationId
     * @param {number} otherUserId
     * @returns {Promise<CryptoKey>}
     */
    async function getOrCreateSessionKey(conversationId, otherUserId) {
        // Check if we already have this session key
        let sessionKey = await GatherlyKeyManager.getConversationSessionKey(conversationId);
        
        if (sessionKey) {
            console.log('[E2EE Chat] Using existing session key for conversation:', conversationId);
            return sessionKey;
        }

        // Generate new session key
        console.log('[E2EE Chat] Generating new session key for conversation:', conversationId);
        sessionKey = await GatherlyE2EE.generateSessionKey();

        // Get other user's public key
        const response = await fetch(`/Gatherly/public/api/e2ee/get-public-key.php?userId=${otherUserId}`);
        const data = await response.json();

        if (!data.success) {
            throw new Error(data.error || 'Failed to get recipient public key');
        }

        // Import recipient's public key
        const recipientPublicKey = await GatherlyE2EE.importPublicKey(data.publicKey);

        // Encrypt session key with recipient's public key
        const encryptedSessionKey = await GatherlyE2EE.encryptSessionKey(sessionKey, recipientPublicKey);

        // Get our own public key to encrypt session key for ourselves
        const ownKeys = await GatherlyKeyManager.getOwnKeys();
        const ownPublicKey = await GatherlyE2EE.importPublicKey(ownKeys.publicKey);

        // Encrypt session key with our own public key (so we can decrypt it later)
        const encryptedSessionKeyForSelf = await GatherlyE2EE.encryptSessionKey(sessionKey, ownPublicKey);

        // Store both encrypted session keys in IndexedDB (we'll use ours to decrypt later)
        await GatherlyKeyManager.storeConversationSessionKey(
            conversationId,
            encryptedSessionKeyForSelf,
            ownKeys.keyVersion,
            '' // IV not needed for RSA
        );

        // Return the unencrypted session key for immediate use
        // Also return the encrypted version for the recipient (to be sent with first message)
        sessionKey.encryptedForRecipient = encryptedSessionKey;
        sessionKey.keyVersion = ownKeys.keyVersion;

        return sessionKey;
    }

    /**
     * Encrypt a message for sending
     * @param {string} plaintext - The message to encrypt
     * @param {number} recipientUserId - The recipient's user ID
     * @param {number} senderUserId - The sender's user ID (current user)
     * @returns {Promise<Object>} - { ciphertext, iv, authTag, encryptedSessionKey, keyVersion }
     */
    async function encryptMessage(plaintext, recipientUserId, senderUserId) {
        try {
            const conversationId = generateConversationId(senderUserId, recipientUserId);
            
            // Get or create session key for this conversation
            const sessionKey = await getOrCreateSessionKey(conversationId, recipientUserId);

            // Encrypt the message with AES-GCM
            const encrypted = await GatherlyE2EE.encrypt(plaintext, sessionKey);

            return {
                ciphertext: encrypted.ciphertext,
                iv: encrypted.iv,
                authTag: encrypted.authTag,
                encryptedSessionKey: sessionKey.encryptedForRecipient || null,
                keyVersion: sessionKey.keyVersion,
                encryptionType: 'e2ee'
            };
        } catch (error) {
            console.error('[E2EE Chat] Encryption failed:', error);
            throw error;
        }
    }

    /**
     * Decrypt a received message
     * @param {Object} encryptedData - { ciphertext, iv, authTag, encryptedSessionKey, keyVersion }
     * @param {number} otherUserId - The other user in the conversation
     * @param {number} currentUserId - Current user's ID
     * @returns {Promise<string>} - Decrypted plaintext
     */
    async function decryptMessage(encryptedData, otherUserId, currentUserId) {
        try {
            const conversationId = generateConversationId(currentUserId, otherUserId);

            // Check if we have the session key
            let sessionKey = await GatherlyKeyManager.getConversationSessionKey(conversationId);

            // If we don't have the session key, but we have it encrypted in the message
            if (!sessionKey && encryptedData.encryptedSessionKey) {
                console.log('[E2EE Chat] Decrypting session key from message');
                
                // Get our private key
                const ownKeys = await GatherlyKeyManager.getOwnKeys();
                
                // Decrypt the session key
                sessionKey = await GatherlyE2EE.decryptSessionKey(
                    encryptedData.encryptedSessionKey,
                    ownKeys.privateKey
                );

                // Store it for future use
                // We need to re-encrypt it with our public key for storage
                const ownPublicKey = await GatherlyE2EE.importPublicKey(ownKeys.publicKey);
                const reEncryptedSessionKey = await GatherlyE2EE.encryptSessionKey(sessionKey, ownPublicKey);
                
                await GatherlyKeyManager.storeConversationSessionKey(
                    conversationId,
                    reEncryptedSessionKey,
                    encryptedData.keyVersion,
                    ''
                );
            }

            if (!sessionKey) {
                throw new Error('Session key not available for this conversation');
            }

            // Decrypt the message
            const plaintext = await GatherlyE2EE.decrypt(
                encryptedData.ciphertext,
                sessionKey,
                encryptedData.iv,
                encryptedData.authTag
            );

            return plaintext;
        } catch (error) {
            console.error('[E2EE Chat] Decryption failed:', error);
            throw error;
        }
    }

    /**
     * Send an encrypted message
     * @param {string} message - Plain text message
     * @param {number} recipientUserId
     * @returns {Promise<Object>} - Server response
     */
    async function sendMessage(message, recipientUserId) {
        try {
            const currentUserId = GatherlyKeyManager.session.getUserId();
            
            if (!currentUserId) {
                throw new Error('User not authenticated');
            }

            // Check if both users have E2EE enabled
            const statusResponse = await fetch(`/Gatherly/public/api/e2ee/key-status.php`);
            const statusData = await statusResponse.json();

            const recipientStatusResponse = await fetch(`/Gatherly/public/api/e2ee/get-public-key.php?userId=${recipientUserId}`);
            const recipientStatusData = await recipientStatusResponse.json();

            const bothHaveE2EE = statusData.success && statusData.hasKeys && recipientStatusData.success;

            let messageData;

            if (bothHaveE2EE) {
                // Use E2EE encryption
                console.log('[E2EE Chat] Encrypting message with E2EE');
                
                try {
                    const encrypted = await encryptMessage(message, recipientUserId, currentUserId);
                    
                    // Validate all required E2EE fields are present
                    if (!encrypted.ciphertext || !encrypted.iv || !encrypted.authTag || !encrypted.encryptedSessionKey) {
                        throw new Error('E2EE encryption missing required fields');
                    }
                    
                    messageData = {
                        action: 'send_message',
                        receiver_id: recipientUserId,
                        message: encrypted.ciphertext,
                        encryption_type: 'e2ee',
                        encrypted_session_key: encrypted.encryptedSessionKey,
                        iv: encrypted.iv,
                        auth_tag: encrypted.authTag,
                        key_version: encrypted.keyVersion
                    };
                } catch (e2eeError) {
                    console.warn('[E2EE Chat] E2EE encryption failed, falling back to legacy:', e2eeError.message);
                    // Fall through to legacy below
                    messageData = null;
                }
            } else {
                messageData = null;
            }

            if (!messageData) {
                // Fall back to legacy encryption (server-side)
                console.log('[E2EE Chat] Falling back to legacy encryption');
                messageData = {
                    action: 'send_message',
                    receiver_id: recipientUserId,
                    message: message,
                    encryption_type: 'legacy'
                };
            }

            // Send to server
            const response = await fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams(messageData)
            });

            const result = await response.json();
            return result;

        } catch (error) {
            console.error('[E2EE Chat] Send message failed:', error);
            throw error;
        }
    }

    /**
     * Decrypt messages in a conversation list
     * @param {Array} messages - Array of message objects from server
     * @param {number} otherUserId - The other user in conversation
     * @returns {Promise<Array>} - Array of messages with decrypted text
     */
    async function decryptMessages(messages, otherUserId) {
        // Check if user has E2EE keys available
        const hasKeys = GatherlyKeyManager && GatherlyKeyManager.hasActiveSession();
        
        // If no keys, return messages as-is (legacy mode)
        if (!hasKeys) {
            console.log('[E2EE Chat] No keys available, treating all messages as legacy');
            return messages.map(msg => ({
                ...msg,
                encryption_type: 'legacy'
            }));
        }
        
        const currentUserId = GatherlyKeyManager.session.getUserId();
        
        const decryptedMessages = await Promise.all(messages.map(async (msg) => {
            try {
                // Skip if encryption_type is not 'e2ee' or is null/undefined
                if (msg.encryption_type !== 'e2ee') {
                    return {
                        ...msg,
                        encryption_type: 'legacy',
                        decrypted: false,
                        legacy: true
                    };
                }
                
                // Validate required E2EE fields exist AND are valid (non-empty strings)
                const hasAllE2EEFields = msg.message_text && msg.iv && msg.auth_tag && 
                                       msg.message_text.length > 10 && 
                                       msg.iv.length > 5 && 
                                       msg.auth_tag.length > 5;
                
                if (!hasAllE2EEFields) {
                    console.warn('[E2EE Chat] Missing or invalid E2EE fields, treating as legacy');
                    return {
                        ...msg,
                        encryption_type: 'legacy',
                        decrypted: false,
                        legacy: true
                    };
                }
                
                // Decrypt E2EE message
                const decrypted = await decryptMessage({
                    ciphertext: msg.message_text,
                    iv: msg.iv,
                    authTag: msg.auth_tag,
                    encryptedSessionKey: msg.encrypted_session_key,
                    keyVersion: msg.key_version
                }, otherUserId, currentUserId);

                return {
                    ...msg,
                    message_text: decrypted,
                    decrypted: true
                };
            } catch (error) {
                console.warn('[E2EE Chat] Decryption failed, treating message as legacy:', error.message);
                return {
                    ...msg,
                    encryption_type: 'legacy',
                    message_text: msg.message_text || '[Unable to decrypt]',
                    decrypted: false,
                    legacy: true
                };
            }
        }));

        return decryptedMessages;
    }

    // Public API
    return {
        encryptMessage,
        decryptMessage,
        sendMessage,
        decryptMessages,
        generateConversationId
    };
})();
