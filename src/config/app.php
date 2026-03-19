<?php

/**
 * Application Configuration
 * 
 * Cấu hình chung cho ứng dụng
 */

return [
    // Application name
    'name' => 'Spicy Noodle Management System',
    'version' => '1.0.0',
    
    // Environment: development, production
    'env' => getenv('APP_ENV') ?: 'development',
    
    // Debug mode
    'debug' => getenv('APP_DEBUG') === 'true' || getenv('APP_ENV') === 'development',
    
    // Application URL
    'url' => getenv('APP_URL') ?: 'http://localhost:8000',
    
    // API prefix
    'api_prefix' => '/api',
    
    // Timezone
    'timezone' => 'Asia/Ho_Chi_Minh',
    
    // Locale
    'locale' => 'vi',
    
    // JWT Configuration
    'jwt' => [
        'secret' => getenv('JWT_SECRET') ?: 'your-secret-key-change-in-production',
        'algorithm' => 'HS256',
        'expiration' => 86400, // 24 hours in seconds
        'refresh_expiration' => 604800, // 7 days in seconds
    ],
    
    // CORS Configuration
    'cors' => [
        'allowed_origins' => ['*'],
        'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
        'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With'],
        'exposed_headers' => [],
        'max_age' => 3600,
        'supports_credentials' => true,
    ],
    
    // Pagination
    'pagination' => [
        'default_per_page' => 20,
        'max_per_page' => 100,
    ],
    
    // File Upload
    'upload' => [
        'max_size' => 5242880, // 5MB in bytes
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        'path' => __DIR__ . '/../../storage/uploads/',
        'url' => '/storage/uploads/',
    ],
    
    // Logging
    'log' => [
        'enabled' => true,
        'path' => __DIR__ . '/../../storage/logs/',
        'level' => 'debug', // debug, info, warning, error
        'max_files' => 30,
    ],
    
    // Rate Limiting
    'rate_limit' => [
        'enabled' => true,
        'max_requests' => 100,
        'per_minutes' => 1,
    ],
    
    // Session
    'session' => [
        'lifetime' => 120, // minutes
        'expire_on_close' => false,
    ],
    
    // Password
    'password' => [
        'min_length' => 6,
        'require_uppercase' => false,
        'require_lowercase' => false,
        'require_numbers' => false,
        'require_special_chars' => false,
    ],
    
    // Loyalty Points
    'loyalty' => [
        'points_per_amount' => 1000, // 1 point per 1000 VND
        'points_to_money_ratio' => 1000, // 1 point = 1000 VND
    ],
];
