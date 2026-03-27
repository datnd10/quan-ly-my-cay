<?php

/**
 * Reservation Entity
 * 
 * Đại diện cho đặt bàn
 * Flow: PENDING → CONFIRMED → CANCELLED / NO_SHOW
 */

class Reservation {
    public $id;
    public $customer_id;
    public $table_id;
    public $reservation_time;
    public $guest_count;
    public $customer_name;
    public $customer_phone;
    public $customer_note;
    public $status;
    public $order_id;
    public $confirmed_by;
    public $confirmed_at;
    public $cancelled_reason;
    public $created_at;
    public $updated_at;
    
    // Relations
    public $customer = null;
    public $table = null;
    
    // Reservation statuses
    const STATUS_PENDING = 'PENDING';
    const STATUS_CONFIRMED = 'CONFIRMED';
    const STATUS_CANCELLED = 'CANCELLED';
    const STATUS_NO_SHOW = 'NO_SHOW';
    
    public function __construct($data = []) {
        if (!empty($data)) {
            $this->id = $data['id'] ?? null;
            $this->customer_id = $data['customer_id'] ?? null;
            $this->table_id = $data['table_id'] ?? null;
            $this->reservation_time = $data['reservation_time'] ?? null;
            $this->guest_count = $data['guest_count'] ?? null;
            $this->customer_name = $data['customer_name'] ?? null;
            $this->customer_phone = $data['customer_phone'] ?? null;
            $this->customer_note = $data['customer_note'] ?? null;
            $this->status = $data['status'] ?? self::STATUS_PENDING;
            $this->order_id = $data['order_id'] ?? null;
            $this->confirmed_by = $data['confirmed_by'] ?? null;
            $this->confirmed_at = $data['confirmed_at'] ?? null;
            $this->cancelled_reason = $data['cancelled_reason'] ?? null;
            $this->created_at = $data['created_at'] ?? null;
            $this->updated_at = $data['updated_at'] ?? null;
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
            'reservation_time' => $this->reservation_time,
            'guest_count' => (int)$this->guest_count,
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'customer_note' => $this->customer_note,
            'status' => $this->status,
            'order_id' => $this->order_id ? (int)$this->order_id : null,
            'confirmed_by' => $this->confirmed_by ? (int)$this->confirmed_by : null,
            'confirmed_at' => $this->confirmed_at,
            'cancelled_reason' => $this->cancelled_reason,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
        
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
        
        return $data;
    }
    
    /**
     * Có thể duyệt không (chỉ PENDING)
     */
    public function canConfirm() {
        return $this->status === self::STATUS_PENDING;
    }

    
    /**
     * Có thể hủy không (PENDING hoặc CONFIRMED)
     */
    public function canCancel() {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_CONFIRMED]);
    }
}
