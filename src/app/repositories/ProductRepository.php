<?php

/**
 * Product Repository
 * 
 * Xử lý truy vấn database cho products
 */

class ProductRepository {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Lấy danh sách products với phân trang và filter
     * Updated: 2026-03-20
     */
    public function getProducts($page = 1, $perPage = 20, $filters = []) {
        $offset = ($page - 1) * $perPage;
        
        $sql = "SELECT p.*, c.name as category_name 
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE 1=1";
        
        $params = [];
        
        // Filter theo category
        if (!empty($filters['category_id'])) {
            $sql .= " AND p.category_id = ?";
            $params[] = $filters['category_id'];
        }
        
        // Filter theo status
        if (isset($filters['status'])) {
            $sql .= " AND p.status = ?";
            $params[] = $filters['status'];
        }
        
        // Search theo tên
        if (!empty($filters['search'])) {
            $sql .= " AND p.name LIKE ?";
            $params[] = '%' . $filters['search'] . '%';
        }
        
        // Filter sản phẩm sắp hết hàng
        if (!empty($filters['low_stock'])) {
            $sql .= " AND p.stock_quantity <= p.min_stock";
        }
        
        // Đếm tổng số - Sử dụng subquery để đảm bảo đúng
        $countSql = "SELECT COUNT(*) as total FROM products p WHERE 1=1";
        $countParams = [];
        
        // Áp dụng lại các filter cho count query
        if (!empty($filters['category_id'])) {
            $countSql .= " AND p.category_id = ?";
            $countParams[] = $filters['category_id'];
        }
        
        if (isset($filters['status'])) {
            $countSql .= " AND p.status = ?";
            $countParams[] = $filters['status'];
        }
        
        if (!empty($filters['search'])) {
            $countSql .= " AND p.name LIKE ?";
            $countParams[] = '%' . $filters['search'] . '%';
        }
        
        if (!empty($filters['low_stock'])) {
            $countSql .= " AND p.stock_quantity <= p.min_stock";
        }
        
        $countResult = $this->db->fetchOne($countSql, $countParams);
        $total = $countResult && isset($countResult['total']) ? (int)$countResult['total'] : 0;
        
        // Lấy data với phân trang
        $sql .= " ORDER BY p.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $perPage;
        $params[] = $offset;
        
        $rows = $this->db->fetchAll($sql, $params);
        
        $products = [];
        foreach ($rows as $row) {
            $products[] = new Product($row);
        }
        
        return [
            'products' => $products,
            'total' => (int)$total
        ];
    }
    
    /**
     * Lấy product theo ID
     */
    public function findById($id) {
        $sql = "SELECT p.*, c.name as category_name 
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE p.id = ?";
        
        $row = $this->db->fetchOne($sql, [$id]);
        
        return $row ? new Product($row) : null;
    }
    
    /**
     * Tạo product mới
     */
    public function create($data) {
        $sql = "INSERT INTO products (category_id, name, price, description, image_url, stock_quantity, min_stock, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $data['category_id'] ?? null,
            $data['name'],
            $data['price'],
            $data['description'] ?? null,
            $data['image_url'] ?? null,
            $data['stock_quantity'] ?? 0,
            $data['min_stock'] ?? 0,
            $data['status'] ?? 1
        ];
        
        $this->db->query($sql, $params);
        $id = $this->db->getConnection()->lastInsertId();
        
        return $this->findById($id);
    }
    
    /**
     * Cập nhật product
     */
    public function update($id, $data) {
        $fields = [];
        $params = [];
        
        if (isset($data['category_id'])) {
            $fields[] = "category_id = ?";
            $params[] = $data['category_id'];
        }
        
        if (isset($data['name'])) {
            $fields[] = "name = ?";
            $params[] = $data['name'];
        }
        
        if (isset($data['price'])) {
            $fields[] = "price = ?";
            $params[] = $data['price'];
        }
        
        if (isset($data['description'])) {
            $fields[] = "description = ?";
            $params[] = $data['description'];
        }
        
        if (isset($data['image_url'])) {
            $fields[] = "image_url = ?";
            $params[] = $data['image_url'];
        }
        
        if (isset($data['stock_quantity'])) {
            $fields[] = "stock_quantity = ?";
            $params[] = $data['stock_quantity'];
        }
        
        if (isset($data['min_stock'])) {
            $fields[] = "min_stock = ?";
            $params[] = $data['min_stock'];
        }
        
        if (isset($data['status'])) {
            $fields[] = "status = ?";
            $params[] = $data['status'];
        }
        
        if (empty($fields)) {
            return $this->findById($id);
        }
        
        $params[] = $id;
        $sql = "UPDATE products SET " . implode(', ', $fields) . " WHERE id = ?";
        
        $this->db->query($sql, $params);
        
        return $this->findById($id);
    }
    
    /**
     * Xóa product
     */
    public function delete($id) {
        $sql = "DELETE FROM products WHERE id = ?";
        return $this->db->query($sql, [$id]);
    }
    
    /**
     * Cập nhật stock quantity
     */
    public function updateStock($id, $quantity) {
        $sql = "UPDATE products SET stock_quantity = ? WHERE id = ?";
        return $this->db->query($sql, [$quantity, $id]);
    }
    
    /**
     * Tăng/giảm stock quantity
     */
    public function adjustStock($id, $amount) {
        $sql = "UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?";
        return $this->db->query($sql, [$amount, $id]);
    }
    
    /**
     * Kiểm tra product có tồn tại không
     */
    public function exists($id) {
        $sql = "SELECT COUNT(*) as count FROM products WHERE id = ?";
        $result = $this->db->fetchOne($sql, [$id]);
        return $result && $result['count'] > 0;
    }
    
    /**
     * Kiểm tra tên sản phẩm đã tồn tại trong category chưa
     */
    public function existsByNameAndCategory($name, $categoryId, $excludeId = null) {
        $sql = "SELECT COUNT(*) as count FROM products WHERE name = ? AND category_id = ?";
        $params = [$name, $categoryId];
        
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $result = $this->db->fetchOne($sql, $params);
        return $result && $result['count'] > 0;
    }
    
    /**
     * Lấy sản phẩm sắp hết hàng
     */
    public function getLowStockProducts() {
        $sql = "SELECT p.*, c.name as category_name 
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE p.stock_quantity <= p.min_stock AND p.status = 1
                ORDER BY p.stock_quantity ASC";
        
        $rows = $this->db->fetchAll($sql);
        
        $products = [];
        foreach ($rows as $row) {
            $products[] = new Product($row);
        }
        
        return $products;
    }
}
