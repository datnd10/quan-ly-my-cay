<?php

/**
 * Customer Service
 * 
 * Business logic cho customer management
 */

class CustomerService {
    private $customerRepo;
    private $userRepo;
    private $pointTransactionRepo;
    
    public function __construct() {
        $this->customerRepo = new CustomerRepository();
        $this->userRepo = new UserRepository();
        $this->pointTransactionRepo = new PointTransactionRepository();
    }
    
    /**
     * Lấy danh sách customers có phân trang
     */
    public function getCustomers($page, $perPage, $filters = []) {
        $total = $this->customerRepo->count($filters);
        $customers = $this->customerRepo->paginate($page, $perPage, $filters);
        
        // Lấy thêm thông tin user cho mỗi customer
        foreach ($customers as &$customer) {
            $user = $this->userRepo->findById($customer['user_id']);
            if ($user) {
                unset($user['password']);
                $customer['user'] = $user;
            }
        }
        
        return [
            'customers' => $customers,
            'total' => $total
        ];
    }
    
    /**
     * Lấy chi tiết customer
     */
    public function getCustomerById($id) {
        $customer = $this->customerRepo->findById($id);
        
        if (!$customer) {
            throw new Exception('Không tìm thấy khách hàng');
        }
        
        // Lấy thông tin user
        $user = $this->userRepo->findById($customer['user_id']);
        if ($user) {
            unset($user['password']);
            $customer['user'] = $user;
        }
        
        return $customer;
    }
    
