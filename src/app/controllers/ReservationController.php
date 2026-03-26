<?php

/**
 * Reservation Controller
 * 
 * Xử lý HTTP requests cho reservations
 */

class ReservationController extends Controller {
    private $reservationService;
    
    public function __construct() {
        $this->reservationService = new ReservationService();
    }
    
    /**
     * GET /reservations
     * Lấy danh sách reservations (Admin/Staff)
     */
    public function index() {
        try {
            $this->requireRole(['ADMIN', 'STAFF']);
            
            $page = max(1, (int)$this->getQuery('page', 1));
            $perPage = min(100, max(1, (int)$this->getQuery('per_page', 20)));
            
            $filters = [];
            
            if ($this->getQuery('status')) {
                $filters['status'] = $this->getQuery('status');
            }
            
            if ($this->getQuery('customer_id')) {
                $filters['customer_id'] = $this->getQuery('customer_id');
            }
            
            if ($this->getQuery('table_id')) {
                $filters['table_id'] = $this->getQuery('table_id');
            }
            
            if ($this->getQuery('date')) {
                $filters['date'] = $this->getQuery('date');
            }
            
            if ($this->getQuery('from_date')) {
                $filters['from_date'] = $this->getQuery('from_date');
            }
            
            if ($this->getQuery('to_date')) {
                $filters['to_date'] = $this->getQuery('to_date');
            }
            
            $result = $this->reservationService->getReservations($page, $perPage, $filters);
            
            $this->paginate($result['reservations'], $result['total'], $page, $perPage);
            
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }
    
    /**
     * GET /reservations/my
     * Lấy reservations của customer đang login
     */
    public function my() {
        try {
            $user = $this->auth(['CUSTOMER']);
            
            // Lấy customer_id từ user_id
            $customerRepo = new CustomerRepository();
            $customer = $customerRepo->findByUserId($user['user_id']);
            
            if (!$customer) {
                return $this->error('Không tìm thấy thông tin khách hàng', 404);
            }
            
            $page = max(1, (int)$this->getQuery('page', 1));
            $perPage = min(100, max(1, (int)$this->getQuery('per_page', 20)));
            
            $filters = ['customer_id' => $customer['id']];
            
            if ($this->getQuery('status')) {
                $filters['status'] = $this->getQuery('status');
            }
            
            $result = $this->reservationService->getReservations($page, $perPage, $filters);
            
            $this->paginate($result['reservations'], $result['total'], $page, $perPage);
            
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }
    
    /**
     * GET /reservations/today
     * Lấy reservations hôm nay (Admin/Staff)
     */
    public function today() {
        try {
            $this->requireRole(['ADMIN', 'STAFF']);
            
            $reservations = $this->reservationService->getToday();
            
            $this->success($reservations);
            
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }
    
    /**
     * GET /reservations/{id}
     * Lấy chi tiết reservation
     */
    public function show($id) {
        try {
            $user = $this->auth();
            
            $reservation = $this->reservationService->getReservationById($id);
            
            // Customer chỉ xem đặt bàn của mình
            if ($user['role'] === 'CUSTOMER') {
                $customerRepo = new CustomerRepository();
                $customer = $customerRepo->findByUserId($user['user_id']);
                if (!$customer || $reservation->customer_id != $customer['id']) {
                    return $this->error('Bạn không có quyền xem đặt bàn này', 403);
                }
            }
            
            $this->success($reservation);
            
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }
    
    /**
     * POST /reservations
     * Tạo reservation mới (Customer)
     */
    public function store() {
        try {
            $user = $this->auth(['CUSTOMER']);
            
            // Lấy customer_id từ user_id
            $customerRepo = new CustomerRepository();
            $customer = $customerRepo->findByUserId($user['user_id']);
            
            if (!$customer) {
                return $this->error('Không tìm thấy thông tin khách hàng', 404);
            }
            
            $data = $this->getBody();
            
            $reservation = $this->reservationService->createReservation($data, $customer['id']);
            
            $this->success($reservation, 'Đặt bàn thành công', 201);
            
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422, $e->getErrors());
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }
    
    /**
     * POST /reservations/{id}/confirm
     * Xác nhận reservation (Staff/Admin)
     */
    public function confirm($id) {
        try {
            $user = $this->requireRole(['ADMIN', 'STAFF']);
            
            $data = $this->getBody();
            $tableId = $data['table_id'] ?? null;
            
            $reservation = $this->reservationService->confirmReservation($id, $user['user_id'], $tableId);
            
            $this->success($reservation, 'Xác nhận đặt bàn thành công');
            
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }
    
    /**
     * POST /reservations/{id}/arrive
     * Khách đến - tạo order (Staff/Admin)
     */
    public function arrive($id) {
        try {
            $user = $this->requireRole(['ADMIN', 'STAFF']);
            
            $reservation = $this->reservationService->arriveReservation($id, $user['user_id']);
            
            $this->success($reservation, 'Check-in thành công');
            
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }
    
    /**
     * PUT /reservations/{id}/cancel
     * Hủy reservation
     */
    public function cancel($id) {
        try {
            $user = $this->auth();
            
            $data = $this->getBody();
            $reason = $data['reason'] ?? null;
            
            // Customer chỉ hủy được đặt bàn của mình
            if ($user['role'] === 'CUSTOMER') {
                $reservation = $this->reservationService->getReservationById($id);
                $customerRepo = new CustomerRepository();
                $customer = $customerRepo->findByUserId($user['user_id']);
                if (!$customer || $reservation->customer_id != $customer['id']) {
                    return $this->error('Bạn không có quyền hủy đặt bàn này', 403);
                }
            }
            
            $reservation = $this->reservationService->cancelReservation($id, $user['user_id'], $reason);
            
            $this->success($reservation, 'Hủy đặt bàn thành công');
            
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }
    
    /**
     * PUT /reservations/{id}/no-show
     * Đánh dấu no-show (Staff/Admin)
     */
    public function noShow($id) {
        try {
            $user = $this->requireRole(['ADMIN', 'STAFF']);
            
            $reservation = $this->reservationService->noShowReservation($id, $user['user_id']);
            
            $this->success($reservation, 'Đánh dấu no-show thành công');
            
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }
    
    /**
     * POST /reservations/check-availability
     * Check bàn có trống không (Public)
     */
    public function checkAvailability() {
        try {
            $data = $this->getBody();
            
            if (empty($data['reservation_time'])) {
                return $this->error('reservation_time không được để trống', 422);
            }
            
            if (empty($data['guest_count'])) {
                return $this->error('guest_count không được để trống', 422);
            }
            
            $reservationTime = $data['reservation_time'];
            $guestCount = (int)$data['guest_count'];
            $tableId = $data['table_id'] ?? null;
            
            // Nếu có table_id cụ thể, check bàn đó
            if ($tableId) {
                $tableRepo = new TableRepository();
                $table = $tableRepo->findById($tableId);
                
                if (!$table) {
                    return $this->error('Không tìm thấy bàn', 404);
                }
                
                if ($table->capacity < $guestCount) {
                    return $this->success([
                        'available' => false,
                        'message' => "Bàn này chỉ có {$table->capacity} chỗ ngồi"
                    ]);
                }
                
                $reservationRepo = new ReservationRepository();
                $isAvailable = $reservationRepo->isTableAvailable($tableId, $reservationTime);
                
                return $this->success([
                    'available' => $isAvailable,
                    'table' => $table->toArray(),
                    'message' => $isAvailable ? 'Bàn còn trống' : 'Bàn đã được đặt trong khung giờ này'
                ]);
            }
            
            // Nếu không có table_id, tìm các bàn phù hợp
            $tableRepo = new TableRepository();
            $reservationRepo = new ReservationRepository();
            
            // Lấy tất cả bàn có capacity >= guest_count
            $allTables = $tableRepo->getAllTables(['min_capacity' => $guestCount]);
            
            $availableTables = [];
            foreach ($allTables as $table) {
                if ($reservationRepo->isTableAvailable($table->id, $reservationTime)) {
                    $availableTables[] = $table->toArray();
                }
            }
            
            return $this->success([
                'available' => !empty($availableTables),
                'available_tables' => $availableTables,
                'total_available' => count($availableTables),
                'message' => !empty($availableTables) 
                    ? 'Có ' . count($availableTables) . ' bàn trống phù hợp' 
                    : 'Không có bàn trống trong khung giờ này'
            ]);
            
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }
    
    /**
     * GET /tables/available-slots
     * Xem slot trống theo ngày (Public)
     */
    public function availableSlots() {
        try {
            $date = $this->getQuery('date');
            $guestCount = (int)$this->getQuery('guest_count', 1);
            
            if (empty($date)) {
                return $this->error('date không được để trống', 422);
            }
            
            // Validate date format
            $dateObj = DateTime::createFromFormat('Y-m-d', $date);
            if (!$dateObj || $dateObj->format('Y-m-d') !== $date) {
                return $this->error('date phải có định dạng Y-m-d (VD: 2026-03-26)', 422);
            }
            
            // Không cho xem quá khứ
            if ($date < date('Y-m-d')) {
                return $this->error('Không thể xem slot của ngày trong quá khứ', 422);
            }
            
            $tableRepo = new TableRepository();
            $reservationRepo = new ReservationRepository();
            
            // Lấy tất cả bàn phù hợp
            $allTables = $tableRepo->getAllTables(['min_capacity' => $guestCount]);
            
            if (empty($allTables)) {
                return $this->success([
                    'date' => $date,
                    'slots' => [],
                    'message' => 'Không có bàn phù hợp với số người này'
                ]);
            }
            
            // Tạo các time slots (11:00 - 22:00, mỗi slot 1 giờ)
            $slots = [];
            $startHour = 11;
            $endHour = 22;
            
            for ($hour = $startHour; $hour < $endHour; $hour++) {
                $timeSlot = sprintf('%s %02d:00:00', $date, $hour);
                
                // Đếm số bàn trống trong slot này
                $availableCount = 0;
                $availableTables = [];
                
                foreach ($allTables as $table) {
                    if ($reservationRepo->isTableAvailable($table->id, $timeSlot)) {
                        $availableCount++;
                        $availableTables[] = [
                            'id' => $table->id,
                            'table_number' => $table->table_number,
                            'capacity' => $table->capacity
                        ];
                    }
                }
                
                $slots[] = [
                    'time' => sprintf('%02d:00', $hour),
                    'datetime' => $timeSlot,
                    'available' => $availableCount > 0,
                    'available_tables_count' => $availableCount,
                    'available_tables' => $availableTables
                ];
            }
            
            return $this->success([
                'date' => $date,
                'guest_count' => $guestCount,
                'slots' => $slots
            ]);
            
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }
}
