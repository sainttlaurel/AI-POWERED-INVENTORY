<?php
/**
 * Supabase Helper Class
 * Provides easy-to-use methods for common database operations
 */

require_once __DIR__ . '/supabase.php';

class SupabaseHelper {
    private $supabase;
    
    public function __construct() {
        global $supabase;
        $this->supabase = $supabase;
    }
    
    /**
     * Get all products with category and supplier info
     */
    public function getAllProducts() {
        return $this->supabase->from('products')
            ->select('*')
            ->order('date_added', 'desc')
            ->get();
    }
    
    /**
     * Get product by ID
     */
    public function getProductById($id) {
        $result = $this->supabase->from('products')
            ->select('*')
            ->eq('id', $id)
            ->get();
        
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Get all categories
     */
    public function getAllCategories() {
        return $this->supabase->from('categories')
            ->select('*')
            ->order('name', 'asc')
            ->get();
    }
    
    /**
     * Get all suppliers
     */
    public function getAllSuppliers() {
        return $this->supabase->from('suppliers')
            ->select('*')
            ->order('name', 'asc')
            ->get();
    }
    
    /**
     * Insert new product
     */
    public function insertProduct($data) {
        return $this->supabase->from('products')->insert($data);
    }
    
    /**
     * Update product
     */
    public function updateProduct($id, $data) {
        return $this->supabase->from('products')
            ->eq('id', $id)
            ->update($data);
    }
    
    /**
     * Delete product
     */
    public function deleteProduct($id) {
        return $this->supabase->from('products')
            ->eq('id', $id)
            ->delete();
    }
    
    /**
     * Get low stock products
     */
    public function getLowStockProducts() {
        // Get all products and filter in PHP (Supabase REST API limitation)
        $products = $this->supabase->from('products')
            ->select('*')
            ->get();
        
        $lowStock = [];
        foreach ($products as $product) {
            if ($product['stock_quantity'] <= $product['reorder_level']) {
                $lowStock[] = $product;
            }
        }
        
        return $lowStock;
    }
    
    /**
     * Search products
     */
    public function searchProducts($searchTerm) {
        $products = $this->supabase->from('products')
            ->select('*')
            ->get();
        
        // Filter in PHP
        $results = [];
        foreach ($products as $product) {
            if (stripos($product['product_name'], $searchTerm) !== false ||
                stripos($product['barcode'], $searchTerm) !== false) {
                $results[] = $product;
            }
        }
        
        return $results;
    }
    
    /**
     * Get inventory logs
     */
    public function getInventoryLogs($limit = 50) {
        return $this->supabase->from('inventory_logs')
            ->select('*')
            ->order('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
    
    /**
     * Add inventory log
     */
    public function addInventoryLog($data) {
        return $this->supabase->from('inventory_logs')->insert($data);
    }
    
    /**
     * Get dashboard stats
     */
    public function getDashboardStats() {
        $products = $this->getAllProducts();
        $categories = $this->getAllCategories();
        $suppliers = $this->getAllSuppliers();
        
        $totalValue = 0;
        $lowStockCount = 0;
        
        foreach ($products as $product) {
            $totalValue += $product['price'] * $product['stock_quantity'];
            if ($product['stock_quantity'] <= $product['reorder_level']) {
                $lowStockCount++;
            }
        }
        
        return [
            'total_products' => count($products),
            'total_categories' => count($categories),
            'total_suppliers' => count($suppliers),
            'total_value' => $totalValue,
            'low_stock_count' => $lowStockCount
        ];
    }
    
    /**
     * Get category by ID
     */
    public function getCategoryById($id) {
        $result = $this->supabase->from('categories')
            ->select('*')
            ->eq('id', $id)
            ->get();
        
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Get supplier by ID
     */
    public function getSupplierById($id) {
        $result = $this->supabase->from('suppliers')
            ->select('*')
            ->eq('id', $id)
            ->get();
        
        return !empty($result) ? $result[0] : null;
    }
}
