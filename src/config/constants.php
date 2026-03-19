<?php

/**
 * Application Constants
 * 
 * File này định nghĩa các hằng số (constants) dùng trong toàn bộ hệ thống
 * Giúp code dễ đọc và tránh typo khi dùng chuỗi trực tiếp
 * 
 * VD: Thay vì dùng 'ADMIN', ta dùng ROLE_ADMIN
 */

// ============================================
// USER ROLES - Vai trò người dùng
// ============================================
define('ROLE_ADMIN', 'ADMIN');      // Quản trị viên - full quyền
define('ROLE_STAFF', 'STAFF');      // Nhân viên - quản lý đơn hàng, bàn
define('ROLE_CUSTOMER', 'CUSTOMER'); // Khách hàng - đặt món, xem lịch sử

// ============================================
// USER STATUS - Trạng thái tài khoản
// ============================================
define('USER_STATUS_ACTIVE', 1);    // Tài khoản hoạt động
define('USER_STATUS_INACTIVE', 0);  // Tài khoản bị khóa/vô hiệu hóa

// ============================================
// PRODUCT STATUS - Trạng thái sản phẩm
// ============================================
define('PRODUCT_STATUS_ACTIVE', 1);   // Sản phẩm đang bán
define('PRODUCT_STATUS_INACTIVE', 0); // Sản phẩm ngừng bán (hết hàng, tạm ngưng)

// ============================================
// ORDER STATUS - Trạng thái đơn hàng
// ============================================
define('ORDER_STATUS_PENDING', 'PENDING');       // Mới tạo, chưa xác nhận
define('ORDER_STATUS_CONFIRMED', 'CONFIRMED');   // Đã xác nhận
define('ORDER_STATUS_PREPARING', 'PREPARING');   // Đang nấu
define('ORDER_STATUS_READY', 'READY');           // Món đã sẵn sàng
define('ORDER_STATUS_SERVING', 'SERVING');       // Đang phục vụ
define('ORDER_STATUS_COMPLETED', 'COMPLETED');   // Hoàn thành
define('ORDER_STATUS_CANCELLED', 'CANCELLED');   // Đã hủy

// ============================================
// PAYMENT STATUS - Trạng thái thanh toán
// ============================================
define('PAYMENT_STATUS_PENDING', 'PENDING');       // Chưa thanh toán
define('PAYMENT_STATUS_COMPLETED', 'COMPLETED');   // Đã thanh toán
define('PAYMENT_STATUS_FAILED', 'FAILED');         // Thanh toán thất bại
define('PAYMENT_STATUS_REFUNDED', 'REFUNDED');     // Đã hoàn tiền

// ============================================
// PAYMENT METHODS - Phương thức thanh toán
// ============================================
define('PAYMENT_METHOD_CASH', 'CASH');         // Tiền mặt
define('PAYMENT_METHOD_CARD', 'CARD');         // Thẻ tín dụng/ghi nợ
define('PAYMENT_METHOD_MOMO', 'MOMO');         // Ví MoMo
define('PAYMENT_METHOD_ZALOPAY', 'ZALOPAY');   // ZaloPay
define('PAYMENT_METHOD_BANKING', 'BANKING');   // Chuyển khoản ngân hàng

// ============================================
// TABLE STATUS - Trạng thái bàn
// ============================================
define('TABLE_STATUS_AVAILABLE', 'AVAILABLE');     // Bàn trống
define('TABLE_STATUS_OCCUPIED', 'OCCUPIED');       // Đang có khách
define('TABLE_STATUS_RESERVED', 'RESERVED');       // Đã đặt trước
define('TABLE_STATUS_MAINTENANCE', 'MAINTENANCE'); // Đang bảo trì

// ============================================
// RESERVATION STATUS - Trạng thái đặt bàn
// ============================================
define('RESERVATION_STATUS_PENDING', 'PENDING');       // Chờ xác nhận
define('RESERVATION_STATUS_CONFIRMED', 'CONFIRMED');   // Đã xác nhận
define('RESERVATION_STATUS_CANCELLED', 'CANCELLED');   // Đã hủy
define('RESERVATION_STATUS_COMPLETED', 'COMPLETED');   // Đã đến và hoàn thành
define('RESERVATION_STATUS_NO_SHOW', 'NO_SHOW');       // Không đến (no-show)

