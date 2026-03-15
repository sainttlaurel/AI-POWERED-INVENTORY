<?php
require_once 'config/session.php';

// Log logout activity before destroying session
if (isLoggedIn()) {
    logUserActivity('logout', "User logged out");
}

session_destroy();
header("Location: login.php");
exit();
?>
