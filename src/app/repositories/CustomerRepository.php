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
     * Lấy customers có phân trang
     */
    public function paginate($page, $perPage, $filters = []) {
        $offset = ($page - 1) * $perPage;
        $where = [];
        $params = [];
        
        // Build where clause
        if (!empty($filters['name'])) {
            $where[] = "name LIKE :name";
            $params['name'] = '%' . $filters['name'] . '%';
        }
        
        if (!empty($filters['phone'])) {
            $where[] = "phone LIKE :phone";
            $params['phone'] = '%' . $filters['phone'] . '%';
        }
        
        if (!empty($filters['email'])) {
            $where[] = "email LIKE :email";
            $params['email'] = '%' . $filters['email'] . '%';
        }
        
        $sql = "SELECT * FROM {$this->table}";
        
        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
        
        $params['limit'] = (int)$perPage;
        $params['offset'] = (int)$offset;
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Đếm tổng số customers
     */
    public function count($filters = []) {
        $where = [];
        $params = [];
        
        if (!empty($filters['name'])) {
            $where[] = "name LIKE :name";
            $params['name'] = '%' . $filters['name'] . '%';
        }
        
        if (!empty($filters['phone'])) {
            $where[] = "phone LIKE :phone";
            $params['phone'] = '%' . $filters['phone'] . '%';
        }
        
        if (!empty($filters['email'])) {
            $where[] = "email LIKE :email";
            $params['email'] = '%' . $filters['email'] . '%';
        }
        
        $sql = "SELECT COUNT(*) as total FROM {$this->table}";
        
        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        
        $result = $this->db->fetchOne($sql, $params);
        return $result && isset($result['total']) ? (int)$result['total'] : 0;
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
        return $result && $result['count'] > 0;
    }
    
    /**
     * Cập nhật điểm tích lũy
     */
    public function updatePoints($id, $points) {
        $sql = "UPDATE {$this->table} SET points = points + :points WHERE id = :id";
        return $this->db->query($sql, ['points' => $points, 'id' => $id]);
    }
}
