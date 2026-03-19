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

// Swagger documentation
$router->get('docs', function() {
    header('Content-Type: text/html; charset=utf-8', true);
    echo file_get_contents(__DIR__ . '/../public/swagger.html');
    exit();
});

$router->get('docs/openapi.json', function() {
    // Đọc file openapi.json
    $openapi = json_decode(file_get_contents(__DIR__ . '/../docs/openapi.json'), true);
    
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

// Example: Products routes (sẽ implement sau)
// $router->get('products', 'ProductController@index');
// $router->get('products/{id}', 'ProductController@show');
// $router->post('products', 'ProductController@store');
// $router->put('products/{id}', 'ProductController@update');
// $router->delete('products/{id}', 'ProductController@destroy');
