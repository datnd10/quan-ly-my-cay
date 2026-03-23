<?php

/**
 * Order Repository
 * 
 * Xử lý database operations cho orders
 */

class OrderRepository {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Lấy danh sách orders với phân trang và filter
     */
    public function getOrders($page, $perPage, $filters = []) {
        $offset = ($page - 1) * $perPage;
        
        // Build WHERE clause
        $where = [];
        $params = [];
        
        if (!empty($filters['status'])) {
            $where[] = "o.status = :status";
            $params[':status'] = $filters['status'];
        }
        
        if (!empty($filters['customer_id'])) {
            $where[] = "o.customer_id = :customer_id";
            $params[':customer_id'] = $filters['customer_id'];
        }
        
        if (!empty($filters['table_id'])) {
            $where[] = "o.table_id = :table_id";
            $params[':table_id'] = $filters['table_id'];
        }
        
        if (!empty($filters['from_date'])) {
            $where[] = "DATE(o.created_at) >= :from_date";
            $params[':from_date'] = $filters['from_date'];
        }
        
        if (!empty($filters['to_date'])) {
            $where[] = "DATE(o.created_at) <= :to_date";
            $params[':to_date'] = $filters['to_date'];
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        // Count total
        $countSql = "SELECT COUNT(*) as total FROM orders o $whereClause";
        $result = $this->db->fetchOne($countSql, $params);
        $total = $result && isset($result['total']) ? (int)$result['total'] : 0;
        
        // Get orders
        $sql = "SELECT o.*, 
                       c.name as customer_name, c.phone as customer_phone,
                       t.table_number, t.capacity as table_capacity
                FROM orders o
                LEFT JOIN customers c ON o.customer_id = c.id
                LEFT JOIN tables t ON o.table_id = t.id
                $whereClause
                ORDER BY o.created_at DESC
                LIMIT :limit OFFSET :offset";
        
        $params[':limit'] = $perPage;
        $params[':offset'] = $offset;
        
        $rows = $this->db->fetchAll($sql, $params);
        
        $orders = [];
        foreach ($rows as $row) {
            $order = new Order($row);
            
            // Attach customer info
            if ($row['customer_name']) {
                $order->customer = [
                    'id' => (int)$row['customer_id'],
                    'name' => $row['customer_name'],
                    'phone' => $row['customer_phone']
                ];
            }
            
            // Attach table info
            if ($row['table_number']) {
                $order->table = [
                    'id' => (int)$row['table_id'],
                    'table_number' => $row['table_number'],
                    'capacity' => (int)$row['table_capacity']
                ];
            }
            
            $orders[] = $order;
        }
        
        return [
            'orders' => $orders,
            'total' => $total
        ];
    }

    
    /**
     * Tìm order theo ID với items
     */
    public function findById($id, $withItems = true) {
        $sql = "SELECT o.*, 
                       c.name as customer_name, c.phone as customer_phone,
                       t.table_number, t.capacity as table_capacity, t.status as table_status
                FROM orders o
                LEFT JOIN customers c ON o.customer_id = c.id
                LEFT JOIN tables t ON o.table_id = t.id
                WHERE o.id = :id";
        
        $row = $this->db->fetchOne($sql, [':id' => $id]);
        
        if (!$row) {
            return null;
        }
        
        $order = new Order($row);
        
        // Attach customer info
        if ($row['customer_name']) {
            $order->customer = [
                'id' => (int)$row['customer_id'],
                'name' => $row['customer_name'],
                'phone' => $row['customer_phone']
            ];
        }
        
        // Attach table info
        if ($row['table_number']) {
            $order->table = [
                'id' => (int)$row['table_id'],
                'table_number' => $row['table_number'],
                'capacity' => (int)$row['table_capacity'],
                'status' => $row['table_status']
            ];
        }
        
        // Load items
        if ($withItems) {
            $order->items = $this->getOrderItems($id);
        }
        
        return $order;
    }
    
    /**
     * Lấy items của order
     */
    public function getOrderItems($orderId) {
        $sql = "SELECT oi.*, 
                       p.name as product_name, p.image_url as product_image
                FROM order_items oi
                LEFT JOIN products p ON oi.product_id = p.id
                WHERE oi.order_id = :order_id
                ORDER BY oi.id";
        
        $rows = $this->db->fetchAll($sql, [':order_id' => $orderId]);
        
        $items = [];
        foreach ($rows as $row) {
            $item = new OrderItem($row);
            
            // Attach product info
            if ($row['product_name']) {
                $item->product = [
                    'id' => (int)$row['product_id'],
                    'name' => $row['product_name'],
                    'image_url' => $row['product_image']
                ];
            }
            
            $items[] = $item;
        }
        
        return $items;
    }
    
    /**
     * Tìm draft order của customer
     */
    public function findDraftByCustomer($customerId) {
        $sql = "SELECT * FROM orders 
                WHERE customer_id = :customer_id AND status = 'DRAFT'
                ORDER BY created_at DESC LIMIT 1";
        
        $row = $this->db->fetchOne($sql, [':customer_id' => $customerId]);
        
        if (!$row) {
            return null;
        }
        
        return $this->findById($row['id']);
    }
    
    /**
     * Tìm order đang active của bàn
     */
    public function findActiveByTable($tableId) {
        $sql = "SELECT * FROM orders 
                WHERE table_id = :table_id 
                AND status NOT IN ('COMPLETED', 'CANCELLED')
                ORDER BY created_at DESC LIMIT 1";
        
        $row = $this->db->fetchOne($sql, [':table_id' => $tableId]);
        
        if (!$row) {
            return null;
        }
        
        return $this->findById($row['id']);
    }
    
    /**
     * Tạo order mới
     */
    public function create($data) {
        $sql = "INSERT INTO orders (customer_id, table_id, status, total_amount, discount_amount, final_amount, created_at)
                VALUES (:customer_id, :table_id, :status, :total_amount, :discount_amount, :final_amount, NOW())";
        
        $params = [
            ':customer_id' => $data['customer_id'] ?? null,
            ':table_id' => $data['table_id'] ?? null,
            ':status' => $data['status'] ?? Order::STATUS_DRAFT,
            ':total_amount' => $data['total_amount'] ?? 0,
            ':discount_amount' => $data['discount_amount'] ?? 0,
            ':final_amount' => $data['final_amount'] ?? 0
        ];
        
        $this->db->query($sql, $params);
        $orderId = $this->db->getConnection()->lastInsertId();
        
        return $this->findById($orderId);
    }
    
    /**
     * Cập nhật order
     */
    public function update($id, $data) {
        $fields = [];
        $params = [':id' => $id];
        
        $allowedFields = ['customer_id', 'table_id', 'voucher_id', 'voucher_discount', 
                          'status', 'total_amount', 'discount_amount', 'final_amount'];
        
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }
        
        if (empty($fields)) {
            return $this->findById($id);
        }
        
        $sql = "UPDATE orders SET " . implode(', ', $fields) . " WHERE id = :id";
        $this->db->query($sql, $params);
        
        return $this->findById($id);
    }
    
