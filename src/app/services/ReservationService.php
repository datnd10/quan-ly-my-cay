<?php

/**
 * Reservation Service
 * 
 * Business logic cho reservations (đơn giản hóa)
 * 
 * Flow: Khách đặt (PENDING) → Staff duyệt (CONFIRMED) → CANCELLED / NO_SHOW
 */

class ReservationService {
    private $reservationRepo;
    private $customerRepo;
    
    public function __construct() {
        $this->reservationRepo = new ReservationRepository();
        $this->customerRepo = new CustomerRepository();
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
     * Tạo reservation mới (Customer)
     * Chỉ cần: ngày giờ, số người, ghi chú
     * Không chọn bàn - Staff sẽ assign khi khách đến
     */
    public function createReservation($data, $customerId) {
        // Validate
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
        
        // Kiểm tra thời gian
        $reservationTime = strtotime($data['reservation_time']);
        $now = time();
        
        if ($reservationTime < $now + 1800) {
            throw new Exception('Vui lòng đặt bàn trước ít nhất 30 phút');
        }
        
        if ($reservationTime > $now + (30 * 24 * 3600)) {
            throw new Exception('Chỉ có thể đặt bàn trong vòng 30 ngày');
        }
        
        // Lấy thông tin customer
        $customer = $this->customerRepo->findById($customerId);
        if (!$customer) {
            throw new Exception('Không tìm thấy thông tin khách hàng');
        }
        
        $customerName = is_array($customer) ? $customer['name'] : $customer->name;
        $customerPhone = is_array($customer) ? $customer['phone'] : $customer->phone;
        
        // Tạo reservation - status PENDING, chưa có bàn
        return $this->reservationRepo->create([
            'customer_id' => $customerId,
            'reservation_time' => $data['reservation_time'],
            'guest_count' => $data['guest_count'],
            'customer_name' => $data['customer_name'] ?? $customerName,
            'customer_phone' => $data['customer_phone'] ?? $customerPhone,
            'customer_note' => $data['customer_note'] ?? null,
            'status' => Reservation::STATUS_PENDING
        ]);
    }
    
    /**
     * Staff duyệt reservation (PENDING → CONFIRMED)
     * Chỉ đổi status, chưa assign bàn
     */
    public function confirmReservation($id, $userId) {
        $reservation = $this->reservationRepo->findById($id);
        
        if (!$reservation) {
            throw new Exception('Không tìm thấy đặt bàn');
        }
        
        if (!$reservation->canConfirm()) {
            throw new Exception('Chỉ có thể duyệt đặt bàn đang PENDING');
        }
        
        return $this->reservationRepo->update($id, [
            'status' => Reservation::STATUS_CONFIRMED,
            'confirmed_by' => $userId,
            'confirmed_at' => date('Y-m-d H:i:s')
        ]);
    }

    
    /**
     * Hủy reservation (PENDING/CONFIRMED → CANCELLED)
     */
    public function cancelReservation($id, $userId, $reason = null) {
        $reservation = $this->reservationRepo->findById($id);
        
        if (!$reservation) {
            throw new Exception('Không tìm thấy đặt bàn');
        }
        
        if (!$reservation->canCancel()) {
            throw new Exception('Không thể hủy đặt bàn này');
        }
        
        return $this->reservationRepo->update($id, [
            'status' => Reservation::STATUS_CANCELLED,
            'cancelled_reason' => $reason
        ]);
    }
    
    /**
     * Đánh dấu no-show (CONFIRMED → NO_SHOW)
     */
    public function noShowReservation($id, $userId) {
        $reservation = $this->reservationRepo->findById($id);
        
        if (!$reservation) {
            throw new Exception('Không tìm thấy đặt bàn');
        }
        
        if ($reservation->status !== Reservation::STATUS_CONFIRMED) {
            throw new Exception('Chỉ có thể đánh dấu NO_SHOW cho đặt bàn CONFIRMED');
        }
        
        return $this->reservationRepo->update($id, [
            'status' => Reservation::STATUS_NO_SHOW
        ]);
    }
}
