<?php
// Database setup script
// This creates the database and tables needed for the inventory system

$host = "localhost";
$username = "root";
$password = "";
$database_name = "inventory_db";

try {
    // Connect to MySQL server (without selecting a database first)
    $conn = new PDO("mysql:host=$host", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>Setting up Inventory System Database...</h2>";
    
    // Create the database
    $conn->exec("CREATE DATABASE IF NOT EXISTS $database_name");
    echo "<p>✓ Database '$database_name' created</p>";
    
    // Now connect to the specific database
    $conn = new PDO("mysql:host=$host;dbname=$database_name", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create users table
    $conn->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role ENUM('admin', 'staff') DEFAULT 'staff',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "<p>✓ Users table created</p>";
    
    // Create categories table
    $conn->exec("CREATE TABLE IF NOT EXISTS categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT
    )");
    echo "<p>✓ Categories table created</p>";
    
    // Create suppliers table
    $conn->exec("CREATE TABLE IF NOT EXISTS suppliers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        contact_person VARCHAR(100),
        email VARCHAR(100),
        phone VARCHAR(20),
        address TEXT
    )");
    echo "<p>✓ Suppliers table created</p>";
    
    // Create products table
    $conn->exec("CREATE TABLE IF NOT EXISTS products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_name VARCHAR(200) NOT NULL,
        category_id INT,
        supplier_id INT,
        price DECIMAL(10,2) NOT NULL,
        stock_quantity INT DEFAULT 0,
        reorder_level INT DEFAULT 10,
        barcode VARCHAR(100),
        image VARCHAR(255),
        date_added TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (category_id) REFERENCES categories(id),
        FOREIGN KEY (supplier_id) REFERENCES suppliers(id)
    )");
    echo "<p>✓ Products table created</p>";
    
    // Create sales table
    $conn->exec("CREATE TABLE IF NOT EXISTS sales (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        quantity INT NOT NULL,
        total_price DECIMAL(10,2) NOT NULL,
        sale_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(id)
    )");
    echo "<p>✓ Sales table created</p>";
    
    // Create inventory logs table
    $conn->exec("CREATE TABLE IF NOT EXISTS inventory_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        action ENUM('stock_in', 'stock_out', 'adjustment', 'reservation') NOT NULL,
        quantity INT NOT NULL,
        user_id INT,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(id),
        FOREIGN KEY (user_id) REFERENCES users(id)
    )");
    echo "<p>✓ Inventory logs table created</p>";
    
    // Create forecast data table
    $conn->exec("CREATE TABLE IF NOT EXISTS forecast_data (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        avg_daily_sales DECIMAL(10,2),
        forecast_weekly DECIMAL(10,2),
        forecast_monthly DECIMAL(10,2),
        predicted_depletion_days INT,
        reorder_suggestion INT,
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(id)
    )");
    echo "<p>✓ Forecast data table created</p>";
    
    // Create reservations table
    $conn->exec("CREATE TABLE IF NOT EXISTS reservations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        reservation_id VARCHAR(50) UNIQUE,
        product_id INT NOT NULL,
        quantity INT NOT NULL,
        customer_name VARCHAR(100),
        notes TEXT,
        status ENUM('active', 'completed', 'cancelled') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        created_by INT,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    )");
    echo "<p>✓ Reservations table created</p>";
    
    // Add default admin user
    $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
    $conn->exec("INSERT IGNORE INTO users (id, username, password, role) VALUES 
        (1, 'admin', '$admin_password', 'admin')");
    echo "<p>✓ Default admin user created (username: admin, password: admin123)</p>";
    
    // Add sample categories
    $conn->exec("INSERT IGNORE INTO categories (id, name, description) VALUES 
        (1, 'Electronics', 'Electronic devices and gadgets'),
        (2, 'Office Supplies', 'Office equipment and supplies'),
        (3, 'Hardware', 'Computer hardware and accessories'),
        (4, 'Accessories', 'Various accessories and add-ons')");
    echo "<p>✓ Sample categories added</p>";
    
    // Add sample suppliers
    $conn->exec("INSERT IGNORE INTO suppliers (id, name, contact_person, email, phone) VALUES 
        (1, 'Tech Solutions Inc', 'John Smith', 'john@techsolutions.com', '555-0101'),
        (2, 'Office World', 'Sarah Johnson', 'sarah@officeworld.com', '555-0102'),
        (3, 'Hardware Plus', 'Mike Wilson', 'mike@hardwareplus.com', '555-0103')");
    echo "<p>✓ Sample suppliers added</p>";
    
    // Add sample products
    $conn->exec("INSERT IGNORE INTO products (id, product_name, category_id, supplier_id, price, stock_quantity, reorder_level, barcode) VALUES 
        (1, 'Wireless Mouse', 1, 1, 25.99, 50, 10, 'WM001'),
        (2, 'USB Keyboard', 1, 1, 45.00, 30, 5, 'KB001'),
        (3, 'Office Chair', 2, 2, 150.00, 15, 3, 'OC001'),
        (4, 'Desk Lamp', 2, 2, 35.50, 25, 5, 'DL001'),
        (5, 'Printer Paper', 2, 2, 12.99, 100, 20, 'PP001')");
    echo "<p>✓ Sample products added</p>";
    
    // Add some sample sales data
    $conn->exec("INSERT IGNORE INTO sales (product_id, quantity, total_price, sale_date) VALUES 
        (1, 2, 51.98, DATE_SUB(NOW(), INTERVAL 1 DAY)),
        (2, 1, 45.00, DATE_SUB(NOW(), INTERVAL 2 DAY)),
        (3, 1, 150.00, DATE_SUB(NOW(), INTERVAL 3 DAY)),
        (4, 3, 106.50, DATE_SUB(NOW(), INTERVAL 4 DAY)),
        (5, 5, 64.95, DATE_SUB(NOW(), INTERVAL 5 DAY))");
    echo "<p>✓ Sample sales data added</p>";
    
    echo "<h3 style='color: green;'>✓ Setup Complete!</h3>";
    echo "<p>Your inventory system is ready to use!</p>";
    echo "<p><a href='login.php' style='padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px;'>Go to Login Page</a></p>";
    
} catch(PDOException $e) {
    echo "<h3 style='color: red;'>Setup Error: " . $e->getMessage() . "</h3>";
    echo "<p>Please check your database connection settings and try again.</p>";
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Database Setup</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        h2 { color: #333; }
        p { margin: 5px 0; }
    </style>
</head>
<body>
    <h1>Inventory System Setup</h1>
    <p>This script sets up the database and creates sample data for the inventory management system.</p>
</body>
</html>