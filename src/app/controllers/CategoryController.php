<?php

/**
 * Category Controller
 * 
 * Quản lý danh mục món ăn
 */

class CategoryController extends Controller {
    private $categoryService;
    
    public function __construct() {
        $this->categoryService = new CategoryService();
    }
    
    /**
     * Lấy tất cả categories (không phân trang)
     * GET /api/categories/all
     * Public - không cần auth
     */
    public function all() {
        try {
            $categories = $this->categoryService->getAllCategories();
            return $this->success($categories);
            
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * Lấy danh sách categories (có phân trang)
     * GET /api/categories?page=1&per_page=20&search=my
     */
    public function index() {
        $this->requireRole([ROLE_ADMIN, ROLE_STAFF]);
        $page = max(1, (int)$this->getQuery('page', 1));
        $perPage = min(100, max(1, (int)$this->getQuery('per_page', 20)));
        
        $filters = [];
        if ($search = $this->getQuery('search')) {
            $filters['search'] = $search;
        }
        
        try {
            $result = $this->categoryService->getCategories($page, $perPage, $filters);
            return $this->paginate($result['categories'], $result['total'], $page, $perPage);
            
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * Lấy chi tiết category
     * GET /api/categories/{id}
     */
    public function show($id) {
        $this->requireRole([ROLE_ADMIN, ROLE_STAFF]);
        try {
            $category = $this->categoryService->getCategoryById($id);
            return $this->success($category);
            
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 404);
        }
    }
    
    /**
     * Tạo category mới
     * POST /api/categories
     * Body: { "name": "Mỳ cay" }
     */
    public function store() {
        // Yêu cầu role ADMIN hoặc STAFF
        $this->requireRole([ROLE_ADMIN, ROLE_STAFF]);
        
        $data = $this->getBody();
        
        try {
            $category = $this->categoryService->createCategory($data);
            return $this->success($category, 'Tạo danh mục thành công', 201);
            
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422, $e->getErrors());
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * Cập nhật category
     * PUT /api/categories/{id}
     * Body: { "name": "Mỳ cay đặc biệt" }
     */
    public function update($id) {
        // Yêu cầu role ADMIN hoặc STAFF
        $this->requireRole([ROLE_ADMIN, ROLE_STAFF]);
        
        $data = $this->getBody();
        
        try {
            $category = $this->categoryService->updateCategory($id, $data);
            return $this->success($category, 'Cập nhật danh mục thành công');
            
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422, $e->getErrors());
        } catch (Exception $e) {
            $statusCode = ($e->getMessage() === 'Không tìm thấy danh mục') ? 404 : 500;
            return $this->error($e->getMessage(), $statusCode);
        }
    }
    
    /**
     * Xóa category (soft delete)
     * DELETE /api/categories/{id}
     */
    public function destroy($id) {
        // Yêu cầu role ADMIN
        $this->requireRole([ROLE_ADMIN]);
        
        try {
            $this->categoryService->deleteCategory($id);
            return $this->success(null, 'Xóa danh mục thành công (soft delete)');
            
        } catch (Exception $e) {
            $statusCode = ($e->getMessage() === 'Không tìm thấy danh mục') ? 404 : 400;
            return $this->error($e->getMessage(), $statusCode);
        }
    }
    
    /**
     * Khôi phục category đã xóa
     * POST /api/categories/{id}/restore
     * Admin only
     */
    public function restore($id) {
        $this->requireRole([ROLE_ADMIN]);
        
        try {
            $category = $this->categoryService->restoreCategory($id);
            return $this->success($category, 'Khôi phục danh mục thành công');
            
        } catch (Exception $e) {
            $statusCode = ($e->getMessage() === 'Không tìm thấy danh mục') ? 404 : 400;
            return $this->error($e->getMessage(), $statusCode);
        }
    }
}
