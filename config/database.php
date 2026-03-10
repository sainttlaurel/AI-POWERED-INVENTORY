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
        } catch(PDOException $exception) {
            die("Connection error: " . $exception->getMessage() . "<br><br>Please run <a href='setup.php'>setup.php</a> first to create the database.");
        }
        return $this->conn;
    }
}
?>
