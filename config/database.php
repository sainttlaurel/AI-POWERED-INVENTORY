<?php
class Database {
    private $host = "localhost";
    private $db_name = "inventory_db";
    private $username = "root";
    private $password = "";
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec("set names utf8");
            
            // Ensure notifications table exists
            $this->initializeNotificationsTable();
            
        } catch(PDOException $exception) {
            error_log("Database connection failed: " . $exception->getMessage());
            die("Database connection failed. Please check your configuration.");
        }
        return $this->conn;
    }
    
    private function initializeNotificationsTable() {
        try {
            $this->conn->exec("CREATE TABLE IF NOT EXISTS notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                type VARCHAR(50) NOT NULL DEFAULT 'info',
                title VARCHAR(255) NOT NULL,
                message TEXT NOT NULL,
                user_id INT NULL,
                priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
                is_read BOOLEAN DEFAULT FALSE,
                action_url VARCHAR(255) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_user_read (user_id, is_read),
                INDEX idx_created_at (created_at),
                INDEX idx_type (type)
            )");
        } catch(PDOException $e) {
            error_log("Failed to create notifications table: " . $e->getMessage());
        }
    }
}
?>
