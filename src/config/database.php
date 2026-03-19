<?php

/**
 * Database Configuration
 * 
 * File này cấu hình kết nối database
 * Hỗ trợ nhiều connections (MySQL, PostgreSQL...) nhưng hiện chỉ dùng MySQL
 */

return [
    // Connection mặc định sẽ dùng (key trong 'connections')
    'default' => 'mysql',
    
    // Danh sách các kết nối database
    'connections' => [
        'mysql' => [
            'driver'    => 'mysql', // Loại database
            
            // Thông tin kết nối - đọc từ biến môi trường hoặc dùng giá trị mặc định
            'host'      => getenv('DB_HOST') ?: (getenv('MYSQLHOST') ?: 'mysql'), // Railway dùng MYSQLHOST
            'port'      => getenv('DB_PORT') ?: (getenv('MYSQLPORT') ?: '3306'), // Railway dùng MYSQLPORT
            'database'  => getenv('DB_DATABASE') ?: (getenv('MYSQLDATABASE') ?: 'spicy_noodle_db'), // Railway dùng MYSQLDATABASE
            'username'  => getenv('DB_USERNAME') ?: (getenv('MYSQLUSER') ?: 'root'), // Railway dùng MYSQLUSER
            'password'  => getenv('DB_PASSWORD') ?: (getenv('MYSQLPASSWORD') ?: '123456'), // Railway dùng MYSQLPASSWORD
            
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
