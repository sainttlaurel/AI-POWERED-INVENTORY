<div id="chatbot-widget" class="chatbot-widget">
    <!-- Circular Toggle Button (Default State) -->
    <div class="chatbot-toggle-btn" onclick="toggleChatbot()">
        <i class="bi bi-chat-dots"></i>
        <!-- Optional notification badge -->
        <!-- <div class="chatbot-notification">3</div> -->
    </div>
    
    <!-- Expanded Header (Hidden by default) -->
    <div class="chatbot-header" onclick="toggleChatbot()">
        <div>
            <i class="bi bi-chat-dots"></i> <span>Inventory Assistant</span>
            <span class="chatbot-status">Online</span>
        </div>
        <span class="chatbot-toggle">−</span>
    </div>
    
    <!-- Chat Body (Hidden by default) -->
    <div class="chatbot-body" id="chatbot-body">
        <div class="chatbot-messages" id="chatbot-messages">
            <div class="bot-message">
                <div class="bot-avatar">🤖</div>
                <div class="bot-content">
                    <strong>Hi there! 👋</strong>
                    <div>I'm your inventory assistant. I can help you with:</div>
                    <ul class="chatbot-suggestions">
                        <li onclick="quickQuery('low stock products')">📦 Low stock alerts</li>
                        <li onclick="quickQuery('top selling product')">🏆 Top sellers</li>
                        <li onclick="quickQuery('forecast demand')">📊 Demand forecasts</li>
                        <li onclick="quickQuery('out of stock')">❌ Out of stock items</li>
                        <li onclick="quickQuery('check stock')">🔍 Check stock</li>
                        <li onclick="quickQuery('search products')">🔎 Search products</li>
                        <li onclick="quickQuery('my reservations')">📋 My reservations</li>
                        <li onclick="quickQuery('suppliers list')">🏭 Suppliers</li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="chatbot-typing" id="chatbot-typing" style="display: none;">
            <div class="typing-indicator">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </div>
        <div class="chatbot-input">
            <input type="text" id="chatbot-input" placeholder="Ask me anything..." onkeypress="if(event.key==='Enter') sendMessage()">
            <button onclick="sendMessage()"><i class="bi bi-send-fill"></i></button>
        </div>
    </div>
</div>
