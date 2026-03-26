<?php
/**
 * Supabase Configuration and Connection
 * 
 * This file provides a simple way to connect to Supabase from PHP
 * using the REST API (no additional dependencies required)
 */

class SupabaseClient {
    private $url;
    private $key;
    private $headers;

    public function __construct($url, $key) {
        $this->url = rtrim($url, '/');
        $this->key = $key;
        $this->headers = [
            'apikey: ' . $key,
            'Authorization: Bearer ' . $key,
            'Content-Type: application/json',
            'Prefer: return=representation'
        ];
    }

    /**
     * Select data from a table
     * 
     * @param string $table Table name
     * @param array $options Query options (select, filters, order, limit)
     * @return array Response data
     */
    public function from($table) {
        return new SupabaseQuery($this, $table);
    }

    /**
     * Execute HTTP request
     */
    public function request($method, $endpoint, $data = null) {
        $ch = curl_init();
        $url = $this->url . $endpoint;

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true);

        if ($httpCode >= 400) {
            throw new Exception("Supabase Error: " . ($result['message'] ?? 'Unknown error'));
        }

        return $result;
    }
}

class SupabaseQuery {
    private $client;
    private $table;
    private $select = '*';
    private $filters = [];
    private $order = null;
    private $limit = null;
    private $offset = null;

    public function __construct($client, $table) {
        $this->client = $client;
        $this->table = $table;
    }

    /**
     * Select specific columns
     */
    public function select($columns = '*') {
        $this->select = $columns;
        return $this;
    }

    /**
     * Filter by equality
     */
    public function eq($column, $value) {
        $this->filters[] = "$column=eq.$value";
        return $this;
    }

    /**
     * Filter by greater than
     */
    public function gt($column, $value) {
        $this->filters[] = "$column=gt.$value";
        return $this;
    }

    /**
     * Filter by less than
     */
    public function lt($column, $value) {
        $this->filters[] = "$column=lt.$value";
        return $this;
    }

    /**
     * Filter by LIKE pattern
     */
    public function like($column, $pattern) {
        $this->filters[] = "$column=like.$pattern";
        return $this;
    }

    /**
     * Order results
     */
    public function order($column, $direction = 'asc') {
        $this->order = "$column.$direction";
        return $this;
    }

    /**
     * Limit results
     */
    public function limit($limit) {
        $this->limit = $limit;
        return $this;
    }

    /**
     * Offset results
     */
    public function offset($offset) {
        $this->offset = $offset;
        return $this;
    }

    /**
     * Execute SELECT query
     */
    public function get() {
        $params = ['select=' . $this->select];
        
        if (!empty($this->filters)) {
            $params = array_merge($params, $this->filters);
        }
        
        if ($this->order) {
            $params[] = 'order=' . $this->order;
        }
        
        if ($this->limit) {
            $params[] = 'limit=' . $this->limit;
        }
        
        if ($this->offset) {
            $params[] = 'offset=' . $this->offset;
        }

        $endpoint = "/rest/v1/{$this->table}?" . implode('&', $params);
        return $this->client->request('GET', $endpoint);
    }

    /**
     * Insert data
     */
    public function insert($data) {
        $endpoint = "/rest/v1/{$this->table}";
        return $this->client->request('POST', $endpoint, $data);
    }

    /**
     * Update data
     */
    public function update($data) {
        $params = [];
        if (!empty($this->filters)) {
            $params = $this->filters;
        }
        
        $endpoint = "/rest/v1/{$this->table}";
        if (!empty($params)) {
            $endpoint .= '?' . implode('&', $params);
        }
        
        return $this->client->request('PATCH', $endpoint, $data);
    }

    /**
     * Delete data
     */
    public function delete() {
        $params = [];
        if (!empty($this->filters)) {
            $params = $this->filters;
        }
        
        $endpoint = "/rest/v1/{$this->table}";
        if (!empty($params)) {
            $endpoint .= '?' . implode('&', $params);
        }
        
        return $this->client->request('DELETE', $endpoint);
    }
}

// Initialize Supabase client
// Load from environment or config
$SUPABASE_URL = 'https://temdnfcnllnvpgpookex.supabase.co';
$SUPABASE_KEY = 'sb_publishable_uUj76dDQz6DVaYXpWH7Npw_Zpo-jLKP';

$supabase = new SupabaseClient($SUPABASE_URL, $SUPABASE_KEY);

// Example usage:
/*
// Get all products
$products = $supabase->from('products')->get();

// Get products with filters
$products = $supabase->from('products')
    ->select('id,product_name,price,stock_quantity')
    ->eq('category_id', 1)
    ->gt('stock_quantity', 0)
    ->order('product_name', 'asc')
    ->limit(10)
    ->get();

// Insert a product
$newProduct = $supabase->from('products')->insert([
    'product_name' => 'New Product',
    'price' => 99.99,
    'cost_price' => 50.00,
    'stock_quantity' => 100
]);

// Update a product
$updated = $supabase->from('products')
    ->eq('id', 1)
    ->update(['price' => 89.99]);

// Delete a product
$deleted = $supabase->from('products')
    ->eq('id', 1)
    ->delete();
*/
