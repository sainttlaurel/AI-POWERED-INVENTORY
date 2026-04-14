<?php
/**
 * api/get_customers.php
 * Search customers by name/email/phone — returns JSON for autocomplete
 */
require_once '../config/database.php';
require_once '../config/session.php';
requireLogin();

header('Content-Type: application/json');

$db = (new Database())->getConnection();

// Auto-create customers table on first use
try {
    $db->exec("CREATE TABLE IF NOT EXISTS customers (
        id              INT AUTO_INCREMENT PRIMARY KEY,
        name            VARCHAR(255) NOT NULL,
        email           VARCHAR(255) DEFAULT NULL,
        phone           VARCHAR(30)  DEFAULT NULL,
        address         TEXT         DEFAULT NULL,
        total_invoices  INT          NOT NULL DEFAULT 0,
        total_spent     DECIMAL(14,2) NOT NULL DEFAULT 0.00,
        created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_name  (name),
        INDEX idx_email (email)
    )");
} catch (Exception $e) { /* table already exists */ }

$q = trim($_GET['q'] ?? '');

if (strlen($q) < 1) {
    // Return recent customers
    $stmt = $db->query("SELECT id, name, email, phone, address FROM customers ORDER BY updated_at DESC LIMIT 10");
} else {
    $stmt = $db->prepare("SELECT id, name, email, phone, address FROM customers
        WHERE name LIKE ? OR email LIKE ? OR phone LIKE ?
        ORDER BY name ASC LIMIT 15");
    $like = "%$q%";
    $stmt->execute([$like, $like, $like]);
}

echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
