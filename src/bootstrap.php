<?php

/**
 * Bootstrap Application
 * 
 * Khởi tạo và cấu hình ứng dụng
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

// Autoload core classes
spl_autoload_register(function ($class) {
    $paths = [
        __DIR__ . '/app/core/' . $class . '.php',
        __DIR__ . '/app/models/' . $class . '.php',
        __DIR__ . '/app/controllers/' . $class . '.php',
        __DIR__ . '/app/middlewares/' . $class . '.php',
        __DIR__ . '/app/services/' . $class . '.php',
        __DIR__ . '/app/validators/' . $class . '.php',
        __DIR__ . '/utils/' . $class . '.php',
    ];
    
    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

return [
    'config' => $config,
    'dbConfig' => $dbConfig
];
