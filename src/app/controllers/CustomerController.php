<?php

/**
 * Customer Controller
 * 
 * Quản lý khách hàng (Admin/Staff)
 */

class CustomerController extends Controller {
    private $customerService;
    
    public function __construct() {
        $this->customerService = new CustomerService();
    }
    
    /**
     * Lấy danh sách customers (có phân trang + tìm kiếm)
     * GET /api/customers?page=1&per_page=20&name=nguyen&phone=0123
     */
    public function index() {
        // Yêu cầu role ADMIN hoặc STAFF
        $this->requireRole([ROLE_ADMIN, ROLE_STAFF]);
        
        $page = max(1, (int)$this->getQuery('page', 1));
        $perPage = min(100, max(1, (int)$this->getQuery('per_page', 20)));
        
        $filters = [];
        if ($name = $this->getQuery('name')) {
            $filters['name'] = $name;
        }
        if ($phone = $this->getQuery('phone')) {
            $filters['phone'] = $phone;
        }
        if ($email = $this->getQuery('email')) {
            $filters['email'] = $email;
        }
        
        try {
            $result = $this->customerService->getCustomers($page, $perPage, $filters);
            return $this->paginate($result['customers'], $result['total'], $page, $perPage);
            
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * Lấy chi tiết customer
     * GET /api/customers/{id}
     */
    public function show($id) {
        // Yêu cầu role ADMIN hoặc STAFF
        $this->requireRole([ROLE_ADMIN, ROLE_STAFF]);
        
        try {
            $customer = $this->customerService->getCustomerById($id);
            return $this->success($customer);
            
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 404);
        }
    }
    
    /**
     * Tạo customer mới (Admin/Staff tạo)
     * POST /api/customers
     * Body: { "phone": "0987654321", "password": "Pass@123", "name": "Nguyen Van A", "email": "a@gmail.com" }
     */
    public function store() {
        // Yêu cầu role ADMIN hoặc STAFF
        $this->requireRole([ROLE_ADMIN, ROLE_STAFF]);
        
        $data = $this->getBody();
        
        try {
            $customer = $this->customerService->createCustomer($data);
            return $this->success($customer, 'Tạo khách hàng thành công', 201);
            
        } catch (Exception $e) {
            $statusCode = isset($e->errors) ? 422 : 500;
            $errors = isset($e->errors) ? $e->errors : null;
            return $this->error($e->getMessage(), $statusCode, $errors);
        }
    }
    
    /**
     * Cập nhật customer
     * PUT /api/customers/{id}
     * Body: { "name": "Nguyen Van B", "email": "b@gmail.com", "address": "123 ABC" }
     */
    public function update($id) {
        // Yêu cầu role ADMIN hoặc STAFF
        $this->requireRole([ROLE_ADMIN, ROLE_STAFF]);
        
        $data = $this->getBody();
        
        try {
            $customer = $this->customerService->updateCustomer($id, $data);
            return $this->success($customer, 'Cập nhật khách hàng thành công');
            
        } catch (Exception $e) {
            $statusCode = isset($e->errors) ? 422 : ($e->getMessage() === 'Không tìm thấy khách hàng' ? 404 : 500);
            $errors = isset($e->errors) ? $e->errors : null;
            return $this->error($e->getMessage(), $statusCode, $errors);
        }
    }
    
    /**
     * Cập nhật trạng thái customer
     * PUT /api/customers/{id}/status
     * Body: { "status": 0 }
     */
    public function updateStatus($id) {
        // Yêu cầu role ADMIN hoặc STAFF
        $this->requireRole([ROLE_ADMIN, ROLE_STAFF]);
        
        $data = $this->getBody();
        
        if (!isset($data['status'])) {
            return $this->error('Trạng thái là bắt buộc', 422);
        }
        
        try {
            $customer = $this->customerService->updateCustomerStatus($id, $data['status']);
            return $this->success($customer, 'Cập nhật trạng thái thành công');
            
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }
    
    /**
     * Cập nhật điểm tích lũy
     * PUT /api/customers/{id}/points
     * Body: { "points": 100, "action": "add" } // action: add, subtract, set
     */
    public function updatePoints($id) {
        // Yêu cầu role ADMIN hoặc STAFF
        $this->requireRole([ROLE_ADMIN, ROLE_STAFF]);
        
        $data = $this->getBody();
        
        if (!isset($data['points'])) {
            return $this->error('Số điểm là bắt buộc', 422);
        }
        
        $action = $data['action'] ?? 'add';
        
        try {
            $customer = $this->customerService->updatePoints($id, $data['points'], $action);
            return $this->success($customer, 'Cập nhật điểm thành công');
            
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }
    
    /**
     * Xóa customer (vô hiệu hóa account)
     * DELETE /api/customers/{id}
     */
    public function destroy($id) {
        // Yêu cầu role ADMIN
        $this->requireRole([ROLE_ADMIN]);
        
        try {
            $this->customerService->deleteCustomer($id);
            return $this->success(null, 'Vô hiệu hóa khách hàng thành công');
            
        } catch (Exception $e) {
            $statusCode = ($e->getMessage() === 'Không tìm thấy khách hàng') ? 404 : 500;
            return $this->error($e->getMessage(), $statusCode);
        }
    }

     /**
     * Khách hàng tự cập nhật thông tin cá nhân
     * PUT /api/customers/profile
     * Body: { "name": "Nguyen Van A", "email": "a@gmail.com", "address": "123 ABC" }
     */
    public function updateProfile() {
        // Lấy thông tin user từ token
        $user = $this->auth();
        
        // Chỉ customer mới được dùng API này
        if ($user['role'] !== ROLE_CUSTOMER) {
            return $this->error('API này chỉ dành cho khách hàng', 403);
        }
        
        $data = $this->getBody();
        
        try {
            // Tìm customer profile theo user_id
            $customerRepo = new CustomerRepository();
            $customer = $customerRepo->findByUserId($user['user_id']);
            
            if (!$customer) {
                return $this->error('Không tìm thấy thông tin khách hàng', 404);
            }
            
            // Cập nhật thông tin
            $updatedCustomer = $this->customerService->updateCustomer($customer['id'], $data);
            
            return $this->success($updatedCustomer, 'Cập nhật thông tin thành công');
            
        } catch (Exception $e) {
            $statusCode = isset($e->errors) ? 422 : 500;
            $errors = isset($e->errors) ? $e->errors : null;
            return $this->error($e->getMessage(), $statusCode, $errors);
        }
    }
}
