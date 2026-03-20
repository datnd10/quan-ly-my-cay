<?php

/**
 * Category Repository
 * 
 * Data access layer cho categories
 */

class CategoryRepository {
    private $db;
    private $table = 'categories';
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Lấy tất cả categories
     */
    public function getAll() {
        $sql = "SELECT * FROM {$this->table} ORDER BY name ASC";
        return $this->db->fetchAll($sql);
    }
    
    /**
     * Lấy categories có phân trang
     */
    public function paginate($page, $perPage, $filters = []) {
        $offset = ($page - 1) * $perPage;
        $where = [];
        $params = [];
        
        // Build where clause
        if (!empty($filters['search'])) {
            $where[] = "name LIKE :search";
            $params['search'] = '%' . $filters['search'] . '%';
        }
        
        $sql = "SELECT * FROM {$this->table}";
        
        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        
        $sql .= " ORDER BY name ASC LIMIT :limit OFFSET :offset";
        
        $params['limit'] = (int)$perPage;
        $params['offset'] = (int)$offset;
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Đếm tổng số categories
     */
    public function count($filters = []) {
        $where = [];
        $params = [];
        
        if (!empty($filters['search'])) {
            $where[] = "name LIKE :search";
            $params['search'] = '%' . $filters['search'] . '%';
        }
        
        $sql = "SELECT COUNT(*) as total FROM {$this->table}";
        
        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        
        $result = $this->db->fetchOne($sql, $params);
        return $result ? (int)$result['total'] : 0;
    }
    
    /**
     * Tìm category theo ID
     */
    public function findById($id) {
        $sql = "SELECT * FROM {$this->table} WHERE id = :id";
        return $this->db->fetchOne($sql, ['id' => $id]);
    }
    
    /**
     * Tìm category theo tên
     */
    public function findByName($name) {
        $sql = "SELECT * FROM {$this->table} WHERE name = :name";
        return $this->db->fetchOne($sql, ['name' => $name]);
    }
    
    /**
     * Tạo category mới
     */
    public function create($data) {
        return $this->db->insert($this->table, $data);
    }
    
    /**
     * Cập nhật category
     */
    public function update($id, $data) {
        $where = "id = :id";
        return $this->db->update($this->table, $data, $where, ['id' => $id]);
    }
    
    /**
     * Xóa category
     */
    public function delete($id) {
        $where = "id = :id";
        return $this->db->delete($this->table, $where, ['id' => $id]);
    }
    
    /**
     * Kiểm tra tên đã tồn tại
     */
    public function nameExists($name, $excludeId = null) {
        $sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE name = :name";
        $params = ['name' => $name];
        
        if ($excludeId) {
            $sql .= " AND id != :id";
            $params['id'] = $excludeId;
        }
        
        $result = $this->db->fetchOne($sql, $params);
        return $result && $result['count'] > 0;
    }
    
    /**
     * Đếm số sản phẩm trong category
     */
    public function countProducts($categoryId) {
        $sql = "SELECT COUNT(*) as count FROM products WHERE category_id = :category_id";
        $result = $this->db->fetchOne($sql, ['category_id' => $categoryId]);
        return $result ? (int)$result['count'] : 0;
    }
}
