<?php

/**
 * Application Entry Point
 * 
 * Bootstrap và khởi chạy ứng dụng
 */

// Bootstrap application
$app = require_once __DIR__ . '/bootstrap.php';
$config = $app['config'];

// Get request info
$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = str_replace($config['api_prefix'], '', $uri);
$uri = trim($uri, '/');

// Set default JSON header (có thể override trong route)
header('Content-Type: application/json; charset=utf-8');

// Initialize router
$router = new Router();

// Load routes
require_once __DIR__ . '/routes/api.php';

// Handle request
try {
    $result = $router->dispatch($method, $uri);
    
    if ($result === null) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Endpoint not found',
            'uri' => $uri,
            'method' => $method
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $config['debug'] ? $e->getMessage() : 'Internal server error',
        'trace' => $config['debug'] ? $e->getTraceAsString() : null
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
