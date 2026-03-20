<?php

/**
 * Application Entry Point (Điểm vào của ứng dụng)
 * 
 * File này là file đầu tiên được chạy khi có request đến API
 * Luồng hoạt động:
 * 1. Bootstrap app (load config, autoload classes...)
 * 2. Parse request (lấy method, URI)
 * 3. Khởi tạo Router và load routes
 * 4. Dispatch request đến route tương ứng
 * 5. Trả về response hoặc lỗi 404/500
 */

// ============================================
// 1. BOOTSTRAP APPLICATION
// ============================================
// Gọi bootstrap.php để chuẩn bị môi trường
// $app chứa config và dbConfig
$app = require_once __DIR__ . '/bootstrap.php';
$config = $app['config']; // Lấy config để dùng

// ============================================
// 2. PARSE REQUEST
// ============================================
// Lấy HTTP method (GET, POST, PUT, DELETE...)
$method = $_SERVER['REQUEST_METHOD'];

// Lấy URI từ request
// VD: Request đến http://localhost:8000/api/products/123
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH); // => /api/products/123

// ============================================
// 2.1. SERVE STATIC FILES (UPLOADED IMAGES)
// ============================================
// Nếu request đến /uploads/*, serve file trực tiếp
if (strpos($uri, '/uploads/') === 0) {
    $filename = basename($uri);
    $filepath = __DIR__ . '/../storage/uploads/' . $filename;
    
    if (file_exists($filepath)) {
        // Xác định MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $filepath);
        finfo_close($finfo);
        
        // Set header và output file
        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit();
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'File not found']);
        exit();
    }
}

// Bỏ prefix '/api' để còn 'products/123'
$uri = str_replace($config['api_prefix'], '', $uri); // => /products/123

// Bỏ dấu / đầu và cuối để còn 'products/123'
$uri = trim($uri, '/'); // => products/123

// ============================================
// 3. SET DEFAULT HEADER
// ============================================
// Mặc định trả về JSON (route có thể override nếu cần)
header('Content-Type: application/json; charset=utf-8');

// ============================================
// 4. KHỞI TẠO ROUTER
// ============================================
// Router sẽ quản lý tất cả routes
$router = new Router();

// ============================================
// 5. LOAD ROUTES
// ============================================
// Load file routes/api.php - nơi định nghĩa tất cả routes
// File đó sẽ gọi $router->get(), $router->post()...
require_once __DIR__ . '/routes/api.php';

// ============================================
// 6. DISPATCH REQUEST
// ============================================
try {
    // Gọi Router để tìm và chạy route phù hợp
    // VD: GET products/123 -> ProductController@show(123)
    $result = $router->dispatch($method, $uri);
    
    // Nếu không tìm thấy route nào khớp
    if ($result === null) {
        http_response_code(404); // Set status code 404
        echo json_encode([
            'success' => false,
            'message' => 'Endpoint not found',
            'uri' => $uri,           // URI đã request
            'method' => $method      // Method đã dùng
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
    
} catch (Exception $e) {
    // Nếu có lỗi xảy ra trong quá trình xử lý
    http_response_code(500); // Set status code 500
    echo json_encode([
        'success' => false,
        // Nếu debug mode: hiện message chi tiết, ngược lại: ẩn đi
        'message' => $config['debug'] ? $e->getMessage() : 'Internal server error',
        // Nếu debug mode: hiện stack trace, ngược lại: null
        'trace' => $config['debug'] ? $e->getTraceAsString() : null
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
