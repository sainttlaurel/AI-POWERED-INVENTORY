<?php
// Connect to database
require_once '../config/database.php';

header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();

// Get the user's question
$input = json_decode(file_get_contents('php://input'), true);
$user_question = strtolower($input['query'] ?? '');

$bot_response = '';

// Check if user wants to look up a product
if (preg_match('/check\s+(stock|inventory|quantity)\s+(.+)/i', $user_question, $matches) || 
    preg_match('/how\s+many\s+(.+)/i', $user_question, $matches) ||
    preg_match('/stock\s+for\s+(.+)/i', $user_question, $matches)) {
    
    $search_for = trim($matches[count($matches) - 1]);
    
    // Look for the product in database
    $stmt = $db->prepare("SELECT id, product_name, stock_quantity, price, barcode 
                          FROM products 
                          WHERE id = :search 
                          OR barcode LIKE :search_like 
                          OR product_name LIKE :search_like 
                          LIMIT 1");
    $stmt->execute([
        ':search' => $search_for,
        ':search_like' => "%$search_for%"
    ]);
    $found_product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($found_product) {
        $bot_response = "📦 **Found it!**\n\n";
        $bot_response .= "**" . $found_product['product_name'] . "**\n";
        $bot_response .= "• ID: " . $found_product['id'] . "\n";
        $bot_response .= "• Barcode: " . $found_product['barcode'] . "\n";
        $bot_response .= "• Stock: **" . $found_product['stock_quantity'] . " units**\n";
        $bot_response .= "• Price: ₱" . number_format($found_product['price'], 2) . "\n\n";
        
        if ($found_product['stock_quantity'] > 0) {
            $bot_response .= "✅ In stock and ready to reserve!\n";
            $bot_response .= "Type: **reserve " . $found_product['id'] . "** to reserve 1 unit";
        } else {
            $bot_response .= "❌ Sorry, we're out of stock";
        }
    } else {
        $bot_response = "❌ Couldn't find that product. Try searching by:\n• Product ID number\n• Product name\n• Barcode";
    }
}
// Handle making reservations
elseif (preg_match('/reserve\s+(\d+)(?:\s+(\d+))?/i', $user_question, $matches)) {
    $product_id = $matches[1];
    $how_many = isset($matches[2]) ? (int)$matches[2] : 1;
    
    // Look up the product
    $stmt = $db->prepare("SELECT id, product_name, stock_quantity, price FROM products WHERE id = :id");
    $stmt->execute([':id' => $product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        $bot_response = "❌ Can't find product ID $product_id.";
    } elseif ($product['stock_quantity'] < $how_many) {
        $bot_response = "❌ **Not enough in stock**\n\n";
        $bot_response .= "**" . $product['product_name'] . "**\n";
        $bot_response .= "• You want: $how_many units\n";
        $bot_response .= "• We have: " . $product['stock_quantity'] . " units\n";
        $bot_response .= "• Max you can reserve: " . $product['stock_quantity'];
    } else {
        // Take the items out of stock
        $remaining_stock = $product['stock_quantity'] - $how_many;
        $stmt = $db->prepare("UPDATE products SET stock_quantity = :new_stock WHERE id = :id");
        $stmt->execute([':new_stock' => $remaining_stock, ':id' => $product_id]);
        
        // Keep track of what happened
        $stmt = $db->prepare("INSERT INTO inventory_logs (product_id, action, quantity, user_id, notes) 
                              VALUES (:pid, 'reservation', :qty, 1, 'Reserved via chatbot')");
        $stmt->execute([':pid' => $product_id, ':qty' => $how_many]);
        
        // Make a reservation record
        try {
            $reservation_code = 'RES-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $stmt = $db->prepare("INSERT INTO reservations (reservation_id, product_id, quantity, status, created_at) 
                                  VALUES (:res_id, :pid, :qty, 'active', NOW())");
            $stmt->execute([':res_id' => $reservation_code, ':pid' => $product_id, ':qty' => $how_many]);
        } catch (Exception $e) {
            // Maybe table doesn't exist, let's create it
            $db->exec("CREATE TABLE IF NOT EXISTS reservations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                reservation_id VARCHAR(50) UNIQUE,
                product_id INT,
                quantity INT,
                status ENUM('active', 'completed', 'cancelled') DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (product_id) REFERENCES products(id)
            )");
            
            // Try creating the reservation again
            $stmt = $db->prepare("INSERT INTO reservations (reservation_id, product_id, quantity, status, created_at) 
                                  VALUES (:res_id, :pid, :qty, 'active', NOW())");
            $stmt->execute([':res_id' => $reservation_code, ':pid' => $product_id, ':qty' => $how_many]);
        }
        
        $total_cost = $product['price'] * $how_many;
        
        $bot_response = "✅ **Reservation made!**\n\n";
        $bot_response .= "**" . $product['product_name'] . "**\n";
        $bot_response .= "• Reservation code: **$reservation_code**\n";
        $bot_response .= "• Reserved: $how_many units\n";
        $bot_response .= "• Total value: ₱" . number_format($total_cost, 2) . "\n";
        $bot_response .= "• Stock left: $remaining_stock units\n\n";
        $bot_response .= "📝 Save your reservation code for pickup!";
    }
}
// Check reservations
elseif (preg_match('/my\s+reservations?|check\s+reservation|reservation\s+status/i', $query)) {
    try {
        $stmt = $db->query("SELECT r.reservation_id, r.quantity, r.status, r.created_at, p.product_name, p.price 
                            FROM reservations r 
                            JOIN products p ON r.product_id = p.id 
                            WHERE r.status = 'active' 
                            ORDER BY r.created_at DESC 
                            LIMIT 10");
        $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($reservations) > 0) {
            $response = "📋 **Active Reservations:**\n\n";
            foreach ($reservations as $res) {
                $total = $res['price'] * $res['quantity'];
                $date = date('M d, Y H:i', strtotime($res['created_at']));
                $response .= "**" . $res['reservation_id'] . "**\n";
                $response .= "• " . $res['product_name'] . "\n";
                $response .= "• Quantity: " . $res['quantity'] . " units\n";
                $response .= "• Value: ₱" . number_format($total, 2) . "\n";
                $response .= "• Reserved: $date\n\n";
            }
            $response .= "Type: **cancel RES-ID** to cancel a reservation";
        } else {
            $response = "📋 No active reservations found.";
        }
    } catch (Exception $e) {
        // Table doesn't exist, create it
        $db->exec("CREATE TABLE IF NOT EXISTS reservations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            reservation_id VARCHAR(50) UNIQUE,
            product_id INT,
            quantity INT,
            status ENUM('active', 'completed', 'cancelled') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (product_id) REFERENCES products(id)
        )");
        $response = "📋 No active reservations found.";
    }
}
// Cancel reservation
elseif (preg_match('/cancel\s+(RES-[\w\-]+)/i', $query, $matches)) {
    $reservation_id = $matches[1];
    
    $stmt = $db->prepare("SELECT r.*, p.product_name FROM reservations r 
                          JOIN products p ON r.product_id = p.id 
                          WHERE r.reservation_id = :res_id AND r.status = 'active'");
    $stmt->execute([':res_id' => $reservation_id]);
    $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($reservation) {
        // Return stock
        $stmt = $db->prepare("UPDATE products SET stock_quantity = stock_quantity + :qty WHERE id = :pid");
        $stmt->execute([':qty' => $reservation['quantity'], ':pid' => $reservation['product_id']]);
        
        // Cancel reservation
        $stmt = $db->prepare("UPDATE reservations SET status = 'cancelled' WHERE reservation_id = :res_id");
        $stmt->execute([':res_id' => $reservation_id]);
        
        // Log the cancellation
        $stmt = $db->prepare("INSERT INTO inventory_logs (product_id, action, quantity, user_id, notes) 
                              VALUES (:pid, 'stock_in', :qty, 1, 'Reservation cancelled: $reservation_id')");
        $stmt->execute([':pid' => $reservation['product_id'], ':qty' => $reservation['quantity']]);
        
        $response = "✅ **Reservation Cancelled**\n\n";
        $response .= "**$reservation_id**\n";
        $response .= "• " . $reservation['product_name'] . "\n";
        $response .= "• " . $reservation['quantity'] . " units returned to stock\n";
        $response .= "• Status: Cancelled";
    } else {
        $response = "❌ Reservation $reservation_id not found or already processed.";
    }
}
// Search products
elseif (preg_match('/search\s+(.+)|find\s+(.+)/i', $query, $matches)) {
    $search_term = trim($matches[1] ?: $matches[2]);
    
    $stmt = $db->prepare("SELECT id, product_name, stock_quantity, price, barcode 
                          FROM products 
                          WHERE product_name LIKE :search 
                          OR barcode LIKE :search 
                          ORDER BY product_name 
                          LIMIT 5");
    $stmt->execute([':search' => "%$search_term%"]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($products) > 0) {
        $response = "🔍 **Search Results for '$search_term':**\n\n";
        foreach ($products as $p) {
            $status = $p['stock_quantity'] > 0 ? "✅ In Stock" : "❌ Out of Stock";
            $response .= "**" . $p['product_name'] . "**\n";
            $response .= "• ID: " . $p['id'] . " | Stock: " . $p['stock_quantity'] . "\n";
            $response .= "• Price: ₱" . number_format($p['price'], 2) . " | $status\n\n";
        }
        $response .= "Type: **check stock [ID]** for details\nType: **reserve [ID]** to reserve";
    } else {
        $response = "🔍 No products found matching '$search_term'";
    }
}

if (strpos($query, 'low stock') !== false || strpos($query, 'need restock') !== false) {
    $stmt = $db->query("SELECT product_name, stock_quantity, reorder_level FROM products WHERE stock_quantity <= reorder_level ORDER BY stock_quantity ASC LIMIT 5");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($products) > 0) {
        $response = "⚠️ **Low Stock Alert!**\n\nThese products need restocking:\n";
        foreach ($products as $p) {
            $shortage = $p['reorder_level'] - $p['stock_quantity'];
            $response .= "• " . $p['product_name'] . " – Only " . $p['stock_quantity'] . " left (Need " . $shortage . " more)\n";
        }
    } else {
        $response = "✅ Great news! All products are well stocked.";
    }
}
elseif (strpos($query, 'top selling') !== false || strpos($query, 'best seller') !== false) {
    $stmt = $db->query("SELECT p.product_name, SUM(s.quantity) as total, SUM(s.total_price) as revenue FROM sales s JOIN products p ON s.product_id = p.id GROUP BY s.product_id ORDER BY total DESC LIMIT 3");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($products) > 0) {
        $response = "🏆 **Top Selling Products:**\n\n";
        $rank = 1;
        foreach ($products as $p) {
            $medal = $rank == 1 ? '🥇' : ($rank == 2 ? '🥈' : '🥉');
            $response .= $medal . " " . $p['product_name'] . " – " . $p['total'] . " units (₱" . number_format($p['revenue'], 2) . ")\n";
            $rank++;
        }
    } else {
        $response = "No sales data available yet.";
    }
}
elseif (strpos($query, 'how many') !== false || strpos($query, 'total products') !== false) {
    $count = $db->query("SELECT COUNT(*) FROM products")->fetchColumn();
    $response = "There are currently " . $count . " products in the inventory.";
}
elseif (strpos($query, 'forecast') !== false || strpos($query, 'predict') !== false) {
    $stmt = $db->query("SELECT p.product_name, f.predicted_depletion_days, f.reorder_suggestion FROM forecast_data f JOIN products p ON f.product_id = p.id WHERE f.predicted_depletion_days <= 7 ORDER BY f.predicted_depletion_days ASC LIMIT 3");
    $forecasts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($forecasts) > 0) {
        $response = "Products predicted to run out soon:\n";
        foreach ($forecasts as $f) {
            $response .= "• " . $f['product_name'] . " – " . $f['predicted_depletion_days'] . " days left. Reorder " . $f['reorder_suggestion'] . " units.\n";
        }
    } else {
        $response = "No urgent restocking needed based on forecasts.";
    }
}
elseif (strpos($query, 'out of stock') !== false) {
    $stmt = $db->query("SELECT product_name FROM products WHERE stock_quantity = 0");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($products) > 0) {
        $response = "❌ **Out of Stock Products:**\n\n";
        foreach ($products as $p) {
            $response .= "• " . $p['product_name'] . "\n";
        }
    } else {
        $response = "✅ No products are out of stock.";
    }
}
elseif (strpos($query, 'recent sales') !== false || strpos($query, 'latest sales') !== false) {
    $stmt = $db->query("SELECT p.product_name, s.quantity, s.total_price, s.sale_date FROM sales s JOIN products p ON s.product_id = p.id ORDER BY s.sale_date DESC LIMIT 5");
    $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($sales) > 0) {
        $response = "💰 **Recent Sales:**\n\n";
        foreach ($sales as $s) {
            $date = date('M d, H:i', strtotime($s['sale_date']));
            $response .= "• " . $s['product_name'] . " – " . $s['quantity'] . " units (₱" . number_format($s['total_price'], 2) . ") on " . $date . "\n";
        }
    } else {
        $response = "No recent sales found.";
    }
}
elseif (strpos($query, 'categories') !== false || strpos($query, 'category') !== false) {
    $stmt = $db->query("SELECT c.name, COUNT(p.id) as product_count FROM categories c LEFT JOIN products p ON c.id = p.category_id GROUP BY c.id ORDER BY product_count DESC");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($categories) > 0) {
        $response = "🏷️ **Product Categories:**\n\n";
        foreach ($categories as $c) {
            $response .= "• " . $c['name'] . " – " . $c['product_count'] . " products\n";
        }
    } else {
        $response = "No categories found.";
    }
}
elseif (strpos($query, 'suppliers') !== false || strpos($query, 'supplier') !== false) {
    $stmt = $db->query("SELECT s.name, COUNT(p.id) as product_count FROM suppliers s LEFT JOIN products p ON s.id = p.supplier_id GROUP BY s.id ORDER BY product_count DESC");
    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($suppliers) > 0) {
        $response = "🏭 **Suppliers:**\n\n";
        foreach ($suppliers as $s) {
            $response .= "• " . $s['name'] . " – " . $s['product_count'] . " products\n";
        }
    } else {
        $response = "No suppliers found.";
    }
}
elseif (strpos($query, 'today sales') !== false || strpos($query, 'daily sales') !== false) {
    $stmt = $db->query("SELECT SUM(total_price) as total, COUNT(*) as transactions FROM sales WHERE DATE(sale_date) = CURDATE()");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $total = $result['total'] ?? 0;
    $transactions = $result['transactions'] ?? 0;
    
    $response = "📈 **Today's Sales:**\n\n";
    $response .= "• Total Revenue: ₱" . number_format($total, 2) . "\n";
    $response .= "• Transactions: " . $transactions . "\n";
    
    if ($transactions > 0) {
        $avg = $total / $transactions;
        $response .= "• Average per transaction: ₱" . number_format($avg, 2);
    }
}
elseif (strpos($query, 'inventory value') !== false || strpos($query, 'stock value') !== false) {
    $stmt = $db->query("SELECT SUM(price * stock_quantity) as total_value, COUNT(*) as total_products FROM products");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $value = $result['total_value'] ?? 0;
    $count = $result['total_products'] ?? 0;
    
    $response = "💎 **Inventory Value:**\n\n";
    $response .= "• Total Stock Value: ₱" . number_format($value, 2) . "\n";
    $response .= "• Total Products: " . $count . " items\n";
    
    if ($count > 0) {
        $avg = $value / $count;
        $response .= "• Average value per product: ₱" . number_format($avg, 2);
    }
}
else {
    $bot_response = "Hi! I can help you with inventory stuff 😊\n\n**Things you can ask me:**\n";
    $bot_response .= "• \"show low stock products\"\n";
    $bot_response .= "• \"what's the top selling product?\"\n";
    $bot_response .= "• \"how many products do we have?\"\n";
    $bot_response .= "• \"what's out of stock?\"\n";
    $bot_response .= "• \"recent sales today\"\n";
    $bot_response .= "• \"inventory value\"\n\n";
    $bot_response .= "**For reservations:**\n";
    $bot_response .= "• \"check stock laptop\" (or any product name)\n";
    $bot_response .= "• \"reserve 5\" (reserve product ID 5)\n";
    $bot_response .= "• \"reserve 5 3\" (reserve 3 units of product ID 5)\n";
    $bot_response .= "• \"my reservations\"\n";
    $bot_response .= "• \"cancel RES-20241210-1234\"\n\n";
    $bot_response .= "Just ask me anything about the inventory!";
}

echo json_encode(['response' => $bot_response]);
?>
