<?php

/**
 * Product Entity
 * 
 * Đại diện cho sản phẩm trong hệ thống
 */

class Product {
    public $id;
    public $category_id;
    public $name;
    public $price;
    public $description;
    public $image_url;
    public $stock_quantity;
    public $min_stock;
    public $status;
    public $created_at;
    public $updated_at;
    
    // Thông tin category (khi join)
    public $category_name;
    
    public function __construct($data = []) {
        if (!empty($data)) {
            $this->id = $data['id'] ?? null;
            $this->category_id = $data['category_id'] ?? null;
            $this->name = $data['name'] ?? null;
            $this->price = $data['price'] ?? null;
            $this->description = $data['description'] ?? null;
            $this->image_url = $data['image_url'] ?? null;
            $this->stock_quantity = $data['stock_quantity'] ?? 0;
            $this->min_stock = $data['min_stock'] ?? 0;
            $this->status = $data['status'] ?? 1;
            $this->created_at = $data['created_at'] ?? null;
            $this->updated_at = $data['updated_at'] ?? null;
            $this->category_name = $data['category_name'] ?? null;
        }
    }
    
    /**
     * Convert to array
     */
    public function toArray() {
        $data = [
            'id' => (int)$this->id,
            'category_id' => $this->category_id ? (int)$this->category_id : null,
            'name' => $this->name,
            'price' => (float)$this->price,
            'description' => $this->description,
            'image_url' => $this->image_url,
            'stock_quantity' => (int)$this->stock_quantity,
            'min_stock' => (int)$this->min_stock,
            'status' => (int)$this->status,
            'is_low_stock' => $this->stock_quantity <= $this->min_stock,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
        
        // Thêm category info nếu có
        if ($this->category_name) {
            $data['category'] = [
                'id' => (int)$this->category_id,
                'name' => $this->category_name
            ];
        }
        
        return $data;
    }
}
