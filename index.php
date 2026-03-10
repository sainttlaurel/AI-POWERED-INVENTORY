<?php
// Check if database is set up
require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

if ($db) {
    // Database exists, check if user is logged in
    session_start();
    if (isset($_SESSION['user_id'])) {
        header("Location: dashboard.php");
    } else {
        header("Location: login.php");
    }
} else {
    // Database not set up, redirect to quick start
    header("Location: QUICKSTART.html");
}
exit();
?>
