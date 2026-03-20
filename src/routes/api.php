<?php

/**
 * API Routes
 * 
 * Định nghĩa các routes cho API
 */

// Health check
$router->get('', function() {
    $config = require __DIR__ . '/../config/app.php';
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Spicy Noodle API is running',
        'version' => $config['version'],
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
});

$router->get('health', function() {
    $config = require __DIR__ . '/../config/app.php';
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Spicy Noodle API is running',
        'version' => $config['version'],
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
});

// Test database connection
$router->get('test-db', function() {
    $dbConfig = require __DIR__ . '/../config/database.php';
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Database connection successful',
        'database' => $dbConfig['connections']['mysql']['database']
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
});

// Run migrations - Tạo tất cả bảng tự động
$router->get('migrate', function() {
    try {
        $migration = new Migration();
        $migration->run();
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Database migration completed successfully'
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Migration failed: ' . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
});

// Check environment (chỉ dùng để debug)
$router->get('env-check', function() {
    $config = require __DIR__ . '/../config/app.php';
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'environment' => $config['env'],
        'debug' => $config['debug'],
        'url' => $config['url'],
        'railway_detected' => getenv('RAILWAY_ENVIRONMENT') ? true : false,
        'mysql_connected' => getenv('MYSQLHOST') ? true : false
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
});

// Get password requirements
$router->get('password-requirements', function() {
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => [
            'requirements' => PasswordValidator::getRequirements(),
            'example' => 'Example@123'
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
});

// Swagger documentation
$router->get('docs', function() {
    header('Content-Type: text/html; charset=utf-8', true);
    echo file_get_contents(__DIR__ . '/../public/swagger.html');
    exit();
});

$router->get('docs/openapi.json', function() {
    // Đọc file generated từ annotations
    $openApiFile = __DIR__ . '/../docs/openapi.json';
    
    // Nếu chưa có file, generate mặc định
    if (!file_exists($openApiFile)) {
        $openapi = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Spicy Noodle API',
                'version' => '1.0.0',
                'description' => 'REST API - Run: php generate-openapi.php to update docs'
            ],
            'servers' => [
                ['url' => 'https://seoul-spicy-production.up.railway.app/api'],
                ['url' => 'http://localhost:8000/api']
            ],
            'paths' => []
        ];
    } else {
        $openapi = json_decode(file_get_contents($openApiFile), true);
    }
    
    header('Content-Type: application/json; charset=utf-8', true);
    header('Cache-Control: no-cache, no-store, must-revalidate', true);
    echo json_encode($openapi, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit();
});

// ============================================
// AUTH ROUTES
// ============================================
$router->post('auth/login', 'AuthController@login');
$router->post('auth/register', 'AuthController@register');
$router->get('auth/me', 'AuthController@me');
$router->post('auth/change-password', 'AuthController@changePassword');
$router->post('auth/forgot-password', 'AuthController@forgotPassword');

// ============================================
// USER ROUTES (Admin only)
// ============================================
$router->get('users', 'UserController@index');
$router->get('users/{id}', 'UserController@show');
$router->post('users', 'UserController@store');
$router->put('users/{id}', 'UserController@update');
$router->delete('users/{id}', 'UserController@destroy');

// ============================================
// CUSTOMER ROUTES (Admin/Staff)
// ============================================
$router->get('customers', 'CustomerController@index');
$router->get('customers/{id}', 'CustomerController@show');
$router->post('customers', 'CustomerController@store');
$router->put('customers/{id}', 'CustomerController@update');
$router->put('customers/{id}/status', 'CustomerController@updateStatus');
$router->put('customers/{id}/points', 'CustomerController@updatePoints');
$router->get('customers/{id}/points/history', 'CustomerController@pointHistory');
$router->delete('customers/{id}', 'CustomerController@destroy');

// Customer tự cập nhật profile
$router->put('customers/profile', 'CustomerController@updateProfile');

// Customer xem lịch sử điểm của mình
$router->get('customers/my-points/history', 'CustomerController@myPointHistory');

// ============================================
// CATEGORY ROUTES
// ============================================
$router->get('categories/all', 'CategoryController@all'); // Public - lấy tất cả
$router->get('categories', 'CategoryController@index'); // Public - có phân trang
$router->get('categories/{id}', 'CategoryController@show'); // Public
$router->post('categories', 'CategoryController@store')->middleware('AuthMiddleware'); // Admin/Staff
$router->put('categories/{id}', 'CategoryController@update')->middleware('AuthMiddleware'); // Admin/Staff
$router->delete('categories/{id}', 'CategoryController@destroy')->middleware('AuthMiddleware'); // Admin only

// ============================================
// PRODUCT ROUTES
// ============================================
$router->get('products/low-stock', 'ProductController@lowStock')->middleware('AuthMiddleware'); // Admin/Staff - phải đặt trước products/{id}
$router->get('products', 'ProductController@index'); // Public - có phân trang + filter
$router->get('products/{id}', 'ProductController@show'); // Public
$router->post('products', 'ProductController@store')->middleware('AuthMiddleware'); // Admin/Staff
$router->put('products/{id}', 'ProductController@update')->middleware('AuthMiddleware'); // Admin/Staff
$router->put('products/{id}/stock', 'ProductController@updateStock')->middleware('AuthMiddleware'); // Admin/Staff
$router->delete('products/{id}', 'ProductController@destroy')->middleware('AuthMiddleware'); // Admin only

// ============================================
// TABLE ROUTES
// ============================================
$router->get('tables/available', 'TableController@available'); // Public - bàn trống
$router->get('tables/all', 'TableController@all'); // Public - tất cả bàn (không phân trang)
$router->get('tables', 'TableController@index')->middleware('AuthMiddleware'); // Admin/Staff - có phân trang
$router->get('tables/{id}', 'TableController@show'); // Public
$router->post('tables', 'TableController@store')->middleware('AuthMiddleware'); // Admin/Staff
$router->put('tables/{id}', 'TableController@update')->middleware('AuthMiddleware'); // Admin/Staff
$router->put('tables/{id}/status', 'TableController@updateStatus')->middleware('AuthMiddleware'); // Admin/Staff
$router->delete('tables/{id}', 'TableController@destroy')->middleware('AuthMiddleware'); // Admin only
