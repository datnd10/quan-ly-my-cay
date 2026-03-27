<?php

/**
 * Reservation Repository
 * 
 * Data access layer cho reservations (đơn giản hóa)
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
        
        if (!empty($filters['status'])) {
            $where[] = "r.status = :status";
            $params[':status'] = $filters['status'];
        }
        
        if (!empty($filters['customer_id'])) {
            $where[] = "r.customer_id = :customer_id";
            $params[':customer_id'] = $filters['customer_id'];
        }
        
        if (!empty($filters['date'])) {
            $where[] = "DATE(r.reservation_time) = :date";
            $params[':date'] = $filters['date'];
        }
        
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
        
        // Get reservations with customer info
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
            $reservations[] = $this->buildReservation($row);
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
        
        return $this->buildReservation($row);
    }
    
    /**
     * Tạo reservation mới
     */
    public function create($data) {
        $sql = "INSERT INTO {$this->table} 
                (customer_id, reservation_time, guest_count, customer_name, customer_phone, customer_note, status)
                VALUES (:customer_id, :reservation_time, :guest_count, :customer_name, :customer_phone, :customer_note, :status)";
        
        $params = [
            ':customer_id' => $data['customer_id'],
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
        
        $allowedFields = ['table_id', 'status', 'order_id', 
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
     * Lấy reservations hôm nay (PENDING + CONFIRMED)
     */
    public function getToday() {
        $sql = "SELECT r.*, 
                       c.name as customer_name_rel, c.phone as customer_phone_rel,
                       t.table_number, t.capacity as table_capacity
                FROM {$this->table} r
                LEFT JOIN customers c ON r.customer_id = c.id
                LEFT JOIN tables t ON r.table_id = t.id
                WHERE DATE(r.reservation_time) = CURDATE()
                AND r.status IN ('PENDING', 'CONFIRMED')
                ORDER BY r.reservation_time ASC";
        
        $rows = $this->db->fetchAll($sql);
        
        $reservations = [];
        foreach ($rows as $row) {
            $reservations[] = $this->buildReservation($row);
        }
        
        return $reservations;
    }
    
    /**
     * Build Reservation object từ row
     */
    private function buildReservation($row) {
        $reservation = new Reservation($row);
        
        if (!empty($row['customer_name_rel'])) {
            $reservation->customer = [
                'id' => (int)$row['customer_id'],
                'name' => $row['customer_name_rel'],
                'phone' => $row['customer_phone_rel']
            ];
        }
        
        if (!empty($row['table_number'])) {
            $reservation->table = [
                'id' => (int)$row['table_id'],
                'table_number' => $row['table_number'],
                'capacity' => (int)$row['table_capacity']
            ];
        }
        
        return $reservation;
    }
}
