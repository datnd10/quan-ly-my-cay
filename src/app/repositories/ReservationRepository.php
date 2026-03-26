<?php

/**
 * Reservation Repository
 * 
 * Data access layer cho reservations
 */

class ReservationRepository {
    private $db;
    private $table = 'reservations';
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Lấy reservations có phân trang
     */
    public function getReservations($page, $perPage, $filters = []) {
        $offset = ($page - 1) * $perPage;
        $where = [];
        $params = [];
        
        // Filter by status
        if (!empty($filters['status'])) {
            $where[] = "r.status = :status";
            $params[':status'] = $filters['status'];
        }
        
        // Filter by customer
        if (!empty($filters['customer_id'])) {
            $where[] = "r.customer_id = :customer_id";
            $params[':customer_id'] = $filters['customer_id'];
        }
        
        // Filter by table
        if (!empty($filters['table_id'])) {
            $where[] = "r.table_id = :table_id";
            $params[':table_id'] = $filters['table_id'];
        }
        
        // Filter by date
        if (!empty($filters['date'])) {
            $where[] = "DATE(r.reservation_time) = :date";
            $params[':date'] = $filters['date'];
        }
        
        // Filter by date range
        if (!empty($filters['from_date'])) {
            $where[] = "DATE(r.reservation_time) >= :from_date";
            $params[':from_date'] = $filters['from_date'];
        }
        
        if (!empty($filters['to_date'])) {
            $where[] = "DATE(r.reservation_time) <= :to_date";
            $params[':to_date'] = $filters['to_date'];
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        // Count total
        $countSql = "SELECT COUNT(*) as total FROM {$this->table} r $whereClause";
        $result = $this->db->fetchOne($countSql, $params);
        $total = $result && isset($result['total']) ? (int)$result['total'] : 0;
        
        // Get reservations
        $sql = "SELECT r.*, 
                       c.name as customer_name_rel, c.phone as customer_phone_rel,
                       t.table_number, t.capacity as table_capacity
                FROM {$this->table} r
                LEFT JOIN customers c ON r.customer_id = c.id
                LEFT JOIN tables t ON r.table_id = t.id
                $whereClause
                ORDER BY r.reservation_time DESC
                LIMIT :limit OFFSET :offset";
        
        $params[':limit'] = $perPage;
        $params[':offset'] = $offset;
        
        $rows = $this->db->fetchAll($sql, $params);
        
        $reservations = [];
        foreach ($rows as $row) {
            $reservation = new Reservation($row);
            
            // Attach customer info
            if ($row['customer_name_rel']) {
                $reservation->customer = [
                    'id' => (int)$row['customer_id'],
                    'name' => $row['customer_name_rel'],
                    'phone' => $row['customer_phone_rel']
                ];
            }
            
            // Attach table info
            if ($row['table_number']) {
                $reservation->table = [
                    'id' => (int)$row['table_id'],
                    'table_number' => $row['table_number'],
                    'capacity' => (int)$row['table_capacity']
                ];
            }
            
            $reservations[] = $reservation;
        }
        
        return [
            'reservations' => $reservations,
            'total' => $total
        ];
    }
    
    /**
     * Tìm reservation theo ID
     */
    public function findById($id) {
        $sql = "SELECT r.*, 
                       c.name as customer_name_rel, c.phone as customer_phone_rel,
                       t.table_number, t.capacity as table_capacity, t.status as table_status
                FROM {$this->table} r
                LEFT JOIN customers c ON r.customer_id = c.id
                LEFT JOIN tables t ON r.table_id = t.id
                WHERE r.id = :id";
        
        $row = $this->db->fetchOne($sql, [':id' => $id]);
        
        if (!$row) {
            return null;
        }
        
        $reservation = new Reservation($row);
        
        // Attach customer info
        if ($row['customer_name_rel']) {
            $reservation->customer = [
                'id' => (int)$row['customer_id'],
                'name' => $row['customer_name_rel'],
                'phone' => $row['customer_phone_rel']
            ];
        }
        
        // Attach table info
        if ($row['table_number']) {
            $reservation->table = [
                'id' => (int)$row['table_id'],
                'table_number' => $row['table_number'],
                'capacity' => (int)$row['table_capacity'],
                'status' => $row['table_status']
            ];
        }
        
        return $reservation;
    }
    
    /**
     * Tạo reservation mới
     */
    public function create($data) {
        $sql = "INSERT INTO {$this->table} 
                (customer_id, table_id, reservation_time, guest_count, customer_name, customer_phone, customer_note, status)
                VALUES (:customer_id, :table_id, :reservation_time, :guest_count, :customer_name, :customer_phone, :customer_note, :status)";
        
        $params = [
            ':customer_id' => $data['customer_id'],
            ':table_id' => $data['table_id'] ?? null,
            ':reservation_time' => $data['reservation_time'],
            ':guest_count' => $data['guest_count'],
            ':customer_name' => $data['customer_name'],
            ':customer_phone' => $data['customer_phone'],
            ':customer_note' => $data['customer_note'] ?? null,
            ':status' => $data['status'] ?? Reservation::STATUS_PENDING
        ];
        
        $this->db->query($sql, $params);
        $id = $this->db->getConnection()->lastInsertId();
        
        return $this->findById($id);
    }
    
    /**
     * Cập nhật reservation
     */
    public function update($id, $data) {
        $fields = [];
        $params = [':id' => $id];
        
        $allowedFields = ['table_id', 'reservation_time', 'guest_count', 'customer_name', 
                          'customer_phone', 'customer_note', 'status', 'order_id', 
                          'confirmed_by', 'confirmed_at', 'cancelled_reason'];
        
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }
        
        if (empty($fields)) {
            return $this->findById($id);
        }
        
        $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE id = :id";
        $this->db->query($sql, $params);
        
        return $this->findById($id);
    }
    
