<?php
// Connect to database
require_once '../config/database.php';

header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();

// Get the user's question and chat history
$input = json_decode(file_get_contents('php://input'), true);
$user_question = strtolower($input['query'] ?? '');
$chat_history = $input['history'] ?? [];

$bot_response = '';

// Context-aware responses based on chat history
$context = '';
if (!empty($chat_history)) {
    $recent_messages = array_slice($chat_history, -3);
    foreach ($recent_messages as $msg) {
        if ($msg['type'] === 'user') {
            $context .= $msg['message'] . ' ';
        }
    }
}

// Enhanced pattern matching with context awareness
function matchesPattern($query, $patterns) {
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $query)) {
            return true;
        }
    }
    return false;
}

// Product lookup patterns
$stock_patterns = [
    '/check\s+(stock|inventory|quantity)\s+(.+)/i',
    '/how\s+many\s+(.+)/i',
    '/stock\s+(for|of)\s+(.+)/i',
    '/do\s+we\s+have\s+(.+)/i',
    '/(.+)\s+(available|in\s+stock)/i'
];

// Reservation patterns  
$reserve_patterns = [
    '/reserve\s+(\d+)(?:\s+(\d+))?/i',
    '/book\s+(\d+)(?:\s+(\d+))?/i',
    '/hold\s+(\d+)(?:\s+(\d+))?/i'
];

