<?php

/**
 * OrderItem Entity
 * 
 * Đại diện cho món trong đơn hàng
 */

class OrderItem {
    public $id;
    public $order_id;
    public $product_id;
    public $quantity;
    public $price;
    public $subtotal;
    
    // Relations
    public $product = null;
    
    public function __construct($data = []) {
        if (!empty($data)) {
            $this->id = $data['id'] ?? null;
            $this->order_id = $data['order_id'] ?? null;
            $this->product_id = $data['product_id'] ?? null;
            $this->quantity = $data['quantity'] ?? 0;
            $this->price = $data['price'] ?? 0;
            $this->subtotal = $data['subtotal'] ?? 0;
        }
    }
    
    /**
     * Convert to array
     */
    public function toArray() {
        $data = [
            'id' => (int)$this->id,
            'order_id' => (int)$this->order_id,
            'product_id' => (int)$this->product_id,
            'quantity' => (int)$this->quantity,
            'price' => (float)$this->price,
            'subtotal' => (float)$this->subtotal
        ];
        
        // Include product if loaded
        if ($this->product) {
            $data['product'] = is_object($this->product) && method_exists($this->product, 'toArray')
                ? $this->product->toArray()
                : $this->product;
        }
        
        return $data;
    }
}
