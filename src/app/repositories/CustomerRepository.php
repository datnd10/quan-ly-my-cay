<?php

/**
 * Customer Repository
 * 
 * Data access layer cho customers
 */

class CustomerRepository {
    private $db;
    private $table = 'customers';
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Tìm customer theo ID
     */
    public function findById($id) {
        $sql = "SELECT * FROM {$this->table} WHERE id = :id";
        return $this->db->fetchOne($sql, ['id' => $id]);
    }
    
    /**
     * Tìm customer theo user_id
     */
    public function findByUserId($userId) {
        $sql = "SELECT * FROM {$this->table} WHERE user_id = :user_id";
        return $this->db->fetchOne($sql, ['user_id' => $userId]);
    }
    
    /**
     * Tìm customer theo phone
     */
    public function findByPhone($phone) {
        $sql = "SELECT * FROM {$this->table} WHERE phone = :phone";
        return $this->db->fetchOne($sql, ['phone' => $phone]);
    }
    
    /**
     * Tạo customer mới
     */
    public function create($data) {
        return $this->db->insert($this->table, $data);
    }
    
    /**
     * Cập nhật customer
     */
    public function update($id, $data) {
        $where = "id = :id";
        return $this->db->update($this->table, $data, $where, ['id' => $id]);
    }
    
    /**
     * Kiểm tra phone đã tồn tại
     */
    public function phoneExists($phone, $excludeId = null) {
        $sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE phone = :phone";
        $params = ['phone' => $phone];
        
        if ($excludeId) {
            $sql .= " AND id != :id";
            $params['id'] = $excludeId;
        }
        
        $result = $this->db->fetchOne($sql, $params);
        return $result['count'] > 0;
    }
    
    /**
     * Cập nhật điểm tích lũy
     */
    public function updatePoints($id, $points) {
        $sql = "UPDATE {$this->table} SET points = points + :points WHERE id = :id";
        return $this->db->query($sql, ['points' => $points, 'id' => $id]);
    }
}
