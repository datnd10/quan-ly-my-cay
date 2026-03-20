<?php

/**
 * User Controller
 * 
 * CRUD users cho Admin
 */

class UserController extends Controller {
    private $userService;
    
    public function __construct() {
        $this->userService = new UserService();
    }
    
    /**
     * Lấy danh sách users (có phân trang)
     * GET /api/users?page=1&per_page=20&role=STAFF
     */
    public function index() {
        $page = max(1, (int)$this->getQuery('page', 1));
        $perPage = min(100, max(1, (int)$this->getQuery('per_page', 20)));
        
        $filters = [];
        if ($role = $this->getQuery('role')) {
            $filters['role'] = $role;
        }
        
        try {
            $result = $this->userService->getUsers($page, $perPage, $filters);
            return $this->paginate($result['users'], $result['total'], $page, $perPage);
            
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * Lấy chi tiết user
     * GET /api/users/{id}
     */
    public function show($id) {
        try {
            $user = $this->userService->getUserById($id);
            return $this->success($user);
            
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 404);
        }
    }
    
    /**
     * Tạo user mới (Admin tạo Staff/Admin)
     * POST /api/users
     * Body: { "username": "admin01", "password": "123456", "role": "STAFF" }
     */
    public function store() {
        $data = $this->getBody();
        
        try {
            $user = $this->userService->createUser($data);
            return $this->success($user, 'Tạo user thành công', 201);
            
        } catch (Exception $e) {
            $statusCode = isset($e->errors) ? 422 : 500;
            $errors = isset($e->errors) ? $e->errors : null;
            return $this->error($e->getMessage(), $statusCode, $errors);
        }
    }
    
    /**
     * Cập nhật user
     * PUT /api/users/{id}
     * Body: { "password": "newpass", "status": 0 }
     */
    public function update($id) {
        $data = $this->getBody();
        
        try {
            $user = $this->userService->updateUser($id, $data);
            return $this->success($user, 'Cập nhật user thành công');
            
        } catch (Exception $e) {
            $statusCode = isset($e->errors) ? 422 : ($e->getMessage() === 'Không tìm thấy user' ? 404 : 500);
            $errors = isset($e->errors) ? $e->errors : null;
            return $this->error($e->getMessage(), $statusCode, $errors);
        }
    }
    
    /**
     * Xóa user
     * DELETE /api/users/{id}
     */
    public function destroy($id) {
        try {
            $this->userService->deleteUser($id);
            return $this->success(null, 'Xóa user thành công');
            
        } catch (Exception $e) {
            $statusCode = ($e->getMessage() === 'Không tìm thấy user') ? 404 : 500;
            return $this->error($e->getMessage(), $statusCode);
        }
    }
}
