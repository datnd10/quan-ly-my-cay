<?php

/**
 * Product Controller
 * 
 * Quản lý sản phẩm
 */

class ProductController extends Controller {
    private $productService;
    private $cloudinaryService;
    
    public function __construct() {
        $this->productService = new ProductService();
        
        // Khởi tạo Cloudinary service
        try {
            $this->cloudinaryService = new CloudinaryService();
        } catch (Exception $e) {
            // Nếu không config Cloudinary, để null (fallback về local storage)
            $this->cloudinaryService = null;
        }
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
        $this->requireRole([ROLE_ADMIN, ROLE_STAFF]);
        
        $data = $this->getFormData();
        $imageFiles = $this->getImageFiles();
        
        if ($imageFiles) {
            try {
                $imageUrls = $this->handleMultipleImageUpload($imageFiles);
                $data['image_url'] = implode('|', $imageUrls);
            } catch (Exception $e) {
                return $this->error($e->getMessage(), 422);
            }
        }
        
        try {
            $product = $this->productService->createProduct($data);
            return $this->success($product, 'Tạo sản phẩm thành công', 201);
            
        } catch (ValidationException $e) {
            if (isset($data['image_url'])) {
                $this->deleteMultipleImages($data['image_url']);
            }
            return $this->error($e->getMessage(), 422, $e->getErrors());
        } catch (Exception $e) {
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
        $this->requireRole([ROLE_ADMIN, ROLE_STAFF]);
        
        $data = $this->getFormData();
        $imageFiles = $this->getImageFiles();
        
        if ($imageFiles) {
            try {
                $imageUrls = $this->handleMultipleImageUpload($imageFiles);
                $data['image_url'] = implode('|', $imageUrls);
            } catch (Exception $e) {
                return $this->error($e->getMessage(), 422);
            }
        }
        
        try {
            $product = $this->productService->updateProduct($id, $data);
            return $this->success($product, 'Cập nhật sản phẩm thành công');
            
        } catch (ValidationException $e) {
            if (isset($data['image_url'])) {
                $this->deleteMultipleImages($data['image_url']);
            }
            return $this->error($e->getMessage(), 422, $e->getErrors());
        } catch (Exception $e) {
            if (isset($data['image_url'])) {
                $this->deleteMultipleImages($data['image_url']);
            }
            $statusCode = ($e->getMessage() === 'Không tìm thấy sản phẩm') ? 404 : 500;
            return $this->error($e->getMessage(), $statusCode);
        }
    }
    
    /**
     * Xóa product (soft delete)
     * DELETE /api/products/{id}
     * Admin only
     */
    public function destroy($id) {
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
     * Khôi phục product đã xóa
     * POST /api/products/{id}/restore
     * Admin only
     */
    public function restore($id) {
        $this->requireRole([ROLE_ADMIN]);
        
        try {
            $product = $this->productService->restoreProduct($id);
            return $this->success($product, 'Khôi phục sản phẩm thành công');
            
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
        $this->requireRole([ROLE_ADMIN, ROLE_STAFF]);
        
        try {
            $products = $this->productService->getLowStockProducts();
            return $this->success($products);
            
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * Lấy data từ form (multipart/form-data hoặc JSON)
     */
    private function getFormData() {
        if (!empty($_POST)) {
            $data = [];
            foreach ($_POST as $key => $value) {
                if (in_array($key, ['price', 'stock_quantity', 'min_stock', 'status', 'category_id'])) {
                    $data[$key] = is_numeric($value) ? ($key === 'price' ? (float)$value : (int)$value) : $value;
                } else {
                    $data[$key] = $value;
                }
            }
            return $data;
        }
        
        return $this->getBody();
    }
    
    /**
     * Lấy image files từ $_FILES
     */
    private function getImageFiles() {
        if (isset($_FILES['images']) && $_FILES['images']['error'][0] !== UPLOAD_ERR_NO_FILE) {
            return $_FILES['images'];
        }
        return null;
    }
    
    /**
     * Xử lý upload nhiều ảnh
     */
    private function handleMultipleImageUpload($files) {
        // Nếu có Cloudinary, dùng Cloudinary
        if ($this->cloudinaryService) {
            return $this->handleCloudinaryUpload($files);
        }
        
        // Fallback: Local storage
        return $this->handleLocalUpload($files);
    }
    
    /**
     * Upload lên Cloudinary
     */
    private function handleCloudinaryUpload($files) {
        $uploadedResults = [];
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        
        // Normalize: nếu upload 1 file, PHP không tự động tạo array
        if (!is_array($files['name'])) {
            $files = [
                'name' => [$files['name']],
                'type' => [$files['type']],
                'tmp_name' => [$files['tmp_name']],
                'error' => [$files['error']],
                'size' => [$files['size']]
            ];
        }
        
        $fileCount = count($files['name']);
        $tempFiles = [];
        
        for ($i = 0; $i < $fileCount; $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                continue;
            }
            
            if (!in_array($files['type'][$i], $allowedTypes)) {
                foreach ($uploadedResults as $result) {
                    $this->cloudinaryService->deleteImage($result['public_id']);
                }
                throw new Exception('Chỉ chấp nhận file ảnh (jpg, png, gif, webp)');
            }
            
            if ($files['size'][$i] > $maxSize) {
                foreach ($uploadedResults as $result) {
                    $this->cloudinaryService->deleteImage($result['public_id']);
                }
                throw new Exception('Kích thước file không được vượt quá 5MB');
            }
            
            $tempFiles[] = $files['tmp_name'][$i];
        }
        
        try {
            $uploadedResults = $this->cloudinaryService->uploadMultipleImages($tempFiles, 'products');
            return array_column($uploadedResults, 'url');
            
        } catch (Exception $e) {
            throw new Exception('Upload ảnh thất bại: ' . $e->getMessage());
        }
    }
    
    /**
     * Upload local (fallback)
     */
    private function handleLocalUpload($files) {
        $uploadedUrls = [];
        $uploadDir = __DIR__ . '/../../storage/uploads/';
        
        // Tạo thư mục nếu chưa tồn tại
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Validate file type
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        
        // Normalize: nếu upload 1 file, PHP không tự động tạo array
        if (!is_array($files['name'])) {
            $files = [
                'name' => [$files['name']],
                'type' => [$files['type']],
                'tmp_name' => [$files['tmp_name']],
                'error' => [$files['error']],
                'size' => [$files['size']]
            ];
        }
        
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
     * Xóa ảnh local (khi có lỗi)
     */
    private function deleteUploadedImage($imageUrl) {
        $filename = basename($imageUrl);
        $filepath = __DIR__ . '/../../storage/uploads/' . $filename;
        
        if (file_exists($filepath)) {
            @unlink($filepath);
        }
    }
    
    /**
     * Xóa nhiều ảnh (string ngăn cách bằng dấu |)
     */
    private function deleteMultipleImages($imageUrlString) {
        $imageUrls = explode('|', $imageUrlString);
        foreach ($imageUrls as $url) {
            $url = trim($url);
            
            // Nếu là Cloudinary URL
            if (strpos($url, 'cloudinary.com') !== false && $this->cloudinaryService) {
                $publicId = $this->cloudinaryService->getPublicIdFromUrl($url);
                if ($publicId) {
                    $this->cloudinaryService->deleteImage($publicId);
                }
            } else {
                // Local file
                $this->deleteUploadedImage($url);
            }
        }
    }
}

