<?php

/**
 * Table Repository
 * 
 * Xử lý truy vấn database cho tables
 */

class TableRepository {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Lấy tất cả tables
     */
    public function getAllTables($filters = []) {
        $sql = "SELECT * FROM tables WHERE 1=1";
        $params = [];
        
        // Filter theo status
        if (!empty($filters['status'])) {
            $sql .= " AND status = ?";
            $params[] = $filters['status'];
        }
        
        // Filter theo capacity
        if (!empty($filters['min_capacity'])) {
            $sql .= " AND capacity >= ?";
            $params[] = $filters['min_capacity'];
        }
        
        $sql .= " ORDER BY table_number ASC";
        
        $rows = $this->db->fetchAll($sql, $params);
        
        $tables = [];
        foreach ($rows as $row) {
            $tables[] = new Table($row);
        }
        
        return $tables;
    }
    
    /**
     * Lấy tables với phân trang
     */
    public function getTables($page = 1, $perPage = 20, $filters = []) {
        $offset = ($page - 1) * $perPage;
        
        $sql = "SELECT * FROM tables WHERE 1=1";
        $params = [];
        
        // Filter theo status
        if (!empty($filters['status'])) {
            $sql .= " AND status = ?";
            $params[] = $filters['status'];
        }
        
        // Filter theo capacity
        if (!empty($filters['min_capacity'])) {
            $sql .= " AND capacity >= ?";
            $params[] = $filters['min_capacity'];
        }
        
        // Search theo table_number
        if (!empty($filters['search'])) {
            $sql .= " AND table_number LIKE ?";
            $params[] = '%' . $filters['search'] . '%';
        }
        
        // Đếm tổng số
        $countSql = preg_replace('/SELECT .+ FROM/', 'SELECT COUNT(*) as total FROM', $sql);
        $total = $this->db->fetchOne($countSql, $params)['total'];
        
        // Lấy data với phân trang
        $sql .= " ORDER BY table_number ASC LIMIT ? OFFSET ?";
        $params[] = $perPage;
        $params[] = $offset;
        
        $rows = $this->db->fetchAll($sql, $params);
        
        $tables = [];
        foreach ($rows as $row) {
            $tables[] = new Table($row);
        }
        
        return [
            'tables' => $tables,
            'total' => (int)$total
        ];
    }
    
    /**
     * Lấy table theo ID
     */
    public function findById($id) {
        $sql = "SELECT * FROM tables WHERE id = ?";
        $row = $this->db->fetchOne($sql, [$id]);
        
        return $row ? new Table($row) : null;
    }
    
    /**
     * Lấy table theo table_number
     */
    public function findByTableNumber($tableNumber) {
        $sql = "SELECT * FROM tables WHERE table_number = ?";
        $row = $this->db->fetchOne($sql, [$tableNumber]);
        
        return $row ? new Table($row) : null;
    }
    
    /**
     * Tạo table mới
     */
    public function create($data) {
        $sql = "INSERT INTO tables (table_number, capacity, status)
                VALUES (?, ?, ?)";
        
        $params = [
            $data['table_number'],
            $data['capacity'] ?? 4,
            $data['status'] ?? 'AVAILABLE'
        ];
        
        $this->db->execute($sql, $params);
        $id = $this->db->lastInsertId();
        
        return $this->findById($id);
    }
    
    /**
     * Cập nhật table
     */
    public function update($id, $data) {
        $fields = [];
        $params = [];
        
        if (isset($data['table_number'])) {
            $fields[] = "table_number = ?";
            $params[] = $data['table_number'];
        }
        
        if (isset($data['capacity'])) {
            $fields[] = "capacity = ?";
            $params[] = $data['capacity'];
        }
        
        if (isset($data['status'])) {
            $fields[] = "status = ?";
            $params[] = $data['status'];
        }
        
        if (empty($fields)) {
            return $this->findById($id);
        }
        
        $params[] = $id;
        $sql = "UPDATE tables SET " . implode(', ', $fields) . " WHERE id = ?";
        
        $this->db->execute($sql, $params);
        
        return $this->findById($id);
    }
    
    /**
     * Cập nhật status
     */
    public function updateStatus($id, $status) {
        $sql = "UPDATE tables SET status = ? WHERE id = ?";
        $this->db->execute($sql, [$status, $id]);
        
        return $this->findById($id);
    }
    
    /**
     * Xóa table
     */
    public function delete($id) {
        $sql = "DELETE FROM tables WHERE id = ?";
        return $this->db->execute($sql, [$id]);
    }
    
    /**
     * Kiểm tra table_number đã tồn tại chưa
     */
    public function existsByTableNumber($tableNumber, $excludeId = null) {
        $sql = "SELECT COUNT(*) as count FROM tables WHERE table_number = ?";
        $params = [$tableNumber];
        
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $result = $this->db->fetchOne($sql, $params);
        return $result['count'] > 0;
    }
    
    /**
     * Lấy tables theo status
     */
    public function getByStatus($status) {
        $sql = "SELECT * FROM tables WHERE status = ? ORDER BY table_number ASC";
        $rows = $this->db->fetchAll($sql, [$status]);
        
        $tables = [];
        foreach ($rows as $row) {
            $tables[] = new Table($row);
        }
        
        return $tables;
    }
}
