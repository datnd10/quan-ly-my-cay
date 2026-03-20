<?php

/**
 * Auth Service
 * 
 * Business logic cho authentication
 */

class AuthService {
    private $userRepo;
    private $customerRepo;
    private $jwtConfig;
    
    private $emailService;
    
    public function __construct() {
        $this->userRepo = new UserRepository();
        $this->customerRepo = new CustomerRepository();
        $this->emailService = new EmailService();
        
        $config = require __DIR__ . '/../../config/app.php';
        $this->jwtConfig = $config['jwt'];
    }
    
    /**
     * Đăng nhập
     */
    public function login($username, $password) {
        // Tìm user
        $user = $this->userRepo->findByUsername($username);
        
        if (!$user) {
            throw new Exception('Tên đăng nhập hoặc mật khẩu không đúng');
        }
        
        // Verify password
        if (!password_verify($password, $user['password'])) {
            throw new Exception('Tên đăng nhập hoặc mật khẩu không đúng');
        }
        
        // Kiểm tra status
        if ($user['status'] != USER_STATUS_ACTIVE) {
            throw new Exception('Tài khoản đã bị khóa');
        }
        
        // Tạo token
        $token = $this->generateJWT($user);
        
        return [
            'token' => $token
        ];
    }
    
    /**
     * Đăng ký khách hàng
     */
    public function register($data) {
        // Validate
        $this->validateRegister($data);
        
        // Kiểm tra phone đã tồn tại
        if ($this->userRepo->usernameExists($data['phone'])) {
            throw new Exception('Số điện thoại đã được đăng ký');
        }
        
        if ($this->customerRepo->phoneExists($data['phone'])) {
            throw new Exception('Số điện thoại đã được đăng ký');
        }
        
        $db = Database::getInstance();
        
        try {
            $db->beginTransaction();
            
            // Tạo user
            $userId = $this->userRepo->create([
                'username' => $data['phone'],
                'password' => password_hash($data['password'], PASSWORD_DEFAULT),
                'role' => ROLE_CUSTOMER,
                'status' => USER_STATUS_ACTIVE
            ]);
            
            // Tạo customer profile
            $customerId = $this->customerRepo->create([
                'user_id' => $userId,
                'name' => $data['name'],
                'phone' => $data['phone'],
                'email' => $data['email'] ?? null,
                'points' => 0
            ]);
            
            $db->commit();
            
            // Lấy thông tin vừa tạo
            $user = $this->userRepo->findById($userId);
            $customer = $this->customerRepo->findById($customerId);
            
            // Tạo token
            $token = $this->generateJWT($user);
            
            unset($user['password']);
            
            return [
                'token' => $token,
                'user' => $user,
                'profile' => $customer
            ];
            
        } catch (Exception $e) {
            $db->rollback();
            throw $e;
        }
    }
    
    /**
     * Validate dữ liệu đăng ký
     */
    private function validateRegister($data) {
        $errors = [];
        
        if (empty($data['phone'])) {
            $errors['phone'] = 'Số điện thoại là bắt buộc';
        } elseif (!preg_match(REGEX_PHONE, $data['phone'])) {
            $errors['phone'] = 'Số điện thoại không hợp lệ';
        }
        
        if (empty($data['password'])) {
            $errors['password'] = 'Mật khẩu là bắt buộc';
        } else {
            // Validate strong password
            $passwordValidation = PasswordValidator::validate($data['password']);
            if (!$passwordValidation['valid']) {
                $errors['password'] = implode('. ', $passwordValidation['errors']);
            }
        }
        
        if (empty($data['name'])) {
            $errors['name'] = 'Tên là bắt buộc';
        }
        
        if (!empty($data['email']) && !preg_match(REGEX_EMAIL, $data['email'])) {
            $errors['email'] = 'Email không hợp lệ';
        }
        
        if (!empty($errors)) {
            $exception = new Exception('Dữ liệu không hợp lệ');
            $exception->errors = $errors;
            throw $exception;
        }
    }
    
    /**
     * Generate JWT token
     */
    private function generateJWT($user) {
        $header = json_encode(['typ' => 'JWT', 'alg' => $this->jwtConfig['algorithm']]);
        
        $payload = json_encode([
            'user_id' => $user['id'],
            'username' => $user['username'],
            'role' => $user['role'],
            'iat' => time(),
            'exp' => time() + $this->jwtConfig['expiration']
        ]);
        
        $base64UrlHeader = $this->base64UrlEncode($header);
        $base64UrlPayload = $this->base64UrlEncode($payload);
        
        $signature = hash_hmac(
            'sha256',
            $base64UrlHeader . "." . $base64UrlPayload,
            $this->jwtConfig['secret'],
            true
        );
        
        $base64UrlSignature = $this->base64UrlEncode($signature);
        
        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }
    
    /**
     * Base64 URL encode
     */
    private function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }


    /**
     * Đổi mật khẩu (user đã đăng nhập)
     */
    public function changePassword($userId, $oldPassword, $newPassword) {
        // Validate strong password
        $passwordValidation = PasswordValidator::validate($newPassword);
        if (!$passwordValidation['valid']) {
            throw new Exception(implode('. ', $passwordValidation['errors']));
        }
        
        // Lấy user
        $user = $this->userRepo->findById($userId);
        
        if (!$user) {
            throw new Exception('Không tìm thấy user');
        }
        
        // Verify old password
        if (!password_verify($oldPassword, $user['password'])) {
            throw new Exception('Mật khẩu cũ không đúng');
        }
        
        // Update password
        $this->userRepo->update($userId, [
            'password' => password_hash($newPassword, PASSWORD_DEFAULT)
        ]);
        
        return true;
    }
    
    /**
     * Quên mật khẩu - Gửi mật khẩu mới qua email
     */
    public function forgotPassword($phone) {
        // Tìm user theo phone (username)
        $user = $this->userRepo->findByUsername($phone);
        
        if (!$user) {
            throw new Exception('Số điện thoại không tồn tại trong hệ thống');
        }
        
        // Lấy thông tin customer để có email
        $customer = $this->customerRepo->findByUserId($user['id']);
        
        if (!$customer || empty($customer['email'])) {
            throw new Exception('Tài khoản chưa có email. Vui lòng liên hệ admin để được hỗ trợ.');
        }
        
        // Generate random password
        $newPassword = PasswordValidator::generate(12);
        
        // Update password
        $this->userRepo->update($user['id'], [
            'password' => password_hash($newPassword, PASSWORD_DEFAULT)
        ]);
        
        // Gửi email (nếu fail thì vẫn giữ password mới)
        $this->emailService->sendResetPassword(
            $customer['email'],
            $customer['name'],
            $newPassword
        );
        
        return [
            'email' => $this->maskEmail($customer['email']),
            'new_password' => $newPassword // Trả về password để test (production nên xóa)
        ];
    }
    
    /**
     * Mask email để bảo mật
     * example@gmail.com -> e****e@gmail.com
     */
    private function maskEmail($email) {
        $parts = explode('@', $email);
        $name = $parts[0];
        $domain = $parts[1];
        
        $nameLength = strlen($name);
        if ($nameLength <= 2) {
            $masked = $name[0] . '*';
        } else {
            $masked = $name[0] . str_repeat('*', $nameLength - 2) . $name[$nameLength - 1];
        }
        
        return $masked . '@' . $domain;
    }
}
