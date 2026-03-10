function toggleChatbot() {
    const body = document.getElementById('chatbot-body');
    const toggle = document.querySelector('.chatbot-toggle');
    const widget = document.getElementById('chatbot-widget');
    const toggleBtn = document.querySelector('.chatbot-toggle-btn');
    const header = document.querySelector('.chatbot-header');
    
    // Check if currently expanded
    const isExpanded = widget.classList.contains('expanded');
    
    if (isExpanded) {
        // Collapse to circular button
        widget.classList.remove('expanded');
        body.style.display = 'none';
        header.style.display = 'none';
        toggleBtn.style.display = 'flex';
        
        // Update toggle icon
        toggleBtn.querySelector('i').className = 'bi bi-chat-dots';
        
        // Add collapse animation
        widget.style.animation = 'chatbotCollapse 0.4s cubic-bezier(0.4, 0, 0.2, 1)';
    } else {
        // Expand to full chat
        widget.classList.add('expanded');
        toggleBtn.style.display = 'none';
        header.style.display = 'flex';
        body.style.display = 'flex';
        
        // Update toggle icon
        toggle.textContent = '−';
        
        // Add expand animation
        widget.style.animation = 'chatbotExpand 0.4s cubic-bezier(0.4, 0, 0.2, 1)';
        
        // Auto-scroll to bottom
        setTimeout(() => {
            const messagesDiv = document.getElementById('chatbot-messages');
            messagesDiv.scrollTop = messagesDiv.scrollHeight;
        }, 100);
    }
    
    // Clear animation after completion
    setTimeout(() => {
        widget.style.animation = '';
    }, 400);
}

// Add CSS animations for expand/collapse
const chatbotAnimations = `
    @keyframes chatbotExpand {
        from {
            width: 60px;
            height: 60px;
            border-radius: 50%;
        }
        to {
            width: 320px;
            height: 480px;
            border-radius: 20px;
        }
    }
    
    @keyframes chatbotCollapse {
        from {
            width: 320px;
            height: 480px;
            border-radius: 20px;
        }
        to {
            width: 60px;
            height: 60px;
            border-radius: 50%;
        }
    }
`;

// Add the animations to the page
const style = document.createElement('style');
style.textContent = chatbotAnimations;
document.head.appendChild(style);

function quickQuery(query) {
    // Ensure chatbot is expanded first
    const widget = document.getElementById('chatbot-widget');
    if (!widget.classList.contains('expanded')) {
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
    
    if (!query) return;
    
    // Show user message
    addMessage(query, 'user');
    input.value = '';
    
    // Show typing indicator
    showTyping();
    
    // Send to backend
    try {
        const response = await fetch('ai/chatbot_engine.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ query })
        });
        
        const data = await response.json();
        
        // Simulate typing delay
        setTimeout(() => {
            hideTyping();
            addMessage(data.response, 'bot');
        }, 800);
    } catch (error) {
        hideTyping();
        addMessage('Sorry, I encountered an error. Please try again.', 'bot');
    }
}

function showTyping() {
    document.getElementById('chatbot-typing').style.display = 'block';
    const messagesDiv = document.getElementById('chatbot-messages');
    messagesDiv.scrollTop = messagesDiv.scrollHeight;
}

function hideTyping() {
    document.getElementById('chatbot-typing').style.display = 'none';
}

function addMessage(text, type) {
    const messagesDiv = document.getElementById('chatbot-messages');
    const messageDiv = document.createElement('div');
    messageDiv.className = type === 'user' ? 'user-message' : 'bot-message';
    
    if (type === 'bot') {
        const avatar = document.createElement('div');
        avatar.className = 'bot-avatar';
        avatar.textContent = '🤖';
        messageDiv.appendChild(avatar);
        
        const textDiv = document.createElement('div');
        textDiv.innerHTML = formatBotMessage(text);
        messageDiv.appendChild(textDiv);
    } else {
        messageDiv.textContent = text;
    }
    
    messagesDiv.appendChild(messageDiv);
    messagesDiv.scrollTop = messagesDiv.scrollHeight;
}

function formatBotMessage(text) {
    // Convert line breaks to <br>
    text = text.replace(/\n/g, '<br>');
    
    // Convert bullet points to styled list
    if (text.includes('•')) {
        const lines = text.split('<br>');
        let formatted = '';
        let inList = false;
        
        lines.forEach(line => {
            if (line.trim().startsWith('•')) {
                if (!inList) {
                    formatted += '<ul class="bot-list">';
                    inList = true;
                }
                formatted += '<li>' + line.replace('•', '').trim() + '</li>';
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
    
    return text;
}

// Initialize chatbot in collapsed state
document.addEventListener('DOMContentLoaded', function() {
    const widget = document.getElementById('chatbot-widget');
    const body = document.getElementById('chatbot-body');
    const header = document.querySelector('.chatbot-header');
    
    // Ensure initial state is collapsed
    body.style.display = 'none';
    header.style.display = 'none';
    
    // Add notification badge functionality (optional)
    function showNotificationBadge(count) {
        let badge = document.querySelector('.chatbot-notification');
        if (!badge) {
            badge = document.createElement('div');
            badge.className = 'chatbot-notification';
            document.querySelector('.chatbot-toggle-btn').appendChild(badge);
        }
        badge.textContent = count;
        badge.style.display = count > 0 ? 'flex' : 'none';
    }
    
    // Example: Show notification badge
    // showNotificationBadge(3);
    
    // Add click outside to close functionality
    document.addEventListener('click', function(event) {
        const widget = document.getElementById('chatbot-widget');
        const isClickInside = widget.contains(event.target);
        
        if (!isClickInside && widget.classList.contains('expanded')) {
            // Optional: Auto-close when clicking outside
            // toggleChatbot();
        }
    });
});
