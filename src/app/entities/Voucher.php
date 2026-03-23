<?php

/**
 * Voucher Entity
 * 
 * Đại diện cho mã giảm giá
 */

class Voucher {
    public $id;
    public $code;
    public $discount_type;
    public $discount_value;
    public $min_order_amount;
    public $max_discount;
    public $usage_limit;
    public $used_count;
    public $start_date;
    public $expired_at;
    public $status;
    public $created_at;
    
    public function __construct($data = []) {
        if (!empty($data)) {
            $this->id = $data['id'] ?? null;
            $this->code = $data['code'] ?? null;
            $this->discount_type = $data['discount_type'] ?? null;
            $this->discount_value = $data['discount_value'] ?? null;
            $this->min_order_amount = $data['min_order_amount'] ?? 0;
            $this->max_discount = $data['max_discount'] ?? null;
            $this->usage_limit = $data['usage_limit'] ?? 1;
            $this->used_count = $data['used_count'] ?? 0;
            $this->start_date = $data['start_date'] ?? null;
            $this->expired_at = $data['expired_at'] ?? null;
            $this->status = $data['status'] ?? 1;
            $this->created_at = $data['created_at'] ?? null;
        }
    }
    
    /**
     * Convert to array
     */
    public function toArray() {
        return [
            'id' => (int)$this->id,
            'code' => $this->code,
            'discount_type' => $this->discount_type,
            'discount_value' => (float)$this->discount_value,
            'min_order_amount' => (float)$this->min_order_amount,
            'max_discount' => $this->max_discount ? (float)$this->max_discount : null,
            'usage_limit' => (int)$this->usage_limit,
            'used_count' => (int)$this->used_count,
            'remaining_uses' => max(0, (int)$this->usage_limit - (int)$this->used_count),
            'start_date' => $this->start_date,
            'expired_at' => $this->expired_at,
            'status' => (int)$this->status,
            'is_active' => $this->isActive(),
            'created_at' => $this->created_at
        ];
    }
    
    /**
     * Kiểm tra voucher có active không
     */
    public function isActive() {
        if ($this->status != 1) {
            return false;
        }
        
        $now = date('Y-m-d H:i:s');
        
        // Kiểm tra ngày bắt đầu
        if ($this->start_date && $now < $this->start_date) {
            return false;
        }
        
        // Kiểm tra ngày hết hạn
        if ($this->expired_at && $now > $this->expired_at) {
            return false;
        }
        
        // Kiểm tra số lần sử dụng
        if ($this->usage_limit > 0 && $this->used_count >= $this->usage_limit) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Tính số tiền giảm cho đơn hàng
     */
    public function calculateDiscount($orderAmount) {
        if (!$this->isActive()) {
            return 0;
        }
        
        // Kiểm tra đơn tối thiểu
        if ($orderAmount < $this->min_order_amount) {
            return 0;
        }
        
        $discount = 0;
        
        if ($this->discount_type === 'PERCENTAGE') {
            // Giảm theo %
            $discount = $orderAmount * ($this->discount_value / 100);
            
            // Áp dụng giảm tối đa nếu có
            if ($this->max_discount && $discount > $this->max_discount) {
                $discount = $this->max_discount;
            }
        } elseif ($this->discount_type === 'FIXED') {
            // Giảm cố định
            $discount = $this->discount_value;
            
            // Không giảm quá tổng đơn
            if ($discount > $orderAmount) {
                $discount = $orderAmount;
            }
        }
        
        return round($discount, 2);
    }
}
