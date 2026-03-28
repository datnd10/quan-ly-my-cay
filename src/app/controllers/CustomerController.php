<?php

/**
 * Customer Controller
 * 
 * Quản lý khách hàng (Admin/Staff)
 */

class CustomerController extends Controller
{
    private $customerService;

    public function __construct()
    {
        $this->customerService = new CustomerService();
    }

    /**
     * Lấy danh sách customers (có phân trang + tìm kiếm)
     * GET /api/customers?page=1&per_page=20&name=nguyen&phone=0123
     */
    public function index()
    {
        // Yêu cầu role ADMIN hoặc STAFF
        $this->requireRole([ROLE_ADMIN, ROLE_STAFF]);

        $page = max(1, (int) $this->getQuery('page', 1));
        $perPage = min(100, max(1, (int) $this->getQuery('per_page', 20)));

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
     * Tìm kiếm nhanh customer theo SĐT (cho dropdown/autocomplete khi tạo order)
     * GET /api/customers/search?phone=0987
     * Trả về list customers match với phone (không phân trang, limit 10)
     */
    public function search()
    {
        // Yêu cầu role ADMIN hoặc STAFF
        $this->requireRole([ROLE_ADMIN, ROLE_STAFF]);

        $phone = $this->getQuery('phone');

        if (empty($phone)) {
            return $this->error('Số điện thoại không được để trống', 400);
        }

        try {
            $customers = $this->customerService->searchByPhone($phone);
            return $this->success($customers);

        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * Lấy chi tiết customer
     * GET /api/customers/{id}
     */
    public function show($id)
    {
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
    public function store()
    {
        // Yêu cầu role ADMIN hoặc STAFF
        $this->requireRole([ROLE_ADMIN, ROLE_STAFF]);

        $data = $this->getBody();

        try {
            $customer = $this->customerService->createCustomer($data);
            return $this->success($customer, 'Tạo khách hàng thành công', 201);

        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422, $e->getErrors());
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * Cập nhật customer
     * PUT /api/customers/{id}
     * Body: { "name": "Nguyen Van B", "email": "b@gmail.com" }
     */
    public function update($id)
    {
        // Yêu cầu role ADMIN hoặc STAFF
        $this->requireRole([ROLE_ADMIN, ROLE_STAFF]);

        $data = $this->getBody();

        try {
            $customer = $this->customerService->updateCustomer($id, $data);
            return $this->success($customer, 'Cập nhật khách hàng thành công');

        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422, $e->getErrors());
        } catch (Exception $e) {
            $statusCode = ($e->getMessage() === 'Không tìm thấy khách hàng') ? 404 : 500;
            return $this->error($e->getMessage(), $statusCode);
        }
    }

    /**
     * Cập nhật trạng thái customer
     * PUT /api/customers/{id}/status
     * Body: { "status": 0 }
     */
    public function updateStatus($id)
    {
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
     * Body: { "points": 100, "action": "add", "description": "Mua hàng" }
     */
    public function updatePoints($id)
    {
        // Yêu cầu role ADMIN hoặc STAFF
        $user = $this->requireRole([ROLE_ADMIN, ROLE_STAFF]);

        $data = $this->getBody();

        if (!isset($data['points'])) {
            return $this->error('Số điểm là bắt buộc', 422);
        }

        $action = $data['action'] ?? 'add';
        $description = $data['description'] ?? null;

        try {
            $customer = $this->customerService->updatePoints(
                $id,
                $data['points'],
                $action,
                $description,
                $user['user_id']
            );
            return $this->success($customer, 'Cập nhật điểm thành công');

        } catch (Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * Lấy lịch sử điểm của customer
     * GET /api/customers/{id}/points/history?page=1&per_page=20
     */
    public function pointHistory($id)
    {
        // Yêu cầu role ADMIN, STAFF hoặc chính customer đó
        try {
            $user = $this->auth();

            // Kiểm tra quyền: Admin/Staff hoặc chính customer đó
            if ($user['role'] !== ROLE_ADMIN && $user['role'] !== ROLE_STAFF) {
                // Nếu là customer, chỉ được xem lịch sử của mình
                $customerRepo = new CustomerRepository();
                $customer = $customerRepo->findById($id);

                if (!$customer || $customer['user_id'] != $user['user_id']) {
                    return $this->error('Bạn không có quyền xem lịch sử này', 403);
                }
            }

            $page = max(1, (int) $this->getQuery('page', 1));
            $perPage = min(100, max(1, (int) $this->getQuery('per_page', 20)));

            $result = $this->customerService->getPointHistory($id, $page, $perPage);
            return $this->paginate($result['transactions'], $result['total'], $page, $perPage);

        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * Xóa customer (vô hiệu hóa account)
     * DELETE /api/customers/{id}
     */
    public function destroy($id)
    {
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
     * Body: { "name": "Nguyen Van A", "phone": "0987654321", "email": "a@gmail.com" }
     */
    public function updateProfile()
    {
        // Lấy thông tin user từ token
        $user = $this->auth();

        // Chỉ customer mới được dùng API này

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

        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422, $e->getErrors());
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * Customer xem lịch sử điểm của chính mình
     * GET /api/customers/my-points/history?page=1&per_page=20
     */
    public function myPointHistory()
    {
        // Lấy thông tin user từ token
        $user = $this->auth();

        // Chỉ customer mới được dùng API này
        if ($user['role'] !== ROLE_CUSTOMER) {
            return $this->error('API này chỉ dành cho khách hàng', 403);
        }

        try {
            // Tìm customer profile theo user_id
            $customerRepo = new CustomerRepository();
            $customer = $customerRepo->findByUserId($user['user_id']);

            if (!$customer) {
                return $this->error('Không tìm thấy thông tin khách hàng', 404);
            }

            $page = max(1, (int) $this->getQuery('page', 1));
            $perPage = min(100, max(1, (int) $this->getQuery('per_page', 20)));

            $result = $this->customerService->getPointHistory($customer['id'], $page, $perPage);
            return $this->paginate($result['transactions'], $result['total'], $page, $perPage);

        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
}
