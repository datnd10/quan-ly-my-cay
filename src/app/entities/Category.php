<?php

/**
 * Category Entity
 * 
 * Đại diện cho danh mục món ăn
 */

class Category {
    public $id;
    public $name;
    public $status; // 1 = active, 0 = deleted (soft delete)
    public $created_at;
    
    public function __construct($data = []) {
        $this->id = $data['id'] ?? null;
        $this->name = $data['name'] ?? null;
        $this->status = $data['status'] ?? 1;
        $this->created_at = $data['created_at'] ?? null;
    }
    
    public function toArray() {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'status' => $this->status,
            'created_at' => $this->created_at
        ];
    }
}
