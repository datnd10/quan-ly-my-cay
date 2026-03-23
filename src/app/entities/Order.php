<?php

/**
 * Order Entity
 * 
 * Đại diện cho đơn hàng
 */

class Order {
    public $id;
    public $customer_id;
    public $table_id;
    public $voucher_id;
    public $voucher_discount;
    public $status;
    public $total_amount;
    public $discount_amount;
    public $final_amount;
    public $payment_method;
    public $payment_at;
    public $created_at;
    public $completed_at;
    
    // Relations
    public $items = [];
    public $customer = null;
    public $table = null;
    public $voucher = null;
    
    // Order statuses
    const STATUS_ACTIVE = 'ACTIVE';
    const STATUS_COMPLETED = 'COMPLETED';
    const STATUS_CANCELLED = 'CANCELLED';
    
    public function __construct($data = []) {
        if (!empty($data)) {
            $this->id = $data['id'] ?? null;
            $this->customer_id = $data['customer_id'] ?? null;
            $this->table_id = $data['table_id'] ?? null;
            $this->voucher_id = $data['voucher_id'] ?? null;
            $this->voucher_discount = $data['voucher_discount'] ?? 0;
            $this->status = $data['status'] ?? self::STATUS_ACTIVE;
            $this->total_amount = $data['total_amount'] ?? 0;
            $this->discount_amount = $data['discount_amount'] ?? 0;
            $this->final_amount = $data['final_amount'] ?? 0;
            $this->payment_method = $data['payment_method'] ?? null;
            $this->payment_at = $data['payment_at'] ?? null;
            $this->created_at = $data['created_at'] ?? null;
            $this->completed_at = $data['completed_at'] ?? null;
        }
    }
    
    /**
     * Convert to array
     */
    public function toArray() {
        $data = [
            'id' => (int)$this->id,
            'customer_id' => $this->customer_id ? (int)$this->customer_id : null,
            'table_id' => $this->table_id ? (int)$this->table_id : null,
            'voucher_id' => $this->voucher_id ? (int)$this->voucher_id : null,
            'voucher_discount' => (float)$this->voucher_discount,
            'status' => $this->status,
            'total_amount' => (float)$this->total_amount,
            'discount_amount' => (float)$this->discount_amount,
            'final_amount' => (float)$this->final_amount,
            'payment_method' => $this->payment_method,
            'payment_at' => $this->payment_at,
            'created_at' => $this->created_at,
            'completed_at' => $this->completed_at
        ];
        
        // Include items if loaded
        if (!empty($this->items)) {
            $data['items'] = array_map(function($item) {
                return is_object($item) && method_exists($item, 'toArray') 
                    ? $item->toArray() 
                    : $item;
            }, $this->items);
        }
        
        // Include relations if loaded
        if ($this->customer) {
            $data['customer'] = is_object($this->customer) && method_exists($this->customer, 'toArray')
                ? $this->customer->toArray()
                : $this->customer;
        }
        
        if ($this->table) {
            $data['table'] = is_object($this->table) && method_exists($this->table, 'toArray')
                ? $this->table->toArray()
                : $this->table;
        }
        
        if ($this->voucher) {
            $data['voucher'] = is_object($this->voucher) && method_exists($this->voucher, 'toArray')
                ? $this->voucher->toArray()
                : $this->voucher;
        }
        
        return $data;
    }
    
    /**
     * Kiểm tra order có thể sửa không (gọi thêm món)
     */
    public function canEdit() {
        return $this->status === self::STATUS_ACTIVE;
    }
    
    /**
     * Kiểm tra order có thể hủy không
     */
    public function canCancel() {
        return $this->status === self::STATUS_ACTIVE;
    }
    
    /**
     * Kiểm tra order có thể thanh toán không
     */
    public function canPay() {
        return $this->status === self::STATUS_ACTIVE;
    }
}
