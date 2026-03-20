<?php

/**
 * Auth Controller
 * 
 * Xử lý đăng nhập, đăng ký, đăng xuất
 */

class AuthController extends Controller {
    private $authService;
    
    public function __construct() {
        $this->authService = new AuthService();
    }
    
    /**
     * Đăng nhập
     * POST /api/auth/login
     * Body: { "username": "0123456789", "password": "123456" }
     */
    public function login() {
        $data = $this->getBody();
        
        // Validate
        $errors = $this->validate($data, [
            'username' => 'required',
            'password' => 'required'
        ]);
        
        if ($errors !== true) {
            return $this->error('Dữ liệu không hợp lệ', 422, $errors);
        }
        
        try {
            $result = $this->authService->login($data['username'], $data['password']);
            return $this->success($result, 'Đăng nhập thành công');
            
        } catch (Exception $e) {
            $statusCode = ($e->getMessage() === 'Tài khoản đã bị khóa') ? 403 : 401;
            return $this->error($e->getMessage(), $statusCode);
        }
    }
    
    /**
     * Đăng ký khách hàng
     * POST /api/auth/register
     * Body: { "phone": "0123456789", "password": "123456", "name": "Nguyễn Văn A", "email": "a@gmail.com" }
     */
    public function register() {
        $data = $this->getBody();
        
        try {
            $result = $this->authService->register($data);
            return $this->success($result, 'Đăng ký thành công', 201);
            
        } catch (Exception $e) {
            $statusCode = isset($e->errors) ? 422 : 500;
            $errors = isset($e->errors) ? $e->errors : null;
            return $this->error($e->getMessage(), $statusCode, $errors);
        }
    }
    
    /**
     * Lấy thông tin user hiện tại
     * GET /api/auth/me
     * Header: Authorization: Bearer {token}
     */
    public function me() {
        // Sẽ implement sau khi có AuthMiddleware
        $this->success(['message' => 'Get current user info']);
    }
    
    /**
     * Đổi mật khẩu
     * POST /api/auth/change-password
     * Header: Authorization: Bearer {token}
     * Body: { "old_password": "123456", "new_password": "newpass123" }
     */
    public function changePassword() {
        $data = $this->getBody();
        
        // Validate
        $errors = $this->validate($data, [
            'old_password' => 'required',
            'new_password' => 'required'
        ]);
        
        if ($errors !== true) {
            return $this->error('Dữ liệu không hợp lệ', 422, $errors);
        }
        
        try {
            // TODO: Lấy user_id từ JWT token sau khi có AuthMiddleware
            // Tạm thời hardcode để test
            $userId = $_GET['user_id'] ?? null;
            
            if (!$userId) {
                return $this->error('Vui lòng đăng nhập', 401);
            }
            
            $this->authService->changePassword(
                $userId,
                $data['old_password'],
                $data['new_password']
            );
            
            return $this->success(null, 'Đổi mật khẩu thành công');
            
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }
    
    /**
     * Quên mật khẩu - Gửi mật khẩu mới qua email
     * POST /api/auth/forgot-password
     * Body: { "phone": "0123456789" }
     */
    public function forgotPassword() {
        $data = $this->getBody();
        
        // Validate
        $errors = $this->validate($data, [
            'phone' => 'required'
        ]);
        
        if ($errors !== true) {
            return $this->error('Dữ liệu không hợp lệ', 422, $errors);
        }
        
        try {
            $result = $this->authService->forgotPassword($data['phone']);
            
            return $this->success(
                $result,
                'Mật khẩu mới đã được gửi đến email của bạn'
            );
            
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

}
