<?php

/**
 * User Service
 * 
 * Business logic cho user management
 */

class UserService {
    private $userRepo;
    private $customerRepo;
    
    public function __construct() {
        $this->userRepo = new UserRepository();
        $this->customerRepo = new CustomerRepository();
    }
    
    /**
     * Lấy danh sách users có phân trang
     */
    public function getUsers($page, $perPage, $filters = []) {
        $total = $this->userRepo->count($filters);
        $users = $this->userRepo->paginate($page, $perPage, $filters);
        
        // Xóa password
        foreach ($users as &$user) {
            unset($user['password']);
        }
        
        return [
            'users' => $users,
            'total' => $total
        ];
    }
    
    /**
     * Lấy chi tiết user
     */
    public function getUserById($id) {
        $user = $this->userRepo->findById($id);
        
        if (!$user) {
            throw new Exception('Không tìm thấy user');
        }
        
        unset($user['password']);
        
        // Nếu là customer thì lấy thêm profile
        if ($user['role'] === ROLE_CUSTOMER) {
            $user['profile'] = $this->customerRepo->findByUserId($id);
        }
        
        return $user;
    }
    
    /**
     * Tạo user mới
     */
    public function createUser($data) {
        // Validate
        $this->validateUser($data, true);
        
        // Kiểm tra username đã tồn tại
        if ($this->userRepo->usernameExists($data['username'])) {
            throw new Exception('Username đã tồn tại');
        }
        
        // Hash password
        $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        $data['status'] = $data['status'] ?? USER_STATUS_ACTIVE;
        
        $userId = $this->userRepo->create($data);
        
        return $this->getUserById($userId);
    }
    
    /**
     * Cập nhật user
     */
    public function updateUser($id, $data) {
        $user = $this->userRepo->findById($id);
        
        if (!$user) {
            throw new Exception('Không tìm thấy user');
        }
        
        // Validate
        $this->validateUser($data, false, $id);
        
        // Hash password nếu có
        if (!empty($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        } else {
            unset($data['password']);
        }
        
        $this->userRepo->update($id, $data);
        
        return $this->getUserById($id);
    }
    
    /**
     * Xóa user
     */
    public function deleteUser($id) {
        $user = $this->userRepo->findById($id);
        
        if (!$user) {
            throw new Exception('Không tìm thấy user');
        }
        
        return $this->userRepo->delete($id);
    }
    
    /**
     * Validate dữ liệu user
     */
    private function validateUser($data, $isCreate = false, $userId = null) {
        $errors = [];
        
        // Username validation (chỉ khi tạo mới hoặc có thay đổi)
        if ($isCreate || isset($data['username'])) {
            if (empty($data['username'])) {
                $errors['username'] = 'Username là bắt buộc';
            } elseif (!preg_match(REGEX_USERNAME, $data['username'])) {
                $errors['username'] = 'Username chỉ chứa chữ, số và gạch dưới (3-50 ký tự)';
            } elseif ($this->userRepo->usernameExists($data['username'], $userId)) {
                $errors['username'] = 'Username đã tồn tại';
            }
        }
        
        // Password validation (chỉ khi tạo mới hoặc có thay đổi)
        if ($isCreate) {
            if (empty($data['password'])) {
                $errors['password'] = 'Mật khẩu là bắt buộc';
            } else {
                // Validate strong password
                $passwordValidation = PasswordValidator::validate($data['password']);
                if (!$passwordValidation['valid']) {
                    $errors['password'] = implode('. ', $passwordValidation['errors']);
                }
            }
        } elseif (isset($data['password']) && !empty($data['password'])) {
            // Validate strong password khi update
            $passwordValidation = PasswordValidator::validate($data['password']);
            if (!$passwordValidation['valid']) {
                $errors['password'] = implode('. ', $passwordValidation['errors']);
            }
        }
        
        // Role validation
        if ($isCreate || isset($data['role'])) {
            if (empty($data['role'])) {
                $errors['role'] = 'Role là bắt buộc';
            } elseif (!in_array($data['role'], [ROLE_ADMIN, ROLE_STAFF, ROLE_CUSTOMER])) {
                $errors['role'] = 'Role không hợp lệ';
            }
        }
        
        if (!empty($errors)) {
            throw new ValidationException('Dữ liệu không hợp lệ', $errors);
        }
    }
}
