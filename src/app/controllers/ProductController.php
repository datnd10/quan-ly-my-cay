<?php

/**
 * Product Controller
 * 
 * Quản lý sản phẩm
 */

class ProductController extends Controller {
    private $productService;
    
    public function __construct() {
        $this->productService = new ProductService();
    }
    
    /**
     * Lấy danh sách products
     * GET /api/products?page=1&per_page=20&category_id=1&status=1&search=my&low_stock=1
     * Public - không cần auth
     */
    public function index() {
        $page = max(1, (int)$this->getQuery('page', 1));
        $perPage = min(100, max(1, (int)$this->getQuery('per_page', 20)));
        
        $filters = [];
        
        if ($categoryId = $this->getQuery('category_id')) {
            $filters['category_id'] = $categoryId;
        }
        
        if (isset($_GET['status'])) {
            $filters['status'] = (int)$this->getQuery('status');
        }
        
        if ($search = $this->getQuery('search')) {
            $filters['search'] = $search;
        }
        
        if ($this->getQuery('low_stock')) {
            $filters['low_stock'] = true;
        }
        
        try {
            $result = $this->productService->getProducts($page, $perPage, $filters);
            return $this->paginate($result['products'], $result['total'], $page, $perPage);
            
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * Lấy chi tiết product
     * GET /api/products/{id}
     * Public
     */
    public function show($id) {
        try {
            $product = $this->productService->getProductById($id);
            return $this->success($product);
            
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 404);
        }
    }
    
    /**
     * Tạo product mới
     * POST /api/products
     * Body: { "category_id": 1, "name": "Mỳ cay level 1", "price": 50000, "description": "...", "stock_quantity": 100, "min_stock": 10 }
     * Admin/Staff only
     */
    public function store() {
        // Yêu cầu role ADMIN hoặc STAFF
        $this->requireRole([ROLE_ADMIN, ROLE_STAFF]);
        
        $data = $this->getBody();
        
        try {
            $product = $this->productService->createProduct($data);
            return $this->success($product, 'Tạo sản phẩm thành công', 201);
            
        } catch (Exception $e) {
            $statusCode = isset($e->errors) ? 422 : 500;
            $errors = isset($e->errors) ? $e->errors : null;
            return $this->error($e->getMessage(), $statusCode, $errors);
        }
    }
    
    /**
     * Cập nhật product
     * PUT /api/products/{id}
     * Body: { "name": "Mỳ cay level 2", "price": 60000, ... }
     * Admin/Staff only
     */
    public function update($id) {
        // Yêu cầu role ADMIN hoặc STAFF
        $this->requireRole([ROLE_ADMIN, ROLE_STAFF]);
        
        $data = $this->getBody();
        
        try {
            $product = $this->productService->updateProduct($id, $data);
            return $this->success($product, 'Cập nhật sản phẩm thành công');
            
        } catch (Exception $e) {
            $statusCode = isset($e->errors) ? 422 : ($e->getMessage() === 'Không tìm thấy sản phẩm' ? 404 : 500);
            $errors = isset($e->errors) ? $e->errors : null;
            return $this->error($e->getMessage(), $statusCode, $errors);
        }
    }
    
    /**
     * Xóa product
     * DELETE /api/products/{id}
     * Admin only
     */
    public function destroy($id) {
        // Yêu cầu role ADMIN
        $this->requireRole([ROLE_ADMIN]);
        
        try {
            $this->productService->deleteProduct($id);
            return $this->success(null, 'Xóa sản phẩm thành công');
            
        } catch (Exception $e) {
            $statusCode = ($e->getMessage() === 'Không tìm thấy sản phẩm') ? 404 : 400;
            return $this->error($e->getMessage(), $statusCode);
        }
    }
    
    /**
     * Cập nhật stock
     * PUT /api/products/{id}/stock
     * Body: { "action": "add|subtract|set", "quantity": 50, "description": "Nhập hàng" }
     * Admin/Staff only
     */
    public function updateStock($id) {
        // Yêu cầu role ADMIN hoặc STAFF
        $this->requireRole([ROLE_ADMIN, ROLE_STAFF]);
        
        $data = $this->getBody();
        
        if (empty($data['action'])) {
            return $this->error('Action không được để trống', 422);
        }
        
        if (!isset($data['quantity']) || $data['quantity'] === '') {
            return $this->error('Quantity không được để trống', 422);
        }
        
        try {
            $product = $this->productService->updateStock(
                $id,
                $data['action'],
                (int)$data['quantity'],
                $data['description'] ?? null
            );
            
            return $this->success($product, 'Cập nhật tồn kho thành công');
            
        } catch (Exception $e) {
            $statusCode = ($e->getMessage() === 'Không tìm thấy sản phẩm') ? 404 : 400;
            return $this->error($e->getMessage(), $statusCode);
        }
    }
    
    /**
     * Lấy danh sách sản phẩm sắp hết hàng
     * GET /api/products/low-stock
     * Admin/Staff only
     */
    public function lowStock() {
        // Yêu cầu role ADMIN hoặc STAFF
        $this->requireRole([ROLE_ADMIN, ROLE_STAFF]);
        
        try {
            $products = $this->productService->getLowStockProducts();
            return $this->success($products);
            
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
}
