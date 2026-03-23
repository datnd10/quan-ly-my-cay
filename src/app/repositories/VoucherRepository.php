<?php

/**
 * Voucher Repository
 * 
 * Xử lý truy vấn database cho vouchers
 */

class VoucherRepository {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Lấy danh sách vouchers với phân trang
     */
    public function getVouchers($page = 1, $perPage = 20, $filters = []) {
        $offset = ($page - 1) * $perPage;
        
        $sql = "SELECT * FROM vouchers WHERE 1=1";
        $params = [];
        
        // Filter theo status
        if (isset($filters['status'])) {
            $sql .= " AND status = ?";
            $params[] = $filters['status'];
        }
        
        // Filter theo discount_type
        if (!empty($filters['discount_type'])) {
            $sql .= " AND discount_type = ?";
            $params[] = $filters['discount_type'];
        }
        
        // Search theo code
        if (!empty($filters['search'])) {
            $sql .= " AND code LIKE ?";
            $params[] = '%' . $filters['search'] . '%';
        }
        
        // Filter voucher còn hiệu lực
        if (!empty($filters['active_only'])) {
            $now = date('Y-m-d H:i:s');
            $sql .= " AND status = 1";
            $sql .= " AND (start_date IS NULL OR start_date <= ?)";
            $sql .= " AND (expired_at IS NULL OR expired_at >= ?)";
            $sql .= " AND (usage_limit = 0 OR used_count < usage_limit)";
            $params[] = $now;
            $params[] = $now;
        }
        
        // Đếm tổng số
        $countSql = "SELECT COUNT(*) as total FROM vouchers WHERE 1=1";
        $countParams = [];
        
        if (isset($filters['status'])) {
            $countSql .= " AND status = ?";
            $countParams[] = $filters['status'];
        }
        
        if (!empty($filters['discount_type'])) {
            $countSql .= " AND discount_type = ?";
            $countParams[] = $filters['discount_type'];
        }
        
        if (!empty($filters['search'])) {
            $countSql .= " AND code LIKE ?";
            $countParams[] = '%' . $filters['search'] . '%';
        }
        
        if (!empty($filters['active_only'])) {
            $now = date('Y-m-d H:i:s');
            $countSql .= " AND status = 1";
            $countSql .= " AND (start_date IS NULL OR start_date <= ?)";
            $countSql .= " AND (expired_at IS NULL OR expired_at >= ?)";
            $countSql .= " AND (usage_limit = 0 OR used_count < usage_limit)";
            $countParams[] = $now;
            $countParams[] = $now;
        }
        
        $countResult = $this->db->fetchOne($countSql, $countParams);
        $total = $countResult && isset($countResult['total']) ? (int)$countResult['total'] : 0;
        
        // Lấy data với phân trang
        $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $perPage;
        $params[] = $offset;
        
        $rows = $this->db->fetchAll($sql, $params);
        
        $vouchers = [];
        foreach ($rows as $row) {
            $vouchers[] = new Voucher($row);
        }
        
        return [
            'vouchers' => $vouchers,
            'total' => $total
        ];
    }
    
    /**
     * Lấy voucher theo ID
     */
    public function findById($id) {
        $sql = "SELECT * FROM vouchers WHERE id = ?";
        $row = $this->db->fetchOne($sql, [$id]);
        
        return $row ? new Voucher($row) : null;
    }
    
    /**
     * Lấy voucher theo code
     */
    public function findByCode($code) {
        $sql = "SELECT * FROM vouchers WHERE code = ?";
        $row = $this->db->fetchOne($sql, [$code]);
        
        return $row ? new Voucher($row) : null;
    }
    
    /**
     * Tạo voucher mới
     */
    public function create($data) {
        $sql = "INSERT INTO vouchers (code, discount_type, discount_value, min_order_amount, 
                max_discount, usage_limit, used_count, start_date, expired_at, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $data['code'],
            $data['discount_type'],
            $data['discount_value'],
            $data['min_order_amount'] ?? 0,
            $data['max_discount'] ?? null,
            $data['usage_limit'] ?? 1,
            0, // used_count
            $data['start_date'] ?? null,
            $data['expired_at'] ?? null,
            $data['status'] ?? 1
        ];
        
        $this->db->query($sql, $params);
        $id = $this->db->getConnection()->lastInsertId();
        
        return $this->findById($id);
    }
    
    /**
     * Cập nhật voucher
     */
    public function update($id, $data) {
        $fields = [];
        $params = [];
        
        if (isset($data['code'])) {
            $fields[] = "code = ?";
            $params[] = $data['code'];
        }
        
        if (isset($data['discount_type'])) {
            $fields[] = "discount_type = ?";
            $params[] = $data['discount_type'];
        }
        
        if (isset($data['discount_value'])) {
            $fields[] = "discount_value = ?";
            $params[] = $data['discount_value'];
        }
        
        if (isset($data['min_order_amount'])) {
            $fields[] = "min_order_amount = ?";
            $params[] = $data['min_order_amount'];
        }
        
        if (isset($data['max_discount'])) {
            $fields[] = "max_discount = ?";
            $params[] = $data['max_discount'];
        }
        
        if (isset($data['usage_limit'])) {
            $fields[] = "usage_limit = ?";
            $params[] = $data['usage_limit'];
        }
        
        if (isset($data['start_date'])) {
            $fields[] = "start_date = ?";
            $params[] = $data['start_date'];
        }
        
        if (isset($data['expired_at'])) {
            $fields[] = "expired_at = ?";
            $params[] = $data['expired_at'];
        }
        
        if (isset($data['status'])) {
            $fields[] = "status = ?";
            $params[] = $data['status'];
        }
        
        if (empty($fields)) {
            return $this->findById($id);
        }
        
        $params[] = $id;
        $sql = "UPDATE vouchers SET " . implode(', ', $fields) . " WHERE id = ?";
        
        $this->db->query($sql, $params);
        
        return $this->findById($id);
    }
    
    /**
     * Xóa voucher
     */
    public function delete($id) {
        $sql = "DELETE FROM vouchers WHERE id = ?";
        $stmt = $this->db->query($sql, [$id]);
        return $stmt->rowCount();
    }
    
    /**
     * Tăng used_count
     */
    public function incrementUsedCount($id) {
        $sql = "UPDATE vouchers SET used_count = used_count + 1 WHERE id = ?";
        $stmt = $this->db->query($sql, [$id]);
        return $stmt->rowCount();
    }
    
    /**
     * Giảm used_count (khi hủy order)
     */
    public function decrementUsedCount($id) {
        $sql = "UPDATE vouchers SET used_count = GREATEST(0, used_count - 1) WHERE id = ?";
        $stmt = $this->db->query($sql, [$id]);
        return $stmt->rowCount();
    }
    
    /**
     * Kiểm tra code đã tồn tại chưa
     */
    public function existsByCode($code, $excludeId = null) {
        $sql = "SELECT COUNT(*) as count FROM vouchers WHERE code = ?";
        $params = [$code];
        
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $result = $this->db->fetchOne($sql, $params);
        return $result && isset($result['count']) ? $result['count'] > 0 : false;
    }
}
