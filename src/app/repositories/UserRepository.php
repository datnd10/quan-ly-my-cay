<?php

/**
 * User Repository
 * 
 * Data access layer cho users
 */

class UserRepository {
    private $db;
    private $table = 'users';
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Lấy tất cả users
     */
    public function getAll() {
        $sql = "SELECT * FROM {$this->table} ORDER BY created_at DESC";
        return $this->db->fetchAll($sql);
    }
    
    /**
     * Lấy users có phân trang
     */
    public function paginate($page, $perPage, $filters = []) {
        $offset = ($page - 1) * $perPage;
        $where = [];
        $params = [];
        
        // Build where clause
        if (!empty($filters['role'])) {
            $where[] = "role = :role";
            $params['role'] = $filters['role'];
        }
        
        if (!empty($filters['status'])) {
            $where[] = "status = :status";
            $params['status'] = $filters['status'];
        }
        
        if (!empty($filters['username'])) {
            $where[] = "username LIKE :username";
            $params['username'] = '%' . $filters['username'] . '%';
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
     * Đếm tổng số users
     */
    public function count($filters = []) {
        $where = [];
        $params = [];
        
        if (!empty($filters['role'])) {
            $where[] = "role = :role";
            $params['role'] = $filters['role'];
        }
        
        if (!empty($filters['status'])) {
            $where[] = "status = :status";
            $params['status'] = $filters['status'];
        }
        
        if (!empty($filters['username'])) {
            $where[] = "username LIKE :username";
            $params['username'] = '%' . $filters['username'] . '%';
        }
        
        $sql = "SELECT COUNT(*) as total FROM {$this->table}";
        
        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        
        $result = $this->db->fetchOne($sql, $params);
        return (int)$result['total'];
    }
    
    /**
     * Tìm user theo ID
     */
    public function findById($id) {
        $sql = "SELECT * FROM {$this->table} WHERE id = :id";
        return $this->db->fetchOne($sql, ['id' => $id]);
    }
    
    /**
     * Tìm user theo username
     */
    public function findByUsername($username) {
        $sql = "SELECT * FROM {$this->table} WHERE username = :username";
        return $this->db->fetchOne($sql, ['username' => $username]);
    }
    
    /**
     * Tạo user mới
     */
    public function create($data) {
        return $this->db->insert($this->table, $data);
    }
    
    /**
     * Cập nhật user
     */
    public function update($id, $data) {
        $where = "id = :id";
        return $this->db->update($this->table, $data, $where, ['id' => $id]);
    }
    
    /**
     * Xóa user
     */
    public function delete($id) {
        $where = "id = :id";
        return $this->db->delete($this->table, $where, ['id' => $id]);
    }
    
    /**
     * Kiểm tra username đã tồn tại
     */
    public function usernameExists($username, $excludeId = null) {
        $sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE username = :username";
        $params = ['username' => $username];
        
        if ($excludeId) {
            $sql .= " AND id != :id";
            $params['id'] = $excludeId;
        }
        
        $result = $this->db->fetchOne($sql, $params);
        return $result['count'] > 0;
    }
}
