<?php
require_once '../config/session.php';

// Only allow logged-in users
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Generate fresh CSRF token
$token = generateCSRFToken();

// Return as JSON
header('Content-Type: application/json');
echo json_encode([
    'csrf_token' => $token,
    'timestamp' => time()
]);
?>