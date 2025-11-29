// AI Planner Page JavaScript - OpenAI Integration

// Chat functionality
const chatForm = document.getElementById('chatForm');
const chatInput = document.getElementById('chatInput');
const chatMessages = document.getElementById('chatMessages');
const clearChatBtn = document.getElementById('clearChat');
const newChatSidebarBtn = document.getElementById('newChatSidebar');
const conversationsList = document.getElementById('conversationsList');
const toggleSidebarBtn = document.getElementById('toggleSidebar');
const showSidebarBtn = document.getElementById('showSidebar');
const toggleSidebarMobileBtn = document.getElementById('toggleSidebarMobile');
const chatHistorySidebar = document.getElementById('chatHistorySidebar');
const currentChatTitle = document.getElementById('currentChatTitle');
const renameChatBtn = document.getElementById('renameChatBtn');
const renameModal = document.getElementById('renameModal');
const newConversationTitle = document.getElementById('newConversationTitle');
const confirmRename = document.getElementById('confirmRename');
const cancelRename = document.getElementById('cancelRename');
const closeRenameModal = document.getElementById('closeRenameModal');

// Conversation history for OpenAI API
let conversationHistory = [];
let currentConversationId = null;
let allConversations = [];
let conversationToRename = null;

// Initialize with welcome message
window.addEventListener('DOMContentLoaded', () => {
    // Load conversations list
    loadConversationsList();
    
    // Load existing chat history or request greeting
    loadChatHistory();
    
    // Setup sidebar toggle handlers
    setupSidebarHandlers();
});

// Load chat history from database
async function loadChatHistory() {
    try {
        const response = await fetch('../../../src/services/ai/load-chat-history.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({})
        });
        
        const data = await response.json();
        
        if (data.success && data.messages && data.messages.length > 0) {
            currentConversationId = data.conversation_id;
            
            // Display all messages from history
            data.messages.forEach(msg => {
                if (msg.role === 'user') {
                    addUserMessage(msg.content, false); // Don't scroll for each historical message
                } else if (msg.role === 'assistant') {
                    addBotMessage(msg.content, msg.venue_ids, false);
                }
                
                // Add to conversation history for API
                conversationHistory.push({
                    role: msg.role,
                    content: msg.content
                });
            });
            
            // Scroll to bottom after all messages loaded
            scrollToBottom();
            
            // Update chat title
            updateChatTitle();
        } else {
            // No history, fetch greeting
            fetchGreeting();
        }
    } catch (error) {
        console.error('Failed to load chat history:', error);
        // Fallback to greeting
        fetchGreeting();
    }
}

// Load conversations list for sidebar
async function loadConversationsList() {
    try {
        const response = await fetch('../../../src/services/ai/manage-conversations.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'list'
            })
        });
        
        const data = await response.json();
        
        if (data.success && data.conversations) {
            allConversations = data.conversations;
            renderConversationsList(data.conversations);
        } else {
            conversationsList.innerHTML = '<p class="text-center text-gray-500 py-4 text-sm">No conversations yet</p>';
        }
    } catch (error) {
        console.error('Failed to load conversations:', error);
        conversationsList.innerHTML = '<p class="text-center text-red-400 py-4 text-sm">Failed to load</p>';
    }
}

