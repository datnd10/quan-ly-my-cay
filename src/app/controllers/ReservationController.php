<?php

/**
 * Reservation Controller
 * 
 * Xử lý HTTP requests cho reservations (đơn giản hóa)
 * 
 * Flow: Khách đặt (PENDING) → Staff duyệt (CONFIRMED) → CANCELLED / NO_SHOW
 */

class ReservationController extends Controller {
    private $reservationService;
    
    public function __construct() {
        $this->reservationService = new ReservationService();
    }
    
    /**
     * GET /reservations
     * Danh sách reservations (Admin/Staff)
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
     * Xem đặt bàn của mình (Customer)
     */
    public function my() {
        try {
            $user = $this->auth(['CUSTOMER']);
            
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
     * Đặt bàn hôm nay (Admin/Staff)
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
     * Chi tiết reservation
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
     * Đặt bàn mới (Customer)
     * Chỉ cần: reservation_time, guest_count, customer_note
     */
    public function store() {
        try {
            $user = $this->auth(['CUSTOMER']);
            
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
     * Staff duyệt đặt bàn (PENDING → CONFIRMED)
     */
    public function confirm($id) {
        try {
            $user = $this->requireRole(['ADMIN', 'STAFF']);
            
            $reservation = $this->reservationService->confirmReservation($id, $user['user_id']);
            
            $this->success($reservation, 'Duyệt đặt bàn thành công');
            
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }
    
    /**
     * PUT /reservations/{id}/cancel
     * Hủy đặt bàn
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
     * Đánh dấu khách không đến (Admin/Staff)
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
}