// ============================================
// VOUCHER - Loại và trạng thái voucher
// ============================================
define('VOUCHER_TYPE_PERCENTAGE', 'PERCENTAGE'); // Giảm theo % (VD: 10%)
define('VOUCHER_TYPE_FIXED', 'FIXED');           // Giảm cố định (VD: 50.000đ)

define('VOUCHER_STATUS_ACTIVE', 1);   // Voucher đang hoạt động
define('VOUCHER_STATUS_INACTIVE', 0); // Voucher hết hạn/vô hiệu

// ============================================
// LOYALTY - Loại giao dịch điểm thưởng
// ============================================
define('LOYALTY_TYPE_EARN', 'EARN');         // Tích điểm (mua hàng)
define('LOYALTY_TYPE_REDEEM', 'REDEEM');     // Đổi điểm (dùng điểm)
define('LOYALTY_TYPE_EXPIRED', 'EXPIRED');   // Điểm hết hạn
define('LOYALTY_TYPE_ADJUSTED', 'ADJUSTED'); // Điều chỉnh thủ công (admin)

// ============================================
// BRANCH STATUS - Trạng thái chi nhánh
// ============================================
define('BRANCH_STATUS_ACTIVE', 1);   // Chi nhánh đang hoạt động
define('BRANCH_STATUS_INACTIVE', 0); // Chi nhánh đóng cửa/tạm ngưng

// ============================================
// HTTP STATUS CODES - Mã trạng thái HTTP
// ============================================
define('HTTP_OK', 200);                      // Thành công
define('HTTP_CREATED', 201);                 // Tạo mới thành công
define('HTTP_NO_CONTENT', 204);              // Thành công nhưng không trả data
define('HTTP_BAD_REQUEST', 400);             // Request sai format
define('HTTP_UNAUTHORIZED', 401);            // Chưa đăng nhập
define('HTTP_FORBIDDEN', 403);               // Không có quyền
define('HTTP_NOT_FOUND', 404);               // Không tìm thấy
define('HTTP_UNPROCESSABLE_ENTITY', 422);    // Validation lỗi
define('HTTP_INTERNAL_SERVER_ERROR', 500);   // Lỗi server

// ============================================
// VALIDATION MESSAGES - Thông báo lỗi validation
// ============================================
define('MSG_REQUIRED', 'Trường này là bắt buộc');
define('MSG_INVALID_EMAIL', 'Email không hợp lệ');
define('MSG_INVALID_PHONE', 'Số điện thoại không hợp lệ');
define('MSG_MIN_LENGTH', 'Độ dài tối thiểu là %d ký tự'); // Dùng sprintf(MSG_MIN_LENGTH, 6)
define('MSG_MAX_LENGTH', 'Độ dài tối đa là %d ký tự');
define('MSG_UNIQUE', 'Giá trị này đã tồn tại');
define('MSG_NOT_FOUND', 'Không tìm thấy dữ liệu');
define('MSG_UNAUTHORIZED', 'Bạn không có quyền truy cập');
define('MSG_INVALID_CREDENTIALS', 'Tên đăng nhập hoặc mật khẩu không đúng');

// ============================================
// SUCCESS MESSAGES - Thông báo thành công
// ============================================
define('MSG_SUCCESS', 'Thao tác thành công');
define('MSG_CREATED', 'Tạo mới thành công');
define('MSG_UPDATED', 'Cập nhật thành công');
define('MSG_DELETED', 'Xóa thành công');

// ============================================
// ERROR MESSAGES - Thông báo lỗi
// ============================================
define('MSG_ERROR', 'Có lỗi xảy ra');
define('MSG_VALIDATION_ERROR', 'Dữ liệu không hợp lệ');
define('MSG_DATABASE_ERROR', 'Lỗi cơ sở dữ liệu');

// ============================================
// DATE FORMATS - Định dạng ngày tháng
// ============================================
define('DATE_FORMAT', 'Y-m-d');           // 2024-01-15
define('DATETIME_FORMAT', 'Y-m-d H:i:s'); // 2024-01-15 14:30:00
define('TIME_FORMAT', 'H:i:s');           // 14:30:00

// ============================================
// REGEX PATTERNS - Biểu thức chính quy để validate
// ============================================
define('REGEX_PHONE', '/^(0|\+84)[0-9]{9,10}$/');  // 0912345678 hoặc +84912345678
define('REGEX_EMAIL', '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/');
define('REGEX_USERNAME', '/^[a-zA-Z0-9_]{3,50}$/'); // Chỉ chữ, số, gạch dưới, 3-50 ký tự
