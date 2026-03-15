<?php
class RateLimiter {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
        $this->createTable();
    }
    
    private function createTable() {
        try {
            $this->db->exec("CREATE TABLE IF NOT EXISTS rate_limits (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ip_address VARCHAR(45) NOT NULL,
                action VARCHAR(50) NOT NULL,
                attempts INT DEFAULT 1,
                last_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                blocked_until TIMESTAMP NULL,
                INDEX idx_ip_action (ip_address, action),
                INDEX idx_blocked_until (blocked_until)
            )");
        } catch (Exception $e) {
            error_log("Rate limiter table creation failed: " . $e->getMessage());
        }
    }
    
    public function checkLimit($action, $maxAttempts = 5, $timeWindow = 300) {
        $ip = $this->getClientIP();
        
        // Clean old records
        $this->db->prepare("DELETE FROM rate_limits WHERE last_attempt < DATE_SUB(NOW(), INTERVAL ? SECOND)")
                 ->execute([$timeWindow]);
        
        // Check if currently blocked
        $stmt = $this->db->prepare("SELECT blocked_until FROM rate_limits WHERE ip_address = ? AND action = ? AND blocked_until > NOW()");
        $stmt->execute([$ip, $action]);
        
        if ($stmt->fetch()) {
            return false; // Still blocked
        }
        
        // Get current attempts
        $stmt = $this->db->prepare("SELECT attempts FROM rate_limits WHERE ip_address = ? AND action = ?");
        $stmt->execute([$ip, $action]);
        $current = $stmt->fetch();
        
        if ($current) {
            $attempts = $current['attempts'] + 1;
            
            if ($attempts >= $maxAttempts) {
                // Block for increasing time based on attempts
                $blockTime = min(3600, $timeWindow * pow(2, $attempts - $maxAttempts)); // Max 1 hour
                
                $stmt = $this->db->prepare("UPDATE rate_limits SET attempts = ?, blocked_until = DATE_ADD(NOW(), INTERVAL ? SECOND) WHERE ip_address = ? AND action = ?");
                $stmt->execute([$attempts, $blockTime, $ip, $action]);
                
                return false;
            } else {
                $stmt = $this->db->prepare("UPDATE rate_limits SET attempts = ? WHERE ip_address = ? AND action = ?");
                $stmt->execute([$attempts, $ip, $action]);
            }
        } else {
            $stmt = $this->db->prepare("INSERT INTO rate_limits (ip_address, action, attempts) VALUES (?, ?, 1)");
            $stmt->execute([$ip, $action]);
        }
        
        return true;
    }
    
    public function resetLimit($action) {
        $ip = $this->getClientIP();
        $stmt = $this->db->prepare("DELETE FROM rate_limits WHERE ip_address = ? AND action = ?");
        $stmt->execute([$ip, $action]);
    }
    
    public function getRemainingAttempts($action, $maxAttempts = 5) {
        $ip = $this->getClientIP();
        $stmt = $this->db->prepare("SELECT attempts FROM rate_limits WHERE ip_address = ? AND action = ?");
        $stmt->execute([$ip, $action]);
        $current = $stmt->fetch();
        
        return $maxAttempts - ($current['attempts'] ?? 0);
    }
    
    private function getClientIP() {
        $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}
?>