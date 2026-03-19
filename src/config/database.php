<?php

/**
 * Database Configuration
 * 
 * File này cấu hình kết nối database
 * Hỗ trợ nhiều connections (MySQL, PostgreSQL...) nhưng hiện chỉ dùng MySQL
 */

// Parse Railway MYSQL_PUBLIC_URL nếu có
$mysqlConfig = [
    'host' => 'mysql',
    'port' => '3306',
    'database' => 'railway',
    'username' => 'root',
    'password' => ''
];

// Nếu có MYSQL_PUBLIC_URL, parse nó
if ($publicUrl = getenv('MYSQL_PUBLIC_URL')) {
    $parsed = parse_url($publicUrl);
    if ($parsed) {
        $mysqlConfig['host'] = $parsed['host'] ?? 'mysql';
        $mysqlConfig['port'] = $parsed['port'] ?? '3306';
        $mysqlConfig['database'] = ltrim($parsed['path'] ?? '/railway', '/');
        $mysqlConfig['username'] = $parsed['user'] ?? 'root';
        $mysqlConfig['password'] = $parsed['pass'] ?? '';
    }
} else {
    // Fallback: Đọc từng biến riêng lẻ
    $mysqlConfig['host'] = getenv('MYSQLHOST') ?: 'mysql';
    $mysqlConfig['port'] = getenv('MYSQLPORT') ?: '3306';
    $mysqlConfig['database'] = getenv('MYSQLDATABASE') ?: 'railway';
    $mysqlConfig['username'] = getenv('MYSQLUSER') ?: 'root';
    $mysqlConfig['password'] = getenv('MYSQLPASSWORD') ?: '';
}

return [
    // Connection mặc định sẽ dùng (key trong 'connections')
    'default' => 'mysql',
    
    // Danh sách các kết nối database
    'connections' => [
        'mysql' => [
            'driver'    => 'mysql',
            
            // Sử dụng config đã parse từ MYSQL_PUBLIC_URL hoặc biến riêng lẻ
            'host'      => $mysqlConfig['host'],
            'port'      => $mysqlConfig['port'],
            'database'  => $mysqlConfig['database'],
            'username'  => $mysqlConfig['username'],
            'password'  => $mysqlConfig['password'],
            
            // Cấu hình charset - quan trọng cho tiếng Việt
            'charset'   => 'utf8mb4', // Hỗ trợ emoji và ký tự đặc biệt
            'collation' => 'utf8mb4_unicode_ci', // Quy tắc so sánh chuỗi
            
            'prefix'    => '', // Tiền tố cho tên bảng (VD: 'app_' -> app_users)
            
            // Các tùy chọn PDO
            'options'   => [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Throw exception khi lỗi
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // Trả về array thay vì object
                PDO::ATTR_EMULATE_PREPARES   => false, // Dùng prepared statements thật
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci" // Set charset khi connect
            ]
        ]
    ],
    
    // Connection pool - quản lý số lượng kết nối đồng thời
    'pool' => [
        'min' => 2, // Tối thiểu 2 connections luôn mở
        'max' => 10 // Tối đa 10 connections cùng lúc
    ]
];
