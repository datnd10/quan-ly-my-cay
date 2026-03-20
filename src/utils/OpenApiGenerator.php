<?php

/**
 * OpenAPI Generator
 * 
 * Tự động generate OpenAPI docs từ routes
 */

class OpenApiGenerator {
    
    public static function generate() {
        $openapi = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Spicy Noodle Management API',
                'version' => '1.0.0',
                'description' => 'REST API cho hệ thống quản lý nhà hàng mỳ cay'
            ],
            'servers' => [
                ['url' => 'https://seoul-spicy-production.up.railway.app/api', 'description' => 'Production'],
                ['url' => 'http://localhost:8000/api', 'description' => 'Local']
            ],
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'JWT'
                    ]
                ]
            ],
            'paths' => self::generatePaths()
        ];
        
        return $openapi;
    }
    
    private static function generatePaths() {
        // Đọc routes từ file
        $routesFile = __DIR__ . '/../routes/api.php';
        $content = file_get_contents($routesFile);
        
        $paths = [];
        
        // Parse routes (simplified)
        // Trong thực tế, bạn có thể dùng reflection hoặc parse phức tạp hơn
        
        return $paths;
    }
    
    public static function saveToFile($filename = null) {
        $filename = $filename ?: __DIR__ . '/../docs/openapi-generated.json';
        $openapi = self::generate();
        file_put_contents($filename, json_encode($openapi, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $filename;
    }
}
