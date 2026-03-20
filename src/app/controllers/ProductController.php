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
     * Body: multipart/form-data với các field: category_id, name, price, description, stock_quantity, min_stock, images[] (multiple files)
     * Hoặc JSON nếu không có ảnh
     * Admin/Staff only
     */
    public function store() {
        // Yêu cầu role ADMIN hoặc STAFF
        $this->requireRole([ROLE_ADMIN, ROLE_STAFF]);
        
        // Lấy data từ POST (multipart/form-data hoặc JSON)
        $data = $this->getFormData();
        
        // Xử lý upload nhiều ảnh nếu có
        if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
            try {
                $imageUrls = $this->handleMultipleImageUpload($_FILES['images']);
                // Lưu dạng string ngăn cách bằng dấu phẩy
                $data['image_url'] = implode(',', $imageUrls);
            } catch (Exception $e) {
                return $this->error($e->getMessage(), 422);
            }
        }
        
        try {
            $product = $this->productService->createProduct($data);
            return $this->success($product, 'Tạo sản phẩm thành công', 201);
            
        } catch (ValidationException $e) {
            // Nếu có lỗi và đã upload ảnh, xóa tất cả ảnh đi
            if (isset($data['image_url'])) {
                $this->deleteMultipleImages($data['image_url']);
            }
            return $this->error($e->getMessage(), 422, $e->getErrors());
        } catch (Exception $e) {
            // Nếu có lỗi và đã upload ảnh, xóa tất cả ảnh đi
            if (isset($data['image_url'])) {
                $this->deleteMultipleImages($data['image_url']);
            }
            return $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * Cập nhật product
     * PUT /api/products/{id}
     * Body: multipart/form-data với các field cần update, có thể có images[] (multiple files)
     * Hoặc JSON nếu không có ảnh
     * Admin/Staff only
     */
    public function update($id) {
        // Yêu cầu role ADMIN hoặc STAFF
        $this->requireRole([ROLE_ADMIN, ROLE_STAFF]);
        
        // Lấy data từ POST (multipart/form-data hoặc JSON)
        $data = $this->getFormData();
        
        // Xử lý upload nhiều ảnh mới nếu có
        if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
            try {
                $imageUrls = $this->handleMultipleImageUpload($_FILES['images']);
                // Lưu dạng string ngăn cách bằng dấu phẩy
                $data['image_url'] = implode(',', $imageUrls);
            } catch (Exception $e) {
                return $this->error($e->getMessage(), 422);
            }
        }
        
        try {
            $product = $this->productService->updateProduct($id, $data);
            return $this->success($product, 'Cập nhật sản phẩm thành công');
            
        } catch (ValidationException $e) {
            // Nếu có lỗi và đã upload ảnh mới, xóa ảnh mới đi
            if (isset($data['image_url'])) {
                $this->deleteMultipleImages($data['image_url']);
            }
            return $this->error($e->getMessage(), 422, $e->getErrors());
        } catch (Exception $e) {
            // Nếu có lỗi và đã upload ảnh mới, xóa ảnh mới đi
            if (isset($data['image_url'])) {
                $this->deleteMultipleImages($data['image_url']);
            }
            $statusCode = ($e->getMessage() === 'Không tìm thấy sản phẩm') ? 404 : 500;
            return $this->error($e->getMessage(), $statusCode);
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
    
    /**
     * Upload ảnh sản phẩm
     * POST /api/products/upload-image
     * Body: multipart/form-data với field "image"
     * Admin/Staff only
     */
    public function uploadImage() {
        // Yêu cầu role ADMIN hoặc STAFF
        $this->requireRole([ROLE_ADMIN, ROLE_STAFF]);
        
        // Kiểm tra có file không
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            return $this->error('Vui lòng chọn file ảnh', 422);
        }
        
        $file = $_FILES['image'];
        
        // Validate file type
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file['type'], $allowedTypes)) {
            return $this->error('Chỉ chấp nhận file ảnh (jpg, png, gif, webp)', 422);
        }
        
        // Validate file size (max 5MB)
        $maxSize = 5 * 1024 * 1024; // 5MB
        if ($file['size'] > $maxSize) {
            return $this->error('Kích thước file không được vượt quá 5MB', 422);
        }
        
        try {
            // Tạo tên file unique
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'product_' . time() . '_' . uniqid() . '.' . $extension;
            
            // Đường dẫn lưu file
            $uploadDir = __DIR__ . '/../../storage/uploads/';
            
            // Tạo thư mục nếu chưa tồn tại
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $filepath = $uploadDir . $filename;
            
            // Di chuyển file
            if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                throw new Exception('Không thể lưu file');
            }
            
            // Trả về URL của ảnh
            $imageUrl = '/uploads/' . $filename;
            
            return $this->success([
                'image_url' => $imageUrl,
                'filename' => $filename
            ], 'Upload ảnh thành công');
            
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * Lấy data từ form (multipart/form-data hoặc JSON)
     */
    private function getFormData() {
        // Nếu là multipart/form-data (có file upload)
        if (!empty($_POST)) {
            return $_POST;
        }
        
        // Nếu là JSON
        return $this->getBody();
    }
    
    /**
     * Xử lý upload nhiều ảnh
     */
    private function handleMultipleImageUpload($files) {
        $uploadedUrls = [];
        $uploadDir = __DIR__ . '/../../storage/uploads/';
        
        // Tạo thư mục nếu chưa tồn tại
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Validate file type
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        
        // Xử lý từng file
        $fileCount = count($files['name']);
        
        for ($i = 0; $i < $fileCount; $i++) {
            // Bỏ qua file lỗi
            if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                continue;
            }
            
            // Validate type
            if (!in_array($files['type'][$i], $allowedTypes)) {
                // Xóa các ảnh đã upload trước đó
                foreach ($uploadedUrls as $url) {
                    $this->deleteUploadedImage($url);
                }
                throw new Exception('Chỉ chấp nhận file ảnh (jpg, png, gif, webp)');
            }
            
            // Validate size
            if ($files['size'][$i] > $maxSize) {
                // Xóa các ảnh đã upload trước đó
                foreach ($uploadedUrls as $url) {
                    $this->deleteUploadedImage($url);
                }
                throw new Exception('Kích thước file không được vượt quá 5MB');
            }
            
            // Tạo tên file unique
            $extension = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
            $filename = 'product_' . time() . '_' . uniqid() . '.' . $extension;
            $filepath = $uploadDir . $filename;
            
            // Di chuyển file
            if (!move_uploaded_file($files['tmp_name'][$i], $filepath)) {
                // Xóa các ảnh đã upload trước đó
                foreach ($uploadedUrls as $url) {
                    $this->deleteUploadedImage($url);
                }
                throw new Exception('Không thể lưu file');
            }
            
            $uploadedUrls[] = '/uploads/' . $filename;
        }
        
        return $uploadedUrls;
    }
    
    /**
     * Xử lý upload ảnh
     */
    private function handleImageUpload($file) {
        // Validate file type
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file['type'], $allowedTypes)) {
            throw new Exception('Chỉ chấp nhận file ảnh (jpg, png, gif, webp)');
        }
        
        // Validate file size (max 5MB)
        $maxSize = 5 * 1024 * 1024; // 5MB
        if ($file['size'] > $maxSize) {
            throw new Exception('Kích thước file không được vượt quá 5MB');
        }
        
        // Tạo tên file unique
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'product_' . time() . '_' . uniqid() . '.' . $extension;
        
        // Đường dẫn lưu file
        $uploadDir = __DIR__ . '/../../storage/uploads/';
        
        // Tạo thư mục nếu chưa tồn tại
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $filepath = $uploadDir . $filename;
        
        // Di chuyển file
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            throw new Exception('Không thể lưu file');
        }
        
        // Trả về URL của ảnh
        return '/uploads/' . $filename;
    }
    
    /**
     * Xóa ảnh đã upload (khi có lỗi)
     */
    private function deleteUploadedImage($imageUrl) {
        $filename = basename($imageUrl);
        $filepath = __DIR__ . '/../../storage/uploads/' . $filename;
        
        if (file_exists($filepath)) {
            @unlink($filepath);
        }
    }
    
    /**
     * Xóa nhiều ảnh (string ngăn cách bằng dấu phẩy)
     */
    private function deleteMultipleImages($imageUrlString) {
        $imageUrls = explode(',', $imageUrlString);
        foreach ($imageUrls as $url) {
            $this->deleteUploadedImage(trim($url));
        }
    }
}