    /**
     * Cập nhật status
     */
    public function updateStatus($id, $status, $note = null) {
        $sql = "UPDATE orders SET status = :status";
        $params = [':id' => $id, ':status' => $status];
        
        // Set completed_at nếu status = COMPLETED
        if ($status === Order::STATUS_COMPLETED) {
            $sql .= ", completed_at = NOW()";
        }
        
        $sql .= " WHERE id = :id";
        
        $this->db->query($sql, $params);
        
        return $this->findById($id);
    }
    
    /**
     * Thêm item vào order
     */
    public function addItem($orderId, $productId, $quantity, $price) {
        // Check if item already exists
        $existingSql = "SELECT * FROM order_items WHERE order_id = :order_id AND product_id = :product_id";
        $existing = $this->db->fetchOne($existingSql, [
            ':order_id' => $orderId,
            ':product_id' => $productId
        ]);
        
        if ($existing) {
            // Update quantity
            $newQuantity = $existing['quantity'] + $quantity;
            $sql = "UPDATE order_items SET quantity = :quantity WHERE id = :id";
            $this->db->query($sql, [
                ':id' => $existing['id'],
                ':quantity' => $newQuantity
            ]);
            return (int)$existing['id'];
        } else {
            // Insert new item
            $sql = "INSERT INTO order_items (order_id, product_id, quantity, price)
                    VALUES (:order_id, :product_id, :quantity, :price)";
            
            $this->db->query($sql, [
                ':order_id' => $orderId,
                ':product_id' => $productId,
                ':quantity' => $quantity,
                ':price' => $price
            ]);
            
            return (int)$this->db->getConnection()->lastInsertId();
        }
    }
    
    /**
     * Cập nhật item
     */
    public function updateItem($itemId, $quantity) {
        $sql = "UPDATE order_items SET quantity = :quantity WHERE id = :id";
        $this->db->query($sql, [
            ':id' => $itemId,
            ':quantity' => $quantity
        ]);
    }
    
    /**
     * Xóa item
     */
    public function deleteItem($itemId) {
        $sql = "DELETE FROM order_items WHERE id = :id";
        $this->db->query($sql, [':id' => $itemId]);
    }
    
    /**
     * Xóa tất cả items của order
     */
    public function deleteAllItems($orderId) {
        $sql = "DELETE FROM order_items WHERE order_id = :order_id";
        $this->db->query($sql, [':order_id' => $orderId]);
    }
    
    /**
     * Xóa order
     */
    public function delete($id) {
        // Items sẽ tự động xóa do CASCADE
        $sql = "DELETE FROM orders WHERE id = :id";
        $this->db->query($sql, [':id' => $id]);
    }
    
    /**
     * Xóa draft orders cũ
     */
    public function deleteOldDrafts($days = 7) {
        $sql = "DELETE FROM orders 
                WHERE status = 'DRAFT' 
                AND created_at < DATE_SUB(NOW(), INTERVAL :days DAY)";
        
        $this->db->query($sql, [':days' => $days]);
    }
    
    /**
     * Lưu lịch sử thay đổi status
     */
    public function logStatusChange($orderId, $oldStatus, $newStatus, $changedBy, $note = null) {
        $sql = "INSERT INTO order_status_history (order_id, old_status, new_status, changed_by, note, created_at)
                VALUES (:order_id, :old_status, :new_status, :changed_by, :note, NOW())";
        
        $this->db->query($sql, [
            ':order_id' => $orderId,
            ':old_status' => $oldStatus,
            ':new_status' => $newStatus,
            ':changed_by' => $changedBy,
            ':note' => $note
        ]);
    }
    
    /**
     * Lấy lịch sử status của order
     */
    public function getStatusHistory($orderId) {
        $sql = "SELECT h.*, u.username as changed_by_username
                FROM order_status_history h
                LEFT JOIN users u ON h.changed_by = u.id
                WHERE h.order_id = :order_id
                ORDER BY h.created_at DESC";
        
        return $this->db->fetchAll($sql, [':order_id' => $orderId]);
    }
}
