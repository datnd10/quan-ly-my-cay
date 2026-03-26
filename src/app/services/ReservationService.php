<?php

/**
 * Reservation Service
 * 
 * Business logic cho reservations
 */

class ReservationService {
    private $reservationRepo;
    private $customerRepo;
    private $tableRepo;
    private $orderService;
    private $db;
    
    public function __construct() {
        $this->reservationRepo = new ReservationRepository();
        $this->customerRepo = new CustomerRepository();
        $this->tableRepo = new TableRepository();
        $this->orderService = new OrderService();
        $this->db = Database::getInstance();
    }
    
    /**
     * Lấy danh sách reservations
     */
    public function getReservations($page, $perPage, $filters) {
        return $this->reservationRepo->getReservations($page, $perPage, $filters);
    }
    
    /**
     * Lấy reservation theo ID
     */
    public function getReservationById($id) {
        $reservation = $this->reservationRepo->findById($id);
        
        if (!$reservation) {
            throw new Exception('Không tìm thấy đặt bàn');
        }
        
        return $reservation;
    }
    
    /**
     * Lấy reservations hôm nay
     */
    public function getToday() {
        return $this->reservationRepo->getToday();
    }
    
    /**
     * Tạo reservation mới
     */
    public function createReservation($data, $customerId) {
        // Validate
        $this->validateReservation($data);
        
        // Kiểm tra thời gian đặt bàn
        $reservationTime = strtotime($data['reservation_time']);
        $now = time();
        
        // Phải đặt trước ít nhất 30 phút
        if ($reservationTime < $now + 1800) {
            throw new Exception('Vui lòng đặt bàn trước ít nhất 30 phút');
        }
        
        // Không đặt quá 30 ngày
        if ($reservationTime > $now + (30 * 24 * 3600)) {
            throw new Exception('Chỉ có thể đặt bàn trong vòng 30 ngày');
        }
        
        // Lấy thông tin customer
        $customer = $this->customerRepo->findById($customerId);
        if (!$customer) {
            throw new Exception('Không tìm thấy thông tin khách hàng');
        }
        
        // Nếu có table_id, kiểm tra bàn
        if (!empty($data['table_id'])) {
            $table = $this->tableRepo->findById($data['table_id']);
            if (!$table) {
                throw new Exception('Không tìm thấy bàn');
            }
            
            // Kiểm tra capacity
            if ($table->capacity < $data['guest_count']) {
                throw new Exception("Bàn này chỉ có {$table->capacity} chỗ ngồi");
            }
            
            // Kiểm tra bàn có trống không
            if (!$this->reservationRepo->isTableAvailable($data['table_id'], $data['reservation_time'])) {
                throw new Exception('Bàn này đã được đặt trong khung giờ này');
            }
        }
        
        // Tạo reservation
        $reservationData = [
            'customer_id' => $customerId,
            'table_id' => $data['table_id'] ?? null,
            'reservation_time' => $data['reservation_time'],
            'guest_count' => $data['guest_count'],
            'customer_name' => $data['customer_name'] ?? (is_array($customer) ? $customer['name'] : $customer->name),
            'customer_phone' => $data['customer_phone'] ?? (is_array($customer) ? $customer['phone'] : $customer->phone),
            'customer_note' => $data['customer_note'] ?? null,
            'status' => Reservation::STATUS_PENDING
        ];
        
        return $this->reservationRepo->create($reservationData);
    }
    
