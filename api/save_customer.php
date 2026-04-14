<?php
/**
 * api/save_customer.php
 * Upsert a customer after invoice creation.
 * Called via fetch() from create_invoice.php JS after a successful invoice save.
 */
require_once '../config/database.php';
require_once '../config/session.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$db   = (new Database())->getConnection();
$name    = trim($_POST['name']    ?? '');
$email   = trim($_POST['email']   ?? '');
$phone   = trim($_POST['phone']   ?? '');
$address = trim($_POST['address'] ?? '');
$amount  = floatval($_POST['amount'] ?? 0);

if (!$name) {
    echo json_encode(['error' => 'Customer name is required']);
    exit();
}

try {
    // Upsert — if same name+email match, update totals and contact info
    if ($email) {
        $existing = $db->prepare("SELECT id FROM customers WHERE email = ? LIMIT 1");
        $existing->execute([$email]);
        $row = $existing->fetch(PDO::FETCH_ASSOC);
    } else {
        $existing = $db->prepare("SELECT id FROM customers WHERE name = ? LIMIT 1");
        $existing->execute([$name]);
        $row = $existing->fetch(PDO::FETCH_ASSOC);
    }

    if ($row) {
        // Update existing customer
        $upd = $db->prepare("UPDATE customers
            SET name=?, email=?, phone=?, address=?,
                total_invoices = total_invoices + 1,
                total_spent    = total_spent + ?,
                updated_at     = NOW()
            WHERE id = ?");
        $upd->execute([$name, $email ?: null, $phone ?: null, $address ?: null, $amount, $row['id']]);
        echo json_encode(['status' => 'updated', 'id' => $row['id']]);
    } else {
        // Insert new customer
        $ins = $db->prepare("INSERT INTO customers (name, email, phone, address, total_invoices, total_spent)
                             VALUES (?, ?, ?, ?, 1, ?)");
        $ins->execute([$name, $email ?: null, $phone ?: null, $address ?: null, $amount]);
        echo json_encode(['status' => 'created', 'id' => $db->lastInsertId()]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
