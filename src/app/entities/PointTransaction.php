<?php

/**
 * Point Transaction Entity
 * 
 * Đại diện cho giao dịch điểm tích lũy
 */

class PointTransaction {
    public $id;
    public $customer_id;
    public $points;
    public $type;
    public $description;
    public $reference_type;
    public $reference_id;
    public $balance_after;
    public $created_by;
    public $created_at;
    
    public function __construct($data = []) {
        $this->id = $data['id'] ?? null;
        $this->customer_id = $data['customer_id'] ?? null;
        $this->points = $data['points'] ?? null;
        $this->type = $data['type'] ?? null;
        $this->description = $data['description'] ?? null;
        $this->reference_type = $data['reference_type'] ?? null;
        $this->reference_id = $data['reference_id'] ?? null;
        $this->balance_after = $data['balance_after'] ?? null;
        $this->created_by = $data['created_by'] ?? null;
        $this->created_at = $data['created_at'] ?? null;
    }
    
    public function toArray() {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'points' => $this->points,
            'type' => $this->type,
            'description' => $this->description,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'balance_after' => $this->balance_after,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at
        ];
    }
}
