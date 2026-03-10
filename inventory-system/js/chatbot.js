function toggleChatbot() {
    const body = document.getElementById('chatbot-body');
    const toggle = document.querySelector('.chatbot-toggle');
    
    body.classList.toggle('collapsed');
    toggle.textContent = body.classList.contains('collapsed') ? '+' : '−';
}

async function sendMessage() {
    const input = document.getElementById('chatbot-input');
    const query = input.value.trim();
    
    if (!query) return;
    
    // Show user message
    addMessage(query, 'user');
    input.value = '';
    
    // Send to backend
    try {
        const response = await fetch('ai/chatbot_engine.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ query })
        });
        
        const data = await response.json();
        addMessage(data.response, 'bot');
    } catch (error) {
        addMessage('Sorry, I encountered an error. Please try again.', 'bot');
    }
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
        textDiv.textContent = text;
        messageDiv.appendChild(textDiv);
    } else {
        messageDiv.textContent = text;
    }
    
    messagesDiv.appendChild(messageDiv);
    messagesDiv.scrollTop = messagesDiv.scrollHeight;
}