    /**
     * Tạo customer mới (Admin/Staff tạo)
     * Tạo cả user account + customer profile
     */
    public function createCustomer($data) {
        // Validate
        $this->validateCustomer($data, true);
        
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
            
            // Tạo user account
            $userId = $this->userRepo->create([
                'username' => $data['phone'],
                'password' => password_hash($data['password'], PASSWORD_DEFAULT),
                'role' => ROLE_CUSTOMER,
                'status' => $data['status'] ?? USER_STATUS_ACTIVE
            ]);
            
            // Tạo customer profile
            $customerId = $this->customerRepo->create([
                'user_id' => $userId,
                'name' => $data['name'],
                'phone' => $data['phone'],
                'email' => $data['email'] ?? null,
                'address' => $data['address'] ?? null,
                'points' => 0
            ]);
            
            $db->commit();
            
            return $this->getCustomerById($customerId);
            
        } catch (Exception $e) {
            $db->rollback();
            throw $e;
        }
    }
    
    /**
     * Cập nhật customer
     */
    public function updateCustomer($id, $data) {
        $customer = $this->customerRepo->findById($id);
        
        if (!$customer) {
            throw new Exception('Không tìm thấy khách hàng');
        }
        
        // Validate
        $this->validateCustomer($data, false, $id);
        
        // Kiểm tra phone nếu có thay đổi
        if (isset($data['phone']) && $data['phone'] !== $customer['phone']) {
            if ($this->customerRepo->phoneExists($data['phone'], $id)) {
                throw new Exception('Số điện thoại đã được sử dụng');
            }
        }
        
        // Chỉ update các field của customer
        $updateData = [];
        $allowedFields = ['name', 'phone', 'email', 'address'];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }
        
        if (!empty($updateData)) {
            $this->customerRepo->update($id, $updateData);
        }
        
        return $this->getCustomerById($id);
    }
    
    /**
     * Cập nhật trạng thái user account của customer
     */
    public function updateCustomerStatus($id, $status) {
        $customer = $this->customerRepo->findById($id);
        
        if (!$customer) {
            throw new Exception('Không tìm thấy khách hàng');
        }
        
        if (!in_array($status, [USER_STATUS_ACTIVE, USER_STATUS_INACTIVE])) {
            throw new Exception('Trạng thái không hợp lệ');
        }
        
        $this->userRepo->update($customer['user_id'], ['status' => $status]);
        
        return $this->getCustomerById($id);
    }
    
    /**
     * Cập nhật điểm tích lũy
     */
    public function updatePoints($id, $points, $action = 'add', $description = null, $createdBy = null) {
        $customer = $this->customerRepo->findById($id);
        
        if (!$customer) {
            throw new Exception('Không tìm thấy khách hàng');
        }
        
        if (!is_numeric($points) || $points < 0) {
            throw new Exception('Số điểm không hợp lệ');
        }
        
        $pointsChange = 0;
        $type = POINT_TYPE_ADJUST;
        
        if ($action === 'add') {
            // Cộng điểm
            $pointsChange = $points;
            $type = POINT_TYPE_EARN;
            $description = $description ?: "Cộng {$points} điểm";
        } elseif ($action === 'subtract') {
            // Trừ điểm
            if ($customer['points'] < $points) {
                throw new Exception('Số điểm không đủ');
            }
            $pointsChange = -$points;
            $type = POINT_TYPE_REDEEM;
            $description = $description ?: "Trừ {$points} điểm";
        } elseif ($action === 'set') {
            // Set điểm cụ thể
            $pointsChange = $points - $customer['points'];
            $type = POINT_TYPE_ADJUST;
            $description = $description ?: "Điều chỉnh điểm thành {$points}";
        } else {
            throw new Exception('Action không hợp lệ');
        }
        
        $db = Database::getInstance();
        
        try {
            $db->beginTransaction();
            
            // Cập nhật điểm
            $this->customerRepo->updatePoints($id, $pointsChange);
            
            // Lấy số điểm sau khi cập nhật
            $updatedCustomer = $this->customerRepo->findById($id);
            $balanceAfter = $updatedCustomer['points'];
            
            // Tạo transaction log
            $this->pointTransactionRepo->create([
                'customer_id' => $id,
                'points' => $pointsChange,
                'type' => $type,
                'description' => $description,
                'reference_type' => 'manual',
                'balance_after' => $balanceAfter,
                'created_by' => $createdBy
            ]);
            
            $db->commit();
            
            return $this->getCustomerById($id);
            
        } catch (Exception $e) {
            $db->rollback();
            throw $e;
        }
    }
    
    /**
     * Lấy lịch sử điểm của customer
     */
    public function getPointHistory($customerId, $page = 1, $perPage = 20) {
        $total = $this->pointTransactionRepo->countByCustomerId($customerId);
        $transactions = $this->pointTransactionRepo->getByCustomerId($customerId, $page, $perPage);
        
        return [
            'transactions' => $transactions,
            'total' => $total
        ];
    }
    
    /**
     * Xóa customer (soft delete - chỉ vô hiệu hóa account)
     */
    public function deleteCustomer($id) {
        $customer = $this->customerRepo->findById($id);
        
        if (!$customer) {
            throw new Exception('Không tìm thấy khách hàng');
        }
        
        // Vô hiệu hóa user account thay vì xóa
        $this->userRepo->update($customer['user_id'], ['status' => USER_STATUS_INACTIVE]);
        
        return true;
    }
    
    /**
     * Validate dữ liệu customer
     */
    private function validateCustomer($data, $isCreate = false, $customerId = null) {
        $errors = [];
        
        // Name validation
        if ($isCreate || isset($data['name'])) {
            if (empty($data['name'])) {
                $errors['name'] = 'Tên khách hàng là bắt buộc';
            } elseif (strlen($data['name']) < 2) {
                $errors['name'] = 'Tên phải có ít nhất 2 ký tự';
            }
        }
        
        // Phone validation
        if ($isCreate || isset($data['phone'])) {
            if (empty($data['phone'])) {
                $errors['phone'] = 'Số điện thoại là bắt buộc';
            } elseif (!preg_match(REGEX_PHONE, $data['phone'])) {
                $errors['phone'] = 'Số điện thoại không hợp lệ';
            }
        }
        
        // Email validation (optional)
        if (!empty($data['email']) && !preg_match(REGEX_EMAIL, $data['email'])) {
            $errors['email'] = 'Email không hợp lệ';
        }
        
        // Password validation (chỉ khi tạo mới)
        if ($isCreate) {
            if (empty($data['password'])) {
                $errors['password'] = 'Mật khẩu là bắt buộc';
            } else {
                $passwordValidation = PasswordValidator::validate($data['password']);
                if (!$passwordValidation['valid']) {
                    $errors['password'] = implode('. ', $passwordValidation['errors']);
                }
            }
        }
        
        if (!empty($errors)) {
            throw new ValidationException('Dữ liệu không hợp lệ', $errors);
        }
    }
}
