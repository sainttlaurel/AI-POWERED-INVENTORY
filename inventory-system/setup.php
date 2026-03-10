<?php
$host = "localhost";
$username = "root";
$password = "";

try {
    $conn = new PDO("mysql:host=$host", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>Setting up Inventory Database...</h2>";
    
    $conn->exec("CREATE DATABASE IF NOT EXISTS inventory_db");
    echo "<p>✓ Database 'inventory_db' created</p>";
    
    $conn->exec("USE inventory_db");
    
    $conn->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role ENUM('admin', 'staff') DEFAULT 'staff',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "<p>✓ Table 'users' created</p>";
    
    $conn->exec("CREATE TABLE IF NOT EXISTS categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "<p>✓ Table 'categories' created</p>";
    
    $conn->exec("CREATE TABLE IF NOT EXISTS suppliers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        contact_person VARCHAR(100),
        email VARCHAR(100),
        phone VARCHAR(20),
        address TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "<p>✓ Table 'suppliers' created</p>";
    
    $conn->exec("CREATE TABLE IF NOT EXISTS products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_name VARCHAR(200) NOT NULL,
        category_id INT,
        supplier_id INT,
        price DECIMAL(10,2) NOT NULL,
        stock_quantity INT DEFAULT 0,
        reorder_level INT DEFAULT 10,
        barcode VARCHAR(100) UNIQUE,
        image VARCHAR(255),
        date_added TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
        FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL
    )");
    echo "<p>✓ Table 'products' created</p>";


    $conn->exec("CREATE TABLE IF NOT EXISTS sales (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        quantity INT NOT NULL,
        total_price DECIMAL(10,2) NOT NULL,
        sale_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    )");
    echo "<p>✓ Table 'sales' created</p>";
    
    $conn->exec("CREATE TABLE IF NOT EXISTS inventory_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        action ENUM('stock_in', 'stock_out') NOT NULL,
        quantity INT NOT NULL,
        user_id INT,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    )");
    echo "<p>✓ Table 'inventory_logs' created</p>";
    
    $conn->exec("CREATE TABLE IF NOT EXISTS forecast_data (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        avg_daily_sales DECIMAL(10,2),
        forecast_weekly DECIMAL(10,2),
        forecast_monthly DECIMAL(10,2),
        predicted_depletion_days INT,
        reorder_suggestion INT,
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
        UNIQUE KEY unique_product (product_id)
    )");
    echo "<p>✓ Table 'forecast_data' created</p>";
    
    $conn->exec("CREATE TABLE IF NOT EXISTS chatbot_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        query TEXT NOT NULL,
        response TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    )");
    echo "<p>✓ Table 'chatbot_logs' created</p>";
    
    $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $conn->exec("INSERT IGNORE INTO users (username, password, role) VALUES ('admin', '$hashedPassword', 'admin')");
    echo "<p>✓ Default admin user created</p>";
    
    $conn->exec("INSERT IGNORE INTO categories (id, name, description) VALUES 
        (1, 'Electronics', 'Electronic devices and accessories'),
        (2, 'Office Supplies', 'Office equipment and supplies'),
        (3, 'Hardware', 'Computer hardware components')");
    echo "<p>✓ Sample categories added</p>";
    
    $conn->exec("INSERT IGNORE INTO suppliers (id, name, contact_person, email, phone) VALUES 
        (1, 'Tech Supplies Inc', 'John Doe', 'john@techsupplies.com', '555-0100'),
        (2, 'Office World', 'Jane Smith', 'jane@officeworld.com', '555-0200')");
    echo "<p>✓ Sample suppliers added</p>";
    
    $conn->exec("INSERT IGNORE INTO products (id, product_name, category_id, supplier_id, price, stock_quantity, reorder_level, barcode) VALUES 
        (1, 'Wireless Mouse', 1, 1, 25.99, 50, 10, 'WM001'),
        (2, 'USB Cable', 1, 1, 5.99, 100, 20, 'USB001'),
        (3, 'Notebook A4', 2, 2, 3.50, 200, 30, 'NB001'),
        (4, 'Laptop Battery', 1, 1, 89.99, 15, 5, 'LB001'),
        (5, 'Printer Ink', 2, 2, 45.00, 8, 10, 'PI001')");
    echo "<p>✓ Sample products added</p>";
    
    $conn->exec("INSERT IGNORE INTO sales (product_id, quantity, total_price, sale_date) VALUES 
        (1, 5, 129.95, DATE_SUB(NOW(), INTERVAL 1 DAY)),
        (2, 10, 59.90, DATE_SUB(NOW(), INTERVAL 2 DAY)),
        (3, 15, 52.50, DATE_SUB(NOW(), INTERVAL 3 DAY)),
        (1, 3, 77.97, DATE_SUB(NOW(), INTERVAL 4 DAY)),
        (4, 2, 179.98, DATE_SUB(NOW(), INTERVAL 5 DAY))");
    echo "<p>✓ Sample sales data added</p>";
    
    echo "<h3 style='color: green;'>✓ Setup Complete!</h3>";
    echo "<p><a href='login.php' style='padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px;'>Go to Login Page</a></p>";
    
} catch(PDOException $e) {
    echo "<h3 style='color: red;'>Error: " . $e->getMessage() . "</h3>";
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Database Setup</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        p { padding: 5px; background: #f0f0f0; margin: 5px 0; border-radius: 3px; }
    </style>
</head>
<body>
</body>
</html>
