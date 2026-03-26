<?php

/**
 * Category Service
 * 
 * Business logic cho category management
 */

class CategoryService {
    private $categoryRepo;
    
    public function __construct() {
        $this->categoryRepo = new CategoryRepository();
    }
    
    /**
     * Lấy tất cả categories (không phân trang)
     */
    public function getAllCategories() {
        return $this->categoryRepo->getAll();
    }
    
    /**
     * Lấy danh sách categories có phân trang
     */
    public function getCategories($page, $perPage, $filters = []) {
        $total = $this->categoryRepo->count($filters);
        $categories = $this->categoryRepo->paginate($page, $perPage, $filters);
        
        // Thêm số lượng sản phẩm cho mỗi category
        foreach ($categories as &$category) {
            $category['product_count'] = $this->categoryRepo->countProducts($category['id']);
        }
        
        return [
            'categories' => $categories,
            'total' => $total
        ];
    }
    
    /**
     * Lấy chi tiết category
     */
    public function getCategoryById($id) {
        $category = $this->categoryRepo->findById($id);
        
        if (!$category) {
            throw new Exception('Không tìm thấy danh mục');
        }
        
        // Thêm số lượng sản phẩm
        $category['product_count'] = $this->categoryRepo->countProducts($id);
        
        return $category;
    }
    
    /**
     * Tạo category mới
     */
    public function createCategory($data) {
        // Validate
        $this->validateCategory($data, true);
        
        // Kiểm tra tên đã tồn tại
        if ($this->categoryRepo->nameExists($data['name'])) {
            throw new Exception('Tên danh mục đã tồn tại');
        }
        
        $categoryId = $this->categoryRepo->create([
            'name' => trim($data['name'])
        ]);
        
        return $this->getCategoryById($categoryId);
    }
    
    /**
     * Cập nhật category
     */
    public function updateCategory($id, $data) {
        $category = $this->categoryRepo->findById($id);
        
        if (!$category) {
            throw new Exception('Không tìm thấy danh mục');
        }
        
        // Validate
        $this->validateCategory($data, false, $id);
        
        // Kiểm tra tên nếu có thay đổi
        if (isset($data['name']) && $data['name'] !== $category['name']) {
            if ($this->categoryRepo->nameExists($data['name'], $id)) {
                throw new Exception('Tên danh mục đã tồn tại');
            }
        }
        
        $updateData = [];
        if (isset($data['name'])) {
            $updateData['name'] = trim($data['name']);
        }
        
        if (!empty($updateData)) {
            $this->categoryRepo->update($id, $updateData);
        }
        
        return $this->getCategoryById($id);
    }
    
    /**
     * Xóa category (soft delete)
     * Set status = 0 để giữ lại dữ liệu cho products đã tồn tại
     */
    public function deleteCategory($id) {
        $category = $this->categoryRepo->findById($id);
        
        if (!$category) {
            throw new Exception('Không tìm thấy danh mục');
        }
        
        // Kiểm tra đã bị xóa chưa
        if (isset($category['status']) && $category['status'] == 0) {
            throw new Exception('Danh mục đã bị xóa trước đó');
        }
        
        // Kiểm tra xem có sản phẩm nào đang dùng category này không
        $productCount = $this->categoryRepo->countProducts($id);
        if ($productCount > 0) {
            throw new Exception("Không thể xóa danh mục này vì đang có {$productCount} sản phẩm");
        }
        
        // Soft delete: Set status = 0
        return $this->categoryRepo->update($id, ['status' => 0]);
    }
    
    /**
     * Khôi phục category đã xóa
     */
    public function restoreCategory($id) {
        $category = $this->categoryRepo->findById($id);
        
        if (!$category) {
            throw new Exception('Không tìm thấy danh mục');
        }
        
        if (!isset($category['status']) || $category['status'] == 1) {
            throw new Exception('Danh mục đang hoạt động, không cần khôi phục');
        }
        
        return $this->categoryRepo->update($id, ['status' => 1]);
    }
    
    /**
     * Validate dữ liệu category
     */
    private function validateCategory($data, $isCreate = false, $categoryId = null) {
        $errors = [];
        
        // Name validation
        if ($isCreate || isset($data['name'])) {
            if (empty($data['name'])) {
                $errors['name'] = 'Tên danh mục là bắt buộc';
            } elseif (strlen(trim($data['name'])) < 2) {
                $errors['name'] = 'Tên danh mục phải có ít nhất 2 ký tự';
            } elseif (strlen(trim($data['name'])) > 100) {
                $errors['name'] = 'Tên danh mục không được quá 100 ký tự';
            }
        }
        
        if (!empty($errors)) {
            throw new ValidationException('Dữ liệu không hợp lệ', $errors);
        }
    }
}