// Check if user wants to look up a product
if (matchesPattern($user_question, $stock_patterns)) {
    // Extract product identifier
    $search_for = '';
    foreach ($stock_patterns as $pattern) {
        if (preg_match($pattern, $user_question, $matches)) {
            $search_for = trim($matches[count($matches) - 1]);
            break;
        }
    }
    
    // Look for the product in database
    $stmt = $db->prepare("SELECT id, product_name, stock_quantity, price, barcode, category_id, reorder_level
                          FROM products 
                          WHERE id = :search 
                          OR barcode LIKE :search_like 
                          OR product_name LIKE :search_like 
                          ORDER BY 
                            CASE 
                                WHEN id = :search THEN 1
                                WHEN barcode = :search_exact THEN 2
                                WHEN product_name LIKE :search_exact_name THEN 3
                                ELSE 4
                            END
                          LIMIT 1");
    $stmt->execute([
        ':search' => $search_for,
        ':search_like' => "%$search_for%",
        ':search_exact' => $search_for,
        ':search_exact_name' => $search_for
    ]);
    $found_product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($found_product) {
        // Get category name
        $cat_stmt = $db->prepare("SELECT name FROM categories WHERE id = :cat_id");
        $cat_stmt->execute([':cat_id' => $found_product['category_id']]);
        $category = $cat_stmt->fetchColumn() ?: 'Uncategorized';
        
        $bot_response = "📦 **Found it!**\n\n";
        $bot_response .= "**" . $found_product['product_name'] . "**\n";
        $bot_response .= "• ID: " . $found_product['id'] . "\n";
        $bot_response .= "• Category: " . $category . "\n";
        $bot_response .= "• Barcode: " . $found_product['barcode'] . "\n";
        $bot_response .= "• Stock: **" . $found_product['stock_quantity'] . " units**\n";
        $bot_response .= "• Price: ₱" . number_format($found_product['price'], 2) . "\n";
        $bot_response .= "• Reorder Level: " . $found_product['reorder_level'] . " units\n\n";
        
        // Stock status with recommendations
        if ($found_product['stock_quantity'] > $found_product['reorder_level']) {
            $bot_response .= "✅ **Well stocked** - Ready for orders!\n";
        } elseif ($found_product['stock_quantity'] > 0) {
            $bot_response .= "⚠️ **Low stock** - Consider reordering soon\n";
        } else {
            $bot_response .= "❌ **Out of stock** - Needs immediate restocking\n";
        }
        
        if ($found_product['stock_quantity'] > 0) {
            $bot_response .= "\n💡 Type: **reserve " . $found_product['id'] . "** to reserve 1 unit";
        }
    } else {
        $bot_response = "❌ Couldn't find '$search_for'. Try searching by:\n• Product ID number\n• Product name\n• Barcode\n\n💡 Type: **search products** to browse all items";
    }
}
// Handle making reservations with enhanced validation
elseif (matchesPattern($user_question, $reserve_patterns)) {
    preg_match('/(?:reserve|book|hold)\s+(\d+)(?:\s+(\d+))?/i', $user_question, $matches);
    $product_id = $matches[1];
    $how_many = isset($matches[2]) ? (int)$matches[2] : 1;
    
    // Validate quantity
    if ($how_many <= 0) {
        $bot_response = "❌ Invalid quantity. Please specify a positive number.";
    } elseif ($how_many > 100) {
        $bot_response = "❌ Maximum 100 units per reservation. Please contact admin for bulk orders.";
    } else {
        // Look up the product with additional details
        $stmt = $db->prepare("SELECT p.*, c.name as category_name FROM products p 
                              LEFT JOIN categories c ON p.category_id = c.id 
                              WHERE p.id = :id");
        $stmt->execute([':id' => $product_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$product) {
            $bot_response = "❌ Can't find product ID $product_id. Type: **search products** to browse available items.";
        } elseif ($product['stock_quantity'] < $how_many) {
            $bot_response = "❌ **Insufficient Stock**\n\n";
            $bot_response .= "**" . $product['product_name'] . "**\n";
            $bot_response .= "• Requested: $how_many units\n";
            $bot_response .= "• Available: " . $product['stock_quantity'] . " units\n";
            if ($product['stock_quantity'] > 0) {
                $bot_response .= "• Max you can reserve: " . $product['stock_quantity'] . "\n\n";
                $bot_response .= "💡 Type: **reserve $product_id " . $product['stock_quantity'] . "** to reserve all available";
            }
        } else {
            // Process reservation
            $remaining_stock = $product['stock_quantity'] - $how_many;
            $stmt = $db->prepare("UPDATE products SET stock_quantity = :new_stock WHERE id = :id");
            $stmt->execute([':new_stock' => $remaining_stock, ':id' => $product_id]);
            
            // Log the transaction
            try {
                $stmt = $db->prepare("INSERT INTO inventory_logs (product_id, action, quantity, user_id, notes) 
                                      VALUES (:pid, 'reservation', :qty, 1, 'Reserved via AI chatbot')");
                $stmt->execute([':pid' => $product_id, ':qty' => $how_many]);
            } catch (Exception $e) {
                // Create logs table if it doesn't exist
                $db->exec("CREATE TABLE IF NOT EXISTS inventory_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    product_id INT,
                    action VARCHAR(50),
                    quantity INT,
                    user_id INT DEFAULT 1,
                    notes TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (product_id) REFERENCES products(id)
                )");
            }
            
            // Create reservation record
            try {
                $reservation_code = 'RES-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                $stmt = $db->prepare("INSERT INTO reservations (reservation_id, product_id, quantity, status, created_at) 
                                      VALUES (:res_id, :pid, :qty, 'active', NOW())");
                $stmt->execute([':res_id' => $reservation_code, ':pid' => $product_id, ':qty' => $how_many]);
            } catch (Exception $e) {
                // Create reservations table if it doesn't exist
                $db->exec("CREATE TABLE IF NOT EXISTS reservations (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    reservation_id VARCHAR(50) UNIQUE,
                    product_id INT,
                    quantity INT,
                    status ENUM('active', 'completed', 'cancelled') DEFAULT 'active',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (product_id) REFERENCES products(id)
                )");
                
                // Retry reservation creation
                $stmt = $db->prepare("INSERT INTO reservations (reservation_id, product_id, quantity, status, created_at) 
                                      VALUES (:res_id, :pid, :qty, 'active', NOW())");
                $stmt->execute([':res_id' => $reservation_code, ':pid' => $product_id, ':qty' => $how_many]);
            }
            
            $total_cost = $product['price'] * $how_many;
            
            $bot_response = "✅ **Reservation Confirmed!**\n\n";
            $bot_response .= "**" . $product['product_name'] . "**\n";
            $bot_response .= "• Reservation ID: **$reservation_code**\n";
            $bot_response .= "• Category: " . ($product['category_name'] ?: 'Uncategorized') . "\n";
            $bot_response .= "• Reserved: $how_many units\n";
            $bot_response .= "• Unit Price: ₱" . number_format($product['price'], 2) . "\n";
            $bot_response .= "• Total Value: ₱" . number_format($total_cost, 2) . "\n";
            $bot_response .= "• Remaining Stock: $remaining_stock units\n\n";
            $bot_response .= "📝 **Important:** Save your reservation code for pickup!\n";
            $bot_response .= "💡 Type: **my reservations** to view all your bookings";
        }
    }
}
// Check reservations
elseif (preg_match('/my\s+reservations?|check\s+reservation|reservation\s+status/i', $user_question)) {
    try {
        $stmt = $db->query("SELECT r.reservation_id, r.quantity, r.status, r.created_at, p.product_name, p.price 
                            FROM reservations r 
                            JOIN products p ON r.product_id = p.id 
                            WHERE r.status = 'active' 
                            ORDER BY r.created_at DESC 
                            LIMIT 10");
        $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($reservations) > 0) {
            $bot_response = "📋 **Active Reservations:**\n\n";
            foreach ($reservations as $res) {
                $total = $res['price'] * $res['quantity'];
                $date = date('M d, Y H:i', strtotime($res['created_at']));
                $bot_response .= "**" . $res['reservation_id'] . "**\n";
                $bot_response .= "• " . $res['product_name'] . "\n";
                $bot_response .= "• Quantity: " . $res['quantity'] . " units\n";
                $bot_response .= "• Value: ₱" . number_format($total, 2) . "\n";
                $bot_response .= "• Reserved: $date\n\n";
            }
            $bot_response .= "Type: **cancel RES-ID** to cancel a reservation";
        } else {
            $bot_response = "📋 No active reservations found.";
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
        $bot_response = "📋 No active reservations found.";
    }
}
// Cancel reservation
elseif (preg_match('/cancel\s+(RES-[\w\-]+)/i', $user_question, $matches)) {
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
        
        $bot_response = "✅ **Reservation Cancelled**\n\n";
        $bot_response .= "**$reservation_id**\n";
        $bot_response .= "• " . $reservation['product_name'] . "\n";
        $bot_response .= "• " . $reservation['quantity'] . " units returned to stock\n";
        $bot_response .= "• Status: Cancelled";
    } else {
        $bot_response = "❌ Reservation $reservation_id not found or already processed.";
    }
}
// Search products
elseif (preg_match('/search\s+(.+)|find\s+(.+)/i', $user_question, $matches)) {
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
        $bot_response = "🔍 **Search Results for '$search_term':**\n\n";
        foreach ($products as $p) {
            $status = $p['stock_quantity'] > 0 ? "✅ In Stock" : "❌ Out of Stock";
            $bot_response .= "**" . $p['product_name'] . "**\n";
            $bot_response .= "• ID: " . $p['id'] . " | Stock: " . $p['stock_quantity'] . "\n";
            $bot_response .= "• Price: ₱" . number_format($p['price'], 2) . " | $status\n\n";
        }
        $bot_response .= "Type: **check stock [ID]** for details\nType: **reserve [ID]** to reserve";
    } else {
        $bot_response = "🔍 No products found matching '$search_term'";
    }
}

if (strpos($user_question, 'low stock') !== false || strpos($user_question, 'need restock') !== false) {
    $stmt = $db->query("SELECT product_name, stock_quantity, reorder_level FROM products WHERE stock_quantity <= reorder_level ORDER BY stock_quantity ASC LIMIT 5");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($products) > 0) {
        $bot_response = "⚠️ **Low Stock Alert!**\n\nThese products need restocking:\n";
        foreach ($products as $p) {
            $shortage = $p['reorder_level'] - $p['stock_quantity'];
            $bot_response .= "• " . $p['product_name'] . " – Only " . $p['stock_quantity'] . " left (Need " . $shortage . " more)\n";
        }
    } else {
        $bot_response = "✅ Great news! All products are well stocked.";
    }
}
elseif (strpos($user_question, 'top selling') !== false || strpos($user_question, 'best seller') !== false) {
    $stmt = $db->query("SELECT p.product_name, SUM(ii.quantity) as total, SUM(ii.subtotal) as revenue FROM invoice_items ii JOIN invoices i ON ii.invoice_id = i.id JOIN products p ON ii.product_id = p.id WHERE i.payment_status = 'paid' GROUP BY ii.product_id ORDER BY total DESC LIMIT 3");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($products) > 0) {
        $bot_response = "🏆 **Top Selling Products:**\n\n";
        $rank = 1;
        foreach ($products as $p) {
            $medal = $rank == 1 ? '🥇' : ($rank == 2 ? '🥈' : '🥉');
            $bot_response .= $medal . " " . $p['product_name'] . " – " . $p['total'] . " units (₱" . number_format($p['revenue'], 2) . ")\n";
            $rank++;
        }
    } else {
        $bot_response = "No sales data available yet.";
    }
}
elseif (strpos($user_question, 'how many') !== false || strpos($user_question, 'total products') !== false) {
    $count = $db->query("SELECT COUNT(*) FROM products")->fetchColumn();
    $bot_response = "There are currently " . $count . " products in the inventory.";
}
elseif (strpos($user_question, 'forecast') !== false || strpos($user_question, 'predict') !== false) {
    $stmt = $db->query("SELECT p.product_name, f.predicted_depletion_days, f.reorder_suggestion FROM forecast_data f JOIN products p ON f.product_id = p.id WHERE f.predicted_depletion_days <= 7 ORDER BY f.predicted_depletion_days ASC LIMIT 3");
    $forecasts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($forecasts) > 0) {
        $bot_response = "Products predicted to run out soon:\n";
        foreach ($forecasts as $f) {
            $bot_response .= "• " . $f['product_name'] . " – " . $f['predicted_depletion_days'] . " days left. Reorder " . $f['reorder_suggestion'] . " units.\n";
        }
    } else {
        $bot_response = "No urgent restocking needed based on forecasts.";
    }
}
elseif (strpos($user_question, 'out of stock') !== false) {
    $stmt = $db->query("SELECT product_name FROM products WHERE stock_quantity = 0");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($products) > 0) {
        $bot_response = "❌ **Out of Stock Products:**\n\n";
        foreach ($products as $p) {
            $bot_response .= "• " . $p['product_name'] . "\n";
        }
    } else {
        $bot_response = "✅ No products are out of stock.";
    }
}
elseif (strpos($user_question, 'recent sales') !== false || strpos($user_question, 'latest sales') !== false) {
    $stmt = $db->query("SELECT p.product_name, ii.quantity, ii.subtotal as total_price, i.created_at FROM invoice_items ii JOIN invoices i ON ii.invoice_id = i.id JOIN products p ON ii.product_id = p.id WHERE i.payment_status = 'paid' ORDER BY i.created_at DESC LIMIT 5");
    $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($sales) > 0) {
        $bot_response = "💰 **Recent Sales:**\n\n";
        foreach ($sales as $s) {
            $date = date('M d, H:i', strtotime($s['created_at']));
            $bot_response .= "• " . $s['product_name'] . " – " . $s['quantity'] . " units (₱" . number_format($s['total_price'], 2) . ") on " . $date . "\n";
        }
    } else {
        $bot_response = "No recent sales found.";
    }
}
elseif (strpos($user_question, 'categories') !== false || strpos($user_question, 'category') !== false) {
    $stmt = $db->query("SELECT c.name, COUNT(p.id) as product_count FROM categories c LEFT JOIN products p ON c.id = p.category_id GROUP BY c.id ORDER BY product_count DESC");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($categories) > 0) {
        $bot_response = "🏷️ **Product Categories:**\n\n";
        foreach ($categories as $c) {
            $bot_response .= "• " . $c['name'] . " – " . $c['product_count'] . " products\n";
        }
    } else {
        $bot_response = "No categories found.";
    }
}
elseif (strpos($user_question, 'suppliers') !== false || strpos($user_question, 'supplier') !== false) {
    $stmt = $db->query("SELECT s.name, COUNT(p.id) as product_count FROM suppliers s LEFT JOIN products p ON s.id = p.supplier_id GROUP BY s.id ORDER BY product_count DESC");
    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($suppliers) > 0) {
        $bot_response = "🏭 **Suppliers:**\n\n";
        foreach ($suppliers as $s) {
            $bot_response .= "• " . $s['name'] . " – " . $s['product_count'] . " products\n";
        }
    } else {
        $bot_response = "No suppliers found.";
    }
}
elseif (strpos($user_question, 'today sales') !== false || strpos($user_question, 'daily sales') !== false) {
    $stmt = $db->query("SELECT SUM(total_amount) as total, COUNT(DISTINCT id) as transactions FROM invoices WHERE payment_status = 'paid' AND DATE(created_at) = CURDATE()");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $total = $result['total'] ?? 0;
    $transactions = $result['transactions'] ?? 0;
    
    $bot_response = "📈 **Today's Sales:**\n\n";
    $bot_response .= "• Total Revenue: ₱" . number_format($total, 2) . "\n";
    $bot_response .= "• Transactions: " . $transactions . "\n";
    
    if ($transactions > 0) {
        $avg = $total / $transactions;
        $bot_response .= "• Average per transaction: ₱" . number_format($avg, 2);
    }
}
elseif (strpos($user_question, 'inventory value') !== false || strpos($user_question, 'stock value') !== false) {
    $stmt = $db->query("SELECT SUM(price * stock_quantity) as total_value, COUNT(*) as total_products FROM products");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $value = $result['total_value'] ?? 0;
    $count = $result['total_products'] ?? 0;
    
    $bot_response = "💎 **Inventory Value:**\n\n";
    $bot_response .= "• Total Stock Value: ₱" . number_format($value, 2) . "\n";
    $bot_response .= "• Total Products: " . $count . " items\n";
    
    if ($count > 0) {
        $avg = $value / $count;
        $bot_response .= "• Average value per product: ₱" . number_format($avg, 2);
    }
}
else {
    // Intelligent help system with context awareness
    $help_context = '';
    
    // Check for common misspellings and variations
    $query_clean = preg_replace('/[^a-zA-Z0-9\s]/', '', $user_question);
    
    if (strpos($query_clean, 'help') !== false || strpos($query_clean, 'what can you do') !== false) {
        $bot_response = "🤖 **I'm your AI Inventory Assistant!** Here's what I can help you with:\n\n";
        $bot_response .= "**📦 Product Management:**\n";
        $bot_response .= "• \"check stock laptop\" - Check product availability\n";
        $bot_response .= "• \"search Nike shoes\" - Find products by name\n";
        $bot_response .= "• \"reserve 123 2\" - Reserve 2 units of product ID 123\n\n";
        
        $bot_response .= "**📊 Analytics & Reports:**\n";
        $bot_response .= "• \"show low stock products\" - Items needing restock\n";
        $bot_response .= "• \"top selling products\" - Best performers\n";
        $bot_response .= "• \"today's sales report\" - Daily performance\n";
        $bot_response .= "• \"inventory value\" - Total stock worth\n\n";
        
        $bot_response .= "**🏷️ Categories & Organization:**\n";
        $bot_response .= "• \"show categories\" - All product categories\n";
        $bot_response .= "• \"suppliers list\" - All suppliers\n";
        $bot_response .= "• \"out of stock items\" - Products needing attention\n\n";
        
        $bot_response .= "**📋 Reservations:**\n";
        $bot_response .= "• \"my reservations\" - View active bookings\n";
        $bot_response .= "• \"cancel RES-20241210-1234\" - Cancel reservation\n\n";
        
        $bot_response .= "💡 **Pro Tips:**\n";
        $bot_response .= "• Use natural language: \"How many laptops do we have?\"\n";
        $bot_response .= "• I understand context from our conversation\n";
        $bot_response .= "• Try the quick action buttons above for common tasks!";
    }
    elseif (strpos($query_clean, 'thank') !== false || strpos($query_clean, 'thanks') !== false) {
        $responses = [
            "You're welcome! Happy to help with your inventory needs! 😊",
            "Glad I could assist! Let me know if you need anything else! 👍",
            "No problem! I'm here whenever you need inventory support! 🤖",
            "My pleasure! Feel free to ask me anything about your stock! 📦"
        ];
        $bot_response = $responses[array_rand($responses)];
    }
    elseif (strpos($query_clean, 'hello') !== false || strpos($query_clean, 'hi') !== false || strpos($query_clean, 'hey') !== false) {
        $greetings = [
            "Hello! 👋 Ready to help you manage your inventory efficiently!",
            "Hi there! 😊 What can I help you with today?",
            "Hey! 🤖 I'm here to assist with all your inventory needs!",
            "Greetings! 📦 How can I help optimize your stock management?"
        ];
        $bot_response = $greetings[array_rand($greetings)];
        $bot_response .= "\n\n💡 Try asking: \"show low stock\" or \"what's selling well?\"";
    }
    elseif (preg_match('/\b(good|great|awesome|excellent|perfect)\b/', $query_clean)) {
        $positive_responses = [
            "Glad you're happy with the service! 😊 Anything else I can help with?",
            "Thank you! I'm here to make inventory management easier! 🎯",
            "Awesome! Let me know if you need more inventory insights! 📊",
            "Great to hear! I'm always ready to help with your stock needs! 📦"
        ];
        $bot_response = $positive_responses[array_rand($positive_responses)];
    }
    else {
        // Smart suggestions based on partial matches
        $suggestions = [];
        
        if (strpos($query_clean, 'stock') !== false) {
            $suggestions[] = "show low stock products";
            $suggestions[] = "check stock [product name]";
            $suggestions[] = "out of stock items";
        }
        
        if (strpos($query_clean, 'sell') !== false || strpos($query_clean, 'sale') !== false) {
            $suggestions[] = "top selling products";
            $suggestions[] = "today's sales report";
            $suggestions[] = "recent sales";
        }
        
        if (strpos($query_clean, 'product') !== false) {
            $suggestions[] = "search products";
            $suggestions[] = "total products count";
            $suggestions[] = "show categories";
        }
        
        $bot_response = "🤖 **I'm your Inventory Assistant!** I can help you with:\n\n";
        $bot_response .= "**🔍 Quick Actions:**\n";
        $bot_response .= "• \"show low stock products\"\n";
        $bot_response .= "• \"what's the top selling product?\"\n";
        $bot_response .= "• \"how many products do we have?\"\n";
        $bot_response .= "• \"what's out of stock?\"\n";
        $bot_response .= "• \"today's sales report\"\n";
        $bot_response .= "• \"inventory value\"\n\n";
        
        $bot_response .= "**📦 Product Operations:**\n";
        $bot_response .= "• \"check stock [product name]\" - Find specific items\n";
        $bot_response .= "• \"search [term]\" - Browse products\n";
        $bot_response .= "• \"reserve [ID] [quantity]\" - Book items\n\n";
        
        $bot_response .= "**📋 Reservations:**\n";
        $bot_response .= "• \"my reservations\" - View bookings\n";
        $bot_response .= "• \"cancel RES-XXXXXXXX\" - Cancel booking\n\n";
        
        if (!empty($suggestions)) {
            $bot_response .= "**💡 Based on your query, try:**\n";
            foreach (array_slice($suggestions, 0, 3) as $suggestion) {
                $bot_response .= "• \"$suggestion\"\n";
            }
            $bot_response .= "\n";
        }
        
        $bot_response .= "Just ask me anything about your inventory! 😊";
    }
}

// Add response metadata for better client handling
$response_data = [
    'response' => $bot_response,
    'timestamp' => date('Y-m-d H:i:s'),
    'query_type' => 'general',
    'suggestions' => []
];

// Add contextual suggestions based on response type
if (strpos($bot_response, 'reservation code') !== false) {
    $response_data['query_type'] = 'reservation_success';
    $response_data['suggestions'] = ['my reservations', 'check stock levels'];
} elseif (strpos($bot_response, 'Low Stock Alert') !== false) {
    $response_data['query_type'] = 'low_stock';
    $response_data['suggestions'] = ['show suppliers', 'reorder recommendations'];
} elseif (strpos($bot_response, 'Search Results') !== false) {
    $response_data['query_type'] = 'search_results';
    $response_data['suggestions'] = ['check stock details', 'reserve items'];
} elseif (strpos($bot_response, 'Top Selling') !== false) {
    $response_data['query_type'] = 'analytics';
    $response_data['suggestions'] = ['recent sales', 'inventory trends'];
}

echo json_encode($response_data);
?>
