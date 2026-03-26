<?php

/**
 * Table Service
 * 
 * Business logic cho tables
 */

class TableService {
    private $tableRepo;
    
    // Các status hợp lệ
    const VALID_STATUSES = ['AVAILABLE', 'OCCUPIED', 'RESERVED', 'MAINTENANCE'];
    
    public function __construct() {
        $this->tableRepo = new TableRepository();
    }
    
    /**
     * Lấy tất cả tables (không phân trang)
     */
    public function getAllTables($filters = []) {
        return $this->tableRepo->getAllTables($filters);
    }
    
    /**
     * Lấy danh sách tables (có phân trang)
     */
    public function getTables($page, $perPage, $filters) {
        return $this->tableRepo->getTables($page, $perPage, $filters);
    }
    
    /**
     * Lấy table theo ID
     */
    public function getTableById($id) {
        $table = $this->tableRepo->findById($id);
        
        if (!$table) {
            throw new Exception('Không tìm thấy bàn');
        }
        
        return $table;
    }
    
    /**
     * Tạo table mới
     */
    public function createTable($data) {
        // Validate
        $errors = $this->validateTable($data);
        if (!empty($errors)) {
            throw new ValidationException('Dữ liệu không hợp lệ', $errors);
        }
        
        // Kiểm tra table_number đã tồn tại chưa
        if ($this->tableRepo->existsByTableNumber($data['table_number'])) {
            throw new Exception('Số bàn đã tồn tại');
        }
        
        return $this->tableRepo->create($data);
    }
    
    /**
     * Cập nhật table
     */
    public function updateTable($id, $data) {
        // Kiểm tra table tồn tại
        $table = $this->tableRepo->findById($id);
        if (!$table) {
            throw new Exception('Không tìm thấy bàn');
        }
        
        // Validate
        $errors = $this->validateTable($data, true);
        if (!empty($errors)) {
            throw new ValidationException('Dữ liệu không hợp lệ', $errors);
        }
        
        // Kiểm tra table_number đã tồn tại chưa (nếu có update)
        if (isset($data['table_number']) && $this->tableRepo->existsByTableNumber($data['table_number'], $id)) {
            throw new Exception('Số bàn đã tồn tại');
        }
        
        return $this->tableRepo->update($id, $data);
    }
    
    /**
     * Cập nhật status
     */
    public function updateStatus($id, $status) {
        // Kiểm tra table tồn tại
        $table = $this->tableRepo->findById($id);
        if (!$table) {
            throw new Exception('Không tìm thấy bàn');
        }
        
        // Validate status
        if (!in_array($status, self::VALID_STATUSES)) {
            throw new Exception('Status không hợp lệ. Chỉ chấp nhận: ' . implode(', ', self::VALID_STATUSES));
        }
        
        return $this->tableRepo->updateStatus($id, $status);
    }
    
    /**
     * Xóa table (soft delete)
     * Set is_deleted = 1 để giữ lại dữ liệu cho orders đã tồn tại
     */
    public function deleteTable($id) {
        // Kiểm tra table tồn tại
        $table = $this->tableRepo->findById($id);
        if (!$table) {
            throw new Exception('Không tìm thấy bàn');
        }
        
        // Kiểm tra đã bị xóa chưa
        if ($table->is_deleted == 1) {
            throw new Exception('Bàn đã bị xóa trước đó');
        }
        
        // Kiểm tra table có đang được sử dụng không
        if ($table->status === 'OCCUPIED') {
            throw new Exception('Không thể xóa bàn đang có khách');
        }
        
        // Soft delete: Set is_deleted = 1 thay vì xóa khỏi database
        return $this->tableRepo->update($id, ['is_deleted' => 1]);
    }
    
    /**
     * Khôi phục table đã xóa
     */
    public function restoreTable($id) {
        $table = $this->tableRepo->findById($id);
        
        if (!$table) {
            throw new Exception('Không tìm thấy bàn');
        }
        
        if ($table->is_deleted == 0) {
            throw new Exception('Bàn đang hoạt động, không cần khôi phục');
        }
        
        return $this->tableRepo->update($id, ['is_deleted' => 0]);
    }
    
    /**
     * Lấy tables available
     */
    public function getAvailableTables() {
        return $this->tableRepo->getByStatus('AVAILABLE');
    }
    
    /**
     * Validate table data
     */
    private function validateTable($data, $isUpdate = false) {
        $errors = [];
        
        // Table number
        if (!$isUpdate || isset($data['table_number'])) {
            if (empty($data['table_number'])) {
                $errors['table_number'] = 'Số bàn không được để trống';
            } elseif (strlen($data['table_number']) > 20) {
                $errors['table_number'] = 'Số bàn không được vượt quá 20 ký tự';
            }
        }
        
        // Capacity
        if (isset($data['capacity'])) {
            if (!is_numeric($data['capacity']) || $data['capacity'] < 1) {
                $errors['capacity'] = 'Số chỗ ngồi phải là số và lớn hơn 0';
            }
        }
        
        // Status
        if (isset($data['status'])) {
            if (!in_array($data['status'], self::VALID_STATUSES)) {
                $errors['status'] = 'Status không hợp lệ. Chỉ chấp nhận: ' . implode(', ', self::VALID_STATUSES);
            }
        }
        
        return $errors;
    }
}