    /**
     * Xác nhận reservation (Staff)
     */
    public function confirmReservation($id, $userId, $tableId = null) {
        $reservation = $this->reservationRepo->findById($id);
        
        if (!$reservation) {
            throw new Exception('Không tìm thấy đặt bàn');
        }
        
        if (!$reservation->canConfirm()) {
            throw new Exception('Không thể xác nhận đặt bàn này');
        }
        
        $this->db->beginTransaction();
        
        try {
            $updateData = [
                'status' => Reservation::STATUS_CONFIRMED,
                'confirmed_by' => $userId,
                'confirmed_at' => date('Y-m-d H:i:s')
            ];
            
            // Nếu assign bàn mới
            if ($tableId) {
                $table = $this->tableRepo->findById($tableId);
                if (!$table) {
                    throw new Exception('Không tìm thấy bàn');
                }
                
                if ($table->capacity < $reservation->guest_count) {
                    throw new Exception("Bàn này chỉ có {$table->capacity} chỗ ngồi");
                }
                
                if (!$this->reservationRepo->isTableAvailable($tableId, $reservation->reservation_time, $id)) {
                    throw new Exception('Bàn này đã được đặt trong khung giờ này');
                }
                
                $updateData['table_id'] = $tableId;
                
                // Cập nhật status bàn sang RESERVED
                $this->tableRepo->updateStatus($tableId, 'RESERVED');
            } elseif ($reservation->table_id) {
                // Nếu đã có bàn, cập nhật status
                $this->tableRepo->updateStatus($reservation->table_id, 'RESERVED');
            }
            
            $result = $this->reservationRepo->update($id, $updateData);
            
            $this->db->commit();
            
            return $result;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Khách đến (tạo order)
     */
    public function arriveReservation($id, $userId) {
        $reservation = $this->reservationRepo->findById($id);
        
        if (!$reservation) {
            throw new Exception('Không tìm thấy đặt bàn');
        }
        
        if (!$reservation->canArrive()) {
            throw new Exception('Chỉ có thể check-in đặt bàn đã CONFIRMED');
        }
        
        if (!$reservation->table_id) {
            throw new Exception('Chưa assign bàn cho đặt bàn này');
        }
        
        $this->db->beginTransaction();
        
        try {
            // Tạo order cho bàn
            $orderData = [
                'table_id' => $reservation->table_id,
                'customer_id' => $reservation->customer_id
            ];
            
            $order = $this->orderService->createOrder($orderData, $userId);
            
            // Cập nhật reservation
            $this->reservationRepo->update($id, [
                'status' => Reservation::STATUS_ARRIVED,
                'order_id' => $order->id
            ]);
            
            // Bàn đã tự động chuyển sang OCCUPIED trong OrderService
            
            $this->db->commit();
            
            return $this->reservationRepo->findById($id);
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Hủy reservation
     */
    public function cancelReservation($id, $userId, $reason = null) {
        $reservation = $this->reservationRepo->findById($id);
        
        if (!$reservation) {
            throw new Exception('Không tìm thấy đặt bàn');
        }
        
        if (!$reservation->canCancel()) {
            throw new Exception('Không thể hủy đặt bàn này');
        }
        
        $this->db->beginTransaction();
        
        try {
            // Cập nhật reservation
            $this->reservationRepo->update($id, [
                'status' => Reservation::STATUS_CANCELLED,
                'cancelled_reason' => $reason
            ]);
            
            // Nếu bàn đang RESERVED, chuyển về AVAILABLE
            if ($reservation->table_id) {
                $table = $this->tableRepo->findById($reservation->table_id);
                if ($table && $table->status === 'RESERVED') {
                    $this->tableRepo->updateStatus($reservation->table_id, 'AVAILABLE');
                }
            }
            
            $this->db->commit();
            
            return $this->reservationRepo->findById($id);
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * No-show (khách không đến)
     */
    public function noShowReservation($id, $userId) {
        $reservation = $this->reservationRepo->findById($id);
        
        if (!$reservation) {
            throw new Exception('Không tìm thấy đặt bàn');
        }
        
        if ($reservation->status !== Reservation::STATUS_CONFIRMED) {
            throw new Exception('Chỉ có thể đánh dấu NO_SHOW cho đặt bàn CONFIRMED');
        }
        
        $this->db->beginTransaction();
        
        try {
            // Cập nhật reservation
            $this->reservationRepo->update($id, [
                'status' => Reservation::STATUS_NO_SHOW
            ]);
            
            // Free bàn
            if ($reservation->table_id) {
                $this->tableRepo->updateStatus($reservation->table_id, 'AVAILABLE');
            }
            
            $this->db->commit();
            
            return $this->reservationRepo->findById($id);
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Assign bàn cho reservation
     */
    public function assignTable($id, $tableId, $userId) {
        $reservation = $this->reservationRepo->findById($id);
        
        if (!$reservation) {
            throw new Exception('Không tìm thấy đặt bàn');
        }
        
        if (!in_array($reservation->status, [Reservation::STATUS_PENDING, Reservation::STATUS_CONFIRMED])) {
            throw new Exception('Không thể assign bàn cho đặt bàn này');
        }
        
        $table = $this->tableRepo->findById($tableId);
        if (!$table) {
            throw new Exception('Không tìm thấy bàn');
        }
        
        if ($table->capacity < $reservation->guest_count) {
            throw new Exception("Bàn này chỉ có {$table->capacity} chỗ ngồi");
        }
        
        if (!$this->reservationRepo->isTableAvailable($tableId, $reservation->reservation_time, $id)) {
            throw new Exception('Bàn này đã được đặt trong khung giờ này');
        }
        
        $this->db->beginTransaction();
        
        try {
            // Free bàn cũ nếu có
            if ($reservation->table_id && $reservation->status === Reservation::STATUS_CONFIRMED) {
                $oldTable = $this->tableRepo->findById($reservation->table_id);
                if ($oldTable && $oldTable->status === 'RESERVED') {
                    $this->tableRepo->updateStatus($reservation->table_id, 'AVAILABLE');
                }
            }
            
            // Assign bàn mới
            $this->reservationRepo->update($id, ['table_id' => $tableId]);
            
            // Nếu đã CONFIRMED, set bàn mới sang RESERVED
            if ($reservation->status === Reservation::STATUS_CONFIRMED) {
                $this->tableRepo->updateStatus($tableId, 'RESERVED');
            }
            
            $this->db->commit();
            
            return $this->reservationRepo->findById($id);
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Validate reservation data
     */
    private function validateReservation($data) {
        $errors = [];
        
        if (empty($data['reservation_time'])) {
            $errors['reservation_time'] = 'Thời gian đặt bàn không được để trống';
        }
        
        if (empty($data['guest_count']) || $data['guest_count'] < 1) {
            $errors['guest_count'] = 'Số người phải lớn hơn 0';
        }
        
        if (!empty($errors)) {
            throw new ValidationException('Dữ liệu không hợp lệ', $errors);
        }
    }
}