// Render conversations in sidebar
function renderConversationsList(conversations) {
    if (!conversations || conversations.length === 0) {
        conversationsList.innerHTML = '<p class="text-center text-gray-500 py-4 text-sm">No conversations yet</p>';
        return;
    }
    
    conversationsList.innerHTML = conversations.map(conv => {
        const isActive = conv.conversation_id === currentConversationId;
        const preview = conv.first_message ? conv.first_message.substring(0, 60) + '...' : 'New conversation';
        const timeAgo = formatTimeAgo(conv.updated_at);
        
        return `
            <div class="conversation-item ${isActive ? 'bg-indigo-50 border border-indigo-200' : 'bg-gray-50 hover:bg-gray-100'} rounded-lg p-3 cursor-pointer transition-all group" 
                 data-conversation-id="${conv.conversation_id}"
                 onclick="switchConversation(${conv.conversation_id})">
                <div class="flex items-start justify-between gap-2">
                    <div class="flex-1 min-w-0">
                        <h4 class="font-semibold text-sm text-gray-800 truncate mb-1">${escapeHtml(conv.title)}</h4>
                        <p class="text-xs text-gray-600 truncate">${escapeHtml(preview)}</p>
                        <div class="flex items-center gap-2 mt-1">
                            <span class="text-xs text-gray-500">${timeAgo}</span>
                            <span class="text-xs text-gray-400">•</span>
                            <span class="text-xs text-gray-500">${conv.message_count} msgs</span>
                        </div>
                    </div>
                    <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                        <button onclick="event.stopPropagation(); renameConversation(${conv.conversation_id})" 
                                class="w-6 h-6 flex items-center justify-center text-gray-600 hover:text-indigo-600 hover:bg-indigo-100 rounded transition-colors"
                                title="Rename">
                            <i class="fas fa-edit text-xs"></i>
                        </button>
                        <button onclick="event.stopPropagation(); deleteConversation(${conv.conversation_id})" 
                                class="w-6 h-6 flex items-center justify-center text-gray-600 hover:text-red-600 hover:bg-red-100 rounded transition-colors"
                                title="Delete">
                            <i class="fas fa-trash text-xs"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

// Switch to a different conversation
async function switchConversation(conversationId) {
    if (conversationId === currentConversationId) return;
    
    try {
        const response = await fetch('../../../src/services/ai/load-chat-history.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                conversation_id: conversationId
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Clear current chat
            chatMessages.innerHTML = '';
            conversationHistory = [];
            currentConversationId = data.conversation_id;
            
            // Load messages
            if (data.messages && data.messages.length > 0) {
                data.messages.forEach(msg => {
                    if (msg.role === 'user') {
                        addUserMessage(msg.content, false);
                    } else if (msg.role === 'assistant') {
                        addBotMessage(msg.content, msg.venue_ids, false);
                    }
                    
                    conversationHistory.push({
                        role: msg.role,
                        content: msg.content
                    });
                });
                
                scrollToBottom();
            }
            
            // Update UI
            updateChatTitle();
            renderConversationsList(allConversations);
        }
    } catch (error) {
        console.error('Failed to switch conversation:', error);
        alert('Failed to load conversation');
    }
}

// Update chat title in header
function updateChatTitle() {
    const currentConv = allConversations.find(c => c.conversation_id === currentConversationId);
    if (currentConv) {
        currentChatTitle.textContent = currentConv.title;
        renameChatBtn.classList.remove('hidden');
    } else {
        currentChatTitle.textContent = 'AI Event Planner';
        renameChatBtn.classList.add('hidden');
    }
}

// Rename conversation
function renameConversation(conversationId) {
    const currentConv = allConversations.find(c => c.conversation_id === conversationId);
    conversationToRename = conversationId;
    
    // Set current title in modal
    newConversationTitle.value = currentConv ? currentConv.title : '';
    
    // Show modal
    renameModal.classList.remove('hidden');
    newConversationTitle.focus();
    newConversationTitle.select();
}

// Confirm rename action
async function confirmRenameAction() {
    const newTitle = newConversationTitle.value.trim();
    
    if (!newTitle || !conversationToRename) {
        renameModal.classList.add('hidden');
        return;
    }
    
    try {
        const response = await fetch('../../../src/services/ai/manage-conversations.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'update',
                conversation_id: conversationToRename,
                title: newTitle
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Reload conversations list
            await loadConversationsList();
            updateChatTitle();
            renameModal.classList.add('hidden');
            conversationToRename = null;
        } else {
            alert('Failed to rename conversation');
        }
    } catch (error) {
        console.error('Failed to rename conversation:', error);
        alert('Failed to rename conversation');
    }
}

// Close rename modal
function closeRenameModalAction() {
    renameModal.classList.add('hidden');
    conversationToRename = null;
    newConversationTitle.value = '';
}

// Delete conversation
async function deleteConversation(conversationId) {
    if (!confirm('Are you sure you want to delete this conversation? This cannot be undone.')) return;
    
    try {
        const response = await fetch('../../../src/services/ai/manage-conversations.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'delete',
                conversation_id: conversationId
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // If deleting current conversation, create new one
            if (conversationId === currentConversationId) {
                await createNewConversation();
            }
            
            // Reload conversations list
            await loadConversationsList();
        } else {
            alert('Failed to delete conversation');
        }
    } catch (error) {
        console.error('Failed to delete conversation:', error);
        alert('Failed to delete conversation');
    }
}

// Create new conversation
async function createNewConversation() {
    try {
        const response = await fetch('../../../src/services/ai/manage-conversations.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'create',
                title: 'New Conversation'
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Clear UI
            chatMessages.innerHTML = '';
            conversationHistory = [];
            currentConversationId = data.conversation.conversation_id;
            
            // Reload conversations list
            await loadConversationsList();
            
            // Fetch greeting for new conversation
            fetchGreeting();
        } else {
            alert('Failed to create new conversation');
        }
    } catch (error) {
        console.error('Error creating new conversation:', error);
        alert('Failed to create new conversation');
    }
}

// Setup sidebar toggle handlers
function setupSidebarHandlers() {
    if (toggleSidebarBtn) {
        toggleSidebarBtn.addEventListener('click', () => {
            chatHistorySidebar.classList.add('hidden');
            showSidebarBtn.classList.remove('hidden');
        });
    }
    
    if (showSidebarBtn) {
        showSidebarBtn.addEventListener('click', () => {
            chatHistorySidebar.classList.remove('hidden');
            showSidebarBtn.classList.add('hidden');
        });
    }
    
    if (toggleSidebarMobileBtn) {
        toggleSidebarMobileBtn.addEventListener('click', () => {
            chatHistorySidebar.classList.toggle('hidden');
        });
    }
    
    // Setup modal handlers
    if (confirmRename) {
        confirmRename.addEventListener('click', confirmRenameAction);
    }
    
    if (cancelRename) {
        cancelRename.addEventListener('click', closeRenameModalAction);
    }
    
    if (closeRenameModal) {
        closeRenameModal.addEventListener('click', closeRenameModalAction);
    }
    
    // Close modal on escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !renameModal.classList.contains('hidden')) {
            closeRenameModalAction();
        }
    });
    
    // Close modal on backdrop click
    if (renameModal) {
        renameModal.addEventListener('click', (e) => {
            if (e.target === renameModal) {
                closeRenameModalAction();
            }
        });
    }
    
    // Submit on Enter key in input
    if (newConversationTitle) {
        newConversationTitle.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                confirmRenameAction();
            }
        });
    }
}

// Format time ago
function formatTimeAgo(dateString) {
    // Parse the timestamp (handles both ISO format and MySQL datetime)
    const date = new Date(dateString);
    const now = new Date();
    
    // Calculate difference in seconds
    const seconds = Math.floor((now - date) / 1000);
    
    // Handle negative values (future dates or timezone issues)
    if (seconds < 0) return 'Just now';
    
    if (seconds < 60) return 'Just now';
    if (seconds < 3600) return Math.floor(seconds / 60) + 'm ago';
    if (seconds < 86400) return Math.floor(seconds / 3600) + 'h ago';
    if (seconds < 604800) return Math.floor(seconds / 86400) + 'd ago';
    
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}

// Fetch initial greeting from AI
async function fetchGreeting() {
    showTypingIndicator();
    
    try {
        const response = await fetch('../../../src/services/ai/ai-conversation.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                message: '__greeting__',
                history: [],
                conversation_id: currentConversationId
            })
        });

        const data = await response.json();
        removeTypingIndicator();

        if (data.success) {
            currentConversationId = data.conversation_id;
            addBotMessage(data.response);
            // Add to conversation history
            conversationHistory.push({
                role: 'assistant',
                content: data.response
            });
        } else {
            addBotMessage("Hello! I'm your AI event planning assistant. Tell me about your event and I'll help you find the perfect venue!");
        }
    } catch (error) {
        console.error('Greeting error:', error);
        removeTypingIndicator();
        addBotMessage("Hello! I'm your AI event planning assistant. Tell me about your event and I'll help you find the perfect venue!");
    }
}

// Handle chat form submission
if (chatForm) {
    chatForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const message = chatInput.value.trim();
        if (!message) return;

        // Add user message to chat
        addUserMessage(message);
        chatInput.value = '';

        // Add to conversation history
        conversationHistory.push({
            role: 'user',
            content: message
        });

        // Show typing indicator
        showTypingIndicator();

        try {
            // Send message to OpenAI API endpoint
            const response = await fetch('../../../src/services/ai/ai-conversation.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    message: message,
                    history: conversationHistory.slice(0, -1), // Send all history except the current message
                    conversation_id: currentConversationId
                })
            });
            
            const data = await response.json();
            
            // Remove typing indicator
            removeTypingIndicator();

            if (data.success) {
                // Update conversation ID
                currentConversationId = data.conversation_id;
                
                // Add AI response to conversation history
                conversationHistory.push({
                    role: 'assistant',
                    content: data.response
                });
                
                // Display response (venues are now embedded in Gemini's markdown response)
                addBotMessage(data.response, data.venue_ids || null);
                
                // Reload conversations list to update message count and preview
                loadConversationsList();
            } else {
                addBotMessage(data.response || 'Sorry, I encountered an error. Please try again.');
                console.error('API Error:', data.error);
            }
        } catch (error) {
            console.error('Chat error:', error);
            removeTypingIndicator();
            addBotMessage('Sorry, I\'m having trouble connecting. Please try again later.');
        }
    });
}

// Quick action buttons
const quickActionBtns = document.querySelectorAll('.quick-action');
quickActionBtns.forEach(btn => {
    btn.addEventListener('click', () => {
        chatInput.value = btn.textContent.trim();
        chatInput.focus();
    });
});

// Clear chat button
if (clearChatBtn) {
    clearChatBtn.addEventListener('click', async () => {
        await createNewConversation();
    });
}

// New chat sidebar button
if (newChatSidebarBtn) {
    newChatSidebarBtn.addEventListener('click', async () => {
        await createNewConversation();
    });
}

// Rename chat button in header
if (renameChatBtn) {
    renameChatBtn.addEventListener('click', async () => {
        if (currentConversationId) {
            await renameConversation(currentConversationId);
        }
    });
}

// Add user message to chat
function addUserMessage(message, shouldScroll = true) {
    const messageDiv = document.createElement('div');
    messageDiv.className = 'flex items-start justify-end gap-3 mb-4';
    messageDiv.innerHTML = `
        <div class="max-w-lg p-4 bg-indigo-600 text-white rounded-2xl shadow-md">
            <p class="leading-relaxed">${escapeHtml(message)}</p>
        </div>
        <div class="flex items-center justify-center shrink-0 w-10 h-10 bg-gray-300 rounded-full">
            <i class="text-gray-600 fas fa-user"></i>
        </div>
    `;
    chatMessages.appendChild(messageDiv);
    if (shouldScroll) {
        scrollToBottom();
    }
}

// Add bot message to chat
function addBotMessage(message, venueIds = null, shouldScroll = true) {
    const messageDiv = document.createElement('div');
    messageDiv.className = 'flex items-start gap-3 mb-4';
    
    let actionSection = '';
    if (venueIds && venueIds.length > 0) {
        actionSection = `
            <div class="mt-4">
                <h4 class="font-bold text-indigo-900 mb-3 flex items-center gap-2 text-lg">
                    <i class="fas fa-hand-pointer"></i>
                    Select a venue to create your event:
                </h4>
                <div id="venueCards" class="space-y-3">
                    ${venueIds.map(id => `
                        <div class="venue-card-loading p-4 bg-gray-100 rounded-xl animate-pulse" data-venue-id="${id}">
                            <div class="h-6 bg-gray-300 rounded w-3/4 mb-2"></div>
                            <div class="h-4 bg-gray-300 rounded w-1/2"></div>
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
    }
    
    messageDiv.innerHTML = `
        <div class="flex items-center justify-center shrink-0 w-10 h-10 bg-indigo-600 rounded-full">
            <i class="text-white fas fa-robot"></i>
        </div>
        <div class="max-w-3xl p-4 bg-white rounded-2xl shadow-md border-2 border-gray-200">
            <div class="text-gray-800 leading-relaxed prose prose-sm max-w-none">${formatMarkdown(message)}</div>
            ${actionSection}
        </div>
    `;
    chatMessages.appendChild(messageDiv);
    if (shouldScroll) {
        scrollToBottom();
    }
    
    // Load venue details if venue IDs exist
    if (venueIds && venueIds.length > 0) {
        loadVenueCards(venueIds);
    }
}

// Show typing indicator
function showTypingIndicator() {
    const typingDiv = document.createElement('div');
    typingDiv.id = 'typingIndicator';
    typingDiv.className = 'flex items-start gap-3 mb-4';
    typingDiv.innerHTML = `
        <div class="flex items-center justify-center shrink-0 w-10 h-10 bg-indigo-600 rounded-full">
            <i class="text-white fas fa-robot"></i>
        </div>
        <div class="p-4 bg-white rounded-2xl shadow-md border-2 border-gray-200">
            <div class="flex gap-1">
                <div class="w-2 h-2 bg-indigo-600 rounded-full animate-bounce" style="animation-delay: 0ms;"></div>
                <div class="w-2 h-2 bg-indigo-600 rounded-full animate-bounce" style="animation-delay: 150ms;"></div>
                <div class="w-2 h-2 bg-indigo-600 rounded-full animate-bounce" style="animation-delay: 300ms;"></div>
            </div>
        </div>
    `;
    chatMessages.appendChild(typingDiv);
    scrollToBottom();
}

// Remove typing indicator
function removeTypingIndicator() {
    const typingIndicator = document.getElementById('typingIndicator');
    if (typingIndicator) {
        typingIndicator.remove();
    }
}

// Scroll chat to bottom
function scrollToBottom() {
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

// Escape HTML to prevent XSS
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Format markdown tables
function formatTables(text) {
    // Match markdown tables (header | separator | rows)
    const tableRegex = /^(\|.+\|\s*\n)(\|\s*:?-+:?\s*\|.+\|\s*\n)((?:\|.+\|\s*\n?)+)/gm;
    
    return text.replace(tableRegex, function(match, header, separator, rows) {
        // Parse header
        const headerCells = header.split('|').filter(cell => cell.trim()).map(cell => cell.trim());
        
        // Parse rows
        const rowLines = rows.trim().split('\n');
        const bodyRows = rowLines.map(row => {
            return row.split('|').filter(cell => cell.trim()).map(cell => cell.trim());
        });
        
        // Build HTML table
        let html = '<div class="overflow-x-auto my-4"><table class="min-w-full border-collapse border border-gray-300 text-sm">';
        
        // Table header
        html += '<thead class="bg-indigo-100"><tr>';
        headerCells.forEach(cell => {
            html += `<th class="border border-gray-300 px-4 py-2 text-left font-semibold text-indigo-900">${cell}</th>`;
        });
        html += '</tr></thead>';
        
        // Table body
        html += '<tbody>';
        bodyRows.forEach((row, idx) => {
            const bgClass = idx % 2 === 0 ? 'bg-white' : 'bg-gray-50';
            html += `<tr class="${bgClass}">`;
            row.forEach(cell => {
                html += `<td class="border border-gray-300 px-4 py-2">${cell}</td>`;
            });
            html += '</tr>';
        });
        html += '</tbody></table></div>';
        
        return html;
    });
}

// Format markdown text (supports bold, italic, lists, headers, tables, code blocks) blocks)
function formatMarkdown(text) {
    // Escape HTML first
    let formatted = escapeHtml(text);
    
    // Convert markdown tables
    formatted = formatTables(formatted);
    
    // Convert horizontal rules (--- or ***) - MUST be before headers
    formatted = formatted.replace(/^(---|\*\*\*)$/gm, '<hr class="my-4 border-t-2 border-gray-300">');
    
    // Convert #### Headers (h4)
    formatted = formatted.replace(/^####\s+(.+)$/gm, '<h4 class="text-base font-bold text-indigo-800 mt-3 mb-1.5">$1</h4>');
    
    // Convert ### Headers (h3)
    formatted = formatted.replace(/^###\s+(.+)$/gm, '<h3 class="text-lg font-bold text-indigo-900 mt-4 mb-2">$1</h3>');
    
    // Convert ## Headers (h2)
    formatted = formatted.replace(/^##\s+(.+)$/gm, '<h2 class="text-xl font-bold text-indigo-900 mt-5 mb-3">$1</h2>');
    
    // Convert # Headers (h1)
    formatted = formatted.replace(/^#\s+(.+)$/gm, '<h1 class="text-2xl font-bold text-indigo-900 mt-6 mb-3">$1</h1>');
    
    // Convert **bold** to <strong> (non-greedy to avoid matching across lines)
    formatted = formatted.replace(/\*\*([^\*\n]+?)\*\*/g, '<strong class="font-semibold text-gray-900">$1</strong>');
    
    // Convert *italic* to <em> (but not if part of ** or list markers)
    formatted = formatted.replace(/(?<!\*)\*([^\*\n]+?)\*(?!\*)/g, '<em class="italic">$1</em>');
    
    // Convert `code` to <code>
    formatted = formatted.replace(/`([^`]+?)`/g, '<code class="px-1.5 py-0.5 bg-gray-100 text-indigo-600 rounded text-sm font-mono">$1</code>');
    
    // Convert numbered lists (1. item, 2. item, etc.)
    formatted = formatted.replace(/^\d+\.\s+(.+)$/gm, '<li class="ml-4 mb-1.5">$1</li>');
    
    // Convert bullet lists (* item or - item) - including indented ones
    formatted = formatted.replace(/^(\s*)([\*\-])\s+(.+)$/gm, function(match, indent, marker, content) {
        const indentLevel = Math.floor(indent.length / 4); // 4 spaces = 1 indent level
        const marginClass = indentLevel > 0 ? `ml-${4 + (indentLevel * 4)}` : 'ml-4';
        return `<li class="${marginClass} mb-1.5">${content}</li>`;
    });
    
    // Wrap consecutive <li> in <ul> or <ol>
    formatted = formatted.replace(/(<li.*?<\/li>\s*)+/g, function(match) {
        return '<ul class="list-disc ml-6 mb-3 space-y-1">' + match + '</ul>';
    });
    
    // Convert paragraphs (double line breaks)
    formatted = formatted.replace(/\n\n+/g, '</p><p class="mb-3">');
    formatted = '<p class="mb-3">' + formatted + '</p>';
    
    // Clean up empty paragraphs
    formatted = formatted.replace(/<p class="mb-3"><\/p>/g, '');
    
    // Convert single line breaks to <br> (but not around block elements)
    formatted = formatted.replace(/\n(?!<\/?(ul|li|h\d|hr|p|table|thead|tbody|tr|th|td))/g, '<br>');
    
    return formatted;
}

// Format number with commas
function formatNumber(num) {
    if (num === null || num === undefined || num === '') {
        return 'N/A';
    }
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}

// Select venue and redirect to create event page
function selectVenue(venueId) {
    // Store venue ID in session storage for create-event page
    sessionStorage.setItem('selectedVenueId', venueId);
    sessionStorage.setItem('fromAIPlanner', 'true');
    
    // Redirect to create event page
    window.location.href = 'create-event.php?venue_id=' + venueId;
}

// Load venue details and render cards
async function loadVenueCards(venueIds) {
    try {
        const response = await fetch('../../../src/services/ai/get-venue-details.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ venue_ids: venueIds })
        });
        
        const data = await response.json();
        
        if (data.success && data.venues) {
            data.venues.forEach(venue => {
                const card = document.querySelector(`[data-venue-id="${venue.venue_id}"]`);
                if (card) {
                    const location = venue.city && venue.province ? `${venue.city}, ${venue.province}` : 'Location not specified';
                    
                    card.className = 'p-4 border-2 border-indigo-200 rounded-xl bg-gradient-to-br from-white to-indigo-50 hover:from-indigo-50 hover:to-indigo-100 transition-all cursor-pointer shadow-md hover:shadow-lg';
                    card.innerHTML = `
                        <div class="flex items-start justify-between mb-3">
                            <div class="flex-1">
                                <h5 class="font-bold text-indigo-900 text-lg mb-1">${escapeHtml(venue.venue_name)}</h5>
                                <p class="text-sm text-gray-600 flex items-center gap-1">
                                    <i class="fas fa-map-marker-alt text-indigo-600"></i>
                                    ${escapeHtml(location)}
                                </p>
                            </div>
                            <div class="text-right">
                                <div class="text-sm font-semibold text-indigo-700">₱${formatNumber(venue.base_price)}</div>
                                <div class="text-xs text-gray-500">Base Price</div>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-2 mb-3">
                            <div class="flex items-center gap-2 text-sm text-gray-700">
                                <i class="fas fa-users text-indigo-600"></i>
                                <span><strong>${venue.capacity}</strong> guests</span>
                            </div>
                            <div class="flex items-center gap-2 text-sm text-gray-700">
                                <i class="fas fa-calendar text-indigo-600"></i>
                                <span>₱${formatNumber(venue.weekend_price)} (weekend)</span>
                            </div>
                        </div>
                        ${venue.amenities ? `
                            <p class="text-xs text-gray-600 mb-3">
                                <i class="fas fa-check-circle text-green-600"></i>
                                <strong>Amenities:</strong> ${escapeHtml(venue.amenities.substring(0, 80))}${venue.amenities.length > 80 ? '...' : ''}
                            </p>
                        ` : ''}
                        <button onclick="selectVenue(${venue.venue_id})" 
                            class="w-full px-4 py-2 bg-indigo-600 text-white rounded-lg font-semibold hover:bg-indigo-700 transition-all transform hover:scale-105 shadow-md flex items-center justify-center gap-2">
                            <i class="fas fa-calendar-plus"></i>
                            Create Event at This Venue
                        </button>
                    `;
                }
            });
        }
    } catch (error) {
        console.error('Error loading venue details:', error);
    }
}

// Close dropdown with Escape key
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && profileDropdown && !profileDropdown.classList.contains('hidden')) {
        profileDropdown.classList.add('hidden');
    }
});
