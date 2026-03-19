<?php

/**
 * Application Configuration
 * 
 * File này chứa tất cả cấu hình chung của ứng dụng
 * Được load trong bootstrap.php và sử dụng xuyên suốt app
 */

return [
    // Tên ứng dụng - hiển thị trong response, logs
    'name' => 'Spicy Noodle Management System',
    'version' => '1.0.0',
    
    // Môi trường: development (dev) hoặc production (live)
    // Đọc từ biến môi trường APP_ENV, mặc định là development
    'env' => getenv('APP_ENV') ?: (getenv('RAILWAY_ENVIRONMENT') ? 'production' : 'development'),
    
    // Chế độ debug: true = hiện lỗi chi tiết, false = ẩn lỗi (bảo mật)
    // Tự động bật khi env = development
    'debug' => getenv('APP_DEBUG') === 'true' || getenv('APP_ENV') === 'development',
    
    // URL gốc của ứng dụng - dùng để tạo link đầy đủ
    'url' => getenv('APP_URL') ?: (getenv('RAILWAY_PUBLIC_DOMAIN') ? 'https://' . getenv('RAILWAY_PUBLIC_DOMAIN') : 'http://localhost:8000'),
    
    // Tiền tố cho tất cả API routes
    // VD: /api/products, /api/orders
    'api_prefix' => '/api',
    
    // Múi giờ - ảnh hưởng đến date(), time()
    'timezone' => 'Asia/Ho_Chi_Minh',
    
    // Ngôn ngữ mặc định
    'locale' => 'vi',
    
    // Cấu hình JWT (JSON Web Token) - dùng cho authentication
    'jwt' => [
        'secret' => getenv('JWT_SECRET') ?: 'your-secret-key-change-in-production', // Khóa bí mật để mã hóa token
        'algorithm' => 'HS256', // Thuật toán mã hóa
        'expiration' => 86400, // Token hết hạn sau 24 giờ (86400 giây)
        'refresh_expiration' => 604800, // Refresh token hết hạn sau 7 ngày
    ],
    
    // Cấu hình CORS (Cross-Origin Resource Sharing)
    // Cho phép frontend từ domain khác gọi API
    'cors' => [
        'allowed_origins' => ['*'], // Cho phép tất cả domain (production nên giới hạn)
        'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'], // HTTP methods được phép
        'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With'], // Headers được phép
        'exposed_headers' => [], // Headers mà browser được đọc
        'max_age' => 3600, // Cache preflight request trong 1 giờ
        'supports_credentials' => true, // Cho phép gửi cookies
    ],
    
    // Cấu hình phân trang
    'pagination' => [
        'default_per_page' => 20, // Mặc định 20 items/trang
        'max_per_page' => 100, // Tối đa 100 items/trang (tránh quá tải)
    ],
    
    // Cấu hình upload file (ảnh sản phẩm, avatar...)
    'upload' => [
        'max_size' => 5242880, // Kích thước tối đa: 5MB (5 * 1024 * 1024 bytes)
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp'], // Chỉ cho phép ảnh
        'path' => __DIR__ . '/../../storage/uploads/', // Thư mục lưu file
        'url' => '/storage/uploads/', // URL để truy cập file
    ],
    
    // Cấu hình logging (ghi log lỗi, hoạt động)
    'log' => [
        'enabled' => true, // Bật/tắt logging
        'path' => __DIR__ . '/../../storage/logs/', // Thư mục lưu log
        'level' => 'debug', // Mức độ: debug (chi tiết nhất), info, warning, error
        'max_files' => 30, // Giữ log 30 ngày, xóa cũ
    ],
    
    // Rate Limiting - giới hạn số request để chống spam/DDoS
    'rate_limit' => [
        'enabled' => true, // Bật/tắt rate limiting
        'max_requests' => 100, // Tối đa 100 requests
        'per_minutes' => 1, // Trong 1 phút
    ],
    
    // Cấu hình session (nếu dùng session thay vì JWT)
    'session' => [
        'lifetime' => 120, // Session tồn tại 120 phút (2 giờ)
        'expire_on_close' => false, // false = giữ session khi đóng browser
    ],
    
    // Quy tắc mật khẩu
    'password' => [
        'min_length' => 6, // Tối thiểu 6 ký tự
        'require_uppercase' => false, // Không bắt buộc chữ hoa
        'require_lowercase' => false, // Không bắt buộc chữ thường
        'require_numbers' => false, // Không bắt buộc số
        'require_special_chars' => false, // Không bắt buộc ký tự đặc biệt
    ],
    
    // Cấu hình tích điểm khách hàng
    'loyalty' => [
        'points_per_amount' => 1000, // Mỗi 1000 VND = 1 điểm
        'points_to_money_ratio' => 1000, // 1 điểm = 1000 VND khi đổi
    ],
];
