<?php

/**
 * Point Transaction Repository
 * 
 * Data access layer cho point transactions
 */

class PointTransactionRepository {
    private $db;
    private $table = 'point_transactions';
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Lấy lịch sử điểm của customer
     */
    public function getByCustomerId($customerId, $page = 1, $perPage = 20) {
        $offset = ($page - 1) * $perPage;
        
        $sql = "SELECT * FROM {$this->table} 
                WHERE customer_id = :customer_id 
                ORDER BY created_at DESC 
                LIMIT :limit OFFSET :offset";
        
        return $this->db->fetchAll($sql, [
            'customer_id' => $customerId,
            'limit' => (int)$perPage,
            'offset' => (int)$offset
        ]);
    }
    
    /**
     * Đếm tổng số giao dịch của customer
     */
    public function countByCustomerId($customerId) {
        $sql = "SELECT COUNT(*) as total FROM {$this->table} WHERE customer_id = :customer_id";
        $result = $this->db->fetchOne($sql, ['customer_id' => $customerId]);
        return $result && isset($result['total']) ? (int)$result['total'] : 0;
    }
    
    /**
     * Tạo giao dịch điểm mới
     */
    public function create($data) {
        return $this->db->insert($this->table, $data);
    }
    
    /**
     * Lấy giao dịch theo ID
     */
    public function findById($id) {
        $sql = "SELECT * FROM {$this->table} WHERE id = :id";
        return $this->db->fetchOne($sql, ['id' => $id]);
    }
}
