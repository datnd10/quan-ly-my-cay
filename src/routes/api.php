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
        'mysql_connected' => getenv('MYSQLHOST') || getenv('MYSQL_PUBLIC_URL') ? true : false
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
});

// Swagger documentation
$router->get('docs', function() {
    header('Content-Type: text/html; charset=utf-8', true);
    echo file_get_contents(__DIR__ . '/../public/swagger.html');
    exit();
});

$router->get('docs/openapi.json', function() {
    // Đọc file template
    $openapi = json_decode(file_get_contents(__DIR__ . '/../docs/openapi-template.json'), true);
    
    // Force Railway domain
    $openapi['servers'] = [
        [
            'url' => 'https://seoul-spicy-production.up.railway.app/api',
            'description' => 'Production server (Railway)'
        ]
    ];
    
    header('Content-Type: application/json; charset=utf-8', true);
    header('Cache-Control: no-cache, no-store, must-revalidate', true);
    header('Pragma: no-cache', true);
    header('Expires: 0', true);
    echo json_encode($openapi, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit();
});

// ============================================
// AUTH ROUTES
// ============================================
$router->post('auth/register', 'AuthController@register');
$router->post('auth/login', 'AuthController@login');
$router->post('auth/refresh', 'AuthController@refresh');
$router->get('auth/me', 'AuthController@me');

// ============================================
// CATEGORY ROUTES
// ============================================
$router->get('categories/all', 'CategoryController@all'); // Public - không cần auth
$router->get('categories', 'CategoryController@index');
$router->get('categories/{id}', 'CategoryController@show');
$router->post('categories', 'CategoryController@store');
$router->put('categories/{id}', 'CategoryController@update');
$router->delete('categories/{id}', 'CategoryController@destroy');

// ============================================
// PRODUCT ROUTES
// ============================================
$router->get('products', 'ProductController@index');
$router->get('products/{id}', 'ProductController@show');
$router->post('products', 'ProductController@store');
$router->put('products/{id}', 'ProductController@update');
$router->delete('products/{id}', 'ProductController@destroy');

// ============================================
// CUSTOMER ROUTES
// ============================================
$router->get('customers', 'CustomerController@index');
$router->get('customers/{id}', 'CustomerController@show');
$router->post('customers', 'CustomerController@store');
$router->put('customers/{id}', 'CustomerController@update');
$router->put('customers/{id}/status', 'CustomerController@updateStatus');
$router->put('customers/{id}/points', 'CustomerController@updatePoints');
$router->get('customers/{id}/points/history', 'CustomerController@pointHistory');
$router->delete('customers/{id}', 'CustomerController@destroy');

// Customer self-service routes
$router->put('customers/profile', 'CustomerController@updateProfile');
$router->get('customers/my-points/history', 'CustomerController@myPointHistory');

// ============================================
// TABLE ROUTES
// ============================================
$router->get('tables', 'TableController@index');
$router->get('tables/{id}', 'TableController@show');
$router->post('tables', 'TableController@store');
$router->put('tables/{id}', 'TableController@update');
$router->put('tables/{id}/status', 'TableController@updateStatus');
$router->delete('tables/{id}', 'TableController@destroy');

// ============================================
// USER ROUTES (Admin only)
// ============================================
$router->get('users', 'UserController@index');
$router->get('users/{id}', 'UserController@show');
$router->post('users', 'UserController@store');
$router->put('users/{id}', 'UserController@update');
$router->put('users/{id}/status', 'UserController@updateStatus');
$router->delete('users/{id}', 'UserController@destroy');
