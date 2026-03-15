# Inventory Management System

Just another inventory system I threw together for my web dev class. Started basic but kinda went overboard with features lol.

## What it actually does now

Manages inventory stuff but with way more features than I originally planned. Got carried away and added AI forecasting, profit tracking, modern chatbot, and a bunch of other stuff.

- Add/edit products with profit calculations
- Track stock levels with smart forecasting
- Make reservations with booking codes
- Advanced reports and analytics
- AI-powered chatbot that actually works pretty well
- Glassmorphism UI because why not
- Mobile responsive (finally)

## Requirements

- PHP 7.4+ (because I used some newer syntax)
- MySQL/MariaDB 
- Apache or nginx or whatever
- XAMPP if you're doing this locally like everyone else
- Modern browser (the CSS uses some fancy stuff)

## Installation

1. Clone this mess: `git clone [repo-url]`
2. Dump it in your htdocs folder
3. Run `php setup_advanced_forecasting.php` first (important!)
4. Then go to `http://localhost/inventory-system/`
5. Login with admin/admin123
6. Test the chatbot with `test_chatbot.php`

If stuff breaks, check `config/database.php` and make sure MySQL is actually running.

## Usage

### Login Stuff
- Username: admin
- Password: admin123
- (yeah I know, super secure)

### Products & Profit Tracking
1. Products page has cost price and selling price now
2. Automatically calculates profit margins
3. Color-coded profit indicators (red = bad, green = good)
4. Profit performance dashboard in user management

### AI Forecasting (the cool part)
- Automatically predicts when you'll run out of stock
- Uses trend analysis and seasonal patterns
- Risk assessment (critical/high/medium/low)
- Smart reorder suggestions
- Revenue forecasting

### Modern Chatbot
Click the floating button (bottom right) and try:
- "check stock laptop"
- "reserve 5 units of product 123"
- "show low stock products"
- "top selling products"
- "my reservations"
- "today's sales report"
- "help" (shows all commands)

The chatbot actually understands context now and has a glassmorphism UI that looks pretty sick.

### Reports & Analytics
- Sales reports with profit analysis
- Advanced forecasting dashboard
- Low stock predictions
- Revenue forecasting
- CSV exports still work

## File Structure (updated)

```
inventory-system/
├── config/                    - database and session stuff
├── includes/                  - navbar, sidebar, chatbot UI
├── ai/                       - the smart stuff
│   ├── chatbot_engine.php    - chatbot backend (way better now)
│   └── forecasting.php       - AI forecasting algorithms
├── css/js/                   - styles and scripts (glassmorphism!)
├── uploads/                  - product images
├── api/                      - API endpoints
├── logs/                     - error logs
├── dashboard.php             - main dashboard (redesigned)
├── products.php              - product management (with profits)
├── inventory.php             - stock management
├── forecast.php              - AI forecasting page
├── user_management.php       - profit performance dashboard
├── notifications.php         - notification center
├── reports.php               - advanced reports
├── setup_advanced_forecasting.php - database setup
├── test_chatbot.php          - chatbot testing page
└── AI_CHATBOT_FEATURES.md    - documentation (actually useful)
```

## Database (way more tables now)

Setup creates these tables:
- products (added cost_price column)
- categories, suppliers, users
- sales, reservations, inventory_logs
- **forecast_data_advanced** - AI forecasting data
- **notifications** - notification system

## New Features I Added

### AI Forecasting Engine
- Linear regression for trend analysis
- Seasonal pattern recognition
- Volatility calculations
- Risk level assessment
- Demand pattern classification
- Smart reorder suggestions with safety stock

### Modern Chatbot Interface
- Glassmorphism design (looks fancy)
- Floating action button
- Quick action cards
- Typing indicators
- Message timestamps
- Context-aware responses
- Mobile responsive

### Profit Tracking System
- Cost price vs selling price
- Automatic profit calculations
- Margin percentages with color coding
- Profit performance analytics
- Revenue forecasting

### UI/UX Improvements
- Clean white background (no more crazy gradients)
- Smooth animations everywhere
- Mobile-first responsive design
- Better typography and spacing
- Notification system
- Loading states and transitions

## Chatbot Commands (comprehensive list)

**Stock Management:**
- `check stock [product name/ID]`
- `search [term]`
- `how many [product]`
- `show low stock products`
- `out of stock items`

**Reservations:**
- `reserve [ID] [quantity]`
- `my reservations`
- `cancel [RES-ID]`

**Analytics:**
- `top selling products`
- `today's sales report`
- `inventory value`
- `recent sales`
- `categories`

**Forecasting:**
- `forecast`
- `predict stock depletion`

## Testing

Use `test_chatbot.php` to check if everything works:
- Tests database connections
- Validates AI systems
- Checks chatbot functionality
- Shows system status

## Troubleshooting

**Chatbot not working?**
- Run `test_ai_systems.php` to check status
- Make sure you ran the setup script
- Check browser console for JS errors

**Forecasting showing no data?**
- Need some sales data first
- Run `setup_advanced_forecasting.php`
- Add some sample products and sales

**Profit calculations wrong?**
- Make sure cost_price column exists
- Update products with cost prices
- Check the profit calculation formulas

**UI looks broken?**
- Clear browser cache
- Check if CSS file loads properly
- Make sure you're using a modern browser

## Performance Notes

- Forecasting updates on-demand (not automatic)
- Chatbot responses cached for better speed
- Images optimized for web
- Database queries optimized with indexes
- Mobile-first CSS for faster loading

## Code Quality

Look, I know the code isn't perfect. Started as a simple CRUD app and kept adding features. Some parts could be refactored but it works and that's what matters for a school project.

The AI stuff is actually pretty solid though - spent way too much time on the forecasting algorithms.

## What I Learned

- PHP isn't that bad once you get used to it
- CSS animations are addictive
- AI forecasting is harder than it looks
- Glassmorphism is overused but looks cool
- Mobile-first design actually makes sense
- Documentation is important (who knew?)

## Future Improvements (if I ever come back to this)

- Real-time notifications with WebSockets
- Better AI with machine learning
- Multi-user support with roles
- API for mobile app
- Better error handling
- Unit tests (lol probably not)

## License

MIT or whatever. Do what you want with it. Just don't blame me if it breaks your production server.

---

Built with PHP, MySQL, and way too much caffeine. 
Took 3x longer than expected but learned a ton.

*PS: The chatbot is actually pretty smart now, try asking it complex questions.*
