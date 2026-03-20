<?php

/**
 * Category Entity
 * 
 * Đại diện cho danh mục món ăn
 */

class Category {
    public $id;
    public $name;
    public $created_at;
    
    public function __construct($data = []) {
        $this->id = $data['id'] ?? null;
        $this->name = $data['name'] ?? null;
        $this->created_at = $data['created_at'] ?? null;
    }
    
    public function toArray() {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'created_at' => $this->created_at
        ];
    }
}
