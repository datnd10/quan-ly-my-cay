<?php

/**
 * Product Service
 * 
 * Business logic cho products
 */

class ProductService {
    private $productRepo;
    private $categoryRepo;
    
    public function __construct() {
        $this->productRepo = new ProductRepository();
        $this->categoryRepo = new CategoryRepository();
    }
    
    /**
     * Lấy danh sách products
     */
    public function getProducts($page, $perPage, $filters) {
        return $this->productRepo->getProducts($page, $perPage, $filters);
    }
    
    /**
     * Lấy product theo ID
     */
    public function getProductById($id) {
        $product = $this->productRepo->findById($id);
        
        if (!$product) {
            throw new Exception('Không tìm thấy sản phẩm');
        }
        
        return $product;
    }
    
    /**
     * Tạo product mới
     */
    public function createProduct($data) {
        // Validate
        $errors = $this->validateProduct($data);
        if (!empty($errors)) {
            throw new ValidationException('Dữ liệu không hợp lệ', $errors);
        }
        
        // Kiểm tra category tồn tại
        if (!empty($data['category_id'])) {
            $category = $this->categoryRepo->findById($data['category_id']);
            if (!$category) {
                throw new Exception('Danh mục không tồn tại');
            }
            
            // Kiểm tra tên sản phẩm trùng trong cùng category
            if ($this->productRepo->existsByNameAndCategory($data['name'], $data['category_id'])) {
                throw new ValidationException('Dữ liệu không hợp lệ', [
                    'name' => 'Tên sản phẩm đã tồn tại trong danh mục này'
                ]);
            }
        }
        
        return $this->productRepo->create($data);
    }
    
    /**
     * Cập nhật product
     */
    public function updateProduct($id, $data) {
        // Kiểm tra product tồn tại
        $product = $this->productRepo->findById($id);
        if (!$product) {
            throw new Exception('Không tìm thấy sản phẩm');
        }
        
        // Validate
        $errors = $this->validateProduct($data, true);
        if (!empty($errors)) {
            throw new ValidationException('Dữ liệu không hợp lệ', $errors);
        }
        
        // Kiểm tra category tồn tại (nếu có update)
        if (isset($data['category_id']) && !empty($data['category_id'])) {
            $category = $this->categoryRepo->findById($data['category_id']);
            if (!$category) {
                throw new Exception('Danh mục không tồn tại');
            }
            
            // Kiểm tra tên trùng trong category mới
            if (isset($data['name'])) {
                if ($this->productRepo->existsByNameAndCategory($data['name'], $data['category_id'], $id)) {
                    throw new ValidationException('Dữ liệu không hợp lệ', [
                        'name' => 'Tên sản phẩm đã tồn tại trong danh mục này'
                    ]);
                }
            }
        } elseif (isset($data['name'])) {
            // Chỉ update tên, giữ nguyên category
            $categoryId = $product->category_id ?? null;
            if ($categoryId && $this->productRepo->existsByNameAndCategory($data['name'], $categoryId, $id)) {
                throw new ValidationException('Dữ liệu không hợp lệ', [
                    'name' => 'Tên sản phẩm đã tồn tại trong danh mục này'
                ]);
            }
        }
        
        // Xóa ảnh cũ nếu có ảnh mới
        if (isset($data['image_url']) && !empty($data['image_url']) && !empty($product->image_url)) {
            $this->deleteImageFile($product->image_url);
        }
        
        return $this->productRepo->update($id, $data);
    }
    
    /**
     * Xóa product
     */
    public function deleteProduct($id) {
        // Kiểm tra product tồn tại
        $product = $this->productRepo->findById($id);
        if (!$product) {
            throw new Exception('Không tìm thấy sản phẩm');
        }
        
        // TODO: Kiểm tra product có trong order nào không
        // Nếu có thì không cho xóa hoặc soft delete
        
        // Xóa ảnh nếu có
        if (!empty($product->image_url)) {
            $this->deleteImageFile($product->image_url);
        }
        
        return $this->productRepo->delete($id);
    }
    
