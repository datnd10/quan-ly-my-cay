<?php

/**
 * User Entity
 * 
 * Đại diện cho một user trong hệ thống
 */

class User {
    private $id;
    private $username;
    private $password;
    private $role;
    private $status;
    private $createdAt;
    private $updatedAt;
    
    public function __construct($data = []) {
        $this->id = $data['id'] ?? null;
        $this->username = $data['username'] ?? null;
        $this->password = $data['password'] ?? null;
        $this->role = $data['role'] ?? ROLE_CUSTOMER;
        $this->status = $data['status'] ?? USER_STATUS_ACTIVE;
        $this->createdAt = $data['created_at'] ?? null;
        $this->updatedAt = $data['updated_at'] ?? null;
    }
    
    // Getters
    public function getId() {
        return $this->id;
    }
    
    public function getUsername() {
        return $this->username;
    }
    
    public function getPassword() {
        return $this->password;
    }
    
    public function getRole() {
        return $this->role;
    }
    
    public function getStatus() {
        return $this->status;
    }
    
    public function getCreatedAt() {
        return $this->createdAt;
    }
    
    public function getUpdatedAt() {
        return $this->updatedAt;
    }
    
    // Setters
    public function setUsername($username) {
        $this->username = $username;
        return $this;
    }
    
    public function setPassword($password) {
        $this->password = $password;
        return $this;
    }
    
    public function setRole($role) {
        $this->role = $role;
        return $this;
    }
    
    public function setStatus($status) {
        $this->status = $status;
        return $this;
    }
    
    // Business methods
    public function isActive() {
        return $this->status == USER_STATUS_ACTIVE;
    }
    
    public function isAdmin() {
        return $this->role === ROLE_ADMIN;
    }
    
    public function isStaff() {
        return $this->role === ROLE_STAFF;
    }
    
    public function isCustomer() {
        return $this->role === ROLE_CUSTOMER;
    }
    
    public function verifyPassword($plainPassword) {
        return password_verify($plainPassword, $this->password);
    }
    
    public function hashPassword($plainPassword) {
        $this->password = password_hash($plainPassword, PASSWORD_DEFAULT);
        return $this;
    }
    
    // Convert to array (for JSON response)
    public function toArray($includePassword = false) {
        $data = [
            'id' => $this->id,
            'username' => $this->username,
            'role' => $this->role,
            'status' => $this->status,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt
        ];
        
        if ($includePassword) {
            $data['password'] = $this->password;
        }
        
        return $data;
    }
    
    // Create from array
    public static function fromArray($data) {
        return new self($data);
    }
}
