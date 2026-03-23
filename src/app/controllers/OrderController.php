<?php

/**
 * Order Controller
 * 
 * Xử lý HTTP requests cho orders
 */

class OrderController extends Controller {
    private $orderService;
    
    public function __construct() {
        $this->orderService = new OrderService();
    }
    
    /**
     * GET /orders
     * Lấy danh sách orders
     */
    public function index() {
        try {
            $user = $this->auth();
            
            // Get query params
            $page = max(1, (int)$this->getQuery('page', 1));
            $perPage = min(100, max(1, (int)$this->getQuery('per_page', 20)));
            
            // Filters
            $filters = [];
            
            if ($this->getQuery('status')) {
                $filters['status'] = $this->getQuery('status');
            }
            
            if ($this->getQuery('table_id')) {
                $filters['table_id'] = $this->getQuery('table_id');
            }
            
            if ($this->getQuery('from_date')) {
                $filters['from_date'] = $this->getQuery('from_date');
            }
            
            if ($this->getQuery('to_date')) {
                $filters['to_date'] = $this->getQuery('to_date');
            }
            
            // Customer chỉ xem đơn của mình
            if ($user['role'] === 'CUSTOMER') {
                $filters['customer_id'] = $user['user_id'];
            } elseif ($this->getQuery('customer_id')) {
                // Admin/Staff có thể filter theo customer
                $filters['customer_id'] = $this->getQuery('customer_id');
            }
            
            $result = $this->orderService->getOrders($page, $perPage, $filters);
            
            $this->paginate($result['orders'], $result['total'], $page, $perPage);
            
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }
    
    /**
     * GET /orders/{id}
     * Lấy chi tiết order
     */
    public function show($id) {
        try {
            $user = $this->auth();
            
            $order = $this->orderService->getOrderById($id);
            
            // Customer chỉ xem đơn của mình
            if ($user['role'] === 'CUSTOMER' && $order->customer_id != $user['user_id']) {
                return $this->error('Bạn không có quyền xem đơn hàng này', 403);
            }
            
            $this->success($order);
            
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }
    
    /**
     * POST /orders
     * Tạo order mới
     */
    public function store() {
        try {
            $user = $this->requireRole(['ADMIN', 'STAFF']);
            
            $data = $this->getBody();
            
            $order = $this->orderService->createOrder($data, $user['user_id']);
            
            $this->success($order, 'Tạo đơn hàng thành công', 201);
            
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }
    
    /**
     * PUT /orders/{id}/items
     * Update items của order (thêm/sửa/xóa tất cả trong 1 API)
     * Body: {"items": [{"product_id": 10, "quantity": 2}, ...]}
     */
    public function updateItems($id) {
        try {
            $user = $this->requireRole(['ADMIN', 'STAFF']);
            
            $data = $this->getBody();
            $items = $data['items'] ?? [];
            
            $order = $this->orderService->updateItems($id, $items, $user['user_id']);
            
            $this->success($order, 'Cập nhật món thành công');
            
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }
    
    /**
     * POST /orders/{id}/voucher
     * Áp dụng voucher
     */
    public function applyVoucher($id) {
        try {
            $user = $this->requireRole(['ADMIN', 'STAFF']);
            
            $data = $this->getBody();
            $voucherCode = $data['voucher_code'] ?? '';
            
            if (empty($voucherCode)) {
                return $this->error('Mã voucher không được để trống', 400);
            }
            
            $order = $this->orderService->applyVoucher($id, $voucherCode, $user['user_id']);
            
            $this->success($order, 'Áp dụng voucher thành công');
            
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }
    
    /**
     * DELETE /orders/{id}/voucher
     * Hủy voucher
     */
    public function removeVoucher($id) {
        try {
            $user = $this->requireRole(['ADMIN', 'STAFF']);
            
            $order = $this->orderService->removeVoucher($id, $user['user_id']);
            
            $this->success($order, 'Hủy voucher thành công');
            
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }
    
    /**
     * POST /orders/{id}/confirm
     * Xác nhận đơn hàng
     */
    public function confirm($id) {
        try {
            $user = $this->requireRole(['ADMIN', 'STAFF']);
            
            $order = $this->orderService->confirmOrder($id, $user['user_id']);
            
            $this->success($order, 'Xác nhận đơn hàng thành công');
            
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }
    
    /**
     * PUT /orders/{id}/status
     * Thay đổi trạng thái order
     */
    public function updateStatus($id) {
        try {
            $user = $this->requireRole(['ADMIN', 'STAFF']);
            
            $data = $this->getBody();
            $status = $data['status'] ?? '';
            $note = $data['note'] ?? null;
            
            if (empty($status)) {
                return $this->error('Trạng thái không được để trống', 400);
            }
            
            $order = $this->orderService->updateStatus($id, $status, $user['user_id'], $note);
            
            $this->success($order, 'Cập nhật trạng thái thành công');
            
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }
    
    /**
     * POST /orders/{id}/cancel
     * Hủy đơn hàng
     */
    public function cancel($id) {
        try {
            $user = $this->auth();
            
            $data = $this->getBody();
            $reason = $data['reason'] ?? null;
            
            // Customer chỉ hủy được đơn của mình
            if ($user['role'] === 'CUSTOMER') {
                $order = $this->orderService->getOrderById($id);
                if ($order->customer_id != $user['user_id']) {
                    return $this->error('Bạn không có quyền hủy đơn hàng này', 403);
                }
            }
            
            $order = $this->orderService->cancelOrder($id, $user['user_id'], $reason);
            
            $this->success($order, 'Hủy đơn hàng thành công');
            
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }
    
    /**
     * DELETE /orders/{id}
     * Xóa order
     */
    public function destroy($id) {
        try {
            $user = $this->requireRole(['ADMIN', 'STAFF']);
            
            $this->orderService->deleteOrder($id, $user['user_id']);
            
            $this->success(null, 'Xóa đơn hàng thành công');
            
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }
    
    /**
     * GET /orders/{id}/history
     * Lấy lịch sử thay đổi status
     */
    public function history($id) {
        try {
            $user = $this->requireRole(['ADMIN', 'STAFF']);
            
            $history = $this->orderService->getStatusHistory($id);
            
            $this->success($history);
            
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }
}
