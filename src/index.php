<?php

/**
 * Application Entry Point
 * 
 * Bootstrap và khởi chạy ứng dụng
 */

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load configurations
$config = require_once __DIR__ . '/config/app.php';
$dbConfig = require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';

// Set timezone
date_default_timezone_set($config['timezone']);

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Simple response helper
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit();
}

// Get request info
$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = str_replace($config['api_prefix'], '', $uri);
$uri = trim($uri, '/');

// Simple routing
try {
    // Health check endpoint
    if ($uri === 'health' || $uri === '') {
        jsonResponse([
            'success' => true,
            'message' => 'Spicy Noodle API is running',
            'version' => $config['version'],
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
    
    // Test database connection
    if ($uri === 'test-db') {
        require_once __DIR__ . '/core/Database.php';
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        jsonResponse([
            'success' => true,
            'message' => 'Database connection successful',
            'database' => $dbConfig['connections']['mysql']['database']
        ]);
    }
    
    // API not found
    jsonResponse([
        'success' => false,
        'message' => 'Endpoint not found',
        'uri' => $uri,
        'method' => $method
    ], 404);
    
} catch (Exception $e) {
    jsonResponse([
        'success' => false,
        'message' => $config['debug'] ? $e->getMessage() : 'Internal server error',
        'trace' => $config['debug'] ? $e->getTraceAsString() : null
    ], 500);
}
