<?php
class Forecasting {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function updateAllForecasts() {
        $products = $this->db->query("SELECT id FROM products")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($products as $product) {
            $this->calculateForecast($product['id']);
        }
    }
    
    public function calculateForecast($product_id) {
        // Get sales data for last 30 days
        $query = "SELECT SUM(quantity) as total_sales, 
                         COUNT(DISTINCT DATE(sale_date)) as days_with_sales 
                  FROM sales 
                  WHERE product_id = :pid 
                  AND sale_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([':pid' => $product_id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $total_sales = $data['total_sales'] ?? 0;
        $days = $data['days_with_sales'] > 0 ? $data['days_with_sales'] : 30;
        
        // Calculate forecasts
        $avg_daily_sales = $total_sales / $days;
        $forecast_weekly = $avg_daily_sales * 7;
        $forecast_monthly = $avg_daily_sales * 30;
        
        // Get current stock
        $stock = $this->db->query("SELECT stock_quantity FROM products WHERE id = $product_id")->fetchColumn();
        
        // Predict when stock will run out
        $predicted_depletion_days = $avg_daily_sales > 0 ? ceil($stock / $avg_daily_sales) : 999;
        
        // Suggest reorder quantity (monthly forecast + 20% buffer)
        $reorder_suggestion = ceil($forecast_monthly * 1.2);
        
        // Save forecast data
        $query = "INSERT INTO forecast_data 
                  (product_id, avg_daily_sales, forecast_weekly, forecast_monthly, 
                   predicted_depletion_days, reorder_suggestion) 
                  VALUES (:pid, :avg, :weekly, :monthly, :depletion, :reorder)
                  ON DUPLICATE KEY UPDATE 
                  avg_daily_sales = :avg, 
                  forecast_weekly = :weekly, 
                  forecast_monthly = :monthly, 
                  predicted_depletion_days = :depletion, 
                  reorder_suggestion = :reorder";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            ':pid' => $product_id,
            ':avg' => $avg_daily_sales,
            ':weekly' => $forecast_weekly,
            ':monthly' => $forecast_monthly,
            ':depletion' => $predicted_depletion_days,
            ':reorder' => $reorder_suggestion
        ]);
    }
}
