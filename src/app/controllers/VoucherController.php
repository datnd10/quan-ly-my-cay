<?php

/**
 * Voucher Controller
 * 
 * Quản lý vouchers
 */

class VoucherController extends Controller {
    private $voucherService;
    
    public function __construct() {
        $this->voucherService = new VoucherService();
    }
    
    /**
     * Lấy danh sách vouchers
     * GET /api/vouchers?page=1&per_page=20&status=1&discount_type=PERCENTAGE&search=SUMMER
     * Admin/Staff
     */
    public function index() {
        // Yêu cầu role ADMIN hoặc STAFF
        $this->requireRole([ROLE_ADMIN, ROLE_STAFF]);
        
        $page = max(1, (int)$this->getQuery('page', 1));
        $perPage = min(100, max(1, (int)$this->getQuery('per_page', 20)));
        
        $filters = [];
        
        if (isset($_GET['status'])) {
            $filters['status'] = (int)$this->getQuery('status');
        }
        
        if ($discountType = $this->getQuery('discount_type')) {
            $filters['discount_type'] = $discountType;
        }
        
        if ($search = $this->getQuery('search')) {
            $filters['search'] = $search;
        }
        
        if ($this->getQuery('active_only')) {
            $filters['active_only'] = true;
        }
        
        try {
            $result = $this->voucherService->getVouchers($page, $perPage, $filters);
            return $this->paginate($result['vouchers'], $result['total'], $page, $perPage);
            
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * Lấy danh sách vouchers available (Public)
     * GET /api/vouchers/available?order_amount=500000
     * Public - User/Customer xem voucher có thể dùng
     */
    public function available() {
        // Lấy tổng đơn hàng từ query
        $orderAmount = (float)$this->getQuery('order_amount', 0);
        
        if ($orderAmount <= 0) {
            return $this->error('Vui lòng cung cấp tổng đơn hàng (order_amount)', 422);
        }
        
        // Chỉ lấy voucher còn hiệu lực
        $filters = [
            'active_only' => true,
            'status' => 1
        ];
        
        // Có thể filter theo discount_type
        if ($discountType = $this->getQuery('discount_type')) {
            $filters['discount_type'] = $discountType;
        }
        
        try {
            // Lấy tất cả vouchers available (không phân trang)
            $result = $this->voucherService->getAvailableVouchers($orderAmount, $filters);
            
            return $this->success($result, 'Danh sách vouchers phù hợp');
            
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * Lấy chi tiết voucher
     * GET /api/vouchers/{id}
     * Admin/Staff
     */
    public function show($id) {
        // Yêu cầu role ADMIN hoặc STAFF
        $this->requireRole([ROLE_ADMIN, ROLE_STAFF]);
        
        try {
            $voucher = $this->voucherService->getVoucherById($id);
            return $this->success($voucher);
            
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 404);
        }
    }
    
    /**
     * Tạo voucher mới
     * POST /api/vouchers
     * Body: {
     *   "code": "SUMMER2024",
     *   "discount_type": "PERCENTAGE",
     *   "discount_value": 10,
     *   "min_order_amount": 50000,
     *   "max_discount": 100000,
     *   "usage_limit": 100,
     *   "start_date": "2024-06-01 00:00:00",
     *   "expired_at": "2024-08-31 23:59:59"
     * }
     * Admin only
     */
    public function store() {
        // Yêu cầu role ADMIN
        $this->requireRole([ROLE_ADMIN]);
        
        $data = $this->getBody();
        
        try {
            $voucher = $this->voucherService->createVoucher($data);
            return $this->success($voucher, 'Tạo voucher thành công', 201);
            
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422, $e->getErrors());
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }
    
    /**
     * Cập nhật voucher
     * PUT /api/vouchers/{id}
     * Admin only
     */
    public function update($id) {
        // Yêu cầu role ADMIN
        $this->requireRole([ROLE_ADMIN]);
        
        $data = $this->getBody();
        
        try {
            $voucher = $this->voucherService->updateVoucher($id, $data);
            return $this->success($voucher, 'Cập nhật voucher thành công');
            
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422, $e->getErrors());
        } catch (Exception $e) {
            $statusCode = ($e->getMessage() === 'Không tìm thấy voucher') ? 404 : 400;
            return $this->error($e->getMessage(), $statusCode);
        }
    }
    
    /**
     * Xóa voucher
     * DELETE /api/vouchers/{id}
     * Admin only
     */
    public function destroy($id) {
        // Yêu cầu role ADMIN
        $this->requireRole([ROLE_ADMIN]);
        
        try {
            $this->voucherService->deleteVoucher($id);
            return $this->success(null, 'Xóa voucher thành công');
            
        } catch (Exception $e) {
            $statusCode = ($e->getMessage() === 'Không tìm thấy voucher') ? 404 : 400;
            return $this->error($e->getMessage(), $statusCode);
        }
    }
    
    /**
     * Validate voucher code
     * POST /api/vouchers/validate
     * Body: { "code": "SUMMER2024", "order_amount": 500000 }
     * Public - Khách có thể check voucher trước khi đặt
     */
    public function validateVoucher() {
        $data = $this->getBody();
        
        if (empty($data['code'])) {
            return $this->error('Mã voucher không được để trống', 422);
        }
        
        if (!isset($data['order_amount']) || $data['order_amount'] <= 0) {
            return $this->error('Tổng đơn hàng không hợp lệ', 422);
        }
        
        try {
            $result = $this->voucherService->validateAndCalculate($data['code'], $data['order_amount']);
            
            return $this->success([
                'valid' => true,
                'voucher' => $result['voucher']->toArray(),
                'discount' => $result['discount'],
                'final_amount' => $result['final_amount']
            ], 'Voucher hợp lệ');
            
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }
}
