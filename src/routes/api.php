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

// Swagger documentation
$router->get('docs', function() {
    header('Content-Type: text/html; charset=utf-8', true);
    echo file_get_contents(__DIR__ . '/../public/swagger.html');
    exit();
});

$router->get('docs/openapi.json', function() {
    header('Content-Type: application/json; charset=utf-8', true);
    echo file_get_contents(__DIR__ . '/../docs/openapi.json');
    exit();
});

// Example: Products routes (sẽ implement sau)
// $router->get('products', 'ProductController@index');
// $router->get('products/{id}', 'ProductController@show');
// $router->post('products', 'ProductController@store');
// $router->put('products/{id}', 'ProductController@update');
// $router->delete('products/{id}', 'ProductController@destroy');
