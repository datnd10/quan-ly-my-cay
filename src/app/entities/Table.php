<?php

/**
 * Table Entity
 * 
 * Đại diện cho bàn ăn trong nhà hàng
 */

class Table {
    public $id;
    public $table_number;
    public $capacity;
    public $status; // AVAILABLE, OCCUPIED, RESERVED, MAINTENANCE
    public $is_deleted; // 0 = active, 1 = deleted (soft delete)
    public $created_at;
    public $updated_at;
    
    public function __construct($data = []) {
        if (!empty($data)) {
            $this->id = $data['id'] ?? null;
            $this->table_number = $data['table_number'] ?? null;
            $this->capacity = $data['capacity'] ?? 4;
            $this->status = $data['status'] ?? 'AVAILABLE';
            $this->is_deleted = $data['is_deleted'] ?? 0;
            $this->created_at = $data['created_at'] ?? null;
            $this->updated_at = $data['updated_at'] ?? null;
        }
    }
    
    /**
     * Convert to array
     */
    public function toArray() {
        return [
            'id' => (int)$this->id,
            'table_number' => $this->table_number,
            'capacity' => (int)$this->capacity,
            'status' => $this->status,
            'is_deleted' => (int)$this->is_deleted,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }
}
