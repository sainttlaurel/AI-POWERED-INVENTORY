// Enhanced Modern Chatbot Functionality
(function() {
    'use strict';
    
    // Local variables to prevent conflicts
    let chatHistory = [];
    let isTyping = false;
    let isExpanded = false;
    
    // Make functions global so they can be called from HTML
    window.toggleChatbot = toggleChatbot;
    window.quickQuery = quickQuery;
    window.sendMessage = sendMessage;
    window.clearChat = clearChat;

function toggleChatbot() {
    const fab = document.getElementById('chatbot-fab');
    const container = document.getElementById('chatbot-container');
    
    if (!isExpanded) {
        // Expand chatbot
        fab.classList.add('active');
        container.classList.add('active');
        isExpanded = true;
        
        // Focus input after animation
        setTimeout(() => {
            const input = document.getElementById('chatbot-input');
            if (input) input.focus();
        }, 400);
        
        // Auto-scroll to bottom
        setTimeout(() => {
            const messagesDiv = document.getElementById('chatbot-messages');
            if (messagesDiv) {
                messagesDiv.scrollTop = messagesDiv.scrollHeight;
            }
        }, 100);
    } else {
        // Collapse chatbot
        fab.classList.remove('active');
        container.classList.remove('active');
        isExpanded = false;
    }
}

function quickQuery(query) {
    // Ensure chatbot is expanded first
    if (!isExpanded) {
        toggleChatbot();
        // Wait for expansion animation
        setTimeout(() => {
            document.getElementById('chatbot-input').value = query;
            sendMessage();
        }, 500);
    } else {
        document.getElementById('chatbot-input').value = query;
        sendMessage();
    }
}

async function sendMessage() {
    const input = document.getElementById('chatbot-input');
    const query = input.value.trim();
    
    if (!query || isTyping) return;
    
    // Add to chat history
    chatHistory.push({ type: 'user', message: query, timestamp: new Date() });
    
    // Show user message
    addMessage(query, 'user');
    input.value = '';
    
    // Show typing indicator
    showTyping();
    isTyping = true;
    
    // Send to backend
    try {
        const response = await fetch('ai/chatbot_engine.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                query,
                history: chatHistory.slice(-5) // Send last 5 messages for context
            })
        });
        
        const data = await response.json();
        
        // Add to chat history
        chatHistory.push({ type: 'bot', message: data.response, timestamp: new Date() });
        
        // Simulate realistic typing delay based on response length
        const typingDelay = Math.min(Math.max(data.response.length * 20, 800), 3000);
        
        setTimeout(() => {
            hideTyping();
            addMessage(data.response, 'bot');
            isTyping = false;
            
            // Auto-suggest follow-up actions based on response type
            if (data.suggestions && data.suggestions.length > 0) {
                showSmartSuggestions(data.suggestions);
            } else {
                suggestFollowUp(query, data.response);
            }
        }, typingDelay);
    } catch (error) {
        hideTyping();
        isTyping = false;
        addMessage('Sorry, I encountered an error. Please try again. 🔧', 'bot');
        console.error('Chatbot error:', error);
    }
}

function showTyping() {
    document.getElementById('typing-indicator').style.display = 'flex';
    const messagesDiv = document.getElementById('chatbot-messages');
    messagesDiv.scrollTop = messagesDiv.scrollHeight;
}

function hideTyping() {
    document.getElementById('typing-indicator').style.display = 'none';
}

function addMessage(text, type) {
    const messagesDiv = document.getElementById('chatbot-messages');
    const messageDiv = document.createElement('div');
    messageDiv.className = type === 'user' ? 'user-message' : 'ai-message';
    
    if (type === 'bot') {
        const avatar = document.createElement('div');
        avatar.className = 'message-avatar';
        avatar.innerHTML = '<i class="bi bi-cpu"></i>';
        messageDiv.appendChild(avatar);
        
        const contentDiv = document.createElement('div');
        contentDiv.className = 'message-content';
        
        const bubbleDiv = document.createElement('div');
        bubbleDiv.className = 'message-bubble ai-bubble';
        bubbleDiv.innerHTML = formatBotMessage(text);
        contentDiv.appendChild(bubbleDiv);
        
        messageDiv.appendChild(contentDiv);
        
        // Add timestamp
        const timeDiv = document.createElement('div');
        timeDiv.className = 'message-time';
        timeDiv.style.cssText = 'font-size: 11px; color: #94a3b8; margin-top: 4px; text-align: left;';
        timeDiv.textContent = new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
        contentDiv.appendChild(timeDiv);
    } else {
        const avatar = document.createElement('div');
        avatar.className = 'message-avatar';
        avatar.innerHTML = '<i class="bi bi-person-fill"></i>';
        messageDiv.appendChild(avatar);
        
        const contentDiv = document.createElement('div');
        contentDiv.className = 'message-content';
        
        const bubbleDiv = document.createElement('div');
        bubbleDiv.className = 'message-bubble user-bubble';
        bubbleDiv.textContent = text;
        contentDiv.appendChild(bubbleDiv);
        
        messageDiv.appendChild(contentDiv);
        
        // Add timestamp
        const timeDiv = document.createElement('div');
        timeDiv.className = 'message-time';
        timeDiv.style.cssText = 'font-size: 11px; color: rgba(255,255,255,0.8); margin-top: 4px; text-align: right;';
        timeDiv.textContent = new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
        contentDiv.appendChild(timeDiv);
    }
    
    messagesDiv.appendChild(messageDiv);
    messagesDiv.scrollTop = messagesDiv.scrollHeight;
    
    // Add entrance animation
    messageDiv.style.opacity = '0';
    messageDiv.style.transform = 'translateY(10px)';
    setTimeout(() => {
        messageDiv.style.transition = 'all 0.3s ease';
        messageDiv.style.opacity = '1';
        messageDiv.style.transform = 'translateY(0)';
    }, 50);
}

