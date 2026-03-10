# Inventory Management System

Just a basic inventory system I made for my web dev project. It works I guess.

## What it does

Manages inventory stuff. You can add products, check stock, make reservations, whatever. Has a chatbot too but it's pretty basic.

- Add/edit products 
- Track stock levels
- Make reservations for customers
- Some reports and charts
- Basic chatbot for queries

## Requirements

- PHP (7.4+)
- MySQL 
- Apache or whatever web server
- XAMPP if you're doing this locally like me

## Installation

1. Download/clone this repo
2. Put it in your htdocs folder or wherever
3. Go to `http://localhost/inventory-system/setup.php`
4. It should create the database automatically
5. Login with admin/admin123

That's it. If it doesn't work, check your database settings in `config/database.php`.

## Usage

### Login
- Username: admin
- Password: admin123

### Adding Products
1. Go to Products page
2. Click Add Product
3. Fill out the form
4. Submit

### Stock Management
- Use the Inventory page to add/remove stock
- Everything gets logged automatically

### Reservations
You can make reservations 3 ways:
1. Click Reserve on products page
2. Go to product details and click Create Reservation  
3. Use chatbot commands like "reserve 5" (for product ID 5)

### Chatbot
Click the chat bubble and try:
- "check stock laptop"
- "reserve 3 2" (reserve 2 units of product 3)
- "what's low in stock"
- "my reservations"

### Reports
Reports page has sales reports and stuff. You can print or export to CSV.

## File Structure

```
inventory-system/
├── config/          - database stuff
├── includes/        - header, sidebar, etc
├── ai/             - chatbot and forecasting
├── css/js/         - styles and scripts  
├── uploads/        - product images
├── dashboard.php   - main page
├── products.php    - manage products
├── inventory.php   - stock management
├── reservations.php - view reservations
├── reports.php     - reports and exports
└── login.php       - login page
```

## Database

The setup script creates these tables:
- products
- categories  
- suppliers
- sales
- reservations
- inventory_logs
- users

## Features

### Dashboard
Shows stats and charts. Has cards for total products, low stock alerts, today's sales, etc.

### Products
- CRUD operations
- Image uploads
- Categories and suppliers
- Stock level tracking

### Reservations  
- Create from multiple places
- Track customer info
- Automatic stock updates
- Cancel/complete reservations

### Reports
- Sales reports with date filters
- Stock reports
- Low stock alerts
- CSV export

### Chatbot
Basic chatbot that can:
- Check stock levels
- Create reservations
- Show low stock items
- Display sales info

## Troubleshooting

**Can't login?**
- Try admin/admin123
- Make sure you ran setup.php first

**Database errors?**
- Check if MySQL is running
- Verify database settings in config/database.php

**Images not uploading?**
- Check uploads folder exists and has permissions

**Chatbot not working?**
- Check browser console for errors
- Make sure database connection works

## Notes

This was made for a just project so it's not perfect. Some things could be better but it works for what it needs to do.

The forecasting is pretty basic - just calculates averages and estimates. The chatbot uses simple pattern matching, nothing fancy.

If you want to modify it, the main files are:
- `dashboard.php` - main overview
- `products.php` - product management  
- `ai/chatbot_engine.php` - chatbot logic
- `css/style.css` - styling

## License

Do whatever you want with it. It's just a project.

---

Made with PHP and MySQL. Took way longer than it should have.

