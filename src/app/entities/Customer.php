<?php

/**
 * Customer Entity
 * 
 * Đại diện cho thông tin khách hàng
 */

class Customer {
    private $id;
    private $userId;
    private $name;
    private $phone;
    private $email;
    private $points;
    private $createdAt;
    private $updatedAt;
    
    public function __construct($data = []) {
        $this->id = $data['id'] ?? null;
        $this->userId = $data['user_id'] ?? null;
        $this->name = $data['name'] ?? null;
        $this->phone = $data['phone'] ?? null;
        $this->email = $data['email'] ?? null;
        $this->points = $data['points'] ?? 0;
        $this->createdAt = $data['created_at'] ?? null;
        $this->updatedAt = $data['updated_at'] ?? null;
    }
    
    // Getters
    public function getId() {
        return $this->id;
    }
    
    public function getUserId() {
        return $this->userId;
    }
    
    public function getName() {
        return $this->name;
    }
    
    public function getPhone() {
        return $this->phone;
    }
    
    public function getEmail() {
        return $this->email;
    }
    
    public function getPoints() {
        return $this->points;
    }
    
    public function getCreatedAt() {
        return $this->createdAt;
    }
    
    public function getUpdatedAt() {
        return $this->updatedAt;
    }
    
    // Setters
    public function setName($name) {
        $this->name = $name;
        return $this;
    }
    
    public function setPhone($phone) {
        $this->phone = $phone;
        return $this;
    }
    
    public function setEmail($email) {
        $this->email = $email;
        return $this;
    }
    
    public function setPoints($points) {
        $this->points = $points;
        return $this;
    }
    
    // Business methods
    public function addPoints($points) {
        $this->points += $points;
        return $this;
    }
    
    public function deductPoints($points) {
        if ($this->points >= $points) {
            $this->points -= $points;
            return true;
        }
        return false;
    }
    
    public function hasEnoughPoints($points) {
        return $this->points >= $points;
    }
    
    // Convert to array
    public function toArray() {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'points' => $this->points,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt
        ];
    }
    
    // Create from array
    public static function fromArray($data) {
        return new self($data);
    }
}
