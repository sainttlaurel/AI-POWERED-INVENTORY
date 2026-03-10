<div id="chatbot-widget" class="chatbot-widget">
    <div class="chatbot-header" onclick="toggleChatbot()">
        <i class="bi bi-robot"></i> Inventory Assistant
        <span class="chatbot-toggle">−</span>
    </div>
    <div class="chatbot-body" id="chatbot-body">
        <div class="chatbot-messages" id="chatbot-messages">
            <div class="bot-message">Hi! I'm your inventory assistant. Ask me about stock levels, forecasts, or top products!</div>
        </div>
        <div class="chatbot-input">
            <input type="text" id="chatbot-input" placeholder="Ask me anything..." onkeypress="if(event.key==='Enter') sendMessage()">
            <button onclick="sendMessage()"><i class="bi bi-send"></i></button>
        </div>
    </div>
</div>