function formatBotMessage(text) {
    // Convert line breaks to <br>
    text = text.replace(/\n/g, '<br>');
    
    // Convert **bold** to <strong>
    text = text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
    
    // Convert bullet points to styled list
    if (text.includes('•')) {
        const lines = text.split('<br>');
        let formatted = '';
        let inList = false;
        
        lines.forEach(line => {
            if (line.trim().startsWith('•')) {
                if (!inList) {
                    formatted += '<ul style="margin: 8px 0; padding-left: 20px; list-style: none;">';
                    inList = true;
                }
                formatted += '<li style="margin: 4px 0; position: relative; padding-left: 16px;"><span style="position: absolute; left: 0; color: #667eea;">•</span>' + line.replace('•', '').trim() + '</li>';
            } else {
                if (inList) {
                    formatted += '</ul>';
                    inList = false;
                }
                formatted += line + '<br>';
            }
        });
        
        if (inList) formatted += '</ul>';
        return formatted;
    }
    
    // Add action buttons for certain responses
    if (text.includes('reservation code:') || text.includes('RES-')) {
        const reservationMatch = text.match(/RES-[\w\-]+/);
        if (reservationMatch) {
            text += `<div style="margin-top: 12px;">
                <button onclick="quickQuery('my reservations')" style="
                    background: linear-gradient(135deg, #3b82f6, #2563eb);
                    color: white;
                    border: none;
                    border-radius: 12px;
                    padding: 8px 16px;
                    font-size: 12px;
                    cursor: pointer;
                    transition: all 0.3s ease;
                " onmouseover="this.style.transform='translateY(-1px)'" onmouseout="this.style.transform='translateY(0)'">
                    <i class="bi bi-list"></i> View All Reservations
                </button>
            </div>`;
        }
    }
    
    return text;
}

function showSmartSuggestions(suggestions) {
    setTimeout(() => {
        const messagesDiv = document.getElementById('chatbot-messages');
        const suggestionDiv = document.createElement('div');
        suggestionDiv.className = 'input-suggestions';
        suggestionDiv.style.cssText = 'margin: 16px 24px; padding: 0;';
        suggestionDiv.innerHTML = `
            <div style="font-size: 12px; color: #64748b; margin-bottom: 8px;">
                <i class="bi bi-lightbulb"></i> Smart suggestions:
            </div>
            ${suggestions.map(s => `<button onclick="quickQuery('${s}')" class="suggestion-chip">${s}</button>`).join('')}
        `;
        messagesDiv.appendChild(suggestionDiv);
        messagesDiv.scrollTop = messagesDiv.scrollHeight;
    }, 1000);
}

function suggestFollowUp(userQuery, botResponse) {
    // Smart follow-up suggestions based on context
    const suggestions = [];
    
    if (userQuery.toLowerCase().includes('low stock')) {
        suggestions.push('Show me suppliers for these products');
        suggestions.push('What are the reorder levels?');
    } else if (userQuery.toLowerCase().includes('top selling')) {
        suggestions.push('Show recent sales');
        suggestions.push('What categories sell best?');
    } else if (botResponse.includes('reservation code')) {
        suggestions.push('My reservations');
        suggestions.push('Cancel a reservation');
    } else if (userQuery.toLowerCase().includes('search')) {
        suggestions.push('Check stock levels');
        suggestions.push('Reserve this product');
    }
    
    if (suggestions.length > 0) {
        setTimeout(() => {
            const messagesDiv = document.getElementById('chatbot-messages');
            const suggestionDiv = document.createElement('div');
            suggestionDiv.className = 'input-suggestions';
            suggestionDiv.style.cssText = 'margin: 16px 24px; padding: 0;';
            suggestionDiv.innerHTML = `
                <div style="font-size: 12px; color: #64748b; margin-bottom: 8px;">
                    <i class="bi bi-lightbulb"></i> You might also want to:
                </div>
                ${suggestions.map(s => `<button onclick="quickQuery('${s}')" class="suggestion-chip">${s}</button>`).join('')}
            `;
            messagesDiv.appendChild(suggestionDiv);
            messagesDiv.scrollTop = messagesDiv.scrollHeight;
        }, 1000);
    }
}