    /**
     * Xóa file ảnh trong storage
     */
    private function deleteImageFile($imageUrl) {
        // Nếu là chuỗi nhiều ảnh (ngăn cách bằng dấu phẩy)
        if (strpos($imageUrl, ',') !== false) {
            $imageUrls = explode(',', $imageUrl);
            foreach ($imageUrls as $url) {
                $this->deleteSingleImage(trim($url));
            }
        } else {
            $this->deleteSingleImage($imageUrl);
        }
    }
    
    /**
     * Xóa 1 file ảnh
     */
    private function deleteSingleImage($imageUrl) {
        // Kiểm tra xem là Cloudinary URL hay local
        if (strpos($imageUrl, 'cloudinary.com') !== false) {
            // Cloudinary URL - dùng CloudinaryService để xóa
            try {
                $cloudinaryService = new CloudinaryService();
                $publicId = $cloudinaryService->getPublicIdFromUrl($imageUrl);
                if ($publicId) {
                    $cloudinaryService->deleteImage($publicId);
                }
            } catch (Exception $e) {
                error_log('Failed to delete Cloudinary image: ' . $e->getMessage());
            }
        } else {
            // Local file - xóa từ storage/uploads
            $filename = basename($imageUrl);
            $filepath = __DIR__ . '/../../storage/uploads/' . $filename;
            
            if (file_exists($filepath)) {
                @unlink($filepath);
            }
        }
    }
    
    /**
     * Cập nhật stock
     */
    public function updateStock($id, $action, $quantity, $description = null) {
        // Kiểm tra product tồn tại
        $product = $this->productRepo->findById($id);
        if (!$product) {
            throw new Exception('Không tìm thấy sản phẩm');
        }
        
        if ($quantity <= 0) {
            throw new Exception('Số lượng phải lớn hơn 0');
        }
        
        $newStock = $product->stock_quantity;
        
        switch ($action) {
            case 'add':
                $newStock += $quantity;
                break;
            case 'subtract':
                $newStock -= $quantity;
                if ($newStock < 0) {
                    throw new Exception('Số lượng tồn kho không đủ');
                }
                break;
            case 'set':
                $newStock = $quantity;
                break;
            default:
                throw new Exception('Action không hợp lệ. Chỉ chấp nhận: add, subtract, set');
        }
        
        $this->productRepo->updateStock($id, $newStock);
        
        return $this->productRepo->findById($id);
    }
    
    /**
     * Lấy sản phẩm sắp hết hàng
     */
    public function getLowStockProducts() {
        return $this->productRepo->getLowStockProducts();
    }
    
    /**
     * Validate product data
     */
    private function validateProduct($data, $isUpdate = false) {
        $errors = [];
        
        // Name
        if (!$isUpdate || isset($data['name'])) {
            if (empty($data['name'])) {
                $errors['name'] = 'Tên sản phẩm không được để trống';
            } elseif (strlen($data['name']) > 150) {
                $errors['name'] = 'Tên sản phẩm không được vượt quá 150 ký tự';
            }
        }
        
        // Price
        if (!$isUpdate || isset($data['price'])) {
            if (!isset($data['price']) || $data['price'] === '') {
                $errors['price'] = 'Giá không được để trống';
            } elseif (!is_numeric($data['price']) || $data['price'] < 0) {
                $errors['price'] = 'Giá phải là số và lớn hơn hoặc bằng 0';
            }
        }
        
        // Stock quantity
        if (isset($data['stock_quantity'])) {
            if (!is_numeric($data['stock_quantity']) || $data['stock_quantity'] < 0) {
                $errors['stock_quantity'] = 'Số lượng tồn kho phải là số và lớn hơn hoặc bằng 0';
            }
        }
        
        // Min stock
        if (isset($data['min_stock'])) {
            if (!is_numeric($data['min_stock']) || $data['min_stock'] < 0) {
                $errors['min_stock'] = 'Số lượng tối thiểu phải là số và lớn hơn hoặc bằng 0';
            }
        }
        
        // Status
        if (isset($data['status'])) {
            if (!in_array($data['status'], [0, 1])) {
                $errors['status'] = 'Trạng thái chỉ có thể là 0 hoặc 1';
            }
        }
        
        // Image URL
        if (isset($data['image_url']) && !empty($data['image_url'])) {
            if (strlen($data['image_url']) > 255) {
                $errors['image_url'] = 'URL hình ảnh không được vượt quá 255 ký tự';
            }
        }
        
        return $errors;
    }
}
