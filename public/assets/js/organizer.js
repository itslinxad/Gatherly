// Organizer Dashboard JavaScript

// Profile dropdown toggle - Only initialize if not already done by OrganizerSidebar.php
if (!window.profileDropdownInitialized) {
    const profileBtn = document.getElementById('profile-dropdown-btn');
    const profileDropdown = document.getElementById('profile-dropdown');

    if (profileBtn && profileDropdown) {
        profileBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            profileDropdown.classList.toggle('hidden');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!profileBtn.contains(e.target) && !profileDropdown.contains(e.target)) {
                profileDropdown.classList.add('hidden');
            }
        });
    }
    window.profileDropdownInitialized = true;
}

// AI Chatbot Modal Management
const openChatbotBtn = document.getElementById('openChatbot');
const closeChatbotBtn = document.getElementById('closeChatbot');
const chatbotModal = document.getElementById('chatbotModal');
const chatForm = document.getElementById('chatForm');
const chatInput = document.getElementById('chatInput');
const chatMessages = document.getElementById('chatMessages');

// Conversation state for multi-turn dialogue
let conversationState = {};

// Open chatbot modal
if (openChatbotBtn) {
    openChatbotBtn.addEventListener('click', () => {
        chatbotModal.classList.remove('hidden');
        chatInput.focus();
        
        // Add welcome message if chat is empty
        if (chatMessages.children.length === 0) {
            addBotMessage("Hello! 👋 I'm your AI event planning assistant. I'll help you find the perfect venue and suppliers for your event by asking you a few questions. Let's get started!\n\nWhat type of event are you planning?");
        }
    });
}

// Close chatbot modal
if (closeChatbotBtn) {
    closeChatbotBtn.addEventListener('click', () => {
        chatbotModal.classList.add('hidden');
    });
}

// Close modal when clicking outside
if (chatbotModal) {
    chatbotModal.addEventListener('click', (e) => {
        if (e.target === chatbotModal) {
            chatbotModal.classList.add('hidden');
        }
    });
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

        // Show typing indicator
        showTypingIndicator();

        try {
            // Send message to AI conversational planner API
            const response = await fetch('/../../../src/services/ai-conversation.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    message: message,
                    conversation_state: conversationState
                })
            });

            const data = await response.json();
            
            // Remove typing indicator
            removeTypingIndicator();

            if (data.success) {
                // Update conversation state
                if (data.conversation_state) {
                    conversationState = data.conversation_state;
                }
                
                // Add AI response to chat
                if (data.needs_more_info) {
                    // Still gathering information
                    addBotMessage(data.response);
                } else {
                    // Final recommendations
                    addBotMessage(data.response, data.venues, data.suppliers);
                }
            } else {
                addBotMessage('Sorry, I encountered an error. Please try again.');
                console.error('API Error:', data.error);
            }
        } catch (error) {
            console.error('Chat error:', error);
            removeTypingIndicator();
            addBotMessage('Sorry, I\'m having trouble connecting. Please try again later. Organizer');
        }
    });
}

// Add user message to chat
function addUserMessage(message) {
    const messageDiv = document.createElement('div');
    messageDiv.className = 'flex items-start justify-end gap-3 mb-4';
    messageDiv.innerHTML = `
        <div class="max-w-md p-4 bg-indigo-600 text-white rounded-lg shadow-sm">
            <p>${escapeHtml(message)}</p>
        </div>
        <div class="flex items-center justify-center shrink-0 w-8 h-8 bg-gray-300 rounded-full">
            <i class="text-gray-600 fas fa-user"></i>
        </div>
    `;
    chatMessages.appendChild(messageDiv);
    scrollToBottom();
}