function clearChat() {
    if (confirm('Clear all chat messages?')) {
        const messagesDiv = document.getElementById('chatbot-messages');
        // Keep only the welcome message
        const welcomeMessage = messagesDiv.querySelector('.message-group');
        messagesDiv.innerHTML = '';
        if (welcomeMessage) {
            messagesDiv.appendChild(welcomeMessage);
        }
        chatHistory = [];
    }
}

// Enhanced keyboard shortcuts and accessibility
document.addEventListener('keydown', function(e) {
    const input = document.getElementById('chatbot-input');
    
    // Ctrl/Cmd + K to open chatbot
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        if (!isExpanded) {
            toggleChatbot();
        }
        if (input) input.focus();
    }
    
    // Escape to close chatbot
    if (e.key === 'Escape') {
        if (isExpanded) {
            toggleChatbot();
        }
    }
    
    // Arrow up to recall last message
    if (e.key === 'ArrowUp' && input === document.activeElement && input.value === '') {
        const lastUserMessage = chatHistory.filter(msg => msg.type === 'user').pop();
        if (lastUserMessage) {
            input.value = lastUserMessage.message;
            e.preventDefault();
        }
    }
    
    // Ctrl + Enter to send message
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter' && input === document.activeElement) {
        sendMessage();
        e.preventDefault();
    }
});

// Initialize chatbot
document.addEventListener('DOMContentLoaded', function() {
    const fab = document.getElementById('chatbot-fab');
    const container = document.getElementById('chatbot-container');
    
    // Ensure initial state is collapsed
    if (fab && container) {
        fab.classList.remove('active');
        container.classList.remove('active');
        isExpanded = false;
    }
    
    // Add input suggestions
    const input = document.getElementById('chatbot-input');
    if (input) {
        const suggestions = [
            'Show low stock products',
            'What are today\'s sales?',
            'Search for laptops',
            'Top selling products',
            'My reservations',
            'Inventory value',
            'Out of stock items'
        ];
        
        let currentSuggestion = 0;
        input.addEventListener('focus', function() {
            if (!this.value) {
                this.placeholder = suggestions[currentSuggestion];
                currentSuggestion = (currentSuggestion + 1) % suggestions.length;
            }
        });
        
        // Add dynamic suggestions
        input.addEventListener('input', function() {
            const value = this.value.toLowerCase();
            const suggestionsContainer = document.getElementById('input-suggestions');
            
            if (value.length > 2) {
                const matchingSuggestions = suggestions.filter(s => 
                    s.toLowerCase().includes(value) && s.toLowerCase() !== value
                ).slice(0, 3);
                
                if (matchingSuggestions.length > 0) {
                    suggestionsContainer.innerHTML = matchingSuggestions
                        .map(s => `<button onclick="quickQuery('${s}')" class="suggestion-chip">${s}</button>`)
                        .join('');
                } else {
                    suggestionsContainer.innerHTML = '';
                }
            } else {
                suggestionsContainer.innerHTML = '';
            }
        });
    }
    
    // Monitor connection status
    function updateConnectionStatus(isOnline) {
        const statusElement = document.querySelector('.ai-status');
        if (statusElement) {
            const indicator = statusElement.querySelector('.status-indicator');
            if (isOnline) {
                statusElement.innerHTML = '<div class="status-indicator"></div>AI Online';
                if (indicator) indicator.style.background = '#4ade80';
            } else {
                statusElement.innerHTML = '<div class="status-indicator" style="background: #ef4444;"></div>AI Offline';
            }
        }
    }
    
    // Check connection periodically
    let connectionCheckInterval = setInterval(() => {
        fetch('ai/chatbot_engine.php', { 
            method: 'POST', 
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ query: 'ping' })
        })
        .then(response => response.ok ? updateConnectionStatus(true) : updateConnectionStatus(false))
        .catch(() => updateConnectionStatus(false));
    }, 30000); // Check every 30 seconds
    
    // Cleanup on page unload
    window.addEventListener('beforeunload', function() {
        if (connectionCheckInterval) {
            clearInterval(connectionCheckInterval);
        }
    });
});

})(); // End of IIFE