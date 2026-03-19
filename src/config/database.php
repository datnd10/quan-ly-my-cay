<?php

/**
 * Database Configuration
 * 
 * Cấu hình kết nối database cho hệ thống quản lý mỳ cay
 */

return [
    'default' => 'mysql',
    
    'connections' => [
        'mysql' => [
            'driver'    => 'mysql',
            'host'      => getenv('DB_HOST') ?: 'mysql',
            'port'      => getenv('DB_PORT') ?: '3306',
            'database'  => getenv('DB_DATABASE') ?: 'spicy_noodle_db',
            'username'  => getenv('DB_USERNAME') ?: 'root',
            'password'  => getenv('DB_PASSWORD') ?: '123456',
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix'    => '',
            'options'   => [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ]
        ]
    ],
    
    // Connection pool settings
    'pool' => [
        'min' => 2,
        'max' => 10
    ]
];
