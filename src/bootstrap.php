<?php

/**
 * Bootstrap Application
 * 
 * File này được gọi đầu tiên khi app khởi động (từ index.php)
 * Nhiệm vụ: Chuẩn bị môi trường để app có thể chạy
 */

// ============================================
// 1. CẤU HÌNH LỖI
// ============================================
error_reporting(E_ALL);        // Báo tất cả loại lỗi
ini_set('display_errors', 1);  // Hiển thị lỗi ra màn hình (chỉ dùng khi dev)

// ============================================
// 2. LOAD CÁC FILE CẤU HÌNH
// ============================================
// Load file config và lưu vào biến để dùng sau
$config = require_once __DIR__ . '/config/app.php';      // Cấu hình app
$dbConfig = require_once __DIR__ . '/config/database.php'; // Cấu hình database
require_once __DIR__ . '/config/constants.php';           // Load constants (không cần return)

// ============================================
// 3. SET TIMEZONE
// ============================================
// Đặt múi giờ cho toàn bộ app (ảnh hưởng date(), time()...)
date_default_timezone_set($config['timezone']); // 'Asia/Ho_Chi_Minh'

// ============================================
// 4. AUTOLOAD CLASSES
// ============================================
// Tự động load class khi được gọi, không cần require_once thủ công
// VD: Khi gọi "new Router()", PHP sẽ tự tìm và load file Router.php
spl_autoload_register(function ($class) {
    // Danh sách các thư mục có thể chứa class
    $paths = [
        __DIR__ . '/app/core/' . $class . '.php',        // Core classes: Router, Database...
        __DIR__ . '/app/models/' . $class . '.php',      // Models: Product, Order...
        __DIR__ . '/app/controllers/' . $class . '.php', // Controllers: ProductController...
        __DIR__ . '/app/middlewares/' . $class . '.php', // Middlewares: AuthMiddleware...
        __DIR__ . '/app/services/' . $class . '.php',    // Services: OrderService...
        __DIR__ . '/app/validators/' . $class . '.php',  // Validators: ProductValidator...
        __DIR__ . '/utils/' . $class . '.php',           // Utilities: Helper functions...
    ];
    
    // Duyệt qua từng path, nếu file tồn tại thì load
    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return; // Tìm thấy rồi thì dừng
        }
    }
});

// ============================================
// 5. CORS HEADERS
// ============================================
// Cho phép frontend từ domain khác gọi API
header('Access-Control-Allow-Origin: *');  // Cho phép tất cả domain
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS'); // Các HTTP methods
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With'); // Headers được phép

// ============================================
// 6. XỬ LÝ PREFLIGHT REQUEST
// ============================================
// Browser gửi OPTIONS request trước khi gửi request thật (CORS preflight)
// Ta chỉ cần trả 200 OK và dừng
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ============================================
// 7. RETURN CONFIG
// ============================================
// Trả về config để index.php sử dụng
return [
    'config' => $config,
    'dbConfig' => $dbConfig
];
