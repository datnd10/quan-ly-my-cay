<?php

/**
 * Application Constants
 * 
 * Định nghĩa các hằng số sử dụng trong hệ thống
 */

// User Roles
define('ROLE_ADMIN', 'ADMIN');
define('ROLE_STAFF', 'STAFF');
define('ROLE_CUSTOMER', 'CUSTOMER');

// User Status
define('USER_STATUS_ACTIVE', 1);
define('USER_STATUS_INACTIVE', 0);

// Product Status
define('PRODUCT_STATUS_ACTIVE', 1);
define('PRODUCT_STATUS_INACTIVE', 0);

// Order Status
define('ORDER_STATUS_PENDING', 'PENDING');
define('ORDER_STATUS_CONFIRMED', 'CONFIRMED');
define('ORDER_STATUS_PREPARING', 'PREPARING');
define('ORDER_STATUS_READY', 'READY');
define('ORDER_STATUS_SERVING', 'SERVING');
define('ORDER_STATUS_COMPLETED', 'COMPLETED');
define('ORDER_STATUS_CANCELLED', 'CANCELLED');

// Payment Status
define('PAYMENT_STATUS_PENDING', 'PENDING');
define('PAYMENT_STATUS_COMPLETED', 'COMPLETED');
define('PAYMENT_STATUS_FAILED', 'FAILED');
define('PAYMENT_STATUS_REFUNDED', 'REFUNDED');

// Payment Methods
define('PAYMENT_METHOD_CASH', 'CASH');
define('PAYMENT_METHOD_CARD', 'CARD');
define('PAYMENT_METHOD_MOMO', 'MOMO');
define('PAYMENT_METHOD_ZALOPAY', 'ZALOPAY');
define('PAYMENT_METHOD_BANKING', 'BANKING');

// Table Status
define('TABLE_STATUS_AVAILABLE', 'AVAILABLE');
define('TABLE_STATUS_OCCUPIED', 'OCCUPIED');
define('TABLE_STATUS_RESERVED', 'RESERVED');
define('TABLE_STATUS_MAINTENANCE', 'MAINTENANCE');

// Reservation Status
define('RESERVATION_STATUS_PENDING', 'PENDING');
define('RESERVATION_STATUS_CONFIRMED', 'CONFIRMED');
define('RESERVATION_STATUS_CANCELLED', 'CANCELLED');
define('RESERVATION_STATUS_COMPLETED', 'COMPLETED');
define('RESERVATION_STATUS_NO_SHOW', 'NO_SHOW');

// Voucher Discount Types
define('VOUCHER_TYPE_PERCENTAGE', 'PERCENTAGE');
define('VOUCHER_TYPE_FIXED', 'FIXED');

// Voucher Status
define('VOUCHER_STATUS_ACTIVE', 1);
define('VOUCHER_STATUS_INACTIVE', 0);

// Loyalty Transaction Types
define('LOYALTY_TYPE_EARN', 'EARN');
define('LOYALTY_TYPE_REDEEM', 'REDEEM');
define('LOYALTY_TYPE_EXPIRED', 'EXPIRED');
define('LOYALTY_TYPE_ADJUSTED', 'ADJUSTED');

// Branch Status
define('BRANCH_STATUS_ACTIVE', 1);
define('BRANCH_STATUS_INACTIVE', 0);

// HTTP Status Codes
define('HTTP_OK', 200);
define('HTTP_CREATED', 201);
define('HTTP_NO_CONTENT', 204);
define('HTTP_BAD_REQUEST', 400);
define('HTTP_UNAUTHORIZED', 401);
define('HTTP_FORBIDDEN', 403);
define('HTTP_NOT_FOUND', 404);
define('HTTP_UNPROCESSABLE_ENTITY', 422);
define('HTTP_INTERNAL_SERVER_ERROR', 500);

// Validation Messages
define('MSG_REQUIRED', 'Trường này là bắt buộc');
define('MSG_INVALID_EMAIL', 'Email không hợp lệ');
define('MSG_INVALID_PHONE', 'Số điện thoại không hợp lệ');
define('MSG_MIN_LENGTH', 'Độ dài tối thiểu là %d ký tự');
define('MSG_MAX_LENGTH', 'Độ dài tối đa là %d ký tự');
define('MSG_UNIQUE', 'Giá trị này đã tồn tại');
define('MSG_NOT_FOUND', 'Không tìm thấy dữ liệu');
define('MSG_UNAUTHORIZED', 'Bạn không có quyền truy cập');
define('MSG_INVALID_CREDENTIALS', 'Tên đăng nhập hoặc mật khẩu không đúng');

// Success Messages
define('MSG_SUCCESS', 'Thao tác thành công');
define('MSG_CREATED', 'Tạo mới thành công');
define('MSG_UPDATED', 'Cập nhật thành công');
define('MSG_DELETED', 'Xóa thành công');

// Error Messages
define('MSG_ERROR', 'Có lỗi xảy ra');
define('MSG_VALIDATION_ERROR', 'Dữ liệu không hợp lệ');
define('MSG_DATABASE_ERROR', 'Lỗi cơ sở dữ liệu');

// Date Formats
define('DATE_FORMAT', 'Y-m-d');
define('DATETIME_FORMAT', 'Y-m-d H:i:s');
define('TIME_FORMAT', 'H:i:s');

// Regex Patterns
define('REGEX_PHONE', '/^(0|\+84)[0-9]{9,10}$/');
define('REGEX_EMAIL', '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/');
define('REGEX_USERNAME', '/^[a-zA-Z0-9_]{3,50}$/');
