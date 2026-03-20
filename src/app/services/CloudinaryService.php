<?php

use Cloudinary\Cloudinary;
use Cloudinary\Api\Upload\UploadApi;

/**
 * Cloudinary Service
 * 
 * Xử lý upload/delete ảnh lên Cloudinary
 */
class CloudinaryService {
    private $cloudinary;
    
    public function __construct() {
        // Lấy config từ env
        $cloudName = getenv('CLOUDINARY_CLOUD_NAME');
        $apiKey = getenv('CLOUDINARY_API_KEY');
        $apiSecret = getenv('CLOUDINARY_API_SECRET');
        
        if (!$cloudName || !$apiKey || !$apiSecret) {
            throw new Exception('Cloudinary credentials not configured');
        }
        
        // Khởi tạo Cloudinary
        $this->cloudinary = new Cloudinary([
            'cloud' => [
                'cloud_name' => $cloudName,
                'api_key' => $apiKey,
                'api_secret' => $apiSecret
            ]
        ]);
    }
    
    /**
     * Upload 1 ảnh lên Cloudinary
     * 
     * @param string $filePath - Đường dẫn file tạm
     * @param string $folder - Folder trên Cloudinary (vd: 'products')
     * @return array - ['url' => 'https://...', 'public_id' => '...']
     */
    public function uploadImage($filePath, $folder = 'products') {
        try {
            $result = $this->cloudinary->uploadApi()->upload($filePath, [
                'folder' => $folder,
                'resource_type' => 'image',
                'transformation' => [
                    'quality' => 'auto',
                    'fetch_format' => 'auto'
                ]
            ]);
            
            return [
                'url' => $result['secure_url'],
                'public_id' => $result['public_id']
            ];
            
        } catch (Exception $e) {
            throw new Exception('Upload to Cloudinary failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Upload nhiều ảnh
     * 
     * @param array $filePaths - Mảng đường dẫn file tạm
     * @param string $folder
     * @return array - Mảng ['url' => '...', 'public_id' => '...']
     */
    public function uploadMultipleImages($filePaths, $folder = 'products') {
        $results = [];
        
        foreach ($filePaths as $filePath) {
            try {
                $results[] = $this->uploadImage($filePath, $folder);
            } catch (Exception $e) {
                // Nếu có lỗi, xóa các ảnh đã upload trước đó
                foreach ($results as $uploaded) {
                    $this->deleteImage($uploaded['public_id']);
                }
                throw $e;
            }
        }
        
        return $results;
    }
    
    /**
     * Xóa ảnh trên Cloudinary
     * 
     * @param string $publicId - Public ID của ảnh
     * @return bool
     */
    public function deleteImage($publicId) {
        try {
            $this->cloudinary->uploadApi()->destroy($publicId);
            return true;
        } catch (Exception $e) {
            // Log error nhưng không throw để không block flow
            error_log('Cloudinary delete failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Xóa nhiều ảnh
     * 
     * @param array $publicIds - Mảng public IDs
     */
    public function deleteMultipleImages($publicIds) {
        foreach ($publicIds as $publicId) {
            $this->deleteImage($publicId);
        }
    }
    
    /**
     * Parse public_id từ Cloudinary URL
     * 
     * @param string $url - https://res.cloudinary.com/xxx/image/upload/v123/products/abc.jpg
     * @return string - products/abc
     */
    public function getPublicIdFromUrl($url) {
        // Extract public_id from Cloudinary URL
        // Format: https://res.cloudinary.com/{cloud_name}/image/upload/v{version}/{public_id}.{format}
        if (preg_match('/\/upload\/v\d+\/(.+)\.\w+$/', $url, $matches)) {
            return $matches[1];
        }
        return null;
    }
}
