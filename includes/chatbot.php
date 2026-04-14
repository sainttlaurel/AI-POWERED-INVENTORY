<div id="chatbot-widget" class="chatbot-widget-modern">
    <!-- Floating Action Button -->
    <div class="chatbot-fab" onclick="toggleChatbot()" id="chatbot-fab">
        <div class="fab-icon">
            <i class="bi bi-robot"></i>
        </div>
        <div class="fab-pulse"></div>
    </div>
    
    <!-- Modern Chat Interface -->
    <div class="chatbot-container" id="chatbot-container">
        <!-- Header -->
        <div class="chatbot-header-modern">
            <div style="display:flex;align-items:center;flex:1;min-width:0;">
                <div class="ai-avatar">
                    <i class="bi bi-cpu-fill" style="color:white;font-size:17px;"></i>
                </div>
                <div class="ai-info">
                    <h4>Inventory Assistant</h4>
                    <span class="ai-status">
                        <div class="status-indicator"></div>
                        AI Online
                    </span>
                </div>
            </div>
            <div class="header-actions">
                <button class="action-btn" onclick="clearChat()" title="Clear Chat">
                    <i class="bi bi-arrow-clockwise"></i>
                </button>
                <button class="action-btn" onclick="toggleChatbot()" title="Close">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        </div>
        
        <!-- Messages Area -->
        <div class="chatbot-messages-modern" id="chatbot-messages">
            <!-- Welcome Message -->
            <div class="message-group">
                <div class="ai-message">
                    <div class="message-avatar">
                        <i class="bi bi-cpu"></i>
                    </div>
                    <div class="message-content">
                        <div class="message-bubble ai-bubble">
                            <div class="welcome-content">
                                <h5>👋 Hello! I'm your AI Inventory Assistant</h5>
                                <p>I can help you manage inventory, check stock levels, make reservations, and provide insights about your business.</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions Grid -->
                <div class="quick-actions-modern">
                    <div class="actions-header">
                        <i class="bi bi-lightning-charge"></i>
                        <span>Quick Actions</span>
                    </div>
                    <div class="actions-grid">
                        <button class="action-card" onclick="quickQuery('show low stock products')">
                            <div class="card-icon warning">
                                <i class="bi bi-exclamation-triangle"></i>
                            </div>
                            <div class="card-content">
                                <span class="card-title">Low Stock</span>
                                <span class="card-subtitle">Check alerts</span>
                            </div>
                        </button>
                        
                        <button class="action-card" onclick="quickQuery('top selling products')">
                            <div class="card-icon success">
                                <i class="bi bi-trophy"></i>
                            </div>
                            <div class="card-content">
                                <span class="card-title">Top Sellers</span>
                                <span class="card-subtitle">Best products</span>
                            </div>
                        </button>
                        
                        <button class="action-card" onclick="quickQuery('my reservations')">
                            <div class="card-icon info">
                                <i class="bi bi-bookmark-check"></i>
                            </div>
                            <div class="card-content">
                                <span class="card-title">Reservations</span>
                                <span class="card-subtitle">My bookings</span>
                            </div>
                        </button>
                        
                        <button class="action-card" onclick="quickQuery('inventory value')">
                            <div class="card-icon primary">
                                <i class="bi bi-currency-dollar"></i>
                            </div>
                            <div class="card-content">
                                <span class="card-title">Total Value</span>
                                <span class="card-subtitle">Stock worth</span>
                            </div>
                        </button>
                        
                        <button class="action-card" onclick="quickQuery('search products')">
                            <div class="card-icon secondary">
                                <i class="bi bi-search"></i>
                            </div>
                            <div class="card-content">
                                <span class="card-title">Search</span>
                                <span class="card-subtitle">Find products</span>
                            </div>
                        </button>
                        
                        <button class="action-card" onclick="quickQuery('today sales report')">
                            <div class="card-icon accent">
                                <i class="bi bi-graph-up"></i>
                            </div>
                            <div class="card-content">
                                <span class="card-title">Sales Today</span>
                                <span class="card-subtitle">Daily report</span>
                            </div>
                        </button>
                    </div>
                    
                    <div class="pro-tip">
                        <div class="tip-icon">
                            <i class="bi bi-lightbulb"></i>
                        </div>
                        <div class="tip-content">
                            <strong>Pro tip:</strong> Ask me anything like "How many laptops do we have?" or "Reserve 5 units of product 123"
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Typing Indicator -->
        <div class="typing-indicator-modern" id="typing-indicator" style="display: none;">
            <div class="message-avatar">
                <i class="bi bi-cpu"></i>
            </div>
            <div class="typing-animation">
                <div class="typing-dots">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
                <span class="typing-text">AI is thinking...</span>
            </div>
        </div>
        
        <!-- Input Area -->
        <div class="chatbot-input-modern">
            <div class="input-container">
                <input 
                    type="text" 
                    id="chatbot-input" 
                    placeholder="Ask me anything about your inventory..."
                    onkeypress="if(event.key==='Enter') sendMessage()"
                    autocomplete="off"
                >
                <button class="send-button" onclick="sendMessage()" id="send-button">
                    <i class="bi bi-send-fill"></i>
                </button>
            </div>
            <div class="input-suggestions" id="input-suggestions">
                <!-- Dynamic suggestions will appear here -->
            </div>
        </div>
    </div>
</div>