    /**
     * Kiểm tra bàn có trống tại thời điểm không
     */
    public function isTableAvailable($tableId, $reservationTime, $excludeReservationId = null) {
        // Kiểm tra có reservation nào CONFIRMED trong khoảng thời gian ±2 giờ không
        $sql = "SELECT COUNT(*) as count 
                FROM {$this->table}
                WHERE table_id = :table_id
                AND status IN ('CONFIRMED', 'ARRIVED')
                AND reservation_time BETWEEN 
                    DATE_SUB(:reservation_time, INTERVAL 2 HOUR) 
                    AND DATE_ADD(:reservation_time, INTERVAL 2 HOUR)";
        
        $params = [
            ':table_id' => $tableId,
            ':reservation_time' => $reservationTime
        ];
        
        if ($excludeReservationId) {
            $sql .= " AND id != :exclude_id";
            $params[':exclude_id'] = $excludeReservationId;
        }
        
        $result = $this->db->fetchOne($sql, $params);
        return $result && $result['count'] == 0;
    }
    
    /**
     * Lấy reservations hôm nay
     */
    public function getToday() {
        $sql = "SELECT r.*, 
                       c.name as customer_name_rel, c.phone as customer_phone_rel,
                       t.table_number, t.capacity as table_capacity
                FROM {$this->table} r
                LEFT JOIN customers c ON r.customer_id = c.id
                LEFT JOIN tables t ON r.table_id = t.id
                WHERE DATE(r.reservation_time) = CURDATE()
                AND r.status IN ('PENDING', 'CONFIRMED', 'ARRIVED')
                ORDER BY r.reservation_time ASC";
        
        $rows = $this->db->fetchAll($sql);
        
        $reservations = [];
        foreach ($rows as $row) {
            $reservation = new Reservation($row);
            
            if ($row['customer_name_rel']) {
                $reservation->customer = [
                    'id' => (int)$row['customer_id'],
                    'name' => $row['customer_name_rel'],
                    'phone' => $row['customer_phone_rel']
                ];
            }
            
            if ($row['table_number']) {
                $reservation->table = [
                    'id' => (int)$row['table_id'],
                    'table_number' => $row['table_number'],
                    'capacity' => (int)$row['table_capacity']
                ];
            }
            
            $reservations[] = $reservation;
        }
        
        return $reservations;
    }
    
    /**
     * Xóa reservation
     */
    public function delete($id) {
        $sql = "DELETE FROM {$this->table} WHERE id = :id";
        return $this->db->query($sql, [':id' => $id]);
    }
}