// Add bot message to chat
function addBotMessage(message, venues = null, suppliers = null) {
    const messageDiv = document.createElement('div');
    messageDiv.className = 'flex items-start gap-3 mb-4';
    
    let contentHTML = '';
    
    // Venue recommendations
    if (venues && venues.length > 0) {
        contentHTML += '<div class="mt-3"><h4 class="font-bold text-indigo-900 mb-2">🏛️ Venue Recommendations:</h4><div class="space-y-2">';
        venues.forEach(venue => {
            contentHTML += `
                <div class="p-3 border border-indigo-200 rounded-lg bg-indigo-50 hover:bg-indigo-100 transition-colors">
                    <h5 class="font-semibold text-indigo-900">${escapeHtml(venue.name)}</h5>
                    <p class="text-sm text-gray-700">
                        <i class="mr-1 fas fa-users"></i> Capacity: ${venue.capacity} |
                        <i class="ml-2 mr-1 fas fa-peso-sign"></i> ₱${formatNumber(venue.price)}
                    </p>
                    <p class="text-sm text-gray-600">
                        <i class="mr-1 fas fa-map-marker-alt"></i> ${escapeHtml(venue.location)}
                    </p>
                    <p class="text-xs text-gray-600 mt-1">${escapeHtml(venue.description)}</p>
                    <div class="flex items-center justify-between mt-2">
                        <span class="text-xs font-semibold text-green-600">
                            <i class="mr-1 fas fa-star"></i> ${venue.score}% Match
                        </span>
                        <a href="venue-details.php?id=${venue.id}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-700">
                            View Details <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
            `;
        });
        contentHTML += '</div></div>';
    }
    
    // Supplier recommendations
    if (suppliers && Object.keys(suppliers).length > 0) {
        contentHTML += '<div class="mt-4"><h4 class="font-bold text-indigo-900 mb-2">👥 Recommended Suppliers:</h4>';
        
        for (const [category, services] of Object.entries(suppliers)) {
            if (services && services.length > 0) {
                // Get category icon
                const icons = {
                    'Catering': '🍽️',
                    'Lights and Sounds': '🎵',
                    'Photography': '📸',
                    'Videography': '🎥',
                    'Host/Emcee': '🎤',
                    'Styling and Flowers': '💐',
                    'Equipment Rental': '🪑'
                };
                const icon = icons[category] || '📋';
                
                contentHTML += `<div class="mb-3"><h5 class="font-semibold text-sm text-gray-700 mb-2">${icon} ${category}</h5><div class="space-y-2">`;
                
                services.forEach(service => {
                    contentHTML += `
                        <div class="p-2 border border-blue-200 rounded bg-blue-50 hover:bg-blue-100 transition-colors">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <h6 class="font-semibold text-sm text-blue-900">${escapeHtml(service.service_name)}</h6>
                                    <p class="text-xs text-gray-600">${escapeHtml(service.supplier_name)}</p>
                                    <p class="text-xs text-gray-500 mt-1">${escapeHtml(service.description)}</p>
                                </div>
                                <div class="text-right ml-2">
                                    <p class="text-sm font-bold text-green-600">₱${formatNumber(service.price)}</p>
                                    <p class="text-xs text-gray-500">${escapeHtml(service.location)}</p>
                                </div>
                            </div>
                        </div>
                    `;
                });
                
                contentHTML += '</div></div>';
            }
        }
        contentHTML += '</div>';
    }
    
    messageDiv.innerHTML = `
        <div class="flex items-center justify-center shrink-0 w-8 h-8 bg-indigo-600 rounded-full">
            <i class="text-white fas fa-robot"></i>
        </div>
        <div class="max-w-2xl p-4 bg-white rounded-lg shadow-sm">
            <p class="text-gray-800 whitespace-pre-line">${escapeHtml(message)}</p>
            ${contentHTML}
        </div>
    `;
    chatMessages.appendChild(messageDiv);
    scrollToBottom();
}

// Show typing indicator
function showTypingIndicator() {
    const typingDiv = document.createElement('div');
    typingDiv.id = 'typingIndicator';
    typingDiv.className = 'flex items-start gap-3 mb-4';
    typingDiv.innerHTML = `
        <div class="flex items-center justify-center shrink-0 w-8 h-8 bg-indigo-600 rounded-full">
            <i class="text-white fas fa-robot"></i>
        </div>
        <div class="p-4 bg-white rounded-lg shadow-sm">
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

// Format number with commas
function formatNumber(num) {
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}

// Close modal with Escape key
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && chatbotModal && !chatbotModal.classList.contains('hidden')) {
        chatbotModal.classList.add('hidden');
    }
});
