<?php
class AdvancedForecasting {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function updateAllForecasts() {
        $products = $this->db->query("SELECT id FROM products")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($products as $product) {
            $this->calculateAdvancedForecast($product['id']);
        }
    }
    
    public function calculateAdvancedForecast($product_id) {
        // Get comprehensive sales data for last 90 days
        $query = "SELECT 
                    DATE(i.created_at) as sale_date,
                    SUM(ii.quantity) as daily_sales,
                    COUNT(DISTINCT i.id) as transaction_count,
                    AVG(ii.quantity) as avg_transaction_size
                  FROM invoice_items ii
                  JOIN invoices i ON ii.invoice_id = i.id
                  WHERE ii.product_id = :pid 
                  AND i.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
                  GROUP BY DATE(i.created_at)
                  ORDER BY sale_date ASC";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([':pid' => $product_id]);
        $sales_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get product info
        $product_query = "SELECT stock_quantity, reorder_level, price FROM products WHERE id = :pid";
        $stmt = $this->db->prepare($product_query);
        $stmt->execute([':pid' => $product_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$product) return;
        
        // Advanced calculations
        $forecast_data = $this->performAdvancedAnalysis($sales_data, $product);
        
        // Save enhanced forecast data
        $this->saveForecastData($product_id, $forecast_data);
        
        return $forecast_data;
    }
    
    private function performAdvancedAnalysis($sales_data, $product) {
        $total_days = 90;
        $current_stock = $product['stock_quantity'];
        $reorder_level = $product['reorder_level'];
        $price = $product['price'];
        
        // Basic metrics
        $total_sales = array_sum(array_column($sales_data, 'daily_sales'));
        $sales_days = count($sales_data);
        $zero_sales_days = $total_days - $sales_days;
        
        // Calculate various averages
        $avg_daily_sales = $sales_days > 0 ? $total_sales / $total_days : 0;
        $avg_sales_on_active_days = $sales_days > 0 ? $total_sales / $sales_days : 0;
        
        // Trend analysis using linear regression
        $trend = $this->calculateTrend($sales_data);
        
        // Seasonal patterns (weekly)
        $seasonal_factors = $this->calculateSeasonalFactors($sales_data);
        
        // Volatility analysis
        $volatility = $this->calculateVolatility($sales_data);
        
        // Advanced forecasting with trend and seasonality
        $base_forecast = $avg_daily_sales;
        $trend_adjusted_forecast = $base_forecast + ($trend * 30); // 30-day trend projection
        
        // Apply seasonal adjustments
        $seasonal_forecast = $this->applySeasonalAdjustment($trend_adjusted_forecast, $seasonal_factors);
        
        // Confidence intervals based on volatility
        $confidence_range = $volatility * 1.96; // 95% confidence interval
        
        // Forecasts
        $forecast_weekly = $seasonal_forecast * 7;
        $forecast_monthly = $seasonal_forecast * 30;
        $forecast_quarterly = $seasonal_forecast * 90;
        
        // Stock depletion prediction with confidence
        $predicted_depletion_days = $seasonal_forecast > 0 ? 
            ceil($current_stock / $seasonal_forecast) : 999;
        
        // Smart reorder suggestions
        $reorder_suggestion = $this->calculateSmartReorder(
            $seasonal_forecast, 
            $volatility, 
            $current_stock, 
            $reorder_level
        );
        
        // Risk assessment
        $risk_level = $this->assessRiskLevel(
            $current_stock, 
            $seasonal_forecast, 
            $volatility, 
            $predicted_depletion_days
        );
        
        // Revenue forecasts
        $revenue_forecast_weekly = $forecast_weekly * $price;
        $revenue_forecast_monthly = $forecast_monthly * $price;
        
        // Demand classification
        $demand_pattern = $this->classifyDemandPattern($sales_data, $avg_daily_sales, $volatility);
        
        return [
            'avg_daily_sales' => round($avg_daily_sales, 2),
            'avg_sales_active_days' => round($avg_sales_on_active_days, 2),
            'forecast_weekly' => round($forecast_weekly, 0),
            'forecast_monthly' => round($forecast_monthly, 0),
            'forecast_quarterly' => round($forecast_quarterly, 0),
            'predicted_depletion_days' => $predicted_depletion_days,
            'reorder_suggestion' => $reorder_suggestion,
            'trend_direction' => $trend > 0.1 ? 'increasing' : ($trend < -0.1 ? 'decreasing' : 'stable'),
            'trend_strength' => abs($trend),
            'volatility' => round($volatility, 2),
            'confidence_range' => round($confidence_range, 2),
            'risk_level' => $risk_level,
            'demand_pattern' => $demand_pattern,
            'revenue_forecast_weekly' => round($revenue_forecast_weekly, 2),
            'revenue_forecast_monthly' => round($revenue_forecast_monthly, 2),
            'sales_frequency' => round(($sales_days / $total_days) * 100, 1), // % of days with sales
            'zero_sales_days' => $zero_sales_days,
            'last_updated' => date('Y-m-d H:i:s')
        ];
    }
    
    private function calculateTrend($sales_data) {
        if (count($sales_data) < 2) return 0;
        
        $n = count($sales_data);
        $sum_x = 0;
        $sum_y = 0;
        $sum_xy = 0;
        $sum_x2 = 0;
        
        foreach ($sales_data as $i => $data) {
            $x = $i + 1; // Day number
            $y = $data['daily_sales'];
            
            $sum_x += $x;
            $sum_y += $y;
            $sum_xy += $x * $y;
            $sum_x2 += $x * $x;
        }
        
        // Linear regression slope (trend)
        $denominator = ($n * $sum_x2) - ($sum_x * $sum_x);
        if ($denominator == 0) return 0;
        
        $slope = (($n * $sum_xy) - ($sum_x * $sum_y)) / $denominator;
        return $slope;
    }
    
    private function calculateSeasonalFactors($sales_data) {
        $day_totals = array_fill(0, 7, 0); // 0 = Sunday, 6 = Saturday
        $day_counts = array_fill(0, 7, 0);
        
        foreach ($sales_data as $data) {
            $day_of_week = date('w', strtotime($data['sale_date']));
            $day_totals[$day_of_week] += $data['daily_sales'];
            $day_counts[$day_of_week]++;
        }
        
        $day_averages = [];
        $overall_average = 0;
        $total_count = 0;
        
        for ($i = 0; $i < 7; $i++) {
            $day_averages[$i] = $day_counts[$i] > 0 ? $day_totals[$i] / $day_counts[$i] : 0;
            $overall_average += $day_averages[$i];
            $total_count++;
        }
        
        $overall_average = $total_count > 0 ? $overall_average / $total_count : 1;
        
        // Calculate seasonal factors (ratio to average)
        $seasonal_factors = [];
        for ($i = 0; $i < 7; $i++) {
            $seasonal_factors[$i] = $overall_average > 0 ? $day_averages[$i] / $overall_average : 1;
        }
        
        return $seasonal_factors;
    }
    
    private function calculateVolatility($sales_data) {
        if (count($sales_data) < 2) return 0;
        
        $sales_values = array_column($sales_data, 'daily_sales');
        $mean = array_sum($sales_values) / count($sales_values);
        
        $variance = 0;
        foreach ($sales_values as $value) {
            $variance += pow($value - $mean, 2);
        }
        
        $variance = $variance / (count($sales_values) - 1);
        return sqrt($variance); // Standard deviation
    }
    
    private function applySeasonalAdjustment($base_forecast, $seasonal_factors) {
        $current_day = date('w'); // Current day of week
        $seasonal_factor = $seasonal_factors[$current_day] ?? 1;
        
        return $base_forecast * $seasonal_factor;
    }
    
    private function calculateSmartReorder($forecast, $volatility, $current_stock, $reorder_level) {
        // Base reorder on 30-day forecast plus safety stock
        $monthly_demand = $forecast * 30;
        
        // Safety stock based on volatility and lead time (assume 7 days)
        $lead_time_demand = $forecast * 7;
        $safety_stock = $volatility * sqrt(7) * 1.65; // 95% service level
        
        // Minimum reorder quantity
        $min_reorder = max($monthly_demand + $safety_stock, $reorder_level * 2);
        
        // Adjust based on current stock
        $suggested_order = max(0, $min_reorder - $current_stock);
        
        return ceil($suggested_order);
    }
    
    private function assessRiskLevel($current_stock, $forecast, $volatility, $depletion_days) {
        $risk_score = 0;
        
        // Stock level risk
        if ($depletion_days <= 7) $risk_score += 40;
        elseif ($depletion_days <= 14) $risk_score += 25;
        elseif ($depletion_days <= 30) $risk_score += 10;
        
        // Demand volatility risk
        if ($volatility > $forecast) $risk_score += 30;
        elseif ($volatility > $forecast * 0.5) $risk_score += 15;
        
        // Forecast reliability risk
        if ($forecast <= 0) $risk_score += 20;
        
        // Current stock risk
        if ($current_stock <= 0) $risk_score += 50;
        elseif ($current_stock <= 5) $risk_score += 20;
        
        if ($risk_score >= 70) return 'critical';
        elseif ($risk_score >= 40) return 'high';
        elseif ($risk_score >= 20) return 'medium';
        else return 'low';
    }
    
    private function classifyDemandPattern($sales_data, $avg_daily_sales, $volatility) {
        $sales_days = count($sales_data);
        $total_days = 90;
        $frequency = $sales_days / $total_days;
        
        if ($avg_daily_sales == 0) return 'no_demand';
        
        $cv = $volatility / $avg_daily_sales; // Coefficient of variation
        
        if ($frequency < 0.1) return 'sporadic';
        elseif ($frequency < 0.3) return 'intermittent';
        elseif ($cv > 1.5) return 'erratic';
        elseif ($cv > 0.5) return 'variable';
        else return 'smooth';
    }
    
    private function saveForecastData($product_id, $data) {
        // Create enhanced forecast table if it doesn't exist
        $this->createForecastTable();
        
        $query = "INSERT INTO forecast_data_advanced 
                  (product_id, avg_daily_sales, avg_sales_active_days, forecast_weekly, 
                   forecast_monthly, forecast_quarterly, predicted_depletion_days, 
                   reorder_suggestion, trend_direction, trend_strength, volatility, 
                   confidence_range, risk_level, demand_pattern, revenue_forecast_weekly, 
                   revenue_forecast_monthly, sales_frequency, zero_sales_days, last_updated) 
                  VALUES (:pid, :avg_daily, :avg_active, :weekly, :monthly, :quarterly, 
                          :depletion, :reorder, :trend_dir, :trend_str, :volatility, 
                          :confidence, :risk, :pattern, :rev_weekly, :rev_monthly, 
                          :frequency, :zero_days, :updated)
                  ON DUPLICATE KEY UPDATE 
                  avg_daily_sales = :avg_daily, avg_sales_active_days = :avg_active,
                  forecast_weekly = :weekly, forecast_monthly = :monthly, 
                  forecast_quarterly = :quarterly, predicted_depletion_days = :depletion,
                  reorder_suggestion = :reorder, trend_direction = :trend_dir,
                  trend_strength = :trend_str, volatility = :volatility,
                  confidence_range = :confidence, risk_level = :risk,
                  demand_pattern = :pattern, revenue_forecast_weekly = :rev_weekly,
                  revenue_forecast_monthly = :rev_monthly, sales_frequency = :frequency,
                  zero_sales_days = :zero_days, last_updated = :updated";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            ':pid' => $product_id,
            ':avg_daily' => $data['avg_daily_sales'],
            ':avg_active' => $data['avg_sales_active_days'],
            ':weekly' => $data['forecast_weekly'],
            ':monthly' => $data['forecast_monthly'],
            ':quarterly' => $data['forecast_quarterly'],
            ':depletion' => $data['predicted_depletion_days'],
            ':reorder' => $data['reorder_suggestion'],
            ':trend_dir' => $data['trend_direction'],
            ':trend_str' => $data['trend_strength'],
            ':volatility' => $data['volatility'],
            ':confidence' => $data['confidence_range'],
            ':risk' => $data['risk_level'],
            ':pattern' => $data['demand_pattern'],
            ':rev_weekly' => $data['revenue_forecast_weekly'],
            ':rev_monthly' => $data['revenue_forecast_monthly'],
            ':frequency' => $data['sales_frequency'],
            ':zero_days' => $data['zero_sales_days'],
            ':updated' => $data['last_updated']
        ]);
    }
    
    private function createForecastTable() {
        $query = "CREATE TABLE IF NOT EXISTS forecast_data_advanced (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT UNIQUE,
            avg_daily_sales DECIMAL(10,2) DEFAULT 0,
            avg_sales_active_days DECIMAL(10,2) DEFAULT 0,
            forecast_weekly DECIMAL(10,2) DEFAULT 0,
            forecast_monthly DECIMAL(10,2) DEFAULT 0,
            forecast_quarterly DECIMAL(10,2) DEFAULT 0,
            predicted_depletion_days INT DEFAULT 999,
            reorder_suggestion INT DEFAULT 0,
            trend_direction ENUM('increasing', 'decreasing', 'stable') DEFAULT 'stable',
            trend_strength DECIMAL(10,4) DEFAULT 0,
            volatility DECIMAL(10,2) DEFAULT 0,
            confidence_range DECIMAL(10,2) DEFAULT 0,
            risk_level ENUM('low', 'medium', 'high', 'critical') DEFAULT 'low',
            demand_pattern ENUM('smooth', 'variable', 'erratic', 'intermittent', 'sporadic', 'no_demand') DEFAULT 'smooth',
            revenue_forecast_weekly DECIMAL(12,2) DEFAULT 0,
            revenue_forecast_monthly DECIMAL(12,2) DEFAULT 0,
            sales_frequency DECIMAL(5,2) DEFAULT 0,
            zero_sales_days INT DEFAULT 0,
            last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        )";
        
        $this->db->exec($query);
    }
    
    // Get forecast insights for dashboard
    public function getForecastInsights() {
        try {
            $insights = [];
            
            // Critical stock alerts
            $critical_query = "SELECT p.product_name, f.predicted_depletion_days, f.risk_level 
                              FROM forecast_data_advanced f 
                              JOIN products p ON f.product_id = p.id 
                              WHERE f.risk_level IN ('critical', 'high') 
                              ORDER BY f.predicted_depletion_days ASC 
                              LIMIT 5";
            $insights['critical_items'] = $this->db->query($critical_query)->fetchAll(PDO::FETCH_ASSOC);
            
            // Trending products
            $trending_query = "SELECT p.product_name, f.trend_direction, f.trend_strength 
                              FROM forecast_data_advanced f 
                              JOIN products p ON f.product_id = p.id 
                              WHERE f.trend_direction = 'increasing' 
                              ORDER BY f.trend_strength DESC 
                              LIMIT 5";
            $insights['trending_up'] = $this->db->query($trending_query)->fetchAll(PDO::FETCH_ASSOC);
            
            // Revenue forecasts
            $revenue_query = "SELECT 
                                SUM(f.revenue_forecast_weekly) as weekly_revenue,
                                SUM(f.revenue_forecast_monthly) as monthly_revenue
                              FROM forecast_data_advanced f";
            $insights['revenue_forecast'] = $this->db->query($revenue_query)->fetch(PDO::FETCH_ASSOC);
            
            return $insights;
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
}

// Auto-update forecasts (can be called via cron job)
if (isset($_GET['update_forecasts']) && $_GET['update_forecasts'] === 'true') {
    require_once '../config/database.php';
    
    $database = new Database();
    $db = $database->getConnection();
    $forecasting = new AdvancedForecasting($db);
    
    $forecasting->updateAllForecasts();
    
    echo json_encode([
        'status' => 'success',
        'message' => 'All forecasts updated successfully',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>